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

#include "common.h"
#include "log.h"
#include "zlog.h"

#include "db.h"
#include "dbcache.h"
#include "mutexs.h"

#define	LOCK_CACHE	zbx_mutex_lock(&cache_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(&cache_lock)

#define ZBX_GET_SHM_DBCACHE_KEY(smk_key) 														\
	{if( -1 == (shm_key = ftok(CONFIG_FILE, (int)'c') )) 										\
        { 																\
                zbx_error("Can not create IPC key for path '%s', try to create for path '.' [%s]", CONFIG_FILE, strerror(errno)); 	\
                if( -1 == (shm_key = ftok(".", (int)'c') )) 										\
                { 															\
                        zbx_error("Can not create IPC key for path '.' [%s]", strerror(errno)); 					\
                        exit(1); 													\
                } 															\
        }}

ZBX_DC_CACHE		*cache = NULL;
static ZBX_MUTEX	cache_lock;

/******************************************************************************
 *                                                                            *
 * Function: DCsync                                                           *
 *                                                                            *
 * Purpose: writes updates and new data from pool to database                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCsync()
{
	int	i;

	ZBX_DC_HISTORY	*history;
	ZBX_DC_TREND	*trend;
	char		value_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In DCsync(items %d pool:trends %d pool:history:%d)",
		cache->items_count,
		cache->pool.trends_count,
		cache->pool.history_count);

	LOCK_CACHE;
	DBbegin();
	

	for(i=0;i<cache->pool.history_count;i++)
	{
		history = &cache->pool.history[i];
		zabbix_log(LOG_LEVEL_DEBUG,"History " ZBX_FS_UI64,
			history->itemid);
		switch(history->value_type)
		{
			case	ITEM_VALUE_TYPE_UINT64:
					DBexecute("insert into history_uint (clock,itemid,value) values (%d," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
						history->clock,
						history->itemid,
						history->value.value_uint64);
				break;
			case	ITEM_VALUE_TYPE_FLOAT:
					DBexecute("insert into history (clock,itemid,value) values (%d," ZBX_FS_UI64 "," ZBX_FS_DBL ");\n",
						history->clock,
						history->itemid,
						history->value.value_float);
				break;
			case	ITEM_VALUE_TYPE_STR:
					DBescape_string(history->value.value_str,value_esc,MAX_STRING_LEN);
					DBexecute("insert into history_str (clock,itemid,value) values (%d," ZBX_FS_UI64 ",'%s');\n",
						history->clock,
						history->itemid,
						value_esc);
				break;
			default:
				zabbix_log(LOG_LEVEL_CRIT,"Unsupported history value type %d. Database cache corrupted?",
					history->value_type);
				exit(-1);
				break;
		}
	}

	for(i=0;i<cache->pool.trends_count;i++)
	{
		trend = &cache->pool.trends[i];
		zabbix_log(LOG_LEVEL_DEBUG,"Trend " ZBX_FS_UI64,
			trend->itemid);
		if(trend->operation == ZBX_TREND_OP_INSERT)
		{
			DBexecute("insert into trends (clock,itemid,num,value_min,value_avg,value_max) values (%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ")",
				trend->clock,
				trend->itemid,
				trend->num,
				trend->value_min,
				trend->value_avg,
				trend->value_max);
		}
		else if(trend->operation == ZBX_TREND_OP_UPDATE)
		{
			DBexecute("update trends set num=%d, value_min=" ZBX_FS_DBL ", value_avg=" ZBX_FS_DBL ", value_max=" ZBX_FS_DBL " where itemid=" ZBX_FS_UI64 " and clock=%d",
				trend->num,
				trend->value_min,
				trend->value_avg,
				trend->value_max,
				trend->itemid,
				trend->clock);
		}
	}

	DBcommit();

	cache->pool.history_count=0;
	cache->pool.trends_count=0;
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG,"End of DCsync()");
}

void	DCshow()
{
	int	i;

	ZBX_DC_HISTORY	*history;
	ZBX_DC_TREND	*trend;

	zabbix_log(LOG_LEVEL_WARNING,"In DCshow(items %d pool:trends %d pool:history:%d)",
		cache->items_count,
		cache->pool.trends_count,
		cache->pool.history_count);

	LOCK_CACHE;
	for(i=0;i<cache->pool.history_count;i++)
	{
		history = &cache->pool.history[i];
		zabbix_log(LOG_LEVEL_DEBUG,"History " ZBX_FS_UI64,
			history->itemid);
	}

	for(i=0;i<cache->pool.trends_count;i++)
	{
		trend = &cache->pool.trends[i];
		zabbix_log(LOG_LEVEL_DEBUG,"History " ZBX_FS_UI64,
			trend->itemid);
	}
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG,"End of DCshow()");
}

static ZBX_DC_ITEM	*get_item(zbx_uint64_t itemid)
{
	int	i;
	ZBX_DC_ITEM	*item = NULL;
	int	found = 0;

	for(i=0;i<cache->items_count;i++)
	{
		item = &cache->items[i];
		if(item->itemid == itemid)
		{
			found = 1;
			break;
		}
	}

	if(found == 0)
	{
		item=&cache->items[cache->items_count];
		item->itemid=itemid;
		cache->items_count++;
	}


	return item;
}

int	DCadd_trend(zbx_uint64_t itemid, double value, int clock)
{
	int 		hour;
	ZBX_DC_TREND	*trend = NULL,
			*trend_tmp = NULL;
	ZBX_DC_ITEM	*item = NULL;
	DB_RESULT	result;
	DB_ROW		row;
	int		trend_found=0;

	zabbix_log(LOG_LEVEL_DEBUG,"In DCadd_trend()");

	LOCK_CACHE;
	hour=clock-clock%3600;

	item=get_item(itemid);

	trend=&item->trend;

	if(hour == trend->clock)
	{
		trend_found=1;
	}
	else if(trend->clock !=0)
	{
//		add_trend2pool(trend);
		trend_tmp=&cache->pool.trends[cache->pool.trends_count];
		cache->pool.trends_count++;

		trend_tmp->operation	= trend->operation;
		trend_tmp->itemid	= trend->itemid;
		trend_tmp->clock	= trend->clock;
		trend_tmp->num		= trend->num;
		trend_tmp->value_min	= trend->value_min;
		trend_tmp->value_max	= trend->value_max;
		trend_tmp->value_avg	= trend->value_avg;

		trend->clock = 0;
	}

	/* Not found with the same clock */
	if(0 == trend_found)
	{
	zabbix_log(LOG_LEVEL_DEBUG,"Not found");
		/* Add new, do not look at the database */
		trend->operation	= ZBX_TREND_OP_INSERT;
		trend->itemid		= itemid;
		trend->clock		= hour;
		trend->num		= 1;
		trend->value_min	= value;
		trend->value_max	= value;
		trend->value_avg	= value;

		/* Try to find in the database */
		result = DBselect("select num,value_min,value_avg,value_max from trends where itemid=" ZBX_FS_UI64 " and clock=%d",
			itemid,
			hour);

		row=DBfetch(result);

		if(row)
		{
			trend->operation	= ZBX_TREND_OP_UPDATE;
			trend->itemid		= itemid;
			trend->clock		= hour;
			trend->num		= atoi(row[0]);
			trend->value_min	= atof(row[1]);
			trend->value_avg	= atof(row[2]);
			trend->value_max	= atof(row[3]);
			if(value<trend->value_min)	trend->value_min=value;
			if(value>trend->value_max)	trend->value_max=value;
			trend->value_avg	= (trend->num*trend->value_avg+value)/(trend->num+1);
			trend->num++;
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Found");
		if(value<trend->value_min)	trend->value_min=value;
		if(value>trend->value_max)	trend->value_max=value;
		trend->value_avg=(trend->num*trend->value_avg+value)/(trend->num+1);
		trend->num++;
	}
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG,"End of add_trend()");

	return SUCCEED;
}

