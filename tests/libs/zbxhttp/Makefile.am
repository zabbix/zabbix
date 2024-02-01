noinst_PROGRAMS = \
	zbx_determine_charset

JSON_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_determine_charset_SOURCES = \
	zbx_determine_charset.c \
	../../zbxmocktest.h

zbx_determine_charset_LDADD = $(JSON_LIBS)

if SERVER
zbx_determine_charset_LDADD += @SERVER_LIBS@
zbx_determine_charset_LDFLAGS = @SERVER_LDFLAGS@
else
if PROXY
zbx_determine_charset_LDADD += @PROXY_LIBS@
zbx_determine_charset_LDFLAGS = @PROXY_LDFLAGS@
endif
endif

zbx_determine_charset_CFLAGS = -I@top_srcdir@/tests
