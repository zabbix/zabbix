# Microsoft Developer Studio Generated NMAKE File, Based on zabbix_agentd.dsp
!IF "$(CFG)" == ""
CFG=zabbix_agentd - Win32 Test
!MESSAGE No configuration specified. Defaulting to zabbix_agentd - Win32 Test.
!ENDIF 

!IF "$(CFG)" != "zabbix_agentd - Win32 Release" && "$(CFG)" != "zabbix_agentd - Win32 Debug" && "$(CFG)" != "zabbix_agentd - Win32 TODO" && "$(CFG)" != "zabbix_agentd - Win32 Test"
!MESSAGE Invalid configuration "$(CFG)" specified.
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
!ERROR An invalid configuration is specified.
!ENDIF 

!IF "$(OS)" == "Windows_NT"
NULL=
!ELSE 
NULL=nul
!ENDIF 

CPP=cl.exe
RSC=rc.exe

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"

OUTDIR=.\Release
INTDIR=.\Release
# Begin Custom Macros
OutDir=.\Release
# End Custom Macros

ALL : ".\Msg00001.bin" ".\messages.h" "$(OUTDIR)\zabbix_agentd.exe" "$(OUTDIR)\zabbix_agentd.bsc"


CLEAN :
	-@erase "$(INTDIR)\active.obj"
	-@erase "$(INTDIR)\active.sbr"
	-@erase "$(INTDIR)\alias.obj"
	-@erase "$(INTDIR)\alias.sbr"
	-@erase "$(INTDIR)\base64.obj"
	-@erase "$(INTDIR)\base64.sbr"
	-@erase "$(INTDIR)\cfg.obj"
	-@erase "$(INTDIR)\cfg.sbr"
	-@erase "$(INTDIR)\common.obj"
	-@erase "$(INTDIR)\common.sbr"
	-@erase "$(INTDIR)\comms.obj"
	-@erase "$(INTDIR)\comms.sbr"
	-@erase "$(INTDIR)\cpu.obj"
	-@erase "$(INTDIR)\cpu.sbr"
	-@erase "$(INTDIR)\cpustat.obj"
	-@erase "$(INTDIR)\cpustat.sbr"
	-@erase "$(INTDIR)\diskdevices.obj"
	-@erase "$(INTDIR)\diskdevices.sbr"
	-@erase "$(INTDIR)\diskio.obj"
	-@erase "$(INTDIR)\diskio.sbr"
	-@erase "$(INTDIR)\diskspace.obj"
	-@erase "$(INTDIR)\diskspace.sbr"
	-@erase "$(INTDIR)\file.obj"
	-@erase "$(INTDIR)\file.sbr"
	-@erase "$(INTDIR)\gnuregex.obj"
	-@erase "$(INTDIR)\gnuregex.sbr"
	-@erase "$(INTDIR)\http.obj"
	-@erase "$(INTDIR)\http.sbr"
	-@erase "$(INTDIR)\inodes.obj"
	-@erase "$(INTDIR)\inodes.sbr"
	-@erase "$(INTDIR)\interfaces.obj"
	-@erase "$(INTDIR)\interfaces.sbr"
	-@erase "$(INTDIR)\kernel.obj"
	-@erase "$(INTDIR)\kernel.sbr"
	-@erase "$(INTDIR)\listener.obj"
	-@erase "$(INTDIR)\listener.sbr"
	-@erase "$(INTDIR)\log.obj"
	-@erase "$(INTDIR)\log.sbr"
	-@erase "$(INTDIR)\logfiles.obj"
	-@erase "$(INTDIR)\logfiles.sbr"
	-@erase "$(INTDIR)\md5.obj"
	-@erase "$(INTDIR)\md5.sbr"
	-@erase "$(INTDIR)\memory.obj"
	-@erase "$(INTDIR)\memory.sbr"
	-@erase "$(INTDIR)\misc.obj"
	-@erase "$(INTDIR)\misc.sbr"
	-@erase "$(INTDIR)\mutexs.obj"
	-@erase "$(INTDIR)\mutexs.sbr"
	-@erase "$(INTDIR)\net.obj"
	-@erase "$(INTDIR)\net.sbr"
	-@erase "$(INTDIR)\ntp.obj"
	-@erase "$(INTDIR)\ntp.sbr"
	-@erase "$(INTDIR)\perfmon.obj"
	-@erase "$(INTDIR)\perfmon.sbr"
	-@erase "$(INTDIR)\proc.obj"
	-@erase "$(INTDIR)\proc.sbr"
	-@erase "$(INTDIR)\regexp.obj"
	-@erase "$(INTDIR)\regexp.sbr"
	-@erase "$(INTDIR)\security.obj"
	-@erase "$(INTDIR)\security.sbr"
	-@erase "$(INTDIR)\sensors.obj"
	-@erase "$(INTDIR)\sensors.sbr"
	-@erase "$(INTDIR)\service.obj"
	-@erase "$(INTDIR)\service.sbr"
	-@erase "$(INTDIR)\stats.obj"
	-@erase "$(INTDIR)\stats.sbr"
	-@erase "$(INTDIR)\str.obj"
	-@erase "$(INTDIR)\str.sbr"
	-@erase "$(INTDIR)\swap.obj"
	-@erase "$(INTDIR)\swap.sbr"
	-@erase "$(INTDIR)\system.obj"
	-@erase "$(INTDIR)\system.sbr"
	-@erase "$(INTDIR)\system_w32.obj"
	-@erase "$(INTDIR)\system_w32.sbr"
	-@erase "$(INTDIR)\threads.obj"
	-@erase "$(INTDIR)\threads.sbr"
	-@erase "$(INTDIR)\uptime.obj"
	-@erase "$(INTDIR)\uptime.sbr"
	-@erase "$(INTDIR)\vc60.idb"
	-@erase "$(INTDIR)\win32.obj"
	-@erase "$(INTDIR)\win32.sbr"
	-@erase "$(INTDIR)\xml.obj"
	-@erase "$(INTDIR)\xml.sbr"
	-@erase "$(INTDIR)\zabbix_agentd.obj"
	-@erase "$(INTDIR)\zabbix_agentd.sbr"
	-@erase "$(INTDIR)\zabbixw32.res"
	-@erase "$(INTDIR)\zbxconf.obj"
	-@erase "$(INTDIR)\zbxconf.sbr"
	-@erase "$(INTDIR)\zbxgetopt.obj"
	-@erase "$(INTDIR)\zbxgetopt.sbr"
	-@erase "$(INTDIR)\zbxplugin.obj"
	-@erase "$(INTDIR)\zbxplugin.sbr"
	-@erase "$(INTDIR)\zbxsock.obj"
	-@erase "$(INTDIR)\zbxsock.sbr"
	-@erase "$(OUTDIR)\zabbix_agentd.bsc"
	-@erase "$(OUTDIR)\zabbix_agentd.exe"
	-@erase "messages.h"
	-@erase "Msg00001.bin"

"$(OUTDIR)" :
    if not exist "$(OUTDIR)/$(NULL)" mkdir "$(OUTDIR)"

