
# OpenStack Nova by HTTP

## Overview

This template is designed for the effortless deployment of OpenStack Nova monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- OpenStack release Yoga:

  * Compute API v2.1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

OpenStack template documentation


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SERVICE.URL}|<p>API endpoint for Nova Service, e.g., https://local.openstack:8774/v2.1.</p>||
|{$TOKEN}|<p>API token for the monitoring user.</p>||
|{$INTERVAL.LIMITS}|<p>Interval for absolute limit HTTP agent item query.</p>|`3m`|
|{$INTERVAL.SERVERS}|<p>Interval for server HTTP agent item queries.</p>|`3m`|
|{$INTERVAL.SERVICES}|<p>Interval for service HTTP agent item query.</p>|`3m`|
|{$INTERVAL.HYPERVISOR}|<p>Interval for hypervisor HTTP agent item query.</p>|`3m`|
|{$INTERVAL.AVAILABILITY_ZONE}|<p>Interval for availability zone HTTP agent item query.</p>|`3m`|
|{$INTERVAL.TENANTS}|<p>Interval for tenant HTTP agent item query.</p>|`3m`|
|{$PROJECT.INSTANCES.TRIGGER.WARNING}|<p>Sets the percentage threshold for creating a warning severity event about instances resource count.</p>|`75`|
|{$PROJECT.INSTANCES.TRIGGER.HIGH}|<p>Sets the percentage threshold for creating a high severity event about instances resource count.</p>|`90`|
|{$PROJECT.CPU.TRIGGER.WARNING}|<p>Sets the percentage threshold for creating a warning severity event about vCPU resource usage.</p>|`75`|
|{$PROJECT.CPU.TRIGGER.HIGH}|<p>Sets the percentage threshold for creating a high severity event about vCPU resource usage.</p>|`90`|
|{$PROJECT.RAM.TRIGGER.WARNING}|<p>Sets the percentage threshold for creating a warning severity event about RAM resource usage.</p>|`75`|
|{$PROJECT.RAM.TRIGGER.HIGH}|<p>Sets the percentage threshold for creating a high severity event about RAM resource usage.</p>|`90`|
|{$SERVER.DISCOVERY.SERVER.NAME.MATCHES}|<p>Sets the server name regex filter to use in server discovery for including.</p>|`.*`|
|{$SERVER.DISCOVERY.SERVER.NAME.NOT.MATCHES}|<p>Sets the server name regex filter to use in server discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$SERVICES.DISCOVERY.HOST.MATCHES}|<p>Sets the host name regex filter to use in Compute services discovery for including.</p>|`.*`|
|{$SERVICES.DISCOVERY.HOST.NOT.MATCHES}|<p>Sets the host name regex filter to use in Compute services discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$SERVICES.DISCOVERY.BINARY.MATCHES}|<p>Sets the binary name regex filter to use in Compute services discovery for including.</p>|`.*`|
|{$SERVICES.DISCOVERY.BINARY.NOT.MATCHES}|<p>Sets the binary name regex filter to use in Compute services discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERVISOR.DISCOVERY.HOSTNAME.MATCHES}|<p>Sets the hostname regex filter to use in hypervisor discovery for including.</p>|`.*`|
|{$HYPERVISOR.DISCOVERY.HOSTNAME.NOT.MATCHES}|<p>Sets the hostname regex filter to use in hypervisor discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERVISOR.DISCOVERY.TYPE.MATCHES}|<p>Sets the type regex filter to use in hypervisor discovery for including.</p>|`.*`|
|{$HYPERVISOR.DISCOVERY.TYPE.NOT.MATCHES}|<p>Sets the type regex filter to use in hypervisor discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERVISOR.DISCOVERY.IP.MATCHES}|<p>Sets the host IP address regex filter to use in hypervisor discovery for including.</p>|`.*`|
|{$HYPERVISOR.DISCOVERY.IP.NOT.MATCHES}|<p>Sets the host IP address regex filter to use in hypervisor discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$AVAILABILITY_ZONE.DISCOVERY.NAME.MATCHES}|<p>Sets the zone name regex filter to use in availability zone discovery for including.</p>|`.*`|
|{$AVAILABILITY_ZONE.DISCOVERY.NAME.NOT.MATCHES}|<p>Sets the zone name regex filter to use in availability zone discovery for excluding.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Get absolute limits|<p>Gets absolute limits for the project.</p>|HTTP agent|openstack.nova.limits.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.limits.absolute`</p><p>⛔️Custom on fail: Set error to: `Could not get absolute project limits`</p></li></ul>|
|Nova: Get servers|<p>Gets a list of servers.</p>|HTTP agent|openstack.nova.servers.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.servers`</p><p>⛔️Custom on fail: Set error to: `Could not get servers list`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nova: Get compute services|<p>Gets a list of compute services and it's data.</p>|HTTP agent|openstack.nova.services.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.services`</p><p>⛔️Custom on fail: Set error to: `Could not get compute services list`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nova: Get hypervisors|<p>Gets a list of hypervisors and it's data.</p>|HTTP agent|openstack.nova.hypervisors.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hypervisors`</p><p>⛔️Custom on fail: Set error to: `Could not get hypervisors list`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nova: Get availability zones|<p>Gets a list of availability zones and it's data.</p>|HTTP agent|openstack.nova.availability_zone.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.availabilityZoneInfo`</p><p>⛔️Custom on fail: Set error to: `Could not get availability zones list`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nova: Get tenants|<p>Gets a list of tenants and it's data.</p>|HTTP agent|openstack.nova.tenant.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tenant_usages`</p><p>⛔️Custom on fail: Set error to: `Could not get tenant list`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nova: Instances count, current|<p>Gets the current instances count.</p>|Dependent item|openstack.nova.limits.instances.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalInstancesUsed`</p></li></ul>|
|Nova: Instances count, max|<p>Gets the maximum instance count.</p>|Dependent item|openstack.nova.limits.instances.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxTotalInstances`</p></li></ul>|
|Nova: Instances count, free|<p>Gets the free instances count.</p>|Calculated|openstack.nova.limits.instances.free|
|Nova: vCPUs usage, current|<p>Gets the current vCPUs count.</p>|Dependent item|openstack.nova.limits.vcpu.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalCoresUsed`</p></li></ul>|
|Nova: vCPUs usage, max|<p>Gets the maximum vCPUs count.</p>|Dependent item|openstack.nova.limits.vcpu.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxTotalCores`</p></li></ul>|
|Nova: vCPUs usage, free|<p>Gets the free vCPUs count.</p>|Calculated|openstack.nova.limits.vcpu.free|
|Nova: RAM usage, current|<p>Gets the current instances count.</p>|Dependent item|openstack.nova.limits.ram.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalRAMUsed`</p></li><li><p>Custom multiplier: `1000000`</p></li></ul>|
|Nova: RAM usage, max|<p>Gets the maximum instance count.</p>|Dependent item|openstack.nova.limits.ram.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxTotalRAMSize`</p></li><li><p>Custom multiplier: `1000000`</p></li></ul>|
|Nova: RAM usage, free|<p>Gets the free RAM.</p>|Calculated|openstack.nova.limits.ram.free|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nova: Current instances count exceeded {$PROJECT.INSTANCES.TRIGGER.HIGH}% of max available|<p>Current instances count has exceeded {$PROJECT.INSTANCES.TRIGGER.HIGH}% of the max available instances count.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.current) >= ({$PROJECT.INSTANCES.TRIGGER.HIGH} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.max))`|High||
|Nova: Current instances count exceeded {$PROJECT.INSTANCES.TRIGGER.WARNING}% of max available|<p>Current instances count has exceeded {$PROJECT.INSTANCES.TRIGGER.WARNING}% of the max available instances count.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.current,#1) >= ({$PROJECT.INSTANCES.TRIGGER.WARNING} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.max,#1))`|Warning|**Depends on**:<br><ul><li>Nova: Current instances count exceeded {$PROJECT.INSTANCES.TRIGGER.HIGH}% of max available</li></ul>|
|Nova: Current vCPU usage exceeded {$PROJECT.CPU.TRIGGER.HIGH}% of max available|<p>Current vCPU usage has exceeded {$PROJECT.CPU.TRIGGER.HIGH}% of the max available vCPU usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.current) >= ({$PROJECT.CPU.TRIGGER.HIGH} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.max))`|High||
|Nova: Current vCPU usage exceeded {$PROJECT.CPU.TRIGGER.WARNING}% of max available|<p>Current vCPU usage has exceeded {$PROJECT.CPU.TRIGGER.WARNING}% of the max available vCPU usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.current) >= ({$PROJECT.CPU.TRIGGER.WARNING} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.max))`|Warning|**Depends on**:<br><ul><li>Nova: Current vCPU usage exceeded {$PROJECT.CPU.TRIGGER.HIGH}% of max available</li></ul>|
|Nova: Current RAM usage exceeded {$PROJECT.RAM.TRIGGER.HIGH}% of max available|<p>Current RAM usage has exceeded {$PROJECT.RAM.TRIGGER.HIGH}% of the max available RAM usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.current) >= ({$PROJECT.RAM.TRIGGER.HIGH} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.max))`|High||
|Nova: Current RAM usage exceeded {$PROJECT.RAM.TRIGGER.WARNING}% of max available|<p>Current RAM usage has exceeded {$PROJECT.RAM.TRIGGER.WARNING}% of the max available RAM usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.current) >= ({$PROJECT.RAM.TRIGGER.WARNING} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.max))`|Warning|**Depends on**:<br><ul><li>Nova: Current RAM usage exceeded {$PROJECT.RAM.TRIGGER.HIGH}% of max available</li></ul>|

