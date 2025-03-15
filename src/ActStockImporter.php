<?php declare(strict_types=1);

namespace ActStockImporter;

use ActStockImporter\Scheduled\StockImportTask;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class ActStockImporter extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.xml');
    }

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

        // Get configured interval or use default
        $configService = $this->container->get(SystemConfigService::class);
        $interval = $configService->get('ActStockImporter.config.scheduledImportInterval') ?? 120;

        // Register scheduled task
        $taskRepository = $this->container->get('scheduled_task.repository');
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
        $importDir = $this->container->getParameter('kernel.project_dir') . '/_act_stockimporter';
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
        if (!$uninstallContext->keepUserData()) {
            // Clean up import directory
            $importDir = $this->container->getParameter('kernel.project_dir') . '/_act_stockimporter';
            if (is_dir($importDir)) {
                $this->removeDirectory($importDir);
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
