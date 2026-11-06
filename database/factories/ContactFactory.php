<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->name(),
            'company' => fake()->company(),
            'phone' => '6019'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'whatsapp_opt_in_at' => now(),
        ];
    }
}
