# ACT Stock Importer

## English

A Shopware 6 plugin for importing stock levels via CSV files, either locally or via SFTP.

### Features

- Import stock levels from CSV files
- Support for local files and SFTP
- Automatic aggregation of duplicate article numbers
- Product activation/deactivation support
- Choice between absolute and normal stock levels
- Automatic import at configurable intervals
- Backup of all processed CSV files

### Installation

1. Install the plugin:
```bash
composer require act/stock-importer
bin/console plugin:refresh
bin/console plugin:install --activate ActStockImporter
```

2. Clear cache:
```bash
bin/console cache:clear
```

### Configuration

#### Import Settings
- Choose between local import and SFTP
- Stock update method (Absolute/Normal)
  - Absolute: Sets both stock fields to the same value
  - Normal: Updates regular stock, available stock is calculated by Shopware

#### SFTP Settings (optional)
- Host
- Port (default: 22)
- Username
- Password
- File path

#### CSV Settings
- Delimiter (default: ;)
- Encoding (UTF-8 or ISO-8859-1)

#### Automatic Import
- Enable/disable automatic import
- Configurable interval (5 minutes to daily)

### CSV Format

The CSV file must have the following format:
```
articlenumber;stock;active
ABC123;10;1
DEF456;5;0
```

Fields:
- articlenumber: Product number in Shopware
- stock: Integer value
- active: 1 for active, 0 for inactive

Example scenarios:
```
ABC123;10;1    # Set stock to 10, product active
DEF456;5;0     # Set stock to 5, product inactive
ABC123;3;1     # Will be added to previous stock (13 total)
```

### Usage

#### Manual Import
```bash
bin/console act:stock:import
```

#### Automatic Import
The automatic import runs according to the plugin configuration.

#### Import Directory
Local CSV files must be placed in the `_act_stockimporter` directory in the Shopware root.
Processed files are automatically moved to the `backup` subdirectory with a timestamp.

#### File Processing
1. Files are processed in order of discovery
2. Duplicate article numbers in a file have their stock levels aggregated
3. The active status is set based on the last occurrence in the file
4. After processing, files are moved to backup with timestamp

### Logging

The plugin logs all actions in the Shopware log:
- Found CSV files
- Import status
- SFTP connections
- Errors and warnings

### Support

For questions or issues, please create an issue in our repository or contact support@act.de

---

## Deutsch

Ein Shopware 6 Plugin zum Import von Lagerbeständen über CSV-Dateien, entweder lokal oder via SFTP.

### Funktionen

- Import von Lagerbeständen aus CSV-Dateien
- Unterstützung für lokale Dateien und SFTP
- Automatische Summierung von mehrfachen Artikelnummern
- Aktivierung/Deaktivierung von Artikeln
- Wahl zwischen absolutem und normalem Bestand
- Automatischer Import in konfigurierbaren Intervallen
- Backup aller verarbeiteten CSV-Dateien

### Installation

1. Plugin installieren:
```bash
composer require act/stock-importer
bin/console plugin:refresh
bin/console plugin:install --activate ActStockImporter
```

2. Cache leeren:
```bash
bin/console cache:clear
```

### Konfiguration

#### Import-Einstellungen
- Wahl zwischen lokalem Import und SFTP
- Bestandsart wählbar (Absolut/Normal)
  - Absolut: Setzt beide Bestandsfelder auf den gleichen Wert
  - Normal: Aktualisiert den regulären Bestand, verfügbarer Bestand wird von Shopware berechnet

#### SFTP-Einstellungen (optional)
- Host
- Port (Standard: 22)
- Benutzername
- Passwort
- Dateipfad

#### CSV-Einstellungen
- Trennzeichen (Standard: ;)
- Kodierung (UTF-8 oder ISO-8859-1)

#### Automatischer Import
- Aktivierung/Deaktivierung des automatischen Imports
- Konfigurierbares Intervall (5 Minuten bis täglich)

### CSV-Format

Die CSV-Datei muss folgendes Format haben:
```
artikelnummer;bestand;aktiv
ABC123;10;1
DEF456;5;0
```

Felder:
- artikelnummer: Artikelnummer in Shopware
- bestand: Ganzzahl
- aktiv: 1 für aktiv, 0 für inaktiv

Beispielszenarien:
```
ABC123;10;1    # Bestand auf 10 setzen, Artikel aktiv
DEF456;5;0     # Bestand auf 5 setzen, Artikel inaktiv
ABC123;3;1     # Wird zum vorherigen Bestand addiert (13 gesamt)
```

### Verwendung

#### Manueller Import
```bash
bin/console act:stock:import
```

#### Automatischer Import
Der automatische Import läuft entsprechend der Plugin-Konfiguration.

#### Import-Verzeichnis
Lokale CSV-Dateien müssen im Verzeichnis `_act_stockimporter` im Shopware-Root abgelegt werden.
Verarbeitete Dateien werden automatisch in den Unterordner `backup` mit Zeitstempel verschoben.

#### Dateiverarbeitung
1. Dateien werden in der Reihenfolge der Entdeckung verarbeitet
2. Mehrfache Artikelnummern in einer Datei werden summiert
3. Der Aktiv-Status wird basierend auf dem letzten Vorkommen in der Datei gesetzt
4. Nach der Verarbeitung werden die Dateien mit Zeitstempel ins Backup verschoben

### Protokollierung

Das Plugin protokolliert alle Aktionen im Shopware-Log:
- Gefundene CSV-Dateien
- Import-Status
- SFTP-Verbindungen
- Fehler und Warnungen

### Unterstützung

Bei Fragen oder Problemen erstellen Sie bitte ein Issue in unserem Repository oder kontaktieren Sie support@act.de
