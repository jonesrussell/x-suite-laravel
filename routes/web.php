<?php

use Illuminate\Support\Facades\Route;
use JonesRussell\XSuite\Http\Controllers\XAnalyticsController;
use JonesRussell\XSuite\Http\Controllers\XAutoReplyController;
use JonesRussell\XSuite\Http\Controllers\XContentDiscoveryController;
use JonesRussell\XSuite\Http\Controllers\XOAuth2Controller;
use JonesRussell\XSuite\Http\Controllers\XPostController;
use JonesRussell\XSuite\Http\Controllers\XPostImportController;
use JonesRussell\XSuite\Http\Controllers\XSettingsController;
use JonesRussell\XSuite\Http\Controllers\XTrendMonitoringController;
use JonesRussell\XSuite\Models\XPost;

$middleware = config('x-suite.middleware', ['web', 'auth', 'verified', 'admin']);
$prefix = config('x-suite.route_prefix', '');
$namePrefix = config('x-suite.route_name_prefix', 'admin');

Route::middleware($middleware)->prefix($prefix)->group(function () use ($namePrefix) {

    // X OAuth 2.0
    Route::get('/x-oauth2/redirect', [XOAuth2Controller::class, 'redirect'])->name("{$namePrefix}.x-oauth2.redirect");
    Route::get('/x-oauth2/callback', [XOAuth2Controller::class, 'callback'])->name("{$namePrefix}.x-oauth2.callback");

    // X Settings
    Route::get('/x-settings', [XSettingsController::class, 'index'])->name("{$namePrefix}.x-settings.index");
    Route::post('/x-settings/disconnect', [XSettingsController::class, 'disconnect'])->name("{$namePrefix}.x-settings.disconnect");

    // X Post Management
    Route::prefix('x-posts')->name("{$namePrefix}.x-posts.")->group(function () {
        Route::get('/import', [XPostImportController::class, 'show'])->name('import');
        Route::post('/import', [XPostImportController::class, 'store'])->name('import.store');
        Route::post('/{xPost}/schedule', [XPostController::class, 'schedule'])->name('schedule');
        Route::post('/{xPost}/publish', [XPostController::class, 'publish'])->name('publish');
        Route::post('/{xPost}/cancel', [XPostController::class, 'cancel'])->name('cancel');
    });
    Route::resource('x-posts', XPostController::class)->names("{$namePrefix}.x-posts");

    // X Analytics
    Route::get('/x-analytics', [XAnalyticsController::class, 'index'])->name("{$namePrefix}.x-analytics.index");
    Route::get('/x-analytics/{xPost}', [XAnalyticsController::class, 'show'])->name("{$namePrefix}.x-analytics.show");
    Route::post('/x-analytics/sync', [XAnalyticsController::class, 'sync'])->name("{$namePrefix}.x-analytics.sync");

    // X Trend Monitoring
    Route::get('/x-trends', [XTrendMonitoringController::class, 'index'])->name("{$namePrefix}.x-trends.index");
    Route::post('/x-trends', [XTrendMonitoringController::class, 'store'])->name("{$namePrefix}.x-trends.store");
    Route::put('/x-trends/{keyword}', [XTrendMonitoringController::class, 'update'])->name("{$namePrefix}.x-trends.update");
    Route::delete('/x-trends/{keyword}', [XTrendMonitoringController::class, 'destroy'])->name("{$namePrefix}.x-trends.destroy");
    Route::post('/x-trends/{keyword}/search', [XTrendMonitoringController::class, 'search'])->name("{$namePrefix}.x-trends.search");
    Route::get('/x-trends/{keyword}/results', [XTrendMonitoringController::class, 'results'])->name("{$namePrefix}.x-trends.results");

    // X Auto Replies
    Route::get('/x-auto-replies', [XAutoReplyController::class, 'index'])->name("{$namePrefix}.x-auto-replies.index");
    Route::post('/x-auto-replies', [XAutoReplyController::class, 'store'])->name("{$namePrefix}.x-auto-replies.store");
    Route::put('/x-auto-replies/{rule}', [XAutoReplyController::class, 'update'])->name("{$namePrefix}.x-auto-replies.update");
    Route::delete('/x-auto-replies/{rule}', [XAutoReplyController::class, 'destroy'])->name("{$namePrefix}.x-auto-replies.destroy");
    Route::post('/x-auto-replies/{rule}/toggle', [XAutoReplyController::class, 'toggle'])->name("{$namePrefix}.x-auto-replies.toggle");
    Route::get('/x-auto-replies/{rule}/test', [XAutoReplyController::class, 'test'])->name("{$namePrefix}.x-auto-replies.test");

    // X Content Discovery
    Route::get('/x-content-discovery', [XContentDiscoveryController::class, 'index'])->name("{$namePrefix}.x-content-discovery.index");
    Route::post('/x-content-discovery/discover', [XContentDiscoveryController::class, 'discover'])->name("{$namePrefix}.x-content-discovery.discover");
    Route::post('/x-content-discovery', [XContentDiscoveryController::class, 'store'])->name("{$namePrefix}.x-content-discovery.store");
    Route::put('/x-content-discovery/{post}', [XContentDiscoveryController::class, 'update'])->name("{$namePrefix}.x-content-discovery.update");
    Route::delete('/x-content-discovery/{post}', [XContentDiscoveryController::class, 'destroy'])->name("{$namePrefix}.x-content-discovery.destroy");
});

// Public feed endpoint (optional)
if (config('x-suite.features.public_feed', true)) {
    Route::middleware('web')->get('/api/x-feed', function () {
        return XPost::published()
            ->latest('published_at')
            ->limit(5)
            ->get(['id', 'content', 'x_post_id', 'published_at', 'media_urls']);
    })->name('x-suite.public-feed');
}
