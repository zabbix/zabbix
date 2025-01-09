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

#include "zbxalerter.h"

#include "zbxalgo.h"

ZBX_PTR_VECTOR_IMPL(alerter_dispatch_result, zbx_alerter_dispatch_result_t *)

void	zbx_alerter_dispatch_result_free(zbx_alerter_dispatch_result_t *result)
{
	zbx_free(result->recipient);
	zbx_free(result->info);
	zbx_free(result);
}