CPP_PROJ=/nologo /MT /W3 /GX /O2 /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "NDEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /FR"$(INTDIR)\\" /Fp"$(INTDIR)\zabbix_agentd.pch" /YX /Fo"$(INTDIR)\\" /Fd"$(INTDIR)\\" /FD /c 
RSC_PROJ=/l 0x409 /fo"$(INTDIR)\zabbixw32.res" /d "NDEBUG" 
BSC32=bscmake.exe
BSC32_FLAGS=/nologo /o"$(OUTDIR)\zabbix_agentd.bsc" 
BSC32_SBRS= \
	"$(INTDIR)\alias.sbr" \
	"$(INTDIR)\comms.sbr" \
	"$(INTDIR)\gnuregex.sbr" \
	"$(INTDIR)\misc.sbr" \
	"$(INTDIR)\regexp.sbr" \
	"$(INTDIR)\str.sbr" \
	"$(INTDIR)\xml.sbr" \
	"$(INTDIR)\zbxgetopt.sbr" \
	"$(INTDIR)\log.sbr" \
	"$(INTDIR)\base64.sbr" \
	"$(INTDIR)\md5.sbr" \
	"$(INTDIR)\security.sbr" \
	"$(INTDIR)\zbxsock.sbr" \
	"$(INTDIR)\cfg.sbr" \
	"$(INTDIR)\common.sbr" \
	"$(INTDIR)\file.sbr" \
	"$(INTDIR)\http.sbr" \
	"$(INTDIR)\ntp.sbr" \
	"$(INTDIR)\system.sbr" \
	"$(INTDIR)\cpu.sbr" \
	"$(INTDIR)\diskio.sbr" \
	"$(INTDIR)\diskspace.sbr" \
	"$(INTDIR)\inodes.sbr" \
	"$(INTDIR)\kernel.sbr" \
	"$(INTDIR)\memory.sbr" \
	"$(INTDIR)\net.sbr" \
	"$(INTDIR)\proc.sbr" \
	"$(INTDIR)\sensors.sbr" \
	"$(INTDIR)\swap.sbr" \
	"$(INTDIR)\system_w32.sbr" \
	"$(INTDIR)\uptime.sbr" \
	"$(INTDIR)\win32.sbr" \
	"$(INTDIR)\perfmon.sbr" \
	"$(INTDIR)\service.sbr" \
	"$(INTDIR)\mutexs.sbr" \
	"$(INTDIR)\threads.sbr" \
	"$(INTDIR)\zbxplugin.sbr" \
	"$(INTDIR)\active.sbr" \
	"$(INTDIR)\cpustat.sbr" \
	"$(INTDIR)\diskdevices.sbr" \
	"$(INTDIR)\interfaces.sbr" \
	"$(INTDIR)\listener.sbr" \
	"$(INTDIR)\logfiles.sbr" \
	"$(INTDIR)\stats.sbr" \
	"$(INTDIR)\zabbix_agentd.sbr" \
	"$(INTDIR)\zbxconf.sbr"

"$(OUTDIR)\zabbix_agentd.bsc" : "$(OUTDIR)" $(BSC32_SBRS)
    $(BSC32) @<<
  $(BSC32_FLAGS) $(BSC32_SBRS)
<<

LINK32=link.exe
LINK32_FLAGS=ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /incremental:no /pdb:"$(OUTDIR)\zabbix_agentd.pdb" /machine:I386 /out:"$(OUTDIR)\zabbix_agentd.exe" 
LINK32_OBJS= \
	"$(INTDIR)\alias.obj" \
	"$(INTDIR)\comms.obj" \
	"$(INTDIR)\gnuregex.obj" \
	"$(INTDIR)\misc.obj" \
	"$(INTDIR)\regexp.obj" \
	"$(INTDIR)\str.obj" \
	"$(INTDIR)\xml.obj" \
	"$(INTDIR)\zbxgetopt.obj" \
	"$(INTDIR)\log.obj" \
	"$(INTDIR)\base64.obj" \
	"$(INTDIR)\md5.obj" \
	"$(INTDIR)\security.obj" \
	"$(INTDIR)\zbxsock.obj" \
	"$(INTDIR)\cfg.obj" \
	"$(INTDIR)\common.obj" \
	"$(INTDIR)\file.obj" \
	"$(INTDIR)\http.obj" \
	"$(INTDIR)\ntp.obj" \
	"$(INTDIR)\system.obj" \
	"$(INTDIR)\cpu.obj" \
	"$(INTDIR)\diskio.obj" \
	"$(INTDIR)\diskspace.obj" \
	"$(INTDIR)\inodes.obj" \
	"$(INTDIR)\kernel.obj" \
	"$(INTDIR)\memory.obj" \
	"$(INTDIR)\net.obj" \
	"$(INTDIR)\proc.obj" \
	"$(INTDIR)\sensors.obj" \
	"$(INTDIR)\swap.obj" \
	"$(INTDIR)\system_w32.obj" \
	"$(INTDIR)\uptime.obj" \
	"$(INTDIR)\win32.obj" \
	"$(INTDIR)\perfmon.obj" \
	"$(INTDIR)\service.obj" \
	"$(INTDIR)\mutexs.obj" \
	"$(INTDIR)\threads.obj" \
	"$(INTDIR)\zbxplugin.obj" \
	"$(INTDIR)\active.obj" \
	"$(INTDIR)\cpustat.obj" \
	"$(INTDIR)\diskdevices.obj" \
	"$(INTDIR)\interfaces.obj" \
	"$(INTDIR)\listener.obj" \
	"$(INTDIR)\logfiles.obj" \
	"$(INTDIR)\stats.obj" \
	"$(INTDIR)\zabbix_agentd.obj" \
	"$(INTDIR)\zbxconf.obj" \
	"$(INTDIR)\zabbixw32.res"

"$(OUTDIR)\zabbix_agentd.exe" : "$(OUTDIR)" $(DEF_FILE) $(LINK32_OBJS)
    $(LINK32) @<<
  $(LINK32_FLAGS) $(LINK32_OBJS)
<<

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"

OUTDIR=.\Debug
INTDIR=.\Debug
# Begin Custom Macros
OutDir=.\Debug
# End Custom Macros

ALL : ".\Msg00001.bin" ".\messages.h" "$(OUTDIR)\zabbix_agentd.exe" "$(OUTDIR)\zabbix_agentd.bsc"


