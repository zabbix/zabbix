
# WildFly Server by JMX

## Overview

For Zabbix version: 6.0 and higher  
Official JMX Template for WildFly server.


This template was tested on:

- WildFly, version 22.6.0

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/jmx) for basic instructions.

Metrics are collected by JMX.
This template works with standalone and domain instances.

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
|{$WILDFLY.CONN.USAGE.WARN.MAX} |<p>The maximum connection usage percent for trigger expression.</p> |`80` |
|{$WILDFLY.CONN.WAIT.MAX.WARN} |<p>The maximum number of waiting connections for trigger expression.</p> |`300` |
|{$WILDFLY.DEPLOYMENT.MATCHES} |<p>Filter of discoverable deployments</p> |`.*` |
|{$WILDFLY.DEPLOYMENT.NOT_MATCHES} |<p>Filter to exclude discovered deployments</p> |`CHANGE_IF_NEEDED` |
|{$WILDFLY.JMX.PROTOCOL} |<p>-</p> |`remote+http` |
|{$WILDFLY.PASSWORD} |<p>-</p> |`zabbix` |
|{$WILDFLY.USER} |<p>-</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Deployments discovery |<p>Discovery deployments metrics.</p> |JMX |jmx.get[beans,"jboss.as.expr:deployment=*"]<p>**Filter**:</p>AND <p>- {#DEPLOYMENT} MATCHES_REGEX `{$WILDFLY.DEPLOYMENT.MATCHES}`</p><p>- {#DEPLOYMENT} NOT_MATCHES_REGEX `{$WILDFLY.DEPLOYMENT.NOT_MATCHES}`</p> |
|JDBC metrics discovery |<p>-</p> |JMX |jmx.get[beans,"jboss.as:subsystem=datasources,data-source=*,statistics=jdbc"] |
|Pools metrics discovery |<p>-</p> |JMX |jmx.get[beans,"jboss.as:subsystem=datasources,data-source=*,statistics=pool"] |
|Undertow metrics discovery |<p>-</p> |JMX |jmx.get[beans,"jboss.as:subsystem=undertow,server=*,http-listener=*"] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|WildFly |WildFly: Launch type |<p>The manner in which the server process was launched. Either "DOMAIN" for a domain mode server launched by a Host Controller, "STANDALONE" for a standalone server launched from the command line, or "EMBEDDED" for a standalone server launched as an embedded part of an application running in the same virtual machine.</p> |JMX |jmx["jboss.as:management-root=server","launchType"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Name |<p>For standalone mode: The name of this server. If not set, defaults to the runtime value of InetAddress.getLocalHost().getHostName().</p><p>For domain mode: The name given to this domain</p> |JMX |jmx["jboss.as:management-root=server","name"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Process type |<p>The type of process represented by this root resource.</p> |JMX |jmx["jboss.as:management-root=server","processType"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Runtime configuration state |<p>The current persistent configuration state, one of starting, ok, reload-required, restart-required, stopping or stopped.</p> |JMX |jmx["jboss.as:management-root=server","runtimeConfigurationState"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Server controller state |<p>The current state of the server controller; either STARTING, RUNNING, RESTART_REQUIRED, RELOAD_REQUIRED or STOPPING.</p> |JMX |jmx["jboss.as:management-root=server","serverState"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Version |<p>The version of the WildFly Core based product release</p> |JMX |jmx["jboss.as:management-root=server","productVersion"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly: Uptime |<p>WildFly server uptime.</p> |JMX |jmx["java.lang:type=Runtime","Uptime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|WildFly |WildFly: Transactions: Total, rate |<p>The total number of transactions (top-level and nested) created per second.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfTransactions"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: Aborted, rate |<p>The number of aborted (i.e. rolledback) transactions per second.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfAbortedTransactions"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: Application rollbacks, rate |<p>The number of transactions that have been rolled back by application request. This includes those that timeout, since the timeout behavior is considered an attribute of the application configuration.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfApplicationRollbacks"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: Committed, rate |<p>The number of committed transactions</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfCommittedTransactions"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: Heuristics, rate |<p>The number of transactions which have terminated with heuristic outcomes.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfHeuristics"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: Current |<p>The number of transactions that have begun but not yet terminated.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfInflightTransactions"] |
|WildFly |WildFly: Transactions: Nested, rate |<p>The total number of nested (sub) transactions created.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfNestedTransactions"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: ResourceRollbacks, rate |<p>The number of transactions that rolled back due to resource (participant) failure.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfResourceRollbacks"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: System rollbacks, rate |<p>The number of transactions that have been rolled back due to internal system errors.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfSystemRollbacks"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly: Transactions: Timed out, rate |<p>The number of transactions that have rolled back due to timeout.</p> |JMX |jmx["jboss.as:subsystem=transactions","numberOfTimedOutTransactions"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly deployment [{#DEPLOYMENT}]: Status |<p>The current runtime status of a deployment.</p><p>Possible status modes are OK, FAILED, and STOPPED.</p><p>FAILED indicates a dependency is missing or a service could not start.</p><p>STOPPED indicates that the deployment was not enabled or was manually stopped.</p> |JMX |jmx["{#JMXOBJ}",status]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly deployment [{#DEPLOYMENT}]: Enabled |<p>Boolean indicating whether the deployment content is currently deployed in the runtime (or should be deployed in the runtime the next time the server starts.)</p> |JMX |jmx["{#JMXOBJ}",enabled]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly deployment [{#DEPLOYMENT}]: Managed |<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p> |JMX |jmx["{#JMXOBJ}",managed]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly deployment [{#DEPLOYMENT}]: Persistent |<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p> |JMX |jmx["{#JMXOBJ}",persistent]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly deployment [{#DEPLOYMENT}]: Enabled time |<p>Indicates if the deployment is managed (aka uses the ContentRepository).</p> |JMX |jmx["{#JMXOBJ}",enabledTime]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Cache access, rate |<p>The number of times that the statement cache was accessed  per second.</p> |JMX |jmx["{#JMXOBJ}",PreparedStatementCacheAccessCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Cache add, rate |<p>The number of statements added to the statement cache per second.</p> |JMX |jmx["{#JMXOBJ}",PreparedStatementCacheAddCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Cache current size |<p>The number of prepared and callable statements currently cached in the statement cache.</p> |JMX |jmx["{#JMXOBJ}",PreparedStatementCacheCurrentSize] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Cache delete, rate |<p>The number of statements discarded from the cache per second.</p> |JMX |jmx["{#JMXOBJ}",PreparedStatementCacheDeleteCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Cache hit, rate |<p>The number of times that statements from the cache were used per second.</p> |JMX |jmx["{#JMXOBJ}",PreparedStatementCacheHitCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Cache miss, rate |<p>The number of times that a statement request could not be satisfied with a statement from the cache per second.</p> |JMX |jmx["{#JMXOBJ}",PreparedStatementCacheMissCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Statistics enabled |<p>Define whether runtime statistics are enabled or not.</p> |JMX |jmx["{#JMXOBJ}",statisticsEnabled]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Active |<p>The number of open connections.</p> |JMX |jmx["{#JMXOBJ}",ActiveCount] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Available |<p>The available count.</p> |JMX |jmx["{#JMXOBJ}",AvailableCount] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Blocking time, avg |<p>Average Blocking Time for pool.</p> |JMX |jmx["{#JMXOBJ}",AverageBlockingTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Creating time, avg |<p>The average time spent creating a physical connection.</p> |JMX |jmx["{#JMXOBJ}",AverageCreationTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Get time, avg |<p>The average time spent obtaining a physical connection.</p> |JMX |jmx["{#JMXOBJ}",AverageGetTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Pool time, avg |<p>The average time for a physical connection spent in the pool.</p> |JMX |jmx["{#JMXOBJ}",AveragePoolTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Usage time, avg |<p>The average time spent using a physical connection</p> |JMX |jmx["{#JMXOBJ}",AverageUsageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Blocking failure, rate |<p>The number of failures trying to obtain a physical connection per second.</p> |JMX |jmx["{#JMXOBJ}",BlockingFailureCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Created, rate |<p>The created per second</p> |JMX |jmx["{#JMXOBJ}",CreatedCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Destroyed, rate |<p>The destroyed count.</p> |JMX |jmx["{#JMXOBJ}",DestroyedCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Idle |<p>The number of physical connections currently idle.</p> |JMX |jmx["{#JMXOBJ}",IdleCount] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: In use |<p>The number of physical connections currently in use.</p> |JMX |jmx["{#JMXOBJ}",InUseCount] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Used, max |<p>The maximum number of connections used.</p> |JMX |jmx["{#JMXOBJ}",MaxUsedCount] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Statistics enabled |<p>Define whether runtime statistics are enabled or not.</p> |JMX |jmx["{#JMXOBJ}",statisticsEnabled]<p>**Preprocessing**:</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Timed out, rate |<p>The timed out connections per second.</p> |JMX |jmx["{#JMXOBJ}",TimedOut]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: Connections: Wait |<p>The number of requests that had to wait to obtain a physical connection.</p> |JMX |jmx["{#JMXOBJ}",WaitCount] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Commit time, avg |<p>The average time for a XAResource commit invocation.</p> |JMX |jmx["{#JMXOBJ}",XACommitAverageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Commit, rate |<p>The number of XAResource commit invocations per second.</p> |JMX |jmx["{#JMXOBJ}",XACommitCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: End time, avg |<p>The average time for a XAResource end invocation.</p> |JMX |jmx["{#JMXOBJ}",XAEndAverageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: End, rate |<p>The number of XAResource end invocations per second.</p> |JMX |jmx["{#JMXOBJ}",XAEndCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Forget time, avg |<p>The average time for a XAResource forget invocation.</p> |JMX |jmx["{#JMXOBJ}",XAForgetAverageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Forget, rate |<p>The number of XAResource forget invocations per second.</p> |JMX |jmx["{#JMXOBJ}",XAForgetCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Prepare time, avg |<p>The average time for a XAResource prepare invocation.</p> |JMX |jmx["{#JMXOBJ}",XAPrepareAverageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Prepare, rate |<p>The number of XAResource prepare invocations per second.</p> |JMX |jmx["{#JMXOBJ}",XAPrepareCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Recover time, avg |<p>The average time for a XAResource recover invocation.</p> |JMX |jmx["{#JMXOBJ}",XARecoverAverageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Recover, rate |<p>The number of XAResource recover invocations per second.</p> |JMX |jmx["{#JMXOBJ}",XARecoverCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Rollback time, avg |<p>The average time for a XAResource rollback invocation.</p> |JMX |jmx["{#JMXOBJ}",XARollbackAverageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Rollback, rate |<p>The number of XAResource rollback invocations per second.</p> |JMX |jmx["{#JMXOBJ}",XARollbackCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Start time, avg |<p>The average time for a XAResource start invocation.</p> |JMX |jmx["{#JMXOBJ}",XAStartAverageTime] |
|WildFly |WildFly {#JMX_DATA_SOURCE}: XA: Start rate |<p>The number of XAResource start invocations per second.</p> |JMX |jmx["{#JMXOBJ}",XAStartCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly listener {#HTTP_LISTENER}: Errors, rate |<p>The number of 500 responses that have been sent by this listener per second.</p> |JMX |jmx["{#JMXOBJ}",errorCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly listener {#HTTP_LISTENER}: Requests, rate |<p>The number of requests this listener has served per second.</p> |JMX |jmx["{#JMXOBJ}",requestCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly listener {#HTTP_LISTENER}: Bytes sent, rate |<p>The number of bytes that have been sent out on this listener per second.</p> |JMX |jmx["{#JMXOBJ}",bytesSent]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|WildFly |WildFly listener {#HTTP_LISTENER}: Bytes received, rate |<p>The number of bytes that have been received by this listener per second.</p> |JMX |jmx["{#JMXOBJ}",bytesReceived]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|WildFly: Server needs to restart for configuration change. |<p>-</p> |`find(/WildFly Server by JMX/jmx["jboss.as:management-root=server","runtimeConfigurationState"],,"like","ok")=0` |WARNING | |
|WildFly: Server controller is not in RUNNING state |<p>-</p> |`find(/WildFly Server by JMX/jmx["jboss.as:management-root=server","serverState"],,"like","running")=0` |WARNING |<p>**Depends on**:</p><p>- WildFly: Server needs to restart for configuration change.</p> |
|WildFly: Version has changed |<p>WildFly version has changed. Ack to close.</p> |`last(/WildFly Server by JMX/jmx["jboss.as:management-root=server","productVersion"],#1)<>last(/WildFly Server by JMX/jmx["jboss.as:management-root=server","productVersion"],#2) and length(last(/WildFly Server by JMX/jmx["jboss.as:management-root=server","productVersion"]))>0` |INFO |<p>Manual close: YES</p> |
|WildFly: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/WildFly Server by JMX/jmx["java.lang:type=Runtime","Uptime"])<10m` |INFO |<p>Manual close: YES</p> |
|WildFly: Failed to fetch info data |<p>Zabbix has not received data for items for the last 15 minutes</p> |`nodata(/WildFly Server by JMX/jmx["java.lang:type=Runtime","Uptime"],15m)=1` |WARNING | |
|WildFly deployment [{#DEPLOYMENT}]: Deployment status has changed |<p>Deployment status has changed. Ack to close.</p> |`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",status],#1)<>last(/WildFly Server by JMX/jmx["{#JMXOBJ}",status],#2) and length(last(/WildFly Server by JMX/jmx["{#JMXOBJ}",status]))>0` |WARNING |<p>Manual close: YES</p> |
|WildFly {#JMX_DATA_SOURCE}: JDBC monitoring statistic is not enabled |<p>-</p> |`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",statisticsEnabled])=0` |INFO | |
|WildFly {#JMX_DATA_SOURCE}: There are no active connections for 5m |<p>-</p> |`max(/WildFly Server by JMX/jmx["{#JMXOBJ}",ActiveCount],5m)=0` |WARNING | |
|WildFly {#JMX_DATA_SOURCE}: Connection usage is too high |<p>-</p> |`min(/WildFly Server by JMX/jmx["{#JMXOBJ}",InUseCount],5m)/last(/WildFly Server by JMX/jmx["{#JMXOBJ}",AvailableCount])*100>{$WILDFLY.CONN.USAGE.WARN.MAX}` |HIGH | |
|WildFly {#JMX_DATA_SOURCE}: Pools monitoring statistic is not enabled |<p>Zabbix has not received data for items for the last 15 minutes</p> |`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",statisticsEnabled])=0` |INFO | |
|WildFly {#JMX_DATA_SOURCE}: There are timeout connections |<p>-</p> |`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",TimedOut])>0` |WARNING | |
|WildFly {#JMX_DATA_SOURCE}: Too many waiting connections |<p>-</p> |`min(/WildFly Server by JMX/jmx["{#JMXOBJ}",WaitCount],5m)>{$WILDFLY.CONN.WAIT.MAX.WARN}` |WARNING | |
|WildFly listener {#HTTP_LISTENER}: There are 500 responses by this listener. |<p>-</p> |`last(/WildFly Server by JMX/jmx["{#JMXOBJ}",errorCount])>0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

