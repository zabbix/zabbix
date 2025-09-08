
# Nutanix Prism Element by HTTP

## Overview

This template is designed for the effortless deployment of Nutanix Prism Element monitoring and doesn't require any external scripts.

The templates "Nutanix Host Prism Element by HTTP" and "Nutanix Cluster Prism Element by HTTP" can be used in discovery, as well as manually linked to a host.

More details can be found in the official documentation:
- [on the Nutanix Prism Element REST API](https://www.nutanix.dev/api_reference/apis/prism_v2.html);
- [on differences between Nutanix API versions](https://www.nutanix.dev/api-versions/).

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Nutanix Prism Element 6.5.5.7 (API v2.0)

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a new Nutanix user and add the role "Viewer"
2. Create a new host
3. Link the template to host created earlier
4. Set the host macros (on the host or template level) required for getting data:
```text
{$NUTANIX.PRISM.ELEMENT.IP}
{$NUTANIX.PRISM.ELEMENT.PORT}
```
5. Set the host macros (on the host or template level) with the login and password of the Nutanix user created earlier:
```text
{$NUTANIX.USER}
{$NUTANIX.PASSWORD}
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NUTANIX.PRISM.ELEMENT.IP}|<p>Set the Nutanix API IP here.</p>||
|{$NUTANIX.PRISM.ELEMENT.PORT}|<p>Set the Nutanix API port here.</p>|`9440`|
|{$NUTANIX.USER}|<p>Nutanix API username.</p>||
|{$NUTANIX.PASSWORD}|<p>Nutanix API password.</p>||
|{$NUTANIX.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$NUTANIX.CLUSTER.DISCOVERY.NAME.MATCHES}|<p>Filter of discoverable Nutanix clusters by name.</p>|`.*`|
|{$NUTANIX.CLUSTER.DISCOVERY.NAME.NOT_MATCHES}|<p>Filter to exclude discovered Nutanix clusters by name.</p>|`CHANGE_IF_NEEDED`|
|{$NUTANIX.HOST.DISCOVERY.NAME.MATCHES}|<p>Filter of discoverable Nutanix hosts by name.</p>|`.*`|
|{$NUTANIX.HOST.DISCOVERY.NAME.NOT_MATCHES}|<p>Filter to exclude discovered Nutanix hosts by name.</p>|`CHANGE_IF_NEEDED`|
|{$NUTANIX.STORAGE.CONTAINER.DISCOVERY.NAME.MATCHES}|<p>Filter of discoverable storage containers by name.</p>|`.*`|
|{$NUTANIX.STORAGE.CONTAINER.DISCOVERY.NAME.NOT_MATCHES}|<p>Filter to exclude discovered storage containers by name.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get cluster|<p>Get the available clusters.</p>|Script|nutanix.cluster.get|
|Get cluster check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.cluster.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get host|<p>Get the available hosts.</p>|Script|nutanix.host.get|
|Get host check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.host.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get storage container|<p>Get the available storage containers.</p>|Script|nutanix.storage.container.get|
|Get storage container check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.storage.container.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nutanix: Failed to get cluster data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Prism Element by HTTP/nutanix.cluster.get.check))>0`|High||
|Nutanix: Failed to get host data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Prism Element by HTTP/nutanix.host.get.check))>0`|High||
|Nutanix: Failed to get storage container data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Prism Element by HTTP/nutanix.storage.container.get.check))>0`|High||

### LLD rule Cluster discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster discovery|<p>Discovery of all clusters.</p>|Dependent item|nutanix.cluster.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.entities`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Host discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host discovery|<p>Discovery of all hosts.</p>|Dependent item|nutanix.host.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.entities`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Storage container discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage container discovery|<p>Discovery of all storage containers.</p>|Dependent item|nutanix.storage.container.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Storage container discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Container [{#STORAGE.CONTAINER.NAME}]: Space: Total, bytes|<p>The total space of the storage container.</p>|Dependent item|nutanix.storage.container.capacity.bytes["{#STORAGE.CONTAINER.UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Container [{#STORAGE.CONTAINER.NAME}]: Space: Free, bytes|<p>The free space of the storage container.</p>|Dependent item|nutanix.storage.container.free.bytes["{#STORAGE.CONTAINER.UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Container [{#STORAGE.CONTAINER.NAME}]: Replication factor|<p>The replication factor of the storage container.</p>|Dependent item|nutanix.storage.container.replication.factor["{#STORAGE.CONTAINER.UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#STORAGE.CONTAINER.UUID}'].replication_factor`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Container [{#STORAGE.CONTAINER.NAME}]: Space: Used, bytes|<p>The used space of the storage container.</p>|Dependent item|nutanix.storage.container.usage.bytes["{#STORAGE.CONTAINER.UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

# Nutanix Cluster Prism Element by HTTP

## Overview

This template is designed for the effortless deployment of Nutanix Cluster Prism Element monitoring and doesn't require any external scripts.

This template can be used in discovery, as well as manually linked to a host - to do so, attach it to the host and manually set the value of the `{$NUTANIX.CLUSTER.UUID}` macro.

