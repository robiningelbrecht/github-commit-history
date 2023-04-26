<?php

namespace App\Domain\GitHub;

class GithubRepo implements \JsonSerializable
{
    private function __construct(
        private array $data
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

    public function updateStargazersCount(int $count): self
    {
        $this->data['stargazers_count'] = $count;

        return $this;
    }

    public function updateTopics(array $topics): self
    {
        $this->data['topics'] = $topics;

        return $this;
    }

    public function updateDescription(string $description = null): self
    {
        $this->data['description'] = $description;

        return $this;
    }

    public function updateLanguage(string $language = null): self
    {
        $this->data['language'] = $language;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
