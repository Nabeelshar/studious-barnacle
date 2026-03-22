<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chapter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'novel_id',
        'number',
        'title',
        'content',
        'word_count',
        'is_locked',        // requires coins to unlock
        'coin_price',
        'is_early_access',  // VIP-only advance chapter
        'is_wait_free',     // unlocks automatically after timer
        'wait_free_hours',
        'views',
        'translator_id',
        'status',           // draft | published | scheduled
        'published_at',
        'scheduled_at',
    ];

    protected $casts = [
        'is_locked'       => 'boolean',
        'is_early_access' => 'boolean',
        'is_wait_free'    => 'boolean',
        'published_at'    => 'datetime',
        'scheduled_at'    => 'datetime',
    ];

    // ── Auto-calculate word_count & coin_price on save ─────────────

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Chapter $chapter) {
            // Auto-calculate word count from HTML content
            if ($chapter->isDirty('content') && $chapter->content) {
                $text = strip_tags($chapter->content);
                $chapter->word_count = str_word_count($text);
            }

            // Auto-set coin_price based on word count when:
            //   • chapter is locked, AND
            //   • content was edited (fresh word count) OR is_locked was just toggled on
            if ($chapter->is_locked && ($chapter->isDirty('content') || $chapter->isDirty('is_locked'))) {
                $words = $chapter->word_count ?? 0;
                $chapter->coin_price = self::coinPriceForWords($words);
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────

    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }

    public function translator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translator_id');
    }

    // ── Pricing helper ─────────────────────────────────────────────

    public static function coinPriceForWords(int $words): int
    {
        return match (true) {
            $words < 500  => 2,
            $words < 1000 => 5,
            $words < 1500 => 8,
            $words < 2000 => 10,
            $words < 3000 => 12,
            default       => 15,
        };
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function scopeFree($query)
    {
        return $query->where('is_locked', false);
    }

    // ── Accessors ──────────────────────────────────────────────────

    public function getWordCountFormattedAttribute(): string
    {
        return number_format($this->word_count);
    }
}
