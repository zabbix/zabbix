## About
Please read:  
https://documentation.zabbix.lan/specifications/3.4/zbxnext-3725/generator
and
https://documentation.zabbix.lan/specifications/3.4/zbxnext-3725

Structure:

**bin** - templates
* bin/in - metric_source files  
* bin/merged - intermediate merged files (not yet converted to zabbix_export.xsd)  
* bin/out - zabbix templates generated ready to be delivered and imported    

**src** - generator code 
Spring-boot and Apache Camel are used to glue XSLT transformations.    
## Install  

You will need maven to build.  

then do:  
```
cd templates
mvn package
```
grab jar file in target dir  


```
cd templates
java -jar target/zabbix-template-generator-0.5.jar
```  
or  

```
mkdir /opt/zabbix-template-generator
cp target/zabbix-template-generator-0.5.jar /opt/zabbix-template-generator
cp -Rf bin /opt/zabbix-template-generator
cd /opt/zabbix-template-generator
chmod u+x zabbix-template-generator-0.5.jar
./zabbix-template-generator-0.5.jar
```  


