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

#ifndef ZABBIX_CONFIGCACHE_MOCK_H
#define ZABBIX_CONFIGCACHE_MOCK_H

void	mock_config_init(void);
void	mock_config_free(void);

void	mock_config_load_user_macros(const char *path);
void	mock_config_load_hosts(const char *path);

#endif
