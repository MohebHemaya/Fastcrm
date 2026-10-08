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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->index('created_at', 'idx_work_orders_created_at');
        });

        Schema::table('driver_group', function (Blueprint $table) {
            $table->index('group_id', 'idx_driver_group_group_id');
        });

        Schema::table('work_order_recipients', function (Blueprint $table) {
            $table->index('sent_at', 'idx_work_order_recipients_sent_at');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->index('created_at', 'idx_drivers_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex('idx_drivers_created_at');
        });

        Schema::table('work_order_recipients', function (Blueprint $table) {
            $table->dropIndex('idx_work_order_recipients_sent_at');
        });

        Schema::table('driver_group', function (Blueprint $table) {
            $table->dropIndex('idx_driver_group_group_id');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('idx_work_orders_created_at');
        });
    }
};
