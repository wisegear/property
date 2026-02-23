<?php

use App\Http\Controllers\Admin\DataUpdateController;
use App\Http\Controllers\AdminBlogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminInflationController;
use App\Http\Controllers\AdminPostCodesController;
use App\Http\Controllers\AdminSupportController;
use App\Http\Controllers\AdminUnemploymentController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentsController;
use App\Http\Controllers\DeprivationController;
use App\Http\Controllers\EpcController;
use App\Http\Controllers\EpcPostcodeController;
use App\Http\Controllers\HpiDashboardController;
use App\Http\Controllers\InterestRateController;
use App\Http\Controllers\LocalAuthorityController;
use App\Http\Controllers\MlarArrearsController;
use App\Http\Controllers\MortgageApprovalController;
use App\Http\Controllers\MortgageCalcController;
use App\Http\Controllers\NewOldController;
use App\Http\Controllers\OuterPrimeLondonController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PrimeLondonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyAreaController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\RepossessionsController;
use App\Http\Controllers\StampDutyController;
use App\Http\Controllers\SupportController;
// 3rd Party packages

use App\Http\Controllers\UltraLondonController;
use App\Http\Controllers\UnemploymentController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

// Base Pages

Route::get('/', [PagesController::class, 'home'])->name('home');
Route::get('/about', [PagesController::class, 'about'])->name('about');
Route::get('/dashboard', fn () => redirect('/'))->name('dashboard');

Route::get('/property', [PropertyController::class, 'home'])->name('property.home');
Route::get('/property/search', [PropertyController::class, 'search'])->name('property.search');
Route::get('/property/heatmap', [PropertyController::class, 'heatmap'])->name('property.heatmap');
Route::get('/property/points', [PropertyController::class, 'points'])->name('property.points');
Route::get('/property/show', [PropertyController::class, 'show'])->name('property.show');
Route::get('/property/prime-central-london', [PrimeLondonController::class, 'home'])->name('property.pcl');
Route::get('/property/outer-prime-london', [OuterPrimeLondonController::class, 'home'])->name('property.outer');
Route::get('/property/ultra-prime-central-london', [UltraLondonController::class, 'home'])->name('property.upcl');
Route::get('/epc', [EpcController::class, 'home'])->name('epc.home');
Route::get('/epc/search', [EpcController::class, 'search'])->name('epc.search');
Route::get('/epc/points', [EpcController::class, 'points'])->name('epc.points');
Route::get('/epc/points_scotland', [EpcController::class, 'pointsScotland'])->name('epc.points_scotland');
Route::get('/epc/postcode/{postcode}', [EpcPostcodeController::class, 'englandWales'])
    ->where('postcode', '[A-Z0-9\-]+');
Route::get('/epc/scotland/postcode/{postcode}', [EpcPostcodeController::class, 'scotland'])
    ->where('postcode', '[A-Z0-9\-]+');
// routes/web.php
Route::get('/epc/search_scotland', [\App\Http\Controllers\EpcController::class, 'searchScotland'])
    ->name('epc.search_scotland');
Route::get('/epc/scotland/{rrn}', [\App\Http\Controllers\EpcController::class, 'showScotland'])
    ->name('epc.scotland.show');
Route::get('/epc/{lmk}', [EpcController::class, 'show'])->name('epc.show');
Route::get('/hpi', [HpiDashboardController::class, 'index'])->name('hpi.home');
Route::get('/rental', [RentalController::class, 'index'])->name('rental.index');
Route::get('/rental/england', [RentalController::class, 'england'])->name('rental.england');
Route::get('/rental/scotland', [RentalController::class, 'scotland'])->name('rental.scotland');
Route::get('/rental/wales', [RentalController::class, 'wales'])->name('rental.wales');
Route::get('/rental/northern-ireland', [RentalController::class, 'northernIreland'])->name('rental.northern-ireland');
// New vs Existing (New/Old) dashboard
Route::get('/new-old', [NewOldController::class, 'index'])->name('newold.index');
Route::match(['get', 'post'], '/mortgage-calculator', [MortgageCalcController::class, 'index'])->name('mortgagecalc.index');

