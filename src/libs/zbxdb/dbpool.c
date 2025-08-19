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

#include "zbxdb.h"
#include "dbconn.h"
#include "zbxcommon.h"
#include "zbxcfg.h"
#include "zbxmutexs.h"
#include "zbxshmem.h"
#include "zbxalgo.h"
#include "zbxdbschema.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtypes.h"
#include "zbxtime.h"

#define DBPOOL_HOUSEKEEP_INTERVAL	60

#define DBPOOL_DEFAULT_MIN_LIMIT        1
#define DBPOOL_DEFAULT_MAX_LIMIT        100
#define DBPOOL_DEFAULT_IDLE_TIMEOUT     (SEC_PER_HOUR)

struct zbx_dbconn_pool
{
	zbx_dbconn_pool_config_t	cfg;		/* local configuration */
	zbx_dbconn_pool_stats_t		stats;		/* local statistics */

	zbx_vector_dbconn_ptr_t	conns;
	zbx_vector_dbconn_ptr_t	available;

	pthread_mutex_t		lock;
	pthread_cond_t		event;

	int			open_num;

	double			time_modified;	// time when statistics were updated (connection acquired/released)
	double			time_housekeep;	// time when pool housekeeping was performed
};

/******************************************************************************
 *                                                                            *
 * Purpose: close unused database connections                                 *
 *                                                                            *
 * Parameters: pool - [IN] database connection pool                           *
 *             now  - [IN] current time in seconds                            *
 *                                                                            *
 * Comments: The connection pool must be already locked.                      *
 *                                                                            *
 ******************************************************************************/
static void	dbconn_pool_close_unused(zbx_dbconn_pool_t *pool, double now)
{
	int	closed_num = 0;

	for (int i = 0; i < pool->available.values_num && pool->open_num > pool->cfg.min_limit; i++)
	{
		zbx_dbconn_t	*db = pool->available.values[i];

		if (SUCCEED == dbconn_is_open(db) && now - db->last_used > pool->cfg.idle_timeout)
		{
			dbconn_close(pool->available.values[i]);
			pool->open_num--;
			closed_num++;
		}
	}

	if (0 != closed_num)
		zabbix_log(LOG_LEVEL_DEBUG, "closed %d unused connection(s) in database pool", closed_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create database connection and add it to the pool                 *
 *                                                                            *
 * Parameters: pool - [IN] database connection pool                           *
 *             now  - [IN] current time in seconds                            *
 *                                                                            *
 ******************************************************************************/
static void        dbconn_pool_create_connection(zbx_dbconn_pool_t *pool, double now)
{
	zbx_dbconn_t        *db;

	db = zbx_dbconn_create();
	dbconn_set_managed(db, DBCONN_TYPE_MANAGED);
	zbx_vector_dbconn_ptr_append(&pool->conns, db);
	zbx_vector_dbconn_ptr_append(&pool->available, db);
	db->last_used = now;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove database connection from pool, close and free it           *
 *                                                                            *
 * Parameters: pool - [IN] database connection pool                           *
 *             db   - [IN] database connection                                *
 *                                                                            *
 ******************************************************************************/
static void	dbconn_pool_remove_connection(zbx_dbconn_pool_t *pool, zbx_dbconn_t *db)
{
	int	i;

	if (FAIL != (i = zbx_vector_dbconn_ptr_search(&pool->conns, db, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		zbx_vector_dbconn_ptr_remove_noorder(&pool->conns, i);

	if (FAIL != (i = zbx_vector_dbconn_ptr_search(&pool->available, db, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		zbx_vector_dbconn_ptr_remove_noorder(&pool->available, i);

	dbconn_set_managed(db, DBCONN_TYPE_UNMANAGED);
	zbx_dbconn_free(db);
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply connection pool configuration                               *
 *                                                                            *
 * Parameters: pool   - [IN] database connection pool                         *
 *             config - [IN] new configuration                                *
 *                                                                            *
 * Comments: The connection pool must be already locked.                      *
 *                                                                            *
 ******************************************************************************/
static void	dbconn_pool_apply_config(zbx_dbconn_pool_t *pool, zbx_dbconn_pool_config_t *config)
{
	int	old_limit = pool->cfg.max_limit;

	pool->cfg.max_limit = config->max_limit;
	pool->cfg.min_limit = config->min_limit;
	pool->cfg.idle_timeout = config->idle_timeout;

	if (DBPOOL_MINIMUM_MIN_LIMIT > pool->cfg.min_limit)
		pool->cfg.min_limit = DBPOOL_MINIMUM_MIN_LIMIT;

	if (DBPOOL_MINIMUM_MAX_LIMIT > pool->cfg.max_limit)
		pool->cfg.max_limit = DBPOOL_MINIMUM_MAX_LIMIT;
	else if (pool->cfg.min_limit > pool->cfg.max_limit)
		pool->cfg.max_limit = pool->cfg.min_limit;

	if (DBPOOL_MINIMUM_IDLE_TIMEOUT > pool->cfg.idle_timeout)
		pool->cfg.idle_timeout = DBPOOL_MINIMUM_IDLE_TIMEOUT;
	else if (pool->cfg.idle_timeout > DBPOOL_MAXIMUM_IDLE_TIMEOUT)
		pool->cfg.idle_timeout = DBPOOL_MAXIMUM_IDLE_TIMEOUT;

	if (pool->cfg.max_limit == pool->conns.values_num)
		return;

	if (pool->cfg.max_limit > pool->conns.values_num)
	{
		double	now;

		zbx_vector_dbconn_ptr_reserve(&pool->conns, (size_t)pool->cfg.max_limit);
		now = zbx_time();

		for (int i = pool->conns.values_num; i < pool->cfg.max_limit; i++)
			dbconn_pool_create_connection(pool, now);

		zabbix_log(LOG_LEVEL_DEBUG, "extended database pool limit from %d to %d",
				old_limit, pool->cfg.max_limit);

		return;
	}

	int	removed_num = 0;

	while (0 < pool->available.values_num && pool->conns.values_num > pool->cfg.max_limit)
	{
		dbconn_pool_remove_connection(pool, pool->available.values[pool->available.values_num - 1]);
		removed_num++;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "reduced database pool limit from %d to %d and removed %d connections",
			old_limit, pool->cfg.max_limit, removed_num);

}

/******************************************************************************
 *                                                                            *
 * Purpose: refresh connection pool configuration and flush statistics        *
 *                                                                            *
 * Comments: The connection pool must be already locked.                      *
 *                                                                            *
 ******************************************************************************/
static void	dbconn_pool_sync_cache(zbx_dbconn_pool_t *pool, double now)
{
	if (now - pool->time_housekeep > DBPOOL_HOUSEKEEP_INTERVAL)
	{
		zbx_dbconn_pool_config_t	config;

		/* calculate idle time of available connections */
		for (int i = 0; i < pool->available.values_num; i++)
		{
			double	last_used = pool->available.values[i]->last_used;

			pool->stats.time_idle += now - MAX(last_used, pool->time_housekeep);
		}

		dbconn_pool_close_unused(pool, now);
		dbconn_pool_sync_info(&pool->stats, &config);
		dbconn_pool_apply_config(pool, &config);

		pool->time_housekeep = now;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update connection pool statistics                                 *
 *                                                                            *
 * Parameters: pool - [IN] database connection pool                           *
 *             now  - [IN] current time in seconds                            *
 *             wait - [IN] wait time in seconds (when acquiring connection)   *
 *             num  - [IN] 1 - acquiring connection, -1, releasing            *
 *             db   - [IN] acquired/released database connection              *
 *                                                                            *
 * Comments: The connection pool must be already locked.                      *
 *                                                                            *
 ******************************************************************************/
static void	dbconn_pool_update_stats(zbx_dbconn_pool_t *pool, double now, double wait, int num, zbx_dbconn_t *db)
{
	pool->time_modified = now;

	if (0 != num)
	{
		pool->stats.provided_num += num;
		pool->stats.time_wait += wait;
		/* idle time prior last housekeeping update is already accounted for */
		pool->stats.time_idle += now - MAX(db->last_used, pool->time_housekeep);
	}

	db->last_used = now;

	dbconn_pool_sync_cache(pool, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync database connection pool settings with the database          *
 *                                                                            *
 * Parameters: cfg   - [OUT] structure to store fetched settings              *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - settings fetched successfully                      *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 * Comments: If settings are not found in the database, default values will   *
 *           be written to the database. In the case of write failure         *
 *           connection pool will still be initialized successfully.          *
 *                                                                            *
 ******************************************************************************/
static int	dbconn_pool_sync_settings(zbx_dbconn_pool_config_t *cfg, char **error)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_dbconn_t	*db;
	char		*pattern;

	db = zbx_dbconn_create();

	if (ZBX_DB_OK != zbx_dbconn_open(db))
	{
		*error = zbx_strdup(NULL, "Cannot open database to get connection pool settings");
		zbx_dbconn_free(db);

		return FAIL;
	}

	pattern = zbx_db_dyn_escape_string(ZBX_SETTINGS_DBPOOL);
	result = zbx_dbconn_select(db, "select name,value_int from settings where name like '%s%%'", pattern);
	zbx_free(pattern);

	cfg->min_limit = cfg->max_limit = cfg->idle_timeout = -1;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 == strcmp(row[0], ZBX_SETTINGS_DBPOOL_MIN_CONN))
			cfg->min_limit = atoi(row[1]);
		else if (0 == strcmp(row[0], ZBX_SETTINGS_DBPOOL_MAX_CONN))
			cfg->max_limit = atoi(row[1]);
		else if (0 == strcmp(row[0], ZBX_SETTINGS_DBPOOL_IDLE_TIMEOUT))
			cfg->idle_timeout = atoi(row[1]);
	}
	zbx_db_free_result(result);

	/* write default settings to database if not set */
	if (-1 == cfg->min_limit || -1 == cfg->max_limit || -1 == cfg->idle_timeout)
	{
		zbx_db_insert_t	db_insert;

		zbx_dbconn_prepare_insert(db, &db_insert, "settings", "name", "type", "value_int", NULL);

		if (-1 == cfg->min_limit)
		{
			cfg->min_limit = DBPOOL_DEFAULT_MIN_LIMIT;
			zbx_db_insert_add_values(&db_insert, ZBX_SETTINGS_DBPOOL_MIN_CONN, ZBX_SETTING_TYPE_INT,
					cfg->min_limit);
		}

		if (-1 == cfg->max_limit)
		{
			cfg->max_limit = DBPOOL_DEFAULT_MAX_LIMIT;
			zbx_db_insert_add_values(&db_insert, ZBX_SETTINGS_DBPOOL_MAX_CONN, ZBX_SETTING_TYPE_INT,
					cfg->max_limit);
		}

		if (-1 == cfg->idle_timeout)
		{
			cfg->idle_timeout = DBPOOL_DEFAULT_IDLE_TIMEOUT;
			zbx_db_insert_add_values(&db_insert, ZBX_SETTINGS_DBPOOL_IDLE_TIMEOUT, ZBX_SETTING_TYPE_INT,
					cfg->idle_timeout);
		}

		if (ZBX_DB_OK != zbx_db_insert_execute(&db_insert))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot write default database connection pool settings to"
					" database");
		}

		zbx_db_insert_clean(&db_insert);
	}

	zbx_dbconn_close(db);
	zbx_dbconn_free(db);

	zbx_dbconn_pool_set_config(cfg);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create database connection pool                                   *
 *                                                                            *
 * Parameters: cache    - [IN] shared cache to exchange config and stats      *
 *             conn_num - [IN] total number of threads with db access         *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 ******************************************************************************/
zbx_dbconn_pool_t	*zbx_dbconn_pool_create(char **error)
{
	zbx_dbconn_pool_t		*pool;
	int				err;
	pthread_mutex_t			lock;
	pthread_cond_t			event;
	double				now;
	zbx_dbconn_pool_config_t	cfg;

	if (FAIL == dbconn_pool_sync_settings(&cfg, error))
		return NULL;

	if (0 != (err = pthread_mutex_init(&lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize database connection pool mutex: %s", zbx_strerror(err));
		return NULL;
	}

	if (0 != (err = pthread_cond_init(&event, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize database connection pool conditional variable: %s",
				zbx_strerror(err));
		pthread_mutex_destroy(&lock);
		return NULL;
	}

	now = zbx_time();

	pool = (zbx_dbconn_pool_t *)zbx_malloc(NULL, sizeof(zbx_dbconn_pool_t));
	memset(pool, 0, sizeof(zbx_dbconn_pool_t));

	pool->time_housekeep = now;
	pool->time_modified = now;
	pool->lock = lock;
	pool->event = event;
	pool->open_num = 0;

	zbx_vector_dbconn_ptr_create(&pool->conns);
	zbx_vector_dbconn_ptr_create(&pool->available);

	dbconn_pool_apply_config(pool, &cfg);

	return pool;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free database connection pool                                     *
 *                                                                            *
 * Parameters: pool - [IN] database connection pool                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_pool_free(zbx_dbconn_pool_t *pool)
{
	pthread_mutex_destroy(&pool->lock);
	pthread_cond_destroy(&pool->event);

	zbx_vector_dbconn_ptr_destroy(&pool->available);

	zbx_vector_dbconn_ptr_clear_ext(&pool->conns, zbx_dbconn_free);
	zbx_vector_dbconn_ptr_destroy(&pool->conns);
	zbx_free(pool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire database connection from pool                             *
 *                                                                            *
 * Parameters: pool - [IN] database connection pool                           *
 *                                                                            *
 * Return value: database connection                                          *
 *                                                                            *
 * Comments: This function will block until a connection has become available.*
 *           If necessary database connection will be opened before returning *
 *           it.                                                              *
 *                                                                            *
 ******************************************************************************/
zbx_dbconn_t	*zbx_dbconn_pool_acquire_connection(zbx_dbconn_pool_t *pool)
{
	double	start, now;

	start = zbx_time();
	pthread_mutex_lock(&pool->lock);

	while (0 == pool->available.values_num)
		pthread_cond_wait(&pool->event, &pool->lock);

	int		last = pool->available.values_num - 1;
	zbx_dbconn_t	*db = pool->available.values[last];

	now = zbx_time();

	dbconn_pool_update_stats(pool, now, now - start, 1, db);

	zbx_vector_dbconn_ptr_remove_noorder(&pool->available, last);
	pthread_mutex_unlock(&pool->lock);

	if (SUCCEED != dbconn_is_open(db))
	{
		dbconn_open_retry(db);
		pool->open_num++;
	}

	return db;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release database connection                                       *
 *                                                                            *
 * Parameters: pool - [IN] database connection pool                           *
 *             db   - [IN] database connection                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_pool_release_connection(zbx_dbconn_pool_t *pool, zbx_dbconn_t *db)
{
	pthread_mutex_lock(&pool->lock);

	dbconn_pool_update_stats(pool, zbx_time(), 0.0, 0, db);

	if (pool->conns.values_num <= pool->cfg.max_limit)
	{
		zbx_vector_dbconn_ptr_append(&pool->available, db);
		pthread_cond_broadcast(&pool->event);
	}
	else
	{
		dbconn_pool_remove_connection(pool, db);
		zabbix_log(LOG_LEVEL_DEBUG, "removed connection from database pool due to exceeding connection limit");
	}

	pthread_mutex_unlock(&pool->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync configuration and statistics cache with connection pool      *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_pool_sync(zbx_dbconn_pool_t *pool)
{
	pthread_mutex_lock(&pool->lock);
	dbconn_pool_sync_cache(pool, zbx_time());
	pthread_mutex_unlock(&pool->lock);
}
