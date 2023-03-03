<?php

namespace App\Domain;

use App\Infrastructure\Environment\Settings;
use SleekDB\Store;

class GitHubCommitRepositoryFactory
{
    public function for(string $repoName): GitHubCommitRepository
    {
        return new GitHubCommitRepository(
            new Store('commit-'.$repoName, Settings::getAppRoot().'/database', [
                'auto_cache' => false,
                'timeout' => false,
            ])
        );
    }
}
