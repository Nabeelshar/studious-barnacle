<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Novel;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function index(Novel $novel)
    {
        return response()->json(
            $novel->chapters()
                ->where('status', 'published')
                ->orderBy('number')
                ->get(['id', 'novel_id', 'number', 'title', 'word_count', 'is_locked', 'coin_price',
                       'is_early_access', 'is_wait_free', 'published_at', 'views', 'status'])
        );
    }

    /** Free preview — strips content for locked chapters */
    public function preview(Chapter $chapter)
    {
        $data = $chapter->only(['id', 'novel_id', 'number', 'title', 'word_count',
                                'is_locked', 'coin_price', 'is_early_access', 'published_at']);

        if ($chapter->is_locked) {
            // Return first ~300 words as teaser
            $preview = implode(' ', array_slice(explode(' ', strip_tags($chapter->content)), 0, 300));
            $data['content'] = $preview . '…';
            $data['is_preview'] = true;
        } else {
            $data['content']    = $chapter->content;
            $data['is_preview'] = false;
        }

        return response()->json($data);
    }

    /** Full chapter — checks unlock status for authenticated users */
    public function show(Request $request, Chapter $chapter)
    {
        $chapter->increment('views');
        $user = $request->user();

        if (! $chapter->is_locked) {
            // Increment 'read_chapters' task progress for free chapters
            TaskController::incrementProgress($user->id, 'read_chapters');
            return response()->json($chapter);
        }

        // Check if user has unlocked this chapter
        $unlocked = \DB::table('user_chapter_unlocks')
            ->where('user_id', $user->id)
            ->where('chapter_id', $chapter->id)
            ->exists();

        if ($unlocked || $user->vip_tier !== 'none') {
            // Increment task progress on successful read
            TaskController::incrementProgress($user->id, 'read_chapters');
            // Override is_locked to false so the client knows this user has access
            $data = $chapter->toArray();
            $data['is_locked'] = false;
            return response()->json($data);
        }

        return response()->json(['message' => 'Chapter is locked. Purchase to read.'], 403);
    }
}
