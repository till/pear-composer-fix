<?php
namespace PEAR;

use Symfony\Component\Process\Process;

class ComposerFix
{
    private $config;

    /**
     * @var ComposerFix\Repository
     */
    private $currentRepository;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function cloneOrUpdate()
    {
        $target = $this->getTarget($this->currentRepository->getName());

        if (is_dir($target)) {

            $cwd = $target;

            if ($this->isEmpty($cwd)) {
                return;
            }

            $branch = $this->currentRepository->getBranch();

            $commands = [];

            if ($this->hasBranch()) {
                $branch = $this->config['branch'];
                $commands[] = 'git checkout ' . $branch;
            }

            $commands = array_merge(
                $commands,
                [
                    'git clean -f',
                    'git reset --hard origin/' . $branch,
                    'git pull origin ' . $branch,
                ]
            );
            $command = implode(' && ', $commands);

        } else {
            $command = 'git clone ' . $this->currentRepository->getUrl();
            $cwd = $this->getStore();
        }

        $process = $this->execute($command, $cwd);
        if (!$process->isSuccessful()) {
            echo "Command failed for {$this->currentRepository->getName()}: {$command}" . PHP_EOL;
            echo $process->getOutput() . PHP_EOL;
            echo $process->getErrorOutput() . PHP_EOL;
            exit($process->getExitCode());
        }
    }

    public function commit()
    {
        $branch = $this->config['branch'];

        $commands = [];
        if ($this->hasBranch()) {
            $commands[] = "git checkout {$branch}";
        } else {
            $commands[] = "git checkout -b {$branch}";
        }

        $commands = array_merge(
            $commands,
            [
                "git add -A",
                'git commit -a -m "Enhancement: composer setup"',
                "git push origin {$branch}"
            ]
        );

        $cwd = $this->getTarget($this->currentRepository->getName());

        foreach ($commands as $command) {
            $process = $this->execute($command, $cwd);
            if ($process->isSuccessful()) {
                continue;
            }

            echo sprintf("Command failed: %s (%s)", $command, $this->currentRepository->getName());
            echo $process->getErrorOutput();
            exit($process->getExitCode());
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

    /**
     * Find out if a repository contains composer.json and so on, or if it needs to be updated.
     *
     * @return bool
     * @throws \RuntimeException
     */
    public function needsUpdate()
    {
        $cwd = $this->getTarget($this->currentRepository->getName());

        if ($this->isEmpty($cwd)) {
            return false;
        }

        // check if we already ran updates
        //if ($this->hasBranch()) {
        //    return false;
        //}

        // check for un-committed changes
        $command = "git diff --exit-code";
        $process = $this->execute($command, $cwd);
        if (!$process->isSuccessful()) {
            return true;
        }

        // check for un-staged files
        $command = "git ls-files . --exclude-standard --others";
        $process = $this->execute($command, $cwd);

        if (!$process->isSuccessful()) {
            $msg  = "Command for {$this->currentRepository->getName()} failed: {$command}" . PHP_EOL;
            $msg .= "Error: {$process->getErrorOutput()}" . PHP_EOL;
            throw new \RuntimeException($msg);
        }

        $output = $process->getOutput();
        if (!empty($output)) {
            return true;
        }

        return false;
    }

    /**
     * This needs to be used to set the Github repository we're dealing with.
     *
     * @param ComposerFix\Repository $repository
     *
     * @return $this
     */
    public function setRepository(ComposerFix\Repository $repository)
    {
        $this->currentRepository = $repository;
        return $this;
    }

    /**
     * @param string $command
     * @param string $cwd
     *
     * @return Process
     */
    private function execute($command, $cwd)
    {
        $process = new Process($command, $cwd);
        $process->run();
        return $process;
    }

    /**
     * Check if the branch is available locally or remote.
     */
    private function hasBranch()
    {
        $branch = $this->config['branch'];
        $cwd = $this->getTarget($this->currentRepository->getName());

        $process = $this->execute("git show-branch {$branch}", $cwd);
        if ($process->isSuccessful()) {
            return true;
        }

        // fetch origin in case no local branch was found
        $process = $this->execute("git show-branch -r --list", $cwd);
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $branches = $process->getOutput();
        if (strpos($branches, $branch)) {
            return true;
        }

        return false;
    }
    /**
     * Neat situation: empty repository which the Github API says has been pushed to.
     *
     * `git log` returns an error when executed on an empty repository.
     *
     * @param string $cwd
     *
     * @return bool
     */
    private function isEmpty($cwd)
    {
        $process = $this->execute("git log", $cwd);
        if (!$process->isSuccessful()) {
            return true;
        }
        return false;
    }
}
