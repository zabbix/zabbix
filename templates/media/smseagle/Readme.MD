# SMSEagle webhook 

This guide describes how to integrate your Zabbix installation with SMSEagle hardware SMS gateway using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.
<br/><br/>
## In SMSEagle

1\. Create a new user in SMSEagle.

2\. Grant API access to the created user.

3\. Enable API Access token. Generate new token.

<br/><br/>
## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to SMSEagle device through the Rest API.


1\. In the **Administration > Media types** section, import the [media_smseagle.yaml](media_smseagle.yaml).


2\. Open the newly added **SMSEagle** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters are required:<br>
**access_token** - API access token created in SMSEagle<br>
**url** - actual URL of your SMSEagle device (for example: http://10.10.0.100 or https://sms.mycompany.com)<br>


3\. in the **Administration > Users** click on a User, and add a new media called **SMSEagle**. Enter SMS recipient. Available recipient formats:<br>
Phone number: <code>phone_number</code><br>
Contact in SMSEagle Phonebook: <code>contact_name:c</code><br>
Group in SMSEagle Phonebook: <code>group_name:g</code><br>


<br/><br/>
For more information, please see [Zabbix](https://www.zabbix.com/documentation/6.2/manual/config/notifications) and [SMSEagle](https://www.smseagle.eu/integration-plugins/zabbix-sms-integration/) documentation.
<br/><br/>
## Supported Versions

Zabbix 6.2