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

#ifndef ZABBIX_ZJSON_H
#define ZABBIX_ZJSON_H

#include <stdarg.h>

typedef enum
{
	ZBX_JSON_EMPTY = 0,
	ZBX_JSON_COMMA
} zbx_json_status_t;

struct zbx_json {
	char	*buffer;
	size_t	buffer_allocated;
	size_t	buffer_offset;
	size_t	buffer_size;
	zbx_json_status_t	status;
	int	level;
};

int	zbx_json_init(struct zbx_json *j, size_t allocate);
int	zbx_json_free(struct zbx_json *j);
int	zbx_json_addobject(struct zbx_json *j, const char *name);
int	zbx_json_addarray(struct zbx_json *j, const char *name);
int	zbx_json_addstring(struct zbx_json *j, const char *name, const char *string);
int	zbx_json_addunit64(struct zbx_json *j, const char *name, zbx_uint64_t value);
int	zbx_json_adddouble(struct zbx_json *j, const char *name, const double *value);
int	zbx_json_return(struct zbx_json *j);

#endif /* ZABBIX_ZJSON_H */
