<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRule extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['location_ids' => 'array', 'approver_ids' => 'array', 'manual_referral' => 'boolean',
        'effective_at' => 'datetime', 'approval_limit_cents' => 'integer', 'quote_limit_cents' => 'integer', 'quote_count' => 'integer'];

    public static function current(): self
    {
        return static::where('effective_at', '<=', now())->orderByDesc('effective_at')->orderByDesc('id')->firstOrFail();
    }
}
