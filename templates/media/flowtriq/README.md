# Flowtriq webhook

## Overview

This guide describes how to integrate your Zabbix installation with [Flowtriq](https://flowtriq.com) using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

Flowtriq is a DDoS detection and traffic analytics platform. This integration forwards Zabbix alerts to a Flowtriq webhook endpoint, allowing you to correlate infrastructure alerts with network traffic data.

## Requirements

Zabbix version: 8.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|flowtriq\_url|\<PLACE WEBHOOK URL\>|The URL of your Flowtriq webhook endpoint.|
|flowtriq\_api\_key|\<PLACE API KEY\>|Your Flowtriq API key for authentication.|
|zabbix\_url|\{$ZABBIX\.URL\}|The URL of the Zabbix frontend.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event\_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event\_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event\_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event\_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event\_update\_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event\_update\_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|event\_update\_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|alert\_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert\_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|event\_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|trigger\_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|trigger\_name|\{TRIGGER\.NAME\}|Name of the trigger.|
|trigger\_description|\{TRIGGER\.DESCRIPTION\}|Description of the trigger.|
|host\_name|\{HOST\.NAME\}|Name of the host.|
|host\_ip|\{HOST\.IP\}|IP address of the host.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Webhook payload

The webhook sends a JSON payload to your Flowtriq endpoint with the following structure:

```json
{
  "source": "zabbix",
  "subject": "Problem: High CPU usage",
  "message": "Problem started at 14:30:00 on 2024-01-15...",
  "severity": "high",
  "severity_name": "High",
  "event_type": "problem",
  "host": {
    "name": "web-server-01",
    "ip": "10.0.0.5"
  },
  "trigger": {
    "name": "CPU usage is too high",
    "description": "CPU usage has exceeded 90% for 5 minutes"
  },
  "event_id": "12345",
  "event_url": "https://zabbix.example.com/tr_events.php?triggerid=100&eventid=12345",
  "zabbix_url": "https://zabbix.example.com"
}
```

The `event_type` field will be one of: `problem`, `resolve`, `update`, `discovery`, or `autoregistration`.

## Flowtriq configuration

1. Log in to your Flowtriq dashboard at [https://flowtriq.com](https://flowtriq.com).

2. Navigate to your webhook settings and create a new webhook endpoint (or use an existing one). Copy the webhook URL.

3. Copy your API key from the Flowtriq dashboard.

## Zabbix configuration

1. Before you can start using the Flowtriq webhook, you need to set up the global macro `{$ZABBIX.URL}`:
  - In the Zabbix web interface, go to *Administration* > *Macros* in the top-left dropdown menu.
  - Set up the global macro `{$ZABBIX.URL}` which will contain the URL to the Zabbix frontend. The URL should be either an IP address, a fully qualified domain name, or a localhost.
  - Specifying a protocol is mandatory, whereas the port is optional. Good examples:
    - `http://zabbix.com`
    - `https://zabbix.lan/zabbix`
    - `http://server.zabbix.lan/`
    - `http://localhost`
    - `http://127.0.0.1:8080`
  - Bad examples:
    - `zabbix.com`
    - `http://zabbix/`

2. Import the media type:
  - In the *Alerts* > *Media types* section, import the [`media_flowtriq.yaml`](media_flowtriq.yaml) file.

3. Configure the media type parameters:
  - Open the imported **Flowtriq** media type.
  - Set the `flowtriq_url` parameter to your Flowtriq webhook endpoint URL.
  - Set the `flowtriq_api_key` parameter to your Flowtriq API key.

4. Create a Zabbix user and add media:
  - If you want to create a new user, go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - In the *Media* tab, add a new media and select **Flowtriq** from the *Type* drop-down list.
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to Flowtriq.

5. Done! You can now start using this media type in actions and receive alerts in Flowtriq.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/8.0/manual/config/notifications) and [Flowtriq](https://flowtriq.com) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
