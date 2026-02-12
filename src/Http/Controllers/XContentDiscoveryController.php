<?php

namespace JonesRussell\XSuite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use JonesRussell\XSuite\Models\XCuratedPost;
use JonesRussell\XSuite\Services\XContentDiscoveryService;

class XContentDiscoveryController extends Controller
{
    public function __construct(
        protected XContentDiscoveryService $discoveryService
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'featured' => $request->boolean('featured'),
            'high_engagement' => $request->boolean('high_engagement'),
            'recent' => $request->boolean('recent'),
        ];

        $posts = XCuratedPost::query()
            ->when($filters['featured'], fn ($q) => $q->featured())
            ->when($filters['high_engagement'], fn ($q) => $q->highEngagement())
            ->when($filters['recent'], fn ($q) => $q->recent(7))
            ->orderBy('discovered_at', 'desc')
            ->paginate(20);

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        return Inertia::render("{$prefix}/XContentDiscovery/Index", [
            'posts' => $posts,
            'filters' => $filters,
            'routePrefix' => $routePrefix,
        ]);
    }

    public function discover(Request $request): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $filters = [
            'min_likes' => (int) $request->get('min_likes', 10),
            'max_results' => (int) $request->get('max_results', 50),
        ];

        try {
            $discovered = $this->discoveryService->discoverContent($filters);

            return redirect()->route("{$routePrefix}.x-content-discovery.index")
                ->with('success', "Discovered {$discovered} new posts.");
        } catch (\Exception $e) {
            return redirect()->route("{$routePrefix}.x-content-discovery.index")
                ->with('error', 'Failed to discover content: '.$e->getMessage());
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $validated = $request->validate([
            'tweet_id' => ['required', 'string', 'unique:x_curated_posts,tweet_id'],
            'author_username' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'media_urls' => ['sometimes', 'array'],
            'media_urls.*' => ['string'],
            'like_count' => ['sometimes', 'integer', 'min:0'],
            'retweet_count' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->discoveryService->saveCuratedPost($validated);

        return redirect()->route("{$routePrefix}.x-content-discovery.index")
            ->with('success', 'Curated post added.');
    }

    public function update(Request $request, XCuratedPost $post): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $validated = $request->validate([
            'is_featured' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $post->update($validated);

        return redirect()->route("{$routePrefix}.x-content-discovery.index")
            ->with('success', 'Curated post updated.');
    }

    public function destroy(XCuratedPost $post): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $post->delete();

        return redirect()->route("{$routePrefix}.x-content-discovery.index")
            ->with('success', 'Curated post removed.');
    }
}
