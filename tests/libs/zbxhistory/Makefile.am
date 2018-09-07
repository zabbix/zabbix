if SERVER
noinst_PROGRAMS = zbx_history_get_values

HISTORY_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_history_get_values_SOURCES = \
	zbx_history_get_values.c

zbx_history_get_values_WRAP = \
	-Wl,--wrap=zbx_sleep_loop \
	-Wl,--wrap=DCget_nextid \
	-Wl,--wrap=zbx_host_availability_is_set \
	-Wl,--wrap=zbx_add_event \
	-Wl,--wrap=zbx_process_events \
	-Wl,--wrap=zbx_clean_events

zbx_history_get_values_LDADD = $(HISTORY_LIBS) @SERVER_LIBS@

zbx_history_get_values_LDFLAGS = @SERVER_LDFLAGS@

zbx_history_get_values_CFLAGS = \
	$(zbx_history_get_values_WRAP) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/tests 
endif
