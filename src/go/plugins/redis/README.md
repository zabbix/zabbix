# Redis plugin
Provides native Zabbix solution for monitoring Redis servers (in-memory data structure store). It can monitor several 
Redis instances simultaneously, remotes or locals to the Zabbix Agent. Both TCP and Unix-socket connections are 
supported. The plugin keeps connections in the opened state to reduce network congestion, latency, CPU and 
memory usage. Best for use in conjunction with the official Redis template. You can extend it or create your 
template for your specific needs. 

## Requirements
- Zabbix Agent 2
- Go >= 1.12 (required just to build from source)

## Installation
The plugin is supplied as a part of Zabbix Agent 2, and it does not require any special installation steps. Once 
Zabbix Agent 2 installed, the plugin is ready to work. The only thing you need to do is to make sure that a Redis 
instance is available for connection.

## Configuration
The Zabbix Agent's configuration file is used to configure plugins.

**Plugins.Redis.Uri** — Uri to connect.  
*Default value:* tcp://localhost:6379  
*Limits:*
- Must match the URI format.
- The only supported schemas: "tcp" and "unix".
  
*Examples:*
- tcp://localhost:6379
- tcp://localhost
- unix:/var/run/redis.sock

**Plugins.Redis.Password** — A password to send to a protected Redis instance.  
*Default value:* none.  
*Limits:* Length is limited by 512 characters.

**Plugins.Redis.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Redis.Timeout** — The maximum time for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

### Authentication
The plugin can authenticate by a password if such is set in the Agent's configuration file. It's possible to 
use different passwords for different Redis instances using named sessions in the configuration file (as well as 
different URIs).

**Note:** For security reasons, it's forbidden to pass embedded credentials within the connString (an item key's param, 
can be either a Uri or a session name) — they will be just ignored. 

- If you pass a Uri as the connString, and this connection requires authentication, you must use the
Plugins.Redis.Password parameter (the 1st level password) in the configuration file. In other words, once defined this 
parameter will be used for authenticating all connections where the connString is represented by Uri. 

- If you want to use different passwords for different Redis instances, you should create named session in the config 
for each instance and should define a session-level password.
 
#### Named sessions
Named sessions allow you to define specific parameters for each Redis instance. Currently, there are supported only two 
parameters: Uri and password. It can be useful if you have multiple instances with different credentials. E.g: if you
have two instances: "Redis1" and "Redis2", you need to add these options to your agent's config:   

    Plugins.Redis.Sessions.Redis1.Uri=tcp://127.0.0.1:6379  
    Plugins.Redis.Sessions.Redis1.Password=<PasswordForRedis1>    
    Plugins.Redis.Sessions.Redis2.Uri=tcp://127.0.0.1:6380   
    Plugins.Redis.Sessions.Redis2.Password=<PasswordForRedis2>  
    
Then you are able to use these names as a connStrings in keys instead of URIs, e.g:

    redis.info[Redis1]
    redis.info[Redis2]

### Parameters priority
There are 4 levels of parameters overwriting:
1. Hardcoded default values →
2. 1st level config params (Plugins.Redis.\<parameter\>) →
3. Named sessions (Plugins.Redis.Sessions.\<sessionName\>.\<parameter\>) →
4. Items' key params.

## Supported keys

**redis.ping[connString]** — Tests if a connection is alive or not.  
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**redis.slowlog.count[connString]** — Returns the number of slow log entries since Redis was started.

**redis.info[connString][,section]** — Returns An output of the INFO command serialized to JSON.  
*Params:*  
section — Section of information. The default section name is "default".

**redis.config[connString][,pattern]** — Gets a configuration parameters of a Redis instance matching pattern.  
*Params:*  
pattern — Glob-style pattern. The default value is "*".  
*Returns:*
- JSON if a glob-style pattern was used.
- Single value if a pattern did not contain any wildcard character.  


## Troubleshooting
The plugin uses Zabbix Agent's logs. You can increase a debug level of Zabbix Agent if you need more information about 
what is happening.
