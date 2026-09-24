<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 32)->index();
            $table->boolean('is_active')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->string('group', 32)->index();
            $table->string('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percent')->default(100);
            $table->string('min_app_version', 32)->nullable();
            $table->string('platforms', 64)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 100)->index();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('meta')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title');
            $table->longText('body');
            $table->boolean('is_published')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32)->index();
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('admins');
    }
};
