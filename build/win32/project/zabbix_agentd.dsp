# Microsoft Developer Studio Project File - Name="zabbix_agentd" - Package Owner=<4>
# Microsoft Developer Studio Generated Build File, Format Version 6.00
# ** DO NOT EDIT **

# TARGTYPE "Win32 (x86) Console Application" 0x0103

CFG=zabbix_agentd - Win32 Test
!MESSAGE This is not a valid makefile. To build this project using NMAKE,
!MESSAGE use the Export Makefile command and run
!MESSAGE 
!MESSAGE NMAKE /f "zabbix_agentd.mak".
!MESSAGE 
!MESSAGE You can specify a configuration when running NMAKE
!MESSAGE by defining the macro CFG on the command line. For example:
!MESSAGE 
!MESSAGE NMAKE /f "zabbix_agentd.mak" CFG="zabbix_agentd - Win32 Test"
!MESSAGE 
!MESSAGE Possible choices for configuration are:
!MESSAGE 
!MESSAGE "zabbix_agentd - Win32 Release" (based on "Win32 (x86) Console Application")
!MESSAGE "zabbix_agentd - Win32 Debug" (based on "Win32 (x86) Console Application")
!MESSAGE "zabbix_agentd - Win32 TODO" (based on "Win32 (x86) Console Application")
!MESSAGE "zabbix_agentd - Win32 Test" (based on "Win32 (x86) Console Application")
!MESSAGE 

# Begin Project
# PROP AllowPerConfigDependencies 0
# PROP Scc_ProjName ""
# PROP Scc_LocalPath ""
CPP=cl.exe
RSC=rc.exe

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"

# PROP BASE Use_MFC 0
# PROP BASE Use_Debug_Libraries 0
# PROP BASE Output_Dir "Release"
# PROP BASE Intermediate_Dir "Release"
# PROP BASE Target_Dir ""
# PROP Use_MFC 0
# PROP Use_Debug_Libraries 0
# PROP Output_Dir "Release"
# PROP Intermediate_Dir "Release"
# PROP Ignore_Export_Lib 0
# PROP Target_Dir ""
# ADD BASE CPP /nologo /W3 /GX /O2 /D "WIN32" /D "NDEBUG" /D "_CONSOLE" /D "_MBCS" /YX /FD /c
# ADD CPP /nologo /MT /W3 /GX /O2 /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "NDEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /FR /YX /FD /c
# ADD BASE RSC /l 0x419 /d "NDEBUG"
# ADD RSC /l 0x409 /fo"Release/zabbixw32.res" /d "NDEBUG"
BSC32=bscmake.exe
# ADD BASE BSC32 /nologo
# ADD BSC32 /nologo
LINK32=link.exe
# ADD BASE LINK32 kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /machine:I386
# ADD LINK32 ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /machine:I386

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"

# PROP BASE Use_MFC 0
# PROP BASE Use_Debug_Libraries 1
# PROP BASE Output_Dir "Debug"
# PROP BASE Intermediate_Dir "Debug"
# PROP BASE Target_Dir ""
# PROP Use_MFC 0
# PROP Use_Debug_Libraries 1
# PROP Output_Dir "Debug"
# PROP Intermediate_Dir "Debug"
# PROP Ignore_Export_Lib 0
# PROP Target_Dir ""
# ADD BASE CPP /nologo /W3 /Gm /GX /ZI /Od /D "WIN32" /D "_DEBUG" /D "_CONSOLE" /D "_MBCS" /YX /FD /GZ /c
# ADD CPP /nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /FR /YX /FD /GZ /c
# ADD BASE RSC /l 0x419 /d "_DEBUG"
# ADD RSC /l 0x409 /fo"Debug/zabbixw32.res" /d "_DEBUG"
BSC32=bscmake.exe
# ADD BASE BSC32 /nologo
# ADD BSC32 /nologo
LINK32=link.exe
# ADD BASE LINK32 kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept
# ADD LINK32 ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 TODO"

# PROP BASE Use_MFC 0
# PROP BASE Use_Debug_Libraries 1
# PROP BASE Output_Dir "zabbix_agentd___Win32_TODO"
# PROP BASE Intermediate_Dir "zabbix_agentd___Win32_TODO"
# PROP BASE Ignore_Export_Lib 0
# PROP BASE Target_Dir ""
# PROP Use_MFC 0
# PROP Use_Debug_Libraries 1
# PROP Output_Dir "TODO"
# PROP Intermediate_Dir "TODO"
# PROP Ignore_Export_Lib 0
# PROP Target_Dir ""
# ADD BASE CPP /nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /FR /YX /FD /GZ /c
# ADD CPP /nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /D "TODO" /FR /YX /FD /GZ /c
# ADD BASE RSC /l 0x409 /fo"Debug/zabbixw32.res" /d "_DEBUG"
# ADD RSC /l 0x409 /fo"Debug/zabbixw32.res" /d "_DEBUG"
BSC32=bscmake.exe
# ADD BASE BSC32 /nologo
# ADD BSC32 /nologo
LINK32=link.exe
# ADD BASE LINK32 ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept
# ADD LINK32 ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Test"

