include ../../libs/Makefile.include

if SERVER
SERVER_tests = zbx_lld_hgsets_test \
		lld_entry_create \
		lld_compare_entries \
		zbx_substitute_lld_macros \
		zbx_substitute_macros_in_json_pairs \
		zbx_substitute_expression_lld_macros

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

LLD_WRAP_FUNCS = \
	-Wl,--wrap=zbx_preprocess_item_value \
	-Wl,--wrap=zbx_preprocessor_flush

LLD_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

# zbx_lld_hgsets_test

zbx_lld_hgsets_test_SOURCES = \
	zbx_lld_hgsets_test.c \
	mock_lld.c

zbx_lld_hgsets_test_LDADD = $(LLD_LIBS)
zbx_lld_hgsets_test_LDADD += @SERVER_LIBS@
zbx_lld_hgsets_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(LLD_WRAP_FUNCS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_lld_hgsets_test_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

# lld_entry_create

lld_entry_create_SOURCES = \
	lld_entry_create.c \
	mock_lld.c

lld_entry_create_LDADD = $(LLD_LIBS)
lld_entry_create_LDADD += @SERVER_LIBS@
lld_entry_create_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) $(LLD_WRAP_FUNCS)

lld_entry_create_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

# lld_compare_entries

lld_compare_entries_SOURCES = \
	lld_compare_entries.c \
	mock_lld.c

lld_compare_entries_LDADD = $(LLD_LIBS)
lld_compare_entries_LDADD += @SERVER_LIBS@
lld_compare_entries_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) $(LLD_WRAP_FUNCS)

lld_compare_entries_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

# zbx_substitute_lld_macro

zbx_substitute_lld_macros_SOURCES = \
	zbx_substitute_lld_macros.c \
	lld_common.c \
	mock_lld.c

zbx_substitute_lld_macros_LDADD = $(LLD_LIBS)
zbx_substitute_lld_macros_LDADD += @SERVER_LIBS@
zbx_substitute_lld_macros_LDFLAGS = @SERVER_LDFLAGS@ $(LLD_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_substitute_lld_macros_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

# zbx_substitute_macros_in_json_pairs

zbx_substitute_macros_in_json_pairs_SOURCES = \
	zbx_substitute_macros_in_json_pairs.c \
	lld_common.c \
	mock_lld.c

zbx_substitute_macros_in_json_pairs_LDADD = $(LLD_LIBS)
zbx_substitute_macros_in_json_pairs_LDADD += @SERVER_LIBS@
zbx_substitute_macros_in_json_pairs_LDFLAGS = @SERVER_LDFLAGS@ $(LLD_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_substitute_macros_in_json_pairs_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

# zbx_substitute_expression_lld_macros

zbx_substitute_expression_lld_macros_SOURCES = \
	zbx_substitute_expression_lld_macros.c \
	lld_common.c \
	mock_lld.c

zbx_substitute_expression_lld_macros_LDADD = $(LLD_LIBS)
zbx_substitute_expression_lld_macros_LDADD += @SERVER_LIBS@
zbx_substitute_expression_lld_macros_LDFLAGS = @SERVER_LDFLAGS@ $(LLD_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_substitute_expression_lld_macros_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

endif
