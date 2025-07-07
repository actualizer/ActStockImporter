# ActStockImporter - Shopware Plugin

A Shopware 6 plugin that automatically imports stock levels from CSV files, supporting both local file processing and SFTP integration for automated stock management.

## Features

- ✅ Import stock levels from CSV files
- ✅ Support for both local directory and SFTP server as data source
- ✅ Scheduled automatic imports with configurable intervals
- ✅ Manual import via console command
- ✅ Product activation/deactivation based on stock status
- ✅ Two stock update methods: absolute and normal
- ✅ Automatic file backup with configurable retention
- ✅ Stock aggregation for duplicate product numbers
- ✅ Comprehensive logging and error handling
- ✅ Compatible with Shopware 6.6.10 - 6.7.x

## Requirements

- Shopware 6.6.10 or higher (up to 6.7.x)
- PHP 8.3 or higher
- phpseclib/phpseclib ^3.0 (for SFTP functionality)

## Installation

1. Download or clone this plugin into your `custom/plugins/` directory
2. Install and activate the plugin via CLI:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate ActStockImporter
   bin/console cache:clear
   ```

## Configuration

1. Go to Admin Panel → Settings → System → Plugins
2. Find "Actualize: Stock Importer" and click on the three dots
3. Click "Config" to access plugin settings

### Configuration Options

- **Import Method**: Choose between local directory or SFTP server
- **Stock Update Method**:
  - `normal`: Updates only stock field
  - `absolute`: Updates both stock and availableStock fields
- **Scheduled Import**: Enable/disable automatic imports
- **Import Interval**: Set interval in minutes for automatic imports
- **Backup Retention**: Number of days to keep backup files
- **SFTP Settings** (if using SFTP method):
  - Host, Port, Username, Password
  - Remote directory path

## CSV File Format

The plugin expects CSV files with semicolon (`;`) as delimiter and the following structure:

```csv
Product Number;Stock;Active
ABC-123;50;1
XYZ-456;0;0
DEF-789;25;1
```

**Columns:**
- `Product Number`: The product's article number in Shopware
- `Stock`: Stock quantity (integer)
- `Active`: Product activation status (1 = active, 0 = inactive)

## How it works

### Local Directory Method
1. **File Placement**: Place CSV files in the `_act_stockimporter` directory in your project root
2. **Processing**: Files are processed in order of modification time (oldest first)
3. **Backup**: Processed files are moved to `_act_stockimporter/backup` with timestamp prefix
4. **Cleanup**: Old backup files are automatically deleted based on retention settings

### SFTP Method
1. **File Download**: Plugin connects to SFTP server and downloads CSV files
2. **Processing**: Downloaded files are processed locally
3. **Cleanup**: Files are deleted from SFTP server after successful download
4. **Backup**: Same local backup process as directory method

### Stock Processing
1. **Aggregation**: If multiple rows exist for the same product number, stock quantities are summed
2. **Activation**: If any row for a product is marked as active, the product remains active
3. **Update**: Products are updated with new stock levels and activation status
4. **Logging**: All operations are logged with detailed information

## Manual Import

You can trigger imports manually using the console command:

```bash
bin/console act:stock:import
```

## Technical Details

### Events and Scheduling
- Uses Shopware's scheduled task system for automatic imports
- Task interval is dynamically configurable through admin settings
- Supports both immediate execution and scheduled processing

### File Processing
- CSV parsing with semicolon delimiter
- Automatic header row detection and skipping
- Error handling for malformed files
- Support for different file encodings

### Stock Update Methods
- **Normal**: Updates only the `stock` field
- **Absolute**: Updates both `stock` and `availableStock` fields for complete inventory control

### Backup and Retention
- Automatic backup of processed files with timestamp
- Configurable retention period (default: 30 days)
- Automatic cleanup of old backup files

## File Structure

```
ActStockImporter/
├── composer.json
├── README.md
├── src/
│   ├── ActStockImporter.php
│   ├── Command/
│   │   └── ImportStockCommand.php
│   ├── Resources/
│   │   ├── config/
│   │   │   ├── config.xml
│   │   │   └── services.xml
│   │   └── import/
│   │       └── example_stock.csv
│   ├── Scheduled/
│   │   ├── StockImportTask.php
│   │   └── StockImportTaskHandler.php
│   └── Service/
│       ├── FileHandlerService.php
│       └── StockImportService.php
```

## Development

### Building/Testing
After making changes:
```bash
bin/console cache:clear
bin/console scheduled-task:run
```

### Debugging
- Check log files for import operations and errors
- Enable Shopware's debug mode for detailed error information
- Use the manual import command for testing

## Example Usage

1. **Local Directory Setup**:
   - Enable local directory import method
   - Place CSV files in `_act_stockimporter/`
   - Files are processed automatically or manually

2. **SFTP Integration**:
   - Configure SFTP connection details
   - Set up automated file upload to SFTP server
   - Plugin downloads and processes files automatically

3. **Scheduled Processing**:
   - Enable scheduled imports
   - Set appropriate interval (e.g., 5 minutes)
   - Monitor logs for successful operations

## Compatibility

- **Shopware Version**: 6.6.10 - 6.7.x
- **PHP Version**: 8.3+
- **Dependencies**: phpseclib/phpseclib ^3.0
- **Template Compatibility**: Uses Shopware 6.6+ plugin structure

## Support

For issues and feature requests, please use the GitHub issue tracker.

## License

This plugin is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by Actualize

---

Made with ❤️ for the Shopware Community
