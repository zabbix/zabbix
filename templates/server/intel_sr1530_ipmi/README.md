
# Intel SR1530 IPMI

## Overview

Template for monitoring Intel SR1530 server system.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Intel SR1530 IPMI

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Intel SR1530 IPMI: BB +1.8V SM||IPMI agent|bb_1.8v_sm|
|Intel SR1530 IPMI: BB +3.3V||IPMI agent|bb_3.3v|
|Intel SR1530 IPMI: BB +3.3V STBY||IPMI agent|bb_3.3v_stby|
|Intel SR1530 IPMI: BB +5.0V||IPMI agent|bb_5.0v|
|Intel SR1530 IPMI: BB Ambient Temp||IPMI agent|bb_ambient_temp|
|Intel SR1530 IPMI: Power||IPMI agent|power|
|Intel SR1530 IPMI: Processor Vcc||IPMI agent|processor_vcc|
|Intel SR1530 IPMI: System Fan 3||IPMI agent|system_fan_3|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Intel SR1530 IPMI: BB +1.8V SM Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_1.8v_sm)<1.597 or last(/Intel SR1530 IPMI/bb_1.8v_sm)>2.019`|Disaster|**Depends on**:<br><ul><li>Intel SR1530 IPMI: Power</li></ul>|
|Intel SR1530 IPMI: BB +1.8V SM Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_1.8v_sm)<1.646 or last(/Intel SR1530 IPMI/bb_1.8v_sm)>1.960`|High|**Depends on**:<br><ul><li>Intel SR1530 IPMI: BB +1.8V SM Critical [{ITEM.VALUE}]</li><li>Intel SR1530 IPMI: Power</li></ul>|
|Intel SR1530 IPMI: BB +3.3V Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_3.3v)<2.876 or last(/Intel SR1530 IPMI/bb_3.3v)>3.729`|Disaster|**Depends on**:<br><ul><li>Intel SR1530 IPMI: Power</li></ul>|
|Intel SR1530 IPMI: BB +3.3V Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_3.3v)<2.970 or last(/Intel SR1530 IPMI/bb_3.3v)>3.618`|High|**Depends on**:<br><ul><li>Intel SR1530 IPMI: BB +3.3V Critical [{ITEM.VALUE}]</li><li>Intel SR1530 IPMI: Power</li></ul>|
|Intel SR1530 IPMI: BB +3.3V STBY Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_3.3v_stby)<2.876 or last(/Intel SR1530 IPMI/bb_3.3v_stby)>3.729`|Disaster||
|Intel SR1530 IPMI: BB +3.3V STBY Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_3.3v_stby)<2.970 or last(/Intel SR1530 IPMI/bb_3.3v_stby)>3.618`|High|**Depends on**:<br><ul><li>Intel SR1530 IPMI: BB +3.3V STBY Critical [{ITEM.VALUE}]</li></ul>|
|Intel SR1530 IPMI: BB +5.0V Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_5.0v)<4.362 or last(/Intel SR1530 IPMI/bb_5.0v)>5.663`|Disaster|**Depends on**:<br><ul><li>Intel SR1530 IPMI: Power</li></ul>|
|Intel SR1530 IPMI: BB +5.0V Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_5.0v)<4.483 or last(/Intel SR1530 IPMI/bb_5.0v)>5.495`|High|**Depends on**:<br><ul><li>Intel SR1530 IPMI: BB +5.0V Critical [{ITEM.VALUE}]</li><li>Intel SR1530 IPMI: Power</li></ul>|
|Intel SR1530 IPMI: BB Ambient Temp Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_ambient_temp)<5 or last(/Intel SR1530 IPMI/bb_ambient_temp)>66`|Disaster||
|Intel SR1530 IPMI: BB Ambient Temp Non-Critical [{ITEM.VALUE}]||`last(/Intel SR1530 IPMI/bb_ambient_temp)<10 or last(/Intel SR1530 IPMI/bb_ambient_temp)>61`|High|**Depends on**:<br><ul><li>Intel SR1530 IPMI: BB Ambient Temp Critical [{ITEM.VALUE}]</li></ul>|
|Intel SR1530 IPMI: Power||`last(/Intel SR1530 IPMI/power)=0`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

