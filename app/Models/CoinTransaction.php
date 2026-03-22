<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',       // purchase | spend | bonus | refund | daily_checkin
        'amount',     // positive = credit, negative = debit
        'balance_after',
        'description',
        'novel_id',   // nullable — for chapter unlocks
        'chapter_id', // nullable — for chapter unlocks
        'package_id', // nullable — for coin package purchase
        'reference',  // payment gateway ref
    ];

    protected $casts = [
        'amount'        => 'integer',
        'balance_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
