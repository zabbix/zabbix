if SERVER
SERVER_tests = zbx_trapper_preproc_test_run 

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

TRAPPER_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/zabbix_server/preprocessor/libpreprocessor.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxembed/libzbxembed.a \
	$(top_srcdir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_trapper_preproc_test_run_SOURCES = \
	zbx_trapper_preproc_test_run.c \
	../../../src/zabbix_server/trapper/trapper_preproc.c  \
	../../zbxmockjson.c

zbx_trapper_preproc_test_run_LDADD = $(TRAPPER_LIBS)
zbx_trapper_preproc_test_run_LDADD += @SERVER_LIBS@ 
zbx_trapper_preproc_test_run_LDFLAGS = @SERVER_LDFLAGS@

zbx_trapper_preproc_test_run_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ \
	-Wl,--wrap=zbx_preprocessor_test \
	-Wl,--wrap=DBget_user_by_active_session

endif

