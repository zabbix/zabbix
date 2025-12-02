
# NetApp AFF A700 by HTTP

## Overview

The template to monitor SAN NetApp AFF A700 cluster by Zabbix HTTP agent.    


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- NetApp AFF A700 9.7 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1\. Create a host for AFF A700 with cluster management IP as the Zabbix agent interface.

2\. Link the template to the host.

3\. Customize macro values if needed.



### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NETAPP.URL}|<p>AFF700 cluster URL address.</p>||
|{$NETAPP.USERNAME}|<p>AFF700 user name.</p>||
|{$NETAPP.PASSWORD}|<p>AFF700 user password.</p>||
|{$NETAPP.HTTP.AGENT.TIMEOUT}|<p>The HTTP agent timeout to wait for a response from AFF700.</p>|`5s`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get cluster||HTTP agent|netapp.cluster.get|
|Get nodes||HTTP agent|netapp.nodes.get|
|Get disks||HTTP agent|netapp.disks.get|
|Get volumes||HTTP agent|netapp.volumes.get|
|Get ethernet ports||HTTP agent|netapp.ports.eth.get|
|Get FC ports||HTTP agent|netapp.ports.fc.get|
|Get SVMs||HTTP agent|netapp.svms.get|
|Get LUNs||HTTP agent|netapp.luns.get|
|Get chassis||HTTP agent|netapp.chassis.get|
|Get FRUs||HTTP agent|netapp.frus.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Cluster software version|<p>This returns the cluster version information. When the cluster has more than one node, the cluster version is equivalent to the lowest of generation, major, and minor versions on all nodes.</p>|Dependent item|netapp.cluster.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version.full`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Cluster name|<p>The name of the cluster.</p>|Dependent item|netapp.cluster.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.name`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Cluster location|<p>The location of the cluster.</p>|Dependent item|netapp.cluster.location<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.location`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Cluster status|<p>The status of the cluster: ok, error, partial_no_data, partial_no_response, partial_other_error, negative_delta, backfilled_data, inconsistent_delta_time, inconsistent_old_data.</p>|Dependent item|netapp.cluster.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.status`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Cluster throughput, other rate|<p>Throughput bytes observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.cluster.statistics.throughput.other.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.throughput_raw.other`</p></li><li>Change per second</li></ul>|
|Cluster throughput, read rate|<p>Throughput bytes observed at the storage object. Performance metric for read I/O operations.</p>|Dependent item|netapp.cluster.statistics.throughput.read.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.throughput_raw.read`</p></li><li>Change per second</li></ul>|
|Cluster throughput, write rate|<p>Throughput bytes observed at the storage object. Performance metric for write I/O operations.</p>|Dependent item|netapp.cluster.statistics.throughput.write.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.throughput_raw.write`</p></li><li>Change per second</li></ul>|
|Cluster throughput, total rate|<p>Throughput bytes observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.cluster.statistics.throughput.total.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.throughput_raw.total`</p></li><li>Change per second</li></ul>|
|Cluster IOPS, other rate|<p>The number of I/O operations observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.cluster.statistics.iops.other.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.other`</p></li><li>Change per second</li></ul>|
|Cluster IOPS, read rate|<p>The number of I/O operations observed at the storage object. Performance metric for read I/O operations.</p>|Dependent item|netapp.cluster.statistics.iops.read.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.read`</p></li><li>Change per second</li></ul>|
|Cluster IOPS, write rate|<p>The number of I/O operations observed at the storage object. Performance metric for write I/O operations.</p>|Dependent item|netapp.cluster.statistics.iops.write.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.write`</p></li><li>Change per second</li></ul>|
|Cluster IOPS, total rate|<p>The number of I/O operations observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.cluster.statistics.iops.total.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.total`</p></li><li>Change per second</li></ul>|
|Cluster latency, other|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Calculated|netapp.cluster.statistics.latency.other|
|Cluster latency, read|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric for read I/O operations.</p>|Calculated|netapp.cluster.statistics.latency.read|
|Cluster latency, write|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric for write I/O operations.</p>|Calculated|netapp.cluster.statistics.latency.write|
|Cluster latency, total|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Calculated|netapp.cluster.statistics.latency.total|
|Cluster latency raw, other|<p>The raw latency in microseconds observed at the storage object. This can be divided by the raw IOPS value to calculate the average latency per I/O operation. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.cluster.statistics.latency_raw.other<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.latency_raw.other`</p></li></ul>|
|Cluster latency raw, read|<p>The raw latency in microseconds observed at the storage object. This can be divided by the raw IOPS value to calculate the average latency per I/O operation. Performance metric for read I/O operations.</p>|Dependent item|netapp.cluster.statistics.latency_raw.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.latency_raw.read`</p></li></ul>|
|Cluster latency raw, write|<p>The raw latency in microseconds observed at the storage object. This can be divided by the raw IOPS value to calculate the average latency per I/O operation. Performance metric for write I/O operations.</p>|Dependent item|netapp.cluster.statistics.latency_raw.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.latency_raw.write`</p></li></ul>|
|Cluster latency raw, total|<p>The raw latency in microseconds observed at the storage object. This can be divided by the raw IOPS value to calculate the average latency per I/O operation. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.cluster.statistics.latency_raw.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.latency_raw.total`</p></li></ul>|
|Cluster IOPS raw, other|<p>The number of I/O operations observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.cluster.statistics.iops_raw.other<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.other`</p></li></ul>|
|Cluster IOPS raw, read|<p>The number of I/O operations observed at the storage object. Performance metric for read I/O operations.</p>|Dependent item|netapp.cluster.statistics.iops_raw.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.read`</p></li></ul>|
|Cluster IOPS raw, write|<p>The number of I/O operations observed at the storage object. Performance metric for write I/O operations.</p>|Dependent item|netapp.cluster.statistics.iops_raw.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.write`</p></li></ul>|
|Cluster IOPS raw, total|<p>The number of I/O operations observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.cluster.statistics.iops_raw.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statistics.iops_raw.total`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: Version has changed|<p>The NetApp AFF A700 version has changed. Acknowledge to close the problem manually.</p>|`last(/NetApp AFF A700 by HTTP/netapp.cluster.version,#1)<>last(/NetApp AFF A700 by HTTP/netapp.cluster.version,#2) and length(last(/NetApp AFF A700 by HTTP/netapp.cluster.version))>0`|Info|**Manual close**: Yes|
|NetApp AFF A700: Cluster status is abnormal|<p>Any errors associated with the sample. For example, if the aggregation of data over multiple nodes fails then any of the partial errors might be returned, “ok” on success, or “error” on any internal uncategorized failure. Whenever a sample collection is missed but done at a later time, it is back filled to the previous 15 second timestamp and tagged with "backfilled_data". “Inconsistent_ delta_time” is encountered when the time between two collections is not the same for all nodes. Therefore, the aggregated value might be over or under inflated. “Negative_delta” is returned when an expected monotonically increasing value has decreased in value. “Inconsistent_old_data” is returned when one or more nodes do not have the latest data.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.cluster.status)<>"ok")`|Average||

### LLD rule Nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nodes discovery||HTTP agent|netapp.nodes.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#NODENAME}: Software version|<p>This returns the cluster version information. When the cluster has more than one node, the cluster version is equivalent to the lowest of generation, major, and minor versions on all nodes.</p>|Dependent item|netapp.node.version[{#NODENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#NODENAME}')].version.full.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NODENAME}: Location|<p>The location of the node.</p>|Dependent item|netapp.nodes.location[{#NODENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#NODENAME}')].location.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NODENAME}: State|<p>State of the node:</p><p>up - Node is up and operational.</p><p>booting - Node is booting up.</p><p>down - Node has stopped or is dumping core.</p><p>taken_over - Node has been taken over by its HA partner and is not yet waiting for giveback.</p><p>waiting_for_giveback - Node has been taken over by its HA partner and is waiting for the HA partner to giveback disks.</p><p>degraded - Node has one or more critical services offline.</p><p>unknown - Node or its HA partner cannot be contacted and there is no information on the node's state.</p>|Dependent item|netapp.nodes.state[{#NODENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#NODENAME}')].state.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NODENAME}: Membership|<p>Possible values:</p><p>  available - If a node is available, this means it is detected on the internal cluster network and can be added to the cluster. Nodes that have a membership of “available” are not returned when a GET request is called when the cluster exists. A query on the “membership” property for available must be provided to scan for nodes on the cluster network. Nodes that have a membership of “available” are returned automatically before a cluster is created.</p><p>  joining - Joining nodes are in the process of being added to the cluster. The node may be progressing through the steps to become a member or might have failed. The job to add the node or create the cluster provides details on the current progress of the node.</p><p>  member - Nodes that are members have successfully joined the cluster.</p>|Dependent item|netapp.nodes.membership[{#NODENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#NODENAME}')].membership.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NODENAME}: Uptime|<p>The total time, in seconds, that the node has been up.</p>|Dependent item|netapp.nodes.uptime[{#NODENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#NODENAME}')].uptime.first()`</p></li></ul>|
|{#NODENAME}: Controller over temperature|<p>Specifies whether the hardware is currently operating outside of its recommended temperature range. The hardware shuts down if the temperature exceeds critical thresholds. Possible values: over, normal</p>|Dependent item|netapp.nodes.controller.over_temperature[{#NODENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Nodes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#NODENAME}: Version has changed|<p>{#NODENAME} version has changed. Acknowledge to close the problem manually.</p>|`last(/NetApp AFF A700 by HTTP/netapp.node.version[{#NODENAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.node.version[{#NODENAME}],#2) and length(last(/NetApp AFF A700 by HTTP/netapp.node.version[{#NODENAME}]))>0`|Info|**Manual close**: Yes|
|NetApp AFF A700: {#NODENAME}: Node state is abnormal|<p>The state of the node is different from up:<br>booting - Node is booting up.<br>down - Node has stopped or is dumping core.<br>taken_over - Node has been taken over by its HA partner and is not yet waiting for giveback.<br>waiting_for_giveback - Node has been taken over by its HA partner and is waiting for the HA partner to giveback disks.<br>degraded - Node has one or more critical services offline.<br>unknown - Node or its HA partner cannot be contacted and there is no information on the node's state.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.nodes.state[{#NODENAME}])<>"up")`|Average||
|NetApp AFF A700: {#NODENAME}: Node has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/NetApp AFF A700 by HTTP/netapp.nodes.uptime[{#NODENAME}])<10m`|Info|**Manual close**: Yes|
|NetApp AFF A700: {#NODENAME}: Node has over temperature|<p>The hardware shuts down if the temperature exceeds critical thresholds(item's value is "over").</p>|`(last(/NetApp AFF A700 by HTTP/netapp.nodes.controller.over_temperature[{#NODENAME}])<>"normal")`|Average||

### LLD rule Ethernet ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ethernet ports discovery||HTTP agent|netapp.ports.ether.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Ethernet ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ETHPORTNAME}: State|<p>The operational state of the port. Possible values: up, down.</p>|Dependent item|netapp.port.eth.state[{#NODENAME},{#ETHPORTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#ETHPORTNAME}')].state.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Ethernet ports discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#ETHPORTNAME}: Ethernet port of the Node "{#NODENAME}" is down|<p>Something is wrong with the ethernet port.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.port.eth.state[{#NODENAME},{#ETHPORTNAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.port.eth.state[{#NODENAME},{#ETHPORTNAME}],#2) and last(/NetApp AFF A700 by HTTP/netapp.port.eth.state[{#NODENAME},{#ETHPORTNAME}])="down")`|Average|**Manual close**: Yes|

### LLD rule FC ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FC ports discovery||HTTP agent|netapp.ports.fc.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for FC ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FCPORTNAME}: Description|<p>A description of the FC port.</p>|Dependent item|netapp.port.fc.description[{#NODENAME},{#FCPORTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#FCPORTNAME}')].description.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#FCPORTNAME}: State|<p>The operational state of the FC port. Possible values:</p><p>startup - The port is booting up.</p><p>link_not_connected - The port has finished initialization, but a link with the fabric is not established.</p><p>online - The port is initialized and a link with the fabric has been established.</p><p>link_disconnected - The link was present at one point on this port but is currently not established.</p><p>offlined_by_user - The port is administratively disabled.</p><p>offlined_by_system - The port is set to offline by the system. This happens when the port encounters too many errors.</p><p>node_offline - The state information for the port cannot be retrieved. The node is offline or inaccessible.</p>|Dependent item|netapp.port.fc.state[{#NODENAME},{#FCPORTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#FCPORTNAME}')].state.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FC ports discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#FCPORTNAME}: FC port of the Node "{#NODENAME}" has state different from "online"|<p>Something is wrong with the FC port.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.port.fc.state[{#NODENAME},{#FCPORTNAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.port.fc.state[{#NODENAME},{#FCPORTNAME}],#2) and last(/NetApp AFF A700 by HTTP/netapp.port.fc.state[{#NODENAME},{#FCPORTNAME}])<>"online")`|Average|**Manual close**: Yes|

### LLD rule Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disks discovery||HTTP agent|netapp.disks.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISKNAME}: State|<p>The state of the disk. Possible values: broken, copy, maintenance, partner, pending, present, reconstructing, removed, spare, unfail, zeroing</p>|Dependent item|netapp.disk.state[{#NODENAME},{#DISKNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#DISKNAME}: Disk of the Node "{#NODENAME}" has state different from "present"|<p>Something is wrong with the disk.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.disk.state[{#NODENAME},{#DISKNAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.disk.state[{#NODENAME},{#DISKNAME}],#2) and last(/NetApp AFF A700 by HTTP/netapp.disk.state[{#NODENAME},{#DISKNAME}])<>"present")`|Average|**Manual close**: Yes|

### LLD rule Chassis discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis discovery||HTTP agent|netapp.chassis.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Chassis discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ID}: State|<p>The chassis state: ok, error.</p>|Dependent item|netapp.chassis.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.id=='{#ID}')].state.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Chassis discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#ID}: Chassis has something errors|<p>Something is wrong with the chassis.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.chassis.state[{#ID}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.chassis.state[{#ID}],#2) and last(/NetApp AFF A700 by HTTP/netapp.chassis.state[{#ID}])="error")`|Average|**Manual close**: Yes|

### LLD rule FRUs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FRUs discovery||Dependent item|netapp.frus.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for FRUs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FRUID}: State|<p>The FRU state: ok, error.</p>|Dependent item|netapp.chassis.fru.state[{#CHASSISID},{#FRUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FRUs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#FRUID}: FRU of the chassis "{#ID}" state is error|<p>Something is wrong with the FRU.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.chassis.fru.state[{#CHASSISID},{#FRUID}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.chassis.fru.state[{#CHASSISID},{#FRUID}],#2) and last(/NetApp AFF A700 by HTTP/netapp.chassis.fru.state[{#CHASSISID},{#FRUID}])="error")`|Average|**Manual close**: Yes|

### LLD rule SVMs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SVMs discovery||HTTP agent|netapp.svms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for SVMs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SVMNAME}: State|<p>SVM state: starting, running, stopping, stopped, deleting.</p>|Dependent item|netapp.svm.state[{#SVMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#SVMNAME}')].state.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SVMNAME}: Comment|<p>The comment for the SVM.</p>|Dependent item|netapp.svm.comment[{#SVMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#SVMNAME}')].comment.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for SVMs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#SVMNAME}: SVM state is abnormal|<p>Something is wrong with the SVM.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.svm.state[{#SVMNAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.svm.state[{#SVMNAME}],#2) and last(/NetApp AFF A700 by HTTP/netapp.svm.state[{#SVMNAME}])<>"running")`|Average|**Manual close**: Yes|

### LLD rule LUNs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|LUNs discovery||HTTP agent|netapp.luns.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for LUNs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#LUNNAME}: State|<p>The state of the LUN. Normal states for a LUN are online and offline. Other states indicate errors. Possible values: foreign_lun_error, nvfail, offline, online, space_error.</p>|Dependent item|netapp.lun.status.state[{#SVMNAME},{#LUNNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#LUNNAME}: Container state|<p>The state of the volume and aggregate that contain the LUN: online, aggregate_offline, volume_offline. LUNs are only available when their containers are available.</p>|Dependent item|netapp.lun.status.container_state[{#SVMNAME},{#LUNNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#LUNNAME}: Space size|<p>The total provisioned size of the LUN.</p>|Dependent item|netapp.lun.space.size[{#SVMNAME},{#LUNNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#LUNNAME}: Space used|<p>The amount of space consumed by the main data stream of the LUN.</p>|Dependent item|netapp.lun.space.used[{#SVMNAME},{#LUNNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for LUNs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#LUNNAME}: LUN of the SVM "{#SVMNAME}" has abnormal state|<p>Normal states for a LUN are online and offline. Other states indicate errors.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.lun.status.state[{#SVMNAME},{#LUNNAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.lun.status.state[{#SVMNAME},{#LUNNAME}],#2) and last(/NetApp AFF A700 by HTTP/netapp.lun.status.state[{#SVMNAME},{#LUNNAME}])<>"online")`|Average|**Manual close**: Yes|
|NetApp AFF A700: {#LUNNAME}: LUN of the SVM "{#SVMNAME}" has abnormal container state|<p>LUNs are only available when their containers are available.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.lun.status.container_state[{#SVMNAME},{#LUNNAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.lun.status.container_state[{#SVMNAME},{#LUNNAME}],#2) and last(/NetApp AFF A700 by HTTP/netapp.lun.status.container_state[{#SVMNAME},{#LUNNAME}])<>"online")`|Average|**Manual close**: Yes|

### LLD rule Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volumes discovery||HTTP agent|netapp.volumes.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#VOLUMENAME}: Comment|<p>A comment for the volume.</p>|Dependent item|netapp.volume.comment[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#VOLUMENAME}')].comment.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VOLUMENAME}: State|<p>Volume state. A volume can only be brought online if it is offline. Taking a volume offline removes its junction path. The 'mixed' state applies to FlexGroup volumes only and cannot be specified as a target state. An 'error' state implies that the volume is not in a state to serve data.</p>|Dependent item|netapp.volume.state[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#VOLUMENAME}')].state.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VOLUMENAME}: Type|<p>Type of the volume.</p><p>rw - read-write volume.</p><p>dp - data-protection volume.</p><p>ls - load-sharing dp volume.</p>|Dependent item|netapp.volume.type[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#VOLUMENAME}')].type.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VOLUMENAME}: SVM name|<p>The volume belongs this SVM.</p>|Dependent item|netapp.volume.svm_name[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#VOLUMENAME}')].svm.name.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VOLUMENAME}: Space size|<p>Total provisioned size. The default size is equal to the minimum size of 20MB, in bytes.</p>|Dependent item|netapp.volume.space_size[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#VOLUMENAME}')].space.size.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VOLUMENAME}: Available size|<p>The available space, in bytes.</p>|Dependent item|netapp.volume.space_available[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VOLUMENAME}: Used size|<p>The virtual space used (includes volume reserves) before storage efficiency, in bytes.</p>|Dependent item|netapp.volume.space_used[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.records[?(@.name=='{#VOLUMENAME}')].space.used.first()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VOLUMENAME}: Volume throughput, other rate|<p>Throughput bytes observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.volume.statistics.throughput.other.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume throughput, read rate|<p>Throughput bytes observed at the storage object. Performance metric for read I/O operations.</p>|Dependent item|netapp.volume.statistics.throughput.read.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume throughput, write rate|<p>Throughput bytes observed at the storage object. Performance metric for write I/O operations.</p>|Dependent item|netapp.volume.statistics.throughput.write.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume throughput, total rate|<p>Throughput bytes observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.volume.statistics.throughput.total.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume IOPS, other rate|<p>The number of I/O operations observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.volume.statistics.iops.other.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume IOPS, read rate|<p>The number of I/O operations observed at the storage object. Performance metric for read I/O operations.</p>|Dependent item|netapp.volume.statistics.iops.read.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume IOPS, write rate|<p>The number of I/O operations observed at the storage object. Performance metric for write I/O operations.</p>|Dependent item|netapp.volume.statistics.iops.write.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume IOPS, total rate|<p>The number of I/O operations observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.volume.statistics.iops.total.rate[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#VOLUMENAME}: Volume latency, other|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Calculated|netapp.volume.statistics.latency.other[{#VOLUMENAME}]|
|{#VOLUMENAME}: Volume latency, read|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric for read I/O operations.</p>|Calculated|netapp.volume.statistics.latency.read[{#VOLUMENAME}]|
|{#VOLUMENAME}: Volume latency, write|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric for write I/O operations.</p>|Calculated|netapp.volume.statistics.latency.write[{#VOLUMENAME}]|
|{#VOLUMENAME}: Volume latency, total|<p>The average latency per I/O operation in milliseconds observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Calculated|netapp.volume.statistics.latency.total[{#VOLUMENAME}]|
|{#VOLUMENAME}: Volume latency raw, other|<p>The raw latency in microseconds observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.volume.statistics.latency_raw.other[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#VOLUMENAME}: Volume latency raw, read|<p>The raw latency in microseconds observed at the storage object. Performance metric for read I/O operations.</p>|Dependent item|netapp.volume.statistics.latency_raw.read[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#VOLUMENAME}: Volume latency raw, write|<p>The raw latency in microseconds observed at the storage object. Performance metric for write I/O operations.</p>|Dependent item|netapp.volume.statistics.latency_raw.write[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#VOLUMENAME}: Volume latency raw, total|<p>The raw latency in microseconds observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.volume.statistics.latency_raw.total[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#VOLUMENAME}: Volume IOPS raw, other|<p>The number of I/O operations observed at the storage object. Performance metric for other I/O operations. Other I/O operations can be metadata operations, such as directory lookups and so on.</p>|Dependent item|netapp.volume.statistics.iops_raw.other[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#VOLUMENAME}: Volume IOPS raw, read|<p>The number of I/O operations observed at the storage object. Performance metric for read I/O operations.</p>|Dependent item|netapp.volume.statistics.iops_raw.read[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#VOLUMENAME}: Volume IOPS raw, write|<p>The number of I/O operations observed at the storage object. Performance metric for write I/O operations.</p>|Dependent item|netapp.volume.statistics.iops_raw.write[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#VOLUMENAME}: Volume IOPS raw, total|<p>The number of I/O operations observed at the storage object. Performance metric aggregated over all types of I/O operations.</p>|Dependent item|netapp.volume.statistics.iops_raw.total[{#VOLUMENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Volumes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp AFF A700: {#VOLUMENAME}: Volume state is abnormal|<p>A volume can only be brought online if it is offline. Taking a volume offline removes its junction path. The 'mixed' state applies to FlexGroup volumes only and cannot be specified as a target state. An 'error' state implies that the volume is not in a state to serve data.</p>|`(last(/NetApp AFF A700 by HTTP/netapp.volume.state[{#VOLUMENAME}],#1)<>last(/NetApp AFF A700 by HTTP/netapp.volume.state[{#VOLUMENAME}],#2) and last(/NetApp AFF A700 by HTTP/netapp.volume.state[{#VOLUMENAME}])<>"online")`|Average|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

