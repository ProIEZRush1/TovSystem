<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#6B7280');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_label', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_label');
        Schema::dropIfExists('labels');
    }
};