CLEAN :
	-@erase "$(INTDIR)\active.obj"
	-@erase "$(INTDIR)\active.sbr"
	-@erase "$(INTDIR)\alias.obj"
	-@erase "$(INTDIR)\alias.sbr"
	-@erase "$(INTDIR)\base64.obj"
	-@erase "$(INTDIR)\base64.sbr"
	-@erase "$(INTDIR)\cfg.obj"
	-@erase "$(INTDIR)\cfg.sbr"
	-@erase "$(INTDIR)\common.obj"
	-@erase "$(INTDIR)\common.sbr"
	-@erase "$(INTDIR)\comms.obj"
	-@erase "$(INTDIR)\comms.sbr"
	-@erase "$(INTDIR)\cpu.obj"
	-@erase "$(INTDIR)\cpu.sbr"
	-@erase "$(INTDIR)\cpustat.obj"
	-@erase "$(INTDIR)\cpustat.sbr"
	-@erase "$(INTDIR)\diskdevices.obj"
	-@erase "$(INTDIR)\diskdevices.sbr"
	-@erase "$(INTDIR)\diskio.obj"
	-@erase "$(INTDIR)\diskio.sbr"
	-@erase "$(INTDIR)\diskspace.obj"
	-@erase "$(INTDIR)\diskspace.sbr"
	-@erase "$(INTDIR)\file.obj"
	-@erase "$(INTDIR)\file.sbr"
	-@erase "$(INTDIR)\gnuregex.obj"
	-@erase "$(INTDIR)\gnuregex.sbr"
	-@erase "$(INTDIR)\http.obj"
	-@erase "$(INTDIR)\http.sbr"
	-@erase "$(INTDIR)\inodes.obj"
	-@erase "$(INTDIR)\inodes.sbr"
	-@erase "$(INTDIR)\interfaces.obj"
	-@erase "$(INTDIR)\interfaces.sbr"
	-@erase "$(INTDIR)\kernel.obj"
	-@erase "$(INTDIR)\kernel.sbr"
	-@erase "$(INTDIR)\listener.obj"
	-@erase "$(INTDIR)\listener.sbr"
	-@erase "$(INTDIR)\log.obj"
	-@erase "$(INTDIR)\log.sbr"
	-@erase "$(INTDIR)\logfiles.obj"
	-@erase "$(INTDIR)\logfiles.sbr"
	-@erase "$(INTDIR)\md5.obj"
	-@erase "$(INTDIR)\md5.sbr"
	-@erase "$(INTDIR)\memory.obj"
	-@erase "$(INTDIR)\memory.sbr"
	-@erase "$(INTDIR)\misc.obj"
	-@erase "$(INTDIR)\misc.sbr"
	-@erase "$(INTDIR)\mutexs.obj"
	-@erase "$(INTDIR)\mutexs.sbr"
	-@erase "$(INTDIR)\net.obj"
	-@erase "$(INTDIR)\net.sbr"
	-@erase "$(INTDIR)\ntp.obj"
	-@erase "$(INTDIR)\ntp.sbr"
	-@erase "$(INTDIR)\perfmon.obj"
	-@erase "$(INTDIR)\perfmon.sbr"
	-@erase "$(INTDIR)\proc.obj"
	-@erase "$(INTDIR)\proc.sbr"
	-@erase "$(INTDIR)\regexp.obj"
	-@erase "$(INTDIR)\regexp.sbr"
	-@erase "$(INTDIR)\security.obj"
	-@erase "$(INTDIR)\security.sbr"
	-@erase "$(INTDIR)\sensors.obj"
	-@erase "$(INTDIR)\sensors.sbr"
	-@erase "$(INTDIR)\service.obj"
	-@erase "$(INTDIR)\service.sbr"
	-@erase "$(INTDIR)\stats.obj"
	-@erase "$(INTDIR)\stats.sbr"
	-@erase "$(INTDIR)\str.obj"
	-@erase "$(INTDIR)\str.sbr"
	-@erase "$(INTDIR)\swap.obj"
	-@erase "$(INTDIR)\swap.sbr"
	-@erase "$(INTDIR)\system.obj"
	-@erase "$(INTDIR)\system.sbr"
	-@erase "$(INTDIR)\system_w32.obj"
	-@erase "$(INTDIR)\system_w32.sbr"
	-@erase "$(INTDIR)\threads.obj"
	-@erase "$(INTDIR)\threads.sbr"
	-@erase "$(INTDIR)\uptime.obj"
	-@erase "$(INTDIR)\uptime.sbr"
	-@erase "$(INTDIR)\vc60.idb"
	-@erase "$(INTDIR)\vc60.pdb"
	-@erase "$(INTDIR)\win32.obj"
	-@erase "$(INTDIR)\win32.sbr"
	-@erase "$(INTDIR)\xml.obj"
	-@erase "$(INTDIR)\xml.sbr"
	-@erase "$(INTDIR)\zabbix_agentd.obj"
	-@erase "$(INTDIR)\zabbix_agentd.sbr"
	-@erase "$(INTDIR)\zabbixw32.res"
	-@erase "$(INTDIR)\zbxconf.obj"
	-@erase "$(INTDIR)\zbxconf.sbr"
	-@erase "$(INTDIR)\zbxgetopt.obj"
	-@erase "$(INTDIR)\zbxgetopt.sbr"
	-@erase "$(INTDIR)\zbxplugin.obj"
	-@erase "$(INTDIR)\zbxplugin.sbr"
	-@erase "$(INTDIR)\zbxsock.obj"
	-@erase "$(INTDIR)\zbxsock.sbr"
	-@erase "$(OUTDIR)\zabbix_agentd.bsc"
	-@erase "$(OUTDIR)\zabbix_agentd.exe"
	-@erase "$(OUTDIR)\zabbix_agentd.ilk"
	-@erase "$(OUTDIR)\zabbix_agentd.pdb"
	-@erase "messages.h"
	-@erase "Msg00001.bin"

"$(OUTDIR)" :
    if not exist "$(OUTDIR)/$(NULL)" mkdir "$(OUTDIR)"

CPP_PROJ=/nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /FR"$(INTDIR)\\" /Fp"$(INTDIR)\zabbix_agentd.pch" /YX /Fo"$(INTDIR)\\" /Fd"$(INTDIR)\\" /FD /GZ /c 
RSC_PROJ=/l 0x409 /fo"$(INTDIR)\zabbixw32.res" /d "_DEBUG" 
BSC32=bscmake.exe
BSC32_FLAGS=/nologo /o"$(OUTDIR)\zabbix_agentd.bsc" 
BSC32_SBRS= \
	"$(INTDIR)\alias.sbr" \
	"$(INTDIR)\comms.sbr" \
	"$(INTDIR)\gnuregex.sbr" \
	"$(INTDIR)\misc.sbr" \
	"$(INTDIR)\regexp.sbr" \
	"$(INTDIR)\str.sbr" \
	"$(INTDIR)\xml.sbr" \
	"$(INTDIR)\zbxgetopt.sbr" \
	"$(INTDIR)\log.sbr" \
	"$(INTDIR)\base64.sbr" \
	"$(INTDIR)\md5.sbr" \
	"$(INTDIR)\security.sbr" \
	"$(INTDIR)\zbxsock.sbr" \
	"$(INTDIR)\cfg.sbr" \
	"$(INTDIR)\common.sbr" \
	"$(INTDIR)\file.sbr" \
	"$(INTDIR)\http.sbr" \
	"$(INTDIR)\ntp.sbr" \
	"$(INTDIR)\system.sbr" \
	"$(INTDIR)\cpu.sbr" \
	"$(INTDIR)\diskio.sbr" \
	"$(INTDIR)\diskspace.sbr" \
	"$(INTDIR)\inodes.sbr" \
	"$(INTDIR)\kernel.sbr" \
	"$(INTDIR)\memory.sbr" \
	"$(INTDIR)\net.sbr" \
	"$(INTDIR)\proc.sbr" \
	"$(INTDIR)\sensors.sbr" \
	"$(INTDIR)\swap.sbr" \
	"$(INTDIR)\system_w32.sbr" \
	"$(INTDIR)\uptime.sbr" \
	"$(INTDIR)\win32.sbr" \
	"$(INTDIR)\perfmon.sbr" \
	"$(INTDIR)\service.sbr" \
	"$(INTDIR)\mutexs.sbr" \
	"$(INTDIR)\threads.sbr" \
	"$(INTDIR)\zbxplugin.sbr" \
	"$(INTDIR)\active.sbr" \
	"$(INTDIR)\cpustat.sbr" \
	"$(INTDIR)\diskdevices.sbr" \
	"$(INTDIR)\interfaces.sbr" \
	"$(INTDIR)\listener.sbr" \
	"$(INTDIR)\logfiles.sbr" \
	"$(INTDIR)\stats.sbr" \
	"$(INTDIR)\zabbix_agentd.sbr" \
	"$(INTDIR)\zbxconf.sbr"

