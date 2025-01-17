# Memcached plugin
Provides native Zabbix solution for monitoring Memcached servers (distributed memory object caching system). 
It can monitor several Memcached instances simultaneously, remotes or locals to the Zabbix Agent. 
Both TCP and Unix-socket connections are supported. The plugin keeps connections in the opened state to reduce network 
congestion, latency, CPU and memory usage. Best for use in conjunction with the official 
[Memcached template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/app/memcached)
You can extend it or create your template for your specific needs. 

## Requirements
* Zabbix Agent 2
* Go >= 1.21 (required only to build from source)

## Supported versions
* Memcached, version 1.4+

## Installation
The plugin is supplied as a part of Zabbix Agent 2, and it does not require any special installation steps. Once 
Zabbix Agent 2 installed, the plugin is ready to work. The only thing you need to do is to make sure a Memcached 
instance is available for connection.

## Configuration
The Zabbix Agent's configuration file is used to configure plugins.

**Plugins.Memcached.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Memcached.Timeout** — The maximum time for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

### Configuring connection
A connection can be configured using either keys' parameters or named sessions.     

*Notes*:  
* You can leave any connection parameter empty, a default hard-coded value will be used in the such case.
* Embedded URI credentials (userinfo) are forbidden and will be ignored. So, you can't pass the credentials by this:   
  
      memcached.ping[tcp://user:password@127.0.0.1] — WRONG  
  
  The correct way is:
    
      memcached.ping[tcp://127.0.0.1,user,password]
      
* The only supported network schemas for a URI are "tcp" and "unix".  
Examples of valid URIs:
    - tcp://127.0.0.1:11211
    - tcp://localhost
    - localhost
    - unix:/var/run/memcached.sock
    - /var/run/memcached.sock
      
#### Using keys' parameters
The common parameters for all keys are: [ConnString][,User][,Password]  
Where ConnString can be either a URI or a session name.   
ConnString will be treated as a URI if no session with the given name is found.  
If you use ConnString as a session name, just skip the rest of the connection parameters.  
 
#### Using named sessions
Named sessions allow you to define specific parameters for each Memcached instance. Currently, there are only three supported 
parameters: Uri, User and Password. It's a bit more secure way to store credentials compared to item keys or macros.  

E.g: suppose you have two Memcached instances: "Prod" and "Test". 
You should add the following options to the agent configuration file:   

    Plugins.Memcached.Sessions.Prod.Uri=tcp://192.168.1.1:11211
    Plugins.Memcached.Sessions.Prod.User=<UserForProd>  
    Plugins.Memcached.Sessions.Prod.Password=<PasswordForProd>  
      
    Plugins.Memcached.Sessions.Test.Uri=tcp://192.168.0.1:11211
    Plugins.Memcached.Sessions.Test.User=<UserForTest>   
    Plugins.Memcached.Sessions.Test.Password=<PasswordForTest>
        
Then you will be able to use these names as the 1st parameter (ConnString) in keys instead of URIs, e.g:

    memcached.ping[Prod]
    memcached.ping[Test]

*Note*: sessions names are case-sensitive.

## Supported keys
**memcached.ping[\<commonParams\>]** — Tests if a connection is alive or not.  
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**memcached.stats[\<commonParams\>[,type]]** — Returns an output of the "stats" command 
serialized to JSON.  
*Params:*  
type — One of supported stat types: items, sizes, slabs and settings. Empty by default (returns general statistics).  

## Troubleshooting
The plugin uses Zabbix agent's logs. You can increase debugging level of Zabbix Agent if you need more details about 
what is happening. 
