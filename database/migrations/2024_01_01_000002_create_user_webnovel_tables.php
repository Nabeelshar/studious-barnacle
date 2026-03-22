<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend users table with webnovel fields
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('coin_balance')->default(0)->after('password');
            $table->unsignedInteger('total_spent')->default(0)->after('coin_balance');
            $table->enum('vip_tier', ['none', 'Bronze', 'Silver', 'Gold', 'Diamond'])
                ->default('none')->after('total_spent');
            $table->boolean('is_banned')->default(false)->after('vip_tier');
            $table->date('last_checkin_date')->nullable()->after('is_banned');
            $table->unsignedTinyInteger('checkin_streak')->default(0)->after('last_checkin_date');
        });

        // Coin transactions
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['purchase', 'spend', 'bonus', 'refund', 'daily_checkin']);
            $table->integer('amount');           // + credit / - debit
            $table->unsignedInteger('balance_after')->default(0);
            $table->string('description')->nullable();
            $table->foreignId('novel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('package_id')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // User library (bookmarks)
        Schema::create('user_novels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_chapter_id')->nullable()->constrained('chapters')->nullOnDelete();
            $table->unsignedInteger('last_chapter_number')->default(0);
            $table->boolean('is_bookmarked')->default(false);
            $table->boolean('notifications_on')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'novel_id']);
        });

        // Unlocked chapters
        Schema::create('user_chapter_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('coins_spent')->default(0);
            $table->timestamp('unlocked_at')->useCurrent();

            $table->unique(['user_id', 'chapter_id']);
        });

        // Reading history
        Schema::create('reading_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });

        // Power stone votes
        Schema::create('power_stone_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stones')->default(1);
            $table->timestamp('voted_at')->useCurrent();

            $table->index(['novel_id', 'voted_at']);
        });

        // Reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');  // 1-5
            $table->text('content')->nullable();
            $table->unsignedInteger('likes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'novel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('power_stone_votes');
        Schema::dropIfExists('reading_history');
        Schema::dropIfExists('user_chapter_unlocks');
        Schema::dropIfExists('user_novels');
        Schema::dropIfExists('coin_transactions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['coin_balance', 'total_spent', 'vip_tier', 'is_banned', 'last_checkin_date', 'checkin_streak']);
        });
    }
};
