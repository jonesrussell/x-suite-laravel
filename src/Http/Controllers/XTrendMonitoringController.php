<?php

namespace JonesRussell\XSuite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use JonesRussell\XSuite\Models\XTrendKeyword;
use JonesRussell\XSuite\Models\XTrendResult;
use JonesRussell\XSuite\Services\XTrendMonitoringService;

class XTrendMonitoringController extends Controller
{
    public function __construct(
        protected XTrendMonitoringService $trendService
    ) {}

    public function index(Request $request): Response
    {
        $keywords = XTrendKeyword::query()
            ->withCount('results')
            ->latest()
            ->paginate(20);

        $recentTrends = $this->trendService->getRecentTrends(10);

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XTrends/Index", [
            'keywords' => $keywords,
            'recentTrends' => $recentTrends,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:hashtag,keyword,phrase'],
        ]);

        XTrendKeyword::create($validated);

        return redirect()->route("{$routePrefix}.x-trends.index")
            ->with('success', 'Keyword added for monitoring.');
    }

    public function update(Request $request, XTrendKeyword $keyword): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $keyword->update($validated);

        return redirect()->route("{$routePrefix}.x-trends.index")
            ->with('success', 'Keyword updated.');
    }

    public function destroy(XTrendKeyword $keyword): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $keyword->delete();

        return redirect()->route("{$routePrefix}.x-trends.index")
            ->with('success', 'Keyword removed from monitoring.');
    }

    public function search(XTrendKeyword $keyword): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        try {
            $results = $this->trendService->searchKeyword($keyword, 20);

            return redirect()->route("{$routePrefix}.x-trends.index")
                ->with('success', "Found {$results} new results for keyword.");
        } catch (\Exception $e) {
            return redirect()->route("{$routePrefix}.x-trends.index")
                ->with('error', 'Failed to search keyword: '.$e->getMessage());
        }
    }

    public function results(XTrendKeyword $keyword): Response
    {
        $results = XTrendResult::query()
            ->where('trend_keyword_id', $keyword->id)
            ->orderBy('like_count', 'desc')
            ->paginate(20);

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XTrends/Results", [
            'keyword' => $keyword,
            'results' => $results,
        ]);
    }
}
