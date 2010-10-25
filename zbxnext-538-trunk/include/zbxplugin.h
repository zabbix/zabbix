/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_ZBXPLUGIN_H
#define ZABBIX_ZBXPLUGIN_H

#ifdef _WINDOWS

#	define __zabbix_api __cdecl
typedef HMODULE ZBX_MODULE;

#else /* not _WINDOWS */

#	define __zabbix_api
typedef void* ZBX_MODULE;

#endif /* _WINDOWS */

#define MAX_CMDNAME	256

typedef struct
{
   char name[MAX_CMDNAME];
   int (__zabbix_api * handler_float)(char *,double *); /* Handler if return value is floating point numeric */
   int (__zabbix_api * handler_string)(char *,char **); /* Handler if return value is string */
} ZBX_PLUGIN_ARGS;

struct zbx_plugin_list
{
	struct zbx_plugin_list	*next;	/* Pointer to next element in a chain */

	ZBX_MODULE	hModule;	/* DLL module handle */
	int		runned;
	int	(__zabbix_api * init)(char *,ZBX_PLUGIN_ARGS **);
	void	(__zabbix_api * shutdown)(void);
	ZBX_PLUGIN_ARGS *args;       /* List of subagent's commands */
};

typedef struct zbx_plugin_list ZBX_PLUGIN_LIST;

extern ZBX_PLUGIN_LIST	*PluginsList;

int add_plugin(char *args);

#endif /* ZABBIX_ZBXPLUGIN_H */
