<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Data\X;

use Illuminate\Support\Collection;

readonly class XSearchResponse
{
    /**
     * @param  Collection<int, XTweetData>  $tweets
     * @param  Collection<string, XUserData>  $users
     */
    public function __construct(
        public Collection $tweets,
        public Collection $users,
        public ?string $nextToken = null,
        public int $resultCount = 0,
    ) {}

    /**
     * @param  array{data?: array<int, array<string, mixed>>, includes?: array{users?: array<int, array<string, mixed>>}, meta?: array{next_token?: string, result_count?: int}}  $response
     */
    public static function fromApiResponse(array $response): self
    {
        $users = collect($response['includes']['users'] ?? [])
            ->mapWithKeys(function (array $userData) {
                $user = XUserData::fromApiResponse($userData);

                return [$user->id => $user];
            });

        $tweets = collect($response['data'] ?? [])
            ->map(function (array $tweetData) use ($users) {
                $author = $users->get($tweetData['author_id']);

                return XTweetData::fromApiResponse($tweetData, $author);
            });

        return new self(
            tweets: $tweets,
            users: $users,
            nextToken: $response['meta']['next_token'] ?? null,
            resultCount: $response['meta']['result_count'] ?? count($response['data'] ?? []),
        );
    }

    public function hasMoreResults(): bool
    {
        return $this->nextToken !== null;
    }

    public function isEmpty(): bool
    {
        return $this->tweets->isEmpty();
    }

    public function getUser(string $userId): ?XUserData
    {
        return $this->users->get($userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tweets' => $this->tweets->map(fn (XTweetData $t) => $t->toArray())->values()->all(),
            'users' => $this->users->map(fn (XUserData $u) => $u->toArray())->all(),
            'next_token' => $this->nextToken,
            'result_count' => $this->resultCount,
        ];
    }
}
