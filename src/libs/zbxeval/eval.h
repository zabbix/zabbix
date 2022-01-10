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

#ifndef ZABBIX_EVAL_H
#define ZABBIX_EVAL_H

#include "common.h"
#include "zbxeval.h"

int	eval_suffixed_number_parse(const char *value, char *suffix);
int	eval_compare_token(const zbx_eval_context_t *ctx, const zbx_strloc_t *loc, const char *text,
		size_t len);
size_t	eval_parse_query(const char *str, const char **phost, const char **pkey, const char **pfilter);

#endif
