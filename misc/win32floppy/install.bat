@echo off
rem 
rem Installs Zabbix in Windows
rem directory c:\zabbix
rem
rem  	Fabricio Ferrari, jan 2004
rem  	fabricio@ferrari.pro.br
 
echo.
echo. 
echo Installing ZabbixW32
echo. 

echo Creating directory  c:\zabbix ...
mkdir c:\zabbix
echo.

echo Copying  ZabbixW32.exe ...
copy a:\ZabbixW32.exe c:\zabbix
echo.

echo Copying zabbix_agentd.conf ...
copy a:\zabbix_agentd.conf c:\zabbix
echo.

echo.
echo Agent configured to server 127.0.0.1
echo Modify your c:\zabbix\zabbix_agentd.conf to your needs.
echo.

echo Configuring ZabbixW32 as a system service

c:
cd zabbix
ZabbixW32.exe --config c:\zabbix\zabbix_agentd.conf install

echo.
echo Should be working. Verify in the system services!
echo.

echo.
echo  Zabbix [www.zabbix.org]
echo.
echo.
echo.
