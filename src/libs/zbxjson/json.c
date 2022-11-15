/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "json.h"

#include "json_parser.h"
#include "jsonpath.h"

/******************************************************************************
 *                                                                            *
 * Purpose: return string describing json error                               *
 *                                                                            *
 * Return value: pointer to the null terminated string                        *
 *                                                                            *
 ******************************************************************************/
#define ZBX_JSON_MAX_STRERROR	255

static ZBX_THREAD_LOCAL char	zbx_json_strerror_message[ZBX_JSON_MAX_STRERROR];

const char	*zbx_json_strerror(void)
{
	return zbx_json_strerror_message;
}

void	zbx_set_json_strerror(const char *fmt, ...)
{
	size_t	sz;
	va_list	args;

	va_start(args, fmt);

	sz = zbx_vsnprintf(zbx_json_strerror_message, sizeof(zbx_json_strerror_message), fmt, args);

	if (sizeof(zbx_json_strerror_message) - 1 == sz)
	{
		/* ensure that the string is not cut in the middle of UTF-8 sequence */
		size_t	idx = sz - 1;
		while (0x80 == (0xc0 & zbx_json_strerror_message[idx]) && 0 < idx)
			idx--;

		if (zbx_utf8_char_len(zbx_json_strerror_message + idx) != sz - idx)
			zbx_json_strerror_message[idx] = '\0';
	}

	va_end(args);
}

static void	__zbx_json_realloc(struct zbx_json *j, size_t need)
{
	int	realloc = 0;

	if (NULL == j->buffer)
	{
		if (need > sizeof(j->buf_stat))
		{
			j->buffer_allocated = need;
			j->buffer = (char *)zbx_malloc(j->buffer, j->buffer_allocated);
		}
		else
		{
			j->buffer_allocated = sizeof(j->buf_stat);
			j->buffer = j->buf_stat;
		}
		return;
	}

	while (need > j->buffer_allocated)
	{
		if (0 == j->buffer_allocated)
			j->buffer_allocated = 1024;
		else
			j->buffer_allocated *= 2;
		realloc = 1;
	}

	if (1 == realloc)
	{
		if (j->buffer == j->buf_stat)
		{
			j->buffer = NULL;
			j->buffer = (char *)zbx_malloc(j->buffer, j->buffer_allocated);
			memcpy(j->buffer, j->buf_stat, sizeof(j->buf_stat));
		}
		else
			j->buffer = (char *)zbx_realloc(j->buffer, j->buffer_allocated);
	}
}

void	zbx_json_init(struct zbx_json *j, size_t allocate)
{
	assert(j);

	j->buffer = NULL;
	j->buffer_allocated = 0;
	j->buffer_offset = 0;
	j->buffer_size = 0;
	j->status = ZBX_JSON_EMPTY;
	j->level = 0;
	__zbx_json_realloc(j, allocate);
	*j->buffer = '\0';

	zbx_json_addobject(j, NULL);
}

void	zbx_json_initarray(struct zbx_json *j, size_t allocate)
{
	assert(j);

	j->buffer = NULL;
	j->buffer_allocated = 0;
	j->buffer_offset = 0;
	j->buffer_size = 0;
	j->status = ZBX_JSON_EMPTY;
	j->level = 0;
	__zbx_json_realloc(j, allocate);
	*j->buffer = '\0';

	zbx_json_addarray(j, NULL);
}

static void	zbx_json_setempty(struct zbx_json *j)
{
	j->buffer_offset = 0;
	j->buffer_size = 0;
	j->status = ZBX_JSON_EMPTY;
	j->level = 0;
	*j->buffer = '\0';
}

void	zbx_json_cleanarray(struct zbx_json *j)
{
	zbx_json_setempty(j);
	zbx_json_addarray(j, NULL);
}

void	zbx_json_clean(struct zbx_json *j)
{
	zbx_json_setempty(j);
	zbx_json_addobject(j, NULL);
}

