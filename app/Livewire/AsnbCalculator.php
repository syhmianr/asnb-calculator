<?php

namespace App\Livewire;

use App\Models\AsnbFund;
use App\Models\AsnbRate;
use App\Models\Calculation;
use App\Services\AsnbCalculatorService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('ASNB Dividend & Profit Calculator')]
class AsnbCalculator extends Component
{
    public $financialYear;
    public $fundId;
    public $startingBalance = 10000;

    // Dropdown value: a rate such as "5.20", or "custom".
    public $dividendRate;
    public $customDividendRate = '';
    public $bonusRate;
    public $customBonusRate = '';
    public $includeBonus = true;

    public $transactionTiming = AsnbCalculatorService::TIMING_BEGINNING;
    public $calculationMethod = AsnbRate::METHOD_MINIMUM;

    public $monthlyTransactions = [];
    public $results = [];

    // Multi-year projection
    public const SAVING_YEARS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 30];
    public $savingYears = 1;
    public $reinvest = true;
    public $projection = [];

    // Bulk helpers
    public $bulkDeposit = '';

    // What-if
    public $whatIfEnabled = false;
    public $whatIfDeposits = [];
    public $whatIfIncrement = 200;
    public $whatIfResults = [];

    #[Locked]
    public $shareUrl = null;

    #[Locked]
    public $bonusCap = null;

    public function mount(?Calculation $calculation = null): void
    {
        $this->monthlyTransactions = $this->emptyTransactions();

        if ($calculation?->exists) {
            $this->loadSharedCalculation($calculation);
        } else {
            $this->fundId = AsnbFund::active()->value('id');
            $this->financialYear = (int) now()->year;
            $this->applyDefaultRates();
        }

        $this->recalculate();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['fundId', 'financialYear'], true)) {
            $this->applyDefaultRates();
        }

        if ($property === 'dividendRate' && $this->dividendRate === 'custom' && $this->customDividendRate === '') {
            $this->customDividendRate = $this->results['totals']['dividend_rate'] ?? '';
        }
        if ($property === 'bonusRate' && $this->bonusRate === 'custom' && $this->customBonusRate === '') {
            $this->customBonusRate = $this->results['totals']['bonus_rate'] ?? '';
        }

        // Any change invalidates a previously generated share link.
        $this->shareUrl = null;

        $this->recalculate();
    }

    public function applyDefaultRates(): void
    {
        $options = $this->service()->rateOptions($this->selectedFund(), (int) $this->financialYear);
        $default = $options['default'];

        $this->dividendRate = $default ? AsnbCalculatorService::rateKey($default->dividend_rate) : '5.00';
        $this->bonusRate = $default ? AsnbCalculatorService::rateKey($default->bonus_rate) : '0.00';
        $this->calculationMethod = $default->calculation_method ?? AsnbRate::METHOD_MINIMUM;
        $this->bonusCap = $default?->bonus_cap !== null ? (float) $default->bonus_cap : null;
    }

    public function recalculate(): void
    {
        $this->resetErrorBag();

        $validator = Validator::make($this->all(), $this->rules(), [
            '*.min' => 'Must be zero or more.',
            '*.numeric' => 'Enter a valid amount.',
            'monthlyTransactions.*.*.min' => 'Must be zero or more.',
            'monthlyTransactions.*.*.numeric' => 'Enter a valid amount.',
        ]);
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
        }

        $this->results = $this->runCalculation($this->monthlyTransactions);

        foreach ($this->results['invalid_months'] as $month) {
            $this->addError("monthlyTransactions.{$month}.withdrawal", 'Withdrawal cannot exceed the available balance.');
        }

        $this->projection = $this->projectionYears() > 1
            ? $this->service()->project(
                startingBalance: $this->startingBalance,
                transactions: $this->monthlyTransactions,
                dividendRate: $this->effectiveDividendRate(),
                bonusRate: $this->effectiveBonusRate(),
                years: $this->projectionYears(),
                reinvest: (bool) $this->reinvest,
                timing: $this->transactionTiming,
                method: $this->calculationMethod,
                bonusCap: $this->bonusCap,
            )
            : [];

        if ($this->whatIfEnabled) {
            $whatIf = $this->monthlyTransactions;
            foreach (AsnbCalculatorService::MONTHS as $month) {
                $whatIf[$month]['deposit'] = $this->whatIfDeposits[$month] ?? 0;
            }
            $this->whatIfResults = $this->runCalculation($whatIf);
        }

        $this->dispatch('asnb-chart-updated', data: $this->chartData());
    }

    protected function rules(): array
    {
        return [
            'startingBalance' => ['nullable', 'numeric', 'min:0'],
            'customDividendRate' => [$this->dividendRate === 'custom' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],
            'customBonusRate' => [$this->bonusRate === 'custom' && $this->includeBonus ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],
            'monthlyTransactions.*.deposit' => ['nullable', 'numeric', 'min:0'],
            'monthlyTransactions.*.withdrawal' => ['nullable', 'numeric', 'min:0'],
            'whatIfDeposits.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function runCalculation(array $transactions): array
    {
        return $this->service()->calculate(
            startingBalance: $this->startingBalance,
            transactions: $transactions,
            dividendRate: $this->effectiveDividendRate(),
            bonusRate: $this->effectiveBonusRate(),
            timing: $this->transactionTiming,
            method: $this->calculationMethod,
            bonusCap: $this->bonusCap,
        );
    }

    public function projectionYears(): int
    {
        return in_array((int) $this->savingYears, self::SAVING_YEARS, true) ? (int) $this->savingYears : 1;
    }

    public function effectiveDividendRate(): float
    {
        $rate = $this->dividendRate === 'custom' ? $this->customDividendRate : $this->dividendRate;

        return is_numeric($rate) ? max(0, (float) $rate) : 0.0;
    }

    public function effectiveBonusRate(): float
    {
        if (! $this->includeBonus) {
            return 0.0;
        }
        $rate = $this->bonusRate === 'custom' ? $this->customBonusRate : $this->bonusRate;

        return is_numeric($rate) ? max(0, (float) $rate) : 0.0;
    }

    // ---- Monthly input helpers -------------------------------------------

    public function addToDeposit(string $month, int $amount): void
    {
        abort_unless(in_array($month, AsnbCalculatorService::MONTHS, true), 422);
        $current = is_numeric($this->monthlyTransactions[$month]['deposit']) ? (float) $this->monthlyTransactions[$month]['deposit'] : 0;
        $this->monthlyTransactions[$month]['deposit'] = round($current + $amount, 2);
        $this->updated('monthlyTransactions');
    }

    public function clearMonth(string $month): void
    {
        abort_unless(in_array($month, AsnbCalculatorService::MONTHS, true), 422);
        $this->monthlyTransactions[$month] = ['deposit' => 0, 'withdrawal' => 0];
        $this->updated('monthlyTransactions');
    }

    public function copyJanuaryToAll(): void
    {
        $this->applyDepositToAll($this->monthlyTransactions['January']['deposit'] ?? 0);
    }

    public function applyBulkDeposit(): void
    {
        $this->validateOnly('bulkDeposit', ['bulkDeposit' => ['required', 'numeric', 'min:0']]);
        $this->applyDepositToAll($this->bulkDeposit);
    }

    private function applyDepositToAll(mixed $amount): void
    {
        $amount = is_numeric($amount) ? round(max(0, (float) $amount), 2) : 0;
        foreach (AsnbCalculatorService::MONTHS as $month) {
            $this->monthlyTransactions[$month]['deposit'] = $amount;
        }
        $this->updated('monthlyTransactions');
    }

    public function resetCalculator(): void
    {
        $this->startingBalance = 0;
        $this->monthlyTransactions = $this->emptyTransactions();
        $this->bulkDeposit = '';
        $this->whatIfEnabled = false;
        $this->whatIfDeposits = [];
        $this->updated('monthlyTransactions');
    }

    // ---- What-if ------------------------------------------------------------

    public function startWhatIf(): void
    {
        $this->whatIfEnabled = true;
        $this->whatIfDeposits = collect(AsnbCalculatorService::MONTHS)
            ->mapWithKeys(fn ($m) => [$m => (float) ($this->monthlyTransactions[$m]['deposit'] ?: 0)])
            ->all();
        $this->recalculate();
    }

    public function stopWhatIf(): void
    {
        $this->whatIfEnabled = false;
        $this->whatIfResults = [];
    }

    public function addWhatIfIncrement(): void
    {
        $increment = is_numeric($this->whatIfIncrement) ? (float) $this->whatIfIncrement : 0;
        foreach (AsnbCalculatorService::MONTHS as $month) {
            $this->whatIfDeposits[$month] = round(max(0, (float) ($this->whatIfDeposits[$month] ?: 0) + $increment), 2);
        }
        $this->recalculate();
    }

    public function resetWhatIf(): void
    {
        $this->startWhatIf();
    }

    // ---- Sharing ------------------------------------------------------------

    public function share(): void
    {
        $this->recalculate();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $totals = $this->results['totals'];

        $calculation = Calculation::create([
            'asnb_fund_id' => $this->fundId,
            'financial_year' => (int) $this->financialYear,
            'starting_balance' => $totals['starting_balance'],
            'dividend_rate' => $this->effectiveDividendRate(),
            'bonus_rate' => $this->effectiveBonusRate(),
            'transaction_timing' => $this->transactionTiming,
            'calculation_data' => [
                'dividend_option' => $this->dividendRate,
                'bonus_option' => $this->bonusRate,
                'include_bonus' => (bool) $this->includeBonus,
                'calculation_method' => $this->calculationMethod,
                'bonus_cap' => $this->bonusCap,
                'saving_years' => $this->projectionYears(),
                'reinvest' => (bool) $this->reinvest,
                'transactions' => collect($this->results['months'])->mapWithKeys(fn ($m) => [
                    $m['month'] => ['deposit' => $m['deposit'], 'withdrawal' => $m['withdrawal']],
                ])->all(),
            ],
            'estimated_dividend' => $totals['estimated_dividend'],
            'estimated_bonus' => $totals['estimated_bonus'],
            'estimated_profit' => $totals['total_profit'],
            'ending_balance' => $totals['ending_balance'],
        ]);

        $this->shareUrl = route('calculator.shared', $calculation);
    }

    private function loadSharedCalculation(Calculation $calculation): void
    {
        $data = $calculation->calculation_data;

        $this->fundId = $calculation->asnb_fund_id;
        $this->financialYear = $calculation->financial_year;
        $this->startingBalance = (float) $calculation->starting_balance;
        $this->transactionTiming = $calculation->transaction_timing;
        $this->includeBonus = $data['include_bonus'] ?? true;
        $this->calculationMethod = $data['calculation_method'] ?? AsnbRate::METHOD_MINIMUM;
        $this->bonusCap = $data['bonus_cap'] ?? null;
        $this->savingYears = $data['saving_years'] ?? 1;
        $this->reinvest = $data['reinvest'] ?? true;

        // Always restore the exact rates used, as custom values, unless they match a dropdown option.
        $options = $this->service()->rateOptions($this->selectedFund(), (int) $this->financialYear);
        [$this->dividendRate, $this->customDividendRate] = $this->restoreRate($calculation->dividend_rate, $options['dividend']);
        [$this->bonusRate, $this->customBonusRate] = $this->restoreRate($calculation->bonus_rate, $options['bonus']);

        foreach (AsnbCalculatorService::MONTHS as $month) {
            $this->monthlyTransactions[$month] = [
                'deposit' => (float) ($data['transactions'][$month]['deposit'] ?? 0),
                'withdrawal' => (float) ($data['transactions'][$month]['withdrawal'] ?? 0),
            ];
        }

        $this->shareUrl = route('calculator.shared', $calculation);
    }

    private function restoreRate(mixed $rate, array $options): array
    {
        $key = AsnbCalculatorService::rateKey($rate);

        return in_array($key, array_column($options, 'value'), true)
            ? [$key, '']
            : ['custom', (float) $rate];
    }

    // ---- View data ----------------------------------------------------------

    #[Computed]
    public function funds()
    {
        return AsnbFund::active()->get(['id', 'name', 'code', 'description']);
    }

    #[Computed]
    public function years(): array
    {
        $current = (int) now()->year;

        return AsnbRate::active()->distinct()->pluck('financial_year')
            ->push($current)
            ->push((int) $this->financialYear)
            ->unique()->sortDesc()->values()->all();
    }

    #[Computed]
    public function rateOptions(): array
    {
        return $this->service()->rateOptions($this->selectedFund(), (int) $this->financialYear);
    }

    public function chartData(): array
    {
        $months = $this->results['months'] ?? [];

        return [
            'labels' => array_map(fn ($m) => substr($m['month'], 0, 3), $months),
            'balance' => array_column($months, 'closing'),
            'deposits' => array_column($months, 'deposit'),
            'withdrawals' => array_column($months, 'withdrawal'),
            'whatIf' => $this->whatIfEnabled ? array_column($this->whatIfResults['months'] ?? [], 'closing') : [],
        ];
    }

    public function render()
    {
        return view('livewire.asnb-calculator', [
            'months' => AsnbCalculatorService::MONTHS,
            'methods' => AsnbRate::METHODS,
            'years' => $this->projectionYears(),
            'savingYearOptions' => self::SAVING_YEARS,
        ]);
    }

    private function selectedFund(): ?AsnbFund
    {
        return $this->fundId ? AsnbFund::find($this->fundId) : null;
    }

    private function service(): AsnbCalculatorService
    {
        return app(AsnbCalculatorService::class);
    }

    private function emptyTransactions(): array
    {
        return collect(AsnbCalculatorService::MONTHS)
            ->mapWithKeys(fn ($m) => [$m => ['deposit' => 0, 'withdrawal' => 0]])
            ->all();
    }
}
