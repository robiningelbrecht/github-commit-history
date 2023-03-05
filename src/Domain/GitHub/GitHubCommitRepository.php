<?php

namespace App\Domain\GitHub;

class GitHubCommitRepository
{
    public function __construct(
        private readonly GitHubRepoRepository $gitHubRepoRepository,
        private readonly GitHubRepoCommitRepositoryFactory $gitHubRepoCommitRepositoryFactory
    ) {
    }

    /**
     * @return \App\Domain\GitHub\Commit[]
     */
    public function findAll(): array
    {
        $commits = [];
        foreach ($this->gitHubRepoRepository->findAll() as $repo) {
            $commitRepository = $this->gitHubRepoCommitRepositoryFactory->for($repo->getName());
            $commits = array_merge($commits, $commitRepository->findAll());
        }

        return $commits;
    }

    public function findFirstImportedCommit(): Commit
    {
        $fistCommit = null;
        foreach ($this->gitHubRepoRepository->findAll() as $repo) {
            $commitRepository = $this->gitHubRepoCommitRepositoryFactory->for($repo->getName());
            $firstCommitForRepo = $commitRepository->findFirstImportedCommit();
            if (is_null($fistCommit) || ($firstCommitForRepo->getCommitDate() < $fistCommit->getCommitDate())) {
                $fistCommit = $firstCommitForRepo;
            }
        }

        return $fistCommit;
    }

    /**
     * @return \App\Domain\GitHub\Commit[]
     */
    public function findMostRecentCommits(int $limit): array
    {
        $allCommits = $this->findAll();
        usort($allCommits, function (Commit $a, Commit $b) {
            return $a->getCommitDate()->getTimestamp() < $b->getCommitDate()->getTimestamp() ? 1 : -1;
        });

        return array_slice($allCommits, 0, $limit);
    }
}
