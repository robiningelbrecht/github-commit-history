<?php

namespace App\Domain;

use App\Infrastructure\Environment\Settings;
use SleekDB\Store;

class GitHubRepoCommitRepositoryFactory
{
    public function for(string $repoName): GitHubRepoCommitRepository
    {
        return new GitHubRepoCommitRepository(
            new Store('commit-'.$repoName, Settings::getAppRoot().'/database', [
                'auto_cache' => false,
                'timeout' => false,
            ])
        );
    }
}
