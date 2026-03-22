<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Novel;

class AuthorController extends Controller
{
    public function show(Author $author)
    {
        return response()->json(array_merge($author->toArray(), [
            'novel_count'     => $author->novels()->count(),
            'follower_count'  => 0, // placeholder — add followers table later
        ]));
    }

    public function novels(Author $author)
    {
        return response()->json(
            Novel::with('author')
                ->where('author_id', $author->id)
                ->published()
                ->orderByDesc('views')
                ->get()
        );
    }

    public function index()
    {
        return response()->json(
            Author::orderBy('name')->paginate(20)
        );
    }
}
