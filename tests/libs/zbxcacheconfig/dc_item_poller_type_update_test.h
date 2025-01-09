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

#ifndef DC_ITEM_POLLER_TYPE_UPDATE_TEST_H
#define DC_ITEM_POLLER_TYPE_UPDATE_TEST_H

void	DCitem_poller_type_update_test(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, int flags);
void	init_test_configuration_cache(zbx_get_config_forks_f get_config_forks);

#endif /* DC_ITEM_POLLER_TYPE_UPDATE_TEST_H */
