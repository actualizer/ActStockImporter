<?php declare(strict_types=1);

namespace ActStockImporter\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Psr\Log\LoggerInterface;

/**
 * Service for importing stock data into Shopware.
 * Handles both stock update methods (absolute/normal) and product activation status.
 */
class StockImportService
{
    private EntityRepository $productRepository;
    private SystemConfigService $systemConfigService;
    private FileHandlerService $fileHandler;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $productRepository,
        SystemConfigService $systemConfigService,
        FileHandlerService $fileHandler,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
    }

    /**
     * Main import method that processes all stock files and updates products.
     * 
     * The process:
     * 1. Get all stock files (local or SFTP)
     * 2. Process each file in order (oldest first)
     * 3. Parse CSV and aggregate stock for duplicate product numbers
     * 4. Update each product's stock and active status
     * 5. Move processed file to backup
     */
    public function import(): void
    {
        $files = $this->fileHandler->getStockFiles();
        if (empty($files)) {
            return;
        }

        foreach ($files as $filePath) {
            $this->logger->info('ACT Stock Importer: Processing file', ['file' => basename($filePath)]);
            
            $stocks = $this->fileHandler->parseCSV($filePath);
            if (empty($stocks)) {
                $this->logger->warning('ACT Stock Importer: No valid data found in file', ['file' => basename($filePath)]);
                $this->fileHandler->backupFile($filePath);
                continue;
            }

            $this->updateProducts($stocks);
            $this->fileHandler->backupFile($filePath);
        }
    }

    /**
     * Update products with new stock data
     */
    private function updateProducts(array $stocks): void
    {
        $context = Context::createDefaultContext();
        $updateMethod = $this->systemConfigService->get('ActStockImporter.config.stockUpdateMethod');

        foreach ($stocks as $articleNumber => $data) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productNumber', $articleNumber));
            
            /** @var ProductEntity|null $product */
            $product = $this->productRepository->search($criteria, $context)->first();
            
            if (!$product) {
                $this->logger->warning('ACT Stock Importer: Product not found', ['articleNumber' => $articleNumber]);
                continue;
            }

            $updateData = [
                'id' => $product->getId(),
                'active' => $data['active'],
            ];

            if ($updateMethod === 'absolute') {
                $updateData['stock'] = $data['stock'];
                $updateData['availableStock'] = $data['stock'];
            } else {
                $updateData['stock'] = $data['stock'];
            }

            $this->productRepository->update([$updateData], $context);
            $this->logger->info('ACT Stock Importer: Updated product', [
                'articleNumber' => $articleNumber,
                'stock' => $data['stock'],
                'active' => $data['active']
            ]);
        }
    }
}
