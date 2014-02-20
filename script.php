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

$errors = [];

$container = [
    'config' => $config,
    'fix' => function (array $config) {
        return new ComposerFix($config);
    },
    'github.client' => function (ComposerFix $fix) {
        $client = new Client();
        $client->authenticate(
            $fix->getToken(),
            null,
            Client::AUTH_URL_TOKEN
        );
        return $client;
    },
    'github.api.repository' => function ($client) {
        return $repoApi = new ComposerFix\RepoApi($client);
    },
];

$console = new ComposerFix\Application('pear-composer-fix', `git rev-parse --verify HEAD`);
$console->add(new ComposerFix\Command\AddComposer());
$console->add(new ComposerFix\Command\MergePR());
$console->setContainer($container);
$console->run();
