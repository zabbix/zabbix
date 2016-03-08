CC = cl.exe /nologo
LINKER = link.exe /NOLOGO
RC = rc.exe /d "ZABBIX_AGENT" /d _WINDOWS
MC = mc.exe
MT = mt.exe
MESSAGES_H = messages.h
RESOURCE_H = resource.h
DESC_H = zabbix_agent_desc.h
RESOURCE_RC = resource.rc
RESOURCE_RES = zabbix_agent.res
OBJS  = ..\..\..\src\libs\zbxalgo\algodefs.o \
	..\..\..\src\libs\zbxalgo\vector.o \
	..\..\..\src\libs\zbxcommon\alias.o \
	..\..\..\src\libs\zbxcommon\comms.o \
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
	..\..\..\src\libs\zbxregexp\zbxregexp.o

LIBS = ws2_32.lib psapi.lib pdh.lib Wldap32.lib advapi32.lib uuid.lib Iphlpapi.lib delayimp.lib wevtapi.lib

all: $(PROGNAME)

clean:
	del $(OBJS) $(RESOURCE_RES) $(MESSAGES_H) $(PROGNAME) messages.rc MSG00001.bin zabbix_agentd.exe.manifest

$(PROGNAME): $(MESSAGES_H) $(OBJS) $(RESOURCE_RES)
	$(LINKER) $(OBJS) $(RESOURCE_RES) /OUT:$(PROGNAME) $(LFLAGS) $(LIBS)
	$(MT) /outputresource:"$(PROGNAME);#1" /manifest zabbix_agentd.exe.manifest /nologo

$(MESSAGES_H): messages.mc
	$(MC) -U -h ".\\" -r ".\\" messages.mc

$(RESOURCE_RES): $(RESOURCE_RC) $(RESOURCE_H) $(DESC_H)
	$(RC) /d "NDEBUG" /d "_VC80_UPGRADE=0x0600" /l 0x419 /fo"$(RESOURCE_RES)" $(RESOURCE_RC)

..\..\..\src\libs\zbxalgo\algodefs.o: ..\..\..\src\libs\zbxalgo\algodefs.c
	$(CC) ..\..\..\src\libs\zbxalgo\algodefs.c /Fo"..\..\..\src\libs\zbxalgo\algodefs.o" $(CFLAGS)

..\..\..\src\libs\zbxalgo\vector.o: ..\..\..\src\libs\zbxalgo\vector.c
	$(CC) ..\..\..\src\libs\zbxalgo\vector.c /Fo"..\..\..\src\libs\zbxalgo\vector.o" $(CFLAGS)

..\..\..\src\libs\zbxcommon\alias.o: ..\..\..\src\libs\zbxcommon\alias.c
	$(CC) ..\..\..\src\libs\zbxcommon\alias.c /Fo"..\..\..\src\libs\zbxcommon\alias.o" $(CFLAGS)

..\..\..\src\libs\zbxcommon\comms.o: ..\..\..\src\libs\zbxcommon\comms.c
	$(CC) ..\..\..\src\libs\zbxcommon\comms.c /Fo"..\..\..\src\libs\zbxcommon\comms.o" $(CFLAGS)

..\..\..\src\libs\zbxcommon\misc.o: ..\..\..\src\libs\zbxcommon\misc.c
	$(CC) ..\..\..\src\libs\zbxcommon\misc.c /Fo"..\..\..\src\libs\zbxcommon\misc.o" $(CFLAGS)

..\..\..\src\libs\zbxcommon\str.o: ..\..\..\src\libs\zbxcommon\str.c
	$(CC) ..\..\..\src\libs\zbxcommon\str.c /Fo"..\..\..\src\libs\zbxcommon\str.o" $(CFLAGS)

..\..\..\src\libs\zbxcommon\xml.o: ..\..\..\src\libs\zbxcommon\xml.c
	$(CC) ..\..\..\src\libs\zbxcommon\xml.c /Fo"..\..\..\src\libs\zbxcommon\xml.o" $(CFLAGS)

