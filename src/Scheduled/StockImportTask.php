<?php declare(strict_types=1);

namespace ActStockImporter\Scheduled;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class StockImportTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'act_stock.import';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // 5 minutes (Shopware minimum for scheduled tasks)
    }
}
