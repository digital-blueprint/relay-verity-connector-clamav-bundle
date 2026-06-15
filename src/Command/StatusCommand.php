<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Command;

use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;
use Dbp\Relay\VerityConnectorClamavBundle\Service\ConfigurationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dbp:relay:verity-connector-clamav:status',
    description: 'Display ClamAV daemon status',
)]
class StatusCommand extends Command
{
    public function __construct(private readonly ConfigurationService $configurationService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $bundleConfig = $this->configurationService->getConfig();
        $parts = parse_url($bundleConfig['url']);
        if ($parts === false || !isset($parts['host'])) {
            $io->error('Invalid ClamAV URL in configuration: '.$bundleConfig['url']);

            return Command::FAILURE;
        }
        $host = $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : 3310;

        $io->title('ClamAV Status');

        $io->definitionList(
            ['Host' => $host],
            ['Port' => $port],
        );

        try {
            $client = ClamAvClient::createForHost($host, $port);

            $client->ping();
            $io->success('Daemon is reachable (PING OK)');

            $version = $client->version();
            $io->section('Version');
            $io->text($version);

            $stats = $client->stats();
            $io->section('Stats');
            $io->text(explode("\n", $stats));
        } catch (\Exception $e) {
            $io->error('Failed to connect to ClamAV daemon: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
