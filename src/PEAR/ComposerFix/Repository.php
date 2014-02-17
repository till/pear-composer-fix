<?php
namespace PEAR\ComposerFix;

class Repository
{
    private $checks = [
        'authors',
        'autoload',
        'description',
        'include-path',
        'license',
        'name',
        'type',
    ];

    private $data;
    private $missing = [];
    private $store;

    public function __construct(array $repository, $store)
    {
        $this->data = $repository;
        $this->store = $store;
    }

    public function getBranch()
    {
        return $this->data['default_branch'];
    }

    public function getDescription()
    {
        return $this->data['description'];
    }

    public function getMissing()
    {
        return $this->missing;
    }

    public function getName()
    {
        return substr(basename($this->getUrl()), 0, -4); // cut off .git
    }

    public function getOrg()
    {
        return $this->data['owner']['login'];
    }

    public function getUrl()
    {
        return $this->data['ssh_url'];
    }

    public function isFork()
    {
        return $this->data['fork'];
    }

    public function isPrivate()
    {
        return $this->data['private'];
    }

    public function isProcessable()
    {
        if (null === $this->data['pushed_at']) {
            return false; // Y U CREATE EMPTY REPOSITORY
        }
        if ($this->isFork()) {
            return false;
        }
        if ($this->isPrivate()) {
            return false;
        }
        return true;
    }

    public function needsFixing()
    {
        $repo = sprintf('%s/%s', $this->store, $this->getName());

        $foundPackageXml = false;

        foreach (['package.xml', 'package2.xml'] as $xml) {
            if (file_exists($repo . '/' . $xml) && 0 < filesize($repo . '/' . $xml)) {
                $foundPackageXml = true;
                break;
            }
        }

        if (false === $foundPackageXml) {
            return false;
        }

        $composerJson = $repo . '/composer.json';
        if (!file_exists($composerJson)) {
            $this->missing = $this->checks;
            return true;
        }

        $json = json_decode(file_get_contents($composerJson), true);

        foreach ($this->checks as $check) {
            if (!isset($json[$check])) {
                $this->missing[] = $check;
            }
        }

        if (!empty($this->missing)) {
            return true;
        }

        return false;
    }
}
