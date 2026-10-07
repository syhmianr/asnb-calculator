<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Calculation extends Model
{
    protected $fillable = [
        'share_token', 'user_id', 'asnb_fund_id', 'financial_year', 'starting_balance',
        'dividend_rate', 'bonus_rate', 'transaction_timing', 'calculation_data',
        'estimated_dividend', 'estimated_bonus', 'estimated_profit', 'ending_balance',
    ];

    protected $casts = [
        'calculation_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Calculation $calculation) {
            $calculation->share_token ??= static::newShareToken();
        });
    }

    public static function newShareToken(): string
    {
        do {
            $token = Str::lower(Str::random(10));
        } while (static::where('share_token', $token)->exists());

        return $token;
    }

    public function getRouteKeyName(): string
    {
        return 'share_token';
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(AsnbFund::class, 'asnb_fund_id');
    }
}
