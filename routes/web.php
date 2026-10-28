<?php

use App\Http\Controllers\AccountCheckController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonatorController;
use App\Http\Controllers\OwnerAuthController;
use App\Http\Controllers\OwnerManageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewFeedbackController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShareImageController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubmissionController;
use App\Http\Middleware\AuthenticateOwner;
use App\Http\Middleware\BlockChannelOwners;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureOwnerPortalEnabled;
use App\Support\SocialLogin;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// For search engines (public/robots.txt is removed so this one is served)
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');

Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(array_key_exists($locale, config('trust.locales')), 404);
    session(['locale' => $locale]);

    return redirect()->back(fallback: route('home'));
})->name('locale.switch');

Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
Route::get('/check-account', AccountCheckController::class)->middleware('throttle:30,1')->name('account-check');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
Route::get('/category/{category}', [ProviderController::class, 'index'])->name('categories.show');
Route::get('/p/{provider}', [ProviderController::class, 'show'])->name('providers.show');

// A single review's page, used as the shared link so it gets its own preview image
Route::get('/reviews/{review}', [ProviderController::class, 'review'])->name('reviews.show');

// Preview images for social networks (og:image); the language is in the URL
Route::prefix('/share-images/{locale}')->whereIn('locale', array_keys(config('trust.locales')))
    ->middleware('throttle:120,1')->group(function () {
        Route::get('/site.png', [ShareImageController::class, 'site'])->name('share-images.site');
        Route::get('/providers/{provider}.png', [ShareImageController::class, 'provider'])->name('share-images.provider');
        Route::get('/reviews/{review}.png', [ShareImageController::class, 'review'])->name('share-images.review');
    });

// Open to guests too; one vote/report per account or IP (see Review::voterKey)
Route::post('/reviews/{review}/helpful', [ReviewFeedbackController::class, 'helpful'])->middleware('throttle:30,1')->name('reviews.helpful');
Route::post('/reviews/{review}/report', [ReviewFeedbackController::class, 'report'])->middleware('throttle:10,10')->name('reviews.report');
Route::post('/p/{provider}/impersonators', [ImpersonatorController::class, 'report'])->middleware('throttle:5,10')->name('impersonators.report');

// Content pages managed from the admin panel (About us, Privacy, …)
Route::redirect('/page/contact', '/contact');
Route::get('/page/{page}', [PageController::class, 'show'])->name('pages.show');

Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,10')->name('contact.store');

Route::get('/submit', [SubmissionController::class, 'create'])->name('submissions.create');
Route::get('/submit/check', [SubmissionController::class, 'check'])->middleware('throttle:30,1')->name('submissions.check');
Route::post('/submit', [SubmissionController::class, 'store'])->middleware('throttle:5,10')->name('submissions.store');

// Writing reviews: never by channel owners (checked first, so they get a message rather than
// the login page), and members need a confirmed email address
Route::middleware([BlockChannelOwners::class, 'auth', EnsureEmailIsVerified::class])->group(function () {
    Route::get('/p/{provider}/review', [ReviewController::class, 'create'])->name('reviews.create');
    Route::post('/p/{provider}/review', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');
    Route::delete('/p/{provider}/review', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('verification.verify');
    Route::post('/verify-email/email', [EmailVerificationController::class, 'updateEmail'])->middleware('throttle:5,10')->name('verification.email');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:5,10')->name('verification.resend');

    Route::post('/auth/telegram/link', [SocialAuthController::class, 'linkTelegram'])->middleware('throttle:10,1')->name('social.telegram.link');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:20,1')->name('profile.update');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Channel owners: sign in on the site, then manage their channel from its own page
Route::middleware(EnsureOwnerPortalEnabled::class)->group(function () {
    Route::get('/owner/login', [OwnerAuthController::class, 'showLogin'])->name('owner.login');
    Route::post('/owner/login', [OwnerAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/owner/logout', [OwnerAuthController::class, 'logout'])->name('owner.logout');

    Route::middleware(AuthenticateOwner::class)->group(function () {
        Route::post('/p/{provider}/reviews/{review}/reply', [OwnerManageController::class, 'reply'])->middleware('throttle:30,1')->name('owner.reply');
        Route::delete('/p/{provider}/reviews/{review}/reply', [OwnerManageController::class, 'deleteReply'])->name('owner.reply.delete');
        Route::put('/p/{provider}/manage', [OwnerManageController::class, 'update'])->middleware('throttle:20,1')->name('owner.update');
    });
});
Route::redirect('/owner', '/owner/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    // Google / Facebook (OAuth redirect) and Telegram (Login Widget posts its signed data)
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/auth/telegram/callback', [SocialAuthController::class, 'telegram'])->name('social.telegram');
        Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->whereIn('provider', SocialLogin::OAUTH)->name('social.redirect');
        Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->whereIn('provider', SocialLogin::OAUTH)->name('social.callback');
    });
});