"$(OUTDIR)\zabbix_agentd.bsc" : "$(OUTDIR)" $(BSC32_SBRS)
    $(BSC32) @<<
  $(BSC32_FLAGS) $(BSC32_SBRS)
<<

LINK32=link.exe
LINK32_FLAGS=ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /incremental:yes /pdb:"$(OUTDIR)\zabbix_agentd.pdb" /debug /machine:I386 /out:"$(OUTDIR)\zabbix_agentd.exe" /pdbtype:sept 
LINK32_OBJS= \
	"$(INTDIR)\alias.obj" \
	"$(INTDIR)\comms.obj" \
	"$(INTDIR)\gnuregex.obj" \
	"$(INTDIR)\misc.obj" \
	"$(INTDIR)\regexp.obj" \
	"$(INTDIR)\str.obj" \
	"$(INTDIR)\xml.obj" \
	"$(INTDIR)\zbxgetopt.obj" \
	"$(INTDIR)\log.obj" \
	"$(INTDIR)\base64.obj" \
	"$(INTDIR)\md5.obj" \
	"$(INTDIR)\security.obj" \
	"$(INTDIR)\zbxsock.obj" \
	"$(INTDIR)\cfg.obj" \
	"$(INTDIR)\common.obj" \
	"$(INTDIR)\file.obj" \
	"$(INTDIR)\http.obj" \
	"$(INTDIR)\ntp.obj" \
	"$(INTDIR)\system.obj" \
	"$(INTDIR)\cpu.obj" \
	"$(INTDIR)\diskio.obj" \
	"$(INTDIR)\diskspace.obj" \
	"$(INTDIR)\inodes.obj" \
	"$(INTDIR)\kernel.obj" \
	"$(INTDIR)\memory.obj" \
	"$(INTDIR)\net.obj" \
	"$(INTDIR)\proc.obj" \
	"$(INTDIR)\sensors.obj" \
	"$(INTDIR)\swap.obj" \
	"$(INTDIR)\system_w32.obj" \
	"$(INTDIR)\uptime.obj" \
	"$(INTDIR)\win32.obj" \
	"$(INTDIR)\perfmon.obj" \
	"$(INTDIR)\service.obj" \
	"$(INTDIR)\mutexs.obj" \
	"$(INTDIR)\threads.obj" \
	"$(INTDIR)\zbxplugin.obj" \
	"$(INTDIR)\active.obj" \
	"$(INTDIR)\cpustat.obj" \
	"$(INTDIR)\diskdevices.obj" \
	"$(INTDIR)\interfaces.obj" \
	"$(INTDIR)\listener.obj" \
	"$(INTDIR)\logfiles.obj" \
	"$(INTDIR)\stats.obj" \
	"$(INTDIR)\zabbix_agentd.obj" \
	"$(INTDIR)\zbxconf.obj" \
	"$(INTDIR)\zabbixw32.res"

"$(OUTDIR)\zabbix_agentd.exe" : "$(OUTDIR)" $(DEF_FILE) $(LINK32_OBJS)
    $(LINK32) @<<
  $(LINK32_FLAGS) $(LINK32_OBJS)
<<

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 TODO"

OUTDIR=.\TODO
INTDIR=.\TODO
# Begin Custom Macros
OutDir=.\TODO
# End Custom Macros

ALL : ".\Msg00001.bin" ".\messages.h" "$(OUTDIR)\zabbix_agentd.exe" "$(OUTDIR)\zabbix_agentd.bsc"


CLEAN :
	-@erase "$(INTDIR)\active.obj"
	-@erase "$(INTDIR)\active.sbr"
	-@erase "$(INTDIR)\alias.obj"
	-@erase "$(INTDIR)\alias.sbr"
	-@erase "$(INTDIR)\base64.obj"
	-@erase "$(INTDIR)\base64.sbr"
	-@erase "$(INTDIR)\cfg.obj"
	-@erase "$(INTDIR)\cfg.sbr"
	-@erase "$(INTDIR)\common.obj"
	-@erase "$(INTDIR)\common.sbr"
	-@erase "$(INTDIR)\comms.obj"
	-@erase "$(INTDIR)\comms.sbr"
	-@erase "$(INTDIR)\cpu.obj"
	-@erase "$(INTDIR)\cpu.sbr"
	-@erase "$(INTDIR)\cpustat.obj"
	-@erase "$(INTDIR)\cpustat.sbr"
	-@erase "$(INTDIR)\diskdevices.obj"
	-@erase "$(INTDIR)\diskdevices.sbr"
	-@erase "$(INTDIR)\diskio.obj"
	-@erase "$(INTDIR)\diskio.sbr"
	-@erase "$(INTDIR)\diskspace.obj"
	-@erase "$(INTDIR)\diskspace.sbr"
	-@erase "$(INTDIR)\file.obj"
	-@erase "$(INTDIR)\file.sbr"
	-@erase "$(INTDIR)\gnuregex.obj"
	-@erase "$(INTDIR)\gnuregex.sbr"
	-@erase "$(INTDIR)\http.obj"
	-@erase "$(INTDIR)\http.sbr"
	-@erase "$(INTDIR)\inodes.obj"
	-@erase "$(INTDIR)\inodes.sbr"
	-@erase "$(INTDIR)\interfaces.obj"
	-@erase "$(INTDIR)\interfaces.sbr"
	-@erase "$(INTDIR)\kernel.obj"
	-@erase "$(INTDIR)\kernel.sbr"
	-@erase "$(INTDIR)\listener.obj"
	-@erase "$(INTDIR)\listener.sbr"
	-@erase "$(INTDIR)\log.obj"
	-@erase "$(INTDIR)\log.sbr"
	-@erase "$(INTDIR)\logfiles.obj"
	-@erase "$(INTDIR)\logfiles.sbr"
	-@erase "$(INTDIR)\md5.obj"
	-@erase "$(INTDIR)\md5.sbr"
	-@erase "$(INTDIR)\memory.obj"
	-@erase "$(INTDIR)\memory.sbr"
	-@erase "$(INTDIR)\misc.obj"
	-@erase "$(INTDIR)\misc.sbr"
	-@erase "$(INTDIR)\mutexs.obj"
	-@erase "$(INTDIR)\mutexs.sbr"
	-@erase "$(INTDIR)\net.obj"
	-@erase "$(INTDIR)\net.sbr"
	-@erase "$(INTDIR)\ntp.obj"
	-@erase "$(INTDIR)\ntp.sbr"
	-@erase "$(INTDIR)\perfmon.obj"
	-@erase "$(INTDIR)\perfmon.sbr"
	-@erase "$(INTDIR)\proc.obj"
	-@erase "$(INTDIR)\proc.sbr"
	-@erase "$(INTDIR)\regexp.obj"
	-@erase "$(INTDIR)\regexp.sbr"
	-@erase "$(INTDIR)\security.obj"
	-@erase "$(INTDIR)\security.sbr"
	-@erase "$(INTDIR)\sensors.obj"
	-@erase "$(INTDIR)\sensors.sbr"
	-@erase "$(INTDIR)\service.obj"
	-@erase "$(INTDIR)\service.sbr"
	-@erase "$(INTDIR)\stats.obj"
	-@erase "$(INTDIR)\stats.sbr"
	-@erase "$(INTDIR)\str.obj"
	-@erase "$(INTDIR)\str.sbr"
	-@erase "$(INTDIR)\swap.obj"
	-@erase "$(INTDIR)\swap.sbr"
	-@erase "$(INTDIR)\system.obj"
	-@erase "$(INTDIR)\system.sbr"
	-@erase "$(INTDIR)\system_w32.obj"
	-@erase "$(INTDIR)\system_w32.sbr"
	-@erase "$(INTDIR)\threads.obj"
	-@erase "$(INTDIR)\threads.sbr"
	-@erase "$(INTDIR)\uptime.obj"
	-@erase "$(INTDIR)\uptime.sbr"
	-@erase "$(INTDIR)\vc60.idb"
	-@erase "$(INTDIR)\vc60.pdb"
	-@erase "$(INTDIR)\win32.obj"
	-@erase "$(INTDIR)\win32.sbr"
	-@erase "$(INTDIR)\xml.obj"
	-@erase "$(INTDIR)\xml.sbr"
	-@erase "$(INTDIR)\zabbix_agentd.obj"
	-@erase "$(INTDIR)\zabbix_agentd.sbr"
	-@erase "$(INTDIR)\zbxconf.obj"
	-@erase "$(INTDIR)\zbxconf.sbr"
	-@erase "$(INTDIR)\zbxgetopt.obj"
	-@erase "$(INTDIR)\zbxgetopt.sbr"
	-@erase "$(INTDIR)\zbxplugin.obj"
	-@erase "$(INTDIR)\zbxplugin.sbr"
	-@erase "$(INTDIR)\zbxsock.obj"
	-@erase "$(INTDIR)\zbxsock.sbr"
	-@erase "$(OUTDIR)\zabbix_agentd.bsc"
	-@erase "$(OUTDIR)\zabbix_agentd.exe"
	-@erase "$(OUTDIR)\zabbix_agentd.ilk"
	-@erase "$(OUTDIR)\zabbix_agentd.pdb"
	-@erase ".\Debug\zabbixw32.res"
	-@erase "messages.h"
	-@erase "Msg00001.bin"

