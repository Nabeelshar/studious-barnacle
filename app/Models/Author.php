<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Author extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'pen_name',
        'bio',
        'avatar_url',
        'country',
        'user_id',   // optional linked platform user account
        'is_verified',
        'contract_status', // pending | signed | expired
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function novels(): HasMany
    {
        return $this->hasMany(Novel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
