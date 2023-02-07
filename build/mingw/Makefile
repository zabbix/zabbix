.ONESHELL:

SHELL := cmd
BUILDDIR := $(subst /,\,$(CURDIR))
TOPDIR := $(BUILDDIR)\..\..
OUTPUTDIR := output

MODULEDIR = $(TOPDIR)\src\go
TARGETDIR = $(TOPDIR)\src\go\cmd\zabbix_agent2
TARGETPKG=zabbix.com/cmd/zabbix_agent2

CC = gcc
WINDRES = windres.exe

OBJS = \
	$(OUTPUTDIR)\threads.o \
	$(OUTPUTDIR)\str.o \
	$(OUTPUTDIR)\misc.o \
	$(OUTPUTDIR)\time.o \
	$(OUTPUTDIR)\fatal.o \
	$(OUTPUTDIR)\disk.o \
	$(OUTPUTDIR)\iprange.o \
	$(OUTPUTDIR)\md5.o \
	$(OUTPUTDIR)\sysinfo.o \
	$(OUTPUTDIR)\vector.o \
	$(OUTPUTDIR)\hashset.o \
	$(OUTPUTDIR)\zbxregexp.o \
	$(OUTPUTDIR)\persistent_state.o \
	$(OUTPUTDIR)\logfiles.o \
	$(OUTPUTDIR)\file.o \
	$(OUTPUTDIR)\alias.o \
	$(OUTPUTDIR)\algodefs.o \
	$(OUTPUTDIR)\json.o \
	$(OUTPUTDIR)\json_parser.o \
	$(OUTPUTDIR)\jsonpath.o \
	$(OUTPUTDIR)\jsonobj.o \
	$(OUTPUTDIR)\sha256crypt.o \
	$(OUTPUTDIR)\variant.o \
	$(OUTPUTDIR)\sysinfo_system.o \
	$(OUTPUTDIR)\sysinfo_dns.o \
	$(OUTPUTDIR)\sysinfo_file.o \
	$(OUTPUTDIR)\sysinfo_dir.o \
	$(OUTPUTDIR)\eventlog.o \
	$(TARGETDIR)\resource.syso

CFLAGS := $(CFLAGS) -O2 -g -DHAVE_STDINT_H=1 -DZBX_BUILD_AGENT2=1  -DHAVE_THREAD_LOCAL=1 \
	-I$(TOPDIR)\build\win32\include -I$(TOPDIR)\build\mingw -I$(TOPDIR)\include

RFLAGS := $(RFLAGS) --input-format=rc -O coff
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
	RFLAGS := $(RFLAGS) --target=pe-i386

else
	TARGETBIN=$(TOPDIR)\bin\win64\zabbix_agent2.exe
	GOARCH := amd64
	RFLAGS := $(RFLAGS) --target=pe-x86-64
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
	CFLAGS := $(CFLAGS) -DPCRE_STATIC -I$(PCRE)\include -DHAVE_PCRE_H=1
	LDFLAGS := $(LDFLAGS) -L$(PCRE)\lib
	TAGS := $(TAGS) pcre
endif

ifneq ("$(PCRE2)", "")
	CFLAGS := $(CFLAGS) -DPCRE2_STATIC -I$(PCRE2)\include -DHAVE_PCRE2_H=1
	LDFLAGS := $(LDFLAGS) -L$(PCRE2)\lib
	TAGS := $(TAGS) pcre2
endif

CGO_CFLAGS += $(CFLAGS)
CGO_LDFLAGS += $(LDFLAGS)

PKG=zabbix.com/pkg/version

GOLDFLAGS = -X $(PKG).compileOs=windows
GOLDFLAGS += -X $(PKG).compileArch=$(GOARCH)
GOLDFLAGS += -X main.confDefault=zabbix_agent2.win.conf
GOLDFLAGS += -X main.applicationName=zabbix_agent2

all: $(TARGETBIN)

$(OUTPUTDIR)\misc.o: $(TOPDIR)\src\libs\zbxcommon\misc.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\iprange.o: $(TOPDIR)\src\libs\zbxcommon\iprange.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\str.o: $(TOPDIR)\src\libs\zbxcommon\str.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\file.o: $(TOPDIR)\src\libs\zbxcommon\file.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\alias.o: $(TOPDIR)\src\libs\zbxcommon\alias.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\time.o: $(TOPDIR)\src\libs\zbxcommon\time.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\tls_version.o: $(TOPDIR)\src\libs\zbxcrypto\tls_version.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\fatal.o: $(TOPDIR)\src\libs\zbxwin32\fatal.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\disk.o: $(TOPDIR)\src\libs\zbxwin32\disk.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\threads.o: $(TOPDIR)\src\libs\zbxsys\threads.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\md5.o: $(TOPDIR)\src\libs\zbxcrypto\md5.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\json_parser.o: $(TOPDIR)\src\libs\zbxjson\json_parser.c
	$(CC) $(CFLAGS) -DUNICODE -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\jsonpath.o: $(TOPDIR)\src\libs\zbxjson\jsonpath.c
	$(CC) $(CFLAGS) -DUNICODE -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\jsonobj.o: $(TOPDIR)\src\libs\zbxjson\jsonobj.c
	$(CC) $(CFLAGS) -DUNICODE -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\sha256crypt.o: $(TOPDIR)\src\libs\zbxcrypto\sha256crypt.c
	$(CC) $(CFLAGS) -DUNICODE -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\variant.o: $(TOPDIR)\src\libs\zbxcommon\variant.c
	$(CC) $(CFLAGS) -DUNICODE -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\json.o: $(TOPDIR)\src\libs\zbxjson\json.c
	$(CC) $(CFLAGS) -DUNICODE -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\sysinfo.o: $(TOPDIR)\src\libs\zbxsysinfo\sysinfo.c
	$(CC) $(CFLAGS) -DUNICODE -DWITH_COMMON_METRICS -c $^ -o $@

$(OUTPUTDIR)\sysinfo_system.o: $(TOPDIR)\src\libs\zbxsysinfo\common\system.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\sysinfo_dns.o: $(TOPDIR)\src\libs\zbxsysinfo\common\dns.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\sysinfo_file.o: $(TOPDIR)\src\libs\zbxsysinfo\common\file.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\sysinfo_dir.o: $(TOPDIR)\src\libs\zbxsysinfo\common\dir.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\vector.o: $(TOPDIR)\src\libs\zbxalgo\vector.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\hashset.o: $(TOPDIR)\src\libs\zbxalgo\hashset.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\version.o: $(TOPDIR)\src\libs\zbxversion\version.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\algodefs.o: $(TOPDIR)\src\libs\zbxalgo\algodefs.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\zbxregexp.o: $(TOPDIR)\src\libs\zbxregexp\zbxregexp.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\persistent_state.o: $(TOPDIR)\src\zabbix_agent\logfiles\persistent_state.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\logfiles.o: $(TOPDIR)\src\zabbix_agent\logfiles\logfiles.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -o $@

$(OUTPUTDIR)\eventlog.o: $(TOPDIR)\src\zabbix_agent\eventlog.c
	$(CC) $(CFLAGS) -DUNICODE -c $^ -I$(TOPDIR)\go\win64 -o $@

$(TARGETDIR)\resource.syso: $(TOPDIR)\build\win32\project\resource.rc
	$(WINDRES) $^ $(RFLAGS) -D _WINDOWS -D ZABBIX_AGENT2 -o $@

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
	del $(TOPDIR)\include\config.h "$(TARGETBIN)" $(OUTPUTDIR)\*.o $(TARGETDIR)\*.syso 2>NUL
