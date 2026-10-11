<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately NOT called "sessions" - Laravel uses that for auth session storage.
        Schema::create('bay_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_no')->unique();
            $table->foreignId('bay_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at');
            $table->unsignedSmallInteger('planned_minutes')->default(90);
            $table->dateTime('ends_at');                 // moves forward on every extension
            $table->dateTime('actual_end_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['bay_id', 'status']);
        });

        Schema::create('session_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bay_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('bay_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bay_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('waiting')->index();
            $table->dateTime('queued_at');
            $table->dateTime('served_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bay_queues');
        Schema::dropIfExists('session_extensions');
        Schema::dropIfExists('bay_sessions');
    }
};
