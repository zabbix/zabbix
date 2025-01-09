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

#ifndef ZABBIX_GLOBAL_H
#define ZABBIX_GLOBAL_H

#include "zbxembed.h"
#include "duktape.h"

void	es_init_global_functions(zbx_es_t *es);
char	*es_get_buffer_dyn(duk_context *ctx, int index, duk_size_t *len);

#endif
