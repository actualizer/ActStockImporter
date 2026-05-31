<?php declare(strict_types=1);

namespace ActStockImporter\Scheduled;

use ActStockImporter\Service\StockImportService;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Psr\Log\LoggerInterface;

/**
 * Handles scheduled stock imports.
 * The interval can be configured in the plugin settings.
 */
class StockImportTaskHandler extends ScheduledTaskHandler
{
    private SystemConfigService $systemConfigService;
    private StockImportService $stockImportService;
    private LoggerInterface $logger;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        SystemConfigService $systemConfigService,
        StockImportService $stockImportService,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
        $this->systemConfigService = $systemConfigService;
        $this->stockImportService = $stockImportService;
        $this->logger = $logger;
    }

    /**
     * @return iterable<class-string>
     */
    public static function getHandledMessages(): iterable
    {
        return [StockImportTask::class];
    }

    /**
     * Main task execution method.
     *
     * Process:
     * 1. Check if automatic import is enabled
     * 2. Update task interval from config if changed
     * 3. Run the import process
     */
    public function run(): void
    {
        $this->logger->info('Actualize Stock Import Task: Starting scheduled import');

        // Skip if automatic import is disabled
        $isActive = $this->systemConfigService->get('ActStockImporter.config.scheduledImportActive');
        $this->logger->info('Actualize Stock Import Task: Automatic import active: ' . ($isActive ? 'yes' : 'no'));

        if (!$isActive) {
            return;
        }

        // Update task interval from config (convert minutes to seconds)
        $intervalRaw = $this->systemConfigService->get('ActStockImporter.config.scheduledImportInterval');
        $intervalMinutes = is_numeric($intervalRaw) ? (int) $intervalRaw : 2;
        $intervalSeconds = $intervalMinutes * 60;
        $this->updateTaskInterval($intervalSeconds);

        try {
            $this->stockImportService->import();
            $this->logger->info('Actualize Stock Import Task: Import completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Actualize Stock Import Task: Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Update the scheduled task interval in the database.
     * This allows dynamic interval changes without plugin reinstall.
     */
    private function updateTaskInterval(int $interval): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', StockImportTask::getTaskName()));

        $taskId = $this->scheduledTaskRepository->searchIds($criteria, Context::createCLIContext())->firstId();

        if ($taskId) {
            $this->scheduledTaskRepository->update([
                [
                    'id' => $taskId,
                    'runInterval' => $interval,
                ]
            ], Context::createCLIContext());
            $this->logger->info('Actualize StockImportTask: Updated interval to ' . ($interval / 60) . ' minutes (' . $interval . ' seconds)');
        }
    }
}
