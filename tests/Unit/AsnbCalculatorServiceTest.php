<?php

namespace Tests\Unit;

use App\Models\AsnbRate;
use App\Services\AsnbCalculatorService;
use PHPUnit\Framework\TestCase;

class AsnbCalculatorServiceTest extends TestCase
{
    private AsnbCalculatorService $service;

    /** The scenario from the spec: RM20,000 start, mixed deposits and withdrawals. */
    private array $scenario = [
        'January' => ['deposit' => 500, 'withdrawal' => 0],
        'February' => ['deposit' => 500, 'withdrawal' => 0],
        'March' => ['deposit' => 1000, 'withdrawal' => 0],
        'April' => ['deposit' => 0, 'withdrawal' => 2000],
        'May' => ['deposit' => 500, 'withdrawal' => 0],
        'June' => ['deposit' => 1000, 'withdrawal' => 0],
        'July' => ['deposit' => 300, 'withdrawal' => 0],
        'August' => ['deposit' => 0, 'withdrawal' => 500],
        'September' => ['deposit' => 1000, 'withdrawal' => 0],
        'October' => ['deposit' => 500, 'withdrawal' => 0],
        'November' => ['deposit' => 700, 'withdrawal' => 0],
        'December' => ['deposit' => 1000, 'withdrawal' => 0],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AsnbCalculatorService;
    }

    public function test_spec_scenario_with_beginning_of_month_timing(): void
    {
        $result = $this->service->calculate(20000, $this->scenario, 5.25, 0.25);
        $totals = $result['totals'];

        $this->assertSame(7000.0, $totals['total_deposits']);
        $this->assertSame(2500.0, $totals['total_withdrawals']);
        $this->assertSame(24500.0, $totals['ending_balance']);
        // Sum of closing balances = 261,700 → 261,700 × 5.25% ÷ 12
        $this->assertSame(1144.94, $totals['estimated_dividend']);
        $this->assertSame(54.52, $totals['estimated_bonus']);
        $this->assertSame(1199.46, $totals['total_profit']);
        $this->assertSame(25699.46, $totals['final_value']);
        $this->assertSame(20000.0, $result['months'][3]['closing']);
        $this->assertSame([], $result['invalid_months']);
    }

    public function test_end_of_month_timing_delays_deposits_but_not_withdrawals(): void
    {
        $result = $this->service->calculate(20000, $this->scenario, 5.25, 0);

        $end = $this->service->calculate(20000, $this->scenario, 5.25, 0, AsnbCalculatorService::TIMING_END);

        // Eligible sum = 254,700 → 254,700 × 5.25% ÷ 12
        $this->assertSame(1114.31, $end['totals']['estimated_dividend']);
        $this->assertLessThan($result['totals']['estimated_dividend'], $end['totals']['estimated_dividend']);
        // April: opening 22,000, withdrawal 2,000 at month end still hits April's minimum
        $this->assertSame(20000.0, $end['months'][3]['eligible']);
        // January deposit does not count in January
        $this->assertSame(20000.0, $end['months'][0]['eligible']);
    }

    public function test_flat_balance_earns_exactly_the_rate(): void
    {
        $result = $this->service->calculate(10000, [], 5.75, 0);

        $this->assertSame(575.0, $result['totals']['estimated_dividend']);
        $this->assertSame(47.92, $result['months'][0]['dividend']);
    }

    public function test_average_and_custom_methods(): void
    {
        $tx = ['January' => ['deposit' => 1200, 'withdrawal' => 0]];

        $avgEnd = $this->service->calculate(0, $tx, 6, 0, 'end', AsnbRate::METHOD_AVERAGE);
        $custom = $this->service->calculate(0, $tx, 6, 0, 'beginning', AsnbRate::METHOD_CUSTOM);

        // Avg/end: Jan counts 0, Feb–Dec count 1,200 → 11 × 1,200 × 6% ÷ 12 = 66
        $this->assertSame(66.0, $avgEnd['totals']['estimated_dividend']);
        // Mid-month: Jan counts 600, Feb–Dec 1,200 → (600 + 13,200) × 6% ÷ 12 = 69
        $this->assertSame(69.0, $custom['totals']['estimated_dividend']);
    }

    public function test_overdrawn_withdrawal_is_capped_and_flagged(): void
    {
        $result = $this->service->calculate(1000, [
            'March' => ['deposit' => 200, 'withdrawal' => 5000],
        ], 5, 0);

        $this->assertSame(['March'], $result['invalid_months']);
        $this->assertSame(0.0, $result['months'][2]['closing']);
        $this->assertSame(0.0, $result['totals']['ending_balance']);
    }

    public function test_bonus_cap_limits_bonus_eligible_balance(): void
    {
        $result = $this->service->calculate(50000, [], 5, 1, bonusCap: 30000);

        $this->assertSame(2500.0, $result['totals']['estimated_dividend']);
        $this->assertSame(300.0, $result['totals']['estimated_bonus']);
    }

    public function test_blank_and_negative_inputs_are_treated_as_zero(): void
    {
        $result = $this->service->calculate('', ['January' => ['deposit' => '', 'withdrawal' => '-50']], 5, 0);

        $this->assertSame(0.0, $result['totals']['ending_balance']);
        $this->assertSame([], $result['invalid_months']);
    }

    public function test_multi_year_projection_compounds_reinvested_distributions(): void
    {
        $empty = array_fill_keys(AsnbCalculatorService::MONTHS, ['deposit' => 0, 'withdrawal' => 0]);

        $result = $this->service->project(10000, $empty, 5.0, 0.0, years: 2);

        $this->assertSame([500.0, 525.0], array_column($result['years'], 'dividend'));
        $this->assertSame(10500.0, $result['years'][1]['opening']);
        $this->assertSame(1025.0, $result['totals']['total_profit']);
        $this->assertSame(11025.0, $result['totals']['final_value']);
    }

    public function test_multi_year_projection_without_reinvesting_pays_out(): void
    {
        $empty = array_fill_keys(AsnbCalculatorService::MONTHS, ['deposit' => 0, 'withdrawal' => 0]);

        $result = $this->service->project(10000, $empty, 5.0, 0.0, years: 2, reinvest: false);

        $this->assertSame([500.0, 500.0], array_column($result['years'], 'dividend'));
        $this->assertSame(10000.0, $result['totals']['ending_balance']);
        $this->assertSame(11000.0, $result['totals']['final_value']);
    }

    public function test_multi_year_projection_repeats_monthly_plan(): void
    {
        $result = $this->service->project(20000, $this->scenario, 5.25, 0.25, years: 3);

        $this->assertCount(3, $result['years']);
        $this->assertSame(21000.0, $result['totals']['total_deposits']);
        $this->assertSame(7500.0, $result['totals']['total_withdrawals']);
        // Year 1 matches the single-year calculation.
        $this->assertSame(1199.46, $result['years'][0]['profit']);
    }
}
