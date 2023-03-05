<?php

namespace App\Domain\GitHub;

use App\Infrastructure\Serialization\Json;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class GitHub
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    public function __construct(
        private readonly Client $client,
        private readonly GitHubAccessToken $gitHubAccessToken
    ) {
    }

    private function request(
        string $path,
        string $method = 'GET',
        array $options = []): array
    {
        $options = array_merge([
            'base_uri' => 'https://api.github.com/',
            RequestOptions::HEADERS => [
                'Authorization' => "bearer {$this->gitHubAccessToken}",
            ],
        ], $options);
        $response = $this->client->request($method, $path, $options);

        return Json::decode($response->getBody()->getContents());
    }

    public function getRepos(): array
    {
        $options = [
            RequestOptions::QUERY => [
                'per_page' => 100,
                'sort' => 'pushed',
            ],
        ];

        return $this->request('user/repos', 'GET', $options);
    }

    public function getRepoLanguages(string $owner, string $name): array
    {
        return $this->request(sprintf('repos/%s/%s/languages', $owner, $name));
    }

    public function getRepoCommits(
        string $owner,
        string $name,
        string $author,
        \DateTimeImmutable $since = null): array
    {
        $commits = [];
        $page = 1;
        do {
            $options = [
                RequestOptions::QUERY => [
                    'per_page' => 100,
                    'page' => $page,
                    'sort' => 'pushed',
                    'author' => $author,
                ],
            ];

            if ($since) {
                $options[RequestOptions::QUERY]['since'] = $since->add(\DateInterval::createFromDateString('1 second'))->format(self::DATE_FORMAT);
            }

            $response = $this->request(sprintf('repos/%s/%s/commits', $owner, $name), 'GET', $options);
            $commits = array_merge($commits, $response);
            ++$page;
        } while (!empty($response));

        return $commits;
    }
}
