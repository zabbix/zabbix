# Redis plugin
Provides native Zabbix solution for monitoring Redis servers (in-memory data structure store). It can monitor several 
Redis instances simultaneously, remotes or locals to the Zabbix Agent. Both TCP and Unix-socket connections are 
supported. The plugin keeps connections in the opened state to reduce network congestion, latency, CPU and 
memory usage. Best for use in conjunction with the official 
[Redis template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/db/redis)
You can extend it or create your template for your specific needs. 

## Requirements
* Zabbix Agent 2
* Go >= 1.21 (required only to build from source)

## Supported versions
* Redis, version 3.0+

## Installation
The plugin is supplied as a part of Zabbix Agent 2, and it does not require any special installation steps. Once 
Zabbix Agent 2 installed, the plugin is ready to work. The only thing you need to do is to make sure a Redis 
instance is available for connection.

## Configuration
The Zabbix Agent's configuration file is used to configure plugins.

**Plugins.Redis.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Redis.Timeout** — The maximum time for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

### Configuring connection
A connection can be configured using either keys' parameters or named sessions.     

*Notes*:  
* You can leave any connection parameter empty, a default hard-coded value will be used in the such case.
* Embedded URI credentials (userinfo) are forbidden and will be ignored. So, you can't pass the credentials by this:   
  
      redis.ping[tcp://user:password@127.0.0.1] — WRONG  
  
  The correct way is:
    
      redis.ping[tcp://127.0.0.1,password,user]
      
* The only supported network schemas for a URI are "tcp" and "unix".  
Examples of valid URIs:
    - tcp://127.0.0.1:6379
    - tcp://localhost
    - localhost
    - unix:/var/run/redis.sock
    - /var/run/redis.sock
      
#### Using keys' parameters
The common parameters for all keys are: [ConnString][,Password][,...][,User]
Where ConnString can be either a URI or a session name.
ConnString will be treated as a URI if no session with the given name is found.
If you use ConnString as a session name, just skip the rest of the connection parameters.
User name defaults to "default" if not set.

User is added as last parameter to keep backward compatibility. User must be added as the final parameter in the whole
item and not just last connection parameter if there are more non connection parameters.
 
#### Using named sessions
Named sessions allow you to define specific parameters for each Redis instance. Currently, there are only three
supported parameters: Uri, Password and User. It's a bit more secure way to store credentials compared to item keys or
macros.  

E.g: suppose you have two Redis instances: "Prod" and "Test". 
You should add the following options to the agent configuration file:   

    Plugins.Redis.Sessions.Prod.Uri=tcp://192.168.1.1:6379  
    Plugins.Redis.Sessions.Prod.Password=<PasswordForProd>  
    Plugins.Redis.Sessions.Prod.User=<UserForProd>

    Plugins.Redis.Sessions.Test.Uri=tcp://192.168.0.1:6379   
    Plugins.Redis.Sessions.Test.Password=<PasswordForTest>  
    Plugins.Redis.Sessions.Test.User=<UserForTest>

Then you will be able to use these names as the 1st parameter (ConnString) in keys instead of URIs, e.g:

    redis.ping[Prod]
    redis.ping[Test]

*Note*: sessions names are case-sensitive.

## Supported keys
**redis.config[\<commonParams\>[,pattern][,user]]** — Gets a configuration parameters of a Redis instance matching pattern.  
*Params:*  
pattern — Glob-style pattern. The default value is "*".  
*Returns:*
- JSON if a glob-style pattern is specified.
- Single value if a pattern did not contain any wildcard character.

**redis.info[\<commonParams\>[,section][,user]]** — Returns an output of the INFO command serialized to JSON.  
*Params:*  
section — Section of information. The default section name is "default".

**redis.ping[\<commonParams\>]** — Tests if a connection is alive or not.  
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**redis.slowlog.count[\<commonParams\>]** — Returns the number of slow log entries since Redis was started.

## Troubleshooting
The plugin uses Zabbix agent's logs. You can increase debugging level of Zabbix Agent if you need more details about 
what is happening. 
