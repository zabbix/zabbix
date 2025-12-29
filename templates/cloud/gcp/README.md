
# GCP by HTTP

## Overview

This template is designed to monitor Google Cloud Platform (hereinafter - GCP) by Zabbix.
It works without any external scripts and uses the script item.
The template currently supports the discovery of [Compute Engine](https://cloud.google.com/compute)/[Cloud SQL](https://cloud.google.com/sql) instances and Compute Engine project quota metrics.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Google Cloud Platform

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Enable the `Stackdriver Monitoring API` for the GCP project you wish to monitor.
>Refer to the [vendor documentation](https://cloud.google.com/monitoring/api/enable-api).
2. Create a service account in Google Cloud console for the project you have to monitor.
>Refer to the [vendor documentation](https://cloud.google.com/iam/docs/creating-managing-service-accounts).
3. Create and download the service account key in JSON format.
>Refer to the [vendor documentation](https://cloud.google.com/iam/docs/creating-managing-service-account-keys).
4. If you want to monitor Cloud SQL services - don't forget to activate the Cloud SQL Admin API.
>Refer to the [vendor documentation](https://cloud.google.com/sql/docs/mysql/admin-api) for the details.
5. Copy the `project_id`, `private_key_id`, `private_key`, `client_email` from the JSON key file and add them to their corresponding macros `{$GCP.PROJECT.ID}`, `{$GCP.PRIVATE.KEY.ID}`, `{$GCP.PRIVATE.KEY}`, `{$GCP.CLIENT.EMAIL}` on the template/host.

**Additional information**:

    Make sure that you're creating the service account using the credentials with the `Project Owner/Project IAM Admin/service account Admin` role.

    The service account JSON key file can only be downloaded once: regenerate it if the previous key has been lost.

    The service account should have `Project Viewer` permissions or granular permissions for the GCP Compute Engine API/GCP Cloud SQL.

    You can copy and paste private_key string data from the Service Account JSON key file as is or replace the new line metasymbol (\n) with an actual new line.

Please, refer to the [vendor documentation](https://cloud.google.com/iam/docs/manage-access-service-accounts) about the service accounts management.

**IMPORTANT!!!**

     Secret authorization token is defined as a plain text in host prototype settings by default due to Zabbix templates export/import limits: therefore, it is highly recommended to change the user macro `{$GCP.AUTH.TOKEN}` value type to `SECRET` for all host prototypes after the template `GCP by HTTP` import.

     All the instances/quotas/metrics discovered are related to a particular GCP project.
     To monitor several GCP projects - create their corresponding service accounts/Zabbix hosts.

     GCP Access Token is available for 1 hour (3600 seconds) after the generation request.

     To avoid a GCP token inconsistency between Zabbix database and Zabbix server configuration cache, don't set Zabbix server configuration parameter CacheUpdateFrequency to a value over 45 minutes and don't set the update interval for the GCP Authorization item to more than 1 hour (maximum CacheUpdateFrequency value).

Additional information about metrics and used API methods:

  [Compute Engine](https://cloud.google.com/monitoring/api/metrics_gcp#gcp-compute)

  [Cloud SQL](https://cloud.google.com/monitoring/api/metrics_gcp#gcp-cloudsql)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.PROJECT.ID}|<p>GCP project ID.</p>||
|{$GCP.CLIENT.EMAIL}|<p>Service account client e-mail.</p>||
|{$GCP.PRIVATE.KEY.ID}|<p>Service account private key id.</p>||
|{$GCP.PRIVATE.KEY}|<p>Service account private key data.</p>||
|{$GCP.AUTH.FREQUENCY}|<p>The update interval for the GCP Authorization item, which also equals to the access token regeneration request frequency.</p><p>Check the template documentation notes carefully for more details.</p>|`45m`|
|{$GCP.GCE.QUOTA.PUSED.MIN.WARN}|<p>GCP Compute Engine project quota warning utilization threshold.</p>|`80`|
|{$GCP.GCE.QUOTA.PUSED.MIN.CRIT}|<p>GCP Compute Engine project quota critical quota utilization threshold.</p>|`95`|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$GCP.GCE.INST.NAME.MATCHES}|<p>The filter to include GCP Compute Engine instances by namespace.</p>|`.*`|
|{$GCP.GCE.INST.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Compute Engine instances by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.GCE.ZONE.MATCHES}|<p>The filter to include GCP Compute Engine instances by zone.</p>|`.*`|
|{$GCP.GCE.ZONE.NOT_MATCHES}|<p>The filter to exclude GCP Compute Engine instances by zone.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.MYSQL.INST.NAME.MATCHES}|<p>The filter to include GCP Cloud SQL MySQL instances by namespace.</p>|`.*`|
|{$GCP.MYSQL.INST.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MySQL instances by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.MYSQL.ZONE.MATCHES}|<p>The filter to include GCP Cloud SQL MySQL instances by zone.</p>|`.*`|
|{$GCP.MYSQL.ZONE.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MySQL instances by zone.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.MYSQL.INST.TYPE.MATCHES}|<p>The filter to include GCP Cloud SQL MySQL instances by type (standalone/replica).</p>|`.*`|
|{$GCP.MYSQL.INST.TYPE.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MySQL instances by type (standalone/replica).</p><p>Set a macro value 'CLOUD_SQL_INSTANCE' to exclude standalone Instances or 'READ_REPLICA_INSTANCE' to exclude read-only Replicas.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.PGSQL.INST.NAME.MATCHES}|<p>The filter to include GCP Cloud SQL PostgreSQL instances by namespace.</p>|`.*`|
|{$GCP.PGSQL.INST.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL PostgreSQL instances by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.PGSQL.ZONE.MATCHES}|<p>The filter to include GCP Cloud SQL PostgreSQL instances by zone.</p>|`.*`|
|{$GCP.PGSQL.ZONE.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL PostgreSQL instances by zone.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.PGSQL.INST.TYPE.MATCHES}|<p>The filter to include GCP Cloud SQL PostgreSQL instances by type (standalone/replica).</p>|`.*`|
|{$GCP.PGSQL.INST.TYPE.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL PostgreSQL instances by type (standalone/replica).</p><p>Set a macro value 'CLOUD_SQL_INSTANCE' to exclude standalone Instances or 'READ_REPLICA_INSTANCE' to exclude read-only Replicas.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.MSSQL.INST.NAME.MATCHES}|<p>The filter to include GCP Cloud SQL MSSQL instances by namespace.</p>|`.*`|
|{$GCP.MSSQL.INST.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MSSQL instances by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.MSSQL.ZONE.MATCHES}|<p>The filter to include GCP Cloud SQL MSSQL instances by zone.</p>|`.*`|
|{$GCP.MSSQL.ZONE.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MSSQL instances by zone.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.MSSQL.INST.TYPE.MATCHES}|<p>The filter to include GCP Cloud SQL MSSQL instances by type (standalone/replica).</p>|`.*`|
|{$GCP.MSSQL.INST.TYPE.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MSSQL instances by type (standalone/replica).</p><p>Set a macro value 'CLOUD_SQL_INSTANCE' to exclude standalone Instances or 'READ_REPLICA_INSTANCE' to exclude read-only Replicas.</p>|`CHANGE_IF_NEEDED`|
|{$GCP.GCE.QUOTA.MATCHES}|<p>The filter to include GCP Compute Engine project quotas by namespace.</p>|`.*`|
|{$GCP.GCE.QUOTA.NOT_MATCHES}|<p>The filter to exclude GCP Compute Engine project quotas by namespace.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Authorization|<p>Google Cloud Platform REST authorization with service account authentication parameters and temporary-generated RSA-based JWT-token usage.</p><p>The necessary scopes are pre-defined.</p><p>Returns a signed authorization token with 1 hour lifetime; it is required only once, and is used for all the dependent script items.</p><p>Check the template documentation for the details.</p>|Script|gcp.authorization|
|Instances get|<p>Get GCP Compute Engine instances.</p>|Dependent item|gcp.gce.instances.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Authorization errors check|<p>A list of errors from API requests.</p>|Dependent item|gcp.auth.err.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|
|Instances get|<p>GCP Cloud SQL: Instances get.</p>|Dependent item|gcp.cloudsql.instances.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Cloud SQL instances total|<p>GCP Cloud SQL instances total count.</p>|Dependent item|gcp.cloudsql.instances.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[*].length()`</p></li></ul>|
|MSSQL instances count|<p>GCP Cloud SQL MSSQL instances count.</p>|Dependent item|gcp.cloudsql.instances.mssql_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.db_type =~ 'SQLSERVER')].length()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MySQL instances count|<p>GCP Cloud SQL MySQL instances count.</p>|Dependent item|gcp.cloudsql.instances.mysql_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.db_type =~ 'MYSQL')].length()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PostgreSQL instances count|<p>GCP Cloud SQL PostgreSQL instances count.</p>|Dependent item|gcp.cloudsql.instances.pgsql_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.db_type =~ 'POSTGRES')].length()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GCE instances total|<p>GCP Compute Engine instances total count.</p>|Dependent item|gcp.gce.instances.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[*].length()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Regular GCE instances count|<p>GCP Compute Engine: Regular instances count.</p>|Dependent item|gcp.gce.instances.regular_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.i_type == 'regular')].length()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Container-optimized GCE instances count|<p>GCP Compute Engine: count of instances with Container-Optimized OS used.</p>|Dependent item|gcp.gce.instances.cos_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.i_type == 'container-optimized')].length()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Project quotas get|<p>GCP Compute Engine resource quotas available for the particular project.</p>|Dependent item|gcp.gce.quotas.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GCP: Authorization has failed|<p>GCP: Authorization has failed.<br>Check the authorization parameters and GCP API availability from a network segment, where Zabbix-server/proxy is located.</p>|`length(last(/GCP by HTTP/gcp.auth.err.check)) > 0`|Average||

### LLD rule GCP Compute Engine: Instances discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GCP Compute Engine: Instances discovery|<p>GCP Compute Engine: Instances discovery.</p>|Dependent item|gcp.gce.inst.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule GCP Cloud SQL: PostgreSQL instances discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GCP Cloud SQL: PostgreSQL instances discovery|<p>GCP Cloud SQL: PostgreSQL instances discovery.</p>|Dependent item|gcp.cloudsql.pgsql.inst.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule GCP Cloud SQL: MSSQL instances discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GCP Cloud SQL: MSSQL instances discovery|<p>GCP Cloud SQL: MSSQL instances discovery.</p>|Dependent item|gcp.cloudsql.mssql.inst.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule GCP Cloud SQL: MySQL instances discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GCP Cloud SQL: MySQL instances discovery|<p>GCP Cloud SQL: MySQL instances discovery.</p>|Dependent item|gcp.cloudsql.mysql.inst.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule GCP Compute Engine: Project quotas discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GCP Compute Engine: Project quotas discovery|<p>GCP Compute Engine: Quotas discovery.</p>|Dependent item|gcp.gce.quotas.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for GCP Compute Engine: Project quotas discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Quota [{#GCE.QUOTA.NAME}]: Raw data|<p>GCP Compute Engine: Get metrics for [{#GCE.QUOTA.NAME}] quota.</p>|Dependent item|gcp.gce.quota.single.raw[{#GCE.QUOTA.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "{#GCE.QUOTA.NAME}")].first()`</p></li></ul>|
|Quota [{#GCE.QUOTA.NAME}]: Usage|<p>GCP Compute Engine: The current usage value for [{#GCE.QUOTA.NAME}] quota.</p>|Dependent item|gcp.gce.quota.usage[{#GCE.QUOTA.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage`</p></li></ul>|
|Quota [{#GCE.QUOTA.NAME}]: Limit|<p>GCP Compute Engine: The current limit value for [{#GCE.QUOTA.NAME}] quota.</p>|Dependent item|gcp.gce.quota.limit[{#GCE.QUOTA.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.limit`</p></li></ul>|
|Quota [{#GCE.QUOTA.NAME}]: Percentage used|<p>GCP Compute Engine: Percentage usage for [{#GCE.QUOTA.NAME}] quota.</p>|Dependent item|gcp.gce.quota.pused[{#GCE.QUOTA.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.p_used`</p></li></ul>|

### Trigger prototypes for GCP Compute Engine: Project quotas discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GCP: Quota [{#GCE.QUOTA.NAME}] limit has been changed|<p>GCP Compute Engine: The limit for the `{#GCE.QUOTA.NAME}` quota has been changed.</p>|`change(/GCP by HTTP/gcp.gce.quota.limit[{#GCE.QUOTA.NAME}]) <> 0`|Info|**Manual close**: Yes|
|GCP: Quota [{#GCE.QUOTA.NAME}] usage is close to reaching the limit|<p>GCP Compute Engine: The usage percentage for the `{#GCE.QUOTA.NAME}` quota is close to reaching the limit.</p>|`last(/GCP by HTTP/gcp.gce.quota.pused[{#GCE.QUOTA.NAME}]) >= {$GCP.GCE.QUOTA.PUSED.MIN.WARN:"{#GCE.QUOTA.NAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>GCP: Quota [{#GCE.QUOTA.NAME}] usage is critically close to reaching the limit</li></ul>|
|GCP: Quota [{#GCE.QUOTA.NAME}] usage is critically close to reaching the limit|<p>GCP Compute Engine: The usage percentage for the `{#GCE.QUOTA.NAME}` quota is critically close to reaching the limit.</p>|`last(/GCP by HTTP/gcp.gce.quota.pused[{#GCE.QUOTA.NAME}]) >= {$GCP.GCE.QUOTA.PUSED.MIN.CRIT:"{#GCE.QUOTA.NAME}"}`|Average|**Manual close**: Yes|

# GCP Compute Engine Instance by HTTP

## Overview

This template is designed to monitor Google Cloud Platform Compute Engine instances by Zabbix.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GCP Compute Engine

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template will be automatically connected to discovered entities with all their required parameters pre-defined.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.TIME.WINDOW}|<p>Time interval for the data requests.</p><p>Supported usage type:</p><p>1. The default update interval for most of the items.</p><p>2. The minimal time window for the data requested in the Monitoring Query Language REST API request.</p>|`5m`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$GCE.DISK.NAME.MATCHES}|<p>The filter to include GCP Compute Engine disks by namespace.</p>|`.*`|
|{$GCE.DISK.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Compute Engine disks by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$GCE.DISK.DEV_TYPE.MATCHES}|<p>The filter to include GCP Compute Engine disks by device type.</p>|`.*`|
|{$GCE.DISK.DEV_TYPE.NOT_MATCHES}|<p>The filter to exclude GCP Compute Engine disks by device type.</p>|`CHANGE_IF_NEEDED`|
|{$GCE.DISK.STOR_TYPE.MATCHES}|<p>The filter to include GCP Compute Engine disks by storage type.</p>|`.*`|
|{$GCE.DISK.STOR_TYPE.NOT_MATCHES}|<p>The filter to exclude GCP Compute Engine disks by storage type.</p>|`CHANGE_IF_NEEDED`|
|{$GCE.CPU.UTIL.MAX}|<p>GCP Compute Engine instance CPU utilization threshold.</p>|`95`|
|{$GCE.RAM.UTIL.MAX}|<p>GCP Compute Engine instance RAM utilization threshold.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Metrics get|<p>GCP Compute Engine metrics get in raw format.</p>|Script|gcp.gce.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Firewall: Dropped packets|<p>Count of incoming packets dropped by the firewall.</p>|Dependent item|gcp.gce.firewall.dropped_packets_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dropped_packets_count`</p></li></ul>|
|Firewall: Dropped bytes|<p>Count of incoming bytes dropped by the firewall.</p>|Dependent item|gcp.gce.firewall.dropped_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dropped_bytes_count`</p></li></ul>|
|Guest visible vCPUs|<p>Number of vCPUs visible inside the guest.</p><p>For many GCE machine types, the number of vCPUs visible inside the guest is equal to the `compute.googleapis.com/instance/cpu/reserved_cores` metric.</p><p>For shared-core machine types, the number of guest-visible vCPUs differs from the number of reserved cores.</p><p>For example, e2-small instances have two vCPUs visible inside the guest and 0.5 fractional vCPUs reserved.</p><p>Therefore, for an e2-small instance, `compute.googleapis.com/instance/cpu/guest_visible_vcpus` has a value of 2 and `compute.googleapis.com/instance/cpu/reserved_cores` has a value of 0.5.</p>|Dependent item|gcp.gce.cpu.guest_visible_vcpus<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.guest_visible_vcpus`</p></li></ul>|
|Reserved vCPUs|<p>Number of vCPUs reserved on the host of the instance.</p>|Dependent item|gcp.gce.cpu.reserved_cores<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reserved_cores`</p></li></ul>|
|Scheduler wait time|<p>Wait time is the time a vCPU is ready to run, but unexpectedly not scheduled to run.</p><p>The wait time returned here is the accumulated value for all vCPUs.</p><p>The time interval for which the value was measured is returned by Monitoring in whole seconds as start_time and end_time.</p><p>This metric is only available for VMs that belong to the e2 family or to overcommitted VMs on sole-tenant nodes.</p>|Dependent item|gcp.gce.cpu.scheduler_wait_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.scheduler_wait_time`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU usage time|<p>Delta vCPU usage for all vCPUs, in vCPU-seconds.</p><p>To compute the per-vCPU utilization fraction, divide this value by (end-start)*N, where end and start define this value's time interval and N is `compute.googleapis.com/instance/cpu/reserved_cores` at the end of the interval.</p><p>This value is reported by the hypervisor for the VM and can differ from `agent.googleapis.com/cpu/usage_time`, which is reported from inside the VM.</p>|Dependent item|gcp.gce.cpu.usage_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usage_time`</p></li></ul>|
|CPU utilization|<p>Fractional utilization of allocated CPU on this instance.</p><p>This metric is reported by the hypervisor for the VM and can differ from `agent.googleapis.com/cpu/utilization`, which is reported from inside the VM.</p>|Dependent item|gcp.gce.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Memory size|<p>Total VM memory size.</p><p>This metric is only available for VMs that belong to the e2 family; returns empty value for different instance types.</p>|Dependent item|gcp.gce.memory.ram_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ram_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory used|<p>Memory currently used in the VM.</p><p>This metric is only available for VMs that belong to the e2 family; returns empty value for different instance types.</p>|Dependent item|gcp.gce.memory.ram_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ram_used`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory usage percentage|<p>Memory usage Percentage.</p><p>This metric is only available for VMs that belong to the e2 family; returns empty value for different instance types.</p>|Dependent item|gcp.gce.memory.ram_pused<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ram_pused`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM swap in|<p>The amount of memory read into the guest from its own swap space.</p><p>This metric is only available for VMs that belong to the e2 family; returns empty value for different instance types.</p>|Dependent item|gcp.gce.memory.swap_in_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.swap_in_bytes_count`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM swap out|<p>The amount of memory written from the guest to its own swap space.</p><p>This metric is only available for VMs that belong to the e2 family; returns empty value for different instance types.</p>|Dependent item|gcp.gce.memory.swap_out_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.swap_out_bytes_count`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Received bytes|<p>Count of bytes received from the network without load-balancing.</p>|Dependent item|gcp.gce.network.lb.received_bytes_count.false<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.received_bytes_count.false`</p></li></ul>|
|Network: Received bytes: Load-balanced|<p>Whether traffic was received by an L3 loadbalanced IP address assigned to the VM.</p><p>Traffic that is externally routed to the VM's standard internal or external IP address, such as L7 loadbalanced traffic, is not considered to be loadbalanced in this metric.</p><p>The value is empty when load-balancing is not used.</p>|Dependent item|gcp.gce.network.lb.received_bytes_count.true<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.received_bytes_count.true`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Received packets|<p>Count of packets received from the network without load-balancing.</p>|Dependent item|gcp.gce.network.lb.received_packets_count.false<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.received_packets_count.false`</p></li></ul>|
|Network: Received packets: Load-balanced|<p>Whether traffic was received by an L3 loadbalanced IP address assigned to the VM.</p><p>Traffic that is externally routed to the VM's standard internal or external IP address, such as L7 loadbalanced traffic, is not considered to be loadbalanced in this metric.</p><p>The value is empty when load-balancing is not used.</p>|Dependent item|gcp.gce.network.lb.received_packets_count.true<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.received_packets_count.true`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Sent bytes|<p>Count of bytes sent over the network without load-balancing.</p>|Dependent item|gcp.gce.network.lb.sent_bytes_count.false<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sent_bytes_count.false`</p></li></ul>|
|Network: Sent bytes: Load-balanced|<p>Whether traffic was received by an L3 loadbalanced IP address assigned to the VM.</p><p>Traffic that is externally routed to the VM's standard internal or external IP address, such as L7 loadbalanced traffic, is not considered to be loadbalanced in this metric.</p><p>The value is empty when load-balancing is not used.</p>|Dependent item|gcp.gce.network.lb.sent_bytes_count.true<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sent_bytes_count.true`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Sent packets|<p>Count of packets sent over the network without load-balancing.</p>|Dependent item|gcp.gce.network.lb.sent_packets_count.false<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sent_packets_count.false`</p></li></ul>|
|Network: Sent packets: Load-balanced|<p>Whether traffic was received by an L3 loadbalanced IP address assigned to the VM.</p><p>Traffic that is externally routed to the VM's standard internal or external IP address, such as L7 loadbalanced traffic, is not considered to be loadbalanced in this metric.</p><p>The value is empty when load-balancing is not used.</p>|Dependent item|gcp.gce.network.lb.sent_packets_count.true<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sent_packets_count.true`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Mirrored bytes|<p>The count of mirrored bytes.</p>|Dependent item|gcp.gce.network.mirrored_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mirrored_bytes_count`</p></li></ul>|
|Network: Mirrored packets|<p>The count of mirrored packets.</p>|Dependent item|gcp.gce.network.mirrored_packets_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mirrored_packets_count`</p></li></ul>|
|Network: Mirrored packets dropped: Out of quota|<p>The count of mirrored packets dropped.</p><p>Reason - out of quota.</p>|Dependent item|gcp.gce.network.mirr_dropped_packets.out_of_quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.out_of_quota`</p></li></ul>|
|Network: Mirrored packets dropped: Unknown|<p>The count of mirrored packets dropped.</p><p>Reason - unknown.</p>|Dependent item|gcp.gce.network.mirr_dropped_packets.unknown<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.unknown`</p></li></ul>|
|Network: Mirrored packets dropped: Invalid|<p>The count of mirrored packets dropped.</p><p>Reason - invalid.</p>|Dependent item|gcp.gce.network.mirr_dropped_packets.invalid<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.invalid`</p></li></ul>|
|Integrity: Early boot validation status|<p>The validation status of early boot integrity policy.</p><p>Empty value if integrity monitoring isn't enabled.</p>|Dependent item|gcp.gce.integrity.early_boot_validation_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.early_boot_validation_status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Integrity: Late boot validation status|<p>The validation status of late boot integrity policy.</p><p>Empty value if integrity monitoring isn't enabled.</p>|Dependent item|gcp.gce.integrity.late_boot_validation_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.late_boot_validation_status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Instance uptime|<p>Elapsed time since the VM was started, in seconds.</p>|Dependent item|gcp.gce.instance.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime_total`</p></li></ul>|
|Instance state|<p>GCP Compute Engine instance state.</p>|HTTP agent|gcp.gce.instance.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set value to: `10`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Disks get|<p>Disk entities and metrics related to a particular instance.</p>|Script|gcp.gce.disks.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GCP Compute Engine Instance: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/GCP Compute Engine Instance by HTTP/gcp.gce.cpu.utilization,15m) >= {$GCE.CPU.UTIL.MAX}`|Average|**Manual close**: Yes|
|GCP Compute Engine Instance: High memory utilization|<p>RAM utilization is too high. The system might be slow to respond.</p>|`min(/GCP Compute Engine Instance by HTTP/gcp.gce.memory.ram_pused,15m) >= {$GCE.RAM.UTIL.MAX}`|Average||
|GCP Compute Engine Instance: Instance is in suspended state|<p>The VM is in a suspended state. You can resume the VM or delete it.</p>|`last(/GCP Compute Engine Instance by HTTP/gcp.gce.instance.state) = 7`|Info|**Manual close**: Yes|
|GCP Compute Engine Instance: The instance is in repairing state|<p>The VM is being repaired.<br>Repairing occurs when the VM encounters an internal error or the underlying machine is unavailable due to maintenance.<br>During this time, the VM is unusable.</p>|`last(/GCP Compute Engine Instance by HTTP/gcp.gce.instance.state) = 4`|Warning|**Manual close**: Yes|
|GCP Compute Engine Instance: The instance is in terminated state|<p>The VM is stopped. You stopped the VM, or the VM encountered a failure.</p>|`last(/GCP Compute Engine Instance by HTTP/gcp.gce.instance.state) = 5`|Average|**Manual close**: Yes|
|GCP Compute Engine Instance: Failed to get the instance state|<p>Failed to get the instance state.<br>Check access permissions to GCP API or service account.</p>|`last(/GCP Compute Engine Instance by HTTP/gcp.gce.instance.state) = 10`|Average|**Manual close**: Yes|

### LLD rule GCP Compute Engine: Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GCP Compute Engine: Physical disks discovery|<p>GCP Compute Engine: Physical disks discovery.</p>|Dependent item|gcp.gce.phys.disks.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for GCP Compute Engine: Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#GCE.DISK.NAME}]: Raw data|<p>Data in raw format for the disk with the name [{#GCE.DISK.NAME}].</p>|Dependent item|gcp.gce.quota.single.raw[{#GCE.DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.disk_name == "{#GCE.DISK.NAME}")].metrics.first()`</p></li></ul>|
|Disk [{#GCE.DISK.NAME}]: Read bytes|<p>Count of bytes read from [{#GCE.DISK.NAME}] disk.</p>|Dependent item|gcp.gce.disk.read_bytes_count[{#GCE.DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.read_bytes_count`</p></li></ul>|
|Disk [{#GCE.DISK.NAME}]: Read operations|<p>Count of read IO operations from [{#GCE.DISK.NAME}] disk.</p>|Dependent item|gcp.gce.disk.read_ops_count[{#GCE.DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.read_ops_count`</p></li></ul>|
|Disk [{#GCE.DISK.NAME}]: Write bytes|<p>Count of bytes written to {#GCE.DISK.NAME}] disk.</p>|Dependent item|gcp.gce.disk.write_bytes_count[{#GCE.DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_bytes_count`</p></li></ul>|
|Disk [{#GCE.DISK.NAME}]: Write operations|<p>Count of write IO operations to [{#GCE.DISK.NAME}] disk.</p>|Dependent item|gcp.gce.disk.write_ops_count[{#GCE.DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_ops_count`</p></li></ul>|

# GCP Cloud SQL MySQL by HTTP

## Overview

This template is designed to monitor Google Cloud Platform Cloud SQL MySQL instances by Zabbix.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GCP Cloud SQL MySQL versions: 8.0, 5.7

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template will be automatically connected to discovered entities with all their required parameters pre-defined.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.TIME.WINDOW}|<p>Time interval for the data requests.</p><p>Supported usage type:</p><p>1. The default update interval for most of the items.</p><p>2. The minimal time window for the data requested in the Monitoring Query Language REST API request.</p>|`5m`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$CLOUD_SQL.MYSQL.DISK.UTIL.WARN}|<p>GCP Cloud SQL MySQL instance warning disk usage threshold.</p>|`80`|
|{$CLOUD_SQL.MYSQL.DISK.UTIL.CRIT}|<p>GCP Cloud SQL MySQL instance critical disk usage threshold.</p>|`90`|
|{$CLOUD_SQL.MYSQL.CPU.UTIL.MAX}|<p>GCP Cloud SQL MySQL instance CPU usage threshold.</p>|`95`|
|{$CLOUD_SQL.MYSQL.RAM.UTIL.MAX}|<p>GCP Cloud SQL MySQL instance RAM usage threshold.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Metrics get|<p>MySQL metrics in raw format.</p>|Script|gcp.cloudsql.mysql.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Reserved CPU cores|<p>Number of cores reserved for the database.</p>|Dependent item|gcp.cloudsql.mysql.cpu.reserved_cores<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_reserved_cores`</p></li></ul>|
|CPU usage time|<p>Cumulative CPU usage time in seconds.</p>|Dependent item|gcp.cloudsql.mysql.cpu.usage_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_usage_time`</p></li></ul>|
|CPU utilization|<p>Current CPU utilization represented as a percentage of the reserved CPU that is currently in use.</p>|Dependent item|gcp.cloudsql.mysql.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Disk size|<p>Maximum data disk size in bytes.</p>|Dependent item|gcp.cloudsql.mysql.disk.quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_quota`</p></li></ul>|
|Disk bytes used|<p>Data utilization in bytes.</p>|Dependent item|gcp.cloudsql.mysql.disk.bytes_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_bytes_used`</p></li></ul>|
|Disk read I/O|<p>Delta count of data disk read I/O operations.</p>|Dependent item|gcp.cloudsql.mysql.disk.read_ops_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_read_ops_count`</p></li></ul>|
|Disk write I/O|<p>Delta count of data disk write I/O operations.</p>|Dependent item|gcp.cloudsql.mysql.disk.write_ops_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_write_ops_count`</p></li></ul>|
|Disk utilization|<p>The fraction of the disk quota that is currently in use. </p><p>Shown as percentage.</p>|Dependent item|gcp.cloudsql.mysql.disk.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Memory size|<p>Maximum RAM size in bytes.</p>|Dependent item|gcp.cloudsql.mysql.memory.quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_quota`</p></li></ul>|
|Memory used by DB engine|<p>Total RAM usage in bytes. </p><p>This metric reports the RAM usage of the database process, including the buffer/cache.</p>|Dependent item|gcp.cloudsql.mysql.memory.total_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_total_usage`</p></li></ul>|
|Memory usage|<p>The RAM usage in bytes. </p><p>This metric reports the RAM usage of the server, excluding the buffer/cache.</p>|Dependent item|gcp.cloudsql.mysql.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_usage`</p></li></ul>|
|Memory utilization|<p>The fraction of the memory quota that is currently in use.</p><p>Shown as percentage.</p>|Dependent item|gcp.cloudsql.mysql.memory.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_ram_pused`</p></li></ul>|
|Network: Received bytes|<p>Delta count of bytes received through the network.</p>|Dependent item|gcp.cloudsql.mysql.network.received_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_received_bytes_count`</p></li></ul>|
|Network: Sent bytes|<p>Delta count of bytes sent through the network.</p>|Dependent item|gcp.cloudsql.mysql.network.sent_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_sent_bytes_count`</p></li></ul>|
|Connections|<p>Number of connections to the databases on the Cloud SQL instance.</p>|Dependent item|gcp.cloudsql.mysql.network.connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_connections`</p></li></ul>|
|Instance state|<p>GCP Cloud SQL MySQL Current instance state.</p>|HTTP agent|gcp.cloudsql.mysql.inst.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.timeSeriesData[0].pointData[0].values[0].stringValue`</p><p>⛔️Custom on fail: Set value to: `10`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|DB engine state|<p>GCP Cloud SQL MySQL DB Engine State.</p>|HTTP agent|gcp.cloudsql.mysql.db.state<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.timeSeriesData[0].pointData[0].values[0].int64Value`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|InnoDB dirty pages|<p>Number of unflushed pages in the InnoDB buffer pool.</p>|Dependent item|gcp.cloudsql.mysql.innodb_buffer_pool_pages_dirty<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_innodb_buffer_pool_pages_dirty`</p></li></ul>|
|InnoDB free pages|<p>Number of unused pages in the InnoDB buffer pool.</p>|Dependent item|gcp.cloudsql.mysql.innodb_buffer_pool_pages_free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_innodb_buffer_pool_pages_free`</p></li></ul>|
|InnoDB total pages|<p>Total number of pages in the InnoDB buffer pool.</p>|Dependent item|gcp.cloudsql.mysql.innodb_buffer_pool_pages_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_innodb_buffer_pool_pages_total`</p></li></ul>|
|InnoDB fsync calls|<p>Delta count of InnoDB fsync() calls.</p>|Dependent item|gcp.cloudsql.mysql.innodb_data_fsyncs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_innodb_data_fsyncs`</p></li></ul>|
|InnoDB log fsync calls|<p>Delta count of InnoDB fsync() calls to the log file.</p>|Dependent item|gcp.cloudsql.mysql.innodb_os_log_fsyncs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_innodb_os_log_fsyncs`</p></li></ul>|
|InnoDB pages read|<p>Delta count of InnoDB pages read.</p>|Dependent item|gcp.cloudsql.mysql.innodb_pages_read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_innodb_pages_read`</p></li></ul>|
|InnoDB pages written|<p>Delta count of InnoDB pages written.</p>|Dependent item|gcp.cloudsql.mysql.innodb_pages_written<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_innodb_pages_written`</p></li></ul>|
|Open tables|<p>The number of tables that are currently open.</p>|Dependent item|gcp.cloudsql.mysql.open_tables<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_open_tables`</p></li></ul>|
|Open table definitions|<p>The number of table definitions that are currently cached.</p>|Dependent item|gcp.cloudsql.mysql.open_table_definitions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_open_table_definitions`</p></li></ul>|
|Queries|<p>Delta of statements executed by the server.</p>|Dependent item|gcp.cloudsql.queries<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_queries`</p></li></ul>|
|Questions|<p>Delta of statements executed by the server sent by the client.</p>|Dependent item|gcp.cloudsql.questions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_questions`</p></li></ul>|
|Network: Bytes received by MySQL|<p>Delta count of bytes received by MySQL process.</p>|Dependent item|gcp.cloudsql.mysql_received_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_received_bytes_count`</p></li></ul>|
|Network: Bytes sent by MySQL|<p>Delta count of bytes sent by MySQL process.</p>|Dependent item|gcp.cloudsql.mysql_sent_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mysql_sent_bytes_count`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GCP MySQL: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.cpu.utilization,5m) >= {$CLOUD_SQL.MYSQL.CPU.UTIL.MAX}`|Average||
|GCP MySQL: Disk space is low|<p>High utilization of the storage space.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.disk.utilization) >= {$CLOUD_SQL.MYSQL.DISK.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>GCP MySQL: Disk space is critically low</li></ul>|
|GCP MySQL: Disk space is critically low|<p>Critical utilization of the disk space.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.disk.utilization) >= {$CLOUD_SQL.MYSQL.DISK.UTIL.CRIT}`|Average||
|GCP MySQL: High memory utilization|<p>RAM utilization is too high. The system might be slow to respond.</p>|`min(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.memory.utilization,5m) >= {$CLOUD_SQL.MYSQL.RAM.UTIL.MAX}`|High||
|GCP MySQL: Instance is in suspended state|<p>The instance is in suspended state. <br>It is not available, for example, due to problems with billing.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.inst.state) = 1`|Warning||
|GCP MySQL: Instance is stopped by the owner|<p>The instance has been stopped by the owner. <br>It is not currently running, but it's ready to be restarted.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.inst.state) = 2`|Info||
|GCP MySQL: Instance is in maintenance|<p>The instance is down for maintenance.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.inst.state) = 4`|Info||
|GCP MySQL: Instance is in failed state|<p>The instance creation failed, or an operation left the instance in an own bad state.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.inst.state) = 5`|Average||
|GCP MySQL: Instance is in unknown state|<p>The state of the instance is unknown.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.inst.state) = 6`|Average||
|GCP MySQL: Failed to get the instance state|<p>Failed to get the instance state. <br>Check access permissions to GCP API or service account.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.inst.state) = 10`|Average||
|GCP MySQL: Database engine is down|<p>Database engine is down.<br>If an instance experiences unplanned (non-maintenance) downtime, the instance state will still be RUNNING, but the database engine state metric will report 0.</p>|`last(/GCP Cloud SQL MySQL by HTTP/gcp.cloudsql.mysql.db.state)=0`|Average|**Depends on**:<br><ul><li>GCP MySQL: Instance is stopped by the owner</li><li>GCP MySQL: Instance is in suspended state</li><li>GCP MySQL: Instance is in maintenance</li><li>GCP MySQL: Instance is in failed state</li><li>GCP MySQL: Instance is in unknown state</li><li>GCP MySQL: Failed to get the instance state</li></ul>|

# GCP Cloud SQL MySQL Replica by HTTP

## Overview

This template is designed to monitor Google Cloud Platform Cloud SQL metrics for the MySQL read-only replica instances by Zabbix.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GCP Cloud SQL MySQL read replica versions: 8.0, 5.7

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template will be automatically connected to discovered entities with all their required parameters pre-defined.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.TIME.WINDOW}|<p>Time interval for the data requests.</p><p>Supported usage type:</p><p>1. The default update interval for most of the items.</p><p>2. The minimal time window for the data requested in the Monitoring Query Language REST API request.</p>|`5m`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replica metrics get|<p>MySQL replication metrics data in raw format.</p>|Script|gcp.cloudsql.mysql.repl.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Last I/O thread error number|<p>The error number of the most recent error that caused the I/O thread to stop.</p>|Dependent item|gcp.cloudsql.mysql.repl.last_io_errno<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_io_errno`</p></li></ul>|
|Last SQL thread error number|<p>The error number of the most recent error that caused the SQL thread to stop.</p>|Dependent item|gcp.cloudsql.mysql.repl.last_sql_errno<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_sql_errno`</p></li></ul>|
|Replication lag|<p>Number of seconds the read replica is behind its primary (approximation).</p>|Dependent item|gcp.cloudsql.mysql.repl.replica_lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replica_lag`</p></li></ul>|
|Network lag|<p>Indicates time taken from primary binary log to IO thread on replica.</p>|Dependent item|gcp.cloudsql.mysql.repl.network_lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network_lag`</p></li></ul>|
|Replication state|<p>The current serving state of replication.</p><p>This metric is only available for the MySQL/PostgreSQL instances.</p>|Dependent item|gcp.cloudsql.mysql.repl.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Slave I/O thread running|<p>Indicates whether the I/O thread for reading the primary's binary log is running.</p><p>Possible values are Yes, No and Connecting.</p>|Dependent item|gcp.cloudsql.mysql.repl.slave_io_running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_io_running`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Slave SQL thread running|<p>Indicates whether the SQL thread for executing events in the relay log is running.</p>|Dependent item|gcp.cloudsql.mysql.repl.slave_sql_running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_sql_running`</p></li><li>Boolean to decimal</li></ul>|

# GCP Cloud SQL PostgreSQL by HTTP

## Overview

This template is designed to monitor Google Cloud Platform Cloud SQL PostgreSQL database metrics by Zabbix.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GCP Cloud SQL PostgreSQL versions: 14, 13, 12

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template will be automatically connected to discovered entities with all their required parameters pre-defined.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.TIME.WINDOW}|<p>Time interval for the data requests.</p><p>Supported usage type:</p><p>1. The default update interval for most of the items.</p><p>2. The minimal time window for the data requested in the Monitoring Query Language REST API request.</p>|`5m`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$GCP.CLOUD_SQL.DB.NAME.MATCHES}|<p>The filter to include GCP Cloud SQL PostgreSQL databases by namespace.</p>|`.*`|
|{$GCP.CLOUD_SQL.DB.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL PostgreSQL databases by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$CLOUD_SQL.PGSQL.DISK.UTIL.WARN}|<p>GCP Cloud SQL PostgreSQL instance warning disk usage threshold.</p>|`80`|
|{$CLOUD_SQL.PGSQL.DISK.UTIL.CRIT}|<p>GCP Cloud SQL PostgreSQL instance critical disk usage threshold.</p>|`90`|
|{$CLOUD_SQL.PGSQL.CPU.UTIL.MAX}|<p>GCP Cloud SQL PostgreSQL instance CPU usage threshold.</p>|`95`|
|{$CLOUD_SQL.PGSQL.RAM.UTIL.MAX}|<p>GCP Cloud SQL PostgreSQL instance RAM usage threshold.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Metrics get|<p>PostgreSQL metrics data in raw format.</p>|Script|gcp.cloudsql.pgsql.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Reserved CPU cores|<p>Number of cores reserved for the database.</p>|Dependent item|gcp.cloudsql.pgsql.cpu.reserved_cores<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_reserved_cores`</p></li></ul>|
|CPU usage time|<p>Cumulative CPU usage time in seconds.</p>|Dependent item|gcp.cloudsql.pgsql.cpu.usage_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_usage_time`</p></li></ul>|
|CPU utilization|<p>Current CPU utilization represented as a percentage of the reserved CPU that is currently in use.</p>|Dependent item|gcp.cloudsql.pgsql.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Disk size|<p>Maximum data disk size in bytes.</p>|Dependent item|gcp.cloudsql.pgsql.disk.quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_quota`</p></li></ul>|
|Disk bytes used|<p>Data utilization in bytes.</p>|Dependent item|gcp.cloudsql.pgsql.disk.bytes_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_bytes_used`</p></li></ul>|
|Disk read I/O|<p>Delta count of data disk read I/O operations.</p>|Dependent item|gcp.cloudsql.pgsql.disk.read_ops_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_read_ops_count`</p></li></ul>|
|Disk write I/O|<p>Delta count of data disk write I/O operations.</p>|Dependent item|gcp.cloudsql.pgsql.disk.write_ops_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_write_ops_count`</p></li></ul>|
|Disk utilization|<p>The fraction of the disk quota that is currently in use. </p><p>Shown as percentage.</p>|Dependent item|gcp.cloudsql.pgsql.disk.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Memory size|<p>Maximum RAM size in bytes.</p>|Dependent item|gcp.cloudsql.pgsql.memory.quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_quota`</p></li></ul>|
|Memory used by DB engine|<p>Total RAM usage in bytes. </p><p>This metric reports the RAM usage of the database process, including the buffer/cache.</p>|Dependent item|gcp.cloudsql.pgsql.memory.total_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_total_usage`</p></li></ul>|
|Memory usage|<p>The RAM usage in bytes. </p><p>This metric reports the RAM usage of the server, excluding the buffer/cache.</p>|Dependent item|gcp.cloudsql.pgsql.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_usage`</p></li></ul>|
|Memory utilization|<p>The fraction of the memory quota that is currently in use.</p><p>Shown as percentage.</p>|Dependent item|gcp.cloudsql.pgsql.memory.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_ram_pused`</p></li></ul>|
|Network: Received bytes|<p>Delta count of bytes received through the network.</p>|Dependent item|gcp.cloudsql.pgsql.network.received_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_received_bytes_count`</p></li></ul>|
|Network: Sent bytes|<p>Delta count of bytes sent through the network.</p>|Dependent item|gcp.cloudsql.pgsql.network.sent_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_sent_bytes_count`</p></li></ul>|
|Instance state|<p>GCP Cloud SQL PostgreSQL Current instance state.</p>|HTTP agent|gcp.cloudsql.pgsql.inst.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.timeSeriesData[0].pointData[0].values[0].stringValue`</p><p>⛔️Custom on fail: Set value to: `10`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|DB engine state|<p>GCP Cloud SQL PostgreSQL DB Engine State.</p>|HTTP agent|gcp.cloudsql.pgsql.db.state<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.timeSeriesData[0].pointData[0].values[0].int64Value`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Transaction ID utilization|<p>Current utilization represented as a percentage of transaction IDs consumed by the Cloud SQL PostgreSQL instance.</p>|Dependent item|gcp.cloudsql.pgsql.transaction_id_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_transaction_id_utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Assigned transactions|<p>Delta count of assigned transaction IDs.</p>|Dependent item|gcp.cloudsql.pgsql.transaction_id_count_assigned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_assigned`</p></li></ul>|
|Frozen transactions|<p>Delta count of frozen transaction IDs.</p>|Dependent item|gcp.cloudsql.pgsql.transaction_id_count_frozen<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_frozen`</p></li></ul>|
|Data written to temporary|<p>Total data size (in bytes) written to temporary files by the queries.</p>|Dependent item|gcp.cloudsql.pgsql.temp_bytes_written_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_temp_bytes_written_count`</p></li></ul>|
|Temporary files used for writing data|<p>Total number of temporary files used for writing data while performing algorithms such as join and sort.</p>|Dependent item|gcp.cloudsql.pgsql.temp_files_written_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_temp_files_written_count`</p></li></ul>|
|Oldest running transaction age|<p>Age of the oldest running transaction yet to be vacuumed in the Cloud SQL PostgreSQL instance, measured in number of transactions that have happened since the oldest transaction.</p><p>Empty value when there is no such transaction type.</p>|Dependent item|gcp.cloudsql.pgsql.oldest_transaction.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_running`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Oldest prepared transaction age|<p>Age of the oldest prepared transaction yet to be vacuumed in the Cloud SQL PostgreSQL instance, measured in number of transactions that have happened since the oldest transaction.</p><p>Empty value when there is no such transaction type.</p>|Dependent item|gcp.cloudsql.pgsql.oldest_transaction.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_prepared`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Oldest replication slot transaction age|<p>Age of the oldest replication slot transaction yet to be vacuumed in the Cloud SQL PostgreSQL instance, measured in number of transactions that have happened since the oldest transaction.</p><p>Empty value when there is no such transaction type.</p>|Dependent item|gcp.cloudsql.pgsql.oldest_transaction.replication_slot<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_replication_slot`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Oldest replica transaction age|<p>Age of the oldest replica transaction yet to be vacuumed in the Cloud SQL PostgreSQL instance, measured in number of transactions that have happened since the oldest transaction.</p><p>Empty value when there is no such transaction type.</p>|Dependent item|gcp.cloudsql.pgsql.oldest_transaction.replica<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_replica`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections|<p>The number of the connections to the Cloud SQL PostgreSQL instance.</p><p>Includes connections to the system databases, which aren't visible by default.</p>|Dependent item|gcp.cloudsql.pgsql.num_backends<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pgsql_num_backends`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GCP PostgreSQL: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.cpu.utilization,5m) >= {$CLOUD_SQL.PGSQL.CPU.UTIL.MAX}`|Average||
|GCP PostgreSQL: Disk space is low|<p>High utilization of the storage space.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.disk.utilization) >= {$CLOUD_SQL.PGSQL.DISK.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>GCP PostgreSQL: Disk space is critically low</li></ul>|
|GCP PostgreSQL: Disk space is critically low|<p>Critical utilization of the disk space.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.disk.utilization) >= {$CLOUD_SQL.PGSQL.DISK.UTIL.CRIT}`|Average||
|GCP PostgreSQL: High memory utilization|<p>RAM utilization is too high. The system might be slow to respond.</p>|`min(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.memory.utilization,5m) >= {$CLOUD_SQL.PGSQL.RAM.UTIL.MAX}`|High||
|GCP PostgreSQL: Instance is in suspended state|<p>The instance is in suspended state. <br>It is not available, for example, due to problems with billing.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.inst.state) = 1`|Warning||
|GCP PostgreSQL: Instance is stopped by the owner|<p>The instance has been stopped by the owner. <br>It is not currently running, but it's ready to be restarted.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.inst.state) = 2`|Info||
|GCP PostgreSQL: Instance is in maintenance|<p>The instance is down for maintenance.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.inst.state) = 4`|Info||
|GCP PostgreSQL: Instance is in failed state|<p>The instance creation failed, or an operation left the instance in an own bad state.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.inst.state) = 5`|Average||
|GCP PostgreSQL: Instance is in unknown state|<p>The state of the instance is unknown.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.inst.state) = 6`|Average||
|GCP PostgreSQL: Failed to get the instance state|<p>Failed to get the instance state. <br>Check access permissions to GCP API or service account.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.inst.state) = 10`|Average||
|GCP PostgreSQL: Database engine is down|<p>Database engine is down.<br>If an instance experiences unplanned (non-maintenance) downtime, the instance state will still be RUNNING, but the database engine state metric will report 0.</p>|`last(/GCP Cloud SQL PostgreSQL by HTTP/gcp.cloudsql.pgsql.db.state)=0`|Average|**Depends on**:<br><ul><li>GCP PostgreSQL: Instance is stopped by the owner</li><li>GCP PostgreSQL: Instance is in suspended state</li><li>GCP PostgreSQL: Instance is in maintenance</li><li>GCP PostgreSQL: Instance is in failed state</li><li>GCP PostgreSQL: Instance is in unknown state</li><li>GCP PostgreSQL: Failed to get the instance state</li></ul>|

### LLD rule GCP Cloud SQL PostgreSQL: Databases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GCP Cloud SQL PostgreSQL: Databases discovery|<p>Databases discovery for the particular PostgreSQL instance.</p>|HTTP agent|gcp.cloudsql.pgsql.db.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for GCP Cloud SQL PostgreSQL: Databases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database [{#PGSQL.DB.NAME}]: Metrics raw|<p>PostgreSQL metrics in raw format.</p>|Script|gcp.cloudsql.pgsql.db.metrics.get[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Deadlocks count|<p>Number of deadlocks detected in the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.deadlock_count[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlock_count`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Tuples returned|<p>Total number of rows scanned while processing the queries of the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.tuples_returned_count[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tuples_returned_count`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Tuples fetched|<p>Total number of rows fetched as a result of queries to the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.tuples_fetched_count[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tuples_fetched_count`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Committed transactions|<p>Delta count of number of committed transactions to the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.transaction_count_commit[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.commit`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Rolled-back transactions|<p>Delta count of number of rolled-back transactions in the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.transaction_count_rollback[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rollback`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Buffer cache blocks read.|<p>Number of buffer cache blocks read by the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.blocks_read_count_buffer_cache[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffer_cache`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Disk blocks read.|<p>Number of disk blocks read by the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.blocks_read_count_disk[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Inserted rows processed.|<p>Number of tuples(rows) processed for insert operations for the database with the name [{#PGSQL.DB.NAME}].</p>|Dependent item|gcp.cloudsql.pgsql.tuples_processed_count_insert[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.insert`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Deleted rows processed|<p>Number of tuples(rows) processed for delete operations for the database with the name [{#PGSQL.DB.NAME}].</p>|Dependent item|gcp.cloudsql.pgsql.tuples_processed_count_delete[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.delete`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Updated rows processed|<p>Number of tuples(rows) processed for update operations for the database with the name [{#PGSQL.DB.NAME}].</p>|Dependent item|gcp.cloudsql.pgsql.tuples_processed_count_update[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.update`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Live tuples|<p>Number of live tuples(rows) in the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.tuple_size_live[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.live`</p></li></ul>|
|Database [{#PGSQL.DB.NAME}]: Dead tuples|<p>Number of live tuples(rows) in the [{#PGSQL.DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.pgsql.tuple_size_dead[{#PGSQL.DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dead`</p></li></ul>|

# GCP Cloud SQL PostgreSQL Replica by HTTP

## Overview

This template is designed to monitor Google Cloud Platform Cloud SQL PostgreSQL read-only replica instances by Zabbix.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GCP Cloud SQL PostgreSQL read replica versions: 14, 13, 12

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template will be automatically connected to discovered entities with all their required parameters pre-defined.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.TIME.WINDOW}|<p>Time interval for the data requests.</p><p>Supported usage type:</p><p>1. The default update interval for most of the items.</p><p>2. The minimal time window for the data requested in the Monitoring Query Language REST API request.</p>|`5m`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replica metrics get|<p>PostgreSQL replica metrics data in raw format.</p>|Script|gcp.cloudsql.pgsql.repl.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network lag|<p>Indicates time taken from primary binary log to IO thread on replica.</p>|Dependent item|gcp.cloudsql.pgsql.repl.network_lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network_lag`</p></li></ul>|
|Replication lag|<p>Number of seconds the read replica is behind its primary (approximation).</p>|Dependent item|gcp.cloudsql.pgsql.repl.replica_lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replica_lag`</p></li></ul>|
|Replication state|<p>The current serving state of replication.</p><p>This metric is only available for the MySQL/PostgreSQL instances.</p>|Dependent item|gcp.cloudsql.pgsql.repl.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Replay location lag|<p>Replay location replication lag in bytes.</p>|Dependent item|gcp.cloudsql.pgsql.repl.replay_location<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replay_location`</p></li></ul>|
|Write location lag|<p>Write location replication lag in bytes.</p>|Dependent item|gcp.cloudsql.pgsql.repl.write_location<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_location`</p></li></ul>|
|Flush location lag|<p>Flush location replication lag in bytes.</p>|Dependent item|gcp.cloudsql.pgsql.repl.flush_location<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flush_location`</p></li></ul>|
|Sent location lag|<p>Sent location replication lag in bytes.</p>|Dependent item|gcp.cloudsql.pgsql.repl.sent_location<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sent_location`</p></li></ul>|
|Number of log archival failures|<p>Number of failed attempts for archiving replication log files.</p>|Dependent item|gcp.cloudsql.pgsql.repl.log_archive_failure_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_archive_failure_count`</p></li></ul>|
|Number of log archival successes|<p>Number of failed attempts for archiving replication log files.</p>|Dependent item|gcp.cloudsql.pgsql.repl.log_archive_success_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_archive_success_count`</p></li></ul>|

# GCP Cloud SQL MSSQL by HTTP

## Overview

This template is designed to monitor Google Cloud Platform Cloud SQL MSSQL instances by Zabbix.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GCP Cloud SQL MSSQL versions: 2022 Standard/Enterprise, 2019 Standard/Enterprise, 2017 Standard/Enterprise.

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template will be automatically connected to discovered entities with all their required parameters pre-defined.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.TIME.WINDOW}|<p>Time interval for the data requests.</p><p>Supported usage type:</p><p>1. The default update interval for most of the items.</p><p>2. The minimal time window for the data requested in the Monitoring Query Language REST API request.</p>|`5m`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$CLOUD_SQL.MSSQL.RES.NAME.MATCHES}|<p>The filter to include GCP Cloud SQL MSSQL resources by namespace.</p>|`.*`|
|{$CLOUD_SQL.MSSQL.RES.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MSSQL resources by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$CLOUD_SQL.MSSQL.DB.NAME.MATCHES}|<p>The filter to include GCP Cloud SQL MSSQL databases by namespace.</p>|`.*`|
|{$CLOUD_SQL.MSSQL.DB.NAME.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MSSQL databases by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$CLOUD_SQL.MSSQL.SCHEDULER.ID.MATCHES}|<p>The filter to include GCP Cloud SQL MSSQL schedulers by namespace.</p>|`.*`|
|{$CLOUD_SQL.MSSQL.SCHEDULER.ID.NOT_MATCHES}|<p>The filter to exclude GCP Cloud SQL MSSQL schedulers by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$CLOUD_SQL.MSSQL.DISK.UTIL.WARN}|<p>GCP Cloud SQL MSSQL instance warning disk usage threshold.</p>|`80`|
|{$CLOUD_SQL.MSSQL.DISK.UTIL.CRIT}|<p>GCP Cloud SQL MSSQL instance critical disk usage threshold.</p>|`90`|
|{$CLOUD_SQL.MSSQL.CPU.UTIL.MAX}|<p>GCP Cloud SQL MSSQL instance CPU usage threshold.</p>|`95`|
|{$CLOUD_SQL.MSSQL.RAM.UTIL.MAX}|<p>GCP Cloud SQL MSSQL instance RAM usage threshold.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Metrics get|<p>MSSQL metrics data in raw format.</p>|Script|gcp.cloudsql.mssql.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Reserved CPU cores|<p>Number of cores reserved for the database.</p>|Dependent item|gcp.cloudsql.mssql.cpu.reserved_cores<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_reserved_cores`</p></li></ul>|
|CPU usage time|<p>Cumulative CPU usage time in seconds.</p>|Dependent item|gcp.cloudsql.mssql.cpu.usage_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_usage_time`</p></li></ul>|
|CPU utilization|<p>Current CPU utilization represented as a percentage of the reserved CPU that is currently in use.</p>|Dependent item|gcp.cloudsql.mssql.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Disk size|<p>Maximum data disk size in bytes.</p>|Dependent item|gcp.cloudsql.mssql.disk.quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_quota`</p></li></ul>|
|Disk bytes used|<p>Data utilization in bytes.</p>|Dependent item|gcp.cloudsql.mssql.disk.bytes_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_bytes_used`</p></li></ul>|
|Disk read I/O|<p>Delta count of data disk read I/O operations.</p>|Dependent item|gcp.cloudsql.mssql.disk.read_ops_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_read_ops_count`</p></li></ul>|
|Disk write I/O|<p>Delta count of data disk write I/O operations.</p>|Dependent item|gcp.cloudsql.mssql.disk.write_ops_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_write_ops_count`</p></li></ul>|
|Disk utilization|<p>The fraction of the disk quota that is currently in use. </p><p>Shown as percentage.</p>|Dependent item|gcp.cloudsql.mssql.disk.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_utilization`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Memory size|<p>Maximum RAM size in bytes.</p>|Dependent item|gcp.cloudsql.mssql.memory.quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_quota`</p></li></ul>|
|Memory used by DB engine|<p>Total RAM usage in bytes. </p><p>This metric reports the RAM usage of the database process, including the buffer/cache.</p>|Dependent item|gcp.cloudsql.mssql.memory.total_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_total_usage`</p></li></ul>|
|Memory usage|<p>The RAM usage in bytes. </p><p>This metric reports the RAM usage of the server, excluding the buffer/cache.</p>|Dependent item|gcp.cloudsql.mssql.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_usage`</p></li></ul>|
|Memory utilization|<p>The fraction of the memory quota that is currently in use.</p><p>Shown as percentage.</p>|Dependent item|gcp.cloudsql.mssql.memory.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_ram_pused`</p></li></ul>|
|Network: Received bytes|<p>Delta count of bytes received through the network.</p>|Dependent item|gcp.cloudsql.mssql.network.received_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_received_bytes_count`</p></li></ul>|
|Network: Sent bytes|<p>Delta count of bytes sent through the network.</p>|Dependent item|gcp.cloudsql.mssql.network.sent_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_sent_bytes_count`</p></li></ul>|
|Connections|<p>Number of connections to the databases on the Cloud SQL instance.</p>|Dependent item|gcp.cloudsql.mssql.network.connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_connections`</p></li></ul>|
|Instance state|<p>GCP Cloud SQL MSSQL Current instance state.</p>|HTTP agent|gcp.cloudsql.mssql.inst.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.timeSeriesData[0].pointData[0].values[0].stringValue`</p><p>⛔️Custom on fail: Set value to: `10`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|DB engine state|<p>GCP Cloud SQL MSSQL DB Engine State.</p>|HTTP agent|gcp.cloudsql.mssql.db.state<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.timeSeriesData[0].pointData[0].values[0].int64Value`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Connection resets|<p>Total number of login operations started from the connection pool since the last restart of SQL Server service.</p>|Dependent item|gcp.cloudsql.mssql.conn.connection_reset_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_connection_reset_count`</p></li></ul>|
|Login attempts|<p>Total number of login attempts since the last restart of SQL Server service.</p><p>This does not include pooled connections.</p>|Dependent item|gcp.cloudsql.mssql.conn.login_attempt_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_login_attempt_count`</p></li></ul>|
|Logouts|<p>Total number of logout operations since the last restart of SQL Server service.</p>|Dependent item|gcp.cloudsql.mssql.conn.logout_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_logout_count`</p></li></ul>|
|Processes blocked|<p>Current number of blocked processes.</p>|Dependent item|gcp.cloudsql.mssql.conn.processes_blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_processes_blocked`</p></li></ul>|
|Buffer cache hit ratio|<p>Current percentage of pages found in the buffer cache without having to read from disk.</p><p>The ratio is the total number of cache hits divided by the total number of cache lookups.</p>|Dependent item|gcp.cloudsql.mssql.memory.buffer_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_buffer_cache_hit_ratio`</p></li></ul>|
|Checkpoint pages|<p>Total number of pages flushed to disk by a checkpoint or other operation that requires all dirty pages to be flushed.</p>|Dependent item|gcp.cloudsql.mssql.memory.checkpoint_page_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_checkpoint_page_count`</p></li></ul>|
|Free list stalls|<p>Total number of requests that had to wait for a free page.</p>|Dependent item|gcp.cloudsql.mssql.memory.free_list_stall_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_free_list_stall_count`</p></li></ul>|
|Lazy writes|<p>Total number of buffers written by the buffer manager's lazy writer.</p><p>The lazy writer is a system process that flushes out batches of dirty, aged buffers</p><p>(buffers that contain changes that must be written back to disk before the buffer can be reused for a different page)</p><p>and makes them available to user processes.</p>|Dependent item|gcp.cloudsql.mssql.memory.lazy_write_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_lazy_write_count`</p></li></ul>|
|Memory grants pending|<p>Current number of processes waiting for a workspace memory grant.</p>|Dependent item|gcp.cloudsql.mssql.memory.memory_grants_pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_memory_grants_pending`</p></li></ul>|
|Page life expectancy|<p>Current number of seconds a page will stay in the buffer pool without references.</p>|Dependent item|gcp.cloudsql.mssql.memory.page_life_expectancy<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_page_life_expectancy`</p></li></ul>|
|Batch requests|<p>Total number of Transact-SQL command batches received.</p>|Dependent item|gcp.cloudsql.mssql.trans.batch_request_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_batch_request_count`</p></li></ul>|
|Forwarded records|<p>Total number of records fetched through forwarded record pointers.</p>|Dependent item|gcp.cloudsql.mssql.trans.forwarded_record_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_forwarded_record_count`</p></li></ul>|
|Full scans|<p>Total number of unrestricted full scans.</p><p>These can be either base-table or full-index scans.</p>|Dependent item|gcp.cloudsql.mssql.trans.full_scan_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_full_scan_count`</p></li></ul>|
|Page splits|<p>Total number of page splits that occur as the result of overflowing index pages.</p>|Dependent item|gcp.cloudsql.mssql.trans.page_split_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_page_split_count`</p></li></ul>|
|Probe scans|<p>Total number of probe scans that are used to find at least one single qualified row in an index or base table directly.</p>|Dependent item|gcp.cloudsql.mssql.trans.probe_scan_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_probe_scan_count`</p></li></ul>|
|SQL compilations|<p>Total number of SQL compilations.</p>|Dependent item|gcp.cloudsql.mssql.trans.sql_compilation_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_sql_compilation_count`</p></li></ul>|
|SQL recompilations|<p>Total number of SQL recompilations.</p>|Dependent item|gcp.cloudsql.mssql.trans.sql_recompilation_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_sql_recompilation_count`</p></li></ul>|
|Read page operations|<p>Total number of physical database page reads.</p><p>This metric counts physical page reads across all databases.</p>|Dependent item|gcp.cloudsql.mssql.memory.page_ops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_read`</p></li></ul>|
|Write age operations|<p>Total number of physical database page writes.</p><p>This metric counts physical page writes across all databases.</p>|Dependent item|gcp.cloudsql.mssql.memory.page_ops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_write`</p></li></ul>|
|Audits size|<p>Tracks the size in bytes of stored SQLServer audit files on an instance.</p><p>Empty value if there are no audits enabled.</p>|Dependent item|gcp.cloudsql.mssql.audits_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.base_audits_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Audits successfully uploaded|<p>Tracks the size in bytes of stored SQLServer audit files on an instance.</p><p>Empty value if there are no audits enabled.</p>|Dependent item|gcp.cloudsql.mssql.audits_upload_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mssql_success`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Resources get|<p>MSSQL resources data in raw format.</p>|Script|gcp.cloudsql.mssql.resources.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Databases get|<p>MSSQL databases data in raw format.</p>|Script|gcp.cloudsql.mssql.db.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Schedulers get|<p>MSSQL schedulers data in raw format.</p>|Script|gcp.cloudsql.mssql.schedulers.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GCP MSSQL: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.cpu.utilization,5m) >= {$CLOUD_SQL.MSSQL.CPU.UTIL.MAX}`|Average||
|GCP MSSQL: Disk space is low|<p>High utilization of the storage space.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.disk.utilization) >= {$CLOUD_SQL.MSSQL.DISK.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>GCP MSSQL: Disk space is critically low</li></ul>|
|GCP MSSQL: Disk space is critically low|<p>Critical utilization of the disk space.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.disk.utilization) >= {$CLOUD_SQL.MSSQL.DISK.UTIL.CRIT}`|Average||
|GCP MSSQL: High memory utilization|<p>RAM utilization is too high. The system might be slow to respond.</p>|`min(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.memory.utilization,5m) >= {$CLOUD_SQL.MSSQL.RAM.UTIL.MAX}`|High||
|GCP MSSQL: Instance is in suspended state|<p>The instance is in suspended state. <br>It is not available, for example, due to problems with billing.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.inst.state) = 1`|Warning||
|GCP MSSQL: Instance is stopped by the owner|<p>The instance has been stopped by the owner. <br>It is not currently running, but it's ready to be restarted.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.inst.state) = 2`|Info||
|GCP MSSQL: Instance is in maintenance|<p>The instance is down for maintenance.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.inst.state) = 4`|Info||
|GCP MSSQL: Instance is in failed state|<p>The instance creation failed, or an operation left the instance in an own bad state.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.inst.state) = 5`|Average||
|GCP MSSQL: Instance is in unknown state|<p>The state of the instance is unknown.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.inst.state) = 6`|Average||
|GCP MSSQL: Failed to get the instance state|<p>Failed to get the instance state. <br>Check access permissions to GCP API or service account.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.inst.state) = 10`|Average||
|GCP MSSQL: Database engine is down|<p>Database engine is down.<br>If an instance experiences unplanned (non-maintenance) downtime, the instance state will still be RUNNING, but the database engine state metric will report 0.</p>|`last(/GCP Cloud SQL MSSQL by HTTP/gcp.cloudsql.mssql.db.state)=0`|Average|**Depends on**:<br><ul><li>GCP MSSQL: Instance is stopped by the owner</li><li>GCP MSSQL: Instance is in suspended state</li><li>GCP MSSQL: Instance is in maintenance</li><li>GCP MSSQL: Instance is in failed state</li><li>GCP MSSQL: Instance is in unknown state</li><li>GCP MSSQL: Failed to get the instance state</li></ul>|

### LLD rule Resources discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Resources discovery|<p>Resources discovery.</p>|Dependent item|gcp.cloudsql.resources.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Resources discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Resource [{#RESOURCE.NAME}]: Raw data|<p>Data in raw format for the [{#RESOURCE.NAME}] resource.</p>|Dependent item|gcp.cloudsql.mssql.resource.raw[{#RESOURCE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.resource == "{#RESOURCE.NAME}")].metrics.first()`</p></li></ul>|
|Resource [{#RESOURCE.NAME}]: Deadlocks|<p>Total number of lock requests that resulted in a deadlock for the [{#RESOURCE.NAME}] resource.</p>|Dependent item|gcp.cloudsql.mssql.resource.deadlock_count[{#RESOURCE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlock_count`</p></li></ul>|
|Resource [{#RESOURCE.NAME}]: Lock waits|<p>Total number of lock requests that required the caller to wait for the [{#RESOURCE.NAME}] resource.</p>|Dependent item|gcp.cloudsql.mssql.resource.lock_wait_count[{#RESOURCE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lock_wait_count`</p></li></ul>|
|Resource [{#RESOURCE.NAME}]: Lock wait time|<p>Total time lock requests were waiting for locks for the [{#RESOURCE.NAME}] resource.</p>|Dependent item|gcp.cloudsql.mssql.resource.lock_wait_time[{#RESOURCE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lock_wait_time`</p></li></ul>|

### LLD rule Databases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Databases discovery|<p>Databases discovery.</p>|Dependent item|gcp.cloudsql.db.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Databases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database [{#DB.NAME}]: Raw data|<p>Data in raw format for the [{#DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.mssql.db.raw[{#DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.database == "{#DB.NAME}")].metrics.first()`</p></li></ul>|
|Database [{#DB.NAME}]: Log bytes flushed|<p>Total number of log bytes flushed for the [{#DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.mssql.db.log_bytes_flushed_count[{#DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_bytes_flushed_count`</p></li></ul>|
|Database [{#DB.NAME}]: Transactions started|<p>Total number of transactions started for the [{#DB.NAME}] database.</p>|Dependent item|gcp.cloudsql.mssql.db.transaction_count[{#DB.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.transaction_count`</p></li></ul>|

### LLD rule Schedulers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Schedulers discovery|<p>Schedulers discovery.</p>|Dependent item|gcp.cloudsql.schedulers.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Schedulers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Scheduler [{#SCHEDULER.ID}]: Raw data|<p>Data in raw format associated with the scheduler that goes by its ID [{#SCHEDULER.ID}].</p>|Dependent item|gcp.cloudsql.mssql.scheduler.raw[{#SCHEDULER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.scheduler == "{#SCHEDULER.ID}")].metrics.first()`</p></li></ul>|
|Scheduler [{#SCHEDULER.ID}]: Active workers|<p>Current number of active workers associated with the scheduler that goes by its ID [{#SCHEDULER.ID}].</p><p>An active worker is never preemptive, must have an associated task, and is either running, runnable, or suspended.</p>|Dependent item|gcp.cloudsql.mssql.scheduler.active_workers[{#SCHEDULER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_workers`</p></li></ul>|
|Scheduler [{#SCHEDULER.ID}]: Current tasks|<p>Current number of present tasks associated with the scheduler that goes by its ID [{#SCHEDULER.ID}].</p><p>This count includes tasks that are waiting for a worker to execute them and tasks that are currently waiting or running (in SUSPENDED or RUNNABLE state).</p>|Dependent item|gcp.cloudsql.mssql.scheduler.current_tasks[{#SCHEDULER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.current_tasks`</p></li></ul>|
|Scheduler [{#SCHEDULER.ID}]: Current workers|<p>Current number of workers associated with the scheduler that goes by its ID [{#SCHEDULER.ID}].</p><p>It includes workers that are not assigned any task.</p>|Dependent item|gcp.cloudsql.mssql.scheduler.current_workers[{#SCHEDULER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.current_workers`</p></li></ul>|
|Scheduler [{#SCHEDULER.ID}]: Pending I/O operations|<p>Current number of pending I/Os waiting to be completed that are associated with the scheduler that goes by its ID [{#SCHEDULER.ID}].</p><p>Each scheduler has a list of pending I/Os that are checked to determine whether they have been completed every time there is a context switch.</p><p>The count is incremented when the request is inserted.</p><p>This count is decremented when the request is completed.</p><p>This number does not indicate the state of the I/Os.</p>|Dependent item|gcp.cloudsql.mssql.scheduler.pending_disk_io[{#SCHEDULER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pending_disk_io`</p></li></ul>|
|Scheduler [{#SCHEDULER.ID}]: Runnable tasks|<p>Current number of workers that are associated with the scheduler that goes by its ID [{#SCHEDULER.ID}] and have assigned tasks waiting to be scheduled on the runnable queue.</p>|Dependent item|gcp.cloudsql.mssql.scheduler.runnable_tasks[{#SCHEDULER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.runnable_tasks`</p></li></ul>|
|Scheduler [{#SCHEDULER.ID}]: Work queue|<p>Current number of tasks in the pending queue associated with the scheduler that goes by its ID [{#SCHEDULER.ID}].</p><p>These tasks are waiting for a worker to pick them up.</p>|Dependent item|gcp.cloudsql.mssql.scheduler.work_queue[{#SCHEDULER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.work_queue`</p></li></ul>|

# GCP Cloud SQL MSSQL Replica by HTTP

## Overview

This template is designed to monitor Google Cloud Platform Cloud SQL MSSQL read-only replica instances by Zabbix.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GCP Cloud SQL MSSQL read replicas versions: 2019 Standard/Enterprise, 2017 Standard/Enterprise

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template will be automatically connected to discovered entities with all their required parameters pre-defined.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GCP.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$GCP.TIME.WINDOW}|<p>Time interval for the data requests.</p><p>Supported usage type:</p><p>1. The default update interval for most of the items.</p><p>2. The minimal time window for the data requested in the Monitoring Query Language REST API request.</p>|`5m`|
|{$GCP.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replica metrics get|<p>MSSQL replica metrics data in raw format.</p>|Script|gcp.cloudsql.mssql.repl.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Bytes sent to replica|<p>Total number of bytes sent to the remote availability replica.</p><p>For an async replica, returns the number of bytes before compression.</p><p>For a sync replica without compression, returns the actual number of bytes.</p>|Dependent item|gcp.cloudsql.mssql.repl.bytes_sent_to_replica_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes_sent_to_replica_count`</p></li></ul>|
|Resent messages|<p>Total count of Always On messages to resend.</p><p>This includes messages that were attempted to be sent but failed and require resending.</p>|Dependent item|gcp.cloudsql.mssql.repl.resent_message_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resent_message_count`</p></li></ul>|
|Log apply pending queue|<p>Current number of log blocks that are waiting to be applied to replica.</p>|Dependent item|gcp.cloudsql.mssql.repl.log_apply_pending_queue<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_apply_pending_queue`</p></li></ul>|
|Log bytes received|<p>Total size of log records received by the replica.</p>|Dependent item|gcp.cloudsql.mssql.repl.log_bytes_received_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_bytes_received_count`</p></li></ul>|
|Recovery queue|<p>Current size of log records in bytes in the replica's log files that have not been redone.</p>|Dependent item|gcp.cloudsql.mssql.repl.recovery_queue<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.recovery_queue`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Redone bytes|<p>Total size in bytes of redone log records.</p>|Dependent item|gcp.cloudsql.mssql.repl.redone_bytes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redone_bytes_count`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

