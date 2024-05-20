include ../Makefile.common

if SERVER
noinst_PROGRAMS = parse_cfg_file

parse_cfg_file_SOURCES = \
	parse_cfg_file.c \
	../../zbxmocktest.h

CFG_LIBS = \
	$(CFG_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

parse_cfg_file_LDADD = $(CFG_LIBS)

parse_cfg_file_LDFLAGS = \
	@SERVER_LDFLAGS@ \
	$(CMOCKA_LDFLAGS) \
	$(YAML_LDFLAGS)

parse_cfg_file_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)
endif
