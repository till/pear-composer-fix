<?php
namespace PEAR\ComposerFix\Command;

use Symfony\Component\Console;

abstract class BaseCommand extends Console\Command\Command
{
    /**
     * @var \Github\Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $container;

    /**
     * @var \PEAR\ComposerFix
     */
    protected $fix;

    protected function setUp()
    {
        $this->container = $this->getApplication()->getContainer();

        $this->fix = $this->container['fix']($this->container['config']);
        $this->client = $this->container['github.client']($this->fix);
    }

    /**
     * @param Console\Output\OutputInterface $output
     *
     * @return array
     */
    protected function getAllRepositories(Console\Output\OutputInterface $output)
    {
        /** @var \PEAR\ComposerFix\RepoApi $repoApi */
        $repoApi = $this->container['github.api.repository']($this->client);

        $repositories = $repoApi->getAllRepositories($this->fix->getOrg());

        $output->writeln("<info>Repositories found: " . count($repositories) . "</info>");

        return $repositories;
    }
}
