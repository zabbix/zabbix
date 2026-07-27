include ../Makefile.include

if SERVER
noinst_PROGRAMS = \
	http_process_variables

HTTPPOLLER_LIBS = \
	$(XML_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

http_process_variables_SOURCES = \
	http_process_variables.c \
	$(top_srcdir)/src/libs/zbxhttppoller/httpmacro.c \
	../../zbxmocktest.h

http_process_variables_LDADD = $(HTTPPOLLER_LIBS)
http_process_variables_LDADD += @SERVER_LIBS@

http_process_variables_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

http_process_variables_CFLAGS = \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/libs/zbxhttppoller \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(LIBXML2_CFLAGS)
endif
