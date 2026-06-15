<?php

declare(strict_types=1);

namespace Dbp\Relay\VerityConnectorClamavBundle\Command;

use Dbp\Relay\VerityConnectorClamavBundle\Service\ClamAvAPI;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\File;

#[AsCommand(
    name: 'dbp:relay:verity-connector-clamav:check-file',
    description: 'Scan a local file for viruses using the ClamAV daemon',
)]
class CheckFileCommand extends Command
{
    public function __construct(private readonly ClamAvAPI $clamAvAPI)
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

        $file = new File($filePath);
        $fileSize = $file->getSize();
        $fileName = $file->getFilename();
        $fileHash = hash_file('sha256', $filePath);

        $io->title('ClamAV File Scan');
        $io->definitionList(
            ['File' => $filePath],
            ['Size' => $fileSize.' bytes'],
            ['SHA-256' => $fileHash],
        );

        try {
            $result = $this->clamAvAPI->validate($file, $fileName, $fileSize, $fileHash, '', $file->getMimeType() ?? '');
        } catch (\Exception $e) {
            $io->error('Scan failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($result->validity) {
            $io->success('File is clean — no threats detected.');

            return Command::SUCCESS;
        }

        $io->error('Threat detected: '.$result->message);
        foreach ($result->errors as $error) {
            $io->writeln("  $error");
        }

        return Command::FAILURE;
    }
}
