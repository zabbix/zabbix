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

#include "zbxstr.h"
#include "zbxhttp.h"
#include "zbxtypes.h"
#include "zbxalgo.h"
#include "zbxexpr.h"

static int	str_loc_cmp(const char *src, const zbx_strloc_t *loc, const char *text, size_t text_len)
{
	ZBX_RETURN_IF_NOT_EQUAL(loc->r - loc->l + 1, text_len);

	return zbx_strncasecmp(src + loc->l, text, text_len);
}

static char	*str_loc_dup(const char *src, const zbx_strloc_t *loc)
{
	char	*str;
	size_t	len;

	len = loc->r - loc->l + 1;
	str = zbx_malloc(NULL, len + 1);
	memcpy(str, src + loc->l, len);
	str[len] = '\0';

	return str;
}

#define ZBX_ATTRIBUTE_NAME_CHARLIST	" \"'=<>`/"
static int	parse_attribute_name(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;

	if (NULL != strchr(ZBX_ATTRIBUTE_NAME_CHARLIST, *ptr))
		return FAIL;

	while (NULL == strchr(ZBX_ATTRIBUTE_NAME_CHARLIST, *(++ptr)))
		;

	loc->l = pos;
	loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}
#undef ZBX_ATTRIBUTE_NAME_CHARLIST

static size_t	skip_spaces(const char *data, size_t pos)
{
	while (' ' == data[pos] || '\t' == data[pos])
		pos++;

	return pos;
}

static int	parse_attribute_op(const char *data, size_t pos, zbx_strloc_t *loc)
{
	if ('=' == data[pos])
	{
		loc->l = pos;
		loc->r = pos;

		return SUCCEED;
	}

	return FAIL;
}

#define ZBX_UNQUOTED_ATTRIBUTE_VALUE_CHARLIST	" \"'=<>`"
static int	parse_attribute_value(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr;
	char		*charlist;
	unsigned char	quoted;

	ptr = data + pos;

	if ('"' == *ptr)
	{
		charlist = "\"";
		quoted = 1;
	}
	else if ('\'' == *ptr)
	{
		charlist = "'";
		quoted = 1;
	}
	else if (NULL == strchr(ZBX_UNQUOTED_ATTRIBUTE_VALUE_CHARLIST, *ptr))
	{
		quoted = 0;
		charlist = ZBX_UNQUOTED_ATTRIBUTE_VALUE_CHARLIST;
	}
	else
		return FAIL;

	loc->l = pos;

	while (NULL == strchr(charlist, *(++ptr)))
		;

	if (1 == quoted)
	{
		if ('\0' == *ptr)
			return FAIL;

		loc->r = (size_t)(ptr - data);
	}
	else
		loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}
#undef ZBX_UNQUOTED_ATTRIBUTE_VALUE_CHARLIST

static int	parse_attribute_name_value(const char *data, size_t pos, zbx_strloc_t *loc_name,
		zbx_strloc_t *loc_value)
{
	zbx_strloc_t	loc_op;

	if (SUCCEED != parse_attribute_name(data, pos, loc_name))
		return FAIL;

	pos = skip_spaces(data, loc_name->r + 1);

	if (SUCCEED != parse_attribute_op(data, pos, &loc_op))
	{
		*loc_value = *loc_name;
		return SUCCEED;
	}

	pos = skip_spaces(data, loc_op.r + 1);

	if (SUCCEED != parse_attribute_value(data, pos, loc_value))
		return FAIL;

	return SUCCEED;
}

static size_t	parse_html_attributes(const char *data, char **content, char **charset)
{
	size_t		pos = 0;
	zbx_strloc_t	loc_name, loc_value, loc_content;
	int		http_equiv_content_found = 0, content_found = 0;

	pos = skip_spaces(data, pos);

	while (1)
	{
		if (FAIL == parse_attribute_name_value(data, pos, &loc_name, &loc_value))
			break;

		pos = skip_spaces(data, loc_value.r + 1);
		if (0 == str_loc_cmp(data, &loc_name, "http-equiv", ZBX_CONST_STRLEN("http-equiv")))
		{
			if (0 == str_loc_cmp(data, &loc_value, "\"content-type\"",
					ZBX_CONST_STRLEN("\"content-type\"")) ||
					0 == str_loc_cmp(data, &loc_value, "content-type",
					ZBX_CONST_STRLEN("content-type")) ||
					0 == str_loc_cmp(data, &loc_value, "'content-type'",
					ZBX_CONST_STRLEN("'content-type'")))
			{
				http_equiv_content_found = 1;
			}
		}
		else if (0 == str_loc_cmp(data, &loc_name, "content", ZBX_CONST_STRLEN("content")))
		{
			loc_content = loc_value;
			content_found = 1;
		}
		else if (0 == str_loc_cmp(data, &loc_name, "charset", ZBX_CONST_STRLEN("charset")))
		{
			*charset = str_loc_dup(data, &loc_value);
			zbx_lrtrim(*charset, " \"'");
			return pos;
		}
	}

	if (1 == http_equiv_content_found && 1 == content_found)
	{
		*content = str_loc_dup(data, &loc_content);
		zbx_lrtrim(*content, " \"'");
	}

	return pos;
}

