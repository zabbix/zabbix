RC = rc.exe /d "ZABBIX_GET" /d _WINDOWS
DESC_H = zabbix_get_desc.h
RESOURCE_RES = zabbix_get.res
ADD_INCS = /I ..\..\..\src\zabbix_get

!INCLUDE Makefile_macros.inc

OBJS  = ..\..\..\src\libs\zbxcommon\comms.o \
	..\..\..\src\libs\zbxcommon\iprange.o \
	..\..\..\src\libs\zbxcommon\misc.o \
	..\..\..\src\libs\zbxcommon\str.o \
	..\..\..\src\libs\zbxcommon\xml.o \
	..\..\..\src\libs\zbxcommon\zbxgetopt.o \
	..\..\..\src\libs\zbxcomms\comms.o \
	..\..\..\src\libs\zbxconf\cfg.o \
	..\..\..\src\libs\zbxcrypto\base64.o \
	..\..\..\src\libs\zbxlog\log.o \
	..\..\..\src\libs\zbxsys\mutexs.o \
	..\..\..\src\libs\zbxsys\symbols.o \
	..\..\..\src\libs\zbxsys\threads.o \
	..\..\..\src\zabbix_get\zabbix_get.o \
	..\..\..\src\libs\zbxwin32\fatal.o

LIBS = ws2_32.lib psapi.lib pdh.lib Wldap32.lib advapi32.lib uuid.lib Iphlpapi.lib

!INCLUDE Makefile_tls.inc

all: $(PROGNAME)

clean:
# break this down because del has limit for command-line args length (NMAKE : fatal error U1077: 'del' : return code '0x1')
	del $(OBJS) 2>nul
	del $(RESOURCE_RES) $(MESSAGES_H) $(PROGNAME) messages.rc MSG00001.bin $(TARGETNAME).manifest 2>nul

$(PROGNAME): $(MESSAGES_H) $(OBJS) $(RESOURCE_RES)
	$(LINKER) $(OBJS) $(RESOURCE_RES) /OUT:$(PROGNAME) $(LFLAGS) $(LIBS)
	$(MT) /outputresource:"$(PROGNAME);#1" /manifest $(TARGETNAME).manifest /nologo

!INCLUDE Makefile_targets.inc
