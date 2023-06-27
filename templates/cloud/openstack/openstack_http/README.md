
# OpenStack by HTTP

## Overview

This template is designed for the effortless deployment of OpenStack monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- OpenStack release Yoga:

  * Identity API v3

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

OpenStack template documentation


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KEYSTONE.API.ENDPOINT}|<p>API endpoint for Identity Service, e.g., https://local.openstack:5000.</p>||
|{$APP.CRED.ID}|<p>Application credential ID for monitoring user access.</p>||
|{$APP.CRED.SECRET}|<p>Application credential password for monitoring user access.</p>||
|{$AUTH.INTERVAL}|<p>Interval in minutes, in which API token will be regenerated. By default, OpenStack API tokens expire after 60m.</p>|`50m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenStack: Get access token and service catalog|<p>Authorizes user on the OpenStack Identity service and gets the service catalog.</p>|Script|openstack.identity.auth|

### LLD rule OpenStack: Nova discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenStack: Nova discovery|<p>Discovers OpenStack services from monitoring user's services catalog.</p>|Dependent item|openstack.services.nova.discovery|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

