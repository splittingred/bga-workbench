<?php

namespace BGAWorkbench\Project;

use BGAWorkbench\Utils;
use BGAWorkbench\Utils\FileUtils;
use PhpOption\Option;

class WorkbenchProjectConfig
{
    /**
     * @var \SplFileInfo
     */
    private $directory;

    /**
     * @var boolean
     */
    private $useComposer;

    /**
     * @var string[]
     */
    private $extraSrcPaths;

    /**
     * @var string
     */
    private $testDbName;

    /**
     * @var string
     */
    private $testDbNamePrefix;

    /**
     * @var string
     */
    private $testDbHost;

    /**
     * @var int
     */
    private $testDbPort;

    /**
     * @var string
     */
    private $testDbUsername;

    /**
     * @var string
     */
    private $testDbPassword;

    /**
     * @var bool
     */
    private $testDbExternallyManaged;

    /**
     * @var string
     */
    private $linterPhpBin;

    /**
     * @var Option
     */
    private $sftpConfig;

    /**
     * @param \SplFileInfo $directory
     * @param bool $useComposer
     * @param string[] $extraSrcPaths
     * @param string $testDbName
     * @param string $testDbNamePrefix
     * @param string $testDbHost
     * @param int $testDbPort
     * @param string $testDbUsername
     * @param string $testDbPassword
     * @param bool $testDbExternallyManaged
     * @param string $linterPhpBin
     * @param Option $sftpConfig
     */
    public function __construct(
        \SplFileInfo $directory,
        bool $useComposer,
        array $extraSrcPaths,
        string $testDbName,
        string $testDbNamePrefix,
        string $testDbHost,
        int $testDbPort,
        string $testDbUsername,
        string $testDbPassword,
        bool $testDbExternallyManaged,
        string $linterPhpBin,
        Option $sftpConfig
    ) {

        $this->directory = $directory;
        $this->useComposer = $useComposer;
        $this->extraSrcPaths = $extraSrcPaths;
        $this->testDbName = $testDbName;
        $this->testDbNamePrefix = $testDbNamePrefix;
        $this->testDbHost = $testDbHost;
        $this->testDbPort = $testDbPort;
        $this->testDbUsername = $testDbUsername;
        $this->testDbPassword = $testDbPassword;
        $this->testDbExternallyManaged = $testDbExternallyManaged;
        $this->linterPhpBin = $linterPhpBin;
        $this->sftpConfig = $sftpConfig;
    }

    /**
     * @return string
     */
    public function getTestDbName() : string
    {
        return !empty($this->testDbName) ? $this->testDbName : $this->getTestDbNamePrefix() . substr(md5(time()), 0, 10);
    }

    /**
     * @return string
     */
    public function getTestDbHost(): string
    {
        return $this->testDbHost;
    }

    /**
     * @return int
     */
    public function getTestDbPort(): int
    {
        return $this->testDbPort;
    }

    /**
     * @return string
     */
    public function getTestDbUsername() : string
    {
        return $this->testDbUsername;
    }

    /**
     * @return string
     */
    public function getTestDbPassword() : string
    {
        return $this->testDbPassword;
    }

    /**
     * @return bool
     */
    public function externallyManaged(): bool
    {
        return $this->testDbExternallyManaged;
    }

    /**
     * @return string
     */
    public function getLinterPhpBin() : string
    {
        return $this->linterPhpBin;
    }

    /**
     * @return Option
     */
    public function getDeployConfig() : Option
    {
        return $this->sftpConfig;
    }

    /**
     * @return bool
     */
    public function getUseComposer() : bool
    {
        return $this->useComposer;
    }

    /**
     * @return string[]
     */
    public function getExtraSrcPaths(): array
    {
        return $this->extraSrcPaths;
    }

    /**
     * @return Project
     */
    public function loadProject() : Project
    {
        $versionFile = FileUtils::joinPathToFileInfo($this->directory, 'version.php');

        $GAME_VERSION_PREFIX = 'game_version_';
        $variableName = Utils::getVariableNameFromFile(
            $versionFile,
            function ($name) use ($GAME_VERSION_PREFIX) {
                return strpos($name, $GAME_VERSION_PREFIX) === 0;
            }
        )->getOrThrow(
            new \InvalidArgumentException(
                "File {$versionFile->getPathname()} missing version variable {$GAME_VERSION_PREFIX}_%%project_name%%"
            )
        );
        $projectName = substr($variableName, strlen($GAME_VERSION_PREFIX));

        if ($this->useComposer) {
            return new ComposerProject($this->directory, $projectName, $this->extraSrcPaths);
        }
        return new Project($this->directory, $projectName);
    }

    /**
     * @return string
     */
    private function getTestDbNamePrefix() : string
    {
        return $this->testDbNamePrefix;
    }
}
