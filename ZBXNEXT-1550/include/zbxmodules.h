/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_ZBXMODULES_H
#define ZABBIX_ZBXMODULES_H

#define ZBX_MODULE_FUNC_INIT		"zbx_module_init"
#define ZBX_MODULE_FUNC_VERSION		"zbx_module_version"
#define ZBX_MODULE_FUNC_ITEM_LIST	"zbx_module_item_list"
#define ZBX_MODULE_FUNC_ITEM_PROCESS	"zbx_module_item_process"
#define ZBX_MODULE_FUNC_ITEM_TIMEOUT	"zbx_module_item_timeout"
#define ZBX_MODULE_FUNC_UNINIT		"zbx_module_uninit"

int	load_modules(const char *path, char **modules, int timeout);
void	unload_modules();

#endif
