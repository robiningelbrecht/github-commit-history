<?php

namespace App\Console;

use App\Domain\DayTime;
use App\Domain\GitHubCommitRepository;
use App\Domain\GithubRepo;
use App\Domain\GitHubRepoRepository;
use App\Domain\ProgressBar;
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

        $template = $this->twig->load('progress-bars.html.twig');

        $commitsPerDayTime = [];
        $commits = $this->gitHubCommitRepository->findAll();
        foreach ($commits as $commit) {
            $dayTime = DayTime::fromDateTime($commit->getCommitDate());
            if (!isset($commitsPerDayTime[$dayTime->value])) {
                $commitsPerDayTime[$dayTime->value] = 0;
            }
            ++$commitsPerDayTime[$dayTime->value];
        }

        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commit-history-day-time-summary.html',
            $template->render([
                'title' => array_sum(array_slice($commitsPerDayTime, 0, 2)) > array_sum(array_slice($commitsPerDayTime, 2, 2)) ? "I'm an Early 🐤" : "I'm a Night 🦉",
                'progressBars' => array_map(fn (DayTime $dayTime) => ProgressBar::fromValues(
                    $dayTime->getEmoji().' '.$dayTime->value,
                    sprintf('%s commits', $commitsPerDayTime[$dayTime->value]),
                    ($commitsPerDayTime[$dayTime->value] / count($commits)) * 100,
                ), DayTime::cases()),
            ]),
        );

        return Command::SUCCESS;
    }

    private function buildReposForWebsite(): void
    {
        $reposForWebsite = array_filter(
            $this->gitHubRepoRepository->findAll(),
            fn (GithubRepo $repo) => in_array('website', $repo->getTopics())
        );

        $reposForWebsite = array_map(function (GithubRepo $repo) {
            $repoAsArray = Json::decode(Json::encode($repo));
            $repoAsArray['topics'] = array_filter($repo->getTopics(), fn (string $topic) => 'website' !== $topic);

            return GithubRepo::fromMap($repoAsArray);
        }, $reposForWebsite);

        usort($reposForWebsite, function (GithubRepo $a, GithubRepo $b) {
            return $a->getCreatedAt()->getTimestamp() < $b->getCreatedAt()->getTimestamp() ? 1 : -1;
        });

        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/repos-for-website.json',
            Json::encode($reposForWebsite)
        );
    }
}
