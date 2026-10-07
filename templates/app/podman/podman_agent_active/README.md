
# Podman by Zabbix agent active

## Overview

This template is designed for the effortless deployment of Podman monitoring by Zabbix agent and doesn't require any external scripts.

Check the [`API documentation`](https://docs.podman.io/en/latest/_static/api.html?version=latest) for details.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Podman 5.4.2

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Run an API service that listens on localhost and custom port

Example:

```bash
podman system service --time=0 tcp:127.0.0.1:8080
```

Note: for convenience, you can configure automatic start of the API service on system startup.

## API service security

By default, the API service listens on localhost and is **not protected** by authentication. 
All processes executing on the host can access the API service and perform any actions that the user running the API service has permissions for.
This can pose a security risk if not properly configured.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PODMAN.PORT}|<p>Port on which the Podman API service is listening.</p>|`8088`|
|{$PODMAN.CONTAINER.FILTER.NAME.MATCHES}|<p>Regex to include containers by name.</p>|`.*`|
|{$PODMAN.CONTAINER.FILTER.NAME.NOT_MATCHES}|<p>Regex to exclude containers by name.</p>|`CHANGE_IF_NEEDED`|
|{$PODMAN.CONTAINER.FILTER.IMAGE.MATCHES}|<p>Regex to include containers by image.</p>|`.*`|
|{$PODMAN.CONTAINER.FILTER.IMAGE.NOT_MATCHES}|<p>Regex to exclude containers by image.</p>|`CHANGE_IF_NEEDED`|
|{$PODMAN.CONTAINER.FILTER.STATE.MATCHES}|<p>Regex to include containers by state.</p>|`.*`|
|{$PODMAN.CONTAINER.FILTER.STATE.NOT_MATCHES}|<p>Regex to exclude containers by state.</p>|`CHANGE_IF_NEEDED`|
|{$PODMAN.CONTAINER.FILTER.POD.MATCHES}|<p>Regex to include containers by pod.</p>|`.*`|
|{$PODMAN.CONTAINER.FILTER.POD.NOT_MATCHES}|<p>Regex to exclude containers by pod.</p>|`CHANGE_IF_NEEDED`|
|{$PODMAN.IMAGE.FILTER.NAME.MATCHES}|<p>Regex to include images by name.</p>|`.*`|
|{$PODMAN.IMAGE.FILTER.NAME.NOT_MATCHES}|<p>Regex to exclude images by name.</p>|`CHANGE_IF_NEEDED`|
|{$PODMAN.CONTAINER.NORMAL_STATE}|<p>Normal state of the container. Support context macro with container name, e.g. {$PODMAN.CONTAINER.NORMAL_STATE:"container_name"}.</p>|`1`|
|{$PODMAN.CONTAINER.CPU.HIGH}|<p>CPU usage percentage that is considered high for containers. Support context macro with container name, e.g. {$PODMAN.CONTAINER.CPU.HIGH:"container_name"}.</p>|`80`|
|{$PODMAN.CONTAINER.MEMORY.HIGH}|<p>Memory usage percentage that is considered high for containers. Support context macro with container name, e.g. {$PODMAN.CONTAINER.MEMORY.HIGH:"container_name"}.</p>|`80`|
|{$PODMAN.HOST.CPU.HIGH}|<p>CPU usage percentage that is considered high for the host.</p>|`80`|
|{$PODMAN.HOST.MEMORY.HIGH}|<p>Memory usage percentage that is considered high for the host.</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data [Containers]|<p>Get data from Podman API (Containers).</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/containers/json?all=true]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"error": "Error getting data from Podman API", "data": ""}`</p></li></ul>|
|Get data error|<p>Error message when failing to get data from Podman API.</p>|Dependent item|podman.get_data.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get data [Images]|<p>Get data from Podman API (Images).</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/images/json]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li></ul>|
|Get data [DF]|<p>Get data from Podman API (DF).</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/system/df]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li></ul>|
|Get data [Container stats]|<p>Get data from Podman API (Container stats).</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/containers/stats?stream=false]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li></ul>|
|Get data [Info]|<p>Get data from Podman API (Info).</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/info]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Images size|<p>Total size of all images in Podman.</p>|Dependent item|podman.images.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ImagesSize`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Podman API version|<p>Version of the Podman API.</p>|Dependent item|podman.host.api.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.version.APIVersion`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Podman version|<p>Version of Podman.</p>|Dependent item|podman.host.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.version.Version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Podman host OS architecture|<p>OS architecture of the host running Podman.</p>|Dependent item|podman.host.os.arch<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.version.OsArch`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of images|<p>Total number of images in Podman.</p>|Dependent item|podman.images.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.store.imageStore.number`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of containers|<p>Total number of containers in Podman.</p>|Dependent item|podman.containers.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.store.containerStore.number`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of paused containers|<p>Number of paused containers in Podman.</p>|Dependent item|podman.containers.paused<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.store.containerStore.paused`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of running containers|<p>Number of running containers in Podman.</p>|Dependent item|podman.containers.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.store.containerStore.running`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of stopped containers|<p>Number of stopped containers in Podman.</p>|Dependent item|podman.containers.stopped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.store.containerStore.stopped`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of CPU cores|<p>Number of CPU cores on the host running Podman.</p>|Dependent item|podman.host.cpu.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.cpus`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Host CPU utilization in user mode|<p>CPU utilization in user mode on the host running Podman.</p>|Dependent item|podman.host.cpu.util.user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.cpuUtilization.userPercent`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Host CPU utilization in system mode|<p>CPU utilization in system mode on the host running Podman.</p>|Dependent item|podman.host.cpu.util.system<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.cpuUtilization.systemPercent`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Host CPU utilization in idle mode|<p>CPU utilization in idle mode on the host running Podman.</p>|Dependent item|podman.host.cpu.util.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.cpuUtilization.idlePercent`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Host total memory|<p>Total memory on the host running Podman.</p>|Dependent item|podman.host.memory.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.memTotal`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Host free memory|<p>Free memory on the host running Podman.</p>|Dependent item|podman.host.memory.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.memFree`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Host total swap|<p>Total swap on the host running Podman.</p>|Dependent item|podman.host.swap.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.swapTotal`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Host used memory|<p>Used memory on the host running Podman.</p>|Dependent item|podman.host.memory.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.memUse`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Host free swap|<p>Free swap on the host running Podman.</p>|Dependent item|podman.host.swap.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.host.swapFree`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Host used swap|<p>Used swap on the host running Podman.</p>|Dependent item|podman.host.swap.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.swapUse`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Podman: Get data error|<p>Trigger when there is an error getting data from Podman API<br>Additional information:<br>"{ITEM.VALUE}".</p>|`last(/Podman by Zabbix agent active/podman.get_data.error)<>""`|High||
|Podman: Has been changed|<p>Trigger when Podman version changes.<br>Current version: {ITEM.LASTVALUE}.</p>|`change(/Podman by Zabbix agent active/podman.host.version)<>0`|Info||
|Podman: Host CPU utilization is high|<p>Trigger when the CPU utilization of the host running Podman is too high.</p>|`min(/Podman by Zabbix agent active/podman.host.cpu.util.idle,5m)>(100 - {$PODMAN.HOST.CPU.HIGH})`|Warning||
|Podman: Host memory utilization is high|<p>Trigger when the memory utilization of the host running Podman is too high.</p>|`min(/Podman by Zabbix agent active/podman.host.memory.free,5m)/ (last(/Podman by Zabbix agent active/podman.host.memory.total)+(last(/Podman by Zabbix agent active/podman.host.memory.total)=0)) * 100 * (last(/Podman by Zabbix agent active/podman.host.memory.total)>0)>{$PODMAN.HOST.MEMORY.HIGH}`|Warning||

### LLD rule Containers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Containers discovery|<p>Discovery of Podman containers.</p>|Dependent item|podman.containers.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.containers`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Containers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Container [{#CONTAINER.NAME}]: Get data|<p>Get data for a specific container.</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/containers/{#CONTAINER.NAME}/json]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"error": "Error getting data form container \"{#CONTAINER.NAME}\"", "data": ""}`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Get data error|<p>Error message when failing to get data for a specific container.</p>|Dependent item|podman.container.error[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Created|<p>Creation time of the container.</p>|Dependent item|podman.container.created[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.Created`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Status|<p>Current status of the container.</p>|Dependent item|podman.container.status[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.State.Status`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Started|<p>Start time of the container.</p>|Dependent item|podman.container.started[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.State.StartedAt`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Finished|<p>Finish time of the container.</p>|Dependent item|podman.container.finished[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.State.FinishedAt`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Checkpointed|<p>Checkpoint time of the container.</p>|Dependent item|podman.container.checkpointed[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.State.CheckpointedAt`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Restored|<p>Restore time of the container.</p>|Dependent item|podman.container.restored[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.State.RestoredAt`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Average CPU usage|<p>Average CPU usage of the container.</p>|Dependent item|podman.container.avgcpu[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats[?(@.ContainerID=="{#CONTAINER.ID}")].AvgCPU.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Memory usage percentage|<p>Memory usage percentage of the container.</p>|Dependent item|podman.container.mem_percentage[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats[?(@.ContainerID=="{#CONTAINER.ID}")].MemPerc.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Memory usage|<p>Memory usage of the container in bytes.</p>|Dependent item|podman.container.mem_usage[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Memory limit|<p>Memory limit of the container in bytes.</p>|Dependent item|podman.container.mem_limit[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Block input|<p>Block input of the container in bytes.</p>|Dependent item|podman.container.block_input[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Block output|<p>Block output of the container in bytes.</p>|Dependent item|podman.container.block_output[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Size of root filesystem|<p>Size of the container's root filesystem in bytes.</p>|Dependent item|podman.container.size_rootfs[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Size of read-write layer|<p>Size of the container's read-write layer in bytes.</p>|Dependent item|podman.container.size_rw[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Containers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Podman: Container [{#CONTAINER.NAME}]: Get data error|<p>Trigger when there is an error getting data for a specific container<br>Additional information:<br>"{ITEM.VALUE}".</p>|`last(/Podman by Zabbix agent active/podman.container.error[{#CONTAINER.ID}])<>""`|High|**Depends on**:<br><ul><li>Podman: Get data error</li></ul>|
|Podman: Container [{#CONTAINER.NAME}]: Not in normal state|<p>Trigger when the container is not in a normal state (e.g., not running).</p>|`last(/Podman by Zabbix agent active/podman.container.status[{#CONTAINER.ID}])<>{$PODMAN.CONTAINER.NORMAL_STATE:"{#CONTAINER.NAME}"}`|Warning||
|Podman: Container [{#CONTAINER.NAME}]: High CPU usage|<p>Trigger when the average CPU usage of the container is too high.</p>|`min(/Podman by Zabbix agent active/podman.container.avgcpu[{#CONTAINER.ID}],5m)>{$PODMAN.CONTAINER.CPU.HIGH:"{#CONTAINER.NAME}"}`|Warning||
|Podman: Container [{#CONTAINER.NAME}]: High memory usage|<p>Trigger when the memory usage percentage of the container is too high.</p>|`min(/Podman by Zabbix agent active/podman.container.mem_percentage[{#CONTAINER.ID}],5m)>{$PODMAN.CONTAINER.MEMORY.HIGH:"{#CONTAINER.NAME}"}`|Warning||

### LLD rule Mounts discovery for [{#CONTAINER.NAME}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounts discovery for [{#CONTAINER.NAME}]|<p>Discovery of container mounts.</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/containers/{#CONTAINER.NAME}/json?placebo=foo]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li><li><p>JSON Path: `$.Mounts`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Mounts discovery for [{#CONTAINER.NAME}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}]: Get data|<p>Get mount data for a specific container.</p>|Zabbix agent (active)|web.page.get[http://127.0.0.1:{$PODMAN.PORT}/v6.0.0/libpod/containers/{#CONTAINER.NAME}/json?placebo=bar&cruft={#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^([\s\S]*?)\r?\n\r?\n([\s\S]*)$ \2`</p></li><li><p>JSON Path: `$.Mounts`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}] source|<p>Source of the container mount.</p>|Dependent item|podman.container.mount.source[{#CONTAINER.ID},{#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name=="{#MOUNT.NAME}")].Source.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}] mode|<p>Mode of the container mount (read-only or read-write).</p>|Dependent item|podman.container.mount.mode[{#CONTAINER.ID},{#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name=="{#MOUNT.NAME}")].Mode.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}] read-write|<p>Whether the container mount is read-write (1) or read-only (0).</p>|Dependent item|podman.container.mount.rw[{#CONTAINER.ID},{#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name=="{#MOUNT.NAME}")].RW.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return value === "true" ? 1 : 0;`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}] propagation|<p>Propagation mode of the container mount.</p>|Dependent item|podman.container.mount.propagation[{#CONTAINER.ID},{#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name=="{#MOUNT.NAME}")].Propagation.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}] options|<p>Additional options of the container mount.</p>|Dependent item|podman.container.mount.options[{#CONTAINER.ID},{#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name=="{#MOUNT.NAME}")].Options.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}] size|<p>Size of the container mount in bytes.</p>|Dependent item|podman.container.mount.size[{#CONTAINER.ID},{#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Volumes[?(@.VolumeName=="{#MOUNT.NAME}")].Size.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Mount [{#MOUNT.DESTINATION}] reclaimable size|<p>Size that can be reclaimed of the container mount in bytes.</p>|Dependent item|podman.container.mount.reclaimable_size[{#CONTAINER.ID},{#MOUNT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### LLD rule Networks discovery for [{#CONTAINER.NAME}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Networks discovery for [{#CONTAINER.NAME}]|<p>Discovery of container networks.</p>|Dependent item|podman.containers.networks.discovery[{#CONTAINER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats[?(@.ContainerID=="{#CONTAINER.ID}")].Network.first()`</p><p>⛔️Custom on fail: Set value to: `{}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Networks discovery for [{#CONTAINER.NAME}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] received bytes|<p>Total bytes received on the network interface of the container.</p>|Dependent item|podman.container.network.rx_bytes[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] transmitted bytes|<p>Total bytes transmitted on the network interface of the container.</p>|Dependent item|podman.container.network.tx_bytes[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] input rate|<p>Average bits received per second on the network interface of the container.</p>|Dependent item|podman.container.network.rx_rates[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] output rate|<p>Average bits transmitted per second on the network interface of the container.</p>|Dependent item|podman.container.network.tx_rates[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] input packets rate|<p>Average packets received per second on the network interface of the container.</p>|Dependent item|podman.container.network.packets_rx_rates[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] output packets rate|<p>Average packets transmitted per second on the network interface of the container.</p>|Dependent item|podman.container.network.packets_tx_rates[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] receive errors|<p>Count of receive errors on the network interface of the container.</p>|Dependent item|podman.container.network.errors_rx[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] transmit errors|<p>Count of transmit errors on the network interface of the container.</p>|Dependent item|podman.container.network.errors_tx[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] receive dropped packets|<p>Count of dropped packets on the network interface of the container.</p>|Dependent item|podman.container.network.dropped_rx[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] transmit dropped packets|<p>Count of dropped packets on the network interface of the container.</p>|Dependent item|podman.container.network.dropped_tx[{#CONTAINER.ID},{#NETWORK.INTERFACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Trigger prototypes for Networks discovery for [{#CONTAINER.NAME}]

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Podman: Container [{#CONTAINER.NAME}]: Interface [{#NETWORK.INTERFACE}] errors|<p>Trigger when count of errors on the network interface of the container is changing.</p>|`change(/Podman by Zabbix agent active/podman.container.network.errors_tx[{#CONTAINER.ID},{#NETWORK.INTERFACE}])>0 or change(/Podman by Zabbix agent active/podman.container.network.errors_rx[{#CONTAINER.ID},{#NETWORK.INTERFACE}])>0`|Warning||

### LLD rule Images discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Images discovery|<p>Discovery of Podman images.</p>|Dependent item|podman.images.discovery|

### Item prototypes for Images discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Image [{#IMAGE.NAME}]: Creation time|<p>Creation time of the image in Unix timestamp format.</p>|Dependent item|podman.image.created[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id=="{#IMAGE.ID}")].Created.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Image [{#IMAGE.NAME}]: Size|<p>Size of the image in bytes.</p>|Dependent item|podman.image.size[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id=="{#IMAGE.ID}")].Size.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Image [{#IMAGE.NAME}]: Shared size|<p>Shared size of the image in bytes.</p>|Dependent item|podman.image.shared_size[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id=="{#IMAGE.ID}")].SharedSize.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Image [{#IMAGE.NAME}]: Virtual size|<p>Virtual size of the image in bytes.</p>|Dependent item|podman.image.virtual_size[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id=="{#IMAGE.ID}")].VirtualSize.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Image [{#IMAGE.NAME}]: Containers count|<p>Number of containers using the image.</p>|Dependent item|podman.image.containers[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id=="{#IMAGE.ID}")].Containers.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Image [{#IMAGE.NAME}]: Architecture|<p>Architecture of the image.</p>|Dependent item|podman.image.architecture[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id=="{#IMAGE.ID}")].Arch.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Image [{#IMAGE.NAME}]: Operating system|<p>Operating system of the image.</p>|Dependent item|podman.image.os[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Id=="{#IMAGE.ID}")].Os.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Image [{#IMAGE.NAME}]: Unique size|<p>Unique size of the image in bytes.</p>|Dependent item|podman.image.unique_size[{#IMAGE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Images[?(@.ImageID=="{#IMAGE.ID}")].UniqueSize.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Images discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Podman: Image [{#IMAGE.NAME}]: Unused|<p>Trigger when the image is not used by any container.</p>|`last(/Podman by Zabbix agent active/podman.image.containers[{#IMAGE.ID}])=0`|Info||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