..\..\..\src\libs\zbxcommon\zbxgetopt.o: ..\..\..\src\libs\zbxcommon\zbxgetopt.c
	$(CC) ..\..\..\src\libs\zbxcommon\zbxgetopt.c /Fo"..\..\..\src\libs\zbxcommon\zbxgetopt.o" $(CFLAGS)

..\..\..\src\libs\zbxcommon\file.o: ..\..\..\src\libs\zbxcommon\file.c
	$(CC) ..\..\..\src\libs\zbxcommon\file.c /Fo"..\..\..\src\libs\zbxcommon\file.o" $(CFLAGS)

..\..\..\src\libs\zbxcomms\comms.o: ..\..\..\src\libs\zbxcomms\comms.c
	$(CC) ..\..\..\src\libs\zbxcomms\comms.c /Fo"..\..\..\src\libs\zbxcomms\comms.o" $(CFLAGS)

..\..\..\src\libs\zbxcomms\telnet.o: ..\..\..\src\libs\zbxcomms\telnet.c
	$(CC) ..\..\..\src\libs\zbxcomms\telnet.c /Fo"..\..\..\src\libs\zbxcomms\telnet.o" $(CFLAGS)

..\..\..\src\libs\zbxconf\cfg.o: ..\..\..\src\libs\zbxconf\cfg.c
	$(CC) ..\..\..\src\libs\zbxconf\cfg.c /Fo"..\..\..\src\libs\zbxconf\cfg.o" $(CFLAGS)

..\..\..\src\libs\zbxcrypto\base64.o: ..\..\..\src\libs\zbxcrypto\base64.c
	$(CC) ..\..\..\src\libs\zbxcrypto\base64.c /Fo"..\..\..\src\libs\zbxcrypto\base64.o" $(CFLAGS)

..\..\..\src\libs\zbxcrypto\md5.o: ..\..\..\src\libs\zbxcrypto\md5.c
	$(CC) ..\..\..\src\libs\zbxcrypto\md5.c /Fo"..\..\..\src\libs\zbxcrypto\md5.o" $(CFLAGS)

..\..\..\src\libs\zbxjson\json.o: ..\..\..\src\libs\zbxjson\json.c
	$(CC) ..\..\..\src\libs\zbxjson\json.c /Fo"..\..\..\src\libs\zbxjson\json.o" $(CFLAGS)

..\..\..\src\libs\zbxjson\json_parser.o: ..\..\..\src\libs\zbxjson\json_parser.c
	$(CC) ..\..\..\src\libs\zbxjson\json_parser.c /Fo"..\..\..\src\libs\zbxjson\json_parser.o" $(CFLAGS)

..\..\..\src\libs\zbxlog\log.o: ..\..\..\src\libs\zbxlog\log.c
	$(CC) ..\..\..\src\libs\zbxlog\log.c /Fo"..\..\..\src\libs\zbxlog\log.o" $(CFLAGS)

..\..\..\src\libs\zbxregexp\gnuregex.o: ..\..\..\src\libs\zbxregexp\gnuregex.c
	$(CC) ..\..\..\src\libs\zbxregexp\gnuregex.c /Fo"..\..\..\src\libs\zbxregexp\gnuregex.o" $(CFLAGS)

..\..\..\src\libs\zbxregexp\zbxregexp.o: ..\..\..\src\libs\zbxregexp\zbxregexp.c
	$(CC) ..\..\..\src\libs\zbxregexp\zbxregexp.c /Fo"..\..\..\src\libs\zbxregexp\zbxregexp.o" $(CFLAGS)

..\..\..\src\libs\zbxsys\mutexs.o: ..\..\..\src\libs\zbxsys\mutexs.c
	$(CC) ..\..\..\src\libs\zbxsys\mutexs.c /Fo"..\..\..\src\libs\zbxsys\mutexs.o" $(CFLAGS)

