<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('contact_no')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('bays', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // B1 ... B12
            $table->unsignedTinyInteger('bay_number')->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_out_of_order')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bays');
        Schema::dropIfExists('customers');
    }
};
