include ../Makefile.common

if SERVER
SERVER_tests = \
	queue \
	list
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

ALGO_LIBS = \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

queue_SOURCES = \
	queue.c \
	$(COMMON_SRC_FILES)

queue_LDADD = \
	$(ALGO_LIBS)

queue_LDADD += @SERVER_LIBS@

queue_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

queue_CFLAGS = $(COMMON_COMPILER_FLAGS)


list_SOURCES = \
	list.c \
	$(COMMON_SRC_FILES)

list_LDADD = \
	$(ALGO_LIBS)

list_LDADD += @SERVER_LIBS@

list_LDFLAGS = @SERVER_LDFLAGS@

list_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
