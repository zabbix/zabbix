noinst_PROGRAMS =

COMMMON_SOURCES = \
	../../zbxmocktest.h

COMMON_LIBS1 = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a

COMMON_LIBS2 = \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libfunclistsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

if SERVER

noinst_PROGRAMS += server_module_load

server_module_load_SOURCES = \
	generic_module_load.c \
	$(COMMMON_SOURCES)

server_module_load_CFLAGS = \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

server_module_load_LDADD = $(COMMON_LIBS1) \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(COMMON_LIBS2) @SERVER_LIBS@

endif

if PROXY

noinst_PROGRAMS += proxy_module_load

proxy_module_load_SOURCES = \
	generic_module_load.c \
	$(COMMMON_SOURCES)

proxy_module_load_CFLAGS = \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

proxy_module_load_LDADD = $(COMMON_LIBS1) \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxproxysysinfo.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(COMMON_LIBS2) @PROXY_LIBS@

endif

if AGENT

noinst_PROGRAMS += agent_module_load

agent_module_load_SOURCES = \
	generic_module_load.c \
	$(COMMMON_SOURCES)

agent_module_load_CFLAGS = \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

agent_module_load_LDADD = $(COMMON_LIBS1) \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(COMMON_LIBS2) \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	@AGENT_LIBS@

endif
