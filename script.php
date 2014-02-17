#!/usr/bin/env php
<?php
require 'vendor/autoload.php';

use Github\Client;
use PEAR\ComposerFix;

if (!file_exists(__DIR__ . '/config.php')) {
    echo "Please setup `config.php`" . PHP_EOL;
    exit(1);
}

$config = require __DIR__ . '/config.php';
$fix = new ComposerFix($config);

$errors = [];

$client = new Client();
$client->authenticate($fix->getToken(), null, Client::AUTH_URL_TOKEN);

$repoApi = new ComposerFix\RepoApi($client);

$repositories = $repoApi->getAllRepositories($fix->getOrg());

echo "Found: " . count($repositories) . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;

$count = 0;
foreach ($repositories as $repositoryData) {

    ++$count;

    if (($count%80) == 0) {
        echo PHP_EOL;
    }

    $repo = new ComposerFix\Repository(
        $repositoryData,
        $fix->getStore()
    );

    if (!$repo->isProcessable()) {
        echo "s";
        continue;
    }

    $fix
        ->setRepository($repo)
        ->cloneOrUpdate();

    if (!$repo->needsFixing()) {
        echo "d";
        continue;
    }

    $jsonFile = $fix->getTarget($repo->getName()) . '/composer.json';

    try {
        $file = new ComposerFix\File(
            $repo,
            $jsonFile,
            $repo->getMissing()
        );
        $file->fix();
    } catch (\DomainException $e) {
        echo "e";

        $errors[] = $e->getMessage();
        continue;
    }

    try {
        $json = new Composer\JSON\JsonFile($jsonFile);
        $json->validateSchema();
    } catch (\Exception $e) {
        $errors[] = "The composer.json for {$repo->getName()} is (still) invalid: ";
        $errors[] = (string) $e;

        echo "e";

        continue;
    }

    if (!$fix->needsUpdate()) {
        echo "d";
        continue;
    }

    $fix->commit();

    /** @var \Github\Api\PullRequest $pullRequest */
    $pullRequest = $client->api('pr');
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
    echo $error . PHP_EOL;
}

echo PHP_EOL;
echo "Done: " . date('Y-m-d H:i:s') . PHP_EOL;

if (!empty($errors)) {
    exit(4);
}
exit(0);
