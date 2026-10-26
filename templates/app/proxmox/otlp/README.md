
# Proxmox VE by OTLP

## Overview

The template monitors a Proxmox VE node from its OpenTelemetry metric stream stored in the APM (ClickHouse) backend, using Telemetry query items.
It covers the node itself: CPU, load, memory, swap, ZFS ARC, root filesystem and uptime.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Proxmox VE 9

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. In the Proxmox VE web interface, add a metric server of the OpenTelemetry (OTLP) type (`Datacenter` > `Metric Server` > `Add` > `OpenTelemetry`) and point it at your OTLP collector.
2. Make sure the OTLP collector exports the received metrics into the ClickHouse database (service name `proxmox-ve`).
3. Configure `TelemetryProvider` on Zabbix server/proxy to point at that ClickHouse database.
4. Link the template to a host.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PROXMOX.OTLP.CPU.UTIL.MAX.WARN}|<p>Maximum CPU utilization of the node, in percent, for the warning trigger.</p>|`90`|
|{$PROXMOX.OTLP.MEM.PAVAIL.MIN.WARN}|<p>Minimum available memory of the node, in percent, for the warning trigger.</p>|`10`|
|{$PROXMOX.OTLP.FS.PUSED.MAX.WARN}|<p>Maximum used space of the root filesystem, in percent, for the warning trigger.</p>|`90`|
|{$PROXMOX.OTLP.FS.INODE.PUSED.MAX.WARN}|<p>Maximum used inodes of the root filesystem, in percent, for the warning trigger.</p>|`90`|
|{$PROXMOX.OTLP.LOAD_AVG_PER_CPU.MAX.WARN}|<p>Maximum 1-minute load average per CPU of the node for the average trigger.</p>|`1.5`|
|{$PROXMOX.OTLP.CPU.IOWAIT.MAX.WARN}|<p>Maximum share of CPU time spent waiting for I/O, in percent of the total CPU capacity of the node, for the warning trigger.</p>|`20`|
|{$PROXMOX.OTLP.SWAP.PUSED.MAX.WARN}|<p>Maximum used swap space of the node, in percent, for the warning trigger.</p>|`50`|
|{$PROXMOX.OTLP.INTERVAL}|<p>Update interval of the Telemetry query and calculated items. The aggregation bucket (granularity) of the Telemetry query items stays fixed at 1 minute, so the interval should be a multiple of 1 minute.</p>|`1m`|
|{$PROXMOX.OTLP.TIME_SHIFT}|<p>Time shift of the processing window of the Telemetry query items. Increase it if the OTLP collector writes metrics into ClickHouse with a delay.</p>|`15s`|
|{$PROXMOX.OTLP.NODATA.TIMEOUT}|<p>Time period without telemetry values after which the availability trigger fires.</p>|`30m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uptime|<p>Uptime of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Number of CPUs|<p>Number of CPU threads of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.cpu.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU utilization|<p>Average CPU utilization of the Proxmox VE node per aggregation bucket.</p>|Telemetry query|proxmox.otlp.node.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Load average (1m avg)|<p>1-minute load average of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.cpu.load1<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Load average (5m avg)|<p>5-minute load average of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.cpu.load5<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Load average (15m avg)|<p>15-minute load average of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.cpu.load15<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|CPU user time, rate|<p>CPU seconds spent in user mode per second, averaged over the 1m aggregation bucket (busy CPU cores).</p>|Telemetry query|proxmox.otlp.node.cpu.user.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li><li><p>Custom multiplier: `0.00016666666667`</p></li></ul>|
|CPU system time, rate|<p>CPU seconds spent in system mode per second, averaged over the 1m aggregation bucket (busy CPU cores).</p>|Telemetry query|proxmox.otlp.node.cpu.system.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li><li><p>Custom multiplier: `0.00016666666667`</p></li></ul>|
|CPU iowait time, rate|<p>CPU seconds spent waiting for I/O per second, averaged over the 1m aggregation bucket (busy CPU cores).</p>|Telemetry query|proxmox.otlp.node.cpu.iowait.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li><li><p>Custom multiplier: `0.00016666666667`</p></li></ul>|
|CPU idle time, rate|<p>Idle CPU seconds per second, averaged over the 1m aggregation bucket (idle CPU cores).</p>|Telemetry query|proxmox.otlp.node.cpu.idle.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li><li><p>Custom multiplier: `0.00016666666667`</p></li></ul>|
|CPU user time|<p>Share of the CPU time spent in user mode, in percent of the total CPU capacity of the node.</p>|Calculated|proxmox.otlp.node.cpu.util.user|
|CPU system time|<p>Share of the CPU time spent in system mode, in percent of the total CPU capacity of the node.</p>|Calculated|proxmox.otlp.node.cpu.util.system|
|CPU iowait time|<p>Share of the CPU time spent waiting for I/O, in percent of the total CPU capacity of the node.</p>|Calculated|proxmox.otlp.node.cpu.util.iowait|
|CPU idle time|<p>Share of the idle CPU time, in percent of the total CPU capacity of the node.</p>|Calculated|proxmox.otlp.node.cpu.util.idle|
|Memory: Total|<p>Total memory of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.mem.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory: Used|<p>Used memory of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.mem.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Memory: Available|<p>Available memory of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.mem.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Memory: Available, in %|<p>Available memory of the Proxmox VE node as a percentage of total memory.</p>|Calculated|proxmox.otlp.node.mem.pavail|
|Memory: ZFS ARC size|<p>Current size of the ZFS ARC cache on the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.mem.arcsize<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Swap: Total|<p>Total swap space of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.swap.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Swap: Used|<p>Used swap space of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.swap.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Swap: Space utilization|<p>Used swap space of the Proxmox VE node, in percent of the total swap space. Reports 0 if the node has no swap configured.</p>|Calculated|proxmox.otlp.node.swap.pused|
|Root FS: Total space|<p>Total space of the root filesystem of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.fs.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Root FS: Used space|<p>Used space of the root filesystem of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.fs.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Root FS: Space utilization|<p>Used space of the root filesystem of the Proxmox VE node, in percent of the total space.</p>|Calculated|proxmox.otlp.node.fs.pused|
|Root FS: Total inodes|<p>Total number of inodes of the root filesystem of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.fs.inodes.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Root FS: Used inodes|<p>Number of used inodes of the root filesystem of the Proxmox VE node.</p>|Telemetry query|proxmox.otlp.node.fs.inodes.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Root FS: Inode utilization|<p>Used inodes of the root filesystem of the Proxmox VE node, in percent of the total number of inodes.</p>|Calculated|proxmox.otlp.node.fs.inodes.pused|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: No telemetry data received|<p>No values of the node uptime metric have arrived from the telemetry backend for {$PROXMOX.OTLP.NODATA.TIMEOUT}.<br>Check the metric server in Proxmox VE, the OTLP collector, the ClickHouse exporter and the `TelemetryProvider` setting of Zabbix server or proxy.</p>|`nodata(/Proxmox VE by OTLP/proxmox.otlp.node.uptime,{$PROXMOX.OTLP.NODATA.TIMEOUT}) = 1`|Average||
|Proxmox VE: Node has been restarted|<p>The uptime of the Proxmox VE node is less than 10 minutes.</p>|`last(/Proxmox VE by OTLP/proxmox.otlp.node.uptime) < 10m`|Warning|**Manual close**: Yes|
|Proxmox VE: High CPU utilization|<p>The CPU utilization of the node has stayed above {$PROXMOX.OTLP.CPU.UTIL.MAX.WARN}% for the last 5 minutes.</p>|`min(/Proxmox VE by OTLP/proxmox.otlp.node.cpu.util,5m) > {$PROXMOX.OTLP.CPU.UTIL.MAX.WARN}`|Warning||
|Proxmox VE: Load average is too high|<p>The 1-minute load average per CPU of the node has stayed above {$PROXMOX.OTLP.LOAD_AVG_PER_CPU.MAX.WARN} for the last 5 minutes and the 5- and 15-minute load averages are not zero.</p>|`min(/Proxmox VE by OTLP/proxmox.otlp.node.cpu.load1,5m) / last(/Proxmox VE by OTLP/proxmox.otlp.node.cpu.count) > {$PROXMOX.OTLP.LOAD_AVG_PER_CPU.MAX.WARN} and last(/Proxmox VE by OTLP/proxmox.otlp.node.cpu.load5) > 0 and last(/Proxmox VE by OTLP/proxmox.otlp.node.cpu.load15) > 0`|Average||
|Proxmox VE: High CPU iowait|<p>The share of CPU time spent waiting for I/O has stayed above {$PROXMOX.OTLP.CPU.IOWAIT.MAX.WARN}% of the total CPU capacity of the node for the last 5 minutes.<br>Slow or overloaded storage is the usual cause.</p>|`min(/Proxmox VE by OTLP/proxmox.otlp.node.cpu.util.iowait,5m) > {$PROXMOX.OTLP.CPU.IOWAIT.MAX.WARN}`|Warning||
|Proxmox VE: Low available memory|<p>The available memory of the node has stayed below {$PROXMOX.OTLP.MEM.PAVAIL.MIN.WARN}% of the total memory for the last 5 minutes.</p>|`max(/Proxmox VE by OTLP/proxmox.otlp.node.mem.pavail,5m) < {$PROXMOX.OTLP.MEM.PAVAIL.MIN.WARN}`|Warning||
|Proxmox VE: High swap space usage|<p>The used swap space of the node has stayed above {$PROXMOX.OTLP.SWAP.PUSED.MAX.WARN}% of the total swap space for the last 5 minutes.<br>If the node has no swap configured, this trigger is never fired.</p>|`min(/Proxmox VE by OTLP/proxmox.otlp.node.swap.pused,5m) > {$PROXMOX.OTLP.SWAP.PUSED.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Proxmox VE: Low available memory</li></ul>|
|Proxmox VE: Root FS: Running out of free space|<p>The space utilization of the root filesystem has stayed above {$PROXMOX.OTLP.FS.PUSED.MAX.WARN}% for the last 5 minutes.</p>|`min(/Proxmox VE by OTLP/proxmox.otlp.node.fs.pused,5m) > {$PROXMOX.OTLP.FS.PUSED.MAX.WARN}`|Warning||
|Proxmox VE: Root FS: Running out of free inodes|<p>The inode utilization of the root filesystem has stayed above {$PROXMOX.OTLP.FS.INODE.PUSED.MAX.WARN}% for the last 5 minutes.</p>|`min(/Proxmox VE by OTLP/proxmox.otlp.node.fs.inodes.pused,5m) > {$PROXMOX.OTLP.FS.INODE.PUSED.MAX.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

