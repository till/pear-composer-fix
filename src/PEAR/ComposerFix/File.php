<?php
namespace PEAR\ComposerFix;

class File
{
    private $file;
    private $missing;
    private $repo;
    private $name;

    /**
     * Internal table to cache parsed XML.
     * @var array
     */
    private $parsed = [];

    public function __construct(Repository $repo, $file, array $missing = [])
    {
        $this->file = $file;
        $this->missing = $missing;
        $this->name = $repo->getName();
        $this->repo = $repo;
    }

    public function fix()
    {
        $composer = [];
        if (file_exists($this->file)) {
            $composer = json_decode(file_get_contents($this->file), true);
        }

        foreach ($this->missing as $missing) {
            try {
                $composer[$missing] = $this->create($missing);
                $this->updateIgnore();
            } catch (\RuntimeException $e) {
                // skip for now
                //echo "Skipped: {$missing} for {$this->name}" . PHP_EOL;
            }
        }

        $this->addRequire($composer);

        if (!isset($composer['require-dev'])) {
            $composer['require-dev'] = [
                'phpunit/phpunit' => '*',
            ];
        }

        file_put_contents($this->file, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function addRequire(&$composer)
    {
        $xml = $this->parsePackageXml();
        if (!property_exists($xml, 'dependencies')) {
            return;
        }

        $require = [];

        $dependencies = $xml->dependencies;

        if (property_exists($dependencies, 'required')) {

            $required = $dependencies->required;

            // add require
            if (property_exists($required, 'php')) {
                unset($dependencies->required->php);
            }
            if (property_exists($required, 'pearinstaller')) {
                unset($required->pearinstaller);
                $require['pear/exception'] = '*';
            }
            foreach ($required->attributes() as $foo => $bar) {
                var_dump($foo, $bar); exit;
            }
        }
        if (property_exists($dependencies, 'optional')) {
            // add suggest

        }

        if (empty($require)) {
            return;
        }

        if (!isset($composer['require'])) {
            $composer['require'] = [];
        }
        $composer['require'] = array_merge($composer['require'], $require);
    }

    private function create($key)
    {
        switch ($key) {
        default:
            echo 'key: ' . $key; exit;
            //throw new \DomainException("Un-supported key: {$key}.");
            break;
        case 'authors':
            $xml = $this->parsePackageXml();
            $authors = $this->findAuthors($xml);
            if (!empty($authors)) {
                return $authors;
            }
            return [
                [
                    'name' => sprintf(
                        'The %s contributors',
                        $this->name
                    ),
                    'homepage' => sprintf(
                        'https://github.com/%s/%s/graphs/contributors',
                        $this->repo->getOrg(),
                        $this->name
                    ),
                ]
            ];
        case 'autoload':
            if ($this->isPSR0()) {
                $includePath = $this->create('include-path');
                return [
                    'psr-0' => [
                        $this->name => $includePath[0],
                    ],
                ];
            }
            return [
                'classmap' => ['./'],
            ];
        case 'description':
            $description = $this->repo->getDescription();
            if (!empty($description)) {
                return $description;
            }
            return 'More info available on: http://pear.php.net/package/' . $this->name;
        case 'include-path':
            if ($this->isPSR0()) {
                return ['./'];
            }
            throw new \RuntimeException("Could not find include-path for {$this->name}");
            break;
        case 'license':
            $xml = $this->parsePackageXml();
            return $this->findLicense($xml);
        case 'name':
            return sprintf('%s/%s',
                strtolower($this->repo->getOrg()),
                strtolower($this->name)
            );
        case 'type':
            return 'library';
        }
    }

    private function extract(&$author, $obj, $prop)
    {
        if (property_exists($obj, $prop)) {
            $author[$prop] = (string) $obj->$prop;
        }
    }

    private function findAuthors(\SimpleXMLElement $xml)
    {
        if (!property_exists($xml, 'maintainers')) {
            return [];
        }
        if (empty($xml->maintainers)) {
            return [];
        }
        $authors = [];
        foreach ($xml->maintainers as $maintainer) {
            $user = $maintainer->maintainer;

            $author = [];

            $this->extract($author, $user, 'email');
            $this->extract($author, $user, 'name');
            $this->extract($author, $user, 'role');

            if (property_exists($xml, 'channel')) {
                $author['homepage'] = sprintf(
                    'http://%s/user/%s',
                    (string) $xml->channel,
                    (string) $user->user
                );
            }

            array_push($authors, $author);
        }
        return $authors;
    }

    private function findLicense(\SimpleXMLElement $xml)
    {
        if (property_exists($xml, 'license')) {
            return (string) $xml->license;
        }
        if (property_exists($xml, 'release')) {
            if (property_exists($xml->release, 'license')) {
                return (string) $xml->release->license;
            }
        }
        if (property_exists($xml, 'changelog')) {
            if (property_exists($xml->changelog, 'release')) {
                if (property_exists($xml->changelog->release, 'license')) {
                    return (string) $xml->changelog->release->license;
                }
            }
        }

        // this is unfortunate - the code is probably PHP licensed?
        throw new \RuntimeException("Cannot find license of {$this->name}");
    }

    private function getGitRepo()
    {
        return dirname($this->file);
    }

    private function isPSR0()
    {
        $fileName = str_replace('_', '/', $this->name) . '.php';
        if (file_exists($this->getGitRepo() . '/' . $fileName)) {
            return true;
        }
        return false;
    }

    /**
     * @return \SimpleXMLElement
     * @throws \RuntimeException
     */
    private function parsePackageXml()
    {
        $repo = $this->getGitRepo();

        $cacheKey = md5($repo);
        if (isset($this->parsed[$cacheKey])) {
            return $this->parsed[$cacheKey];
        }

        $packageXml = $repo . '/package.xml';
        if (!file_exists($packageXml)) {
            $packageXml = $repo . '/package2.xml';
        }

        if (!file_exists($packageXml)) {
            throw new \RuntimeException("No package[2].xml found: {$this->name}.");
        }
        $xml = @simplexml_load_file($packageXml);
        if (false === $xml) {
            throw new \RuntimeException("Empty or mal-formed package.xml found: {$this->name}");
        }

        return $this->parsed[$cacheKey] = $xml;
    }

    private function updateIgnore()
    {
        $fileName = $this->getGitRepo() . '/.gitignore';
        $ignoreFile = '';
        if (file_exists($fileName)) {
            $ignoreFile .= file_get_contents($fileName);
            $ignoreFile .= "\n";
        }

        $filesToIgnore = ['composer.lock', 'composer.phar', 'vendor'];
        foreach ($filesToIgnore as $file) {
            $ignoreFile .= "{$file}\n";
        }
        file_put_contents($fileName, $ignoreFile);
    }
}
