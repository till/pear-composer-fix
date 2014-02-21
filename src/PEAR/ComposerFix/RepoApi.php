<?php
namespace PEAR\ComposerFix;

use Github\Api;
use Github\HttpClient\Message;
use Guzzle\Http\Message\Header;

/**
 * I heard you like loops.
 */
class RepoApi extends Api\User
{
    const THE_LAST_PAGE_OF_GITHUB = 10000000;

    /**
     * @param string $org
     *
     * @return array
     */
    public function getAllRepositories($org)
    {
        $parameters = [
            'per_page' => 100,
            'type' => 'all',
        ];

        $lastPage = self::THE_LAST_PAGE_OF_GITHUB; // extreme

        $path = 'orgs/' . rawurlencode($org) . '/repos';

        $repositories = [];

        for ($parameters['page'] = 1; $parameters['page'] <= $lastPage; ++$parameters['page']) {

            $response = $this->makeRequest($path, $parameters);
            if ($lastPage === self::THE_LAST_PAGE_OF_GITHUB) {
                $lastPage = $this->getLastPage($response->getHeader('link'));
            }

            $repositories = array_merge($repositories, Message\ResponseMediator::getContent($response));

        }

        return $repositories;
    }

    private function getLastPage(Header\Link $header)
    {
        $data = $header->getLink('last');
        if (!isset($data['url'])) {
            throw new \RuntimeException("Something is wrong.");
        }
        $queryString = parse_url($data['url'], \PHP_URL_QUERY);
        parse_str($queryString); // LOL
        return $page;
    }

    /**
     * @param string $path
     * @param array $parameters
     *
     * @return \Guzzle\Http\Message\Response
     */
    private function makeRequest($path, array $parameters)
    {
        $response = $this->client->getHttpClient()->get(
            $path,
            $parameters,
            []
        );
        return $response;
    }
}
