/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcacheconfig.h"

int	zbx_dc_get_item_key(zbx_uint64_t itemid, char **key)
{
	zbx_dc_item_t	dc_item;
	int		ret = FAIL, errcode;

	zbx_dc_config_get_items_by_itemids(&dc_item, &itemid, &errcode, 1);

	if (SUCCEED == errcode)
	{
		zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros_masked();
		char			*key_tmp = zbx_strdup(NULL, dc_item.key_orig);

		zbx_substitute_item_key_params_default(&key_tmp, NULL, 0, um_handle, dc_item.host.hostid,
				dc_item.host.host, dc_item.host.name, dc_item.itemid, &dc_item.interface);
		zbx_dc_close_user_macros(um_handle);

		zbx_free(*key);
		*key = key_tmp;
		ret = SUCCEED;
	}

	zbx_dc_config_clean_items(&dc_item, &errcode, 1);

	return ret;
}
