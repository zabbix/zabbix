# Docker plugin
This plugin provides a native solution to monitor Docker
containers and images by Zabbix. 
The plugin can monitor docker instances with Zabbix agent 2 using docker socket
and querying docker API. It can be used in conjunction with the official 
[Docker template](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/app/docker). 
You can extend it or create your own template to cater specific needs.

## Requirements
* Zabbix agent 2
* Go >= 1.21 (required only to build from source)

## Tested versions
* Docker, version 19.03.5

## Installation
The plugin is supplied as an out-of-box part of Zabbix agent 2 and 
does not require any special installation steps. Once 
Zabbix agent 2 is installed, the plugin is ready to work. 
Now, you need to make sure that a Docker instance is available.

## Configuration
Open Zabbix agent 2 docker configuration file `zabbix_agent2.d/plugins.d/docker.conf` and 
set the required parameters.

**Plugins.Docker.Endpoint** — the Docker API endpoint.
*Default value:* `unix:///var/run/docker.sock`    
 
**Plugins.Docker.Timeout** — the maximum time (in seconds) for 
waiting when a request has to be done.
*Default value:* equals the global Timeout configuration parameter.    
*Limits:* 1-30

*Notes*:  
* You can leave both `endpoint` and `timeout` parameter values empty;
default hard-coded values will be used instead. 
  
## Supported keys
**docker.container_info[\<Container\>,\<Info\>]** — returns low-level information about a container.
*Parameters:*  
Container (required) — a container name or ID.
Info (not required; default: short) — returns all container info (full) or shortened version (short).

**docker.container_stats[\<Container\>]** — returns near real-time statistics for a given container.
*Parameters:*
Container (required) — a container name or ID.

**docker.containers[]** — returns a list of containers.

**docker.containers.discovery[\<All\>]** — returns a list of containers, 
used for low-level discovery.
*Parameters:*  
All (not required; default: false) — returns all containers (true) or only running (false).

**docker.images[]** — returns a list of images.

**docker.images.discovery[]** — returns a list of images, used in low-level discovery rules.

**docker.info[]** — returns the information about the docker server.

**docker.data_usage[]** — returns the information about the current data usage.

**docker.ping[]** — pings the server and returns 0 or 1.
*Returns:*
- "1" if the connection is alive;
- "0" if the connection is broken (is returned if there is any error during the test, or the response is not "OK").

## Troubleshooting
The plugin uses log output of Zabbix agent 2. You can increase debugging level of Zabbix agent 2 
if you need more details about the current situation.
