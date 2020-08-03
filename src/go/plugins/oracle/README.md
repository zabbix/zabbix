# Oracle Database plugin
Provides native Zabbix solution for monitoring Oracle Database. It can monitor several 
Oracle instances simultaneously, remotes or locals to the Zabbix Agent.
The plugin keeps connections in the opened state to reduce network congestion, latency, CPU and 
memory usage. Best for use in conjunction with the official Oracle template. You can extend it or create your 
template for your specific needs. 

## Requirements
- Zabbix Agent 2
- Go >= 1.13
- Oracle Instant Client >= 12

## Installation
* Install Oracle Instant Client (TODO: put instructions here)
* Make sure a TNS Listener and an Oracle instance are available for connection.
* Set tcp.connect_timeout=\<Timeout\> (1-30 sec) in sqlnet.ora (TODO: explain why it's necessary)  

## Configuration
The Zabbix Agent's configuration file is used to configure plugins.


### Authentication
The plugin can authenticate using credentials specified as key's params or within named sessions.
Embedded URI credentials (userinfo) will be ignored.
 
#### Named sessions

### Parameters priority
There are 4 levels of parameters overwriting:
1. Hardcoded default values →
2. 1st level config params (Plugins.Oracle.\<parameter\>) →
3. Named sessions (Plugins.Oracle.Sessions.\<sessionName\>.\<parameter\>) →
4. Items' key params.

## Supported keys

## Known issues
* tcp.connect_timeout

## Troubleshooting
The plugin uses Zabbix Agent's logs. You can increase a debug level of Zabbix Agent if you need more information about 
what is happening.
