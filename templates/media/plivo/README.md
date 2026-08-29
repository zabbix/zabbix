# Plivo webhook

## Overview

This guide describes how to integrate your Zabbix installation with Plivo using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix to send notifications via SMS or WhatsApp.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|auth_id|\{$PLIVO\.AUTH\_ID\}|Plivo Auth ID.|
|auth_token|\{$PLIVO\.AUTH\_TOKEN\}|Plivo Auth Token.|
|message_type|sms|Message type. Possible values: sms, whatsapp.|
|from_number|\<PLACE YOUR FROM NUMBER\>|Sender phone number or alphanumeric sender ID.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|to_number|\{ALERT\.SENDTO\}|Destination phone number.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Sign up for a Plivo account at [https://www.plivo.com](https://www.plivo.com).

2\. In your Plivo console, navigate to **Account Settings** to obtain your Auth ID and Auth Token.

3\. For SMS messaging:
   - Purchase a phone number from Plivo or use an approved alphanumeric sender ID.

4\. For WhatsApp messaging:
   - Set up WhatsApp Business Account integration through Plivo.
   - Ensure your WhatsApp Business Account is approved and connected to your Plivo account.

## Zabbix configuration

### Create global macros

1\. Before setting up the webhook, you need to setup the global macros for Plivo credentials.

2\. In the Zabbix web interface, go to *Administration* > *General* > *Macros*.

3\. Add the following macros:
   - `{$PLIVO.AUTH_ID}` with your Plivo Auth ID
   - `{$PLIVO.AUTH_TOKEN}` with your Plivo Auth Token (mark as secret)

4\. Click the *Update* button to save the macros.

### Create the Plivo media type

1\. In the Zabbix interface *Alerts* > *Media types* section, import the [`media_plivo.yaml`](media_plivo.yaml) file.

2\. Open the newly added **Plivo** media type and configure the following parameters:
   - Set `from_number` to your Plivo phone number or approved sender ID
   - Set `message_type` to `sms` for SMS messages or `whatsapp` for WhatsApp messages

3\. Click the *Update* button to save the webhook settings.

### Create a user for Plivo notifications

1\. To receive notifications via Plivo, you need to create a Zabbix user and add **Media** with the **Plivo** media type.

2\. In the *Media* configuration, the **Send to** field should contain the destination phone number in E.164 format (e.g., `+14155551234`).

3\. Make sure this user has access to all the hosts for which you would like problem notifications to be sent via Plivo.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Plivo API](https://www.plivo.com/docs/) documentation.

## Feedback

This is a community-contributed media type template. 

Please report any issues, provide feedback, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

For official Zabbix support, please visit [`https://support.zabbix.com`](https://support.zabbix.com).