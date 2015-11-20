all: $(PROGNAME) $(POSTBUILD)

clean: $(POSTCLEAN)
# break this down because del has limit for command-line args length (NMAKE : fatal error U1077: 'del' : return code '0x1')
	del $(OBJS) 2>NUL
	del $(RESOURCE_RES) $(MESSAGES_H) $(PROGNAME) messages.rc MSG00001.bin $(TARGETNAME).$(TARGETEXT).manifest \
		$(PDBS) $(TARGETNAME).$(TARGETEXT).idb 2>NUL

$(PROGNAME): $(MESSAGES_H) $(OBJS) $(RESOURCE_RES)
	$(LINKER) $(OBJS) $(RESOURCE_RES) $(LFLAGS) /OUT:$(PROGNAME) $(LIBS)
	$(MT) /outputresource:"$(PROGNAME);#1" /manifest $(TARGETNAME).$(TARGETEXT).manifest /nologo

$(MESSAGES_H): messages.mc
	$(MC) -U -h ".\\" -r ".\\" messages.mc

$(RESOURCE_RES): $(RESOURCE_RC) $(RESOURCE_H) $(DESC_H)
	$(RC) $(RFLAGS) /d "NDEBUG" /d "_VC80_UPGRADE=0x0600" /l 0x419 /fo"$(RESOURCE_RES)" $(RESOURCE_RC)

{..\..\..\src\libs\zbxalgo}.c{..\..\..\src\libs\zbxalgo}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxcommon}.c{..\..\..\src\libs\zbxcommon}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxcomms}.c{..\..\..\src\libs\zbxcomms}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxconf}.c{..\..\..\src\libs\zbxconf}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxcrypto}.c{..\..\..\src\libs\zbxcrypto}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxjson}.c{..\..\..\src\libs\zbxjson}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxlog}.c{..\..\..\src\libs\zbxlog}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxregexp}.c{..\..\..\src\libs\zbxregexp}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxsys}.c{..\..\..\src\libs\zbxsys}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxexec}.c{..\..\..\src\libs\zbxexec}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxsysinfo}.c{..\..\..\src\libs\zbxsysinfo}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxsysinfo\agent}.c{..\..\..\src\libs\zbxsysinfo\agent}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxsysinfo\simple}.c{..\..\..\src\libs\zbxsysinfo\simple}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxsysinfo\common}.c{..\..\..\src\libs\zbxsysinfo\common}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxsysinfo\win32}.c{..\..\..\src\libs\zbxsysinfo\win32}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxsysinfo\win32}.cpp{..\..\..\src\libs\zbxsysinfo\win32}.o:
	$(CC) $? /Fo"$@" $(CPPFLAGS)

{..\..\..\src\libs\zbxwin32}.c{..\..\..\src\libs\zbxwin32}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{..\..\..\src\libs\zbxself}.c{..\..\..\src\libs\zbxself}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)

{$(PROJECTDIR)}.c{$(PROJECTDIR)}.o:
	$(CC) $? /Fo"$@" $(CFLAGS)
