<?php
/*
|--------------------------------------------------------------------------
| HighlightController
|--------------------------------------------------------------------------
| Manages user paragraph highlights and reading notes inside the reader.
|
| Each highlight records:
|   - which chapter + paragraph
|   - character offsets within the paragraph text
|   - a colour tag (yellow/pink/blue/green)
|   - an optional reader note/annotation
|
| Endpoints:
|   index()   GET    /v1/chapters/{chapter}/highlights — all highlights for chapter
|   store()   POST   /v1/chapters/{chapter}/highlights — create highlight
|   update()  PUT    /v1/highlights/{highlight}        — update color or note
|   destroy() DELETE /v1/highlights/{highlight}        — delete highlight
|
| Dependencies: user_highlights, users
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HighlightController extends Controller
{
    private const VALID_COLORS = ['yellow', 'pink', 'blue', 'green'];

    /**
     * GET /v1/chapters/{chapterId}/highlights
     * Returns all highlights for the authenticated user in this chapter.
     */
    public function index(Request $request, int $chapterId)
    {
        $highlights = DB::table('user_highlights')
            ->where('user_id', $request->user()->id)
            ->where('chapter_id', $chapterId)
            ->orderBy('paragraph_index')
            ->orderBy('start_offset')
            ->get();

        return response()->json($highlights);
    }

    /**
     * POST /v1/chapters/{chapterId}/highlights
     * Body: { paragraph_index, start_offset, end_offset, color?, note? }
     */
    public function store(Request $request, int $chapterId)
    {
        $data = $request->validate([
            'paragraph_index' => 'required|integer|min:0',
            'start_offset'    => 'required|integer|min:0',
            'end_offset'      => 'required|integer|min:0',
            'color'           => 'sometimes|string|in:' . implode(',', self::VALID_COLORS),
            'note'            => 'nullable|string|max:500',
        ]);

        $id = DB::table('user_highlights')->insertGetId([
            'user_id'         => $request->user()->id,
            'chapter_id'      => $chapterId,
            'paragraph_index' => $data['paragraph_index'],
            'start_offset'    => $data['start_offset'],
            'end_offset'      => $data['end_offset'],
            'color'           => $data['color'] ?? 'yellow',
            'note'            => $data['note'] ?? null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(DB::table('user_highlights')->find($id), 201);
    }

    /**
     * PUT /v1/highlights/{highlightId}
     * Body: { color?, note? } — update color or annotation.
     */
    public function update(Request $request, int $highlightId)
    {
        $data = $request->validate([
            'color' => 'sometimes|string|in:' . implode(',', self::VALID_COLORS),
            'note'  => 'nullable|string|max:500',
        ]);

        $affected = DB::table('user_highlights')
            ->where('id', $highlightId)
            ->where('user_id', $request->user()->id)
            ->update(array_merge($data, ['updated_at' => now()]));

        if (! $affected) {
            return response()->json(['message' => 'Highlight not found.'], 404);
        }

        return response()->json(DB::table('user_highlights')->find($highlightId));
    }

    /**
     * DELETE /v1/highlights/{highlightId}
     */
    public function destroy(Request $request, int $highlightId)
    {
        $affected = DB::table('user_highlights')
            ->where('id', $highlightId)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $affected) {
            return response()->json(['message' => 'Highlight not found.'], 404);
        }

        return response()->json(['success' => true]);
    }
}
