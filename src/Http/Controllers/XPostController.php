<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use JonesRussell\XSuite\Enums\XPostStatus;
use JonesRussell\XSuite\Http\Requests\ScheduleXPostRequest;
use JonesRussell\XSuite\Http\Requests\StoreXPostRequest;
use JonesRussell\XSuite\Http\Resources\XPostResource;
use JonesRussell\XSuite\Jobs\PublishXPost;
use JonesRussell\XSuite\Models\XPost;

class XPostController extends Controller
{
    public function index(Request $request): Response
    {
        $query = XPost::query()->with('user');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where('content', 'like', "%{$search}%");
        }

        $xPosts = $query->latest()->paginate(20);

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XPosts/Index", [
            'xPosts' => XPostResource::collection($xPosts),
            'queryParams' => $request->only(['status', 'search']),
            'statuses' => collect(XPostStatus::cases())->mapWithKeys(fn ($status) => [
                $status->value => $status->label(),
            ])->all(),
        ]);
    }

    public function create(): Response
    {
        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XPosts/Create", [
            'maxTweetLength' => (int) config('x-suite.max_tweet_length', 280),
        ]);
    }

    public function store(StoreXPostRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['user_id'] = auth()->id();
        $publishImmediately = $validated['publish_immediately'] ?? false;

        unset($validated['publish_immediately']);

        $xPost = XPost::create($validated);

        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        if ($publishImmediately) {
            PublishXPost::dispatchSync($xPost);

            return redirect()->route("{$routePrefix}.x-posts.index")
                ->with('success', 'X post published successfully.');
        }

        $message = match ($xPost->status) {
            XPostStatus::Scheduled => 'X post scheduled for '.
                $xPost->scheduled_for->format('M j, Y g:i A'),
            default => 'X post draft created successfully.',
        };

        return redirect()->route("{$routePrefix}.x-posts.index")
            ->with('success', $message);
    }

    public function show(XPost $xPost): Response
    {
        $xPost->load('user');

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XPosts/Show", [
            'xPost' => new XPostResource($xPost),
        ]);
    }

    public function edit(XPost $xPost): Response|RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        if (! $xPost->canEdit()) {
            return redirect()->route("{$routePrefix}.x-posts.index")
                ->with('error', 'Only draft and failed posts can be edited.');
        }

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XPosts/Edit", [
            'xPost' => $xPost,
            'maxTweetLength' => (int) config('x-suite.max_tweet_length', 280),
        ]);
    }

    public function update(StoreXPostRequest $request, XPost $xPost): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        if (! $xPost->canEdit()) {
            return redirect()->route("{$routePrefix}.x-posts.index")
                ->with('error', 'Only draft and failed posts can be edited.');
        }

        $xPost->update($request->validated());

        return redirect()->route("{$routePrefix}.x-posts.index")
            ->with('success', 'X post updated successfully.');
    }

    public function destroy(XPost $xPost): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        if ($xPost->isPublished()) {
            return redirect()->route("{$routePrefix}.x-posts.index")
                ->with('error', 'Published posts cannot be deleted.');
        }

        $xPost->delete();

        return redirect()->route("{$routePrefix}.x-posts.index")
            ->with('success', 'X post deleted successfully.');
    }

    public function schedule(ScheduleXPostRequest $request, XPost $xPost): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        if (! $xPost->canSchedule()) {
            return redirect()->route("{$routePrefix}.x-posts.index")
                ->with('error', 'This post cannot be scheduled in its current state.');
        }

        $scheduledFor = new \DateTime($request->validated('scheduled_for'));
        $xPost->markAsScheduled($scheduledFor);

        return redirect()->route("{$routePrefix}.x-posts.index")
            ->with('success', 'X post scheduled for '.$xPost->scheduled_for->format('M j, Y g:i A'));
    }

    public function publish(XPost $xPost): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        if (! $xPost->canPublish()) {
            return redirect()->route("{$routePrefix}.x-posts.index")
                ->with('error', 'This post cannot be published in its current state.');
        }

        PublishXPost::dispatch($xPost);

        return redirect()->route("{$routePrefix}.x-posts.index")
            ->with('success', 'X post queued for immediate publishing.');
    }

    public function cancel(XPost $xPost): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        if (! $xPost->isScheduled()) {
            return redirect()->route("{$routePrefix}.x-posts.index")
                ->with('error', 'Only scheduled posts can be cancelled.');
        }

        $xPost->cancel();

        return redirect()->route("{$routePrefix}.x-posts.index")
            ->with('success', 'Scheduled post cancelled.');
    }
}
