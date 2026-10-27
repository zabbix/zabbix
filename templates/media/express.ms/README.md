![](images/logo.png?raw=true)
# Express.ms webhook

## Overview

This guide describes how to integrate Zabbix installation with Express.ms messenger using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|express_token|\<PLACE BOT TOKEN\>||
|express_url|\<PLACE INSTANCE URL\>||
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Express\.ms"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Express.ms", e.g. {$HTTP.TLS.VERIFY:"Express.ms"}.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|express_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|express_send_to|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|
|express_tags|\{EVENT\.TAGSJSON\}|A JSON array containing event tag [objects]('https://www.zabbix.com/documentation/current/manual/api/reference/event/object#event-tag'). Expanded to an empty array if no tags exist.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Create a bot user for API or use an existing one. *URL* cannot be empty, pass any URL here.<br>

2\. Open created bot and set *allowed_data* to *none*.<br>

3\. Copy *ID* and *Secret key*.

4\. Now you need to generate HMAC-SHA256 signature, represented as a base16 (hex) string.<br>
Bash usage:
```
echo -n <BOT_ID> | openssl dgst -sha256 -hmac <SECRET> | awk '{print toupper($0)}'
```
Replace placeholders with your values from the previous step.<br>
Example:
```
echo -n bb16c1e3-4ea9-542e-aa7f-2e26aff92780 | openssl dgst -sha256 -hmac 38h5z7obgfc5re0amua5h588rg7a1a19 | awk '{print toupper($0)}'

# 34DF7A8702F0F5C952C81463626C0A18C8DD92A0AA71A97F37F5E2CDCADBEA2E
```

5\. Make GET request to `/api/v2/botx/bots/<BOT_ID>/token?signature=<SIGNATURE>` for getting permanent access token.<br>
Example:<br>
```
curl 'https://localhost/api/v2/botx/bots/bb16c1e3-4ea9-542e-aa7f-2e26aff92780/token?signature=34DF7A8702F0F5C952C81463626C0A18C8DD92A0AA71A97F37F5E2CDCADBEA2E'

{"result": "TFMyNTY.g2gDbQAtACRiYjE2YzFmMy00ZWU5LTU0MmUtYWE0Zi0yZTY2YWGmOTI3ODBuBgDlhs73eAFiAAFRgA.o3LIGvKLjmuZ6Ja_dT7YeNEV71r6xgZYh8g8-QPasNQ", "status": "ok}
```

## Zabbix configuration

1\. Before setting up a media type, you need to set up a global macro "{$ZABBIX.URL}", which must contain the URL to Zabbix frontend.

2\. In the *Administration > Media types* section, import [media_express_ms.yaml](media_express_ms.yaml).

3\. Open the newly added **Express.ms** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters should be filled:<br>
**express_url** - the actual URL of your Express instance.<br>
**express_token** - bot's API access token created earlier.<br>

4\. Create a **Zabbix user** and add **Media** with the **Express.ms** media type.
"Send to" field should be filled as *channel ID* of the chat.<br>
Note, that "Send to" field cannot be empty. If the channel ID is already specified in the **express_send_to** parameter, you can put any symbol in this field to comply with frontend requirements.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into Express tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [Express.ms](https://express.ms/docs) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
