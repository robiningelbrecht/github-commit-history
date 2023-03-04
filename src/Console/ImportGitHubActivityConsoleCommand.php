<?php

namespace App\Console;

use App\Domain\GitHub;
use App\Domain\GitHubCommitRepositoryFactory;
use App\Domain\GitHubRepoRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:github:import-activity', description: 'Build site')]
class ImportGitHubActivityConsoleCommand extends Command
{
    public function __construct(
        private readonly GitHub $gitHub,
        private readonly GitHubRepoRepository $gitHubRepoRepository,
        private readonly GitHubCommitRepositoryFactory $githubCommitRepositoryFactory
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->gitHub->getRepos() as $gitHubRepo) {
            try {
                $this->gitHubRepoRepository->findOneBy($gitHubRepo['full_name']);
            } catch (EntityNotFound) {
                if ('github-commit-history' === $gitHubRepo['name']) {
                    continue;
                }

                $languages = $this->gitHub->getRepoLanguages(
                    $gitHubRepo['owner']['login'],
                    $gitHubRepo['name'],
                );
                $this->gitHubRepoRepository->add(array_merge($gitHubRepo, ['languages' => $languages]));
            }
        }

        foreach ($this->gitHubRepoRepository->findAll() as $repo) {
            $commitRepository = $this->githubCommitRepositoryFactory->for($repo['name']);
            $lastImportedCommit = $commitRepository->findLastImportedCommit();
            $sinceDate = !empty($lastImportedCommit['commit']['committer']['date']) ? \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $lastImportedCommit['commit']['committer']['date']) : null;

            foreach (['robiningelbrecht', 'robin@baldwin.be', 'robin.ingelbrecht@entityone.be'] as $author) {
                $commits = $this->gitHub->getRepoCommits(
                    $repo['owner']['login'],
                    $repo['name'],
                    $author,
                    $sinceDate
                );

                if (!$commits) {
                    continue;
                }

                $commitRepository->addMany($commits);
            }
        }

        return Command::SUCCESS;
    }
}
