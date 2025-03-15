<?php declare(strict_types=1);

namespace ActStockImporter\Command;

use ActStockImporter\Service\StockImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'act:stock:import',
    description: 'Import stock levels from CSV file'
)]
class ImportStockCommand extends Command
{
    private StockImportService $stockImportService;

    public function __construct(StockImportService $stockImportService)
    {
        parent::__construct();
        $this->stockImportService = $stockImportService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting stock import...');
        
        try {
            $this->stockImportService->import();
            $output->writeln('Stock import completed successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error during import: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