### LLD rule Nova: Servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Servers discovery|<p>Discovers OpenStack Nova servers.</p>|Dependent item|openstack.nova.servers.discovery|

### Item prototypes for Nova: Servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Raw data|<p>Gets a detailed report of server.</p>|HTTP agent|openstack.nova.servers.details.get[{#SERVER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.server`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed server report`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status|<p>Gets status of the server.</p>|Dependent item|openstack.nova.servers.status[{#SERVER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set error to: `Could not get server status from master item`</p></li></ul>|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Flavor|<p>Gets flavor of the server.</p>|Dependent item|openstack.nova.servers.flavor[{#SERVER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flavor.id`</p><p>⛔️Custom on fail: Set error to: `Could not get server flavor from master item`</p></li></ul>|

### Trigger prototypes for Nova: Servers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status is "ERROR"|<p>Server is in "ERROR" status.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.servers.status[{#SERVER_ID}])="ERROR"`|High|**Manual close**: Yes|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status has changed|<p>Status of the server has changed. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.servers.status[{#SERVER_ID}],#1)<>last(/OpenStack Nova by HTTP/openstack.nova.servers.status[{#SERVER_ID}],#2) and length(last(/OpenStack Nova by HTTP/openstack.nova.servers.status[{#SERVER_ID}]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status is "ERROR"</li></ul>|

