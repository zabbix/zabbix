![](images/logo.png?raw=true)
# iTop webhook

## Overview

This guide describes how to integrate Zabbix installation with iTop using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
Please note that recovery and update operations are supported only for trigger-based events.

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|itop_api_version|1\.3||
|itop_class|UserRequest||
|itop_comment|Created by Zabbix action \{ACTION\.NAME\}||
|itop_log|private\_log||
|itop_organization_id|\<PLACE ORGANIZATION ID\>||
|itop_password|\<PLACE PASSWORD OR TOKEN\>||
|itop_url|\<PLACE YOUR ITOP URL\>||
|itop_user|\<PLACE LOGIN\>||
|tls_verify|\{$HTTP\.TLS\.VERIFY:"iTop"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "iTop", e.g. {$HTTP.TLS.VERIFY:"iTop"}.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_recovery_value|\{EVENT\.RECOVERY\.VALUE\}|Numeric value of the recovery event.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|itop_id|\{EVENT\.TAGS\.\_\_zbx\_itop\_id\}||

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Create a user for API with profile "REST Services User" or use an existing one. Make sure the user is able to create tickets in the required ticketing module.<br>
2\. Get the organization's ID. You can obtain it from the URL of organization's profile in *Data administration > Catalog > Organizations*.<br>
*&lt;itop_url&gt;/pages/UI.php?operation=details&class=Organization&**id=1**&c\[menu\]=Organization*

## Zabbix configuration

1\. In the *Administration > Media types* section, import [media_itop.yaml](media_itop.yaml).

2\. Open the newly added **iTop** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters are required:<br>
**itop_url** - actual URL of your iTop instance.<br>
**itop_user** - iTop user login.<br>
**itop_password** - user's password.<br>
**itop_organization_id** - ID of your organization.<br>
**itop_class** - name of the class to be used when creating new tickets from Zabbix notifications. For example, *UserRequest* or *Problem*.<br>
**itop_log** - the type of log section in the ticket for posting problem's updates from Zabbix. Must be *Private* or *Public*.<br>
**itop_comment** - the comment that will be posted to ticket's history.

3\. Create a **Zabbix user** and add **Media** with the **iTop** media type. 
Though a "Send to" field is not used in iTop webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into iTop tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [iTop](https://www.itophub.io/wiki/page) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
