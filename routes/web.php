<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ImagesController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\CommentsController;
use Illuminate\Support\Facades\Route;

// Route relating to the admin section.

use App\Http\Controllers\Admin\AdminBlogController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\BlogCategoriesController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UsersController;

use App\Http\Middleware\IsMember;
use App\Http\Middleware\IsAdmin;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [PagesController::class, 'home']);
Route::get('/about', [PagesController::class, 'about']);
Route::get('/contact', [PagesController::class, 'contact']);
Route::get('/important', [PagesController::class, 'important']);


Route::resource('/blog', BlogController::class);
Route::resource('/media', ImagesController::class);

// Protected routes only accessible by member group.

Route::middleware([IsMember::class])->group(function() {

    Route::resource('profile', UserProfileController::class);
    Route::resource('support', SupportController::class);
    Route::resource('comments', CommentsController::class)->only(['destroy', 'update']);
    Route::resource('support', SupportController::class);

});

// Protected Admin routes only accessible by admin.

Route::middleware([IsAdmin::class])->group(function() {
    
    Route::get('admin', [AdminController::class, 'index']);
    Route::resource('/media', ImagesController::class);
    Route::resource('/admin/users', UsersController::class)->only(['index', 'destroy']);
    Route::resource('/admin/blog', AdminBlogController::class);
    Route::resource('/admin/support', AdminSupportController::class);
    route::resource('/admin/blog-categories', BlogCategoriesController::class)->except(['create', 'show', 'edit']);

});

Route::get('/logout', function(){
    Session::flush();
    Auth::logout();
    return Redirect::to("/");
});


require __DIR__.'/auth.php';

