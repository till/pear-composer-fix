<?php
namespace PEAR\ComposerFix\Command;

use PEAR\ComposerFix;
use Composer\JSON;

use Symfony\Component\Console;

class AddComposer extends BaseCommand
{
    protected $client;

    /**
     * @var array
     */
    protected $repositories;

    protected function configure()
    {
        $this
            ->setName('pear:composer:add')
            ->setDescription('Add composer.json file to repositories')
        ;
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $this->setUp();
        $this->client = $this->container['github.client'];

        $this->repositories = $this->getAllRepositories($output);

        $errors = [];
        $count = 0;

        $exclude = [];
        $include = [];

        foreach ($this->repositories as $repositoryData) {

            ++$count;


            if (($count%80) == 0) {
                $output->writeln('');
            }

            $repo = new ComposerFix\Repository(
                $repositoryData,
                $this->fix->getStore()
            );

            if (in_array($repo->getName(), $exclude) && empty($include)) {
                $output->write('x');
                continue;
            }

            if (!in_array($repo->getName(), $include) && empty($exclude)) {
                $output->write('x');
                continue;
            }

            if (!$repo->isProcessable()) {
                $output->write("s");
                continue;
            }

            $this->fix
                ->setRepository($repo)
                ->cloneOrUpdate();

            if (!$repo->needsFixing()) {
                //echo "c";
                //continue;
            }

            $jsonFile = $this->fix->getTarget($repo->getName()) . '/composer.json';

            try {

                $file = new ComposerFix\File(
                    $repo,
                    $jsonFile,
                    $repo->getMissing()
                );
                $file->fix('autoload');

            } catch (\DomainException $e) {
                $output->write("e");
                $errors[] = $e->getMessage();
                continue;
            } catch (\RuntimeException $e) {
                $output->write("s");
                $errors[] = $e->getMessage();
                return;
            }

            try {
                $json = new JSON\JsonFile($jsonFile);
                $json->validateSchema();
            } catch (\Exception $e) {
                $errors[] = "The composer.json for {$repo->getName()} is (still) invalid: ";
                $errors[] = (string) $e;

                $output->write("e");
                continue;
            }

            if (!$this->fix->needsUpdate()) {
                $output->write("d");
                continue;
            }

            $this->fix->commit();

            continue;

            /** @var \Github\Api\PullRequest $pullRequest */
            $pullRequest = $this->client->api('pr');

            $pullRequest->create(
                $repo->getOrg(),
                $repo->getName(),
                [
                    'base' => $repo->getBranch(),
                    'body' => 'See diff for full changes',
                    'head' => $config['branch'],
                    'title' => 'Updated/New Composer support for ' . $repo->getName(),
                ]
            );

            echo ".";
        }

        foreach ($errors as $error) {
            $output->writeln("<error>$error</error>");
        }

        $output->writeln('');
        $output->writeln("<info>Done: " . date('Y-m-d H:i:s') . '</info>');

        if (!empty($errors)) {
            exit(4);
        }
        exit(0);
    }
}
