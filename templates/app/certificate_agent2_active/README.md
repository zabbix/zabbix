
# Website certificate by Zabbix agent 2 active

## Overview

The template to monitor TLS/SSL certificate on the website by Zabbix agent 2 that works without any external scripts.
Zabbix agent 2 with the WebCertificate plugin requests certificate using the web.certificate.get key and returns
JSON with certificate attributes.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Website TLS/SSL certificate

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1\. Setup and configure zabbix-agent2 with the WebCertificate plugin.

2\. Test availability: `zabbix_get -s <zabbix_agent_addr> -k web.certificate.get[<website_DNS_name>]`

3\. Create a host with Zabbix agent interface.

4\. Link the template to the host.

5\. Customize the values of {$CERT.WEBSITE.HOSTNAME}, {$CERT.WEBSITE.IP} and {$CERT.WEBSITE.PORT} macros. {$CERT.WEBSITE.HOSTNAME} is a required parameter in Zabbix agent 2 `web.certificate.get` key, so it must be set to at least single value. Other macros may be set as desired (will behave as explained below). Note, that multiple values can be specified delimited by commas and corresponding values in other macros are processed by the order they are specified (check the table below for examples):

|    Macro               |         Value                  |
|------------------------|--------------------------------|
|{$CERT.WEBSITE.HOSTNAME}|hostname_01,hostname_02 , hostname_03 |
|{$CERT.WEBSITE.PORT}    |port_01  ,          , port_03   |
|{$CERT.WEBSITE.IP}      |         , ip_02                |

As shown in the example above, the following websites will be discovered:

- Website with hostname `hostname_01`, the hostname itself will be used for connection (because the address is set to an empty string), the port is `port_01`.
- Website with hostname `hostname_02`, which will also be used for SNI verification, the address `ip_02` will be used for connection, the port will default to 443 (because it is set to an empty string).
- Website with hostname `hostname_03`, the hostname itself will be used for connection (because the address is not set and treated as an empty string), the port is `port_03`.

For additional details please refer to official documentation about the Zabbix agent 2 web.certificate.get key:
https://www.zabbix.com/documentation/7.4/manual/config/items/itemtypes/zabbix_agent/zabbix_agent2#web.certificate.get

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CERT.EXPIRY.WARN}|<p>Number of days until the certificate expires.</p>|`7`|
|{$CERT.WEBSITE.HOSTNAME}|<p>The website DNS name for the connection.</p>|`<Put DNS name>`|
|{$CERT.WEBSITE.PORT}|<p>The TLS/SSL port number of the website.</p>|`443`|
|{$CERT.WEBSITE.IP}|<p>The website IP address for the connection.</p>||
|{$CERT.PARAMS.CHECK}|<p>Type of verification of input parameters. </p><p>A "STRICT" (by default) - when an error occurs, the check stops. </p><p>Any other meaning - erroneous records are ignored.</p>|`STRICT`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Parsing the parameters in user macros. JSON string, which is used in LLD.</p>|Script|cert.get.data<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Certificate: Error parse parameters|<p>Some entries in the macro "{$CERT.WEBSITE.HOSTNAME}" are incorrect and ignored.</p>|`jsonpath(last(/Website certificate by Zabbix agent 2 active/cert.get.data),"$.error.code", 0) = 1`|Warning|**Manual close**: Yes|
|Certificate: Error parse parameters|<p>Some entries in the macro "{$CERT.WEBSITE.HOSTNAME}" are incorrect.<br>Please, in order not to lose the data, edit the macros.</p>|`jsonpath(last(/Website certificate by Zabbix agent 2 active/cert.get.data),"$.error.code", 0) = 2`|High|**Manual close**: Yes|

### LLD rule Website discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Website discovery||Dependent item|cert.website.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|

### Item prototypes for Website discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#CERT.WEBSITE.ITEMNAME}]: Get|<p>Returns the JSON with attributes of a certificate of the requested site.</p>|Zabbix agent (active)|web.certificate.get[{#CERT.WEBSITE.HOSTNAME},{#CERT.WEBSITE.PORT},{#CERT.WEBSITE.IP}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Validation result|<p>The certificate validation result. Possible values: valid/invalid/valid-but-self-signed</p>|Dependent item|cert.validation[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.result.value`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Last validation status|<p>Last check result message.</p>|Dependent item|cert.message[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.result.message`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Version|<p>The version of the encoded certificate.</p>|Dependent item|cert.version[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.version`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Serial number|<p>The serial number is a positive integer assigned by the CA to each certificate. It is unique for each certificate issued by a given CA. Non-conforming CAs may issue certificates with serial numbers that are negative or zero.</p>|Dependent item|cert.serial_number[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.serial_number`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Signature algorithm|<p>The algorithm identifier for the algorithm used by the CA to sign the certificate.</p>|Dependent item|cert.signature_algorithm[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.signature_algorithm`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Issuer|<p>The field identifies the entity that has signed and issued the certificate.</p>|Dependent item|cert.issuer[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.issuer`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Valid from|<p>The date on which the certificate validity period begins.</p>|Dependent item|cert.not_before[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.not_before.timestamp`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Expires on|<p>The date on which the certificate validity period ends.</p>|Dependent item|cert.not_after[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.not_after.timestamp`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Subject|<p>The field identifies the entity associated with the public key stored in the subject public key field.</p>|Dependent item|cert.subject[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.subject`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Subject alternative name|<p>The subject alternative name extension allows identities to be bound to the subject of the certificate.  These identities may be included in addition to or in place of the identity in the subject field of the certificate.  Defined options include an Internet electronic mail address, a DNS name, an IP address, and a Uniform Resource Identifier (URI).</p>|Dependent item|cert.alternative_names[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.alternative_names`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Public key algorithm|<p>The digital signature algorithm is used to verify the signature of a certificate.</p>|Dependent item|cert.public_key_algorithm[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.x509.public_key_algorithm`</p></li></ul>|
|[{#CERT.WEBSITE.ITEMNAME}]: Fingerprint|<p>The Certificate Signature (SHA1 Fingerprint or Thumbprint) is the hash of the entire certificate in DER form.</p>|Dependent item|cert.sha1_fingerprint[{#CERT.WEBSITE.ITEMNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sha1_fingerprint`</p></li></ul>|

### Trigger prototypes for Website discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cert [{#CERT.WEBSITE.ITEMNAME}]: SSL certificate is invalid|<p>SSL certificate has expired or it is issued for another domain.</p>|`find(/Website certificate by Zabbix agent 2 active/cert.validation[{#CERT.WEBSITE.ITEMNAME}],,"like","invalid")=1`|High||
|Cert [{#CERT.WEBSITE.ITEMNAME}]: SSL certificate expires soon|<p>The SSL certificate should be updated or it will become untrusted.</p>|`(last(/Website certificate by Zabbix agent 2 active/cert.not_after[{#CERT.WEBSITE.ITEMNAME}]) - now()) / 86400 < {$CERT.EXPIRY.WARN}`|Warning|**Depends on**:<br><ul><li>Cert [{#CERT.WEBSITE.ITEMNAME}]: SSL certificate is invalid</li></ul>|
|Cert [{#CERT.WEBSITE.ITEMNAME}]: Fingerprint has changed|<p>The SSL certificate fingerprint has changed. If you did not update the certificate, it may mean your certificate has been hacked. Acknowledge to close the problem manually.<br>There could be multiple valid certificates on some installations. In this case, the trigger will have a false positive. You can ignore it or disable the trigger.</p>|`last(/Website certificate by Zabbix agent 2 active/cert.sha1_fingerprint[{#CERT.WEBSITE.ITEMNAME}]) <> last(/Website certificate by Zabbix agent 2 active/cert.sha1_fingerprint[{#CERT.WEBSITE.ITEMNAME}],#2)`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

