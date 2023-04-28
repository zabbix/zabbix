if SERVER
SERVER_tests = \
	line_process \
	get_interval_option
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

ICMPPING_LIBS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	-lz -lssl -lcrypto


line_process_SOURCES = \
	line_process.c \
	../../zbxmocktest.h \
	../../zbxmockexit.c \
	../../zbxmockdir.c

line_process_WRAP_FUNCS = \
	-Wl,--wrap=zbx_fgets

line_process_LDADD = $(ICMPPING_LIBS)
line_process_LDFLAGS = @SERVER_LDFLAGS@

line_process_CFLAGS = \
	-I@top_srcdir@/tests \
	$(line_process_WRAP_FUNCS)

get_interval_option_SOURCES = \
	get_interval_option.c \
	../../zbxmocktest.h \
	../../zbxmockexit.c \
	../../zbxmockdir.c

get_interval_option_WRAP_FUNCS = \
	-Wl,--wrap=zbx_fgets \
	-Wl,--wrap=zbx_execute

get_interval_option_LDADD = $(ICMPPING_LIBS)
get_interval_option_LDFLAGS = @SERVER_LDFLAGS@

get_interval_option_CFLAGS = \
	-I@top_srcdir@/tests \
	$(get_interval_option_WRAP_FUNCS)
endif
