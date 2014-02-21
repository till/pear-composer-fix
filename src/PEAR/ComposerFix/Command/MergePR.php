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

        $unmaintained = $this->getUnmaintainedPackages();

        $output->writeln("<info>Found " . count($unmaintained) . " unmaintained repositories.");

        $organization = $this->container['config']['org'];

        $commits = new ComposerFix\CommitApi($this->client);

        /** @var ComposerFix\PullRequestApi $pullRequest */
        $pullRequest = new ComposerFix\PullRequestApi($this->client);

        /** @var Api\Issue\Comments $comments */
        $issue = new Api\Issue\Comments($this->client);

        $repositories = $this->getAllRepositories($output);

        foreach ($repositories as $repositoryData) {

            $repo = new ComposerFix\Repository(
                $repositoryData,
                $this->fix->getStore()
            );

            $name = $repo->getName();

            $commit = $commits->getLastCommit($organization, $name, $repo->getBranch());

            $dateCommit = new \DateTime($commit['committer']['date']);
            $dateNow = new \DateTime();

            $dateDiff = $dateCommit->diff($dateNow);
            if ($dateDiff->days <= 100) {
                continue;
            }

            $unmaintained[] = $repo->getName();

            if (empty($unmaintained)) {
                continue;
            }
            if (!in_array($name, $unmaintained)) {
                continue;
            }

            $openPRs = $pullRequest->getOpenFiltered(
                $organization,
                $name,
                ['key' => 'title', 'value' => 'Updated/New Composer support for']
            );

            foreach ($openPRs as $openPR) {

                $output->writeln("Repository seems unmaintained for {$dateDiff->days} days: {$name}");
                $output->writeln("Merging PR.");

                $prId = $openPR['number']; // Eh!?
                $issueId = $openPR['number'];

                $comment = ['body' => $this->getComment('auto merged')];

                try {
                    $issue->create(
                        $organization,
                        $name,
                        $issueId,
                        $comment
                    );

                    $pullRequest->merge($organization, $name, $prId);
                } catch (\Exception $e) {
                    var_dump($name, $openPR['title']);
                    echo $e->getMessage(); exit;
                }
            }

        }
    }

    /**
     * Trolling.
     */
    private function getComment($text = null)
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
        if (null !== $text) {
            $comment .= "\n\n" . $text;
        }

        return $comment;
    }

    private function getUnmaintainedPackages()
    {
        return [];

        $csv = $this->container['config']['root'] . '/var/db/unmaintained-packages.csv';

        $unmaintained = explode("\n", file_get_contents($csv));
        if (empty($unmaintained)) {
            throw new \RuntimeException("'$csv' seems empty.");
        }

        return $unmaintained;
    }
}
