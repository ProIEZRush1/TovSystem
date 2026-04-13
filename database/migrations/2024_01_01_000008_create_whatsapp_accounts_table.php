<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone_number', 30);
            $table->string('phone_number_id', 50)->nullable();
            $table->string('waba_id', 50)->nullable();
            $table->text('access_token');
            $table->string('verified_name')->nullable();
            $table->string('quality_rating', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