"$(OUTDIR)" :
    if not exist "$(OUTDIR)/$(NULL)" mkdir "$(OUTDIR)"

CPP_PROJ=/nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /D "TODO" /FR"$(INTDIR)\\" /Fp"$(INTDIR)\zabbix_agentd.pch" /YX /Fo"$(INTDIR)\\" /Fd"$(INTDIR)\\" /FD /GZ /c 
RSC_PROJ=/l 0x409 /fo"Debug/zabbixw32.res" /d "_DEBUG" 
BSC32=bscmake.exe
BSC32_FLAGS=/nologo /o"$(OUTDIR)\zabbix_agentd.bsc" 
BSC32_SBRS= \
	"$(INTDIR)\alias.sbr" \
	"$(INTDIR)\comms.sbr" \
	"$(INTDIR)\gnuregex.sbr" \
	"$(INTDIR)\misc.sbr" \
	"$(INTDIR)\regexp.sbr" \
	"$(INTDIR)\str.sbr" \
	"$(INTDIR)\xml.sbr" \
	"$(INTDIR)\zbxgetopt.sbr" \
	"$(INTDIR)\log.sbr" \
	"$(INTDIR)\base64.sbr" \
	"$(INTDIR)\md5.sbr" \
	"$(INTDIR)\security.sbr" \
	"$(INTDIR)\zbxsock.sbr" \
	"$(INTDIR)\cfg.sbr" \
	"$(INTDIR)\common.sbr" \
	"$(INTDIR)\file.sbr" \
	"$(INTDIR)\http.sbr" \
	"$(INTDIR)\ntp.sbr" \
	"$(INTDIR)\system.sbr" \
	"$(INTDIR)\cpu.sbr" \
	"$(INTDIR)\diskio.sbr" \
	"$(INTDIR)\diskspace.sbr" \
	"$(INTDIR)\inodes.sbr" \
	"$(INTDIR)\kernel.sbr" \
	"$(INTDIR)\memory.sbr" \
	"$(INTDIR)\net.sbr" \
	"$(INTDIR)\proc.sbr" \
	"$(INTDIR)\sensors.sbr" \
	"$(INTDIR)\swap.sbr" \
	"$(INTDIR)\system_w32.sbr" \
	"$(INTDIR)\uptime.sbr" \
	"$(INTDIR)\win32.sbr" \
	"$(INTDIR)\perfmon.sbr" \
	"$(INTDIR)\service.sbr" \
	"$(INTDIR)\mutexs.sbr" \
	"$(INTDIR)\threads.sbr" \
	"$(INTDIR)\zbxplugin.sbr" \
	"$(INTDIR)\active.sbr" \
	"$(INTDIR)\cpustat.sbr" \
	"$(INTDIR)\diskdevices.sbr" \
	"$(INTDIR)\interfaces.sbr" \
	"$(INTDIR)\listener.sbr" \
	"$(INTDIR)\logfiles.sbr" \
	"$(INTDIR)\stats.sbr" \
	"$(INTDIR)\zabbix_agentd.sbr" \
	"$(INTDIR)\zbxconf.sbr"

"$(OUTDIR)\zabbix_agentd.bsc" : "$(OUTDIR)" $(BSC32_SBRS)
    $(BSC32) @<<
  $(BSC32_FLAGS) $(BSC32_SBRS)
<<

LINK32=link.exe
LINK32_FLAGS=ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /incremental:yes /pdb:"$(OUTDIR)\zabbix_agentd.pdb" /debug /machine:I386 /out:"$(OUTDIR)\zabbix_agentd.exe" /pdbtype:sept 
LINK32_OBJS= \
	"$(INTDIR)\alias.obj" \
	"$(INTDIR)\comms.obj" \
	"$(INTDIR)\gnuregex.obj" \
	"$(INTDIR)\misc.obj" \
	"$(INTDIR)\regexp.obj" \
	"$(INTDIR)\str.obj" \
	"$(INTDIR)\xml.obj" \
	"$(INTDIR)\zbxgetopt.obj" \
	"$(INTDIR)\log.obj" \
	"$(INTDIR)\base64.obj" \
	"$(INTDIR)\md5.obj" \
	"$(INTDIR)\security.obj" \
	"$(INTDIR)\zbxsock.obj" \
	"$(INTDIR)\cfg.obj" \
	"$(INTDIR)\common.obj" \
	"$(INTDIR)\file.obj" \
	"$(INTDIR)\http.obj" \
	"$(INTDIR)\ntp.obj" \
	"$(INTDIR)\system.obj" \
	"$(INTDIR)\cpu.obj" \
	"$(INTDIR)\diskio.obj" \
	"$(INTDIR)\diskspace.obj" \
	"$(INTDIR)\inodes.obj" \
	"$(INTDIR)\kernel.obj" \
	"$(INTDIR)\memory.obj" \
	"$(INTDIR)\net.obj" \
	"$(INTDIR)\proc.obj" \
	"$(INTDIR)\sensors.obj" \
	"$(INTDIR)\swap.obj" \
	"$(INTDIR)\system_w32.obj" \
	"$(INTDIR)\uptime.obj" \
	"$(INTDIR)\win32.obj" \
	"$(INTDIR)\perfmon.obj" \
	"$(INTDIR)\service.obj" \
	"$(INTDIR)\mutexs.obj" \
	"$(INTDIR)\threads.obj" \
	"$(INTDIR)\zbxplugin.obj" \
	"$(INTDIR)\active.obj" \
	"$(INTDIR)\cpustat.obj" \
	"$(INTDIR)\diskdevices.obj" \
	"$(INTDIR)\interfaces.obj" \
	"$(INTDIR)\listener.obj" \
	"$(INTDIR)\logfiles.obj" \
	"$(INTDIR)\stats.obj" \
	"$(INTDIR)\zabbix_agentd.obj" \
	"$(INTDIR)\zbxconf.obj" \
	".\Debug\zabbixw32.res"

