RC = rc.exe /d "ZABBIX_AGENT" /d _WINDOWS
DESC_H = zabbix_agent_desc.h
RESOURCE_RES = zabbix_agent.res
PDBPATH = $(TARGETDIR)\zabbix_agentd.pdb
IDBPATH = $(TARGETDIR)\zabbix_agentd.idb
ADD_INCS = /I ..\..\..\src\zabbix_agent
ADD_CFLAGS = $(ADD_CFLAGS) /D WITH_AGENT_METRICS /D WITH_COMMON_METRICS /D WITH_SPECIFIC_METRICS \
	/D WITH_HOSTNAME_METRIC /D WITH_SIMPLE_METRICS /Zi /Fd$(PDBPATH)
ADD_LFLAGS = $(ADD_LFLAGS) /PDB:$(PDBPATH)

!INCLUDE Makefile_macros.inc

OBJS  = ..\..\..\src\libs\zbxalgo\algodefs.o \
	..\..\..\src\libs\zbxalgo\vector.o \
	..\..\..\src\libs\zbxcommon\alias.o \
	..\..\..\src\libs\zbxcommon\comms.o \
	..\..\..\src\libs\zbxcommon\iprange.o \
	..\..\..\src\libs\zbxcommon\misc.o \
	..\..\..\src\libs\zbxcommon\str.o \
	..\..\..\src\libs\zbxcommon\xml.o \
	..\..\..\src\libs\zbxcommon\zbxgetopt.o \
	..\..\..\src\libs\zbxcommon\file.o \
	..\..\..\src\libs\zbxcomms\comms.o \
	..\..\..\src\libs\zbxcomms\telnet.o \
	..\..\..\src\libs\zbxconf\cfg.o \
	..\..\..\src\libs\zbxcrypto\base64.o \
	..\..\..\src\libs\zbxcrypto\md5.o \
	..\..\..\src\libs\zbxjson\json.o \
	..\..\..\src\libs\zbxjson\json_parser.o \
	..\..\..\src\libs\zbxlog\log.o \
	..\..\..\src\libs\zbxsys\mutexs.o \
	..\..\..\src\libs\zbxsys\symbols.o \
	..\..\..\src\libs\zbxsys\threads.o \
	..\..\..\src\libs\zbxexec\execute.o \
	..\..\..\src\libs\zbxsysinfo\agent\agent.o \
	..\..\..\src\libs\zbxsysinfo\common\common.o \
	..\..\..\src\libs\zbxsysinfo\common\file.o \
	..\..\..\src\libs\zbxsysinfo\common\http.o \
	..\..\..\src\libs\zbxsysinfo\common\net.o \
	..\..\..\src\libs\zbxsysinfo\common\system.o \
	..\..\..\src\libs\zbxsysinfo\simple\ntp.o \
	..\..\..\src\libs\zbxsysinfo\simple\simple.o \
	..\..\..\src\libs\zbxsysinfo\win32\cpu.o \
	..\..\..\src\libs\zbxsysinfo\win32\diskio.o \
	..\..\..\src\libs\zbxsysinfo\win32\diskspace.o \
	..\..\..\src\libs\zbxsysinfo\win32\memory.o \
	..\..\..\src\libs\zbxsysinfo\win32\net.o \
	..\..\..\src\libs\zbxsysinfo\win32\pdhmon.o \
	..\..\..\src\libs\zbxsysinfo\win32\proc.o \
	..\..\..\src\libs\zbxsysinfo\win32\services.o \
	..\..\..\src\libs\zbxsysinfo\win32\swap.o \
	..\..\..\src\libs\zbxsysinfo\win32\uptime.o \
	..\..\..\src\libs\zbxsysinfo\win32\win32.o \
	..\..\..\src\libs\zbxsysinfo\win32\wmi.o \
	..\..\..\src\libs\zbxsysinfo\sysinfo.o \
	..\..\..\src\libs\zbxsysinfo\win32\software.o \
	..\..\..\src\libs\zbxsysinfo\win32\system.o \
	..\..\..\src\libs\zbxsysinfo\win32\hostname.o \
	..\..\..\src\libs\zbxwin32\perfmon.o \
	..\..\..\src\libs\zbxwin32\service.o \
	..\..\..\src\libs\zbxself\selfmon.o \
	..\..\..\src\zabbix_agent\active.o \
	..\..\..\src\zabbix_agent\cpustat.o \
	..\..\..\src\zabbix_agent\eventlog.o \
	..\..\..\src\zabbix_agent\listener.o \
	..\..\..\src\zabbix_agent\logfiles.o \
	..\..\..\src\zabbix_agent\perfstat.o \
	..\..\..\src\zabbix_agent\stats.o \
	..\..\..\src\zabbix_agent\zabbix_agentd.o \
	..\..\..\src\zabbix_agent\zbxconf.o \
	..\..\..\src\libs\zbxregexp\gnuregex.o \
	..\..\..\src\libs\zbxregexp\zbxregexp.o \
	..\..\..\src\libs\zbxwin32\fatal.o

LIBS = ws2_32.lib psapi.lib pdh.lib Wldap32.lib advapi32.lib uuid.lib Iphlpapi.lib delayimp.lib wevtapi.lib

!INCLUDE Makefile_tls.inc

all: $(PROGNAME)

clean:
# break this down because del has limit for command-line args length (NMAKE : fatal error U1077: 'del' : return code '0x1')
	del $(OBJS) 2>nul
	del $(RESOURCE_RES) $(MESSAGES_H) $(PROGNAME) messages.rc MSG00001.bin $(TARGETNAME).manifest $(PDBPATH) $(IDBPATH) 2>nul

$(PROGNAME): $(MESSAGES_H) $(OBJS) $(RESOURCE_RES)
	$(LINKER) $(OBJS) $(RESOURCE_RES) /OUT:$(PROGNAME) $(LFLAGS) $(LIBS)
	$(MT) /outputresource:"$(PROGNAME);#1" /manifest $(TARGETNAME).manifest /nologo

!INCLUDE Makefile_targets.inc
