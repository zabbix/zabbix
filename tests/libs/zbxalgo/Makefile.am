if SERVER
SERVER_tests = \
	evaluate \
	evaluate_unknown \
	queue \
	list
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxexpression/libzbxexpression.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

evaluate_SOURCES = \
	evaluate.c \
	$(COMMON_SRC_FILES)

evaluate_LDADD = \
	$(COMMON_LIB_FILES) $(TLS_LIBS)

evaluate_LDADD += @SERVER_LIBS@

evaluate_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

evaluate_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)


evaluate_unknown_SOURCES = \
	evaluate_unknown.c \
	$(COMMON_SRC_FILES)

evaluate_unknown_LDADD = \
	$(COMMON_LIB_FILES) $(TLS_LIBS)

evaluate_unknown_LDADD += @SERVER_LIBS@

evaluate_unknown_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

evaluate_unknown_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)


queue_SOURCES = \
	queue.c \
	$(COMMON_SRC_FILES)

queue_LDADD = \
	$(COMMON_LIB_FILES)

queue_LDADD += @SERVER_LIBS@

queue_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

queue_CFLAGS = $(COMMON_COMPILER_FLAGS)


list_SOURCES = \
	list.c \
	$(COMMON_SRC_FILES)

list_LDADD = \
	$(COMMON_LIB_FILES)

list_LDADD += @SERVER_LIBS@

list_LDFLAGS = @SERVER_LDFLAGS@

list_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
