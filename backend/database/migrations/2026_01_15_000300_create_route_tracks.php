<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Opt-in: routes appear on the public map only when the user turns this on.
            $table->boolean('share_route')->default(false)->after('leaderboard_visible');
            $table->string('route_color', 7)->nullable()->after('share_route');
        });

        // Short-lived by design: a track is shown for 24 h after its last point, then deleted.
        Schema::create('route_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('walking_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('color', 7);
            $table->json('points'); // [[lat, lng], ...] after trimming and simplification
            $table->unsignedSmallInteger('point_count');
            $table->decimal('min_lat', 9, 6);
            $table->decimal('max_lat', 9, 6);
            $table->decimal('min_lng', 9, 6);
            $table->decimal('max_lng', 9, 6);
            $table->dateTime('first_point_at');
            $table->dateTime('last_point_at');
            $table->dateTime('visible_until')->index();
            $table->boolean('published')->default(false);
            $table->timestamps();

            $table->index(['published', 'visible_until', 'min_lat', 'max_lat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_tracks');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['share_route', 'route_color']));
    }
};
