<?php

namespace App\Console;

use App\Domain\GitHubCommitRepository;
use App\Domain\GitHubRepoRepository;
use App\Infrastructure\Environment\Settings;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

#[AsCommand(name: 'app:github:build-files', description: 'Build site')]
class BuildGitHubActivityFilesConsoleCommand extends Command
{
    public function __construct(
        private readonly GitHubRepoRepository $gitHubRepoRepository,
        private readonly GitHubCommitRepository $gitHubCommitRepository,
        private readonly Environment $twig
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->buildReposForWebsite();

        $DAY_TIME_EMOJI = ['🌞', '🌆', '🌃', '🌙'];
        $DAY_TIME_NAMES = ['Morning', 'Daytime', 'Evening', 'Night'];
        $WEEK_DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $template = $this->twig->load('commit-history-day-time-summary.html.twig');

        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commit-history-day-time-summary.json',
            Json::encode(['content' => $template->render([])]),
        );

        return Command::SUCCESS;
    }

    private function buildReposForWebsite(): void
    {
        $reposForWebsite = array_filter(
            $this->gitHubRepoRepository->findAll(),
            fn (array $repo) => in_array('website', $repo['topics'])
        );

        $reposForWebsite = array_map(function (array $repo) {
            $repo['topics'] = array_filter($repo['topics'], fn (string $topic) => 'website' !== $topic);

            return $repo;
        }, $reposForWebsite);

        usort($reposForWebsite, function (array $a, array $b) {
            return (new \DateTimeImmutable($a['created_at']))->getTimestamp() < (new \DateTimeImmutable($b['created_at']))->getTimestamp() ? 1 : -1;
        });

        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/repos-for-website.json',
            Json::encode($reposForWebsite)
        );
    }
}
