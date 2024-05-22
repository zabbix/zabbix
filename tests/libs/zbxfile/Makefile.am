noinst_PROGRAMS = \
	zbx_buf_readln

# zbxfile depends on zbxcommon

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

FILE_LIBS = \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_buf_readln_SOURCES = \
	zbx_buf_readln.c \
	../../zbxmocktest.h

zbx_buf_readln_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_buf_readln_LDADD = $(FILE_LIBS)
zbx_buf_readln_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_buf_readln_LDADD += @SERVER_LIBS@
zbx_buf_readln_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_buf_readln_LDADD += @PROXY_LIBS@
zbx_buf_readln_LDFLAGS += @PROXY_LDFLAGS@
endif
endif
