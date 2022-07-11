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

#ifndef ZABBIX_JSON_H
#define ZABBIX_JSON_H

#include "common.h"

#define SKIP_WHITESPACE(src)	\
	while ('\0' != *(src) && NULL != strchr(ZBX_WHITESPACE, *(src))) (src)++

/* can only be used on non empty string */
#define SKIP_WHITESPACE_NEXT(src)\
	(src)++; \
	SKIP_WHITESPACE(src)

void	zbx_set_json_strerror(const char *fmt, ...) __zbx_attr_format_printf(1, 2);

#endif
