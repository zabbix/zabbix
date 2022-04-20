
# Asterisk by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template for monitoring Asterisk over HTTP that works without any external scripts.  
It collects metrics by polling the Asterisk Manager API remotely using an HTTP agent and JS preprocessing.  
All metrics are collected at once, thanks to Zabbix's bulk data collection.


This template was tested on:

- Asterisk, version 13 and later

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

You should enable the mini-HTTP Server, add the option webenabled=yes in the general section of the manager.conf file and
 create Asterisk Manager user with system and command write permissions within your Asterisk instance. Disable the PJSIP driver
 if you do not use PJSIP or do not have PJSIP endpoints.  
Please, define AMI address in the {$AMI.URL} macro. Also, the Zabbix host should have an Agent interface with the AMI address to check Asterisk service status.
Then you can define {$AMI.USERNAME} and {$AMI.SECRET} macros in the template for using on the host level.  
If there are errors, increase the logging to debug level and see the Zabbix server log.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AMI.PORT} |<p>AMI port number for checking service availability.</p> |`5038` |
|{$AMI.QUEUE_CALLERS.MAX.WARN} |<p>The maximum number of callers in a queue for trigger expression.</p> |`10` |
|{$AMI.RESPONSE_TIME.MAX.WARN} |<p>The Asterisk Manager API page maximum response time in seconds for trigger expression.</p> |`10s` |
|{$AMI.SECRET} |<p>The Asterisk Manager secret.</p> |`zabbix` |
|{$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN} |<p>The maximum number of busy channels of a trunk for trigger expression.</p> |`28` |
|{$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"IAX"} |<p>The total maximum number of busy channels of IAX trunks for trigger expression.</p> |`28` |
|{$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"PJSIP"} |<p>The total maximum number of busy channels of PJSIP trunks for trigger expression.</p> |`28` |
|{$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"SIP"} |<p>The total maximum number of busy channels of SIP trunks for trigger expression.</p> |`28` |
|{$AMI.TRUNK_REGEXP} |<p>The regexp for the identification of trunk peers.</p> |`trunk` |
|{$AMI.URL} |<p>The Asterisk Manager API URL in the format `<scheme>://<host>:<port>/<prefix>/rawman`.</p> |`http://asterisk:8088/asterisk/rawman` |
|{$AMI.USERNAME} |<p>The Asterisk Manager name.</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|IAX peers discovery |<p>-</p> |DEPENDENT |asterisk.iax_peers.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.trunks`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PJSIP endpoints discovery |<p>-</p> |DEPENDENT |asterisk.pjsip_endpoints.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.pjsip.trunks`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Queues discovery |<p>-</p> |DEPENDENT |asterisk.queues.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.queue.queues`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|SIP peers discovery |<p>-</p> |DEPENDENT |asterisk.sip_peers.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.trunks`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Asterisk |Asterisk: Service status |<p>Asterisk Manager API port availability.</p> |SIMPLE |net.tcp.service["tcp","{HOST.CONN}","{$AMI.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Asterisk |Asterisk: Service response time |<p>Asterisk Manager API performance.</p> |SIMPLE |net.tcp.service.perf["tcp","{HOST.CONN}","{$AMI.PORT}"] |
|Asterisk |Asterisk: Version |<p>Service version</p> |DEPENDENT |asterisk.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p> |
|Asterisk |Asterisk: Uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |DEPENDENT |asterisk.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.uptime`</p> |
|Asterisk |Asterisk: Uptime after reload |<p>System uptime after a config reload in 'N days, hh:mm:ss' format.</p> |DEPENDENT |asterisk.uptime_reload<p>**Preprocessing**:</p><p>- JSONPATH: `$.uptime_reload`</p> |
|Asterisk |Asterisk: Active channels |<p>The number of active channels at the moment.</p> |DEPENDENT |asterisk.active_channels<p>**Preprocessing**:</p><p>- JSONPATH: `$.active_channels`</p> |
|Asterisk |Asterisk: Active calls |<p>The number of active calls at the moment.</p> |DEPENDENT |asterisk.active_calls<p>**Preprocessing**:</p><p>- JSONPATH: `$.active_calls`</p> |
|Asterisk |Asterisk: Calls processed |<p>The number of calls processed after the last service restart.</p> |DEPENDENT |asterisk.calls_processed<p>**Preprocessing**:</p><p>- JSONPATH: `$.calls_processed`</p> |
|Asterisk |Asterisk: Calls processed per second |<p>The number of calls processed per second.</p> |DEPENDENT |asterisk.calls_processed.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.calls_processed`</p><p>- CHANGE_PER_SECOND</p> |
|Asterisk |Asterisk: Total queues |<p>The number of configured queues.</p> |DEPENDENT |asterisk.total_queues<p>**Preprocessing**:</p><p>- JSONPATH: `$.queue.total`</p> |
|Asterisk |Asterisk: SIP monitored online |<p>The number of monitored online SIP peers.</p> |DEPENDENT |asterisk.sip.monitored_online<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.monitored_online`</p> |
|Asterisk |Asterisk: SIP monitored offline |<p>The number of monitored offline SIP peers.</p> |DEPENDENT |asterisk.sip.monitored_offline<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.monitored_offline`</p> |
|Asterisk |Asterisk: SIP unmonitored online |<p>The number of unmonitored online SIP peers.</p> |DEPENDENT |asterisk.sip.unmonitored_online<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.unmonitored_online`</p> |
|Asterisk |Asterisk: SIP unmonitored offline |<p>The number of unmonitored offline SIP peers.</p> |DEPENDENT |asterisk.sip.unmonitored_offline<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.unmonitored_offline`</p> |
|Asterisk |Asterisk: SIP peers |<p>The total number of SIP peers.</p> |DEPENDENT |asterisk.sip.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.total`</p> |
|Asterisk |Asterisk: SIP trunks active channels |<p>The total number of SIP trunks active channels.</p> |DEPENDENT |asterisk.sip.active_channels<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.active_channels`</p> |
|Asterisk |Asterisk: IAX online peers |<p>The number of online IAX peers.</p> |DEPENDENT |asterisk.iax.online<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.online`</p> |
|Asterisk |Asterisk: IAX offline peers |<p>The number of offline IAX peers.</p> |DEPENDENT |asterisk.iax.offline<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.offline`</p> |
|Asterisk |Asterisk: IAX unmonitored peers |<p>The number of unmonitored IAX peers.</p> |DEPENDENT |asterisk.iax.unmonitored<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.unmonitored`</p> |
|Asterisk |Asterisk: IAX peers |<p>The total number of IAX peers.</p> |DEPENDENT |asterisk.iax.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.total`</p> |
|Asterisk |Asterisk: IAX trunks active channels |<p>The total number of IAX trunks active channels.</p> |DEPENDENT |asterisk.iax.active_channels<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.active_channels`</p> |
|Asterisk |Asterisk: PJSIP available endpoints |<p>The number of available PJSIP peers.</p> |DEPENDENT |asterisk.pjsip.available<p>**Preprocessing**:</p><p>- JSONPATH: `$.pjsip.available`</p> |
|Asterisk |Asterisk: PJSIP unavailable endpoints |<p>The number of unavailable PJSIP peers.</p> |DEPENDENT |asterisk.pjsip.unavailable<p>**Preprocessing**:</p><p>- JSONPATH: `$.pjsip.unavailable`</p> |
|Asterisk |Asterisk: PJSIP endpoints |<p>The total number of PJSIP peers.</p> |DEPENDENT |asterisk.pjsip.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.pjsip.total`</p> |
|Asterisk |Asterisk: PJSIP trunks active channels |<p>The total number of PJSIP trunks active channels.</p> |DEPENDENT |asterisk.pjsip.active_channels<p>**Preprocessing**:</p><p>- JSONPATH: `$.pjsip.active_channels`</p> |
|Asterisk |SIP trunk "{#OBJECTNAME}": Status |<p>SIP trunk status. Here are the possible states that a device state may have:</p><p>Unmonitored</p><p>UNKNOWN</p><p>UNREACHABLE</p><p>OK</p> |DEPENDENT |asterisk.sip.trunk.status[{#OBJECTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.trunks[?(@.ObjectName=='{#OBJECTNAME}')].Status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Asterisk |SIP trunk "{#OBJECTNAME}": Active channels |<p>The total number of active SIP trunk channels.</p> |DEPENDENT |asterisk.sip.trunk.active_channels[{#OBJECTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.sip.trunks[?(@.ObjectName=='{#OBJECTNAME}')].active_channels.first()`</p> |
|Asterisk |IAX trunk "{#OBJECTNAME}": Status |<p>IAX trunk status. Here are the possible states that a device state may have:</p><p>Unmonitored</p><p>UNKNOWN</p><p>UNREACHABLE</p><p>OK</p> |DEPENDENT |asterisk.iax.trunk.status[{#OBJECTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.trunks[?(@.ObjectName=='{#OBJECTNAME}')].Status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Asterisk |IAX trunk "{#OBJECTNAME}": Active channels |<p>The total number of active IAX trunk channels.</p> |DEPENDENT |asterisk.iax.trunk.active_channels[{#OBJECTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.iax.trunks[?(@.ObjectName=='{#OBJECTNAME}')].active_channels.first()`</p> |
|Asterisk |PJSIP trunk "{#OBJECTNAME}": Device state |<p>PJSIP trunk status. Here are the possible states that a device state may have:</p><p>Unavailable</p><p>Not in use</p><p>In use</p> |DEPENDENT |asterisk.pjsip.trunk.devicestate[{#OBJECTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pjsip.trunks[?(@.ObjectName=='{#OBJECTNAME}')].DeviceState.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Asterisk |PJSIP trunk "{#OBJECTNAME}": Active channels |<p>The total number of active PJSIP trunk channels.</p> |DEPENDENT |asterisk.pjsip.trunk.active_channels[{#OBJECTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pjsip.trunks[?(@.ObjectName=='{#OBJECTNAME}')].active_channels.first()`</p> |
|Asterisk |"{#QUEUE}": Logged in |<p>The number of queue members.</p> |DEPENDENT |asterisk.queue.loggedin[{#QUEUE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queue.queues[?(@.Queue=='{#QUEUE}')].LoggedIn.first()`</p> |
|Asterisk |"{#QUEUE}": Available |<p>The number of available queue members.</p> |DEPENDENT |asterisk.queue.available[{#QUEUE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queue.queues[?(@.Queue=='{#QUEUE}')].Available.first()`</p> |
|Asterisk |"{#QUEUE}": Callers |<p>The number incoming calls in queue.</p> |DEPENDENT |asterisk.queue.callers[{#QUEUE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queue.queues[?(@.Queue=='{#QUEUE}')].Callers.first()`</p> |
|Zabbix raw items |Asterisk: Get stats |<p>Asterisk system information in JSON format.</p> |HTTP_AGENT |asterisk.get_stats<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Asterisk: Service is down |<p>-</p> |`last(/Asterisk by HTTP/net.tcp.service["tcp","{HOST.CONN}","{$AMI.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|Asterisk: Service response time is too high |<p>-</p> |`min(/Asterisk by HTTP/net.tcp.service.perf["tcp","{HOST.CONN}","{$AMI.PORT}"],5m)>{$AMI.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Asterisk: Service is down</p> |
|Asterisk: Version has changed |<p>Asterisk version has changed. Ack to close.</p> |`last(/Asterisk by HTTP/asterisk.version,#1)<>last(/Asterisk by HTTP/asterisk.version,#2) and length(last(/Asterisk by HTTP/asterisk.version))>0` |INFO |<p>Manual close: YES</p> |
|Asterisk: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Asterisk by HTTP/asterisk.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Asterisk: Failed to fetch AMI page |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/Asterisk by HTTP/asterisk.uptime,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Asterisk: Service is down</p> |
|Asterisk: has been reloaded |<p>Uptime is less than 10 minutes</p> |`last(/Asterisk by HTTP/asterisk.uptime_reload)<10m` |INFO |<p>Manual close: YES</p> |
|Asterisk: Total number of active channels of SIP trunks is too high |<p>The SIP trunks may not be able to process new calls.</p> |`min(/Asterisk by HTTP/asterisk.sip.active_channels,10m)>={$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"SIP"}` |WARNING | |
|Asterisk: Total number of active channels of IAX trunks is too high |<p>The IAX trunks may not be able to process new calls.</p> |`min(/Asterisk by HTTP/asterisk.iax.active_channels,10m)>={$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"IAX"}` |WARNING | |
|Asterisk: Total number of active channels of PJSIP trunks is too high |<p>The PJSIP trunks may not be able to process new calls.</p> |`min(/Asterisk by HTTP/asterisk.pjsip.active_channels,10m)>={$AMI.TRUNK_ACTIVE_CHANNELS_TOTAL.MAX.WARN:"PJSIP"}` |WARNING | |
|SIP trunk "{#OBJECTNAME}": SIP trunk {#OBJECTNAME} has a state {ITEM.VALUE} |<p>The SIP trunk is unable to establish a connection with a neighbor due to network issues or incorrect configuration.</p> |`last(/Asterisk by HTTP/asterisk.sip.trunk.status[{#OBJECTNAME}])="UNKNOWN" or last(/Asterisk by HTTP/asterisk.sip.trunk.status[{#OBJECTNAME}])="UNREACHABLE"` |AVERAGE | |
|SIP trunk "{#OBJECTNAME}": Number of the SIP trunk "{#OBJECTNAME}" active channels is too high |<p>The SIP trunk may not be able to process new calls.</p> |`min(/Asterisk by HTTP/asterisk.sip.trunk.active_channels[{#OBJECTNAME}],10m)>={$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN:"{#OBJECTNAME}"}` |WARNING | |
|IAX trunk "{#OBJECTNAME}": IAX trunk {#OBJECTNAME} has a state {ITEM.VALUE} |<p>The IAX trunk is unable to establish a connection with a neighbor due to network issues or incorrect configuration.</p> |`last(/Asterisk by HTTP/asterisk.iax.trunk.status[{#OBJECTNAME}])="UNKNOWN" or last(/Asterisk by HTTP/asterisk.iax.trunk.status[{#OBJECTNAME}])="UNREACHABLE"` |AVERAGE | |
|IAX trunk "{#OBJECTNAME}": Number of the IAX trunk "{#OBJECTNAME}" active channels is too high |<p>The IAX trunk may not be able to process new calls.</p> |`min(/Asterisk by HTTP/asterisk.iax.trunk.active_channels[{#OBJECTNAME}],10m)>={$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN:"{#OBJECTNAME}"}` |WARNING | |
|PJSIP trunk "{#OBJECTNAME}": PJSIP trunk {#OBJECTNAME} has a state Unavailable |<p>The PJSIP trunk is unable to establish a connection with a neighbor due to network issues or incorrect configuration.</p> |`last(/Asterisk by HTTP/asterisk.pjsip.trunk.devicestate[{#OBJECTNAME}])="Unavailable"` |AVERAGE | |
|PJSIP trunk "{#OBJECTNAME}": Number of the PJSIP trunk "{#OBJECTNAME}" active channels is too high |<p>The PJSIP trunk may not be able to process new calls.</p> |`min(/Asterisk by HTTP/asterisk.pjsip.trunk.active_channels[{#OBJECTNAME}],10m)>={$AMI.TRUNK_ACTIVE_CHANNELS.MAX.WARN:"{#OBJECTNAME}"}` |WARNING | |
|"{#QUEUE}": Number of callers in the queue "{#QUEUE}" is too high |<p>There is a large number of calls in the queue.</p> |`min(/Asterisk by HTTP/asterisk.queue.callers[{#QUEUE}],10m)>{$AMI.QUEUE_CALLERS.MAX.WARN:"{#QUEUE}"}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/410060-discussion-thread-for-official-zabbix-template-asterisk).


## References

https://wiki.asterisk.org/wiki/display/AST/Home
