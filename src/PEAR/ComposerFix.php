<?php
namespace PEAR;

class ComposerFix
{
    private $config;

    private $currentRepository;

    private $descriptors;

    public function __construct(array $config, $errorLog)
    {
        $this->config = $config;
        $this->descriptors = [
            ['file', '/dev/null', 'r'],
            ['file', '/dev/null', 'w'],
            ['file', $errorLog, 'w'],
        ];
    }

    public function cloneOrUpdate()
    {
        $target = $this->getTarget($this->currentRepository->getName());

        if (is_dir($target)) {
            $commands = [
                'git clean -f',
                'git reset --hard origin/' . $this->currentRepository->getBranch(),
                'git pull origin ' . $this->currentRepository->getBranch(),
            ];
            $command = implode(' && ', $commands);
            $cwd = $target;
        } else {
            $command = 'git clone ' . $this->currentRepository->getUrl();
            $cwd = $this->getStore();
        }

        $process = proc_open($command, $this->descriptors, $pipes, $cwd);
        if (!is_resource($process)) {
            echo PHP_EOL . "Failed to start: {$command}";
            exit(2);
        }

        $status = proc_close($process);
        if ($status !== 0) {
            echo "Command failed for {$this->currentRepository->getName()}: {$command}" . PHP_EOL;
            exit(3);
        }
    }

    public function getOrg()
    {
        return $this->config['org'];
    }

    public function getStore()
    {
        if (!is_dir($this->config['store'])) {
            mkdir($this->config['store'], 0755);
        }

        return $this->config['store'];
    }

    public function getTarget($repo)
    {
        return $this->getStore() . '/' . $repo;
    }

    public function getToken()
    {
        return $this->config['token'];
    }
}
