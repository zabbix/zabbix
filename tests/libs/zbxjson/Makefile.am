noinst_PROGRAMS = jsonpath_next zbx_json_path_open

JSON_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
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

jsonpath_next_SOURCES = \
	jsonpath_next.c \
	../../zbxmocktest.h

jsonpath_next_LDADD = $(JSON_LIBS)

if SERVER
jsonpath_next_LDADD += @SERVER_LIBS@
jsonpath_next_LDFLAGS = @SERVER_LDFLAGS@
endif

jsonpath_next_CFLAGS = -I@top_srcdir@/tests

zbx_json_path_open_SOURCES = \
	zbx_json_path_open.c \
	../../zbxmocktest.h

zbx_json_path_open_LDADD = $(JSON_LIBS)

if SERVER
zbx_json_path_open_LDADD += @SERVER_LIBS@
zbx_json_path_open_LDFLAGS = @SERVER_LDFLAGS@
endif

zbx_json_path_open_CFLAGS = -I@top_srcdir@/tests
