<?php declare(strict_types=1);

namespace ActStockImporter;

use ActStockImporter\Scheduled\StockImportTask;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ActStockImporter extends Plugin
{
    public function getMigrationNamespace(): string
    {
        return 'ActStockImporter\Migration';
    }

    public function getInstallationDirectory(): string
    {
        return __DIR__ . '/../';
    }

    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $container = $this->container;
        if ($container === null) {
            return;
        }

        // Get configured interval or use default
        $configService = $container->get(SystemConfigService::class);
        if (!$configService instanceof SystemConfigService) {
            return;
        }
        $interval = $configService->get('ActStockImporter.config.scheduledImportInterval') ?? 300;

        // Register scheduled task
        $taskRepository = $container->get('scheduled_task.repository');
        if (!$taskRepository instanceof EntityRepository) {
            return;
        }
        $taskRepository->create([
            [
                'name' => StockImportTask::getTaskName(),
                'scheduledTaskClass' => StockImportTask::class,
                'runInterval' => $interval,
                'defaultRunInterval' => $interval,
                'status' => 'scheduled',
                'nextExecutionTime' => new \DateTime(),
            ]
        ], $installContext->getContext());

        // Create example directory and copy example file
        $projectDir = $container->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            return;
        }
        $importDir = $projectDir . '/_act_stockimporter';
        if (!file_exists($importDir)) {
            mkdir($importDir, 0755, true);
        }

        $exampleFile = __DIR__ . '/Resources/import/example_stock.csv';
        if (file_exists($exampleFile)) {
            copy($exampleFile, $importDir . '/example_stock.csv');
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $container = $this->container;
        if (!$uninstallContext->keepUserData() && $container !== null) {
            // Clean up import directory
            $projectDir = $container->getParameter('kernel.project_dir');
            if (is_string($projectDir)) {
                $importDir = $projectDir . '/_act_stockimporter';
                if (is_dir($importDir)) {
                    $this->removeDirectory($importDir);
                }
            }
        }

        parent::uninstall($uninstallContext);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
