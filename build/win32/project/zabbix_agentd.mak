# Microsoft Developer Studio Generated NMAKE File, Based on zabbix_agentd.dsp
!IF "$(CFG)" == ""
CFG=zabbix_agentd - Win32 Debug
!MESSAGE No configuration specified. Defaulting to zabbix_agentd - Win32 Debug.
!ENDIF 

!IF "$(CFG)" != "zabbix_agentd - Win32 Release" && "$(CFG)" != "zabbix_agentd - Win32 Debug"
!MESSAGE Invalid configuration "$(CFG)" specified.
!MESSAGE You can specify a configuration when running NMAKE
!MESSAGE by defining the macro CFG on the command line. For example:
!MESSAGE 
!MESSAGE NMAKE /f "zabbix_agentd.mak" CFG="zabbix_agentd - Win32 Debug"
!MESSAGE 
!MESSAGE Possible choices for configuration are:
!MESSAGE 
!MESSAGE "zabbix_agentd - Win32 Release" (based on "Win32 (x86) Console Application")
!MESSAGE "zabbix_agentd - Win32 Debug" (based on "Win32 (x86) Console Application")
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

ALL : "$(OUTDIR)\zabbix_agentd.exe"


CLEAN :
	-@erase "$(INTDIR)\active.obj"
	-@erase "$(INTDIR)\base64.obj"
	-@erase "$(INTDIR)\cfg.obj"
	-@erase "$(INTDIR)\comms.obj"
	-@erase "$(INTDIR)\cpustat.obj"
	-@erase "$(INTDIR)\diskdevices.obj"
	-@erase "$(INTDIR)\interfaces.obj"
	-@erase "$(INTDIR)\log.obj"
	-@erase "$(INTDIR)\logfiles.obj"
	-@erase "$(INTDIR)\md5.obj"
	-@erase "$(INTDIR)\misc.obj"
	-@erase "$(INTDIR)\pid.obj"
	-@erase "$(INTDIR)\regexp.obj"
	-@erase "$(INTDIR)\security.obj"
	-@erase "$(INTDIR)\snprintf.obj"
	-@erase "$(INTDIR)\stats.obj"
	-@erase "$(INTDIR)\str.obj"
	-@erase "$(INTDIR)\threads.obj"
	-@erase "$(INTDIR)\vc60.idb"
	-@erase "$(INTDIR)\win32.obj"
	-@erase "$(INTDIR)\xml.obj"
	-@erase "$(INTDIR)\zabbix_agent.obj"
	-@erase "$(INTDIR)\zabbix_agentd.obj"
	-@erase "$(OUTDIR)\zabbix_agentd.exe"

"$(OUTDIR)" :
    if not exist "$(OUTDIR)/$(NULL)" mkdir "$(OUTDIR)"

CPP_PROJ=/nologo /MT /W3 /GX /O2 /I "../../../include/" /D "NDEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /Fp"$(INTDIR)\zabbix_agentd.pch" /YX /Fo"$(INTDIR)\\" /Fd"$(INTDIR)\\" /FD /c 
BSC32=bscmake.exe
BSC32_FLAGS=/nologo /o"$(OUTDIR)\zabbix_agentd.bsc" 
BSC32_SBRS= \
	
LINK32=link.exe
LINK32_FLAGS=ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /incremental:no /pdb:"$(OUTDIR)\zabbix_agentd.pdb" /machine:I386 /out:"$(OUTDIR)\zabbix_agentd.exe" 
LINK32_OBJS= \
	"$(INTDIR)\zabbix_agent.obj" \
	"$(INTDIR)\zabbix_agentd.obj" \
	"$(INTDIR)\active.obj" \
	"$(INTDIR)\cpustat.obj" \
	"$(INTDIR)\diskdevices.obj" \
	"$(INTDIR)\interfaces.obj" \
	"$(INTDIR)\logfiles.obj" \
	"$(INTDIR)\stats.obj" \
	"$(INTDIR)\snprintf.obj" \
	"$(INTDIR)\win32.obj" \
	"$(INTDIR)\base64.obj" \
	"$(INTDIR)\comms.obj" \
	"$(INTDIR)\misc.obj" \
	"$(INTDIR)\regexp.obj" \
	"$(INTDIR)\str.obj" \
	"$(INTDIR)\xml.obj" \
	"$(INTDIR)\log.obj" \
	"$(INTDIR)\md5.obj" \
	"$(INTDIR)\pid.obj" \
	"$(INTDIR)\security.obj" \
	"$(INTDIR)\cfg.obj" \
	"$(INTDIR)\threads.obj"

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

ALL : "$(OUTDIR)\zabbix_agentd.exe" "$(OUTDIR)\zabbix_agentd.bsc"


