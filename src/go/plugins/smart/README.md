# SMART Plugin

## Overview

The Zabbix SMART plugin for `zabbix_agent2` provides monitoring of storage devices' health and performance. It utilizes the `smartctl` utility to collect and report SMART metrics, helping to identify potential hardware issues before they result in failures.

## Requirements

The following requirements must be met:

- **Smartmontools**: Version 7.1 or later is required to provide the `smartctl` utility for SMART data retrieval.
- **Golang**: Version 1.21 or higher is required only when building the plugin from source.

## Configuration

- The SMART plugin can be configured through the `zabbix_agent2` configuration file (e.g., `zabbix_agent2.conf`) or a dedicated plugin configuration file (e.g., `smart.conf`).

- Grant Zabbix agent 2 super/admin user privileges for smartctl utility.

### Configuration Parameters

#### `Plugins.Smart.Path`
- **Description:** Specifies the path to the `smartctl` executable.
- **Mandatory:** No
- **Default Value:** `smartctl`
- **Example for Linux:**
```
Plugins.Smart.Path=/usr/sbin/smartctl
```
- **Example for Windows:**
```
Plugins.Smart.Path="C:\Program Files\smartctl\smartctl.exe"
```
- **Usage:** Use this parameter to specify the location of the `smartctl` binary.
- **Note:**  
  Ensure the path to the `smartctl` executable is correctly specified. You can either provide the full path to the executable (e.g., `/usr/sbin/smartctl` on Linux or `C:\Program Files\smartctl\smartctl.exe` on Windows) in config file or ensure that the folder containing the `smartctl` executable is added to the system's environment variables (`PATH`). This applies to both Linux and Windows systems.

#### `Plugins.Smart.Timeout`
- **Description:** Defines the maximum time (in seconds) to wait for SMART data requests to complete.
- **Mandatory:** No
- **Range:** 1–30 seconds
- **Default Value:** Inherits the global timeout setting of the agent.
- **Example:**
```
Plugins.Smart.Timeout=10
```

## Monitoring Items

The plugin provides specific items for monitoring disk health.

### `smart.disk.discovery`
- **Description:** Performs low-level discovery of all SMART-capable disks on the system.
- **Key:** `smart.disk.discovery`
- **Output:** Returns a JSON array containing details for each discovered disk, including:
  - `{#NAME}`: Disk name.
  - `{#DISKTYPE}`: Disk type (e.g., `nvme`, `ata`).
  - `{#MODEL}`: Disk model.
  - `{#SN}`: Serial number.
  - `{#PATH}`: Device path.
  - `{#RAIDTYPE}`: RAID type, if applicable.
  - `{#ATTRIBUTES}`: Disk attributes.

### `smart.attribute.discovery`
- **Description:** Discovers all SMART attributes for SMART-capable devices. Returns a JSON array of attributes.
- **Key:** `smart.attribute.discovery`
- **Output:** Returns a JSON array where each object represents a SMART attribute with the following fields:
  - `{#NAME}`: Name of the disk and protocol type (e.g., `sda sat`).
  - `{#DISKTYPE}`: Type of the disk (e.g., `SSD`, `HDD`).
  - `{#ID}`: SMART attribute ID (e.g., `5`, `9`, `12`).
  - `{#ATTRNAME}`: Name of the SMART attribute (e.g., `Power_On_Hours`, `Reallocated_Sector_Ct`).
  - `{#THRESH}`: Threshold value for the attribute, representing the critical limit.

### `smart.disk.get`
- **Description:** Retrieves detailed SMART attributes for a specified disk or all disks if no parameters are provided.
- **Key:** `smart.disk.get["<device_path>","<raid_type>"]`
  - `<device_path>`: Path to the disk device (e.g., `/dev/sda`).
  - `<raid_type>`: RAID type, if applicable (e.g., `megaraid,0`); use an empty string if not applicable.
- **Output:** Provides a JSON object containing SMART attributes. Fields include:
  - `critical_warning`: Indicates any critical warnings for the disk (e.g., temperature, spare capacity issues).
  - `disk_type`: Type of disk (e.g., `ssd` or `hdd`).
  - `error`: Message indicating any errors encountered during data retrieval.
  - `exit_status`: Exit status of the SMART data retrieval process (0 for success, non-zero for errors).
  - `firmware_version`: Firmware version of the disk.
  - `media_errors`: Number of media errors detected.
  - `model_name`: Model name of the disk.
  - `percentage_used`: Percentage of disk life used, typically for SSDs.
  - `power_on_time`: Total power-on time of the disk, in hours.
  - `self_test_passed`: Boolean indicating whether the last self-test passed.
  - `serial_number`: Serial number of the disk.
  - `temperature`: Current temperature of the disk, in Celsius.
- **Note:**  
  Executing `smart.disk.get` without parameters retrieves information for all disks on the system.
