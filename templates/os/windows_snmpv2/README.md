
# Template OS Windows SNMPv2

## Overview

For Zabbix version: 4.4  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

|Name|
|----|
|Template Module Generic SNMPv2|
|Template Module HOST-RESOURCES-MIB SNMPv2|
|Template Module Interfaces Windows SNMPv2|

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: Doesn't support In/Out 64 bit counters even though IfxTable is present:
Currently, Windows gets it’s interface status from MIB-2. Since these 64bit SNMP counters (ifHCInOctets, ifHCOutOctets, etc.) are defined as an extension to IF-MIB, Microsoft has not implemented it.
https://social.technet.microsoft.com/Forums/windowsserver/en-US/07b62ff0-94f6-40ca-a99d-d129c1b33d70/windows-2008-r2-snmp-64bit-counters-support?forum=winservergen

  - Version: Win2008, Win2012R2.

- Description: Doesn't support ifXTable at all
  - Version: WindowsXP

- Description: EtherLike MIB is not supported
  - Version: *
  - Device: *

