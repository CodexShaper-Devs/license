<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->slug,
            'description' => $this->faker->paragraph,
            'version' => $this->faker->semver,
            'type' => $this->faker->randomElement(['software', 'service', 'plugin']),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'is_active' => true,
            'metadata' => [
                'author' => $this->faker->name,
                'website' => $this->faker->url,
                'support_email' => $this->faker->email,
            ],
            'settings' => [
                'max_seats' => $this->faker->numberBetween(1, 100),
                'trial_days' => $this->faker->numberBetween(7, 30),
                'requires_domain_validation' => $this->faker->boolean,
            ],
        ];
    }

    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }
}