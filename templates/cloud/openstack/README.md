
# OpenStack by HTTP

## Overview

This template is designed for the effortless deployment of OpenStack monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- OpenStack Yoga release and OpenStack built from sources (27568ea3):

  * Identity API v3
  * Compute API v2.1 (for OpenStack Nova by HTTP template)

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This is a master template that needs to be assigned to a host, and it will discover all OpenStack services supported by Zabbix automatically.

Before using this template it is recommended to create a separate monitoring user on OpenStack that will have access to specific API resources. Zabbix uses OpenStack application credentials for authorization, as it is a more secure method than a username and password-based authentication.

Below are instructions and examples on how to set up a user on OpenStack that will be used by Zabbix. Examples use the OpenStack CLI (command-line interface) tool, but this can also be done from OpenStack Horizon (web interface).

> **If using the CLI tool, make sure you have the OpenStack RC file for your project with a user that has rights to create other users, roles, etc., and source it**, for example, `. zabbix-admin-openrc.sh`.
>
> The OpenStack RC file can be obtained from Horizon.

The project that needs to be monitored is assumed to be already present in OpenStack. In the following examples, a project named `zabbix` is used:

```
# openstack project list
+----------------------------------+--------------------+
| ID                               | Name               |
+----------------------------------+--------------------+
| 28d6bb25d62b4e7e8c2d59ce056a0334 | service            |
| 4688a19e02324c42a34220e9b6a2407e | admin              |
| bc78db4bb2044148a0abf90be512fa12 | zabbix             |
+----------------------------------+--------------------+
```

1. After the project name is noted, a monitoring user needs to be created. This can be done by executing an `openstack user create` command:

```
# openstack user create --project zabbix --password-prompt zabbix-monitoring
User Password:
Repeat User Password:
+---------------------+----------------------------------+
| Field               | Value                            |
+---------------------+----------------------------------+
| default_project_id  | bc78db4bb2044148a0abf90be512fa12 |
| domain_id           | default                          |
| enabled             | True                             |
| id                  | abd3eda9a29244568b1801e4825b6d71 |
| name                | zabbix-monitoring                |
| options             | {}                               |
| password_expires_at | None                             |
+---------------------+----------------------------------+
```

2. When the monitoring user is created, it needs to be assigned a role. But first, a monitoring-specific role needs to be created:

```
# openstack role create --description "A role for Zabbix monitoring user" monitoring
+-------------+-----------------------------------+
| Field       | Value                             |
+-------------+-----------------------------------+
| description | A role for Zabbix monitoring user |
| domain_id   | None                              |
| id          | 93577a7f13184cf7af76f7bdecf7f6ee  |
| name        | monitoring                        |
| options     | {}                                |
+-------------+-----------------------------------+
```

3. Then assign this newly created role to the monitoring user created in Step 1:
```
# openstack role add --user zabbix-monitoring --project zabbix monitoring
```

4. Verify that the role has been assigned correctly. There should be one role only:

```
# openstack role assignment list --user zabbix-monitoring --project zabbix --names
+------------+---------------------------+-------+----------------+--------+--------+-----------+
| Role       | User                      | Group | Project        | Domain | System | Inherited |
+------------+---------------------------+-------+----------------+--------+--------+-----------+
| monitoring | zabbix-monitoring@Default |       | zabbix@Default |        |        | False     |
+------------+---------------------------+-------+----------------+--------+--------+-----------+
```

5. Get the OpenStack RC file for the monitoring user in this project, source it, and generate application credentials:
```
# openstack application credential create --description "Application credential for Zabbix monitoring" zabbix-app-cred
  +--------------+----------------------------------------------------------------------------------------+
  | Field        | Value                                                                                  |
  +--------------+----------------------------------------------------------------------------------------+
  | description  | Application credential for Zabbix monitoring                                           |
  | expires_at   | None                                                                                   |
  | id           | c8087b91354249f3b157a50fc5ecfb3c                                                       |
  | name         | zabbix-app-cred                                                                        |
  | project_id   | bc78db4bb2044148a0abf90be512fa12                                                       |
  | roles        | monitoring                                                                             |
  | secret       | E1kC-s8QTWUaIpmexF18GW-FL3TI9-HXoexdExvGsw7uOhb3SEFW1zDa1qTs80Vqn-2xgviIPRuYOCDp2NDVUg |
  | system       | None                                                                                   |
  | unrestricted | False                                                                                  |
  | user_id      | abd3eda9a29244568b1801e4825b6d71                                                       |
  +--------------+----------------------------------------------------------------------------------------+
```

