<?php

namespace App\Console;

use App\Domain\GitHub\Commit;
use App\Domain\GitHub\GitHub;
use App\Domain\GitHub\GithubRepo;
use App\Domain\GitHub\GitHubRepoCommitRepositoryFactory;
use App\Domain\GitHub\GitHubRepoRepository;
use App\Infrastructure\Exception\EntityNotFound;
use GuzzleHttp\Exception\RequestException;
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
        private readonly GitHubRepoCommitRepositoryFactory $gitHubRepoCommitRepositoryFactory
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->gitHub->getRepos() as $gitHubRepo) {
            try {
                $repo = $this->gitHubRepoRepository->findOneBy($gitHubRepo['full_name']);
                $repo
                    ->updateStargazersCount($gitHubRepo['stargazers_count'])
                    ->updateTopics($gitHubRepo['topics'])
                    ->updateDescription($gitHubRepo['description'] ?? null)
                    ->updateLanguage($gitHubRepo['language']);

                $this->gitHubRepoRepository->update($repo);
            } catch (EntityNotFound) {
                if ('github-commit-history' === $gitHubRepo['name']) {
                    continue;
                }

                $languages = $this->gitHub->getRepoLanguages(
                    $gitHubRepo['owner']['login'],
                    $gitHubRepo['name'],
                );
                $this->gitHubRepoRepository->add(GithubRepo::fromMap(array_merge($gitHubRepo, ['languages' => $languages])));
            }
        }

        foreach ($this->gitHubRepoRepository->findAll() as $repo) {
            $commitRepository = $this->gitHubRepoCommitRepositoryFactory->for($repo->getName());
            $sinceDate = $commitRepository->findLastImportedCommit()?->getCommitDate();

            foreach (['robiningelbrecht', 'robin@baldwin.be', 'robin.ingelbrecht@entityone.be'] as $author) {
                try {
                    $commits = $this->gitHub->getRepoCommits(
                        $repo->getOwnerLogin(),
                        $repo->getName(),
                        $author,
                        $sinceDate
                    );
                } catch (RequestException) {
                    continue;
                }

                if (!$commits) {
                    continue;
                }

                foreach ($commits as &$commit) {
                    $commitDate = \DateTimeImmutable::createFromFormat(GitHub::DATE_FORMAT, $commit['commit']['committer']['date']);
                    // Save timestamp to be able to sort on it.
                    $commit['commit']['timestamp'] = $commitDate->getTimestamp();
                }

                $commitRepository->addMany(
                    array_map(
                        fn (array $commit) => Commit::fromMap($commit),
                        $commits
                    )
                );
            }
        }

        return Command::SUCCESS;
    }
}
