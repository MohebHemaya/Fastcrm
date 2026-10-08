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
            $table->foreignId('target_group_id')
                ->nullable()
                ->after('include_unrated')
                ->constrained('whatsapp_groups')
                ->nullOnDelete();
            $table->string('driver_experience', 32)
                ->nullable()
                ->default('all')
                ->after('target_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['target_group_id']);
            $table->dropColumn(['target_group_id', 'driver_experience']);
        });
    }
};
