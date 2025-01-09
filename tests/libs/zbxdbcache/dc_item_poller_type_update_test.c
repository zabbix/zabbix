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

#include "dc_item_poller_type_update_test.h"

void	DCitem_poller_type_update_test(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, int flags)
{
	DCitem_poller_type_update(dc_item, dc_host, flags);
}

void	init_test_configuration_cache(zbx_get_config_forks_f get_config_forks)
{
	get_config_forks_cb = get_config_forks;
	config = (zbx_dc_config_t *)zbx_malloc(NULL, sizeof(zbx_dc_config_t));
}
