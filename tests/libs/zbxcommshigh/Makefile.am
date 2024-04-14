if SERVER
ZLIB_tests = zbx_tcp_recv_ext_zlib
endif

noinst_PROGRAMS = zbx_tcp_recv_ext zbx_tcp_recv_raw_ext $(ZLIB_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

MOCK_TEST_DEPS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

MUTEX_LIBS = \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

CFG_LIBS = \
        $(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
        $(top_srcdir)/src/libs/zbxip/libzbxip.a \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
        $(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

LOG_LIBS = \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(MUTEX_LIBS) \
	$(CFG_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

CRYPTO_LIBS = \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

COMMS_LIBS = \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(CRYPTO_LIBS) \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(LOG_LIBS) \
	$(MUTEX_LIBS) \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

JSON_LIBS = \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(REGEXP_LIBS) \
	$(VARIANT_LIBS) \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(EXPR_LIBS) \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

TIME_LIBS = \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

NIX_LIBS = \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(COMMS_LIBS) \
	$(MUTEX_LIBS) \
	$(LOG_LIBS) \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

# commshigh depends on zbxcomms, zbxcfg, zbxjson, zbxlog, zbxtime, zbxnix and zbxcommon

COMMSHIGH_LIB_FILES = 
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(COMMS_LIB_FILES) \
	$(CFG_LIB_FILES) \
	$(JSON_LIBS) \
	$(LOG_LIBS) \
	$(TIME_LIBS) \
	$(NIX_LIBS) \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)


COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_tcp_recv_ext_SOURCES = \
	zbx_tcp_recv_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_ext_LDADD = \
	$(COMMSHIGH_LIB_FILES)

zbx_tcp_recv_ext_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_ext_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_ext_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)

if SERVER
zbx_tcp_recv_ext_zlib_SOURCES = \
	zbx_tcp_recv_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_ext_zlib_LDADD = \
	$(COMMSHIGH_LIB_FILES)

zbx_tcp_recv_ext_zlib_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_ext_zlib_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_ext_zlib_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)
endif

zbx_tcp_recv_raw_ext_SOURCES = \
	zbx_tcp_recv_raw_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_raw_ext_LDADD = \
	$(COMMSHIGH_LIB_FILES)

zbx_tcp_recv_raw_ext_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_raw_ext_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_raw_ext_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)