CLEAN :
	-@erase "$(INTDIR)\active.obj"
	-@erase "$(INTDIR)\active.sbr"
	-@erase "$(INTDIR)\base64.obj"
	-@erase "$(INTDIR)\base64.sbr"
	-@erase "$(INTDIR)\cfg.obj"
	-@erase "$(INTDIR)\cfg.sbr"
	-@erase "$(INTDIR)\comms.obj"
	-@erase "$(INTDIR)\comms.sbr"
	-@erase "$(INTDIR)\cpustat.obj"
	-@erase "$(INTDIR)\cpustat.sbr"
	-@erase "$(INTDIR)\diskdevices.obj"
	-@erase "$(INTDIR)\diskdevices.sbr"
	-@erase "$(INTDIR)\interfaces.obj"
	-@erase "$(INTDIR)\interfaces.sbr"
	-@erase "$(INTDIR)\log.obj"
	-@erase "$(INTDIR)\log.sbr"
	-@erase "$(INTDIR)\logfiles.obj"
	-@erase "$(INTDIR)\logfiles.sbr"
	-@erase "$(INTDIR)\md5.obj"
	-@erase "$(INTDIR)\md5.sbr"
	-@erase "$(INTDIR)\misc.obj"
	-@erase "$(INTDIR)\misc.sbr"
	-@erase "$(INTDIR)\pid.obj"
	-@erase "$(INTDIR)\pid.sbr"
	-@erase "$(INTDIR)\regexp.obj"
	-@erase "$(INTDIR)\regexp.sbr"
	-@erase "$(INTDIR)\security.obj"
	-@erase "$(INTDIR)\security.sbr"
	-@erase "$(INTDIR)\snprintf.obj"
	-@erase "$(INTDIR)\snprintf.sbr"
	-@erase "$(INTDIR)\stats.obj"
	-@erase "$(INTDIR)\stats.sbr"
	-@erase "$(INTDIR)\str.obj"
	-@erase "$(INTDIR)\str.sbr"
	-@erase "$(INTDIR)\threads.obj"
	-@erase "$(INTDIR)\threads.sbr"
	-@erase "$(INTDIR)\vc60.idb"
	-@erase "$(INTDIR)\vc60.pdb"
	-@erase "$(INTDIR)\win32.obj"
	-@erase "$(INTDIR)\win32.sbr"
	-@erase "$(INTDIR)\xml.obj"
	-@erase "$(INTDIR)\xml.sbr"
	-@erase "$(INTDIR)\zabbix_agent.obj"
	-@erase "$(INTDIR)\zabbix_agent.sbr"
	-@erase "$(INTDIR)\zabbix_agentd.obj"
	-@erase "$(INTDIR)\zabbix_agentd.sbr"
	-@erase "$(OUTDIR)\zabbix_agentd.bsc"
	-@erase "$(OUTDIR)\zabbix_agentd.exe"
	-@erase "$(OUTDIR)\zabbix_agentd.ilk"
	-@erase "$(OUTDIR)\zabbix_agentd.pdb"

"$(OUTDIR)" :
    if not exist "$(OUTDIR)/$(NULL)" mkdir "$(OUTDIR)"

CPP_PROJ=/nologo /MTd /W3 /Gm /GX /ZI /Od /I "../../../include/" /D "_DEBUG" /D "HAVE_ASSERT_H" /D "WIN32" /D "_CONSOLE" /D "_MBCS" /FR"$(INTDIR)\\" /Fp"$(INTDIR)\zabbix_agentd.pch" /YX /Fo"$(INTDIR)\\" /Fd"$(INTDIR)\\" /FD /GZ  /c 
BSC32=bscmake.exe
BSC32_FLAGS=/nologo /o"$(OUTDIR)\zabbix_agentd.bsc" 
BSC32_SBRS= \
	"$(INTDIR)\zabbix_agent.sbr" \
	"$(INTDIR)\zabbix_agentd.sbr" \
	"$(INTDIR)\active.sbr" \
	"$(INTDIR)\cpustat.sbr" \
	"$(INTDIR)\diskdevices.sbr" \
	"$(INTDIR)\interfaces.sbr" \
	"$(INTDIR)\logfiles.sbr" \
	"$(INTDIR)\stats.sbr" \
	"$(INTDIR)\snprintf.sbr" \
	"$(INTDIR)\win32.sbr" \
	"$(INTDIR)\base64.sbr" \
	"$(INTDIR)\comms.sbr" \
	"$(INTDIR)\misc.sbr" \
	"$(INTDIR)\regexp.sbr" \
	"$(INTDIR)\str.sbr" \
	"$(INTDIR)\xml.sbr" \
	"$(INTDIR)\log.sbr" \
	"$(INTDIR)\md5.sbr" \
	"$(INTDIR)\pid.sbr" \
	"$(INTDIR)\security.sbr" \
	"$(INTDIR)\cfg.sbr" \
	"$(INTDIR)\threads.sbr"

