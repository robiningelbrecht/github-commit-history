<?php

namespace App\Domain;

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

    public function getOwnerLogin(): string
    {
        return $this->data['owner']['login'];
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
