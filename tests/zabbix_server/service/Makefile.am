include ../../libs/Makefile.include

if SERVER
SERVER_tests = \
	service_get_status \
	service_get_main_status \
	service_get_rule_status \
	service_get_rootcause_eventids


noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

SERVICE_WRAP_FUNCS = \
	-Wl,--wrap=zbx_dc_get_nextid \
	-Wl,--wrap=zbx_add_event \
	-Wl,--wrap=zbx_process_events \
	-Wl,--wrap=zbx_clean_events \
	-Wl,--wrap=zbx_interface_availability_is_set \
	-Wl,--wrap=zbx_rtc_subscribe \
	-Wl,--wrap=zbx_rtc_notify_finished_sync \
	-Wl,--wrap=zbx_dc_set_itservices_num
	
# service_get_rootcause_eventids

service_get_rootcause_eventids_SOURCES = \
	service_get_rootcause_eventids.c \
	mock_service.c \
	mock_service.h

service_get_rootcause_eventids_LDADD = $(COMMON_LIBS)
service_get_rootcause_eventids_LDADD += @SERVER_LIBS@
service_get_rootcause_eventids_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_rootcause_eventids_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_status

service_get_status_SOURCES = \
	service_get_status.c \
	mock_service.c \
	mock_service.h

service_get_status_LDADD = $(COMMON_LIBS)
service_get_status_LDADD += @SERVER_LIBS@
service_get_status_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_status_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_main_status

service_get_main_status_SOURCES = \
	service_get_main_status.c \
	mock_service.c \
	mock_service.h

service_get_main_status_LDADD = $(COMMON_LIBS)
service_get_main_status_LDADD += @SERVER_LIBS@
service_get_main_status_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_main_status_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_rule_status

service_get_rule_status_SOURCES = \
	service_get_rule_status.c \
	mock_service.c \
	mock_service.h

service_get_rule_status_LDADD = $(COMMON_LIBS)
service_get_rule_status_LDADD += @SERVER_LIBS@
service_get_rule_status_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_rule_status_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

endif
