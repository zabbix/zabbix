
# Template App Ceph by Zabbix Agent2

## Overview

For Zabbix version: 5.0 and higher.  
The template is designed to monitor Ceph cluster by Zabbix, which works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `Ceph by Zabbix Agent2` — collects metrics by polling *zabbix-agent2*.



This template was tested on:

- Ceph, version 14.2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/5.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

1. Setup and configure *zabbix-agent2* compiled with the *Ceph* monitoring plugin.
2. Set the {$CEPH.CONNSTRING}, such as <protocol(host:port)>, or named session.
3. Set the user name and password in the host macros ({$CEPH.USER}, {$CEPH.API.KEY}) if you want to override the parameters from the Zabbix agent configuration file.

Test availability: `zabbix_get -s ceph-host -k ceph.ping["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]`


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CEPH.API.KEY} |<p>-</p> |`zabbix_pass` |
|{$CEPH.CONNSTRING} |<p>-</p> |`https://localhost:8003` |
|{$CEPH.USER} |<p>-</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|OSD |<p>-</p> |ZABBIX_PASSIVE |ceph.osd.discovery["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"] |
|Pool |<p>-</p> |ZABBIX_PASSIVE |ceph.pool.discovery["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Ceph |Ceph: Ping | |ZABBIX_PASSIVE |ceph.ping["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Ceph |Ceph: Number of Monitors |<p>The number of Monitors configured in a Ceph cluster.</p> |DEPENDENT |ceph.num_mon<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_mon`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Ceph |Ceph: Overall cluster status |<p>The overall Ceph cluster status, eg 0 - HEALTH_OK, 1 - HEALTH_WARN or 2 - HEALTH_ERR.</p> |DEPENDENT |ceph.overall_status<p>**Preprocessing**:</p><p>- JSONPATH: `$.overall_status`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: Minimum Mon release version |<p>min_mon_release_name</p> |DEPENDENT |ceph.min_mon_release_name<p>**Preprocessing**:</p><p>- JSONPATH: `$.min_mon_release_name`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Ceph |Ceph: Ceph Read bandwidth |<p>The global read bytes per second.</p> |DEPENDENT |ceph.rd_bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.rd_bytes`</p><p>- CHANGE_PER_SECOND |
|Ceph |Ceph: Ceph Write bandwidth |<p>The global write bytes per second.</p> |DEPENDENT |ceph.wr_bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.wr_bytes`</p><p>- CHANGE_PER_SECOND |
|Ceph |Ceph: Ceph Read operations per sec |<p>The global read operations per second.</p> |DEPENDENT |ceph.rd_ops.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.rd_ops`</p><p>- CHANGE_PER_SECOND |
|Ceph |Ceph: Ceph Write operations per sec |<p>The global write operations per second.</p> |DEPENDENT |ceph.wr_ops.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.wr_ops`</p><p>- CHANGE_PER_SECOND |
|Ceph |Ceph: Total bytes available |<p>The total bytes available in a Ceph cluster.</p> |DEPENDENT |ceph.total_avail_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_avail_bytes`</p> |
|Ceph |Ceph: Total bytes |<p>The total (RAW) capacity of a Ceph cluster in bytes.</p> |DEPENDENT |ceph.total_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_bytes`</p> |
|Ceph |Ceph: Total bytes used |<p>The total bytes used in a Ceph cluster.</p> |DEPENDENT |ceph.total_used_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_used_bytes`</p> |
|Ceph |Ceph: Total number of objects |<p>The total number of objects in a Ceph cluster.</p> |DEPENDENT |ceph.total_objects<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_objects`</p> |
|Ceph |Ceph: Number of Placement Groups |<p>The total number of Placement Groups in a Ceph cluster.</p> |DEPENDENT |ceph.num_pg<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_pg`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: Number of Placement Groups in Temporary state |<p>The total number of Placement Groups in a *pg_temp* state.</p> |DEPENDENT |ceph.num_pg_temp<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_pg_temp`</p> |
|Ceph |Ceph: Number of Placement Groups in Active state |<p>The total number of Placement Groups in an active state.</p> |DEPENDENT |ceph.pg_states.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.active`</p> |
|Ceph |Ceph: Number of Placement Groups in Clean state |<p>The total number of Placement Groups in a clean state.</p> |DEPENDENT |ceph.pg_states.clean<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.clean`</p> |
|Ceph |Ceph: Number of Placement Groups in Peering state |<p>The total number of Placement Groups in a peering state.</p> |DEPENDENT |ceph.pg_states.peering<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.peering`</p> |
|Ceph |Ceph: Number of Placement Groups in Scrubbing state |<p>The total number of Placement Groups in a scrubbing state.</p> |DEPENDENT |ceph.pg_states.scrubbing<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.scrubbing`</p> |
|Ceph |Ceph: Number of Placement Groups in Undersized state |<p>The total number of Placement Groups in an undersized state.</p> |DEPENDENT |ceph.pg_states.undersized<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.undersized`</p> |
|Ceph |Ceph: Number of Placement Groups in Backfilling state |<p>The total number of Placement Groups in a backfill state.</p> |DEPENDENT |ceph.pg_states.backfilling<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.backfilling`</p> |
|Ceph |Ceph: Number of Placement Groups in degraded state |<p>The total number of Placement Groups in a degraded state.</p> |DEPENDENT |ceph.pg_states.degraded<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.degraded`</p> |
|Ceph |Ceph: Number of Placement Groups in inconsistent state |<p>The total number of Placement Groups in an inconsistent state.</p> |DEPENDENT |ceph.pg_states.inconsistent<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.inconsistent`</p> |
|Ceph |Ceph: Number of Placement Groups in Unknown state |<p>The total number of Placement Groups in an unknown state.</p> |DEPENDENT |ceph.pg_states.unknown<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.unknown`</p> |
|Ceph |Ceph: Number of Placement Groups in remapped state |<p>The total number of Placement Groups in a remapped state.</p> |DEPENDENT |ceph.pg_states.remapped<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.remapped`</p> |
|Ceph |Ceph: Number of Placement Groups in recovering state |<p>The total number of Placement Groups in a recovering state.</p> |DEPENDENT |ceph.pg_states.recovering<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.recovering`</p> |
|Ceph |Ceph: Number of Placement Groups in backfill_toofull state |<p>The total number of Placement Groups in a *backfill_toofull state*.</p> |DEPENDENT |ceph.pg_states.backfill_toofull<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.backfill_toofull`</p> |
|Ceph |Ceph: Number of Placement Groups in backfill_wait state |<p>The total number of Placement Groups in a *backfill_wait* state.</p> |DEPENDENT |ceph.pg_states.backfill_wait<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.backfill_wait`</p> |
|Ceph |Ceph: Number of Placement Groups in recovery_wait state |<p>The total number of Placement Groups in a *recovery_wait* state.</p> |DEPENDENT |ceph.pg_states.recovery_wait<p>**Preprocessing**:</p><p>- JSONPATH: `$.pg_states.recovery_wait`</p> |
|Ceph |Ceph: Number of Pools |<p>The total number of pools in a Ceph cluster.</p> |DEPENDENT |ceph.num_pools<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_pools`</p> |
|Ceph |Ceph: Number of OSDs |<p>The number of the known storage daemons in a Ceph cluster.</p> |DEPENDENT |ceph.num_osd<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_osd`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: Number of OSDs in state: UP |<p>The total number of the online storage daemons in a Ceph cluster.</p> |DEPENDENT |ceph.num_osd_up<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_osd_up`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: Number of OSDs in state: IN |<p>The total number of the participating storage daemons in a Ceph cluster.</p> |DEPENDENT |ceph.num_osd_in<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_osd_in`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: Ceph OSD avg fill |<p>The average fill of OSDs.</p> |DEPENDENT |ceph.osd_fill.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_fill.avg`</p> |
|Ceph |Ceph: Ceph OSD max fill |<p>The percentage of the most filled OSD.</p> |DEPENDENT |ceph.osd_fill.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_fill.max`</p> |
|Ceph |Ceph: Ceph OSD min fill |<p>The percentage fill of the minimum filled OSD.</p> |DEPENDENT |ceph.osd_fill.min<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_fill.min`</p> |
|Ceph |Ceph: Ceph OSD max PGs |<p>The maximum amount of Placement Groups on OSDs.</p> |DEPENDENT |ceph.osd_pgs.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_pgs.max`</p> |
|Ceph |Ceph: Ceph OSD min PGs |<p>The minimum amount of Placement Groups on OSDs.</p> |DEPENDENT |ceph.osd_pgs.min<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_pgs.min`</p> |
|Ceph |Ceph: Ceph OSD avg PGs |<p>The average amount of Placement Groups on OSDs.</p> |DEPENDENT |ceph.osd_pgs.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_pgs.avg`</p> |
|Ceph |Ceph: Ceph OSD Apply latency Avg |<p>The average apply latency of OSDs.</p> |DEPENDENT |ceph.osd_latency_apply.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_latency_apply.avg`</p> |
|Ceph |Ceph: Ceph OSD Apply latency Max |<p>The maximum apply latency of OSDs.</p> |DEPENDENT |ceph.osd_latency_apply.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_latency_apply.max`</p> |
|Ceph |Ceph: Ceph OSD Apply latency Min |<p>The minimum apply latency of OSDs.</p> |DEPENDENT |ceph.osd_latency_apply.min<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_latency_apply.min`</p> |
|Ceph |Ceph: Ceph OSD Commit latency Avg |<p>The average commit latency of OSDs.</p> |DEPENDENT |ceph.osd_latency_commit.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_latency_commit.avg`</p> |
|Ceph |Ceph: Ceph OSD Commit latency Max |<p>The maximum commit latency of OSDs.</p> |DEPENDENT |ceph.osd_latency_commit.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_latency_commit.max`</p> |
|Ceph |Ceph: Ceph OSD Commit latency Min |<p>The minimum commit latency of OSDs.</p> |DEPENDENT |ceph.osd_latency_commit.min<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_latency_commit.min`</p> |
|Ceph |Ceph: Ceph backfill full ratio |<p>The backfill full ratio setting of the Ceph cluster as configured on OSDMap.</p> |DEPENDENT |ceph.osd_backfillfull_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_backfillfull_ratio`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: Ceph full ratio |<p>The full ratio setting of the Ceph cluster as configured on OSDMap.</p> |DEPENDENT |ceph.osd_full_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_full_ratio`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: Ceph nearfull ratio |<p>The near full ratio setting of the Ceph cluster as configured on OSDMap.</p> |DEPENDENT |ceph.osd_nearfull_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.osd_nearfull_ratio`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: [osd.{#OSDNAME}] OSD in | |DEPENDENT |ceph.osd[{#OSDNAME},in]<p>**Preprocessing**:</p><p>- JSONPATH: `$.osds.{#OSDNAME}.in`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: [osd.{#OSDNAME}] OSD up | |DEPENDENT |ceph.osd[{#OSDNAME},up]<p>**Preprocessing**:</p><p>- JSONPATH: `$.osds.{#OSDNAME}.up`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Ceph |Ceph: [osd.{#OSDNAME}] OSD PGs | |DEPENDENT |ceph.osd[{#OSDNAME},num_pgs]<p>**Preprocessing**:</p><p>- JSONPATH: `$.osds.{#OSDNAME}.num_pgs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Ceph |Ceph: [osd.{#OSDNAME}] OSD fill | |DEPENDENT |ceph.osd[{#OSDNAME},fill]<p>**Preprocessing**:</p><p>- JSONPATH: `$.osds.{#OSDNAME}.osd_fill`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Ceph |Ceph: [osd.{#OSDNAME}] OSD latency apply |<p>The time taken to flush an update to disks.</p> |DEPENDENT |ceph.osd[{#OSDNAME},latency_apply]<p>**Preprocessing**:</p><p>- JSONPATH: `$.osds.{#OSDNAME}.osd_latency_apply`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Ceph |Ceph: [osd.{#OSDNAME}] OSD latency commit |<p>The time taken to commit an operation to the journal.</p> |DEPENDENT |ceph.osd[{#OSDNAME},latency_commit]<p>**Preprocessing**:</p><p>- JSONPATH: `$.osds.{#OSDNAME}.osd_latency_commit`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Ceph |Ceph: [{#POOLNAME}] Pool Used |<p>The total bytes used in a pool.</p> |DEPENDENT |ceph.pool["{#POOLNAME}",bytes_used]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].bytes_used`</p> |
|Ceph |Ceph: [{#POOLNAME}] Max available |<p>The maximum available space in the given pool.</p> |DEPENDENT |ceph.pool["{#POOLNAME}",max_avail]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].max_avail`</p> |
|Ceph |Ceph: [{#POOLNAME}] Pool RAW Used |<p>Bytes used in pool including the copies made.</p> |DEPENDENT |ceph.pool["{#POOLNAME}",stored_raw]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].stored_raw`</p> |
|Ceph |Ceph: [{#POOLNAME}] Pool Percent Used |<p>The percentage of the storage used per pool.</p> |DEPENDENT |ceph.pool["{#POOLNAME}",percent_used]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].percent_used`</p> |
|Ceph |Ceph: [{#POOLNAME}] Pool objects |<p>The number of objects in the pool.</p> |DEPENDENT |ceph.pool["{#POOLNAME}",objects]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].objects`</p> |
|Ceph |Ceph: [{#POOLNAME}] Pool Read bandwidth |<p>The read rate per pool (bytes per second).</p> |DEPENDENT |ceph.pool["{#POOLNAME}",rd_bytes.rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].rd_bytes`</p><p>- CHANGE_PER_SECOND |
|Ceph |Ceph: [{#POOLNAME}] Pool Write bandwidth |<p>The write rate per pool (bytes per second).</p> |DEPENDENT |ceph.pool["{#POOLNAME}",wr_bytes.rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].wr_bytes`</p><p>- CHANGE_PER_SECOND |
|Ceph |Ceph: [{#POOLNAME}] Pool Read operations |<p>The read rate per pool (operations per second).</p> |DEPENDENT |ceph.pool["{#POOLNAME}",rd_ops.rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].rd_ops`</p><p>- CHANGE_PER_SECOND |
|Ceph |Ceph: [{#POOLNAME}] Pool Write operations |<p>The read rate per pool (operations per second).</p> |DEPENDENT |ceph.pool["{#POOLNAME}",wr_ops.rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pools["{#POOLNAME}"].wr_ops`</p><p>- CHANGE_PER_SECOND |
|Zabbix_raw_items |Ceph: Get overall cluster status | |ZABBIX_PASSIVE |ceph.status["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"] |
|Zabbix_raw_items |Ceph: Get OSD stats | |ZABBIX_PASSIVE |ceph.osd.stats["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"] |
|Zabbix_raw_items |Ceph: Get OSD dump | |ZABBIX_PASSIVE |ceph.osd.dump["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"] |
|Zabbix_raw_items |Ceph: Get df | |ZABBIX_PASSIVE |ceph.df.details["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Ceph: Can not connect to cluster |<p>Connection to Ceph RESTful module is broken (if there is any error presented including AUTH and configuration issues).</p> |`{TEMPLATE_NAME:ceph.ping["{$CEPH.CONNSTRING}","{$CEPH.USER}","{$CEPH.API.KEY}"].last()}=0` |AVERAGE | |
|Ceph: Cluster in ERROR state |<p>-</p> |`{TEMPLATE_NAME:ceph.overall_status.last()}=2` |AVERAGE |<p>Manual close: YES</p> |
|Ceph: Cluster in WARNING state |<p>-</p> |`{TEMPLATE_NAME:ceph.overall_status.last()}=1`<p>Recovery expression:</p>`{TEMPLATE_NAME:ceph.overall_status.last()}=0` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Ceph: Cluster in ERROR state</p> |
|Ceph: Minimum monitor release version has changed (new version: {ITEM.VALUE}) |<p>Ceph version has changed. Ack to close.</p> |`{TEMPLATE_NAME:ceph.min_mon_release_name.diff()}=1 and {TEMPLATE_NAME:ceph.min_mon_release_name.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Ceph: OSD osd.{#OSDNAME} is down |<p>OSD osd.{#OSDNAME} is marked "down" in the osdmap.</p><p>The OSD daemon may have been stopped, or peer OSDs may be unable to reach the OSD over the network.</p> |`{TEMPLATE_NAME:ceph.osd[{#OSDNAME},up].last()} = 0` |AVERAGE | |
|Ceph: OSD osd.{#OSDNAME} is full |<p>-</p> |`{TEMPLATE_NAME:ceph.osd[{#OSDNAME},fill].min(15m)} > {Ceph by Zabbix Agent2:ceph.osd_full_ratio.last()}*100` |AVERAGE | |
|Ceph: Ceph OSD osd.{#OSDNAME} is near full |<p>-</p> |`{TEMPLATE_NAME:ceph.osd[{#OSDNAME},fill].min(15m)} > {Ceph by Zabbix Agent2:ceph.osd_nearfull_ratio.last()}*100` |WARNING |<p>**Depends on**:</p><p>- Ceph: OSD osd.{#OSDNAME} is full</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/410059-discussion-thread-for-official-zabbix-template-ceph).

