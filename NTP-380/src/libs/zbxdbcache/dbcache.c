/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2007 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include "common.h"
#include "log.h"
#include "db.h"
#include "dbsync.h"

#include "dbcache.h"
#include "mutexs.h"

#define	LOCK_CACHE	zbx_mutex_lock(&cache_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(&cache_lock)

#define ZBX_GET_SHM_DBCACHE_KEY(smk_key) 								\
	{if( -1 == (shm_key = ftok(CONFIG_FILE, (int)'c') )) 						\
        { 												\
                zbx_error("Can not create IPC key for path '%s', try to create for path '.' [%s]",	\
				CONFIG_FILE, strerror(errno)); 						\
                if( -1 == (shm_key = ftok(".", (int)'c') )) 						\
                { 											\
                        zbx_error("Can not create IPC key for path '.' [%s]", strerror(errno)); 	\
                        exit(1); 									\
                } 											\
        }}

ZBX_DC_CACHE		*cache = NULL;
static ZBX_MUTEX	cache_lock;


static const ZBX_TABLE *DBget_table(const char *tablename)
{
	int	t;

	for (t = 0; tables[t].table != 0; t++ )
		if (0 == strcmp(tables[t].table, tablename))
			return &tables[t];
	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_maxid                                                      *
 *                                                                            *
 * Purpose: Return next id for requested table                                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_nextid(const char *table_name, const char *field_name)
{
	int		i, nodeid;
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_TABLE	*table;
	ZBX_DC_IDS	*id;
	zbx_uint64_t	min, max, lastid;

	LOCK_CACHE;

	for (i = 0; i < ZBX_IDS_SIZE; i++)
	{
		id = &cache->ids[i];
		if ('\0' == *id->table_name)
			break;

		if (0 == strcmp(id->table_name, table_name) && 0 == strcmp(id->field_name, field_name))
		{
			lastid = ++id->lastid;

			UNLOCK_CACHE;

			return lastid;
		}
	}

	if (i == ZBX_IDS_SIZE)
	{
		zabbix_log(LOG_LEVEL_ERR, "Insufficient shared memory");
		exit(-1);
	}

	zbx_strlcpy(id->table_name, table_name, sizeof(id->table_name));
	zbx_strlcpy(id->field_name, field_name, sizeof(id->field_name));

	table = DBget_table(table_name);
	nodeid = CONFIG_NODEID >= 0 ? CONFIG_NODEID : 0;

	min = (zbx_uint64_t)__UINT64_C(100000000000000) * (zbx_uint64_t)nodeid;
	max = (zbx_uint64_t)__UINT64_C(100000000000000) * (zbx_uint64_t)nodeid;

	if (table->flags & ZBX_SYNC)
	{
		min += (zbx_uint64_t)__UINT64_C(100000000000) * (zbx_uint64_t)nodeid;
		max += (zbx_uint64_t)__UINT64_C(100000000000) * (zbx_uint64_t)nodeid + (zbx_uint64_t)__UINT64_C(99999999999);
	}
	else
		max += (zbx_uint64_t)__UINT64_C(99999999999999);

	result = DBselect("select max(%s) from %s where %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
			field_name,
			table_name,
			field_name,
			min, max);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
		id->lastid = min + 1;
	else
		ZBX_STR2UINT64(id->lastid, row[0]);

	lastid = ++id->lastid;

	DBfree_result(result);

	UNLOCK_CACHE;

	return lastid;
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
void	init_database_cache()
{
#define ZBX_MAX_ATTEMPTS 10
	int	attempts = 0;

	key_t	shm_key;
	int	shm_id;
	size_t	sz;

	ZBX_GET_SHM_DBCACHE_KEY(shm_key);

	sz = sizeof(ZBX_DC_CACHE);

lbl_create:
	if ( -1 == (shm_id = shmget(shm_key, sz, IPC_CREAT | IPC_EXCL | 0666 /* 0022 */)) )
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
			zabbix_log(LOG_LEVEL_CRIT, "Can't allocate shared memory for database cache. [%s]",strerror(errno));
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

	memset(cache, 0, sz);
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
void	free_database_cache()
{

	key_t	shm_key;
	int	shm_id;

	zabbix_log(LOG_LEVEL_DEBUG, "In free_database_cache()");

	if (NULL == cache)
		return;

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

	zabbix_log(LOG_LEVEL_DEBUG,"End of free_database_cache()");
}
