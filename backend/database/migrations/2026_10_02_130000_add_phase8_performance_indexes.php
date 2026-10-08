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
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->index('created_at', 'idx_webhook_events_created_at');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index('created_at', 'idx_whatsapp_messages_created_at');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->index(['status', 'filled_at'], 'idx_work_orders_status_filled_at');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->index(['import_id', 'classification', 'id'], 'idx_import_rows_import_class_id');
        });

        Schema::table('driver_answers', function (Blueprint $table) {
            $table->index(['question_id', 'option_id', 'is_current'], 'idx_driver_answers_q_opt_curr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_answers', function (Blueprint $table) {
            $table->dropIndex('idx_driver_answers_q_opt_curr');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropIndex('idx_import_rows_import_class_id');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('idx_work_orders_status_filled_at');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('idx_whatsapp_messages_created_at');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex('idx_webhook_events_created_at');
        });
    }
};
