# Rocket.Chat webhook

This guide describes how to integrate Zabbix 5.4 installation with Rocket.Chat using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
By default, all new alerts will be posted as messages with an attachment card. Event updates and resolve messages will be added to the thread of the first message.

## Setting up Rocket.Chat
1\. Create a user for API or use an existing one. Make sure the user is able to post messages in the required channel.<br>

2\. Grant to the user a role with *create-personal-access-tokens* permission. See Rocket.Chat [documentation](https://docs.rocket.chat/api/rest-api/personal-access-tokens) for the information.<br>

3\. Get the API access token. The tokens that will be generated are irrecoverable, after generating, you must save it in a safe place. If the token is lost or forgotten, you can regenerate or delete the token.


## Setting up the webhook in Zabbix
1\. In the *Administration > Media types* section, import [media_rocketchat.yaml](media_rocketchat.yaml).

2\. Open the newly added **Rocket.Chat** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters should be filled:<br>
**rc_url** - the actual URL of your Rocket.Chat instance.<br>
**rc_user_id** - Rocket.Chat API user ID.<br>
**rc_user_token** - user's API access token created earlier.<br>

3\. The following parameters can help you customize the alerts:<br>
**rc_api_url** - API URL. Can be useful if the version will be changed.<br>
**rc_send_to** - *#channel* or *@username*. Supports private and public channels and direct messages.<br>
**use_default_message** - **false** (default) or **true**. If **true** all messages will be posted as text of *{ALERT.MESSAGE}.* For non trigger-based notifications, it is always set as **true**.<br>
**field_1_short_p:Host** - contains data for each field of the attachment. "Field" parameters with another format or empty value will be ignored.<br>
Format explanation:<br>
- *field* - prefix of the parameter with field info.
- *1* - the position of the field. Fields with the same position will be added in the alphabetical order.
- *short* - whether the field should be short or not. If *short*, there can be several fields on one line, otherwise, the field will be placed on a separate line.
- *p* - optional. Used if the field should be sent only on problem/recovery operation. Possible values:
    - *p* - problem
    - *r* - recovery
- *Host* - the title of the field. There can be any text including whitespaces or symbols.aces or symbols.

4\. Create a **Zabbix user** and add **Media** with the **Rocket.Chat** media type.
"Send to" field should be filled as `#channel_name` or `@username`.<br>
Note, that "Send to" field cannot be empty. If the channel is already specified in the **rc_send_to** parameter, you can put any symbol in this field to comply with frontend requirements.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into Rocket.Chat tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Rocket.Chat](https://docs.rocket.chat/) documentations.

## Supported versions
Zabbix 6.0 and higher
