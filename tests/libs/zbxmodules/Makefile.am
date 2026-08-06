include ../Makefile.include

noinst_PROGRAMS =

COMMMON_SOURCES = \
	../../zbxmocktest.h


if SERVER

noinst_PROGRAMS += server_module_load

server_module_load_SOURCES = \
	generic_module_load.c \
	$(COMMMON_SOURCES)

server_module_load_CFLAGS = \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

server_module_load_LDADD = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS) \
	@SERVER_LIBS@ \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

server_module_load_LDFLAGS = @SERVER_LDFLAGS@

endif

if PROXY

noinst_PROGRAMS += proxy_module_load

proxy_module_load_SOURCES = \
	generic_module_load.c \
	$(COMMMON_SOURCES)

proxy_module_load_CFLAGS = \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

proxy_module_load_LDADD = \
	$(PROXY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS) \
	@PROXY_LIBS@ \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

proxy_module_load_LDFLAGS = @PROXY_LDFLAGS@

endif

if AGENT

noinst_PROGRAMS += agent_module_load

agent_module_load_SOURCES = \
	generic_module_load.c \
	$(COMMMON_SOURCES)

agent_module_load_CFLAGS = \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

agent_module_load_LDADD = \
	$(AGENT_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS) \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS) \
	@AGENT_LIBS@

agent_module_load_LDFLAGS = @AGENT_LDFLAGS@

endif
