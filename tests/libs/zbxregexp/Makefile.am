include ../Makefile.common

if SERVER
noinst_PROGRAMS = wildcard_match

wildcard_match_SOURCES = \
	wildcard_match.c \
	../../zbxmocktest.h

REGEXP_LIBS = \
	$(REGEXP_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

wildcard_match_LDADD = $(REGEXP_LIBS)

wildcard_match_LDADD += @SERVER_LIBS@

wildcard_match_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

wildcard_match_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
endif
