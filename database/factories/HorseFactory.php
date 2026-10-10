<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HorseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'official_name' => strtoupper(fake()->unique()->firstName()).' '.fake()->randomElement(['DU BOIS', 'DE LA PLAINE', "D'ESTRAN", 'Z', 'DES PINS']),
            'sex' => fake()->randomElement(['male', 'female', 'gelding']),
            'birth_year' => fake()->numberBetween(2005, 2021),
            'coat' => fake()->randomElement(['Bai', 'Alezan', 'Gris', 'Noir', 'Bai brun']),
        ];
    }
}
