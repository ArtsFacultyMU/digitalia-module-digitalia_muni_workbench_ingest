This module serves to run specified Islandora Workbench scripts from web interface.
Currently tested with create operation.

## Basic operation
User ID and current node ID are used to fill Author and Member of nodes respectively.

## Prerequisites
- sudo with config allowing apache user to execute workbench as a normal system user
`<apache user> ALL=(<system user>) NOPASSWD: /path/to/workbench/executable`
`www-data ALL=(islandora) NOPASSWD: /home/islandora/islandora_workbench/workbench`

## Configuration
Configuration is available at Configuration -> System -> Development -> Workbench Ingest Settings `/admin/config/system/digitalia_muni_workbench_ingest`

### Workbench system user
This user must be able to run workbench executable.

### Workbench Drupal user
Drupal user under which Workbench authenticates.

### Workbench Drupal user password
WARNING: create a key override for this field (using key with a File provider), otherwise the password WILL be written in plaintext after config export.

### Path to workbench executable
Absolute path to workbench executable.

### Paths to workbench configuration files
List of absolute paths to configuration files, one per line. First one is considered default.

## Blocks
### Ingest items
Contains controls for data ingest.

### Status of current ingest
Optional block displaying progress of current ingest.

## Workbench config file
Example config for workbench:
```
task: create
host: "https://dva-devel3.ics.muni.cz/"
username:
password:
input_dir: /var/www/html/drupal/web/sites/default/files/scan_upload
input_csv: import.csv
delimiter: ;
content_type: scan
id_field: field_weight
rollback_dir: /var/www/html/drupal/workbench
rollback_config_file_path: /var/www/html/drupal/workbench/rollback.yml
timestamp_rollback: true
rollback_file_include_node_info: true
progress_bar: true
# REMOVE AFTER TESTING
#include_password_in_rollback_config_file: true

allow_csv_value_templates_if_field_empty: ['title', 'field_weight']
csv_value_templates:
 - file: $csv_value.tif
 - title: $filename_without_extension
```

## Time to check input configuration
| Number of nodes | time in seconds |
| 499             |         90      |
| 1727            |        292      |
| 2000            |        328      |
