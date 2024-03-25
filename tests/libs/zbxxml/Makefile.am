if SERVER
SERVER_tests = \
	zbx_json_to_xml \
	zbx_xml_to_json
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

COMMON_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(TLS_CFLAGS) \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

zbx_json_to_xml_SOURCES = \
	zbx_json_to_xml.c \
	$(COMMON_SRC_FILES)

zbx_json_to_xml_LDADD = \
	$(COMMON_LIB_FILES)

zbx_json_to_xml_LDADD += @SERVER_LIBS@

zbx_json_to_xml_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_json_to_xml_CFLAGS = $(COMMON_COMPILER_FLAGS) @LIBXML2_CFLAGS@

zbx_xml_to_json_SOURCES = \
	zbx_xml_to_json.c \
	$(COMMON_SRC_FILES)

zbx_xml_to_json_LDADD = \
	$(COMMON_LIB_FILES)

zbx_xml_to_json_LDADD += @SERVER_LIBS@

zbx_xml_to_json_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_xml_to_json_CFLAGS = $(COMMON_COMPILER_FLAGS) @LIBXML2_CFLAGS@

endif
