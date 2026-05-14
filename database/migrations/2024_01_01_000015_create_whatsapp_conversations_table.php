<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remote_phone', 30);
            $table->string('contact_name')->nullable();
            $table->integer('unread_count')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->enum('last_message_direction', ['inbound', 'outbound'])->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('window_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_account_id', 'remote_phone'], 'wa_conv_account_phone');
            $table->index('last_message_at');
        });

        // Add conversation_id to messages
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('whatsapp_conversation_id')->nullable()->after('whatsapp_account_id')
                ->constrained('whatsapp_conversations')->nullOnDelete();
            $table->string('media_url')->nullable()->after('content');
            $table->string('media_mime_type', 50)->nullable()->after('media_url');
            $table->string('media_filename')->nullable()->after('media_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_conversation_id');
            $table->dropColumn(['media_url', 'media_mime_type', 'media_filename']);
        });
        Schema::dropIfExists('whatsapp_conversations');
    }
};
