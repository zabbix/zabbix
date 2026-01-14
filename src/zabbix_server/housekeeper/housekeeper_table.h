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

#ifndef ZABBIX_HOUSEKEEPER_TABLE_H
#define ZABBIX_HOUSEKEEPER_TABLE_H

void	housekeeper_init(void);
void	housekeeper_deinit(void);

void	housekeeper_process(int config_max_hk_delete, int *deleted_history, int *deleted_events, int *deleted_problems);

int	hk_cfg_history_mode(void);
int	hk_cfg_trends_mode(void);
int	hk_cfg_events_mode(void);

int	hk_delete_from_table(const char *tablename, const char *filter, int limit);

#endif
