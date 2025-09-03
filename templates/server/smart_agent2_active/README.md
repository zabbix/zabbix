
# SMART by Zabbix agent 2 active

## Overview

This template is designed for the effortless deployment of SMART monitoring by Zabbix via Zabbix agent 2 active and doesn't require any external scripts.

It collects metrics by Zabbix agent 2 version 5.0 and later with Smartmontools version 7.1 and later.
Disk discovery LLD rule finds all HDD, SSD, NVMe disks with S.M.A.R.T. enabled. Attribute discovery LLD rule have pre-defined Vendor Specific Attributes for each disk, and will be discovered if attribute is present.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Smartmontools 7.1 and later

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Install Zabbix agent 2 and Smartmontools 7.1 or newer.

2. Ensure the path to the `smartctl` executable is correctly specified. You can either provide the full path to the executable (e.g., `/usr/sbin/smartctl` on Linux or `C:\Program Files\smartctl\smartctl.exe` on Windows) in the configuration file or ensure that the folder containing the `smartctl` executable is added to the system's environment variables (`PATH`). This applies to both Linux and Windows systems.

Example for Linux:

`Plugins.Smart.Path=/usr/sbin/smartctl`

Example for Windows:

`Plugins.Smart.Path="C:\Program Files\smartctl\smartctl.exe"`

3. Grant Zabbix agent 2 super/admin user privileges for the `smartctl` utility (not required for Windows). Example for Linux (add the line that grants execution of the `smartctl` utility without the password):

- Run the `visudo` command to edit the `sudoers` file:

`sudo visudo`

- Add the permission line and save the changes:

`zabbix ALL=(ALL) NOPASSWD:/usr/sbin/smartctl`

