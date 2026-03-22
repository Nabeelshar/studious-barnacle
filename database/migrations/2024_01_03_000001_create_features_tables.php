<?php
/*
|--------------------------------------------------------------------------
| Feature Tables Migration
|--------------------------------------------------------------------------
| Creates all tables for the new GoodNovel-inspired features:
|   - wait_for_free_timers    : per-user countdown to free chapter unlock
|   - user_auto_subscriptions : auto-purchase next chapter on release
|   - promo_codes             : exchange codes for bonus coins
|   - promo_code_redemptions  : tracks who used which code
|   - gem_transactions        : log of gem credits/debits
|   - novel_gem_gifts         : gems gifted by users to novels (ranking fuel)
|   - reading_tasks           : CMS-defined tasks (e.g. "read 5 chapters")
|   - user_task_progress      : per-user progress + completion record
|   - chapter_comments        : in-chapter discussion (not novel-level reviews)
|   - user_highlights         : paragraph highlights & reader notes
|   - user_ad_unlocks         : daily ad-watch unlock log (rate-limiting)
| Also adds:
|   - users.gem_balance       : gem currency balance
|   - users.bonus_coins_claimed: one-time signup bonus flag
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Add gems + signup bonus flag to users ──────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('gem_balance')->default(0)->after('coin_balance');
            $table->boolean('bonus_coins_claimed')->default(false)->after('gem_balance');
        });

        // ── Wait-for-Free timers ───────────────────────────────────────
        // Created when user first views a locked chapter.
        // available_at = created_at + hours defined in novel/chapter settings.
        Schema::create('wait_for_free_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->timestamp('available_at');       // when the chapter becomes free
            $table->timestamp('claimed_at')->nullable(); // null = not yet claimed
            $table->timestamps();

            $table->unique(['user_id', 'chapter_id']);
            $table->index(['user_id', 'available_at']);
        });

        // ── Auto-subscriptions ─────────────────────────────────────────
        // User can set auto-purchase on a novel; new chapters deduct coins automatically.
        Schema::create('user_auto_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('max_coins_per_chapter')->default(3); // safety cap
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'novel_id']);
        });

        // ── Promo / Exchange codes ─────────────────────────────────────
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedInteger('coin_value')->default(0);
            $table->unsignedInteger('gem_value')->default(0);
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->timestamp('redeemed_at')->useCurrent();

            $table->unique(['user_id', 'promo_code_id']); // one use per user per code
        });

        // ── Gem transactions ───────────────────────────────────────────
        Schema::create('gem_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['earned', 'gifted', 'spent', 'bonus']);
            $table->integer('amount');            // positive = credit, negative = debit
            $table->unsignedInteger('balance_after')->default(0);
            $table->string('description')->nullable();
            $table->foreignId('novel_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // ── Novel gem gifts (drives ranking) ──────────────────────────
        Schema::create('novel_gem_gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('gems');
            $table->timestamps();

            $table->index(['novel_id', 'created_at']); // for ranking queries
        });

        // ── Reading tasks ──────────────────────────────────────────────
        // task_type: 'read_chapters' | 'library_add' | 'daily_login' | 'share'
        Schema::create('reading_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('task_type', 32);
            $table->unsignedInteger('target_value')->default(1); // e.g., read 5 chapters
            $table->unsignedInteger('gem_reward')->default(10);
            $table->unsignedInteger('coin_reward')->default(0);
            $table->boolean('is_daily')->default(false); // resets every day
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reading_task_id')->constrained('reading_tasks')->cascadeOnDelete();
            $table->unsignedInteger('current_value')->default(0);
            $table->timestamp('completed_at')->nullable();  // null = in progress
            $table->timestamp('claimed_at')->nullable();    // null = reward not yet claimed
            $table->date('task_date')->nullable();          // set for daily tasks
            $table->timestamps();

            $table->unique(['user_id', 'reading_task_id', 'task_date']);
        });

        // ── Per-chapter comments ───────────────────────────────────────
        // Separate from novel-level reviews. Supports one level of threading (replies).
        Schema::create('chapter_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()
                  ->constrained('chapter_comments')->nullOnDelete(); // reply support
            $table->text('content');
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('paragraph_index')->nullable(); // pin to paragraph
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chapter_id', 'created_at']);
        });

        // ── Paragraph highlights & notes ──────────────────────────────
        Schema::create('user_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('paragraph_index');
            $table->unsignedSmallInteger('start_offset')->default(0);
            $table->unsignedSmallInteger('end_offset')->default(0);
            $table->string('color', 16)->default('yellow'); // yellow|pink|blue|green
            $table->text('note')->nullable();               // optional annotation
            $table->timestamps();

            $table->index(['user_id', 'chapter_id']);
        });

        // ── Ad-watch unlock log ────────────────────────────────────────
        // Each row = one ad watched. Daily limit enforced by counting today's rows.
        Schema::create('user_ad_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->date('watched_date');   // local date for daily cap
            $table->timestamps();

            $table->index(['user_id', 'watched_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ad_unlocks');
        Schema::dropIfExists('user_highlights');
        Schema::dropIfExists('chapter_comments');
        Schema::dropIfExists('user_task_progress');
        Schema::dropIfExists('reading_tasks');
        Schema::dropIfExists('novel_gem_gifts');
        Schema::dropIfExists('gem_transactions');
        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('user_auto_subscriptions');
        Schema::dropIfExists('wait_for_free_timers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gem_balance', 'bonus_coins_claimed']);
        });
    }
};
