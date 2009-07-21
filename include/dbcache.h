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
#define ZBX_DC_STATS struct zbx_dc_stats_type
#define ZBX_DC_HISTORY struct zbx_dc_history_type
#define ZBX_DC_TREND struct zbx_dc_trend_type
#define ZBX_DC_NEXTCHECK struct zbx_dc_nextcheck_type
#define ZBX_DC_ID struct zbx_dc_id_type
#define ZBX_DC_IDS struct zbx_dc_ids_type

#define	ZBX_HISTORY_SIZE	1000000
/* Must be less than ZBX_HISTORY_SIZE */
#define	ZBX_SYNC_MAX		10000
#define	ZBX_TREND_SIZE		100000
#define	ZBX_TEXTBUFFER_SIZE	16384*1024
#define ZBX_IDS_SIZE		6

#define ZBX_SYNC_PARTIAL	0
#define	ZBX_SYNC_FULL		1

extern char *CONFIG_FILE;

typedef union{
	double		value_float;
	zbx_uint64_t	value_uint64;
	char		*value_str;
} history_value_t;

ZBX_DC_ID
{
	char		table_name[64], field_name[64];
	zbx_uint64_t	lastid;
};

ZBX_DC_IDS
{
	ZBX_DC_ID	id[ZBX_IDS_SIZE];
};

ZBX_DC_HISTORY
{
	zbx_uint64_t	itemid;
	int		clock;
	int		value_type;
	history_value_t	value_orig;
	history_value_t	value;
	int		value_null;
	int		timestamp;
	char		*source;
	int		severity;
	int		logeventid;
	int		lastlogsize;
	int		keep_history;
	int		keep_trends;
	int		functions;
};

ZBX_DC_TREND
{
	zbx_uint64_t	itemid;
	int		clock;
	int		num;
	int		value_type;
	history_value_t	value_min;
	history_value_t	value_avg;
	history_value_t	value_max;
};

ZBX_DC_STATS
{
	zbx_uint64_t	buffer_history_counter;	/* Total number of record added to the history buffer */
	zbx_uint64_t	buffer_history_total;	/* ZBX_HISTORY_SIZE */
	zbx_uint64_t	buffer_history_used;
	zbx_uint64_t	buffer_history_free;
	zbx_uint64_t	buffer_trend_total;	/* ZBX_TREND_SIZE */
	zbx_uint64_t	buffer_trend_used;
	zbx_uint64_t	buffer_trend_free;
	zbx_uint64_t	buffer_history_num_float;	/* Number of floats in the buffer */
	zbx_uint64_t	buffer_history_num_uint;
	zbx_uint64_t	buffer_history_num_str;
	zbx_uint64_t	buffer_history_num_log;
	zbx_uint64_t	buffer_history_num_text;
	zbx_uint64_t	history_counter;	/* Total number of saved values in th DB */
	zbx_uint64_t	history_float_counter;	/* Number of saved float values in th DB */
	zbx_uint64_t	history_uint_counter;	/* Number of saved uint values in the DB */
	zbx_uint64_t	history_str_counter;	/* Number of saved str values in the DB */
	zbx_uint64_t	history_log_counter;	/* Number of saved log values in the DB */
	zbx_uint64_t	history_text_counter;	/* Number of saved text values in the DB */
	zbx_uint64_t	add_trend_counter;	/* Total number of saved trends in the DB */
	zbx_uint64_t	add_trend_float_counter;/* Number of saved float trends in the DB */
	zbx_uint64_t	add_trend_uint_counter;	/* Number of saved uint trends in the DB */
	zbx_uint64_t	update_trigger_counter;	/* Total number of updated triggers */
	zbx_uint64_t	queries_counter;	/* Total number of executed queries */
};

ZBX_DC_CACHE
{
	int		history_first;
	int		history_num;
	int		trends_num;
	ZBX_DC_STATS	stats;
	ZBX_DC_HISTORY	history[ZBX_HISTORY_SIZE];
	ZBX_DC_TREND	trends[ZBX_TREND_SIZE];
	char		text[ZBX_TEXTBUFFER_SIZE];
	char		*last_text;
};

ZBX_DC_NEXTCHECK
{
	zbx_uint64_t	itemid;
	time_t		now, nextcheck;
	/* for not supported items */
	char		*error_msg;
};

void	DCadd_history(zbx_uint64_t itemid, double value_orig, int clock);
void	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value_orig, int clock);
void	DCadd_history_str(zbx_uint64_t itemid, char *value_orig, int clock);
void	DCadd_history_text(zbx_uint64_t itemid, char *value_orig, int clock);
void	DCadd_history_log(zbx_uint64_t itemid, char *value_orig, int clock, int timestamp, char *source, int severity,
		int logeventid,int lastlogsize);
int	DCsync_history(int sync_type);
void	init_database_cache(zbx_process_t p);
void	free_database_cache(void);

void	DCinit_nextchecks();
void	DCadd_nextcheck(DB_ITEM *item, time_t now, time_t timediff, const char *error_msg);
void	DCflush_nextchecks();
void	DCget_stats(ZBX_DC_STATS *stats);

zbx_uint64_t	DCget_nextid(const char *table_name, const char *field_name, int num);

#endif
