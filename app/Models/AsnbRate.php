<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsnbRate extends Model
{
    public const METHOD_MINIMUM = 'minimum_monthly_balance';
    public const METHOD_AVERAGE = 'average_monthly_balance';
    public const METHOD_CUSTOM = 'custom';

    public const METHODS = [
        self::METHOD_MINIMUM => 'Minimum monthly balance',
        self::METHOD_AVERAGE => 'Average monthly balance',
        self::METHOD_CUSTOM => 'Mid-month (pro-rata)',
    ];

    protected $fillable = [
        'asnb_fund_id', 'financial_year', 'dividend_rate', 'bonus_rate',
        'calculation_method', 'bonus_cap', 'notes', 'is_active',
    ];

    protected $casts = [
        'financial_year' => 'integer',
        'dividend_rate' => 'decimal:3',
        'bonus_rate' => 'decimal:3',
        'bonus_cap' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(AsnbFund::class, 'asnb_fund_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
