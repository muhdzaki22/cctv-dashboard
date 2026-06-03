<?php

namespace Database\Seeders;

use App\Models\FootTrafficRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FootTrafficSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('foot_traffic_records')->truncate();

        // Generate data for the past 120 days
        $startDate = now()->subDays(120);
        $currentDate = clone $startDate;

        while ($currentDate <= now()) {
            $isWeekend = $currentDate->isWeekend();
            $dateStr = $currentDate->format('Y-m-d');

            // Traffic patterns by hour (store typically open 8 AM - 10 PM)
            // Key: hour (0-23), Value: traffic multiplier
            $hourlyMultipliers = [
                0 => 0.0, 1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.1, 7 => 0.3,
                8 => 0.6, 9 => 0.8, 10 => 1.2, 11 => 1.5, 12 => 1.8, 13 => 1.6, 14 => 1.4, 15 => 1.3,
                16 => 1.5, 17 => 1.8, 18 => 2.0, 19 => 1.7, 20 => 1.3, 21 => 0.8, 22 => 0.4, 23 => 0.1
            ];

            // Weekend adjustments
            if ($isWeekend) {
                $hourlyMultipliers = [
                    0 => 0.0, 1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.1, 7 => 0.2,
                    8 => 0.4, 9 => 0.6, 10 => 1.0, 11 => 1.3, 12 => 1.5, 13 => 1.4, 14 => 1.2, 15 => 1.1,
                    16 => 1.3, 17 => 1.5, 18 => 1.7, 19 => 1.6, 20 => 1.2, 21 => 0.6, 22 => 0.3, 23 => 0.1
                ];
            }

            // Base daily count (varies by day of week)
            $baseCount = $isWeekend
                ? rand(150, 250)  // Weekend: lower traffic
                : rand(200, 350); // Weekday: higher traffic

            // Add some seasonality
            $seasonality = sin($currentDate->dayOfYear / 58); // Yearly cycle
            $baseCount = (int)($baseCount * (1 + $seasonality * 0.2));

            foreach ($hourlyMultipliers as $hour => $multiplier) {
                if ($multiplier === 0) continue;

                // Calculate total for this hour
                $hourBase = ($baseCount * $multiplier) / 24;
                $total = max(0, (int)($hourBase + rand(-10, 10)));

                if ($total === 0) continue;

                // Split into male/female (roughly 50/50 with variation)
                $maleRatio = rand(45, 55) / 100;
                $maleCount = (int)($total * $maleRatio);
                $femaleCount = $total - $maleCount;

                FootTrafficRecord::create([
                    'date' => $dateStr,
                    'hour' => $hour,
                    'male_count' => $maleCount,
                    'female_count' => $femaleCount,
                    'total_count' => $total,
                ]);
            }

            $currentDate->addDay();
        }

        $this->command->info('Foot traffic data generated successfully!');
        $this->command->info('Generated ' . FootTrafficRecord::count() . ' records.');
    }
}
