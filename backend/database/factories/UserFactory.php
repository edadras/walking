<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone' => '+989'.fake()->unique()->numerify('#########'),
            'phone_verified_at' => now(),
            'display_name' => fake()->firstName(),
            'status' => UserStatus::Active,
            'referral_code' => strtoupper(Str::random(7)),
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! $user->profile()->exists()) {
                $user->profile()->create(['daily_step_goal' => 7500, 'water_goal_ml' => 2000]);
            }
        });
    }

    public function status(UserStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
