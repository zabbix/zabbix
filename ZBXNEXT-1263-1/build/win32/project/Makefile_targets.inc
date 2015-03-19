$(MESSAGES_H): messages.mc
	$(MC) -U -h ".\\" -r ".\\" messages.mc

$(RESOURCE_RES): $(RESOURCE_RC) $(RESOURCE_H) $(DESC_H)
	$(RC) /d "NDEBUG" /d "_VC80_UPGRADE=0x0600" /l 0x419 /fo"$(RESOURCE_RES)" $(RESOURCE_RC)

..\..\..\src\libs\zbxalgo\algodefs.o: ..\..\..\src\libs\zbxalgo\algodefs.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxalgo\vector.o: ..\..\..\src\libs\zbxalgo\vector.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\alias.o: ..\..\..\src\libs\zbxcommon\alias.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\comms.o: ..\..\..\src\libs\zbxcommon\comms.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\iprange.o: ..\..\..\src\libs\zbxcommon\iprange.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\misc.o: ..\..\..\src\libs\zbxcommon\misc.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\str.o: ..\..\..\src\libs\zbxcommon\str.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\xml.o: ..\..\..\src\libs\zbxcommon\xml.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\zbxgetopt.o: ..\..\..\src\libs\zbxcommon\zbxgetopt.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcommon\file.o: ..\..\..\src\libs\zbxcommon\file.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcomms\comms.o: ..\..\..\src\libs\zbxcomms\comms.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcomms\telnet.o: ..\..\..\src\libs\zbxcomms\telnet.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxconf\cfg.o: ..\..\..\src\libs\zbxconf\cfg.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcrypto\base64.o: ..\..\..\src\libs\zbxcrypto\base64.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcrypto\md5.o: ..\..\..\src\libs\zbxcrypto\md5.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxcrypto\tls.o: ..\..\..\src\libs\zbxcrypto\tls.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxjson\json.o: ..\..\..\src\libs\zbxjson\json.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxjson\json_parser.o: ..\..\..\src\libs\zbxjson\json_parser.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxlog\log.o: ..\..\..\src\libs\zbxlog\log.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxregexp\gnuregex.o: ..\..\..\src\libs\zbxregexp\gnuregex.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxregexp\zbxregexp.o: ..\..\..\src\libs\zbxregexp\zbxregexp.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsys\mutexs.o: ..\..\..\src\libs\zbxsys\mutexs.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsys\symbols.o: ..\..\..\src\libs\zbxsys\symbols.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsys\threads.o: ..\..\..\src\libs\zbxsys\threads.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxexec\execute.o: ..\..\..\src\libs\zbxexec\execute.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\agent\agent.o: ..\..\..\src\libs\zbxsysinfo\agent\agent.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\common.o: ..\..\..\src\libs\zbxsysinfo\common\common.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\file.o: ..\..\..\src\libs\zbxsysinfo\common\file.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\http.o: ..\..\..\src\libs\zbxsysinfo\common\http.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\net.o: ..\..\..\src\libs\zbxsysinfo\common\net.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\common\system.o: ..\..\..\src\libs\zbxsysinfo\common\system.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\simple\ntp.o: ..\..\..\src\libs\zbxsysinfo\simple\ntp.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\simple\simple.o: ..\..\..\src\libs\zbxsysinfo\simple\simple.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\cpu.o: ..\..\..\src\libs\zbxsysinfo\win32\cpu.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\diskio.o: ..\..\..\src\libs\zbxsysinfo\win32\diskio.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\diskspace.o: ..\..\..\src\libs\zbxsysinfo\win32\diskspace.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\memory.o: ..\..\..\src\libs\zbxsysinfo\win32\memory.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\net.o: ..\..\..\src\libs\zbxsysinfo\win32\net.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\system.o: ..\..\..\src\libs\zbxsysinfo\win32\system.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\hostname.o: ..\..\..\src\libs\zbxsysinfo\win32\hostname.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\software.o: ..\..\..\src\libs\zbxsysinfo\win32\software.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\pdhmon.o: ..\..\..\src\libs\zbxsysinfo\win32\pdhmon.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\proc.o: ..\..\..\src\libs\zbxsysinfo\win32\proc.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\services.o: ..\..\..\src\libs\zbxsysinfo\win32\services.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\swap.o: ..\..\..\src\libs\zbxsysinfo\win32\swap.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\uptime.o: ..\..\..\src\libs\zbxsysinfo\win32\uptime.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\win32.o: ..\..\..\src\libs\zbxsysinfo\win32\win32.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxsysinfo\win32\wmi.o: ..\..\..\src\libs\zbxsysinfo\win32\wmi.cpp
	$(CC) $? /Fo"$@" $(CPPFLAGS)

..\..\..\src\libs\zbxsysinfo\sysinfo.o: ..\..\..\src\libs\zbxsysinfo\sysinfo.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxwin32\perfmon.o: ..\..\..\src\libs\zbxwin32\perfmon.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxwin32\service.o: ..\..\..\src\libs\zbxwin32\service.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxself\selfmon.o: ..\..\..\src\libs\zbxself\selfmon.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\active.o: ..\..\..\src\zabbix_agent\active.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\cpustat.o: ..\..\..\src\zabbix_agent\cpustat.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\eventlog.o: ..\..\..\src\zabbix_agent\eventlog.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\listener.o: ..\..\..\src\zabbix_agent\listener.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\logfiles.o: ..\..\..\src\zabbix_agent\logfiles.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\perfstat.o: ..\..\..\src\zabbix_agent\perfstat.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\stats.o: ..\..\..\src\zabbix_agent\stats.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\zabbix_agentd.o: ..\..\..\src\zabbix_agent\zabbix_agentd.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_get\zabbix_get.o: ..\..\..\src\zabbix_get\zabbix_get.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_sender\zabbix_sender.o: ..\..\..\src\zabbix_sender\zabbix_sender.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\zabbix_agent\zbxconf.o: ..\..\..\src\zabbix_agent\zbxconf.c
	$(CC) $? /Fo"$@" $(CFLAGS)

..\..\..\src\libs\zbxwin32\fatal.o: ..\..\..\src\libs\zbxwin32\fatal.c
	$(CC) $? /Fo"$@" $(CFLAGS)