"$(OUTDIR)\zabbix_agentd.exe" : "$(OUTDIR)" $(DEF_FILE) $(LINK32_OBJS)
    $(LINK32) @<<
  $(LINK32_FLAGS) $(LINK32_OBJS)
<<

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Test"

OUTDIR=.\Test
INTDIR=.\Test
# Begin Custom Macros
OutDir=.\Test
# End Custom Macros

ALL : ".\Msg00001.bin" ".\messages.h" "$(OUTDIR)\zabbix_agentd.exe" "$(OUTDIR)\zabbix_agentd.bsc"


CLEAN :
	-@erase "$(INTDIR)\active.obj"
	-@erase "$(INTDIR)\active.sbr"
	-@erase "$(INTDIR)\alias.obj"
	-@erase "$(INTDIR)\alias.sbr"
	-@erase "$(INTDIR)\base64.obj"
	-@erase "$(INTDIR)\base64.sbr"
	-@erase "$(INTDIR)\cfg.obj"
	-@erase "$(INTDIR)\cfg.sbr"
	-@erase "$(INTDIR)\common.obj"
	-@erase "$(INTDIR)\common.sbr"
	-@erase "$(INTDIR)\comms.obj"
	-@erase "$(INTDIR)\comms.sbr"
	-@erase "$(INTDIR)\cpu.obj"
	-@erase "$(INTDIR)\cpu.sbr"
	-@erase "$(INTDIR)\cpustat.obj"
	-@erase "$(INTDIR)\cpustat.sbr"
	-@erase "$(INTDIR)\diskdevices.obj"
	-@erase "$(INTDIR)\diskdevices.sbr"
	-@erase "$(INTDIR)\diskio.obj"
	-@erase "$(INTDIR)\diskio.sbr"
	-@erase "$(INTDIR)\diskspace.obj"
	-@erase "$(INTDIR)\diskspace.sbr"
	-@erase "$(INTDIR)\file.obj"
	-@erase "$(INTDIR)\file.sbr"
	-@erase "$(INTDIR)\gnuregex.obj"
	-@erase "$(INTDIR)\gnuregex.sbr"
	-@erase "$(INTDIR)\http.obj"
	-@erase "$(INTDIR)\http.sbr"
	-@erase "$(INTDIR)\inodes.obj"
	-@erase "$(INTDIR)\inodes.sbr"
	-@erase "$(INTDIR)\interfaces.obj"
	-@erase "$(INTDIR)\interfaces.sbr"
	-@erase "$(INTDIR)\kernel.obj"
	-@erase "$(INTDIR)\kernel.sbr"
	-@erase "$(INTDIR)\listener.obj"
	-@erase "$(INTDIR)\listener.sbr"
	-@erase "$(INTDIR)\log.obj"
	-@erase "$(INTDIR)\log.sbr"
	-@erase "$(INTDIR)\logfiles.obj"
	-@erase "$(INTDIR)\logfiles.sbr"
	-@erase "$(INTDIR)\md5.obj"
	-@erase "$(INTDIR)\md5.sbr"
	-@erase "$(INTDIR)\memory.obj"
	-@erase "$(INTDIR)\memory.sbr"
	-@erase "$(INTDIR)\misc.obj"
	-@erase "$(INTDIR)\misc.sbr"
	-@erase "$(INTDIR)\mutexs.obj"
	-@erase "$(INTDIR)\mutexs.sbr"
	-@erase "$(INTDIR)\net.obj"
	-@erase "$(INTDIR)\net.sbr"
	-@erase "$(INTDIR)\ntp.obj"
	-@erase "$(INTDIR)\ntp.sbr"
	-@erase "$(INTDIR)\perfmon.obj"
	-@erase "$(INTDIR)\perfmon.sbr"
	-@erase "$(INTDIR)\proc.obj"
	-@erase "$(INTDIR)\proc.sbr"
	-@erase "$(INTDIR)\regexp.obj"
	-@erase "$(INTDIR)\regexp.sbr"
	-@erase "$(INTDIR)\security.obj"
	-@erase "$(INTDIR)\security.sbr"
	-@erase "$(INTDIR)\sensors.obj"
	-@erase "$(INTDIR)\sensors.sbr"
	-@erase "$(INTDIR)\service.obj"
	-@erase "$(INTDIR)\service.sbr"
	-@erase "$(INTDIR)\stats.obj"
	-@erase "$(INTDIR)\stats.sbr"
	-@erase "$(INTDIR)\str.obj"
	-@erase "$(INTDIR)\str.sbr"
	-@erase "$(INTDIR)\swap.obj"
	-@erase "$(INTDIR)\swap.sbr"
	-@erase "$(INTDIR)\system.obj"
	-@erase "$(INTDIR)\system.sbr"
	-@erase "$(INTDIR)\system_w32.obj"
	-@erase "$(INTDIR)\system_w32.sbr"
	-@erase "$(INTDIR)\threads.obj"
	-@erase "$(INTDIR)\threads.sbr"
	-@erase "$(INTDIR)\uptime.obj"
	-@erase "$(INTDIR)\uptime.sbr"
	-@erase "$(INTDIR)\vc60.idb"
	-@erase "$(INTDIR)\vc60.pdb"
	-@erase "$(INTDIR)\win32.obj"
	-@erase "$(INTDIR)\win32.sbr"
	-@erase "$(INTDIR)\xml.obj"
	-@erase "$(INTDIR)\xml.sbr"
	-@erase "$(INTDIR)\zabbix_agentd.obj"
	-@erase "$(INTDIR)\zabbix_agentd.sbr"
	-@erase "$(INTDIR)\zbxconf.obj"
	-@erase "$(INTDIR)\zbxconf.sbr"
	-@erase "$(INTDIR)\zbxgetopt.obj"
	-@erase "$(INTDIR)\zbxgetopt.sbr"
	-@erase "$(INTDIR)\zbxplugin.obj"
	-@erase "$(INTDIR)\zbxplugin.sbr"
	-@erase "$(INTDIR)\zbxsock.obj"
	-@erase "$(INTDIR)\zbxsock.sbr"
	-@erase "$(OUTDIR)\zabbix_agentd.bsc"
	-@erase "$(OUTDIR)\zabbix_agentd.exe"
	-@erase "$(OUTDIR)\zabbix_agentd.ilk"
	-@erase "$(OUTDIR)\zabbix_agentd.pdb"
	-@erase ".\Debug\zabbixw32.res"
	-@erase "messages.h"
	-@erase "Msg00001.bin"

"$(OUTDIR)" :
    if not exist "$(OUTDIR)/$(NULL)" mkdir "$(OUTDIR)"

