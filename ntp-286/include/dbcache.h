/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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

#ifndef ZABBIX_DBCACHE_H
#define ZABBIX_DBCACHE_H

#define ZBX_DC_IDS struct zbx_dc_ids_type
#define ZBX_DC_CACHE struct zbx_dc_cache_type

#define ZBX_IDS_SIZE	2

extern char *CONFIG_FILE;

ZBX_DC_IDS
{
	char		table_name[64], field_name[64];
	zbx_uint64_t	lastid;
};

ZBX_DC_CACHE
{
	ZBX_DC_IDS	ids[ZBX_IDS_SIZE];
};

zbx_uint64_t	DCget_nextid(const char *table_name, const char *field_name);

void	init_database_cache(void);
void	free_database_cache(void);

#endif