Plugin [parameters list](https://www.zabbix.com/documentation/8.0/manual/appendix/config/zabbix_agent2_plugins/smart_plugin).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SMART.DISK.DISCOVERY.TYPE}|<p>This macro is responsible for changing how SMART disks get discovered. Only "name" or "id" values allowed.</p>|`name`|
|{$SMART.TEMPERATURE.MAX.WARN}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`50`|
|{$SMART.TEMPERATURE.MAX.CRIT}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`65`|
|{$SMART.DISK.NAME.MATCHES}|<p>This macro is used in the filter of attribute and disk discoveries. It can be overridden on the host or linked on the template level.</p>|`^.*$`|
|{$SMART.DISK.NAME.NOT_MATCHES}|<p>This macro is used in the filter of attribute and disk discoveries. It can be overridden on the host or linked on the template level.</p>|`CHANGE_IF_NEEDED`|

### LLD rule Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk discovery|<p>Discovery SMART disks.</p>|Zabbix agent (active)|smart.disk.discovery[{$SMART.DISK.DISCOVERY.TYPE}]|

### Item prototypes for Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#NAME}]: Smartctl error|<p>This metric will contain smartctl errors.</p>|Dependent item|smart.disk.error[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#NAME}]: Get disk attributes||Zabbix agent (active)|smart.disk.get[{#PATH},"{#RAIDTYPE}"]|
|[{#NAME}]: Device model||Dependent item|smart.disk.model[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model_name`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Serial number||Dependent item|smart.disk.sn[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serial_number`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Self-test passed|<p>The disk is passed the SMART self-test or not.</p>|Dependent item|smart.disk.test[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.self_test_passed`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Self-test in progress|<p>Reports if disk currently is executing SMART self-test.</p>|Dependent item|smart.disk.test.progress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.self_test_in_progress`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Temperature|<p>Current drive temperature.</p>|Dependent item|smart.disk.temperature[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temperature`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Power on hours|<p>Count of hours in power-on state. The raw value of this attribute</p><p>shows total count of hours (or minutes, or seconds, depending on manufacturer)</p><p>in power-on state. "By default, the total expected lifetime of a hard disk</p><p>in perfect condition is defined as 5 years (running every day and night on</p><p>all days). This is equal to 1825 days in 24/7 mode or 43800 hours." On some</p><p>pre-2005 drives, this raw value may advance erratically and/or "wrap around"</p><p>(reset to zero periodically). https://en.wikipedia.org/wiki/S.M.A.R.T.#Known_ATA_S.M.A.R.T._attributes</p>|Dependent item|smart.disk.hours[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.power_on_time`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Percentage used|<p>Contains a vendor specific estimate of the percentage of NVM subsystem</p><p>life used based on the actual usage and the manufacturer's prediction of NVM</p><p>life. A value of 100 indicates that the estimated endurance of the NVM in</p><p>the NVM subsystem has been consumed, but may not indicate an NVM subsystem</p><p>failure. The value is allowed to exceed 100. Percentages greater than 254</p><p>shall be represented as 255. This value shall be updated once per power-on</p><p>hour (when the controller is not in a sleep state).</p>|Dependent item|smart.disk.percentage_used[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.percentage_used`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Critical warning|<p>This field indicates critical warnings for the state of the controller.</p>|Dependent item|smart.disk.critical_warning[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.critical_warning`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Media errors|<p>Contains the number of occurrences where the controller detected</p><p>an unrecovered data integrity error. Errors such as uncorrectable ECC, CRC</p><p>checksum failure, or LBA tag mismatch are included in this field.</p>|Dependent item|smart.disk.media_errors[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.media_errors`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Exit status|<p>The exit statuses of smartctl are defined by a bitmask but in decimal value. The eight different bits in the exit status have the following  meanings  for  ATA disks; some of these values may also be returned for SCSI disks.</p><p>Bit 0: Command line did not parse.</p><p>Bit 1: Device  open failed, device did not return an IDENTIFY DEVICE structure, or device is in a low-power mode (see '-n' option above).</p><p>Bit 2: Some SMART or other ATA command to the disk failed, or there was a checksum error in a SMART data  structure  (see '-b' option above).</p><p>Bit 3: SMART status check returned "DISK FAILING".</p><p>Bit 4: We found prefail Attributes <= threshold.</p><p>Bit 5: SMART  status  check returned "DISK OK" but we found that some (usage or prefail) Attributes have been <= threshold at some time in the past.</p><p>Bit 6: The device error log contains records of errors.</p><p>Bit 7: The device self-test log contains records of errors. [ATA only] Failed self-tests outdated by a newer successful extended self-test are ignored.</p>|Dependent item|smart.disk.es[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.exit_status`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Raw_Read_Error_Rate|<p>Stores data related to the rate of hardware read errors that occurred when reading data from a disk surface. The raw value has different structure for different vendors and is often not meaningful as a decimal number. For some drives, this number may increase during normal operation without necessarily signifying errors.</p>|Dependent item|smart.disk.attribute.raw_read_error_rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.raw_read_error_rate.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Spin_Up_Time|<p>Average time of spindle spin up (from zero RPM to fully operational [milliseconds]).</p>|Dependent item|smart.disk.attribute.spin_up_time[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.spin_up_time.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Start_Stop_Count|<p>A tally of spindle start/stop cycles. The spindle turns on, and hence the count is increased, both when the hard disk is turned on after having before been turned entirely off (disconnected from power source) and when the hard disk returns from having previously been put to sleep mode.</p>|Dependent item|smart.disk.attribute.start_stop_count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.start_stop_count.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Power_Cycle_Count|<p>This attribute indicates the count of full hard disk power on/off cycles.</p>|Dependent item|smart.disk.attribute.power_cycle_count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.power_cycle_count.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Reported_Uncorrect|<p>The count of errors that could not be recovered using hardware ECC.</p>|Dependent item|smart.disk.attribute.reported_uncorrect[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reported_uncorrect.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Seek_Error_Rate|<p>Rate of seek errors of the magnetic heads. If there is a partial failure in the mechanical positioning system, then seek errors will arise. Such a failure may be due to numerous factors, such as damage to a servo, or thermal widening of the hard disk. The raw value has different structure for different vendors and is often not meaningful as a decimal number. For some drives, this number may increase during normal operation without necessarily signifying errors.</p>|Dependent item|smart.disk.attribute.seek_error_rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.seek_error_rate.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Bad_Block_Rate|<p>Percentage of used reserve blocks divided by total reserve blocks.</p>|Dependent item|smart.disk.attribute.bad_block_rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bad_block_rate.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Program_Fail_Count_Chip|<p>The total number of flash program operation failures since the drive was deployed.</p>|Dependent item|smart.disk.attribute.program_fail_count_chip[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.program_fail_count_chip.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#NAME}]: Reallocated_Sector_Ct|<p>Disk discovered attribute.</p>|Dependent item|smart.disk.attribute.reallocated_sector_ct[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reallocated_sector_ct.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SMART: [{#NAME}]: Disk has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/SMART by Zabbix agent 2 active/smart.disk.sn[{#NAME}],#1)<>last(/SMART by Zabbix agent 2 active/smart.disk.sn[{#NAME}],#2) and length(last(/SMART by Zabbix agent 2 active/smart.disk.sn[{#NAME}]))>0`|Info|**Manual close**: Yes|
|SMART: [{#NAME}]: Disk self-test is not passed||`last(/SMART by Zabbix agent 2 active/smart.disk.test[{#NAME}])=2 and last(/SMART by Zabbix agent 2 active/smart.disk.test.progress[{#NAME}])=2`|High||
|SMART: [{#NAME}]: Average disk temperature is too high||`avg(/SMART by Zabbix agent 2 active/smart.disk.temperature[{#NAME}],5m)>{$SMART.TEMPERATURE.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>SMART: [{#NAME}]: Average disk temperature is critical</li></ul>|
|SMART: [{#NAME}]: Average disk temperature is critical||`avg(/SMART by Zabbix agent 2 active/smart.disk.temperature[{#NAME}],5m)>{$SMART.TEMPERATURE.MAX.CRIT}`|Average||
|SMART: [{#NAME}]: NVMe disk percentage using is over 90% of estimated endurance||`last(/SMART by Zabbix agent 2 active/smart.disk.percentage_used[{#NAME}])>90`|Average||
|SMART: [{#NAME}]: Command line did not parse|<p>Command line did not parse.</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),1) = 1 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),1) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),1) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),1) )`|High|**Manual close**: Yes|
|SMART: [{#NAME}]: Device open failed|<p>Device open failed, device did not return an IDENTIFY DEVICE structure, or device is in a low-power mode.</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),2) = 2 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),2) = 2 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),2) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),2) )`|High|**Manual close**: Yes|
|SMART: [{#NAME}]: Some command to the disk failed|<p>Some SMART or other ATA command to the disk failed,<br>or there was a checksum error in a SMART data structure.</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),4) = 4 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),4) = 4 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),4) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),4) )`|High|**Manual close**: Yes|
|SMART: [{#NAME}]: Check returned "DISK FAILING"|<p>SMART status check returned "DISK FAILING".</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),8) = 8 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),8) = 8 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),8) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),8) )`|High|**Manual close**: Yes|
|SMART: [{#NAME}]: Some prefail Attributes <= threshold|<p>We found prefail Attributes <= threshold.</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),16) = 16 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),16) = 16 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),16) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),16) )`|High|**Manual close**: Yes|
|SMART: [{#NAME}]: Some Attributes have been <= threshold|<p>SMART status check returned "DISK OK" but we found that some (usage<br>or prefail) Attributes have been <= threshold at some time in the past.</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),32) = 32 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),32) = 32 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),32) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),32) )`|High|**Manual close**: Yes|
|SMART: [{#NAME}]: Error log contains records|<p>The device error log contains records of errors.</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),64) = 64 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),64) = 64 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),64) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),64) )`|High|**Manual close**: Yes|
|SMART: [{#NAME}]: Self-test log contains records|<p>The device self-test log contains records of errors. [ATA only]<br>Failed self-tests outdated by a newer successful extended self-test are ignored.</p>|`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),128) = 128 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),128) = 128 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),128) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),128) )`|High|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

