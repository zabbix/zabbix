# Express webhook

This guide describes how to integrate Zabbix 5.4 installation with Express.ms messenger using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>

## Setting up Express
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

5\. Make GET request to `/api/v2/botx/bots/<BOT_ID>/token?signatire=<SIGNATURE>` for getting permanent access token.<br>
Example:<br>
```
curl 'https://localhost/api/v2/botx/bots/bb16c1e3-4ea9-542e-aa7f-2e26aff92780/token?signature=34DF7A8702F0F5C952C81463626C0A18C8DD92A0AA71A97F37F5E2CDCADBEA2E'

{"result": "TFMyNTY.g2gDbQAtACRiYjE2YzFmMy00ZWU5LTU0MmUtYWE0Zi0yZTY2YWGmOTI3ODBuBgDlhs73eAFiAAFRgA.o3LIGvKLjmuZ6Ja_dT7YeNEV71r6xgZYh8g8-QPasNQ", "status": "ok}
```

## Setting up the webhook in Zabbix
1\. Before setting up a media type, you need to set up a global macro "{$ZABBIX.URL}", which must contain the URL to Zabbix frontend.

1\. In the *Administration > Media types* section, import [media_express_ms.yaml](media_express_ms.yaml).

2\. Open the newly added **Express.ms** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters should be filled:<br>
**express_url** - the actual URL of your Express instance.<br>
**express_token** - bot's API access token created earlier.<br>

3\. Create a **Zabbix user** and add **Media** with the **Express.ms** media type.
"Send to" field should be filled as *channel ID* of the chat.<br>
Note, that "Send to" field cannot be empty. If the channel ID is already specified in the **express_send_to** parameter, you can put any symbol in this field to comply with frontend requirements.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into Express tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Express.ms](https://express.ms/docs) documentations.

## Supported versions
Zabbix 6.0 and higher
