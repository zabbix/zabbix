
# Cisco Meraki dashboard by HTTP

## Overview

For Zabbix version: 6.2 and higher.
The template to monitor Cisco Meraki dashboard by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.  



This template was tested on:

- Cisco Meraki API, version 1.24.0

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

You must set {$MERAKI.TOKEN} and {$MERAKI.API.URL} macros. 

Create the token in the Meraki dashboard (see Meraki [documentation](https://developer.cisco.com/meraki/api-latest/#!authorization/authorization) for instructions). Set this token as {$MERAKI.TOKEN} macro value in Zabbix.

Set your Meraki dashboard URl as {$MERAKI.API.URL} macro value in Zabbix (e.g., api.meraki.com/api/v1).


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MERAKI.API.URL} |<p>Cisco Meraki Dashboard API URL. e.g api.meraki.com/api/v1</p> |`api.meraki.com/api/v1` |
|{$MERAKI.DEVICE.NAME.MATCHES} |<p>This macro is used in devices discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$MERAKI.DEVICE.NAME.NOT_MATCHES} |<p>This macro is used in devices discovery. Can be overridden on the host or linked template level.</p> |`CHANGE_IF_NEEDED` |
|{$MERAKI.HTTP_PROXY} |<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See documentation at https://www.zabbix.com/documentation/6.2/manual/config/items/itemtypes/http</p> |`` |
|{$MERAKI.ORGANIZATION.NAME.MATCHES} |<p>This macro is used in organizations discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$MERAKI.ORGANIZATION.NAME.NOT_MATCHES} |<p>This macro is used in organizations discovery. Can be overridden on the host or linked template level.</p> |`CHANGE_IF_NEEDED` |
|{$MERAKI.TOKEN} |<p>Cisco Meraki Dashboard API Token.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Devices discovery |<p>-</p> |DEPENDENT |meraki.devices.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.devices`</p><p>**Filter**:</p> <p>- {#NAME} MATCHES_REGEX `{$MERAKI.DEVICE.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$MERAKI.DEVICE.NAME.NOT_MATCHES}`</p> |
|Organizations discovery |<p>-</p> |DEPENDENT |meraki.organization.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.organizations`</p><p>**Filter**:</p> <p>- {#NAME} MATCHES_REGEX `{$MERAKI.ORGANIZATION.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$MERAKI.ORGANIZATION.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Zabbix raw items |Meraki: Get data |<p>Item for gathering all the organizations and devices from Meraki API.</p> |SCRIPT |meraki.get.data<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Meraki: Data item errors |<p>Item for gathering all the data item errors.</p> |DEPENDENT |meraki.get.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Meraki: There are errors in 'Get data' metric |<p>-</p> |`length(last(/Cisco Meraki dashboard by HTTP/meraki.get.data.errors))>0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Cisco Meraki organization by HTTP

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MERAKI.API.URL} |<p>Cisco Meraki Dashboard API URL. e.g api.meraki.com/api/v1</p> |`api.meraki.com/api/v1` |
|{$MERAKI.CONFIG.CHANGE.TIMESPAN} |<p>Timespan for gathering config change log. Used in metric config and in URL query.</p> |`1200` |
|{$MERAKI.HTTP_PROXY} |<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See documentation at https://www.zabbix.com/documentation/6.2/manual/config/items/itemtypes/http</p> |`` |
|{$MERAKI.LICENSE.EXPIRE} |<p>Time in seconds for license to expire.</p> |`86400` |
|{$MERAKI.TOKEN} |<p>Cisco Meraki Dashboard API Token.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Uplinks discovery |<p>-</p> |DEPENDENT |meraki.uplinks.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.uplinks`</p> |
|VPN stats discovery |<p>-</p> |DEPENDENT |meraki.vpn.stats.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.vpnStats`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Meraki |Meraki: Groups |<p>Meraki adaptive policy groups count.</p> |DEPENDENT |meraki.policies.groups<p>**Preprocessing**:</p><p>- JSONPATH: `$.counts.groups`</p> |
|Meraki |Meraki: Custom ACLs |<p>Meraki adaptive policy custom ACLs count.</p> |DEPENDENT |meraki.policies.custom.acls<p>**Preprocessing**:</p><p>- JSONPATH: `$.counts.customAcls`</p> |
|Meraki |Meraki: Policies |<p>Meraki adaptive policies count.</p> |DEPENDENT |meraki.policies<p>**Preprocessing**:</p><p>- JSONPATH: `$.counts.policies`</p> |
|Meraki |Meraki: Allow policies |<p>Meraki adaptive allow policies count.</p> |DEPENDENT |meraki.policies.allow<p>**Preprocessing**:</p><p>- JSONPATH: `$.counts.allowPolicies`</p> |
|Meraki |Meraki: Deny policies |<p>Meraki adaptive deny policies count.</p> |DEPENDENT |meraki.policies.deny<p>**Preprocessing**:</p><p>- JSONPATH: `$.counts.denyPolicies`</p> |
|Meraki |Meraki: License status |<p>Meraki license status.</p> |DEPENDENT |meraki.license.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Meraki |Meraki: License expire |<p>Meraki license expire time in seconds left.</p> |DEPENDENT |meraki.license.expire<p>**Preprocessing**:</p><p>- JSONPATH: `$.expirationDate`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Meraki |Uplink [{#INTERFACE}]: [{#UPLINK.ROLE}]: [{#NETWORK.NAME}]: status |<p>Network uplink status.</p> |DEPENDENT |meraki.uplink.status[{#NETWORK.NAME}, {#INTERFACE}, {#UPLINK.ROLE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.uplinks[?(@.networkName== '{#NETWORK.NAME}' && @.interface== '{#INTERFACE}' && @.role== '{#UPLINK.ROLE}' )].status.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Meraki |VPN [{#NETWORK.NAME}]=>[{#PEER.NETWORK.NAME}]: stats raw |<p>VPN connection stats raw.</p> |DEPENDENT |meraki.vpn.stat.raw[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.vpnStats[?(@.networkId=='{#NETWORK.ID}' && @.senderUplink=='{#SENDER.UPLINK}' && @.peerNetworkId=='{#PEER.NETWORK.ID}' && @.receiverUplink=='{#RECEIVER.UPLINK}')].first()`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: latency avg |<p>VPN connection avg latency.</p> |DEPENDENT |meraki.vpn.stat.latency.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.avgLatencyMs`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: latency min |<p>VPN connection min latency.</p> |DEPENDENT |meraki.vpn.stat.latency.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.minLatencyMs`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: latency max |<p>VPN connection max latency.</p> |DEPENDENT |meraki.vpn.stat.latency.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxLatencyMs`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: loss avg, % |<p>VPN connection loss avg.</p> |DEPENDENT |meraki.vpn.stat.loss.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.avgLossPercentage`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: loss min, % |<p>VPN connection loss min.</p> |DEPENDENT |meraki.vpn.stat.loss.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.minLossPercentage`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: loss max, % |<p>VPN connection loss max.</p> |DEPENDENT |meraki.vpn.stat.loss.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxLossPercentage`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: jitter avg |<p>VPN connection jitter avg.</p> |DEPENDENT |meraki.vpn.stat.jitter.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.avgJitter`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: jitter min |<p>VPN connection jitter min.</p> |DEPENDENT |meraki.vpn.stat.jitter.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.minJitter`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: jitter max |<p>VPN connection jitter max.</p> |DEPENDENT |meraki.vpn.stat.jitter.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxJitter`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: mos avg |<p>VPN connection mos avg.</p> |DEPENDENT |meraki.vpn.stat.mos.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.avgMos`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: mos min |<p>VPN connection mos min.</p> |DEPENDENT |meraki.vpn.stat.mos.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.minMos`</p> |
|Meraki |VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: mos max |<p>VPN connection mos max.</p> |DEPENDENT |meraki.vpn.stat.mos.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxMos`</p> |
|Zabbix raw items |Meraki: Get list of the networks |<p>Item for gathering all the networks of organization from Meraki API.</p> |SCRIPT |meraki.get.networks<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Meraki: Networks item errors |<p>Item for gathering all the networks item errors.</p> |DEPENDENT |meraki.get.networks.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Meraki: Get list of the vpn stats |<p>Item for gathering all the vpn stats of the organization.</p> |SCRIPT |meraki.get.vpn.stats<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Meraki: VPN item errors |<p>Item for gathering all the vpn item errors.</p> |DEPENDENT |meraki.get.vpn.stats.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Meraki: Get list of configuration changes |<p>Item for viewing the Change Log for your organization.\nGathering once per 20m by default.</p> |HTTP_AGENT |meraki.get.configuration.changes<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `2h`</p> |
|Zabbix raw items |Meraki: Get list of adaptive policy aggregate statistics |<p>Item for adaptive policy aggregate statistics for an organization.</p> |HTTP_AGENT |meraki.get.adaptive.policy |
|Zabbix raw items |Meraki: Get licenses info |<p>Return an overview of the license state for an organization.</p> |HTTP_AGENT |meraki.get.licenses |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Meraki: License status is not OK |<p>-</p> |`last(/Cisco Meraki organization by HTTP/meraki.license.status)<>1` |WARNING | |
|Meraki: License expires in less than {$MERAKI.LICENSE.EXPIRE} seconds |<p>-</p> |`last(/Cisco Meraki organization by HTTP/meraki.license.expire)<{$MERAKI.LICENSE.EXPIRE} and last(/Cisco Meraki organization by HTTP/meraki.license.expire)>=0` |WARNING | |
|Uplink [{#INTERFACE}]: [{#UPLINK.ROLE}]: [{#NETWORK.NAME}]: status is failed |<p>-</p> |`last(/Cisco Meraki organization by HTTP/meraki.uplink.status[{#NETWORK.NAME}, {#INTERFACE}, {#UPLINK.ROLE}])=0` |WARNING | |
|Meraki: There are errors in 'Get networks' metric |<p>-</p> |`length(last(/Cisco Meraki organization by HTTP/meraki.get.networks.errors))>0` |WARNING | |
|Meraki: There are errors in 'Get VPNs' metric |<p>-</p> |`length(last(/Cisco Meraki organization by HTTP/meraki.get.vpn.stats.errors))>0` |WARNING | |
|Meraki: Configuration has been changed |<p>-</p> |`length(last(/Cisco Meraki organization by HTTP/meraki.get.configuration.changes))>3` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

# Cisco Meraki device by HTTP

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MERAKI.API.URL} |<p>Cisco Meraki Dashboard API URL. e.g api.meraki.com/api/v1</p> |`api.meraki.com/api/v1` |
|{$MERAKI.DEVICE.LATENCY} |<p>Devices uplink latency threshold in seconds.</p> |`0.15` |
|{$MERAKI.DEVICE.LOSS.LATENCY.IP.MATCHES} |<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p> |`^((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4}$` |
|{$MERAKI.DEVICE.LOSS.LATENCY.IP.NOT_MATCHES} |<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p> |`^$` |
|{$MERAKI.DEVICE.LOSS} |<p>Devices uplink loss threshold in percents.</p> |`15` |
|{$MERAKI.DEVICE.UPLINK.MATCHES} |<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$MERAKI.DEVICE.UPLINK.NOT_MATCHES} |<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p> |`^$` |
|{$MERAKI.HTTP_PROXY} |<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See documentation at https://www.zabbix.com/documentation/6.2/manual/config/items/itemtypes/http</p> |`` |
|{$MERAKI.TOKEN} |<p>Cisco Meraki Dashboard API Token.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Uplinks loss and quality discovery |<p>-</p> |DEPENDENT |meraki.device.uplinks.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.uplinksLL`</p><p>**Filter**:</p> <p>- {#UPLINK} MATCHES_REGEX `{$MERAKI.DEVICE.UPLINK.MATCHES}`</p><p>- {#UPLINK} NOT_MATCHES_REGEX `{$MERAKI.DEVICE.UPLINK.NOT_MATCHES}`</p><p>- {#IP} MATCHES_REGEX `{$MERAKI.DEVICE.LOSS.LATENCY.IP.MATCHES}`</p><p>- {#IP} NOT_MATCHES_REGEX `{$MERAKI.DEVICE.LOSS.LATENCY.IP.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Meraki |Meraki: status |<p>Device operational status</p><p>Network: {$NETWORK.ID} </p><p>MAC: {$MAC}</p> |DEPENDENT |meraki.device.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.device[0].status`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Meraki |Meraki: public ip |<p>Device public ip</p><p>Network: {$NETWORK.ID} </p><p>MAC: {$MAC}</p> |DEPENDENT |meraki.device.public.ip<p>**Preprocessing**:</p><p>- JSONPATH: `$.device[0].publicIp`</p> |
|Meraki |Uplink [{#IP}]: [{#UPLINK}]: Loss, % |<p>Loss percent of the device uplink. </p><p>Network: {#NETWORK.ID}. </p><p>Device serial: {#SERIAL}.</p> |DEPENDENT |meraki.device.loss.pct[{#IP},{#UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.uplinksLL[?(@.ip == '{#IP}' && @.uplink== '{#UPLINK}')].timeSeries.[0].lossPercent.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> -1`</p> |
|Meraki |Uplink [{#IP}]: [{#UPLINK}]: Latency |<p>Latency of the device uplink. </p><p>Network: {#NETWORK.ID}. </p><p>Device serial: {#SERIAL}.</p> |DEPENDENT |meraki.device.latency[{#IP},{#UPLINK}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.uplinksLL[?(@.ip == '{#IP}' && @.uplink== '{#UPLINK}')].timeSeries.[0].latencyMs.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> -1000`</p><p>- MULTIPLIER: `0.001`</p> |
|Zabbix raw items |Meraki: Get device data |<p>Item for gathering device data from Meraki API.</p> |SCRIPT |meraki.get.device<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Meraki: Device data item errors |<p>Item for gathering errors of the device item.</p> |DEPENDENT |meraki.get.device.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Meraki: Status is not online |<p>-</p> |`last(/Cisco Meraki device by HTTP/meraki.device.status)<>1` |WARNING | |
|Uplink [{#IP}]: [{#UPLINK}]: loss > {$MERAKI.DEVICE.LOSS}% |<p>-</p> |`min(/Cisco Meraki device by HTTP/meraki.device.loss.pct[{#IP},{#UPLINK}],#3)>{$MERAKI.DEVICE.LOSS}` |WARNING | |
|Uplink [{#IP}]: [{#UPLINK}]: latency > {$MERAKI.DEVICE.LATENCY} |<p>-</p> |`min(/Cisco Meraki device by HTTP/meraki.device.latency[{#IP},{#UPLINK}],#3)>{$MERAKI.DEVICE.LATENCY}` |WARNING | |
|Meraki: There are errors in 'Get Device data' metric |<p>-</p> |`length(last(/Cisco Meraki device by HTTP/meraki.get.device.errors))>0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

