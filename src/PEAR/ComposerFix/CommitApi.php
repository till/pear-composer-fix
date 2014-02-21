<?php
namespace PEAR\ComposerFix;

use Github\Api;

class CommitApi extends Api\PullRequest
{
    public function getLastCommit($organization, $repository, $branch)
    {
        $response = $this->get(
            sprintf(
                'repos/%s/%s/git/refs/heads/%s',
                $organization,
                $repository,
                $branch
            )
        );

        $path = substr(parse_url($response['object']['url'], PHP_URL_PATH), 1);

        return $this->get($path);
    }
}
