<?php
/*
|--------------------------------------------------------------------------
| API Routes — Webnovel Reader App (Flutter)
|--------------------------------------------------------------------------
| Sections:
|   1. Imports
|   2. Public routes   (v1, no auth)
|   3. Auth routes     (v1, sanctum middleware)
|      - Auth, Library, Progress, Wallet
|      - Unlock (wait-free, bulk, ad, auto-sub)
|      - Gems
|      - Tasks
|      - Chapter comments & highlights
|      - Promo codes
|   4. Rankings (public)
*/

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NovelController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\UnlockController;
use App\Http\Controllers\Api\GemController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ChapterCommentController;
use App\Http\Controllers\Api\HighlightController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\RankingController;
use Illuminate\Support\Facades\Route;

// ── Public routes ──────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/register',        [AuthController::class, 'register']);
    Route::post('/auth/login',           [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password',  [AuthController::class, 'resetPassword']);

    // Novel browsing (no auth required)
    Route::get('/novels',              [NovelController::class, 'index']);
    Route::get('/novels/featured',     [NovelController::class, 'featured']);
    Route::get('/novels/trending',     [NovelController::class, 'trending']);
    Route::get('/novels/new-arrivals', [NovelController::class, 'newArrivals']);
    Route::get('/novels/{novel}',      [NovelController::class, 'show']);
    Route::get('/novels/{novel}/chapters', [ChapterController::class, 'index']);
    Route::get('/novels/{novel}/reviews',  [NovelController::class, 'reviews']);
    Route::get('/novels/{novel}/similar',  [NovelController::class, 'similar']);

    // Free chapter preview
    Route::get('/chapters/{chapter}/preview', [ChapterController::class, 'preview']);

    // Genres
    Route::get('/genres', fn () => response()->json(\App\Models\Novel::distinct()->pluck('genre')->filter()->values()));

    // Search
    Route::get('/search', [NovelController::class, 'search']);

    // Authors
    Route::get('/authors',                 [AuthorController::class, 'index']);
    Route::get('/authors/{author}',        [AuthorController::class, 'show']);
    Route::get('/authors/{author}/novels', [AuthorController::class, 'novels']);

    // Rankings (public — no auth needed to browse)
    Route::get('/rankings', [RankingController::class, 'index']);

    // Novel gem totals (public social proof)
    Route::get('/gems/novel/{novelId}', [GemController::class, 'novelGifts']);
});

// ── Authenticated routes ───────────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // ── Full chapter reading ──────────────────────────────────────────
    Route::get('/chapters/{chapter}', [ChapterController::class, 'show']);

    // ── User library ──────────────────────────────────────────────────
    Route::get('/library',                [UserController::class, 'library']);
    Route::post('/library/add',           [UserController::class, 'addToLibrary']);
    Route::delete('/library/{novel}',     [UserController::class, 'removeFromLibrary']);
    Route::get('/library/{novel}/status', [UserController::class, 'libraryStatus']);
    Route::get('/history',                [UserController::class, 'history']);
    Route::post('/history',               [UserController::class, 'recordHistory']);

    // ── Reading progress ──────────────────────────────────────────────
    Route::get('/progress/{novel}',  [UserController::class, 'progress']);
    Route::post('/progress',         [UserController::class, 'updateProgress']);

    // ── Wallet & coins ────────────────────────────────────────────────
    Route::get('/wallet',                 [WalletController::class, 'index']);
    Route::get('/wallet/transactions',    [WalletController::class, 'transactions']);
    Route::post('/wallet/unlock-chapter',  [WalletController::class, 'unlockChapter']);
    Route::post('/wallet/gift-novel',      [WalletController::class, 'giftNovel']);
    Route::post('/wallet/daily-checkin',   [WalletController::class, 'dailyCheckin']);
    Route::post('/wallet/purchase-coins',  [WalletController::class, 'purchaseCoins']);

    // ── Reviews ───────────────────────────────────────────────────────
    Route::post('/novels/{novel}/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'store']);
    Route::delete('/reviews/{review}',     [\App\Http\Controllers\Api\ReviewController::class, 'destroy']);

    // ── Power stones ──────────────────────────────────────────────────
    Route::post('/novels/{novel}/vote', [NovelController::class, 'vote']);

    // ── Unlock strategies ─────────────────────────────────────────────
    Route::post('/unlock/start-timer', [UnlockController::class, 'startTimer']);
    Route::get('/unlock/timers',       [UnlockController::class, 'timers']);     // ?novel_id=
    Route::post('/unlock/claim-free',  [UnlockController::class, 'claimFree']);
    Route::post('/unlock/bulk',        [UnlockController::class, 'bulkUnlock']);
    Route::post('/unlock/ad-claim',    [UnlockController::class, 'adClaim']);
    Route::get('/unlock/ad-status',    [UnlockController::class, 'adStatus']);
    Route::post('/unlock/auto-sub/{novelId}', [UnlockController::class, 'toggleAutoSub']);
    Route::get('/unlock/auto-subs',    [UnlockController::class, 'autoSubs']);

    // ── Gems ──────────────────────────────────────────────────────────
    Route::get('/gems',               [GemController::class, 'balance']);
    Route::get('/gems/transactions',  [GemController::class, 'transactions']);
    Route::post('/gems/gift',         [GemController::class, 'giftToNovel']);

    // ── Reading tasks ─────────────────────────────────────────────────
    Route::get('/tasks',                    [TaskController::class, 'index']);
    Route::post('/tasks/{taskId}/claim',    [TaskController::class, 'claim']);

    // ── Chapter comments ──────────────────────────────────────────────
    Route::get('/chapters/{chapterId}/comments',  [ChapterCommentController::class, 'index']);
    Route::post('/chapters/{chapterId}/comments', [ChapterCommentController::class, 'store']);
    Route::get('/comments/{commentId}/replies',   [ChapterCommentController::class, 'replies']);
    Route::delete('/comments/{commentId}',         [ChapterCommentController::class, 'destroy']);
    Route::post('/comments/{commentId}/like',      [ChapterCommentController::class, 'like']);

    // ── Paragraph highlights ──────────────────────────────────────────
    Route::get('/chapters/{chapterId}/highlights',  [HighlightController::class, 'index']);
    Route::post('/chapters/{chapterId}/highlights', [HighlightController::class, 'store']);
    Route::put('/highlights/{highlightId}',         [HighlightController::class, 'update']);
    Route::delete('/highlights/{highlightId}',      [HighlightController::class, 'destroy']);

    // ── Promo codes ───────────────────────────────────────────────────
    Route::post('/promo/redeem', [PromoController::class, 'redeem']);
});