..\..\..\src\libs\zbxsys\symbols.o: ..\..\..\src\libs\zbxsys\symbols.c
	$(CC) ..\..\..\src\libs\zbxsys\symbols.c /Fo"..\..\..\src\libs\zbxsys\symbols.o" $(CFLAGS)

..\..\..\src\libs\zbxsys\threads.o: ..\..\..\src\libs\zbxsys\threads.c
	$(CC) ..\..\..\src\libs\zbxsys\threads.c /Fo"..\..\..\src\libs\zbxsys\threads.o" $(CFLAGS)

..\..\..\src\libs\zbxexec\execute.o: ..\..\..\src\libs\zbxexec\execute.c
	$(CC) ..\..\..\src\libs\zbxexec\execute.c /Fo"..\..\..\src\libs\zbxexec\execute.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\agent\agent.o: ..\..\..\src\libs\zbxsysinfo\agent\agent.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\agent\agent.c /Fo"..\..\..\src\libs\zbxsysinfo\agent\agent.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\common.o: ..\..\..\src\libs\zbxsysinfo\common\common.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\common\common.c /Fo"..\..\..\src\libs\zbxsysinfo\common\common.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\file.o: ..\..\..\src\libs\zbxsysinfo\common\file.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\common\file.c /Fo"..\..\..\src\libs\zbxsysinfo\common\file.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\http.o: ..\..\..\src\libs\zbxsysinfo\common\http.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\common\http.c /Fo"..\..\..\src\libs\zbxsysinfo\common\http.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\net.o: ..\..\..\src\libs\zbxsysinfo\common\net.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\common\net.c /Fo"..\..\..\src\libs\zbxsysinfo\common\net.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\system.o: ..\..\..\src\libs\zbxsysinfo\common\system.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\common\system.c /Fo"..\..\..\src\libs\zbxsysinfo\common\system.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\simple\ntp.o: ..\..\..\src\libs\zbxsysinfo\simple\ntp.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\simple\ntp.c /Fo"..\..\..\src\libs\zbxsysinfo\simple\ntp.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\simple\simple.o: ..\..\..\src\libs\zbxsysinfo\simple\simple.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\simple\simple.c /Fo"..\..\..\src\libs\zbxsysinfo\simple\simple.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\cpu.o: ..\..\..\src\libs\zbxsysinfo\win32\cpu.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\cpu.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\cpu.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\diskio.o: ..\..\..\src\libs\zbxsysinfo\win32\diskio.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\diskio.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\diskio.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\diskspace.o: ..\..\..\src\libs\zbxsysinfo\win32\diskspace.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\diskspace.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\diskspace.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\memory.o: ..\..\..\src\libs\zbxsysinfo\win32\memory.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\memory.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\memory.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\net.o: ..\..\..\src\libs\zbxsysinfo\win32\net.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\net.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\net.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\system.o: ..\..\..\src\libs\zbxsysinfo\win32\system.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\system.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\system.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\hostname.o: ..\..\..\src\libs\zbxsysinfo\win32\hostname.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\hostname.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\hostname.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\software.o: ..\..\..\src\libs\zbxsysinfo\win32\software.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\software.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\software.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\pdhmon.o: ..\..\..\src\libs\zbxsysinfo\win32\pdhmon.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\pdhmon.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\pdhmon.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\proc.o: ..\..\..\src\libs\zbxsysinfo\win32\proc.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\proc.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\proc.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\services.o: ..\..\..\src\libs\zbxsysinfo\win32\services.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\services.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\services.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\swap.o: ..\..\..\src\libs\zbxsysinfo\win32\swap.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\swap.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\swap.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\uptime.o: ..\..\..\src\libs\zbxsysinfo\win32\uptime.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\uptime.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\uptime.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\win32.o: ..\..\..\src\libs\zbxsysinfo\win32\win32.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\win32.c /Fo"..\..\..\src\libs\zbxsysinfo\win32\win32.o" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\wmi.o: ..\..\..\src\libs\zbxsysinfo\win32\wmi.cpp
	$(CC) ..\..\..\src\libs\zbxsysinfo\win32\wmi.cpp /Fo"..\..\..\src\libs\zbxsysinfo\win32\wmi.o" $(CPPFLAGS)

