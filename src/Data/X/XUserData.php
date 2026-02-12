<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Data\X;

readonly class XUserData
{
    public function __construct(
        public string $id,
        public string $username,
        public ?string $name = null,
    ) {}

    /**
     * @param  array{id: string, username: string, name?: string}  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: $data['id'],
            username: $data['username'],
            name: $data['name'] ?? null,
        );
    }

    public function getProfileUrl(): string
    {
        return "https://x.com/{$this->username}";
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->username;
    }

    /**
     * @return array{id: string, username: string, name: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
        ];
    }
}