While creating the application credential, it is also possible to define __access rules__ using the `--access-rules` flag, which offers even more fine-grained access to various API endpoints.
This is optional and up to the user to decide if such rules are needed.

Once the application credential is created, the values of `id` and `secret` need to be set as user macro values in Zabbix:

* value of `id` in `{$APP.CRED.ID}` user macro;
* value of `secret` in `{$APP.CRED.SECRET}` user macro.


At this point, the monitoring user will not be able to access any resources on OpenStack, therefore some access rights need to be defined.
Access rights are set using policies. Each service has its own policy file, therefore **further steps for setting up policies, are mentioned in the template documentation of each supported service**, e.g., __OpenStack Nova by HTTP__.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OPENSTACK.KEYSTONE.API.ENDPOINT}|<p>API endpoint for Identity Service, e.g., https://local.openstack:5000.</p>||
|{$OPENSTACK.AUTH.INTERVAL}|<p>API token regeneration interval, in minutes. By default, OpenStack API tokens expire after 60m.</p>|`50m`|
|{$OPENSTACK.HTTP.PROXY}|<p>Sets the HTTP proxy for the authorization item. Host prototypes will also use this value for HTTP proxy. If this parameter is empty, then no proxy is used.</p>||
|{$OPENSTACK.APP.CRED.ID}|<p>Application credential ID for monitoring user access.</p>||
|{$OPENSTACK.APP.CRED.SECRET}|<p>Application credential password for monitoring user access.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenStack: Get access token and service catalog|<p>Authorizes user on the OpenStack Identity service and gets the service catalog.</p>|Script|openstack.identity.auth|

### LLD rule OpenStack: Nova discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenStack: Nova discovery|<p>Discovers OpenStack services from the monitoring user's services catalog.</p>|Dependent item|openstack.services.nova.discovery|

# OpenStack Nova by HTTP

## Overview

This template is designed for the effortless deployment of OpenStack Nova monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- OpenStack Yoga release and OpenStack built from sources (27568ea3):

  * Compute API v2.1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the __OpenStack by HTTP__ template will discover the __Nova__ service automatically and create a host prototype with this template assigned to it.

If needed, you can specify an HTTP proxy for the template to use by changing the value of `{$OPENSTACK.NOVA.HTTP.PROXY}` user macro.

For tenant usage statistics, it is possible to choose a custom time period for which the data will be queried. This can be set with the `{$OPENSTACK.NOVA.TENANT.PERIOD}` macro value.
The value can be one of the following:

* `y` - current year until now;

* `m` - current month until now (default value);

* `w` - current week until now;

* `d` - current day until now;

This template discovers servers (instances) present in the project and monitors their statuses, but, depending on different use cases, most likely it is not necessary to monitor all servers.
To filter which servers to monitor, set the `{$OPENSTACK.SERVER.DISCOVERY.NAME.MATCHES}` and `{$OPENSTACK.SERVER.DISCOVERY.NAME.NOT_MATCHES}` macro values accordingly. This logic also applies to other low-level discovery rules.

**OpenStack configuration**

For the OpenStack monitoring user to be able to access the API resources used in this template, it is needed to configure the policy file for OpenStack Nova.

On the OpenStack server, open the `/etc/nova/policy.json` file in your favorite text editor.

In this file, assign the following target resources to the role that the monitoring user uses:
```
{
  "os_compute_api:servers:index": "role:monitoring",
  "os_compute_api:servers:show": "role:monitoring",
  "os_compute_api:os-services:list": "role:monitoring",
  "os_compute_api:os-hypervisors:list-detail": "role:monitoring",
  "os_compute_api:os-availability-zone:detail": "role:monitoring",
  "os_compute_api:os-simple-tenant-usage:list": "role:monitoring"
}
```

If some role is already assigned to the target, it is possible to add another role with `or`, for example, `role:firstRole or role:monitoring`.

