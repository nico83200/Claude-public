<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(6), 'type' => 'stable', 'owner_id' => User::factory()];
    }
}