void	zbx_json_free(struct zbx_json *j)
{
	assert(j);

	if (j->buffer != j->buf_stat)
		zbx_free(j->buffer);
}

static size_t	__zbx_json_stringsize(const char *string, zbx_json_type_t type)
{
	size_t		len = 0;
	const char	*sptr;
	char		buffer[] = {"null"};

	for (sptr = (NULL != string ? string : buffer); '\0' != *sptr; sptr++)
	{
		switch (*sptr)
		{
			case '"':  /* quotation mark */
			case '\\': /* reverse solidus */
			/* We do not escape '/' (solidus). https://www.rfc-editor.org/errata_search.php?rfc=4627 */
			/* says: "/" and "\/" are both allowed and both produce the same result. */
			case '\b': /* backspace */
			case '\f': /* formfeed */
			case '\n': /* newline */
			case '\r': /* carriage return */
			case '\t': /* horizontal tab */
				len += 2;
				break;
			default:
				/* RFC 8259 requires escaping control characters U+0000 - U+001F */
				if (0x1f >= (unsigned char)*sptr)
					len += 6;
				else
					len++;
		}
	}

	if (NULL != string && ZBX_JSON_TYPE_STRING == type)
		len += 2; /* "" */

	return len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert parameter c (0-15) to hexadecimal value ('0'-'f')         *
 *                                                                            *
 * Parameters:                                                                *
 *      c - number 0-15                                                       *
 *                                                                            *
 * Return value:                                                              *
 *      '0'-'f'                                                               *
 *                                                                            *
 ******************************************************************************/
static char	zbx_num2hex(unsigned char c)
{
	if (c >= 10)
		return (char)(c + 0x57);	/* a-f */
	else
		return (char)(c + 0x30);	/* 0-9 */
}

static char	*__zbx_json_insstring(char *p, const char *string, zbx_json_type_t type)
{
	const char	*sptr;
	char		buffer[] = {"null"};

	if (NULL != string && ZBX_JSON_TYPE_STRING == type)
		*p++ = '"';

	for (sptr = (NULL != string ? string : buffer); '\0' != *sptr; sptr++)
	{
		switch (*sptr)
		{
			case '"':		/* quotation mark */
				*p++ = '\\';
				*p++ = '"';
				break;
			case '\\':		/* reverse solidus */
				*p++ = '\\';
				*p++ = '\\';
				break;
			/* We do not escape '/' (solidus). https://www.rfc-editor.org/errata_search.php?rfc=4627 */
			/* says: "/" and "\/" are both allowed and both produce the same result. */
			case '\b':		/* backspace */
				*p++ = '\\';
				*p++ = 'b';
				break;
			case '\f':		/* formfeed */
				*p++ = '\\';
				*p++ = 'f';
				break;
			case '\n':		/* newline */
				*p++ = '\\';
				*p++ = 'n';
				break;
			case '\r':		/* carriage return */
				*p++ = '\\';
				*p++ = 'r';
				break;
			case '\t':		/* horizontal tab */
				*p++ = '\\';
				*p++ = 't';
				break;
			default:
				/* RFC 8259 requires escaping control characters U+0000 - U+001F */
				if (0x1f >= (unsigned char)*sptr)
				{
					*p++ = '\\';
					*p++ = 'u';
					*p++ = '0';
					*p++ = '0';
					*p++ = zbx_num2hex((((unsigned char)*sptr) >> 4) & 0xf);
					*p++ = zbx_num2hex(((unsigned char)*sptr) & 0xf);
				}
				else
					*p++ = *sptr;
		}
	}

	if (NULL != string && ZBX_JSON_TYPE_STRING == type)
		*p++ = '"';

	return p;
}

void	zbx_json_escape(char **string)
{
	size_t	size;
	char	*buffer;

	if (0 == (size = __zbx_json_stringsize(*string, ZBX_JSON_TYPE_UNKNOWN)))
		return;

	buffer = zbx_malloc(NULL, size + 1);
	buffer[size] = '\0';
	__zbx_json_insstring(buffer, *string, ZBX_JSON_TYPE_UNKNOWN);
	zbx_free(*string);
	*string = buffer;
}

static void	__zbx_json_addobject(struct zbx_json *j, const char *name, int object)
{
	size_t	len = 2; /* brackets */
	char	*p, *psrc, *pdst;

	assert(j);

	if (ZBX_JSON_COMMA == j->status)
		len++; /* , */

	if (NULL != name)
	{
		len += __zbx_json_stringsize(name, ZBX_JSON_TYPE_STRING);
		len += 1; /* : */
	}

	__zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (ZBX_JSON_COMMA == j->status)
		*p++ = ',';

	if (NULL != name)
	{
		p = __zbx_json_insstring(p, name, ZBX_JSON_TYPE_STRING);
		*p++ = ':';
	}

	*p++ = object ? '{' : '[';
	*p = object ? '}' : ']';

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->level++;
	j->status = ZBX_JSON_EMPTY;
}

void	zbx_json_addobject(struct zbx_json *j, const char *name)
{
	__zbx_json_addobject(j, name, 1);
}

void	zbx_json_addarray(struct zbx_json *j, const char *name)
{
	__zbx_json_addobject(j, name, 0);
}

void	zbx_json_addstring(struct zbx_json *j, const char *name, const char *string, zbx_json_type_t type)
{
	size_t	len = 0;
	char	*p, *psrc, *pdst;

	assert(j);

	if (ZBX_JSON_COMMA == j->status)
		len++; /* , */

	if (NULL != name)
	{
		len += __zbx_json_stringsize(name, ZBX_JSON_TYPE_STRING);
		len += 1; /* : */
	}
	len += __zbx_json_stringsize(string, type);

	__zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (ZBX_JSON_COMMA == j->status)
		*p++ = ',';

	if (NULL != name)
	{
		p = __zbx_json_insstring(p, name, ZBX_JSON_TYPE_STRING);
		*p++ = ':';
	}
	p = __zbx_json_insstring(p, string, type);

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->status = ZBX_JSON_COMMA;
}

void	zbx_json_addraw(struct zbx_json *j, const char *name, const char *data)
{
	size_t	len = 0, len_data;
	char	*p, *psrc, *pdst;

	assert(j);
	len_data = strlen(data);

	if (ZBX_JSON_COMMA == j->status)
		len++; /* , */

	if (NULL != name)
	{
		len += __zbx_json_stringsize(name, ZBX_JSON_TYPE_STRING);
		len += 1; /* : */
	}
	len += len_data;

	__zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (ZBX_JSON_COMMA == j->status)
		*p++ = ',';

	if (NULL != name)
	{
		p = __zbx_json_insstring(p, name, ZBX_JSON_TYPE_STRING);
		*p++ = ':';
	}

	memcpy(p, data, len_data);
	p += len_data;

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->status = ZBX_JSON_COMMA;
}

void	zbx_json_adduint64(struct zbx_json *j, const char *name, zbx_uint64_t value)
{
	char	buffer[MAX_ID_LEN];

	zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, value);
	zbx_json_addstring(j, name, buffer, ZBX_JSON_TYPE_INT);
}

