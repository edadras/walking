<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('category', 16);
            $table->string('subject', 150);
            $table->string('status', 24)->default('open');
            $table->string('priority', 8)->default('normal');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            // Optional link to what the ticket is about (an order, a session…).
            $table->string('subject_type', 32)->nullable();
            $table->string('subject_ref', 32)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_message_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'last_message_at']);
            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 8);
            $table->unsignedBigInteger('author_id')->nullable();
            $table->text('body');
            // Internal notes are only visible to staff.
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index('support_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
