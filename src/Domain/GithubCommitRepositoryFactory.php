<?php

namespace App\Domain;

use App\Infrastructure\Environment\Settings;
use SleekDB\Store;

class GithubCommitRepositoryFactory
{
    public function for(string $repoName): GithubCommitRepository
    {
        return new GithubCommitRepository(
            new Store('commit-'.$repoName, Settings::getAppRoot().'/database', [
                'auto_cache' => false,
                'timeout' => false,
            ])
        );
    }
}
