.ONESHELL:

BUILDDIR := $(subst /,\,$(CURDIR))
TOPDIR := $(BUILDDIR)\..\..
OUTPUTDIR := output

MODULEDIR = $(TOPDIR)\src\go
TARGETDIR = $(TOPDIR)\src\go\cmd\zabbix_agent2
TARGETPKG=zabbix.com/cmd/zabbix_agent2

CC = gcc

OBJS = \
	$(OUTPUTDIR)\threads.o \
	$(OUTPUTDIR)\str.o \
	$(OUTPUTDIR)\misc.o \
	$(OUTPUTDIR)\fatal.o \
	$(OUTPUTDIR)\iprange.o \
	$(OUTPUTDIR)\md5.o \
	$(OUTPUTDIR)\sysinfo.o \
	$(OUTPUTDIR)\vector.o \
	$(OUTPUTDIR)\zbxregexp.o \
	$(OUTPUTDIR)\logfiles.o \
	$(OUTPUTDIR)\file.o \
	$(OUTPUTDIR)\alias.o \
	$(OUTPUTDIR)\algodefs.o \
	$(OUTPUTDIR)\sysinfo_system.o \
	$(OUTPUTDIR)\eventlog.o

CFLAGS := $(CFLAGS) -O2 -g -DUNICODE -DHAVE_STDINT_H=1 -I$(TOPDIR)\include -I$(TOPDIR)\build\win32\include -I$(TOPDIR)\build\mingw
GOPATH := $(GOPATH);$(TOPDIR)\go
TAGS :=

ifeq ("$(ARCH)", "")
	ARCH := $(PROCESSOR_ARCHITECTURE)
endif

ifeq ("$(ARCH)", "x86")
	CFLAGS := $(CFLAGS) -m32
	CGO_CFLAGS := $(CGO_CFLAGS) -m32
	GOARCH := 386
	TARGETBIN=$(TOPDIR)\bin\win32\zabbix_agent2.exe

else
	TARGETBIN=$(TOPDIR)\bin\win64\zabbix_agent2.exe
	GOARCH := amd64
endif

ifneq ("$(OPENSSL)", "")
	CFLAGS := $(CFLAGS) -DHAVE_OPENSSL=1 -DHAVE_OPENSSL_WITH_PSK=1
	ifneq (,$(wildcard $(OPENSSL)))
		CFLAGS := $(CFLAGS) -I$(OPENSSL)\include
		LDFLAGS := $(LDFLAGS) -L$(OPENSSL)\lib
		TAGS += openssl
		OBJS += $(OUTPUTDIR)\tls_version.o
	endif
endif

ifneq ("$(PCRE)", "")
	CFLAGS := $(CFLAGS) -I$(PCRE)\include
	LDFLAGS := $(LDFLAGS) -L$(PCRE)\lib
endif

CGO_CFLAGS += $(CFLAGS)
CGO_LDFLAGS += $(LDFLAGS)

PKG=zabbix.com/pkg/version

GOLDFLAGS = -X $(PKG).titleMessage=zabbix_agent2
GOLDFLAGS += -X $(PKG).compileOs=windows
GOLDFLAGS += -X $(PKG).compileArch=$(GOARCH)
GOLDFLAGS += -X main.confDefault=zabbix_agent2.win.conf

all: $(TARGETBIN)

$(OUTPUTDIR)\misc.o: $(TOPDIR)\src\libs\zbxcommon\misc.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\iprange.o: $(TOPDIR)\src\libs\zbxcommon\iprange.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\str.o: $(TOPDIR)\src\libs\zbxcommon\str.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\file.o: $(TOPDIR)\src\libs\zbxcommon\file.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\alias.o: $(TOPDIR)\src\libs\zbxcommon\alias.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\tls_version.o: $(TOPDIR)\src\libs\zbxcrypto\tls_version.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\fatal.o: $(TOPDIR)\src\libs\zbxwin32\fatal.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\threads.o: $(TOPDIR)\src\libs\zbxsys\threads.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\md5.o: $(TOPDIR)\src\libs\zbxcrypto\md5.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\sysinfo.o: $(TOPDIR)\src\libs\zbxsysinfo\sysinfo.c
	$(CC) $(CFLAGS) -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\sysinfo_system.o: $(TOPDIR)\src\libs\zbxsysinfo\common\system.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\sysinfo_net.o: $(TOPDIR)\src\libs\zbxsysinfo\common\net.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\vector.o: $(TOPDIR)\src\libs\zbxalgo\vector.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\algodefs.o: $(TOPDIR)\src\libs\zbxalgo\algodefs.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\zbxregexp.o: $(TOPDIR)\src\libs\zbxregexp\zbxregexp.c
	$(CC) $(CFLAGS) -DPCRE_STATIC -c $^ -o $@

$(OUTPUTDIR)\logfiles.o: $(TOPDIR)\src\zabbix_agent\logfiles\logfiles.c
	$(CC) $(CFLAGS) -c $^ -o $@

$(OUTPUTDIR)\eventlog.o: $(TOPDIR)\src\zabbix_agent\eventlog.c
	$(CC) $(CFLAGS) -c $^ -I$(TOPDIR)\go\win64 -o $@

.FORCE:
$(TARGETBIN): $(OBJS) .FORCE
	set CGO_CFLAGS=$(CGO_CFLAGS)
	set CGO_LDFLAGS=$(CGO_LDFLAGS)
	set GOARCH=$(GOARCH)
	set CGO_ENABLED=1

	for /F "usebackq tokens=1,2 delims==" %%i in (`wmic os get LocalDateTime /VALUE 2^>NUL`) do if '.%%i.'=='.LocalDateTime.' set ldt=%%j
	set BUILD_DATE=%ldt:~0,4%-%ldt:~4,2%-%ldt:~6,2%
	set BUILD_TIME=%ldt:~8,2%:%ldt:~10,2%:%ldt:~12,2%

	set GOLDFLAGS=$(GOLDFLAGS) -X $(PKG).compileDate=%BUILD_DATE%
	set GOLDFLAGS=%GOLDFLAGS% -X $(PKG).compileTime=%BUILD_TIME%
	cd $(MODULEDIR)

	go build -tags "$(TAGS)" -ldflags="%GOLDFLAGS%" -o $(TARGETBIN) zabbix.com/cmd/zabbix_agent2

clean:
	go clean -cache
	del $(TOPDIR)\include\config.h "$(TARGETBIN)" $(OBJS) 2>NUL