Note that a restart of OpenStack Nova services might be needed for these new changes to be applied.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OPENSTACK.NOVA.SERVICE.URL}|<p>API endpoint for Nova Service, e.g., https://local.openstack:8774/v2.1.</p>||
|{$OPENSTACK.TOKEN}|<p>API token for the monitoring user.</p>||
|{$OPENSTACK.HTTP.PROXY}|<p>Sets the HTTP proxy for script and HTTP agent items. If this parameter is empty, then no proxy is used.</p>||
|{$OPENSTACK.NOVA.TENANT.PERIOD}|<p>Period for which tenant usage statistics will be queried. Possible values are:</p><p> 'y' - current year until now,</p><p> 'm' - current month until now,</p><p> 'w' - current week until now,</p><p> 'd' - current day until now.</p>|`m`|
|{$OPENSTACK.NOVA.INTERVAL.LIMITS}|<p>Interval for absolute limit HTTP agent item query.</p>|`3m`|
|{$OPENSTACK.NOVA.INTERVAL.SERVERS}|<p>Interval for server HTTP agent item queries.</p>|`3m`|
|{$OPENSTACK.NOVA.INTERVAL.SERVICES}|<p>Interval for service HTTP agent item query.</p>|`3m`|
|{$OPENSTACK.NOVA.INTERVAL.HYPERVISOR}|<p>Interval for hypervisor HTTP agent item query.</p>|`3m`|
|{$OPENSTACK.NOVA.INTERVAL.AVAILABILITY_ZONE}|<p>Interval for availability zone HTTP agent item query.</p>|`3m`|
|{$OPENSTACK.NOVA.INTERVAL.TENANTS}|<p>Interval for tenant HTTP agent item query.</p>|`3m`|
|{$OPENSTACK.NOVA.INSTANCES.UTIL.WARN}|<p>Sets the percentage threshold for creating a warning severity event about instances resource count.</p>|`75`|
|{$OPENSTACK.NOVA.INSTANCES.UTIL.HIGH}|<p>Sets the percentage threshold for creating a high severity event about instances resource count.</p>|`90`|
|{$OPENSTACK.NOVA.CPU.UTIL.WARN}|<p>Sets the percentage threshold for creating a warning severity event about vCPU resource usage.</p>|`75`|
|{$OPENSTACK.NOVA.CPU.UTIL.HIGH}|<p>Sets the percentage threshold for creating a high severity event about vCPU resource usage.</p>|`90`|
|{$OPENSTACK.NOVA.RAM.UTIL.WARN}|<p>Sets the percentage threshold for creating a warning severity event about RAM resource usage.</p>|`75`|
|{$OPENSTACK.NOVA.RAM.UTIL.HIGH}|<p>Sets the percentage threshold for creating a high severity event about RAM resource usage.</p>|`90`|
|{$OPENSTACK.SERVER.DISCOVERY.NAME.MATCHES}|<p>Sets the server name regex filter to use in server discovery for including.</p>|`.*`|
|{$OPENSTACK.SERVER.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the server name regex filter to use in server discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSTACK.SERVICES.DISCOVERY.HOST.MATCHES}|<p>Sets the host name regex filter to use in compute services discovery for including.</p>|`.*`|
|{$OPENSTACK.SERVICES.DISCOVERY.HOST.NOT_MATCHES}|<p>Sets the host name regex filter to use in compute services discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSTACK.SERVICES.DISCOVERY.BINARY.MATCHES}|<p>Sets the binary name regex filter to use in compute services discovery for including.</p>|`.*`|
|{$OPENSTACK.SERVICES.DISCOVERY.BINARY.NOT_MATCHES}|<p>Sets the binary name regex filter to use in compute services discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSTACK.HYPERVISOR.DISCOVERY.HOSTNAME.MATCHES}|<p>Sets the hostname regex filter to use in hypervisor discovery for including.</p>|`.*`|
|{$OPENSTACK.HYPERVISOR.DISCOVERY.HOSTNAME.NOT_MATCHES}|<p>Sets the hostname regex filter to use in hypervisor discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSTACK.HYPERVISOR.DISCOVERY.TYPE.MATCHES}|<p>Sets the type regex filter to use in hypervisor discovery for including.</p>|`.*`|
|{$OPENSTACK.HYPERVISOR.DISCOVERY.TYPE.NOT_MATCHES}|<p>Sets the type regex filter to use in hypervisor discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSTACK.HYPERVISOR.DISCOVERY.IP.MATCHES}|<p>Sets the host IP address regex filter to use in hypervisor discovery for including.</p>|`.*`|
|{$OPENSTACK.HYPERVISOR.DISCOVERY.IP.NOT_MATCHES}|<p>Sets the host IP address regex filter to use in hypervisor discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSTACK.AVAILABILITY_ZONE.DISCOVERY.NAME.MATCHES}|<p>Sets the zone name regex filter to use in availability zone discovery for including.</p>|`.*`|
|{$OPENSTACK.AVAILABILITY_ZONE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the zone name regex filter to use in availability zone discovery for excluding.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Get absolute limits|<p>Gets absolute limits for the project.</p>|HTTP agent|openstack.nova.limits.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.limits.absolute`</p><p>⛔️Custom on fail: Set error to: `Could not get absolute project limits`</p></li></ul>|
|Nova: Get servers|<p>Gets a list of servers.</p>|HTTP agent|openstack.nova.servers.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.servers`</p><p>⛔️Custom on fail: Set error to: `Could not get servers list`</p></li></ul>|
|Nova: Get compute services|<p>Gets a list of compute services and its data.</p>|HTTP agent|openstack.nova.services.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.services`</p><p>⛔️Custom on fail: Set error to: `Could not get compute services list`</p></li></ul>|
|Nova: Get hypervisors|<p>Gets a list of hypervisors and its data.</p>|HTTP agent|openstack.nova.hypervisors.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hypervisors`</p><p>⛔️Custom on fail: Set error to: `Could not get hypervisors list`</p></li></ul>|
|Nova: Get availability zones|<p>Gets a list of availability zones and its data.</p>|HTTP agent|openstack.nova.availability_zone.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.availabilityZoneInfo`</p><p>⛔️Custom on fail: Set error to: `Could not get availability zones list`</p></li></ul>|
|Nova: Get tenants|<p>Gets a list of tenants and its data.</p>|Script|openstack.nova.tenant.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tenant_usages`</p><p>⛔️Custom on fail: Set error to: `Could not get tenant list`</p></li></ul>|
|Nova: Instances count, current|<p>Number of servers in each tenant.</p>|Dependent item|openstack.nova.limits.instances.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalInstancesUsed`</p></li></ul>|
|Nova: Instances count, max|<p>Number of allowed servers for each tenant.</p>|Dependent item|openstack.nova.limits.instances.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxTotalInstances`</p></li></ul>|
|Nova: Instances count, free|<p>Number of available servers for each tenant.</p>|Calculated|openstack.nova.limits.instances.free<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nova: vCPUs usage, current|<p>Number of used server cores in each tenant.</p>|Dependent item|openstack.nova.limits.vcpu.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalCoresUsed`</p></li></ul>|
|Nova: vCPUs usage, max|<p>Number of allowed server cores for each tenant.</p>|Dependent item|openstack.nova.limits.vcpu.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxTotalCores`</p></li></ul>|
|Nova: vCPUs usage, free|<p>Number of available server cores for each tenant.</p>|Calculated|openstack.nova.limits.vcpu.free<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nova: RAM usage, current|<p>Amount of used server RAM.</p>|Dependent item|openstack.nova.limits.ram.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalRAMUsed`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Nova: RAM usage, max|<p>Amount of allowed server RAM.</p>|Dependent item|openstack.nova.limits.ram.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxTotalRAMSize`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Nova: RAM usage, free|<p>Amount of available server RAM.</p>|Calculated|openstack.nova.limits.ram.free<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nova: Current instances count is too high|<p>Current instances count has exceeded {$OPENSTACK.NOVA.INSTANCES.UTIL.HIGH}% of the max available instances count.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.current) >= ({$OPENSTACK.NOVA.INSTANCES.UTIL.HIGH} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.max))`|High||
|Nova: Current instances count is high|<p>Current instances count has exceeded {$OPENSTACK.NOVA.INSTANCES.UTIL.WARN}% of the max available instances count.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.current) >= ({$OPENSTACK.NOVA.INSTANCES.UTIL.WARN} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.instances.max))`|Warning|**Depends on**:<br><ul><li>Nova: Current instances count is too high</li></ul>|
|Nova: Current vCPU usage is too high|<p>Current vCPU usage has exceeded {$OPENSTACK.NOVA.CPU.UTIL.HIGH}% of the max available vCPU usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.current) >= ({$OPENSTACK.NOVA.CPU.UTIL.HIGH} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.max))`|High||
|Nova: Current vCPU usage is high|<p>Current vCPU usage has exceeded {$OPENSTACK.NOVA.CPU.UTIL.WARN}% of the max available vCPU usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.current) >= ({$OPENSTACK.NOVA.CPU.UTIL.WARN} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.vcpu.max))`|Warning|**Depends on**:<br><ul><li>Nova: Current vCPU usage is too high</li></ul>|
|Nova: Current RAM usage is too high|<p>Current RAM usage has exceeded {$OPENSTACK.NOVA.RAM.UTIL.HIGH}% of the max available RAM usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.current) >= ({$OPENSTACK.NOVA.RAM.UTIL.HIGH} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.max))`|High||
|Nova: Current RAM usage is high|<p>Current RAM usage has exceeded {$OPENSTACK.NOVA.RAM.UTIL.WARN}% of the max available RAM usage.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.current) >= ({$OPENSTACK.NOVA.RAM.UTIL.WARN} / 100 * last(/OpenStack Nova by HTTP/openstack.nova.limits.ram.max))`|Warning|**Depends on**:<br><ul><li>Nova: Current RAM usage is too high</li></ul>|

