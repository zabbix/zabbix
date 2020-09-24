# Ceph plugin
Provides native Zabbix solution for monitoring Ceph clusters (distributed storage system). It can monitor several 
Ceph instances simultaneously, remote or local to the Zabbix Agent.
Best for use in conjunction with the official 
[Template App Сeph Agent 2](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/app/ceph_agent2). 
You can extend it or create your template for your specific needs. 

## Requirements
- Zabbix Agent 2
- Go >= 1.13
- Ceph version 14+

## Installation
* Configure the Ceph RESTful Module according to [documentation.](https://docs.ceph.com/en/latest/mgr/restful/)  
* Make sure a RESTful API endpoint is available for connection.  

## Configuration
The Zabbix agent 2 configuration file is used to configure plugins.

**Plugins.Ceph.InsecureSkipVerify** — InsecureSkipVerify controls whether an http client verifies the
server's certificate chain and host name. If InsecureSkipVerify is true, TLS accepts any certificate presented by 
the server and any host name in that certificate. In this mode, TLS is susceptible to man-in-the-middle attacks.  
**This should be used only for testing.**  
*Default value:* false  
*Limits:* false | true

**Plugins.Ceph.Timeout** — The maximum time in seconds for waiting when a request has to be done. The timeout includes 
connection time, any redirects, and reading the response body.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

**Plugins.Ceph.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Ceph.Uri** — Uri to connect.  
*Default value:* https://localhost:8003  
*Limits:*
- Must match the URI format.
- The only supported schema is "https".
- Embedded credentials are forbidden (will be ignored).
  
*Examples:*
- https://127.0.0.1:8003
- https://localhost 

### Authentication
The plugin can authenticate using credentials specified as key parameters or within named sessions. 
Embedded URI credentials (userinfo) will be ignored. So, you can't pass the credentials by this:   

    ceph.ping[https://user:apikey@127.0.0.1] — WRONG  

The correct way is:
  
    ceph.ping[https://127.0.0.1,user,apikey]

#### Using named sessions
Named sessions allow you to define specific parameters for each Ceph instance. Currently, there are only three supported 
parameters: Uri, User and ApiKey. It's a bit more secure way to store credentials compared to 
item keys or macros.  

E.g: suppose you have two clusters: "Prod" and "Test". 
You should add the following options to the agent configuration file:   

    Plugins.Ceph.Sessions.Prod.Uri=https://192.168.1.1:8003
    Plugins.Ceph.Sessions.Prod.User=<UserForProd>
    Plugins.Ceph.Sessions.Prod.ApiKey=<ApiKeyForProd>
        
    Plugins.Ceph.Sessions.Test.Uri=https://192.168.0.1:8003
    Plugins.Ceph.Sessions.Test.User=<UserForTest>
    Plugins.Ceph.Sessions.Test.ApiKey=<ApiKeyForTest>
    
You can omit a Uri if it is already specified as 1st level parameter:

    Plugins.Ceph.Uri=https://192.168.1.1:8003
    
Then you will be able to use these names as connStrings in keys instead of URIs, e.g:

    ceph.ping[Prod]
    ceph.ping[Test]
    
### Parameters priority
There are 4 levels of parameters overwriting:
1. Hardcoded default values →
2. 1st level config parameters (Plugins.Ceph.\<parameter\>) →
3. Named sessions (Plugins.Ceph.Sessions.\<sessionName\>.\<parameter\>) →
4. Item keys parameters.

## Supported keys
The common parameters for all keys are: [connString][,user][,apikey]

**ceph.df.details[\<commonParams\>]** — Returns statistics provided by "df detail" command.  

**ceph.osd.stats[\<commonParams\>]** — Returns OSDs statistics provided by "pg dump" command.  

**ceph.osd.discovery[\<commonParams\>]** — Returns list of OSDs in LLD format.  

**ceph.osd.dump[\<commonParams\>]** — Returns OSDs dump provided by "osd dump" command.  

**ceph.ping[\<commonParams\>]** — Tests if a connection is alive or not.  
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**ceph.pool.discovery[\<commonParams\>]** — Returns list of pools in LLD format.  

**ceph.status[\<commonParams\>]** — Returns data provided by "status" command.  


## Troubleshooting
The plugin uses Zabbix agent's logs. You can increase debugging level of Zabbix Agent if you need more details about 
what is happening.  