Route::get('/stamp-duty', [StampDutyController::class, 'index']);
Route::post('/stamp-duty/calc', [StampDutyController::class, 'calculate']);

Route::get('/interest-rates', [InterestRateController::class, 'home'])->name('interest.home');
Route::get('/unemployment', [UnemploymentController::class, 'index'])->name('unemployment.home');
Route::get('/inflation', [\App\Http\Controllers\InflationController::class, 'index'])->name('inflation.home');
Route::get('/wage-growth', [\App\Http\Controllers\WageGrowthController::class, 'index'])->name('wagegrowth.home');
Route::get('/hpi-overview', [HpiDashboardController::class, 'overview'])->name('hpi.overview');
Route::get('/economic-dashboard', [\App\Http\Controllers\EconomicDashboardController::class, 'index'])->name('economic.dashboard');
Route::get('/approvals', [MortgageApprovalController::class, 'home'])->name('mortgages.home');
Route::get('/repossessions/local-authority/{slug}', [RepossessionsController::class, 'localAuthority'])->name('repossessions.local-authority');
Route::get('/repossessions', [RepossessionsController::class, 'index'])->name('repossessions.index');
Route::get('/arrears', [MlarArrearsController::class, 'index'])->name('arrears.index');

Route::get('/social-housing-scotland', [LocalAuthorityController::class, 'scotland'])->name('localauthority.scotland');
Route::get('/social-housing-england', [LocalAuthorityController::class, 'england'])->name('localauthority.england');

// Deprivation Routes
Route::get('/deprivation', [DeprivationController::class, 'index'])->name('deprivation.index');
Route::get('/deprivation/{lsoa21cd}', [DeprivationController::class, 'show'])->name('deprivation.show');
Route::get('/deprivation/scotland/{dz}', [\App\Http\Controllers\DeprivationController::class, 'showScotland'])->name('deprivation.scot.show');
Route::get('/deprivation/wales/{lsoa}', [DeprivationController::class, 'showWales'])->name('deprivation.wales.show');
Route::get('/deprivation/northern-ireland/{sa}', [DeprivationController::class, 'showNorthernIreland'])->name('deprivation.ni.show');

Route::resource('/blog', BlogController::class);

// Area property search
Route::get('/property/area/{type}/{slug}', [PropertyAreaController::class, 'show'])
    ->whereIn('type', ['locality', 'town', 'district', 'county'])
    ->name('property.area.show');
