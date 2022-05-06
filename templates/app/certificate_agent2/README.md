
# Website certificate by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor TLS/SSL certificate on the website by Zabbix agent 2 that works without any external scripts.
Zabbix agent 2 with the WebCertificate plugin requests certificate using the web.certificate.get key and returns
JSON with certificate attributes.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

1\. Setup and configure zabbix-agent2 with the WebCertificate plugin.

2\. Test availability: `zabbix_get -s <zabbix_agent_addr> -k web.certificate.get[<website_DNS_name>]`

3\. Create a host for the TLS/SSL certificate with Zabbix agent interface.

4\. Link the template to the host.

5\. Customize the value of {$CERT.WEBSITE.HOSTNAME} macro.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CERT.EXPIRY.WARN} |<p>Number of days until the certificate expires.</p> |`7` |
|{$CERT.WEBSITE.HOSTNAME} |<p>The website DNS name for the connection.</p> |`<Put DNS name>` |
|{$CERT.WEBSITE.IP} |<p>The website IP address for the connection.</p> |`` |
|{$CERT.WEBSITE.PORT} |<p>The TLS/SSL port number of the website.</p> |`443` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|General |Cert: Validation result |<p>The certificate validation result. Possible values: valid/invalid/valid-but-self-signed</p> |DEPENDENT |cert.validation<p>**Preprocessing**:</p><p>- JSONPATH: `$.result.value`</p> |
|General |Cert: Last validation status |<p>Last check result message.</p> |DEPENDENT |cert.message<p>**Preprocessing**:</p><p>- JSONPATH: `$.result.message`</p> |
|General |Cert: Version |<p>The version of the encoded certificate.</p> |DEPENDENT |cert.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.version`</p> |
|General |Cert: Serial number |<p>The serial number is a positive integer assigned by the CA to each certificate. It is unique for each certificate issued by a given CA. Non-conforming CAs may issue certificates with serial numbers that are negative or zero.</p> |DEPENDENT |cert.serial_number<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.serial_number`</p> |
|General |Cert: Signature algorithm |<p>The algorithm identifier for the algorithm used by the CA to sign the certificate.</p> |DEPENDENT |cert.signature_algorithm<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.signature_algorithm`</p> |
|General |Cert: Issuer |<p>The field identifies the entity that has signed and issued the certificate.</p> |DEPENDENT |cert.issuer<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.issuer`</p> |
|General |Cert: Valid from |<p>The date on which the certificate validity period begins.</p> |DEPENDENT |cert.not_before<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.not_before.timestamp`</p> |
|General |Cert: Expires on |<p>The date on which the certificate validity period ends.</p> |DEPENDENT |cert.not_after<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.not_after.timestamp`</p> |
|General |Cert: Subject |<p>The field identifies the entity associated with the public key stored in the subject public key field.</p> |DEPENDENT |cert.subject<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.subject`</p> |
|General |Cert: Subject alternative name |<p>The subject alternative name extension allows identities to be bound to the subject of the certificate.  These identities may be included in addition to or in place of the identity in the subject field of the certificate.  Defined options include an Internet electronic mail address, a DNS name, an IP address, and a Uniform Resource Identifier (URI).</p> |DEPENDENT |cert.alternative_names<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.alternative_names`</p> |
|General |Cert: Public key algorithm |<p>The digital signature algorithm is used to verify the signature of a certificate.</p> |DEPENDENT |cert.public_key_algorithm<p>**Preprocessing**:</p><p>- JSONPATH: `$.x509.public_key_algorithm`</p> |
|General |Cert: Fingerprint |<p>The Certificate Signature (SHA1 Fingerprint or Thumbprint) is the hash of the entire certificate in DER form.</p> |DEPENDENT |cert.sha1_fingerprint<p>**Preprocessing**:</p><p>- JSONPATH: `$.sha1_fingerprint`</p> |
|Zabbix raw items |Cert: Get |<p>Returns the JSON with attributes of a certificate of the requested site.</p> |ZABBIX_PASSIVE |web.certificate.get[{$CERT.WEBSITE.HOSTNAME},{$CERT.WEBSITE.PORT},{$CERT.WEBSITE.IP}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Cert: SSL certificate is invalid |<p>SSL certificate has expired or it is issued for another domain.</p> |`find(/Website certificate by Zabbix agent 2/cert.validation,,"like","invalid")=1` |HIGH | |
|Cert: SSL certificate expires soon |<p>The SSL certificate should be updated or it will become untrusted.</p> |`(last(/Website certificate by Zabbix agent 2/cert.not_after) - now()) / 86400 < {$CERT.EXPIRY.WARN}` |WARNING |<p>**Depends on**:</p><p>- Cert: SSL certificate is invalid</p> |
|Cert: Fingerprint has changed |<p>The SSL certificate fingerprint has changed. If you did not update the certificate, it may mean your certificate has been hacked. Ack to close.</p><p>There could be multiple valid certificates on some installations. In this case, the trigger will have a false positive. You can ignore it or disable the trigger.</p> |`last(/Website certificate by Zabbix agent 2/cert.sha1_fingerprint) <> last(/Website certificate by Zabbix agent 2/cert.sha1_fingerprint,#2)` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/428309-discussion-thread-for-official-zabbix-template-tls-ssl-certificates-monitoring).

