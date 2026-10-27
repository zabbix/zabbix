include ../Makefile.include

if SERVER
SERVER_tests = zbx_item_preproc
SERVER_tests += item_preproc_csv_to_json

if HAVE_LIBXML2
SERVER_tests +=	item_preproc_xpath
endif

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

PREPROC_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	@SERVER_LIBS@ \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

zbx_item_preproc_SOURCES = \
	zbx_item_preproc.c \
	configcache_mock.c

zbx_item_preproc_LDADD = $(PREPROC_LIBS)

zbx_item_preproc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=zbx_dc_expand_user_and_func_macros_from_cache

zbx_item_preproc_CFLAGS = -I@top_srcdir@/tests -I@top_srcdir@/src @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) \
	$(TLS_CFLAGS)

item_preproc_xpath_SOURCES = \
	item_preproc_xpath.c \
	configcache_mock.c \
	$(COMMON_SRC_FILES)

item_preproc_xpath_LDADD = $(PREPROC_LIBS)

item_preproc_xpath_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLD_LDFLAGS) \
	-Wl,--wrap=zbx_dc_expand_user_and_func_macros_from_cache

item_preproc_xpath_CFLAGS = -I@top_srcdir@/tests -I@top_srcdir@/src @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) \
	$(TLS_CFLAGS)

item_preproc_csv_to_json_SOURCES = \
	item_preproc_csv_to_json.c \
	configcache_mock.c \
	$(COMMON_SRC_FILES)

item_preproc_csv_to_json_LDADD = $(PREPROC_LIBS)

item_preproc_csv_to_json_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=zbx_dc_expand_user_and_func_macros_from_cache

item_preproc_csv_to_json_CFLAGS = -I@top_srcdir@/tests -I@top_srcdir@/src @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) $(TLS_CFLAGS)

endif