# PROP BASE Use_MFC 0
# PROP BASE Use_Debug_Libraries 1
# PROP BASE Output_Dir "zabbix_agentd___Win32_Test"
# PROP BASE Intermediate_Dir "zabbix_agentd___Win32_Test"
# PROP BASE Ignore_Export_Lib 0
# PROP BASE Target_Dir ""
# PROP Use_MFC 0
# PROP Use_Debug_Libraries 1
# PROP Output_Dir "Test"
# PROP Intermediate_Dir "Test"
# PROP Ignore_Export_Lib 0
# PROP Target_Dir ""
# ADD BASE CPP /nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /FR /YX /FD /GZ /c
# ADD CPP /nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /D "ZABBIX_TEST" /FR /YX /FD /GZ /c
# ADD BASE RSC /l 0x409 /fo"Debug/zabbixw32.res" /d "_DEBUG"
# ADD RSC /l 0x409 /fo"Debug/zabbixw32.res" /d "_DEBUG"
BSC32=bscmake.exe
# ADD BASE BSC32 /nologo
# ADD BSC32 /nologo
LINK32=link.exe
# ADD BASE LINK32 ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept
# ADD LINK32 ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept

!ENDIF 

# Begin Target

# Name "zabbix_agentd - Win32 Release"
# Name "zabbix_agentd - Win32 Debug"
# Name "zabbix_agentd - Win32 TODO"
# Name "zabbix_agentd - Win32 Test"
# Begin Group "src"

# PROP Default_Filter ""
# Begin Group "lib"

# PROP Default_Filter ""
# Begin Group "zbxcommon"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\alias.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\comms.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\gnuregex.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\misc.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\regexp.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\str.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\xml.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\zbxgetopt.c
# End Source File
# End Group
# Begin Group "zbxlog"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxlog\log.c
# End Source File
# End Group
# Begin Group "zbxcrypto"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcrypto\base64.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcrypto\md5.c
# End Source File
# End Group
# Begin Group "zbxnet"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxnet\security.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxnet\zbxsock.c
# End Source File
# End Group
# Begin Group "zbxconf"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxconf\cfg.c
# End Source File
# End Group
# Begin Group "zbxsysinfo"

# PROP Default_Filter ""
# Begin Group "common"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\common\common.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\common\file.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\common\http.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\common\ntp.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\common\system.c
# End Source File
# End Group
# Begin Group "win32"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\cpu.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\diskio.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\diskspace.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\inodes.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\kernel.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\memory.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\net.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\proc.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\sensors.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\swap.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\system_w32.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\uptime.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\win32.c
# End Source File
# End Group
# End Group
# Begin Group "zbxwin32"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxwin32\perfmon.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxwin32\service.c
# End Source File
# End Group
# Begin Group "zbxnix"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxnix\daemon.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"

# PROP Exclude_From_Build 1

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"

# PROP Exclude_From_Build 1

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 TODO"

# PROP BASE Exclude_From_Build 1
# PROP Exclude_From_Build 1

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Test"

# PROP BASE Exclude_From_Build 1
# PROP Exclude_From_Build 1

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxnix\pid.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"

# PROP Exclude_From_Build 1

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"

# PROP Exclude_From_Build 1

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 TODO"

# PROP Exclude_From_Build 1

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Test"

# PROP BASE Exclude_From_Build 1
# PROP Exclude_From_Build 1

!ENDIF 

# End Source File
# End Group
# Begin Group "zbxsys"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsys\mutexs.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsys\threads.c
# End Source File
# End Group
# Begin Group "zbxplugin"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxplugin\zbxplugin.c
# End Source File
# End Group
# End Group
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\active.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\active.h
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\cpustat.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\cpustat.h
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\diskdevices.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\diskdevices.h
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\interfaces.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\interfaces.h
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\listener.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\listener.h
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\logfiles.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\logfiles.h
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\stats.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\stats.h
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\zabbix_agentd.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\zbxconf.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\zabbix_agent\zbxconf.h
# End Source File
# End Group
# Begin Group "inlcude"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\include\alias.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\cfg.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\common.h
# End Source File
# Begin Source File

SOURCE=..\include\config.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\daemon.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\db.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\email.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\gnuregex.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\log.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\md5.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\mutexs.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\perfmon.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\pid.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\service.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\sms.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\sysinc.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\sysinfo.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\threads.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zbxgetopt.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zbxplugin.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zbxsecurity.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zbxsock.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zbxtypes.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zlog.h
# End Source File
# End Group
# Begin Group "Resource Files"

# PROP Default_Filter "ico;cur;bmp;dlg;rc2;rct;bin;rgs;gif;jpg;jpeg;jpe"
# End Group
# End Target
# End Project
