include ../Makefile.common

if SERVER
SERVER_tests = \
	zbx_json_to_xml \
	zbx_xml_to_json
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

XML_LIBS = \
	$(XML_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

XML_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

zbx_json_to_xml_SOURCES = \
	zbx_json_to_xml.c \
	$(COMMON_SRC_FILES)

zbx_json_to_xml_LDADD = \
	$(XML_LIBS)

zbx_json_to_xml_LDADD += @SERVER_LIBS@

zbx_json_to_xml_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_json_to_xml_CFLAGS = $(XML_COMPILER_FLAGS) @LIBXML2_CFLAGS@

zbx_xml_to_json_SOURCES = \
	zbx_xml_to_json.c \
	$(COMMON_SRC_FILES)

zbx_xml_to_json_LDADD = \
	$(XML_LIBS)

zbx_xml_to_json_LDADD += @SERVER_LIBS@

zbx_xml_to_json_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_xml_to_json_CFLAGS = $(XML_COMPILER_FLAGS) @LIBXML2_CFLAGS@

endif
