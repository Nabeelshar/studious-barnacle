<?php
/*
|--------------------------------------------------------------------------
| RankingController
|--------------------------------------------------------------------------
| Serves novel ranking lists segmented by type and time period.
|
| Ranking types:
|   gems     — total gems gifted (novel_gem_gifts.gems SUM)
|   reads    — chapter read count (reading_history rows)
|   library  — library add count (user_novels rows)
|   reviews  — review count + average rating
|   power    — power stones (power_stone_votes.stones SUM)
|
| Time periods:
|   weekly   — last 7 days
|   monthly  — last 30 days
|   all_time — no date filter
|
| Endpoint:
|   GET /v1/rankings?type=gems&period=weekly&limit=20
|
| Returns paginated list of ApiNovel-shaped objects with rank_score attached.
|
| Dependencies: novels, novel_gem_gifts, reading_history,
|               user_novels, power_stone_votes, reviews
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    private const ALLOWED_TYPES   = ['gems', 'reads', 'library', 'reviews', 'power'];
    private const ALLOWED_PERIODS = ['weekly', 'monthly', 'all_time'];

    /**
     * GET /v1/rankings
     * Query: type, period, limit (default 20)
     */
    public function index(Request $request)
    {
        $type   = in_array($request->type, self::ALLOWED_TYPES) ? $request->type : 'gems';
        $period = in_array($request->period, self::ALLOWED_PERIODS) ? $request->period : 'weekly';
        $limit  = min((int) ($request->limit ?? 20), 50);

        $since = match($period) {
            'weekly'  => now()->subDays(7),
            'monthly' => now()->subDays(30),
            default   => null,
        };

        $novels = match($type) {
            'gems'    => $this->rankByGems($since, $limit),
            'reads'   => $this->rankByReads($since, $limit),
            'library' => $this->rankByLibrary($since, $limit),
            'reviews' => $this->rankByReviews($since, $limit),
            'power'   => $this->rankByPower($since, $limit),
        };

        return response()->json([
            'type'    => $type,
            'period'  => $period,
            'data'    => $novels,
        ]);
    }

    // ── Ranking queries ───────────────────────────────────────────────

    private function rankByGems(?\Carbon\Carbon $since, int $limit): \Illuminate\Support\Collection
    {
        $q = DB::table('novels as n')
            ->join('novel_gem_gifts as g', 'g.novel_id', '=', 'n.id')
            ->groupBy('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status')
            ->select('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status',
                     DB::raw('SUM(g.gems) as rank_score'));

        if ($since) $q->where('g.created_at', '>=', $since);

        return $q->orderByDesc('rank_score')->limit($limit)->get();
    }

    private function rankByReads(?\Carbon\Carbon $since, int $limit): \Illuminate\Support\Collection
    {
        $q = DB::table('novels as n')
            ->join('reading_history as h', 'h.novel_id', '=', 'n.id')
            ->groupBy('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status')
            ->select('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status',
                     DB::raw('COUNT(h.id) as rank_score'));

        if ($since) $q->where('h.read_at', '>=', $since);

        return $q->orderByDesc('rank_score')->limit($limit)->get();
    }

    private function rankByLibrary(?\Carbon\Carbon $since, int $limit): \Illuminate\Support\Collection
    {
        $q = DB::table('novels as n')
            ->join('user_novels as un', 'un.novel_id', '=', 'n.id')
            ->groupBy('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status')
            ->select('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status',
                     DB::raw('COUNT(un.id) as rank_score'));

        if ($since) $q->where('un.created_at', '>=', $since);

        return $q->orderByDesc('rank_score')->limit($limit)->get();
    }

    private function rankByReviews(?\Carbon\Carbon $since, int $limit): \Illuminate\Support\Collection
    {
        $q = DB::table('novels as n')
            ->join('reviews as r', 'r.novel_id', '=', 'n.id')
            ->whereNull('r.deleted_at')
            ->groupBy('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status')
            ->select('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status',
                     DB::raw('COUNT(r.id) as rank_score'),
                     DB::raw('ROUND(AVG(r.rating),1) as avg_rating'));

        if ($since) $q->where('r.created_at', '>=', $since);

        return $q->orderByDesc('rank_score')->limit($limit)->get();
    }

    private function rankByPower(?\Carbon\Carbon $since, int $limit): \Illuminate\Support\Collection
    {
        $q = DB::table('novels as n')
            ->join('power_stone_votes as v', 'v.novel_id', '=', 'n.id')
            ->groupBy('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status')
            ->select('n.id', 'n.title', 'n.slug', 'n.cover_url', 'n.genre', 'n.status',
                     DB::raw('SUM(v.stones) as rank_score'));

        if ($since) $q->where('v.voted_at', '>=', $since);

        return $q->orderByDesc('rank_score')->limit($limit)->get();
    }
}
