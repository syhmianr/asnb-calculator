<?php

namespace App\Services;

use App\Models\AsnbFund;
use App\Models\AsnbRate;
use Illuminate\Support\Collection;

/**
 * Pure calculation engine for ASNB fixed-price fund distributions.
 *
 * Balances are handled internally in sen (integers) to avoid floating point
 * drift; distributions are rounded to the nearest sen only at the end.
 *
 * Distribution = rate × (sum of 12 monthly eligible balances) ÷ 12
 *
 * The "eligible balance" for a month depends on the calculation method and
 * the transaction timing assumption:
 *
 *  minimum_monthly_balance (ASNB's published basis for ASB)
 *    beginning: transactions happen on day 1 → the whole month sits at the closing balance
 *    end:       deposits land too late to count; withdrawals still reduce the month's minimum
 *               → opening − withdrawal
 *
 *  average_monthly_balance
 *    beginning: closing balance (held for the whole month)
 *    end:       opening balance (transactions only on the last day)
 *
 *  custom (mid-month / pro-rata)
 *    (opening + closing) ÷ 2, regardless of timing
 */
class AsnbCalculatorService
{
    public const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    public const TIMING_BEGINNING = 'beginning';
    public const TIMING_END = 'end';

    /**
     * @param  array<string, array{deposit?: mixed, withdrawal?: mixed}>  $transactions  keyed by month name
     * @return array{months: array, totals: array, invalid_months: array<int, string>}
     */
    public function calculate(
        mixed $startingBalance,
        array $transactions,
        float $dividendRate,
        float $bonusRate,
        string $timing = self::TIMING_BEGINNING,
        string $method = AsnbRate::METHOD_MINIMUM,
        ?float $bonusCap = null,
    ): array {
        $balances = $this->calculateBalances($startingBalance, $transactions);
        $eligible = $this->eligibleBalances($balances, $timing, $method);
        $bonusEligible = $bonusCap === null
            ? $eligible
            : array_map(fn (int $sen) => min($sen, self::toSen($bonusCap)), $eligible);

        $months = [];
        foreach ($balances as $i => $row) {
            $months[] = $row + [
                'eligible' => self::toRm($eligible[$i]),
                'dividend' => round($this->monthlyShare($eligible[$i], $dividendRate), 2),
                'bonus' => round($this->monthlyShare($bonusEligible[$i], $bonusRate), 2),
            ];
        }

        $dividend = $this->calculateDividend($eligible, $dividendRate);
        $bonus = $this->calculateDividend($bonusEligible, $bonusRate);

        $startSen = self::toSen($startingBalance);
        $endSen = self::toSen(end($balances)['closing']);
        $averageClosing = array_sum(array_map(fn ($r) => self::toSen($r['closing']), $balances)) / 12;

        $profit = round($dividend + $bonus, 2);

        return [
            'months' => $months,
            'invalid_months' => array_values(array_map(
                fn ($r) => $r['month'],
                array_filter($balances, fn ($r) => $r['overdrawn']),
            )),
            'totals' => [
                'starting_balance' => self::toRm($startSen),
                'total_deposits' => round(array_sum(array_column($balances, 'deposit')), 2),
                'total_withdrawals' => round(array_sum(array_column($balances, 'withdrawal')), 2),
                'ending_balance' => self::toRm($endSen),
                'average_eligible_balance' => round(array_sum($eligible) / 12 / 100, 2),
                'dividend_rate' => $dividendRate,
                'bonus_rate' => $bonusRate,
                'estimated_dividend' => $dividend,
                'estimated_bonus' => $bonus,
                'total_profit' => $profit,
                'final_value' => round(self::toRm($endSen) + $profit, 2),
                // Profit relative to the average month-end balance actually held.
                'effective_return' => $averageClosing > 0 ? round($profit / ($averageClosing / 100) * 100, 3) : 0.0,
            ],
        ];
    }

    /**
     * Multi-year projection: the same monthly plan and rates repeated every year.
     * With $reinvest, each year's distribution is added to the next year's
     * starting balance (ASNB credits it as new units); otherwise it is paid out.
     *
     * @return array{years: array, totals: array}
     */
    public function project(
        mixed $startingBalance,
        array $transactions,
        float $dividendRate,
        float $bonusRate,
        int $years,
        bool $reinvest = true,
        string $timing = self::TIMING_BEGINNING,
        string $method = AsnbRate::METHOD_MINIMUM,
        ?float $bonusCap = null,
    ): array {
        $balance = self::toRm(self::toSen($startingBalance));
        $start = $balance;
        $deposits = $withdrawals = $dividend = $bonus = 0.0;
        $rows = [];

        for ($year = 1; $year <= max(1, $years); $year++) {
            $t = $this->calculate($balance, $transactions, $dividendRate, $bonusRate, $timing, $method, $bonusCap)['totals'];

            $deposits += $t['total_deposits'];
            $withdrawals += $t['total_withdrawals'];
            $dividend += $t['estimated_dividend'];
            $bonus += $t['estimated_bonus'];
            $closing = $reinvest ? $t['final_value'] : $t['ending_balance'];
            $contributed = round($start + $deposits - $withdrawals, 2);

            $rows[] = [
                'year' => $year,
                'opening' => $t['starting_balance'],
                'deposits' => $t['total_deposits'],
                'withdrawals' => $t['total_withdrawals'],
                'dividend' => $t['estimated_dividend'],
                'bonus' => $t['estimated_bonus'],
                'profit' => $t['total_profit'],
                'closing' => $closing,
                // Split of the closing balance into money put in vs. reinvested earnings.
                'contributed' => min($contributed, $closing),
                'earnings' => round(max(0, $closing - $contributed), 2),
            ];

            $balance = $closing;
        }

        $profit = round($dividend + $bonus, 2);

        return [
            'years' => $rows,
            'totals' => [
                'years' => count($rows),
                'reinvest' => $reinvest,
                'starting_balance' => $start,
                'total_deposits' => round($deposits, 2),
                'total_withdrawals' => round($withdrawals, 2),
                'estimated_dividend' => round($dividend, 2),
                'estimated_bonus' => round($bonus, 2),
                'total_profit' => $profit,
                'ending_balance' => $balance,
                // Paid-out distributions still count towards what you end up with.
                'final_value' => round($reinvest ? $balance : $balance + $profit, 2),
            ],
        ];
    }

