
# Intel SR1530 IPMI

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
|Fans |System Fan 3 |<p>-</p> |IPMI |system_fan_3 |
|Temperature |BB Ambient Temp |<p>-</p> |IPMI |bb_ambient_temp |
|Voltage |BB +1.8V SM |<p>-</p> |IPMI |bb_1.8v_sm |
|Voltage |BB +3.3V |<p>-</p> |IPMI |bb_3.3v |
|Voltage |BB +3.3V STBY |<p>-</p> |IPMI |bb_3.3v_stby |
|Voltage |BB +5.0V |<p>-</p> |IPMI |bb_5.0v |
|Voltage |Power |<p>-</p> |IPMI |power |
|Voltage |Processor Vcc |<p>-</p> |IPMI |processor_vcc |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|BB Ambient Temp Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_ambient_temp)<5 or last(/Intel SR1530 IPMI/bb_ambient_temp)>66` |DISASTER | |
|BB Ambient Temp Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_ambient_temp)<10 or last(/Intel SR1530 IPMI/bb_ambient_temp)>61` |HIGH |<p>**Depends on**:</p><p>- BB Ambient Temp Critical [{ITEM.VALUE}]</p> |
|BB +1.8V SM Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_1.8v_sm)<1.597 or last(/Intel SR1530 IPMI/bb_1.8v_sm)>2.019` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +1.8V SM Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_1.8v_sm)<1.646 or last(/Intel SR1530 IPMI/bb_1.8v_sm)>1.960` |HIGH |<p>**Depends on**:</p><p>- BB +1.8V SM Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|BB +3.3V Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_3.3v)<2.876 or last(/Intel SR1530 IPMI/bb_3.3v)>3.729` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +3.3V Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_3.3v)<2.970 or last(/Intel SR1530 IPMI/bb_3.3v)>3.618` |HIGH |<p>**Depends on**:</p><p>- BB +3.3V Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|BB +3.3V STBY Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_3.3v_stby)<2.876 or last(/Intel SR1530 IPMI/bb_3.3v_stby)>3.729` |DISASTER | |
|BB +3.3V STBY Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_3.3v_stby)<2.970 or last(/Intel SR1530 IPMI/bb_3.3v_stby)>3.618` |HIGH |<p>**Depends on**:</p><p>- BB +3.3V STBY Critical [{ITEM.VALUE}]</p> |
|BB +5.0V Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_5.0v)<4.362 or last(/Intel SR1530 IPMI/bb_5.0v)>5.663` |DISASTER |<p>**Depends on**:</p><p>- Power</p> |
|BB +5.0V Non-Critical [{ITEM.VALUE}] |<p>-</p> |`last(/Intel SR1530 IPMI/bb_5.0v)<4.483 or last(/Intel SR1530 IPMI/bb_5.0v)>5.495` |HIGH |<p>**Depends on**:</p><p>- BB +5.0V Critical [{ITEM.VALUE}]</p><p>- Power</p> |
|Power |<p>-</p> |`last(/Intel SR1530 IPMI/power)=0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

