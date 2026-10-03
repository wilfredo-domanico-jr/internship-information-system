<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('course_code', 30);
            $table->string('subject');
            $table->string('section', 50);
            $table->string('day', 20);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('school_year', 20);
            $table->string('join_code', 20)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::table('intern_profiles', function (Blueprint $table) {
            $table->foreignId('class_section_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('class_adviser_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adviser_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('announcement_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('class_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('class_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_late')->default(false);
            $table->text('reviewer_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('class_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_resources');
        Schema::dropIfExists('class_submissions');
        Schema::dropIfExists('class_folders');
        Schema::dropIfExists('announcement_comments');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('class_adviser_logs');
        Schema::table('intern_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_section_id');
        });
        Schema::dropIfExists('class_sections');
    }
};
