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

#include "zbxcommon.h"

#ifndef ZABBIX_MOCK_EXPRESSION_EVAL_H
#define ZABBIX_MOCK_EXPRESSION_EVAL_H

zbx_uint64_t	mock_eval_read_rules(const char *path);
void	mock_eval_read_values(zbx_eval_context_t *ctx, const char *path);

void	mock_compare_stack(const zbx_eval_context_t *ctx, const char *path);
void	mock_dump_stack(const zbx_eval_context_t *ctx);

#endif
