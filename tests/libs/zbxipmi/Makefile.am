include ../Makefile.include

if HAVE_IPMI
BINARIES_tests = \
	ipmi_manager_test

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

IPMI_LIBS = \
	$(ANY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

IPMI_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

ipmi_manager_test_SOURCES = \
	ipmi_manager_test.c \
	$(COMMON_SRC_FILES)

ipmi_manager_test_WRAP_FUNC = \
	-Wl,--wrap=zbx_ipc_service_recv \
	-Wl,--wrap=getppid \
	-Wl,--wrap=zbx_ipc_client_close

ipmi_manager_test_LDADD = \
	$(IPMI_LIBS)

ipmi_manager_test_LDADD += @SERVER_LIBS@

ipmi_manager_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)
ipmi_manager_test_CFLAGS = $(IPMI_COMPILER_FLAGS) \
	$(ipmi_manager_test_WRAP_FUNC)
endif
