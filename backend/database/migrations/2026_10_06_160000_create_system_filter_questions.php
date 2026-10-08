<?php

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('questions', 'is_system')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->boolean('is_system')->default(false)->after('is_filter');
            });
        }

        // 1. WhatsApp Group system filter question
        $groupQuestion = Question::firstOrCreate(
            ['key' => 'whatsapp_group'],
            [
                'sort_order' => 90,
                'is_active' => false,
                'is_required' => false,
                'is_filter' => true,
                'is_system' => true,
                'type' => QuestionType::SINGLE_CHOICE,
            ]
        );
        $groupQuestion->update([
            'is_filter' => true,
            'is_system' => true,
        ]);

        if (! $groupQuestion->current_version_id) {
            $groupQuestion->recordVersion('جروب الواتساب التابع له السائق', QuestionType::SINGLE_CHOICE, []);
        }

        // 2. Driver Fleet Experience system filter question
        $expQuestion = Question::firstOrCreate(
            ['key' => 'driver_experience'],
            [
                'sort_order' => 91,
                'is_active' => false,
                'is_required' => false,
                'is_filter' => true,
                'is_system' => true,
                'type' => QuestionType::SINGLE_CHOICE,
            ]
        );
        $expQuestion->update([
            'is_filter' => true,
            'is_system' => true,
        ]);

        if (! $expQuestion->current_version_id) {
            $expQuestion->recordVersion('مستوى خبرة السائق بالأسطول', QuestionType::SINGLE_CHOICE, [
                ['value' => 'experienced_only', 'label_ar' => 'سائق ذو خبرة سابقة (نفّذ رحلات)', 'sort_order' => 1],
                ['value' => 'new_only', 'label_ar' => 'سائق جديد (لم ينفّذ رحلات بعد)', 'sort_order' => 2],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Question::whereIn('key', ['whatsapp_group', 'driver_experience'])->delete();

        if (Schema::hasColumn('questions', 'is_system')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn('is_system');
            });
        }
    }
};
