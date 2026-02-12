<?php

namespace JonesRussell\XSuite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use JonesRussell\XSuite\Models\XAutoReplyRule;
use JonesRussell\XSuite\Services\XAutoReplyService;

class XAutoReplyController extends Controller
{
    public function __construct(
        protected XAutoReplyService $autoReplyService
    ) {}

    public function index(): Response
    {
        $rules = XAutoReplyRule::query()
            ->orderedByPriority()
            ->paginate(20);

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XAutoReplies/Index", [
            'rules' => $rules,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_keywords' => ['required', 'array', 'min:1'],
            'trigger_keywords.*' => ['required', 'string'],
            'trigger_type' => ['required', 'in:mention,hashtag,keyword'],
            'reply_template' => ['required', 'string', 'max:280'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        XAutoReplyRule::create($validated);

        return redirect()->route("{$routePrefix}.x-auto-replies.index")
            ->with('success', 'Auto-reply rule created.');
    }

    public function update(Request $request, XAutoReplyRule $rule): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trigger_keywords' => ['sometimes', 'required', 'array', 'min:1'],
            'trigger_keywords.*' => ['required', 'string'],
            'trigger_type' => ['sometimes', 'required', 'in:mention,hashtag,keyword'],
            'reply_template' => ['sometimes', 'required', 'string', 'max:280'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $rule->update($validated);

        return redirect()->route("{$routePrefix}.x-auto-replies.index")
            ->with('success', 'Auto-reply rule updated.');
    }

    public function destroy(XAutoReplyRule $rule): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $rule->delete();

        return redirect()->route("{$routePrefix}.x-auto-replies.index")
            ->with('success', 'Auto-reply rule deleted.');
    }

    public function toggle(XAutoReplyRule $rule): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $rule->update(['is_active' => ! $rule->is_active]);

        return redirect()->route("{$routePrefix}.x-auto-replies.index")
            ->with('success', 'Auto-reply rule status updated.');
    }

    public function test(Request $request, XAutoReplyRule $rule): Response
    {
        $text = $request->get('text', '');
        $matches = $this->autoReplyService->testRule($rule, $text);

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XAutoReplies/Test", [
            'rule' => $rule,
            'testText' => $text,
            'matches' => $matches,
            'generatedReply' => $matches ? $rule->generateReply() : null,
        ]);
    }
}
