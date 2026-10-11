<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Sign-in uses a username (per prototype), so e-mail becomes optional.
            $table->string('email')->nullable()->change();
            $table->string('username')->unique()->after('name');
            $table->string('role')->default('staff')->index()->after('username');
            $table->foreignId('employee_id')
                ->nullable()
                ->after('role')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropIndex(['role']);
            $table->dropColumn(['username', 'role']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
