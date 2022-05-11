
# IMAP Service

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
|Services |IMAP service is running |<p>-</p> |SIMPLE |net.tcp.service[imap] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|IMAP service is down on {HOST.NAME} |<p>-</p> |`max(/IMAP Service/net.tcp.service[imap],#3)=0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

