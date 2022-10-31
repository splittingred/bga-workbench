<?php

namespace BGAWorkbench\Tests\Console;

use BGAWorkbench\Console\Application;
use BGAWorkbench\External\WorkbenchProjectConfigSerialiser;
use BGAWorkbench\Project\DeployConfig;
use BGAWorkbench\Project\WorkbenchProjectConfig;
use BGAWorkbench\TestUtils\WorkingDirectory;
use PhpOption\Some;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitCommandTest extends TestCase
{
    /**
     * @var WorkingDirectory
     */
    private $workingDir;

    /**
     * @var CommandTester
     */
    private $tester;

    protected function setUp()
    {
        $this->workingDir = WorkingDirectory::createTemp();
        chdir($this->workingDir->getPathname());

        $application = new Application();
        $application->setAutoExit(false);
        $command = $application->find('init');

        $this->tester = new CommandTester($command);
    }

    public function testSuccess()
    {
        $this->tester->setInputs([
            'y',

            'dbname',
            'dbuser',
            'dbpass',

            'shost',
            'suser',
            'spass'
        ]);
        $this->tester->execute([]);

        $newWbConfig = new WorkbenchProjectConfig(
            $this->workingDir->getFileInfo(),
            true,
            [],
            '',
            'dbname',
            '127.0.0.1',
            3306,
            'dbuser',
            'dbpass',
            false,
            'php',
            new Some(
                new DeployConfig('shost', 'suser', 'spass')
            )
        );
        $fileWbConfig = WorkbenchProjectConfigSerialiser::readFromDirectory($this->workingDir->getFileInfo());
        assertThat($this->tester->getStatusCode(), equalTo(0));
        assertThat(
            $fileWbConfig,
            equalTo($newWbConfig)
        );
    }

    public function testAlreadyExists()
    {
        touch(WorkbenchProjectConfigSerialiser::getConfigFileInfo($this->workingDir->getFileInfo()));

        $this->tester->execute([]);

        assertThat($this->tester->getStatusCode(), not(equalTo(0)));
    }
}
