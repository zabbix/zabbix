# SMSEagle webhook 

This guide describes how to integrate your Zabbix installation with SMSEagle hardware SMS gateway using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.
<br/><br/>
## In SMSEagle

1\. Create a new user in SMSEagle (menu **Users** > **+ Add Users**, user access level: “User”).

2\. Grant API access to the created user:

- Click **Access to API** beside the newly created user.
- Enable **APIv2**
- Generate new token
- For text messages, add access permissions in section Messages for: **Send SMS, Send MMS**.
- For voice alerting, add access permissions in section Calls for: **Make a ring call, Make a TTS call, Make a TTS Advanced call**.
- Save settings.

<br/><br/>
## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to SMSEagle device through the Rest API.


1\. In the **Administration > Media types** section, import the [media_smseagle.yaml](media_smseagle.yaml).


2\. Open the newly added **SMSEagle** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters are required:<br>
**access_token** - API access token created in SMSEagle<br>
**url** - actual URL of your SMSEagle device (for example: http://10.10.0.100 or https://sms.mycompany.com)<br>
**type** - type(s) of message(s) to send. Possible values: **sms, mms, tts** and **tts_adv**, respectively for SMS, MMS, TTS Call and Advanced TTS Call.<br/>
Allows multiple types, separated by commas (e.g. "sms,tts_adv").

Other required parameters are message type specific. More information can be found on our [APIv2](https://www.smseagle.eu/docs/apiv2/) page.


3\. in the **Administration > Users** click on a User, and add a new media called **SMSEagle**. Enter SMS recipient. Available recipient formats:<br>
Phone number: <code>phone_number</code><br>
Contact in SMSEagle Phonebook: <code>contact_name:c</code><br>
Group in SMSEagle Phonebook: <code>group_name:g</code><br>

Multiple recipients can be separated by comma.


<br/><br/>
For more information, please see [Zabbix](https://www.zabbix.com/documentation/6.2/manual/config/notifications) and [SMSEagle](https://www.smseagle.eu/integration-plugins/zabbix-sms-integration/) documentation.
<br/><br/>
## Supported Versions

Zabbix 6.2+