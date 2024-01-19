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
    private const MARKDOWN = 'markdown';
    private const HTML = 'html';

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
        $output->writeln('Building files');
        $output->writeln('Rendering daytime progress bars');
        $dayTimeSummaryContent = $this->renderDayTimeProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/markdown/commit-history-day-time-summary.md',
            $dayTimeSummaryContent[self::MARKDOWN],
        );
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/html/commit-history-day-time-summary.html',
            $dayTimeSummaryContent[self::HTML],
        );

        $output->writeln('Rendering week days progress bars');
        $weekdaySummaryContent = $this->renderWeekdaysProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/markdown/commit-history-week-day-summary.md',
            $weekdaySummaryContent[self::MARKDOWN],
        );
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/html/commit-history-week-day-summary.html',
            $weekdaySummaryContent[self::HTML],
        );

        $output->writeln('Rendering language progress bars');
        $reposPerLanguageContent = $this->renderLanguageProgressBars();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/markdown/commit-history-language-summary.md',
            $reposPerLanguageContent[self::MARKDOWN],
        );
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/html/commit-history-language-summary.html',
            $reposPerLanguageContent[self::HTML],
        );

        $output->writeln('Rendering most recent commits');
        $mostRecentCommitsContent = $this->renderMostRecentCommits();
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/markdown/most-recent-commits.md',
            $mostRecentCommitsContent[self::MARKDOWN],
        );
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/html/most-recent-commits.html',
            $mostRecentCommitsContent[self::HTML],
        );

        $output->writeln('Rendering repos for site');
        \Safe\file_put_contents(
            Settings::getAppRoot().'/build/repos-for-website.json',
            Json::encode($this->buildReposForWebsite())
        );

        $output->writeln('Rendering commits summary');
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
            ->updateCommitsPerDayTime($dayTimeSummaryContent[self::MARKDOWN])
            ->updateCommitsPerWeekday($weekdaySummaryContent[self::MARKDOWN])
            ->updateReposPerLanguage($reposPerLanguageContent[self::MARKDOWN])
            ->updateMostRecentCommits($mostRecentCommitsContent[self::MARKDOWN]);

        \Safe\file_put_contents($pathToReadMe, (string) $readme);

        return Command::SUCCESS;
    }

    private function renderDayTimeProgressBars(): array
    {
        $commitsPerDayTime = [];
        $commits = $this->gitHubCommitRepository->findAll();
        foreach ($commits as $commit) {
            $dayTime = DayTime::fromDateTime($commit->getCommitDate());
            if (!isset($commitsPerDayTime[$dayTime->value])) {
                $commitsPerDayTime[$dayTime->value] = 0;
            }
            ++$commitsPerDayTime[$dayTime->value];
        }

        $templateContext = [
            'title' => array_sum(array_slice($commitsPerDayTime, 0, 2)) > array_sum(array_slice($commitsPerDayTime, 2, 2)) ? "I'm an Early 🐤" : "I'm a Night 🦉",
            'progressBars' => array_map(fn (DayTime $dayTime) => ProgressBar::fromValues(
                $dayTime->getEmoji().' '.$dayTime->value,
                sprintf('%s commits', $commitsPerDayTime[$dayTime->value]),
                ($commitsPerDayTime[$dayTime->value] / count($commits)) * 100,
            ), DayTime::cases()),
        ];

        return [
            self::MARKDOWN => $this->twig->load('progress-bars-markdown.html.twig')->render($templateContext),
            self::HTML => $this->twig->load('progress-bars-html.html.twig')->render($templateContext),
        ];
    }

    private function renderWeekdaysProgressBars(): array
    {
        $commitsPerWeekday = [];
        $commits = $this->gitHubCommitRepository->findAll();
        foreach ($commits as $commit) {
            $weekday = Weekday::fromDateTime($commit->getCommitDate());
            if (!isset($commitsPerWeekday[$weekday->value])) {
                $commitsPerWeekday[$weekday->value] = 0;
            }
            ++$commitsPerWeekday[$weekday->value];
        }

        $templateContext = [
            'title' => "📅 I'm Most Productive on ".array_search(max($commitsPerWeekday), $commitsPerWeekday),
            'progressBars' => array_map(fn (Weekday $weekday) => ProgressBar::fromValues(
                $weekday->value,
                sprintf('%s commits', $commitsPerWeekday[$weekday->value]),
                ($commitsPerWeekday[$weekday->value] / count($commits)) * 100,
            ), Weekday::cases()),
        ];

        return [
            self::MARKDOWN => $this->twig->load('progress-bars-markdown.html.twig')->render($templateContext),
            self::HTML => $this->twig->load('progress-bars-html.html.twig')->render($templateContext),
        ];
    }

    private function renderLanguageProgressBars(): array
    {
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

        $templateContext = [
            'title' => '💬 I mostly code in '.array_search(max($reposPerLanguage), $reposPerLanguage),
            'progressBars' => array_map(fn (string $language) => ProgressBar::fromValues(
                $language,
                sprintf('%s repos', $reposPerLanguage[$language]),
                ($reposPerLanguage[$language] / count($repos)) * 100,
            ), array_keys($reposPerLanguage)),
        ];

        return [
            self::MARKDOWN => $this->twig->load('progress-bars-markdown.html.twig')->render($templateContext),
            self::HTML => $this->twig->load('progress-bars-html.html.twig')->render($templateContext),
        ];
    }

    public function renderMostRecentCommits(): array
    {
        $templateContext = [
            'title' => '⏳ Most recent commits',
            'commits' => $this->gitHubCommitRepository->findMostRecentCommits(10),
        ];

        return [
            self::MARKDOWN => $this->twig->load('most-recent-commits-markdown.html.twig')->render($templateContext),
            self::HTML => $this->twig->load('most-recent-commits-html.html.twig')->render($templateContext),
        ];
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
