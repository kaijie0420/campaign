<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'customer_id', 'redeemed', 'locked_at'
    ];

    protected $casts = [
        'redeemed' => 'boolean'
    ];

    public static function getUnlockedVoucher()
    {
        return static::where('redeemed', false)
            ->whereNull('customer_id')
            ->whereNull('locked_at');
    }
}
