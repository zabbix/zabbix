
# SMART by Zabbix agent 2 active

## Overview

For Zabbix version: 6.0 and higher  
The template for monitoring S.M.A.R.T. attributes of physical disk that works without any external scripts.
It collects metrics by Zabbix agent 2 version 5.0 and later with Smartmontools version 7.1 and later.
Disk discovery LLD rule finds all HDD, SSD, NVMe disks with S.M.A.R.T. enabled. Attribute discovery LLD rule finds all Vendor Specific Attributes
for each disk. If you want to skip some attributes, please set regular expressions with disk names in {$SMART.DISK.NAME.MATCHES}
and with attribute IDs in {$SMART.ATTRIBUTE.ID.MATCHES} macros on the host level.


This template was tested on:

- Smartmontools, version 7.1 and later

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

Install the Zabbix agent 2 and Smartmontools 7.1.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SMART.ATTRIBUTE.ID.MATCHES} |<p>This macro is used in the filter of attribute discovery for filtering IDs. It can be overridden on the host or linked on the template level.</p> |`^.*$` |
|{$SMART.ATTRIBUTE.ID.NOT_MATCHES} |<p>This macro is used in the filter of attribute discovery for filtering IDs. It can be overridden on the host or linked on the template level.</p> |`CHANGE_IF_NEEDED` |
|{$SMART.DISK.NAME.MATCHES} |<p>This macro is used in the filter of attribute and disk discoveries. It can be overridden on the host or linked on the template level.</p> |`^.*$` |
|{$SMART.DISK.NAME.NOT_MATCHES} |<p>This macro is used in the filter of attribute and disk discoveries. It can be overridden on the host or linked on the template level.</p> |`CHANGE_IF_NEEDED` |
|{$SMART.RAW.ATTRIBUTE.ID.MATCHES} |<p>This macro is used for the creation of the integer items instead of the text ones and also triggers for some raw values.</p> |`^(?:5|187|188|196|197|198|199)$` |
|{$SMART.RAW.ATTRIBUTE.MAX.CRIT} |<p>The threshold for raw values.</p> |`0` |
|{$SMART.TEMPERATURE.MAX.CRIT} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`65` |
|{$SMART.TEMPERATURE.MAX.WARN} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Attribute discovery |<p>Discovery SMART Vendor Specific Attributes of disks.</p> |ZABBIX_ACTIVE |smart.attribute.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$SMART.DISK.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$SMART.DISK.NAME.NOT_MATCHES}`</p><p>- {#ID} MATCHES_REGEX `{$SMART.ATTRIBUTE.ID.MATCHES}`</p><p>- {#ID} NOT_MATCHES_REGEX `{$SMART.ATTRIBUTE.ID.NOT_MATCHES}`</p><p>**Overrides:**</p><p>ID filter for Raw trigger<br> - {#ID} NOT_MATCHES_REGEX `{$SMART.RAW.ATTRIBUTE.ID.MATCHES}`<br>  - TRIGGER_PROTOTYPE LIKE `Attribute raw value` - NO_DISCOVER</p> |
|Disk discovery |<p>Discovery SMART disks.</p> |ZABBIX_ACTIVE |smart.disk.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$SMART.DISK.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$SMART.DISK.NAME.NOT_MATCHES}`</p><p>**Overrides:**</p><p>Self-test<br> - {#DISKTYPE} MATCHES_REGEX `nvme`<br>  - ITEM_PROTOTYPE LIKE `Self-test` - NO_DISCOVER</p><p>Not NVMe<br> - {#DISKTYPE} NOT_MATCHES_REGEX `nvme`<br>  - ITEM_PROTOTYPE REGEXP `Media|Percentage|Critical` - NO_DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Zabbix raw items |SMART: Get attributes |<p>-</p> |ZABBIX_ACTIVE |smart.disk.get |
|Zabbix raw items |SMART [{#NAME}]: Device model |<p>-</p> |DEPENDENT |smart.disk.model[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].model_name.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Serial number |<p>-</p> |DEPENDENT |smart.disk.sn[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].serial_number.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Self-test passed |<p>The disk is passed the SMART self-test or not.</p> |DEPENDENT |smart.disk.test[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].ata_smart_data.self_test.status.passed.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Temperature |<p>Current drive temperature.</p> |DEPENDENT |smart.disk.temperature[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].temperature.current.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Power on hours |<p>Count of hours in power-on state. The raw value of this attribute shows total count of hours (or minutes, or seconds, depending on manufacturer) in power-on state. "By default, the total expected lifetime of a hard disk in perfect condition is defined as 5 years (running every day and night on all days). This is equal to 1825 days in 24/7 mode or 43800 hours." On some pre-2005 drives, this raw value may advance erratically and/or "wrap around" (reset to zero periodically). https://en.wikipedia.org/wiki/S.M.A.R.T.#Known_ATA_S.M.A.R.T._attributes</p> |DEPENDENT |smart.disk.hours[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].power_on_time.hours.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Percentage used |<p>Contains a vendor specific estimate of the percentage of NVM subsystem life used based on the actual usage and the manufacturer's prediction of NVM life. A value of 100 indicates that the estimated endurance of the NVM in the NVM subsystem has been consumed, but may not indicate an NVM subsystem failure. The value is allowed to exceed 100. Percentages greater than 254 shall be represented as 255. This value shall be updated once per power-on hour (when the controller is not in a sleep state).</p> |DEPENDENT |smart.disk.percentage_used[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].nvme_smart_health_information_log.percentage_used.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Critical warning |<p>This field indicates critical warnings for the state of the controller.</p> |DEPENDENT |smart.disk.critical_warning[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].nvme_smart_health_information_log.critical_warning.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Media errors |<p>Contains the number of occurrences where the controller detected an unrecovered data integrity error. Errors such as uncorrectable ECC, CRC checksum failure, or LBA tag mismatch are included in this field.</p> |DEPENDENT |smart.disk.media_errors[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].nvme_smart_health_information_log.media_errors.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: Exit status |<p>The exit statuses of smartctl are defined by a bitmask but in decimal value. The eight different bits in the exit status have the following  meanings  for  ATA disks; some of these values may also be returned for SCSI disks.</p><p>Bit 0: Command line did not parse.</p><p>Bit 1: Device  open failed, device did not return an IDENTIFY DEVICE structure, or device is in a low-power mode (see '-n' option above).</p><p>Bit 2: Some SMART or other ATA command to the disk failed, or there was a checksum error in a SMART data  structure  (see '-b' option above).</p><p>Bit 3: SMART status check returned "DISK FAILING".</p><p>Bit 4: We found prefail Attributes <= threshold.</p><p>Bit 5: SMART  status  check returned "DISK OK" but we found that some (usage or prefail) Attributes have been <= threshold at some time in the past.</p><p>Bit 6: The device error log contains records of errors.</p><p>Bit 7: The device self-test log contains records of errors. [ATA only] Failed self-tests outdated by a newer successful extended self-test are ignored.</p> |DEPENDENT |smart.disk.es[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].smartctl.exit_status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: ID {#ID} {#ATTRNAME} |<p>-</p> |DEPENDENT |smart.disk.error[{#NAME},{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].ata_smart_attributes.table[?(@.id=={#ID})].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |SMART [{#NAME}]: ID {#ID} {#ATTRNAME} raw value |<p>-</p> |DEPENDENT |smart.disk.attr.raw[{#NAME},{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.disk_name=='{#NAME}')].ata_smart_attributes.table[?(@.id=={#ID})].raw.string.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|SMART [{#NAME}]: Disk has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close.</p> |`last(/SMART by Zabbix agent 2 active/smart.disk.sn[{#NAME}],#1)<>last(/SMART by Zabbix agent 2 active/smart.disk.sn[{#NAME}],#2) and length(last(/SMART by Zabbix agent 2 active/smart.disk.sn[{#NAME}]))>0` |INFO |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Disk self-test is not passed |<p>-</p> |`last(/SMART by Zabbix agent 2 active/smart.disk.test[{#NAME}])="false"` |HIGH | |
|SMART [{#NAME}]: Average disk temperature is too high (over {$SMART.TEMPERATURE.MAX.WARN}°C for 5m) |<p>-</p> |`avg(/SMART by Zabbix agent 2 active/smart.disk.temperature[{#NAME}],5m)>{$SMART.TEMPERATURE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- SMART [{#NAME}]: Average disk temperature is critical (over {$SMART.TEMPERATURE.MAX.CRIT}°C for 5m)</p> |
|SMART [{#NAME}]: Average disk temperature is critical (over {$SMART.TEMPERATURE.MAX.CRIT}°C for 5m) |<p>-</p> |`avg(/SMART by Zabbix agent 2 active/smart.disk.temperature[{#NAME}],5m)>{$SMART.TEMPERATURE.MAX.CRIT}` |AVERAGE | |
|SMART [{#NAME}]: NVMe disk percentage using is over 90% of estimated endurance |<p>-</p> |`last(/SMART by Zabbix agent 2 active/smart.disk.percentage_used[{#NAME}])>90` |AVERAGE | |
|SMART [{#NAME}]: Command line did not parse |<p>Command line did not parse.</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),1) = 1 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),1) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),1) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),1) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Device open failed |<p>Device open failed, device did not return an IDENTIFY DEVICE structure, or device is in a low-power mode.</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),2) = 2 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),2) = 2 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),2) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),2) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Some command to the disk failed |<p>Some SMART or other ATA command to the disk failed, or there was a checksum error in a SMART data structure.</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),4) = 4 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),4) = 4 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),4) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),4) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Check returned "DISK FAILING" |<p>SMART status check returned "DISK FAILING".</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),8) = 8 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),8) = 8 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),8) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),8) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Some prefail Attributes <= threshold |<p>We found prefail Attributes <= threshold.</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),16) = 16 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),16) = 16 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),16) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),16) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Some Attributes have been <= threshold |<p>SMART status check returned "DISK OK" but we found that some (usage or prefail) Attributes have been <= threshold at some time in the past.</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),32) = 32 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),32) = 32 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),32) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),32) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Error log contains records |<p>The device error log contains records of errors.</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),64) = 64 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),64) = 64 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),64) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),64) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Self-test log contains records |<p>The device self-test log contains records of errors. [ATA only] Failed self-tests outdated by a newer successful extended self-test are ignored.</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2) = 1 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),128) = 128 ) or ( bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),128) = 128 and bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}]),128) > bitand(last(/SMART by Zabbix agent 2 active/smart.disk.es[{#NAME}],#2),128) )` |HIGH |<p>Manual close: YES</p> |
|SMART [{#NAME}]: Attribute {#ID} {#ATTRNAME} is failed |<p>The value should be greater than THRESH.</p> |`last(/SMART by Zabbix agent 2 active/smart.disk.error[{#NAME},{#ID}]) <= {#THRESH}` |WARNING | |
|SMART [{#NAME}]: Attribute raw value {#ID} {#ATTRNAME} is greater than {$SMART.RAW.ATTRIBUTE.MAX.CRIT:"{#ID}"} |<p>The value should not be more {$SMART.RAW.ATTRIBUTE.MAX.CRIT:"{#ID}"}</p> |`( count(/SMART by Zabbix agent 2 active/smart.disk.attr.raw[{#NAME},{#ID}],#2) = 1 and last(/SMART by Zabbix agent 2 active/smart.disk.attr.raw[{#NAME},{#ID}]) > {$SMART.RAW.ATTRIBUTE.MAX.CRIT:"{#ID}"} ) or ( last(/SMART by Zabbix agent 2 active/smart.disk.attr.raw[{#NAME},{#ID}]) > {$SMART.RAW.ATTRIBUTE.MAX.CRIT:"{#ID}"} and last(/SMART by Zabbix agent 2 active/smart.disk.attr.raw[{#NAME},{#ID}]) > last(/SMART by Zabbix agent 2 active/smart.disk.attr.raw[{#NAME},{#ID}],#2) )` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/415662-discussion-thread-for-official-zabbix-smart-disk-monitoring).


## References

https://www.smartmontools.org/
