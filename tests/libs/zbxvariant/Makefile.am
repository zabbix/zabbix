include ../Makefile.common

if SERVER
SERVER_tests = \
	zbx_variant_compare
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

# zbxvariant depends on zbxalgo, zbxstr, zbxnum and zbxcommon

VARIANT_LIBS = \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

VARIANT_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

zbx_variant_compare_SOURCES = \
	zbx_variant_compare.c \
	$(COMMON_SRC_FILES)

zbx_variant_compare_LDADD = \
	$(VARIANT_LIBS)

zbx_variant_compare_LDADD += @SERVER_LIBS@

zbx_variant_compare_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_variant_compare_CFLAGS = $(VARIANT_COMPILER_FLAGS)

endif
