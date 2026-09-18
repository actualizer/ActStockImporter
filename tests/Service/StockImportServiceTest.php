<?php declare(strict_types=1);

namespace ActStockImporter\Tests\Service;

use ActStockImporter\Service\FileHandlerService;
use ActStockImporter\Service\StockImportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

final class StockImportServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/act-stock-importer-' . bin2hex(random_bytes(4));
        mkdir($this->projectDir . '/_act_stockimporter', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectDir . '/_act_stockimporter/{,backup/}*.csv', \GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->projectDir . '/_act_stockimporter/backup');
        @rmdir($this->projectDir . '/_act_stockimporter');
        @rmdir($this->projectDir);
    }

    public function testWritesOnlyChangedProductsAndLeavesAvailableStockToShopware(): void
    {
        file_put_contents(
            $this->projectDir . '/_act_stockimporter/stock.csv',
            "number;stock;active\nUNCHANGED;5;1\nCHANGED;7;0\nUNKNOWN;1;1\n"
        );

        $repository = new StaticEntityRepository([
            $this->searchResult(
                $this->product('id-unchanged', 'UNCHANGED', 5, true),
                $this->product('id-changed', 'CHANGED', 3, true),
            ),
        ]);

        $this->service($repository)->import();

        self::assertSame([[['id' => 'id-changed', 'active' => false, 'stock' => 7]]], $repository->updates);
    }

    public function testMovesProcessedFileToBackup(): void
    {
        file_put_contents($this->projectDir . '/_act_stockimporter/stock.csv', "number;stock;active\nUNKNOWN;1;1\n");

        $this->service(new StaticEntityRepository([$this->searchResult()]))->import();

        self::assertFileDoesNotExist($this->projectDir . '/_act_stockimporter/stock.csv');
        self::assertCount(1, glob($this->projectDir . '/_act_stockimporter/backup/*_stock.csv') ?: []);
    }

    /**
     * @param StaticEntityRepository<ProductCollection> $repository
     */
    private function service(StaticEntityRepository $repository): StockImportService
    {
        $fileHandler = new FileHandlerService(new StaticSystemConfigService(), new NullLogger(), $this->projectDir);

        return new StockImportService($repository, $fileHandler, new NullLogger());
    }

    private function product(string $id, string $number, int $stock, bool $active): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId($id);
        $product->setUniqueIdentifier($id);
        $product->setProductNumber($number);
        $product->setStock($stock);
        $product->setActive($active);

        return $product;
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function searchResult(ProductEntity ...$products): EntitySearchResult
    {
        return new EntitySearchResult(
            'product',
            \count($products),
            new ProductCollection($products),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
