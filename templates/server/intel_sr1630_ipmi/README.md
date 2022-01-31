
# Intel SR1630 IPMI

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |System Fan 2 |<p>-</p> |IPMI |system_fan_2 |
|Fans |System Fan 3 |<p>-</p> |IPMI |system_fan_3 |
|Temperature |Baseboard Temp |<p>-</p> |IPMI |baseboard_temp |
|Temperature |Front Panel Temp |<p>-</p> |IPMI |front_panel_temp |
|Voltage |BB +1.05V PCH |<p>-</p> |IPMI |bb_1.05v_pch |
|Voltage |BB +1.1V P1 Vccp |<p>-</p> |IPMI |bb_1.1v_p1_vccp |
|Voltage |BB +1.5V P1 DDR3 |<p>-</p> |IPMI |bb_1.5v_p1_ddr3 |
|Voltage |BB +3.3V |<p>-</p> |IPMI |bb_3.3v |
|Voltage |BB +3.3V STBY |<p>-</p> |IPMI |bb_3.3v_stby |
|Voltage |BB +5.0V |<p>-</p> |IPMI |bb_5.0v |
|Voltage |Power |<p>-</p> |IPMI |power |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|System Fan 2 Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/system_fan_2)<324` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|System Fan 2 Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/system_fan_2)<378` |HIGH |<p>**Depends on**:</p><p>- Power</p><p>- System Fan 2 Critical [{ITEM.VALUE}]</p> |
|System Fan 3 Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/system_fan_3)<324` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|System Fan 3 Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/system_fan_3)<378` |HIGH |<p>**Depends on**:</p><p>- Power</p><p>- System Fan 3 Critical [{ITEM.VALUE}]</p> |
|Baseboard Temp Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/baseboard_temp)<5 or last(/Intel SR1630 IPMI/baseboard_temp)>90` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|Baseboard Temp Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/baseboard_temp)<10 or last(/Intel SR1630 IPMI/baseboard_temp)>83` |HIGH |<p>**Depends on**:</p><p>- Baseboard Temp Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|Front Panel Temp Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/front_panel_temp)<0 or last(/Intel SR1630 IPMI/front_panel_temp)>48` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|Front Panel Temp Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/front_panel_temp)<5 or last(/Intel SR1630 IPMI/front_panel_temp)>44` |HIGH |<p>**Depends on**:</p><p>- Front Panel Temp Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|BB +1.05V PCH Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_1.05v_pch)<0.953 or last(/Intel SR1630 IPMI/bb_1.05v_pch)>1.149` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +1.05V PCH Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_1.05v_pch)<0.985 or last(/Intel SR1630 IPMI/bb_1.05v_pch)>1.117` |HIGH |<p>**Depends on**:</p><p>- BB +1.05V PCH Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|BB +1.1V P1 Vccp Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)<0.683 or last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)>1.543` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +1.1V P1 Vccp Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)<0.708 or last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)>1.501` |HIGH |<p>**Depends on**:</p><p>- BB +1.1V P1 Vccp Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|BB +1.5V P1 DDR3 Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)<1.362 or last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)>1.635` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +1.5V P1 DDR3 Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)<1.401 or last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)>1.589` |HIGH |<p>**Depends on**:</p><p>- BB +1.5V P1 DDR3 Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|BB +3.3V Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_3.3v)<2.982 or last(/Intel SR1630 IPMI/bb_3.3v)>3.625` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +3.3V Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_3.3v)<3.067 or last(/Intel SR1630 IPMI/bb_3.3v)>3.525` |HIGH |<p>**Depends on**:</p><p>- BB +3.3V Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|BB +3.3V STBY Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_3.3v_stby)<2.982 or last(/Intel SR1630 IPMI/bb_3.3v_stby)>3.625` |DISASTER | |
|BB +3.3V STBY Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_3.3v_stby)<3.067 or last(/Intel SR1630 IPMI/bb_3.3v_stby)>3.525` |HIGH |<p>**Depends on**:</p><p>- BB +3.3V STBY Critical [{ITEM.VALUE}]</p> |
|BB +5.0V Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_5.0v)<4.471 or last(/Intel SR1630 IPMI/bb_5.0v)>5.538` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +5.0V Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1630 IPMI/bb_5.0v)<4.630 or last(/Intel SR1630 IPMI/bb_5.0v)>5.380` |HIGH |<p>**Depends on**:</p><p>- BB +5.0V Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|Power |<p>-</p> |`last(/Intel SR1630 IPMI/power)=0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

