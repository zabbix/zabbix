include ../Makefile.include

if SERVER
SERVER_tests = zbx_poller_test

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

POLLER_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS) \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

zbx_poller_test_SOURCES = \
	zbx_poller_test.c \
	test_get_value_ssh.c \
	test_get_value_telnet.c \
	../../zbxmockexit.c \
	../../zbxmockfile.c \
	../../zbxmocklog.c \
	../../zbxmockdir.c

zbx_poller_test_LDADD = $(POLLER_LIBS)
zbx_poller_test_LDADD += @SERVER_LIBS@
zbx_poller_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=ssh_run \
	-Wl,--wrap=telnet_run

zbx_poller_test_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

if HAVE_SSH
zbx_poller_test_LDADD += $(SSH_LIBS)
zbx_poller_test_LDFLAGS += $(SSH_LDFLAGS)
zbx_poller_test_CFLAGS += $(SSH_CFLAGS)
endif

if HAVE_SSH2
zbx_poller_test_LDADD += $(SSH2_LIBS)
zbx_poller_test_LDFLAGS += $(SSH2_LDFLAGS)
zbx_poller_test_CFLAGS += $(SSH2_CFLAGS)
endif

endif
