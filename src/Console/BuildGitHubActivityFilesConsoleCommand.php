<?php

namespace App\Console;

use App\Domain\DayTime;
use App\Domain\GitHubCommitRepository;
use App\Domain\GithubRepo;
use App\Domain\GitHubRepoRepository;
use App\Domain\ProgressBar;
use App\Domain\Weekday;
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
        $dayTimeSummaryContent = $this->buildDayTimeProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commit-history-day-time-summary.html',
            $dayTimeSummaryContent,
        );

        $weekdaySummaryContent = $this->buildWeekdaysProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commit-history-week-day-summary.html',
            $weekdaySummaryContent,
        );

        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/repos-for-website.json',
            Json::encode($this->buildReposForWebsite())
        );

        $pathToReadMe = Settings::getAppRoot().'/README.md';
        $readme = \Safe\file_get_contents($pathToReadMe);

        $readme = preg_replace(
            '/<!--START_SECTION:commits-per-day-time-->\s(.*?)\s<!--END_SECTION:commits-per-day-time-->/',
            implode("\n", [
                '<!--START_SECTION:commits-per-day-time-->',
                $dayTimeSummaryContent,
                '<!--END_SECTION:commits-per-day-time-->',
            ]),
            $readme
        );
        $readme = preg_replace(
            '/<!--START_SECTION:commits-per-weekday-->\s(.*?)\s<!--END_SECTION:commits-per-weekday-->/',
            implode("\n", [
                '<!--START_SECTION:commits-per-weekday-->',
                $weekdaySummaryContent,
                '<!--END_SECTION:commits-per-weekday-->',
            ]),
            $readme
        );
        \Safe\file_put_contents($pathToReadMe, $readme);

        return Command::SUCCESS;
    }

    private function buildDayTimeProgressBars(): string
    {
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

        return $template->render([
            'title' => array_sum(array_slice($commitsPerDayTime, 0, 2)) > array_sum(array_slice($commitsPerDayTime, 2, 2)) ? "I'm an Early 🐤" : "I'm a Night 🦉",
            'progressBars' => array_map(fn (DayTime $dayTime) => ProgressBar::fromValues(
                $dayTime->getEmoji().' '.$dayTime->value,
                sprintf('%s commits', $commitsPerDayTime[$dayTime->value]),
                ($commitsPerDayTime[$dayTime->value] / count($commits)) * 100,
            ), DayTime::cases()),
        ]);
    }

    private function buildWeekdaysProgressBars(): string
    {
        $template = $this->twig->load('progress-bars.html.twig');

        $commitsPerWeekday = [];
        $commits = $this->gitHubCommitRepository->findAll();
        foreach ($commits as $commit) {
            $weekday = Weekday::fromDateTime($commit->getCommitDate());
            if (!isset($commitsPerWeekday[$weekday->value])) {
                $commitsPerWeekday[$weekday->value] = 0;
            }
            ++$commitsPerWeekday[$weekday->value];
        }

        return $template->render([
            'title' => "📅 I'm Most Productive on ".array_search(max($commitsPerWeekday), $commitsPerWeekday),
            'progressBars' => array_map(fn (Weekday $weekday) => ProgressBar::fromValues(
                $weekday->value,
                sprintf('%s commits', $commitsPerWeekday[$weekday->value]),
                ($commitsPerWeekday[$weekday->value] / count($commits)) * 100,
            ), Weekday::cases()),
        ]);
    }

    private function buildReposForWebsite(): array
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

        return $reposForWebsite;
    }
}
