<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

/**
 * The SshCommand is used to establish an SSH tunnel to the replicas for local environments.
 * To use, in your application run `php bin/console toolforge:ssh`
 */
class SshCommand extends Command
{

    /** @var string */
    protected static $defaultName = 'toolforge:ssh';

    /** @var ReplicasClient */
    protected $client;

    /** @var string */
    public const LOGIN_URL = 'login.toolforge.org';

    /** @var string Follows the slice and service, i.e. 's1.web' */
    public const HOST_SUFFIX = '.db.svc.eqiad.wmflabs';

    /**
     * SshCommand constructor.
     * @param ReplicasClient $client
     */
    public function __construct(ReplicasClient $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    /**
     * Configuration for the SshCommand.
     */
    protected function configure(): void
    {
        $whoAmI = new Process(['whoami']);
        $whoAmI->run();
        $username = trim($whoAmI->getOutput());

        $this->setDescription('Create an SSH tunnel to the Toolforge replicas.');
        $this->setHelp('Note you must already have added your SSH key to the ssh-agent before running this.');
        $this->addArgument(
            'username',
            InputArgument::OPTIONAL,
            'Your Toolforge UNIX shell username, if different than your local username.',
            $username
        );
        $this->addOption(
            'service',
            null,
            InputOption::VALUE_OPTIONAL,
            "Service to use. Either 'web' (default) or 'analytics'",
            'web'
        );
        $this->addOption(
            'bind-address',
            'b',
            InputOption::VALUE_REQUIRED,
            "Sets the binding address of the SSH tunnel. For Docker installations you may need to set this to 0.0.0.0"
        );
        $this->addOption(
            'toolsdb',
            null,
            InputOption::VALUE_NONE,
            'Sets up an SSH tunnel to tools.db.svc.wikimedia.cloud'
        );
    }

    /**
     * Create the SSH tunnel and wait until the process is manually killed.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @returns int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $service = $input->getOption('service');
        $bindAddress = $input->getOption('bind-address');
        $toolsDb = $input->getOption('toolsdb');
        $host = "$service".self::HOST_SUFFIX;
        $login = $username.'@'.self::LOGIN_URL;

        $toolsDbStr = $toolsDb ? ' and tools'.self::HOST_SUFFIX : '';
        $output->writeln("Connecting to *.$host$toolsDbStr via $login... use ^C to cancel or terminate connection.");

        $slices = array_unique(array_values($this->client->getDbList()));
        $processArgs = ['ssh', '-N'];
        foreach ($slices as $slice) {
            $processArgs[] = '-L';
            $arg = $this->client->getPortForSlice($slice).":$slice.$host:3306";
            if ($bindAddress) {
                $arg = $bindAddress.':'.$arg;
            }
            $processArgs[] = $arg;
        }
        if ($toolsDb) {
            $processArgs[] = '-L';
            $port = $this->client->getPortForSlice('toolsdb');
            $processArgs[] = $port.':tools'.self::HOST_SUFFIX.':3306';
        }
        $processArgs[] = $login;

        $process = Process::fromShellCommandline(implode(' ', $processArgs));
        $process->setTimeout(null); // Never time out
        $process->start();

        $process->wait(function ($type, $buffer) use ($output): void {
            if (Process::ERR === $type) {
                $output->writeln('<error>'.trim($buffer).'</error>');
            } else {
                $output->writeln($buffer);
            }
        });

        return Command::SUCCESS;
    }
}
