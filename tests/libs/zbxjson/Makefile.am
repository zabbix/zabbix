noinst_PROGRAMS = zbx_json_path_open

zbx_json_path_open_SOURCES = \
	zbx_json_path_open.c \
	../../zbxmocktest.h

zbx_json_path_open_LDADD = \
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
	

#parse_item_key_LDADD += @SERVER_LIBS@

zbx_json_path_open_CFLAGS = -I@top_srcdir@/tests

