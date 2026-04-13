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
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remote_phone', 30);
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('type', 20)->default('text');
            $table->text('content')->nullable();
            $table->string('template_name')->nullable();
            $table->string('wamid', 100)->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('remote_phone');
            $table->index('wamid');
            $table->index(['whatsapp_account_id', 'remote_phone', 'created_at'], 'wa_msg_account_phone_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
