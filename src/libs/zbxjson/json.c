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

int	zbx_json_realloc(struct zbx_json *j, size_t need)
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
printf("zbx_json_realloc() %d\n", j->buffer_allocated);*/
		j->buffer = zbx_realloc(j->buffer, j->buffer_allocated);
/*	}*/

	return SUCCEED;
}

size_t	zbx_json_stringsize(const char *string, int type_string)
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
			len++;
			break;
		}
		len++;
	}

	if (NULL != string && 0 != type_string)
		len += 2; /* "" */
	
	return len;
}

char	*zbx_json_insstring(char *p, const char *string, int type_string)
{
	size_t		len;
	const char	*sptr;
	char		buffer[] = {"null"};

	if (NULL != string && 0 != type_string)
		*p++ = '\"';
	
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
			*p++ = '\\';
			break;
		}
		switch (*sptr) {
		case '\b': *p++ = 'b'; break; /* backspace */
		case '\f': *p++ = 'f'; break; /* formfeed */
		case '\n': *p++ = 'n'; break; /* newline */
		case '\r': *p++ = 'r'; break; /* carriage return */
		case '\t': *p++ = 't'; break; /* horizontal tab */
		default:
			*p++ = *sptr;
		}
	}

	if (NULL != string && 0 != type_string)
		*p++ = '\"';

	return p;
}

int	___zbx_json_addobject(struct zbx_json *j, const char *name, char lbracket, char rbracket)
{
	size_t	len = 2; /* brackets */
	char	*p, *psrc, *pdst;

	assert(j);

	if (j->status == ZBX_JSON_COMMA)
		len++; /* , */

	if (NULL != name) {
		len += zbx_json_stringsize(name, 1);
		len += 1; /* : */
	}
	
	zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (j->status == ZBX_JSON_COMMA)
		*p++ = ',';

	if (NULL != name) {
		p = zbx_json_insstring(p, name, 1);
		*p++ = ':';
	}

	*p++ = lbracket;
	*p = rbracket;

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->level++;
	j->status = ZBX_JSON_EMPTY;
/*
printf("zbx_json_addobject() [sizeof:%4d] [size:%4d] [offset:%4d] [status:%d] %s\n", j->buffer_allocated, j->buffer_size, j->buffer_offset, j->status, j->buffer);
*/
	return SUCCEED;
}

int	zbx_json_addobject(struct zbx_json *j, const char *name)
{
	return  ___zbx_json_addobject(j, name, '{', '}');
}

int	zbx_json_addarray(struct zbx_json *j, const char *name)
{
	assert(name);

	return  ___zbx_json_addobject(j, name, '[', ']');
}

int	__zbx_json_addstring(struct zbx_json *j, const char *name, const char *string, int type_string)
{
	size_t	len = 0;
	char	*p, *psrc, *pdst;

	assert(j);

	if (j->status == ZBX_JSON_COMMA)
		len++; /* , */

	if (NULL != name) {
		len += zbx_json_stringsize(name, 1);
		len += 1; /* : */
	}
	len += zbx_json_stringsize(string, type_string);
	
	zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (j->status == ZBX_JSON_COMMA)
		*p++ = ',';

	if (NULL != name) {
		p = zbx_json_insstring(p, name, 1);
		*p++ = ':';
	}
	p = zbx_json_insstring(p, string, type_string);

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->status = ZBX_JSON_COMMA;
/*
printf("zbx_json_addstring() [sizeof:%4d] [size:%4d] [offset:%4d] [status:%d] %s\n", j->buffer_allocated, j->buffer_size, j->buffer_offset, j->status, j->buffer);
*/
	return SUCCEED;
}

int	zbx_json_addstring(struct zbx_json *j, const char *name, const char *string)
{
	return __zbx_json_addstring(j, name, string, 1);
}

int	zbx_json_adduint64(struct zbx_json *j, const char *name, const zbx_uint64_t *value)
{
	char	buffer[21]; /* strlen("18446744073709551615") == 20 */
	char	*string = NULL;

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

int	zbx_json_return(struct zbx_json *j)
{
	if (j->level == 1)
		return FAIL;

	j->level--;
	j->buffer_offset++;
	j->status = ZBX_JSON_COMMA;

	return SUCCEED;
}
