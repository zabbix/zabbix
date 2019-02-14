noinst_PROGRAMS = zbx_prometheus_getmetric zbx_prometheus_tojson

PROMETHEUS_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_prometheus_getmetric_SOURCES = \
	zbx_prometheus_getmetric.c \
	../../zbxmocktest.h

zbx_prometheus_getmetric_LDADD = $(PROMETHEUS_LIBS)

if SERVER
zbx_prometheus_getmetric_LDADD += @SERVER_LIBS@
zbx_prometheus_getmetric_LDFLAGS = @SERVER_LDFLAGS@
endif

zbx_prometheus_getmetric_CFLAGS = -I@top_srcdir@/tests

zbx_prometheus_tojson_SOURCES = \
	zbx_prometheus_tojson.c \
	../../zbxmocktest.h

zbx_prometheus_tojson_LDADD = $(PROMETHEUS_LIBS)

if SERVER
zbx_prometheus_tojson_LDADD += @SERVER_LIBS@
zbx_prometheus_tojson_LDFLAGS = @SERVER_LDFLAGS@
endif

zbx_prometheus_tojson_CFLAGS = -I@top_srcdir@/tests
