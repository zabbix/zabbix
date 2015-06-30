RC = rc.exe /d "ZABBIX_SENDER" /d _WINDOWS
DESC_H = zabbix_sender_desc.h
RESOURCE_RES = zabbix_sender.res
ADD_INCS = /I ..\..\..\src\zabbix_sender

!INCLUDE Makefile_macros.inc

OBJS = ..\..\..\src\libs\zbxcommon\comms.o \
	..\..\..\src\libs\zbxcommon\iprange.o \
	..\..\..\src\libs\zbxcommon\misc.o \
	..\..\..\src\libs\zbxcommon\str.o \
	..\..\..\src\libs\zbxcommon\xml.o \
	..\..\..\src\libs\zbxcommon\zbxgetopt.o \
	..\..\..\src\libs\zbxcomms\comms.o \
	..\..\..\src\libs\zbxconf\cfg.o \
	..\..\..\src\libs\zbxcrypto\base64.o \
	..\..\..\src\libs\zbxjson\json.o \
	..\..\..\src\libs\zbxjson\json_parser.o \
	..\..\..\src\libs\zbxlog\log.o \
	..\..\..\src\libs\zbxsys\mutexs.o \
	..\..\..\src\libs\zbxsys\symbols.o \
	..\..\..\src\libs\zbxsys\threads.o \
	..\..\..\src\zabbix_sender\zabbix_sender.o \
	..\..\..\src\libs\zbxwin32\fatal.o
SOURCEDIR = ..\..\..\src\zabbix_sender\win32

LIBS = ws2_32.lib psapi.lib pdh.lib Wldap32.lib advapi32.lib uuid.lib Iphlpapi.lib

!INCLUDE Makefile_tls.inc

all: $(PROGNAME) $(POSTBUILD)

clean:
# break this down because del has limit for command-line args length (NMAKE : fatal error U1077: 'del' : return code '0x1')
	del $(OBJS) 2>nul
	del $(RESOURCE_RES) $(MESSAGES_H) $(PROGNAME) messages.rc MSG00001.bin $(TARGETNAME).manifest 2>nul

$(PROGNAME): $(MESSAGES_H) $(OBJS) $(RESOURCE_RES)
	if not exist $(TARGETDIR) @mkdir $(TARGETDIR)
	$(LINKER) $(OBJS) $(RESOURCE_RES) /OUT:$(PROGNAME) $(LFLAGS) $(LIBS)
	$(MT) /outputresource:"$(PROGNAME);#1" /manifest $(TARGETNAME).manifest /nologo

$(TARGETDIR)\zabbix_sender.h: $(SOURCEDIR)\zabbix_sender.h
	copy $(SOURCEDIR)\zabbix_sender.h $(TARGETDIR)

post-build: $(TARGETDIR)\zabbix_sender.h
	del $(TARGETDIR)\zabbix_sender.exp 2>nul

!INCLUDE Makefile_targets.inc
