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

    public function fix($missing = null)
    {
        if (null !== $missing) {
            $this->missing[] = $missing;
        }

        $composer = [];
        if (file_exists($this->file)) {
            $composer = json_decode(file_get_contents($this->file), true);
        }

        foreach ($this->missing as $missing) {
            try {
                $composer[$missing] = $this->create($missing);
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

        $this->updateIgnore();
    }

    private function addRequire(&$composer)
    {
        $xml = $this->parsePackageXml();
        if (!property_exists($xml, 'dependencies')) {
            return;
        }

        $channel = (string) $xml->channel;

        switch ($channel) {
        case 'pear.php.net':
            $vendorPrefix = 'pear';
            break;
        default:
            throw new \DomainException("Unknown channel: {$channel}");
        }

        $composerRequire = [];

        $dependencies = $xml->dependencies;

        if (property_exists($dependencies, 'required')) {

            $xmlRequired = $dependencies->required;

            // add require
            if (property_exists($xmlRequired, 'php')) {
                unset($xmlRequired->php);
            }
            if (property_exists($xmlRequired, 'pearinstaller')) {
                unset($xmlRequired->pearinstaller);
            }

            if (property_exists($xmlRequired, 'package')) {
                $composerRequire = array_merge(
                    $composerRequire,
                    $this->createDependencies($xmlRequired, $channel, $vendorPrefix, 'require')
                );
            }
        }

        if (property_exists($dependencies, 'optional')) {

            $optional = $dependencies->optional;

            $suggest = $this->createDependencies($optional, $channel, $vendorPrefix, 'suggest');
            if (!empty($suggest)) {
                $composer['suggest'] = $suggest;
            }
        }

        if (empty($composerRequire)) {
            return;
        }

        if (!isset($composer['require'])) {
            $composer['require'] = [];
        }
        $composer['require'] = array_merge($composer['require'], $composerRequire);
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
                $dir = explode('_', $this->name);
                return [
                    'psr-0' => [
                        $dir[0] => $includePath[0],
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
        case 'support':
            $xml = $this->parsePackageXml();
            $packageName = (string) $xml->name;
            $channel = (string) $xml->channel;

            return [
                "issues" => "http://{$channel}/bugs/search.php?cmd=display&package_name[]={$packageName}",
                "source" => "https://github.com/{$this->repo->getOrg()}/{$packageName}",
            ];
        case 'type':
            return 'library';
        }
    }

    /**
     * Create the JSON structure from the XML.
     *
     * Should support required and optional pear packages and PHP extensions.
     *
     * @param \SimpleXMLElement $data
     * @param string            $defaultChannel
     * @param string            $vendorPrefix
     * @param string            $type
     *
     * @return array
     * @throws \DomainException
     */
    private function createDependencies(\SimpleXMLElement $data, $defaultChannel, $vendorPrefix, $type)
    {
        $tree = [];

        $key = 'package';
        if (!property_exists($data, $key)) {
            $key = 'extension'; // PHP extension :]
            if (!property_exists($data, $key)) {
                throw new \DomainException(
                    sprintf(
                        "Missing 'package' or 'extension in %s/%s'.",
                        $this->repo->getOrg(),
                        $this->repo->getName()
                    )
                );
            }
        }

        $packages = $data->$key;
        if (!is_array($data->$key)) {
            $packages = [];
            $packages[] = $data->$key;
        }

        foreach ($packages as $dep) {

            $name = (string) $dep->name;
            $channel = (string) $dep->channel;

            if ('extension' == $key) {
                $version = '*';
                if ('suggest' === $type) {
                    $version = 'May require the ' . $name . ' PHP extension';
                }
                $tree['ext-' . strtolower($name)] = $version;
                continue;
            }

            if ($channel !== $defaultChannel) {
                continue;
            }

            $packageName = $this->createPackageName($vendorPrefix, $name);

            if ('suggest' === $type) {
                $tree[$packageName] = "Install optionally via your project's composer.json";
            }

            if ('require' === $type) {
                $tree[$packageName] = '*';
            }
        }

        return $tree;
    }

    /**
     * Composer style package name: vendor/library
     *
     * @param string $vendor
     * @param string $name
     *
     * @return string
     */
    private function createPackageName($vendor, $name)
    {
        return sprintf('%s/%s', $vendor, strtolower($name));
    }

    private function extract(&$author, $obj, $prop)
    {
        if (property_exists($obj, $prop)) {
            if (!property_exists($obj, $prop)) {
                return;
            }
            $author[$prop] = (string) $obj->$prop;
        }
    }

    /**
     * Parses package.xml v1 and v2 for maintainer, helper, etc. data.
     *
     * @param \SimpleXMLElement $xml
     *
     * @return array
     */
    private function findAuthors(\SimpleXMLElement $xml)
    {
        $authors = [];

        if (property_exists($xml, 'maintainers')) {
            if (empty($xml->maintainers)) {
                return $authors;
            }
            $data = $xml->maintainers;

            $this->parseAuthorData($data, $authors);
            return $authors;
        }

        foreach (['lead', 'developer', 'helper'] as $role) {
            if (property_exists($xml, $role)) {
                if (empty($xml->$role)) {
                    continue;
                }
                $data = $xml->$role;
                $this->parseAuthorData($data, $authors, $role);
            }
        }

        return $authors;
    }

    /**
     * Find license data from package.xml files.
     *
     * @param \SimpleXMLElement $xml
     *
     * @return string
     * @throws \RuntimeException
     * @todo Maybe normalize?!
     */
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

    /**
     * Short-hand to find the git repository.
     *
     * @return string
     */
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
     * Parses the XML for author data, and adds it to authors.
     *
     * @param mixed  $data
     * @param array  $authors
     * @param string $defaultRole
     *
     * @return void
     */
    private function parseAuthorData($data, &$authors, $defaultRole = 'Lead')
    {
        foreach ($data as $maintainer) {

            if (property_exists($maintainer, 'maintainer')) {
                $user = $maintainer->maintainer;
            } else {
                $user = $maintainer;
            }

            $this->extract($author, $user, 'email');
            $this->extract($author, $user, 'name');
            $this->extract($author, $user, 'role');

            if (!isset($author['role'])) {
                $author['role'] = $defaultRole;
            }

            $author['role'] = ucfirst($author['role']);

            array_push($authors, $author);
        }
    }

    /**
     * Parse and cache the XML.
     *
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

            if (stripos($ignoreFile, 'composer')) {
                return;
            }
        }

        $ignoreFile .= "# composer related\n";

        $filesToIgnore = ['composer.lock', 'composer.phar', 'vendor'];
        foreach ($filesToIgnore as $file) {

            // double-check that we don't add duplicates
            if (false !== stripos($ignoreFile, $file)) {
                continue;
            }

            $ignoreFile .= "{$file}\n";
        }
        file_put_contents($fileName, $ignoreFile);
    }
}
