include ../Makefile.common

if SERVER
noinst_PROGRAMS = tagfilter_match_tags

tagfilter_match_tags_SOURCES = \
	tagfilter_match_tags.c \
	../../zbxmocktest.h

TAGFILTER_LIBS = \
	$(TAGFILTER_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

tagfilter_match_tags_LDADD = $(TAGFILTER_LIBS) @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS)

tagfilter_match_tags_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

tagfilter_match_tags_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
endif
