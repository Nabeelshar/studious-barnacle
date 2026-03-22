<?php
/*
|--------------------------------------------------------------------------
| TaskController
|--------------------------------------------------------------------------
| Manages Reading Tasks — CMS-defined activities that reward users with
| Gems (and optionally Coins) for engaging behaviour.
|
| Task types: read_chapters | library_add | daily_login | share
|
| Endpoints:
|   index()    GET  /v1/tasks          — list all active tasks with user progress
|   claim()    POST /v1/tasks/{id}/claim — claim reward for a completed task
|
| Progress is automatically incremented by other controllers:
|   - ChapterController::show()  → increments 'read_chapters' tasks
|   - UserController::addToLibrary() → increments 'library_add' tasks
|   - AuthController::login()    → increments 'daily_login' tasks
|
| This controller exposes the list + claim actions for Flutter.
|
| Dependencies: reading_tasks, user_task_progress, gem_transactions,
|   coin_transactions, users.gem_balance, users.coin_balance
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * GET /v1/tasks
     * Returns all active tasks merged with this user's progress.
     * For daily tasks: only today's progress row counts.
     */
    public function index(Request $request)
    {
        $user  = $request->user();
        $today = now()->toDateString();

        $tasks = DB::table('reading_tasks')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $taskIds = $tasks->pluck('id');

        // Fetch user progress: for daily tasks match today's date, others match null
        $progress = DB::table('user_task_progress')
            ->where('user_id', $user->id)
            ->whereIn('reading_task_id', $taskIds)
            ->where(function ($q) use ($today) {
                $q->whereNull('task_date')->orWhere('task_date', $today);
            })
            ->get()
            ->keyBy('reading_task_id');

        return response()->json($tasks->map(function ($task) use ($progress) {
            $p = $progress->get($task->id);
            return [
                'id'            => $task->id,
                'title'         => $task->title,
                'description'   => $task->description,
                'task_type'     => $task->task_type,
                'target_value'  => $task->target_value,
                'gem_reward'    => $task->gem_reward,
                'coin_reward'   => $task->coin_reward,
                'is_daily'      => (bool) $task->is_daily,
                'current_value' => $p ? (int) $p->current_value : 0,
                'is_completed'  => $p && $p->completed_at !== null,
                'is_claimed'    => $p && $p->claimed_at !== null,
            ];
        }));
    }

    /**
     * POST /v1/tasks/{taskId}/claim
     * Claims the gem+coin reward for a completed task.
     * Idempotent: returns 400 if already claimed.
     */
    public function claim(Request $request, int $taskId)
    {
        $user  = $request->user();
        $today = now()->toDateString();

        $task = DB::table('reading_tasks')
            ->where('id', $taskId)
            ->where('is_active', true)
            ->first();

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $progress = DB::table('user_task_progress')
            ->where('user_id', $user->id)
            ->where('reading_task_id', $taskId)
            ->where(function ($q) use ($today) {
                $q->whereNull('task_date')->orWhere('task_date', $today);
            })
            ->first();

        if (! $progress || ! $progress->completed_at) {
            return response()->json(['message' => 'Task not yet completed.'], 400);
        }

        if ($progress->claimed_at) {
            return response()->json(['message' => 'Reward already claimed.'], 400);
        }

        DB::transaction(function () use ($user, $task, $progress) {
            // Mark claimed
            DB::table('user_task_progress')
                ->where('id', $progress->id)
                ->update(['claimed_at' => now()]);

            // Grant gems
            if ($task->gem_reward > 0) {
                $user->increment('gem_balance', $task->gem_reward);

                DB::table('gem_transactions')->insert([
                    'user_id'       => $user->id,
                    'type'          => 'earned',
                    'amount'        => $task->gem_reward,
                    'balance_after' => $user->fresh()->gem_balance,
                    'description'   => "Task reward: {$task->title}",
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // Grant coins
            if ($task->coin_reward > 0) {
                $user->increment('coin_balance', $task->coin_reward);

                DB::table('coin_transactions')->insert([
                    'user_id'       => $user->id,
                    'type'          => 'bonus',
                    'amount'        => $task->coin_reward,
                    'balance_after' => $user->fresh()->coin_balance,
                    'description'   => "Task reward: {$task->title}",
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        });

        return response()->json([
            'success'         => true,
            'gems_earned'     => $task->gem_reward,
            'coins_earned'    => $task->coin_reward,
            'new_gem_balance' => $user->fresh()->gem_balance,
        ]);
    }

    // ── Used internally by other controllers ──────────────────────────

    /**
     * Static helper: increment progress for a given task type.
     * Call from ChapterController, UserController etc.
     * Usage: TaskController::incrementProgress($userId, 'read_chapters');
     */
    public static function incrementProgress(int $userId, string $taskType, int $by = 1): void
    {
        $today = now()->toDateString();

        $tasks = DB::table('reading_tasks')
            ->where('task_type', $taskType)
            ->where('is_active', true)
            ->get();

        foreach ($tasks as $task) {
            DB::table('user_task_progress')->updateOrInsert(
                [
                    'user_id'         => $userId,
                    'reading_task_id' => $task->id,
                    'task_date'       => $task->is_daily ? $today : null,
                ],
                [
                    'current_value' => DB::raw("current_value + {$by}"),
                    'completed_at'  => DB::raw(
                        "CASE WHEN current_value + {$by} >= {$task->target_value}
                              AND completed_at IS NULL
                              THEN NOW() ELSE completed_at END"
                    ),
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );
        }
    }
}
