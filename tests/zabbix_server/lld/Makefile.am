include ../../libs/Makefile.include

if SERVER
SERVER_tests = zbx_lld_hgsets_test

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

LLD_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_lld_hgsets_test_SOURCES = \
	../../../src/zabbix_server/lld/lld_common.c \
	../../../src/zabbix_server/lld/lld_graph.c \
	../../../src/zabbix_server/lld/lld_audit.c \
	../../../src/zabbix_server/lld/lld_item.c \
	../../../src/zabbix_server/lld/lld_item_dep.c \
	../../../src/zabbix_server/lld/lld_trigger.c \
	../../../src/zabbix_server/lld/lld.c \
	zbx_lld_hgsets_test.c \
	../../zbxmockexit.c \
	../../zbxmockdb.c \
	../../zbxmockdata.c \
	../../zbxmocklog.c \
	../../zbxmockfile.c \
	../../zbxmockdir.c

zbx_lld_hgsets_test_LDADD = $(LLD_LIBS)
zbx_lld_hgsets_test_LDADD += @SERVER_LIBS@
zbx_lld_hgsets_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_lld_hgsets_test_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif
