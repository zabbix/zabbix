
# Cisco Meraki dashboard by HTTP

## Overview

This template is designed for the effortless deployment of Cisco Meraki dashboard monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Cisco Meraki API 1.24.0 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

You must set {$MERAKI.TOKEN} and {$MERAKI.API.URL} macros. 

Create the token in the Meraki dashboard (see Meraki [documentation](https://developer.cisco.com/meraki/api-latest/#!authorization/authorization) for instructions). Set this token as {$MERAKI.TOKEN} macro value in Zabbix.

Set your Meraki dashboard URL as {$MERAKI.API.URL} macro value in Zabbix (e.g., api.meraki.com/api/v1).

Set filters with macros if you want to override default filter parameters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MERAKI.TOKEN}|<p>Cisco Meraki dashboard API token.</p>||
|{$MERAKI.API.URL}|<p>Cisco Meraki dashboard API URL, e.g., api.meraki.com/api/v1</p>|`api.meraki.com/api/v1`|
|{$MERAKI.ORGANIZATION.NAME.MATCHES}|<p>This macro is used in organizations discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$MERAKI.ORGANIZATION.NAME.NOT_MATCHES}|<p>This macro is used in organizations discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.DEVICE.NAME.MATCHES}|<p>This macro is used in devices discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$MERAKI.DEVICE.NAME.NOT_MATCHES}|<p>This macro is used in devices discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.DEVICE.STATUS.MATCHES}|<p>This macro is used in devices discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.DEVICE.STATUS.NOT_MATCHES}|<p>This macro is used in devices discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See documentation at https://www.zabbix.com/documentation/7.0/manual/config/items/itemtypes/http</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Meraki: Get data|<p>Item for gathering all the organizations and devices from Meraki API.</p>|Script|meraki.get.data|
|Meraki: Data item errors|<p>Item for gathering all the data item errors.</p>|Dependent item|meraki.get.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Meraki: There are errors in 'Get data' metric||`length(last(/Cisco Meraki dashboard by HTTP/meraki.get.data.errors))>0`|Warning||

### LLD rule Organizations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Organizations discovery||Dependent item|meraki.organization.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.organizations`</p></li></ul>|

### LLD rule Devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Devices discovery||Dependent item|meraki.devices.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices`</p></li></ul>|

# Cisco Meraki organization by HTTP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MERAKI.TOKEN}|<p>Cisco Meraki dashboard API token.</p>||
|{$MERAKI.API.URL}|<p>Cisco Meraki dashboard API URL, e.g., api.meraki.com/api/v1</p>|`api.meraki.com/api/v1`|
|{$MERAKI.LICENSE.EXPIRE}|<p>Time in seconds for license to expire.</p>|`86400`|
|{$MERAKI.VPN.LOSS.PERCENTILE}|<p>Average VPN connection loss percentage. Used in the trigger expression</p>|`90`|
|{$MERAKI.CONFIG.CHANGE.TIMESPAN}|<p>Timespan in seconds for gathering configuration change log. Used in the metric configuration and in the URL query.</p>|`1200`|
|{$MERAKI.VPN.STATS.TIMESPAN}|<p>Timespan in seconds for getting organization appliance VPN stats. Used in the metric configuration and in the JavaScript API query. Must be between 1 and 86400 seconds.</p>|`180`|
|{$MERAKI.LICENSE.TYPE.MATCHES}|<p>Filter of discoverable license.</p>|`.*`|
|{$MERAKI.LICENSE.TYPE.NOT_MATCHES}|<p>Filter to exclude discovered license.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.LICENSE.STATE.MATCHES}|<p>Filter of discoverable license.</p>|`.*`|
|{$MERAKI.LICENSE.STATE.NOT_MATCHES}|<p>Filter to exclude discovered license.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.SAML.ORG.ACCESS.MATCHES}|<p>Filter of discoverable SAML role.</p>|`.*`|
|{$MERAKI.SAML.ORG.ACCESS.NOT_MATCHES}|<p>Filter to exclude discovered SAML role.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.SAML.ROLE.MATCHES}|<p>Filter of discoverable SAML role.</p>|`.*`|
|{$MERAKI.SAML.ROLE.NOT_MATCHES}|<p>Filter to exclude discovered SAML role.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.ADMIN.NAME.MATCHES}|<p>Filter of discoverable admins in organization.</p>|`.*`|
|{$MERAKI.ADMIN.NAME.NOT_MATCHES}|<p>Filter to exclude discovered admins in organization.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.ADMIN.ORG.ACCESS.MATCHES}|<p>Filter of discoverable admins in organization.</p>|`.*`|
|{$MERAKI.ADMIN.ORG.ACCESS.NOT_MATCHES}|<p>Filter to exclude discovered admins in organization.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See documentation at https://www.zabbix.com/documentation/7.0/manual/config/items/itemtypes/http</p>||
|{$MERAKI.LLD.UPLINK.NETWORK.NAME.MATCHES}|<p>This macro is used in uplinks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.LLD.UPLINK.NETWORK.NAME.NOT_MATCHES}|<p>This macro is used in uplinks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.LLD.UPLINK.ROLE.MATCHES}|<p>This macro is used in uplinks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.LLD.UPLINK.ROLE.NOT_MATCHES}|<p>This macro is used in uplinks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.LLD.VPN.NETWORK.NAME.MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.LLD.VPN.NETWORK.NAME.NOT_MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.LLD.VPN.PEER.NETWORK.NAME.MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.LLD.VPN.PEER.NETWORK.NAME.NOT_MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.LLD.VPN.SENDER.UPLINK.MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.LLD.VPN.SENDER.UPLINK.NOT_MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MERAKI.LLD.VPN.RECEIVER.UPLINK.MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.LLD.VPN.RECEIVER.UPLINK.NOT_MATCHES}|<p>This macro is used in VPN stats discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Meraki: Get list of the networks|<p>Item for gathering all the networks of organization from Meraki API.</p>|Script|meraki.get.networks|
|Meraki: Networks item errors|<p>Item for gathering all the networks item errors.</p>|Dependent item|meraki.get.networks.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Meraki: Get list of the VPN stats|<p>Item for gathering all the VPN stats of the organization.</p>|Script|meraki.get.vpn.stats|
|Meraki: VPN item errors|<p>Item for gathering all the VPN item errors.</p>|Dependent item|meraki.get.vpn.stats.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Meraki: Get list of configuration changes|<p>Item for viewing the change log for your organization. Gathering once per 20m by default.</p>|HTTP agent|meraki.get.configuration.changes<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `2h`</p></li></ul>|
|Meraki: Get list of adaptive policy aggregate statistics|<p>Item for adaptive policy aggregate statistics for the organization.</p>|HTTP agent|meraki.get.adaptive.policy|
|Meraki: Groups|<p>Meraki adaptive policy groups count.</p>|Dependent item|meraki.policies.groups<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.counts.groups`</p></li></ul>|
|Meraki: Custom ACLs|<p>Meraki adaptive policy custom ACLs count.</p>|Dependent item|meraki.policies.custom.acls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.counts.customAcls`</p></li></ul>|
|Meraki: Policies|<p>Meraki adaptive policies count.</p>|Dependent item|meraki.policies<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.counts.policies`</p></li></ul>|
|Meraki: Allow policies|<p>Meraki adaptive allow policies count.</p>|Dependent item|meraki.policies.allow<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.counts.allowPolicies`</p></li></ul>|
|Meraki: Deny policies|<p>Meraki adaptive deny policies count.</p>|Dependent item|meraki.policies.deny<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.counts.denyPolicies`</p></li></ul>|
|Meraki: Get licenses overview|<p>Return overview of the license state for the organization.</p>|HTTP agent|meraki.get.licenses|
|Meraki: License status|<p>Meraki license status.</p>|Dependent item|meraki.license.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Meraki: License expire|<p>Meraki license expire time, in seconds left.</p>|Dependent item|meraki.license.expire<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expirationDate`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Meraki: Get list licenses|<p>Return list of the licenses for the organization.</p>|Script|meraki.get.list.licenses|
|Meraki: Get SAML SSO|<p>Return the enabled SAML SSO settings for the organization.</p>|HTTP agent|meraki.get.saml<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enabled`</p></li><li>Boolean to decimal</li></ul>|
|Meraki: Get SAML roles|<p>Get list of the SAML roles for this organization.</p>|HTTP agent|meraki.get.saml.roles|
|Meraki: Get admin's account|<p>Get list of the dashboard administrators in this organization.</p>|HTTP agent|meraki.get.admins|
|Meraki: Get login security|<p>Return the login security settings for the organization.</p>|HTTP agent|meraki.get.login.security|
|Meraki: Account lockout attempts|<p>Number of consecutive failed login attempts after which users' accounts will be locked.</p>|Dependent item|meraki.account.lockout.attempts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accountLockoutAttempts`</p></li></ul>|
|Meraki: Idle timeout minutes|<p>Number of minutes users can remain idle before being logged out of their accounts.</p>|Dependent item|meraki.idle.timeout.minutes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idleTimeoutMinutes`</p></li></ul>|
|Meraki: Number of different passwords|<p>Number of recent passwords that new password must be distinct from.</p>|Dependent item|meraki.login.num.different.passwords<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numDifferentPasswords`</p></li></ul>|
|Meraki: Password expiration days|<p>Number of days after which users will be forced to change their password.</p>|Dependent item|meraki.login.password.expiration.days<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.passwordExpirationDays`</p></li></ul>|
|Meraki: Enforce account lockout|<p>Boolean indicating whether users' dashboard accounts will be locked out after a specified number of consecutive failed login attempts.</p>|Dependent item|meraki.login.enforce.account.lockout<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enforceAccountLockout`</p></li><li>Boolean to decimal</li></ul>|
|Meraki: Enforce different passwords|<p>Boolean indicating whether users, when setting a new password, are forced to choose a new password that is different from any past passwords.</p>|Dependent item|meraki.login.enforce.different.passwords<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enforceDifferentPasswords`</p></li><li>Boolean to decimal</li></ul>|
|Meraki: Enforce idle timeout|<p>Boolean indicating whether users will be logged out after being idle for the specified number of minutes.</p>|Dependent item|meraki.login.enforce.idle.timeout<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enforceIdleTimeout`</p></li><li>Boolean to decimal</li></ul>|
|Meraki: Enforce login IP ranges|<p>Boolean indicating whether organization will restrict access to the dashboard (including the API) from certain IP addresses.</p>|Dependent item|meraki.login.enforce.login.ip.ranges<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enforceLoginIpRanges`</p></li><li>Boolean to decimal</li></ul>|
|Meraki: Enforce password expiration|<p>Boolean indicating whether users are forced to change their password every X days.</p>|Dependent item|meraki.login.enforce.password.expiration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enforcePasswordExpiration`</p></li><li>Boolean to decimal</li></ul>|
|Meraki: Enforce 2FA|<p>Boolean indicating whether users in this organization will be required to use an extra verification code when logging in to the dashboard. This code will be sent to their mobile phones via SMS or can be generated by the authenticator application.</p>|Dependent item|meraki.login.enforce.two.factor.auth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enforceTwoFactorAuth`</p></li><li>Boolean to decimal</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Meraki: There are errors in 'Get networks' metric||`length(last(/Cisco Meraki organization by HTTP/meraki.get.networks.errors))>0`|Warning||
|Meraki: There are errors in 'Get VPNs' metric||`length(last(/Cisco Meraki organization by HTTP/meraki.get.vpn.stats.errors))>0`|Warning||
|Meraki: Configuration has been changed||`length(last(/Cisco Meraki organization by HTTP/meraki.get.configuration.changes))>3`|Warning||
|Meraki: License status is not OK||`last(/Cisco Meraki organization by HTTP/meraki.license.status)<>1`|Warning||
|Meraki: License expires in less than {$MERAKI.LICENSE.EXPIRE} seconds||`last(/Cisco Meraki organization by HTTP/meraki.license.expire)<{$MERAKI.LICENSE.EXPIRE} and last(/Cisco Meraki organization by HTTP/meraki.license.expire)>=0`|Warning||

### LLD rule Uplinks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uplinks discovery||Dependent item|meraki.uplinks.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uplinks`</p></li></ul>|

### Item prototypes for Uplinks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uplink [{#INTERFACE}]: [{#UPLINK.ROLE}]: [{#NETWORK.NAME}]: status|<p>Network uplink status.</p>|Dependent item|meraki.uplink.status[{#NETWORK.NAME}, {#INTERFACE}, {#UPLINK.ROLE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Uplink [{#INTERFACE}]: [{#UPLINK.ROLE}]: [{#NETWORK.NAME}]: interface|<p>Network uplink interface.</p>|Dependent item|meraki.uplink.interface[{#NETWORK.NAME}, {#INTERFACE}, {#UPLINK.ROLE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Uplink [{#INTERFACE}]: [{#UPLINK.ROLE}]: [{#NETWORK.NAME}]: public IP|<p>Network uplink public IP.</p>|Dependent item|meraki.uplink.public.ip[{#NETWORK.NAME}, {#INTERFACE}, {#UPLINK.ROLE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Uplinks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Uplink [{#INTERFACE}]: [{#UPLINK.ROLE}]: [{#NETWORK.NAME}]: status is failed||`last(/Cisco Meraki organization by HTTP/meraki.uplink.status[{#NETWORK.NAME}, {#INTERFACE}, {#UPLINK.ROLE}])=0`|Warning||

### LLD rule Networks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Networks discovery||Dependent item|meraki.networks.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.networks`</p></li></ul>|

### Item prototypes for Networks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network [{#NETWORK.NAME}]: time zone|<p>Timezone of the network.</p>|Dependent item|meraki.network.timezone[{#NETWORK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule VPN statuses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN statuses discovery||Dependent item|meraki.vpn.statuses.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vpnStatuses`</p></li></ul>|

### Item prototypes for VPN statuses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN [{#NETWORK.NAME}]: statuses raw|<p>VPN statuses raw.</p>|Dependent item|meraki.vpn.statuses.raw[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|VPN [{#NETWORK.NAME}]: mode|<p>VPN network mode.</p>|Dependent item|meraki.vpn.statuses.mode[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vpnMode`</p></li></ul>|
|VPN [{#NETWORK.NAME}]: peers network name|<p>VPN network name Meraki VPN peers.</p>|Dependent item|meraki.vpn.statuses.peers.network.name[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.merakiVpnPeers..networkName.first()`</p></li></ul>|
|VPN [{#NETWORK.NAME}]: peers network ID|<p>VPN network ID.</p>|Dependent item|meraki.vpn.statuses.peers.network.id[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.merakiVpnPeers..networkId.first()`</p></li></ul>|
|VPN [{#NETWORK.NAME}]: peers network reachability|<p>VPN network Meraki VPN peers reachability.</p>|Dependent item|meraki.vpn.statuses.peers.reachability[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.merakiVpnPeers..reachability.first()`</p></li></ul>|
|VPN [{#NETWORK.NAME}]: third-party peers network name|<p>Return network name of the third-party VPN peers for the organization.</p>|Dependent item|meraki.vpn.statuses.third.party.peers.network.name[{#NETWORK.ID},  {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.thirdPartyVpnPeers..networkName.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VPN [{#NETWORK.NAME}]: third-party peers network ID|<p>Return network ID of the third-party VPN peers for the organization.</p>|Dependent item|meraki.vpn.statuses.third.party.peers.network.id[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.thirdPartyVpnPeers..networkId.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VPN [{#NETWORK.NAME}]: third-party peers network reachability|<p>Return network reachability of the third-party VPN peers for the organization.</p>|Dependent item|meraki.vpn.statuses.third.party.peers.reachability[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.thirdPartyVpnPeers..reachability.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VPN [{#NETWORK.NAME}]: device serial|<p>VPN network device serial.</p>|Dependent item|meraki.vpn.statuses.device.serial[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceSerial`</p></li></ul>|
|VPN [{#NETWORK.NAME}]: device status|<p>VPN network device status.</p>|Dependent item|meraki.vpn.statuses.device.status[{#NETWORK.ID}, {#NETWORK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceStatus`</p></li></ul>|

### LLD rule VPN stats discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN stats discovery||Dependent item|meraki.vpn.stats.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vpnStats`</p></li></ul>|

### Item prototypes for VPN stats discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN [{#NETWORK.NAME}]=>[{#PEER.NETWORK.NAME}]: stats raw|<p>VPN connection stats raw.</p>|Dependent item|meraki.vpn.stat.raw[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: latency avg|<p>VPN connection avg latency.</p>|Dependent item|meraki.vpn.stat.latency.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avgLatencyMs`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: latency min|<p>VPN connection min latency.</p>|Dependent item|meraki.vpn.stat.latency.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.minLatencyMs`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: latency max|<p>VPN connection max latency.</p>|Dependent item|meraki.vpn.stat.latency.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxLatencyMs`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: loss avg, %|<p>VPN connection loss avg.</p>|Dependent item|meraki.vpn.stat.loss.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avgLossPercentage`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: loss min, %|<p>VPN connection loss min.</p>|Dependent item|meraki.vpn.stat.loss.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.minLossPercentage`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: loss max, %|<p>VPN connection loss max.</p>|Dependent item|meraki.vpn.stat.loss.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxLossPercentage`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: jitter avg|<p>VPN connection jitter avg.</p>|Dependent item|meraki.vpn.stat.jitter.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avgJitter`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: jitter min|<p>VPN connection jitter min.</p>|Dependent item|meraki.vpn.stat.jitter.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.minJitter`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: jitter max|<p>VPN connection jitter max.</p>|Dependent item|meraki.vpn.stat.jitter.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxJitter`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: mos avg|<p>VPN connection mos avg.</p>|Dependent item|meraki.vpn.stat.mos.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avgMos`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: mos min|<p>VPN connection mos min.</p>|Dependent item|meraki.vpn.stat.mos.min[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.minMos`</p></li></ul>|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: mos max|<p>VPN connection mos max.</p>|Dependent item|meraki.vpn.stat.mos.max[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxMos`</p></li></ul>|

### Trigger prototypes for VPN stats discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VPN [{#NETWORK.NAME}][{#SENDER.UPLINK}]=>[{#PEER.NETWORK.NAME}][{#RECEIVER.UPLINK}]: High average VPN connection loss (over >= {$MERAKI.VPN.LOSS.PERCENTILE%)||`count(/Cisco Meraki organization by HTTP/meraki.vpn.stat.loss.avg[{#NETWORK.ID}, {#SENDER.UPLINK}, {#PEER.NETWORK.ID}, {#RECEIVER.UPLINK}],#3,,"{$MERAKI.VPN.LOSS.PERCENTILE}")>=3`|Average||

### LLD rule License discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|License discovery||Dependent item|meraki.license.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.licenses`</p></li></ul>|

### Item prototypes for License discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|License [{#LICENSE.ID}]: get data|<p>Raw data for a license.</p>|Dependent item|meraki.license.get[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.licenses.[?(@.id == "{#LICENSE.ID}")].first()`</p></li></ul>|
|License [{#LICENSE.ID}]: activation date|<p>The date the license started burning.</p>|Dependent item|meraki.license.activation.date[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.activationDate`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|License [{#LICENSE.ID}]: expiration date|<p>The date the license will expire.</p>|Dependent item|meraki.license.expiration.date[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expirationDate`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|License [{#LICENSE.ID}]: total duration in days|<p>The duration of the license plus all permanently queued licenses associated with it.</p>|Dependent item|meraki.license.total.duration[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalDurationInDays`</p></li><li><p>JavaScript: `return days = Math.floor(value) || -1;`</p></li></ul>|
|License [{#LICENSE.ID}]: device serial|<p>Serial number of the device the license is assigned to.</p>|Dependent item|meraki.license.device.serial[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceSerial`</p></li></ul>|
|License [{#LICENSE.ID}]: device name|<p>Name of the device the license is assigned to.</p>|Dependent item|meraki.license.device.name[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceName`</p></li></ul>|
|License [{#LICENSE.ID}]: key|<p>License key.</p>|Dependent item|meraki.license.key[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.licenseKey`</p></li></ul>|
|License [{#LICENSE.ID}]: state|<p>The state of the license. All queued licenses have a status of 'recently queued'.</p>|Dependent item|meraki.license.state[{#LICENSE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li></ul>|

### LLD rule SAML roles discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SAML roles discovery||Dependent item|meraki.saml.roles.discovery|

### Item prototypes for SAML roles discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SAML role [{#SAML.ROLE}]: get data|<p>Raw data for SAML roles.</p>|Dependent item|meraki.saml.get[{#SAML.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#SAML.ID}")].first()`</p></li></ul>|
|SAML role [{#SAML.ROLE}]: organization access|<p>The privilege of the SAML administrator in the organization.</p>|Dependent item|meraki.saml.org.access[{#SAML.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.orgAccess`</p></li></ul>|

### LLD rule Administrators discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Administrators discovery||Dependent item|meraki.admins.discovery|

### Item prototypes for Administrators discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Admin [{#ADMIN.NAME}]: get data|<p>Raw data for admin in this organization.</p>|Dependent item|meraki.admin.get[{#ADMIN.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#ADMIN.ID}")].first()`</p></li></ul>|
|Admin [{#ADMIN.NAME}]: account status|<p>Status of the admin's account.</p>|Dependent item|meraki.admin.account.status[{#ADMIN.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accountStatus`</p></li></ul>|
|Admin [{#ADMIN.NAME}]: authentication method|<p>Admin's authentication method.</p>|Dependent item|meraki.admin.account.auth.method[{#ADMIN.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.authenticationMethod`</p></li></ul>|
|Admin [{#ADMIN.NAME}]: organization access|<p>Admin's level of access to the organization.</p>|Dependent item|meraki.admin.account.org.access[{#ADMIN.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.orgAccess`</p></li></ul>|
|Admin [{#ADMIN.NAME}]: 2FA enabled|<p>Indicates whether two-factor authentication is enabled.</p>|Dependent item|meraki.admin.account.two.factor.auth[{#ADMIN.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.twoFactorAuthEnabled`</p></li><li>Boolean to decimal</li></ul>|

# Cisco Meraki device by HTTP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MERAKI.TOKEN}|<p>Cisco Meraki dashboard API token.</p>||
|{$MERAKI.API.URL}|<p>Cisco Meraki dashboard API URL, e.g., api.meraki.com/api/v1</p>|`api.meraki.com/api/v1`|
|{$MERAKI.DEVICE.LOSS}|<p>Devices uplink loss threshold, in percent.</p>|`15`|
|{$MERAKI.DEVICE.LATENCY}|<p>Devices uplink latency threshold, in seconds.</p>|`0.15`|
|{$MERAKI.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See documentation at https://www.zabbix.com/documentation/7.0/manual/config/items/itemtypes/http</p>||
|{$MERAKI.UPLINK.LL.TIMESPAN}|<p>Timespan in seconds for getting device uplinks loss and quality stats. Used in the metric configuration and in the JavaScript API query. Must be between 1 and 86400 seconds.</p>|`180`|
|{$MERAKI.DEVICE.UPLINK.MATCHES}|<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MERAKI.DEVICE.UPLINK.NOT_MATCHES}|<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p>|`^null$`|
|{$MERAKI.DEVICE.LOSS.LATENCY.IP.MATCHES}|<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p>|`^((25[0-5]\|(2[0-4]\|1\d\|[1-9]\|)\d)\.?\b){4}$`|
|{$MERAKI.DEVICE.LOSS.LATENCY.IP.NOT_MATCHES}|<p>This macro is used in loss and latency checks discovery. Can be overridden on the host or linked template level.</p>|`^null$`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Meraki: Get device data|<p>Item for gathering device data from Meraki API.</p>|Script|meraki.get.device|
|Meraki: Device data item errors|<p>Item for gathering errors of the device item.</p>|Dependent item|meraki.get.device.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Meraki: status|<p>Device operational status</p><p>Network: {$NETWORK.ID} </p><p>MAC: {$MAC}</p>|Dependent item|meraki.device.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.device[0].status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Meraki: public IP|<p>Device public IP</p><p>Network: {$NETWORK.ID}</p><p>MAC: {$MAC}</p>|Dependent item|meraki.device.public.ip<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.device[0].publicIp`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Meraki: There are errors in 'Get device data' metric||`length(last(/Cisco Meraki device by HTTP/meraki.get.device.errors))>0`|Warning||
|Meraki: Status is not online||`last(/Cisco Meraki device by HTTP/meraki.device.status)<>1`|Warning||

### LLD rule Uplinks loss and quality discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uplinks loss and quality discovery||Dependent item|meraki.device.uplinks.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uplinksLL`</p></li></ul>|

### Item prototypes for Uplinks loss and quality discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uplink [{#IP}]: [{#UPLINK}]: Loss, %|<p>Loss percent of the device uplink. </p><p>Network: {#NETWORK.ID}. </p><p>Device serial: {#SERIAL}.</p>|Dependent item|meraki.device.loss.pct[{#IP},{#UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|Uplink [{#IP}]: [{#UPLINK}]: Latency|<p>Latency of the device uplink. </p><p>Network: {#NETWORK.ID}. </p><p>Device serial: {#SERIAL}.</p>|Dependent item|meraki.device.latency[{#IP},{#UPLINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `-1000`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Trigger prototypes for Uplinks loss and quality discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Uplink [{#IP}]: [{#UPLINK}]: loss > {$MERAKI.DEVICE.LOSS}%||`min(/Cisco Meraki device by HTTP/meraki.device.loss.pct[{#IP},{#UPLINK}],#3)>{$MERAKI.DEVICE.LOSS}`|Warning||
|Uplink [{#IP}]: [{#UPLINK}]: latency > {$MERAKI.DEVICE.LATENCY}||`min(/Cisco Meraki device by HTTP/meraki.device.latency[{#IP},{#UPLINK}],#3)>{$MERAKI.DEVICE.LATENCY}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

