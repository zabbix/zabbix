
# SMTP Service

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
|Services |SMTP service is running |<p>-</p> |SIMPLE |net.tcp.service[smtp] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|SMTP service is down on {HOST.NAME} |<p>-</p> |`max(/SMTP Service/net.tcp.service[smtp],#3)=0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

