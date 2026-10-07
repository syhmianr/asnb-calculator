<?php

use App\Livewire\AsnbCalculator;
use App\Models\AsnbRate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $rates = AsnbRate::active()
        ->with('fund')
        ->whereHas('fund', fn ($q) => $q->where('code', 'ASB'))
        ->orderByDesc('financial_year')
        ->get();

    return view('landing', ['rates' => $rates]);
})->name('home');

Route::get('/asnb-calculator', AsnbCalculator::class)->name('calculator');
Route::get('/asnb-calculator/{calculation}', AsnbCalculator::class)->name('calculator.shared');
