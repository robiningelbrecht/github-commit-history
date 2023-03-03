<?php

namespace App\Domain;

use App\Infrastructure\Exception\EntityNotFound;
use SleekDB\Store;

class GitHubRepoRepository
{
    public function __construct(
        private readonly Store $store
    ) {
    }

    public function findAll(): array
    {
        return $this->store->findAll();
    }

    public function findOneBy(string $fullName): array
    {
        if (!$row = $this->store->findOneBy(['full_name', '==', $fullName])) {
            throw new EntityNotFound(sprintf('Repo "%s" not found', $fullName));
        }

        return $row;
    }

    public function add(array $repo): void
    {
        $this->store->insert($repo);
    }
}