..\..\..\src\libs\zbxsysinfo\sysinfo.o: ..\..\..\src\libs\zbxsysinfo\sysinfo.c
	$(CC) ..\..\..\src\libs\zbxsysinfo\sysinfo.c /Fo"..\..\..\src\libs\zbxsysinfo\sysinfo.o" $(CFLAGS)

..\..\..\src\libs\zbxwin32\perfmon.o: ..\..\..\src\libs\zbxwin32\perfmon.c
	$(CC) ..\..\..\src\libs\zbxwin32\perfmon.c /Fo"..\..\..\src\libs\zbxwin32\perfmon.o" $(CFLAGS)

..\..\..\src\libs\zbxwin32\service.o: ..\..\..\src\libs\zbxwin32\service.c
	$(CC) ..\..\..\src\libs\zbxwin32\service.c /Fo"..\..\..\src\libs\zbxwin32\service.o" $(CFLAGS)

..\..\..\src\zabbix_agent\active.o: ..\..\..\src\zabbix_agent\active.c
	$(CC) ..\..\..\src\zabbix_agent\active.c /Fo"..\..\..\src\zabbix_agent\active.o" $(CFLAGS)

..\..\..\src\zabbix_agent\cpustat.o: ..\..\..\src\zabbix_agent\cpustat.c
	$(CC) ..\..\..\src\zabbix_agent\cpustat.c /Fo"..\..\..\src\zabbix_agent\cpustat.o" $(CFLAGS)

..\..\..\src\zabbix_agent\eventlog.o: ..\..\..\src\zabbix_agent\eventlog.c
	$(CC) ..\..\..\src\zabbix_agent\eventlog.c /Fo"..\..\..\src\zabbix_agent\eventlog.o" $(CFLAGS)

..\..\..\src\zabbix_agent\listener.o: ..\..\..\src\zabbix_agent\listener.c
	$(CC) ..\..\..\src\zabbix_agent\listener.c /Fo"..\..\..\src\zabbix_agent\listener.o" $(CFLAGS)

..\..\..\src\zabbix_agent\logfiles.o: ..\..\..\src\zabbix_agent\logfiles.c
	$(CC) ..\..\..\src\zabbix_agent\logfiles.c /Fo"..\..\..\src\zabbix_agent\logfiles.o" $(CFLAGS)

..\..\..\src\zabbix_agent\perfstat.o: ..\..\..\src\zabbix_agent\perfstat.c
	$(CC) ..\..\..\src\zabbix_agent\perfstat.c /Fo"..\..\..\src\zabbix_agent\perfstat.o" $(CFLAGS)

..\..\..\src\zabbix_agent\stats.o: ..\..\..\src\zabbix_agent\stats.c
	$(CC) ..\..\..\src\zabbix_agent\stats.c /Fo"..\..\..\src\zabbix_agent\stats.o" $(CFLAGS)

..\..\..\src\zabbix_agent\zabbix_agentd.o: ..\..\..\src\zabbix_agent\zabbix_agentd.c
	$(CC) ..\..\..\src\zabbix_agent\zabbix_agentd.c /Fo"..\..\..\src\zabbix_agent\zabbix_agentd.o" $(CFLAGS)

..\..\..\src\zabbix_agent\zbxconf.o: ..\..\..\src\zabbix_agent\zbxconf.c
	$(CC) ..\..\..\src\zabbix_agent\zbxconf.c /Fo"..\..\..\src\zabbix_agent\zbxconf.o" $(CFLAGS)


