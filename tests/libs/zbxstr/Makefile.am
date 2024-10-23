include ../Makefile.include

BINARIES_tests = \
	zbx_str_extract \
	convert_to_utf8 \
	zbx_truncate_itemkey \
	zbx_truncate_value \
	zbx_dyn_escape_string \
	zbx_ltrim_utf8 \
	zbx_rtrim_utf8 \
	zbx_is_ascii_string\
	zbx_remove_chars \
	zbx_del_zeros \
	zbx_str_n_in_list \
	zbx_get_escape_string_len \
	zbx_str_in_list \
	zbx_strsplit_first \
	zbx_strsplit_last \
	zbx_strlcpy_utf8 \
	zbx_utf8_char_len \
	zbx_strlen_utf8_nchars \
	zbx_charcount_utf8_nbytes \
	zbx_is_utf8 \
	zbx_replace_invalid_utf8 \
	zbx_trim_str_list \
	zbx_strcmp_null \
	zbx_strcmp_natural \
	zbx_replace_mem_dyn \
	zbx_substr \
	zbx_replace_string \
	zbx_str_printable_dyn \
	zbx_str_linefeed \
	zbx_strlcat \
	zbx_escape_string \
	zbx_strdcatf \
	zbx_strcasestr \
	zbx_dyn_escape_shell_single_quote

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

STR_LIBS = \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

STR_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

zbx_str_extract_SOURCES = \
	zbx_str_extract.c \
	$(COMMON_SRC_FILES)

zbx_str_extract_LDADD = \
	$(STR_LIBS)

zbx_str_extract_LDADD += @SERVER_LIBS@

zbx_str_extract_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_str_extract_CFLAGS = $(STR_COMPILER_FLAGS)

convert_to_utf8_SOURCES = \
	convert_to_utf8.c \
	$(COMMON_SRC_FILES)

convert_to_utf8_LDADD = \
	$(STR_LIBS)

convert_to_utf8_LDADD += @SERVER_LIBS@

convert_to_utf8_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

convert_to_utf8_CFLAGS = $(STR_COMPILER_FLAGS)


zbx_truncate_itemkey_SOURCES = \
	zbx_truncate_itemkey.c \
	$(COMMON_SRC_FILES)

zbx_truncate_itemkey_LDADD = \
	$(STR_LIBS)

zbx_truncate_itemkey_LDADD += @SERVER_LIBS@

zbx_truncate_itemkey_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_truncate_itemkey_CFLAGS = $(STR_COMPILER_FLAGS)


zbx_truncate_value_SOURCES = \
	zbx_truncate_value.c \
	$(COMMON_SRC_FILES)

zbx_truncate_value_LDADD = \
	$(STR_LIBS)

zbx_truncate_value_LDADD += @SERVER_LIBS@

zbx_truncate_value_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_truncate_value_CFLAGS = $(STR_COMPILER_FLAGS)


zbx_dyn_escape_string_SOURCES = \
	zbx_dyn_escape_string.c \
	$(COMMON_SRC_FILES)

zbx_dyn_escape_string_LDADD = \
	$(STR_LIBS)

zbx_dyn_escape_string_LDADD += @SERVER_LIBS@

zbx_dyn_escape_string_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_dyn_escape_string_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_ltrim_utf8

zbx_ltrim_utf8_SOURCES = \
	zbx_common_trim_utf8.c \
	zbx_ltrim_utf8.c \
	$(COMMON_SRC_FILES)

zbx_ltrim_utf8_LDADD = \
	$(STR_LIBS)

zbx_ltrim_utf8_LDADD += @SERVER_LIBS@

zbx_ltrim_utf8_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_ltrim_utf8_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_ltrim_utf8

zbx_rtrim_utf8_SOURCES = \
	zbx_common_trim_utf8.c \
	zbx_rtrim_utf8.c \
	$(COMMON_SRC_FILES)

zbx_rtrim_utf8_LDADD = \
	$(STR_LIBS)

zbx_rtrim_utf8_LDADD += @SERVER_LIBS@

zbx_rtrim_utf8_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_rtrim_utf8_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_is_ascii_string

zbx_is_ascii_string_SOURCES = \
	zbx_is_ascii_string.c \
	$(COMMON_SRC_FILES)

zbx_is_ascii_string_LDADD = \
	$(STR_LIBS)

zbx_is_ascii_string_LDADD += @SERVER_LIBS@

zbx_is_ascii_string_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_is_ascii_string_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_remove_chars

zbx_remove_chars_SOURCES = \
	zbx_remove_chars.c \
	$(COMMON_SRC_FILES)

zbx_remove_chars_LDADD = \
	$(STR_LIBS)

zbx_remove_chars_LDADD += @SERVER_LIBS@

zbx_remove_chars_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_remove_chars_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_del_zeros

