if SERVER
noinst_PROGRAMS = zbx_item_preproc

JSON_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxembed/libzbxembed.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_item_preproc_SOURCES = \
	../../../src/zabbix_server/preprocessor/item_preproc.c \
	zbx_item_preproc.c 

zbx_item_preproc_LDADD = $(JSON_LIBS)

zbx_item_preproc_LDADD += @SERVER_LIBS@
zbx_item_preproc_LDFLAGS = @SERVER_LDFLAGS@

zbx_item_preproc_CFLAGS = -I@top_srcdir@/tests @LIBXML2_CFLAGS@
endif
