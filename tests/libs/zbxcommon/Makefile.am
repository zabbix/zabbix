if SERVER
SERVER_tests = \
	init_library_common
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

COMMON_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(TLS_CFLAGS) \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

init_library_common_SOURCES = \
	init_library_common.c \
	$(COMMON_SRC_FILES)

init_library_common_LDADD = \
	$(COMMON_LIB_FILES)

init_library_common_LDADD += @SERVER_LIBS@

init_library_common_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

init_library_common_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
