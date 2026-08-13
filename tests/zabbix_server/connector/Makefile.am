include ../../libs/Makefile.include

if SERVER
SERVER_tests = connector_manager_test

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

CONNECTOR_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

connector_manager_test_SOURCES = \
	connector_manager_test.c

connector_manager_test_LDADD = $(CONNECTOR_LIBS)
connector_manager_test_LDADD += @SERVER_LIBS@
connector_manager_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=zbx_ipc_service_recv \
	-Wl,--wrap=getppid \
	-Wl,--wrap=zbx_ipc_client_close

connector_manager_test_CFLAGS = \
	-I@top_srcdir@/tests -I@top_srcdir@/src  @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif
