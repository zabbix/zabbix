
# WildFly Domain by JMX

## Overview

For Zabbix version: 6.0 and higher  
Official JMX Template for WildFly Domain Controller.


This template was tested on:

- WildFly, version 22.6.0

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/jmx) for basic instructions.

Metrics are collected by JMX.
This template works with Domain Controller.

1. Enable and configure JMX access to WildFly. See documentation for [instructions](https://docs.wildfly.org/23/Admin_Guide.html#JMX).
2. Copy jboss-client.jar from `/(wildfly,EAP,Jboss,AS)/bin/client` in to directory `/usr/share/zabbix-java-gateway/lib`
3. Restart Zabbix Java gateway
4. Set the user name and password in host macros {$WILDFLY.USER} and {$WILDFLY.PASSWORD}.
Depending on your server setup, you may need to specify a custom JMX scheme in macro {$WILDFLY.JMX.PROTOCOL} (default: remote+http)



## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$WILDFLY.DEPLOYMENT.MATCHES} |<p>Filter of discoverable deployments</p> |`.*` |
|{$WILDFLY.DEPLOYMENT.NOT_MATCHES} |<p>Filter to exclude discovered deployments</p> |`CHANGE_IF_NEEDED` |
|{$WILDFLY.JMX.PROTOCOL} |<p>-</p> |`remote+http` |
|{$WILDFLY.PASSWORD} |<p>-</p> |`zabbix` |
|{$WILDFLY.SERVER.MATCHES} |<p>Filter of discoverable servers</p> |`.*` |
|{$WILDFLY.SERVER.NOT_MATCHES} |<p>Filter to exclude discovered servers</p> |`CHANGE_IF_NEEDED` |
|{$WILDFLY.USER} |<p>-</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Deployments discovery |<p>Discovery deployments metrics.</p> |JMX |jmx.get[beans,"jboss.as.expr:deployment=*,server-group=*"]<p>**Filter**:</p>AND <p>- {#DEPLOYMENT} MATCHES_REGEX `{$WILDFLY.DEPLOYMENT.MATCHES}`</p><p>- {#DEPLOYMENT} NOT_MATCHES_REGEX `{$WILDFLY.DEPLOYMENT.NOT_MATCHES}`</p> |
|Servers discovery |<p>Discovery instances in domain.</p> |JMX |jmx.get[beans,"jboss.as:host=master,server-config=*"]<p>**Filter**:</p>AND <p>- {#SERVER} MATCHES_REGEX `{$WILDFLY.SERVER.MATCHES}`</p><p>- {#SERVER} NOT_MATCHES_REGEX `{$WILDFLY.SERVER.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|WildFly |WildFly: Launch type |<p>The manner in which the server process was launched. Either "DOMAIN" for a domain mode server launched by a Host Controller, "STANDALONE" for a standalone server launched from the command line, or "EMBEDDED" for a standalone server launched as an embedded part of an application running in the same virtual machine.</p> |JMX |jmx["jboss.as:management-root=server","launchType"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Name |<p>For standalone mode: The name of this server. If not set, defaults to the runtime value of InetAddress.getLocalHost().getHostName().</p><p>For domain mode: The name given to this domain</p> |JMX |jmx["jboss.as:management-root=server","name"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Process type |<p>The type of process represented by this root resource.</p> |JMX |jmx["jboss.as:management-root=server","processType"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Version |<p>The version of the WildFly Core based product release</p> |JMX |jmx["jboss.as:management-root=server","productVersion"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Uptime |<p>WildFly server uptime.</p> |JMX |jmx["java.lang:type=Runtime","Uptime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|WildFly |WildFly deployment [{#DEPLOYMENT}]: Enabled |<p>Boolean indicating whether the deployment content is currently deployed in the runtime (or should be deployed in the runtime the next time the server starts.)</p> |JMX |jmx["{#JMXOBJ}",enabled]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly deployment [{#DEPLOYMENT}]: Managed |<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p> |JMX |jmx["{#JMXOBJ}",managed]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly domain: Server {#SERVER}: Autostart |<p>Whether or not this server should be started when the Host Controller starts.</p> |JMX |jmx["{#JMXOBJ}",autoStart]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly domain: Server {#SERVER}: Status |<p>The current status of the server.</p> |JMX |jmx["{#JMXOBJ}",status]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly domain: Server {#SERVER}: Server group |<p>The name of a server group from the domain model.</p> |JMX |jmx["{#JMXOBJ}",group]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|WildFly: Version has changed |<p>WildFly version has changed. Ack to close.</p> |`last(/WildFly Domain by JMX/jmx["jboss.as:management-root=server","productVersion"],#1)<>last(/WildFly Domain by JMX/jmx["jboss.as:management-root=server","productVersion"],#2) and length(last(/WildFly Domain by JMX/jmx["jboss.as:management-root=server","productVersion"]))>0` |INFO |<p>Manual close: YES</p> |
|WildFly: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/WildFly Domain by JMX/jmx["java.lang:type=Runtime","Uptime"])<10m` |INFO |<p>Manual close: YES</p> |
|WildFly domain: Server {#SERVER}: Server status has changed |<p>Server status has changed. Ack to close.</p> |`last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",status],#1)<>last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",status],#2) and length(last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",status]))>0` |WARNING |<p>Manual close: YES</p> |
|WildFly domain: Server {#SERVER}: Server group has changed |<p>Server group has changed. Ack to close.</p> |`last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",group],#1)<>last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",group],#2) and length(last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",group]))>0` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

