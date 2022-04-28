
# Microsoft Exchange Server 2016 by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  
Official Template for Microsoft Exchange Server 2016.


This template was tested on:

- Microsoft Exchange Server, version 2016 CU18

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Metrics are collected by Zabbix agent.

1\. Import the template into Zabbix.

2\. Link the imported template to a host with MS Exchange.

Note that template doesn't provide information about Windows services state. Recommended to use it with "OS Windows by Zabbix agent" template.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MS.EXCHANGE.DB.ACTIVE.READ.TIME} |<p>The time during which the active database read operations latency may exceed the threshold.</p> |`5m` |
|{$MS.EXCHANGE.DB.ACTIVE.READ.WARN} |<p>Threshold for active database read operations latency trigger.</p> |`0.02` |
|{$MS.EXCHANGE.DB.ACTIVE.WRITE.TIME} |<p>The time during which the active database write operations latency may exceed the threshold.</p> |`10m` |
|{$MS.EXCHANGE.DB.ACTIVE.WRITE.WARN} |<p>Threshold for active database write operations latency trigger.</p> |`0.05` |
|{$MS.EXCHANGE.DB.FAULTS.TIME} |<p>The time during which the database page faults may exceed the threshold.</p> |`5m` |
|{$MS.EXCHANGE.DB.FAULTS.WARN} |<p>Threshold for database page faults trigger.</p> |`0` |
|{$MS.EXCHANGE.DB.PASSIVE.READ.TIME} |<p>The time during which the passive database read operations latency may exceed the threshold.</p> |`5m` |
|{$MS.EXCHANGE.DB.PASSIVE.READ.WARN} |<p>Threshold for passive database read operations latency trigger.</p> |`0.2` |
|{$MS.EXCHANGE.DB.PASSIVE.WRITE.TIME} |<p>The time during which the passive database write operations latency may exceed the threshold.</p> |`10m` |
|{$MS.EXCHANGE.LDAP.TIME} |<p>The time during which the LDAP metrics may exceed the threshold.</p> |`5m` |
|{$MS.EXCHANGE.LDAP.WARN} |<p>Threshold for LDAP triggers.</p> |`0.05` |
|{$MS.EXCHANGE.LOG.STALLS.TIME} |<p>The time during which the log records stalled may exceed the threshold.</p> |`10m` |
|{$MS.EXCHANGE.LOG.STALLS.WARN} |<p>Threshold for log records stalled trigger.</p> |`100` |
|{$MS.EXCHANGE.PERF.INTERVAL} |<p>Update interval for perf_counter_en items.</p> |`60` |
|{$MS.EXCHANGE.RPC.COUNT.TIME} |<p>The time during which the RPC total requests may exceed the threshold.</p> |`5m` |
|{$MS.EXCHANGE.RPC.COUNT.WARN} |<p>Threshold for LDAP triggers.</p> |`70` |
|{$MS.EXCHANGE.RPC.TIME} |<p>The time during which the RPC requests latency may exceed the threshold.</p> |`10m` |
|{$MS.EXCHANGE.RPC.WARN} |<p>Threshold for RPC requests latency trigger.</p> |`0.05` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Databases discovery |<p>Discovery of Exchange databases.</p> |ZABBIX_PASSIVE |perf_instance.discovery["MSExchange Active Manager"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|LDAP discovery |<p>Discovery of domain controller.</p> |ZABBIX_PASSIVE |perf_instance_en.discovery["MSExchange ADAccess Domain Controllers"] |
|Web services discovery |<p>Discovery of Exchange web services.</p> |ZABBIX_PASSIVE |perf_instance_en.discovery["Web Service"] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|MS Exchange |MS Exchange: Databases total mounted |<p>Shows the number of active database copies on the server.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Active Manager(_total)\Database Mounted"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|MS Exchange |MS Exchange [Client Access Server]: ActiveSync: ping command pending |<p>Shows the number of ping commands currently pending in the queue.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange ActiveSync\Ping Commands Pending", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |MS Exchange [Client Access Server]: ActiveSync: requests per second |<p>Shows the number of HTTP requests received from the client via ASP.NET per second. Determines the current Exchange ActiveSync request rate. Used only to determine current user load.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange ActiveSync\Requests/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |MS Exchange [Client Access Server]: ActiveSync: sync commands per second |<p>Shows the number of sync commands processed per second. Clients use this command to synchronize items within a folder.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange ActiveSync\Sync Commands/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |MS Exchange [Client Access Server]: Autodiscover: requests per second |<p>Shows the number of Autodiscover service requests processed each second. Determines current user load.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchangeAutodiscover\Requests/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |MS Exchange [Client Access Server]: Availability Service: availability requests per second |<p>Shows the number of requests serviced per second. The request can be only for free/ busy information or include suggestions. One request may contain multiple mailboxes. Determines the rate at which Availability service requests are occurring.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Availability Service\Availability Requests (sec)", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |MS Exchange [Client Access Server]: Outlook Web App: current unique users |<p>Shows the number of unique users currently logged on to Outlook Web App. This value monitors the number of unique active user sessions, so that users are only removed from this counter after they log off or their session times out. Determines current user load.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange OWA\Current Unique Users", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |MS Exchange [Client Access Server]: Outlook Web App: requests per second |<p>Shows the number of requests handled by Outlook Web App per second. Determines current user load.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange OWA\Requests/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |MS Exchange [Client Access Server]: MSExchangeWS: requests per second |<p>Shows the number of requests processed each second. Determines current user load.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchangeWS\Requests/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Active Manager [{#INSTANCE}]: Database copy role |<p>Database copy active or passive role.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Active Manager({#INSTANCE})\Database Copy Role Active"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|MS Exchange |Information Store [{#INSTANCE}]: Database state |<p>Database state. Possible values:</p><p>0: Database without any copy and dismounted.</p><p>1: Database is a primary database and mounted.</p><p>2: Database is a passive copy and the state is healthy.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchangeIS Store({#INSTANCE})\Database State"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3m`</p> |
|MS Exchange |Information Store [{#INSTANCE}]: Active mailboxes count |<p>Number of active mailboxes in this database.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchangeIS Store({#INSTANCE})\Active mailboxes"] |
|MS Exchange |Information Store [{#INSTANCE}]: Page faults per second |<p>Indicates the rate of page faults that can't be serviced because there are no pages available for allocation from the database cache. If this counter is above 0, it's an indication that the MSExchange Database\I/O Database Writes (Attached) Average Latency is too high.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database({#INF.STORE})\Database Page Fault Stalls/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Information Store [{#INSTANCE}]: Log records stalled |<p>Indicates the number of log records that can't be added to the log buffers per second because the log buffers are full. The average value should be below 10 per second. Spikes (maximum values) shouldn't be higher than 100 per second.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database({#INF.STORE})\Log Record Stalls/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Information Store [{#INSTANCE}]: Log threads waiting |<p>Indicates the number of threads waiting to complete an update of the database by writing their data to the log.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database({#INF.STORE})\Log Threads Waiting", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Information Store [{#INSTANCE}]: RPC requests per second |<p>Shows the number of RPC operations per second for each database instance.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchangeIS Store({#INSTANCE})\RPC Operations/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Information Store [{#INSTANCE}]: RPC requests latency |<p>RPC Latency average is the average latency of RPC requests per database. Average is calculated over all RPCs since exrpc32 was loaded. Should be less than 50ms at all times, with spikes less than 100ms.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchangeIS Store({#INSTANCE})\RPC Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|MS Exchange |Information Store [{#INSTANCE}]: RPC requests total |<p>Indicates the overall RPC requests currently executing within the information store process. Should be below 70 at all times.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchangeIS Store({#INSTANCE})\RPC requests", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Database Counters [{#INSTANCE}]: Active database read operations per second |<p>Shows the number of database read operations.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Attached)/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Database Counters [{#INSTANCE}]: Active database read operations latency |<p>Shows the average length of time per database read operation. Should be less than 20 ms on average.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Attached) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|MS Exchange |Database Counters [{#INSTANCE}]: Passive database read operations latency |<p>Shows the average length of time per passive database read operation. Should be less than 200ms on average.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Recovery) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|MS Exchange |Database Counters [{#INSTANCE}]: Active database write operations per second |<p>Shows the number of database write operations per second for each attached database instance.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Writes (Attached)/sec", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Database Counters [{#INSTANCE}]: Active database write operations latency |<p>Shows the average length of time per database write operation. Should be less than 50ms on average.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Writes (Attached) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|MS Exchange |Database Counters [{#INSTANCE}]: Passive database write operations latency |<p>Shows the average length of time, in ms, per passive database write operation. Should be less than the read latency for the same instance, as measured by the MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Recovery) Average Latency counter.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Writes (Recovery) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|MS Exchange |Web Service [{#INSTANCE}]: Current connections |<p>Shows the current number of connections established to the each Web Service.</p> |ZABBIX_PASSIVE |perf_counter_en["\Web Service({#INSTANCE})\Current Connections", {$MS.EXCHANGE.PERF.INTERVAL}] |
|MS Exchange |Domain Controller [{#INSTANCE}]: Read time |<p>Time that it takes to send an LDAP read request to the domain controller in question and get a response. Should ideally be below 50 ms; spikes below 100 ms are acceptable.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange ADAccess Domain Controllers({#INSTANCE})\LDAP Read Time", {$MS.EXCHANGE.PERF.INTERVAL}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|MS Exchange |Domain Controller [{#INSTANCE}]: Search time |<p>Time that it takes to send an LDAP search request and get a response. Should ideally be below 50 ms; spikes below 100 ms are acceptable.</p> |ZABBIX_PASSIVE |perf_counter_en["\MSExchange ADAccess Domain Controllers({#INSTANCE})\LDAP Search Time", {$MS.EXCHANGE.PERF.INTERVAL}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Information Store [{#INSTANCE}]: Page faults is too high |<p>Too much page faults stalls for database "{#INSTANCE}". This counter should be 0 on production servers.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange Database({#INF.STORE})\Database Page Fault Stalls/sec", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.DB.FAULTS.TIME})>{$MS.EXCHANGE.DB.FAULTS.WARN}` |AVERAGE | |
|Information Store [{#INSTANCE}]: Log records stalls is too high |<p>Stalled log records too high. The average value should be less than 10 threads waiting.</p> |`avg(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange Database({#INF.STORE})\Log Record Stalls/sec", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.LOG.STALLS.TIME})>{$MS.EXCHANGE.LOG.STALLS.WARN}` |AVERAGE | |
|Information Store [{#INSTANCE}]: RPC Requests latency is too high |<p>Should be less than 50ms at all times, with spikes less than 100ms.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchangeIS Store({#INSTANCE})\RPC Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.RPC.TIME})>{$MS.EXCHANGE.RPC.WARN}` |WARNING | |
|Information Store [{#INSTANCE}]: RPC Requests total count is too high |<p>Should be below 70 at all times.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchangeIS Store({#INSTANCE})\RPC requests", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.RPC.COUNT.TIME})>{$MS.EXCHANGE.RPC.COUNT.WARN}` |WARNING | |
|Database Counters [{#INSTANCE}]: Average read time latency is too high |<p>Should be less than 20ms on average.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Attached) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.DB.ACTIVE.READ.TIME})>{$MS.EXCHANGE.DB.ACTIVE.READ.WARN}` |WARNING | |
|Database Counters [{#INSTANCE}]: Average read time latency is too high |<p>Should be less than 200ms on average.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Recovery) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.DB.PASSIVE.READ.TIME})>{$MS.EXCHANGE.DB.PASSIVE.READ.WARN}` |WARNING | |
|Database Counters [{#INSTANCE}]: Average write time latency is too high for {$MS.EXCHANGE.DB.ACTIVE.WRITE.TIME} |<p>Should be less than 50ms on average.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Writes (Attached) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.DB.ACTIVE.WRITE.TIME})>{$MS.EXCHANGE.DB.ACTIVE.WRITE.WARN}` |WARNING | |
|Database Counters [{#INSTANCE}]: Average write time latency is higher than read time latency for {$MS.EXCHANGE.DB.PASSIVE.WRITE.TIME} |<p>Should be less than the read latency for the same instance, as measured by the MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Recovery) Average Latency counter.</p> |`avg(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Writes (Recovery) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.DB.PASSIVE.WRITE.TIME})>avg(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange Database ==> Instances({#INF.STORE}/_Total)\I/O Database Reads (Recovery) Average Latency", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.DB.PASSIVE.WRITE.TIME})` |WARNING | |
|Domain Controller [{#INSTANCE}]: LDAP read time is too high |<p>Should be less than 50ms at all times, with spikes less than 100ms.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange ADAccess Domain Controllers({#INSTANCE})\LDAP Read Time", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.LDAP.TIME})>{$MS.EXCHANGE.LDAP.WARN}` |AVERAGE | |
|Domain Controller [{#INSTANCE}]: LDAP search time is too high |<p>Should be less than 50ms at all times, with spikes less than 100ms.</p> |`min(/Microsoft Exchange Server 2016 by Zabbix agent/perf_counter_en["\MSExchange ADAccess Domain Controllers({#INSTANCE})\LDAP Search Time", {$MS.EXCHANGE.PERF.INTERVAL}],{$MS.EXCHANGE.LDAP.TIME})>{$MS.EXCHANGE.LDAP.WARN}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/415007-discussion-thread-for-official-zabbix-template-microsoft-exchange).

