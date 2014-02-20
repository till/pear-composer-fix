<?php
namespace PEAR\ComposerFix\Command;

use Symfony\Component\Console;

abstract class BaseCommand extends Console\Command\Command
{
    /**
     * @return array
     */
    protected function getAllRepositories()
    {
        $container = $this->getApplication()->getContainer();

        /** @var \PEAR\ComposerFix $fix */
        $fix = $container['fix'];

        /** @var \PEAR\ComposerFix\RepoApi $repoApi */
        $repoApi = $container['github.api.repository'];

        $repositories = $repoApi->getAllRepositories($fix->getOrg());

        echo "Found: " . count($repositories) . PHP_EOL;
        echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;

        return $repositories;
    }
}
