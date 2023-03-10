<?php

namespace App\Domain\GitHub;

use App\Infrastructure\Exception\EntityNotFound;
use SleekDB\Store;

class GitHubRepoRepository
{
    public function __construct(
        private readonly Store $store
    ) {
    }

    /**
     * @return \App\Domain\GitHub\GithubRepo[]
     */
    public function findAll(): array
    {
        return array_map(
            fn (array $row) => GithubRepo::fromMap($row),
            $this->store->findAll()
        );
    }

    public function findOneBy(string $fullName): GithubRepo
    {
        if (!$row = $this->store->findOneBy(['full_name', '==', $fullName])) {
            throw new EntityNotFound(sprintf('Repo "%s" not found', $fullName));
        }

        return GithubRepo::fromMap($row);
    }

    public function add(GithubRepo $repo): void
    {
        $this->store->insert($repo->jsonSerialize());
    }

    public function update(GithubRepo $repo): void
    {
        $this->store->update($repo->jsonSerialize());
    }
}
