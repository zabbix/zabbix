include ../Makefile.include

if SERVER
noinst_PROGRAMS = zbx_history_get_values

HISTORY_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_history_get_values_SOURCES = \
	zbx_history_get_values.c

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
endif
