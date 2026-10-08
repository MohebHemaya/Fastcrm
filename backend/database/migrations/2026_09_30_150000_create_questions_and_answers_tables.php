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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_filter')->default(false);
            $table->string('type', 30); // single_choice, yes_no, number, text
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->text('text_ar');
            $table->string('type', 30);
            $table->json('options_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['question_id', 'version']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('question_versions')
                ->nullOnDelete();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('value');
            $table->string('label_ar');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['question_id', 'is_active', 'sort_order']);
        });

        Schema::create('driver_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('question_version_id')->constrained('question_versions')->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('question_options')->nullOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 10, 2)->nullable();
            $table->boolean('is_current')->default(true);
            $table->boolean('is_skipped')->default(false);
            $table->timestamp('answered_at')->useCurrent();
            $table->timestamps();

            $table->index(['driver_id', 'question_id', 'is_current']);
            $table->index(['question_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_answers');
        Schema::dropIfExists('question_options');

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('question_versions');
        Schema::dropIfExists('questions');
    }
};
