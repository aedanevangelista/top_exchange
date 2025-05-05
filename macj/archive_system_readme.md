# Archive System for MacJ Pest Control

This document provides instructions for setting up and using the archive system for deleted data in the MacJ Pest Control system.

## Overview

The archive system allows deleted data to be stored in archive tables for 30 days before being permanently deleted. This provides a safety net in case data is accidentally deleted and needs to be restored.

The following modules have archive functionality:
- Chemical Inventory
- Clients
- Technicians
- Tools & Equipment

## Database Setup

The archive system requires additional tables in the database. Run the `create_archive_tables.sql` script to create these tables:

```sql
-- Run this script to create the archive tables
mysql -u your_username -p your_database_name < create_archive_tables.sql
```

## Cron Job Setup

The archive system uses a cron job to automatically delete expired archived items (items that have been in the archive for more than 30 days).

### Setting Up the Cron Job on Hostinger

Since you're using a shared Hostinger plan, you'll need to set up the cron job using Hostinger's control panel:

1. Log in to your Hostinger control panel
2. Navigate to the "Advanced" section
3. Click on "Cron Jobs"
4. Create a new cron job with the following settings:
   - Common Settings: Once a day
   - Command: `php /path/to/your/public_html/macj/cron_cleanup_archives.php`
   - Replace `/path/to/your/public_html/macj/` with the actual path to your MacJ Pest Control installation

### Alternative: Manual Execution

If you can't set up a cron job, you can manually run the cleanup script periodically:

1. Navigate to your MacJ Pest Control installation directory
2. Run the following command:
   ```
   php cron_cleanup_archives.php
   ```

## Using the Archive System

### Viewing Archived Items

Each module has an "View Archive" button that takes you to the archive page for that module:

- Chemical Inventory: `chemical_archive.php`
- Clients: `clients_archive.php`
- Technicians: `technicians_archive.php`
- Tools & Equipment: `tools_archive.php`

### Restoring Archived Items

On each archive page, you can restore items by clicking the "Restore" button next to the item you want to restore.

## Troubleshooting

### Checking the Archive Cleanup Log

The archive cleanup script creates a log file called `archive_cleanup_log.txt` in the root directory of your MacJ Pest Control installation. You can check this file to see if the cleanup script is running correctly.

### Common Issues

1. **Cron job not running**: Check the Hostinger cron job logs to see if there are any errors.
2. **Database connection issues**: Make sure the database connection parameters in `db_connect.php` and `db_config.php` are correct.
3. **Permission issues**: Make sure the web server has permission to write to the log file.

## Support

If you encounter any issues with the archive system, please contact the system administrator.
