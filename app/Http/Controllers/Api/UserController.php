<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Novel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function library(Request $request)
    {
        $novels = $request->user()
            ->belongsToMany(Novel::class, 'user_novels')
            ->withTimestamps()
            ->with('author')
            ->paginate(20);

        return response()->json($novels);
    }

    public function addToLibrary(Request $request)
    {
        $data = $request->validate(['novel_id' => 'required|exists:novels,id']);
        $request->user()->belongsToMany(Novel::class, 'user_novels')
            ->syncWithoutDetaching([$data['novel_id']]);

        return response()->json(['message' => 'Added to library.']);
    }

    public function removeFromLibrary(Request $request, Novel $novel)
    {
        DB::table('user_novels')
            ->where('user_id', $request->user()->id)
            ->where('novel_id', $novel->id)
            ->delete();

        return response()->json(['message' => 'Removed from library.']);
    }

    public function history(Request $request)
    {
        $history = DB::table('reading_history as rh')
            ->join('novels as n', 'n.id', '=', 'rh.novel_id')
            ->leftJoin('chapters as c', 'c.id', '=', 'rh.chapter_id')
            ->where('rh.user_id', $request->user()->id)
            ->orderByDesc('rh.read_at')
            ->take(50)
            ->select(
                'rh.novel_id',
                'rh.chapter_id',
                'rh.read_at',
                'n.title as novel_title',
                'n.cover_url',
                'n.slug',
                'n.total_chapters',
                'c.number as chapter_number'
            )
            ->get();

        return response()->json($history);
    }

    public function recordHistory(Request $request)
    {
        $data = $request->validate([
            'novel_id'   => 'required|exists:novels,id',
            'chapter_id' => 'required|exists:chapters,id',
        ]);

        DB::table('reading_history')->updateOrInsert(
            ['user_id' => $request->user()->id, 'novel_id' => $data['novel_id']],
            ['chapter_id' => $data['chapter_id'], 'read_at' => now()]
        );

        return response()->json(['message' => 'Progress saved.']);
    }

    public function progress(Request $request, Novel $novel)
    {
        $row = DB::table('reading_history')
            ->where('user_id', $request->user()->id)
            ->where('novel_id', $novel->id)
            ->first();

        return response()->json($row);
    }

    public function updateProgress(Request $request)
    {
        return $this->recordHistory($request);
    }
}
