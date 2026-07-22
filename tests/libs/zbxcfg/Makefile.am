include ../Makefile.include

if SERVER
noinst_PROGRAMS = parse_cfg_file zbx_parse_item_types

parse_cfg_file_SOURCES = \
	parse_cfg_file.c \
	../../zbxmocktest.h

zbx_parse_item_types_SOURCES = \
	zbx_parse_item_types.c \
	../../zbxmocktest.h

CFG_LIBS = \
	$(CFG_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

parse_cfg_file_LDADD = $(CFG_LIBS)
zbx_parse_item_types_LDADD = $(CFG_LIBS)

parse_cfg_file_LDFLAGS = \
	@SERVER_LDFLAGS@ \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

zbx_parse_item_types_LDFLAGS = \
	@SERVER_LDFLAGS@ \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

parse_cfg_file_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

zbx_parse_item_types_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)
endif
