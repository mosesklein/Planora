<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Stop;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a Test Company
        $company = Company::create([
            'name' => 'Brooklyn School District',
            'slug' => 'brooklyn-sd',
        ]);

        // 2. Create 20 Random Stops in Brooklyn
        for ($i = 1; $i <= 20; $i++) {
            Stop::create([
                'company_id' => $company->id,
                'name' => "Stop #{$i} - Brooklyn Corner",
                'lat' => 40.6782 + (rand(-100, 100) / 10000),
                'lng' => -73.9442 + (rand(-100, 100) / 10000),
            ]);
        }
    }
}