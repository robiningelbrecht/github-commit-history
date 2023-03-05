<?php

namespace App\Domain;

class ReadMe implements \Stringable
{
    private function __construct(
        private string $content
    ) {
    }

    public function updateFirstCommitDate(\DateTimeImmutable $firstCommitDate): self
    {
        $this->pregReplace('first-commit-date', '`'.$firstCommitDate->format('d-m-Y').'`');

        return $this;
    }

    public function updateTotalCommitCount(int $totalCommitCount): self
    {
        $this->pregReplace('total-commit-count', '`'.$totalCommitCount.'`');

        return $this;
    }

    public function updateCommitsPerDayTime(string $content): self
    {
        $this->pregReplace('commits-per-day-time', $content, true);

        return $this;
    }

    public function updateCommitsPerWeekday(string $content): self
    {
        $this->pregReplace('commits-per-weekday', $content, true);

        return $this;
    }

    public function updateReposPerLanguage(string $content): self
    {
        $this->pregReplace('repos-per-language', $content, true);

        return $this;
    }

    public function updateMostRecentCommits(string $content): self
    {
        $this->pregReplace('most-recent-commits', $content, true);

        return $this;
    }

    private function pregReplace(string $sectionName, string $replaceWith, bool $enforceNewLines = false): void
    {
        if (!$enforceNewLines) {
            $this->content = preg_replace(
                sprintf('/<!--START_SECTION:%s-->[\s\S]+<!--END_SECTION:%s-->/', $sectionName, $sectionName),
                sprintf('<!--START_SECTION:%s-->%s<!--END_SECTION:%s-->', $sectionName, $replaceWith, $sectionName),
                $this->content
            );

            return;
        }

        $this->content = preg_replace(
            sprintf('/<!--START_SECTION:%s-->[\s\S]+<!--END_SECTION:%s-->/', $sectionName, $sectionName),
            implode("\n", [
                sprintf('<!--START_SECTION:%s-->', $sectionName),
                $replaceWith,
                sprintf('<!--END_SECTION:%s-->', $sectionName),
            ]),
            $this->content
        );
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public static function fromPathToReadMe(string $path): self
    {
        return new self(\Safe\file_get_contents($path));
    }
}
