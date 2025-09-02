
# Asterisk by HTTP

## Overview

The template for monitoring Asterisk over HTTP that works without any external scripts.
It collects metrics by polling the Asterisk Manager API remotely using an HTTP agent and JS preprocessing.
All metrics are collected at once, thanks to Zabbix's bulk data collection.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Asterisk, version 13 and later

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

You should enable the mini-HTTP Server, add the option webenabled=yes in the general section of the manager.conf file and create Asterisk Manager user with system and command write permissions within your Asterisk instance. 
Disable the PJSIP driver if you do not use PJSIP or do not have PJSIP endpoints.
Please, define AMI address in the {$AMI.URL} macro. Also, set the hostname or IP address of the AMI host in the {$AMI.HOST} macro for Zabbix to check Asterisk service status.
Then you can define {$AMI.USERNAME} and {$AMI.SECRET} macros in the template for using on the host level.
If there are errors, increase the logging to debug level and see the Zabbix server log.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AMI.URL}|<p>The Asterisk Manager API URL in the format `<scheme>://<host>:<port>/<prefix>/rawman`.</p>|`http://asterisk:8088/asterisk/rawman`|
|{$AMI.HOST}|<p>The hostname or IP address of the Asterisk Manager API host.</p>||
|{$AMI.PORT}|<p>AMI port number for checking service availability.</p>|`5038`|
|{$AMI.USERNAME}|<p>The Asterisk Manager name.</p>|`zabbix`|
|{$AMI.SECRET}|<p>The Asterisk Manager secret.</p>|`zabbix`|
|{$AMI.TRUNK_REGEXP}|<p>The regexp for the identification of trunk peers.</p>|`trunk`|
|{$AMI.RESPONSE_TIME.MAX.WARN}|<p>The Asterisk Manager API page maximum response time in seconds for trigger expression.</p>|`10s`|
|{$AMI.QUEUE_CALLERS.MAX.WARN}|<p>The maximum number of callers in a queue for trigger expression.</p>|`10`|
|{$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN}|<p>The maximum number of busy channels of a trunk for trigger expression.</p>|`28`|
|{$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"PJSIP"}|<p>The total maximum number of busy channels of PJSIP trunks for trigger expression.</p>|`28`|
|{$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"SIP"}|<p>The total maximum number of busy channels of SIP trunks for trigger expression.</p>|`28`|
|{$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"IAX"}|<p>The total maximum number of busy channels of IAX trunks for trigger expression.</p>|`28`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service status|<p>Asterisk Manager API port availability.</p>|Simple check|net.tcp.service["tcp","{$AMI.HOST}","{$AMI.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Service response time|<p>Asterisk Manager API performance.</p>|Simple check|net.tcp.service.perf["tcp","{$AMI.HOST}","{$AMI.PORT}"]|
|Get stats|<p>Asterisk system information in JSON format.</p>|HTTP agent|asterisk.get_stats<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Version|<p>Service version</p>|Dependent item|asterisk.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li></ul>|
|Uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Dependent item|asterisk.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li></ul>|
|Uptime after reload|<p>System uptime after a config reload in 'N days, hh:mm:ss' format.</p>|Dependent item|asterisk.uptime_reload<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime_reload`</p></li></ul>|
|Active channels|<p>The number of active channels at the moment.</p>|Dependent item|asterisk.active_channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_channels`</p></li></ul>|
|Active calls|<p>The number of active calls at the moment.</p>|Dependent item|asterisk.active_calls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_calls`</p></li></ul>|
|Calls processed|<p>The number of calls processed after the last service restart.</p>|Dependent item|asterisk.calls_processed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.calls_processed`</p></li></ul>|
|Calls processed per second|<p>The number of calls processed per second.</p>|Dependent item|asterisk.calls_processed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.calls_processed`</p></li><li>Change per second</li></ul>|
|Total queues|<p>The number of configured queues.</p>|Dependent item|asterisk.total_queues<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue.total`</p></li></ul>|
|SIP monitored online|<p>The number of monitored online SIP peers.</p>|Dependent item|asterisk.sip.monitored_online<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.monitored_online`</p></li></ul>|
|SIP monitored offline|<p>The number of monitored offline SIP peers.</p>|Dependent item|asterisk.sip.monitored_offline<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.monitored_offline`</p></li></ul>|
|SIP unmonitored online|<p>The number of unmonitored online SIP peers.</p>|Dependent item|asterisk.sip.unmonitored_online<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.unmonitored_online`</p></li></ul>|
|SIP unmonitored offline|<p>The number of unmonitored offline SIP peers.</p>|Dependent item|asterisk.sip.unmonitored_offline<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.unmonitored_offline`</p></li></ul>|
|SIP peers|<p>The total number of SIP peers.</p>|Dependent item|asterisk.sip.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.total`</p></li></ul>|
|SIP trunks active channels|<p>The total number of SIP trunks active channels.</p>|Dependent item|asterisk.sip.active_channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.active_channels`</p></li></ul>|
|IAX online peers|<p>The number of online IAX peers.</p>|Dependent item|asterisk.iax.online<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iax.online`</p></li></ul>|
|IAX offline peers|<p>The number of offline IAX peers.</p>|Dependent item|asterisk.iax.offline<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iax.offline`</p></li></ul>|
|IAX unmonitored peers|<p>The number of unmonitored IAX peers.</p>|Dependent item|asterisk.iax.unmonitored<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iax.unmonitored`</p></li></ul>|
|IAX peers|<p>The total number of IAX peers.</p>|Dependent item|asterisk.iax.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iax.total`</p></li></ul>|
|IAX trunks active channels|<p>The total number of IAX trunks active channels.</p>|Dependent item|asterisk.iax.active_channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iax.active_channels`</p></li></ul>|
|PJSIP available endpoints|<p>The number of available PJSIP peers.</p>|Dependent item|asterisk.pjsip.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pjsip.available`</p></li></ul>|
|PJSIP unavailable endpoints|<p>The number of unavailable PJSIP peers.</p>|Dependent item|asterisk.pjsip.unavailable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pjsip.unavailable`</p></li></ul>|
|PJSIP endpoints|<p>The total number of PJSIP peers.</p>|Dependent item|asterisk.pjsip.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pjsip.total`</p></li></ul>|
|PJSIP trunks active channels|<p>The total number of PJSIP trunks active channels.</p>|Dependent item|asterisk.pjsip.active_channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pjsip.active_channels`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Asterisk: Service is down||`last(/Asterisk by HTTP/net.tcp.service["tcp","{$AMI.HOST}","{$AMI.PORT}"])=0`|Average|**Manual close**: Yes|
|Asterisk: Service response time is too high||`min(/Asterisk by HTTP/net.tcp.service.perf["tcp","{$AMI.HOST}","{$AMI.PORT}"],5m)>{$AMI.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Asterisk: Service is down</li></ul>|
|Asterisk: Version has changed|<p>The Asterisk version has changed. Acknowledge to close the problem manually.</p>|`last(/Asterisk by HTTP/asterisk.version,#1)<>last(/Asterisk by HTTP/asterisk.version,#2) and length(last(/Asterisk by HTTP/asterisk.version))>0`|Info|**Manual close**: Yes|
|Asterisk: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Asterisk by HTTP/asterisk.uptime)<10m`|Info|**Manual close**: Yes|
|Asterisk: Failed to fetch AMI page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Asterisk by HTTP/asterisk.uptime,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Asterisk: Service is down</li></ul>|
|Asterisk: Configuration has been reloaded|<p>Uptime is less than 10 minutes.</p>|`last(/Asterisk by HTTP/asterisk.uptime_reload)<10m`|Info|**Manual close**: Yes|
|Asterisk: Total number of active channels of SIP trunks is too high|<p>The SIP trunks may not be able to process new calls.</p>|`min(/Asterisk by HTTP/asterisk.sip.active_channels,10m)>={$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"SIP"}`|Warning||
|Asterisk: Total number of active channels of IAX trunks is too high|<p>The IAX trunks may not be able to process new calls.</p>|`min(/Asterisk by HTTP/asterisk.iax.active_channels,10m)>={$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"IAX"}`|Warning||
|Asterisk: Total number of active channels of PJSIP trunks is too high|<p>The PJSIP trunks may not be able to process new calls.</p>|`min(/Asterisk by HTTP/asterisk.pjsip.active_channels,10m)>={$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"PJSIP"}`|Warning||

### LLD rule SIP peers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SIP peers discovery||Dependent item|asterisk.sip_peers.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.trunks`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SIP peers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SIP trunk "{#OBJECTNAME}": Get SIP trunk|<p>Raw data for a SIP trunk.</p>|Dependent item|asterisk.sip.trunk.get[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sip.trunks[?(@.ObjectName=='{#OBJECTNAME}')].first()`</p></li></ul>|
|SIP trunk "{#OBJECTNAME}": Status|<p>SIP trunk status. Here are the possible states that a device state may have:</p><p>Unmonitored</p><p>UNKNOWN</p><p>UNREACHABLE</p><p>OK</p>|Dependent item|asterisk.sip.trunk.status[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SIP trunk "{#OBJECTNAME}": Active channels|<p>The total number of active SIP trunk channels.</p>|Dependent item|asterisk.sip.trunk.active_channels[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_channels`</p></li></ul>|

### Trigger prototypes for SIP peers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Asterisk: SIP trunk "{#OBJECTNAME}": SIP trunk {#OBJECTNAME} has a state {ITEM.VALUE}|<p>The SIP trunk is unable to establish a connection with a neighbor due to network issues or incorrect configuration.</p>|`last(/Asterisk by HTTP/asterisk.sip.trunk.status[{#OBJECTNAME}])="UNKNOWN" or last(/Asterisk by HTTP/asterisk.sip.trunk.status[{#OBJECTNAME}])="UNREACHABLE"`|Average||
|Asterisk: SIP trunk "{#OBJECTNAME}": Number of the SIP trunk "{#OBJECTNAME}" active channels is too high|<p>The SIP trunk may not be able to process new calls.</p>|`min(/Asterisk by HTTP/asterisk.sip.trunk.active_channels[{#OBJECTNAME}],10m)>={$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN:"{#OBJECTNAME}"}`|Warning||

### LLD rule IAX peers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|IAX peers discovery||Dependent item|asterisk.iax_peers.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iax.trunks`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for IAX peers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|IAX trunk "{#OBJECTNAME}": Get IAX trunk|<p>Raw data for an IAX trunk.</p>|Dependent item|asterisk.iax.trunk.get[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iax.trunks[?(@.ObjectName=='{#OBJECTNAME}')].first()`</p></li></ul>|
|IAX trunk "{#OBJECTNAME}": Status|<p>IAX trunk status. Here are the possible states that a device state may have:</p><p>Unmonitored</p><p>UNKNOWN</p><p>UNREACHABLE</p><p>OK</p>|Dependent item|asterisk.iax.trunk.status[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|IAX trunk "{#OBJECTNAME}": Active channels|<p>The total number of active IAX trunk channels.</p>|Dependent item|asterisk.iax.trunk.active_channels[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_channels`</p></li></ul>|

### Trigger prototypes for IAX peers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Asterisk: IAX trunk "{#OBJECTNAME}": IAX trunk {#OBJECTNAME} has a state {ITEM.VALUE}|<p>The IAX trunk is unable to establish a connection with a neighbor due to network issues or incorrect configuration.</p>|`last(/Asterisk by HTTP/asterisk.iax.trunk.status[{#OBJECTNAME}])="UNKNOWN" or last(/Asterisk by HTTP/asterisk.iax.trunk.status[{#OBJECTNAME}])="UNREACHABLE"`|Average||
|Asterisk: IAX trunk "{#OBJECTNAME}": Number of the IAX trunk "{#OBJECTNAME}" active channels is too high|<p>The IAX trunk may not be able to process new calls.</p>|`min(/Asterisk by HTTP/asterisk.iax.trunk.active_channels[{#OBJECTNAME}],10m)>={$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN:"{#OBJECTNAME}"}`|Warning||

### LLD rule PJSIP endpoints discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PJSIP endpoints discovery||Dependent item|asterisk.pjsip_endpoints.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pjsip.trunks`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for PJSIP endpoints discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PJSIP trunk "{#OBJECTNAME}": Get PJSIP trunk|<p>Raw data for a PJSIP trunk.</p>|Dependent item|asterisk.pjsip.trunk.get[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pjsip.trunks[?(@.ObjectName=='{#OBJECTNAME}')].first()`</p></li></ul>|
|PJSIP trunk "{#OBJECTNAME}": Device state|<p>PJSIP trunk status. Here are the possible states that a device state may have:</p><p>Unavailable</p><p>Not in use</p><p>In use</p>|Dependent item|asterisk.pjsip.trunk.devicestate[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DeviceState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PJSIP trunk "{#OBJECTNAME}": Active channels|<p>The total number of active PJSIP trunk channels.</p>|Dependent item|asterisk.pjsip.trunk.active_channels[{#OBJECTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_channels`</p></li></ul>|

### Trigger prototypes for PJSIP endpoints discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Asterisk: PJSIP trunk "{#OBJECTNAME}": PJSIP trunk {#OBJECTNAME} has a state Unavailable|<p>The PJSIP trunk is unable to establish a connection with a neighbor due to network issues or incorrect configuration.</p>|`last(/Asterisk by HTTP/asterisk.pjsip.trunk.devicestate[{#OBJECTNAME}])="Unavailable"`|Average||
|Asterisk: PJSIP trunk "{#OBJECTNAME}": Number of the PJSIP trunk "{#OBJECTNAME}" active channels is too high|<p>The PJSIP trunk may not be able to process new calls.</p>|`min(/Asterisk by HTTP/asterisk.pjsip.trunk.active_channels[{#OBJECTNAME}],10m)>={$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN:"{#OBJECTNAME}"}`|Warning||

### LLD rule Queues discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Queues discovery||Dependent item|asterisk.queues.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue.queues`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Queues discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|"{#QUEUE}": Get queue|<p>Raw data for a queue.</p>|Dependent item|asterisk.queue.get[{#QUEUE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue.queues[?(@.Queue=='{#QUEUE}')].first()`</p></li></ul>|
|"{#QUEUE}": Logged in|<p>The number of queue members.</p>|Dependent item|asterisk.queue.loggedin[{#QUEUE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LoggedIn`</p></li></ul>|
|"{#QUEUE}": Available|<p>The number of available queue members.</p>|Dependent item|asterisk.queue.available[{#QUEUE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Available`</p></li></ul>|
|"{#QUEUE}": Callers|<p>The number incoming calls in queue.</p>|Dependent item|asterisk.queue.callers[{#QUEUE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Callers`</p></li></ul>|

### Trigger prototypes for Queues discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Asterisk: "{#QUEUE}": Number of callers in the queue "{#QUEUE}" is too high|<p>There is a large number of calls in the queue.</p>|`min(/Asterisk by HTTP/asterisk.queue.callers[{#QUEUE}],10m)>{$AMI.QUEUE_CALLERS.MAX.WARN:"{#QUEUE}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

