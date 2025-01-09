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

#ifndef ZABBIX_EVAL_H
#define ZABBIX_EVAL_H

#include "zbxeval.h"

int	eval_suffixed_number_parse(const char *value, char *suffix);
int	eval_compare_token(const zbx_eval_context_t *ctx, const zbx_strloc_t *loc, const char *text,
		size_t len);
size_t	eval_parse_query(const char *str, const char **phost, const char **pkey, const char **pfilter);

#endif