Route::get('/property/{slug}', [PropertyController::class, 'showBySlug'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('property.show.slug');

// Routes first protected by Auth

Route::middleware('auth')->group(function () {

    // Account profile endpoints (Breeze-style)
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::patch('/profile', [ProfileController::class, 'updateAuthenticated'])->name('profile.account.update');
    Route::delete('/profile', [ProfileController::class, 'destroyAuthenticated'])->name('profile.destroy');

    // Public/member profile endpoints by slug
    Route::get('/profile/{name_slug}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/{name_slug}/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/{name_slug}', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/comments', [CommentsController::class, 'store'])->name('comments.store');
    Route::resource('support', SupportController::class);

    // Protect the Dashboard routes behind both Auth and Can
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('can:Admin')
        ->group(function () {
            Route::resource('/', AdminController::class);
            Route::resource('users', AdminUserController::class);
            Route::resource('blog', AdminBlogController::class);
            Route::resource('postcodes', AdminPostCodesController::class);
            Route::resource('/support', AdminSupportController::class);
            Route::resource('updates', DataUpdateController::class)->except(['show']);
            // Inflation (admin)
            Route::get('/inflation', [AdminInflationController::class, 'index'])->name('inflation.index');
            Route::post('/inflation/add', [AdminInflationController::class, 'add'])->name('inflation.add');
            Route::post('/inflation', [AdminInflationController::class, 'store'])->name('inflation.store');
            Route::delete('/inflation/{id}', [AdminInflationController::class, 'destroy'])->name('inflation.destroy');
            // Unemployment
            Route::get('/unemployment', [AdminUnemploymentController::class, 'index'])->name('unemployment.index');
            Route::post('/unemployment/add', [AdminUnemploymentController::class, 'add'])->name('unemployment.add');
            Route::post('/unemployment', [AdminUnemploymentController::class, 'store'])->name('unemployment.store');
            Route::delete('/unemployment/{id}', [AdminUnemploymentController::class, 'destroy'])->name('unemployment.destroy');
            // Wage Growth (admin)
            Route::get('/wage-growth', [\App\Http\Controllers\AdminWageGrowthController::class, 'index'])->name('wagegrowth.index');
            Route::post('/wage-growth/add', [\App\Http\Controllers\AdminWageGrowthController::class, 'add'])->name('wagegrowth.add');
            Route::post('/wage-growth', [\App\Http\Controllers\AdminWageGrowthController::class, 'store'])->name('wagegrowth.store');
            Route::delete('/wage-growth/{id}', [\App\Http\Controllers\AdminWageGrowthController::class, 'destroy'])->name('wagegrowth.destroy');
            // Interest Rates (admin)
            Route::get('/interest-rates', [\App\Http\Controllers\AdminInterestRateController::class, 'index'])->name('interestrates.index');
            Route::post('/interest-rates/add', [\App\Http\Controllers\AdminInterestRateController::class, 'add'])->name('interestrates.add');
            Route::post('/interest-rates', [\App\Http\Controllers\AdminInterestRateController::class, 'store'])->name('interestrates.store');
            Route::delete('/interest-rates/{id}', [\App\Http\Controllers\AdminInterestRateController::class, 'destroy'])->name('interestrates.destroy');
            // Arrears (admin)
            Route::get('/arrears', [\App\Http\Controllers\AdminArrearsController::class, 'index'])->name('arrears.index');
            Route::post('/arrears/add', [\App\Http\Controllers\AdminArrearsController::class, 'add'])->name('arrears.add');
            Route::post('/arrears', [\App\Http\Controllers\AdminArrearsController::class, 'store'])->name('arrears.store');
            Route::delete('/arrears/{id}', [\App\Http\Controllers\AdminArrearsController::class, 'destroy'])->name('arrears.destroy');

            // Mortgage Approvals (admin)
            Route::get('/approvals', [\App\Http\Controllers\AdminMortgageApprovalController::class, 'index'])->name('approvals.index');
            Route::post('/approvals/add', [\App\Http\Controllers\AdminMortgageApprovalController::class, 'add'])->name('approvals.add');
            Route::post('/approvals', [\App\Http\Controllers\AdminMortgageApprovalController::class, 'store'])->name('approvals.store');
            Route::delete('/approvals/{id}', [\App\Http\Controllers\AdminMortgageApprovalController::class, 'destroy'])->name('approvals.destroy');
        });

    // Logout route to clear session.

    Route::get('/logout', function () {
        Session::flush();
        Auth::logout();

        return Redirect::to('/');
    });

});

// Sitemap by Spatie - Need to run generate-sitemap

Route::get('/sitemap.xml', function () {
    $path = public_path('sitemap.xml');
    if (! File::exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->name('sitemap.index');

Route::get('/sitemap-index.xml', function () {
    $path = public_path('sitemap-index.xml');
    if (! File::exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->name('sitemap.master-index');

Route::get('/sitemap-{chunk}.xml', function (string $chunk) {
    $path = public_path("sitemap-{$chunk}.xml");
    if (! File::exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->whereNumber('chunk')->name('sitemap.chunk');

Route::get('/sitemap-epc-postcodes.xml', function () {
    $path = public_path('sitemap-epc-postcodes.xml');
    if (! File::exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->name('sitemap.epc-postcodes');

Route::get('/sitemap-epc-postcodes-{chunk}.xml', function (string $chunk) {
    $path = public_path("sitemap-epc-postcodes-{$chunk}.xml");
    if (! File::exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->whereNumber('chunk')->name('sitemap.epc-postcodes.chunk');

Route::get('/generate-sitemap', function () {
    Artisan::call('sitemap:generate');

    return response(Artisan::output(), 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
});

require __DIR__.'/auth.php';
