![](images/logo.png?raw=true)
# Brevis.one webhook

## Overview

This guide describes how to integrate Zabbix installation with Brevis.one SMS Gateway using HTTP API and Zabbix webhook feature. This guide provides instructions on setting up a media type, a user, and an action in Zabbix.

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|endpoint|\<PLACE HTTP API URL\>||
|flash|false||
|password|\<PLACE PASSWORD\>||
|ring|false||
|telauto|true||
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Brevis\.one"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Brevis.one", e.g. {$HTTP.TLS.VERIFY:"Brevis.one"}.|
|username|\<PLACE USERNAME\>||

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|send_to|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|
|text|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Create a user for HTTP API or use an existing one.<br>

2\. Grant to the user *Access to the HTTP API* permission. See Brevis.one [documentation](https://docs.brevis.one/current/en/Content/Functionality/Sending%20Messages/HTTP%20API.htm) for the information.<br>

## Zabbix configuration

1\. Before setting up a media type, you need to set up a global macro "{$ZABBIX.URL}", which must contain the URL to Zabbix frontend.

2\. In the *Administration > Media types* section, import [media_brevis.one.xml](media_brevis.one.xml).

3\. Open the newly added **Brevis.one** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters should be filled out:<br>
**endpoint** - the actual URL of your Brevis.one API instance. The API can be addressed with the following: `https://<SMS Gateway IP>/api.php`<br>
**username** - Brevis.one API username.<br>
**password** - user's password.<br>

3\. The following parameters can help you customize the alerts: ***ring**, **flash**, **telauto**<br>
See Brevis.one [documentation](https://docs.brevis.one/current/en/Content/Functionality/Sending%20Messages/HTTP%20API.htm) for details.<br>

4\. Create a service **Zabbix user** or use any existing user, then add **Media** with the **Brevis.one**.
The "Send to" field should be filled as a phone number without a plus (+) sign or as "mode:option".<br>
Allowed modes: number (Default), group, telgroup, telnumber, user, teluser.<br>
Examples:
`37167784742` (Send SMS to the individual telephone number)<br>
`group:11` (Send a text message to the specified user group. User groups are managed in the Configuration - Groups.)<br>
`telnumber:37167784742` (Send a message via Automatic to the individual telephone number. Automatic tries to deliver the notification via Telegram. If this fails the notification will be delivered by a text message.)<br>
See Brevis.one [documentation](https://docs.brevis.one/current/en/Content/Functionality/Sending%20Messages/HTTP%20API.htm) for additional information.<br>
Note, that the "Send to" field cannot be empty. If the phone number or user/group ID is already specified in the **send_to** parameter, you can put any symbol in this field to comply with frontend requirements.
Make sure this user has access to all hosts, for which you would like problem notifications to be sent via Brevis.one HTTP API.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
