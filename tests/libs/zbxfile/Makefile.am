noinst_PROGRAMS = \
	zbx_buf_readln

COMMON_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

zbx_buf_readln_SOURCES = \
	zbx_buf_readln.c \
	../../zbxmocktest.h

zbx_buf_readln_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_buf_readln_LDADD = $(COMMON_LIBS)
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
