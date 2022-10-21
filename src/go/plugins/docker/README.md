# Docker plugin
This plugin provides a native solution for monitoring Docker 
containers and images by Zabbix. 
The plugin can monitor docker instances via Zabbix agent 2 using docker socket
and querying docker API. It can be used in conjunction with the official 
[Docker template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/app/docker) 
You can extend it or create your template for your specific needs.

## Requirements
* Zabbix Agent 2
* Go >= 1.18 (required only to build from source)

## Tested versions
* Docker, version 19.03.5

## Installation
The plugin is supplied as part of the Zabbix Agent 2 and 
does not require any special installation steps. Once 
Zabbix Agent 2 is installed, the plugin is ready to work. 
Now you need to make sure that a Docker instance is available.

## Configuration
Open the Zabbix Agent 2 configuration file (zabbix_agent2.conf) and 
set the required parameters.

**Plugins.Docker.Endpoint** — Docker API endpoint.
*Default value:* `unix:///var/run/docker.sock`    
 
**Plugins.Docker.Timeout** — The maximum time (in seconds) for 
waiting when a request has to be done.
*Default value:* equals the global Timeout configuration parameter.    
*Limits:* 1-30

*Notes*:  
* You can leave both endpoint and timeout parameter empty, 
default hard-coded values will be used in such cases. 
  
## Supported keys
**docker.container_info[\<Container\>]** — Return low-level information about a container.
*Parameters:*  
Container (required) — container name.

**docker.container_stats[\<Container\>]** — Returns near realtime 
stats for a given container.  
*Parameters:*  
Container (required) — container name.

**docker.containers[]** — Returns a list of containers.

**docker.containers.discovery[\<All\>]** — "Returns a list of containers, 
used for low-level discovery."
*Parameters:*  
All (not required, default:false) — "Return all containers (true) or
only running (false)."

**docker.images[]** — Returns a list of images.

**docker.images.discovery[]** — Returns a list of images, used for low-level discovery.

**docker.info[]** — Returns information about the docker server.

**docker.data_usage[]** — Returns information about current data usage.

**docker.ping[]** — Pings the server and returns 0 or 1.
*Returns:*
- "1" if the connection is alive.
- "0" if the connection is broken (returned if there was any error during the test, or
response is not `OK`).    

## Troubleshooting
The plugin uses Zabbix agent 2's logs. You can increase debugging level of Zabbix Agent 2 
if you need more details about what is happening. 
