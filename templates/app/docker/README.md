
# Docker by Zabbix agent 2

## Overview

The template to monitor Docker engine by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Docker by Zabbix agent 2` — collects metrics by polling zabbix-agent2.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Docker 23.0.3

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Setup and configure Zabbix agent 2 compiled with the Docker monitoring plugin. The user by which the Zabbix agent 2 is running should have access permissions to the Docker socket.

Test availability: `zabbix_get -s docker-host -k docker.info`

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$DOCKER.LLD.FILTER.CONTAINER.MATCHES}|<p>Filter of discoverable containers.</p>|`.*`|
|{$DOCKER.LLD.FILTER.CONTAINER.NOT_MATCHES}|<p>Filter to exclude discovered containers.</p>|`CHANGE_IF_NEEDED`|
|{$DOCKER.LLD.FILTER.IMAGE.MATCHES}|<p>Filter of discoverable images.</p>|`.*`|
|{$DOCKER.LLD.FILTER.IMAGE.NOT_MATCHES}|<p>Filter to exclude discovered images.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Docker: Ping||Zabbix agent|docker.ping<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Docker: Get info||Zabbix agent|docker.info|
|Docker: Get containers||Zabbix agent|docker.containers|
|Docker: Get images||Zabbix agent|docker.images|
|Docker: Get data_usage||Zabbix agent|docker.data_usage|
|Docker: Containers total|<p>Total number of containers on this host.</p>|Dependent item|docker.containers.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Containers`</p></li></ul>|
|Docker: Containers running|<p>Total number of containers running on this host.</p>|Dependent item|docker.containers.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ContainersRunning`</p></li></ul>|
|Docker: Containers stopped|<p>Total number of containers stopped on this host.</p>|Dependent item|docker.containers.stopped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ContainersStopped`</p></li></ul>|
|Docker: Containers paused|<p>Total number of containers paused on this host.</p>|Dependent item|docker.containers.paused<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ContainersPaused`</p></li></ul>|
|Docker: Images total|<p>Number of images with intermediate image layers.</p>|Dependent item|docker.images.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Images`</p></li></ul>|
|Docker: Storage driver|<p>Docker storage driver.</p><p>https://docs.docker.com/storage/storagedriver/</p>|Dependent item|docker.driver<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Driver`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Memory limit enabled||Dependent item|docker.mem_limit.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MemoryLimit`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Swap limit enabled||Dependent item|docker.swap_limit.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SwapLimit`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Kernel memory enabled||Dependent item|docker.kernel_mem.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.KernelMemory`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Kernel memory TCP enabled||Dependent item|docker.kernel_mem_tcp.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.KernelMemoryTCP`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: CPU CFS Period enabled|<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p>|Dependent item|docker.cpu_cfs_period.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CpuCfsPeriod`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: CPU CFS Quota enabled|<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p>|Dependent item|docker.cpu_cfs_quota.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CpuCfsQuota`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: CPU Shares enabled|<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p>|Dependent item|docker.cpu_shares.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CPUShares`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: CPU Set enabled|<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p>|Dependent item|docker.cpu_set.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CPUSet`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Pids limit enabled||Dependent item|docker.pids_limit.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PidsLimit`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: IPv4 Forwarding enabled||Dependent item|docker.ipv4_forwarding.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.IPv4Forwarding`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Debug enabled||Dependent item|docker.debug.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Debug`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Nfd|<p>Number of used File Descriptors.</p>|Dependent item|docker.nfd<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NFd`</p></li></ul>|
|Docker: OomKill disabled||Dependent item|docker.oomkill.disabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.OomKillDisable`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Goroutines|<p>Number of goroutines.</p>|Dependent item|docker.goroutines<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NGoroutines`</p></li></ul>|
|Docker: Logging driver||Dependent item|docker.logging_driver<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LoggingDriver`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Cgroup driver||Dependent item|docker.cgroup_driver<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CgroupDriver`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: NEvents listener||Dependent item|docker.nevents_listener<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NEventsListener`</p></li></ul>|
|Docker: Kernel version||Dependent item|docker.kernel_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.KernelVersion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Operating system||Dependent item|docker.operating_system<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.OperatingSystem`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: OS type||Dependent item|docker.os_type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.OSType`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Architecture||Dependent item|docker.architecture<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Architecture`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: NCPU||Dependent item|docker.ncpu<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NCPU`</p></li></ul>|
|Docker: Memory total||Dependent item|docker.mem.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MemTotal`</p></li></ul>|
|Docker: Docker root dir||Dependent item|docker.root_dir<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DockerRootDir`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Name||Dependent item|docker.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Name`</p></li></ul>|
|Docker: Server version||Dependent item|docker.server_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ServerVersion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Default runtime||Dependent item|docker.default_runtime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DefaultRuntime`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Live restore enabled||Dependent item|docker.live_restore.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LiveRestoreEnabled`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Docker: Layers size||Dependent item|docker.layers_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LayersSize`</p></li></ul>|
|Docker: Images size||Dependent item|docker.images_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Images[*].Size.sum()`</p></li></ul>|
|Docker: Containers size||Dependent item|docker.containers_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Containers[*].SizeRw.sum()`</p></li></ul>|
|Docker: Volumes size||Dependent item|docker.volumes_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Volumes[*].UsageData.Size.sum()`</p></li></ul>|
|Docker: Images available|<p>Number of top-level images.</p>|Dependent item|docker.images.top_level<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.length()`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Docker: Service is down||`last(/Docker by Zabbix agent 2/docker.ping)=0`|Average|**Manual close**: Yes|
|Docker: Failed to fetch info data|<p>Zabbix has not received data for items for the last 30 minutes.</p>|`nodata(/Docker by Zabbix agent 2/docker.name,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Docker: Service is down</li></ul>|
|Docker: Version has changed|<p>Docker version has changed. Acknowledge to close the problem manually.</p>|`last(/Docker by Zabbix agent 2/docker.server_version,#1)<>last(/Docker by Zabbix agent 2/docker.server_version,#2) and length(last(/Docker by Zabbix agent 2/docker.server_version))>0`|Info|**Manual close**: Yes|

### LLD rule Images discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Images discovery|<p>Discovery of images metrics.</p>|Zabbix agent|docker.images.discovery|

### Item prototypes for Images discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Image {#NAME}: Created||Dependent item|docker.image.created["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id == "{#ID}")].Created.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Image {#NAME}: Size||Dependent item|docker.image.size["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id == "{#ID}")].Size.first()`</p></li></ul>|

### LLD rule Containers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Containers discovery|<p>Discovery of containers metrics.</p><p></p><p>Parameter:</p><p>true  - Returns all containers</p><p>false - Returns only running containers</p>|Zabbix agent|docker.containers.discovery[false]|

### Item prototypes for Containers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Container {#NAME}: Get stats|<p>Get container stats based on resource usage.</p>|Zabbix agent|docker.container_stats["{#NAME}"]|
|Container {#NAME}: CPU total usage per second||Dependent item|docker.container_stats.cpu_usage.total.rate["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.cpu_usage.total_usage`</p></li><li>Change per second</li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Container {#NAME}: CPU percent usage||Dependent item|docker.container_stats.cpu_pct_usage["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.cpu_usage.percent_usage`</p></li></ul>|
|Container {#NAME}: CPU kernelmode usage per second||Dependent item|docker.container_stats.cpu_usage.kernel.rate["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.cpu_usage.usage_in_kernelmode`</p></li><li>Change per second</li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Container {#NAME}: CPU usermode usage per second||Dependent item|docker.container_stats.cpu_usage.user.rate["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.cpu_usage.usage_in_usermode`</p></li><li>Change per second</li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Container {#NAME}: Online CPUs||Dependent item|docker.container_stats.online_cpus["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.online_cpus`</p></li></ul>|
|Container {#NAME}: Throttling periods|<p>Number of periods with throttling active.</p>|Dependent item|docker.container_stats.cpu_usage.throttling_periods["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.throttling_data.periods`</p></li></ul>|
|Container {#NAME}: Throttled periods|<p>Number of periods when the container hits its throttling limit.</p>|Dependent item|docker.container_stats.cpu_usage.throttled_periods["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.throttling_data.throttled_periods`</p></li></ul>|
|Container {#NAME}: Throttled time|<p>Aggregate time the container was throttled for in nanoseconds.</p>|Dependent item|docker.container_stats.cpu_usage.throttled_time["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_stats.throttling_data.throttled_time`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Container {#NAME}: Memory usage||Dependent item|docker.container_stats.memory.usage["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory_stats.usage`</p></li></ul>|
|Container {#NAME}: Memory maximum usage||Dependent item|docker.container_stats.memory.max_usage["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory_stats.max_usage`</p></li></ul>|
|Container {#NAME}: Memory commit bytes||Dependent item|docker.container_stats.memory.commit_bytes["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory_stats.commitbytes`</p></li></ul>|
|Container {#NAME}: Memory commit peak bytes||Dependent item|docker.container_stats.memory.commit_peak_bytes["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory_stats.commitpeakbytes`</p></li></ul>|
|Container {#NAME}: Memory private working set||Dependent item|docker.container_stats.memory.private_working_set["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory_stats.privateworkingset`</p></li></ul>|
|Container {#NAME}: Current PIDs count|<p>Current number of PIDs the container has created.</p>|Dependent item|docker.container_stats.pids_stats.current["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pids_stats.current`</p></li></ul>|
|Container {#NAME}: Networks bytes received per second||Dependent item|docker.networks.rx_bytes["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].rx_bytes.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Networks packets received per second||Dependent item|docker.networks.rx_packets["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].rx_packets.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Networks errors received per second||Dependent item|docker.networks.rx_errors["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].rx_errors.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Networks incoming packets dropped per second||Dependent item|docker.networks.rx_dropped["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].rx_dropped.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Networks bytes sent per second||Dependent item|docker.networks.tx_bytes["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].tx_bytes.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Networks packets sent per second||Dependent item|docker.networks.tx_packets["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].tx_packets.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Networks errors sent per second||Dependent item|docker.networks.tx_errors["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].tx_errors.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Networks outgoing packets dropped per second||Dependent item|docker.networks.tx_dropped["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks[*].tx_dropped.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Container {#NAME}: Get info|<p>Return low-level information about a container.</p>|Zabbix agent|docker.container_info["{#NAME}",full]|
|Container {#NAME}: Created||Dependent item|docker.container_info.created["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Container {#NAME}: Image||Dependent item|docker.container_info.image["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Names[0] == "{#NAME}")].Image.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Container {#NAME}: Restart count||Dependent item|docker.container_info.restart_count["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.RestartCount`</p></li></ul>|
|Container {#NAME}: Status||Dependent item|docker.container_info.state.status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Container {#NAME}: Health status|<p>Container's `HEALTHCHECK`.</p>|Dependent item|docker.container_info.state.health["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>In range: `1 -> 4`</p><p>⛔️Custom on fail: Set value to: `4`</p></li></ul>|
|Container {#NAME}: Health failing streak||Dependent item|docker.container_info.state.health.failing["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Health.FailingStreak`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Container {#NAME}: Running||Dependent item|docker.container_info.state.running["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Running`</p></li><li>Boolean to decimal</li></ul>|
|Container {#NAME}: Paused||Dependent item|docker.container_info.state.paused["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Paused`</p></li><li>Boolean to decimal</li></ul>|
|Container {#NAME}: Restarting||Dependent item|docker.container_info.state.restarting["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Restarting`</p></li><li>Boolean to decimal</li></ul>|
|Container {#NAME}: OOMKilled||Dependent item|docker.container_info.state.oomkilled["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.OOMKilled`</p></li><li>Boolean to decimal</li></ul>|
|Container {#NAME}: Dead||Dependent item|docker.container_info.state.dead["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Dead`</p></li><li>Boolean to decimal</li></ul>|
|Container {#NAME}: Pid||Dependent item|docker.container_info.state.pid["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Pid`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Container {#NAME}: Exit code||Dependent item|docker.container_info.state.exitcode["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.ExitCode`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Container {#NAME}: Error||Dependent item|docker.container_info.state.error["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State.Error`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Container {#NAME}: Started at||Dependent item|docker.container_info.started["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Container {#NAME}: Finished at|<p>Time at which the container last terminated.</p>|Dependent item|docker.container_info.finished["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Containers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Container {#NAME}: Health state container is unhealthy|<p>Container health state is unhealthy.</p>|`count(/Docker by Zabbix agent 2/docker.container_info.state.health["{#NAME}"],2m,,2)>=2`|High||
|Container {#NAME}: Container has been stopped with error code||`last(/Docker by Zabbix agent 2/docker.container_info.state.exitcode["{#NAME}"])>0 and last(/Docker by Zabbix agent 2/docker.container_info.state.running["{#NAME}"])=0`|Average|**Manual close**: Yes|
|Container {#NAME}: An error has occurred in the container|<p>Container {#NAME} has an error. Acknowledge to close the problem manually.</p>|`last(/Docker by Zabbix agent 2/docker.container_info.state.error["{#NAME}"],#1)<>last(/Docker by Zabbix agent 2/docker.container_info.state.error["{#NAME}"],#2) and length(last(/Docker by Zabbix agent 2/docker.container_info.state.error["{#NAME}"]))>0`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

