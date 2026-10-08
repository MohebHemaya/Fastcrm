<?php

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
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, integer, boolean, json
            $table->string('description_ar')->nullable();
            $table->timestamps();
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->unsignedInteger('questionnaire_retries')->default(0)->after('notes');
            $table->timestamp('questionnaire_last_sent_at')->nullable()->after('questionnaire_retries');
            $table->timestamp('questionnaire_reminder_sent_at')->nullable()->after('questionnaire_last_sent_at');
            $table->boolean('needs_review')->default(false)->after('questionnaire_reminder_sent_at');
            $table->string('review_reason')->nullable()->after('needs_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'questionnaire_retries',
                'questionnaire_last_sent_at',
                'questionnaire_reminder_sent_at',
                'needs_review',
                'review_reason',
            ]);
        });

        Schema::dropIfExists('settings');
    }
};