static void	html_get_charset_content(const char *data, char **charset, char **content)
{
	while (NULL == *charset && NULL == *content && NULL != (data = strstr(data, "<meta")))
	{
		data += ZBX_CONST_STRLEN("<meta");
		data += parse_html_attributes(data, content, charset);
	}
}

#define ZBX_TSPECIALS			"()<>@,;:\"/[]?="
#define ZBX_CONTENT_TOKEN_CHARLIST	ZBX_TSPECIALS " \r\n"

static int	parse_content_name(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;

	if (NULL != strchr(ZBX_CONTENT_TOKEN_CHARLIST, *ptr))
		return FAIL;

	while (NULL == strchr(ZBX_CONTENT_TOKEN_CHARLIST, *(++ptr)))
		;

	loc->l = pos;
	loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}

static int	parse_content_op(const char *data, size_t pos, zbx_strloc_t *loc)
{
	if ('=' == data[pos])
	{
		loc->l = pos;
		loc->r = pos;

		return SUCCEED;
	}

	return FAIL;
}

static int	parse_quoted_content_value(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr;

	ptr = data + pos;
	loc->l = pos;

	while ('"' != *(++ptr))
	{
		if ('\\' == *ptr)
		{
			ptr++;

			if ('\\' != *ptr && 'n' != *ptr && '"' != *ptr)
				return FAIL;
			continue;
		}
		if ('\0' == *ptr)
			return FAIL;
	}

	loc->r = (size_t)(ptr - data);

	return SUCCEED;
}

static int	parse_content_value(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr;

	ptr = data + pos;

	if ('"' == *ptr)
		return parse_quoted_content_value(data, pos, loc);
	else if (NULL != strchr(ZBX_CONTENT_TOKEN_CHARLIST, *ptr))
		return FAIL;

	loc->l = pos;

	while (NULL == strchr(ZBX_CONTENT_TOKEN_CHARLIST, *(++ptr)))
		;

	loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}

#undef ZBX_CONTENT_TOKEN_CHARLIST
#undef ZBX_TSPECIALS

static int	parse_content_key_value(const char *data, size_t pos, zbx_strloc_t *loc_name, zbx_strloc_t *loc_value)
{
	zbx_strloc_t	loc_op;

	if (SUCCEED != parse_content_name(data, pos, loc_name))
		return FAIL;

	pos = skip_spaces(data, loc_name->r + 1);

	if (SUCCEED != parse_content_op(data, pos, &loc_op))
		return FAIL;

	pos = skip_spaces(data, loc_op.r + 1);

	if (SUCCEED != parse_content_value(data, pos, loc_value))
		return FAIL;

	return SUCCEED;
}

static char	*str_loc_unquote_dyn(const char *src, const zbx_strloc_t *loc)
{
	char		*str, *ptr;

	src += loc->l + 1;

	str = ptr = zbx_malloc(NULL, loc->r - loc->l);

	while ('"' != *src)
	{
		if ('\\' == *src)
		{
			switch (*(++src))
			{
				case '\\':
					*ptr++ = '\\';
					break;
				case 'n':
					*ptr++ = '\n';
					break;
				case '"':
					*ptr++ = '"';
					break;
			}
		}
		else
			*ptr++ = *src;
		src++;
	}
	*ptr = '\0';

	return str;
}

static char	*parse_content(const char *data)
{
	size_t		pos = 0;
	zbx_strloc_t	loc_name, loc_value;

	pos = skip_spaces(data, pos);

	while (1)
	{
		if (FAIL == parse_content_key_value(data, pos, &loc_name, &loc_value))
			break;

		pos = skip_spaces(data, loc_value.r + 1);

		if (0 == str_loc_cmp(data, &loc_name, "charset", ZBX_CONST_STRLEN("charset")))
		{
			if ('"' == *(data + loc_value.l))
				return str_loc_unquote_dyn(data, &loc_value);

			return str_loc_dup(data, &loc_value);
		}
	}

	return NULL;
}

char	*zbx_determine_charset(const char *content_type, char *body, size_t len)
{
	const char	*ptr;
	char		*charset = NULL, *content = NULL;

	if (NULL != content_type)
	{
		if (NULL != (ptr = strchr(content_type, ';')))
			charset = parse_content(ptr + 1);
	}

	if (NULL == charset && 0 == len)
		charset = zbx_strdup(NULL, "UTF-8");

	html_get_charset_content(body, &charset, &content);

	if (NULL != content && NULL == charset)
	{
		if (NULL != (ptr = strchr(content, ';')))
			charset = parse_content(ptr + 1);
	}

	zbx_free(content);

	if (NULL == charset)
	{
		const char	*bom_encoding = zbx_get_bom_econding(body, len);

		if ('\0' != *bom_encoding)
			charset = zbx_strdup(NULL, bom_encoding);
		else if (SUCCEED == zbx_is_utf8(body))
			charset = zbx_strdup(NULL, "UTF-8");
		else
			charset = zbx_strdup(NULL, "WINDOWS-1252");
	}

	zbx_lrtrim(charset, " ");
	zbx_strupper(charset);

	return charset;
}
