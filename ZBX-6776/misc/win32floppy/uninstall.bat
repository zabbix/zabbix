@echo off
REM Uninstallation script for Windows ZABBIX Agent
REM Version 1
REM 
REM Written by Eugene Grigorjev, Nov 2005
REM email: eugene.grigorjev@zabbix.com

ECHO Uninstallation script for Windows ZABBIX Agent [ http://www.zabbix.com ]
ECHO.
ECHO Welcome to ZABBIX Agent uninstallation!
ECHO.

SET def_install_dir=%PROGRAMFILES%/zabbix

IF "%1"=="help" GOTO Syntax
IF "%1"=="/help" GOTO Syntax
IF "%1"=="--help" GOTO Syntax
IF "%1"=="-help" GOTO Syntax
IF "%1"=="-h" GOTO Syntax
IF "%1"=="/h" GOTO Syntax

IF NOT "%1"=="" SET install_dir=%1
IF "%1"=="" SET install_dir=%def_install_dir%

IF NOT EXIST "%install_dir%" GOTO Err_path

SET zabbix_agent=%install_dir%/ZabbixW32.exe
IF NOT EXIST "%zabbix_agent%" GOTO Err_agent

ECHO Stoping ZABBIX Agent srvice...

CALL "%zabbix_agent%" stop
IF ERRORLEVEL 1 ECHO UNINSTALL WARNING: Can't stop ZABBIX Agent service!

ECHO Removing ZABBIX Agent srvice...

CALL "%zabbix_agent%" remove
IF ERRORLEVEL 1 GOTO Err_remove

ECHO.
ECHO #############################################################
ECHO.
ECHO ZABBIX agent for Windows successfuly uninstaled from Your PC.
ECHO.
ECHO   Now you can remove:
ECHO           ZABBIX Agent binary file
ECHO           ZABBIX Agent log file (see config file)
ECHO           ZABBIX Agent config file
ECHO.
ECHO                  http://www.zabbix.com
ECHO.
ECHO #############################################################
ECHO.

GOTO End

:Err_agent
ECHO UNINSTAL ERROR: Can't find ZABBIX Agent binary file 'ZabbixW32.exe'! 
:Err_path
ECHO UNINSTAL ERROR: Please set the correct installation directory!
GOTO Syntax

:Err_remove
ECHO UNINSTALL ERROR: Can't remove ZABBIX Agent service"!
GOTO Syntax

:Syntax
ECHO.
ECHO -------------------------------------------------------------
ECHO Usage:  
ECHO    %0 ["install path]"
ECHO.
ECHO    Default installation path is "%def_install_dir%"
ECHO -------------------------------------------------------------

:End
@echo on
