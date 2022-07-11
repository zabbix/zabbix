
# Docker by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Docker engine by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Docker by Zabbix agent 2` — collects metrics by polling zabbix-agent2.



This template was tested on:

- Docker, version 19.03.5

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

Setup and configure zabbix-agent2 compiled with the Docker monitoring plugin.

Test availability: `zabbix_get -s docker-host -k docker.info`


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$DOCKER.LLD.FILTER.CONTAINER.MATCHES} |<p>Filter of discoverable containers</p> |`.*` |
|{$DOCKER.LLD.FILTER.CONTAINER.NOT_MATCHES} |<p>Filter to exclude discovered containers</p> |`CHANGE_IF_NEEDED` |
|{$DOCKER.LLD.FILTER.IMAGE.MATCHES} |<p>Filter of discoverable images</p> |`.*` |
|{$DOCKER.LLD.FILTER.IMAGE.NOT_MATCHES} |<p>Filter to exclude discovered images</p> |`CHANGE_IF_NEEDED` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Containers discovery |<p>Discovery for containers metrics</p><p>Parameter:</p><p>true  - Returns all containers</p><p>false - Returns only running containers</p> |ZABBIX_PASSIVE |docker.containers.discovery[false]<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$DOCKER.LLD.FILTER.CONTAINER.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$DOCKER.LLD.FILTER.CONTAINER.NOT_MATCHES}`</p> |
|Images discovery |<p>Discovery for images metrics</p> |ZABBIX_PASSIVE |docker.images.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$DOCKER.LLD.FILTER.IMAGE.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$DOCKER.LLD.FILTER.IMAGE.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Docker |Docker: Ping | |ZABBIX_PASSIVE |docker.ping<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Docker |Docker: Containers total |<p>Total number of containers on this host</p> |DEPENDENT |docker.containers.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.Containers`</p> |
|Docker |Docker: Containers running |<p>Total number of containers running on this host</p> |DEPENDENT |docker.containers.running<p>**Preprocessing**:</p><p>- JSONPATH: `$.ContainersRunning`</p> |
|Docker |Docker: Containers stopped |<p>Total number of containers stopped on this host</p> |DEPENDENT |docker.containers.stopped<p>**Preprocessing**:</p><p>- JSONPATH: `$.ContainersStopped`</p> |
|Docker |Docker: Containers paused |<p>Total number of containers paused on this host</p> |DEPENDENT |docker.containers.paused<p>**Preprocessing**:</p><p>- JSONPATH: `$.ContainersPaused`</p> |
|Docker |Docker: Images total |<p>Number of images with intermediate image layers</p> |DEPENDENT |docker.images.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.Images`</p> |
|Docker |Docker: Storage driver |<p>Docker storage driver </p><p> https://docs.docker.com/storage/storagedriver/</p> |DEPENDENT |docker.driver<p>**Preprocessing**:</p><p>- JSONPATH: `$.Driver`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Memory limit enabled |<p>-</p> |DEPENDENT |docker.mem_limit.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.MemoryLimit`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Swap limit enabled |<p>-</p> |DEPENDENT |docker.swap_limit.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.SwapLimit`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Kernel memory enabled |<p>-</p> |DEPENDENT |docker.kernel_mem.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.KernelMemory`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Kernel memory TCP enabled |<p>-</p> |DEPENDENT |docker.kernel_mem_tcp.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.KernelMemoryTCP`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: CPU CFS Period enabled |<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p> |DEPENDENT |docker.cpu_cfs_period.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.CpuCfsPeriod`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: CPU CFS Quota enabled |<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p> |DEPENDENT |docker.cpu_cfs_quota.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.CpuCfsQuota`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: CPU Shares enabled |<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p> |DEPENDENT |docker.cpu_shares.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.CPUShares`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: CPU Set enabled |<p>https://docs.docker.com/config/containers/resource_constraints/#configure-the-default-cfs-scheduler</p> |DEPENDENT |docker.cpu_set.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.CPUSet`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Pids limit enabled |<p>-</p> |DEPENDENT |docker.pids_limit.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.PidsLimit`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: IPv4 Forwarding enabled |<p>-</p> |DEPENDENT |docker.ipv4_forwarding.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.IPv4Forwarding`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Debug enabled |<p>-</p> |DEPENDENT |docker.debug.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.Debug`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Nfd |<p>Number of used File Descriptors</p> |DEPENDENT |docker.nfd<p>**Preprocessing**:</p><p>- JSONPATH: `$.NFd`</p> |
|Docker |Docker: OomKill disabled |<p>-</p> |DEPENDENT |docker.oomkill.disabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.OomKillDisable`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Goroutines |<p>Number of goroutines</p> |DEPENDENT |docker.goroutines<p>**Preprocessing**:</p><p>- JSONPATH: `$.NGoroutines`</p> |
|Docker |Docker: Logging driver |<p>-</p> |DEPENDENT |docker.logging_driver<p>**Preprocessing**:</p><p>- JSONPATH: `$.LoggingDriver`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Cgroup driver |<p>-</p> |DEPENDENT |docker.cgroup_driver<p>**Preprocessing**:</p><p>- JSONPATH: `$.CgroupDriver`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: NEvents listener |<p>-</p> |DEPENDENT |docker.nevents_listener<p>**Preprocessing**:</p><p>- JSONPATH: `$.NEventsListener`</p> |
|Docker |Docker: Kernel version |<p>-</p> |DEPENDENT |docker.kernel_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.KernelVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Operating system |<p>-</p> |DEPENDENT |docker.operating_system<p>**Preprocessing**:</p><p>- JSONPATH: `$.OperatingSystem`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: OS type |<p>-</p> |DEPENDENT |docker.os_type<p>**Preprocessing**:</p><p>- JSONPATH: `$.OSType`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Architecture |<p>-</p> |DEPENDENT |docker.architecture<p>**Preprocessing**:</p><p>- JSONPATH: `$.Architecture`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: NCPU |<p>-</p> |DEPENDENT |docker.ncpu<p>**Preprocessing**:</p><p>- JSONPATH: `$.NCPU`</p> |
|Docker |Docker: Memory total |<p>-</p> |DEPENDENT |docker.mem.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.MemTotal`</p> |
|Docker |Docker: Docker root dir |<p>-</p> |DEPENDENT |docker.root_dir<p>**Preprocessing**:</p><p>- JSONPATH: `$.DockerRootDir`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Name |<p>-</p> |DEPENDENT |docker.name<p>**Preprocessing**:</p><p>- JSONPATH: `$.Name`</p> |
|Docker |Docker: Server version |<p>-</p> |DEPENDENT |docker.server_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.ServerVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Default runtime |<p>-</p> |DEPENDENT |docker.default_runtime<p>**Preprocessing**:</p><p>- JSONPATH: `$.DefaultRuntime`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Live restore enabled |<p>-</p> |DEPENDENT |docker.live_restore.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.LiveRestoreEnabled`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Docker: Layers size |<p>-</p> |DEPENDENT |docker.layers_size<p>**Preprocessing**:</p><p>- JSONPATH: `$.LayersSize`</p> |
|Docker |Docker: Images size |<p>-</p> |DEPENDENT |docker.images_size<p>**Preprocessing**:</p><p>- JSONPATH: `$.Images[*].Size.sum()`</p> |
|Docker |Docker: Containers size |<p>-</p> |DEPENDENT |docker.containers_size<p>**Preprocessing**:</p><p>- JSONPATH: `$.Containers[*].SizeRw.sum()`</p> |
|Docker |Docker: Volumes size |<p>-</p> |DEPENDENT |docker.volumes_size<p>**Preprocessing**:</p><p>- JSONPATH: `$.Volumes[*].UsageData.Size.sum()`</p> |
|Docker |Docker: Images available |<p>Number of top-level images</p> |DEPENDENT |docker.images.top_level<p>**Preprocessing**:</p><p>- JSONPATH: `$.length()`</p> |
|Docker |Image {#NAME}: Created |<p>-</p> |DEPENDENT |docker.image.created["{#ID}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Id == "{#ID}")].Created.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Image {#NAME}: Size |<p>-</p> |DEPENDENT |docker.image.size["{#ID}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Id == "{#ID}")].Size.first()`</p> |
|Docker |Container {#NAME}: Get stats |<p>Get container stats based on resource usage</p> |ZABBIX_PASSIVE |docker.container_stats["{#NAME}"] |
|Docker |Container {#NAME}: CPU total usage per second |<p>-</p> |DEPENDENT |docker.container_stats.cpu_usage.total.rate["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.cpu_usage.total_usage`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `1.0E-9`</p> |
|Docker |Container {#NAME}: CPU percent usage |<p>-</p> |DEPENDENT |docker.container_stats.cpu_pct_usage["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.cpu_usage.percent_usage`</p> |
|Docker |Container {#NAME}: CPU kernelmode usage per second |<p>-</p> |DEPENDENT |docker.container_stats.cpu_usage.kernel.rate["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.cpu_usage.usage_in_kernelmode`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `1.0E-9`</p> |
|Docker |Container {#NAME}: CPU usermode usage per second |<p>-</p> |DEPENDENT |docker.container_stats.cpu_usage.user.rate["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.cpu_usage.usage_in_usermode`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `1.0E-9`</p> |
|Docker |Container {#NAME}: Online CPUs |<p>-</p> |DEPENDENT |docker.container_stats.online_cpus["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.online_cpus`</p> |
|Docker |Container {#NAME}: Throttling periods |<p>Number of periods with throttling active</p> |DEPENDENT |docker.container_stats.cpu_usage.throttling_periods["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.throttling_data.periods`</p> |
|Docker |Container {#NAME}: Throttled periods |<p>Number of periods when the container hits its throttling limit</p> |DEPENDENT |docker.container_stats.cpu_usage.throttled_periods["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.throttling_data.throttled_periods`</p> |
|Docker |Container {#NAME}: Throttled time |<p>Aggregate time the container was throttled for in nanoseconds</p> |DEPENDENT |docker.container_stats.cpu_usage.throttled_time["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu_stats.throttling_data.throttled_time`</p><p>- MULTIPLIER: `1.0E-9`</p> |
|Docker |Container {#NAME}: Memory usage |<p>-</p> |DEPENDENT |docker.container_stats.memory.usage["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.memory_stats.usage`</p> |
|Docker |Container {#NAME}: Memory maximum usage |<p>-</p> |DEPENDENT |docker.container_stats.memory.max_usage["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.memory_stats.max_usage`</p> |
|Docker |Container {#NAME}: Memory commit bytes |<p>-</p> |DEPENDENT |docker.container_stats.memory.commit_bytes["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.memory_stats.commitbytes`</p> |
|Docker |Container {#NAME}: Memory commit peak bytes |<p>-</p> |DEPENDENT |docker.container_stats.memory.commit_peak_bytes["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.memory_stats.commitpeakbytes`</p> |
|Docker |Container {#NAME}: Memory private working set |<p>-</p> |DEPENDENT |docker.container_stats.memory.private_working_set["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.memory_stats.privateworkingset`</p> |
|Docker |Container {#NAME}: Networks bytes received per second |<p>-</p> |DEPENDENT |docker.networks.rx_bytes["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].rx_bytes.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Networks packets received per second |<p>-</p> |DEPENDENT |docker.networks.rx_packets["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].rx_packets.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Networks errors received per second |<p>-</p> |DEPENDENT |docker.networks.rx_errors["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].rx_errors.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Networks incoming packets dropped per second |<p>-</p> |DEPENDENT |docker.networks.rx_dropped["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].rx_dropped.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Networks bytes sent per second |<p>-</p> |DEPENDENT |docker.networks.tx_bytes["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].tx_bytes.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Networks packets sent per second |<p>-</p> |DEPENDENT |docker.networks.tx_packets["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].tx_packets.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Networks errors sent per second |<p>-</p> |DEPENDENT |docker.networks.tx_errors["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].tx_errors.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Networks outgoing packets dropped per second |<p>-</p> |DEPENDENT |docker.networks.tx_dropped["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.networks[*].tx_dropped.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Docker |Container {#NAME}: Get info |<p>Return low-level information about a container</p> |ZABBIX_PASSIVE |docker.container_info["{#NAME}"] |
|Docker |Container {#NAME}: Created |<p>-</p> |DEPENDENT |docker.container_info.created["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Created`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Container {#NAME}: Image |<p>-</p> |DEPENDENT |docker.container_info.image["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Names[0] == "{#NAME}")].Image.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Container {#NAME}: Restart count |<p>-</p> |DEPENDENT |docker.container_info.restart_count["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.RestartCount`</p> |
|Docker |Container {#NAME}: Status |<p>-</p> |DEPENDENT |docker.container_info.state.status["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.Status`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Docker |Container {#NAME}: Running |<p>-</p> |DEPENDENT |docker.container_info.state.running["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.Running`</p><p>- BOOL_TO_DECIMAL</p> |
|Docker |Container {#NAME}: Paused |<p>-</p> |DEPENDENT |docker.container_info.state.paused["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.Paused`</p><p>- BOOL_TO_DECIMAL</p> |
|Docker |Container {#NAME}: Restarting |<p>-</p> |DEPENDENT |docker.container_info.state.restarting["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.Restarting`</p><p>- BOOL_TO_DECIMAL</p> |
|Docker |Container {#NAME}: OOMKilled |<p>-</p> |DEPENDENT |docker.container_info.state.oomkilled["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.OOMKilled`</p><p>- BOOL_TO_DECIMAL</p> |
|Docker |Container {#NAME}: Dead |<p>-</p> |DEPENDENT |docker.container_info.state.dead["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.Dead`</p><p>- BOOL_TO_DECIMAL</p> |
|Docker |Container {#NAME}: Pid |<p>-</p> |DEPENDENT |docker.container_info.state.pid["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.Pid`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Container {#NAME}: Exit code |<p>-</p> |DEPENDENT |docker.container_info.state.exitcode["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.ExitCode`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Container {#NAME}: Error |<p>-</p> |DEPENDENT |docker.container_info.state.error["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.Error`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Container {#NAME}: Started at |<p>-</p> |DEPENDENT |docker.container_info.started["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.StartedAt`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Docker |Container {#NAME}: Finished at |<p>-</p> |DEPENDENT |docker.container_info.finished["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.State.FinishedAt`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Zabbix raw items |Docker: Get info | |ZABBIX_PASSIVE |docker.info |
|Zabbix raw items |Docker: Get containers | |ZABBIX_PASSIVE |docker.containers |
|Zabbix raw items |Docker: Get images | |ZABBIX_PASSIVE |docker.images |
|Zabbix raw items |Docker: Get data_usage | |ZABBIX_PASSIVE |docker.data_usage |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Docker: Service is down |<p>-</p> |`last(/Docker by Zabbix agent 2/docker.ping)=0` |AVERAGE |<p>Manual close: YES</p> |
|Docker: Failed to fetch info data |<p>Zabbix has not received data for items for the last 30 minutes</p> |`nodata(/Docker by Zabbix agent 2/docker.name,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Docker: Service is down</p> |
|Docker: Version has changed |<p>Docker version has changed. Ack to close.</p> |`last(/Docker by Zabbix agent 2/docker.server_version,#1)<>last(/Docker by Zabbix agent 2/docker.server_version,#2) and length(last(/Docker by Zabbix agent 2/docker.server_version))>0` |INFO |<p>Manual close: YES</p> |
|Container {#NAME}: Container has been stopped with error code |<p>-</p> |`last(/Docker by Zabbix agent 2/docker.container_info.state.exitcode["{#NAME}"])>0 and last(/Docker by Zabbix agent 2/docker.container_info.state.running["{#NAME}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|Container {#NAME}: An error has occurred in the container |<p>Container {#NAME} has an error. Ack to close.</p> |`last(/Docker by Zabbix agent 2/docker.container_info.state.error["{#NAME}"],#1)<>last(/Docker by Zabbix agent 2/docker.container_info.state.error["{#NAME}"],#2) and length(last(/Docker by Zabbix agent 2/docker.container_info.state.error["{#NAME}"]))>0` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/435429-discussion-thread-for-official-zabbix-template-docker).

