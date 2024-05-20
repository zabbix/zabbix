include ../Makefile.common

BINARIES_tests = \
	zbx_function_find \
	parse_key \
	calculate_item_nextcheck \
	calculate_item_nextcheck_unreachable \
	zbx_function_get_param_dyn \
	zbx_token_find \
	zbx_interval_preproc \
	zbx_validate_interval \
	is_double_suffix \
	zbx_user_macro_parse \
	zbx_get_report_nextcheck \
	zbx_function_param_parse_ext

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

EXPR_LIBS = \
	$(EXPR_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

EXPR_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

zbx_function_find_SOURCES = \
	zbx_function_find.c \
	$(COMMON_SRC_FILES)

zbx_function_find_LDADD = \
	$(EXPR_LIBS)

zbx_function_find_LDADD += @SERVER_LIBS@

zbx_function_find_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_function_find_CFLAGS = $(EXPR_COMPILER_FLAGS)


parse_key_SOURCES = \
	parse_key.c \
	$(COMMON_SRC_FILES)

parse_key_LDADD = \
	$(EXPR_LIBS)

parse_key_LDADD += @SERVER_LIBS@

parse_key_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

parse_key_CFLAGS = $(EXPR_COMPILER_FLAGS)


calculate_item_nextcheck_SOURCES = \
	calculate_item_nextcheck.c \
	$(COMMON_SRC_FILES)

calculate_item_nextcheck_LDADD = \
	$(EXPR_LIBS)

calculate_item_nextcheck_LDADD += @SERVER_LIBS@

calculate_item_nextcheck_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

calculate_item_nextcheck_CFLAGS = $(EXPR_COMPILER_FLAGS)

calculate_item_nextcheck_unreachable_SOURCES = \
	calculate_item_nextcheck_unreachable.c \
	$(COMMON_SRC_FILES)

calculate_item_nextcheck_unreachable_LDADD = \
	$(EXPR_LIBS)

calculate_item_nextcheck_unreachable_LDADD += @SERVER_LIBS@

calculate_item_nextcheck_unreachable_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

calculate_item_nextcheck_unreachable_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_function_get_param_dyn_SOURCES = \
	zbx_function_get_param_dyn.c \
	$(COMMON_SRC_FILES)

zbx_function_get_param_dyn_LDADD = \
	$(EXPR_LIBS)

zbx_function_get_param_dyn_LDADD += @SERVER_LIBS@

zbx_function_get_param_dyn_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_function_get_param_dyn_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_token_find_SOURCES = \
	zbx_token_find.c \
	$(COMMON_SRC_FILES)

zbx_token_find_LDADD = \
	$(EXPR_LIBS)

zbx_token_find_LDADD += @SERVER_LIBS@

zbx_token_find_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_token_find_CFLAGS = $(EXPR_COMPILER_FLAGS)

zbx_interval_preproc_SOURCES = \
	zbx_interval_preproc.c \
	$(COMMON_SRC_FILES)

zbx_interval_preproc_LDADD = \
	$(EXPR_LIBS)

zbx_interval_preproc_LDADD += @SERVER_LIBS@

zbx_interval_preproc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_interval_preproc_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_validate_interval_SOURCES = \
	zbx_validate_interval.c \
	$(COMMON_SRC_FILES)

zbx_validate_interval_LDADD = \
	$(EXPR_LIBS)

zbx_validate_interval_LDADD += @SERVER_LIBS@

zbx_validate_interval_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_validate_interval_CFLAGS = $(EXPR_COMPILER_FLAGS)


is_double_suffix_SOURCES = \
	is_double_suffix.c \
	$(COMMON_SRC_FILES)

is_double_suffix_LDADD = \
	$(EXPR_LIBS)

is_double_suffix_LDADD += @SERVER_LIBS@

is_double_suffix_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

is_double_suffix_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_user_macro_parse_SOURCES = \
	zbx_user_macro_parse.c \
	$(COMMON_SRC_FILES)

zbx_user_macro_parse_LDADD = \
	$(EXPR_LIBS)

zbx_user_macro_parse_LDADD += @SERVER_LIBS@

zbx_user_macro_parse_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_user_macro_parse_CFLAGS = $(EXPR_COMPILER_FLAGS)


# zbx_get_report_nextcheck

zbx_get_report_nextcheck_SOURCES = \
	zbx_get_report_nextcheck.c \
	$(COMMON_SRC_FILES)

zbx_get_report_nextcheck_LDADD = \
	$(EXPR_LIBS)

zbx_get_report_nextcheck_LDADD += @SERVER_LIBS@

zbx_get_report_nextcheck_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_get_report_nextcheck_CFLAGS = $(EXPR_COMPILER_FLAGS)


zbx_function_param_parse_ext_SOURCES = \
	zbx_function_param_parse_ext.c \
	$(COMMON_SRC_FILES)

zbx_function_param_parse_ext_LDADD = \
	$(EXPR_LIBS)

zbx_function_param_parse_ext_LDADD += @SERVER_LIBS@

zbx_function_param_parse_ext_LDFLAGS = @SERVER_LDFLAGS@

zbx_function_param_parse_ext_CFLAGS = $(EXPR_COMPILER_FLAGS)