int	DCadd_history(zbx_uint64_t itemid, double value, int clock)
{
	ZBX_DC_HISTORY	*history = NULL;

	zabbix_log(LOG_LEVEL_DEBUG,"In DCadd_history(itemid:" ZBX_FS_UI64 ")",
		itemid);

	LOCK_CACHE;

	history=&cache->pool.history[cache->pool.history_count];
	cache->pool.history_count++;

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_FLOAT;
	history->value.value_float	= value;

	UNLOCK_CACHE;

	return SUCCEED;
}

int	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
{
	ZBX_DC_HISTORY	*history = NULL;

	zabbix_log(LOG_LEVEL_DEBUG,"In DCadd_history_uint(itemid:" ZBX_FS_UI64 ")",
		itemid);

	LOCK_CACHE;
	history=&cache->pool.history[cache->pool.history_count];
	cache->pool.history_count++;

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_UINT64;
	history->value.value_uint64	= value;

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG,"End of DCadd_history_uint()");

	return SUCCEED;
}

int	DCadd_history_str(zbx_uint64_t itemid, char *value, int clock)
{
	ZBX_DC_HISTORY	*history = NULL;

	zabbix_log(LOG_LEVEL_DEBUG,"In DCadd_history_uint(itemid:" ZBX_FS_UI64 ")",
		itemid);

	LOCK_CACHE;
	history=&cache->pool.history[cache->pool.history_count];
	cache->pool.history_count++;

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_STR;
	strscpy(history->value.value_str,value);

	UNLOCK_CACHE;

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: init_database_cache                                              *
 *                                                                            *
 * Purpose: Allocate shared memory for database cache                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	init_database_cache(void)
{
#define ZBX_MAX_ATTEMPTS 10
	int	attempts = 0;

	key_t	shm_key;
	int	shm_id;

	ZBX_GET_SHM_DBCACHE_KEY(shm_key);

lbl_create:
	if ( -1 == (shm_id = shmget(shm_key, sizeof(ZBX_DC_CACHE), IPC_CREAT | IPC_EXCL | 0666 /* 0022 */)) )
	{
		if( EEXIST == errno )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Shared memory already exists for database cache, trying to recreate.");

			shm_id = shmget(shm_key, 0 /* get reference */, 0666 /* 0022 */);

			shmctl(shm_id, IPC_RMID, 0);
			if ( ++attempts > ZBX_MAX_ATTEMPTS )
			{
				zabbix_log(LOG_LEVEL_CRIT, "Can't recreate shared memory for database cache. [too many attempts]");
				exit(1);
			}
			if ( attempts > (ZBX_MAX_ATTEMPTS / 2) )
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Wait 1 sec for next attemt of database cache memory allocation.");
				sleep(1);
			}
			goto lbl_create;
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "Can't allocate shared memory for collector. [%s]",strerror(errno));
			exit(1);
		}
	}
	
	cache = shmat(shm_id, 0, 0);

	if ((void*)(-1) == cache)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't attach shared memory for database cache. [%s]",strerror(errno));
		exit(FAIL);
	}

	if(ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_lock, ZBX_MUTEX_CACHE))
	{
		zbx_error("Unable to create mutex for database cache");
		exit(FAIL);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: free_database_cache                                              *
 *                                                                            *
 * Purpose: Free memory aloccated for database cache                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	free_database_cache(void)
{

	key_t	shm_key;
	int	shm_id;

	zabbix_log(LOG_LEVEL_WARNING,"In free_database_cache()");

	if(NULL == cache) return;

	DCsync_all();

	LOCK_CACHE;
	
	ZBX_GET_SHM_DBCACHE_KEY(shm_key);

	shm_id = shmget(shm_key, sizeof(ZBX_DC_CACHE), 0);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_ERR, "Can't find shared memory for database cache. [%s]",strerror(errno));
		exit(1);
	}

	shmctl(shm_id, IPC_RMID, 0);

	cache = NULL;

	UNLOCK_CACHE;

	zbx_mutex_destroy(&cache_lock);

	zabbix_log(LOG_LEVEL_WARNING,"End of free_database_cache()");
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_all                                                       *
 *                                                                            *
 * Purpose: writes updates and new data from pool and cache data to database  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCsync_all()
{
	int	i;

	ZBX_DC_ITEM	*item;
	ZBX_DC_TREND	*trend;

	zabbix_log(LOG_LEVEL_WARNING,"In DCsync_all(items %d pool:trends %d pool:history:%d)",
		cache->items_count,
		cache->pool.trends_count,
		cache->pool.history_count);

	DCsync();

	LOCK_CACHE;
	DBbegin();

	zabbix_log(LOG_LEVEL_WARNING,"In items_count %d",
		cache->items_count);

	for(i=0;i<cache->items_count;i++)
	{
		item = &cache->items[i];
		trend = &item->trend;

		zabbix_log(LOG_LEVEL_DEBUG,"Trend " ZBX_FS_UI64,
			trend->itemid);

		if(trend->clock == 0)	continue;

		if(trend->operation == ZBX_TREND_OP_INSERT)
		{
			DBexecute("insert into trends (clock,itemid,num,value_min,value_avg,value_max) values (%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ")",
				trend->clock,
				trend->itemid,
				trend->num,
				trend->value_min,
				trend->value_avg,
				trend->value_max);
		}
		else if(trend->operation == ZBX_TREND_OP_UPDATE)
		{
			DBexecute("update trends set num=%d, value_min=" ZBX_FS_DBL ", value_avg=" ZBX_FS_DBL ", value_max=" ZBX_FS_DBL " where itemid=" ZBX_FS_UI64 " and clock=%d",
				trend->num,
				trend->value_min,
				trend->value_avg,
				trend->value_max,
				trend->itemid,
				trend->clock);
		}
	}

	DBcommit();

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_WARNING,"End of DCsync_all()");
}
