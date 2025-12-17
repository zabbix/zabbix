include ../Makefile.include

BINARIES_tests = \
	init_library_common \
	zbx_malloc \
	zbx_calloc \
	zbx_realloc

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

# zbxcommon MUST not depend on any other zabbix library

COMMON_LIBS = \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)


COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

init_library_common_SOURCES = \
	init_library_common.c \
	$(COMMON_SRC_FILES)

init_library_common_LDADD = $(COMMON_LIBS)

init_library_common_LDADD += @SERVER_LIBS@

init_library_common_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

init_library_common_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_malloc

zbx_malloc_SOURCES = \
	zbx_malloc.c \
	$(COMMON_SRC_FILES)

zbx_malloc_LDADD = $(COMMON_LIBS)

zbx_malloc_LDADD += @SERVER_LIBS@

zbx_malloc_LDFLAGS = @SERVER_LDFLAGS@ \
		-Wl,--wrap=malloc \
		-Wl,--wrap=zbx_log_handle \
		 $(CMOCKA_LDFLAGS)

zbx_malloc_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_calloc

zbx_calloc_SOURCES = \
	zbx_calloc.c \
	$(COMMON_SRC_FILES)

zbx_calloc_LDADD = $(COMMON_LIBS)

zbx_calloc_LDADD += @SERVER_LIBS@

zbx_calloc_LDFLAGS = @SERVER_LDFLAGS@ \
		-Wl,--wrap=calloc \
		-Wl,--wrap=zbx_log_handle \
		 $(CMOCKA_LDFLAGS)

zbx_calloc_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_realloc

zbx_realloc_SOURCES = \
	zbx_realloc.c \
	$(COMMON_SRC_FILES)

zbx_realloc_LDADD = $(COMMON_LIBS)

zbx_realloc_LDADD += @SERVER_LIBS@

zbx_realloc_LDFLAGS = @SERVER_LDFLAGS@ \
		-Wl,--wrap=realloc \
		-Wl,--wrap=zbx_log_handle \
		 $(CMOCKA_LDFLAGS)

zbx_realloc_CFLAGS = $(COMMON_COMPILER_FLAGS)
