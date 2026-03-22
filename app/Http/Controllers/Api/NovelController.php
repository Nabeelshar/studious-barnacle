<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Novel;
use App\Models\PowerStoneVote;
use Illuminate\Http\Request;

class NovelController extends Controller
{
    public function reviews(Novel $novel)
    {
        $reviews = \DB::table('reviews')
            ->join('users', 'reviews.user_id', '=', 'users.id')
            ->where('reviews.novel_id', $novel->id)
            ->whereNull('reviews.deleted_at')
            ->select('reviews.id', 'reviews.novel_id', 'reviews.user_id',
                     'reviews.rating', 'reviews.content',
                     'reviews.likes', 'reviews.created_at',
                     'users.name as user_name')
            ->orderByDesc('reviews.created_at')
            ->paginate(20);

        return response()->json($reviews);
    }

    public function similar(Novel $novel)
    {
        return response()->json(
            Novel::with('author')
                ->published()
                ->where('genre', $novel->genre)
                ->where('id', '!=', $novel->id)
                ->orderByDesc('views')
                ->take(10)
                ->get()
        );
    }

    public function index(Request $request)
    {
        $novels = Novel::with('author')
            ->published()
            ->when($request->genre, fn ($q, $g) => $q->where('genre', $g))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json($novels);
    }

    public function show(Novel $novel)
    {
        $novel->increment('views');
        return response()->json($novel->load('author', 'chapters'));
    }

    public function featured(Request $request)
    {
        return response()->json(
            Novel::with('author')->published()->featured()->orderByDesc('views')->take(10)->get()
        );
    }

    public function trending(Request $request)
    {
        return response()->json(
            Novel::with('author')->published()->orderByDesc('views')->take(20)->get()
        );
    }

    public function newArrivals(Request $request)
    {
        return response()->json(
            Novel::with('author')->published()->orderByDesc('created_at')->take(20)->get()
        );
    }

    public function rankings(Request $request)
    {
        $type = $request->get('type', 'power_stones'); // power_stones | views | rating
        $column = match ($type) {
            'views'  => 'views',
            'rating' => 'rating',
            default  => 'power_stones',
        };

        return response()->json(
            Novel::with('author')->published()->orderByDesc($column)->take(50)->get()
        );
    }

    public function search(Request $request)
    {
        $q = $request->validate(['q' => 'required|string|max:100'])['q'];

        return response()->json(
            Novel::with('author')
                ->published()
                ->where(fn ($query) =>
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('synopsis', 'like', "%{$q}%")
                          ->orWhere('tags', 'like', "%{$q}%")
                )
                ->paginate(20)
        );
    }

    public function vote(Request $request, Novel $novel)
    {
        // Simple daily power-stone vote: one per user per novel per day
        $today = now()->startOfDay();
        $alreadyVoted = \DB::table('power_stone_votes')
            ->where('user_id', $request->user()->id)
            ->where('novel_id', $novel->id)
            ->where('voted_at', '>=', $today)
            ->exists();

        if ($alreadyVoted) {
            return response()->json(['message' => 'Already voted today.'], 409);
        }

        \DB::table('power_stone_votes')->insert([
            'user_id'  => $request->user()->id,
            'novel_id' => $novel->id,
            'voted_at' => now(),
        ]);
        $novel->increment('power_stones');

        return response()->json(['power_stones' => $novel->fresh()->power_stones]);
    }
}
