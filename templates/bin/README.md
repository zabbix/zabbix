# zbx_template_pack
The idea behind this template pack is to provide single template class for each device type and just define SNMP oids required to collect common metrics liks `CPU load`, `memory`, `temperature` and so on to generate new template for new vendor.  


So all templates are generated using only SNMP OIDs and other vendor specific details.  
LLD details, Context macros values and other attributes can be optionally provided.   
The rest is added automatically, including item names, descriptions, triggers and so on.  

As the output we would have a pack of templates for different vendors(Cisco, Juniper, Mikrotik for `net` template class) that control same items(CPU load in %, Memory load in % , Temperature etc) named the same and have same triggers also named the same with same thresholds(can be tuned using MACROS). So we know what we can expect from this kind of template.  



## Required and optional items  
Some items are marked `required` so the new device we want to add must provide ways to monitor this item. Otherwise this device will not be added since we expect similar behaviour from all devices using template from the pack. If we can't control CPU load or memory load for `net` device then it's not going to be here.
Rules for `optional` metrics are not so strict. We can still live without them but they can be handy in some situations so we add them if they are ways to collect them.  

## Examples
Comparing different devices metrics made easier:  
CPU:  
![image](https://cloud.githubusercontent.com/assets/14870891/22948032/1ef3a5e0-f30e-11e6-8886-43f38998000d.png)  
Temperature:  
![image](https://cloud.githubusercontent.com/assets/14870891/22948078/4d41a514-f30e-11e6-846e-acb5d782f903.png)  
Memory triggers also look similar:  
![image](https://cloud.githubusercontent.com/assets/14870891/22948146/842493e8-f30e-11e6-927a-79d13ca9ef5b.png)  


## How to use this template pack  
Just import the required template into your Zabbix 3.0+. Some templates might have dependencies. Check `deps` directory then.  
Currently `net` template is ready to be tested.  See [`net/README.md`](https://github.com/v-zhuravlev/zbx_template_pack/tree/master/net) for all its items and triggers and supported network device types.  


## Template options  
Templates are provided in two SNMP versions (SNMPvx suffix):  
- with SNMPv1 items  
- with SNMPv2 items  
And two translations (EN or RU suffixes):  
- English (Items and triggers)  
- Russian (most of items and triggers are translated)  
