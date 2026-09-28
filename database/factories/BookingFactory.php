<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $service = Service::query()->inRandomOrder()->first() ?? Service::factory()->create();
        $user = User::query()->inRandomOrder()->first() ?? User::factory()->create();

        return [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'provider_id' => $service->provider_id,
            'booking_date' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'booking_time' => $this->faker->time('H:i:s'),
            'total_price' => $service->price,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'completed', 'cancelled']),
            'notes' => $this->faker->sentence(),
        ];
    }
}
