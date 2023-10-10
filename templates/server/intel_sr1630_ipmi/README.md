
# Intel SR1630 IPMI

## Overview

Template for monitoring Intel SR1630 server system.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Intel SR1630 IPMI

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Intel SR1630 IPMI: Baseboard Temp||IPMI agent|baseboard_temp|
|Intel SR1630 IPMI: BB +1.05V PCH||IPMI agent|bb_1.05v_pch|
|Intel SR1630 IPMI: BB +1.1V P1 Vccp||IPMI agent|bb_1.1v_p1_vccp|
|Intel SR1630 IPMI: BB +1.5V P1 DDR3||IPMI agent|bb_1.5v_p1_ddr3|
|Intel SR1630 IPMI: BB +3.3V||IPMI agent|bb_3.3v|
|Intel SR1630 IPMI: BB +3.3V STBY||IPMI agent|bb_3.3v_stby|
|Intel SR1630 IPMI: BB +5.0V||IPMI agent|bb_5.0v|
|Intel SR1630 IPMI: Front Panel Temp||IPMI agent|front_panel_temp|
|Intel SR1630 IPMI: Power||IPMI agent|power|
|Intel SR1630 IPMI: System Fan 2||IPMI agent|system_fan_2|
|Intel SR1630 IPMI: System Fan 3||IPMI agent|system_fan_3|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Intel SR1630 IPMI: Baseboard Temp Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/baseboard_temp)<5 or last(/Intel SR1630 IPMI/baseboard_temp)>90`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: Baseboard Temp Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/baseboard_temp)<10 or last(/Intel SR1630 IPMI/baseboard_temp)>83`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Baseboard Temp Critical [{ITEM.VALUE}]</li><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +1.05V PCH Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_1.05v_pch)<0.953 or last(/Intel SR1630 IPMI/bb_1.05v_pch)>1.149`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +1.05V PCH Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_1.05v_pch)<0.985 or last(/Intel SR1630 IPMI/bb_1.05v_pch)>1.117`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: BB +1.05V PCH Critical [{ITEM.VALUE}]</li><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +1.1V P1 Vccp Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)<0.683 or last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)>1.543`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +1.1V P1 Vccp Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)<0.708 or last(/Intel SR1630 IPMI/bb_1.1v_p1_vccp)>1.501`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: BB +1.1V P1 Vccp Critical [{ITEM.VALUE}]</li><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +1.5V P1 DDR3 Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)<1.362 or last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)>1.635`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +1.5V P1 DDR3 Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)<1.401 or last(/Intel SR1630 IPMI/bb_1.5v_p1_ddr3)>1.589`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: BB +1.5V P1 DDR3 Critical [{ITEM.VALUE}]</li><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +3.3V Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_3.3v)<2.982 or last(/Intel SR1630 IPMI/bb_3.3v)>3.625`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +3.3V Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_3.3v)<3.067 or last(/Intel SR1630 IPMI/bb_3.3v)>3.525`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: BB +3.3V Critical [{ITEM.VALUE}]</li><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +3.3V STBY Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_3.3v_stby)<2.982 or last(/Intel SR1630 IPMI/bb_3.3v_stby)>3.625`|Disaster||
|Intel SR1630 IPMI: BB +3.3V STBY Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_3.3v_stby)<3.067 or last(/Intel SR1630 IPMI/bb_3.3v_stby)>3.525`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: BB +3.3V STBY Critical [{ITEM.VALUE}]</li></ul>|
|Intel SR1630 IPMI: BB +5.0V Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_5.0v)<4.471 or last(/Intel SR1630 IPMI/bb_5.0v)>5.538`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: BB +5.0V Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/bb_5.0v)<4.630 or last(/Intel SR1630 IPMI/bb_5.0v)>5.380`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: BB +5.0V Critical [{ITEM.VALUE}]</li><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: Front Panel Temp Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/front_panel_temp)<0 or last(/Intel SR1630 IPMI/front_panel_temp)>48`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: Front Panel Temp Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/front_panel_temp)<5 or last(/Intel SR1630 IPMI/front_panel_temp)>44`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Front Panel Temp Critical [{ITEM.VALUE}]</li><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: Power||`last(/Intel SR1630 IPMI/power)=0`|Warning||
|Intel SR1630 IPMI: System Fan 2 Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/system_fan_2)<324`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: System Fan 2 Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/system_fan_2)<378`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li><li>Intel SR1630 IPMI: System Fan 2 Critical [{ITEM.VALUE}]</li></ul>|
|Intel SR1630 IPMI: System Fan 3 Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/system_fan_3)<324`|Disaster|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li></ul>|
|Intel SR1630 IPMI: System Fan 3 Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1630 IPMI/system_fan_3)<378`|High|**Depends on**:<br><ul><li>Intel SR1630 IPMI: Power</li><li>Intel SR1630 IPMI: System Fan 3 Critical [{ITEM.VALUE}]</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

