<?php declare(strict_types=1);

namespace ActStockImporter\Tests\Service;

use ActStockImporter\Service\FileHandlerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

final class FileHandlerServiceTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'act-stock-');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testSkipsHeaderAndParsesRows(): void
    {
        $stocks = $this->parse("Product Number;Stock;Active\nABC-1;50;1\nXYZ-2;0;0\n");

        self::assertSame([
            'ABC-1' => ['stock' => 50, 'active' => true],
            'XYZ-2' => ['stock' => 0, 'active' => false],
        ], $stocks);
    }

    public function testAddsUpDuplicateNumbersAndKeepsProductActiveIfAnyRowIsActive(): void
    {
        $stocks = $this->parse("number;stock;active\nABC-1;10;0\nABC-1;3;1\n");

        self::assertSame(['ABC-1' => ['stock' => 13, 'active' => true]], $stocks);
    }

    public function testSkipsRowsWithFewerThanThreeColumns(): void
    {
        $stocks = $this->parse("number;stock;active\nABC-1;10\nXYZ-2;4;1\n");

        self::assertSame(['XYZ-2' => ['stock' => 4, 'active' => true]], $stocks);
    }

    /**
     * @return array<string, array{stock: int, active: bool}>
     */
    private function parse(string $csv): array
    {
        file_put_contents($this->file, $csv);
        $service = new FileHandlerService(new StaticSystemConfigService(), new NullLogger(), sys_get_temp_dir());

        return $service->parseCSV($this->file);
    }
}
