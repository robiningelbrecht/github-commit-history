<?php

namespace App\Domain;

use SleekDB\Store;

class GitHubCommitRepository
{
    public function __construct(
        private readonly Store $store
    ) {
    }

    public function findLastImportedCommit(): ?array
    {
        $commits = $this->store->findAll(['commit.timestamp' => 'desc'], 1);
        if ($commits) {
            return reset($commits);
        }

        return null;
    }

    public function addMany(array $commits): void
    {
        foreach ($commits as &$commit) {
            $commitDate = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $commit['commit']['committer']['date']);
            // Save timestamp to be able to sort on it.
            $commit['commit']['timestamp'] = $commitDate->getTimestamp();
        }
        $this->store->insertMany($commits);
    }
}
