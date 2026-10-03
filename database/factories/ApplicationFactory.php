<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'url' => 'https://'.Str::slug($name).'.lamajestueuse.cm',
            'type' => 'application',
            'icon' => 'grid',
            'color' => '#1d4ed8',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function quickLink(): static
    {
        return $this->state(fn () => ['type' => 'quick_link', 'opens_new_tab' => true]);
    }

    public function module(string $key = 'informations'): static
    {
        return $this->state(fn () => [
            'type' => 'module',
            'module_key' => $key,
            'url' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
