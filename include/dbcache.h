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
#define ZBX_DC_TREND struct zbx_dc_trend_type
#define ZBX_DC_NEXTCHECK struct zbx_dc_nextcheck_type

#define	ZBX_HISTORY_SIZE	100000
/* Must be less than ZBX_HISTORY_SIZE */
#define	ZBX_SYNC_MAX		1000
#define	ZBX_TREND_SIZE		100000
#define	ZBX_TEXTBUFFER_SIZE	16384*1024

#define ZBX_SYNC_PARTIAL	0
#define	ZBX_SYNC_FULL		1

extern char *CONFIG_FILE;

typedef union{
	double		value_float;
	zbx_uint64_t	value_uint64;
	char		*value_str;
} history_value_t;

typedef union {
	double		value_float;
	zbx_uint64_t	value_uint64;
} trend_value_t;

ZBX_DC_HISTORY
{
	zbx_uint64_t	itemid;
	int		clock;
	int		value_type;
	history_value_t	value;
	int		timestamp;
	char		*source;
	int		severity;
	int		lastlogsize;
};

ZBX_DC_TREND
{
	zbx_uint64_t	itemid;
	int		clock;
	int		num;
	int		value_type;
	trend_value_t	value_min;
	trend_value_t	value_avg;
	trend_value_t	value_max;
};

ZBX_DC_CACHE
{
	int		history_first;
	int		history_num;
	int		trends_num;
	ZBX_DC_HISTORY	history[ZBX_HISTORY_SIZE];
	ZBX_DC_TREND	trends[ZBX_TREND_SIZE];
	char		text[ZBX_TEXTBUFFER_SIZE];
	char		*last_text;
};

ZBX_DC_NEXTCHECK
{
	zbx_uint64_t	itemid;
	time_t		clock;
	/* for not supported items */
	char		*error_msg;
};

void	DCadd_history(zbx_uint64_t itemid, double value, int clock);
void	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock);
void	DCadd_history_str(zbx_uint64_t itemid, char *value, int clock);
void	DCadd_history_text(zbx_uint64_t itemid, char *value, int clock);
void	DCadd_history_log(zbx_uint64_t itemid, char *value, int clock, int timestamp, char *source, int severity,
		int lastlogsize);
int	DCsync_history(int sync_type);
void	init_database_cache(zbx_process_t p);
void	free_database_cache(void);

void	DCinit_nextchecks();
void	DCadd_nextcheck(DB_ITEM *item, time_t now, time_t timediff, const char *error_msg);
void	DCflush_nextchecks();

#endif
