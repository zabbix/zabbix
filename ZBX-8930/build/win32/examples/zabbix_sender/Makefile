CC = cl.exe /nologo
LINKER = link.exe /NOLOGO

CFLAGS = /I ..\..\..\..\bin\win32\dev /c
LFLAGS = /LIBPATH:..\..\..\..\bin\win32\dev /DEFAULTLIB:zabbix_sender.lib

PROGNAME = sender.exe
OBJS = sender.obj
LIBS = zabbix_sender.lib

all: $(PROGNAME)

clean:
	del $(OBJS) $(PROGNAME)

sender.exe: $(OBJS)
	$(LINKER) $(OBJS) /OUT:$(PROGNAME) $(LFLAGS)

sender.obj: sender.c
	$(CC) sender.c $(CFLAGS)
