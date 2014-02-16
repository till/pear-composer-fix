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

            $cwd = $target;

            if ($this->isEmpty($cwd)) {
                return;
            }

            $commands = [
                'git clean -f',
                'git reset --hard origin/' . $this->currentRepository->getBranch(),
                'git pull origin ' . $this->currentRepository->getBranch(),
            ];
            $command = implode(' && ', $commands);

        } else {
            $command = 'git clone ' . $this->currentRepository->getUrl();
            $cwd = $this->getStore();
        }

        $status = $this->execute($command, $cwd);
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

    public function setRepository(ComposerFix\Repository $repository)
    {
        $this->currentRepository = $repository;
        return $this;
    }

    private function execute($command, $cwd, &$output = false)
    {
        $descriptors = $this->descriptors;
        if (false !== $output) {
            $descriptors[1] = ['pipe', 'w'];
        }

        $process = proc_open($command, $descriptors, $pipes, $cwd);
        if (!is_resource($process)) {
            echo PHP_EOL . "Failed to start: {$command}";
            exit(2);
        }

        if (false !== $output) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }

        $status = proc_close($process);
        return $status;
    }

    /**
     * Neat situation: empty repository which the Github API says has been pushed to.
     *
     * @param $cwd
     *
     * @return bool
     */
    private function isEmpty($cwd)
    {
        $status = $this->execute("git log", $cwd);
        if (0 !== $status) {
            return true;
        }
        return false;
    }
}
