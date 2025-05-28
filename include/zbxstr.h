/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_STR_H
#define ZABBIX_STR_H

#include "zbxcommon.h"

char	*zbx_string_replace(const char *str, const char *sub_str1, const char *sub_str2);

int	zbx_is_ascii_string(const char *str);

int	zbx_rtrim(char *str, const char *charlist);
void	zbx_ltrim(char *str, const char *charlist);
void	zbx_lrtrim(char *str, const char *charlist);
void	zbx_remove_chars(char *str, const char *charlist);
char	*zbx_str_printable_dyn(const char *text);
#define ZBX_WHITESPACE			" \t\r\n"
void	zbx_del_zeros(char *s);

size_t	zbx_get_escape_string_len(const char *src, const char *charlist);
char	*zbx_dyn_escape_string(const char *src, const char *charlist);
int	zbx_escape_string(char *dst, size_t len, const char *src, const char *charlist);

int	zbx_str_in_list(const char *list, const char *value, char delimiter);
int	zbx_str_n_in_list(const char *list, const char *value, size_t len, char delimiter);

char	*zbx_str_linefeed(const char *src, size_t maxline, const char *delim);
void	zbx_strarr_init(char ***arr);
void	zbx_strarr_add(char ***arr, const char *entry);
void	zbx_strarr_free(char ***arr);

void	zbx_strcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src);
void	zbx_chrcpy_alloc(char **str, size_t *alloc_len, size_t *offset, char c);
void	zbx_str_memcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n);

#define ZBX_STRQUOTE_DEFAULT		1
#define ZBX_STRQUOTE_SKIP_BACKSLASH	0
void	zbx_strquote_alloc_opt(char **str, size_t *str_alloc, size_t *str_offset, const char *value_str, int option);

void	zbx_strsplit_first(const char *src, char delimiter, char **left, char **right);
void	zbx_strsplit_last(const char *src, char delimiter, char **left, char **right);

/* secure string copy */
#define zbx_strscpy(x, y)	zbx_strlcpy(x, y, sizeof(x))
#define zbx_strscat(x, y)	zbx_strlcat(x, y, sizeof(x))
void	zbx_strlcat(char *dst, const char *src, size_t siz);
size_t	zbx_strlcpy_utf8(char *dst, const char *src, size_t size);

char	*zbx_strdcat(char *dest, const char *src);
char	*zbx_strdcatf(char *dest, const char *f, ...) __zbx_attr_format_printf(2, 3);

const char	*zbx_truncate_itemkey(const char *key, const size_t char_max, char *buf, const size_t buf_len);
const char	*zbx_truncate_value(const char *val, const size_t char_max, char *buf, const size_t buf_len);

#define ZBX_NULL2STR(str)	(NULL != str ? str : "(null)")
#define ZBX_NULL2EMPTY_STR(str)	(NULL != (str) ? (str) : "")

char	*zbx_strcasestr(const char *haystack, const char *needle);
int	zbx_strncasecmp(const char *s1, const char *s2, size_t n);

#if defined(_WINDOWS) || defined(__MINGW32__)
char	*zbx_unicode_to_utf8(const wchar_t *wide_string);
char	*zbx_unicode_to_utf8_static(const wchar_t *wide_string, char *utf8_string, int utf8_size);
#endif

void	zbx_strlower(char *str);
void	zbx_strupper(char *str);

#if defined(_WINDOWS) || defined(__MINGW32__) || defined(HAVE_ICONV)
char	*zbx_convert_to_utf8(char *in, size_t in_size, const char *encoding, char **error);
#endif	/* HAVE_ICONV */

#define ZBX_MAX_BYTES_IN_UTF8_CHAR	4
const char	*zbx_get_bom_econding(char *in, size_t in_size);
size_t	zbx_utf8_char_len(const char *text);
size_t	zbx_strlen_utf8(const char *text);
char	*zbx_strshift_utf8(char *text, size_t num);
size_t	zbx_strlen_utf8_nchars(const char *text, size_t utf8_maxlen);
size_t	zbx_charcount_utf8_nbytes(const char *text, size_t maxlen);

int	zbx_is_ascii_printable(const char *text);
int	zbx_is_utf8(const char *text);
void	zbx_replace_invalid_utf8(char *text);
void	zbx_replace_invalid_utf8_and_nonprintable(char *text);

void	zbx_dos2unix(char *str);

int	zbx_replace_mem_dyn(char **data, size_t *data_alloc, size_t *data_len, size_t offset, size_t sz_to,
		const char *from, size_t sz_from);

void	zbx_trim_str_list(char *list, char delimiter);

int	zbx_strcmp_null(const char *s1, const char *s2);

char	*zbx_dyn_escape_shell_single_quote(const char *arg);

int	zbx_strcmp_natural(const char *s1, const char *s2);
int	zbx_str_extract(const char *text, size_t len, char **value);
char	*zbx_substr(const char *src, size_t left, size_t right);
char	*zbx_substr_unquote(const char *src, size_t left, size_t right);

/* UTF-8 trimming */
void	zbx_ltrim_utf8(char *str, const char *charlist);
void	zbx_rtrim_utf8(char *str, const char *charlist);

void	zbx_strncpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n);
void	zbx_replace_string(char **data, size_t l, size_t *r, const char *value);
#endif /* ZABBIX_STR_H */
