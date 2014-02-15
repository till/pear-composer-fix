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
    public function getAllRepositories($org)
    {
        $parameters = [
            'page' => 1,
            'per_page' => 200,
            'type' => 'all',
        ];

        $lastPage = null;

        $path = 'orgs/' . rawurlencode($org) . '/repos';

        $response = $this->client->getHttpClient()->get(
            $path,
            $parameters,
            []
        );

        if (null === $lastPage) {
            $lastPage = $this->getLastPage($response->getHeader('link'));
        }

        $parameters['page']++;

        $repositories = Message\ResponseMediator::getContent($response);

        while ($parameters['page'] <= $lastPage) {

            var_dump(http_build_query($parameters));

            $response = $this->client->getHttpClient()->get(
                $path,
                $parameters,
                []
            );
            $repositories = array_merge($repositories, Message\ResponseMediator::getContent($response));

            $parameters['page']++;
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
}
