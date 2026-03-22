<?php
/*
|--------------------------------------------------------------------------
| UnlockController
|--------------------------------------------------------------------------
| Handles all chapter unlock strategies beyond single-coin purchase:
|   - startTimer()      : begin Wait-For-Free countdown on first view
|   - checkTimer()      : get timer state for one or many chapters
|   - claimFree()       : claim a chapter whose timer has expired
|   - bulkUnlock()      : purchase multiple chapters at once (coin deduction)
|   - adUnlock()        : grant one free unlock after user watches an ad
|   - toggleAutoSub()   : enable/disable auto-subscribe for a novel
|   - getAutoSubs()     : list user's active auto-subscriptions
|
| Dependencies: uses coin_transactions, user_chapter_unlocks,
|   wait_for_free_timers, user_auto_subscriptions, user_ad_unlocks
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnlockController extends Controller
{
    // Free unlock available after this many hours (adjustable per chapter via meta)
    const DEFAULT_WAIT_HOURS = 24;

    // Max ad-watch unlocks per day
    const AD_DAILY_LIMIT = 3;

    // ── Wait-for-Free ─────────────────────────────────────────────────

    /**
     * POST /v1/unlock/start-timer
     * Body: { chapter_id }
     * Called when user first taps a locked chapter. Starts the countdown
     * if not already running. Returns the timer record.
     */
    public function startTimer(Request $request)
    {
        $data    = $request->validate(['chapter_id' => 'required|exists:chapters,id']);
        $user    = $request->user();
        $chapter = Chapter::find($data['chapter_id']);

        if (! $chapter->is_locked) {
            return response()->json(['message' => 'Chapter is free.'], 400);
        }

        // Check already unlocked via coins
        $owned = DB::table('user_chapter_unlocks')
            ->where('user_id', $user->id)
            ->where('chapter_id', $chapter->id)
            ->exists();

        if ($owned) {
            return response()->json(['message' => 'Already unlocked.'], 400);
        }

        // Create or return existing timer
        $waitHours   = $chapter->wait_free_hours ?? self::DEFAULT_WAIT_HOURS;
        $availableAt = now()->addHours($waitHours);

        $timer = DB::table('wait_for_free_timers')
            ->where('user_id', $user->id)
            ->where('chapter_id', $chapter->id)
            ->first();

        if (! $timer) {
            DB::table('wait_for_free_timers')->insert([
                'user_id'      => $user->id,
                'chapter_id'   => $chapter->id,
                'available_at' => $availableAt,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $timer = DB::table('wait_for_free_timers')
                ->where('user_id', $user->id)
                ->where('chapter_id', $chapter->id)
                ->first();
        }

        return response()->json($this->formatTimer($timer));
    }

    /**
     * GET /v1/unlock/timers?novel_id={id}
     * Returns all active timers for a novel — used to decorate chapter list.
     */
    public function timers(Request $request)
    {
        $request->validate(['novel_id' => 'required|exists:novels,id']);
        $user = $request->user();

        $timers = DB::table('wait_for_free_timers as t')
            ->join('chapters as c', 'c.id', '=', 't.chapter_id')
            ->where('t.user_id', $user->id)
            ->where('c.novel_id', $request->novel_id)
            ->whereNull('t.claimed_at')
            ->select('t.*')
            ->get();

        return response()->json($timers->map(fn($t) => $this->formatTimer($t)));
    }

    /**
     * POST /v1/unlock/claim-free
     * Body: { chapter_id }
     * Claims a free unlock once the timer has expired.
     */
    public function claimFree(Request $request)
    {
        $data  = $request->validate(['chapter_id' => 'required|exists:chapters,id']);
        $user  = $request->user();

        $timer = DB::table('wait_for_free_timers')
            ->where('user_id', $user->id)
            ->where('chapter_id', $data['chapter_id'])
            ->whereNull('claimed_at')
            ->first();

        if (! $timer) {
            return response()->json(['message' => 'No active timer for this chapter.'], 404);
        }

        if (now()->lt($timer->available_at)) {
            return response()->json(['message' => 'Timer not yet expired.', 'available_at' => $timer->available_at], 400);
        }

        DB::transaction(function () use ($user, $timer) {
            // Mark timer claimed
            DB::table('wait_for_free_timers')
                ->where('id', $timer->id)
                ->update(['claimed_at' => now(), 'updated_at' => now()]);

            // Insert into unlocks table (0 coins)
            DB::table('user_chapter_unlocks')->insertOrIgnore([
                'user_id'     => $user->id,
                'chapter_id'  => $timer->chapter_id,
                'coins_spent' => 0,
                'unlocked_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    // ── Bulk Unlock ───────────────────────────────────────────────────

    /**
     * POST /v1/unlock/bulk
     * Body: { chapter_ids: [1,2,3] }
     * Deducts coins for all unowned chapters in the array simultaneously.
     */
    public function bulkUnlock(Request $request)
    {
        $data = $request->validate(['chapter_ids' => 'required|array|min:1|max:50', 'chapter_ids.*' => 'integer|exists:chapters,id']);
        $user = $request->user();

        $chapters = Chapter::whereIn('id', $data['chapter_ids'])
            ->where('is_locked', true)
            ->get();

        // Filter out already-owned chapters
        $alreadyOwned = DB::table('user_chapter_unlocks')
            ->where('user_id', $user->id)
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->pluck('chapter_id')
            ->toArray();

        $toUnlock = $chapters->reject(fn($c) => in_array($c->id, $alreadyOwned));

        if ($toUnlock->isEmpty()) {
            return response()->json(['message' => 'All chapters already unlocked.'], 400);
        }

        $totalCost = $toUnlock->sum(fn($c) => $c->coin_price ?? 1);

        if ($user->coin_balance < $totalCost) {
            return response()->json(['message' => 'Insufficient coins.', 'required' => $totalCost], 402);
        }

        DB::transaction(function () use ($user, $toUnlock, $totalCost) {
            $user->decrement('coin_balance', $totalCost);
            $user->increment('total_spent', $totalCost);

            $now    = now();
            $rows   = $toUnlock->map(fn($c) => [
                'user_id'     => $user->id,
                'chapter_id'  => $c->id,
                'coins_spent' => $c->coin_price ?? 1,
                'unlocked_at' => $now,
            ])->toArray();

            DB::table('user_chapter_unlocks')->insertOrIgnore($rows);

            DB::table('coin_transactions')->insert([
                'user_id'       => $user->id,
                'type'          => 'spend',
                'amount'        => -$totalCost,
                'balance_after' => $user->coin_balance,
                'description'   => "Bulk unlock: {$toUnlock->count()} chapters",
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        });

        return response()->json([
            'success'         => true,
            'unlocked_count'  => $toUnlock->count(),
            'coins_spent'     => $totalCost,
            'new_coin_balance' => $user->fresh()->coin_balance,
        ]);
    }

    // ── Ad Unlock ─────────────────────────────────────────────────────

    /**
     * POST /v1/unlock/ad-claim
     * Body: { chapter_id }
     * Called after Flutter confirms ad was watched. Server grants 1 free unlock.
     * Enforces AD_DAILY_LIMIT per user per day.
     */
    public function adClaim(Request $request)
    {
        $data    = $request->validate(['chapter_id' => 'required|exists:chapters,id']);
        $user    = $request->user();
        $today   = now()->toDateString();

        // Check daily limit
        $todayCount = DB::table('user_ad_unlocks')
            ->where('user_id', $user->id)
            ->where('watched_date', $today)
            ->count();

        if ($todayCount >= self::AD_DAILY_LIMIT) {
            return response()->json(['message' => 'Daily ad limit reached.', 'limit' => self::AD_DAILY_LIMIT], 429);
        }

        // Check not already owned
        $alreadyOwned = DB::table('user_chapter_unlocks')
            ->where('user_id', $user->id)
            ->where('chapter_id', $data['chapter_id'])
            ->exists();

        if ($alreadyOwned) {
            return response()->json(['message' => 'Chapter already unlocked.'], 400);
        }

        DB::transaction(function () use ($user, $data, $today) {
            DB::table('user_ad_unlocks')->insert([
                'user_id'      => $user->id,
                'chapter_id'   => $data['chapter_id'],
                'watched_date' => $today,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('user_chapter_unlocks')->insertOrIgnore([
                'user_id'     => $user->id,
                'chapter_id'  => $data['chapter_id'],
                'coins_spent' => 0,
                'unlocked_at' => now(),
            ]);
        });

        $remaining = self::AD_DAILY_LIMIT - $todayCount - 1;

        return response()->json(['success' => true, 'ad_unlocks_remaining_today' => $remaining]);
    }

    /**
     * GET /v1/unlock/ad-status
     * Returns how many ad unlocks the user has used/remaining today.
     */
    public function adStatus(Request $request)
    {
        $used = DB::table('user_ad_unlocks')
            ->where('user_id', $request->user()->id)
            ->where('watched_date', now()->toDateString())
            ->count();

        return response()->json([
            'used'      => $used,
            'limit'     => self::AD_DAILY_LIMIT,
            'remaining' => max(0, self::AD_DAILY_LIMIT - $used),
        ]);
    }

    // ── Auto-Subscribe ────────────────────────────────────────────────

    /**
     * POST /v1/unlock/auto-sub/{novelId}
     * Body: { max_coins_per_chapter: 3, is_active: true }
     */
    public function toggleAutoSub(Request $request, int $novelId)
    {
        $data = $request->validate([
            'max_coins_per_chapter' => 'sometimes|integer|min:1|max:10',
            'is_active'             => 'required|boolean',
        ]);

        DB::table('user_auto_subscriptions')->updateOrInsert(
            ['user_id' => $request->user()->id, 'novel_id' => $novelId],
            array_merge($data, ['updated_at' => now(), 'created_at' => now()])
        );

        return response()->json(['success' => true]);
    }

    /**
     * GET /v1/unlock/auto-subs
     * Returns user's active auto-subscriptions with novel title.
     */
    public function autoSubs(Request $request)
    {
        $subs = DB::table('user_auto_subscriptions as s')
            ->join('novels as n', 'n.id', '=', 's.novel_id')
            ->where('s.user_id', $request->user()->id)
            ->where('s.is_active', true)
            ->select('s.*', 'n.title as novel_title', 'n.cover_url', 'n.slug')
            ->orderByDesc('s.updated_at')
            ->get();

        return response()->json($subs);
    }

    // ── Helper ────────────────────────────────────────────────────────

    private function formatTimer(object $t): array
    {
        $available = \Carbon\Carbon::parse($t->available_at);
        $isReady   = now()->gte($available);

        return [
            'chapter_id'    => $t->chapter_id,
            'available_at'  => $t->available_at,
            'claimed_at'    => $t->claimed_at,
            'is_ready'      => $isReady,
            'seconds_left'  => $isReady ? 0 : (int) now()->diffInSeconds($available),
        ];
    }
}
