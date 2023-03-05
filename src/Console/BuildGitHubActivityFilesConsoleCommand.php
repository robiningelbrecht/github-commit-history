<?php

namespace App\Console;

use App\Domain\DayTime;
use App\Domain\GitHub\GitHubCommitRepository;
use App\Domain\GitHub\GithubRepo;
use App\Domain\GitHub\GitHubRepoCommitRepositoryFactory;
use App\Domain\GitHub\GitHubRepoRepository;
use App\Domain\ProgressBar;
use App\Domain\ReadMe;
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
        private readonly GitHubRepoCommitRepositoryFactory $gitHubRepoCommitRepositoryFactory,
        private readonly Environment $twig
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dayTimeSummaryContent = $this->renderDayTimeProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commit-history-day-time-summary.html',
            $dayTimeSummaryContent,
        );

        $weekdaySummaryContent = $this->renderWeekdaysProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commit-history-week-day-summary.html',
            $weekdaySummaryContent,
        );

        $reposPerLanguageContent = $this->renderLanguageProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commit-history-language-summary.html',
            $reposPerLanguageContent,
        );

        $mostRecentCommitsContent = $this->renderMostRecentCommits();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/most-recent-commits.html',
            $mostRecentCommitsContent,
        );

        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/repos-for-website.json',
            Json::encode($this->buildReposForWebsite())
        );

        $commitsSummary = $this->buildCommitsSummary();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/commits-summary.json',
            Json::encode($commitsSummary)
        );

        $pathToReadMe = Settings::getAppRoot().'/README.md';
        $readme = ReadMe::fromPathToReadMe($pathToReadMe);

        $readme
            ->updateFirstCommitDate($commitsSummary['firstCommit'])
            ->updateTotalCommitCount($commitsSummary['totalCommits'])
            ->updateCommitsPerDayTime($dayTimeSummaryContent)
            ->updateCommitsPerWeekday($weekdaySummaryContent)
            ->updateReposPerLanguage($reposPerLanguageContent)
            ->updateMostRecentCommits($mostRecentCommitsContent);

        \Safe\file_put_contents($pathToReadMe, (string) $readme);

        return Command::SUCCESS;
    }

    private function renderDayTimeProgressBars(): string
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

    private function renderWeekdaysProgressBars(): string
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

    private function renderLanguageProgressBars(): string
    {
        $template = $this->twig->load('progress-bars.html.twig');

        $reposPerLanguage = [];
        $repos = $this->gitHubRepoRepository->findAll();
        foreach ($repos as $repo) {
            if (!$language = $repo->getMainLanguage()) {
                continue;
            }
            if (!isset($reposPerLanguage[$language])) {
                $reposPerLanguage[$language] = 0;
            }
            ++$reposPerLanguage[$language];
        }

        arsort($reposPerLanguage);

        return $template->render([
            'title' => '💬 I mostly code in '.array_search(max($reposPerLanguage), $reposPerLanguage),
            'progressBars' => array_map(fn (string $language) => ProgressBar::fromValues(
                $language,
                sprintf('%s repos', $reposPerLanguage[$language]),
                ($reposPerLanguage[$language] / count($repos)) * 100,
            ), array_keys($reposPerLanguage)),
        ]);
    }

    public function renderMostRecentCommits(): string
    {
        $template = $this->twig->load('most-recent-commits.html.twig');

        return $template->render([
            'title' => '⏳ Most recent commits',
            'commits' => $this->gitHubCommitRepository->findMostRecentCommits(10),
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

    private function buildCommitsSummary(): array
    {
        $commitsSummary = [
            'repos' => [],
        ];
        $repos = $this->gitHubRepoRepository->findAll();
        foreach ($repos as $repo) {
            $commitRepo = $this->gitHubRepoCommitRepositoryFactory->for($repo->getName());
            $commitsSummary['repos'][$repo->getName()] = [
                'fullName' => $repo->getFullName(),
                'commitCount' => count($commitRepo->findAll()),
            ];
        }

        $commitsSummary['totalCommits'] = count($this->gitHubCommitRepository->findAll());
        $commitsSummary['firstCommit'] = $this->gitHubCommitRepository->findFirstImportedCommit()->getCommitDate();
        $commitsSummary['mostRecentCommits'] = $this->gitHubCommitRepository->findMostRecentCommits(10);

        return $commitsSummary;
    }
}