zbx_del_zeros_SOURCES = \
	zbx_del_zeros.c \
	$(COMMON_SRC_FILES)

zbx_del_zeros_LDADD = \
	$(STR_LIBS)

zbx_del_zeros_LDADD += @SERVER_LIBS@

zbx_del_zeros_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_del_zeros_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_str_n_in_list

zbx_str_n_in_list_SOURCES = \
	zbx_str_n_in_list.c \
	$(COMMON_SRC_FILES)

zbx_str_n_in_list_LDADD = \
	$(STR_LIBS)

zbx_str_n_in_list_LDADD += @SERVER_LIBS@

zbx_str_n_in_list_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_str_n_in_list_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_get_escape_string_len

zbx_get_escape_string_len_SOURCES = \
	zbx_get_escape_string_len.c \
	$(COMMON_SRC_FILES)

zbx_get_escape_string_len_LDADD = \
	$(STR_LIBS)

zbx_get_escape_string_len_LDADD += @SERVER_LIBS@

zbx_get_escape_string_len_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_get_escape_string_len_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_str_in_list

zbx_str_in_list_SOURCES = \
	zbx_str_in_list.c \
	$(COMMON_SRC_FILES)

zbx_str_in_list_LDADD = \
	$(STR_LIBS)

zbx_str_in_list_LDADD += @SERVER_LIBS@

zbx_str_in_list_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_str_in_list_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_strsplit_first

zbx_strsplit_first_SOURCES = \
	zbx_strsplit_first.c \
	$(COMMON_SRC_FILES)

zbx_strsplit_first_LDADD = \
	$(STR_LIBS)

zbx_strsplit_first_LDADD += @SERVER_LIBS@

zbx_strsplit_first_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strsplit_first_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_strsplit_last

zbx_strsplit_last_SOURCES = \
	zbx_strsplit_last.c \
	$(COMMON_SRC_FILES)

zbx_strsplit_last_LDADD = \
	$(STR_LIBS)

zbx_strsplit_last_LDADD += @SERVER_LIBS@

zbx_strsplit_last_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strsplit_last_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_strlcpy_utf8

zbx_strlcpy_utf8_SOURCES = \
	zbx_strlcpy_utf8.c \
	$(COMMON_SRC_FILES)

zbx_strlcpy_utf8_LDADD = \
	$(STR_LIBS)

zbx_strlcpy_utf8_LDADD += @SERVER_LIBS@

zbx_strlcpy_utf8_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strlcpy_utf8_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_utf8_char_len

zbx_utf8_char_len_SOURCES = \
	zbx_utf8_char_len.c \
	$(COMMON_SRC_FILES)

zbx_utf8_char_len_LDADD = \
	$(STR_LIBS)

zbx_utf8_char_len_LDADD += @SERVER_LIBS@

zbx_utf8_char_len_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_utf8_char_len_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_strlen_utf8_nchars

zbx_strlen_utf8_nchars_SOURCES = \
	zbx_strlen_utf8_nchars.c \
	$(COMMON_SRC_FILES)

zbx_strlen_utf8_nchars_LDADD = \
	$(STR_LIBS)

zbx_strlen_utf8_nchars_LDADD += @SERVER_LIBS@

zbx_strlen_utf8_nchars_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strlen_utf8_nchars_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_charcount_utf8_nbytes

zbx_charcount_utf8_nbytes_SOURCES = \
	zbx_charcount_utf8_nbytes.c \
	$(COMMON_SRC_FILES)

zbx_charcount_utf8_nbytes_LDADD = \
	$(STR_LIBS)

zbx_charcount_utf8_nbytes_LDADD += @SERVER_LIBS@

zbx_charcount_utf8_nbytes_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_charcount_utf8_nbytes_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_is_utf8

zbx_is_utf8_SOURCES = \
	zbx_is_utf8.c \
	$(COMMON_SRC_FILES)

zbx_is_utf8_LDADD = \
	$(STR_LIBS)

zbx_is_utf8_LDADD += @SERVER_LIBS@

zbx_is_utf8_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_is_utf8_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_replace_invalid_utf8

zbx_replace_invalid_utf8_SOURCES = \
	zbx_replace_invalid_utf8.c \
	$(COMMON_SRC_FILES)

zbx_replace_invalid_utf8_LDADD = \
	$(STR_LIBS)

zbx_replace_invalid_utf8_LDADD += @SERVER_LIBS@

zbx_replace_invalid_utf8_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_replace_invalid_utf8_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_trim_str_list

zbx_trim_str_list_SOURCES = \
	zbx_trim_str_list.c \
	$(COMMON_SRC_FILES)

zbx_trim_str_list_LDADD = \
	$(STR_LIBS)

zbx_trim_str_list_LDADD += @SERVER_LIBS@

