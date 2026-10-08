<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('whatsapp'); // whatsapp / future providers
            $table->string('external_id')->unique();         // provider event id (idempotency key)
            $table->json('payload');                         // raw event payload
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
