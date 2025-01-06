This module serves to run specified Islandora Workbench scripts from web interface.

## Basic operation
User ID and current node ID are used to fill Author and Member of fields respectively.

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
WARNING: create a key override for this field (using key with a File provider), otherwise the password WILL be written in plaintext after config  export.

### Path to workbench executable
Absolute path to workbench executable.

### Paths to workbench configuration files
List of absolute paths to configuration files, one per line. First one is considered default.

## Block
Block `Ingest items` must be placed.
