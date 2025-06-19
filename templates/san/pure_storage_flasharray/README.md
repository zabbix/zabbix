
# Pure Storage FlashArray v1 by HTTP

## Overview

This template is designed for the effortless deployment of Pure Storage FlashArray v1 monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Pure Storage FlashArray FA-X20R4 (Purity//FA: 6.7.1)

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a host for the Pure Storage FlashArray device and assign to it the "Pure Storage FlashArray v1 by HTTP" template.
2. Enter your API token from the FlashArray (Purity//FA) graphical user interface into the `{$PURE.FLASHARRAY.API.TOKEN}` macro.
3. It is possible to authenticate using username and password by entering them from the FlashArray (Purity//FA) graphical user interface into the `{$PURE.FLASHARRAY.API.USERNAME}` and `{$PURE.FLASHARRAY.API.PASSWORD}` macros.
4. Set your FlashArray (Purity//FA) graphical user interface URL as the `{$PURE.FLASHARRAY.API.URL}` macro value.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PURE.FLASHARRAY.API.URL}|<p>Pure Storage FlashArray Web interface URL.</p>||
|{$PURE.FLASHARRAY.API.TOKEN}|<p>Pure Storage FlashArray API token.</p>||
|{$PURE.FLASHARRAY.API.USERNAME}|<p>Pure Storage FlashArray Web interface username.</p>||
|{$PURE.FLASHARRAY.API.PASSWORD}|<p>Pure Storage FlashArray Web interface password.</p>||
|{$PURE.FLASHARRAY.API.VERSION}|<p>Pure Storage FlashArray API version.</p>|`1.19`|
|{$PURE.FLASHARRAY.CERT.EXPIRY.WARN}|<p>Number of days until the certificate expires.</p>|`7`|
|{$PURE.FLASHARRAY.DATA.TIMEOUT}|<p>Response timeout for the API.</p>|`15s`|
|{$PURE.FLASHARRAY.POD.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable storage pods by name.</p>|`.*`|
|{$PURE.FLASHARRAY.POD.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable storage pods by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.CERT.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable certificates by name.</p>|`.*`|
|{$PURE.FLASHARRAY.CERT.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable certificates by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.CTRL.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable controllers by index.</p>|`.*`|
|{$PURE.FLASHARRAY.CTRL.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable controllers by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.TEMP.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable temperature sensors by index.</p>|`.*`|
|{$PURE.FLASHARRAY.TEMP.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable temperature sensors by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.POWER.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable power supply components by index.</p>|`.*`|
|{$PURE.FLASHARRAY.POWER.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable power supply components by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.FAN.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable fans by index.</p>|`.*`|
|{$PURE.FLASHARRAY.FAN.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable fans by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.DRIVE.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable storage drives by name.</p>|`.*`|
|{$PURE.FLASHARRAY.DRIVE.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable storage drives by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable hosts by name.</p>|`.*`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable hosts by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.GROUP.MATCHES}|<p>Filter of discoverable hosts by group.</p>|`.*`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.GROUP.NOT_MATCHES}|<p>Filter to exclude discoverable hosts by group.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.NETIF.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable network interfaces by name.</p>|`.*`|
|{$PURE.FLASHARRAY.NETIF.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable network interfaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.VOLUME.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable storage volumes by name.</p>|`.*`|
|{$PURE.FLASHARRAY.VOLUME.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable storage volumes by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. For more details, see the documentation at https://www.zabbix.com/documentation/7.4/manual/config/items/itemtypes/http</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Authentication|<p>Pure Storage FlashArray authentication with username and password or API token usage.</p><p>Returns a session ID that is required only once and is used for all dependent script items.</p><p>A session will expire after 30 minutes. Check the template documentation for details.</p>|Script|purestorage.flasharray.auth|
|Authentication item errors|<p>Collects errors from the authentication item.</p>|Dependent item|purestorage.flasharray.auth.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get hardware data|<p>Collects hardware from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.hardware.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage hardware item errors|<p>Collects errors from hardware retrieval.</p>|Dependent item|purestorage.flasharray.hardware.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get hosts|<p>Collects all hosts from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.hosts.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage hosts item errors|<p>Collects errors from host data retrieval.</p>|Dependent item|purestorage.flasharray.hosts.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get volumes|<p>Collects all volumes from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.volumes.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage volumes item errors|<p>Collects errors from volume retrieval.</p>|Dependent item|purestorage.flasharray.volumes.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get certificates|<p>Collects all certificates from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.certificates.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Certificates item errors|<p>Collects errors from certificate retrieval.</p>|Dependent item|purestorage.flasharray.certificates.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get pods|<p>Collects all pods from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.pods.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage pods item errors|<p>Collects errors from pod retrieval.</p>|Dependent item|purestorage.flasharray.pods.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get network interfaces|<p>Collects all network interfaces from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.net_ifs.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage network interfaces item errors|<p>Collects errors from network interface retrieval.</p>|Dependent item|purestorage.flasharray.net_ifs.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get array data|<p>Collects array data from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.array.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage array item errors|<p>Collects errors from array data retrieval.</p>|Dependent item|purestorage.flasharray.array.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Array capacity|<p>Total capacity of the array.</p>|Dependent item|purestorage.flasharray.array.capacity<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.capacity`</p></li></ul>|
|Array data reduction|<p>The data reduction ratio (DRR) represents the efficiency of data reduction techniques such as compression and deduplication for the array volume.</p>|Dependent item|purestorage.flasharray.array.drr<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.data_reduction`</p></li></ul>|
|Array total data reduction|<p>The total reduction ratio of all data on the array volume that has been processed by the data deduplication and compression engines.</p>|Dependent item|purestorage.flasharray.array.total_drr<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.total_reduction`</p></li></ul>|
|Array hostname|<p>Host name of the array.</p>|Dependent item|purestorage.flasharray.array.hostname<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.hostname`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Array bytes written per second|<p>Number of bytes written per second.</p>|Dependent item|purestorage.flasharray.array.written_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.monitor.input_per_sec`</p></li></ul>|
|Array bytes read per second|<p>Number of bytes read per second.</p>|Dependent item|purestorage.flasharray.array.read_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.monitor.output_per_sec`</p></li></ul>|
|Array read requests per second|<p>Number of read requests processed per second.</p>|Dependent item|purestorage.flasharray.array.read_requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.monitor.reads_per_sec`</p></li></ul>|
|Array write requests per second|<p>Number of write requests processed per second.</p>|Dependent item|purestorage.flasharray.array.write_requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.monitor.writes_per_sec`</p></li></ul>|
|Array microseconds per read|<p>Average time in microseconds required to process an I/O read request from the array. The average time does not include SAN time, queue time, or QoS rate limit time.</p>|Dependent item|purestorage.flasharray.array.usec_per_read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.monitor.usec_per_read_op`</p></li></ul>|
|Array microseconds per write|<p>Average time in microseconds required to process an I/O write request to the array. The average time does not include SAN time, queue time, or QoS rate limit time.</p>|Dependent item|purestorage.flasharray.array.usec_per_write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.monitor.usec_per_write_op`</p></li></ul>|
|Array microseconds per operation|<p>Average local queue time in microseconds for both read and write operations.</p>|Dependent item|purestorage.flasharray.array.usec_per_op<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.monitor.local_queue_usec_per_op`</p></li></ul>|
|Array shared space|<p>The physical space occupied by deduplicated data.</p>|Dependent item|purestorage.flasharray.array.shared_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.shared_space`</p></li></ul>|
|Array used space|<p>The total physical space occupied by system, shared space, volume, and snapshot data.</p>|Dependent item|purestorage.flasharray.array.used_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.total`</p></li></ul>|
|Array volumes size|<p>The physical space occupied by volumes.</p>|Dependent item|purestorage.flasharray.array.volumes_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.volumes`</p></li></ul>|
|Array snapshots size|<p>The physical space occupied by snapshots.</p>|Dependent item|purestorage.flasharray.array.snapshots_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.snapshots`</p></li></ul>|
|Array system size|<p>The physical space occupied by internal array metadata.</p>|Dependent item|purestorage.flasharray.array.system_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.system`</p></li></ul>|
|Array thin provisioning|<p>The percentage of volume sectors that do not contain host-written data because the hosts have not written data to them or the sectors have been explicitly trimmed.</p>|Dependent item|purestorage.flasharray.array.thin_provisioning<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.thin_provisioning`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Array parity|<p>The percentage of data that is protected.</p>|Dependent item|purestorage.flasharray.array.parity<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.parity`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Array version|<p>Version of the array.</p>|Dependent item|purestorage.flasharray.array.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Remote assist status|<p>Status of the remote assist connection.</p>|Dependent item|purestorage.flasharray.remote_assist.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.remote_assist.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Phone home status|<p>Current status of a manually-initiated phonehome.</p>|Dependent item|purestorage.flasharray.phone_home.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.phone_home.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Authentication has failed|<p>An error occurred when trying to perform authentication in the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.auth.errors))>0`|Average||
|Pure Storage FlashArray: There are errors in the 'Get hardware data' metric|<p>An error occurred when trying to get hardware data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.hardware.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get hosts' metric|<p>An error occurred when trying to get host data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.hosts.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get volumes' metric|<p>An error occurred when trying to get volume data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.volumes.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get certificates' metric|<p>An error occurred when trying to get certificate data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.certificates.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get pods' metric|<p>An error occurred when trying to get pod data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.pods.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get network interfaces' metric|<p>An error occurred when trying to get network interface data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.net_ifs.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get storage array data' metric|<p>An error occurred when trying to get array data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.array.errors))>0`|Warning||
|Pure Storage FlashArray: RemoteAssist has been enabled|<p>Purity's administrator-controlled RemoteAssist feature enables a Technical Support Engineer to communicate directly with the FlashArray via a secure link, effectively establishing an additional administrative session for the duration of the diagnosis and service.</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.remote_assist.status)=1`|High||
|Pure Storage FlashArray: Phone Home has been disabled|<p>Phone Home connects to the Pure1 service and uploads logs for continuous health monitoring.</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.phone_home.status)=0`|Warning||

### LLD rule Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod discovery|<p>Discovery of storage pods from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.pod.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pods`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod [{#NAME}]: Get data|<p>Collects data about the {#NAME} pod.</p>|Dependent item|purestorage.flasharray.pod.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pods[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pod [{#NAME}]: Promotion status|<p>The current promotion status of the {#NAME} pod.</p>|Dependent item|purestorage.flasharray.pod.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.promotion_status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Pod [{#NAME}]: Number of arrays|<p>Number of arrays connected to the {#NAME} pod.</p>|Dependent item|purestorage.flasharray.pod.arrays[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.arrays.length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### LLD rule Drive discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Drive discovery|<p>Discovery of storage drives from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.drive.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.drives`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Drive discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Drive [{#NAME}]: Get data|<p>Collects data about the {#NAME} drive.</p>|Dependent item|purestorage.flasharray.drive.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.drives[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Drive [{#NAME}]: Capacity|<p>The capacity of the {#NAME} drive.</p>|Dependent item|purestorage.flasharray.drive.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacity`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Drive [{#NAME}]: Status|<p>The current status of the {#NAME} drive.</p>|Dependent item|purestorage.flasharray.drive.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Drive [{#NAME}]: Serial number|<p>Serial number of the {#NAME} drive device.</p>|Dependent item|purestorage.flasharray.drive.serial[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Drive discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Drive [{#NAME}]: Problem on the drive|<p>Drive {#NAME} status is not "healthy", "updating", or "unused".</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.drive.status[{#NAME}])<>0 and last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.drive.status[{#NAME}])<>2 and last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.drive.status[{#NAME}])<>3`|Average||

### LLD rule Controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller discovery|<p>Discovery of controllers from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.ctrl.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.controllers`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller [{#NAME}]: Get data|<p>Collects data about the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.controllers[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Controller [{#NAME}]: Status|<p>The current status of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Controller [{#NAME}]: Mode|<p>The current mode of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.mode[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mode`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Controller [{#NAME}]: Serial number|<p>Serial number of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.serial[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#NAME}]: Model|<p>Model of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.model[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#NAME}]: Version|<p>Version of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.version[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Controller [{#NAME}]: Controller is not ready|<p>Controller {#NAME} status is not "ready" or "updating".</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.ctrl.status[{#NAME}])<>1 and last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.ctrl.status[{#NAME}])<>2`|Average||
|Pure Storage FlashArray: Controller [{#NAME}]: Mode has been changed|<p>The mode of the {#NAME} controller has changed.</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.ctrl.mode[{#NAME}],#1)<>last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.ctrl.mode[{#NAME}],#2)`|Average||

### LLD rule Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature sensor discovery|<p>Discovery of temperature sensors from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.temp.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#NAME}]: Get data|<p>Collects data about the {#NAME} temperature sensor.</p>|Dependent item|purestorage.flasharray.temp.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Sensor [{#NAME}]: Status|<p>The current status of the {#NAME} temperature sensor.</p>|Dependent item|purestorage.flasharray.temp.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Sensor [{#NAME}]: Temperature|<p>The current temperature value of the {#NAME} sensor.</p>|Dependent item|purestorage.flasharray.temp.value[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temperature`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Temperature sensor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Sensor [{#NAME}]: Sensor is not healthy|<p>Temperature sensor {#NAME} status is not "ok".</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.temp.status[{#NAME}])<>0`|Average||

### LLD rule Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply discovery|<p>Discovery of power supply components from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.power.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply [{#NAME}]: Get data|<p>Collects data about the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Power supply [{#NAME}]: Status|<p>The current status of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Power supply [{#NAME}]: Serial number|<p>Serial number of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.serial[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power supply [{#NAME}]: Model|<p>Model of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.model[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power supply [{#NAME}]: Voltage|<p>The current voltage value of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.value[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.voltage`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Power supply discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Power supply [{#NAME}]: Power supply is not healthy|<p>Power supply component {#NAME} status is not "ok".</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.power.status[{#NAME}])<>0`|Average||

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Discovery of fans from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.fan.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#NAME}]: Get data|<p>Collects data about the {#NAME} fan.</p>|Dependent item|purestorage.flasharray.fan.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Fan [{#NAME}]: Status|<p>The current status of the {#NAME} fan.</p>|Dependent item|purestorage.flasharray.fan.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Fan [{#NAME}]: Fan is not healthy|<p>Fan {#NAME} status is not "ok".</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.fan.status[{#NAME}])<>0`|Average||

### LLD rule Host discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host discovery|<p>Discovery of storage hosts from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.host.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hosts`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Host discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host [{#NAME}]: Get data|<p>Collects data about the {#NAME} host.</p>|Dependent item|purestorage.flasharray.host.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hosts[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Host [{#NAME}]: Size|<p>The physical space occupied on the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Host [{#NAME}]: Data reduction|<p>The data reduction ratio (DRR) represents the efficiency of data reduction techniques such as compression and deduplication for the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.drr[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.data_reduction`</p></li></ul>|
|Host [{#NAME}]: Total data reduction|<p>The total reduction ratio of all data on the {#NAME} host volume that has been processed by the data deduplication and compression engines.</p>|Dependent item|purestorage.flasharray.host.total_drr[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_reduction`</p></li></ul>|
|Host [{#NAME}]: Bytes written per second|<p>Number of bytes written to the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.written_bytes.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.input_per_sec`</p></li></ul>|
|Host [{#NAME}]: Bytes read per second|<p>Number of bytes read from the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.read_bytes.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.output_per_sec`</p></li></ul>|
|Host [{#NAME}]: Read requests per second|<p>Number of read requests processed on the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.read_requests.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.reads_per_sec`</p></li></ul>|
|Host [{#NAME}]: Write requests per second|<p>Number of write requests processed on the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.write_requests.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.writes_per_sec`</p></li></ul>|
|Host [{#NAME}]: Microseconds per read|<p>Average time in microseconds required to process an I/O read request from the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.usec_per_read[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.usec_per_read_op`</p></li></ul>|
|Host [{#NAME}]: Microseconds per write|<p>Average time in microseconds required to process an I/O write request to the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.usec_per_write[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.usec_per_write_op`</p></li></ul>|
|Host [{#NAME}]: Used space|<p>The total physical space occupied by all data on the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.used_space[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total`</p></li></ul>|
|Host [{#NAME}]: Snapshots size|<p>The physical space occupied by snapshots on the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.snapshots_size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.snapshots`</p></li></ul>|
|Host [{#NAME}]: Thin provisioning|<p>The percentage of sectors in the {#NAME} host volume that do not contain host-written data because the hosts have not written data to them or the sectors have been explicitly trimmed.</p>|Dependent item|purestorage.flasharray.host.thin_provisioning[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.thin_provisioning`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|

### LLD rule Volume discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volume discovery|<p>Discovery of storage volumes from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.volume.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.volumes`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Volume discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volume [{#NAME}]: Get data|<p>Collects data about the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.get[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.volumes[?(@.serial == "{#SN}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Volume [{#NAME}]: Size|<p>The physical space occupied by the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.size[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Volume [{#NAME}]: Data reduction|<p>The data reduction ratio (DRR) represents the efficiency of data reduction techniques such as compression and deduplication for the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.drr[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.data_reduction`</p></li></ul>|
|Volume [{#NAME}]: Total data reduction|<p>The total reduction ratio of all data on the {#NAME} volume that has been processed by the data deduplication and compression engines.</p>|Dependent item|purestorage.flasharray.volume.total_drr[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_reduction`</p></li></ul>|
|Volume [{#NAME}]: Bytes written per second|<p>Number of bytes written to the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.written_bytes.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.input_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Bytes read per second|<p>Number of bytes read from the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.read_bytes.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.output_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Read requests per second|<p>Number of read requests processed on the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.read_requests.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.reads_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Write requests per second|<p>Number of write requests processed on the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.write_requests.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.writes_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Microseconds per read|<p>Average time in microseconds required to process an I/O read request from the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.usec_per_read[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.usec_per_read_op`</p></li></ul>|
|Volume [{#NAME}]: Microseconds per write|<p>Average time in microseconds required to process an I/O write request to the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.usec_per_write[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitor.usec_per_write_op`</p></li></ul>|
|Volume [{#NAME}]: Shared space|<p>The physical space occupied by deduplicated data on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.shared_space[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.shared_space`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Volume [{#NAME}]: Used space|<p>The total physical space occupied by all data on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.used_space[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total`</p></li></ul>|
|Volume [{#NAME}]: Snapshots size|<p>The physical space occupied by snapshots on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.snapshots_size[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.snapshots`</p></li></ul>|
|Volume [{#NAME}]: Thin provisioning|<p>The percentage of sectors in the {#NAME} volume that do not contain host-written data because the hosts have not written data to them or the sectors have been explicitly trimmed.</p>|Dependent item|purestorage.flasharray.volume.thin_provisioning[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.thin_provisioning`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|

### Trigger prototypes for Volume discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Volume [{#NAME}]: Volume size has been changed|<p>Physical space occupied by the {#NAME} volume has been changed.</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.volume.size[{#SN}],#1)<>last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.volume.size[{#SN}],#2)`|Warning||

### LLD rule Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate discovery|<p>Discovery of certificates from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.cert.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.certs`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate [{#NAME}]: Get data|<p>Collects data about the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.certs[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Common name|<p>The common name field listed in the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.cn[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.common_name`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Issued to|<p>Indicates the entity which holds the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.issued_to[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issued_to`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Issued by|<p>Indicates the authority or organization that issued the {#NAME} certificate, typically including information such as the name of the Certificate Authority (CA) and its digital signature.</p>|Dependent item|purestorage.flasharray.cert.issued_by[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issued_by`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Valid from|<p>Indicates the date and time when the {#NAME} certificate takes effect.</p>|Dependent item|purestorage.flasharray.cert.valid_from[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.valid_from`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Certificate [{#NAME}]: Valid to|<p>Indicates the expiration date and time of the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.valid_to[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.valid_to`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Trigger prototypes for Certificate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Certificate [{#NAME}]: SSL certificate expires soon|<p>Consider reissuing and replacing the {#NAME} certificate.</p>|`last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.cert.valid_to[{#NAME}]) > 0 and (last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.cert.valid_to[{#NAME}]) - now()) / 86400 < {$PURE.FLASHARRAY.CERT.EXPIRY.WARN}`|Average||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of storage hosts from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.net_if.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interfaces`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}]: Get data|<p>Collects data about the {#IFNAME} network interface.</p>|Dependent item|purestorage.flasharray.net_if.get[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interfaces[?(@.name == "{#IFNAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}]: Speed|<p>Current bandwidth of the interface.</p>|Dependent item|purestorage.flasharray.net_if.speed[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.speed`</p></li></ul>|
|Interface [{#IFNAME}]: IP address|<p>Represents the IP address of the {#IFNAME} interface.</p>|Dependent item|purestorage.flasharray.net_if.ip[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.address`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}]: MAC address|<p>Represents the MAC address of the {#IFNAME} interface.</p>|Dependent item|purestorage.flasharray.net_if.mac[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hwaddr`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}]: Gateway|<p>Represents the IP address of the gateway for the {#IFNAME} interface.</p>|Dependent item|purestorage.flasharray.net_if.gateway[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.gateway`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Interface [{#IFNAME}]: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.net_if.speed[{#IFNAME}])<0 and last(/Pure Storage FlashArray v1 by HTTP/purestorage.flasharray.net_if.speed[{#IFNAME}])>0`|Info|**Manual close**: Yes|

# Pure Storage FlashArray v2 by HTTP

## Overview

This template is designed for the effortless deployment of Pure Storage FlashArray v2 monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Pure Storage FlashArray FA-X20R4 (Purity//FA: 6.7.1)

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a host for the Pure Storage FlashArray device and assign to it the "Pure Storage FlashArray v2 by HTTP" template.
2. Enter your API token from the FlashArray (Purity//FA) graphical user interface into the `{$PURE.FLASHARRAY.API.TOKEN}` macro.
3. Set your FlashArray (Purity//FA) graphical user interface URL as the `{$PURE.FLASHARRAY.API.URL}` macro value.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PURE.FLASHARRAY.API.URL}|<p>Pure Storage FlashArray Web interface URL.</p>||
|{$PURE.FLASHARRAY.API.TOKEN}|<p>Pure Storage FlashArray API token.</p>||
|{$PURE.FLASHARRAY.API.VERSION}|<p>Pure Storage FlashArray API version.</p>|`2.36`|
|{$PURE.FLASHARRAY.CERT.EXPIRY.WARN}|<p>Number of days until the certificate expires.</p>|`7`|
|{$PURE.FLASHARRAY.DATA.TIMEOUT}|<p>Response timeout for the API.</p>|`15s`|
|{$PURE.FLASHARRAY.POD.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable storage pods by name.</p>|`.*`|
|{$PURE.FLASHARRAY.POD.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable storage pods by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.CERT.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable certificates by name.</p>|`.*`|
|{$PURE.FLASHARRAY.CERT.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable certificates by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.CTRL.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable controllers by index.</p>|`.*`|
|{$PURE.FLASHARRAY.CTRL.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable controllers by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.TEMP.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable temperature sensors by index.</p>|`.*`|
|{$PURE.FLASHARRAY.TEMP.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable temperature sensors by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.POWER.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable power supply components by index.</p>|`.*`|
|{$PURE.FLASHARRAY.POWER.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable power supply components by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.FAN.LLD.FILTER.INDEX.MATCHES}|<p>Filter of discoverable fans by index.</p>|`.*`|
|{$PURE.FLASHARRAY.FAN.LLD.FILTER.INDEX.NOT_MATCHES}|<p>Filter to exclude discoverable fans by index.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.DRIVE.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable storage drives by name.</p>|`.*`|
|{$PURE.FLASHARRAY.DRIVE.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable storage drives by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable hosts by name.</p>|`.*`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable hosts by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.GROUP.MATCHES}|<p>Filter of discoverable hosts by group.</p>|`.*`|
|{$PURE.FLASHARRAY.HOST.LLD.FILTER.GROUP.NOT_MATCHES}|<p>Filter to exclude discoverable hosts by group.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.NETIF.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable network interfaces by name.</p>|`.*`|
|{$PURE.FLASHARRAY.NETIF.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable network interfaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.VOLUME.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable storage volumes by name.</p>|`.*`|
|{$PURE.FLASHARRAY.VOLUME.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable storage volumes by name.</p>|`CHANGE_IF_NEEDED`|
|{$PURE.FLASHARRAY.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. For more details, see the documentation at https://www.zabbix.com/documentation/7.4/manual/config/items/itemtypes/http</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Authentication|<p>Pure Storage FlashArray authentication with API token usage.</p><p>Returns a session token; it is required only once and is used for all dependent script items.</p><p>A session will expire after 30 minutes. Check the template documentation for the details.</p>|Script|purestorage.flasharray.auth|
|Authentication item errors|<p>Collects errors from the authentication item.</p>|Dependent item|purestorage.flasharray.auth.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get hardware data|<p>Collects hardware from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.hardware.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage hardware item errors|<p>Collects errors from hardware retrieval.</p>|Dependent item|purestorage.flasharray.hardware.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get hosts|<p>Collects all hosts from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.hosts.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage hosts item errors|<p>Collects errors from host data retrieval.</p>|Dependent item|purestorage.flasharray.hosts.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get volumes|<p>Collects all volumes from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.volumes.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage volumes item errors|<p>Collects errors from volume retrieval.</p>|Dependent item|purestorage.flasharray.volumes.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get certificates|<p>Collects all certificates from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.certificates.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Certificates item errors|<p>Collects errors from certificate retrieval.</p>|Dependent item|purestorage.flasharray.certificates.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get pods|<p>Collects all pods from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.pods.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage pods item errors|<p>Collects errors from pod retrieval.</p>|Dependent item|purestorage.flasharray.pods.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get network interfaces|<p>Collects all network interfaces from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.net_ifs.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage network interfaces item errors|<p>Collects errors from network interface retrieval.</p>|Dependent item|purestorage.flasharray.net_ifs.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get array data|<p>Collects array data from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.array.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage array item errors|<p>Collects errors from array data retrieval.</p>|Dependent item|purestorage.flasharray.array.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get support information|<p>Collects support information from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.support.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Support information item errors|<p>Collects errors from support information retrieval.</p>|Dependent item|purestorage.flasharray.support.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get alerts data|<p>Collects alert data from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.alert.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Alerts data item errors|<p>Collects errors from alert data retrieval.</p>|Dependent item|purestorage.flasharray.alert.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Critical alerts|<p>Number of active alerts in the web interface of Pure Storage FlashArray with a severity level of Critical.</p>|Dependent item|purestorage.flasharray.alert.critical<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.severity == "critical")].length()`</p></li></ul>|
|Warning alerts|<p>Number of active alerts in the web interface of Pure Storage FlashArray with a severity level of Warning.</p>|Dependent item|purestorage.flasharray.alert.warning<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.severity == "warning")].length()`</p></li></ul>|
|Array capacity|<p>Total capacity of the array.</p>|Dependent item|purestorage.flasharray.array.capacity<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.capacity`</p></li></ul>|
|Array data reduction|<p>The data reduction ratio (DRR) represents the efficiency of data reduction techniques such as compression and deduplication for the array volume.</p>|Dependent item|purestorage.flasharray.array.drr<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.data_reduction`</p></li></ul>|
|Array total data reduction|<p>The total reduction ratio of all data on the array volume that has been processed by the data deduplication and compression engines.</p>|Dependent item|purestorage.flasharray.array.total_drr<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.total_reduction`</p></li></ul>|
|Array hostname|<p>Host name of the array.</p>|Dependent item|purestorage.flasharray.array.hostname<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.name`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Array bytes written per second|<p>Number of bytes written per second.</p>|Dependent item|purestorage.flasharray.array.written_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.performance.write_bytes_per_sec`</p></li></ul>|
|Array bytes read per second|<p>Number of bytes read per second.</p>|Dependent item|purestorage.flasharray.array.read_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.performance.read_bytes_per_sec`</p></li></ul>|
|Array read requests per second|<p>Number of read requests processed per second.</p>|Dependent item|purestorage.flasharray.array.read_requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.performance.reads_per_sec`</p></li></ul>|
|Array write requests per second|<p>Number of write requests processed per second.</p>|Dependent item|purestorage.flasharray.array.write_requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.performance.writes_per_sec`</p></li></ul>|
|Array microseconds per read|<p>Average time in microseconds required to process an I/O read request from the array. The average time does not include SAN time, queue time, or QoS rate limit time.</p>|Dependent item|purestorage.flasharray.array.usec_per_read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.performance.usec_per_read_op`</p></li></ul>|
|Array microseconds per write|<p>Average time in microseconds required to process an I/O write request to the array. The average time does not include SAN time, queue time, or QoS rate limit time.</p>|Dependent item|purestorage.flasharray.array.usec_per_write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.performance.usec_per_write_op`</p></li></ul>|
|Array microseconds per operation|<p>Average local queue time in microseconds for both read and write operations.</p>|Dependent item|purestorage.flasharray.array.usec_per_op<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.performance.local_queue_usec_per_op`</p></li></ul>|
|Array shared space|<p>The physical space occupied by deduplicated data.</p>|Dependent item|purestorage.flasharray.array.shared_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.shared`</p></li></ul>|
|Array used space|<p>The total space occupied by system, shared space, volume, and snapshot data.</p>|Dependent item|purestorage.flasharray.array.used_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.total_used`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Array physical size|<p>The total physical space occupied by system, shared space, volume, and snapshot data.</p>|Dependent item|purestorage.flasharray.array.physical_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.total_physical`</p></li></ul>|
|Array snapshots size|<p>The physical space occupied by snapshots.</p>|Dependent item|purestorage.flasharray.array.snapshots_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.snapshots`</p></li></ul>|
|Array system size|<p>The physical space occupied by internal array metadata.</p>|Dependent item|purestorage.flasharray.array.system_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.system`</p></li></ul>|
|Array thin provisioning|<p>The percentage of volume sectors that do not contain host-written data because the hosts have not written data to them or the sectors have been explicitly trimmed.</p>|Dependent item|purestorage.flasharray.array.thin_provisioning<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.space.thin_provisioning`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Array parity|<p>The percentage of data that is protected.</p>|Dependent item|purestorage.flasharray.array.parity<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.parity`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Array OS|<p>Operating system of the array.</p>|Dependent item|purestorage.flasharray.array.os<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.os`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Array version|<p>Version of the array.</p>|Dependent item|purestorage.flasharray.array.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array.version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Remote assist status|<p>Status of the remote assist connection.</p>|Dependent item|purestorage.flasharray.remote_assist.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.support.remote_assist_active`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Phone home status|<p>Current status of a manually-initiated phone home.</p>|Dependent item|purestorage.flasharray.phone_home.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.support.phonehome_enabled`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Authentication has failed|<p>An error occurred when trying to perform authentication in the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.auth.errors))>0`|Average||
|Pure Storage FlashArray: There are errors in the 'Get hardware data' metric|<p>An error occurred when trying to get hardware data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.hardware.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get hosts' metric|<p>An error occurred when trying to get host data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.hosts.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get volumes' metric|<p>An error occurred when trying to get volume data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.volumes.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get certificates' metric|<p>An error occurred when trying to get certificate data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.certificates.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get pods' metric|<p>An error occurred when trying to get pod data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.pods.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get network interfaces' metric|<p>An error occurred when trying to get network interface data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.net_ifs.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get storage array data' metric|<p>An error occurred when trying to get array data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.array.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get support information' metric|<p>An error occurred when trying to get support information from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.support.errors))>0`|Warning||
|Pure Storage FlashArray: There are errors in the 'Get alerts data' metric|<p>An error occurred when trying to get alert data from the Pure Storage FlashArray API.</p>|`length(last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.alert.errors))>0`|Warning||
|Pure Storage FlashArray: Critical alerts have been detected|<p>Recommended to refer to the Pure Storage FlashArray web interface to check the alert details.</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.alert.critical)>0`|High||
|Pure Storage FlashArray: Warning alerts have been detected|<p>Recommended to refer to the Pure Storage FlashArray web interface to check the alert details.</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.alert.warning)>0`|Warning||
|Pure Storage FlashArray: RemoteAssist has been enabled|<p>Purity's administrator-controlled RemoteAssist feature enables a Technical Support Engineer to communicate directly with the FlashArray via a secure link, effectively establishing an additional administrative session for the duration of the diagnosis and service.</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.remote_assist.status)=1`|High||
|Pure Storage FlashArray: Phone Home has been disabled|<p>Phone Home connects to the Pure1 service and uploads logs for continuous health monitoring.</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.phone_home.status)=0`|Warning||

### LLD rule Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod discovery|<p>Discovery of storage pods from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.pod.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pods`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod [{#NAME}]: Get data|<p>Collects data about the {#NAME} pod.</p>|Dependent item|purestorage.flasharray.pod.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pods[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pod [{#NAME}]: Quota|<p>The quota limit of the {#NAME} pod.</p>|Dependent item|purestorage.flasharray.pod.quota[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.quota_limit`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pod [{#NAME}]: Status|<p>The current promotion status of the {#NAME} pod.</p>|Dependent item|purestorage.flasharray.pod.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.promotion_status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Pod [{#NAME}]: Number of arrays|<p>Number of arrays connected to the {#NAME} pod.</p>|Dependent item|purestorage.flasharray.pod.arrays[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.array_count`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### LLD rule Drive discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Drive discovery|<p>Discovery of storage drives from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.drive.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.drives`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Drive discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Drive [{#NAME}]: Get data|<p>Collects data about the {#NAME} drive.</p>|Dependent item|purestorage.flasharray.drive.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.drives[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Drive [{#NAME}]: Capacity|<p>The capacity of the {#NAME} drive.</p>|Dependent item|purestorage.flasharray.drive.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacity`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Drive [{#NAME}]: Status|<p>The current status of the {#NAME} drive.</p>|Dependent item|purestorage.flasharray.drive.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Drive [{#NAME}]: Serial number|<p>Serial number of the {#NAME} drive device.</p>|Dependent item|purestorage.flasharray.drive.serial[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Drive discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Drive [{#NAME}]: Problem on the drive|<p>Drive {#NAME} status is not "healthy", "updating", or "unused".</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.drive.status[{#NAME}])<>0 and last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.drive.status[{#NAME}])<>2 and last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.drive.status[{#NAME}])<>3`|Average||

### LLD rule Controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller discovery|<p>Discovery of controllers from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.ctrl.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.controllers`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller [{#NAME}]: Get data|<p>Collects data about the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.controllers[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Controller [{#NAME}]: Status|<p>The current status of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Controller [{#NAME}]: Mode|<p>The current mode of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.mode[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mode`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Controller [{#NAME}]: Serial number|<p>Serial number of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.serial[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#NAME}]: Model|<p>Model of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.model[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#NAME}]: Version|<p>Version of the {#NAME} controller.</p>|Dependent item|purestorage.flasharray.ctrl.version[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Controller [{#NAME}]: Controller is not ready|<p>Controller {#NAME} status is not "ready" or "updating".</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.ctrl.status[{#NAME}])<>1 and last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.ctrl.status[{#NAME}])<>2`|Average||
|Pure Storage FlashArray: Controller [{#NAME}]: Mode has been changed|<p>The mode of the {#NAME} controller has changed.</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.ctrl.mode[{#NAME}],#1)<>last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.ctrl.mode[{#NAME}],#2)`|Average||

### LLD rule Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature sensor discovery|<p>Discovery of temperature sensors from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.temp.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#NAME}]: Get data|<p>Collects data about the {#NAME} temperature sensor.</p>|Dependent item|purestorage.flasharray.temp.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Sensor [{#NAME}]: Status|<p>The current status of the {#NAME} temperature sensor.</p>|Dependent item|purestorage.flasharray.temp.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Sensor [{#NAME}]: Temperature|<p>The current temperature value of the {#NAME} sensor.</p>|Dependent item|purestorage.flasharray.temp.value[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temperature`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Temperature sensor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Sensor [{#NAME}]: Sensor is not healthy|<p>Temperature sensor {#NAME} status is not "ok".</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.temp.status[{#NAME}])<>0`|Average||

### LLD rule Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply discovery|<p>Discovery of power supply components from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.power.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply [{#NAME}]: Get data|<p>Collects data about the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Power supply [{#NAME}]: Status|<p>The current status of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Power supply [{#NAME}]: Serial number|<p>Serial number of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.serial[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power supply [{#NAME}]: Model|<p>Model of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.model[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power supply [{#NAME}]: Voltage|<p>The current voltage value of the {#NAME} power supply component.</p>|Dependent item|purestorage.flasharray.power.value[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.voltage`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Power supply discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Power supply [{#NAME}]: Power supply is not healthy|<p>Power supply component {#NAME} status is not "ok".</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.power.status[{#NAME}])<>0`|Average||

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Discovery of fans from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.fan.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#NAME}]: Get data|<p>Collects data about the {#NAME} fan.</p>|Dependent item|purestorage.flasharray.fan.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardware[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Fan [{#NAME}]: Status|<p>The current status of the {#NAME} fan.</p>|Dependent item|purestorage.flasharray.fan.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Fan [{#NAME}]: Fan is not healthy|<p>Fan {#NAME} status is not "ok".</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.fan.status[{#NAME}])<>0`|Average||

### LLD rule Host discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host discovery|<p>Discovery of storage hosts from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.host.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hosts`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Host discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host [{#NAME}]: Get data|<p>Collects data about the {#NAME} host.</p>|Dependent item|purestorage.flasharray.host.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hosts[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Host [{#NAME}]: Total space provisioned|<p>The total provisioned space on the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_provisioned`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Host [{#NAME}]: Used space provisioned|<p>The provisioned space occupied on the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.used_provisioned[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.used_provisioned`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Host [{#NAME}]: Data reduction|<p>The data reduction ratio (DRR) represents the efficiency of data reduction techniques such as compression and deduplication for the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.drr[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.data_reduction`</p></li></ul>|
|Host [{#NAME}]: Total data reduction|<p>The total reduction ratio of all data on the {#NAME} host volume that has been processed by the data deduplication and compression engines.</p>|Dependent item|purestorage.flasharray.host.total_drr[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_reduction`</p></li></ul>|
|Host [{#NAME}]: Bytes written per second|<p>Number of bytes written to the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.written_bytes.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.write_bytes_per_sec`</p></li></ul>|
|Host [{#NAME}]: Bytes read per second|<p>Number of bytes read from the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.read_bytes.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.read_bytes_per_sec`</p></li></ul>|
|Host [{#NAME}]: Read requests per second|<p>Number of read requests processed on the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.read_requests.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.reads_per_sec`</p></li></ul>|
|Host [{#NAME}]: Write requests per second|<p>Number of write requests processed on the {#NAME} host volume per second.</p>|Dependent item|purestorage.flasharray.host.write_requests.rate[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.writes_per_sec`</p></li></ul>|
|Host [{#NAME}]: Microseconds per read|<p>Average time in microseconds required to process an I/O read request from the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.usec_per_read[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.usec_per_read_op`</p></li></ul>|
|Host [{#NAME}]: Microseconds per write|<p>Average time in microseconds required to process an I/O write request to the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.usec_per_write[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.usec_per_write_op`</p></li></ul>|
|Host [{#NAME}]: Used space|<p>The total physical space occupied by all data on the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.used_space[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_used`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Host [{#NAME}]: Snapshots size|<p>The physical space occupied by snapshots on the {#NAME} host volume.</p>|Dependent item|purestorage.flasharray.host.snapshots_size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.snapshots`</p></li></ul>|
|Host [{#NAME}]: Thin provisioning|<p>The percentage of sectors in the {#NAME} host volume that do not contain host-written data because the hosts have not written data to them or the sectors have been explicitly trimmed.</p>|Dependent item|purestorage.flasharray.host.thin_provisioning[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.thin_provisioning`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|

### LLD rule Volume discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volume discovery|<p>Discovery of storage volumes from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.volume.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.volumes`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Volume discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volume [{#NAME}]: Get data|<p>Collects data about the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.get[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.volumes[?(@.serial == "{#SN}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Volume [{#NAME}]: Size|<p>The physical space occupied by the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.size[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.provisioned`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Volume [{#NAME}]: Data reduction|<p>The data reduction ratio (DRR) represents the efficiency of data reduction techniques such as compression and deduplication for the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.drr[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.data_reduction`</p></li></ul>|
|Volume [{#NAME}]: Total data reduction|<p>The total reduction ratio of all data on the {#NAME} volume that has been processed by the data deduplication and compression engines.</p>|Dependent item|purestorage.flasharray.volume.total_drr[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_reduction`</p></li></ul>|
|Volume [{#NAME}]: Bytes written per second|<p>Number of bytes written to the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.written_bytes.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.write_bytes_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Bytes read per second|<p>Number of bytes read from the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.read_bytes.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.read_bytes_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Read requests per second|<p>Number of read requests processed on the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.read_requests.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.reads_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Write requests per second|<p>Number of write requests processed on the {#NAME} volume per second.</p>|Dependent item|purestorage.flasharray.volume.write_requests.rate[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.writes_per_sec`</p></li></ul>|
|Volume [{#NAME}]: Microseconds per read|<p>Average time in microseconds required to process an I/O read request from the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.usec_per_read[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.usec_per_read_op`</p></li></ul>|
|Volume [{#NAME}]: Microseconds per write|<p>Average time in microseconds required to process an I/O write request to the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.usec_per_write[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance.usec_per_write_op`</p></li></ul>|
|Volume [{#NAME}]: Shared space|<p>The physical space occupied by deduplicated data on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.shared_space[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.shared`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Volume [{#NAME}]: Provisioned space|<p>The total provisioned space on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.provisioned[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_provisioned`</p></li></ul>|
|Volume [{#NAME}]: Used provisioned|<p>The total provisioned space occupied by all data on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.used_provisioned[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.used_provisioned`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Volume [{#NAME}]: Used space|<p>The total physical space occupied by all data on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.used_space[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.total_used`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Volume [{#NAME}]: Snapshots size|<p>The physical space occupied by snapshots on the {#NAME} volume.</p>|Dependent item|purestorage.flasharray.volume.snapshots_size[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.snapshots`</p></li></ul>|
|Volume [{#NAME}]: Thin provisioning|<p>The percentage of sectors in the {#NAME} volume that do not contain host-written data because the hosts have not written data to them or the sectors have been explicitly trimmed.</p>|Dependent item|purestorage.flasharray.volume.thin_provisioning[{#SN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space.thin_provisioning`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|

### Trigger prototypes for Volume discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Volume [{#NAME}]: Volume size has been changed|<p>Physical space occupied by the {#NAME} volume has been changed.</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.volume.size[{#SN}],#1)<>last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.volume.size[{#SN}],#2)`|Warning||

### LLD rule Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate discovery|<p>Discovery of certificates from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.cert.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.certs`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate [{#NAME}]: Get data|<p>Collects data about the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.certs[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Common name|<p>The common name field listed in the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.cn[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.common_name`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Issued to|<p>Indicates the entity which holds the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.issued_to[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issued_to`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Issued by|<p>Indicates the authority or organization that issued the {#NAME} certificate, typically including information such as the name of the Certificate Authority (CA) and its digital signature.</p>|Dependent item|purestorage.flasharray.cert.issued_by[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issued_by`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Certificate [{#NAME}]: Valid from|<p>Indicates the date and time when the {#NAME} certificate takes effect.</p>|Dependent item|purestorage.flasharray.cert.valid_from[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.valid_from`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Certificate [{#NAME}]: Valid to|<p>Indicates the expiration date and time of the {#NAME} certificate.</p>|Dependent item|purestorage.flasharray.cert.valid_to[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.valid_to`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Trigger prototypes for Certificate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Certificate [{#NAME}]: SSL certificate expires soon|<p>Consider reissuing and replacing the {#NAME} certificate.</p>|`last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.cert.valid_to[{#NAME}]) > 0 and (last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.cert.valid_to[{#NAME}]) - now()) / 86400 < {$PURE.FLASHARRAY.CERT.EXPIRY.WARN}`|Average||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of storage hosts from the Pure Storage FlashArray API.</p>|Dependent item|purestorage.flasharray.net_if.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interfaces`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}]: Get data|<p>Collects data about the {#IFNAME} network interface.</p>|Dependent item|purestorage.flasharray.net_if.get[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interfaces[?(@.name == "{#IFNAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}]: Speed|<p>Current bandwidth of the interface.</p>|Dependent item|purestorage.flasharray.net_if.speed[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.speed`</p></li></ul>|
|Interface [{#IFNAME}]: IP address|<p>Represents the IP address of the {#IFNAME} interface.</p>|Dependent item|purestorage.flasharray.net_if.ip[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.eth.address`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}]: MAC address|<p>Represents the MAC address of the {#IFNAME} interface.</p>|Dependent item|purestorage.flasharray.net_if.mac[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.eth.mac_address`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}]: Gateway|<p>Represents the IP address of the gateway for the {#IFNAME} interface.</p>|Dependent item|purestorage.flasharray.net_if.gateway[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.eth.gateway`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Pure Storage FlashArray: Interface [{#IFNAME}]: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.net_if.speed[{#IFNAME}])<0 and last(/Pure Storage FlashArray v2 by HTTP/purestorage.flasharray.net_if.speed[{#IFNAME}])>0`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

