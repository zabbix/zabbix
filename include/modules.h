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

#ifndef ZABBIX_MODULES_H
#define ZABBIX_MODULES_H

/* agent request structure */
typedef struct
{
	char	*key;
	int	nparam;
	int	timeout;
	char	**params;
}
AGENT_REQUEST;

#define ZBX_MODULE_OK		0
#define ZBX_MODULE_FAIL		-1

#define ZBX_MODULE_FUNC_INIT	"zbx_module_init"
#define ZBX_MODULE_FUNC_LIST	"zbx_module_list"
// TODO different name? like: process_key, alert, external commands, etc
#define ZBX_MODULE_FUNC_PROCESS	"zbx_module_process"
#define ZBX_MODULE_FUNC_UNINIT	"zbx_module_uninit"

#define get_rparam(request,num) ((request->nparam > num) ? request->params[num] : NULL)

void	load_modules(const char *path, char **modules);
void	unload_modules();

#endif
