<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30);
            $table->string('country', 100)->nullable();
            $table->foreignId('status_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 100)->nullable();
            $table->string('name')->nullable();
            $table->date('date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('country');
            $table->index('status_id');
            $table->index('name');
            $table->index(['status_id', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
