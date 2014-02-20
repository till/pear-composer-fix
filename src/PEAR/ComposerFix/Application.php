<?php
namespace PEAR\ComposerFix;

use \Symfony\Component\Console;

/**
 * Class Application
 *
 * Quick and dirty DI.
 *
 * @package PEAR\ComposerFix
 */
class Application extends Console\Application
{
    /**
     * @var array
     */
    protected $container;

    public function getContainer()
    {
        return $this->container;
    }

    public function setContainer(array $container)
    {
        $this->container = $container;
    }
}
