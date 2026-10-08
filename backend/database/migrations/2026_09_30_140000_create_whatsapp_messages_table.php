<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->cascadeOnDelete();
            $table->enum('direction', ['out', 'in'])->default('out');
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('type')->default('text'); // text|button|list|template|audio|image|other
            $table->string('template_name')->nullable();
            $table->json('payload')->nullable();    // full body / button list / etc.
            $table->text('body')->nullable();     // plain text body for quick rendering
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('order_id')->nullable(); // used in Phase 5, no FK yet
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'direction']);
            $table->index(['driver_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
