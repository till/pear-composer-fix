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

$repositories = $client->api('user')->repositories($fix->getOrg());
foreach ($repositories as $repository) {

    $repo = new ComposerFix\Repository($repository, $fix->getStore());

    if (!$repo->isProcessable()) {
        continue;
    }

    $fix->cloneOrUpdate($repo);

    if (!$repo->needsFixing()) {
        continue;
    }

    echo "TODO: {$repo->getName()}" . PHP_EOL;

    $file = new ComposerFix\File(
        $repo,
        $fix->getTarget($repo->getName()) . '/composer.json',
        $repo->getMissing()
    );
    $file->fix();
}