"$(OUTDIR)\zabbix_agentd.bsc" : "$(OUTDIR)" $(BSC32_SBRS)
    $(BSC32) @<<
  $(BSC32_FLAGS) $(BSC32_SBRS)
<<

LINK32=link.exe
LINK32_FLAGS=ws2_32.lib pdh.lib psapi.lib kernel32.lib user32.lib gdi32.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib /nologo /subsystem:console /incremental:yes /pdb:"$(OUTDIR)\zabbix_agentd.pdb" /debug /machine:I386 /out:"$(OUTDIR)\zabbix_agentd.exe" /pdbtype:sept 
LINK32_OBJS= \
	"$(INTDIR)\zabbix_agent.obj" \
	"$(INTDIR)\zabbix_agentd.obj" \
	"$(INTDIR)\active.obj" \
	"$(INTDIR)\cpustat.obj" \
	"$(INTDIR)\diskdevices.obj" \
	"$(INTDIR)\interfaces.obj" \
	"$(INTDIR)\logfiles.obj" \
	"$(INTDIR)\stats.obj" \
	"$(INTDIR)\snprintf.obj" \
	"$(INTDIR)\win32.obj" \
	"$(INTDIR)\base64.obj" \
	"$(INTDIR)\comms.obj" \
	"$(INTDIR)\misc.obj" \
	"$(INTDIR)\regexp.obj" \
	"$(INTDIR)\str.obj" \
	"$(INTDIR)\xml.obj" \
	"$(INTDIR)\log.obj" \
	"$(INTDIR)\md5.obj" \
	"$(INTDIR)\pid.obj" \
	"$(INTDIR)\security.obj" \
	"$(INTDIR)\cfg.obj" \
	"$(INTDIR)\threads.obj"

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


!IF "$(CFG)" == "zabbix_agentd - Win32 Release" || "$(CFG)" == "zabbix_agentd - Win32 Debug"
SOURCE=..\..\..\src\libs\zbxcommon\base64.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\base64.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\base64.obj"	"$(INTDIR)\base64.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxcommon\comms.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\comms.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\comms.obj"	"$(INTDIR)\comms.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxcommon\misc.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\misc.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\misc.obj"	"$(INTDIR)\misc.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxcommon\regexp.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\regexp.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\regexp.obj"	"$(INTDIR)\regexp.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxcommon\str.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\str.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\str.obj"	"$(INTDIR)\str.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxcommon\xml.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\xml.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\xml.obj"	"$(INTDIR)\xml.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxlog\log.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\log.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\log.obj"	"$(INTDIR)\log.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxcrypto\md5.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\md5.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\md5.obj"	"$(INTDIR)\md5.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxpid\pid.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\pid.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\pid.obj"	"$(INTDIR)\pid.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxnet\security.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\security.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\security.obj"	"$(INTDIR)\security.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxconf\cfg.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\cfg.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\cfg.obj"	"$(INTDIR)\cfg.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\libs\zbxsysinfo\win32\win32.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\win32.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\win32.obj"	"$(INTDIR)\win32.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\active.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\active.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\active.obj"	"$(INTDIR)\active.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\cpustat.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\cpustat.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\cpustat.obj"	"$(INTDIR)\cpustat.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\diskdevices.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\diskdevices.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\diskdevices.obj"	"$(INTDIR)\diskdevices.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\interfaces.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\interfaces.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\interfaces.obj"	"$(INTDIR)\interfaces.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\logfiles.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\logfiles.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\logfiles.obj"	"$(INTDIR)\logfiles.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\stats.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\stats.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\stats.obj"	"$(INTDIR)\stats.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\threads.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\threads.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\threads.obj"	"$(INTDIR)\threads.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\zabbix_agent.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\zabbix_agent.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\zabbix_agent.obj"	"$(INTDIR)\zabbix_agent.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\src\zabbix_agent\zabbix_agentd.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\zabbix_agentd.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\zabbix_agentd.obj"	"$(INTDIR)\zabbix_agentd.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 

SOURCE=..\..\..\include\snprintf.c

!IF  "$(CFG)" == "zabbix_agentd - Win32 Release"


"$(INTDIR)\snprintf.obj" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ELSEIF  "$(CFG)" == "zabbix_agentd - Win32 Debug"


"$(INTDIR)\snprintf.obj"	"$(INTDIR)\snprintf.sbr" : $(SOURCE) "$(INTDIR)"
	$(CPP) $(CPP_PROJ) $(SOURCE)


!ENDIF 


!ENDIF 

