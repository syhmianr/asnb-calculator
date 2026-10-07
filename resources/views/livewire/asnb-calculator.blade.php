@php
    $t = $results['totals'];
    $rows = $results['months'];
    $fund = $this->funds->firstWhere('id', (int) $fundId);
    $opts = $this->rateOptions;
    $hasErrors = $errors->any();
    $live = 'wire:model.live.debounce.400ms';
@endphp

<div class="mx-auto max-w-7xl px-4 pb-28 pt-10 lg:pb-16 sm:px-6 lg:px-8" x-data="{ selected: 'January' }">

    {{-- Header --}}
    <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            {{-- <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-accent">
                <span class="inline-block size-1.5 rounded-full bg-accent"></span> ASNB Planner
            </div> --}}
            <h1 class="mt-3 font-display text-3xl font-medium tracking-tight text-fg sm:text-4xl">Dividend &amp; Profit Calculator</h1>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-muted">Three quick steps: tell us your balance, check the rates, then add what you plan to deposit or withdraw each month. Your estimate updates as you type.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Theme switcher --}}
            {{-- <div wire:ignore x-data="themeSwitcher" class="segmented flex gap-0.5" role="radiogroup" aria-label="Colour theme">
                @foreach ([
                    'light' => ['Light', 'M12 3v1.5M12 19.5V21M4.6 4.6l1.1 1.1M18.3 18.3l1.1 1.1M3 12h1.5M19.5 12H21M4.6 19.4l1.1-1.1M18.3 5.7l1.1-1.1M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z'],
                    'dark' => ['Dark', 'M20.4 14.5A8.5 8.5 0 0 1 9.5 3.6a8.5 8.5 0 1 0 10.9 10.9Z'],
                    'system' => ['System', 'M4 5.5h16v10H4zM9 19.5h6M12 15.5v4'],
                ] as $mode => [$label, $icon])
                    <button type="button" role="radio" title="{{ $label }} theme" class="segment flex items-center gap-1.5 px-2 text-xs"
                            :class="mode === '{{ $mode }}' && 'segment-on'" :aria-checked="mode === '{{ $mode }}'"
                            x-on:click="set('{{ $mode }}')">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        <span class="sr-only lg:not-sr-only">{{ $label }}</span>
                    </button>
                @endforeach
            </div> --}}
            <button type="button" class="btn btn-ghost"
                    wire:click="resetCalculator"
                    wire:confirm="Reset the calculator? This clears your starting balance and every deposit and withdrawal.">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.6 15A7 7 0 0 0 18.4 17M18.4 9A7 7 0 0 0 5.6 7"/></svg>
                Reset
            </button>
            <a href="{{ route('home') }}" class="btn btn-primary">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M11 18l-6-6 6-6"/></svg>
                Back
            </a>
        </div>
    </header>

    @if ($shareUrl)
        <div class="card mt-5 flex flex-col gap-3 p-4 sm:flex-row sm:items-center" x-data="{ copied: false }">
            <div class="min-w-0 flex-1">
                <div class="label">Shareable link</div>
                <div class="mt-1 truncate font-mono text-sm text-accent">{{ $shareUrl }}</div>
                <p class="mt-1 text-xs text-subtle">Contains only the calculator inputs — no personal information.</p>
            </div>
            <button type="button" class="btn btn-ghost shrink-0"
                    x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 1800)">
                <span x-text="copied ? 'Copied ✓' : 'Copy link'"></span>
            </button>
        </div>
    @endif

    <div class="mt-8 grid items-start gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- Step 1: Your savings --}}
            <section class="card p-5 sm:p-7">
                <div class="step-head">
                    <span class="step">1</span>
                    <div>
                        <h2 class="step-title">Your savings</h2>
                        <p class="step-sub">Where you're starting from and how far ahead to look.</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-x-5 gap-y-5 sm:grid-cols-2">
                    <div>
                        <label for="startingBalance" class="label">Starting balance</label>
                        <div class="money mt-2">
                            <span>RM</span>
                            <input id="startingBalance" type="number" min="0" step="0.01" inputmode="decimal"
                                   class="field text-lg font-semibold @error('startingBalance') field-error @enderror" {{ $live }}="startingBalance">
                        </div>
                        @error('startingBalance') <p class="err">{{ $message }}</p> @else
                        <p class="hint">Your balance on 1 January FY{{ $financialYear }}.</p>
                        @enderror
                    </div>

                    <div>
                        <label for="fundId" class="label">ASNB fund</label>
                        <select id="fundId" class="field mt-2" wire:model.live="fundId">
                            @foreach ($this->funds as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                        <p class="hint">{{ $fund?->description }}</p>
                    </div>

                    <div>
                        <label for="financialYear" class="label">Financial year</label>
                        <select id="financialYear" class="field mt-2" wire:model.live="financialYear">
                            @foreach ($this->years as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                        <p class="hint">The year you want to estimate.</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="savingYears" class="label">Saving period</label>
                            @if ($years > 1)
                                <label class="flex cursor-pointer items-center gap-2 text-xs text-muted" title="Add each year's dividend and bonus to the next year's balance">
                                    <input type="checkbox" class="size-3.5 rounded accent-accent" wire:model.live="reinvest">
                                    Reinvest earnings
                                </label>
                            @endif
                        </div>
                        <select id="savingYears" class="field mt-2" wire:model.live="savingYears">
                            @foreach ($savingYearOptions as $n)
                                <option value="{{ $n }}">{{ $n }} {{ $n === 1 ? 'year' : 'years' }}</option>
                            @endforeach
                        </select>
                        <p class="hint">
                            @if ($years > 1)
                                FY{{ $financialYear }} – FY{{ $financialYear + $years - 1 }}, repeating your monthly plan each year.
                            @else
                                Pick more than 1 year to see a long-term projection.
                            @endif
                        </p>
                    </div>
                </div>
            </section>

            {{-- Step 2: Rates --}}
            <section class="card p-5 sm:p-7" x-data="{ advanced: false }">
                <div class="step-head">
                    <span class="step">2</span>
                    <div>
                        <h2 class="step-title">Dividend &amp; bonus rates</h2>
                        <p class="step-sub">Pre-filled with {{ $fund?->name ?? 'the fund' }}'s declared rates. Change them to test other scenarios.</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-x-5 gap-y-5 sm:grid-cols-2">
                    <div>
                        <label for="dividendRate" class="label">Dividend rate</label>
                        <select id="dividendRate" class="field mt-2" wire:model.live="dividendRate">
                            @foreach ($opts['dividend'] as $o)
                                <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                            @endforeach
                            <option value="custom">Custom…</option>
                        </select>
                        @if ($dividendRate === 'custom')
                            <div class="relative mt-2">
                                <input type="number" min="0" step="0.01" placeholder="5.10" aria-label="Custom dividend rate"
                                       class="field pr-9 text-right @error('customDividendRate') field-error @enderror" {{ $live }}="customDividendRate">
                                <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-subtle">%</span>
                            </div>
                            @error('customDividendRate') <p class="err">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="bonusRate" class="label">Bonus rate</label>
                            <label class="flex cursor-pointer items-center gap-2 text-xs text-muted">
                                <input type="checkbox" class="size-3.5 rounded accent-accent" wire:model.live="includeBonus">
                                Include bonus
                            </label>
                        </div>
                        <select id="bonusRate" class="field mt-2" wire:model.live="bonusRate" @disabled(! $includeBonus)>
                            @foreach ($opts['bonus'] as $o)
                                <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                            @endforeach
                            <option value="custom">Custom…</option>
                        </select>
                        @if ($bonusRate === 'custom' && $includeBonus)
                            <div class="relative mt-2">
                                <input type="number" min="0" step="0.01" placeholder="0.30" aria-label="Custom bonus rate"
                                       class="field pr-9 text-right @error('customBonusRate') field-error @enderror" {{ $live }}="customBonusRate">
                                <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-subtle">%</span>
                            </div>
                            @error('customBonusRate') <p class="err">{{ $message }}</p> @enderror
                        @endif
                        @unless ($includeBonus) <p class="hint">Bonus excluded — bonus rate = 0%.</p> @endunless
                    </div>
                </div>

                @unless ($opts['is_official'])
                    <p class="mt-5 flex gap-2 rounded-xl bg-warn/10 px-3.5 py-2.5 text-xs leading-relaxed text-warn ring-1 ring-warn/20">
                        <svg class="mt-0.5 size-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.5 3.3a1.7 1.7 0 0 1 3 0l6.3 11.2A1.7 1.7 0 0 1 16.3 17H3.7a1.7 1.7 0 0 1-1.5-2.5L8.5 3.3ZM10 7a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 7Zm0 7.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                        <span>
                            @if ($opts['default'])
                                No official {{ $fund?->name }} rate has been declared for FY{{ $financialYear }} yet. Using the latest declared rate (FY{{ $opts['default']->financial_year }}) — treat this as a projection.
                            @else
                                No declared rates are loaded for {{ $fund?->name }}. Choose a rate from the list or enter a custom rate.
                            @endif
                        </span>
                    </p>
                @endunless

                {{-- Advanced: how the balance is counted --}}
                <div class="inset mt-5">
                    <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left" x-on:click="advanced = !advanced" :aria-expanded="advanced">
                        <span>
                            <span class="block text-sm font-medium text-fg">How your balance is counted</span>
                            <span class="block text-xs text-subtle">{{ $methods[$calculationMethod] ?? '' }} · {{ $transactionTiming === 'end' ? 'End' : 'Beginning' }} of month</span>
                        </span>
                        <svg class="size-4 shrink-0 text-subtle transition" :class="advanced && 'rotate-180'" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m6 8 4 4 4-4"/></svg>
                    </button>
                    <div x-show="advanced" x-collapse x-cloak>
                        <div class="grid gap-x-5 gap-y-5 border-t border-line px-4 pb-4 pt-4 sm:grid-cols-2">
                            <div>
                                <label for="calculationMethod" class="label">Distribution basis</label>
                                <select id="calculationMethod" class="field mt-2" wire:model.live="calculationMethod">
                                    @foreach ($methods as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="hint">
                                    @switch($calculationMethod)
                                        @case('minimum_monthly_balance') Each month counts only its lowest balance (ASNB's basis for ASB). @break
                                        @case('average_monthly_balance') Each month counts the balance held through most of the month. @break
                                        @default Each month counts the midpoint of its opening and closing balance. Timing has no effect.
                                    @endswitch
                                </p>
                            </div>
                            <div>
                                <span class="label">Transaction timing</span>
                                <div class="segmented mt-2 grid grid-cols-2 gap-1">
                                    @foreach (['beginning' => 'Start of month', 'end' => 'End of month'] as $value => $label)
                                        <label class="cursor-pointer">
                                            <input type="radio" class="peer sr-only" value="{{ $value }}" wire:model.live="transactionTiming">
                                            <span class="segment py-1.5 peer-checked:bg-surface-3 peer-checked:text-fg peer-checked:shadow-sm peer-checked:ring-1 peer-checked:ring-line peer-focus-visible:ring-2 peer-focus-visible:ring-accent/50">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="hint">When you usually deposit or withdraw. Earlier deposits count for more months.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Step 3: Monthly transactions --}}
            @php
                $activeMonths = collect($rows)->filter(fn ($r) => $r['deposit'] > 0 || $r['withdrawal'] > 0)->count();
                $monthErrors = collect($errors->keys())->filter(fn ($k) => str_starts_with($k, 'monthlyTransactions.'))->count();
            @endphp
            {{-- On mobile the 12 month cards fold away; they open automatically if a month needs fixing. --}}
            <section class="card overflow-hidden" x-data="{ monthsOpen: false }">
                @if ($monthErrors)<span hidden x-init="monthsOpen = true"></span>@endif
                <div class="flex flex-col gap-5 border-b border-line p-5 sm:p-7">
                    <div class="step-head">
                        <span class="step">3</span>
                        <div>
                            <h2 class="step-title">Monthly deposits &amp; withdrawals</h2>
                            <p class="step-sub">Type an amount for each month, or fill every month at once below.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-x-6 gap-y-4">
                        <form class="flex min-w-64 flex-1 items-end gap-2" wire:submit="applyBulkDeposit">
                            <div class="flex-1">
                                <label for="bulkDeposit" class="label">Same deposit every month</label>
                                <div class="money mt-2">
                                    <span>RM</span>
                                    <input id="bulkDeposit" type="number" min="0" step="0.01" placeholder="500" class="field py-2" wire:model="bulkDeposit">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary h-10.5 whitespace-nowrap">Fill all months</button>
                        </form>

                        {{-- Quick add (desktop: acts on the selected month) --}}
                        <div class="hidden md:block">
                            <div class="label">Quick add to <span class="text-accent" x-text="selected"></span></div>
                            <div class="mt-2 flex gap-1.5">
                                @foreach ([100, 500, 1000] as $amt)
                                    <button type="button" class="chip h-10.5 whitespace-nowrap" x-on:click="$wire.addToDeposit(selected, {{ $amt }})">+RM{{ number_format($amt) }}</button>
                                @endforeach
                                <button type="button" class="chip h-10.5 whitespace-nowrap hover:bg-neg/10 hover:text-neg hover:ring-neg/30" x-on:click="$wire.clearMonth(selected)">Clear</button>
                            </div>
                        </div>
                    </div>
                    @error('bulkDeposit') <p class="err -mt-2">{{ $message }}</p> @enderror
                    <button type="button" class="-mt-2 self-start text-xs text-muted underline decoration-line-strong underline-offset-4 transition hover:text-accent hover:decoration-accent" wire:click="copyJanuaryToAll">
                        Or copy January's deposit to every month
                    </button>
                </div>

                {{-- Desktop table --}}
                <div class="hidden md:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-surface-2 text-left text-[11px] uppercase tracking-[0.12em] text-subtle">
                                <th class="px-6 py-3 font-medium">Month</th>
                                <th class="px-3 py-3 text-right font-medium">Opening</th>
                                <th class="px-3 py-3 text-right font-medium"><span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-dep"></span>Deposit</span></th>
                                <th class="px-3 py-3 text-right font-medium"><span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-neg"></span>Withdrawal</span></th>
                                <th class="px-6 py-3 text-right font-medium">Closing</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                @php $m = $row['month']; @endphp
                                <tr wire:key="row-{{ $m }}" class="transition"
                                    x-on:focusin="selected = '{{ $m }}'" x-on:click="selected = '{{ $m }}'"
                                    :class="selected === '{{ $m }}' ? 'bg-accent/[0.05] shadow-[inset_3px_0_0_var(--accent)]' : 'hover:bg-hover'">
                                    <td class="px-6 py-2.5 align-middle font-medium text-fg">{{ $m }}</td>
                                    <td class="px-3 py-2.5 text-right align-middle tabular-nums text-muted">{{ rm($row['opening']) }}</td>
                                    <td class="w-40 px-3 py-2">
                                        <div class="money">
                                            <span>RM</span>
                                            <input type="number" min="0" step="0.01" inputmode="decimal" aria-label="{{ $m }} deposit"
                                                   class="field py-2 @error("monthlyTransactions.$m.deposit") field-error @enderror"
                                                   {{ $live }}="monthlyTransactions.{{ $m }}.deposit">
                                        </div>
                                        @error("monthlyTransactions.$m.deposit") <p class="err">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="w-40 px-3 py-2">
                                        <div class="money">
                                            <span>RM</span>
                                            <input type="number" min="0" step="0.01" inputmode="decimal" aria-label="{{ $m }} withdrawal"
                                                   class="field py-2 {{ $row['withdrawal'] > 0 ? 'text-neg' : '' }} @error("monthlyTransactions.$m.withdrawal") field-error @enderror"
                                                   {{ $live }}="monthlyTransactions.{{ $m }}.withdrawal">
                                        </div>
                                        @error("monthlyTransactions.$m.withdrawal") <p class="err">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-6 py-2.5 text-right align-middle font-semibold tabular-nums text-fg">{{ rm($row['closing']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards (collapsible) --}}
                <button type="button" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-hover md:hidden"
                        x-on:click="monthsOpen = !monthsOpen" :aria-expanded="monthsOpen" aria-controls="month-cards">
                    <span>
                        <span class="block text-sm font-medium text-fg">Edit each month</span>
                        <span class="block text-xs {{ $monthErrors ? 'text-neg' : 'text-subtle' }}">
                            @if ($monthErrors)
                                {{ $monthErrors }} {{ $monthErrors === 1 ? 'entry needs' : 'entries need' }} fixing
                            @elseif ($activeMonths)
                                {{ $activeMonths }} of 12 months have deposits or withdrawals
                            @else
                                No deposits or withdrawals yet
                            @endif
                        </span>
                    </span>
                    <span class="btn btn-ghost shrink-0 px-3 text-xs">
                        <span x-text="monthsOpen ? 'Hide' : 'Show'">Show</span>
                        <svg class="size-4 transition" :class="monthsOpen && 'rotate-180'" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m6 8 4 4 4-4"/></svg>
                    </span>
                </button>
                <div id="month-cards" class="divide-y divide-line border-t border-line md:hidden" x-show="monthsOpen" x-collapse x-cloak>
                    @foreach ($rows as $row)
                        @php $m = $row['month']; @endphp
                        <div wire:key="card-{{ $m }}" class="p-5">
                            <div class="flex items-baseline justify-between">
                                <h3 class="font-display text-base font-medium text-fg">{{ $m }}</h3>
                                <span class="text-xs text-subtle">Opening {{ rm($row['opening']) }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <label class="label">Deposit</label>
                                    <div class="money mt-1.5">
                                        <span>RM</span>
                                        <input type="number" min="0" step="0.01" inputmode="decimal" aria-label="{{ $m }} deposit"
                                               class="field @error("monthlyTransactions.$m.deposit") field-error @enderror"
                                               {{ $live }}="monthlyTransactions.{{ $m }}.deposit">
                                    </div>
                                    @error("monthlyTransactions.$m.deposit") <p class="err">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="label">Withdrawal</label>
                                    <div class="money mt-1.5">
                                        <span>RM</span>
                                        <input type="number" min="0" step="0.01" inputmode="decimal" aria-label="{{ $m }} withdrawal"
                                               class="field {{ $row['withdrawal'] > 0 ? 'text-neg' : '' }} @error("monthlyTransactions.$m.withdrawal") field-error @enderror"
                                               {{ $live }}="monthlyTransactions.{{ $m }}.withdrawal">
                                    </div>
                                    @error("monthlyTransactions.$m.withdrawal") <p class="err">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                @foreach ([100, 500, 1000] as $amt)
                                    <button type="button" class="chip" wire:click="addToDeposit('{{ $m }}', {{ $amt }})">+RM{{ number_format($amt) }}</button>
                                @endforeach
                                <button type="button" class="chip" wire:click="clearMonth('{{ $m }}')">Clear</button>
                                <span class="ml-auto text-xs text-muted">Closing <span class="font-semibold tabular-nums text-fg">{{ rm($row['closing']) }}</span></span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Year totals --}}
                <dl class="grid grid-cols-2 gap-px border-t border-line-strong bg-line sm:grid-cols-4">
                    @foreach ([
                        ['Starting balance', $t['starting_balance'], 'text-fg', ''],
                        ['Total deposits', $t['total_deposits'], 'text-dep', '+'],
                        ['Total withdrawals', $t['total_withdrawals'], 'text-neg', '−'],
                        ['Year-end balance', $t['ending_balance'], 'text-fg', '='],
                    ] as [$label, $value, $color, $op])
                        <div class="bg-surface-2 px-5 py-4 sm:px-6">
                            <dt class="text-[11px] font-medium uppercase tracking-[0.12em] text-subtle">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold tabular-nums {{ $color }}">@if ($op)<span class="mr-1 text-subtle">{{ $op }}</span>@endif{{ rm($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        {{-- Results --}}
        <aside id="results" class="scroll-mt-6 space-y-6 lg:sticky lg:top-6">
            <section class="card overflow-hidden">
                <div class="relative bg-gradient-to-br from-accent/[0.12] via-accent/[0.04] to-transparent p-6 sm:p-7">
                    <div class="label text-accent">Your estimated profit</div>
                    <div class="mt-1 text-xs text-subtle">{{ $fund?->name }} · FY{{ $financialYear }}</div>
                    <div class="mt-3 font-display text-5xl font-medium tracking-tight text-fg tabular-nums">{{ rm($t['total_profit']) }}</div>
                    <div class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-pos/10 px-2.5 py-1 text-xs text-pos ring-1 ring-pos/20">
                        <svg class="size-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14l5-5 3 3 6-6M12 6h5v5"/></svg>
                        <span><span class="font-semibold">{{ number_format($t['effective_return'], 2) }}%</span> effective return</span>
                    </div>
                    @if ($projection)
                        <a href="#projection" class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-surface/70 px-3.5 py-2.5 ring-1 ring-line transition hover:ring-accent/40">
                            <span class="text-xs text-muted">Over {{ $years }} years</span>
                            <span class="text-sm font-semibold tabular-nums text-pos">{{ rm($projection['totals']['total_profit']) }} <span class="font-normal text-subtle">→</span></span>
                        </a>
                    @endif
                    @if ($hasErrors)
                        <p class="mt-4 rounded-lg bg-neg/10 px-3 py-2 text-xs text-neg ring-1 ring-neg/20">Some inputs need fixing. Results use capped / zeroed values.</p>
                    @endif
                </div>

                <dl class="divide-y divide-line px-6 sm:px-7">
                    <div class="flex items-start justify-between gap-3 py-4">
                        <div>
                            <dt class="text-sm text-fg-2">Dividend</dt>
                            <dd class="text-xs text-subtle">{{ pct($t['dividend_rate']) }} of your counted balance</dd>
                        </div>
                        <dd class="whitespace-nowrap text-lg font-semibold tabular-nums text-fg">{{ rm($t['estimated_dividend']) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3 py-4">
                        <div>
                            <dt class="text-sm text-fg-2">Bonus</dt>
                            <dd class="text-xs text-subtle">{{ $includeBonus ? pct($t['bonus_rate']) . ' of your counted balance' : 'Excluded' }}</dd>
                        </div>
                        <dd class="whitespace-nowrap text-lg font-semibold tabular-nums text-fg">{{ rm($t['estimated_bonus']) }}</dd>
                    </div>
                </dl>

                <div class="inset m-4 mt-0 p-4 sm:m-5 sm:mt-0">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Year-end balance</dt><dd class="tabular-nums text-fg-2">{{ rm($t['ending_balance']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">+ Dividend &amp; bonus</dt><dd class="tabular-nums text-pos">{{ rm($t['total_profit']) }}</dd></div>
                        <div class="flex justify-between border-t border-line-strong pt-2.5 text-base font-semibold text-fg"><dt>Estimated final value</dt><dd class="tabular-nums">{{ rm($t['final_value']) }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-subtle">Distributions are credited as new units after the financial year ends.</p>
                </div>
            </section>

            {{-- What-if teaser / toggle --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-medium text-fg">What if I save more?</h2>
                        <p class="text-xs text-subtle">Try different deposits without changing your plan.</p>
                    </div>
                    @if ($whatIfEnabled)
                        <button type="button" class="btn btn-ghost shrink-0 text-xs" wire:click="stopWhatIf">Close</button>
                    @else
                        <button type="button" class="btn btn-ghost shrink-0 whitespace-nowrap text-xs" wire:click="startWhatIf">Try it</button>
                    @endif
                </div>
                @if ($whatIfEnabled && $whatIfResults)
                    @php $diff = $whatIfResults['totals']['total_profit'] - $t['total_profit']; @endphp
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="inset p-3"><dt class="text-[11px] text-subtle">Current</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-fg">{{ rm($t['total_profit']) }}</dd></div>
                        <div class="inset p-3"><dt class="text-[11px] text-subtle">What-if</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-alt">{{ rm($whatIfResults['totals']['total_profit']) }}</dd></div>
                        <div class="inset p-3"><dt class="text-[11px] text-subtle">Difference</dt><dd class="mt-1 text-sm font-semibold tabular-nums {{ $diff >= 0 ? 'text-pos' : 'text-neg' }}">{{ $diff >= 0 ? '+' : '' }}{{ rm($diff) }}</dd></div>
                    </dl>
                    <a href="#what-if" class="mt-3 block text-center text-xs text-alt hover:underline">Edit what-if deposits ↓</a>
                @endif
            </section>
        </aside>
    </div>

    {{-- Multi-year projection --}}
    @if ($projection)
        @php
            $p = $projection['totals'];
            $maxClosing = max(array_column($projection['years'], 'closing')) ?: 1;
            $fy = (int) $financialYear;
        @endphp
        <section id="projection" class="card mt-6 scroll-mt-6 overflow-hidden">
            <div class="flex flex-wrap items-end justify-between gap-4 p-5 sm:p-7">
                <div>
                    <h2 class="font-display text-xl font-medium text-fg">{{ $years }}-year projection</h2>
                    <p class="mt-0.5 max-w-2xl text-xs leading-relaxed text-subtle">
                        FY{{ $fy }} – FY{{ $fy + $years - 1 }}. Your monthly plan and the {{ pct($t['dividend_rate']) }} dividend{{ $includeBonus ? ' + ' . pct($t['bonus_rate']) . ' bonus' : '' }}
                        repeat every year; distributions are {{ $p['reinvest'] ? 'reinvested at each year-end' : 'paid out, not reinvested' }}.
                        Future rates are not guaranteed.
                    </p>
                </div>
                <div class="flex items-center gap-4 text-xs text-muted">
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-dep/60"></span>Your money</span>
                    @if ($p['reinvest'])<span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-pos"></span>Reinvested earnings</span>@endif
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-2 px-5 sm:gap-3 pb-5 sm:px-7 sm:pb-7 lg:grid-cols-4">
                <div class="inset min-w-0 p-3 sm:p-4"><dt class="label">Total deposited</dt><dd class="mt-1 text-base font-semibold [overflow-wrap:anywhere] sm:text-xl tabular-nums text-dep">{{ rm($p['total_deposits']) }}</dd></div>
                <div class="inset min-w-0 p-3 sm:p-4"><dt class="label">Total withdrawn</dt><dd class="mt-1 text-base font-semibold [overflow-wrap:anywhere] sm:text-xl tabular-nums text-neg">{{ rm($p['total_withdrawals']) }}</dd></div>
                <div class="inset min-w-0 p-3 sm:p-4"><dt class="label">Total estimated profit</dt><dd class="mt-1 text-base font-semibold [overflow-wrap:anywhere] sm:text-xl tabular-nums text-pos">{{ rm($p['total_profit']) }}</dd></div>
                <div class="inset min-w-0 bg-accent/[0.06] p-3 sm:p-4 ring-accent/30">
                    <dt class="label">Estimated final value</dt>
                    <dd class="mt-1 font-display text-lg [overflow-wrap:anywhere] sm:text-2xl font-medium tabular-nums text-fg">{{ rm($p['final_value']) }}</dd>
                    @unless ($p['reinvest'])<dd class="text-xs text-subtle">incl. {{ rm($p['total_profit']) }} paid out</dd>@endunless
                </div>
            </dl>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-y border-line bg-surface-2 text-right text-[11px] uppercase tracking-[0.12em] text-subtle">
                            <th class="px-6 py-3 text-left font-medium">Year</th>
                            <th class="px-3 py-3 font-medium">Opening</th>
                            <th class="px-3 py-3 font-medium">Deposits</th>
                            <th class="px-3 py-3 font-medium">Withdrawals</th>
                            <th class="px-3 py-3 font-medium">Dividend</th>
                            <th class="px-3 py-3 font-medium">Bonus</th>
                            <th class="px-3 py-3 font-medium">Year-end</th>
                            <th class="w-48 px-6 py-3 text-left font-medium">Growth</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line text-right tabular-nums">
                        @foreach ($projection['years'] as $y)
                            <tr wire:key="py-{{ $y['year'] }}" class="hover:bg-hover">
                                <td class="px-6 py-2.5 text-left"><span class="text-fg">Year {{ $y['year'] }}</span> <span class="text-xs text-subtle">FY{{ $fy + $y['year'] - 1 }}</span></td>
                                <td class="px-3 py-2.5 text-muted">{{ rm($y['opening']) }}</td>
                                <td class="px-3 py-2.5 {{ $y['deposits'] > 0 ? 'text-dep' : 'text-faint' }}">{{ rm($y['deposits']) }}</td>
                                <td class="px-3 py-2.5 {{ $y['withdrawals'] > 0 ? 'text-neg' : 'text-faint' }}">{{ rm($y['withdrawals']) }}</td>
                                <td class="px-3 py-2.5 text-pos">{{ rm($y['dividend']) }}</td>
                                <td class="px-3 py-2.5 text-pos/80">{{ rm($y['bonus']) }}</td>
                                <td class="px-3 py-2.5 font-semibold text-fg">{{ rm($y['closing']) }}</td>
                                <td class="px-6 py-2.5">
                                    <div class="flex h-2 overflow-hidden rounded-full bg-surface-2" title="{{ rm($y['contributed']) }} your money · {{ rm($y['earnings']) }} earnings">
                                        <div class="bg-dep/60" style="width: {{ round($y['contributed'] / $maxClosing * 100, 2) }}%"></div>
                                        <div class="bg-pos" style="width: {{ round($y['earnings'] / $maxClosing * 100, 2) }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-line-strong bg-surface-2 text-right font-semibold tabular-nums">
                            <td class="px-6 py-3 text-left text-fg">Total</td>
                            <td class="px-3 py-3 text-muted">{{ rm($p['starting_balance']) }}</td>
                            <td class="px-3 py-3 text-dep">{{ rm($p['total_deposits']) }}</td>
                            <td class="px-3 py-3 text-neg">{{ rm($p['total_withdrawals']) }}</td>
                            <td class="px-3 py-3 text-pos">{{ rm($p['estimated_dividend']) }}</td>
                            <td class="px-3 py-3 text-pos/80">{{ rm($p['estimated_bonus']) }}</td>
                            <td class="px-3 py-3 text-fg">{{ rm($p['ending_balance']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif

    {{-- Chart --}}
    <section class="card mt-6 p-5 sm:p-7" wire:ignore x-data="balanceChart(@js($this->chartData()))"
             x-on:asnb-chart-updated.window="update($event.detail.data)"
             x-on:theme-changed.window="repaint()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-medium text-fg">Balance growth</h2>
                <p class="mt-0.5 text-xs text-subtle">Month-end balance with monthly deposits and withdrawals.</p>
            </div>
            <div class="flex gap-1.5">
                @foreach (['balance' => ['Balance', 'bg-pos'], 'deposits' => ['Deposits', 'bg-dep'], 'withdrawals' => ['Withdrawals', 'bg-neg']] as $key => [$label, $dot])
                    <button type="button" class="chip flex items-center gap-1.5" x-on:click="toggle('{{ $key }}')"
                            :class="show.{{ $key }} ? '' : 'opacity-40 line-through'" :aria-pressed="show.{{ $key }}">
                        <span class="size-2 rounded-full {{ $dot }}"></span>{{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
        <div class="relative mt-5 h-72 sm:h-80"><canvas x-ref="canvas"></canvas></div>
    </section>

    {{-- What-if editor --}}
    @if ($whatIfEnabled)
        <section id="what-if" class="card mt-6 p-5 ring-alt/25 sm:p-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="font-display text-xl font-medium text-fg">What-if deposits</h2>
                    <p class="mt-0.5 text-xs text-subtle">Withdrawals, rates and timing stay the same. Your real plan is untouched.</p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="whatIfIncrement" class="label">Add to every month</label>
                        <div class="money mt-2 w-32"><span>RM</span><input id="whatIfIncrement" type="number" step="50" class="field py-2" wire:model="whatIfIncrement"></div>
                    </div>
                    <button type="button" class="btn btn-ghost" wire:click="addWhatIfIncrement">Apply</button>
                    <button type="button" class="btn btn-ghost" wire:click="resetWhatIf">Reset to current</button>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($months as $m)
                    <div wire:key="wi-{{ $m }}">
                        <label class="flex items-baseline justify-between gap-2 text-xs text-muted">{{ $m }}
                            <span class="truncate text-[11px] text-faint">now {{ rm($rows[$loop->index]['deposit'], 0) }}</span>
                        </label>
                        <div class="money mt-1"><span>RM</span>
                            <input type="number" min="0" step="0.01" class="field py-2 @error("whatIfDeposits.$m") field-error @enderror" {{ $live }}="whatIfDeposits.{{ $m }}">
                        </div>
                        @error("whatIfDeposits.$m") <p class="err">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            @if ($whatIfResults)
                @php $w = $whatIfResults['totals']; $diff = $w['total_profit'] - $t['total_profit']; @endphp
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="inset p-4"><div class="label">Current estimated profit</div><div class="mt-1 text-xl font-semibold tabular-nums text-fg">{{ rm($t['total_profit']) }}</div></div>
                    <div class="inset p-4"><div class="label">What-if estimated profit</div><div class="mt-1 text-xl font-semibold tabular-nums text-alt">{{ rm($w['total_profit']) }}</div><div class="text-xs text-subtle">Year-end {{ rm($w['final_value']) }}</div></div>
                    <div class="inset p-4"><div class="label">Potential difference</div><div class="mt-1 text-xl font-semibold tabular-nums {{ $diff >= 0 ? 'text-pos' : 'text-neg' }}">{{ $diff >= 0 ? '+' : '' }}{{ rm($diff) }}</div><div class="text-xs text-subtle">{{ $w['total_deposits'] - $t['total_deposits'] >= 0 ? '+' : '' }}{{ rm($w['total_deposits'] - $t['total_deposits']) }} extra deposits</div></div>
                </div>
            @endif
        </section>
    @endif

    {{-- Monthly breakdown --}}
    <section class="card mt-6 overflow-hidden" x-data="{ open: false }">
        <button type="button" class="flex w-full items-center justify-between gap-4 p-5 text-left transition hover:bg-hover sm:p-7" x-on:click="open = !open" :aria-expanded="open">
            <div>
                <h2 class="font-display text-xl font-medium text-fg">How this was calculated</h2>
                <p class="mt-0.5 text-xs text-subtle">
                    Month-by-month breakdown. Each month earns rate × counted balance ÷ 12, using
                    <span class="text-fg-2">{{ strtolower($methods[$calculationMethod] ?? '') }}</span>
                    with {{ $transactionTiming === 'end' ? 'end' : 'start' }}-of-month timing.
                </p>
            </div>
            <span class="btn btn-ghost shrink-0 text-xs">
                <span x-text="open ? 'Hide' : 'Show'">Show</span>
                <svg class="size-4 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m6 8 4 4 4-4"/></svg>
            </span>
        </button>
        <div class="overflow-x-auto" x-show="open" x-collapse x-cloak>
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-y border-line bg-surface-2 text-right text-[11px] uppercase tracking-[0.12em] text-subtle">
                        <th class="px-6 py-3 text-left font-medium">Month</th>
                        <th class="px-3 py-3 font-medium">Opening</th>
                        <th class="px-3 py-3 font-medium">Deposit</th>
                        <th class="px-3 py-3 font-medium">Withdrawal</th>
                        <th class="px-3 py-3 font-medium">Balance</th>
                        <th class="px-3 py-3 font-medium" title="The balance ASNB counts for this month's distribution">Counted</th>
                        <th class="px-3 py-3 font-medium">Dividend</th>
                        <th class="px-6 py-3 font-medium">Bonus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-right tabular-nums">
                    @foreach ($rows as $row)
                        <tr wire:key="bd-{{ $row['month'] }}" class="hover:bg-hover">
                            <td class="px-6 py-2.5 text-left text-fg-2">{{ substr($row['month'], 0, 3) }}</td>
                            <td class="px-3 py-2.5 text-muted">{{ rm($row['opening']) }}</td>
                            <td class="px-3 py-2.5 {{ $row['deposit'] > 0 ? 'text-dep' : 'text-faint' }}">{{ rm($row['deposit']) }}</td>
                            <td class="px-3 py-2.5 {{ $row['withdrawal'] > 0 ? 'text-neg' : 'text-faint' }}">{{ rm($row['withdrawal']) }}</td>
                            <td class="px-3 py-2.5 text-fg">{{ rm($row['closing']) }}</td>
                            <td class="px-3 py-2.5 text-fg-2">{{ rm($row['eligible']) }}</td>
                            <td class="px-3 py-2.5 text-pos">{{ rm($row['dividend']) }}</td>
                            <td class="px-6 py-2.5 text-pos/80">{{ rm($row['bonus']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-line-strong bg-surface-2 text-right font-semibold tabular-nums">
                        <td class="px-6 py-3 text-left text-fg">Total</td>
                        <td></td>
                        <td class="px-3 py-3 text-dep">{{ rm($t['total_deposits']) }}</td>
                        <td class="px-3 py-3 text-neg">{{ rm($t['total_withdrawals']) }}</td>
                        <td class="px-3 py-3 text-fg">{{ rm($t['ending_balance']) }}</td>
                        <td class="px-3 py-3 text-muted" title="Average counted balance">avg {{ rm($t['average_eligible_balance']) }}</td>
                        <td class="px-3 py-3 text-pos">{{ rm($t['estimated_dividend']) }}</td>
                        <td class="px-6 py-3 text-pos/80">{{ rm($t['estimated_bonus']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Disclaimer --}}
    <footer class="mt-10 border-t border-line pt-6 text-xs leading-relaxed text-subtle">
        <strong class="font-medium text-muted">Disclaimer.</strong>
        This calculator provides an estimate for planning and educational purposes only. Actual ASNB distributions may differ depending on the fund, official distribution rates, transaction timing, eligibility requirements and ASNB's actual calculation methodology. This calculator does not guarantee future returns and is not financial advice.
    </footer>
    {{-- Mobile: keep the result in view while editing months --}}
    <a href="#results" class="fixed inset-x-3 bottom-3 z-20 flex items-center justify-between gap-3 rounded-2xl bg-surface/90 px-4 py-3 shadow-[var(--shadow)] ring-1 ring-line-strong backdrop-blur lg:hidden">
        <span class="text-xs text-muted">Estimated profit <span class="text-subtle">· {{ number_format($t['effective_return'], 2) }}%</span></span>
        <span class="font-display text-xl font-medium tabular-nums text-fg">{{ rm($t['total_profit']) }} <span class="text-sm text-subtle">↑</span></span>
    </a>
</div>
