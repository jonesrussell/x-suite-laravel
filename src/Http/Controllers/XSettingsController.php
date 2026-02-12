<?php

namespace JonesRussell\XSuite\Http\Controllers;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class XSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        $token = DB::table('x_oauth2_tokens')
            ->where(function ($query) {
                $query->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
            })
            ->orderBy('created_at', 'desc')
            ->first();

        $connectionStatus = [
            'connected' => false,
            'username' => null,
            'name' => null,
            'connected_at' => null,
            'expires_at' => null,
        ];

        if ($token) {
            $connectionStatus['connected'] = true;
            $connectionStatus['connected_at'] = $token->created_at;
            $connectionStatus['expires_at'] = $token->expires_at;

            try {
                $userInfo = $this->getAuthenticatedUserInfo($token->access_token);
                if ($userInfo) {
                    $connectionStatus['username'] = $userInfo['username'] ?? null;
                    $connectionStatus['name'] = $userInfo['name'] ?? null;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch X account info', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $oauth2CallbackUrl = route("{$routePrefix}.x-oauth2.callback");

        $prefix = config('x-suite.inertia_page_prefix', 'Admin');

        return Inertia::render("{$prefix}/XSettings/Index", [
            'connectionStatus' => $connectionStatus,
            'oauth2CallbackUrl' => $oauth2CallbackUrl,
        ]);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $routePrefix = config('x-suite.route_name_prefix', 'admin');

        DB::table('x_oauth2_tokens')->truncate();

        return redirect()->route("{$routePrefix}.x-settings.index")
            ->with('success', 'X account disconnected successfully.');
    }

    protected function getAuthenticatedUserInfo(string $accessToken): ?array
    {
        $client = new GuzzleClient([
            'base_uri' => 'https://api.x.com/2/',
        ]);

        $response = $client->get('users/me', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
            RequestOptions::QUERY => [
                'user.fields' => 'id,username,name',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'] ?? null;
    }
}
