# Twilio webhook

## Overview

This guide describes how to integrate your Zabbix installation with Twilio using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix to send notifications via SMS or WhatsApp.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|account_sid|\{$TWILIO\.ACCOUNT\_SID\}|Twilio Account SID.|
|auth_token|\{$TWILIO\.AUTH\_TOKEN\}|Twilio Auth Token.|
|message_type|sms|Message type. Possible values: sms, whatsapp.|
|from_number|\<PLACE YOUR FROM NUMBER\>|Twilio-verified sender phone number.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|to_number|\{ALERT\.SENDTO\}|Destination phone number.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Sign up for a Twilio account at [https://www.twilio.com](https://www.twilio.com).

2\. In your Twilio console, navigate to **Account Settings** to obtain your Account SID and Auth Token.

3\. For SMS messaging:
   - Purchase a phone number from Twilio.

4\. For WhatsApp messaging:
   - Set up WhatsApp Business Account through Twilio.
   - Submit your WhatsApp Business Account for approval by Twilio.
   - Wait for approval before using WhatsApp messaging.

## Zabbix configuration

### Create global macros

1\. Before setting up the webhook, you need to setup the global macros for Twilio credentials.

2\. In the Zabbix web interface, go to *Administration* > *General* > *Macros*.

3\. Add the following macros:
   - `{$TWILIO.ACCOUNT_SID}` with your Twilio Account SID
   - `{$TWILIO.AUTH_TOKEN}` with your Twilio Auth Token (mark as secret)

4\. Click the *Update* button to save the macros.

### Create the Twilio media type

1\. In the Zabbix interface *Alerts* > *Media types* section, import the [`media_twilio.yaml`](media_twilio.yaml) file.

2\. Open the newly added **Twilio** media type and configure the following parameters:
   - Set `from_number` to your Twilio phone number (e.g., `+14155551234`)
   - Set `message_type` to `sms` for SMS messages or `whatsapp` for WhatsApp messages

3\. Click the *Update* button to save the webhook settings.

### Create a user for Twilio notifications

1\. To receive notifications via Twilio, you need to create a Zabbix user and add **Media** with the **Twilio** media type.

2\. In the *Media* configuration, the **Send to** field should contain the destination phone number in E.164 format (e.g., `+14155551234`).

3\. Make sure this user has access to all the hosts for which you would like problem notifications to be sent via Twilio.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Twilio API](https://www.twilio.com/docs/) documentation.

## Feedback

This is a community-contributed media type template. 

Please report any issues, provide feedback, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

For official Zabbix support, please visit [`https://support.zabbix.com`](https://support.zabbix.com). 