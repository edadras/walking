<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('walking_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path');
            $table->string('thumb_path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('caption', 200)->nullable();
            $table->dateTime('captured_at');
            // pending (waiting for its walk to be verified) · published · hidden (reports / admin) · rejected (walk not verified)
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('qualified_views')->default(0);
            $table->unsignedInteger('qualified_likes')->default(0);
            $table->unsignedSmallInteger('reports_count')->default(0);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['user_id', 'captured_at']);
        });

        foreach (['post_likes', 'post_views'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->foreignId('post_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->boolean('qualified')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->primary(['post_id', 'user_id']);
                $table->index('user_id');
            });
        }

        Schema::create('post_reports', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 32);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reports');
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('posts');
    }
};