CPP_PROJ=/nologo /MTd /W3 /Gm /GX /ZI /Od /I "../include/" /I "../../../include/" /I "../../../src/zabbix_agent" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /D "ZABBIX_TEST" /FR"$(INTDIR)\\" /Fp"$(INTDIR)\zabbix_agentd.pch" /YX /Fo"$(INTDIR)\\" /Fd"$(INTDIR)\\" /FD /GZ /c 
RSC_PROJ=/l 0x409 /fo"Debug/zabbixw32.res" /d "_DEBUG" 
BSC32=bscmake.exe
BSC32_FLAGS=/nologo /o"$(OUTDIR)\zabbix_agentd.bsc" 
BSC32_SBRS= \
	"$(INTDIR)\alias.sbr" \
	"$(INTDIR)\comms.sbr" \
	"$(INTDIR)\gnuregex.sbr" \
	"$(INTDIR)\misc.sbr" \
	"$(INTDIR)\regexp.sbr" \
	"$(INTDIR)\str.sbr" \
	"$(INTDIR)\xml.sbr" \
	"$(INTDIR)\zbxgetopt.sbr" \
	"$(INTDIR)\log.sbr" \
	"$(INTDIR)\base64.sbr" \
	"$(INTDIR)\md5.sbr" \
	"$(INTDIR)\security.sbr" \
	"$(INTDIR)\zbxsock.sbr" \
	"$(INTDIR)\cfg.sbr" \
	"$(INTDIR)\common.sbr" \
	"$(INTDIR)\file.sbr" \
	"$(INTDIR)\http.sbr" \
	"$(INTDIR)\ntp.sbr" \
	"$(INTDIR)\system.sbr" \
	"$(INTDIR)\cpu.sbr" \
	"$(INTDIR)\diskio.sbr" \
	"$(INTDIR)\diskspace.sbr" \
	"$(INTDIR)\inodes.sbr" \
	"$(INTDIR)\kernel.sbr" \
	"$(INTDIR)\memory.sbr" \
	"$(INTDIR)\net.sbr" \
	"$(INTDIR)\proc.sbr" \
	"$(INTDIR)\sensors.sbr" \
	"$(INTDIR)\swap.sbr" \
	"$(INTDIR)\system_w32.sbr" \
	"$(INTDIR)\uptime.sbr" \
	"$(INTDIR)\win32.sbr" \
	"$(INTDIR)\perfmon.sbr" \
	"$(INTDIR)\service.sbr" \
	"$(INTDIR)\mutexs.sbr" \
	"$(INTDIR)\threads.sbr" \
	"$(INTDIR)\zbxplugin.sbr" \
	"$(INTDIR)\active.sbr" \
	"$(INTDIR)\cpustat.sbr" \
	"$(INTDIR)\diskdevices.sbr" \
	"$(INTDIR)\interfaces.sbr" \
	"$(INTDIR)\listener.sbr" \
	"$(INTDIR)\logfiles.sbr" \
	"$(INTDIR)\stats.sbr" \
	"$(INTDIR)\zabbix_agentd.sbr" \
	"$(INTDIR)\zbxconf.sbr"

"$(OUTDIR)\zabbix_agentd.bsc" : "$(OUTDIR)" $(BSC32_SBRS)
    $(BSC32) @<<
  $(BSC32_FLAGS) $(BSC32_SBRS)
<<

LINK32=link.exe
LINK32_FLAGS=ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /incremental:yes /pdb:"$(OUTDIR)\zabbix_agentd.pdb" /debug /machine:I386 /out:"$(OUTDIR)\zabbix_agentd.exe" /pdbtype:sept 
LINK32_OBJS= \
	"$(INTDIR)\alias.obj" \
	"$(INTDIR)\comms.obj" \
	"$(INTDIR)\gnuregex.obj" \
	"$(INTDIR)\misc.obj" \
	"$(INTDIR)\regexp.obj" \
	"$(INTDIR)\str.obj" \
	"$(INTDIR)\xml.obj" \
	"$(INTDIR)\zbxgetopt.obj" \
	"$(INTDIR)\log.obj" \
	"$(INTDIR)\base64.obj" \
	"$(INTDIR)\md5.obj" \
	"$(INTDIR)\security.obj" \
	"$(INTDIR)\zbxsock.obj" \
	"$(INTDIR)\cfg.obj" \
	"$(INTDIR)\common.obj" \
	"$(INTDIR)\file.obj" \
	"$(INTDIR)\http.obj" \
	"$(INTDIR)\ntp.obj" \
	"$(INTDIR)\system.obj" \
	"$(INTDIR)\cpu.obj" \
	"$(INTDIR)\diskio.obj" \
	"$(INTDIR)\diskspace.obj" \
	"$(INTDIR)\inodes.obj" \
	"$(INTDIR)\kernel.obj" \
	"$(INTDIR)\memory.obj" \
	"$(INTDIR)\net.obj" \
	"$(INTDIR)\proc.obj" \
	"$(INTDIR)\sensors.obj" \
	"$(INTDIR)\swap.obj" \
	"$(INTDIR)\system_w32.obj" \
	"$(INTDIR)\uptime.obj" \
	"$(INTDIR)\win32.obj" \
	"$(INTDIR)\perfmon.obj" \
	"$(INTDIR)\service.obj" \
	"$(INTDIR)\mutexs.obj" \
	"$(INTDIR)\threads.obj" \
	"$(INTDIR)\zbxplugin.obj" \
	"$(INTDIR)\active.obj" \
	"$(INTDIR)\cpustat.obj" \
	"$(INTDIR)\diskdevices.obj" \
	"$(INTDIR)\interfaces.obj" \
	"$(INTDIR)\listener.obj" \
	"$(INTDIR)\logfiles.obj" \
	"$(INTDIR)\stats.obj" \
	"$(INTDIR)\zabbix_agentd.obj" \
	"$(INTDIR)\zbxconf.obj" \
	".\Debug\zabbixw32.res"

"$(OUTDIR)\zabbix_agentd.exe" : "$(OUTDIR)" $(DEF_FILE) $(LINK32_OBJS)
    $(LINK32) @<<
  $(LINK32_FLAGS) $(LINK32_OBJS)
<<

!ENDIF 

.c{$(INTDIR)}.obj::
   $(CPP) @<<
   $(CPP_PROJ) $< 
<<

.cpp{$(INTDIR)}.obj::
   $(CPP) @<<
   $(CPP_PROJ) $< 
<<

.cxx{$(INTDIR)}.obj::
   $(CPP) @<<
   $(CPP_PROJ) $< 
<<

.c{$(INTDIR)}.sbr::
   $(CPP) @<<
   $(CPP_PROJ) $< 
<<

.cpp{$(INTDIR)}.sbr::
   $(CPP) @<<
   $(CPP_PROJ) $< 
<<

.cxx{$(INTDIR)}.sbr::
   $(CPP) @<<
   $(CPP_PROJ) $< 
<<


!IF "$(NO_EXTERNAL_DEPS)" != "1"
!IF EXISTS("zabbix_agentd.dep")
!INCLUDE "zabbix_agentd.dep"
!ELSE 
!MESSAGE Warning: cannot find "zabbix_agentd.dep"
!ENDIF 
!ENDIF 


!IF "$(CFG)" == "zabbix_agentd - Win32 Release" || "$(CFG)" == "zabbix_agentd - Win32 Debug" || "$(CFG)" == "zabbix_agentd - Win32 TODO" || "$(CFG)" == "zabbix_agentd - Win32 Test"
SOURCE=..\..\..\src\libs\zbxcommon\alias.c

"$(INTDIR)\alias.obj"	"$(INTDIR)\alias.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcommon\comms.c

"$(INTDIR)\comms.obj"	"$(INTDIR)\comms.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcommon\gnuregex.c

"$(INTDIR)\gnuregex.obj"	"$(INTDIR)\gnuregex.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcommon\misc.c

"$(INTDIR)\misc.obj"	"$(INTDIR)\misc.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcommon\regexp.c

"$(INTDIR)\regexp.obj"	"$(INTDIR)\regexp.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcommon\str.c

"$(INTDIR)\str.obj"	"$(INTDIR)\str.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcommon\xml.c

