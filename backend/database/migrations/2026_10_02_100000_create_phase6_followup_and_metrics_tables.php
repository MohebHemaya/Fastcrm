<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Driver Metrics Table
        Schema::create('driver_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')
                ->unique()
                ->constrained('drivers')
                ->cascadeOnDelete();
            $table->unsignedInteger('trips_completed')->default(0);
            $table->unsignedInteger('cancellations')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->dateTime('last_trip_at')->nullable();
            $table->timestamps();

            $table->index('avg_rating');
            $table->index('no_shows');
            $table->index('trips_completed');
            $table->index('last_trip_at');
        });

        // 2. Work Order Events Timeline Table
        Schema::create('work_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')
                ->constrained('work_orders')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('drivers')
                ->nullOnDelete();
            $table->string('action', 64);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'created_at']);
            $table->index('action');
        });

        // 3. Extend work_orders with Phase 6 system filters & metadata
        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('min_avg_rating', 3, 2)->nullable()->after('exclude_same_day_confirmed');
            $table->unsignedInteger('max_no_shows')->nullable()->after('min_avg_rating');
            $table->unsignedInteger('last_trip_within_days')->nullable()->after('max_no_shows');
            $table->boolean('include_unrated')->default(false)->after('last_trip_within_days');
            $table->text('cancellation_reason')->nullable()->after('include_unrated');
        });

        // 4. Extend work_order_recipients with outcomes, ratings, cancellation, and reminders
        Schema::table('work_order_recipients', function (Blueprint $table) {
            $table->string('outcome', 32)->nullable()->after('responded_at');
            $table->unsignedTinyInteger('rating')->nullable()->after('outcome');
            $table->text('outcome_notes')->nullable()->after('rating');
            $table->dateTime('outcome_recorded_at')->nullable()->after('outcome_notes');
            $table->foreignId('outcome_recorded_by')
                ->nullable()
                ->after('outcome_recorded_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('outcome_recorded_by');
            $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
            $table->dateTime('reminder_sent_at')->nullable()->after('cancelled_at');

            $table->index(['work_order_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::table('work_order_recipients', function (Blueprint $table) {
            $table->dropForeign(['outcome_recorded_by']);
            $table->dropIndex(['work_order_id', 'outcome']);
            $table->dropColumn([
                'outcome',
                'rating',
                'outcome_notes',
                'outcome_recorded_at',
                'outcome_recorded_by',
                'cancellation_reason',
                'cancelled_at',
                'reminder_sent_at',
            ]);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'min_avg_rating',
                'max_no_shows',
                'last_trip_within_days',
                'include_unrated',
                'cancellation_reason',
            ]);
        });

        Schema::dropIfExists('work_order_events');
        Schema::dropIfExists('driver_metrics');
    }
};
