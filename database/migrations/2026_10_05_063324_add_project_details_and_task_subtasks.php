<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('icon', 32)->default('icon1.png');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->json('subtasks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('subtasks');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['description', 'icon']);
        });
    }
};
