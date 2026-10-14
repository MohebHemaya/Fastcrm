<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('from_location')->nullable()->change();
            $table->string('to_location')->nullable()->change();
            $table->dateTime('scheduled_at')->nullable()->change();
            $table->dateTime('scheduled_dispatch_at')->nullable()->after('send_strategy');
            $table->unsignedInteger('offer_expires_in_minutes')->nullable()->after('scheduled_dispatch_at');
        });

        Schema::table('work_order_recipients', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_recipients', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['scheduled_dispatch_at', 'offer_expires_in_minutes']);
        });
    }
};
