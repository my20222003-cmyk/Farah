<?php

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = User::query()->pluck('id')->all();
        $serviceIds = Service::query()->pluck('id')->all();

        $pairs = [];
        foreach ($userIds as $userId) {
            foreach ($serviceIds as $serviceId) {
                $pairs[] = [$userId, $serviceId];
            }
        }

        shuffle($pairs);
        $pairs = array_slice($pairs, 0, min(15, count($pairs)));

        foreach ($pairs as [$userId, $serviceId]) {
            Favorite::factory()->create([
                'user_id' => $userId,
                'service_id' => $serviceId,
            ]);
        }
    }
}