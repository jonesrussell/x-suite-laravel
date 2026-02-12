<?php

namespace JonesRussell\XSuite\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use JonesRussell\XSuite\Models\XPost;
use JonesRussell\XSuite\Services\XAnalyticsService;

class XAnalyticsController extends Controller
{
    public function __construct(
        protected XAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): Response
    {
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(30);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        $performanceReport = $this->analyticsService->getPerformanceReport($startDate, $endDate);
        $topPerformers = $this->analyticsService->getTopPerformers(10, 'impressions');

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XAnalytics/Index", [
            'performanceReport' => $performanceReport,
            'topPerformers' => $topPerformers,
            'queryParams' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function show(XPost $xPost): Response
    {
        $xPost->load('analytics', 'user');

        $analytics = $xPost->analytics()->latest('recorded_at')->get();

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XAnalytics/Show", [
            'xPost' => $xPost,
            'analytics' => $analytics,
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');
        $limit = $request->get('limit', 50);
        $synced = $this->analyticsService->syncPublishedPosts((int) $limit);

        return redirect()->route("{$routePrefix}.x-analytics.index")
            ->with('success', "Synced metrics for {$synced} posts.");
    }
}
