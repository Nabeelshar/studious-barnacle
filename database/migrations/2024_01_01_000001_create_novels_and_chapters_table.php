<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Authors
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pen_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->char('country', 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_verified')->default(false);
            $table->enum('contract_status', ['pending', 'signed', 'expired'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        // Novels
        Schema::create('novels', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->string('cover_url')->nullable();
            $table->text('synopsis')->nullable();
            $table->enum('status', ['ongoing', 'completed', 'hiatus', 'dropped'])->default('ongoing');
            $table->string('genre');
            $table->json('tags')->nullable();
            $table->string('language')->default('en');
            $table->string('original_language')->default('zh');
            $table->unsignedInteger('total_chapters')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('power_stones')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_vip')->default(false);
            $table->boolean('has_early_access')->default(false);
            $table->string('series_name')->nullable();
            $table->unsignedTinyInteger('series_order')->nullable();
            $table->unsignedTinyInteger('series_total')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['genre', 'rating']);
            $table->index('is_featured');
        });

        // Chapters
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('word_count')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->unsignedTinyInteger('coin_price')->default(1);
            $table->boolean('is_early_access')->default(false);
            $table->boolean('is_wait_free')->default(false);
            $table->unsignedTinyInteger('wait_free_hours')->default(4);
            $table->unsignedBigInteger('views')->default(0);
            $table->foreignId('translator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['novel_id', 'number']);
            $table->index(['novel_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('novels');
        Schema::dropIfExists('authors');
    }
};
