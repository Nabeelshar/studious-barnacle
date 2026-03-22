<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Novel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request, Novel $novel)
    {
        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:2000',
        ]);

        $existing = DB::table('reviews')
            ->where('user_id', $request->user()->id)
            ->where('novel_id', $novel->id)
            ->first();

        if ($existing) {
            DB::table('reviews')
                ->where('id', $existing->id)
                ->update(['rating' => $data['rating'], 'content' => $data['content'] ?? null, 'updated_at' => now()]);
        } else {
            DB::table('reviews')->insert([
                'user_id'    => $request->user()->id,
                'novel_id'   => $novel->id,
                'rating'     => $data['rating'],
                'content'    => $data['content'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Recalculate average rating
        $avg = DB::table('reviews')->where('novel_id', $novel->id)->avg('rating');
        $novel->update(['rating' => round($avg, 1)]);

        return response()->json(['message' => 'Review saved.', 'rating' => $data['rating']]);
    }

    public function destroy(Request $request, $reviewId)
    {
        $deleted = DB::table('reviews')
            ->where('id', $reviewId)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['message' => 'Review deleted.']);
    }
}
