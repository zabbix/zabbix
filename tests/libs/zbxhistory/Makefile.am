if SERVER
if HAVE_LIBCURL
noinst_PROGRAMS = \
	zbx_history_get_values \
	history_clickhouse_parse_response \
	history_provider_parse_options
	

HISTORY_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxresolver/libzbxresolver.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_history_get_values_SOURCES = \
	zbx_history_get_values.c \
	mock_history.c \
	mock_history.h

zbx_history_get_values_WRAP = \
	-Wl,--wrap=zbx_sleep_loop \
	-Wl,--wrap=zbx_dc_get_nextid \
	-Wl,--wrap=zbx_interface_availability_is_set \
	-Wl,--wrap=zbx_add_event \
	-Wl,--wrap=zbx_process_events \
	-Wl,--wrap=zbx_clean_events \
	-Wl,--wrap=zbx_recalc_time_period

zbx_history_get_values_LDADD = $(HISTORY_LIBS) @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS)

zbx_history_get_values_LDFLAGS = @SERVER_LDFLAGS@ \
	$(zbx_history_get_values_WRAP) \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

zbx_history_get_values_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)
	
	
history_clickhouse_parse_response_SOURCES = \
	history_clickhouse_parse_response.c \
	$(top_srcdir)/src/libs/zbxhistory/history_option.c \
	$(top_srcdir)/src/libs/zbxhistory/history_option.h \
	mock_history.c \
	mock_history.h

history_clickhouse_parse_response_WRAP = \
	-Wl,--wrap=zbx_sleep_loop \
	-Wl,--wrap=zbx_dc_get_nextid \
	-Wl,--wrap=zbx_interface_availability_is_set \
	-Wl,--wrap=zbx_add_event \
	-Wl,--wrap=zbx_process_events \
	-Wl,--wrap=zbx_clean_events \
	-Wl,--wrap=zbx_recalc_time_period

history_clickhouse_parse_response_LDADD = $(HISTORY_LIBS) \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a \
	@SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS)

history_clickhouse_parse_response_LDFLAGS = @SERVER_LDFLAGS@ \
	$(history_clickhouse_parse_response_WRAP) \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

history_clickhouse_parse_response_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)	
	
history_provider_parse_options_SOURCES = \
	history_provider_parse_options.c \
	$(top_srcdir)/src/libs/zbxhistory/history_option.c \
	$(top_srcdir)/src/libs/zbxhistory/history_option.h \
	mock_history.c \
	mock_history.h

history_provider_parse_options_WRAP = \
	-Wl,--wrap=zbx_sleep_loop \
	-Wl,--wrap=zbx_dc_get_nextid \
	-Wl,--wrap=zbx_interface_availability_is_set \
	-Wl,--wrap=zbx_add_event \
	-Wl,--wrap=zbx_process_events \
	-Wl,--wrap=zbx_clean_events \
	-Wl,--wrap=zbx_recalc_time_period

history_provider_parse_options_LDADD = $(HISTORY_LIBS) \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a \
	@SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS)

history_provider_parse_options_LDFLAGS = @SERVER_LDFLAGS@ \
	$(history_provider_parse_options_WRAP) \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

history_provider_parse_options_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)		
	
endif
endif