<?php declare(strict_types=1);

namespace ActStockImporter\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use phpseclib3\Net\SFTP;

class FileHandlerService
{
    private SystemConfigService $systemConfigService;
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(
        SystemConfigService $systemConfigService,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * Get all stock files to process, sorted by modification time
     * @return array<string> Array of file paths
     */
    public function getStockFiles(): array
    {
        $importMethod = $this->systemConfigService->get('ActStockImporter.config.importMethod');

        if ($importMethod === 'sftp') {
            return $this->getStockFilesFromSftp();
        }

        return $this->getStockFilesFromLocal();
    }

    /**
     * Parse CSV file and aggregate stock data
     */
    public function parseCSV(string $filePath): array
    {
        $stocks = [];

        if (($handle = fopen($filePath, "r")) !== false) {
            // Skip header row
            fgetcsv($handle, 1000, ";");

            while (($data = fgetcsv($handle, 1000, ";")) !== false) {
                if (count($data) < 3) {
                    continue;
                }

                $articleNumber = $data[0];
                $stock = (int)$data[1];
                $active = (bool)$data[2];

                // Aggregate stock for duplicate product numbers
                if (!isset($stocks[$articleNumber])) {
                    $stocks[$articleNumber] = [
                        'stock' => $stock,
                        'active' => $active
                    ];
                } else {
                    // Add stock for duplicate entries
                    $stocks[$articleNumber]['stock'] += $stock;
                    // If any entry is active, keep product active
                    $stocks[$articleNumber]['active'] = $stocks[$articleNumber]['active'] || $active;
                }
            }
            fclose($handle);
        }

        return $stocks;
    }

    /**
     * Move processed file to backup directory and clean up old backups
     */
    public function backupFile(string $filePath): void
    {
        $backupDir = $this->projectDir . '/_act_stockimporter/backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $fileName = basename($filePath);
        $backupFile = date('Y-m-d_H-i-s') . '_' . $fileName;
        $backupPath = $backupDir . '/' . $backupFile;

        rename($filePath, $backupPath);
        $this->logger->info('Actualize Stock Importer: Moved processed file to backup', [
            'originalFile' => $fileName,
            'backupFile' => $backupFile
        ]);

        // Clean up old backup files
        $this->cleanupBackupFiles();
    }

    /**
     * Clean up old backup files based on retention period
     */
    private function cleanupBackupFiles(): void
    {
        $backupDir = $this->projectDir . '/_act_stockimporter/backup';
        if (!is_dir($backupDir)) {
            return;
        }

        $retentionDays = (int)$this->systemConfigService->get('ActStockImporter.config.backupRetentionDays') ?? 30;
        $cutoffTime = strtotime("-{$retentionDays} days");

        $files = glob($backupDir . '/*.csv');
        $deletedCount = 0;

        foreach ($files as $file) {
            $modTime = filemtime($file);
            if ($modTime < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                    $this->logger->debug('Actualize Stock Importer: Deleted old backup file', [
                        'file' => basename($file),
                        'age' => floor((time() - $modTime) / 86400) . ' days'
                    ]);
                }
            }
        }

        if ($deletedCount > 0) {
            $this->logger->info('Actualize Stock Importer: Cleaned up old backup files', [
                'deletedCount' => $deletedCount,
                'retentionDays' => $retentionDays
            ]);
        }
    }

    private function getStockFilesFromLocal(): array
    {
        $importDir = $this->projectDir . '/_act_stockimporter';
        if (!is_dir($importDir)) {
            mkdir($importDir, 0755, true);
        }

        $files = glob($importDir . '/*.csv');
        if (empty($files)) {
            return [];
        }

        // Sort files by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        return $files;
    }

    private function getStockFilesFromSftp(): array
    {
        $host = $this->systemConfigService->get('ActStockImporter.config.sftpHost');
        $port = (int)$this->systemConfigService->get('ActStockImporter.config.sftpPort');
        $username = $this->systemConfigService->get('ActStockImporter.config.sftpUsername');
        $password = $this->systemConfigService->get('ActStockImporter.config.sftpPassword');
        $remotePath = $this->systemConfigService->get('ActStockImporter.config.sftpPath');

        if (!$host || !$username || !$password || !$remotePath) {
            $this->logger->error('Actualize Stock Importer: Missing SFTP configuration');
            return [];
        }

        try {
            $sftp = new SFTP($host, $port);
            if (!$sftp->login($username, $password)) {
                throw new \Exception('SFTP Login failed');
            }

            $files = $sftp->nlist($remotePath);
            if (!$files) {
                return [];
            }

            $localFiles = [];
            $importDir = $this->projectDir . '/_act_stockimporter';

            // Filter for CSV files and download them
            foreach ($files as $file) {
                if (!preg_match('/\.csv$/i', $file)) {
                    continue;
                }

                $remotefile = rtrim($remotePath, '/') . '/' . $file;
                $localFile = $importDir . '/' . $file;

                if ($sftp->get($remotefile, $localFile)) {
                    $localFiles[] = $localFile;
                    $sftp->delete($remotefile);
                    $this->logger->info('Actualize Stock Importer: Downloaded file from SFTP', [
                        'file' => $file
                    ]);
                }
            }

            // Sort by modification time
            if (!empty($localFiles)) {
                usort($localFiles, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
            }

            return $localFiles;

        } catch (\Exception $e) {
            $this->logger->error('Actualize Stock Importer: SFTP error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