void	zbx_json_addint64(struct zbx_json *j, const char *name, zbx_int64_t value)
{
	char	buffer[MAX_ID_LEN];

	zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_I64, value);
	zbx_json_addstring(j, name, buffer, ZBX_JSON_TYPE_INT);
}

void	zbx_json_addfloat(struct zbx_json *j, const char *name, double value)
{
	char	buffer[MAX_ID_LEN];

	zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, value);
	zbx_json_addstring(j, name, buffer, ZBX_JSON_TYPE_INT);
}

int	zbx_json_close(struct zbx_json *j)
{
	if (1 == j->level)
	{
		zbx_set_json_strerror("cannot close top level object");
		return FAIL;
	}

	j->level--;
	j->buffer_offset++;
	j->status = ZBX_JSON_COMMA;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Return value: type of input value                                          *
 *                                                                            *
 ******************************************************************************/
static zbx_json_type_t	__zbx_json_type(const char *p)
{
	if ('"' == *p)
		return ZBX_JSON_TYPE_STRING;
	if (('0' <= *p && *p <= '9') || '-' == *p)
		return ZBX_JSON_TYPE_INT;
	if ('[' == *p)
		return ZBX_JSON_TYPE_ARRAY;
	if ('{' == *p)
		return ZBX_JSON_TYPE_OBJECT;
	if ('n' == p[0] && 'u' == p[1] && 'l' == p[2] && 'l' == p[3])
		return ZBX_JSON_TYPE_NULL;
	if ('t' == p[0] && 'r' == p[1] && 'u' == p[2] && 'e' == p[3])
		return ZBX_JSON_TYPE_TRUE;
	if ('f' == p[0] && 'a' == p[1] && 'l' == p[2] && 's' == p[3] && 'e' == p[4])
		return ZBX_JSON_TYPE_FALSE;

	zbx_set_json_strerror("invalid type of JSON value \"%.64s\"", p);

	return ZBX_JSON_TYPE_UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Return value: position of the right bracket                                *
 *               NULL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
static const char	*__zbx_json_rbracket(const char *p)
{
	int	level = 0;
	int	state = 0; /* 0 - outside string; 1 - inside string */
	char	lbracket, rbracket;

	assert(p);

	lbracket = *p;

	if ('{' != lbracket && '[' != lbracket)
		return NULL;

	rbracket = ('{' == lbracket ? '}' : ']');

	while ('\0' != *p)
	{
		switch (*p)
		{
			case '"':
				state = (0 == state ? 1 : 0);
				break;
			case '\\':
				if (1 == state)
					if ('\0' == *++p)
						return NULL;
				break;
			case '[':
			case '{':
				if (0 == state)
					level++;
				break;
			case ']':
			case '}':
				if (0 == state)
				{
					level--;
					if (0 == level)
						return (rbracket == *p ? p : NULL);
				}
				break;
		}
		p++;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: open json buffer and check for brackets                           *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_open(const char *buffer, struct zbx_json_parse *jp)
{
	char		*error = NULL;
	zbx_int64_t	len;

	SKIP_WHITESPACE(buffer);

	/* return immediate failure without logging when opening empty string */
	if ('\0' == *buffer)
		return FAIL;

	jp->start = buffer;
	jp->end = NULL;

	if (0 == (len = zbx_json_validate(jp->start, &error)))
	{
		if (NULL != error)
		{
			zbx_set_json_strerror("cannot parse as a valid JSON object: %s", error);
			zbx_free(error);
		}
		else
		{
			zbx_set_json_strerror("cannot parse as a valid JSON object \"%.64s\"", buffer);
		}

		return FAIL;
	}

	jp->end = jp->start + len - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: locate next pair or element                                       *
 *                                                                            *
 * Return value: NULL - no more values                                        *
 *               NOT NULL - pointer to pair or element                        *
 *      {"name",...    or  "array":["name", ... ,1,null]                      *
 * p =   ^                                         ^                          *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_json_next(const struct zbx_json_parse *jp, const char *p)
{
	int	level = 0;
	int	state = 0;	/* 0 - outside string; 1 - inside string */

	if (1 == jp->end - jp->start)	/* empty object or array */
		return NULL;

	if (NULL == p)
	{
		p = jp->start + 1;
		SKIP_WHITESPACE(p);
		return p;
	}

	while (p <= jp->end)
	{
		switch (*p)
		{
			case '"':
				state = (0 == state) ? 1 : 0;
				break;
			case '\\':
				if (1 == state)
					p++;
				break;
			case '[':
			case '{':
				if (0 == state)
					level++;
				break;
			case ']':
			case '}':
				if (0 == state)
				{
					if (0 == level)
						return NULL;
					level--;
				}
				break;
			case ',':
				if (0 == state && 0 == level)
				{
					p++;
					SKIP_WHITESPACE(p);
					return p;
				}
				break;
		}
		p++;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if a 4 character sequence is a valid hex number 0000 - FFFF *
 *                                                                            *
 * Parameters:                                                                *
 *      p - pointer to the 1st character                                      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_is_valid_json_hex(const char *p)
{
	int	i;

	for (i = 0; i < 4; ++i, ++p)
	{
		if (0 == isxdigit(*p))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert hexit c ('0'-'9''a'-'f''A'-'F') to number (0-15)          *
 *                                                                            *
 * Parameters:                                                                *
 *      c - char ('0'-'9''a'-'f''A'-'F')                                      *
 *                                                                            *
 * Return value:                                                              *
 *      0-15                                                                  *
 *                                                                            *
 ******************************************************************************/
static unsigned int	zbx_hex2num(char c)
{
	int	res;

	if (c >= 'a')
		res = c - 'a' + 10;	/* a-f */
	else if (c >= 'A')
		res = c - 'A' + 10;	/* A-F */
	else
		res = c - '0';		/* 0-9 */

	return (unsigned int)res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: decodes JSON escape character into UTF-8                          *
 *                                                                            *
 * Parameters: p - [IN/OUT] a pointer to the first character in string        *
 *             bytes - [OUT] a 4-element array where 1 - 4 bytes of character *
 *                     UTF-8 representation are written                       *
 *                                                                            *
 * Return value: number of UTF-8 bytes written into 'bytes' array or          *
 *               0 on error (invalid escape sequence)                         *
 *                                                                            *
 ******************************************************************************/
static unsigned int	zbx_json_decode_character(const char **p, unsigned char *bytes)
{
	bytes[0] = '\0';

	switch (**p)
	{
		case '"':
			bytes[0] = '"';
			break;
		case '\\':
			bytes[0] = '\\';
			break;
		case '/':
			bytes[0] = '/';
			break;
		case 'b':
			bytes[0] = '\b';
			break;
		case 'f':
			bytes[0] = '\f';
			break;
		case 'n':
			bytes[0] = '\n';
			break;
		case 'r':
			bytes[0] = '\r';
			break;
		case 't':
			bytes[0] = '\t';
			break;
		default:
			break;
	}

	if ('\0' != bytes[0])
	{
		++*p;
		return 1;
	}

	if ('u' == **p)		/* \u0000 - \uffff */
	{
		unsigned int	num;

		if (FAIL == zbx_is_valid_json_hex(++*p))
			return 0;

		num = zbx_hex2num(**p) << 12;
		num += zbx_hex2num(*(++*p)) << 8;
		num += zbx_hex2num(*(++*p)) << 4;
		num += zbx_hex2num(*(++*p));
		++*p;

		if (0x007f >= num)	/* 0000 - 007f */
		{
			bytes[0] = (unsigned char)num;
			return 1;
		}
		else if (0x07ff >= num)	/* 0080 - 07ff */
		{
			bytes[0] = (unsigned char)(0xc0 | ((num >> 6) & 0x1f));
			bytes[1] = (unsigned char)(0x80 | (num & 0x3f));
			return 2;
		}
		else if (0xd7ff >= num || 0xe000 <= num)	/* 0800 - d7ff or e000 - ffff */
		{
			bytes[0] = (unsigned char)(0xe0 | ((num >> 12) & 0x0f));
			bytes[1] = (unsigned char)(0x80 | ((num >> 6) & 0x3f));
			bytes[2] = (unsigned char)(0x80 | (num & 0x3f));
			return 3;
		}
		else if (0xd800 <= num && num <= 0xdbff)	/* high surrogate d800 - dbff */
		{
			unsigned int	num_lo, uc;

			/* collect the low surrogate */

			if ('\\' != **p || 'u' != *(++*p) || FAIL == zbx_is_valid_json_hex(++*p))
				return 0;

			num_lo = zbx_hex2num(**p) << 12;
			num_lo += zbx_hex2num(*(++*p)) << 8;
			num_lo += zbx_hex2num(*(++*p)) << 4;
			num_lo += zbx_hex2num(*(++*p));
			++*p;

			if (num_lo < 0xdc00 || 0xdfff < num_lo)		/* low surrogate range is dc00 - dfff */
				return 0;

			/* decode surrogate pair */

			uc = 0x010000 + ((num & 0x03ff) << 10) + (num_lo & 0x03ff);

			bytes[0] = (unsigned char)(0xf0 | ((uc >> 18) & 0x07));
			bytes[1] = (unsigned char)(0x80 | ((uc >> 12) & 0x3f));
			bytes[2] = (unsigned char)(0x80 | ((uc >> 6) & 0x3f));
			bytes[3] = (unsigned char)(0x80 | (uc & 0x3f));
			return 4;
		}
		/* error - low surrogate without high surrogate */
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies json name/string value by omitting leading/trailing " and  *
 *          converting escape sequences                                       *
 *                                                                            *
 * Parameters: p     - [IN] a pointer to the next character in string         *
 *             out   - [OUT] the output buffer                                *
 *             size  - [IN] the output buffer size                            *
 *                                                                            *
 * Return value: A pointer to the next character in input string or NULL if   *
 *               string copying failed.                                       *
 *                                                                            *
 ******************************************************************************/
const char	*json_copy_string(const char *p, char *out, size_t size)
{
	char	*start = out;

	if (0 == size)
		return NULL;

	p++;

	while ('\0' != *p)
	{
		unsigned int	nbytes, i;
		unsigned char	uc[4];	/* decoded Unicode character takes 1-4 bytes in UTF-8 */

		switch (*p)
		{
			case '\\':
				++p;
				if (0 == (nbytes = zbx_json_decode_character(&p, uc)))
					return NULL;

				if ((size_t)(out - start) + nbytes >= size)
					return NULL;

				for (i = 0; i < nbytes; ++i)
					*out++ = (char)uc[i];

				break;
			case '"':
				*out = '\0';
				return ++p;
			default:
				*out++ = *p++;
		}

		if ((size_t)(out - start) == size)
			break;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies unquoted (numeric, boolean) json value                     *
 *                                                                            *
 * Parameters: p     - [IN] a pointer to the next character in string         *
 *             len   - [IN] the value length                                  *
 *             out   - [OUT] the output buffer                                *
 *             size  - [IN] the output buffer size                            *
 *                                                                            *
 * Return value: A pointer to the next character in input string or NULL if   *
 *               string copying failed.                                       *
 *                                                                            *
 ******************************************************************************/
static const char	*zbx_json_copy_unquoted_value(const char *p, size_t len, char *out, size_t size)
{
	if (size < len + 1)
		return NULL;

	memcpy(out, p, len);
	out[len] = '\0';

	return p + len;
}

const char	*zbx_json_decodevalue(const char *p, char *string, size_t size, zbx_json_type_t *type)
{
	size_t		len;
	zbx_json_type_t	type_local;

	switch (type_local = __zbx_json_type(p))
	{
		case ZBX_JSON_TYPE_ARRAY:
		case ZBX_JSON_TYPE_OBJECT:
		case ZBX_JSON_TYPE_UNKNOWN:
			/* only primitive values are decoded */
			return NULL;
		default:
			if (0 == (len = json_parse_value(p, NULL, NULL)))
				return NULL;
	}

	if (NULL != type)
		*type = type_local;

	switch (type_local)
	{
		case ZBX_JSON_TYPE_STRING:
			return json_copy_string(p, string, size);
		case ZBX_JSON_TYPE_NULL:
			if (0 == size)
				return NULL;
			*string = '\0';
			return p + len;
		default: /* ZBX_JSON_TYPE_INT, ZBX_JSON_TYPE_TRUE, ZBX_JSON_TYPE_FALSE */
			return zbx_json_copy_unquoted_value(p, len, string, size);
	}
}

const char	*zbx_json_decodevalue_dyn(const char *p, char **string, size_t *string_alloc, zbx_json_type_t *type)
{
	size_t		len;
	zbx_json_type_t	type_local;

	switch (type_local = __zbx_json_type(p))
	{
		case ZBX_JSON_TYPE_ARRAY:
		case ZBX_JSON_TYPE_OBJECT:
		case ZBX_JSON_TYPE_UNKNOWN:
			/* only primitive values are decoded */
			return NULL;
		default:
			if (0 == (len = json_parse_value(p, NULL, NULL)))
				return NULL;
	}

	if (*string_alloc <= len)
	{
		*string_alloc = len + 1;
		*string = (char *)zbx_realloc(*string, *string_alloc);
	}

	if (NULL != type)
		*type = type_local;

	switch (type_local)
	{
		case ZBX_JSON_TYPE_STRING:
			return json_copy_string(p, *string, *string_alloc);
		case ZBX_JSON_TYPE_NULL:
			**string = '\0';
			return p + len;
		default: /* ZBX_JSON_TYPE_INT, ZBX_JSON_TYPE_TRUE, ZBX_JSON_TYPE_FALSE */
			return zbx_json_copy_unquoted_value(p, len, *string, *string_alloc);
	}
}

const char	*zbx_json_pair_next(const struct zbx_json_parse *jp, const char *p, char *name, size_t len)
{
	if (NULL == (p = zbx_json_next(jp, p)))
		return NULL;

	if (ZBX_JSON_TYPE_STRING != __zbx_json_type(p))
		return NULL;

	if (NULL == (p = json_copy_string(p, name, len)))
		return NULL;

	SKIP_WHITESPACE(p);

	if (':' != *p++)
		return NULL;

	SKIP_WHITESPACE(p);

	return p;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find pair by name and return pointer to value                     *
 *                                                                            *
 * Return value: pointer to value                                             *
 *        {"name":["a","b",...]}                                              *
 *                ^ - returned pointer                                        *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_json_pair_by_name(const struct zbx_json_parse *jp, const char *name)
{
	char		buffer[MAX_STRING_LEN];
	const char	*p = NULL;

	while (NULL != (p = zbx_json_pair_next(jp, p, buffer, sizeof(buffer))))
		if (0 == strcmp(name, buffer))
			return p;

	zbx_set_json_strerror("cannot find pair with name \"%s\"", name);

	return NULL;
}

const char	*zbx_json_next_value(const struct zbx_json_parse *jp, const char *p, char *string, size_t len,
		zbx_json_type_t *type)
{
	if (NULL == (p = zbx_json_next(jp, p)))
		return NULL;

	return zbx_json_decodevalue(p, string, len, type);
}

const char	*zbx_json_next_value_dyn(const struct zbx_json_parse *jp, const char *p, char **string,
		size_t *string_alloc, zbx_json_type_t *type)
{
	if (NULL == (p = zbx_json_next(jp, p)))
		return NULL;

	return zbx_json_decodevalue_dyn(p, string, string_alloc, type);
}

/******************************************************************************
 *                                                                            *
 * Purpose: return value by pair name                                         *
 *                                                                            *
 * Return value: SUCCEED - if value successfully parsed, FAIL - otherwise     *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_value_by_name(const struct zbx_json_parse *jp, const char *name, char *string, size_t len,
		zbx_json_type_t *type)
{
	const char	*p;

	if (NULL == (p = zbx_json_pair_by_name(jp, name)))
		return FAIL;

	if (NULL == zbx_json_decodevalue(p, string, len, type))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return value by pair name                                         *
 *                                                                            *
 * Return value: SUCCEED - if value successfully parsed, FAIL - otherwise     *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_value_by_name_dyn(const struct zbx_json_parse *jp, const char *name, char **string,
		size_t *string_alloc, zbx_json_type_t *type)
{
	const char	*p;

	if (NULL == (p = zbx_json_pair_by_name(jp, name)))
		return FAIL;

	if (NULL == zbx_json_decodevalue_dyn(p, string, string_alloc, type))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_brackets_open(const char *p, struct zbx_json_parse *jp)
{
	if (NULL == (jp->end = __zbx_json_rbracket(p)))
	{
		zbx_set_json_strerror("cannot open JSON object or array \"%.64s\"", p);
		return FAIL;
	}

	SKIP_WHITESPACE(p);

	jp->start = p;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_brackets_by_name(const struct zbx_json_parse *jp, const char *name, struct zbx_json_parse *out)
{
	const char	*p;

	if (NULL == (p = zbx_json_pair_by_name(jp, name)))
		return FAIL;

	if (FAIL == zbx_json_brackets_open(p, out))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCESS - if object is empty                                 *
 *               FAIL - if object contains data                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_object_is_empty(const struct zbx_json_parse *jp)
{
	return jp->end - jp->start > 1 ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Return value: number of elements in zbx_json_parse object                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_count(const struct zbx_json_parse *jp)
{
	int		num = 0;
	const char	*p = NULL;

	while (NULL != (p = zbx_json_next(jp, p)))
		num++;

	return num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: opens an object by definite json path                             *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Comments: Only direct path to single object in dot or bracket notation     *
 *           is supported.                                                    *
 *                                                                            *
 ******************************************************************************/
int	json_open_path(const struct zbx_json_parse *jp, const zbx_jsonpath_t *jsonpath, struct zbx_json_parse *out)
{
	int			i, ret = FAIL;
	struct zbx_json_parse	object;

	object = *jp;

	if (0 == jsonpath->definite)
	{
		zbx_set_json_strerror("cannot use indefinite path when opening sub element");
		goto out;
	}

	for (i = 0; i < jsonpath->segments_num; i++)
	{
		const char		*p;
		zbx_jsonpath_segment_t	*segment = &jsonpath->segments[i];

		if (ZBX_JSONPATH_SEGMENT_MATCH_LIST != segment->type)
		{
			zbx_set_json_strerror("jsonpath segment %d is not a name or index", i + 1);
			goto out;
		}

		if (ZBX_JSONPATH_LIST_INDEX == segment->data.list.type)
		{
			int	index;

			if ('[' != *object.start)
				goto out;

			memcpy(&index, segment->data.list.values->data, sizeof(int));

			for (p = NULL; NULL != (p = zbx_json_next(&object, p)) && 0 != index; index--)
				;

			if (0 != index || NULL == p)
			{
				zbx_set_json_strerror("array index out of bounds in jsonpath segment %d", i + 1);
				goto out;
			}
		}
		else
		{
			if (NULL == (p = zbx_json_pair_by_name(&object, (char *)&segment->data.list.values->data)))
			{
				zbx_set_json_strerror("object not found in jsonpath segment %d", i + 1);
				goto out;
			}
		}

		object.start = p;

		if (NULL == (object.end = __zbx_json_rbracket(p)))
			object.end = p + json_parse_value(p, NULL, NULL) - 1;
	}

	*out = object;
	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_open_path                                               *
 *                                                                            *
 * Purpose: opens an object by definite json path                             *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Comments: Only direct path to single object in dot or bracket notation     *
 *           is supported.                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_open_path(const struct zbx_json_parse *jp, const char *path, struct zbx_json_parse *out)
{
	zbx_jsonpath_t		jsonpath;
	int			ret;

	if (FAIL == zbx_jsonpath_compile(path, &jsonpath))
		return FAIL;

	ret = json_open_path(jp, &jsonpath, out);

	zbx_jsonpath_clear(&jsonpath);

	return ret;
}

zbx_json_type_t	zbx_json_valuetype(const char *p)
{
	return __zbx_json_type(p);
}
