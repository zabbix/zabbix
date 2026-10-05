include ../Makefile.include

noinst_PROGRAMS = \
	queue \
	list

COMMON_SRC_FILES = \
	../../zbxmocktest.h

ALGO_LIBS = \
	$(ANY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

queue_SOURCES = \
	queue.c \
	$(COMMON_SRC_FILES)

queue_LDADD = \
	$(ALGO_LIBS)

queue_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

queue_CFLAGS = $(COMMON_COMPILER_FLAGS)


list_SOURCES = \
	list.c \
	$(COMMON_SRC_FILES)

list_LDADD = \
	$(ALGO_LIBS)

list_CFLAGS = $(COMMON_COMPILER_FLAGS)

