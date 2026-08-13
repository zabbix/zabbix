include ../../libs/Makefile.include

if SERVER
SERVER_tests = report_manager_test

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

REPORTER_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

report_manager_test_SOURCES = \
	report_manager_test.c

report_manager_test_LDADD = $(REPORTER_LIBS)
report_manager_test_LDADD += @SERVER_LIBS@
report_manager_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=zbx_ipc_service_recv \
	-Wl,--wrap=getppid \
	-Wl,--wrap=zbx_ipc_client_close \
	-Wl,--wrap=zbx_ipc_service_start

report_manager_test_CFLAGS = \
	-I@top_srcdir@/tests -I@top_srcdir@/src  @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif
