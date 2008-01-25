/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "zbxjson.h"

#define ZBX_JSON_READABLE

int	zbx_json_init(struct zbx_json *j, size_t allocate)
{
	assert(j);
/*
printf("zbx_json_init()\n");
*/
	j->buffer = NULL;
	j->buffer_allocated = allocate;
	j->buffer = zbx_malloc(j->buffer, j->buffer_allocated);
	j->buffer_offset = 0;
	j->buffer_size = 0;
	j->status = ZBX_JSON_EMPTY;
	j->level = 0;
	*j->buffer = '\0';
/*
printf("zbx_json_init()      [sizeof:%4d] [size:%4d] [offset:%4d] [status:%d] %s\n", j->buffer_allocated, j->buffer_size, j->buffer_offset, j->status, j->buffer);
*/
	return zbx_json_addobject(j, NULL);
}

int	zbx_json_free(struct zbx_json *j)
{
	assert(j);

	zbx_free(j->buffer);
/*
printf("zbx_json_free()\n");
*/
	return SUCCEED;
}

static int	__zbx_json_realloc(struct zbx_json *j, size_t need)
{
	int	realloc = 0;

	while (need > j->buffer_allocated) {
		if (0 == j->buffer_allocated)
			j->buffer_allocated = 1024;
		else
			j->buffer_allocated *= 2;
		realloc = 1;
	}

	if (1 == realloc)/* {
printf("----- zbx_json_realloc() [need:%zd] [allocated:%zd]\n", need, j->buffer_allocated);*/
		j->buffer = zbx_realloc(j->buffer, j->buffer_allocated);
/*	}*/

	return SUCCEED;
}

static size_t	__zbx_json_stringsize(const char *string, zbx_json_type_t type)
{
	size_t		len;
	const char	*sptr;
	char		buffer[] = {"null"};

	len = 0;
	for (sptr = (NULL != string) ? string : buffer; *sptr != '\0'; sptr++) {
		switch (*sptr) {
		case '"':  /* quatation mark */
		case '\\': /* reverse solidus */
		case '/':  /* solidus */
		case '\b': /* backspace */
		case '\f': /* formfeed */
		case '\n': /* newline */
		case '\r': /* carriage return */
		case '\t': /* horizontal tab */
			len += 2;
			break;
		default:
			if ((u_char)*sptr < 32)
				len += 6;
			else
				len++;
		}
	}

	if (NULL != string && type == ZBX_JSON_TYPE_STRING)
		len += 2; /* "" */
	
	return len;
}

static char	*__zbx_json_insstring(char *p, const char *string, zbx_json_type_t type)
{
	const char	*sptr;
	char		buffer[] = {"null"};

	if (NULL != string && type == ZBX_JSON_TYPE_STRING)
		*p++ = '"';
	
	for (sptr = (NULL != string) ? string : buffer; *sptr != '\0'; sptr++) {
		switch (*sptr) {
		case '"': *p++ = '\\'; *p++ = '"'; break; /* quatation mark */
		case '\\': *p++ = '\\'; *p++ = '\\'; break; /* reverse solidus */
		case '/': *p++ = '\\'; *p++ = '/'; break; /* solidus */
		case '\b': *p++ = '\\'; *p++ = 'b'; break; //* backspace */
		case '\f': *p++ = '\\'; *p++ = 'f'; break; /* formfeed */
		case '\n': *p++ = '\\'; *p++ = 'n'; break; /* newline */
		case '\r': *p++ = '\\'; *p++ = 'r'; break; /* carriage return */
		case '\t': *p++ = '\\'; *p++ = 't'; break; /* horizontal tab */
		default:
			if ((u_char)*sptr < 32) {
				*p++ = '\\';
				*p++ = 'u';
				*p++ = '0';
				*p++ = '0';
				*p++ = zbx_num2hex( (*sptr >> 4) & 0xf );
				*p++ = zbx_num2hex( *sptr & 0xf );
			} else
				*p++ = *sptr;
		}
	}

	if (NULL != string && type == ZBX_JSON_TYPE_STRING)
		*p++ = '"';

	return p;
}

static int	___zbx_json_addobject(struct zbx_json *j, const char *name, int object)
{
	size_t	len = 2; /* brackets */
	char	*p, *psrc, *pdst;
#ifdef ZBX_JSON_READABLE
	int	i;
#endif

	assert(j);

#ifdef ZBX_JSON_READABLE
	len += j->level;
	if (j->level)
		len++;
#endif

	if (j->status == ZBX_JSON_COMMA)
		len++; /* , */

	if (NULL != name) {
		len += __zbx_json_stringsize(name, 1);
		len += 1; /* : */
	}
	
	__zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (j->status == ZBX_JSON_COMMA)
		*p++ = ',';

#ifdef ZBX_JSON_READABLE
	if (j->level)
		*p++ = '\n';
	for (i = 0; i < j->level; i ++)
		*p++ = '\t';
#endif
	if (NULL != name) {
		p = __zbx_json_insstring(p, name, 1);
		*p++ = ':';
	}

	*p++ = object ? '{' : '[';
	*p = object ? '}' : ']';

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->level++;
	j->status = ZBX_JSON_EMPTY;

	return SUCCEED;
}

int	zbx_json_addobject(struct zbx_json *j, const char *name)
{
	return  ___zbx_json_addobject(j, name, 1);
}

int	zbx_json_addarray(struct zbx_json *j, const char *name)
{
	return  ___zbx_json_addobject(j, name, 0);
}

static int	__zbx_json_addstring(struct zbx_json *j, const char *name, const char *string, zbx_json_type_t type)
{
	size_t	len = 0;
	char	*p, *psrc, *pdst;
#ifdef ZBX_JSON_READABLE
	int	i;
#endif

	assert(j);

	if (j->status == ZBX_JSON_COMMA)
		len++; /* , */

	if (NULL != name) {
		len += __zbx_json_stringsize(name, 1);
		len += 1; /* : */
#ifdef ZBX_JSON_READABLE
		len += j->level + 1;
#endif
	}
	len += __zbx_json_stringsize(string, type);
	
	__zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (j->status == ZBX_JSON_COMMA) {
		*p++ = ',';
	}

	if (NULL != name) {
#ifdef ZBX_JSON_READABLE
		*p++ = '\n';
		for (i = 0; i < j->level; i ++)
			*p++ = '\t';
#endif
		p = __zbx_json_insstring(p, name, 1);
		*p++ = ':';
	}
	p = __zbx_json_insstring(p, string, type);

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->status = ZBX_JSON_COMMA;

	return SUCCEED;
}

int	zbx_json_addstring(struct zbx_json *j, const char *name, const char *string)
{
	return __zbx_json_addstring(j, name, string, 1);
}

/*int	zbx_json_adduint64(struct zbx_json *j, const char *name, const zbx_uint64_t *value)
{
	char	buffer[21];*/ /* strlen("18446744073709551615") == 20 */
/*	char	*string = NULL;

	if (NULL != value) {
		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, *value);
		string = buffer;
	}
	
	return __zbx_json_addstring(j, name, string, 0);
}

int	zbx_json_adddouble(struct zbx_json *j, const char *name, const double *value)
{
	char	buffer[MAX_STRING_LEN];
	char	*string = NULL;

	if (NULL != value) {
		zbx_snprintf(buffer, sizeof(buffer), "%f", *value);
		string = buffer;
	}
	
	return __zbx_json_addstring(j, name, string, 0);
}
*/
int	zbx_json_return(struct zbx_json *j)
{
	if (j->level == 1)
		return FAIL;

	j->level--;
	j->buffer_offset++;
	j->status = ZBX_JSON_COMMA;

	return SUCCEED;
}

int	zbx_json_open(char *buffer, struct zbx_json_parse *jp)
{
	char	*p;
#ifdef ZBX_JSON_READABLE
	char	*i;
#endif

	jp->start = NULL;
	jp->end = NULL;

	if (*buffer == '{')
		jp->start = buffer + 1;
	else
		return FAIL;
	
	p = buffer;
#ifdef ZBX_JSON_READABLE
	i = buffer;
	while (*i != '\0') {
		if (*i != '\t' && *i != '\n')
			*p++ = *i;
		i++;
	}
	*p = '\0';
#else
	while (*++p != '\0')
		;
#endif
	
	if (*--p == '}')
		jp->end = p;
	else
		return FAIL;
/*fprintf(stderr, "----- [%s] [%s] [%c]\n", jp->start, jp->end, *p);*/

	return SUCCEED;
}

const char	*zbx_json_nextfield(struct zbx_json_parse *jp, const char *p)
{
	int	level = 0;
	int	state = 0; /* o - outside string; 1 - inside string */

/*fprintf(stderr, "----- [%s]\n", jp->pair_start);*/
	while (p <= jp->end) {
/*fprintf(stderr, "----- [level:%d] [state:%d] [%c]\n", level, state, *jp->pair_end);*/
		switch (*p) {
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
			if (0 == state) {
				if (0 == level)
					return NULL;
				level--;
			}
			break;
		case ',':
			if (0 == state)
				if (0 == level)
					return ++p;
			break;
		}
		p++;
	}
	return NULL;
}

const char	*zbx_json_decodestring(const char *p, char *string, size_t len)
{
	int	state = 0; /* 0 - init; 1 - inside string */
	char	*o = string;
	u_char	c;

	if ('"' != *p)
		return NULL;

	while (*p != '\0') { /* this should never happen */
		if (*p == '"') {
			if (state == 1) {
				*o = '\0';
				return ++p;
			}
			state = 1;
		} else if (state == 1 && o - string < len - 1/* '\0' */) {
			if (*p == '\\') {
				switch (*++p) {
				case '"': 
				case '\\': 
				case '/': *o++ = *p; break;
				case 'b': *o++ = '\b'; break;
				case 'f': *o++ = '\f'; break;
				case 'n': *o++ = '\n'; break;
				case 'r': *o++ = '\r'; break;
				case 't': *o++ = '\t'; break;
				case 'u':
					p += 2; /* '00' */
					c = zbx_hex2num( *p++ ) << 4;
					c += zbx_hex2num( *p );
					*o++ = (char)c;
					break;
				default:
					/* this should never happen */;
				}
			} else
				*o++ = *p;
		}
		p++;
	}
	return NULL;
}

const char	*zbx_json_getvalue_ptr(struct zbx_json_parse *jp, const char *name)
{
	char		buffer[MAX_STRING_LEN];
	const char	*p;

	for (p = jp->start; p != NULL; p = zbx_json_nextfield(jp, p)) {
		if (NULL == (p = zbx_json_decodestring(p, buffer, sizeof(buffer))))
			return NULL;
		       
		if (0 == strcmp(name, buffer))
			return *p == ':' ? ++p : NULL;
	}
/*fprintf(stderr, "----- [%s] FAIL\n", name);*/
	return NULL;
}

zbx_json_type_t	zbx_json_getvalue_type(const char *p)
{
	if (p[0] == '"')
		return ZBX_JSON_TYPE_STRING;
	if (p[0] == 'n' && p[1] == 'u' && p[2] == 'l' && p[3] == 'l')
		return ZBX_JSON_TYPE_NULL;
	return ZBX_JSON_TYPE_UNKNOWN;
}
