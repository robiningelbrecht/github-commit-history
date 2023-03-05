<?php

namespace App\Domain\GitHub;

class Commit implements \JsonSerializable
{
    private function __construct(
        private readonly array $data
    ) {
    }

    public static function fromMap(array $data): self
    {
        return new self($data);
    }

    public function getMessage(): string
    {
        return $this->data['commit']['message'];
    }

    public function getRepo(): string
    {
        preg_match('/https:\/\/api.github.com\/repos\/(.*?)\/(?<repo>.*?)\/commits\/[\S]*/', $this->data['url'], $matches);

        return $matches['repo'];
    }

    public function getCommitDate(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $this->data['commit']['committer']['date']);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