More details can be found in the official documentation:
- on retrieving [UUIDs](https://www.nutanixbible.com/19b-cli.html);
- on the [Nutanix Prism Element REST API](https://www.nutanix.dev/api_reference/apis/prism_v2.html);
- on differences between [Nutanix API versions](https://www.nutanix.dev/api-versions/).

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Nutanix Prism Element 6.5.5.7 (API v2.0)

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a new Nutanix user and add the role "Viewer"
2. Create a new host
3. Link the template to the host created earlier
4. Set the host macros (on the host or template level) required for getting data:
```text
{$NUTANIX.PRISM.ELEMENT.IP}
{$NUTANIX.PRISM.ELEMENT.PORT}
```
5. Set the host macros (on the host or template level) with the login and password of the Nutanix user created earlier:
```text
{$NUTANIX.USER}
{$NUTANIX.PASSWORD}
```
6. Set the host macros (on the host or template level) with the UUID of the Nutanix Cluster:
```text
{$NUTANIX.CLUSTER.UUID}
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NUTANIX.PRISM.ELEMENT.IP}|<p>Set the Nutanix API IP here.</p>||
|{$NUTANIX.PRISM.ELEMENT.PORT}|<p>Set the Nutanix API port here.</p>|`9440`|
|{$NUTANIX.USER}|<p>Nutanix API username.</p>||
|{$NUTANIX.PASSWORD}|<p>Nutanix API password.</p>||
|{$NUTANIX.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$NUTANIX.CLUSTER.UUID}|<p>UUID of the cluster.</p>||
|{$NUTANIX.TIMEOUT}|<p>API response timeout.</p>|`10s`|
|{$NUTANIX.ALERT.DISCOVERY.NAME.MATCHES}|<p>Filter of discoverable Nutanix alerts by name.</p>|`.*`|
|{$NUTANIX.ALERT.DISCOVERY.NAME.NOT_MATCHES}|<p>Filter to exclude discovered Nutanix alerts by name.</p>|`CHANGE_IF_NEEDED`|
|{$NUTANIX.ALERT.DISCOVERY.STATE.MATCHES}|<p>Filter to exclude discovered Nutanix alerts by state. Set "1" for filtering only problem alerts or "0" for resolved ones.</p>|`.*`|
|{$NUTANIX.ALERT.DISCOVERY.SEVERITY.MATCHES}|<p>Filter to exclude discovered Nutanix alerts by severity. Set all possible severities for filtering in the range 0-2. "0" - Info, "1" - Warning, "2" - Critical.</p>|`.*`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metric|<p>Get data about basic metrics.</p>|Script|nutanix.cluster.metric.get|
|Get metric check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.cluster.metric.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alert|<p>Get data about alerts.</p>|Script|nutanix.cluster.alert.get|
|Get alert check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.cluster.alert.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Content Cache: Hit rate, %|<p>Content cache hits over all lookups.</p>|Dependent item|nutanix.cluster.content.cache.hit.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_hit_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Content Cache: Logical memory usage, bytes|<p>Logical memory used to cache data without deduplication in bytes.</p>|Dependent item|nutanix.cluster.content.cache.logical.memory.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_logical_memory_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Logical saved memory usage, bytes|<p>Memory saved due to content cache deduplication in bytes.</p>|Dependent item|nutanix.cluster.content.cache.saved.memory.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_saved_memory_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Logical SSD usage, bytes|<p>Logical SSD memory used to cache data without deduplication in bytes.</p>|Dependent item|nutanix.cluster.content.cache.logical.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_logical_ssd_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Number of lookups|<p>Number of lookups on the content cache.</p>|Dependent item|nutanix.cluster.content.cache.lookups.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_num_lookups`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Content Cache: Physical memory usage, bytes|<p>Real memory used to cache data via the content cache in bytes.</p>|Dependent item|nutanix.cluster.content.cache.physical.memory.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_physical_memory_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Physical SSD usage, bytes|<p>Real SSD usage used to cache data via the content cache in bytes.</p>|Dependent item|nutanix.cluster.content.cache.physical.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_physical_ssd_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: References|<p>Average number of content cache references.</p>|Dependent item|nutanix.cluster.content.cache.dedup.ref.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_num_dedup_ref_count_pph`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Content Cache: Saved SSD usage, bytes|<p>SSD usage saved due to content cache deduplication in bytes.</p>|Dependent item|nutanix.cluster.content.cache.saved.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_saved_ssd_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Controller: Random IO|<p>The number of random Input/Output operations from the controller.</p>|Dependent item|nutanix.cluster.controller.io.random<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_random_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Controller: Random IO, %|<p>The percentage of random Input/Output from the controller.</p>|Dependent item|nutanix.cluster.controller.io.random.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_random_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Controller: Sequence IO|<p>The number of sequential Input/Output operations from the controller.</p>|Dependent item|nutanix.cluster.controller.io.sequence<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_seq_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Controller: Sequence IO, %|<p>The percentage of sequential Input/Output from the controller.</p>|Dependent item|nutanix.cluster.controller.io.sequence.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_seq_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Storage Controller: Timespan, sec|<p>Controller timespan.</p>|Dependent item|nutanix.cluster.storage.controller.timespan.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_timespan_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Controller: IO total, bytes|<p>Total controller Input/Output size.</p>|Dependent item|nutanix.cluster.storage.controller.io.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: IO total, sec|<p>Total controller Input/Output time.</p>|Dependent item|nutanix.cluster.storage.controller.io.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: IO total read, bytes|<p>Total controller read Input/Output size.</p>|Dependent item|nutanix.cluster.storage.controller.io.read.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: IO total read, sec|<p>Total controller read Input/Output time.</p>|Dependent item|nutanix.cluster.storage.controller.io.read.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_read_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: Cluster operation mode|<p>The cluster operation mode. One of the following:</p><p>- NORMAL;</p><p>- OVERRIDE;</p><p>- READONLY;</p><p>- STANDALONE;</p><p>- SWITCH_TO_TWO_NODE;</p><p>- UNKNOWN.</p>|Dependent item|nutanix.cluster.cluster.operation.mode<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.operation_mode`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Current redundancy factor|<p>Current value of the redundancy factor on the cluster.</p>|Dependent item|nutanix.cluster.redundancy.factor.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cluster_redundancy_state.current_redundancy_factor`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Desired redundancy factor|<p>The desired value of the redundancy factor on the cluster.</p>|Dependent item|nutanix.cluster.redundancy.factor.desired<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cluster_redundancy_state.desired_redundancy_factor`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: IO|<p>The number of Input/Output operations from the disk.</p>|Dependent item|nutanix.cluster.general.io<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: IOPS|<p>Input/Output operations per second from the disk.</p>|Dependent item|nutanix.cluster.general.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: IO, bandwidth|<p>Data transferred in B/sec from the disk.</p>|Dependent item|nutanix.cluster.general.io.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: IO, latency|<p>Input/Output latency from the disk.</p>|Dependent item|nutanix.cluster.general.io.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.avg_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: Random IO|<p>The number of random Input/Output operations.</p>|Dependent item|nutanix.cluster.general.io.random<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_random_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Random IO, %|<p>The percentage of random Input/Output operations.</p>|Dependent item|nutanix.cluster.general.io.random.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.random_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Read IO|<p>Total number of Input/Output read operations.</p>|Dependent item|nutanix.cluster.general.io.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_read_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Read IOPS|<p>Input/Output read operations per second from the disk.</p>|Dependent item|nutanix.cluster.general.iops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_read_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Read IO, %|<p>The total percentage of Input/Output operations that are reads.</p>|Dependent item|nutanix.cluster.general.io.read.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.read_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Read IO, bandwidth|<p>Read data transferred in B/sec from the disk.</p>|Dependent item|nutanix.cluster.general.io.read.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.read_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: Read IO, latency|<p>Average Input/Output read latency.</p>|Dependent item|nutanix.cluster.general.io.read.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.avg_read_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: Sequence IO|<p>The number of sequential Input/Output operations.</p>|Dependent item|nutanix.cluster.general.io.sequence<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_seq_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Sequence IO, %|<p>The percentage of sequential Input/Output.</p>|Dependent item|nutanix.cluster.general.io.sequence.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.seq_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Storage capacity, bytes|<p>Total size of the datastores used by this system in bytes.</p>|Dependent item|nutanix.cluster.general.storage.capacity.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.capacity_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Storage free, bytes|<p>Total free space of the datastores used by this system in bytes.</p>|Dependent item|nutanix.cluster.general.storage.free.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.free_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Storage logical usage, bytes|<p>Total logical space used by the datastores of this system in bytes.</p>|Dependent item|nutanix.cluster.general.storage.logical.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.logical_usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Storage usage, bytes|<p>Total physical datastore space used by this host and all its snapshots on the datastores.</p>|Dependent item|nutanix.cluster.general.storage.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Timespan, sec|<p>Cluster timespan.</p>|Dependent item|nutanix.cluster.general.timespan.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.timespan_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: IO total, sec|<p>Total time of Input/Output operations.</p>|Dependent item|nutanix.cluster.general.io.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: IO total, bytes|<p>Total size of Input/Output operations.</p>|Dependent item|nutanix.cluster.general.io.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: IO total read, sec|<p>Total time of Input/Output read operations.</p>|Dependent item|nutanix.cluster.general.io.read.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_read_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: IO total read, bytes|<p>Total size of Input/Output read operations.</p>|Dependent item|nutanix.cluster.general.io.read.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: Total transformed usage, bytes|<p>Actual usage of storage.</p>|Dependent item|nutanix.cluster.general.transformed.usage.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_transformed_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Total untransformed usage, bytes|<p>Logical usage of storage (physical usage divided by the replication factor).</p>|Dependent item|nutanix.cluster.general.untransformed.usage.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_untransformed_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Upgrade progress|<p>Indicates whether the cluster is currently in an update state.</p>|Dependent item|nutanix.cluster.general.upgrade.progress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_upgrade_in_progress`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Version|<p>Current software version in the cluster.</p>|Dependent item|nutanix.cluster.general.upgrade.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|General: Write IO|<p>Input/Output write operations from the disk.</p>|Dependent item|nutanix.cluster.general.io.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_write_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Write IOPS|<p>Total number of Input/Output write operations per second.</p>|Dependent item|nutanix.cluster.general.iops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_write_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Write IO, %|<p>Total percentage of Input/Output operations that are writes.</p>|Dependent item|nutanix.cluster.general.io.write.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.write_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Write IO, bandwidth|<p>Write data transferred in B/sec from the disk.</p>|Dependent item|nutanix.cluster.general.io.write.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.write_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: Write IO, latency|<p>Average Input/Output write operation latency.</p>|Dependent item|nutanix.cluster.general.io.write.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.avg_write_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: CPU usage, %|<p>Percentage of CPU used by the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.cpu.usage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_cpu_usage_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Hypervisor: IOPS|<p>Input/Output operations per second from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: IO, bandwidth|<p>Data transferred in B/sec from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: IO, latency|<p>Input/Output operation latency from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_avg_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: Memory usage, %|<p>Percentage of memory used by the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.memory.usage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_memory_usage_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Hypervisor: IO|<p>The number of Input/Output operations from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Read IO|<p>The number of Input/Output read operations from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_read_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Read IOPS|<p>Input/Output read operations per second from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.iops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_read_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Read IO, bandwidth|<p>Read data transferred in B/sec from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.read.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_read_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: Read IO, latency|<p>Input/Output read latency from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.read.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_avg_read_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: Timespan, sec|<p>Hypervisor timespan.</p>|Dependent item|nutanix.cluster.hypervisor.timespan.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_timespan_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: IO total, sec|<p>Total Input/Output operation time from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: IO total, bytes|<p>Total Input/Output operation size from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: IO total read, bytes|<p>Total Input/Output read operation size from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.read.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: IO total read, sec|<p>Total Input/Output read operation time from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.read.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_read_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: Write IOPS|<p>Input/Output write operations per second from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.iops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_write_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Write IO|<p>Input/Output write operations from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_write_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Write IO, bandwidth|<p>Write data transferred in B/sec from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.write.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_write_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: Write IO, latency|<p>Input/Output write latency from the Hypervisor.</p>|Dependent item|nutanix.cluster.hypervisor.io.write.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_avg_write_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: IOPS|<p>Input/Output operations per second from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: IO|<p>Input/Output operations from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: IO, bandwidth|<p>Data transferred in B/sec from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: IO, latency|<p>Input/Output latency from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: Read IOPS|<p>Input/Output read operations per second from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.iops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_read_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Read IO|<p>Input/Output read operations from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_read_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Read IO, %|<p>Percentage of Input/Output operations from the Storage Controller that are reads.</p>|Dependent item|nutanix.cluster.storage.controller.io.read.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_read_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Storage Controller: Read IO, bandwidth|<p>Read data transferred in B/sec from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.read.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_read_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: Read IO, latency|<p>Input/Output read latency from the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.read.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_read_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: Read IO, bytes|<p>Storage controller average read Input/Output in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.io.read.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: Total transformed usage, bytes|<p>Actual usage of the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.transformed.usage.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_transformed_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Write IO|<p>Input/Output write operations to the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_write_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Write IOPS|<p>Input/Output write operations per second to the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.iops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_write_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Write IO, %|<p>Percentage of Input/Output operations to the Storage Controller that are writes.</p>|Dependent item|nutanix.cluster.storage.controller.io.write.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_write_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Storage Controller: Write IO, bandwidth|<p>Write data transferred in B/sec to the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.write.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_write_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: Write IO, latency|<p>Input/Output write latency to the Storage Controller.</p>|Dependent item|nutanix.cluster.storage.controller.io.write.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_write_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: Write IO, bytes|<p>Storage Controller average write Input/Output in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.io.write.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_write_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Tier: Das-sata capacity, bytes|<p>The total capacity of Das-sata in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.tier.das_sata.capacity.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.das-sata.capacity_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: Das-sata free, bytes|<p>The free space of Das-sata in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.tier.das_sata.free.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.das-sata.free_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: Das-sata usage, bytes|<p>The used space of Das-sata in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.tier.das_sata.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.das-sata.usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: SSD capacity, bytes|<p>The total capacity of SSD in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.tier.ssd.capacity.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.ssd.capacity_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: SSD free, bytes|<p>The free space of SSD in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.tier.ssd.free.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.ssd.free_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: SSD usage, bytes|<p>The used space of SSD in bytes.</p>|Dependent item|nutanix.cluster.storage.controller.tier.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.ssd.usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nutanix: Failed to get metric data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Cluster Prism Element by HTTP/nutanix.cluster.metric.get.check))>0`|High||
|Nutanix: Failed to get alert data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Cluster Prism Element by HTTP/nutanix.cluster.alert.get.check))>0`|High||
|Nutanix: Redundancy factor mismatched|<p>Current redundancy factor does not match the desired redundancy factor.</p>|`last(/Nutanix Cluster Prism Element by HTTP/nutanix.cluster.redundancy.factor.current)<>last(/Nutanix Cluster Prism Element by HTTP/nutanix.cluster.redundancy.factor.desired)`|High||

### LLD rule Alert discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alert discovery|<p>Discovery of all alerts.</p><p>Alerts will be grouped by title. For each alert, in addition to the basic information, the number of activation and last alert ID will be available.</p>|Dependent item|nutanix.cluster.alert.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Alert discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alert [{#ALERT.NAME}]: Full title|<p>The full title of the alert.</p>|Dependent item|nutanix.cluster.alert.title["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.title`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Create datetime|<p>The alert creation date and time.</p>|Dependent item|nutanix.cluster.alert.created["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.created`</p></li><li><p>Custom multiplier: `0.000001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Severity|<p>Alert severity. One of the following:</p><p>- Info;</p><p>- Warning;</p><p>- Critical;</p><p>- Unknown.</p>|Dependent item|nutanix.cluster.alert.severity["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.severity`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: State|<p>Alert state. One of the following:</p><p>- OK;</p><p>- Problem.</p>|Dependent item|nutanix.cluster.alert.state["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Detailed message|<p>Detailed information about the current alert.</p>|Dependent item|nutanix.cluster.alert.message["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.message`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Last alert ID|<p>Latest ID of the alert.</p>|Dependent item|nutanix.cluster.alert.last_id["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.last_id`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Count alerts|<p>The number of times this alert was triggered.</p>|Dependent item|nutanix.cluster.alert.count["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

# Nutanix Host Prism Element by HTTP

## Overview

This template is designed for the effortless deployment of Nutanix Host Prism Element monitoring and doesn't require any external scripts.

This template can be used in discovery, as well as manually linked to a host - to do so, attach it to the host and manually set the value of the `{$NUTANIX.HOST.UUID}` macro.

More details can be found in the official documentation:
- on retrieving [UUIDs](https://www.nutanixbible.com/19b-cli.html);
- on the [Nutanix Prism Element REST API](https://www.nutanix.dev/api_reference/apis/prism_v2.html);
- on differences between [Nutanix API versions](https://www.nutanix.dev/api-versions/).

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Nutanix Prism Element 6.5.5.7 (API v2.0)

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a new Nutanix user and add the role "Viewer"
2. Create a new host
3. Link the template to the host created earlier
4. Set the host macros (on the host or template level) required for getting data:
```text
{$NUTANIX.PRISM.ELEMENT.IP}
{$NUTANIX.PRISM.ELEMENT.PORT}
```
5. Set the host macros (on the host or template level) with the login and password of the Nutanix user created earlier:
```text
{$NUTANIX.USER}
{$NUTANIX.PASSWORD}
```
6. Set the host macros (on the host or template level) with the UUID of the Nutanix Host:
```text
{$NUTANIX.HOST.UUID}
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NUTANIX.PRISM.ELEMENT.IP}|<p>Set the Nutanix API IP here.</p>||
|{$NUTANIX.PRISM.ELEMENT.PORT}|<p>Set the Nutanix API port here.</p>|`9440`|
|{$NUTANIX.USER}|<p>Nutanix API username.</p>||
|{$NUTANIX.PASSWORD}|<p>Nutanix API password.</p>||
|{$NUTANIX.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$NUTANIX.HOST.UUID}|<p>UUID of the host.</p>||
|{$NUTANIX.TIMEOUT}|<p>API response timeout.</p>|`10s`|
|{$NUTANIX.ALERT.DISCOVERY.NAME.MATCHES}|<p>Filter of discoverable Nutanix alerts by name.</p>|`.*`|
|{$NUTANIX.ALERT.DISCOVERY.NAME.NOT_MATCHES}|<p>Filter to exclude discovered Nutanix alerts by name.</p>|`CHANGE_IF_NEEDED`|
|{$NUTANIX.ALERT.DISCOVERY.STATE.MATCHES}|<p>Filter to exclude discovered Nutanix alerts by state. Set "1" for filtering only problem alerts or "0" for resolved ones.</p>|`.*`|
|{$NUTANIX.ALERT.DISCOVERY.SEVERITY.MATCHES}|<p>Filter to exclude discovered Nutanix alerts by severity. Set all possible severities for filtering in the range 0-2. "0" - Info, "1" - Warning, "2" - Critical.</p>|`.*`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metric|<p>Get data about basic metrics.</p>|Script|nutanix.host.metric.get|
|Get metric check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.host.metric.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get disk|<p>Get data about installed disks.</p>|Script|nutanix.host.disk.get|
|Get disk check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.host.disk.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alert|<p>Get data about alerts.</p>|Script|nutanix.host.alert.get|
|Get alert check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|nutanix.host.alert.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Content Cache: Hit rate, %|<p>Content cache hits over all lookups.</p>|Dependent item|nutanix.host.content.cache.hit.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_hit_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Content Cache: Logical memory usage, bytes|<p>Logical memory used to cache data without deduplication in bytes.</p>|Dependent item|nutanix.host.content.cache.logical.memory.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_logical_memory_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Logical saved memory usage, bytes|<p>Memory saved due to content cache deduplication in bytes.</p>|Dependent item|nutanix.host.content.cache.saved.memory.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_saved_memory_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Logical SSD usage, bytes|<p>Logical SSD memory used to cache data without deduplication in bytes.</p>|Dependent item|nutanix.host.content.cache.logical.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_logical_ssd_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Number of lookups|<p>Number of lookups on the content cache.</p>|Dependent item|nutanix.host.content.cache.lookups.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_num_lookups`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Content Cache: Physical memory usage, bytes|<p>Real memory used to cache data via the content cache in bytes.</p>|Dependent item|nutanix.host.content.cache.physical.memory.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_physical_memory_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: Physical SSD usage, bytes|<p>Real SSD usage used to cache data via the content cache in bytes.</p>|Dependent item|nutanix.host.content.cache.physical.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_physical_ssd_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Content Cache: References|<p>Average number of content cache references.</p>|Dependent item|nutanix.host.content.cache.dedup.ref.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_num_dedup_ref_count_pph`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Content Cache: Saved SSD usage, bytes|<p>SSD usage saved due to content cache deduplication in bytes.</p>|Dependent item|nutanix.host.content.cache.saved.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.content_cache_saved_ssd_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Controller: Random IO|<p>The number of random Input/Output operations from the controller.</p>|Dependent item|nutanix.host.controller.io.random<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_random_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Controller: Random IO, %|<p>The percentage of random Input/Output from the controller.</p>|Dependent item|nutanix.host.controller.io.random.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_random_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Controller: Sequence IO|<p>The number of sequential Input/Output operations from the controller.</p>|Dependent item|nutanix.host.controller.io.sequence<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_seq_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Controller: Sequence IO, %|<p>The percentage of sequential Input/Output from the controller.</p>|Dependent item|nutanix.host.controller.io.sequence.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_seq_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Storage Controller: Timespan, sec|<p>Controller timespan.</p>|Dependent item|nutanix.host.storage.controller.timespan.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_timespan_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Controller: IO total, bytes|<p>Total controller Input/Output size.</p>|Dependent item|nutanix.host.storage.controller.io.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: IO total, sec|<p>Total controller Input/Output time.</p>|Dependent item|nutanix.host.storage.controller.io.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: IO total read, bytes|<p>Total controller read Input/Output size.</p>|Dependent item|nutanix.host.storage.controller.io.read.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: IO total read, sec|<p>Total controller read Input/Output time.</p>|Dependent item|nutanix.host.storage.controller.io.read.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_read_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: Boot time|<p>The last host boot time.</p>|Dependent item|nutanix.host.general.boot.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.boot_time_in_usecs`</p></li><li><p>Custom multiplier: `1.0E-6`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: CPU frequency|<p>The processor frequency.</p>|Dependent item|nutanix.host.general.cpu.frequency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_frequency_in_hz`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: CPU model|<p>The processor model.</p>|Dependent item|nutanix.host.general.cpu.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_model`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|General: Host state|<p>Displays the host state. One of the following:</p><p>- NEW;</p><p>- NORMAL;</p><p>- MARKED_FOR_REMOVAL_BUT_NOT_DETACHABLE;</p><p>- DETACHABLE.</p>|Dependent item|nutanix.host.general.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|General: Host type|<p>Displays the host type. One of the following:</p><p>- HYPER_CONVERGED;</p><p>- COMPUTE_ONLY.</p>|Dependent item|nutanix.host.general.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.host_type`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: IOPS|<p>Input/Output operations per second from the disk.</p>|Dependent item|nutanix.host.general.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: IO|<p>The number of Input/Output operations from the disk.</p>|Dependent item|nutanix.host.general.io<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_io`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: IO, bandwidth|<p>Data transferred in B/sec from the disk.</p>|Dependent item|nutanix.host.general.io.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: IO, latency|<p>Input/Output latency from the disk.</p>|Dependent item|nutanix.host.general.io.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.avg_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: Degrade status|<p>Indicates whether the host is in a degraded state. One of the following:</p><p>- Normal;</p><p>- Degraded;</p><p>- Unknown.</p>|Dependent item|nutanix.host.general.degraded<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_degraded`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Maintenance mode|<p>Indicates whether the host is in maintenance mode. One of the following:</p><p>- Normal;</p><p>- Maintenance;</p><p>- Unknown.</p>|Dependent item|nutanix.host.general.maintenance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.host_in_maintenance_mode`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Number of virtual machines|<p>Number of virtual machines running on this host.</p>|Dependent item|nutanix.host.general.vms.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_vms`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Random IO|<p>The number of random Input/Output operations.</p>|Dependent item|nutanix.host.general.io.random<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_random_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Random IO, %|<p>The percentage of random Input/Output.</p>|Dependent item|nutanix.host.general.io.random.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.random_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Read IO|<p>Input/Output read operations from the disk.</p>|Dependent item|nutanix.host.general.io.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_read_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Read IOPS|<p>Total number of Input/Output read operations per second.</p>|Dependent item|nutanix.host.general.iops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_read_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Read IO, %|<p>The total percentage of Input/Output operations that are reads.</p>|Dependent item|nutanix.host.general.io.read.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.read_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Read IO, bandwidth|<p>Read data transferred in B/sec from the disk.</p>|Dependent item|nutanix.host.general.io.read.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.read_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: Read IO, latency|<p>Average Input/Output read latency.</p>|Dependent item|nutanix.host.general.io.read.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.avg_read_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: Reboot pending|<p>Indicates whether the host is pending to reboot.</p>|Dependent item|nutanix.host.general.reboot<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reboot_pending`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Sequence IO|<p>The number of sequential Input/Output operations.</p>|Dependent item|nutanix.host.general.io.sequence<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_seq_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Sequence IO, %|<p>The percentage of sequential Input/Output.</p>|Dependent item|nutanix.host.general.io.sequence.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.seq_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Storage capacity, bytes|<p>Total size of the datastores used by this system in bytes.</p>|Dependent item|nutanix.host.general.storage.capacity.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.capacity_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Storage free, bytes|<p>Total free space of all the datastores used by this system in bytes.</p>|Dependent item|nutanix.host.general.storage.free.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.free_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Storage logical usage, bytes|<p>Total logical used space by the datastores of this system in bytes.</p>|Dependent item|nutanix.host.general.storage.logical.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.logical_usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Storage usage, bytes|<p>Total physical datastore space used by this host and all its snapshots on the datastores.</p>|Dependent item|nutanix.host.general.storage.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage.usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: Timespan, sec|<p>Host timespan.</p>|Dependent item|nutanix.host.general.timespan.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.timespan_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: Total CPU capacity|<p>Total host CPU capacity in Hz.</p>|Dependent item|nutanix.host.general.cpu.capacity.hz<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_capacity_in_hz`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: IO total, sec|<p>Total time of Input/Output operations.</p>|Dependent item|nutanix.host.general.io.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: IO total, bytes|<p>Total size of Input/Output operations.</p>|Dependent item|nutanix.host.general.io.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: Total memory, bytes|<p>Total host memory in bytes.</p>|Dependent item|nutanix.host.general.memory.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory_capacity_in_bytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|General: IO total read, sec|<p>Total time of Input/Output read operations.</p>|Dependent item|nutanix.host.general.io.read.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_read_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|General: IO total read, bytes|<p>Total size of Input/Output read operations.</p>|Dependent item|nutanix.host.general.io.read.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: Total transformed usage, bytes|<p>Actual usage of storage.</p>|Dependent item|nutanix.host.general.transformed.usage.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_transformed_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Total untransformed usage, bytes|<p>Logical usage of storage (physical usage divided by the replication factor).</p>|Dependent item|nutanix.host.general.untransformed.usage.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.total_untransformed_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Write IO|<p>Total number of Input/Output write operations.</p>|Dependent item|nutanix.host.general.io.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_write_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Write IOPS|<p>Total number of Input/Output operations write per second.</p>|Dependent item|nutanix.host.general.iops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.num_write_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|General: Write IO, %|<p>Total percentage of Input/Output operations that are writes.</p>|Dependent item|nutanix.host.general.io.write.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.write_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|General: Write IO, bandwidth|<p>Write data transferred in B/sec from the disk.</p>|Dependent item|nutanix.host.general.io.write.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.write_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|General: Write IO, latency|<p>Average Input/Output write operation latency.</p>|Dependent item|nutanix.host.general.io.write.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.avg_write_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: CPU usage, %|<p>Percentage of CPU used by the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.cpu.usage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_cpu_usage_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Hypervisor: Full name|<p>Full name of the Hypervisor running on the host.</p>|Dependent item|nutanix.host.hypervisor.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hypervisor_full_name`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Hypervisor: IOPS|<p>Input/Output operations per second from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: IO, bandwidth|<p>Data transferred in B/sec from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: IO, latency|<p>Input/Output operation latency from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_avg_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: Memory usage, %|<p>Percentage of memory used by the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.memory.usage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_memory_usage_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Hypervisor: IO|<p>The number of Input/Output operations from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Read IOPS|<p>The number of Input/Output read operations from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.iops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_read_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Read IO|<p>Input/Output read operations per second from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_read_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Read IO, bandwidth|<p>Read data transferred in B/sec from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.read.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_read_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: Read IO, latency|<p>Input/Output read latency from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.read.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_avg_read_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: Received, bytes|<p>Bytes received over the network reported by the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.received.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_received_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Hypervisor: Timespan, sec|<p>Hypervisor timespan.</p>|Dependent item|nutanix.host.hypervisor.timespan.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_timespan_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: IO total, sec|<p>Total Input/Output operation time from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: IO total, bytes|<p>Total Input/Output operation size from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: IO total read, bytes|<p>Total size of Input/Output read operations from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.read.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: IO total read, sec|<p>Total time of Input/Output read operations from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.read.total.sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_total_read_io_time_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: Transmitted, bytes|<p>Bytes transmitted over the network reported by the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.transmitted.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_transmitted_bytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Hypervisor: Write IOPS|<p>Input/Output write operations per second from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.iops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_write_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Write IO|<p>Input/Output write operations from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_num_write_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Hypervisor: Write IO, bandwidth|<p>Write data transferred in B/sec from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.write.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_write_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Hypervisor: Write IO, latency|<p>Input/Output write latency from the Hypervisor.</p>|Dependent item|nutanix.host.hypervisor.io.write.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.hypervisor_avg_write_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Hypervisor: Number of CPU cores|<p>The number of CPU cores.</p>|Dependent item|nutanix.host.hypervisor.cpu.cores.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_cpu_cores`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Hypervisor: Number of CPU sockets|<p>The number of CPU sockets.</p>|Dependent item|nutanix.host.hypervisor.cpu.sockets.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_cpu_sockets`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Hypervisor: Number of CPU threads|<p>The number of CPU threads.</p>|Dependent item|nutanix.host.hypervisor.cpu.threads.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_cpu_threads`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Storage Controller: IOPS|<p>Input/Output operations per second from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: IO|<p>Input/Output operations from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: IO, bandwidth|<p>Data transferred in B/sec from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: IO, latency|<p>Input/Output latency from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: Read IOPS|<p>Input/Output read operations per second from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.iops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_read_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Read IO|<p>Input/Output read operations from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_read_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Read IO, %|<p>Percentage of Input/Output operations from the Storage Controller that are reads.</p>|Dependent item|nutanix.host.storage.controller.io.read.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_read_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Storage Controller: Read IO, bandwidth|<p>Read data transferred in B/sec from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.read.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_read_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: Read IO, latency|<p>Input/Output read latency from the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.read.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_read_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: Read IO, bytes|<p>Storage Controller average read Input/Output in bytes.</p>|Dependent item|nutanix.host.storage.controller.io.read.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_read_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: Total transformed usage, bytes|<p>Actual usage of the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.transformed.usage.total.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_total_transformed_usage_bytes`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Write IO|<p>Input/Output write operations to the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_write_io`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Write IOPS|<p>Input/Output write operations per second to the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.iops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_num_write_iops`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage Controller: Write IO, %|<p>Percentage of Input/Output operations to the Storage Controller that are writes.</p>|Dependent item|nutanix.host.storage.controller.io.write.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_write_io_ppm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0001`</p></li></ul>|
|Storage Controller: Write IO, bandwidth|<p>Write data transferred in B/sec to the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.write.bandwidth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_write_io_bandwidth_kBps`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Controller: Write IO, latency|<p>Input/Output write latency to the Storage Controller.</p>|Dependent item|nutanix.host.storage.controller.io.write.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_write_io_latency_usecs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Storage Controller: Write IO, bytes|<p>Storage Controller average write Input/Output in bytes.</p>|Dependent item|nutanix.host.storage.controller.io.write.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.controller_avg_write_io_size_kbytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage Tier: Das-sata capacity, bytes|<p>The total capacity of Das-sata in bytes.</p>|Dependent item|nutanix.host.storage.controller.tier.das_sata.capacity.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.das-sata.capacity_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: Das-sata free, bytes|<p>The free space of Das-sata in bytes.</p>|Dependent item|nutanix.host.storage.controller.tier.das_sata.free.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.das-sata.free_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: Das-sata usage, bytes|<p>The used space of Das-sata in bytes.</p>|Dependent item|nutanix.host.storage.controller.tier.das_sata.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.das-sata.usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: SSD capacity, bytes|<p>The total capacity of SSD in bytes.</p>|Dependent item|nutanix.host.storage.controller.tier.ssd.capacity.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.ssd.capacity_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: SSD free, bytes|<p>The free space of SSD in bytes.</p>|Dependent item|nutanix.host.storage.controller.tier.ssd.free.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.ssd.free_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage Tier: SSD usage, bytes|<p>The used space of SSD in bytes.</p>|Dependent item|nutanix.host.storage.controller.tier.ssd.usage.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_stats.['storage_tier.ssd.usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nutanix: Failed to get metric data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Host Prism Element by HTTP/nutanix.host.metric.get.check))>0`|High||
|Nutanix: Failed to get disk data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Host Prism Element by HTTP/nutanix.host.disk.get.check))>0`|High||
|Nutanix: Failed to get alert data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Nutanix Host Prism Element by HTTP/nutanix.host.alert.get.check))>0`|High||
|Nutanix: Host is in degraded status|<p>Host is in a degraded status. The host may soon become unavailable.</p>|`last(/Nutanix Host Prism Element by HTTP/nutanix.host.general.degraded)=1`|High||

### LLD rule Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk discovery|<p>Discovery of all disks.</p>|Dependent item|nutanix.host.disk.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#DISK.SERIAL}]: Bandwidth|<p>Bandwidth of the disk in B/sec.</p>|Dependent item|nutanix.host.disk.io.bandwidth["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].stats.io_bandwidth_kBps`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: Space: Total, bytes|<p>The total disk space in bytes.</p>|Dependent item|nutanix.host.disk.capacity.bytes["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].usage_stats.['storage.capacity_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: Space: Free, bytes|<p>The free disk space in bytes.</p>|Dependent item|nutanix.host.disk.free.bytes["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].usage_stats.['storage.free_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: IOPS|<p>The number of Input/Output operations from the disk.</p>|Dependent item|nutanix.host.disk.iops["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].stats.num_iops`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: IO, latency|<p>The average Input/Output operation latency.</p>|Dependent item|nutanix.host.disk.io.avg.latency["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].stats.avg_io_latency_usecs`</p></li><li><p>Custom multiplier: `1.0E-6`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: Space: Logical usage, bytes|<p>The logical used disk space in bytes.</p>|Dependent item|nutanix.host.disk.logical.usage.bytes["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].usage_stats.['storage.logical_usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: Online|<p>Indicates whether the disk is online.</p>|Dependent item|nutanix.host.disk.online["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].online`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: Status|<p>Current disk status. One of the following:</p><p>- NORMAL;</p><p>- DATA_MIGRATION_INITIATED;</p><p>- MARKED_FOR_REMOVAL_BUT_NOT_DETACHABLE;</p><p>- DETACHABLE.</p>|Dependent item|nutanix.host.disk.status["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].disk_status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DISK.SERIAL}]: Space: Used, bytes|<p>The used disk space in bytes.</p>|Dependent item|nutanix.host.disk.usage.bytes["{#DISK.SERIAL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#DISK.ID}'].usage_stats.['storage.usage_bytes']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Alert discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alert discovery|<p>Discovery of all alerts.</p><p>Alerts will be grouped by title. For each alert, in addition to the basic information, the number of activation and last alert ID will be available.</p>|Dependent item|nutanix.host.alert.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Alert discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alert [{#ALERT.NAME}]: Full title|<p>The full title of the alert.</p>|Dependent item|nutanix.host.alert.title["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.title`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Create datetime|<p>The alert creation date and time.</p>|Dependent item|nutanix.host.alert.created["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.created`</p></li><li><p>Custom multiplier: `0.000001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Severity|<p>Alert severity. One of the following:</p><p>- Info;</p><p>- Warning;</p><p>- Critical;</p><p>- Unknown.</p>|Dependent item|nutanix.host.alert.severity["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.severity`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: State|<p>Alert state. One of the following:</p><p>- OK;</p><p>- Problem.</p>|Dependent item|nutanix.host.alert.state["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Detailed message|<p>Detailed information about the current alert.</p>|Dependent item|nutanix.host.alert.message["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.message`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Last alert ID|<p>Latest ID of the alert.</p>|Dependent item|nutanix.host.alert.last_id["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.last_id`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alert [{#ALERT.NAME}]: Count alerts|<p>The number of times this alert was triggered.</p>|Dependent item|nutanix.host.alert.count["{#ALERT.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALERT.KEY}.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

