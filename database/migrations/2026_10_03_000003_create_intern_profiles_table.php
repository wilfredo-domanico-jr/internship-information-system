<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intern_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_number', 30)->unique();
            $table->string('gender', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('present_address')->nullable();
            $table->string('permanent_address')->nullable();
            $table->text('about')->nullable();
            $table->string('school_year', 20)->nullable();
            $table->string('resume_path')->nullable();
            $table->unsignedInteger('total_hours')->default(0);
            $table->unsignedInteger('total_absences')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intern_profiles');
    }
};
