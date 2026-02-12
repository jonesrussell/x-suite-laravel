<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Data\X;

readonly class XPostTweetResponse
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}

    /**
     * @param  array{id: string, text: string}  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: $data['id'],
            text: $data['text'] ?? '',
        );
    }

    /**
     * @return array{id: string, text: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
        ];
    }
}
