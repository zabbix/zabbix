# Modbus plugin
This plugin provides a native solution for monitoring modbus devices by Zabbix. 
The plugin can monitor several modbus devices simultaneously via Zabbix agent 2. Both TCP and 
RTU connections are supported.

## Requirements
- Zabbix Agent 2
- Go >= 1.21 (required only to build from source)

## Installation
The plugin is supplied as part of the Zabbix Agent 2 and does not require any special installation steps. Once 
Zabbix Agent 2 is installed, the plugin is ready to work. Now you need to make sure that a modbus device is 
available for connection and configure monitoring.

## Configuration
Open the Zabbix Agent configuration file (zabbix_agent2.conf) and set the required parameters.

**Plugins.Modbus.Timeout** — request execution timeout (how long to wait for a request to complete before shutting it down).  
*Default value:* equals the global 'Timeout' (configuration parameter set in zabbix_agent2.conf).  
*Limits:* 1-30

#### Named sessions
Named sessions allow you to define specific parameters for each instance of modbus device. Currently, only three parameters are supported: 
Endpoint, SlaveID and Timeout (if certain parameter is not specified for a named session, a value from key will be used). 

*Example:*  
If you have two instances: "MB1" and "MB2", the following options have to be added to the agent configuration:

    Plugins.Modbus.Sessions.MB1.Endpoint=tcp://127.0.0.1:502
    Plugins.Modbus.Sessions.MB1.SlaveID=20
    Plugins.Modbus.Sessions.MB1.Timeout=2
    Plugins.Modbus.Sessions.MB2.Endpoint=rtu://com1:9600:8n1
    Plugins.Modbus.Sessions.MB2.SlaveID=40
    Plugins.Modbus.Sessions.MB2.Timeout=4
    
Now, these names can be used as connStrings in keys instead of URIs:

    modbus.get[MB1]
    modbus.get[MB2,,,30001,1,uint64,le,0]

### Parameters priority
Zabbix checks parameters in the following order:
1. Item key parameters (checked first).
2. Named session parameters in configuration file (Plugins.Modbus.Sessions.\<sessionName\>.\<parameter\>). Checked only if values provided in a key parameters are empty. →
  
## Supported keys

**modbus.get[endpoint,slaveid,function,address,count,type,endianness,offset]** — receive value from modbus device
*Params:*
- endpoint — tcp or rtu connection string. tcp://localhost:511 rtu://COM1:9600:8n1
- slaveid — Modbus address of the device. Optional.
- function — 1,2,3,4 modbus read functions. Optional.
- address — Address of first registry , coil or input. Default 00001. Optional.
- count — count of number for return. Default 1. Optional.
- type — acceptable values: bit, int8, uint8, uint16, int16, uint32, int32, float, uint64 and double. Default bit or uint8. Optional.
- endianness — acceptable values: be, le, mbe, mle. Optional.
- offset — Number of registers or bits, starting from 'address', the result of which will be discarded. Optional.
*Returns:*
Array of numbers in JSON format.

## Troubleshooting
The plugin uses Zabbix Agent logs. To receive more detailed information about logged events, consider increasing a debug level 
of Zabbix Agent.
