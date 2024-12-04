# SMART Plugin

## Overview

The Zabbix SMART (Self-Monitoring, Analysis, and Reporting Technology) plugin for `zabbix_agent2` provides monitoring of storage devices' health and performance. It utilizes the `smartctl` utility to collect and report SMART metrics, helping to identify potential hardware issues before they result in failures.

## Configuration

The SMART plugin can be configured through the `zabbix_agent2` configuration file (e.g., `zabbix_agent2.conf`) or a dedicated plugin configuration file (e.g., `smart.conf`).

### Configuration Parameters

1. **`Plugins.Smart.Path`**
   - **Description:** Specifies the path to the `smartctl` executable.
   - **Mandatory:** No
   - **Default Value:** `smartctl`
   - **Example for Linux:**
     ```plaintext
     Plugins.Smart.Path=/usr/sbin/smartctl
     ```
   - **Example for Windows:**
     ```plaintext
     Plugins.Smart.Path="C:\Program Files\smartctl\smartctl.exe"
     ```
   - Use this parameter to specify the location of the `smartctl` binary.
   - **Note for Windows Users:**  
     On Windows, either provide the full path to the `smartctl` executable (e.g., `C:\Program Files\smartctl\smartctl.exe`) or ensure that the folder containing the `smartctl` executable is added to the system's environment variables (`PATH`).


2. **`Plugins.Smart.Timeout`**
   - **Description:** Defines the maximum time (in seconds) to wait for SMART data requests to complete.
   - **Mandatory:** No
   - **Range:** 1–30 seconds
   - **Default Value:** Inherits the global timeout setting of the agent.
   - **Example:**
     ```plaintext
     Plugins.Smart.Timeout=10
     ```
   - Adjust this setting to match the performance characteristics of your environment.

## SMART Monitoring Items

The plugin provides specific items for monitoring disk health:

### 1. **`smart.disk.discovery`**
- **Description:** Performs low-level discovery of all SMART-capable disks on the system.
- **Key:** `smart.disk.discovery`
- **Output:** Returns a JSON array containing details for each discovered disk, including:
  - `{#NAME}`: Disk name
  - `{#DISKTYPE}`: Disk type (e.g., `nvme`, `ata`)
  - `{#MODEL}`: Disk model
  - `{#SN}`: Serial number
  - `{#PATH}`: Device path
  - `{#RAIDTYPE}`: RAID type, if applicable
  - `{#ATTRIBUTES}`: Disk attributes

### 2. **`smart.attribute.discovery`**
- **Description:** Discovers all SMART attributes for SMART-capable devices. The item returns a JSON array of attributes that provide detailed information about the disk's health, performance, and usage.
- **Key:** `smart.attribute.discovery`
- **Output:** Returns a JSON array where each object represents a SMART attribute with the following fields:
  - **`{#NAME}`**: Name of the disk and protocol type (e.g., `sda sat`).
  - **`{#DISKTYPE}`**: Type of the disk (e.g., `SSD`, `HDD`).
  - **`{#ID}`**: SMART attribute ID (e.g., `5`, `9`, `12`).
  - **`{#ATTRNAME}`**: Name of the SMART attribute (e.g., `Power_On_Hours`, `Reallocated_Sector_Ct`).
  - **`{#THRESH}`**: Threshold value for the attribute, representing the critical limit.

### 3. **`smart.disk.get`**

- **Description:** Retrieves detailed SMART attributes for a specified disk.

- **Key:** `smart.disk.get["<device_path>","<raid_type>"]`
  - `<device_path>`: Path to the disk device (e.g., `/dev/sda`).
  - `<raid_type>`: RAID type, if applicable (e.g., `megaraid,0`); use an empty string if not applicable.

- **Output:** Provides a JSON object containing SMART attributes. The fields included in the output are:
  - **`critical_warning`**: Indicates any critical warnings for the disk (e.g., temperature, spare capacity issues).
  - **`disk_type`**: Type of disk (e.g., `ssd` or `hdd`).
  - **`error`**: Message indicating any errors encountered during data retrieval.
  - **`exit_status`**: Exit status of the SMART data retrieval process (0 for success, non-zero for errors).
  - **`firmware_version`**: Firmware version of the disk.
  - **`media_errors`**: Number of media errors detected.
  - **`model_name`**: Model name of the disk.
  - **`percentage_used`**: Percentage of disk life used, typically for SSDs.
  - **`power_on_time`**: Total power-on time of the disk, in hours.
  - **`self_test_passed`**: Boolean indicating whether the last self-test passed.
  - **`serial_number`**: Serial number of the disk.
  - **`temperature`**: Current temperature of the disk, in Celsius.

- **Note:**  
  Executing `smart.disk.get` without parameters retrieves information for all disks on the system.
