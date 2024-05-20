include ../Makefile.common

noinst_PROGRAMS = \
	zbx_determine_charset

HTTP_LIBS = \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(DBHIGH_DEPS) \
	$(EXPR_DEPS) \
	$(LOG_DEPS) \
	$(TIME_DEPS) \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_determine_charset_SOURCES = \
	zbx_determine_charset.c \
	../../zbxmocktest.h

zbx_determine_charset_LDADD = $(HTTP_LIBS)
zbx_determine_charset_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

if SERVER
zbx_determine_charset_LDADD += @SERVER_LIBS@
zbx_determine_charset_LDFLAGS += @SERVER_LDFLAGS@
else
if PROXY
zbx_determine_charset_LDADD += @PROXY_LIBS@
zbx_determine_charset_LDFLAGS += @PROXY_LDFLAGS@
endif
endif

zbx_determine_charset_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
