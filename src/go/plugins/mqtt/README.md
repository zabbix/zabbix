# MQTT plugin
This plugin provides a native solution for monitoring messages published by MQTT brokers. 
The plugin can monitor several broker instances simultaneously via Zabbix agent 2. Proxy and websocket connections are 
supported. The plugin keeps all subscriptions to a single broker in one connection to reduce network strain. The plugin 
supports active checks only.

This plugin works using "Eclipse Paho MQTT Go client library" (https://github.com/eclipse/paho.mqtt.golang)


## Requirements
- Zabbix Agent 2
- Go >= 1.21 (required only to build from source)

## Installation
The plugin is supplied as part of the Zabbix Agent 2 and does not require any special installation steps. Once 
Zabbix Agent 2 is installed, the plugin is ready to work. Now you need to make sure that an MQTT broker is available.

## Configuration
Open the Zabbix Agent configuration file (zabbix_agent2.conf) and set the required parameters.

**Plugins.MQTT.Timeout** — connection timeout (how long to wait for a connection to respond before shutting it down).  
*Default value:* equals the global 'Timeout' (configuration parameter set in zabbix_agent2.conf).  
*Limits:* 1-30

**Plugins.MQTT.Sessions.<session_name>.Url** — Broker connection string used for MQTT.
*Default value:* tcp://localhost:1883

**Plugins.MQTT.Sessions.<session_name>.Topic** — Topic used for MQTT subscription.
*Default value:*

**Plugins.MQTT.Sessions.<session_name>.User** — Username to be used for MQTT authentication.
*Default value:* 

**Plugins.MQTT.Sessions.<session_name>.Password** — Password to be used for MQTT authentication.
*Default value:*

**Plugins.MQTT.Sessions.<session_name>.TLSCAFile** — Full pathname of a file containing the top-level CA(s) certificates for MQTT
*Default value:* 

**Plugins.MQTT.Sessions.<session_name>.TLSCertFile** — Full pathname of a file containing the MQTT certificate or certificate chain.
*Default value:* 

**Plugins.MQTT.Sessions.<session_name>.TLSKeyFile** — Full pathname of a file containing the MQTT private key.
*Default value:* 

### Connection and authentication
The plugin uses broker URI, topic, username and password from item key parameters.
The first two parameters broker and topic, broker URI can be empty, but the topic parameter is mandatory.
The last two parameters username and password need to be provided only if required.

Websocket connection is supported using "ws://" scheme.

If the Zabbix agent 2 is running behind a http/https proxy then the following environment variables are used 
'TP_PROXY', 'HTTPS_PROXY' and 'NO_PROXY', when this plugin establishes a connection.

TLS encryption certificates can be used, by providing them either via session or default parameters
in Zabbix agent 2 MQTT plugin configuration file.
For TLS use "tls://" scheme

If broker URI is left empty the default value of "localhost" is used.
If broker URI does not contain a scheme the default value of "tcp://" is used.
If broker URI does not contain a port the default value of "1883" is used. 

The topic may contain wildcards ("+","#").
If the topic contains a wildcard the response will be a json containing the topic and value.

For example:
- mqtt.get["","path/to/topic"]
- mqtt.get["localhost","path/to/topic"]
- mqtt.get["tcp://host:1883","path/to/topic"]
- mqtt.get["tcp://host:1883","path/to/#"]
- mqtt.get["tcp://host:1883","path/+/topic"]
- mqtt.get["ws://host:8080","path/to/topic"]
- mqtt.get["tls://host:8883","path/to/topic"]

**Note!** Broker URI should not contain query parameters. If scheme or port is provided the host should also be provided.
  
## Supported keys

**mqtt.get[broker,topic,username,password]** — subscribes to a specific topic or topics (with wildcards) of the provided broker
and waits for publications.
*Returns:*
- "example publish"
- {"path/to/topic":"example one"}
- error message (if there was an error connecting to the broker or topic)

## Troubleshooting
The plugin uses Zabbix agent 2 logs. To receive more detailed information about logged events, consider increasing a debug level 
of Zabbix agent 2.
