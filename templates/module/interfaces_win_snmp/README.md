
# Interfaces Windows by SNMP

## Overview

For Zabbix version: 6.2 and higher.
Special version of interfaces template that is required for Windows OS. Since MS doesn't support 64 bit counters but supports ifAlias and ifHighSpeed.

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>-</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$NET.IF.IFADMINSTATUS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES} |<p>Ignore down(2) administrative status</p> |`^2$` |
|{$NET.IF.IFALIAS.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFDESCR.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFDESCR.NOT_MATCHES} |<p>-</p> |`Miniport|Virtual|Teredo|Kernel|Loopback|Bluetooth|HTTPS|6to4|QoS|Layer|isatap|ISATAP` |
|{$NET.IF.IFNAME.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9a-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(6)</p> |`^6$` |
|{$NET.IF.IFTYPE.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFTYPE.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[ifOperStatus.{#SNMPINDEX}] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,including framing characters. Discontinuities in the value of this counter can occur at re-initialization of the management system, and atother times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[ifInOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[ifOutOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Interfaces Windows by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Interfaces Windows by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Interfaces Windows by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`<p>Recovery expression:</p>`last(/Interfaces Windows by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/Interfaces Windows by SNMP/net.if.in[ifInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Interfaces Windows by SNMP/net.if.out[ifOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`<p>Recovery expression:</p>`avg(/Interfaces Windows by SNMP/net.if.in[ifInOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) and avg(/Interfaces Windows by SNMP/net.if.out[ifOutOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Interfaces Windows by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Interfaces Windows by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Interfaces Windows by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Interfaces Windows by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Interfaces Windows by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Interfaces Windows by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Interfaces Windows by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Interfaces Windows by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Interfaces Windows by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Interfaces Windows by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Interfaces Windows by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2) `<p>Recovery expression:</p>`(change(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and last(/Interfaces Windows by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}],#2)>0) or (last(/Interfaces Windows by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2) ` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

## Known Issues

- Description: 32bit counters are used in this template (since 64bit are not supported by Windows OS). If busy interfaces return incorrect bits sent/received - set update interval to 1m or less.