zbx_trim_str_list_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_trim_str_list_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_strcmp_null

zbx_strcmp_null_SOURCES = \
	zbx_strcmp_null.c \
	$(COMMON_SRC_FILES)

zbx_strcmp_null_LDADD = \
	$(STR_LIBS)

zbx_strcmp_null_LDADD += @SERVER_LIBS@

zbx_strcmp_null_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strcmp_null_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_strcmp_natural

zbx_strcmp_natural_SOURCES = \
	zbx_strcmp_natural.c \
	$(COMMON_SRC_FILES)

zbx_strcmp_natural_LDADD = \
	$(STR_LIBS)

zbx_strcmp_natural_LDADD += @SERVER_LIBS@

zbx_strcmp_natural_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strcmp_natural_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_replace_mem_dyn

zbx_replace_mem_dyn_SOURCES = \
	zbx_replace_mem_dyn.c \
	$(COMMON_SRC_FILES)

zbx_replace_mem_dyn_LDADD = \
	$(STR_LIBS)

zbx_replace_mem_dyn_LDADD += @SERVER_LIBS@

zbx_replace_mem_dyn_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_replace_mem_dyn_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_substr

zbx_substr_SOURCES = \
	zbx_substr.c \
	$(COMMON_SRC_FILES)

zbx_substr_LDADD = \
	$(STR_LIBS)

zbx_substr_LDADD += @SERVER_LIBS@

zbx_substr_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_substr_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_replace_string

zbx_replace_string_SOURCES = \
	zbx_replace_string.c \
	$(COMMON_SRC_FILES)

zbx_replace_string_LDADD = \
	$(STR_LIBS)

zbx_replace_string_LDADD += @SERVER_LIBS@

zbx_replace_string_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_replace_string_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_str_printable_dyn

zbx_str_printable_dyn_SOURCES = \
	zbx_str_printable_dyn.c \
	$(COMMON_SRC_FILES)

zbx_str_printable_dyn_LDADD = \
	$(STR_LIBS)

zbx_str_printable_dyn_LDADD += @SERVER_LIBS@

zbx_str_printable_dyn_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_str_printable_dyn_CFLAGS = $(STR_COMPILER_FLAGS)

# zbx_str_linefeed

zbx_str_linefeed_SOURCES = \
	zbx_str_linefeed.c \
	$(COMMON_SRC_FILES)

zbx_str_linefeed_LDADD = \
	$(STR_LIBS)

zbx_str_linefeed_LDADD += @SERVER_LIBS@

zbx_str_linefeed_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_str_linefeed_CFLAGS = $(STR_COMPILER_FLAGS)

#zbx_strlcat

zbx_strlcat_SOURCES = \
	zbx_strlcat.c \
	$(COMMON_SRC_FILES)

zbx_strlcat_LDADD = \
	$(STR_LIBS)

zbx_strlcat_LDADD += @SERVER_LIBS@

zbx_strlcat_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strlcat_CFLAGS = $(STR_COMPILER_FLAGS)

#zbx_escape_string

zbx_escape_string_SOURCES = \
	zbx_escape_string.c \
	$(COMMON_SRC_FILES)

zbx_escape_string_LDADD = \
	$(STR_LIBS)

zbx_escape_string_LDADD += @SERVER_LIBS@

zbx_escape_string_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_escape_string_CFLAGS = $(STR_COMPILER_FLAGS)

#zbx_strdcatf

zbx_strdcatf_SOURCES = \
	zbx_strdcatf.c \
	$(COMMON_SRC_FILES)

zbx_strdcatf_LDADD = \
	$(STR_LIBS)

zbx_strdcatf_LDADD += @SERVER_LIBS@

zbx_strdcatf_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strdcatf_CFLAGS = $(STR_COMPILER_FLAGS)

#zbx_strcasestr

zbx_strcasestr_SOURCES = \
	zbx_strcasestr.c \
	$(COMMON_SRC_FILES)

zbx_strcasestr_LDADD = \
	$(STR_LIBS)

zbx_strcasestr_LDADD += @SERVER_LIBS@

zbx_strcasestr_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_strcasestr_CFLAGS = $(STR_COMPILER_FLAGS)

#zbx_dyn_escape_shell_single_quote

zbx_dyn_escape_shell_single_quote_SOURCES = \
	zbx_dyn_escape_shell_single_quote.c \
	$(COMMON_SRC_FILES)

zbx_dyn_escape_shell_single_quote_LDADD = \
	$(STR_LIBS)

zbx_dyn_escape_shell_single_quote_LDADD += @SERVER_LIBS@

zbx_dyn_escape_shell_single_quote_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_dyn_escape_shell_single_quote_CFLAGS = $(STR_COMPILER_FLAGS)

#endif
