This module serves to run specified Islandora Workbench scripts from web interface.
Currently tested with create operation.

## Basic operation
User ID and current node ID are used to fill Author and Member of fields respectively.

The ingest is not interrupted by page reload or closing of the browser window. Only webserver reload/restart stops the process.

Ingested files are not removed from source directory.

## Progressbar
A patch for Workbench is needed to correctly display progress of current ingest.

### REST endpoint permissions
At `/admin/config/services/rest` endpoint `Ingest progress endpoint` must be enabled. GET and POST methods must be set, Accepted request formats must include json an all Authentication providers should be enabled.

Permissions have to be adjusted. Under section `RESTful Web Services` at `/admin/people/permissions`, workbench user must be able to use the POST method and user starting the ingest must be able to use the GET method.

## Prerequisites
- sudo with config allowing apache user to execute workbench as a normal system user
- `<apache user> ALL=(<system user>) NOPASSWD: /path/to/workbench/executable`
- `www-data ALL=(islandora) NOPASSWD: /home/islandora/islandora_workbench/workbench`


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

## `impoprt.csv` example
A warninig is issued if a line contains less columns than header and when the number of lines differs from value in first column on last line.
```
field_weight;file;field_page_name;field_page_number;title
1;3_A3_kn_6_00001;;;
2;3_A3_kn_6_00002;;;
3;3_A3_kn_6_00003;;;
4;3_A3_kn_6_00004;;;
5;3_A3_kn_6_00005;;;
6;3_A3_kn_6_00006;;;
7;3_A3_kn_6_00007;;;
```

## Time to check input configuration

| Number of nodes | time in seconds |
| --------------- | --------------- |
| 499             |         90      |
| 1727            |        292      |
| 2000            |        328      |
