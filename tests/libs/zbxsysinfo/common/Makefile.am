if AGENT
AGENT_tests = \
	system_localtime \
	web_page_get \
	vfs_file_exists \
	zbx_ip_reverse
endif

noinst_PROGRAMS = $(AGENT_tests)

if AGENT
COMMON_SRC_FILES = \
	../../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libfunclistsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

system_localtime_SOURCES = \
	system_localtime.c \
	$(COMMON_SRC_FILES)

system_localtime_WRAP_FUNCS = \
	-Wl,--wrap=time \
	-Wl,--wrap=gettimeofday

system_localtime_LDADD = \
	$(COMMON_LIB_FILES)

system_localtime_LDADD += @AGENT_LIBS@

system_localtime_LDFLAGS = @AGENT_LDFLAGS@ $(system_localtime_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	$(TLS_LDFLAGS)

system_localtime_CFLAGS = $(COMMON_COMPILER_FLAGS)

web_page_get_SOURCES = \
	web_page_get.c \
	$(COMMON_SRC_FILES)

if HAVE_LIBCURL
web_page_get_WRAP_FUNCS = \
	-Wl,--wrap=curl_easy_init \
	-Wl,--wrap=curl_easy_setopt \
	-Wl,--wrap=curl_easy_perform \
	-Wl,--wrap=curl_easy_cleanup
else
web_page_get_WRAP_FUNCS = \
	-Wl,--wrap=zbx_tcp_connect \
	-Wl,--wrap=zbx_tcp_send_ext \
	-Wl,--wrap=zbx_tcp_recv_raw_ext \
	-Wl,--wrap=zbx_tcp_close
endif

web_page_get_LDADD = \
	$(COMMON_LIB_FILES)

web_page_get_LDADD += @AGENT_LIBS@

web_page_get_LDFLAGS = @AGENT_LDFLAGS@ $(web_page_get_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

web_page_get_CFLAGS = $(COMMON_COMPILER_FLAGS)

vfs_file_exists_SOURCES = \
	vfs_file_exists.c \
	$(COMMON_SRC_FILES)

vfs_file_exists_LDADD = \
	$(COMMON_LIB_FILES)

vfs_file_exists_LDADD += @AGENT_LIBS@

vfs_file_exists_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

vfs_file_exists_CFLAGS = $(COMMON_COMPILER_FLAGS) -I$(top_srcdir)/src/libs/zbxsysinfo/common/

zbx_ip_reverse_SOURCES = \
	zbx_ip_reverse.c \
	$(COMMON_SRC_FILES)

zbx_ip_reverse_LDADD = \
	$(COMMON_LIB_FILES)

zbx_ip_reverse_LDADD += @SERVER_LIBS@

zbx_ip_reverse_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ip_reverse_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
