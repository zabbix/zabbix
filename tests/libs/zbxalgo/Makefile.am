if SERVER
SERVER_tests = \
	evaluate \
	evaluate_unknown \
	queue
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
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
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

evaluate_SOURCES = \
	evaluate.c \
	$(COMMON_SRC_FILES)

evaluate_LDADD = \
	$(COMMON_LIB_FILES)

evaluate_LDADD += @SERVER_LIBS@

evaluate_LDFLAGS = @SERVER_LDFLAGS@

evaluate_CFLAGS = $(COMMON_COMPILER_FLAGS)


evaluate_unknown_SOURCES = \
	evaluate_unknown.c \
	$(COMMON_SRC_FILES)

evaluate_unknown_LDADD = \
	$(COMMON_LIB_FILES)

evaluate_unknown_LDADD += @SERVER_LIBS@

evaluate_unknown_LDFLAGS = @SERVER_LDFLAGS@

evaluate_unknown_CFLAGS = $(COMMON_COMPILER_FLAGS)


queue_SOURCES = \
	queue.c \
	$(COMMON_SRC_FILES)

queue_LDADD = \
	$(COMMON_LIB_FILES)

queue_LDADD += @SERVER_LIBS@

queue_LDFLAGS = @SERVER_LDFLAGS@

queue_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
