#!/usr/bin/env php
<?php
require 'vendor/autoload.php';

use Github\Client;
use PEAR\ComposerFix;

$fix = new ComposerFix(
    require './config.php',
    __DIR__ . '/logs/errors.log'
);

$client = new Client();
$client->authenticate($fix->getToken(), null, Client::AUTH_URL_TOKEN);

$repoApi = new ComposerFix\RepoApi($client);

$repositories = $repoApi->getAllRepositories($fix->getOrg());
foreach ($repositories as $repositoryData) {

    $repo = new ComposerFix\Repository($repositoryData, $fix->getStore());

    if (!$repo->isProcessable()) {
        continue;
    }

    $fix
        ->setRepository($repo)
        ->cloneOrUpdate();

    if (!$repo->needsFixing()) {
        continue;
    }

    echo "TODO: {$repo->getName()}" . PHP_EOL;

    $jsonFile = $fix->getTarget($repo->getName()) . '/composer.json';

    $file = new ComposerFix\File(
        $repo,
        $jsonFile,
        $repo->getMissing()
    );
    $file->fix();

    $json = new Composer\JSON\JsonFile($jsonFile);
    $json->validateSchema();
}
