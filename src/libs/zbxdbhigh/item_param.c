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

#include "zbxdbhigh.h"

ZBX_PTR_VECTOR_IMPL(item_param_ptr, zbx_item_param_t *)

zbx_item_param_t	*zbx_item_param_create(const char *item_param_name,
		const char *item_param_value)
{
	zbx_item_param_t	*item_param;

	item_param = (zbx_item_param_t *)zbx_malloc(NULL, sizeof(zbx_item_param_t));

	item_param->item_parameterid = 0;
	item_param->upd_flags = ZBX_FLAG_ITEM_PARAM_UPDATE_RESET_FLAG;
	item_param->name = zbx_strdup(NULL, item_param_name);
	item_param->name_orig = NULL;
	item_param->value = zbx_strdup(NULL, item_param_value);
	item_param->value_orig = NULL;

	return item_param;
}

void	zbx_item_params_free(zbx_item_param_t *param)
{

	if (0 != (param->upd_flags & ZBX_FLAG_ITEM_PARAM_UPDATE_NAME))
		zbx_free(param->name_orig);
	zbx_free(param->name);

	if (0 != (param->upd_flags & ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE))
		zbx_free(param->value_orig);
	zbx_free(param->value);

	zbx_free(param);
}