"$(INTDIR)\xml.obj"	"$(INTDIR)\xml.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcommon\zbxgetopt.c

"$(INTDIR)\zbxgetopt.obj"	"$(INTDIR)\zbxgetopt.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxlog\log.c

"$(INTDIR)\log.obj"	"$(INTDIR)\log.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcrypto\base64.c

"$(INTDIR)\base64.obj"	"$(INTDIR)\base64.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxcrypto\md5.c

"$(INTDIR)\md5.obj"	"$(INTDIR)\md5.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxnet\security.c

"$(INTDIR)\security.obj"	"$(INTDIR)\security.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxnet\zbxsock.c

"$(INTDIR)\zbxsock.obj"	"$(INTDIR)\zbxsock.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxconf\cfg.c

"$(INTDIR)\cfg.obj"	"$(INTDIR)\cfg.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\common\common.c

"$(INTDIR)\common.obj"	"$(INTDIR)\common.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\common\file.c

"$(INTDIR)\file.obj"	"$(INTDIR)\file.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\common\http.c

"$(INTDIR)\http.obj"	"$(INTDIR)\http.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\common\ntp.c

"$(INTDIR)\ntp.obj"	"$(INTDIR)\ntp.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\common\system.c

"$(INTDIR)\system.obj"	"$(INTDIR)\system.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\cpu.c

"$(INTDIR)\cpu.obj"	"$(INTDIR)\cpu.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\diskio.c

"$(INTDIR)\diskio.obj"	"$(INTDIR)\diskio.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\diskspace.c

"$(INTDIR)\diskspace.obj"	"$(INTDIR)\diskspace.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\inodes.c

"$(INTDIR)\inodes.obj"	"$(INTDIR)\inodes.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\kernel.c

"$(INTDIR)\kernel.obj"	"$(INTDIR)\kernel.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\memory.c

"$(INTDIR)\memory.obj"	"$(INTDIR)\memory.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\net.c

"$(INTDIR)\net.obj"	"$(INTDIR)\net.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\proc.c

"$(INTDIR)\proc.obj"	"$(INTDIR)\proc.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\sensors.c

"$(INTDIR)\sensors.obj"	"$(INTDIR)\sensors.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\swap.c

"$(INTDIR)\swap.obj"	"$(INTDIR)\swap.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\system_w32.c

"$(INTDIR)\system_w32.obj"	"$(INTDIR)\system_w32.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\uptime.c

"$(INTDIR)\uptime.obj"	"$(INTDIR)\uptime.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsysinfo\win32\win32.c

"$(INTDIR)\win32.obj"	"$(INTDIR)\win32.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxwin32\perfmon.c

"$(INTDIR)\perfmon.obj"	"$(INTDIR)\perfmon.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxwin32\service.c

"$(INTDIR)\service.obj"	"$(INTDIR)\service.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxnix\daemon.c
SOURCE=..\..\..\src\libs\zbxnix\pid.c
SOURCE=..\..\..\src\libs\zbxsys\mutexs.c

"$(INTDIR)\mutexs.obj"	"$(INTDIR)\mutexs.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxsys\threads.c

"$(INTDIR)\threads.obj"	"$(INTDIR)\threads.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\libs\zbxplugin\zbxplugin.c

"$(INTDIR)\zbxplugin.obj"	"$(INTDIR)\zbxplugin.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\active.c

"$(INTDIR)\active.obj"	"$(INTDIR)\active.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\cpustat.c

"$(INTDIR)\cpustat.obj"	"$(INTDIR)\cpustat.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\diskdevices.c

"$(INTDIR)\diskdevices.obj"	"$(INTDIR)\diskdevices.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\interfaces.c

"$(INTDIR)\interfaces.obj"	"$(INTDIR)\interfaces.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\listener.c

"$(INTDIR)\listener.obj"	"$(INTDIR)\listener.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\logfiles.c

"$(INTDIR)\logfiles.obj"	"$(INTDIR)\logfiles.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\stats.c

"$(INTDIR)\stats.obj"	"$(INTDIR)\stats.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\zabbix_agentd.c

"$(INTDIR)\zabbix_agentd.obj"	"$(INTDIR)\zabbix_agentd.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\zbxconf.c

"$(INTDIR)\zbxconf.obj"	"$(INTDIR)\zbxconf.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


SOURCE=..\..\..\src\zabbix_agent\messages.mc

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"

ProjDir=.
InputPath=..\..\..\src\zabbix_agent\messages.mc
InputName=messages

".\messages.h"	".\Msg00001.bin" : $(SOURCE) "$(INTDIR)" "$(OUTDIR)"
	<<tempfile.bat 
	@echo off 
	mc -s -U -h  $(ProjDir)\..\..\..\src\zabbix_agent\ -r $(ProjDir)\..\..\..\src\zabbix_agent\ $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName) 
	del $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName).rc
<< 
	

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"

ProjDir=.
InputPath=..\..\..\src\zabbix_agent\messages.mc
InputName=messages

".\messages.h"	".\Msg00001.bin" : $(SOURCE) "$(INTDIR)" "$(OUTDIR)"
	<<tempfile.bat 
	@echo off 
	mc -s -U -h  $(ProjDir)\..\..\..\src\zabbix_agent\ -r $(ProjDir)\..\..\..\src\zabbix_agent\ $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName) 
	del $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName).rc
<< 
	

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 TODO"

ProjDir=.
InputPath=..\..\..\src\zabbix_agent\messages.mc
InputName=messages

".\messages.h"	".\Msg00001.bin" : $(SOURCE) "$(INTDIR)" "$(OUTDIR)"
	<<tempfile.bat 
	@echo off 
	mc -s -U -h  $(ProjDir)\..\..\..\src\zabbix_agent\ -r $(ProjDir)\..\..\..\src\zabbix_agent\ $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName) 
	del $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName).rc
<< 
	

!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Test"

ProjDir=.
InputPath=..\..\..\src\zabbix_agent\messages.mc
InputName=messages

".\messages.h"	".\Msg00001.bin" : $(SOURCE) "$(INTDIR)" "$(OUTDIR)"
	<<tempfile.bat 
	@echo off 
	mc -s -U -h  $(ProjDir)\..\..\..\src\zabbix_agent\ -r $(ProjDir)\..\..\..\src\zabbix_agent\ $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName) 
	del $(ProjDir)\..\..\..\src\zabbix_agent\$(InputName).rc
<< 
	

!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\resources.rc

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\zabbixw32.res" : $(SOURCE) "$(INTDIR)"
	$(RSC) /l 0x409 /fo"$(INTDIR)\zabbixw32.res" /i "\Eugene\zabbix\src\zabbix_agent" /d "NDEBUG" $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\zabbixw32.res" : $(SOURCE) "$(INTDIR)"
	$(RSC) /l 0x409 /fo"$(INTDIR)\zabbixw32.res" /i "\Eugene\zabbix\src\zabbix_agent" /d "_DEBUG" $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 TODO"


".\Debug\zabbixw32.res" : $(SOURCE) "$(INTDIR)"
	$(RSC) /l 0x409 /fo"Debug/zabbixw32.res" /i "\Eugene\zabbix\src\zabbix_agent" /d "_DEBUG" $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Test"


".\Debug\zabbixw32.res" : $(SOURCE) "$(INTDIR)"
	$(RSC) /l 0x409 /fo"Debug/zabbixw32.res" /i "\Eugene\zabbix\src\zabbix_agent" /d "_DEBUG" $(SOURCE)


!ENDIF 


!ENDIF 

