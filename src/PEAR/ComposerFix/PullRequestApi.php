<?php
namespace PEAR\ComposerFix;

use Github\Api;

class PullRequestApi extends Api\PullRequest
{
    /**
     * @param string $organization
     * @param string $repository
     * @param array  $filter
     *
     * @return \Generator
     */
    public function getOpenFiltered($organization, $repository, array $filter)
    {
        $openPRs = $this->all($organization, $repository, 'open');
        foreach ($openPRs as $openPR) {
            $value = $openPR[$filter['key']];
            if (false === strpos($value, $filter['value'])) {
                continue;
            }
            yield $openPR;
        }
    }
}