### LLD rule Nova: Compute services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Compute services discovery|<p>Discovers OpenStack Compute services.</p>|Dependent item|openstack.nova.services.discovery|

### Item prototypes for Nova: Compute services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Compute service [{#HOST}]:[{#BINARY}]: Raw data|<p>Gets raw data of a single Compute service.</p>|Dependent item|openstack.nova.services.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#ID}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li></ul>|
|Compute service [{#HOST}]:[{#BINARY}]: State|<p>Gets state of a Compute service.</p>|Dependent item|openstack.nova.services.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li></ul>|
|Compute service [{#HOST}]:[{#BINARY}]: Status|<p>Gets status of a Compute service.</p>|Dependent item|openstack.nova.services.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li></ul>|
|Compute service [{#HOST}]:[{#BINARY}]: Disabled reason|<p>Gets disabled reason of a Compute service.</p>|Dependent item|openstack.nova.services.disabled.reason[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disabled_reason`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li></ul>|

### Trigger prototypes for Nova: Compute services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Compute service [{#HOST}]:[{#BINARY}]: State is "down"|<p>State of the service is "down".</p>|`last(/OpenStack Nova by HTTP/openstack.nova.services.state[{#ID}])="down"`|Warning|**Manual close**: Yes|
|Compute service [{#HOST}]:[{#BINARY}]: Status is "disabled"|<p>Status of the server is disabled. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.services.status[{#ID}])="disabled" and length(last(/OpenStack Nova by HTTP/openstack.nova.services.disabled.reason[{#ID}]))>0`|Info|**Manual close**: Yes|

### LLD rule Nova: Hypervisor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Hypervisor discovery|<p>Discovers OpenStack Nova hypervisors.</p>|Dependent item|openstack.nova.hypervisors.discovery|

### Item prototypes for Nova: Hypervisor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Raw data|<p>Gets raw data of a hypervisor.</p>|Dependent item|openstack.nova.hypervisors.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#ID}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li></ul>|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: State|<p>Gets state of a hypervisor.</p>|Dependent item|openstack.nova.hypervisors.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li></ul>|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Status|<p>Gets status of a hypervisor.</p>|Dependent item|openstack.nova.hypervisors.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li></ul>|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Version|<p>Gets version of a hypervisor.</p>|Dependent item|openstack.nova.hypervisors.version[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hypervisor_version`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li></ul>|

### Trigger prototypes for Nova: Hypervisor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: State is "down"|<p>State of the hypervisor is "down".</p>|`last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.state[{#ID}])="down"`|Warning|**Manual close**: Yes|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Status is "disabled"|<p>Status of the hypervisor is disabled. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.status[{#ID}])="disabled"`|Info|**Manual close**: Yes|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Version has changed|<p>Status of the hypervisor is disabled. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.version[{#ID}],#1)<>last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.version[{#ID}],#2) and length(last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.version[{#ID}]))>0`|Info|**Manual close**: Yes|

### LLD rule Nova: Availability zones discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Availability zones discovery|<p>Discovers OpenStack Nova availability zones.</p>|Dependent item|openstack.nova.availability_zone.discovery|

### Item prototypes for Nova: Availability zones discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Availability zone [{#ZONE_NAME}]: Raw data|<p>Gets raw data of an availability zone.</p>|Dependent item|openstack.nova.availability_zone.raw[{#ZONE_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.zoneName == "{#ZONE_NAME}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed availability zone report`</p></li></ul>|
|Availability zone [{#ZONE_NAME}]: State|<p>Gets state of an availability zone.</p>|Dependent item|openstack.nova.availability_zone.state[{#ZONE_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.zoneState.available`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed availability zone report`</p></li><li>Boolean to decimal</li></ul>|

### Trigger prototypes for Nova: Availability zones discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Availability zone [{#ZONE_NAME}]: Zone is unavailable|<p>Availability zone is unavailable. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.availability_zone.state[{#ZONE_NAME}])=0`|Warning|**Manual close**: Yes|

### LLD rule Nova: Tenant discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Tenant discovery|<p>Usage statistics for all tenants.</p>|Dependent item|openstack.nova.tenant.discovery|

### Item prototypes for Nova: Tenant discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tenant [{#TENANT_ID}]: Raw data|<p>Gets raw data of tenant.</p>|Dependent item|openstack.nova.tenant.raw[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.tenant_id == "{#TENANT_ID}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the tenant report`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total hours|<p>The total duration that servers exist (in hours).</p>|Dependent item|openstack.nova.tenant.total_hours[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_hours`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total vCPUs usage|<p>Multiplying the number of virtual CPUs of the server by hours the server exists, and then adding that all together for each server.</p>|Dependent item|openstack.nova.tenant.total_vcpu[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_vcpus_usage`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total disk usage|<p>Multiplying the server disk size (in GiB) by hours the server exists, and then adding that all together for each server.</p>|Dependent item|openstack.nova.tenant.disk_usage[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_local_gb_usage`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total memory usage|<p>Multiplying the server memory size (in MiB) by hours the server exists, and then adding that all together for each server.</p>|Dependent item|openstack.nova.tenant.total_memory_mb_usage[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_memory_mb_usage`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

