# SMSEagle webhook

## SMSEagle Setup

1. Create a user for HTTP API or use an existing one.

2. Open the API Access menu to generate access token and grant access to sending methods.

## Zabbix Setup

1. Go to "Administration" > "Media types" menu and import the "media_smseagle.yaml" file.

2. Configure added media type (required fields: <b>recipient, message, url, access_token</b>).

<b>URL format:</b><br>
Address of your SMSEagle device, IP or domain name<br>
(e.g. http://192.168.0.2 or http://smseagle-url.com)

<b>Recipient formats:</b><br>
Contact: <code>contact_name:c</code><br>
Group: <code>group_name:g</code><br>
Phone number: <code>phone_number</code>

For more information please refer to the SMSEagle API article:<br>
https://www.smseagle.eu/api/