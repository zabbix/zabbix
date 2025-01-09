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

#ifndef ZABBIX_JSON_H
#define ZABBIX_JSON_H

#include "zbxstr.h"

#define SKIP_WHITESPACE(src)	\
	while ('\0' != *(src) && NULL != strchr(ZBX_WHITESPACE, *(src))) (src)++

/* can only be used on non empty string */
#define SKIP_WHITESPACE_NEXT(src)\
	(src)++; \
	SKIP_WHITESPACE(src)

void	zbx_set_json_strerror(const char *fmt, ...) __zbx_attr_format_printf(1, 2);

const char	*json_copy_string(const char *p, char *out, size_t size);
unsigned int	zbx_json_decode_character(const char **p, unsigned char *bytes);

#endif
