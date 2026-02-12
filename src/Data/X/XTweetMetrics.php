<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Data\X;

readonly class XTweetMetrics
{
    public function __construct(
        public int $retweetCount,
        public int $replyCount,
        public int $likeCount,
        public int $quoteCount,
        public int $bookmarkCount,
        public int $impressionCount,
    ) {}

    /**
     * @param  array{retweet_count?: int, reply_count?: int, like_count?: int, quote_count?: int, bookmark_count?: int, impression_count?: int}  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            retweetCount: $data['retweet_count'] ?? 0,
            replyCount: $data['reply_count'] ?? 0,
            likeCount: $data['like_count'] ?? 0,
            quoteCount: $data['quote_count'] ?? 0,
            bookmarkCount: $data['bookmark_count'] ?? 0,
            impressionCount: $data['impression_count'] ?? 0,
        );
    }

    public function getTotalEngagements(): int
    {
        return $this->retweetCount
            + $this->replyCount
            + $this->likeCount
            + $this->quoteCount;
    }

    public function getEngagementRate(): float
    {
        if ($this->impressionCount === 0) {
            return 0.0;
        }

        return ($this->getTotalEngagements() / $this->impressionCount) * 100;
    }

    /**
     * @return array<string, int|float>
     */
    public function toArray(): array
    {
        return [
            'retweet_count' => $this->retweetCount,
            'reply_count' => $this->replyCount,
            'like_count' => $this->likeCount,
            'quote_count' => $this->quoteCount,
            'bookmark_count' => $this->bookmarkCount,
            'impression_count' => $this->impressionCount,
            'total_engagements' => $this->getTotalEngagements(),
            'engagement_rate' => round($this->getEngagementRate(), 2),
        ];
    }
}