    /**
     * Opening / closing balance for every month. Withdrawals larger than the
     * available balance (opening + deposit) are capped and the month is flagged.
     */
    public function calculateBalances(mixed $startingBalance, array $transactions): array
    {
        $rows = [];
        $opening = self::toSen($startingBalance);

        foreach (self::MONTHS as $month) {
            $deposit = self::toSen($transactions[$month]['deposit'] ?? 0);
            $requestedWithdrawal = self::toSen($transactions[$month]['withdrawal'] ?? 0);
            $available = $opening + $deposit;
            $withdrawal = min($requestedWithdrawal, $available);
            $closing = $available - $withdrawal;

            $rows[] = [
                'month' => $month,
                'opening' => self::toRm($opening),
                'deposit' => self::toRm($deposit),
                'withdrawal' => self::toRm($withdrawal),
                'closing' => self::toRm($closing),
                'available' => self::toRm($available),
                'overdrawn' => $requestedWithdrawal > $available,
            ];

            $opening = $closing;
        }

        return $rows;
    }

    /**
     * @return array<int, int> eligible balance per month, in sen
     */
    public function eligibleBalances(array $balances, string $timing, string $method): array
    {
        return array_map(function (array $row) use ($timing, $method) {
            $opening = self::toSen($row['opening']);
            $closing = self::toSen($row['closing']);
            $withdrawal = self::toSen($row['withdrawal']);

            return match ($method) {
                AsnbRate::METHOD_AVERAGE => $timing === self::TIMING_END ? $opening : $closing,
                AsnbRate::METHOD_CUSTOM => intdiv($opening + $closing, 2),
                default => $timing === self::TIMING_END ? max(0, $opening - $withdrawal) : $closing,
            };
        }, $balances);
    }

    /**
     * Annual distribution in RM for a set of 12 eligible balances (sen) at a % rate.
     */
    public function calculateDividend(array $eligibleBalancesSen, float $rate): float
    {
        return round(array_sum($eligibleBalancesSen) * $rate / 100 / 12 / 100, 2);
    }

    private function monthlyShare(int $eligibleSen, float $rate): float
    {
        return $eligibleSen * $rate / 100 / 12 / 100;
    }

    /**
     * Rate options for the dropdowns. Official rate for the year first, then
     * other declared years for the fund, then a standard ladder.
     *
     * @return array{dividend: array, bonus: array, default: ?AsnbRate, is_official: bool}
     */
    public function rateOptions(?AsnbFund $fund, int $year): array
    {
        $rates = $fund
            ? $fund->rates()->active()->orderByDesc('financial_year')->get()
            : new Collection;

        $official = $rates->firstWhere('financial_year', $year);
        // For an undeclared year, default to the latest declared rate before it.
        $default = $official ?? $rates->first(fn (AsnbRate $r) => $r->financial_year < $year) ?? $rates->first();

        $build = function (string $field, array $ladder) use ($rates, $official, $default) {
            $options = [];
            if ($default) {
                $label = $official ? "FY{$default->financial_year} official" : "FY{$default->financial_year} latest";
                $options[self::rateKey($default->$field)] = $label;
            }
            foreach ($rates as $rate) {
                $options[self::rateKey($rate->$field)] ??= "FY{$rate->financial_year}";
            }
            foreach ($ladder as $value) {
                $options[self::rateKey($value)] ??= null;
            }

            $keys = array_keys($options);
            usort($keys, fn ($a, $b) => (float) $b <=> (float) $a);

            return array_map(fn ($key) => [
                'value' => (string) $key,
                'label' => number_format((float) $key, 2) . '%'
                    . ($options[$key] ? ' (' . $options[$key] . ')' : ''),
            ], $keys);
        };

        return [
            'dividend' => $build('dividend_rate', [6.00, 5.75, 5.50, 5.25, 5.00, 4.75, 4.50, 4.25, 4.00, 3.50]),
            'bonus' => $build('bonus_rate', [1.00, 0.75, 0.50, 0.25, 0.10, 0.00]),
            'default' => $default,
            'is_official' => (bool) $official,
        ];
    }

    public static function rateKey(mixed $rate): string
    {
        return number_format((float) $rate, 2, '.', '');
    }

    public static function toSen(mixed $amount): int
    {
        if (! is_numeric($amount)) {
            return 0;
        }

        return max(0, (int) round((float) $amount * 100));
    }

    private static function toRm(int $sen): float
    {
        return round($sen / 100, 2);
    }
}
