noinst_PROGRAMS = zbx_jsonpath_next

zbx_jsonpath_next_SOURCES = \
	zbx_jsonpath_next.c \
	../../zbxmocktest.h

zbx_jsonpath_next_LDADD = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_jsonpath_next_CFLAGS = -I@top_srcdir@/tests

