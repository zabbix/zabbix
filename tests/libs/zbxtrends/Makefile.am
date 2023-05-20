if SERVER
SERVER_tests = \
	zbx_trends_parse_range \
	zbx_baseline_get_data
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_trends_parse_range_SOURCES = \
	zbx_trends_parse_range.c \
	$(COMMON_SRC_FILES)

zbx_trends_parse_range_LDADD = \
	$(COMMON_LIB_FILES)

zbx_trends_parse_range_LDADD += @SERVER_LIBS@

zbx_trends_parse_range_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=zbx_db_fetch \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_is_null \
	-Wl,--wrap=DBfetch \
	-Wl,--wrap=DBselect \
	-Wl,--wrap=DBis_null \
	-Wl,--wrap=zbx_recalc_time_period

zbx_trends_parse_range_CFLAGS = $(COMMON_COMPILER_FLAGS)

# zbx_baseline_get_data

zbx_baseline_get_data_SOURCES = \
	zbx_baseline_get_data.c \
	$(COMMON_SRC_FILES)

zbx_baseline_get_data_LDADD = \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(COMMON_LIB_FILES)

zbx_baseline_get_data_LDADD += @SERVER_LIBS@

zbx_baseline_get_data_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=zbx_db_fetch \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_is_null \
	-Wl,--wrap=zbx_trends_get_avg \
	-Wl,--wrap=DBfetch \
	-Wl,--wrap=DBselect \
	-Wl,--wrap=DBis_null \
	-Wl,--wrap=zbx_trends_get_avg \
	-Wl,--wrap=zbx_recalc_time_period

zbx_baseline_get_data_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
