<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'coin_balance' => $user->coin_balance,
            'vip_tier'     => $user->vip_tier,
            'total_spent'  => $user->total_spent,
        ]);
    }

    public function transactions(Request $request)
    {
        return response()->json(
            CoinTransaction::where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    public function unlockChapter(Request $request)
    {
        $data = $request->validate(['chapter_id' => 'required|exists:chapters,id']);

        $chapter = \App\Models\Chapter::find($data['chapter_id']);
        $user    = $request->user();

        if (! $chapter->is_locked) {
            return response()->json(['message' => 'Chapter is free.'], 400);
        }

        $alreadyUnlocked = DB::table('user_chapter_unlocks')
            ->where('user_id', $user->id)
            ->where('chapter_id', $chapter->id)
            ->exists();

        if ($alreadyUnlocked) {
            return response()->json(['message' => 'Already unlocked.'], 400);
        }

        $price = $chapter->coin_price ?? 1;

        if ($user->coin_balance < $price) {
            return response()->json(['message' => 'Insufficient coins.'], 402);
        }

        DB::transaction(function () use ($user, $chapter, $price) {
            $user->decrement('coin_balance', $price);
            $user->increment('total_spent', $price);

            DB::table('user_chapter_unlocks')->insert([
                'user_id'    => $user->id,
                'chapter_id' => $chapter->id,
                'coins_spent'=> $price,
                'unlocked_at'=> now(),
            ]);

            CoinTransaction::create([
                'user_id'     => $user->id,
                'type'        => 'spend',
                'amount'      => $price,
                'description' => "Unlocked chapter: {$chapter->title}",
                'chapter_id'  => $chapter->id,
            ]);
        });

        return response()->json(['coin_balance' => $user->fresh()->coin_balance]);
    }

    /**
     * Verify an in-app purchase receipt and credit coins.
     *
     * For Android, $receiptData is the purchaseToken from Google Play.
     * Full server-side receipt verification with the Google Play Developer API
     * requires a service-account key — that verification step is marked with
     * TODO below and can be enabled once you add the key.
     */
    public function purchaseCoins(Request $request)
    {
        $data = $request->validate([
            'product_id'     => 'required|string',
            'receipt_data'   => 'required|string',
            'transaction_id' => 'required|string',
            'platform'       => 'required|in:android,ios,web',
        ]);

        // Map product_id → coin amount
        $coinMap = [
            'coins_100'   => 100,
            'coins_500'   => 500,
            'coins_1200'  => 1200,
            'coins_3000'  => 3000,
            'coins_6500'  => 6500,
            'coins_15000' => 15000,
        ];

        if (! isset($coinMap[$data['product_id']])) {
            return response()->json(['message' => 'Unknown product.'], 400);
        }

        $coins = $coinMap[$data['product_id']];
        $user  = $request->user();

        // Idempotency: prevent re-processing the same transaction
        $alreadyProcessed = CoinTransaction::where('user_id', $user->id)
            ->where('type', 'purchase')
            ->where('reference_id', $data['transaction_id'])
            ->exists();

        if ($alreadyProcessed) {
            return response()->json([
                'message'      => 'Transaction already processed.',
                'coin_balance' => $user->coin_balance,
            ]);
        }

        // TODO: Add Google Play Developer API receipt verification here
        // when you have a service-account key. For now we trust the
        // transaction_id uniqueness check above as minimal fraud protection.

        DB::transaction(function () use ($user, $coins, $data) {
            $user->increment('coin_balance', $coins);

            CoinTransaction::create([
                'user_id'      => $user->id,
                'type'         => 'purchase',
                'amount'       => $coins,
                'description'  => "Purchased {$coins} coins ({$data['platform']})",
                'reference_id' => $data['transaction_id'],
            ]);
        });

        return response()->json([
            'coins_credited' => $coins,
            'coin_balance'   => $user->fresh()->coin_balance,
        ]);
    }

    /**
     * POST /v1/wallet/gift-novel
     * Spend coins to gift a novel (boosts it in gem-based rankings).
     */
    public function giftNovel(Request $request)
    {
        $data = $request->validate([
            'novel_id' => 'required|exists:novels,id',
            'coins'    => 'required|integer|min:1|max:1000',
        ]);

        $user  = $request->user();
        $coins = (int) $data['coins'];

        if ($user->coin_balance < $coins) {
            return response()->json(['message' => 'Insufficient coins.'], 402);
        }

        DB::transaction(function () use ($user, $data, $coins) {
            $user->decrement('coin_balance', $coins);

            CoinTransaction::create([
                'user_id'     => $user->id,
                'type'        => 'spend',
                'amount'      => $coins,
                'novel_id'    => $data['novel_id'],
                'description' => "Gift to novel #{$data['novel_id']}",
            ]);

            // Record in gem gifts table so coin-gifts appear in gem rankings
            DB::table('novel_gem_gifts')->insert([
                'user_id'    => $user->id,
                'novel_id'   => $data['novel_id'],
                'gems'       => $coins,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['coin_balance' => $user->fresh()->coin_balance]);
    }

    public function dailyCheckin(Request $request)    {
        $user  = $request->user();
        $today = now()->toDateString();

        if ($user->last_checkin_date === $today) {
            return response()->json(['message' => 'Already checked in today.'], 400);
        }

        $yesterday = now()->subDay()->toDateString();
        $streak = ($user->last_checkin_date === $yesterday)
            ? $user->checkin_streak + 1
            : 1;

        $reward = min($streak, 7); // 1–7 coins based on streak

        DB::transaction(function () use ($user, $today, $streak, $reward) {
            $user->increment('coin_balance', $reward);
            $user->update([
                'last_checkin_date' => $today,
                'checkin_streak'    => $streak,
            ]);
            CoinTransaction::create([
                'user_id'     => $user->id,
                'type'        => 'bonus',
                'amount'      => $reward,
                'description' => "Daily check-in (day {$streak})",
            ]);
        });

        return response()->json([
            'coins_earned'  => $reward,
            'streak'        => $streak,
            'coin_balance'  => $user->fresh()->coin_balance,
        ]);
    }
}
