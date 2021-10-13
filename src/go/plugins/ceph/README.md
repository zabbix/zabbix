# Ceph plugin
Provides native Zabbix solution for monitoring Ceph clusters (distributed storage system). It can monitor several 
Ceph instances simultaneously, remote or local to the Zabbix Agent.
Best for use in conjunction with the official 
[Ceph template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/app/ceph_agent2) 
You can extend it or create your template for your specific needs. 

## Requirements
* Zabbix Agent 2
* Go >= 1.13 (required only to build from source)

## Supported versions
* Ceph, version 14+

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

### Configuring connection
A connection can be configured using either keys' parameters or named sessions.     

*Notes*:  
* It is not possible to mix configuration using named sessions and keys' parameters simultaneously.
* You can leave any connection parameter empty, a default hard-coded value will be used in the such case.
* Embedded URI credentials (userinfo) are forbidden and will be ignored. So, you can't pass the credentials by this:   
  
      ceph.ping[https://user:apikey@127.0.0.1] — WRONG  
  
  The correct way is:
    
      ceph.ping[https://127.0.0.1,user,apikey]
      
* The only supported network schema for a URI is "https".  
Examples of valid URIs:
    - https://127.0.0.1:8003
    - https://localhost
    - localhost
      
#### Using keys' parameters
The common parameters for all keys are: [ConnString][,User][,ApiKey]  
Where ConnString can be either a URI or a session name.  
ConnString will be treated as a URI if no session with the given name is found.  
If you use ConnString as a session name, just skip the rest of the connection parameters.  
 
#### Using named sessions
Named sessions allow you to define specific parameters for each Ceph instance. Currently, there are only three supported 
parameters: Uri, User and ApiKey. It's a bit more secure way to store credentials compared to item keys or macros.  

E.g: suppose you have two Ceph clusters: "Prod" and "Test". 
You should add the following options to the agent configuration file:   

    Plugins.Ceph.Sessions.Prod.Uri=https://192.168.1.1:8003
    Plugins.Ceph.Sessions.Prod.User=<UserForProd>
    Plugins.Ceph.Sessions.Prod.ApiKey=<ApiKeyForProd>
        
    Plugins.Ceph.Sessions.Test.Uri=https://192.168.0.1:8003
    Plugins.Ceph.Sessions.Test.User=<UserForTest>
    Plugins.Ceph.Sessions.Test.ApiKey=<ApiKeyForTest>
        
Then you will be able to use these names as the 1st parameter (ConnString) in keys instead of URIs, e.g:

    ceph.ping[Prod]
    ceph.ping[Test]
    
*Note*: sessions names are case-sensitive.
    
## Supported keys
**ceph.df.details[\<commonParams\>]** — Returns information about cluster’s data usage and distribution among pools.    
Uses data provided by "df detail" command.  
*Output sample:*
```json
{
    "pools": {
        "device_health_metrics": {
            "percent_used": 0,
            "objects": 0,
            "bytes_used": 0,
            "rd_ops": 0,
            "rd_bytes": 0,
            "wr_ops": 0,
            "wr_bytes": 0,
            "stored_raw": 0,
            "max_avail": 1390035968
        },
        "new_pool": {
            "percent_used": 0,
            "objects": 0,
            "bytes_used": 0,
            "rd_ops": 0,
            "rd_bytes": 0,
            "wr_ops": 0,
            "wr_bytes": 0,
            "stored_raw": 0,
            "max_avail": 695039808
        },
        "test_zabbix": {
            "percent_used": 0,
            "objects": 4,
            "bytes_used": 786432,
            "rd_ops": 0,
            "rd_bytes": 0,
            "wr_ops": 4,
            "wr_bytes": 24576,
            "stored_raw": 66618,
            "max_avail": 1390035968
        },
        "zabbix": {
            "percent_used": 0,
            "objects": 0,
            "bytes_used": 0,
            "rd_ops": 0,
            "rd_bytes": 0,
            "wr_ops": 0,
            "wr_bytes": 0,
            "stored_raw": 0,
            "max_avail": 1390035968
        }
    },
    "rd_ops": 0,
    "rd_bytes": 0,
    "wr_ops": 4,
    "wr_bytes": 24576,
    "num_pools": 4,
    "total_bytes": 12872318976,
    "total_avail_bytes": 6898843648,
    "total_used_bytes": 2752249856,
    "total_objects": 4
}
```

**ceph.osd.stats[\<commonParams\>]** — Returns aggregated and per OSD statistics.  
Uses data provided by "pg dump" command.  
*Output sample:*
```json
{
    "osd_latency_apply": {
        "min": 0,
        "max": 0,
        "avg": 0
    },
    "osd_latency_commit": {
        "min": 0,
        "max": 0,
        "avg": 0
    },
    "osd_fill": {
        "min": 47,
        "max": 47,
        "avg": 47
    },
    "osd_pgs": {
        "min": 65,
        "max": 65,
        "avg": 65
    },
    "osds": {
        "0": {
            "osd_latency_apply": 0,
            "osd_latency_commit": 0,
            "num_pgs": 65,
            "osd_fill": 47
        },
        "1": {
            "osd_latency_apply": 0,
            "osd_latency_commit": 0,
            "num_pgs": 65,
            "osd_fill": 47
        },
        "2": {
            "osd_latency_apply": 0,
            "osd_latency_commit": 0,
            "num_pgs": 65,
            "osd_fill": 47
        }
    }
}
```

**ceph.osd.discovery[\<commonParams\>]** — Returns a list of discovered OSDs in LLD format.
Can be used in conjunction with "ceph.osd.stats" and "ceph.osd.dump" in order to create "per osd" items.  
Uses data provided by "osd crush tree" command.  
*Output sample:*
```json
[
  {
    "{#OSDNAME}": "0",
    "{#CLASS}": "hdd",
    "{#HOST}": "node1"
  },
  {
    "{#OSDNAME}": "1",
    "{#CLASS}": "hdd",
    "{#HOST}": "node2"
  },
  {
    "{#OSDNAME}": "2",
    "{#CLASS}": "hdd",
    "{#HOST}": "node3"
  }
]
```

**ceph.osd.dump[\<commonParams\>]** — Returns usage thresholds and statuses of OSDs.  
Uses data provided by "osd dump" command.  
*Output sample:*
```json
{
    "osd_backfillfull_ratio": 0.9,
    "osd_full_ratio": 0.95,
    "osd_nearfull_ratio": 0.85,
    "num_pg_temp": 65,
    "osds": {
        "0": {
            "in": 1,
            "up": 1
        },
        "1": {
            "in": 1,
            "up": 1
        },
        "2": {
            "in": 1,
            "up": 1
        }
    }
}
```

**ceph.ping[\<commonParams\>]** — Tests if a connection is alive or not.
Uses data provided by "health" command.    
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**ceph.pool.discovery[\<commonParams\>]** — Returns a list of discovered pools in LLD format.
Can be used in conjunction with "ceph.df.details" in order to create "per pool" items.  
Uses data provided by "osd dump" and "osd crush rule dump" commands.  
*Output sample:*
```json
[
    {
        "{#POOLNAME}": "device_health_metrics",
        "{#CRUSHRULE}": "default"
    },
    {
        "{#POOLNAME}": "test_zabbix",
        "{#CRUSHRULE}": "default"
    },
    {
        "{#POOLNAME}": "zabbix",
        "{#CRUSHRULE}": "default"
    },
    {
        "{#POOLNAME}": "new_pool",
        "{#CRUSHRULE}": "newbucket"
    }
]
```

**ceph.status[\<commonParams\>]** — Returns an overall cluster's status.  
Uses data provided by "status" command.  
*Output sample:*
```json
{
    "overall_status": 2,
    "num_mon": 3,
    "num_osd": 3,
    "num_osd_in": 2,
    "num_osd_up": 1,
    "num_pg": 66,
    "pg_states": {
        "activating": 0,
        "active": 0,
        "backfill_toofull": 0,
        "backfill_unfound": 0,
        "backfill_wait": 0,
        "backfilling": 0,
        "clean": 0,
        "creating": 0,
        "deep": 0,
        "degraded": 36,
        "down": 0,
        "forced_backfill": 0,
        "forced_recovery": 0,
        "incomplete": 0,
        "inconsistent": 0,
        "laggy": 0,
        "peered": 65,
        "peering": 0,
        "recovering": 0,
        "recovery_toofull": 0,
        "recovery_unfound": 1,
        "recovery_wait": 0,
        "remapped": 0,
        "repair": 0,
        "scrubbing": 0,
        "snaptrim": 0,
        "snaptrim_error": 0,
        "snaptrim_wait": 0,
        "stale": 0,
        "undersized": 65,
        "unknown": 1,
        "wait": 0
    },
    "min_mon_release_name": "octopus"
}
```

## Troubleshooting
The plugin uses Zabbix agent's logs. You can increase debugging level of Zabbix Agent if you need more details about 
what is happening.  

If you get the error "x509: cannot validate certificate for x.x.x.x because it doesn't contain any IP SANs", 
probably you need to set the InsecureSkipVerify option to "true" or use a certificate that is signed by the 
organization’s certificate authority.
