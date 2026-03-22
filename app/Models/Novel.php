<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Novel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'author_id',
        'cover_url',
        'synopsis',
        'status',         // ongoing | completed | hiatus | dropped
        'genre',
        'tags',
        'language',
        'original_language',
        'total_chapters',
        'rating',
        'views',
        'like_count',
        'power_stones',
        'is_featured',
        'is_vip',         // whether novel has locked chapters
        'has_early_access',
        'series_name',
        'series_order',
        'series_total',
        'published_at',
    ];

    protected $casts = [
        'tags'            => 'array',
        'is_featured'     => 'boolean',
        'is_vip'          => 'boolean',
        'has_early_access'=> 'boolean',
        'rating'          => 'decimal:2',
        'published_at'    => 'datetime',
        'power_stones'    => 'integer',
        'views'           => 'integer',
        'like_count'      => 'integer',
        'total_chapters'  => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('number');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ── Accessors ──────────────────────────────────────────────────

    public function getChapterCountAttribute(): int
    {
        return $this->chapters()->count();
    }
}
