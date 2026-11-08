<?php declare(strict_types=1);

namespace ActStockImporter\Tests\Service;

use ActStockImporter\Service\FileHandlerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * The SFTP settings count as missing only when a value is empty, not when it is "0".
 */
final class FileHandlerServiceSftpConfigTest extends TestCase
{
    private const MISSING_CONFIG = 'Actualize Stock Importer: Missing SFTP configuration';
    private const SFTP_ERROR = 'Actualize Stock Importer: SFTP error';

    public function testPasswordZeroIsUsedToConnect(): void
    {
        $messages = $this->fetchWithPassword('0');

        self::assertNotContains(self::MISSING_CONFIG, $messages);
        self::assertContains(self::SFTP_ERROR, $messages);
    }

    public function testEmptyPasswordIsReportedAsMissingConfiguration(): void
    {
        $messages = $this->fetchWithPassword('');

        self::assertSame([self::MISSING_CONFIG], $messages);
    }

    /**
     * Port 1 on the loopback interface is closed, so a connection attempt fails at once.
     *
     * @return list<string> the logged error messages
     */
    private function fetchWithPassword(string $password): array
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $errors = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if ($level === 'error') {
                    $this->errors[] = (string) $message;
                }
            }
        };

        $config = new StaticSystemConfigService([
            'ActStockImporter.config.importMethod' => 'sftp',
            'ActStockImporter.config.sftpHost' => '127.0.0.1',
            'ActStockImporter.config.sftpPort' => 1,
            'ActStockImporter.config.sftpUsername' => 'import',
            'ActStockImporter.config.sftpPassword' => $password,
            'ActStockImporter.config.sftpPath' => '/upload',
        ]);

        $files = (new FileHandlerService($config, $logger, sys_get_temp_dir()))->getStockFiles();

        self::assertSame([], $files);

        return $logger->errors;
    }
}
