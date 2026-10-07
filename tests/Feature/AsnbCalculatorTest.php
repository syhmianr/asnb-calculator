<?php

namespace Tests\Feature;

use App\Livewire\AsnbCalculator;
use App\Models\Calculation;
use Database\Seeders\AsnbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AsnbCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AsnbSeeder::class);
    }

    public function test_landing_page_shows_rates_and_links_to_calculator(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('ASB declared rates')
            ->assertSee('Example · ASB FY2025', false)
            ->assertSee('5.75%')
            ->assertSee(route('calculator'));
    }

    public function test_page_renders_with_latest_declared_rates(): void
    {
        $this->get('/asnb-calculator')
            ->assertOk()
            ->assertSee('Dividend &amp; Profit Calculator', false)
            ->assertSee('5.20% (FY2025 latest)');
    }

    public function test_official_rates_load_for_a_declared_year(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('financialYear', 2024)
            ->assertSet('dividendRate', '5.50')
            ->assertSet('bonusRate', '0.25');
    }

    public function test_spec_scenario_end_to_end(): void
    {
        $component = Livewire::test(AsnbCalculator::class)
            ->set('startingBalance', 20000)
            ->set('dividendRate', '5.25')
            ->set('bonusRate', '0.25');

        $tx = [500, 500, 1000, -2000, 500, 1000, 300, -500, 1000, 500, 700, 1000];
        foreach ($tx as $i => $amount) {
            $month = \App\Services\AsnbCalculatorService::MONTHS[$i];
            $component->set("monthlyTransactions.$month." . ($amount < 0 ? 'withdrawal' : 'deposit'), abs($amount));
        }

        $component->assertHasNoErrors()
            ->assertSet('results.totals.estimated_dividend', 1144.94)
            ->assertSet('results.totals.estimated_bonus', 54.52)
            ->assertSet('results.totals.total_profit', 1199.46)
            ->assertSet('results.totals.ending_balance', 24500.0);
    }

    public function test_overdraw_shows_error_beside_withdrawal(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('startingBalance', 100)
            ->set('monthlyTransactions.May.withdrawal', 500)
            ->assertHasErrors(['monthlyTransactions.May.withdrawal'])
            ->assertSee('Withdrawal cannot exceed the available balance.');
    }

    public function test_negative_starting_balance_is_rejected(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('startingBalance', -5)
            ->assertHasErrors(['startingBalance']);
    }

    public function test_custom_rate_and_bonus_toggle(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('startingBalance', 12000)
            ->set('dividendRate', 'custom')
            ->set('customDividendRate', '5.10')
            ->set('includeBonus', false)
            ->assertSet('results.totals.estimated_dividend', 612.0)
            ->assertSet('results.totals.estimated_bonus', 0.0);
    }

    public function test_bulk_helpers_and_presets(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('startingBalance', 0)
            ->set('bulkDeposit', 300)
            ->call('applyBulkDeposit')
            ->assertSet('results.totals.total_deposits', 3600.0)
            ->call('addToDeposit', 'March', 500)
            ->assertSet('monthlyTransactions.March.deposit', 800.0)
            ->call('clearMonth', 'March')
            ->assertSet('results.totals.total_deposits', 3300.0);
    }

    public function test_what_if_does_not_change_the_real_plan(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('startingBalance', 10000)
            ->set('dividendRate', '6.00')
            ->set('includeBonus', false)
            ->call('startWhatIf')
            ->set('whatIfIncrement', 1200)
            ->call('addWhatIfIncrement')
            ->assertSet('results.totals.total_profit', 600.0)
            // 1,200 × (12+11+…+1) × 6% ÷ 12 = 468 extra
            ->assertSet('whatIfResults.totals.total_profit', 1068.0)
            ->assertSet('monthlyTransactions.January.deposit', 0);
    }

    public function test_reset_clears_everything(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('monthlyTransactions.June.deposit', 999)
            ->call('resetCalculator')
            ->assertSet('startingBalance', 0)
            ->assertSet('results.totals.final_value', 0.0);
    }

    public function test_share_link_recreates_the_calculation(): void
    {
        $component = Livewire::test(AsnbCalculator::class)
            ->set('financialYear', 2023)
            ->set('startingBalance', 15000)
            ->set('dividendRate', 'custom')
            ->set('customDividendRate', '4.90')
            ->set('transactionTiming', 'end')
            ->set('monthlyTransactions.July.deposit', 2500)
            ->set('monthlyTransactions.October.withdrawal', 1000)
            ->call('share');

        $calculation = Calculation::sole();
        $component->assertSet('shareUrl', route('calculator.shared', $calculation));
        $profit = $component->get('results.totals.total_profit');

        $this->get("/asnb-calculator/{$calculation->share_token}")->assertOk();

        Livewire::test(AsnbCalculator::class, ['calculation' => $calculation])
            ->assertSet('financialYear', 2023)
            ->assertSet('transactionTiming', 'end')
            ->assertSet('dividendRate', 'custom')
            ->assertSet('customDividendRate', 4.9)
            ->assertSet('monthlyTransactions.July.deposit', 2500.0)
            ->assertSet('results.totals.total_profit', $profit);
    }

    public function test_unknown_share_token_404s(): void
    {
        $this->get('/asnb-calculator/doesnotexist')->assertNotFound();
    }

    public function test_saving_period_shows_multi_year_projection(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->assertSet('projection', [])
            ->set('savingYears', '5')
            ->assertCount('projection.years', 5)
            ->assertSee('5-year projection')
            ->set('savingYears', '99')
            ->assertSet('projection', []);
    }

    public function test_saving_period_survives_sharing(): void
    {
        Livewire::test(AsnbCalculator::class)
            ->set('savingYears', '3')
            ->set('reinvest', false)
            ->call('share');

        Livewire::test(AsnbCalculator::class, ['calculation' => Calculation::sole()])
            ->assertSet('savingYears', 3)
            ->assertSet('reinvest', false)
            ->assertSet('projection.totals.reinvest', false);
    }
}
