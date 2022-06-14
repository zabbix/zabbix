CC = cl.exe /nologo
LINKER = link.exe /NOLOGO

CFLAGS = /I ..\..\..\..\bin\win64\dev /c
LFLAGS = /LIBPATH:..\..\..\..\bin\win64\dev /DEFAULTLIB:zabbix_sender.lib

PROGNAME = sender.exe
OBJS = sender.obj
LIBS = zabbix_sender.lib ws2_32.lib

all: $(PROGNAME)

clean:
	del $(OBJS) $(PROGNAME)

sender.exe: $(OBJS)
	$(LINKER) $(OBJS) /OUT:$(PROGNAME) $(LFLAGS) $(LIBS)

sender.obj: sender.c
	$(CC) sender.c $(CFLAGS)
