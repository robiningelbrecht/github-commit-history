<?php

namespace App\Domain;

use App\Infrastructure\Serialization\Json;
use SleekDB\Store;

class GitHubRepoCommitRepository
{
    public function __construct(
        private readonly Store $store
    ) {
    }

    /**
     * @return Commit[]
     */
    public function findAll(): array
    {
        return array_map(
            fn (array $row) => Commit::fromMap($row),
            $this->store->findAll()
        );
    }

    public function findLastImportedCommit(): ?Commit
    {
        $commits = $this->store->findAll(['commit.timestamp' => 'desc'], 1);
        if ($commits) {
            return Commit::fromMap(reset($commits));
        }

        return null;
    }

    public function findFirstImportedCommit(): ?Commit
    {
        $commits = $this->store->findAll(['commit.timestamp' => 'asc'], 1);
        if ($commits) {
            return Commit::fromMap(reset($commits));
        }

        return null;
    }

    public function addMany(array $commits): void
    {
        $this->store->insertMany(Json::decode(Json::encode($commits)));
    }
}
