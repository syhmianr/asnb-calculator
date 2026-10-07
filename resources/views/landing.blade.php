@php
    $latest = $rates->first();
    $maxTotal = $rates->max(fn ($r) => $r->dividend_rate + $r->bonus_rate) ?: 1;
    $example = 10000;
@endphp

<x-layouts.app title="ASNB Planner — Estimate your dividend & bonus">
<div class="mx-auto max-w-6xl px-4 pb-16 pt-6 sm:px-6 lg:px-8">

    {{-- Nav --}}
    <nav class="flex items-center justify-between gap-4">
        {{-- <a href="{{ route('home') }}" class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-accent">
            <span class="inline-block size-1.5 rounded-full bg-accent"></span> ASNB Planner
        </a> --}}
        {{-- <div class="flex items-center gap-2">
            <div x-data="themeSwitcher" class="segmented flex gap-0.5" role="radiogroup" aria-label="Colour theme">
                @foreach ([
                    'light' => ['Light', 'M12 3v1.5M12 19.5V21M4.6 4.6l1.1 1.1M18.3 18.3l1.1 1.1M3 12h1.5M19.5 12H21M4.6 19.4l1.1-1.1M18.3 5.7l1.1-1.1M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z'],
                    'dark' => ['Dark', 'M20.4 14.5A8.5 8.5 0 0 1 9.5 3.6a8.5 8.5 0 1 0 10.9 10.9Z'],
                    'system' => ['System', 'M4 5.5h16v10H4zM9 19.5h6M12 15.5v4'],
                ] as $mode => [$label, $icon])
                    <button type="button" role="radio" title="{{ $label }} theme" class="segment flex items-center px-2"
                            :class="mode === '{{ $mode }}' && 'segment-on'" :aria-checked="mode === '{{ $mode }}'"
                            x-on:click="set('{{ $mode }}')">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        <span class="sr-only">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            <a href="{{ route('calculator') }}" class="btn btn-primary hidden sm:inline-flex">Open calculator</a>
        </div> --}}
    </nav>

    {{-- Hero --}}
    <section class="grid min-h-[calc(100svh-4rem)] content-center items-center gap-10 py-12 sm:py-16 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
        <div>
            <h1 class="font-display text-4xl font-medium leading-[1.08] tracking-tight text-fg sm:text-5xl lg:text-6xl">
                Know what your ASNB savings will earn — before the dividend is announced.
            </h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-muted sm:text-lg">
                Plan every deposit and withdrawal month by month and see your estimated dividend, bonus and year-end balance update as you type. Free, no sign-up.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('calculator') }}" class="btn btn-primary px-5 py-3 text-base">
                    Start calculating
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="#rates" class="btn btn-ghost px-5 py-3 text-base">See past rates</a>
            </div>
        </div>

        {{-- Example result card --}}
        @if ($latest)
            @php
                $dividend = $example * $latest->dividend_rate / 100;
                $bonus = $example * $latest->bonus_rate / 100;
            @endphp
            <div class="card p-6 sm:p-7">
                <div class="flex items-center justify-between">
                    <span class="label">Example · {{ $latest->fund->name }} FY{{ $latest->financial_year }}</span>
                    <span class="rounded-full bg-pos/10 px-2.5 py-0.5 text-xs font-medium text-pos ring-1 ring-pos/25">{{ pct($latest->dividend_rate + $latest->bonus_rate) }}</span>
                </div>
                <div class="mt-5 text-sm text-muted">{{ rm($example, 0) }} held all year earns</div>
                <div class="mt-1 font-display text-5xl font-medium tabular-nums text-fg">{{ rm($dividend + $bonus) }}</div>
                <dl class="inset mt-6 grid grid-cols-2 divide-x divide-line text-sm">
                    <div class="p-4">
                        <dt class="text-xs text-subtle">Dividend · {{ pct($latest->dividend_rate) }}</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-pos">{{ rm($dividend) }}</dd>
                    </div>
                    <div class="p-4">
                        <dt class="text-xs text-subtle">Bonus · {{ pct($latest->bonus_rate) }}</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-pos/80">{{ rm($bonus) }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs leading-relaxed text-subtle">Add monthly deposits and withdrawals in the calculator to see your own figure.</p>
            </div>
        @endif
    </section>

    {{-- Features --}}
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Month-by-month planning', 'Enter deposits and withdrawals for each month. Balances are counted the way ASNB does, using the minimum monthly balance.', 'M4 6h16M4 12h16M4 18h10'],
            ['Official declared rates', 'Past ASB dividend and bonus rates are built in. You can also enter your own rate to estimate a future year.', 'M9 12l2 2 4-4M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z'],
            ['What-if scenarios', 'Try an extra deposit each month and compare it with your real plan, which stays as it is.', 'M12 3v18M3 12h18'],
            ['Multi-year projection', 'Look up to 30 years ahead, with or without reinvesting each year\'s earnings.', 'M4 19l5-6 4 3 7-9'],
        ] as [$title, $body, $icon])
            <div class="card p-5">
                <span class="grid size-9 place-items-center rounded-xl bg-accent/10 text-accent ring-1 ring-accent/25">
                    <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                </span>
                <h2 class="mt-4 font-medium text-fg">{{ $title }}</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $body }}</p>
            </div>
        @endforeach
    </section>

    {{-- How it works + rate history --}}
    <section class="mt-16 grid gap-6 lg:grid-cols-2">
        <div class="card p-6 sm:p-7">
            <h2 class="font-display text-2xl font-medium text-fg">How it works</h2>
            <ol class="mt-6 space-y-5">
                @foreach ([
                    ['Your savings', 'Pick your fund and financial year, and enter your balance on 1 January.'],
                    ['Check the rates', 'Declared rates are filled in for you. Change them to test a different outcome.'],
                    ['Add your months', 'Enter what you plan to deposit or withdraw each month, then share the result with a link.'],
                ] as $i => [$title, $body])
                    <li class="step-head">
                        <span class="step">{{ $i + 1 }}</span>
                        <div>
                            <div class="font-medium leading-7 text-fg">{{ $title }}</div>
                            <p class="text-sm leading-relaxed text-muted">{{ $body }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div id="rates" class="card scroll-mt-6 p-6 sm:p-7">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-display text-2xl font-medium text-fg">ASB declared rates</h2>
                <span class="text-xs text-subtle">dividend + bonus</span>
            </div>
            @if ($rates->isEmpty())
                <p class="mt-6 text-sm text-muted">No rates have been loaded yet.</p>
            @else
                <ul class="mt-6 space-y-2.5">
                    @foreach ($rates as $rate)
                        @php $total = $rate->dividend_rate + $rate->bonus_rate; @endphp
                        <li class="grid grid-cols-[3.25rem_1fr_3.5rem] items-center gap-3 text-sm" title="{{ $rate->notes }}">
                            <span class="tabular-nums text-muted">{{ $rate->financial_year }}</span>
                            <span class="flex h-2.5 overflow-hidden rounded-full bg-surface-2 ring-1 ring-line">
                                <span class="bg-pos" style="width: {{ $rate->dividend_rate / $maxTotal * 100 }}%"></span>
                                <span class="bg-pos/45" style="width: {{ $rate->bonus_rate / $maxTotal * 100 }}%"></span>
                            </span>
                            <span class="text-right font-semibold tabular-nums text-fg">{{ pct($total) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-5 flex gap-4 text-xs text-subtle">
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-pos"></span> Dividend</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-pos/45"></span> Bonus</span>
                </div>
            @endif
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="card mt-16 flex flex-col items-start gap-5 p-7 sm:flex-row sm:items-center sm:justify-between sm:p-9">
        <div>
            <h2 class="font-display text-2xl font-medium text-fg sm:text-3xl">See your own estimate</h2>
            <p class="mt-1.5 text-sm text-muted">It takes about a minute.</p>
        </div>
        <a href="{{ route('calculator') }}" class="btn btn-primary px-5 py-3 text-base">Open the calculator</a>
    </section>

    <footer class="mt-12 border-t border-line pt-6 text-xs leading-relaxed text-subtle">
        <strong class="font-medium text-muted">Disclaimer.</strong>
        This calculator provides an estimate for planning and educational purposes only. Actual ASNB distributions may differ depending on the fund, official distribution rates, transaction timing, eligibility requirements and ASNB's actual calculation methodology. This calculator does not guarantee future returns and is not financial advice. Not affiliated with ASNB or PNB.
    </footer>
</div>

@livewireScripts
</x-layouts.app>
