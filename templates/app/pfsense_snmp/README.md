
# PFSense by SNMP

## Overview

For Zabbix version: 6.0 and higher.  
Template for monitoring pfSense by SNMP

This template was tested on:

- pfSense, version 2.5.0, 2.5.1, 2.5.2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

1. Import template into Zabbix
2. Enable SNMP daemon at Services in pfSense web interface https://docs.netgate.com/pfsense/en/latest/services/snmp.html
3. Setup firewall rule to get access from Zabbix proxy or Zabbix server by SNMP https://docs.netgate.com/pfsense/en/latest/firewall/index.html#managing-firewall-rules
4. Link template to the host


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IF.ERRORS.WARN} |<p>Threshold of error packets rate for warning trigger. Can be used with interface name as context.</p> |`2` |
|{$IF.UTIL.MAX} |<p>Threshold of interface bandwidth utilization for warning trigger in %. Can be used with interface name as context.</p> |`90` |
|{$IFCONTROL} |<p>Macro for operational state of the interface for link down trigger. Can be used with interface name as context.</p> |`1` |
|{$NET.IF.IFADMINSTATUS.MATCHES} |<p>This macro is used in filters of network interfaces discovery rule.</p> |`^.*` |
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES} |<p>Ignore down(2) administrative status</p> |`^2$` |
|{$NET.IF.IFALIAS.MATCHES} |<p>This macro is used in filters of network interfaces discovery rule.</p> |`.*` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>This macro is used in filters of network interfaces discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFDESCR.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`.*` |
|{$NET.IF.IFDESCR.NOT_MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFNAME.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`^em[0-9]+$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`^$` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(6)</p> |`^6$` |
|{$NET.IF.IFTYPE.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`.*` |
|{$NET.IF.IFTYPE.NOT_MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$SNMP.TIMEOUT} |<p>The time interval for SNMP availability trigger.</p> |`5m` |
|{$SOURCE.TRACKING.TABLE.UTIL.MAX} |<p>Threshold of source tracking table utilization trigger in %.</p> |`90` |
|{$STATE.TABLE.UTIL.MAX} |<p>Threshold of state table utilization trigger in %.</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |pfsense.net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Rules references count |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of rules referencing this interface.</p> |SNMP |net.if.rules.refs[{#SNMPINDEX}] |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv4 traffic passed |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv4 bits per second passed coming in on this interface.</p> |SNMP |net.if.in.pass.v4.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv4 traffic blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv4 bits per second blocked coming in on this interface.</p> |SNMP |net.if.in.block.v4.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv4 traffic passed |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv4 bits per second passed going out on this interface.</p> |SNMP |net.if.out.pass.v4.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv4 traffic blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv4 bits per second blocked going out on this interface.</p> |SNMP |net.if.out.block.v4.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv4 packets passed |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv4 packets passed coming in on this interface.</p> |SNMP |net.if.in.pass.v4.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv4 packets blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv4 packets blocked coming in on this interface.</p> |SNMP |net.if.in.block.v4.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv4 packets passed |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv4 packets passed going out on this interface.</p> |SNMP |net.if.out.pass.v4.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv4 packets blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv4 packets blocked going out on this interface.</p> |SNMP |net.if.out.block.v4.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv6 traffic passed |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv6 bits per second passed coming in on this interface.</p> |SNMP |net.if.in.pass.v6.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv6 traffic blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv6 bits per second blocked coming in on this interface.</p> |SNMP |net.if.in.block.v6.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv6 traffic passed |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv6 bits per second passed going out on this interface.</p> |SNMP |net.if.out.pass.v6.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv6 traffic blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>IPv6 bits per second blocked going out on this interface.</p> |SNMP |net.if.out.block.v6.bps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv6 packets passed |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv6 packets passed coming in on this interface.</p> |SNMP |net.if.in.pass.v6.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Inbound IPv6 packets blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv6 packets blocked coming in on this interface.</p> |SNMP |net.if.in.block.v6.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv6 packets passed |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv6 packets passed going out on this interface.</p> |SNMP |net.if.out.pass.v6.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |PFSense: Interface [{#IFNAME}({#IFALIAS})]: Outbound IPv6 packets blocked |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of IPv6 packets blocked going out on this interface.</p> |SNMP |net.if.out.block.v6.pps[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|pfSense |PFSense: Packet filter running status |<p>MIB: BEGEMOT-PF-MIB</p><p>True if packet filter is currently enabled.</p> |SNMP |pfsense.pf.status |
|pfSense |PFSense: States table current |<p>MIB: BEGEMOT-PF-MIB</p><p>Number of entries in the state table.</p> |SNMP |pfsense.state.table.count |
|pfSense |PFSense: States table limit |<p>MIB: BEGEMOT-PF-MIB</p><p>Maximum number of 'keep state' rules in the ruleset.</p> |SNMP |pfsense.state.table.limit |
|pfSense |PFSense: States table utilization in % |<p>Utilization of state table in %.</p> |CALCULATED |pfsense.state.table.pused<p>**Expression**:</p>`last(//pfsense.state.table.count) * 100 / last(//pfsense.state.table.limit)` |
|pfSense |PFSense: Source tracking table current |<p>MIB: BEGEMOT-PF-MIB</p><p>Number of entries in the source tracking table.</p> |SNMP |pfsense.source.tracking.table.count |
|pfSense |PFSense: Source tracking table limit |<p>MIB: BEGEMOT-PF-MIB</p><p>Maximum number of 'sticky-address' or 'source-track' rules in the ruleset.</p> |SNMP |pfsense.source.tracking.table.limit |
|pfSense |PFSense: Source tracking table utilization in % |<p>Utilization of source tracking table in %.</p> |CALCULATED |pfsense.source.tracking.table.pused<p>**Expression**:</p>`last(//pfsense.source.tracking.table.count) * 100 / last(//pfsense.source.tracking.table.limit)` |
|pfSense |PFSense: DHCP server status |<p>MIB: HOST-RESOURCES-MIB</p><p>The status of DHCP server process.</p> |SNMP |pfsense.dhcpd.status<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|pfSense |PFSense: DNS server status |<p>MIB: HOST-RESOURCES-MIB</p><p>The status of DNS server process.</p> |SNMP |pfsense.dns.status<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|pfSense |PFSense: State of nginx process |<p>MIB: HOST-RESOURCES-MIB</p><p>The status of nginx process.</p> |SNMP |pfsense.nginx.status<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|pfSense |PFSense: Packets matched a filter rule |<p>MIB: BEGEMOT-PF-MIB</p><p>True if the packet was logged with the specified packet filter reason code. The known codes are: match, bad-offset, fragment, short, normalize, and memory.</p> |SNMP |pfsense.packets.match<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|pfSense |PFSense: Packets with bad offset |<p>MIB: BEGEMOT-PF-MIB</p><p>True if the packet was logged with the specified packet filter reason code. The known codes are: match, bad-offset, fragment, short, normalize, and memory.</p> |SNMP |pfsense.packets.bad.offset<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|pfSense |PFSense: Fragmented packets |<p>MIB: BEGEMOT-PF-MIB</p><p>True if the packet was logged with the specified packet filter reason code. The known codes are: match, bad-offset, fragment, short, normalize, and memory.</p> |SNMP |pfsense.packets.fragment<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|pfSense |PFSense: Short packets |<p>MIB: BEGEMOT-PF-MIB</p><p>True if the packet was logged with the specified packet filter reason code. The known codes are: match, bad-offset, fragment, short, normalize, and memory.</p> |SNMP |pfsense.packets.short<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|pfSense |PFSense: Normalized packets |<p>MIB: BEGEMOT-PF-MIB</p><p>True if the packet was logged with the specified packet filter reason code. The known codes are: match, bad-offset, fragment, short, normalize, and memory.</p> |SNMP |pfsense.packets.normalize<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|pfSense |PFSense: Packets dropped due to memory limitation |<p>MIB: BEGEMOT-PF-MIB</p><p>True if the packet was logged with the specified packet filter reason code. The known codes are: match, bad-offset, fragment, short, normalize, and memory.</p> |SNMP |pfsense.packets.mem.drop<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|pfSense |PFSense: Firewall rules count |<p>MIB: BEGEMOT-PF-MIB</p><p>The number of labeled filter rules on this system.</p> |SNMP |pfsense.rules.count |
|Status |PFSense: SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|PFSense: Interface [{#IFNAME}({#IFALIAS})]: High input error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p> |`min(/PFSense by SNMP/net.if.in.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/PFSense by SNMP/net.if.in.errors[{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>**Depends on**:</p><p>- PFSense: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|PFSense: Interface [{#IFNAME}({#IFALIAS})]: High inbound bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/PFSense by SNMP/net.if.in[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])) and last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])>0 `<p>Recovery expression:</p>`avg(/PFSense by SNMP/net.if.in[{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])` |WARNING |<p>**Depends on**:</p><p>- PFSense: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|PFSense: Interface [{#IFNAME}({#IFALIAS})]: High output error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p> |`min(/PFSense by SNMP/net.if.out.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/PFSense by SNMP/net.if.out.errors[{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>**Depends on**:</p><p>- PFSense: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|PFSense: Interface [{#IFNAME}({#IFALIAS})]: High outbound bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/PFSense by SNMP/net.if.out[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])) and last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])>0 `<p>Recovery expression:</p>`avg(/PFSense by SNMP/net.if.out[{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])` |WARNING |<p>**Depends on**:</p><p>- PFSense: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|PFSense: Interface [{#IFNAME}({#IFALIAS})]: Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])<0 and last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])>0 and ( last(/PFSense by SNMP/net.if.type[{#SNMPINDEX}])=6 or last(/PFSense by SNMP/net.if.type[{#SNMPINDEX}])=7 or last(/PFSense by SNMP/net.if.type[{#SNMPINDEX}])=11 or last(/PFSense by SNMP/net.if.type[{#SNMPINDEX}])=62 or last(/PFSense by SNMP/net.if.type[{#SNMPINDEX}])=69 or last(/PFSense by SNMP/net.if.type[{#SNMPINDEX}])=117 ) and (last(/PFSense by SNMP/net.if.status[{#SNMPINDEX}])<>2) `<p>Recovery expression:</p>`(change(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}])>0 and last(/PFSense by SNMP/net.if.speed[{#SNMPINDEX}],#2)>0) or (last(/PFSense by SNMP/net.if.status[{#SNMPINDEX}])=2) ` |INFO |<p>**Depends on**:</p><p>- PFSense: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|PFSense: Interface [{#IFNAME}({#IFALIAS})]: Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and (last(/PFSense by SNMP/net.if.status[{#SNMPINDEX}])=2)` |AVERAGE | |
|PFSense: Packet filter is not running |<p>Please check PF status.</p> |`last(/PFSense by SNMP/pfsense.pf.status)<>1` |HIGH | |
|PFSense: State table usage is high |<p>Please check the number of connections https://docs.netgate.com/pfsense/en/latest/config/advanced-firewall-nat.html#config-advanced-firewall-maxstates</p> |`min(/PFSense by SNMP/pfsense.state.table.pused,#3)>{$STATE.TABLE.UTIL.MAX}` |WARNING | |
|PFSense: Source tracking table usage is high |<p>Please check the number of sticky connections https://docs.netgate.com/pfsense/en/latest/monitoring/status/firewall-states-sources.html</p> |`min(/PFSense by SNMP/pfsense.source.tracking.table.pused,#3)>{$SOURCE.TRACKING.TABLE.UTIL.MAX}` |WARNING | |
|PFSense: DHCP server is not running |<p>Please check DHCP server settings https://docs.netgate.com/pfsense/en/latest/services/dhcp/index.html</p> |`last(/PFSense by SNMP/pfsense.dhcpd.status)=0` |AVERAGE | |
|PFSense: DNS server is not running |<p>Please check DNS server settings https://docs.netgate.com/pfsense/en/latest/services/dns/index.html</p> |`last(/PFSense by SNMP/pfsense.dns.status)=0` |AVERAGE | |
|PFSense: Web server is not running |<p>Please check nginx service status.</p> |`last(/PFSense by SNMP/pfsense.nginx.status)=0` |AVERAGE | |
|PFSense: No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/PFSense by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

