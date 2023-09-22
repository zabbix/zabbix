
# WildFly Domain by JMX

## Overview

Official JMX Template for WildFly Domain Controller.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- WildFly 22.6.0 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected by JMX.
This template works with Domain Controller.

1. Enable and configure JMX access to WildFly. See documentation for [instructions](https://docs.wildfly.org/23/Admin_Guide.html#JMX).
2. Copy jboss-client.jar from `/(wildfly,EAP,Jboss,AS)/bin/client` in to directory `/usr/share/zabbix-java-gateway/lib`
3. Restart Zabbix Java gateway
4. Set the user name and password in host macros {$WILDFLY.USER} and {$WILDFLY.PASSWORD}.
Depending on your server setup, you may need to specify a custom JMX scheme in macro {$WILDFLY.JMX.PROTOCOL} (default: remote+http)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$WILDFLY.USER}||`zabbix`|
|{$WILDFLY.PASSWORD}||`zabbix`|
|{$WILDFLY.JMX.PROTOCOL}||`remote+http`|
|{$WILDFLY.DEPLOYMENT.MATCHES}|<p>Filter of discoverable deployments</p>|`.*`|
|{$WILDFLY.DEPLOYMENT.NOT_MATCHES}|<p>Filter to exclude discovered deployments</p>|`CHANGE_IF_NEEDED`|
|{$WILDFLY.SERVER.MATCHES}|<p>Filter of discoverable servers</p>|`.*`|
|{$WILDFLY.SERVER.NOT_MATCHES}|<p>Filter to exclude discovered servers</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly: Launch type|<p>The manner in which the server process was launched. Either "DOMAIN" for a domain mode server launched by a Host Controller, "STANDALONE" for a standalone server launched from the command line, or "EMBEDDED" for a standalone server launched as an embedded part of an application running in the same virtual machine.</p>|JMX agent|jmx["jboss.as:management-root=server","launchType"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Name|<p>For standalone mode: The name of this server. If not set, defaults to the runtime value of InetAddress.getLocalHost().getHostName().</p><p>For domain mode: The name given to this domain</p>|JMX agent|jmx["jboss.as:management-root=server","name"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Process type|<p>The type of process represented by this root resource.</p>|JMX agent|jmx["jboss.as:management-root=server","processType"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Version|<p>The version of the WildFly Core based product release.</p>|JMX agent|jmx["jboss.as:management-root=server","productVersion"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Uptime|<p>WildFly server uptime.</p>|JMX agent|jmx["java.lang:type=Runtime","Uptime"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WildFly: Version has changed|<p>WildFly version has changed. Acknowledge to close the problem manually.</p>|`last(/WildFly Domain by JMX/jmx["jboss.as:management-root=server","productVersion"],#1)<>last(/WildFly Domain by JMX/jmx["jboss.as:management-root=server","productVersion"],#2) and length(last(/WildFly Domain by JMX/jmx["jboss.as:management-root=server","productVersion"]))>0`|Info|**Manual close**: Yes|
|WildFly: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/WildFly Domain by JMX/jmx["java.lang:type=Runtime","Uptime"])<10m`|Info|**Manual close**: Yes|

### LLD rule Deployments discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Deployments discovery|<p>Discovery deployments metrics.</p>|JMX agent|jmx.get[beans,"jboss.as.expr:deployment=*,server-group=*"]|

### Item prototypes for Deployments discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly deployment [{#DEPLOYMENT}]: Enabled|<p>Boolean indicating whether the deployment content is currently deployed in the runtime (or should be deployed in the runtime the next time the server starts).</p>|JMX agent|jmx["{#JMXOBJ}",enabled]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly deployment [{#DEPLOYMENT}]: Managed|<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p>|JMX agent|jmx["{#JMXOBJ}",managed]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule Servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Servers discovery|<p>Discovery instances in domain.</p>|JMX agent|jmx.get[beans,"jboss.as:host=master,server-config=*"]|

### Item prototypes for Servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly domain: Server {#SERVER}: Autostart|<p>Whether or not this server should be started when the Host Controller starts.</p>|JMX agent|jmx["{#JMXOBJ}",autoStart]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly domain: Server {#SERVER}: Status|<p>The current status of the server.</p>|JMX agent|jmx["{#JMXOBJ}",status]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly domain: Server {#SERVER}: Server group|<p>The name of a server group from the domain model.</p>|JMX agent|jmx["{#JMXOBJ}",group]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Servers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WildFly domain: Server {#SERVER}: Server status has changed|<p>Server status has changed. Acknowledge to close the problem manually.</p>|`last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",status],#1)<>last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",status],#2) and length(last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",status]))>0`|Warning|**Manual close**: Yes|
|WildFly domain: Server {#SERVER}: Server group has changed|<p>Server group has changed. Acknowledge to close the problem manually.</p>|`last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",group],#1)<>last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",group],#2) and length(last(/WildFly Domain by JMX/jmx["{#JMXOBJ}",group]))>0`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

