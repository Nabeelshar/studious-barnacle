<?php
/*
|--------------------------------------------------------------------------
| GemController
|--------------------------------------------------------------------------
| Handles the Gems secondary currency system (separate from Coins).
| Gems are earned through reading tasks and can be gifted to novels
| to boost their ranking position.
|
| Endpoints:
|   balance()         GET  /v1/gems            — gem balance + short history
|   giftToNovel()     POST /v1/gems/gift        — deduct gems, record gift
|   transactions()    GET  /v1/gems/transactions — full gem transaction log
|   novelGifts()      GET  /v1/gems/novel/{id}  — total gems a novel received
|
| Dependencies: gem_transactions, novel_gem_gifts, users.gem_balance
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GemController extends Controller
{
    /**
     * GET /v1/gems
     * Returns the user's gem balance and last 5 transactions.
     */
    public function balance(Request $request)
    {
        $user = $request->user();

        $recent = DB::table('gem_transactions')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'gem_balance' => $user->gem_balance,
            'recent'      => $recent,
        ]);
    }

    /**
     * GET /v1/gems/transactions
     * Full paginated gem transaction history.
     */
    public function transactions(Request $request)
    {
        return response()->json(
            DB::table('gem_transactions')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    /**
     * POST /v1/gems/gift
     * Body: { novel_id, gems }
     * Deducts gems from user balance and records the gift to the novel.
     * Gifts accumulate and drive the Gems ranking.
     */
    public function giftToNovel(Request $request)
    {
        $data = $request->validate([
            'novel_id' => 'required|exists:novels,id',
            'gems'     => 'required|integer|min:1|max:9999',
        ]);

        $user = $request->user();

        if ($user->gem_balance < $data['gems']) {
            return response()->json(['message' => 'Insufficient gems.'], 402);
        }

        DB::transaction(function () use ($user, $data) {
            $user->decrement('gem_balance', $data['gems']);

            DB::table('novel_gem_gifts')->insert([
                'user_id'    => $user->id,
                'novel_id'   => $data['novel_id'],
                'gems'       => $data['gems'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('gem_transactions')->insert([
                'user_id'       => $user->id,
                'type'          => 'gifted',
                'amount'        => -$data['gems'],
                'balance_after' => $user->gem_balance,
                'description'   => "Gifted to novel #{$data['novel_id']}",
                'novel_id'      => $data['novel_id'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });

        return response()->json([
            'success'         => true,
            'new_gem_balance' => $user->fresh()->gem_balance,
        ]);
    }

    /**
     * GET /v1/gems/novel/{novelId}
     * Total gems received by a novel (public, no auth required).
     * Also returns the top 5 gifters for social proof.
     */
    public function novelGifts(int $novelId)
    {
        $total = DB::table('novel_gem_gifts')
            ->where('novel_id', $novelId)
            ->sum('gems');

        $topGifters = DB::table('novel_gem_gifts as g')
            ->join('users as u', 'u.id', '=', 'g.user_id')
            ->where('g.novel_id', $novelId)
            ->groupBy('g.user_id', 'u.name')
            ->selectRaw('u.name, SUM(g.gems) as total_gifted')
            ->orderByDesc('total_gifted')
            ->limit(5)
            ->get();

        return response()->json([
            'total_gems'  => (int) $total,
            'top_gifters' => $topGifters,
        ]);
    }
}
