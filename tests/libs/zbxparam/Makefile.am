BINARIES_tests = \
	replace_key_params_dyn

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

# zbxparam depends on zbxexpr, zbxnum, zbxstr and zbxcommon
#
# 1) mockdata needs zbxtime, zbxalgo, zbxstr, zbxnum and zbxcommon
# 2) mocktest needs zbxnix, which also needs zbxlog, zbxnum, zbxthreads, zbxcomms and zbxcommon

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
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

PARAM_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

PARAM_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

replace_key_params_dyn_SOURCES = \
	replace_key_params_dyn.c \
	$(COMMON_SRC_FILES)

replace_key_params_dyn_LDADD = \
	$(PARAM_LIB_FILES)

replace_key_params_dyn_LDADD += @SERVER_LIBS@

replace_key_params_dyn_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

replace_key_params_dyn_CFLAGS = $(PARAM_COMPILER_FLAGS)
