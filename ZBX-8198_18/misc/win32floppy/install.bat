@echo off
REM Installation script for Windows ZABBIX Agent
REM Version 1
REM 
REM Written by Eugene Grigorjev, Nov 2005
REM email: eugene.grigorjev@zabbix.com

ECHO Installation script for Windows ZABBIX Agent [ http://www.zabbix.com ]
ECHO.
ECHO Welcome to ZABBIX Agent installation!
ECHO.

SET srcbindir=.
SET config_name=zabbix_agentd.conf
SET log_name=zabbix_agentd.log
SET pid_name=zabbix_agentd.pid
SET zabbix_agent=ZabbixW32.exe

SET def_install_dir=%PROGRAMFILES%/zabbix
SET def_config_dir=%def_install_dir%

SET Configfile=%def_config_dir%/%config_name%

if "%1"=="" GOTO Err_param
if "%2"=="" GOTO Err_param

:Install

REM default active parameters

SET Hostname=%1
SET serverip=%2
SET serverport=10051 
SET listenport=10050 
SET startagents=5
SET debuglevel=3
SET timeout=3

REM default hidden parameters, to enable this parameters
REM You can remove '#' symbol from line with this 
REM parameters to enable it.

SET listenip=127.0.0.1 
SET refAC=120
SET disableAC=1
SET notimewait=1

REM Check parameters and create needed direcoryes.

IF NOT "%3"=="" SET install_dir=%3
IF "%3"=="" SET install_dir=%def_install_dir%
IF NOT EXIST "%install_dir%" MKDIR "%install_dir%"
IF NOT EXIST "%install_dir%" GOTO Err_install_dir

IF NOT "%4"=="" SET config_dir=%4
IF "%4"=="" SET config_dir=%install_dir%
IF NOT EXIST "%config_dir%" MKDIR "%config_dir%"
IF NOT EXIST "%config_dir%" GOTO Err_config_dir

SET logfile=%install_dir%/%log_name%
SET pidfile=%install_dir%/%pid_name% 

SET Configfile=%config_dir%/%config_name%

ECHO Creating ZABBIX Agent configuration file "%Configfile%"

REM =============================================================================
REM =================== START OF CONFIGURATION FILE =============================
REM =============================================================================
ECHO # This is config file for zabbix_agentd > "%Configfile%"
ECHO # To get more information about ZABBIX, go http://www.zabbix.com >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO ############ GENERAL PARAMETERS ################# >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # List of comma delimited IP addresses (or hostnames) of ZABBIX Servers.  >> "%Configfile%"
ECHO # No spaces allowed. First entry is used for sending active checks. >> "%Configfile%"
ECHO # Note that hostnames must resolve hostname - IP address and >> "%Configfile%"
ECHO # IP address - hostname. >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO Server=%serverip% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Server port for sending active checks >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO ServerPort=%serverport% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Unique hostname. Required for active checks. >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO Hostname=%Hostname% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Listen port. Default is 10050 >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO ListenPort=%listenport%>> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # IP address to bind agent >> "%Configfile%"
ECHO # If missing, bind to all available IPs >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO #ListenIP=%listenip%>> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Number of pre-forked instances of zabbix_agentd. >> "%Configfile%"
ECHO # Default value is 5 >> "%Configfile%"
ECHO # This parameter must be between 1 and 16 >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO StartAgents=%startagents% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # How often refresh list of active checks. 2 minutes by default. >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO #RefreshActiveChecks=%refAC% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Disable active checks. The agent will work in passive mode listening server. >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO #DisableActive=%disableAC% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Specifies debug level >> "%Configfile%"
ECHO # 0 - debug is not created >> "%Configfile%"
ECHO # 1 - critical information >> "%Configfile%"
ECHO # 2 - error information >> "%Configfile%"
ECHO # 3 - warnings (default) >> "%Configfile%"
ECHO # 4 - for debugging (produces lots of information) >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO DebugLevel=%debuglevel% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Name of PID file >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO #PidFile=%pidfile%>> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Name of log file. >> "%Configfile%"
ECHO # If not set, syslog will be used >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO LogFile=%logfile% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Spend no more than Timeout seconds on processing >> "%Configfile%"
ECHO # Must be between 1 and 30 >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO Timeout=%timeout% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO ##### Experimental options. Use with care ! ##### >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # Get rid of sockets in TIME_WAIT state >> "%Configfile%"
ECHO # This will set socket option SO_LINGER >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO # NoTimeWait=%notimewait% >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO ##### End of experimental options >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO ####### USER-DEFINED MONITORED PARAMETERS ####### >> "%Configfile%"
ECHO # Format: "UserParameter='key','shell command'" >> "%Configfile%"
ECHO # Note that shell command must not return empty string or EOL only >> "%Configfile%"
ECHO. >> "%Configfile%"
ECHO UserParameter=mysql.version,mysql -V >> "%Configfile%"
REM =============================================================================
REM ==================== END OF CONFIGURATION FILE ==============================
REM =============================================================================

ECHO Installing ZABBIX Agent files...

COPY "%srcbindir%/%zabbix_agent%" "%install_dir%/%zabbix_agent%" > NULL
IF ERRORLEVEL 1 GOTO Err_copy

ECHO Creating ZABBIX Agent srvice...

CALL "%install_dir%/%zabbix_agent%" --config "%Configfile%" install 
IF ERRORLEVEL 1 GOTO Err_install

ECHO Starting ZABBIX Agent srvice...

CALL "%install_dir%/%zabbix_agent%" start
IF ERRORLEVEL 1 GOTO Err_start

ECHO.
ECHO #############################################################
ECHO                     Congratulations! 
ECHO   ZABBIX agent for Windows successfuly instaled on Your PC!
ECHO.
ECHO     Installation directory: %install_dir%
ECHO     Configureation file: %Configfile%
ECHO.
ECHO   ZABBIX agent have next configuration:
ECHO     Agent hostname for ZABBIX Server: %Hostname%
ECHO     ZABBIX Server IP: %serverip%
ECHO     ZABBIX Server port: %serverport%
ECHO     ZABBIX Agent listen port: %listenport%
ECHO     Connection timeout: %timeout%
ECHO     Start Agent count: %startagents%
ECHO     Debug level: %debuglevel%
ECHO     Log file: %logfile%
ECHO.
ECHO   IF You want change configurations or more detailed configure 
ECHO ZABBIX Agent, you can manualy change configureation file and 
ECHO restart ZABBIX Agent service.
ECHO.
ECHO   Now You can configure ZABBIX Server to monitore this PC.
ECHO.
ECHO            Thank You for using ZABBIX software.
ECHO                  http://www.zabbix.com
ECHO #############################################################
ECHO.

GOTO End

:Err_param
ECHO INSTALL ERROR: Please use script with required parameters!
GOTO Syntax 

:Err_start
ECHO INSTALL ERROR: Can't start ZABBIX Agent service!
GOTO Syntax 

:Err_install
ECHO INSTALL ERROR: Can't install ZABBIX Agent as service!
GOTO Syntax 

:Err_copy
ECHO INSTALL ERROR: Can't copy file "%srcbindir%.%zabbix_agent%" in to "%install_dir%"!
GOTO Syntax 

:Err_install_dir
ECHO INSTALL ERROR: Can't create installation directory "%install_dir%"!
GOTO Syntax 

:Err_config_dir
ECHO INSTALL ERROR: Can't create directory "%config_dir%" for configuretion file!
GOTO Syntax 

:Syntax
ECHO.
ECHO -------------------------------------------------------------
ECHO Usage:  
ECHO    %0 "hostname" "srver ip" ["install path]" ["config file dir"]
ECHO.
ECHO    Default installation path is "%def_install_dir%"
ECHO    Default configureation file is "%Configfile%"
ECHO -------------------------------------------------------------

:End
@echo on
