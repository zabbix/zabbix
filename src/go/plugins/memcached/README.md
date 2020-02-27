# Memcached plugin
Provides native Zabbix solution for monitoring Memcached servers (in-memory data structure store). It can monitor several 
Memcached instances simultaneously, remotes or locals to the Zabbix Agent. Both TCP and Unix-socket connections are 
supported. The plugin keeps connections in the opened state to reduce network congestion, latency, CPU and 
memory usage. Best for use in conjunction with the official Memcached template. You can extend it or create your 
template for your specific needs. 

## Requirements
- Zabbix Agent 2
- Go >= 1.13 (required just to build from source)

## Installation
The plugin is supplied as a part of Zabbix Agent 2, and it does not require any special installation steps. Once 
Zabbix Agent 2 installed, the plugin is ready to work. The only thing you need to do is to make sure that a Memcached 
instance is available for connection.

## Configuration
The Zabbix Agent's configuration file is used to configure plugins.

**Plugins.Memcached.Uri** — Uri to connect.  
*Default value:* tcp://localhost:11211  
*Limits:*
- Must match the URI format.
- The only supported schemas are "tcp" and "unix".
  
*Examples:*
- tcp://127.0.0.1:11211
- tcp://localhost
- unix:/var/run/memcached.sock

**Plugins.Memcached.User** — A username to send to a protected Memcached instance.  
*Default value:* none.  

**Plugins.Memcached.Password** — A password to send to a protected Memcached instance.  
*Default value:* none.  

**Plugins.Memcached.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Memcached.Timeout** — The maximum time for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

### Authentication
The plugin can authenticate by a password if such is set in the Agent's configuration file. It's possible to 
use different passwords for different Memcached instances using named sessions in the configuration file (as well as 
different URIs).

**Note:** For security reasons, it's forbidden to pass embedded credentials within the connString (an item key's param, 
can be either a Uri or a session name) — they will be just ignored. 

- If you pass a Uri as the connString, and this connection requires authentication, you must use the
Plugins.Memcached.User and Plugins.Memcached.Password parameters (the 1st level parameters) in the configuration file.
In other words, once defined these parameters will be used for authenticating all connections where the connString is 
represented by Uri. 

- If you want to use different credentials for different Memcached instances, you should create named session in the 
config for each instance and should define a session-level password.
 
#### Named sessions
Named sessions allow you to define specific parameters for each Memcached instance. Currently, there are supported only 
two parameters: Uri, User and Password. It can be useful if you have multiple instances with different credentials. 
E.g: if you have two instances: "Memcached1" and "Memcached2", you need to add these options to your agent's config:   

    Plugins.Memcached.Sessions.Memcached1.Uri=tcp://127.0.0.1:11211
    Plugins.Memcached.Sessions.Memcached1.User=<UserForMemcached1>  
    Plugins.Memcached.Sessions.Memcached1.Password=<PasswordForMemcached1>    
    Plugins.Memcached.Sessions.Memcached2.Uri=tcp://127.0.0.1:11212
    Plugins.Memcached.Sessions.Memcached2.User=<UserForMemcached2>   
    Plugins.Memcached.Sessions.Memcached2.Password=<PasswordForMemcached2>  
    
Then you can use these names as connStrings in keys instead of URIs, e.g:

    memcached.stats[Memcached1]
    memcached.stats[Memcached2]

### Parameters priority
There are 4 levels of parameters overwriting:
1. Hardcoded default values →
2. 1st level config params (Plugins.Memcached.\<parameter\>) →
3. Named sessions (Plugins.Memcached.Sessions.\<sessionName\>.\<parameter\>) →
4. Items' key params.

## Supported keys

**memcached.ping[connString]** — Tests if a connection is alive or not.  
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**memcached.stats[connString][,type]** — Returns an output of the "stats" command serialized to JSON.  
*Params:*  
type — One of supported stat types: items, sizes, slabs, settings. Empty by default (returns general statistics).  


## Troubleshooting
The plugin uses Zabbix Agent's logs. You can increase a debug level of Zabbix Agent if you need more information about 
what is happening.
