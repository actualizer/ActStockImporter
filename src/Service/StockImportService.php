<?php declare(strict_types=1);

namespace ActStockImporter\Service;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Psr\Log\LoggerInterface;

/**
 * Service for importing stock data into Shopware.
 * Updates product stock and activation status.
 */
class StockImportService
{
    /** @var EntityRepository<ProductCollection> */
    private EntityRepository $productRepository;
    private FileHandlerService $fileHandler;
    private LoggerInterface $logger;

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        EntityRepository $productRepository,
        FileHandlerService $fileHandler,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
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
     * 4. Update the products whose stock or active status actually changed
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

            $result = $this->updateProducts($stocks);
            $this->logger->info('ACT Stock Importer: File processed', [
                'file' => basename($filePath),
                'checked' => $result['checked'],
                'updated' => $result['updated'],
                'unchanged' => $result['unchanged'],
                'notFound' => $result['notFound'],
            ]);

            $this->fileHandler->backupFile($filePath);
        }
    }

    /**
     * Update the products whose stock or active status differs from the imported data.
     *
     * Unchanged products are skipped: writing them would produce no new value but
     * still trigger the product indexer, cache invalidation and (if enabled) a
     * search reindex for every row of the file.
     *
     * @param array<string, array{stock: int, active: bool}> $stocks
     *
     * @return array{checked: int, updated: int, unchanged: int, notFound: int}
     */
    private function updateProducts(array $stocks): array
    {
        $context = Context::createCLIContext();

        $updatedCount = 0;
        $unchangedCount = 0;
        $notFoundCount = 0;

        // Resolve and write in chunks to avoid N:1 queries and bounded memory use.
        foreach (array_chunk($stocks, 500, true) as $chunk) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('productNumber', array_keys($chunk)));

            $currentByNumber = [];
            foreach ($this->productRepository->search($criteria, $context)->getEntities() as $product) {
                $currentByNumber[$product->getProductNumber()] = [
                    'id' => $product->getId(),
                    'stock' => $product->getStock(),
                    'active' => $product->getActive(),
                ];
            }

            $updates = [];
            foreach ($chunk as $articleNumber => $data) {
                if (!isset($currentByNumber[$articleNumber])) {
                    $this->logger->warning('ACT Stock Importer: Product not found', ['articleNumber' => $articleNumber]);
                    ++$notFoundCount;
                    continue;
                }

                $current = $currentByNumber[$articleNumber];

                // availableStock is neither compared nor written: Shopware mirrors it
                // from stock on every stock write.
                if ($current['stock'] === $data['stock'] && $current['active'] === $data['active']) {
                    ++$unchangedCount;
                    continue;
                }

                $updateData = [
                    'id' => $current['id'],
                    'active' => $data['active'],
                    'stock' => $data['stock'],
                ];

                $updates[] = $updateData;
                ++$updatedCount;

                $this->logger->info('ACT Stock Importer: Updated product', [
                    'articleNumber' => $articleNumber,
                    'stock' => $data['stock'],
                    'active' => $data['active'],
                    'previousStock' => $current['stock'],
                    'previousActive' => $current['active'],
                ]);
            }

            if ($updates !== []) {
                $this->productRepository->update($updates, $context);
            }
        }

        return [
            'checked' => count($stocks),
            'updated' => $updatedCount,
            'unchanged' => $unchangedCount,
            'notFound' => $notFoundCount,
        ];
    }
}
