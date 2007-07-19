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

#define ZBX_DC_CACHE struct zbx_dc_cache_type
#define ZBX_DC_HISTORY struct zbx_dc_history_type
#define ZBX_DC_ITEM struct zbx_dc_item_type
#define ZBX_DC_POOL struct zbx_dc_pool_type
#define ZBX_DC_TREND struct zbx_dc_trend_type

#define	ZBX_HISTORY_SIZE	100000
#define	ZBX_TREND_SIZE		100000
#define	ZBX_ITEMS_SIZE		10000

extern char *CONFIG_FILE;

ZBX_DC_HISTORY
{
	zbx_uint64_t	itemid;
	int		clock;
	int		value_type;
	union
	{
		double		value_float;
		zbx_uint64_t	value_uint64;
		char		value_str[MAX_HISTORY_STR_LEN+1];
	} value;
};

ZBX_DC_TREND
{
	zbx_uint64_t	itemid;
	int		clock;
	int		num;
	double		value_min;
	double		value_max;
	double		value_avg;
};

ZBX_DC_ITEM
{
	zbx_uint64_t	itemid;
	ZBX_DC_TREND	trend;
};

ZBX_DC_POOL
{
	int		history_count;
	ZBX_DC_HISTORY	history[ZBX_HISTORY_SIZE];
	int		trends_count;
	ZBX_DC_TREND	trends[ZBX_TREND_SIZE];
};

ZBX_DC_CACHE
{
	int		items_count;
	ZBX_DC_ITEM	items[ZBX_ITEMS_SIZE];
	ZBX_DC_POOL	pool;
};

int	DCadd_trend(zbx_uint64_t itemid, double value, int clock);
int	DCadd_history(zbx_uint64_t itemid, double value, int clock);
int	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock);
int	DCadd_history_str(zbx_uint64_t itemid, char *value, int clock);
void	DCshow(void);
void	init_database_cache(void);
void	free_database_cache(void);

#endif
