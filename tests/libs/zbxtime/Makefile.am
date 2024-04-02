BINARIES_tests = \
	zbx_iso8601_utc \
	zbx_get_week_number \
	zbx_tm_add \
	zbx_tm_round_down \
	zbx_tm_round_up \
	zbx_tm_sub

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

# 1) zbxtime depends on zbxnum and zbxcommon
# 2) zbxcommon currently needs zbxnix, which also needs zbxlog, zbxnum, zbxthreads, zbxcomms and zbxcommon
#
# 1) mockdata needs zbxtime, zbxalgo, zbxstr, zbxnum and zbxcommon
# 2) mocktest needs zbxnix, which also needs zbxlog, zbxnum, zbxthreads, zbxcomms and zbxcommon

COMMON_LIB_DEPS = \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(COMMON_LIB_DEPS) \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

MOCK_TEST_DEPS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(COMMON_LIB_DEPS)

TIME_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(COMMON_LIB_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

TIME_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS)

zbx_iso8601_utc_SOURCES = \
	zbx_iso8601_utc.c \
	$(COMMON_SRC_FILES)

zbx_iso8601_utc_LDADD = \
	$(TIME_LIB_FILES)

zbx_iso8601_utc_LDADD += @SERVER_LIBS@

zbx_iso8601_utc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_iso8601_utc_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_get_week_number_SOURCES = \
	zbx_get_week_number.c \
	$(COMMON_SRC_FILES)

zbx_get_week_number_LDADD = \
	$(TIME_LIB_FILES)

zbx_get_week_number_LDADD += @SERVER_LIBS@

zbx_get_week_number_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_get_week_number_CFLAGS = $(TIME_COMPILER_FLAGS)


zbx_tm_add_SOURCES = \
	zbx_tm_add.c \
	$(COMMON_SRC_FILES)

zbx_tm_add_LDADD = \
	$(TIME_LIB_FILES)

zbx_tm_add_LDADD += @SERVER_LIBS@

zbx_tm_add_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_tm_add_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_tm_round_down_SOURCES = \
	zbx_tm_round_down.c \
	$(COMMON_SRC_FILES)

zbx_tm_round_down_LDADD = \
	$(TIME_LIB_FILES)

zbx_tm_round_down_LDADD += @SERVER_LIBS@

zbx_tm_round_down_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_tm_round_down_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_tm_round_up_SOURCES = \
	zbx_tm_round_up.c \
	$(COMMON_SRC_FILES)

zbx_tm_round_up_LDADD = \
	$(TIME_LIB_FILES)

zbx_tm_round_up_LDADD += @SERVER_LIBS@

zbx_tm_round_up_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_tm_round_up_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_tm_sub_SOURCES = \
	zbx_tm_sub.c \
	$(COMMON_SRC_FILES)

zbx_tm_sub_LDADD = \
	$(TIME_LIB_FILES)

zbx_tm_sub_LDADD += @SERVER_LIBS@

zbx_tm_sub_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_tm_sub_CFLAGS = $(COMMON_COMPILER_FLAGS)
