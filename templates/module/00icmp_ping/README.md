
# Template Module ICMP Ping

## Overview

For Zabbix version: 4.4  

## Setup


## Zabbix configuration


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ICMP_LOSS_WARN}|<p>-</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>-</p>|`0.15`|

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Status|ICMP ping|<p>-</p>|SIMPLE|icmpping|
|Status|ICMP loss|<p>-</p>|SIMPLE|icmppingloss|
|Status|ICMP response time|<p>-</p>|SIMPLE|icmppingsec|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`{TEMPLATE_NAME:icmpping.max(#3)}=0`|HIGH||
|High ICMP ping loss|<p>-</p>|`{TEMPLATE_NAME:icmppingloss.min(5m)}>{$ICMP_LOSS_WARN} and {TEMPLATE_NAME:icmppingloss.min(5m)}<100`|WARNING|<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p>|
|High ICMP ping response time|<p>-</p>|`{TEMPLATE_NAME:icmppingsec.avg(5m)}>{$ICMP_RESPONSE_TIME_WARN}`|WARNING|<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p>|

## Feedback

Please report any issues with the template at https://support.zabbix.com

