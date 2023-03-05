<?php

namespace App\Domain\GitHub;

class GithubRepo implements \JsonSerializable
{
    private function __construct(
        private readonly array $data
    ) {
    }

    public static function fromMap(array $data): self
    {
        return new self($data);
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getFullName(): string
    {
        return $this->data['full_name'];
    }

    public function getOwnerLogin(): string
    {
        return $this->data['owner']['login'];
    }

    public function getTopics(): array
    {
        return $this->data['topics'];
    }

    public function getLanguages(): array
    {
        return $this->data['languages'];
    }

    public function getMainLanguage(): ?string
    {
        if (empty($this->data['languages'])) {
            return null;
        }

        return array_search(max($this->data['languages']), $this->data['languages']);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->data['created_at']);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
