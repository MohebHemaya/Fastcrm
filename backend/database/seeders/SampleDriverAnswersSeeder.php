<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use Illuminate\Database\Seeder;

class SampleDriverAnswersSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = Driver::where('status', 'active')->get();
        if ($drivers->isEmpty()) {
            return;
        }

        $questions = Question::with(['currentVersion', 'activeOptions'])->get()->keyBy('key');

        $vTypes = ['pickup', 'jumbo', 'van', 'truck'];
        $availabilities = ['morning', 'evening', 'any'];

        foreach ($drivers as $idx => $driver) {
            // 0. driver_name
            if (isset($questions['driver_name'])) {
                $q = $questions['driver_name'];
                DriverAnswer::updateOrCreate(
                    ['driver_id' => $driver->id, 'question_id' => $q->id],
                    [
                        'question_version_id' => $q->current_version_id,
                        'value_text' => $driver->name ?: 'كابتن '.($idx + 1),
                        'is_current' => true,
                        'is_skipped' => false,
                        'answered_at' => now(),
                    ]
                );
            }

            // 1. vehicle_type
            if (isset($questions['vehicle_type'])) {
                $q = $questions['vehicle_type'];
                $val = $vTypes[$idx % count($vTypes)];
                $opt = $q->activeOptions->firstWhere('value', $val);
                DriverAnswer::updateOrCreate(
                    ['driver_id' => $driver->id, 'question_id' => $q->id],
                    [
                        'question_version_id' => $q->current_version_id,
                        'option_id' => $opt?->id,
                        'value_text' => $val,
                        'is_current' => true,
                        'is_skipped' => false,
                        'answered_at' => now(),
                    ]
                );
            }

            // 2. has_refrigeration
            if (isset($questions['has_refrigeration'])) {
                $q = $questions['has_refrigeration'];
                $val = ($idx % 3 === 0) ? 'yes' : 'no'; // 1 in 3 drivers has refrigeration
                DriverAnswer::updateOrCreate(
                    ['driver_id' => $driver->id, 'question_id' => $q->id],
                    [
                        'question_version_id' => $q->current_version_id,
                        'value_text' => $val,
                        'is_current' => true,
                        'is_skipped' => false,
                        'answered_at' => now(),
                    ]
                );
            }

            // 3. daily_availability
            if (isset($questions['daily_availability'])) {
                $q = $questions['daily_availability'];
                $val = $availabilities[$idx % count($availabilities)];
                $opt = $q->activeOptions->firstWhere('value', $val);
                DriverAnswer::updateOrCreate(
                    ['driver_id' => $driver->id, 'question_id' => $q->id],
                    [
                        'question_version_id' => $q->current_version_id,
                        'option_id' => $opt?->id,
                        'value_text' => $val,
                        'is_current' => true,
                        'is_skipped' => false,
                        'answered_at' => now(),
                    ]
                );
            }

            // 4. years_experience
            if (isset($questions['years_experience'])) {
                $q = $questions['years_experience'];
                $exp = ($idx % 10) + 1;
                DriverAnswer::updateOrCreate(
                    ['driver_id' => $driver->id, 'question_id' => $q->id],
                    [
                        'question_version_id' => $q->current_version_id,
                        'value_number' => $exp,
                        'value_text' => (string) $exp,
                        'is_current' => true,
                        'is_skipped' => false,
                        'answered_at' => now(),
                    ]
                );
            }
        }
    }
}
