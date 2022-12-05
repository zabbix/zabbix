
# SMART by Zabbix agent 2

## Overview

For Zabbix version: 6.2 and higher  
The template for monitoring S.M.A.R.T. attributes of physical disk that works without any external scripts.
It collects metrics by Zabbix agent 2 version 5.0 and later with Smartmontools version 7.1 and later.
Disk discovery LLD rule finds all HDD, SSD, NVMe disks with S.M.A.R.T. enabled. Attribute discovery LLD rule have pre-defined Vendor Specific Attributes
for each disk, and will be discovered if attribute is present.


This template was tested on:

- Smartmontools, version 7.1 and later

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

Install the Zabbix agent 2 and Smartmontools 7.1.
Grant Zabbix agent 2 super/admin user privileges for smartctl utility.

### Linux example:

> sudo dnf install smartmontools
> sudo visudo
>> zabbix ALL=(ALL) NOPASSWD:/usr/sbin/smartctl

Plugin [parameters list](https://www.zabbix.com/documentation/6.2/manual/appendix/config/zabbix_agent2_plugins/smart_plugin)


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SMART.DISK.NAME.MATCHES} |<p>This macro is used in the filter of attribute and disk discoveries. It can be overridden on the host or linked on the template level.</p> |`^.*$` |
|{$SMART.DISK.NAME.NOT_MATCHES} |<p>This macro is used in the filter of attribute and disk discoveries. It can be overridden on the host or linked on the template level.</p> |`CHANGE_IF_NEEDED` |
|{$SMART.TEMPERATURE.MAX.CRIT} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`65` |
|{$SMART.TEMPERATURE.MAX.WARN} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Disk discovery |<p>Discovery SMART disks.</p> |ZABBIX_PASSIVE |smart.disk.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$SMART.DISK.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$SMART.DISK.NAME.NOT_MATCHES}`</p><p>**Overrides:**</p><p>Self-test<br> - {#DISKTYPE} MATCHES_REGEX `nvme`<br>  - ITEM_PROTOTYPE LIKE `Self-test` - NO_DISCOVER</p><p>Not NVMe<br> - {#DISKTYPE} NOT_MATCHES_REGEX `nvme`<br>  - ITEM_PROTOTYPE REGEXP `Media|Percentage|Critical` - NO_DISCOVER</p><p>Raw_Read_Error_Rate<br> - {#ATTRIBUTES} MATCHES_REGEX `Raw_Read_Error_Rate`<br>  - ITEM_PROTOTYPE REGEXP `Raw_Read_Error_Rate` - DISCOVER</p><p>Spin_Up_Time<br> - {#ATTRIBUTES} MATCHES_REGEX `Spin_Up_Time`<br>  - ITEM_PROTOTYPE REGEXP `Spin_Up_Time` - DISCOVER</p><p>Start_Stop_Count<br> - {#ATTRIBUTES} MATCHES_REGEX `Start_Stop_Count`<br>  - ITEM_PROTOTYPE REGEXP `Start_Stop_Count` - DISCOVER</p><p>Power_Cycle_Count<br> - {#ATTRIBUTES} MATCHES_REGEX `Power_Cycle_Count`<br>  - ITEM_PROTOTYPE REGEXP `Power_Cycle_Count` - DISCOVER</p><p>Reported_Uncorrect<br> - {#ATTRIBUTES} MATCHES_REGEX `Reported_Uncorrect`<br>  - ITEM_PROTOTYPE REGEXP `Reported_Uncorrect` - DISCOVER</p><p>Seek_Error_Rate<br> - {#ATTRIBUTES} MATCHES_REGEX `Seek_Error_Rate`<br>  - ITEM_PROTOTYPE REGEXP `Seek_Error_Rate` - DISCOVER</p><p>Bad_Block_Rate<br> - {#ATTRIBUTES} MATCHES_REGEX `Bad_Block_Rate`<br>  - ITEM_PROTOTYPE REGEXP `Bad_Block_Rate` - DISCOVER</p><p>Program_Fail_Count_Chip<br> - {#ATTRIBUTES} MATCHES_REGEX `Program_Fail_Count_Chip`<br>  - ITEM_PROTOTYPE REGEXP `Program_Fail_Count_Chip` - DISCOVER</p><p>Reallocated_Sector_Ct<br> - {#ATTRIBUTES} MATCHES_REGEX `Reallocated_Sector_Ct`<br>  - ITEM_PROTOTYPE REGEXP `Reallocated_Sector_Ct` - DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Zabbix raw items |SMART [{#NAME}]: Smartctl error |<p>This metric will contain smartctl errors.</p> |DEPENDENT |smart.disk.error[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Get disk attributes |<p>-</p> |ZABBIX_PASSIVE |smart.disk.get[{#PATH},"{#RAIDTYPE}"] |
|Zabbix raw items |SMART [{#NAME}]: Device model |<p>-</p> |DEPENDENT |smart.disk.model[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.model_name`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Serial number |<p>-</p> |DEPENDENT |smart.disk.sn[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.serial_number`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Self-test passed |<p>The disk is passed the SMART self-test or not.</p> |DEPENDENT |smart.disk.test[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.self_test_passed`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Temperature |<p>Current drive temperature.</p> |DEPENDENT |smart.disk.temperature[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.temperature`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Power on hours |<p>Count of hours in power-on state. The raw value of this attribute shows total count of hours (or minutes, or seconds, depending on manufacturer) in power-on state. "By default, the total expected lifetime of a hard disk in perfect condition is defined as 5 years (running every day and night on all days). This is equal to 1825 days in 24/7 mode or 43800 hours." On some pre-2005 drives, this raw value may advance erratically and/or "wrap around" (reset to zero periodically). https://en.wikipedia.org/wiki/S.M.A.R.T.#Known_ATA_S.M.A.R.T._attributes</p> |DEPENDENT |smart.disk.hours[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.power_on_time`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Percentage used |<p>Contains a vendor specific estimate of the percentage of NVM subsystem life used based on the actual usage and the manufacturer's prediction of NVM life. A value of 100 indicates that the estimated endurance of the NVM in the NVM subsystem has been consumed, but may not indicate an NVM subsystem failure. The value is allowed to exceed 100. Percentages greater than 254 shall be represented as 255. This value shall be updated once per power-on hour (when the controller is not in a sleep state).</p> |DEPENDENT |smart.disk.percentage_used[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.percentage_used`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Critical warning |<p>This field indicates critical warnings for the state of the controller.</p> |DEPENDENT |smart.disk.critical_warning[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.critical_warning`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Media errors |<p>Contains the number of occurrences where the controller detected an unrecovered data integrity error. Errors such as uncorrectable ECC, CRC checksum failure, or LBA tag mismatch are included in this field.</p> |DEPENDENT |smart.disk.media_errors[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.media_errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Exit status |<p>The exit statuses of smartctl are defined by a bitmask but in decimal value. The eight different bits in the exit status have the following  meanings  for  ATA disks; some of these values may also be returned for SCSI disks.</p><p>Bit 0: Command line did not parse.</p><p>Bit 1: Device  open failed, device did not return an IDENTIFY DEVICE structure, or device is in a low-power mode (see '-n' option above).</p><p>Bit 2: Some SMART or other ATA command to the disk failed, or there was a checksum error in a SMART data  structure  (see '-b' option above).</p><p>Bit 3: SMART status check returned "DISK FAILING".</p><p>Bit 4: We found prefail Attributes <= threshold.</p><p>Bit 5: SMART  status  check returned "DISK OK" but we found that some (usage or prefail) Attributes have been <= threshold at some time in the past.</p><p>Bit 6: The device error log contains records of errors.</p><p>Bit 7: The device self-test log contains records of errors. [ATA only] Failed self-tests outdated by a newer successful extended self-test are ignored.</p> |DEPENDENT |smart.disk.es[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.exit_status`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Raw_Read_Error_Rate |<p>Stores data related to the rate of hardware read errors that occurred when reading data from a disk surface. The raw value has different structure for different vendors and is often not meaningful as a decimal number. For some drives, this number may increase during normal operation without necessarily signifying errors.</p> |DEPENDENT |smart.disk.attribute.raw_read_error_rate[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.raw_read_error_rate.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Spin_Up_Time |<p>Average time of spindle spin up (from zero RPM to fully operational [milliseconds]).</p> |DEPENDENT |smart.disk.attribute.spin_up_time[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.spin_up_time.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Start_Stop_Count |<p>A tally of spindle start/stop cycles. The spindle turns on, and hence the count is increased, both when the hard disk is turned on after having before been turned entirely off (disconnected from power source) and when the hard disk returns from having previously been put to sleep mode.</p> |DEPENDENT |smart.disk.attribute.start_stop_count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.start_stop_count.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Power_Cycle_Count |<p>This attribute indicates the count of full hard disk power on/off cycles.</p> |DEPENDENT |smart.disk.attribute.power_cycle_count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.power_cycle_count.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Reported_Uncorrect |<p>The count of errors that could not be recovered using hardware ECC.</p> |DEPENDENT |smart.disk.attribute.reported_uncorrect[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.reported_uncorrect.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Seek_Error_Rate |<p>Rate of seek errors of the magnetic heads. If there is a partial failure in the mechanical positioning system, then seek errors will arise. Such a failure may be due to numerous factors, such as damage to a servo, or thermal widening of the hard disk. The raw value has different structure for different vendors and is often not meaningful as a decimal number. For some drives, this number may increase during normal operation without necessarily signifying errors.</p> |DEPENDENT |smart.disk.attribute.seek_error_rate[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.seek_error_rate.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Bad_Block_Rate |<p>Percentage of used reserve blocks divided by total reserve blocks.</p> |DEPENDENT |smart.disk.attribute.bad_block_rate[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.bad_block_rate.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Program_Fail_Count_Chip |<p>The total number of flash program operation failures since the drive was deployed.</p> |DEPENDENT |smart.disk.attribute.program_fail_count_chip[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.program_fail_count_chip.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Reallocated_Sector_Ct |<p>Disk discovered attribute.</p> |DEPENDENT |smart.disk.attribute.reallocated_sector_ct[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.reallocated_sector_ct.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|SMART [{#NAME}]: Disk has been replaced |<p>Device serial number has changed. Ack to close.</p> |`last(/SMART by Zabbix agent 2/smart.disk.sn[{#NAME}],#1)<>last(/SMART by Zabbix agent 2/smart.disk.sn[{#NAME}],#2) and length(last(/SMART by Zabbix agent 2/smart.disk.sn[{#NAME}]))>0` |INFO |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Disk self-test is not passed |<p>-</p> |`last(/SMART by Zabbix agent 2/smart.disk.test[{#NAME}])="false"` |HIGH | |
|SMART [{#NAME}]: Average disk temperature is too high |<p>-</p> |`avg(/SMART by Zabbix agent 2/smart.disk.temperature[{#NAME}],5m)>{$SMART.TEMPERATURE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- SMART [{#NAME}]: Average disk temperature is critical</p> |
|SMART [{#NAME}]: Average disk temperature is critical |<p>-</p> |`avg(/SMART by Zabbix agent 2/smart.disk.temperature[{#NAME}],5m)>{$SMART.TEMPERATURE.MAX.CRIT}` |AVERAGE | |
|SMART [{#NAME}]: NVMe disk percentage using is over 90% of estimated endurance |<p>-</p> |`last(/SMART by Zabbix agent 2/smart.disk.percentage_used[{#NAME}])>90` |AVERAGE | |
|SMART [{#NAME}]: Command line did not parse |<p>Command line did not parse.</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),1) = 1 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),1) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),1) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),1) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Device open failed |<p>Device open failed, device did not return an IDENTIFY DEVICE structure, or device is in a low-power mode.</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),2) = 2 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),2) = 2 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),2) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),2) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Some command to the disk failed |<p>Some SMART or other ATA command to the disk failed, or there was a checksum error in a SMART data structure.</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),4) = 4 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),4) = 4 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),4) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),4) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Check returned "DISK FAILING" |<p>SMART status check returned "DISK FAILING".</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),8) = 8 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),8) = 8 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),8) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),8) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Some prefail Attributes <= threshold |<p>We found prefail Attributes <= threshold.</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),16) = 16 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),16) = 16 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),16) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),16) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Some Attributes have been <= threshold |<p>SMART status check returned "DISK OK" but we found that some (usage or prefail) Attributes have been <= threshold at some time in the past.</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),32) = 32 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),32) = 32 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),32) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),32) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Error log contains records |<p>The device error log contains records of errors.</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),64) = 64 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),64) = 64 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),64) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),64) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Self-test log contains records |<p>The device self-test log contains records of errors. [ATA only] Failed self-tests outdated by a newer successful extended self-test are ignored.</p> |`( count(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),128) = 128 ) or ( bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),128) = 128 and bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}]),128) > bitand(last(/SMART by Zabbix agent 2/smart.disk.es[{#NAME}],#2),128) )` |HIGH |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/415662-discussion-thread-for-official-zabbix-smart-disk-monitoring).


## References

https://www.smartmontools.org/
