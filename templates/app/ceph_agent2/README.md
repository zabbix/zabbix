
# Ceph by Zabbix agent 2

## Overview

The template to monitor Ceph cluster by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Ceph by Zabbix agent 2` — collects metrics by polling zabbix-agent2.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Ceph 14.2 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Setup and configure zabbix-agent2 compiled with the Ceph monitoring plugin.
2. Set the {$CEPH.CONNSTRING} such as <protocol(host:port)> or named session.
3. Set the user name and password in host macros ({$CEPH.USER}, {$CEPH.API.KEY}) if you want to override parameters from the Zabbix agent configuration file.

Test availability: `zabbix_get -s ceph-host -k ceph.ping["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]`


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CEPH.USER}||`zabbix`|
|{$CEPH.API.KEY}||`zabbix_pass`|
|{$CEPH.CONNSTRING}||`https://localhost:8003`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ceph: Get overall cluster status||Zabbix agent|ceph.status["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]|
|Ceph: Get OSD stats||Zabbix agent|ceph.osd.stats["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]|
|Ceph: Get OSD dump||Zabbix agent|ceph.osd.dump["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]|
|Ceph: Get df||Zabbix agent|ceph.df.details["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]|
|Ceph: Ping||Zabbix agent|ceph.ping["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Ceph: Number of Monitors|<p>The number of Monitors configured in a Ceph cluster.</p>|Dependent item|ceph.num_mon<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_mon`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Ceph: Overall cluster status|<p>The overall Ceph cluster status, eg 0 - HEALTH_OK, 1 - HEALTH_WARN or 2 - HEALTH_ERR.</p>|Dependent item|ceph.overall_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.overall_status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: Minimum Mon release version|<p>min_mon_release_name</p>|Dependent item|ceph.min_mon_release_name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.min_mon_release_name`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Ceph: Ceph Read bandwidth|<p>The global read bytes per second.</p>|Dependent item|ceph.rd_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rd_bytes`</p></li><li>Change per second</li></ul>|
|Ceph: Ceph Write bandwidth|<p>The global write bytes per second.</p>|Dependent item|ceph.wr_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wr_bytes`</p></li><li>Change per second</li></ul>|
|Ceph: Ceph Read operations per sec|<p>The global read operations per second.</p>|Dependent item|ceph.rd_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rd_ops`</p></li><li>Change per second</li></ul>|
|Ceph: Ceph Write operations per sec|<p>The global write operations per second.</p>|Dependent item|ceph.wr_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wr_ops`</p></li><li>Change per second</li></ul>|
|Ceph: Total bytes available|<p>The total bytes available in a Ceph cluster.</p>|Dependent item|ceph.total_avail_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_avail_bytes`</p></li></ul>|
|Ceph: Total bytes|<p>The total (RAW) capacity of a Ceph cluster in bytes.</p>|Dependent item|ceph.total_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_bytes`</p></li></ul>|
|Ceph: Total bytes used|<p>The total bytes used in a Ceph cluster.</p>|Dependent item|ceph.total_used_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_used_bytes`</p></li></ul>|
|Ceph: Total number of objects|<p>The total number of objects in a Ceph cluster.</p>|Dependent item|ceph.total_objects<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_objects`</p></li></ul>|
|Ceph: Number of Placement Groups|<p>The total number of Placement Groups in a Ceph cluster.</p>|Dependent item|ceph.num_pg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_pg`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: Number of Placement Groups in Temporary state|<p>The total number of Placement Groups in a *pg_temp* state</p>|Dependent item|ceph.num_pg_temp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_pg_temp`</p></li></ul>|
|Ceph: Number of Placement Groups in Active state|<p>The total number of Placement Groups in an active state.</p>|Dependent item|ceph.pg_states.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.active`</p></li></ul>|
|Ceph: Number of Placement Groups in Clean state|<p>The total number of Placement Groups in a clean state.</p>|Dependent item|ceph.pg_states.clean<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.clean`</p></li></ul>|
|Ceph: Number of Placement Groups in Peering state|<p>The total number of Placement Groups in a peering state.</p>|Dependent item|ceph.pg_states.peering<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.peering`</p></li></ul>|
|Ceph: Number of Placement Groups in Scrubbing state|<p>The total number of Placement Groups in a scrubbing state.</p>|Dependent item|ceph.pg_states.scrubbing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.scrubbing`</p></li></ul>|
|Ceph: Number of Placement Groups in Undersized state|<p>The total number of Placement Groups in an undersized state.</p>|Dependent item|ceph.pg_states.undersized<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.undersized`</p></li></ul>|
|Ceph: Number of Placement Groups in Backfilling state|<p>The total number of Placement Groups in a backfill state.</p>|Dependent item|ceph.pg_states.backfilling<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.backfilling`</p></li></ul>|
|Ceph: Number of Placement Groups in degraded state|<p>The total number of Placement Groups in a degraded state.</p>|Dependent item|ceph.pg_states.degraded<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.degraded`</p></li></ul>|
|Ceph: Number of Placement Groups in inconsistent state|<p>The total number of Placement Groups in an inconsistent state.</p>|Dependent item|ceph.pg_states.inconsistent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.inconsistent`</p></li></ul>|
|Ceph: Number of Placement Groups in Unknown state|<p>The total number of Placement Groups in an unknown state.</p>|Dependent item|ceph.pg_states.unknown<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.unknown`</p></li></ul>|
|Ceph: Number of Placement Groups in remapped state|<p>The total number of Placement Groups in a remapped state.</p>|Dependent item|ceph.pg_states.remapped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.remapped`</p></li></ul>|
|Ceph: Number of Placement Groups in recovering state|<p>The total number of Placement Groups in a recovering state.</p>|Dependent item|ceph.pg_states.recovering<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.recovering`</p></li></ul>|
|Ceph: Number of Placement Groups in backfill_toofull state|<p>The total number of Placement Groups in a *backfill_toofull state*.</p>|Dependent item|ceph.pg_states.backfill_toofull<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.backfill_toofull`</p></li></ul>|
|Ceph: Number of Placement Groups in backfill_wait state|<p>The total number of Placement Groups in a *backfill_wait* state.</p>|Dependent item|ceph.pg_states.backfill_wait<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.backfill_wait`</p></li></ul>|
|Ceph: Number of Placement Groups in recovery_wait state|<p>The total number of Placement Groups in a *recovery_wait* state.</p>|Dependent item|ceph.pg_states.recovery_wait<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_states.recovery_wait`</p></li></ul>|
|Ceph: Number of Pools|<p>The total number of pools in a Ceph cluster.</p>|Dependent item|ceph.num_pools<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_pools`</p></li></ul>|
|Ceph: Number of OSDs|<p>The number of the known storage daemons in a Ceph cluster.</p>|Dependent item|ceph.num_osd<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_osd`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: Number of OSDs in state: UP|<p>The total number of the online storage daemons in a Ceph cluster.</p>|Dependent item|ceph.num_osd_up<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_osd_up`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: Number of OSDs in state: IN|<p>The total number of the participating storage daemons in a Ceph cluster.</p>|Dependent item|ceph.num_osd_in<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_osd_in`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: Ceph OSD avg fill|<p>The average fill of OSDs.</p>|Dependent item|ceph.osd_fill.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_fill.avg`</p></li></ul>|
|Ceph: Ceph OSD max fill|<p>The percentage of the most filled OSD.</p>|Dependent item|ceph.osd_fill.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_fill.max`</p></li></ul>|
|Ceph: Ceph OSD min fill|<p>The percentage fill of the minimum filled OSD.</p>|Dependent item|ceph.osd_fill.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_fill.min`</p></li></ul>|
|Ceph: Ceph OSD max PGs|<p>The maximum amount of Placement Groups on OSDs.</p>|Dependent item|ceph.osd_pgs.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_pgs.max`</p></li></ul>|
|Ceph: Ceph OSD min PGs|<p>The minimum amount of Placement Groups on OSDs.</p>|Dependent item|ceph.osd_pgs.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_pgs.min`</p></li></ul>|
|Ceph: Ceph OSD avg PGs|<p>The average amount of Placement Groups on OSDs.</p>|Dependent item|ceph.osd_pgs.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_pgs.avg`</p></li></ul>|
|Ceph: Ceph OSD Apply latency Avg|<p>The average apply latency of OSDs.</p>|Dependent item|ceph.osd_latency_apply.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_latency_apply.avg`</p></li></ul>|
|Ceph: Ceph OSD Apply latency Max|<p>The maximum apply latency of OSDs.</p>|Dependent item|ceph.osd_latency_apply.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_latency_apply.max`</p></li></ul>|
|Ceph: Ceph OSD Apply latency Min|<p>The minimum apply latency of OSDs.</p>|Dependent item|ceph.osd_latency_apply.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_latency_apply.min`</p></li></ul>|
|Ceph: Ceph OSD Commit latency Avg|<p>The average commit latency of OSDs.</p>|Dependent item|ceph.osd_latency_commit.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_latency_commit.avg`</p></li></ul>|
|Ceph: Ceph OSD Commit latency Max|<p>The maximum commit latency of OSDs.</p>|Dependent item|ceph.osd_latency_commit.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_latency_commit.max`</p></li></ul>|
|Ceph: Ceph OSD Commit latency Min|<p>The minimum commit latency of OSDs.</p>|Dependent item|ceph.osd_latency_commit.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_latency_commit.min`</p></li></ul>|
|Ceph: Ceph backfill full ratio|<p>The backfill full ratio setting of the Ceph cluster as configured on OSDMap.</p>|Dependent item|ceph.osd_backfillfull_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_backfillfull_ratio`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: Ceph full ratio|<p>The full ratio setting of the Ceph cluster as configured on OSDMap.</p>|Dependent item|ceph.osd_full_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_full_ratio`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: Ceph nearfull ratio|<p>The near full ratio setting of the Ceph cluster as configured on OSDMap.</p>|Dependent item|ceph.osd_nearfull_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osd_nearfull_ratio`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ceph: Can not connect to cluster|<p>The connection to the Ceph RESTful module is broken (if there is any error presented including *AUTH* and the configuration issues).</p>|`last(/Ceph by Zabbix agent 2/ceph.ping["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"])=0`|Average||
|Ceph: Cluster in ERROR state||`last(/Ceph by Zabbix agent 2/ceph.overall_status)=2`|Average|**Manual close**: Yes|
|Ceph: Cluster in WARNING state||`last(/Ceph by Zabbix agent 2/ceph.overall_status)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Ceph: Cluster in ERROR state</li></ul>|
|Ceph: Minimum monitor release version has changed|<p>A Ceph version has changed. Acknowledge to close the problem manually.</p>|`last(/Ceph by Zabbix agent 2/ceph.min_mon_release_name,#1)<>last(/Ceph by Zabbix agent 2/ceph.min_mon_release_name,#2) and length(last(/Ceph by Zabbix agent 2/ceph.min_mon_release_name))>0`|Info|**Manual close**: Yes|

### LLD rule OSD

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSD||Zabbix agent|ceph.osd.discovery["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]|

### Item prototypes for OSD

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ceph: [osd.{#OSDNAME}] OSD in||Dependent item|ceph.osd[{#OSDNAME},in]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osds.{#OSDNAME}.in`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: [osd.{#OSDNAME}] OSD up||Dependent item|ceph.osd[{#OSDNAME},up]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osds.{#OSDNAME}.up`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ceph: [osd.{#OSDNAME}] OSD PGs||Dependent item|ceph.osd[{#OSDNAME},num_pgs]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osds.{#OSDNAME}.num_pgs`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Ceph: [osd.{#OSDNAME}] OSD fill||Dependent item|ceph.osd[{#OSDNAME},fill]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osds.{#OSDNAME}.osd_fill`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Ceph: [osd.{#OSDNAME}] OSD latency apply|<p>The time taken to flush an update to disks.</p>|Dependent item|ceph.osd[{#OSDNAME},latency_apply]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osds.{#OSDNAME}.osd_latency_apply`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Ceph: [osd.{#OSDNAME}] OSD latency commit|<p>The time taken to commit an operation to the journal.</p>|Dependent item|ceph.osd[{#OSDNAME},latency_commit]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.osds.{#OSDNAME}.osd_latency_commit`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for OSD

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ceph: OSD osd.{#OSDNAME} is down|<p>OSD osd.{#OSDNAME} is marked "down" in the *osdmap*.<br>The OSD daemon may have been stopped, or peer OSDs may be unable to reach the OSD over the network.</p>|`last(/Ceph by Zabbix agent 2/ceph.osd[{#OSDNAME},up]) = 0`|Average||
|Ceph: OSD osd.{#OSDNAME} is full||`min(/Ceph by Zabbix agent 2/ceph.osd[{#OSDNAME},fill],15m) > last(/Ceph by Zabbix agent 2/ceph.osd_full_ratio)*100`|Average||
|Ceph: Ceph OSD osd.{#OSDNAME} is near full||`min(/Ceph by Zabbix agent 2/ceph.osd[{#OSDNAME},fill],15m) > last(/Ceph by Zabbix agent 2/ceph.osd_nearfull_ratio)*100`|Warning|**Depends on**:<br><ul><li>Ceph: OSD osd.{#OSDNAME} is full</li></ul>|

### LLD rule Pool

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pool||Zabbix agent|ceph.pool.discovery["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]|

### Item prototypes for Pool

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ceph: [{#POOLNAME}] Pool Used|<p>The total bytes used in a pool.</p>|Dependent item|ceph.pool["{#POOLNAME}",bytes_used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].bytes_used`</p></li></ul>|
|Ceph: [{#POOLNAME}] Max available|<p>The maximum available space in the given pool.</p>|Dependent item|ceph.pool["{#POOLNAME}",max_avail]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].max_avail`</p></li></ul>|
|Ceph: [{#POOLNAME}] Pool RAW Used|<p>Bytes used in pool including the copies made.</p>|Dependent item|ceph.pool["{#POOLNAME}",stored_raw]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].stored_raw`</p></li></ul>|
|Ceph: [{#POOLNAME}] Pool Percent Used|<p>The percentage of the storage used per pool.</p>|Dependent item|ceph.pool["{#POOLNAME}",percent_used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].percent_used`</p></li></ul>|
|Ceph: [{#POOLNAME}] Pool objects|<p>The number of objects in the pool.</p>|Dependent item|ceph.pool["{#POOLNAME}",objects]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].objects`</p></li></ul>|
|Ceph: [{#POOLNAME}] Pool Read bandwidth|<p>The read rate per pool (bytes per second).</p>|Dependent item|ceph.pool["{#POOLNAME}",rd_bytes.rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].rd_bytes`</p></li><li>Change per second</li></ul>|
|Ceph: [{#POOLNAME}] Pool Write bandwidth|<p>The write rate per pool (bytes per second).</p>|Dependent item|ceph.pool["{#POOLNAME}",wr_bytes.rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].wr_bytes`</p></li><li>Change per second</li></ul>|
|Ceph: [{#POOLNAME}] Pool Read operations|<p>The read rate per pool (operations per second).</p>|Dependent item|ceph.pool["{#POOLNAME}",rd_ops.rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].rd_ops`</p></li><li>Change per second</li></ul>|
|Ceph: [{#POOLNAME}] Pool Write operations|<p>The write rate per pool (operations per second).</p>|Dependent item|ceph.pool["{#POOLNAME}",wr_ops.rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pools["{#POOLNAME}"].wr_ops`</p></li><li>Change per second</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