### LLD rule Nova: Servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Servers discovery|<p>Discovers OpenStack Nova servers.</p>|Dependent item|openstack.nova.server.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Nova: Servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status|<p>Server status.</p>|HTTP agent|openstack.nova.server.status.get[{#SERVER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.server.status`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed server report`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Nova: Servers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status is "ERROR"|<p>Server is in "ERROR" status.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.server.status.get[{#SERVER_ID}])=5`|High|**Manual close**: Yes|
|Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status has changed|<p>Status of the server has changed. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.server.status.get[{#SERVER_ID}])<>last(/OpenStack Nova by HTTP/openstack.nova.server.status.get[{#SERVER_ID}],#2) and length(last(/OpenStack Nova by HTTP/openstack.nova.server.status.get[{#SERVER_ID}]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Server [{#SERVER_ID}]:[{#SERVER_NAME}]: Status is "ERROR"</li></ul>|

### LLD rule Nova: Compute services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Compute services discovery|<p>Discovers OpenStack compute services.</p>|Dependent item|openstack.nova.services.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Nova: Compute services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Compute service [{#HOST}]:[{#BINARY}]:[{#ID}]: Raw data|<p>Raw data of the service.</p>|Dependent item|openstack.nova.services.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#ID}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li></ul>|
|Compute service [{#HOST}]:[{#BINARY}]:[{#ID}]: State|<p>State of the service.</p>|Dependent item|openstack.nova.services.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Compute service [{#HOST}]:[{#BINARY}]:[{#ID}]: Status|<p>Status of the service.</p>|Dependent item|openstack.nova.services.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Compute service [{#HOST}]:[{#BINARY}]:[{#ID}]: Disabling reason|<p>Reason for disabling a service.</p>|Dependent item|openstack.nova.services.disabled.reason[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disabled_reason`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed services report`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Nova: Compute services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Compute service [{#HOST}]:[{#BINARY}]:[{#ID}]: State is "down"|<p>State of the service is "down".</p>|`last(/OpenStack Nova by HTTP/openstack.nova.services.state[{#ID}])=0`|Warning|**Manual close**: Yes|
|Compute service [{#HOST}]:[{#BINARY}]:[{#ID}]: Status is "disabled"|<p>Status of the server is disabled. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.services.status[{#ID}])=0 and length(last(/OpenStack Nova by HTTP/openstack.nova.services.disabled.reason[{#ID}]))>=0`|Info|**Manual close**: Yes|

### LLD rule Nova: Hypervisor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Hypervisor discovery|<p>Discovers OpenStack Nova hypervisors.</p>|Dependent item|openstack.nova.hypervisors.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Nova: Hypervisor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Raw data|<p>Raw data of the hypervisor.</p>|Dependent item|openstack.nova.hypervisors.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#ID}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li></ul>|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: State|<p>State of the hypervisor.</p>|Dependent item|openstack.nova.hypervisors.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Status|<p>Status of the hypervisor.</p>|Dependent item|openstack.nova.hypervisors.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Version|<p>Hypervisor version.</p>|Dependent item|openstack.nova.hypervisors.version[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hypervisor_version`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed hypervisor report`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Nova: Hypervisor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: State is "down"|<p>State of the hypervisor is "down".</p>|`last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.state[{#ID}])=0`|Warning|**Manual close**: Yes|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Status is "disabled"|<p>Status of the hypervisor is disabled.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.status[{#ID}])=0`|Info|**Manual close**: Yes|
|Hypervisor [{#ID}]:[{#HOSTNAME}]: Version has changed|<p>Version of the hypervisor has changed. Acknowledge to close the problem manually.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.version[{#ID}])<>last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.version[{#ID}],#2) and length(last(/OpenStack Nova by HTTP/openstack.nova.hypervisors.version[{#ID}]))>0`|Info|**Manual close**: Yes|

### LLD rule Nova: Availability zones discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Availability zones discovery|<p>Discovers OpenStack Nova availability zones.</p>|Dependent item|openstack.nova.availability_zone.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Nova: Availability zones discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Availability zone [{#ZONE_NAME}]: Raw data|<p>Raw data of the availability zone.</p>|Dependent item|openstack.nova.availability_zone.raw[{#ZONE_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.zoneName == "{#ZONE_NAME}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed availability zone report`</p></li></ul>|
|Availability zone [{#ZONE_NAME}]: State|<p>Current state of the availability zone.</p>|Dependent item|openstack.nova.availability_zone.state[{#ZONE_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.zoneState.available`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed availability zone report`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability zone [{#ZONE_NAME}]: Host count|<p>Count of hosts and service objects under single availability zone.</p>|Dependent item|openstack.nova.availability_zone.host_count[{#ZONE_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['hosts'].[*].[*].length()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed availability zone report`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Nova: Availability zones discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Availability zone [{#ZONE_NAME}]: Zone is unavailable|<p>Availability zone is not available.</p>|`last(/OpenStack Nova by HTTP/openstack.nova.availability_zone.state[{#ZONE_NAME}])=0`|Warning|**Manual close**: Yes|

### LLD rule Nova: Tenant discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nova: Tenant discovery|<p>Discovers tenants and their usage data.</p>|Dependent item|openstack.nova.tenant.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Nova: Tenant discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tenant [{#TENANT_ID}]: Raw data|<p>Raw data of the tenant.</p>|Dependent item|openstack.nova.tenant.raw[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.tenant_id == "{#TENANT_ID}")].first()`</p><p>⛔️Custom on fail: Set error to: `Could not parse the tenant report`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total hours|<p>Total duration that the servers exist (in hours).</p>|Dependent item|openstack.nova.tenant.total_hours[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_hours`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total vCPUs usage|<p>Total vCPU usage hours for the current tenant (project).</p><p>Multiplying the number of virtual CPUs of the server by hours the server exists, and then adding that all together for each server.</p>|Dependent item|openstack.nova.tenant.total_vcpu[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_vcpus_usage`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total disk usage|<p>Total disk usage hours for the current tenant (project).</p><p>Multiplying the server disk size (in GiB) by hours the server exists, and then adding that all together for each server.</p>|Dependent item|openstack.nova.tenant.disk_usage[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_local_gb_usage`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Tenant [{#TENANT_ID}]: Total memory usage|<p>Total memory usage hours for the current tenant (project).</p><p>Multiplying the server memory size (in MiB) by hours the server exists, and then adding that all together for each server.</p>|Dependent item|openstack.nova.tenant.total_memory_mb_usage[{#TENANT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_memory_mb_usage`</p><p>⛔️Custom on fail: Set error to: `Could not parse the detailed tenant report`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

