<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('from_location');
            $table->string('to_location');
            $table->dateTime('scheduled_at')->index();
            $table->string('cargo_type')->nullable();
            $table->decimal('pay_amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('trips_needed')->default(1);
            $table->unsignedInteger('backup_needed')->default(0);
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedInteger('confirmed_count')->default(0);
            $table->unsignedInteger('backup_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->dateTime('filled_at')->nullable();
            $table->string('send_strategy', 32)->default('all');
            $table->unsignedInteger('batch_size')->nullable();
            $table->unsignedInteger('batch_interval_minutes')->nullable();
            $table->boolean('exclude_same_day_confirmed')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('operator', 16);
            $table->json('value');
            $table->timestamps();

            $table->index(['work_order_id', 'question_id']);
        });

        Schema::create('work_order_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('sequence_number')->nullable();
            $table->unsignedInteger('stage_batch')->default(1);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['work_order_id', 'driver_id']);
            $table->index(['work_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_recipients');
        Schema::dropIfExists('work_order_filters');
        Schema::dropIfExists('work_orders');
    }
};
