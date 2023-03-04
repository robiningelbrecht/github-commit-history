<?php

namespace App\Domain;

class GitHubCommitRepository
{
    public function __construct(
        private readonly GitHubRepoRepository $gitHubRepoRepository,
        private readonly GitHubRepoCommitRepositoryFactory $gitHubRepoCommitRepositoryFactory
    ) {
    }

    public function getAllCommits(): array
    {
        $commits = [];
        foreach ($this->gitHubRepoRepository->findAll() as $repo) {
            $commitRepository = $this->gitHubRepoCommitRepositoryFactory->for($repo->getName());
            $commits = array_merge($commits, $commitRepository->findAll());
        }

        return $commits;
    }
}
