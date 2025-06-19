include ../Makefile.include

if SERVER
SERVER_tests = \
	queue \
	zbx_list \
	zbx_int128 \
	zbx_mode_code \
	zbx_binary_heap \
	zbx_binary_heap_direct \
	zbx_compare_tags_natural \
	zbx_vector
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

#zbx_list

zbx_list_SOURCES = \
	zbx_list.c \
	zbx_algo_common.c \
	$(COMMON_SRC_FILES)

zbx_list_LDADD = \
	$(ALGO_LIBS)

zbx_list_LDADD += @SERVER_LIBS@

zbx_list_LDFLAGS = @SERVER_LDFLAGS@

zbx_list_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_int128

zbx_int128_SOURCES = \
	zbx_int128.c \
	$(COMMON_SRC_FILES)

zbx_int128_LDADD = \
	$(ALGO_LIBS)

zbx_int128_LDADD += @SERVER_LIBS@

zbx_int128_LDFLAGS = @SERVER_LDFLAGS@

zbx_int128_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_mode_code

zbx_mode_code_SOURCES = \
	zbx_mode_code.c \
	$(COMMON_SRC_FILES)

zbx_mode_code_LDADD = \
	$(ALGO_LIBS)

zbx_mode_code_LDADD += @SERVER_LIBS@

zbx_mode_code_LDFLAGS = @SERVER_LDFLAGS@

zbx_mode_code_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_binary_heap

zbx_binary_heap_SOURCES = \
	zbx_binary_heap.c \
	zbx_algo_common.c \
	$(COMMON_SRC_FILES)

zbx_binary_heap_LDADD = \
	$(ALGO_LIBS)

zbx_binary_heap_LDADD += @SERVER_LIBS@

zbx_binary_heap_LDFLAGS = @SERVER_LDFLAGS@

zbx_binary_heap_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_binary_heap_direct

zbx_binary_heap_direct_SOURCES = \
	zbx_binary_heap_direct.c \
	zbx_algo_common.c \
	$(COMMON_SRC_FILES)

zbx_binary_heap_direct_LDADD = \
	$(ALGO_LIBS)

zbx_binary_heap_direct_LDADD += @SERVER_LIBS@

zbx_binary_heap_direct_LDFLAGS = @SERVER_LDFLAGS@

zbx_binary_heap_direct_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_compare_tags_natural

zbx_compare_tags_natural_SOURCES = \
	zbx_compare_tags_natural.c \
	$(COMMON_SRC_FILES)

zbx_compare_tags_natural_LDADD = \
	$(ALGO_LIBS)

zbx_compare_tags_natural_LDADD += @SERVER_LIBS@

zbx_compare_tags_natural_LDFLAGS = @SERVER_LDFLAGS@

zbx_compare_tags_natural_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_vector

zbx_vector_SOURCES = \
	zbx_vector.c \
	$(COMMON_SRC_FILES)

zbx_vector_LDADD = \
	$(ALGO_LIBS)

zbx_vector_LDADD += @SERVER_LIBS@

zbx_vector_LDFLAGS = @SERVER_LDFLAGS@

zbx_vector_CFLAGS = $(COMMON_COMPILER_FLAGS)


endif
