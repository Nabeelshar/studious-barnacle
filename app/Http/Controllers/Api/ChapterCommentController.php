<?php
/*
|--------------------------------------------------------------------------
| ChapterCommentController
|--------------------------------------------------------------------------
| Manages per-chapter discussion comments.
| These are distinct from novel-level reviews (ReviewController).
|
| Supports one level of threading: top-level comments + replies.
| Optional paragraph_index pins a comment to a specific paragraph.
|
| Endpoints:
|   index()   GET    /v1/chapters/{chapter}/comments — paginated comment list
|   store()   POST   /v1/chapters/{chapter}/comments — post a new comment
|   destroy() DELETE /v1/comments/{comment}          — soft-delete own comment
|   like()    POST   /v1/comments/{comment}/like     — toggle like
|
| Dependencies: chapter_comments, users
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChapterCommentController extends Controller
{
    /**
     * GET /v1/chapters/{chapterId}/comments
     * Paginated top-level comments with replies nested.
     * Query params: ?paragraph={n} to filter by paragraph pin.
     */
    public function index(Request $request, int $chapterId)
    {
        $query = DB::table('chapter_comments as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.chapter_id', $chapterId)
            ->whereNull('c.parent_id')      // top-level only
            ->whereNull('c.deleted_at');

        if ($request->filled('paragraph')) {
            $query->where('c.paragraph_index', (int) $request->paragraph);
        }

        $comments = $query
            ->select('c.*', 'u.name as user_name')
            ->orderByDesc('c.created_at')
            ->paginate(20);

        // Attach reply counts
        $ids = collect($comments->items())->pluck('id');
        $replyCounts = DB::table('chapter_comments')
            ->whereIn('parent_id', $ids)
            ->whereNull('deleted_at')
            ->selectRaw('parent_id, COUNT(*) as cnt')
            ->groupBy('parent_id')
            ->pluck('cnt', 'parent_id');

        $comments->getCollection()->transform(function ($c) use ($replyCounts) {
            $c->reply_count = (int) ($replyCounts[$c->id] ?? 0);
            return $c;
        });

        return response()->json($comments);
    }

    /**
     * GET /v1/comments/{commentId}/replies
     * Replies to a specific comment (flat list, no further nesting).
     */
    public function replies(int $commentId)
    {
        $replies = DB::table('chapter_comments as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.parent_id', $commentId)
            ->whereNull('c.deleted_at')
            ->select('c.*', 'u.name as user_name')
            ->orderBy('c.created_at')
            ->paginate(10);

        return response()->json($replies);
    }

    /**
     * POST /v1/chapters/{chapterId}/comments
     * Body: { content, parent_id?, paragraph_index? }
     */
    public function store(Request $request, int $chapterId)
    {
        $data = $request->validate([
            'content'         => 'required|string|max:1000',
            'parent_id'       => 'nullable|exists:chapter_comments,id',
            'paragraph_index' => 'nullable|integer|min:0',
        ]);

        $id = DB::table('chapter_comments')->insertGetId([
            'user_id'         => $request->user()->id,
            'chapter_id'      => $chapterId,
            'parent_id'       => $data['parent_id'] ?? null,
            'content'         => $data['content'],
            'paragraph_index' => $data['paragraph_index'] ?? null,
            'likes'           => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $comment = DB::table('chapter_comments as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.id', $id)
            ->select('c.*', 'u.name as user_name')
            ->first();

        return response()->json($comment, 201);
    }

    /**
     * DELETE /v1/comments/{commentId}
     * Soft-delete the user's own comment.
     */
    public function destroy(Request $request, int $commentId)
    {
        $affected = DB::table('chapter_comments')
            ->where('id', $commentId)
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        if (! $affected) {
            return response()->json(['message' => 'Comment not found.'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * POST /v1/comments/{commentId}/like
     * Increments the like counter (simple, no duplicate check for now).
     */
    public function like(int $commentId)
    {
        DB::table('chapter_comments')
            ->where('id', $commentId)
            ->increment('likes');

        return response()->json(['success' => true]);
    }
}
