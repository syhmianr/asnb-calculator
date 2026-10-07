# ASNB Dividend & Profit Calculator

Laravel 12 · Livewire 3 · Tailwind CSS 4 · Alpine.js (bundled with Livewire) · Chart.js

Simulate a year of ASNB savings with **custom deposits and withdrawals for every month**, then estimate dividend, bonus, total profit and year-end value.

## Run it

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # skip if .env exists
php artisan migrate --seed
npm run build        # or: npm run dev
php artisan serve    # → http://localhost:8000/asnb-calculator
```

Tests: `php artisan test`

## How the estimate is calculated

All money logic lives in `app/Services/AsnbCalculatorService.php` (balances are handled in sen to avoid float drift).

```
Distribution = rate × (sum of 12 monthly eligible balances) ÷ 12
```

| Method (`asnb_rates.calculation_method`) | Beginning-of-month timing | End-of-month timing |
|---|---|---|
| `minimum_monthly_balance` (ASB's basis) | closing balance | opening − withdrawal (deposits count from next month, withdrawals hit this month) |
| `average_monthly_balance` | closing balance | opening balance |
| `custom` (mid-month / pro-rata) | (opening + closing) ÷ 2 | same |

The bonus uses the same eligible balance, optionally capped by `asnb_rates.bonus_cap` (null = no cap).
Withdrawals larger than the available balance (opening + deposit) are flagged beside the input and capped in the calculation.

**Effective return** = total profit ÷ average month-end balance.

## Data

- `asnb_funds`: ASB, ASB 2, ASB 3 Didik, ASM, ASM 2 Wawasan, ASM 3 (add rows to add funds).
- `asnb_rates`: one row per fund + financial year. Seeded with the declared ASB rates for FY2019–FY2025 (special bonuses folded into `bonus_rate`). The other funds have no rates seeded, so verify official figures before adding them.
- `calculations`: saved and shared calculations. `share_token` → `/asnb-calculator/{token}`. Holds only the calculator inputs and results, plus a nullable `user_id` for later.

For a year with no declared rate (for example the current year), the dropdowns default to the latest declared rate, and the page says so.

## Not built yet (Phases 3–4)

Admin rate management, authentication, and saved calculation history. The schema already allows for them (`is_active` flags, `calculations.user_id`).

## Known simplifications

- Months always run January–December. ASB's financial year matches, but some funds (e.g. ASB 2, ASM) have different year-ends.
- Sales charges, unit caps (e.g. ASB's 300,000-unit limit) and financing are not modelled.
