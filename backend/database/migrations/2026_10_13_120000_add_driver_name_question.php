<?php

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reorder subsequent onboarding questions so driver_name takes order 1
        $subsequentOrders = [
            'vehicle_type' => 2,
            'has_refrigeration' => 3,
            'years_experience' => 4,
            'daily_availability' => 5,
            'preferred_routes' => 6,
        ];

        foreach ($subsequentOrders as $key => $order) {
            Question::where('key', $key)->update(['sort_order' => $order]);
        }

        // Create or update driver_name question
        $nameQuestion = Question::firstOrCreate(
            ['key' => 'driver_name'],
            [
                'sort_order' => 1,
                'is_active' => true,
                'is_required' => true,
                'is_filter' => false,
                'type' => QuestionType::TEXT,
            ]
        );

        $nameQuestion->update([
            'sort_order' => 1,
            'is_active' => true,
            'is_required' => true,
            'is_filter' => false,
            'type' => QuestionType::TEXT,
        ]);

        if (! $nameQuestion->current_version_id) {
            $nameQuestion->recordVersion('ما هو اسمك بالكامل يا كابتن؟', QuestionType::TEXT, []);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $orders = [
            'vehicle_type' => 1,
            'has_refrigeration' => 2,
            'years_experience' => 3,
            'daily_availability' => 4,
            'preferred_routes' => 5,
        ];

        foreach ($orders as $key => $order) {
            Question::where('key', $key)->update(['sort_order' => $order]);
        }

        Question::where('key', 'driver_name')->delete();
    }
};
