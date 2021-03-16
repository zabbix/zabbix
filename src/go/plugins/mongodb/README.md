# MongoDB plugin
Provides native Zabbix solution for monitoring MongoDB servers and clusters (document-based, distributed database). 
It can monitor several MongoDB instances simultaneously, remotes or locals to the Zabbix Agent. 
The plugin keeps connections in the opened state to reduce network 
congestion, latency, CPU and memory usage. Best for use in conjunction with the official 
[MongoDB template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/app/mongodb)
You can extend it or create your template for your specific needs. 

## Requirements
* Zabbix Agent 2
* Go >= 1.13 (required only to build from the source)

## Supported versions
* MongoDB, versions 4.4, 4.2, 4.0 and 3.6

## Installation
Depending on your configuration you need to create a local read-only user in the admin database:  
- *STANDALONE*: for each single MongoDB node.
- *REPLICASET*: create the user on the primary node of the replica set.  
- *SHARDING*: for each shard in your cluster (just create the user on the primary node of the replica set). 
Also, create the same user on a mongos router. It will automatically spread to config servers.

```javascript
use admin

db.auth("admin", "<ADMIN_PASSWORD>")

db.createUser({
  "user": "zabbix",
  "pwd": "<PASSWORD>",
  "roles": [
    { role: "readAnyDatabase", db: "admin" },
    { role: "clusterMonitor", db: "admin" },
  ]
})
```

## Configuration
The Zabbix Agent's configuration file is used to configure plugins.

**Plugins.Mongo.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Mongo.Timeout** — The amount of time to wait for a server to respond when first connecting and on follow up 
operations in the session.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

### Configuring connection
A connection can be configured using either keys' parameters or named sessions.     

*Notes*:  
* It is not possible to mix configuration using named sessions and keys' parameters simultaneously.
* You can leave any connection parameter empty, a default hard-coded value will be used in the such case: 
  localhost:27017 without authentication.
* Embedded URI credentials (userinfo) are forbidden and will be ignored. So, you can't pass the credentials by this:   
  
      mongodb.ping[tcp://user:password@127.0.0.1] — WRONG  
  
  The correct way is:
    
      mongodb.ping[tcp://127.0.0.1,user,password]
      
* Currently, only TCP connections supported.
  
Examples of valid URIs:
    - tcp://127.0.0.1:27017
    - tcp://localhost
    - localhost
      
#### Using keys' parameters
The common parameters for all keys are: [ConnString][,User][,Password]  
Where ConnString can be either a URI or session name.   
ConnString will be treated as a URI if no session with the given name found.  
If you use ConnString as a session name, just skip the rest of the connection parameters.  
 
#### Using named sessions
Named sessions allow you to define specific parameters for each MongoDB instance. Currently, there are only three supported 
parameters: Uri, User and Password. It's a bit more secure way to store credentials compared to item keys or macros.  

E.g: suppose you have two MongoDB instances: "Prod" and "Test". 
You should add the following options to the agent configuration file:   

    Plugins.Mongo.Sessions.Prod.Uri=tcp://192.168.1.1:27017
    Plugins.Mongo.Sessions.Prod.User=<UserForProd>
    Plugins.Mongo.Sessions.Prod.Password=<PasswordForProd>
      
    Plugins.Mongo.Sessions.Test.Uri=tcp://192.168.0.1:27017
    Plugins.Mongo.Sessions.Test.User=<UserForTest>
    Plugins.Mongo.Sessions.Test.Password=<PasswordForTest>
        
Then you will be able to use these names as the 1st parameter (ConnString) in keys instead of URIs, e.g:

    mongodb.ping[Prod]
    mongodb.ping[Test]

*Note*: sessions names are case-sensitive.

## Supported keys
**mongodb.collection.stats[\<commonParams\>[,database],collection]** — Returns a variety of storage statistics for a 
given collection.  
*Parameters:*  
database — database name (default: admin).  
collection (required) — collection name.

**mongodb.cfg.discovery[\<commonParams\>]** — Returns a list of discovered config servers.  

**mongodb.collections.discovery[\<commonParams\>]** — Returns a list of discovered collections.  

**mongodb.collections.usage[\<commonParams\>]** — Returns usage statistics for collections.  

**mongodb.connpool.stats[\<commonParams\>]** — Returns information regarding the open outgoing connections from the
current database instance to other members of the sharded cluster or replica set.    

**mongodb.db.stats[\<commonParams\>[,database]]** — Returns statistics reflecting a given database system’s state.  
*Parameters:*  
database — database name (default: admin).    

**mongodb.db.discovery[\<commonParams\>]** — Returns a list of discovered databases.    

**mongodb.jumbo_chunks.count[\<commonParams\>]** — Returns count of jumbo chunks.    

**mongodb.oplog.stats[\<commonParams\>]** — Returns a status of the replica set, using data polled from the oplog.    

**mongodb.ping[\<commonParams\>]** — Tests if a connection is alive or not.  
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**mongodb.rs.config[\<commonParams\>]** — Returns a current configuration of the replica set.    

**mongodb.rs.status[\<commonParams\>]** — Returns a replica set status from the point of view of the member
where the method is run.  
 
**mongodb.server.status[\<commonParams\>]** — Returns a database’s state.    

**mongodb.sh.discovery[\<commonParams\>]** — Returns a list of discovered shards present in the cluster.    

## Troubleshooting
The plugin uses Zabbix agent's logs. You can increase debugging level of Zabbix Agent if you need more details about 
what is happening.   
Set the DebugLevel configuration option to "5" (extended debugging) in order to turn on verbose log messages for the MGO package.
