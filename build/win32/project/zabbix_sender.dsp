# Microsoft Developer Studio Project File - Name="zabbix_sender" - Package Owner=<4>
# Microsoft Developer Studio Generated Build File, Format Version 6.00
# ** DO NOT EDIT **

# TARGTYPE "Win32 (x86) Console Application" 0x0103

CFG=zabbix_sender - Win32 Debug
!MESSAGE This is not a valid makefile. To build this project using NMAKE,
!MESSAGE use the Export Makefile command and run
!MESSAGE 
!MESSAGE NMAKE /f "zabbix_sender.mak".
!MESSAGE 
!MESSAGE You can specify a configuration when running NMAKE
!MESSAGE by defining the macro CFG on the command line. For example:
!MESSAGE 
!MESSAGE NMAKE /f "zabbix_sender.mak" CFG="zabbix_sender - Win32 Debug"
!MESSAGE 
!MESSAGE Possible choices for configuration are:
!MESSAGE 
!MESSAGE "zabbix_sender - Win32 Release" (based on "Win32 (x86) Console Application")
!MESSAGE "zabbix_sender - Win32 Debug" (based on "Win32 (x86) Console Application")
!MESSAGE 

# Begin Project
# PROP AllowPerConfigDependencies 0
# PROP Scc_ProjName ""
# PROP Scc_LocalPath ""
CPP=cl.exe
RSC=rc.exe

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

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
# ADD CPP /nologo /MTd /W3 /GX /O2 /I "./" /I "../include/" /I "../../../include/" /D "_WINDOWS" /D "HAVE_LDAP" /D "HAVE_ASSERT_H" /D "WIN32" /D "NDEBUG" /D "_CONSOLE" /D "_MBCS" /FR /YX /FD /c
# ADD BASE RSC /l 0x419 /d "NDEBUG"
# ADD RSC /l 0x419 /d "NDEBUG"
BSC32=bscmake.exe
# ADD BASE BSC32 /nologo
# ADD BSC32 /nologo
LINK32=link.exe
# ADD BASE LINK32 kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /machine:I386
# ADD LINK32 ws2_32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /machine:I386 /out:"../../../bin/win32/zabbix_sender.exe"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

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
# ADD CPP /nologo /MTd /W3 /Gm /GX /ZI /Od /I "./" /I "../include/" /I "../../../include/" /I "../../../src/zabbix_sender" /D "_DEBUG" /D "WIN32" /D "HAVE_LDAP" /D "HAVE_ASSERT_H" /D "_CONSOLE" /D "_MBCS" /D "_WINDOWS" /FR /YX /FD /GZ /c
# ADD BASE RSC /l 0x419 /d "_DEBUG"
# ADD RSC /l 0x419 /d "_DEBUG"
BSC32=bscmake.exe
# ADD BASE BSC32 /nologo
# ADD BSC32 /nologo
LINK32=link.exe
# ADD BASE LINK32 kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept
# ADD LINK32 ws2_32.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /debug /machine:I386 /pdbtype:sept

!ENDIF 

# Begin Target

# Name "zabbix_sender - Win32 Release"
# Name "zabbix_sender - Win32 Debug"
# Begin Group "src"

# PROP Default_Filter "cpp;c;cxx;rc;def;r;odl;idl;hpj;bat"
# Begin Group "libs"

# PROP Default_Filter ""
# Begin Group "zbxsys"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsys\mutexs.c
# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxsys\threads.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxthreads"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxthreads"

!ENDIF 

# End Source File
# End Group
# Begin Group "zbxlog"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxlog\log.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxlog"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxlog"

!ENDIF 

# End Source File
# End Group
# Begin Group "zbxconf"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxconf\cfg.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxconf"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxconf"

!ENDIF 

# End Source File
# End Group
# Begin Group "zbxcomms"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcomms\comms.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcomms"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcomms"

!ENDIF 

# End Source File
# End Group
# Begin Group "zbxcommon"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\alias.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\comms.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\gnuregex.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\misc.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\regexp.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\str.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\xml.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcommon\zbxgetopt.c

!IF  "$(CFG)" == "zabbix_sender - Win32 Release"

# PROP Intermediate_Dir "Release/zbxcommon"

!ELSEIF  "$(CFG)" == "zabbix_sender - Win32 Debug"

# PROP Intermediate_Dir "Debug/zbxcommon"

!ENDIF 

# End Source File
# End Group
# Begin Group "zbxcrypto"

# PROP Default_Filter ""
# Begin Source File

SOURCE=..\..\..\src\libs\zbxcrypto\base64.c
# End Source File
# End Group
# End Group
# Begin Source File

SOURCE=..\..\..\src\zabbix_sender\zabbix_sender.c
# End Source File
# End Group
# Begin Group "include"

# PROP Default_Filter "h;hpp;hxx;hm;inl"
# Begin Source File

SOURCE=..\..\..\include\cfg.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\common.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\comms.h
# End Source File
# Begin Source File

SOURCE=..\include\config.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\log.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\sysinc.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\threads.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zbxgetopt.h
# End Source File
# Begin Source File

SOURCE=..\..\..\include\zbxtypes.h
# End Source File
# End Group
# Begin Group "Resource Files"

# PROP Default_Filter "ico;cur;bmp;dlg;rc2;rct;bin;rgs;gif;jpg;jpeg;jpe"
# End Group
# End Target
# End Project
