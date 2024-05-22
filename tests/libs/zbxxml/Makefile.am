if SERVER
SERVER_tests = \
	zbx_json_to_xml \
	zbx_xml_to_json
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

# zbxxml depends on zbxalgo, zbxjson, zbxvariant, zbxstr and zbxcommon
#
# 1) mockdata needs zbxtime, zbxalgo, zbxstr, zbxnum and zbxcommon
# 2) mocktest needs zbxnix, which also needs zbxlog, zbxnum, zbxthreads, zbxcomms and zbxcommon

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

MOCK_TEST_DEPS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

MUTEX_LIBS = \
        $(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
        $(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

CFG_LIBS = \
        $(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
        $(top_srcdir)/src/libs/zbxip/libzbxip.a \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
        $(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

LOG_LIBS = \
        $(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
        $(MUTEX_LIBS) \
        $(CFG_LIBS) \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

REGEXP_LIBS = \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(LOG_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

VARIANT_LIBS = \
        $(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
        $(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

EXPR_LIBS = \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

JSON_LIBS = \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(REGEXP_LIBS) \
	$(VARIANT_LIBS) \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(EXPR_LIBS) \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

XML_LIBS = \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(JSON_LIBS) \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
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
