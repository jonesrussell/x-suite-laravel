<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JonesRussell\XSuite\Enums\XPostStatus;
use JonesRussell\XSuite\Models\XPost;

/**
 * @extends Factory<XPost>
 */
class XPostFactory extends Factory
{
    protected $model = XPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => $this->faker->text(280),
            'status' => XPostStatus::Draft,
            'user_id' => function () {
                $userModel = config('x-suite.user_model', 'App\\Models\\User');

                return $userModel::factory()->create()->id;
            },
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XPostStatus::Draft,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XPostStatus::Scheduled,
            'scheduled_for' => now()->addHours($this->faker->numberBetween(1, 48)),
        ]);
    }

    public function readyToPublish(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XPostStatus::Scheduled,
            'scheduled_for' => now()->subMinutes($this->faker->numberBetween(1, 30)),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XPostStatus::Published,
            'published_at' => now()->subHours($this->faker->numberBetween(1, 24)),
            'x_post_id' => (string) $this->faker->numerify('####################'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XPostStatus::Failed,
            'error_message' => 'Failed to connect to X API',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XPostStatus::Cancelled,
        ]);
    }

    public function withThread(): static
    {
        return $this->state(fn (array $attributes) => [
            'thread_parts' => [
                $this->faker->text(280),
                $this->faker->text(280),
                $this->faker->text(280),
            ],
        ]);
    }

    public function withMedia(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_urls' => [
                'images/image1.jpg',
                'images/image2.jpg',
            ],
        ]);
    }
}
