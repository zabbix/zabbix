
# WildFly Server by JMX

## Overview

Official JMX Template for WildFly server.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- WildFly 22.6.0 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected by JMX.
This template works with standalone and domain instances.

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
|{$WILDFLY.CONN.USAGE.WARN.MAX}|<p>The maximum connection usage percent for trigger expression.</p>|`80`|
|{$WILDFLY.CONN.WAIT.MAX.WARN}|<p>The maximum number of waiting connections for trigger expression.</p>|`300`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly: Launch type|<p>The manner in which the server process was launched. Either "DOMAIN" for a domain mode server launched by a Host Controller, "STANDALONE" for a standalone server launched from the command line, or "EMBEDDED" for a standalone server launched as an embedded part of an application running in the same virtual machine.</p>|JMX agent|jmx["jboss.as:management-root=server","launchType"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Name|<p>For standalone mode: The name of this server. If not set, defaults to the runtime value of InetAddress.getLocalHost().getHostName().</p><p>For domain mode: The name given to this domain.</p>|JMX agent|jmx["jboss.as:management-root=server","name"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Process type|<p>The type of process represented by this root resource.</p>|JMX agent|jmx["jboss.as:management-root=server","processType"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Runtime configuration state|<p>The current persistent configuration state, one of starting, ok, reload-required, restart-required, stopping or stopped.</p>|JMX agent|jmx["jboss.as:management-root=server","runtimeConfigurationState"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Server controller state|<p>The current state of the server controller; either STARTING, RUNNING, RESTART_REQUIRED, RELOAD_REQUIRED or STOPPING.</p>|JMX agent|jmx["jboss.as:management-root=server","serverState"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Version|<p>The version of the WildFly Core based product release.</p>|JMX agent|jmx["jboss.as:management-root=server","productVersion"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly: Uptime|<p>WildFly server uptime.</p>|JMX agent|jmx["java.lang:type=Runtime","Uptime"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|WildFly: Transactions: Total, rate|<p>The total number of transactions (top-level and nested) created per second.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfTransactions"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: Aborted, rate|<p>The number of aborted (i.e. rolledback) transactions per second.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfAbortedTransactions"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: Application rollbacks, rate|<p>The number of transactions that have been rolled back by application request. This includes those that timeout, since the timeout behavior is considered an attribute of the application configuration.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfApplicationRollbacks"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: Committed, rate|<p>The number of committed transactions.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfCommittedTransactions"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: Heuristics, rate|<p>The number of transactions which have terminated with heuristic outcomes.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfHeuristics"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: Current|<p>The number of transactions that have begun but not yet terminated.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfInflightTransactions"]|
|WildFly: Transactions: Nested, rate|<p>The total number of nested (sub) transactions created.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfNestedTransactions"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: ResourceRollbacks, rate|<p>The number of transactions that rolled back due to resource (participant) failure.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfResourceRollbacks"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: System rollbacks, rate|<p>The number of transactions that have been rolled back due to internal system errors.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfSystemRollbacks"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly: Transactions: Timed out, rate|<p>The number of transactions that have rolled back due to timeout.</p>|JMX agent|jmx["jboss.as:subsystem=transactions","numberOfTimedOutTransactions"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WildFly: Server needs to restart for configuration change.||`find(/WildFly Server by JMX/jmx["jboss.as:management-root=server","runtimeConfigurationState"],,"like","ok")=0`|Warning||
|WildFly: Server controller is not in RUNNING state||`find(/WildFly Server by JMX/jmx["jboss.as:management-root=server","serverState"],,"like","running")=0`|Warning|**Depends on**:<br><ul><li>WildFly: Server needs to restart for configuration change.</li></ul>|
|WildFly: Version has changed|<p>WildFly version has changed. Acknowledge to close the problem manually.</p>|`last(/WildFly Server by JMX/jmx["jboss.as:management-root=server","productVersion"],#1)<>last(/WildFly Server by JMX/jmx["jboss.as:management-root=server","productVersion"],#2) and length(last(/WildFly Server by JMX/jmx["jboss.as:management-root=server","productVersion"]))>0`|Info|**Manual close**: Yes|
|WildFly: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/WildFly Server by JMX/jmx["java.lang:type=Runtime","Uptime"])<10m`|Info|**Manual close**: Yes|
|WildFly: Failed to fetch info data|<p>Zabbix has not received data for items for the last 15 minutes</p>|`nodata(/WildFly Server by JMX/jmx["java.lang:type=Runtime","Uptime"],15m)=1`|Warning||

### LLD rule Deployments discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Deployments discovery|<p>Discovery deployments metrics.</p>|JMX agent|jmx.get[beans,"jboss.as.expr:deployment=*"]|

### Item prototypes for Deployments discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly deployment [{#DEPLOYMENT}]: Status|<p>The current runtime status of a deployment.</p><p>Possible status modes are OK, FAILED, and STOPPED.</p><p>FAILED indicates a dependency is missing or a service could not start.</p><p>STOPPED indicates that the deployment was not enabled or was manually stopped.</p>|JMX agent|jmx["{#JMXOBJ}",status]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly deployment [{#DEPLOYMENT}]: Enabled|<p>Boolean indicating whether the deployment content is currently deployed in the runtime (or should be deployed in the runtime the next time the server starts).</p>|JMX agent|jmx["{#JMXOBJ}",enabled]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly deployment [{#DEPLOYMENT}]: Managed|<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p>|JMX agent|jmx["{#JMXOBJ}",managed]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly deployment [{#DEPLOYMENT}]: Persistent|<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p>|JMX agent|jmx["{#JMXOBJ}",persistent]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly deployment [{#DEPLOYMENT}]: Enabled time|<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p>|JMX agent|jmx["{#JMXOBJ}",enabledTime]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Deployments discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WildFly deployment [{#DEPLOYMENT}]: Deployment status has changed|<p>Deployment status has changed. Acknowledge to close the problem manually.</p>|`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",status],#1)<>last(/WildFly Server by JMX/jmx["{#JMXOBJ}",status],#2) and length(last(/WildFly Server by JMX/jmx["{#JMXOBJ}",status]))>0`|Warning|**Manual close**: Yes|

### LLD rule JDBC metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|JDBC metrics discovery||JMX agent|jmx.get[beans,"jboss.as:subsystem=datasources,data-source=*,statistics=jdbc"]|

### Item prototypes for JDBC metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly {#JMX_DATA_SOURCE}: Cache access, rate|<p>The number of times that the statement cache was accessed  per second.</p>|JMX agent|jmx["{#JMXOBJ}",PreparedStatementCacheAccessCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Cache add, rate|<p>The number of statements added to the statement cache per second.</p>|JMX agent|jmx["{#JMXOBJ}",PreparedStatementCacheAddCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Cache current size|<p>The number of prepared and callable statements currently cached in the statement cache.</p>|JMX agent|jmx["{#JMXOBJ}",PreparedStatementCacheCurrentSize]|
|WildFly {#JMX_DATA_SOURCE}: Cache delete, rate|<p>The number of statements discarded from the cache per second.</p>|JMX agent|jmx["{#JMXOBJ}",PreparedStatementCacheDeleteCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Cache hit, rate|<p>The number of times that statements from the cache were used per second.</p>|JMX agent|jmx["{#JMXOBJ}",PreparedStatementCacheHitCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Cache miss, rate|<p>The number of times that a statement request could not be satisfied with a statement from the cache per second.</p>|JMX agent|jmx["{#JMXOBJ}",PreparedStatementCacheMissCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Statistics enabled|<p>Define whether runtime statistics are enabled or not.</p>|JMX agent|jmx["{#JMXOBJ}",statisticsEnabled, "JDBC"]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for JDBC metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WildFly {#JMX_DATA_SOURCE}: JDBC monitoring statistic is not enabled||`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",statisticsEnabled, "JDBC"])=0`|Info||

### LLD rule Pools metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pools metrics discovery||JMX agent|jmx.get[beans,"jboss.as:subsystem=datasources,data-source=*,statistics=pool"]|

### Item prototypes for Pools metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly {#JMX_DATA_SOURCE}: Connections: Active|<p>The number of open connections.</p>|JMX agent|jmx["{#JMXOBJ}",ActiveCount]|
|WildFly {#JMX_DATA_SOURCE}: Connections: Available|<p>The available count.</p>|JMX agent|jmx["{#JMXOBJ}",AvailableCount]|
|WildFly {#JMX_DATA_SOURCE}: Blocking time, avg|<p>Average Blocking Time for pool.</p>|JMX agent|jmx["{#JMXOBJ}",AverageBlockingTime]|
|WildFly {#JMX_DATA_SOURCE}: Connections: Creating time, avg|<p>The average time spent creating a physical connection.</p>|JMX agent|jmx["{#JMXOBJ}",AverageCreationTime]|
|WildFly {#JMX_DATA_SOURCE}: Connections: Get time, avg|<p>The average time spent obtaining a physical connection.</p>|JMX agent|jmx["{#JMXOBJ}",AverageGetTime]|
|WildFly {#JMX_DATA_SOURCE}: Connections: Pool time, avg|<p>The average time for a physical connection spent in the pool.</p>|JMX agent|jmx["{#JMXOBJ}",AveragePoolTime]|
|WildFly {#JMX_DATA_SOURCE}: Connections: Usage time, avg|<p>The average time spent using a physical connection</p>|JMX agent|jmx["{#JMXOBJ}",AverageUsageTime]|
|WildFly {#JMX_DATA_SOURCE}: Connections: Blocking failure, rate|<p>The number of failures trying to obtain a physical connection per second.</p>|JMX agent|jmx["{#JMXOBJ}",BlockingFailureCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Connections: Created, rate|<p>The created per second</p>|JMX agent|jmx["{#JMXOBJ}",CreatedCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Connections: Destroyed, rate|<p>The destroyed count.</p>|JMX agent|jmx["{#JMXOBJ}",DestroyedCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Connections: Idle|<p>The number of physical connections currently idle.</p>|JMX agent|jmx["{#JMXOBJ}",IdleCount]|
|WildFly {#JMX_DATA_SOURCE}: Connections: In use|<p>The number of physical connections currently in use.</p>|JMX agent|jmx["{#JMXOBJ}",InUseCount]|
|WildFly {#JMX_DATA_SOURCE}: Connections: Used, max|<p>The maximum number of connections used.</p>|JMX agent|jmx["{#JMXOBJ}",MaxUsedCount]|
|WildFly {#JMX_DATA_SOURCE}: Statistics enabled|<p>Define whether runtime statistics are enabled or not.</p>|JMX agent|jmx["{#JMXOBJ}",statisticsEnabled]<p>**Preprocessing**</p><ul><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Connections: Timed out, rate|<p>The timed out connections per second.</p>|JMX agent|jmx["{#JMXOBJ}",TimedOut]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: Connections: Wait|<p>The number of requests that had to wait to obtain a physical connection.</p>|JMX agent|jmx["{#JMXOBJ}",WaitCount]|
|WildFly {#JMX_DATA_SOURCE}: XA: Commit time, avg|<p>The average time for a XAResource commit invocation.</p>|JMX agent|jmx["{#JMXOBJ}",XACommitAverageTime]|
|WildFly {#JMX_DATA_SOURCE}: XA: Commit, rate|<p>The number of XAResource commit invocations per second.</p>|JMX agent|jmx["{#JMXOBJ}",XACommitCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: XA: End time, avg|<p>The average time for a XAResource end invocation.</p>|JMX agent|jmx["{#JMXOBJ}",XAEndAverageTime]|
|WildFly {#JMX_DATA_SOURCE}: XA: End, rate|<p>The number of XAResource end invocations per second.</p>|JMX agent|jmx["{#JMXOBJ}",XAEndCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: XA: Forget time, avg|<p>The average time for a XAResource forget invocation.</p>|JMX agent|jmx["{#JMXOBJ}",XAForgetAverageTime]|
|WildFly {#JMX_DATA_SOURCE}: XA: Forget, rate|<p>The number of XAResource forget invocations per second.</p>|JMX agent|jmx["{#JMXOBJ}",XAForgetCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: XA: Prepare time, avg|<p>The average time for a XAResource prepare invocation.</p>|JMX agent|jmx["{#JMXOBJ}",XAPrepareAverageTime]|
|WildFly {#JMX_DATA_SOURCE}: XA: Prepare, rate|<p>The number of XAResource prepare invocations per second.</p>|JMX agent|jmx["{#JMXOBJ}",XAPrepareCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: XA: Recover time, avg|<p>The average time for a XAResource recover invocation.</p>|JMX agent|jmx["{#JMXOBJ}",XARecoverAverageTime]|
|WildFly {#JMX_DATA_SOURCE}: XA: Recover, rate|<p>The number of XAResource recover invocations per second.</p>|JMX agent|jmx["{#JMXOBJ}",XARecoverCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: XA: Rollback time, avg|<p>The average time for a XAResource rollback invocation.</p>|JMX agent|jmx["{#JMXOBJ}",XARollbackAverageTime]|
|WildFly {#JMX_DATA_SOURCE}: XA: Rollback, rate|<p>The number of XAResource rollback invocations per second.</p>|JMX agent|jmx["{#JMXOBJ}",XARollbackCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly {#JMX_DATA_SOURCE}: XA: Start time, avg|<p>The average time for a XAResource start invocation.</p>|JMX agent|jmx["{#JMXOBJ}",XAStartAverageTime]|
|WildFly {#JMX_DATA_SOURCE}: XA: Start rate|<p>The number of XAResource start invocations per second.</p>|JMX agent|jmx["{#JMXOBJ}",XAStartCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Trigger prototypes for Pools metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WildFly {#JMX_DATA_SOURCE}: There are no active connections for 5m||`max(/WildFly Server by JMX/jmx["{#JMXOBJ}",ActiveCount],5m)=0`|Warning||
|WildFly {#JMX_DATA_SOURCE}: Connection usage is too high||`min(/WildFly Server by JMX/jmx["{#JMXOBJ}",InUseCount],5m)/last(/WildFly Server by JMX/jmx["{#JMXOBJ}",AvailableCount])*100>{$WILDFLY.CONN.USAGE.WARN.MAX}`|High||
|WildFly {#JMX_DATA_SOURCE}: Pools monitoring statistic is not enabled|<p>Zabbix has not received data for items for the last 15 minutes</p>|`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",statisticsEnabled])=0`|Info||
|WildFly {#JMX_DATA_SOURCE}: There are timeout connections||`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",TimedOut])>0`|Warning||
|WildFly {#JMX_DATA_SOURCE}: Too many waiting connections||`min(/WildFly Server by JMX/jmx["{#JMXOBJ}",WaitCount],5m)>{$WILDFLY.CONN.WAIT.MAX.WARN}`|Warning||

### LLD rule Undertow metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Undertow metrics discovery||JMX agent|jmx.get[beans,"jboss.as:subsystem=undertow,server=*,http-listener=*"]|

### Item prototypes for Undertow metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WildFly listener {#HTTP_LISTENER}: Errors, rate|<p>The number of 500 responses that have been sent by this listener per second.</p>|JMX agent|jmx["{#JMXOBJ}",errorCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly listener {#HTTP_LISTENER}: Requests, rate|<p>The number of requests this listener has served per second.</p>|JMX agent|jmx["{#JMXOBJ}",requestCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly listener {#HTTP_LISTENER}: Bytes sent, rate|<p>The number of bytes that have been sent out on this listener per second.</p>|JMX agent|jmx["{#JMXOBJ}",bytesSent]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|WildFly listener {#HTTP_LISTENER}: Bytes received, rate|<p>The number of bytes that have been received by this listener per second.</p>|JMX agent|jmx["{#JMXOBJ}",bytesReceived]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Trigger prototypes for Undertow metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WildFly listener {#HTTP_LISTENER}: There are 500 responses by this listener.||`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",errorCount])>0`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

