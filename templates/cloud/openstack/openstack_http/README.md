
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

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

