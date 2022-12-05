if AGENT
AGENT_tests = \
	SYSTEM_LOCALTIME \
	WEB_PAGE_GET \
	VFS_FILE_EXISTS
endif

noinst_PROGRAMS = $(AGENT_tests)

if AGENT
COMMON_SRC_FILES = \
	../../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/zabbix_agent/libzbxagent.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests

SYSTEM_LOCALTIME_SOURCES = \
	SYSTEM_LOCALTIME.c \
	$(COMMON_SRC_FILES)

SYSTEM_LOCALTIME_WRAP_FUNCS = \
	-Wl,--wrap=time \
	-Wl,--wrap=gettimeofday

SYSTEM_LOCALTIME_LDADD = \
	$(COMMON_LIB_FILES)

SYSTEM_LOCALTIME_LDADD += @AGENT_LIBS@

SYSTEM_LOCALTIME_LDFLAGS = @AGENT_LDFLAGS@ $(SYSTEM_LOCALTIME_WRAP_FUNCS)

SYSTEM_LOCALTIME_CFLAGS = $(COMMON_COMPILER_FLAGS)

WEB_PAGE_GET_SOURCES = \
	WEB_PAGE_GET.c \
	$(COMMON_SRC_FILES)

if HAVE_LIBCURL
WEB_PAGE_GET_WRAP_FUNCS = \
	-Wl,--wrap=curl_easy_init \
	-Wl,--wrap=curl_easy_setopt \
	-Wl,--wrap=curl_easy_perform \
	-Wl,--wrap=curl_easy_cleanup
else
WEB_PAGE_GET_WRAP_FUNCS = \
	-Wl,--wrap=zbx_tcp_connect \
	-Wl,--wrap=zbx_tcp_send_ext \
	-Wl,--wrap=zbx_tcp_recv_raw_ext \
	-Wl,--wrap=zbx_tcp_close
endif

WEB_PAGE_GET_LDADD = \
	$(COMMON_LIB_FILES)

WEB_PAGE_GET_LDADD += @AGENT_LIBS@

WEB_PAGE_GET_LDFLAGS = @AGENT_LDFLAGS@ $(WEB_PAGE_GET_WRAP_FUNCS)

WEB_PAGE_GET_CFLAGS = $(COMMON_COMPILER_FLAGS)

VFS_FILE_EXISTS_SOURCES = \
	VFS_FILE_EXISTS.c \
	$(COMMON_SRC_FILES)

VFS_FILE_EXISTS_LDADD = \
	$(COMMON_LIB_FILES)

VFS_FILE_EXISTS_LDADD += @AGENT_LIBS@

VFS_FILE_EXISTS = @AGENT_LDFLAGS@

VFS_FILE_EXISTS_CFLAGS = $(COMMON_COMPILER_FLAGS) -I$(top_srcdir)/src/libs/zbxsysinfo/common/

endif
