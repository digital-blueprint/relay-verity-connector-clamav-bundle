<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Command;

use Dbp\Relay\VerityConnectorClamavBundle\ClamAvClient\ClamAvClient;
use Dbp\Relay\VerityConnectorClamavBundle\Service\ConfigurationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dbp:relay:verity-connector-clamav:scan-file',
    description: 'Scan a local file for viruses using the ClamAV daemon',
)]
class ScanFileCommand extends Command
{
    public function __construct(private readonly ConfigurationService $configurationService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the file to scan');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!is_file($filePath)) {
            $io->error("File not found: $filePath");

            return Command::FAILURE;
        }

        if (!is_readable($filePath)) {
            $io->error("File is not readable: $filePath");

            return Command::FAILURE;
        }

        $fileSize = filesize($filePath);
        $fileHash = hash_file('sha256', $filePath);

        $io->title('ClamAV File Scan');
        $io->definitionList(
            ['File' => $filePath],
            ['Size' => $fileSize.' bytes'],
            ['SHA-256' => $fileHash],
        );

        $handle = null;
        try {
            $bundleConfig = $this->configurationService->getConfig();
            $parts = parse_url($bundleConfig['url']);
            if ($parts === false || !isset($parts['host'])) {
                throw new \InvalidArgumentException('Invalid ClamAV URL in configuration: '.$bundleConfig['url']);
            }
            $host = $parts['host'];
            $port = isset($parts['port']) ? (int) $parts['port'] : 3310;

            $client = ClamAvClient::createForHost($host, $port);

            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('Could not open file: '.$filePath);
            }

            $result = $client->scanStream($handle);
        } catch (\Exception $e) {
            $io->error('Scan failed: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            if ($handle !== null) {
                fclose($handle);
            }
        }

        if ($result->isClean()) {
            $io->success('File is clean.');

            return Command::SUCCESS;
        }

        if ($result->isVirusFound()) {
            $io->error("Threat detected: $result->virusName");

            return Command::FAILURE;
        }

        $io->error("Scan error: $result->errorMessage");

        return Command::FAILURE;
    }
}
