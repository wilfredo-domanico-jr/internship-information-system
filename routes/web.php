<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Adviser;
use App\Http\Controllers\Auth\AccountStatusController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterCompanyController;
use App\Http\Controllers\Auth\RegisterInternController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\Classroom;
use App\Http\Controllers\Company;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Intern;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/* Guests */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::get('register/intern', [RegisterInternController::class, 'create'])->name('register.intern');
    Route::post('register/intern', [RegisterInternController::class, 'store'])->middleware('throttle:6,1')->name('register.intern.store');
    Route::get('register/company', [RegisterCompanyController::class, 'create'])->name('register.company');
    Route::post('register/company', [RegisterCompanyController::class, 'store'])->middleware('throttle:6,1')->name('register.company.store');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('password/forgot', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('password/forgot', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('password/reset/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('password/reset', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

/* Signed-in users, regardless of account state (a pending company can still log out) */
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});

/* Any signed-in user */
Route::middleware(['auth', 'auth.session', 'account.usable'])->group(function () {
    Route::get('account/pending', [AccountStatusController::class, 'pending'])->name('account.pending');
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
    Route::put('password', [ChangePasswordController::class, 'update'])->name('password.update');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');
    Route::delete('profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('files/{kind}/{id}', [FileController::class, 'show'])->where('id', '[0-9]+')->name('files.show');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
        Route::get('interns', [Admin\InternController::class, 'index'])->name('interns.index');
        Route::get('interns/{user}', [Admin\InternController::class, 'show'])->name('interns.show');
        Route::get('advisers', [Admin\AdviserController::class, 'index'])->name('advisers.index');
        Route::get('advisers/create', [Admin\AdviserController::class, 'create'])->name('advisers.create');
        Route::post('advisers', [Admin\AdviserController::class, 'store'])->name('advisers.store');
        Route::get('advisers/{user}', [Admin\AdviserController::class, 'show'])->name('advisers.show');
        Route::get('companies', [Admin\CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/pending', [Admin\CompanyApprovalController::class, 'index'])->name('companies.pending');
        Route::post('companies/{company}/approve', [Admin\CompanyApprovalController::class, 'approve'])->name('companies.approve');
        Route::post('companies/{company}/reject', [Admin\CompanyApprovalController::class, 'reject'])->name('companies.reject');
        Route::get('companies/{company}', [Admin\CompanyController::class, 'show'])->name('companies.show');
        Route::post('users/{user}/disable', [Admin\UserStatusController::class, 'disable'])->name('users.disable');
        Route::post('users/{user}/reactivate', [Admin\UserStatusController::class, 'reactivate'])->name('users.reactivate');
        Route::get('classes', [Admin\ClassSectionController::class, 'index'])->name('classes.index');
        Route::get('classes/{classSection}', [Admin\ClassSectionController::class, 'show'])->name('classes.show');
        Route::get('archive', [Admin\ArchiveController::class, 'index'])->name('archive.index');
        Route::get('imports', [Admin\ImportController::class, 'index'])->name('imports.index');
        Route::get('imports/templates/{type}', [Admin\ImportController::class, 'template'])->name('imports.template');
        Route::post('imports/{type}', [Admin\ImportController::class, 'store'])->name('imports.store');
        Route::get('departments', [Admin\DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [Admin\DepartmentController::class, 'store'])->name('departments.store');
        Route::put('departments/{department}', [Admin\DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [Admin\DepartmentController::class, 'destroy'])->name('departments.destroy');
        Route::resource('partners', Admin\PartnerCompanyController::class)->except('show')->parameters(['partners' => 'company']);
    });

    Route::prefix('adviser')->name('adviser.')->middleware('role:adviser')->group(function () {
        Route::get('dashboard', Adviser\DashboardController::class)->name('dashboard');
        Route::get('classes', [Adviser\ClassSectionController::class, 'index'])->name('classes.index');
        Route::get('classes/create', [Adviser\ClassSectionController::class, 'create'])->name('classes.create');
        Route::post('classes/join', [Adviser\ClassSectionController::class, 'join'])->name('classes.join');
        Route::post('classes/{classSection}/leave', [Adviser\ClassSectionController::class, 'leave'])->name('classes.leave');
        Route::post('classes', [Adviser\ClassSectionController::class, 'store'])->name('classes.store');
        Route::get('classes/{classSection}/edit', [Adviser\ClassSectionController::class, 'edit'])->name('classes.edit');
        Route::put('classes/{classSection}', [Adviser\ClassSectionController::class, 'update'])->name('classes.update');
        Route::get('classes/{classSection}', [Adviser\ClassSectionController::class, 'show'])->name('classes.show');
        Route::post('classes/{classSection}/announcements', [Adviser\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}/edit', [Adviser\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('announcements/{announcement}', [Adviser\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [Adviser\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::get('classes/{classSection}/people', [Adviser\ClassSectionController::class, 'people'])->name('classes.people');
        Route::get('classes/{classSection}/print', [Adviser\ClassSectionController::class, 'print'])->name('classes.print');
        Route::get('classes/{classSection}/documents', [Adviser\ClassSectionController::class, 'documents'])->name('classes.documents');
        Route::post('classes/{classSection}/resources', [Adviser\ResourceController::class, 'store'])->name('resources.store');
        Route::delete('resources/{resource}', [Adviser\ResourceController::class, 'destroy'])->name('resources.destroy');
        Route::post('classes/{classSection}/folders', [Adviser\FolderController::class, 'store'])->name('folders.store');
        Route::get('folders/{folder}', [Adviser\FolderController::class, 'show'])->name('folders.show');
        Route::post('folders/{folder}/lock', [Adviser\FolderController::class, 'toggleLock'])->name('folders.lock');
        Route::delete('folders/{folder}', [Adviser\FolderController::class, 'destroy'])->name('folders.destroy');
        Route::post('submissions/{submission}/approve', [Adviser\SubmissionReviewController::class, 'approve'])->name('submissions.approve');
        Route::post('submissions/{submission}/decline', [Adviser\SubmissionReviewController::class, 'decline'])->name('submissions.decline');
        Route::post('announcements/{announcement}/comments', [Classroom\CommentController::class, 'store'])->name('comments.store');
        Route::delete('comments/{comment}', [Classroom\CommentController::class, 'destroy'])->name('comments.destroy');
    });

    Route::prefix('company')->name('company.')->middleware('role:company')->group(function () {
        Route::get('dashboard', Company\DashboardController::class)->name('dashboard');
        Route::get('postings', [Company\PostingController::class, 'index'])->name('postings.index');
        Route::get('postings/create', [Company\PostingController::class, 'create'])->name('postings.create');
        Route::post('postings', [Company\PostingController::class, 'store'])->name('postings.store');
        Route::get('postings/{posting}/edit', [Company\PostingController::class, 'edit'])->name('postings.edit');
        Route::put('postings/{posting}', [Company\PostingController::class, 'update'])->name('postings.update');
        Route::post('postings/{posting}/toggle', [Company\PostingController::class, 'toggle'])->name('postings.toggle');
        Route::delete('postings/{posting}', [Company\PostingController::class, 'destroy'])->name('postings.destroy');
        Route::get('postings/{posting}/applicants', Company\ApplicantController::class)->name('postings.applicants');
        Route::get('applications/{application}', [Company\ApplicationController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/interview', [Company\ApplicationController::class, 'interview'])->name('applications.interview');
        Route::get('interviews', Company\InterviewController::class)->name('interviews.index');
        Route::post('applications/{application}/accept', [Company\ApplicationController::class, 'accept'])->name('applications.accept');
        Route::post('applications/{application}/decline', [Company\ApplicationController::class, 'decline'])->name('applications.decline');
    });

    Route::prefix('intern')->name('intern.')->middleware('role:intern')->group(function () {
        Route::get('dashboard', Intern\DashboardController::class)->name('dashboard');
        Route::get('internships', [Intern\PostingController::class, 'index'])->name('postings.index');
        Route::get('internships/{posting}', [Intern\PostingController::class, 'show'])->name('postings.show');
        Route::post('internships/{posting}/apply', [Intern\ApplicationController::class, 'store'])->name('applications.store');
        Route::get('internship', [Intern\InternshipController::class, 'show'])->name('internship.show');
        Route::post('internship/join', [Intern\InternshipController::class, 'join'])->name('internship.join');
        Route::post('internship/leave', [Intern\InternshipController::class, 'leave'])->name('internship.leave');
        Route::get('applications', [Intern\ApplicationController::class, 'index'])->name('applications.index');
        Route::post('applications/{application}/cancel', [Intern\ApplicationController::class, 'cancel'])->name('applications.cancel');
        Route::get('class', [Intern\ClassController::class, 'show'])->name('class.show');
        Route::post('class/join', [Intern\ClassController::class, 'join'])->name('class.join');
        Route::get('class/people', [Intern\ClassController::class, 'people'])->name('class.people');
        Route::get('class/documents', [Intern\ClassController::class, 'documents'])->name('class.documents');
        Route::get('folders/{folder}', [Intern\FolderController::class, 'show'])->name('folders.show');
        Route::post('folders/{folder}/submissions', [Intern\SubmissionController::class, 'store'])->name('submissions.store');
        Route::get('submissions', [Intern\SubmissionController::class, 'index'])->name('submissions.index');
        Route::delete('submissions/{submission}', [Intern\SubmissionController::class, 'destroy'])->name('submissions.destroy');
        Route::post('announcements/{announcement}/comments', [Classroom\CommentController::class, 'store'])->name('comments.store');
        Route::delete('comments/{comment}', [Classroom\CommentController::class, 'destroy'])->name('comments.destroy');
    });
});
