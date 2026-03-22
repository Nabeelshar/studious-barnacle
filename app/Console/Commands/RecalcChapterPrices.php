<?php

namespace App\Console\Commands;

use App\Models\Chapter;
use Illuminate\Console\Command;

class RecalcChapterPrices extends Command
{
    protected $signature   = 'chapters:recalc-prices
                                {--dry-run : Show what would change without saving}';
    protected $description = 'Recalculate coin_price for all locked chapters based on word count';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');

        $chapters = Chapter::where('is_locked', true)
            ->whereNotNull('content')
            ->get(['id', 'number', 'title', 'word_count', 'coin_price', 'content']);

        if ($chapters->isEmpty()) {
            $this->info('No locked chapters found.');
            return self::SUCCESS;
        }

        $rows = [];
        $updated = 0;

        foreach ($chapters as $ch) {
            $words    = $ch->word_count ?: str_word_count(strip_tags($ch->content));
            $newPrice = Chapter::coinPriceForWords($words);

            $rows[] = [
                $ch->id,
                "Ch.{$ch->number} – {$ch->title}",
                $words,
                $ch->coin_price,
                $newPrice,
                $ch->coin_price !== $newPrice ? ($isDry ? 'would update' : 'updated') : 'ok',
            ];

            if (!$isDry && $ch->coin_price !== $newPrice) {
                Chapter::withoutEvents(function () use ($ch, $words, $newPrice) {
                    $ch->word_count = $words;
                    $ch->coin_price = $newPrice;
                    $ch->saveQuietly();
                });
                $updated++;
            } elseif ($ch->coin_price !== $newPrice) {
                $updated++;
            }
        }

        $this->table(
            ['ID', 'Chapter', 'Words', 'Old Price', 'New Price', 'Status'],
            $rows
        );

        $verb = $isDry ? 'Would update' : 'Updated';
        $this->info("{$verb} {$updated} / {$chapters->count()} chapters.");

        return self::SUCCESS;
    }
}
