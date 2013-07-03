CC = cl.exe /nologo
LINKER = link.exe /NOLOGO
RC = rc.exe /d "ZABBIX_GET" /d _WINDOWS
MC = mc.exe
MT = mt.exe
MESSAGES_H = messages.h
RESOURCE_H = resource.h
RESOURCE_RC = resource.rc
RESOURCE_RES = zabbix_get.res
OBJS  = ..\..\..\src\libs\zbxcommon\comms.o \
	..\..\..\src\libs\zbxcommon\misc.o \
	..\..\..\src\libs\zbxcommon\str.o \
	..\..\..\src\libs\zbxcommon\xml.o \
	..\..\..\src\libs\zbxcommon\zbxgetopt.o \
	..\..\..\src\libs\zbxcommon\file.o \
	..\..\..\src\libs\zbxcomms\comms.o \
	..\..\..\src\libs\zbxconf\cfg.o \
	..\..\..\src\libs\zbxcrypto\base64.o \
	..\..\..\src\libs\zbxlog\log.o \
	..\..\..\src\libs\zbxsys\mutexs.o \
	..\..\..\src\libs\zbxsys\symbols.o \
	..\..\..\src\libs\zbxsys\threads.o \
	..\..\..\src\zabbix_get\zabbix_get.o

LIBS = ws2_32.lib psapi.lib pdh.lib Wldap32.lib advapi32.lib uuid.lib Iphlpapi.lib

all: $(PROGNAME)

clean:
	del $(OBJS) $(RESOURCE_RES) $(MESSAGES_H) $(PROGNAME) messages.rc MSG00001.bin zabbix_get.exe.manifest

$(PROGNAME): $(MESSAGES_H) $(OBJS) $(RESOURCE_RES)
	$(LINKER) $(OBJS) $(RESOURCE_RES) /OUT:$(PROGNAME) $(LFLAGS) $(LIBS)
	$(MT) /outputresource:"$(PROGNAME);#1" /manifest zabbix_get.exe.manifest /nologo

$(MESSAGES_H): messages.mc
	$(MC) -U -h ".\\" -r ".\\" messages.mc

$(RESOURCE_RES): $(RESOURCE_RC) $(RESOURCE_H)
	$(RC) /d "NDEBUG" /d "_VC80_UPGRADE=0x0600" /l 0x419 /fo"$(RESOURCE_RES)" $(RESOURCE_RC)

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

..\..\..\src\libs\zbxconf\cfg.o: ..\..\..\src\libs\zbxconf\cfg.c
	$(CC) ..\..\..\src\libs\zbxconf\cfg.c /Fo"..\..\..\src\libs\zbxconf\cfg.o" $(CFLAGS)

..\..\..\src\libs\zbxcrypto\base64.o: ..\..\..\src\libs\zbxcrypto\base64.c
	$(CC) ..\..\..\src\libs\zbxcrypto\base64.c /Fo"..\..\..\src\libs\zbxcrypto\base64.o" $(CFLAGS)

..\..\..\src\libs\zbxlog\log.o: ..\..\..\src\libs\zbxlog\log.c
	$(CC) ..\..\..\src\libs\zbxlog\log.c /Fo"..\..\..\src\libs\zbxlog\log.o" $(CFLAGS)

..\..\..\src\libs\zbxsys\mutexs.o: ..\..\..\src\libs\zbxsys\mutexs.c
	$(CC) ..\..\..\src\libs\zbxsys\mutexs.c /Fo"..\..\..\src\libs\zbxsys\mutexs.o" $(CFLAGS)

..\..\..\src\libs\zbxsys\symbols.o: ..\..\..\src\libs\zbxsys\symbols.c
	$(CC) ..\..\..\src\libs\zbxsys\symbols.c /Fo"..\..\..\src\libs\zbxsys\symbols.o" $(CFLAGS)

..\..\..\src\libs\zbxsys\threads.o: ..\..\..\src\libs\zbxsys\threads.c
	$(CC) ..\..\..\src\libs\zbxsys\threads.c /Fo"..\..\..\src\libs\zbxsys\threads.o" $(CFLAGS)

..\..\..\src\zabbix_get\zabbix_get.o: ..\..\..\src\zabbix_get\zabbix_get.c
	$(CC) ..\..\..\src\zabbix_get\zabbix_get.c /Fo"..\..\..\src\zabbix_get\zabbix_get.o" $(CFLAGS)
