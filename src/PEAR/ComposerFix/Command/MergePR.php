<?php
namespace PEAR\ComposerFix\Command;

use PEAR\ComposerFix;
use Symfony\Component\Console;
use Github\Api;

class MergePR extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('pear:composer:merge')
            ->setDescription('Merge PRs of unmaintained repositories')
        ;
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $this->setUp();
        $root = $this->container['config']['root'];
        $organization = $this->container['config']['org'];

        $csv = $root . '/var/db/unmaintained-packages.csv';
        $unmaintained = explode("\n", file_get_contents($csv));

        if (empty($unmaintained)) {
            throw new \RuntimeException("'$csv' seems empty.");
        }

        $output->writeln("<info>Found " . count($unmaintained) . " unmaintained repositories.");

        $repositories = $this->getAllRepositories($output);
        if (empty($repositories)) {
            $output->writeln("<error>Couldn't find any repositories on github.com/{$organization}.");
            return;
        }

        foreach ($repositories as $repositoryData) {
            $repo = new ComposerFix\Repository(
                $repositoryData,
                $this->fix->getStore()
            );

            if (!in_array($repo->getName(), $unmaintained)) {
                continue;
            }

            /** @var \Github\Client $client */
            $client = $this->client;

            /** @var Api\PullRequest $pullRequest */
            $pullRequest = $client->api('pr');

            /** @var Api\Issue\Comments $comments */
            $issue = new Api\Issue\Comments($client);

            $openPRs = $pullRequest->all($organization, $repo->getName(), 'open');
            foreach ($openPRs as $openPR) {

                $title = $openPR['title'];
                if (false === strpos($title, 'Updated/New Composer support for')) {
                    continue;
                }

                $prId = $openPR['number']; // Eh!?
                $issueId = $openPR['number'];

                $comment = ['body' => $this->getComment()];
                $issue->create($organization, $repo->getName(), $issueId, $comment);

                $pullRequest->merge($organization, $repo->getName(), $prId);
            }

        }
    }

    /**
     * Trolling.
     */
    private function getComment()
    {
        $pictures = [
            'http://s2.quickmeme.com/img/10/10f98000bac13ec26f5aebf9f2b2bf1e41a3104a9893f73dde9dd76cbce9daff.jpg',
            'http://cdn.memegenerator.net/instances/250x250/45159143.jpg',
            'http://www.troll.me/images/xzibit-yo-dawg/yo-dawg-we-heard-you-like-git-merge-so-we-merged-your-merge-commit-so-you-can-merge-while-you-merge-thumb.jpg',
            'https://github-camo.global.ssl.fastly.net/0bcc4576ad9210ec5a100baa95412402be325c71/687474703a2f2f692e696d6775722e636f6d2f53455169542e6a7067',
            'https://github-camo.global.ssl.fastly.net/d01032920893c75a2da6cfdfe456c0169e3d72f8/687474703a2f2f692e696d6775722e636f6d2f487265634f2e6a7067',
        ];

        $pic = $pictures[mt_rand(0, (count($pictures) - 1))];

        $comment = "![Merged!]({$pic})";
        return $comment;
    }
}
