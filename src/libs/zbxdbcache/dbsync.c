/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "dbsync.h"

#include "log.h"
#include "dbcache.h"
#include "zbxserialize.h"
#include "base64.h"
#include "zbxeval.h"

#define ZBX_DBSYNC_OBJ_HOST		1
#define ZBX_DBSYNC_OBJ_HOST_TAG		2
#define ZBX_DBSYNC_OBJ_ITEM		3
#define ZBX_DBSYNC_OBJ_ITEM_TAG		4
#define ZBX_DBSYNC_OBJ_TRIGGER		5
#define ZBX_DBSYNC_OBJ_TRIGGER_TAG	6
#define ZBX_DBSYNC_OBJ_FUNCTION		7
#define ZBX_DBSYNC_OBJ_ITEM_PREPROC	8

/* number of dbsync objects - keep in sync with above defines */
#define ZBX_DBSYNC_OBJ_COUNT		8

#define ZBX_DBSYNC_JOURNAL(X)		(X - 1)

#define ZBX_DBSYNC_CHANGELOG_PRUNE_INTERVAL	SEC_PER_MIN * 10
#define ZBX_DBSYNC_CHANGELOG_MAX_AGE		SEC_PER_HOUR

#define ZBX_DBSYNC_BATCH_SIZE			1000

typedef struct
{
	zbx_uint64_t	changelogid;
	int		clock;
}
zbx_dbsync_changelog_t;

ZBX_VECTOR_DECL(dbsync_changelog, zbx_dbsync_changelog_t)
ZBX_VECTOR_IMPL(dbsync_changelog, zbx_dbsync_changelog_t)

typedef struct
{
	zbx_uint64_t		objectid;
	zbx_dbsync_changelog_t	changelog;
}
zbx_dbsync_obj_changelog_t;

ZBX_VECTOR_DECL(dbsync_obj_changelog, zbx_dbsync_obj_changelog_t)
ZBX_VECTOR_IMPL(dbsync_obj_changelog, zbx_dbsync_obj_changelog_t)

ZBX_PTR_VECTOR_DECL(dbsync, zbx_dbsync_t *)
ZBX_PTR_VECTOR_IMPL(dbsync, zbx_dbsync_t *)

typedef struct
{
	zbx_vector_uint64_t			inserts;
	zbx_vector_uint64_t			updates;
	zbx_vector_uint64_t			deletes;

	zbx_vector_dbsync_t 			syncs;
	zbx_vector_dbsync_obj_changelog_t	changelog;
}
zbx_dbsync_journal_t;

typedef struct
{
	zbx_hashset_t			strpool;
	ZBX_DC_CONFIG			*cache;

	zbx_hashset_t			changelog;

	zbx_dbsync_journal_t		journals[ZBX_DBSYNC_OBJ_COUNT];
}
zbx_dbsync_env_t;

static zbx_dbsync_env_t	dbsync_env;

/* string pool support */

#define REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

static zbx_hash_t	dbsync_strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((char *)data + REFCOUNT_FIELD_SIZE);
}

static int	dbsync_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp((char *)d1 + REFCOUNT_FIELD_SIZE, (char *)d2 + REFCOUNT_FIELD_SIZE);
}

static char	*dbsync_strdup(const char *str)
{
	void	*ptr;

	ptr = zbx_hashset_search(&dbsync_env.strpool, str - REFCOUNT_FIELD_SIZE);

	if (NULL == ptr)
	{
		ptr = zbx_hashset_insert_ext(&dbsync_env.strpool, str - REFCOUNT_FIELD_SIZE,
				REFCOUNT_FIELD_SIZE + strlen(str) + 1, REFCOUNT_FIELD_SIZE);

		*(zbx_uint32_t *)ptr = 0;
	}

	(*(zbx_uint32_t *)ptr)++;

	return (char *)ptr + REFCOUNT_FIELD_SIZE;
}

static void	dbsync_strfree(char *str)
{
	if (NULL != str)
	{
		void	*ptr = str - REFCOUNT_FIELD_SIZE;

		if (0 == --(*(zbx_uint32_t *)ptr))
			zbx_hashset_remove_direct(&dbsync_env.strpool, ptr);
	}
}

/* macro value validators */

/******************************************************************************
 *                                                                            *
 * Purpose: compares 64 bit unsigned integer with a raw database value        *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_uint64(const char *value_raw, zbx_uint64_t value)
{
	zbx_uint64_t	value_ui64;

	ZBX_DBROW2UINT64(value_ui64, value_raw);

	return (value_ui64 == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares 32 bit signed integer with a raw database value          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_int(const char *value_raw, int value)
{
	return (atoi(value_raw) == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares unsigned character with a raw database value             *
 *                                                                            *
 ******************************************************************************/

static int	dbsync_compare_uchar(const char *value_raw, unsigned char value)
{
	unsigned char	value_uchar;

	ZBX_STR2UCHAR(value_uchar, value_raw);
	return (value_uchar == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares string with a raw database value                         *
 *                                                                            *
 ******************************************************************************/

static int	dbsync_compare_str(const char *value_raw, const char *value)
{
	return (0 == strcmp(value_raw, value) ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds a new row to the changeset                                   *
 *                                                                            *
 * Parameter: sync  - [IN] the changeset                                      *
 *            rowid - [IN] the row identifier                                 *
 *            tag   - [IN] the row tag (see ZBX_DBSYNC_ROW_ defines)          *
 *            dbrow - [IN] the row contents (depending on configuration cache *
 *                         removal logic for the specific object it can be    *
 *                         NULL when used with ZBX_DBSYNC_ROW_REMOVE tag)     *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_add_row(zbx_dbsync_t *sync, zbx_uint64_t rowid, unsigned char tag, const DB_ROW dbrow)
{
	int			i;
	zbx_dbsync_row_t	*row;

	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = rowid;
	row->tag = tag;

	if (NULL != dbrow)
	{
		row->row = (char **)zbx_malloc(NULL, sizeof(char *) * (size_t)sync->columns_num);

		for (i = 0; i < sync->columns_num; i++)
			row->row[i] = (NULL == dbrow[i] ? NULL : dbsync_strdup(dbrow[i]));
	}
	else
		row->row = NULL;

	zbx_vector_ptr_append(&sync->rows, row);

	switch (tag)
	{
		case ZBX_DBSYNC_ROW_ADD:
			sync->add_num++;
			break;
		case ZBX_DBSYNC_ROW_UPDATE:
			sync->update_num++;
			break;
		case ZBX_DBSYNC_ROW_REMOVE:
			sync->remove_num++;
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares changeset                                                *
 *                                                                            *
 * Parameter: sync             - [IN] the changeset                           *
 *            columns_num      - [IN] the number of columns in the changeset  *
 *            preproc_row_func - [IN] the callback function used to retrieve  *
 *                                    associated hostids (can be NULL if      *
 *                                    user macros are not resolved during     *
 *                                    synchronization process)                *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_prepare(zbx_dbsync_t *sync, int columns_num, zbx_dbsync_preproc_row_func_t preproc_row_func)
{
	sync->columns_num = columns_num;
	sync->preproc_row_func = preproc_row_func;

	sync->row = (char **)zbx_malloc(NULL, sizeof(char *) * (size_t)columns_num);
	memset(sync->row, 0, sizeof(char *) * (size_t)columns_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies necessary pre-processing before row is compared/used      *
 *                                                                            *
 * Parameter: sync - [IN] the changeset                                       *
 *            row  - [IN/OUT] the data row                                    *
 *                                                                            *
 * Return value: the resulting row                                            *
 *                                                                            *
 ******************************************************************************/
static char	**dbsync_preproc_row(zbx_dbsync_t *sync, char **row)
{
	int	i;

	if (NULL == sync->preproc_row_func)
		return row;

	/* free the resources allocated by last preprocessing call */
	zbx_vector_ptr_clear_ext(&sync->columns, zbx_ptr_free);

	/* copy the original data */
	memcpy(sync->row, row, sizeof(char *) * (size_t)sync->columns_num);

	sync->row = sync->preproc_row_func(sync->row);

	for (i = 0; i < sync->columns_num; i++)
	{
		if (sync->row[i] != row[i])
			zbx_vector_ptr_append(&sync->columns, sync->row[i]);
	}

	return sync->row;
}

static void	dbsync_journal_init(zbx_dbsync_journal_t *journal)
{
	zbx_vector_uint64_create(&journal->inserts);
	zbx_vector_uint64_create(&journal->updates);
	zbx_vector_uint64_create(&journal->deletes);

	zbx_vector_dbsync_create(&journal->syncs);

	zbx_vector_dbsync_obj_changelog_create(&journal->changelog);
}

static void	dbsync_journal_destroy(zbx_dbsync_journal_t *journal)
{
	zbx_vector_uint64_destroy(&journal->inserts);
	zbx_vector_uint64_destroy(&journal->updates);
	zbx_vector_uint64_destroy(&journal->deletes);

	zbx_vector_dbsync_destroy(&journal->syncs);

	zbx_vector_dbsync_obj_changelog_destroy(&journal->changelog);
}

void	zbx_dbsync_env_init(ZBX_DC_CONFIG *cache)
{
	dbsync_env.cache = cache;
	zbx_hashset_create(&dbsync_env.changelog, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove old (1h+) changelog records from database and cache using  *
 *          database time                                                     *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_prune_changelog()
{
	static int		last_prune_time;
	int			now;
	zbx_dbsync_changelog_t	*changelog;
	DB_ROW			row;
	DB_RESULT		result;

	now = time(NULL);

	if (0 == last_prune_time)
	{
		last_prune_time = now;
		return;
	}

	if (now - last_prune_time < ZBX_DBSYNC_CHANGELOG_PRUNE_INTERVAL)
		return;

	last_prune_time = now;

#ifndef HAVE_ORACLE
	result = DBselect("select %s", ZBX_DB_TIMESTAMP());
#else
	result = DBselect("select %s from dual", ZBX_DB_TIMESTAMP());
#endif

	if (NULL != (row = DBfetch(result)))
	{
		int	changelog_num;

		changelog_num = dbsync_env.changelog.num_data;
		now = atoi(row[0]);

		if (ZBX_DB_OK <= DBexecute("delete from changelog where clock<%d", now - ZBX_DBSYNC_CHANGELOG_MAX_AGE))
		{
			zbx_hashset_iter_t	iter;

			zbx_hashset_iter_reset(&dbsync_env.changelog, &iter);
			while (NULL != (changelog = (zbx_dbsync_changelog_t *)zbx_hashset_iter_next(&iter)))
			{
				if (now - changelog->clock > ZBX_DBSYNC_CHANGELOG_MAX_AGE)
					zbx_hashset_iter_remove(&iter);
			}

			zabbix_log(LOG_LEVEL_DEBUG, "removed %d old changelog records",
					changelog_num - dbsync_env.changelog.num_data);
		}
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove from first vector all ids found in second vector           *
 *                                                                            *
 * Comments: Both vectors must be sorted in ascending order                   *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_remove_duplicate_ids(zbx_vector_uint64_t *dst, const zbx_vector_uint64_t *src)
{
	int	i, j, k;

	for (i = 0, j = 0, k = 0; j < src->values_num && i < dst->values_num;)
	{
		if (dst->values[i] == src->values[j])
		{
			i++;
			j++;
			continue;
		}

		if (dst->values[i] < src->values[j])
		{
			while (i < dst->values_num && dst->values[i] < src->values[j])
				dst->values[k++] = dst->values[i++];
		}
		else
		{
			while (j < src->values_num && dst->values[i] > src->values[j])
				j++;
		}
	}

	if (j == src->values_num && i < dst->values_num)
	{
		if (k != i)
			memmove(dst->values + k, dst->values + i, (size_t)(dst->values_num - i) * sizeof(zbx_uint64_t));

		k += dst->values_num - i;
	}

	dst->values_num = k;
}

/******************************************************************************
 *                                                                            *
 * Purpose: read changelog and prepare lists of modified objects since last   *
 *          sync                                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_env_prepare(unsigned char mode)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_dbsync_changelog_t	changelog_local;
	int			changelog_num = 0;
	size_t			i;

	zbx_hashset_create(&dbsync_env.strpool, 100, dbsync_strpool_hash_func, dbsync_strpool_compare_func);

	for (i = 0; i < ARRSIZE(dbsync_env.journals); i++)
		dbsync_journal_init(&dbsync_env.journals[i]);

	if (ZBX_DBSYNC_INIT == mode)
	{
		result = DBselect("select changelogid,clock from changelog");

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_DBROW2UINT64(changelog_local.changelogid, row[0]);
			changelog_local.clock = atoi(row[1]);
			zbx_hashset_insert(&dbsync_env.changelog, &changelog_local, sizeof(changelog_local));
			changelog_num++;
		}
	}
	else
	{
		result = DBselect("select changelogid,object,objectid,operation,clock from changelog");

		while (NULL != (row = DBfetch(result)))
		{
			int				operation;
			zbx_dbsync_journal_t		*journal;
			zbx_dbsync_obj_changelog_t	obj;

			ZBX_DBROW2UINT64(obj.changelog.changelogid, row[0]);

			if (NULL != zbx_hashset_search(&dbsync_env.changelog, &obj.changelog))
				continue;

			obj.changelog.clock = atoi(row[4]);
			ZBX_DBROW2UINT64(obj.objectid, row[2]);
			operation = atoi(row[3]);
			journal = &dbsync_env.journals[ZBX_DBSYNC_JOURNAL(atoi(row[1]))];

			zbx_vector_dbsync_obj_changelog_append(&journal->changelog, obj);

			switch (operation)
			{
				case ZBX_DBSYNC_ROW_ADD:
					zbx_vector_uint64_append(&journal->inserts, obj.objectid);
					break;
				case ZBX_DBSYNC_ROW_UPDATE:
					zbx_vector_uint64_append(&journal->updates, obj.objectid);
					break;
				case ZBX_DBSYNC_ROW_REMOVE:
					zbx_vector_uint64_append(&journal->deletes, obj.objectid);
					break;
			}

			changelog_num++;
		}
	}
	DBfree_result(result);

	for (i = 0; i < ARRSIZE(dbsync_env.journals); i++)
	{
		zbx_vector_uint64_sort(&dbsync_env.journals[i].inserts, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_sort(&dbsync_env.journals[i].updates, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&dbsync_env.journals[i].updates, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_sort(&dbsync_env.journals[i].deletes, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		/* in the case multiple changelog records are registered for the same object          */
		/* the operation priority is delete, insert, update:                                  */
		/*   delete - if object is removed any prior changes to it does not matter            */
		/*   insert - if object was added and then updated, only the insert operation matters */
		dbsync_remove_duplicate_ids(&dbsync_env.journals[i].inserts, &dbsync_env.journals[i].deletes);
		dbsync_remove_duplicate_ids(&dbsync_env.journals[i].updates, &dbsync_env.journals[i].deletes);
		dbsync_remove_duplicate_ids(&dbsync_env.journals[i].updates, &dbsync_env.journals[i].inserts);
	}

	return changelog_num;
}

static void	dbsync_env_flush_journal(zbx_dbsync_journal_t *journal)
{
	zbx_vector_uint64_t	objectids;
	int			i, j, objects_num;

	if (0 == journal->changelog.values_num)
		return;

	objects_num = journal->inserts.values_num + journal->updates.values_num;

	for (i = 0; i < journal->syncs.values_num; i++)
		objects_num += journal->syncs.values[i]->rows.values_num;

	zbx_vector_uint64_create(&objectids);
	zbx_vector_uint64_reserve(&objectids, (size_t)objects_num);

	for (j = 0; j < journal->syncs.values_num; j++)
	{
		for (i = 0; i < journal->syncs.values[j]->rows.values_num; i++)
		{
			zbx_dbsync_row_t	*row = (zbx_dbsync_row_t *)journal->syncs.values[j]->rows.values[i];

			zbx_vector_uint64_append(&objectids, row->rowid);
		}
	}

	if (0 != journal->inserts.values_num)
		zbx_vector_uint64_append_array(&objectids, journal->inserts.values, journal->inserts.values_num);

	if (0 != journal->updates.values_num)
		zbx_vector_uint64_append_array(&objectids, journal->updates.values, journal->updates.values_num);

	zbx_vector_uint64_sort(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < journal->changelog.values_num; i++)
	{
		if (FAIL != zbx_vector_uint64_bsearch(&objectids, journal->changelog.values[i].objectid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			zbx_hashset_insert(&dbsync_env.changelog, &journal->changelog.values[i].changelog,
					sizeof(zbx_dbsync_changelog_t));
		}
	}

	zbx_vector_uint64_destroy(&objectids);
}

void	zbx_dbsync_env_flush_changelog(void)
{
	size_t	i;

	for (i = 0; i < (int)ARRSIZE(dbsync_env.journals); i++)
		dbsync_env_flush_journal(&dbsync_env.journals[i]);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() changelog  : %d (%d slots)", __func__,
			dbsync_env.changelog.num_data, dbsync_env.changelog.num_slots);

}

void	zbx_dbsync_env_clear(void)
{
	size_t	i;

	dbsync_prune_changelog();

	zbx_hashset_destroy(&dbsync_env.strpool);

	for (i = 0; i < ARRSIZE(dbsync_env.journals); i++)
		dbsync_journal_destroy(&dbsync_env.journals[i]);

}

int	zbx_dbsync_env_changelog_num(void)
{
	return dbsync_env.changelog.num_data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get rows changed since last sync                                  *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_get_rows(zbx_dbsync_t *sync, char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *field, zbx_vector_uint64_t *ids, unsigned char tag)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	char			**row;
	size_t			sql_offset_reset = *sql_offset;
	zbx_uint64_t		rowid, *batch;
	int			batch_size;
	zbx_vector_uint64_t	read_ids;

	zbx_vector_uint64_create(&read_ids);
	zbx_vector_uint64_reserve(&read_ids, (size_t)ids->values_num);

	for (batch = ids->values; batch < ids->values + ids->values_num; batch += ZBX_DBSYNC_BATCH_SIZE)
	{
		batch_size = MIN(ZBX_DBSYNC_BATCH_SIZE, ids->values + ids->values_num - batch);
		DBadd_condition_alloc(sql, sql_alloc, sql_offset, field, batch, batch_size);

		if (NULL == (result = DBselect("%s", *sql)))
			return FAIL;

		*sql_offset = sql_offset_reset;

		while (NULL != (dbrow = DBfetch(result)))
		{
			ZBX_STR2UINT64(rowid, dbrow[0]);
			if (NULL != (row = dbsync_preproc_row(sync, dbrow)))
				dbsync_add_row(sync, rowid, tag, row);

			zbx_vector_uint64_append(&read_ids, rowid);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_sort(&read_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	dbsync_remove_duplicate_ids(ids, &read_ids);

	zbx_vector_uint64_destroy(&read_ids);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: read query data based on changelog journal                        *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_read_journal(zbx_dbsync_t *sync, char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *field, const char *keyword, zbx_dbsync_journal_t *journal)
{
	int	i, inserts_num, updates_num;

	zbx_vector_dbsync_append(&journal->syncs, sync);

	inserts_num = journal->inserts.values_num;
	updates_num = journal->updates.values_num;

	if (0 != journal->inserts.values_num || 0 != journal->updates.values_num)
	{
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, keyword);

		if (0 != journal->inserts.values_num)
		{
			if (FAIL == dbsync_get_rows(sync, sql, sql_alloc, sql_offset, field, &journal->inserts,
					ZBX_DBSYNC_ROW_ADD))
			{
				return FAIL;
			}
		}

		if (0 != journal->updates.values_num)
		{
			if (FAIL == dbsync_get_rows(sync, sql, sql_alloc, sql_offset, field, &journal->updates,
					ZBX_DBSYNC_ROW_UPDATE))
			{
				return FAIL;
			}
		}
	}

	for (i = 0; i < journal->deletes.values_num; i++)
		dbsync_add_row(sync, journal->deletes.values[i], ZBX_DBSYNC_ROW_REMOVE, NULL);

	/* the obtained object identifiers are removed from journal */
	sync->add_num = (zbx_uint64_t)(inserts_num - journal->inserts.values_num);
	sync->update_num = (zbx_uint64_t)(updates_num - journal->updates.values_num);

	sync->remove_num = (zbx_uint64_t)journal->deletes.values_num;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes changeset                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_init(zbx_dbsync_t *sync, unsigned char mode)
{
	sync->columns_num = 0;
	sync->mode = mode;

	sync->add_num = 0;
	sync->update_num = 0;
	sync->remove_num = 0;

	sync->row = NULL;
	sync->preproc_row_func = NULL;
	zbx_vector_ptr_create(&sync->columns);

	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		zbx_vector_ptr_create(&sync->rows);
		sync->row_index = -1;
	}
	else
		sync->dbresult = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated by changeset                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_clear(zbx_dbsync_t *sync)
{
	/* free the resources allocated by row pre-processing */
	zbx_vector_ptr_clear_ext(&sync->columns, zbx_ptr_free);
	zbx_vector_ptr_destroy(&sync->columns);

	zbx_free(sync->row);

	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		int			i, j;
		zbx_dbsync_row_t	*row;

		for (i = 0; i < sync->rows.values_num; i++)
		{
			row = (zbx_dbsync_row_t *)sync->rows.values[i];

			if (NULL != row->row)
			{
				for (j = 0; j < sync->columns_num; j++)
					dbsync_strfree(row->row[j]);

				zbx_free(row->row);
			}

			zbx_free(row);
		}

		zbx_vector_ptr_destroy(&sync->rows);
	}
	else
	{
		DBfree_result(sync->dbresult);
		sync->dbresult = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the next row from the changeset                              *
 *                                                                            *
 * Parameters: sync  - [IN] the changeset                                     *
 *             rowid - [OUT] the row identifier (required for row removal,    *
 *                          optional for new/updated rows)                    *
 *             row   - [OUT] the row data                                     *
 *             tag   - [OUT] the row tag, identifying changes                 *
 *                           (see ZBX_DBSYNC_ROW_* defines)                   *
 *                                                                            *
 * Return value: SUCCEED - the next row was successfully retrieved            *
 *               FAIL    - no more data to retrieve                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_next(zbx_dbsync_t *sync, zbx_uint64_t *rowid, char ***row, unsigned char *tag)
{
	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		zbx_dbsync_row_t	*sync_row;

		if (++sync->row_index == sync->rows.values_num)
			return FAIL;

		sync_row = (zbx_dbsync_row_t *)sync->rows.values[sync->row_index];
		*rowid = sync_row->rowid;
		*row = sync_row->row;
		*tag = sync_row->tag;
	}
	else
	{
		char	**dbrow;

		if (NULL == (dbrow = DBfetch(sync->dbresult)))
		{
			*row = NULL;
			return FAIL;
		}

		*row = dbsync_preproc_row(sync, dbrow);

		*rowid = 0;
		*tag = ZBX_DBSYNC_ROW_ADD;

		sync->add_num++;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encode serialized expression to be returned as db field           *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static char	*encode_expression(const zbx_eval_context_t *ctx)
{
	unsigned char	*data;
	size_t		len;
	char		*str = NULL;

	len = zbx_eval_serialize(ctx, NULL, &data);
	str_base64_encode_dyn((const char *)data, &str, len);
	zbx_free(data);

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares config table with cached configuration data              *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_config(zbx_dbsync_t *sync)
{
	DB_RESULT	result;

#define SELECTED_CONFIG_FIELD_COUNT	33	/* number of columns in the following DBselect() */

	if (NULL == (result = DBselect("select discovery_groupid,snmptrap_logging,"
				"severity_name_0,severity_name_1,severity_name_2,"
				"severity_name_3,severity_name_4,severity_name_5,"
				"hk_events_mode,hk_events_trigger,hk_events_internal,"
				"hk_events_discovery,hk_events_autoreg,hk_services_mode,"
				"hk_services,hk_audit_mode,hk_audit,hk_sessions_mode,hk_sessions,"
				"hk_history_mode,hk_history_global,hk_history,hk_trends_mode,"
				"hk_trends_global,hk_trends,default_inventory_mode,db_extension,autoreg_tls_accept,"
				"compression_status,compress_older,instanceid,default_timezone,hk_events_service,"
				"auditlog_enabled"
			" from config"
			" order by configid")))	/* if you change number of columns in DBselect(), */
						/* adjust SELECTED_CONFIG_FIELD_COUNT */
	{
		return FAIL;
	}

	dbsync_prepare(sync, SELECTED_CONFIG_FIELD_COUNT, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	DBfree_result(result);

	/* global configuration will be always synchronized directly with database */
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
#undef SELECTED_CONFIG_FIELD_COUNT
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares 'config_autoreg_tls' table with cached configuration     *
 *          data                                                              *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments:                                                                  *
 *     On success this function produces a changeset with 0 or 1 record       *
 *     because 'config_autoreg_tls' table can have no more than 1 record.     *
 *     If in future you want to support multiple autoregistration PSKs and/or *
 *     select more columns in DBselect() then do not forget to sync changes   *
 *     with DCsync_autoreg_config() !!!                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_autoreg_psk(zbx_dbsync_t *sync)
{
	DB_RESULT	result;
	DB_ROW		dbrow;
	int		num_records = 0;

#define CONFIG_AUTOREG_TLS_FIELD_COUNT	2	/* number of columns in the following DBselect() */

	if (NULL == (result = DBselect("select tls_psk_identity,tls_psk"
			" from config_autoreg_tls"
			" order by autoreg_tlsid")))	/* if you change number of columns in DBselect(), */
							/* adjust CONFIG_AUTOREG_TLS_FIELD_COUNT */
	{
		return FAIL;
	}

	dbsync_prepare(sync, CONFIG_AUTOREG_TLS_FIELD_COUNT, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	/* 0 or 1 records are expected */

	if (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		if ('\0' == dbsync_env.cache->autoreg_psk_identity[0])	/* no autoregistration PSK in cache */
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_str(dbrow[0], dbsync_env.cache->autoreg_psk_identity) ||
				FAIL == dbsync_compare_str(dbrow[1], dbsync_env.cache->autoreg_psk))
		{
			tag = ZBX_DBSYNC_ROW_UPDATE;
		}

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, 0, tag, dbrow);	/* fictitious rowid 0 is used, there is only 1 record */

		num_records = 1;
	}
	else if ('\0' != dbsync_env.cache->autoreg_psk_identity[0])
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, NULL);

	if (1 == num_records && NULL != DBfetch(result))
		zabbix_log(LOG_LEVEL_ERR, "table 'config_autoreg_tls' has multiple records");

	DBfree_result(result);

	return SUCCEED;
#undef CONFIG_AUTOREG_TLS_FIELD_COUNT
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares hosts table with cached configuration data               *
 *          and populates the changeset                                       *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_hosts(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select h.hostid,h.proxy_hostid,h.host,h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,"
				"h.ipmi_password,h.maintenance_status,h.maintenance_type,h.maintenance_from,"
				"h.status,h.name,hr.lastaccess,h.tls_connect,h.tls_accept,h.tls_issuer,h.tls_subject,"
				"h.tls_psk_identity,h.tls_psk,h.proxy_address,h.auto_compress,h.maintenanceid"
			" from hosts h"
			" left join host_rtdata hr on h.hostid=hr.hostid"
			" where status in (%d,%d,%d,%d)"
				" and flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE);

	dbsync_prepare(sync, 22, NULL);
#else
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select h.hostid,h.proxy_hostid,h.host,h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,"
				"h.ipmi_password,h.maintenance_status,h.maintenance_type,h.maintenance_from,"
				"h.status,h.name,hr.lastaccess,h.tls_connect,h.tls_accept,"
				"h.proxy_address,h.auto_compress,h.maintenanceid"
			" from hosts h"
			" left join host_rtdata hr on h.hostid=hr.hostid"
			" where status in (%d,%d,%d,%d)"
				" and flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE);

	dbsync_prepare(sync, 18, NULL);
#endif

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "h.hostid", "and",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_HOST)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares host inventory table row with cached configuration data  *
 *                                                                            *
 * Parameter: hi    - [IN] the cached host inventory data                     *
 *            dbrow - [IN] the database row                                   *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host_inventory(const ZBX_DC_HOST_INVENTORY *hi, const DB_ROW dbrow)
{
	int	i;

	if (SUCCEED != dbsync_compare_uchar(dbrow[1], hi->inventory_mode))
		return FAIL;

	for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
	{
		if (FAIL == dbsync_compare_str(dbrow[i + 2], hi->values[i]))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares host_inventory table with cached configuration data      *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_inventory(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_HOST_INVENTORY	*hi;
	const char		*sql;

	sql = "select hostid,inventory_mode,type,type_full,name,alias,os,os_full,os_short,serialno_a,"
			"serialno_b,tag,asset_tag,macaddress_a,macaddress_b,hardware,hardware_full,software,"
			"software_full,software_app_a,software_app_b,software_app_c,software_app_d,"
			"software_app_e,contact,location,location_lat,location_lon,notes,chassis,model,"
			"hw_arch,vendor,contract_number,installer_name,deployment_status,url_a,url_b,"
			"url_c,host_networks,host_netmask,host_router,oob_ip,oob_netmask,oob_router,"
			"date_hw_purchase,date_hw_install,date_hw_expiry,date_hw_decomm,site_address_a,"
			"site_address_b,site_address_c,site_city,site_state,site_country,site_zip,site_rack,"
			"site_notes,poc_1_name,poc_1_email,poc_1_phone_a,poc_1_phone_b,poc_1_cell,"
			"poc_1_screen,poc_1_notes,poc_2_name,poc_2_email,poc_2_phone_a,poc_2_phone_b,"
			"poc_2_cell,poc_2_screen,poc_2_notes"
			" from host_inventory";

	if (NULL == (result = DBselect("%s", sql)))
		return FAIL;

	dbsync_prepare(sync, 72, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->host_inventories.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (hi = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&dbsync_env.cache->host_inventories,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_host_inventory(hi, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);

	}

	zbx_hashset_iter_reset(&dbsync_env.cache->host_inventories, &iter);
	while (NULL != (hi = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &hi->hostid))
			dbsync_add_row(sync, hi->hostid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares hosts_templates table with cached configuration data     *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_templates(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_iter_t	iter;
	zbx_um_host_t		**phost;
	zbx_hashset_t		htmpls;
	int			i;
	zbx_uint64_pair_t	ht_local, *ht;
	char			hostid_s[MAX_ID_LEN + 1], templateid_s[MAX_ID_LEN + 1];
	char			*del_row[2] = {hostid_s, templateid_s};

	if (NULL == (result = DBselect(
			"select hostid,templateid"
			" from hosts_templates"
			" order by hostid")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&htmpls, 100, ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	/* index all host->template links */
	zbx_hashset_iter_reset(&dbsync_env.cache->um_cache->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
	{
		ht_local.first = (*phost)->hostid;

		for (i = 0; i < (*phost)->templateids.values_num; i++)
		{
			ht_local.second = (*phost)->templateids.values[i];
			zbx_hashset_insert(&htmpls, &ht_local, sizeof(ht_local));
		}
	}

	/* add new rows, remove existing rows from index */
	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(ht_local.first, dbrow[0]);
		ZBX_STR2UINT64(ht_local.second, dbrow[1]);

		if (NULL == (ht = (zbx_uint64_pair_t *)zbx_hashset_search(&htmpls, &ht_local)))
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_ADD, dbrow);
		else
			zbx_hashset_remove_direct(&htmpls, ht);
	}

	/* add removed rows */
	zbx_hashset_iter_reset(&htmpls, &iter);
	while (NULL != (ht = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_snprintf(hostid_s, sizeof(hostid_s), ZBX_FS_UI64, ht->first);
		zbx_snprintf(templateid_s, sizeof(templateid_s), ZBX_FS_UI64, ht->second);
		dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, del_row);
	}

	DBfree_result(result);
	zbx_hashset_destroy(&htmpls);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares global macro table row with cached configuration data    *
 *                                                                            *
 * Parameter: gmacro - [IN] the cached global macro data                      *
 *            dbrow    - [IN] the database row                                *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_global_macro(const zbx_um_macro_t *gmacro, const DB_ROW dbrow)
{
	char	*macro = NULL, *context = NULL;
	int	ret = FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[3], gmacro->type))
		return FAIL;

	if (ZBX_MACRO_VALUE_VAULT == atoi(dbrow[3]))
	{
		if (FAIL == um_macro_check_vault_location(gmacro, dbrow[2]))
			return FAIL;
	}
	else
	{
		if (FAIL == dbsync_compare_str(dbrow[2], gmacro->value))
			return FAIL;
	}

	if (SUCCEED != zbx_user_macro_parse_dyn(dbrow[1], &macro, &context, NULL, NULL))
		return FAIL;

	if (0 != strcmp(gmacro->name, macro))
		goto out;

	if (NULL == context)
	{
		if (NULL != gmacro->context)
			goto out;

		ret = SUCCEED;
		goto out;
	}

	if (NULL == gmacro->context)
		goto out;

	if (0 == strcmp(gmacro->context, context))
		ret = SUCCEED;
out:
	zbx_free(macro);
	zbx_free(context);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares global macros table with cached configuration data       *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_global_macros(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid, *prowid = &rowid;
	zbx_um_macro_t		**pmacro;

	if (NULL == (result = DBselect(
			"select globalmacroid,macro,value,type"
			" from globalmacro")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->gmacros.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (pmacro = (zbx_um_macro_t **)zbx_hashset_search(&dbsync_env.cache->gmacros, &prowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_global_macro(*pmacro, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->gmacros, &iter);
	while (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &(*pmacro)->macroid))
			dbsync_add_row(sync, (*pmacro)->macroid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares host macro table row with cached configuration data      *
 *                                                                            *
 * Parameter: hmacro - [IN] the cached host macro data                        *
 *            dbrow  - [IN] the database row                                  *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host_macro(const zbx_um_macro_t *hmacro, const DB_ROW dbrow)
{
	char	*macro = NULL, *context = NULL;
	int	ret = FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[4], hmacro->type))
		return FAIL;

	if (ZBX_MACRO_VALUE_VAULT == atoi(dbrow[4]))
	{
		if (FAIL == um_macro_check_vault_location(hmacro, dbrow[3]))
			return FAIL;
	}
	else
	{
		if (FAIL == dbsync_compare_str(dbrow[3], hmacro->value))
			return FAIL;
	}

	if (FAIL == dbsync_compare_uint64(dbrow[1], hmacro->hostid))
		return FAIL;

	if (SUCCEED != zbx_user_macro_parse_dyn(dbrow[2], &macro, &context, NULL, NULL))
		return FAIL;

	if (0 != strcmp(hmacro->name, macro))
		goto out;

	if (NULL == context)
	{
		if (NULL != hmacro->context)
			goto out;

		ret = SUCCEED;
		goto out;
	}

	if (NULL == hmacro->context)
		goto out;

	if (0 == strcmp(hmacro->context, context))
		ret = SUCCEED;
out:
	zbx_free(macro);
	zbx_free(context);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares global macros table with cached configuration data       *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_macros(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid, *prowid = &rowid;
	zbx_um_macro_t		**pmacro;

	if (NULL == (result = DBselect(
			"select m.hostmacroid,m.hostid,m.macro,m.value,m.type"
			" from hostmacro m"
			" inner join hosts h on m.hostid=h.hostid"
			" where h.flags<>%d", ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 5, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->hmacros.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (pmacro = (zbx_um_macro_t **)zbx_hashset_search(&dbsync_env.cache->hmacros, &prowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_host_macro(*pmacro, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->hmacros, &iter);
	while (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &(*pmacro)->macroid))
			dbsync_add_row(sync, (*pmacro)->macroid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares interface table row with cached configuration data       *
 *                                                                            *
 * Parameter: interface - [IN] the cached interface data                      *
 *            dbrow     - [IN] the database row                               *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: User macros used in ip, dns fields will always make compare to   *
 *           fail.                                                            *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_interface(const ZBX_DC_INTERFACE *interface, const DB_ROW dbrow)
{
	ZBX_DC_SNMPINTERFACE *snmp;

	if (FAIL == dbsync_compare_uint64(dbrow[1], interface->hostid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], interface->type))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[3], interface->main))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[4], interface->useip))
		return FAIL;

	if (NULL != strstr(dbrow[5], "{$"))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[5], interface->ip))
		return FAIL;

	if (NULL != strstr(dbrow[6], "{$"))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[6], interface->dns))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[7], interface->port))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[8], interface->available))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[9], interface->disable_until))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[10], interface->error))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[11], interface->errors_from))
		return FAIL;
	/* reset_availability, items_num and availability_ts are excluded from the comparison */

	snmp = (ZBX_DC_SNMPINTERFACE *)zbx_hashset_search(&dbsync_env.cache->interfaces_snmp,
			&interface->interfaceid);

	if (INTERFACE_TYPE_SNMP == interface->type)
	{
		if (NULL == snmp || SUCCEED == DBis_null(dbrow[12]))	/* should never happen */
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[12], snmp->version))
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[13], snmp->bulk))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[14], snmp->community))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[15], snmp->securityname))
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[16], snmp->securitylevel))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[17], snmp->authpassphrase))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[18], snmp->privpassphrase))
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[19], snmp->authprotocol))
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[20], snmp->privprotocol))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[21], snmp->contextname))
			return FAIL;
	}
	else if (NULL != snmp)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares interfaces table with cached configuration data          *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_interfaces(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_INTERFACE	*interface;

	if (NULL == (result = DBselect(
			"select i.interfaceid,i.hostid,i.type,i.main,i.useip,i.ip,i.dns,i.port,"
			"i.available,i.disable_until,i.error,i.errors_from,"
			"s.version,s.bulk,s.community,s.securityname,s.securitylevel,s.authpassphrase,s.privpassphrase,"
			"s.authprotocol,s.privprotocol,s.contextname"
			" from interface i"
			" left join interface_snmp s on i.interfaceid=s.interfaceid")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 22, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->interfaces.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&dbsync_env.cache->interfaces, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_interface(interface, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->interfaces, &iter);
	while (NULL != (interface = (ZBX_DC_INTERFACE *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &interface->interfaceid))
			dbsync_add_row(sync, interface->interfaceid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies necessary preprocessing before row is compared/used       *
 *                                                                            *
 * Parameter: row - [IN] the row to preprocess                                *
 *                                                                            *
 * Return value: the preprocessed row                                         *
 *                                                                            *
 * Comments: The row preprocessing can be used to expand user macros in       *
 *           some columns.                                                    *
 *                                                                            *
 ******************************************************************************/
static char	**dbsync_item_preproc_row(char **row)
{
	unsigned char	type;

	ZBX_STR2UCHAR(type, row[3]);

	/* expand user macros */

	if (ITEM_TYPE_CALCULATED == type)
	{
		zbx_eval_context_t	ctx;
		char			*error = NULL;

		if (FAIL == zbx_eval_parse_expression(&ctx, row[11], ZBX_EVAL_PARSE_CALC_EXPRESSION, &error))
		{
			zbx_eval_set_exception(&ctx, zbx_dsprintf(NULL, "Cannot parse formula: %s", error));
			zbx_free(error);
		}

		row[49] = encode_expression(&ctx);
		zbx_eval_clear(&ctx);
	}

	return row;

}

/******************************************************************************
 *                                                                            *
 * Purpose: compares items table with cached configuration data               *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_items(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid,i.hostid,i.status,i.type,i.value_type,i.key_,i.snmp_oid,i.ipmi_sensor,i.delay,"
				"i.trapper_hosts,i.logtimefmt,i.params,ir.state,i.authtype,i.username,i.password,"
				"i.publickey,i.privatekey,i.flags,i.interfaceid,ir.lastlogsize,ir.mtime,"
				"i.history,i.trends,i.inventory_link,i.valuemapid,i.units,ir.error,i.jmx_endpoint,"
				"i.master_itemid,i.timeout,i.url,i.query_fields,i.posts,i.status_codes,"
				"i.follow_redirects,i.post_type,i.http_proxy,i.headers,i.retrieve_mode,"
				"i.request_method,i.output_format,i.ssl_cert_file,i.ssl_key_file,i.ssl_key_password,"
				"i.verify_peer,i.verify_host,i.allow_traps,i.templateid,null"
			" from items i"
			" inner join hosts h on i.hostid=h.hostid"
			" join item_rtdata ir on i.itemid=ir.itemid"
			" where h.status in (%d,%d) and i.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	dbsync_prepare(sync, 50, dbsync_item_preproc_row);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "i.itemid", "and",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_ITEM)]);
out:
	zbx_free(sql);

	return ret;
}

static int	dbsync_compare_item_discovery(const ZBX_DC_ITEM_DISCOVERY *item_discovery, const DB_ROW dbrow)
{
	return dbsync_compare_uint64(dbrow[1], item_discovery->parent_itemid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares mapping between items, prototypes and rules with         *
 *          configuration cache                                               *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_item_discovery(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_ITEM_DISCOVERY	*item_discovery;
	char			**row;

	if (NULL == (result = DBselect("select itemid,parent_itemid from item_discovery")))
		return FAIL;

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->item_discovery.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		row = dbsync_preproc_row(sync, dbrow);

		if (NULL == (item_discovery = (ZBX_DC_ITEM_DISCOVERY *)zbx_hashset_search(
				&dbsync_env.cache->item_discovery, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_item_discovery(item_discovery, row))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, row);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->item_discovery, &iter);
	while (NULL != (item_discovery = (ZBX_DC_ITEM_DISCOVERY *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &item_discovery->itemid))
			dbsync_add_row(sync, item_discovery->itemid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

static int	dbsync_compare_template_item(const ZBX_DC_TEMPLATE_ITEM *item, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], item->hostid))
		return FAIL;

	if (FAIL == dbsync_compare_uint64(dbrow[2], item->templateid))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares items that belong to templates with configuration cache  *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_template_items(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_TEMPLATE_ITEM	*item;
	char			**row;

	if (NULL == (result = DBselect(
			"select i.itemid,i.hostid,i.templateid from items i inner join hosts h on i.hostid=h.hostid"
			" where h.status=%d", HOST_STATUS_TEMPLATE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 3, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->template_items.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		row = dbsync_preproc_row(sync, dbrow);

		if (NULL == (item = (ZBX_DC_TEMPLATE_ITEM *)zbx_hashset_search(&dbsync_env.cache->template_items,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_template_item(item, row))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, row);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->template_items, &iter);
	while (NULL != (item = (ZBX_DC_TEMPLATE_ITEM *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &item->itemid))
			dbsync_add_row(sync, item->itemid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares lld item prototypes with configuration cache             *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_prototype_items(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid,i.hostid,i.templateid from items i where i.flags=%d",
				ZBX_FLAG_DISCOVERY_PROTOTYPE);

	dbsync_prepare(sync, 3, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "i.itemid", "and",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_ITEM)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies necessary preprocessing before row is compared/used       *
 *                                                                            *
 * Parameter: row - [IN] the row to preprocess                                *
 *                                                                            *
 * Return value: the preprocessed row                                         *
 *                                                                            *
 * Comments: The row preprocessing can be used to expand user macros in       *
 *           some columns.                                                    *
 *           During preprocessing trigger expression/recovery expression are  *
 *           parsed, serialized and stored as base64 strings into 16,17       *
 *           columns.                                                         *
 *                                                                            *
 ******************************************************************************/
static char	**dbsync_trigger_preproc_row(char **row)
{
	zbx_eval_context_t	ctx, ctx_r;
	char			*error = NULL;
	unsigned char		mode, timer = ZBX_TRIGGER_TIMER_DEFAULT, flags;

	ZBX_STR2UCHAR(flags, row[19]);

	if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flags)
		return row;

	if (FAIL == zbx_eval_parse_expression(&ctx, row[2], ZBX_EVAL_TRIGGER_EXPRESSION, &error))
	{
		zbx_eval_set_exception(&ctx, zbx_dsprintf(NULL, "cannot parse trigger expression: %s", error));
		zbx_free(error);
	}
	else
	{
		if (SUCCEED == zbx_eval_check_timer_functions(&ctx))
			timer |= ZBX_TRIGGER_TIMER_EXPRESSION;
	}

	ZBX_STR2UCHAR(mode, row[10]);

	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == mode)
	{
		if (FAIL == zbx_eval_parse_expression(&ctx_r, row[11], ZBX_EVAL_TRIGGER_EXPRESSION, &error))
		{
			zbx_eval_set_exception(&ctx_r, zbx_dsprintf(NULL, "cannot parse trigger recovery"
					" expression: %s", error));
			zbx_free(error);
		}
		else
		{
			if (SUCCEED == zbx_eval_check_timer_functions(&ctx_r))
				timer |= ZBX_TRIGGER_TIMER_RECOVERY_EXPRESSION;
		}
	}

	row[16] = encode_expression(&ctx);
	zbx_eval_clear(&ctx);

	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == mode)
	{
		row[17] = encode_expression(&ctx_r);
		zbx_eval_clear(&ctx_r);
	}

	row[18] = zbx_dsprintf(NULL, "%d", timer);

	return row;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares triggers table with cached configuration data            *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comment: The 16th and 17th fields (starting with 0) are placeholders for   *
 *          serialized expression/recovery expression.                        *
 *          The 18th field is placeholder for trigger timer flag (set if      *
 *          expression/recovery expression contains timer functions).         *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_triggers(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select triggerid,description,expression,error,priority,type,value,state,lastchange,status,"
			"recovery_mode,recovery_expression,correlation_mode,correlation_tag,opdata,event_name,null,"
			"null,null,flags"
			" from triggers");

	dbsync_prepare(sync, 20, dbsync_trigger_preproc_row);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "triggerid", "where",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_TRIGGER)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares trigger_depends table with cached configuration data     *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_trigger_dependency(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		deps;
	zbx_hashset_iter_t	iter;
	ZBX_DC_TRIGGER_DEPLIST	*dep_down, *dep_up;
	zbx_uint64_pair_t	*dep, dep_local;
	char			down_s[MAX_ID_LEN + 1], up_s[MAX_ID_LEN + 1];
	char			*del_row[2] = {down_s, up_s};
	int			i;

	if (NULL == (result = DBselect("select triggerid_down,triggerid_up from trigger_depends")))
		return FAIL;

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&deps, 100, ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	/* index all host->template links */
	zbx_hashset_iter_reset(&dbsync_env.cache->trigdeps, &iter);
	while (NULL != (dep_down = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_iter_next(&iter)))
	{
		dep_local.first = dep_down->triggerid;

		for (i = 0; i < dep_down->dependencies.values_num; i++)
		{
			dep_up = (ZBX_DC_TRIGGER_DEPLIST *)dep_down->dependencies.values[i];
			dep_local.second = dep_up->triggerid;
			zbx_hashset_insert(&deps, &dep_local, sizeof(dep_local));
		}
	}

	/* add new rows, remove existing rows from index */
	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(dep_local.first, dbrow[0]);
		ZBX_STR2UINT64(dep_local.second, dbrow[1]);

		if (NULL == (dep = (zbx_uint64_pair_t *)zbx_hashset_search(&deps, &dep_local)))
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_ADD, dbrow);
		else
			zbx_hashset_remove_direct(&deps, dep);
	}

	/* add removed rows */
	zbx_hashset_iter_reset(&deps, &iter);
	while (NULL != (dep = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_snprintf(down_s, sizeof(down_s), ZBX_FS_UI64, dep->first);
		zbx_snprintf(up_s, sizeof(up_s), ZBX_FS_UI64, dep->second);
		dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, del_row);
	}

	DBfree_result(result);
	zbx_hashset_destroy(&deps);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies necessary preprocessing before row is compared/used       *
 *                                                                            *
 * Parameter: row - [IN] the row to preprocess                                *
 *                                                                            *
 * Return value: the preprocessed row                                         *
 *                                                                            *
 * Comments: The row preprocessing can be used to expand user macros in       *
 *           some columns.                                                    *
 *                                                                            *
 ******************************************************************************/
static char	**dbsync_function_preproc_row(char **row)
{
	const char	*row3;

	/* first parameter is /host/key placeholder $, don't cache it */
	if (NULL == (row3 = strchr(row[3], ',')))
		row3 = "";
	else
		row3++;

	row[3] = zbx_strdup(NULL, row3);

	return row;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares functions table with cached configuration data           *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_functions(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select functionid,itemid,name,parameter,triggerid from functions");

	dbsync_prepare(sync, 5, dbsync_function_preproc_row);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "functionid", "where",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_FUNCTION)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares expressions table row with cached configuration data     *
 *                                                                            *
 * Parameter: expression - [IN] the cached expression                         *
 *            dbrow      - [IN] the database row                              *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_expression(const ZBX_DC_EXPRESSION *expression, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_str(dbrow[0], expression->regexp))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[2], expression->expression))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[3], expression->type))
		return FAIL;

	if (*dbrow[4] != expression->delimiter)
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[5], expression->case_sensitive))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares expressions, regexps tables with cached configuration    *
 *          data                                                              *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_expressions(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_EXPRESSION	*expression;

	if (NULL == (result = DBselect(
			"select r.name,e.expressionid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
			" from regexps r,expressions e"
			" where r.regexpid=e.regexpid")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 6, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->expressions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[1]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (expression = (ZBX_DC_EXPRESSION *)zbx_hashset_search(&dbsync_env.cache->expressions,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_expression(expression, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->expressions, &iter);
	while (NULL != (expression = (ZBX_DC_EXPRESSION *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &expression->expressionid))
			dbsync_add_row(sync, expression->expressionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares actions table row with cached configuration data         *
 *                                                                            *
 * Parameter: action - [IN] the cached action                                 *
 *            dbrow  - [IN] the database row                                  *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_action(const zbx_dc_action_t *action, const DB_ROW dbrow)
{

	if (FAIL == dbsync_compare_uchar(dbrow[1], action->eventsource))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], action->evaltype))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], action->formula))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares actions table with cached configuration data             *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_actions(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_action_t		*action;

	if (NULL == (result = DBselect(
			"select actionid,eventsource,evaltype,formula"
			" from actions"
			" where eventsource<>%d"
				" and status=%d",
			EVENT_SOURCE_SERVICE, ACTION_STATUS_ACTIVE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->actions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&dbsync_env.cache->actions, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_action(action, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->actions, &iter);
	while (NULL != (action = (zbx_dc_action_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &action->actionid))
			dbsync_add_row(sync, action->actionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares action operation class and flushes update row if         *
 *          necessary                                                         *
 *                                                                            *
 * Parameter: sync     - [OUT] the changeset                                  *
 *            actionid - [IN] the action identifier                           *
 *            opflags  - [IN] the action operation class flags                *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_compare_action_op(zbx_dbsync_t *sync, zbx_uint64_t actionid, unsigned char opflags)
{
	zbx_dc_action_t	*action;

	if (0 == actionid)
		return;

	if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&dbsync_env.cache->actions, &actionid)) ||
			opflags != action->opflags)
	{
		char	actionid_s[MAX_ID_LEN], opflags_s[MAX_ID_LEN];
		char	*row[] = {actionid_s, opflags_s};

		zbx_snprintf(actionid_s, sizeof(actionid_s), ZBX_FS_UI64, actionid);
		zbx_snprintf(opflags_s, sizeof(opflags_s), "%d", opflags);

		dbsync_add_row(sync, actionid, ZBX_DBSYNC_ROW_UPDATE, (DB_ROW)row);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares actions by operation class                               *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_action_ops(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_uint64_t		rowid, actionid = 0;
	unsigned char		opflags = ZBX_ACTION_OPCLASS_NONE;

	if (NULL == (result = DBselect(
			"select a.actionid,o.recovery"
			" from actions a"
			" left join operations o"
				" on a.actionid=o.actionid"
			" where a.status=%d"
			" group by a.actionid,o.recovery"
			" order by a.actionid",
			ACTION_STATUS_ACTIVE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 2, NULL);

	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(rowid, dbrow[0]);

		if (actionid != rowid)
		{
			dbsync_compare_action_op(sync, actionid, opflags);
			actionid = rowid;
			opflags = ZBX_ACTION_OPCLASS_NONE;
		}

		if (SUCCEED == DBis_null(dbrow[1]))
			continue;

		switch (atoi(dbrow[1]))
		{
			case 0:
				opflags |= ZBX_ACTION_OPCLASS_NORMAL;
				break;
			case 1:
				opflags |= ZBX_ACTION_OPCLASS_RECOVERY;
				break;
			case 2:
				opflags |= ZBX_ACTION_OPCLASS_ACKNOWLEDGE;
				break;
		}
	}

	dbsync_compare_action_op(sync, actionid, opflags);

	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares conditions table row with cached configuration data      *
 *                                                                            *
 * Parameter: condition - [IN] the cached action condition                    *
 *            dbrow     - [IN] the database row                               *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_action_condition(const zbx_dc_action_condition_t *condition, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uchar(dbrow[2], condition->conditiontype))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[3], condition->op))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[4], condition->value))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[5], condition->value2))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares conditions table with cached configuration data          *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_action_conditions(zbx_dbsync_t *sync)
{
	DB_ROW				dbrow;
	DB_RESULT			result;
	zbx_hashset_t			ids;
	zbx_hashset_iter_t		iter;
	zbx_uint64_t			rowid;
	zbx_dc_action_condition_t	*condition;

	if (NULL == (result = DBselect(
			"select c.conditionid,c.actionid,c.conditiontype,c.operator,c.value,c.value2"
			" from conditions c,actions a"
			" where c.actionid=a.actionid"
				" and a.status=%d",
			ACTION_STATUS_ACTIVE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 6, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->action_conditions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (condition = (zbx_dc_action_condition_t *)zbx_hashset_search(
				&dbsync_env.cache->action_conditions, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_action_condition(condition, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->action_conditions, &iter);
	while (NULL != (condition = (zbx_dc_action_condition_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &condition->conditionid))
			dbsync_add_row(sync, condition->conditionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares trigger tags table with cached configuration data        *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_trigger_tags(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select triggertagid,triggerid,tag,value from trigger_tag");

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "triggertagid", "where",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_TRIGGER_TAG)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares item tags table with cached configuration data           *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_item_tags(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select itemtagid,itemid,tag,value from item_tag");

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "itemtagid", "where",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_ITEM_TAG)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares host tags table with cached configuration data           *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_tags(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select hosttagid,hostid,tag,value from host_tag");

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "hosttagid", "where",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_HOST_TAG)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares correlation table row with cached configuration data     *
 *                                                                            *
 * Parameter: correlation - [IN] the cached correlation rule                  *
 *            dbrow       - [IN] the database row                             *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_correlation(const zbx_dc_correlation_t *correlation, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_str(dbrow[1], correlation->name))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], correlation->evaltype))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], correlation->formula))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares correlation table with cached configuration data         *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_correlations(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_correlation_t	*correlation;

	if (NULL == (result = DBselect(
			"select correlationid,name,evaltype,formula"
			" from correlation"
			" where status=%d",
			ZBX_CORRELATION_ENABLED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->correlations.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&dbsync_env.cache->correlations,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_correlation(correlation, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->correlations, &iter);
	while (NULL != (correlation = (zbx_dc_correlation_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &correlation->correlationid))
			dbsync_add_row(sync, correlation->correlationid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares correlation condition tables dbrow with cached             *
 *          configuration data                                                *
 *                                                                            *
 * Parameter: corr_condition - [IN] the cached correlation condition          *
 *            dbrow          - [IN] the database row                          *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_corr_condition(const zbx_dc_corr_condition_t *corr_condition, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], corr_condition->correlationid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], corr_condition->type))
		return FAIL;

	switch (corr_condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			if (FAIL == dbsync_compare_str(dbrow[3], corr_condition->data.tag.tag))
				return FAIL;
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			if (FAIL == dbsync_compare_str(dbrow[4], corr_condition->data.tag_value.tag))
				return FAIL;
			if (FAIL == dbsync_compare_str(dbrow[5], corr_condition->data.tag_value.value))
				return FAIL;
			if (FAIL == dbsync_compare_uchar(dbrow[6], corr_condition->data.tag_value.op))
				return FAIL;
			break;
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			if (FAIL == dbsync_compare_uint64(dbrow[7], corr_condition->data.group.groupid))
				return FAIL;
			if (FAIL == dbsync_compare_uchar(dbrow[8], corr_condition->data.group.op))
				return FAIL;
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			if (FAIL == dbsync_compare_str(dbrow[9], corr_condition->data.tag_pair.oldtag))
				return FAIL;
			if (FAIL == dbsync_compare_str(dbrow[10], corr_condition->data.tag_pair.newtag))
				return FAIL;
			break;
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares correlation condition tables with cached configuration   *
 *          data                                                              *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_corr_conditions(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_corr_condition_t	*corr_condition;

	if (NULL == (result = DBselect(
			"select cc.corr_conditionid,cc.correlationid,cc.type,cct.tag,cctv.tag,cctv.value,cctv.operator,"
				" ccg.groupid,ccg.operator,cctp.oldtag,cctp.newtag"
			" from correlation c,corr_condition cc"
			" left join corr_condition_tag cct"
				" on cct.corr_conditionid=cc.corr_conditionid"
			" left join corr_condition_tagvalue cctv"
				" on cctv.corr_conditionid=cc.corr_conditionid"
			" left join corr_condition_group ccg"
				" on ccg.corr_conditionid=cc.corr_conditionid"
			" left join corr_condition_tagpair cctp"
				" on cctp.corr_conditionid=cc.corr_conditionid"
			" where c.correlationid=cc.correlationid"
				" and c.status=%d",
			ZBX_CORRELATION_ENABLED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 11, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->corr_conditions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (corr_condition = (zbx_dc_corr_condition_t *)zbx_hashset_search(
				&dbsync_env.cache->corr_conditions, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_corr_condition(corr_condition, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->corr_conditions, &iter);
	while (NULL != (corr_condition = (zbx_dc_corr_condition_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &corr_condition->corr_conditionid))
			dbsync_add_row(sync, corr_condition->corr_conditionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares correlation operation tables dbrow with cached             *
 *          configuration data                                                *
 *                                                                            *
 * Parameter: corr_operation - [IN] the cached correlation operation          *
 *            dbrow          - [IN] the database row                          *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_corr_operation(const zbx_dc_corr_operation_t *corr_operation, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], corr_operation->correlationid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], corr_operation->type))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares correlation operation tables with cached configuration   *
 *          data                                                              *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_corr_operations(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_corr_operation_t	*corr_operation;

	if (NULL == (result = DBselect(
			"select co.corr_operationid,co.correlationid,co.type"
			" from correlation c,corr_operation co"
			" where c.correlationid=co.correlationid"
				" and c.status=%d",
			ZBX_CORRELATION_ENABLED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 3, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->corr_operations.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (corr_operation = (zbx_dc_corr_operation_t *)zbx_hashset_search(
				&dbsync_env.cache->corr_operations, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_corr_operation(corr_operation, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->corr_operations, &iter);
	while (NULL != (corr_operation = (zbx_dc_corr_operation_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &corr_operation->corr_operationid))
			dbsync_add_row(sync, corr_operation->corr_operationid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares host group table row with cached configuration data      *
 *                                                                            *
 * Parameter: group - [IN] the cached host group                              *
 *            dbrow - [IN] the database row                                   *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host_group(const zbx_dc_hostgroup_t *group, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_str(dbrow[1], group->name))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares host groups table with cached configuration data         *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_groups(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_hostgroup_t	*group;

	if (NULL == (result = DBselect("select groupid,name from hstgrp")))
		return FAIL;

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->hostgroups.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&dbsync_env.cache->hostgroups, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_host_group(group, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->hostgroups, &iter);
	while (NULL != (group = (zbx_dc_hostgroup_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &group->groupid))
			dbsync_add_row(sync, group->groupid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares item preproc tables with cached configuration data       *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_item_preprocs(zbx_dbsync_t *sync)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select item_preprocid,itemid,type,params,step,error_handler,error_handler_params"
			" from item_preproc");

	dbsync_prepare(sync, 7, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		if (NULL == (sync->dbresult = DBselect("%s", sql)))
			ret = FAIL;
		goto out;
	}

	ret = dbsync_read_journal(sync, &sql, &sql_alloc, &sql_offset, "item_preprocid", "where",
			&dbsync_env.journals[ZBX_DBSYNC_JOURNAL(ZBX_DBSYNC_OBJ_ITEM_PREPROC)]);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares maintenance table row with cached configuration data     *
 *                                                                            *
 * Parameter: maintenance - [IN] the cached maintenance data                  *
 *            dbrow       - [IN] the database row                             *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_maintenance(const zbx_dc_maintenance_t *maintenance, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uchar(dbrow[1], maintenance->type))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[2], maintenance->active_since))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[3], maintenance->active_until))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[4], maintenance->tags_evaltype))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares item script params table row with cached configuration   *
 *          data                                                              *
 *                                                                            *
 * Parameter: script - [IN] the cached item script                            *
 *            dbrow  - [IN] the database row                                  *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_itemscript_param(const zbx_dc_scriptitem_param_t *scriptitem_param, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], scriptitem_param->itemid))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[2], scriptitem_param->name))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], scriptitem_param->value))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares item_parameter table with cached configuration data      *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_item_script_param(zbx_dbsync_t *sync)
{
	DB_ROW				dbrow;
	DB_RESULT			result;
	zbx_hashset_t			ids;
	zbx_hashset_iter_t		iter;
	zbx_uint64_t			rowid;
	zbx_dc_scriptitem_param_t	*itemscript_params;
	char				**row;

	if (NULL == (result = DBselect(
			"select p.item_parameterid,p.itemid,p.name,p.value,i.hostid"
			" from item_parameter p,items i,hosts h"
			" where p.itemid=i.itemid"
				" and i.hostid=h.hostid"
				" and h.status in (%d,%d)"
				" and i.flags<>%d"
			" order by p.itemid",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 5, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->itemscript_params.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		row = dbsync_preproc_row(sync, dbrow);

		if (NULL == (itemscript_params = (zbx_dc_scriptitem_param_t *)
				zbx_hashset_search(&dbsync_env.cache->itemscript_params, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_itemscript_param(itemscript_params, row))
		{
			tag = ZBX_DBSYNC_ROW_UPDATE;
		}

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, row);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->itemscript_params, &iter);

	while (NULL != (itemscript_params = (zbx_dc_scriptitem_param_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &itemscript_params->item_script_paramid))
			dbsync_add_row(sync, itemscript_params->item_script_paramid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares maintenances table with cached configuration data        *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_maintenances(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_maintenance_t	*maintenance;

	if (NULL == (result = DBselect("select maintenanceid,maintenance_type,active_since,active_till,tags_evaltype"
						" from maintenances")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 5, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->maintenances.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&dbsync_env.cache->maintenances, &rowid);

		if (NULL == maintenance)
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_maintenance(maintenance, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->maintenances, &iter);
	while (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &maintenance->maintenanceid))
			dbsync_add_row(sync, maintenance->maintenanceid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares maintenance_tag table row with cached configuration data *
 *                                                                            *
 * Parameter: maintenance_tag - [IN] the cached maintenance tag               *
 *            dbrow           - [IN] the database row                         *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_maintenance_tag(const zbx_dc_maintenance_tag_t *maintenance_tag, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_int(dbrow[2], maintenance_tag->op))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], maintenance_tag->tag))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[4], maintenance_tag->value))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares maintenances table with cached configuration data        *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_maintenance_tags(zbx_dbsync_t *sync)
{
	DB_ROW				dbrow;
	DB_RESULT			result;
	zbx_hashset_t			ids;
	zbx_hashset_iter_t		iter;
	zbx_uint64_t			rowid;
	zbx_dc_maintenance_tag_t	*maintenance_tag;

	if (NULL == (result = DBselect("select maintenancetagid,maintenanceid,operator,tag,value"
						" from maintenance_tag")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 5, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->maintenance_tags.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		maintenance_tag = (zbx_dc_maintenance_tag_t *)zbx_hashset_search(&dbsync_env.cache->maintenance_tags,
				&rowid);

		if (NULL == maintenance_tag)
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_maintenance_tag(maintenance_tag, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->maintenance_tags, &iter);
	while (NULL != (maintenance_tag = (zbx_dc_maintenance_tag_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &maintenance_tag->maintenancetagid))
			dbsync_add_row(sync, maintenance_tag->maintenancetagid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares maintenance_period table row with cached configuration   *
 *          dat                                                               *
 *                                                                            *
 * Parameter: period - [IN] the cached maintenance period                     *
 *            dbrow  - [IN] the database row                                  *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_maintenance_period(const zbx_dc_maintenance_period_t *period, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uchar(dbrow[1], period->type))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[2], period->every))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[3], period->month))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[4], period->dayofweek))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[5], period->day))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[6], period->start_time))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[7], period->period))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[8], period->start_date))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares timeperiods table with cached configuration data         *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_maintenance_periods(zbx_dbsync_t *sync)
{
	DB_ROW				dbrow;
	DB_RESULT			result;
	zbx_hashset_t			ids;
	zbx_hashset_iter_t		iter;
	zbx_uint64_t			rowid;
	zbx_dc_maintenance_period_t	*period;

	if (NULL == (result = DBselect("select t.timeperiodid,t.timeperiod_type,t.every,t.month,t.dayofweek,t.day,"
						"t.start_time,t.period,t.start_date,m.maintenanceid"
					" from maintenances_windows m,timeperiods t"
					" where t.timeperiodid=m.timeperiodid")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 10, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, (size_t)dbsync_env.cache->maintenance_periods.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		period = (zbx_dc_maintenance_period_t *)zbx_hashset_search(&dbsync_env.cache->maintenance_periods,
				&rowid);

		if (NULL == period)
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_maintenance_period(period, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->maintenance_periods, &iter);
	while (NULL != (period = (zbx_dc_maintenance_period_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &period->timeperiodid))
			dbsync_add_row(sync, period->timeperiodid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares maintenances_groups table with cached configuration data *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_maintenance_groups(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_iter_t	iter;
	zbx_dc_maintenance_t	*maintenance;
	zbx_hashset_t		mgroups;
	int			i;
	zbx_uint64_pair_t	mg_local, *mg;
	char			maintenanceid_s[MAX_ID_LEN + 1], groupid_s[MAX_ID_LEN + 1];
	char			*del_row[2] = {maintenanceid_s, groupid_s};

	if (NULL == (result = DBselect("select maintenanceid,groupid from maintenances_groups order by maintenanceid")))
		return FAIL;

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&mgroups, 100, ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	/* index all maintenance->group links */
	zbx_hashset_iter_reset(&dbsync_env.cache->maintenances, &iter);
	while (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		mg_local.first = maintenance->maintenanceid;

		for (i = 0; i < maintenance->groupids.values_num; i++)
		{
			mg_local.second = maintenance->groupids.values[i];
			zbx_hashset_insert(&mgroups, &mg_local, sizeof(mg_local));
		}
	}

	/* add new rows, remove existing rows from index */
	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(mg_local.first, dbrow[0]);
		ZBX_STR2UINT64(mg_local.second, dbrow[1]);

		if (NULL == (mg = (zbx_uint64_pair_t *)zbx_hashset_search(&mgroups, &mg_local)))
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_ADD, dbrow);
		else
			zbx_hashset_remove_direct(&mgroups, mg);
	}

	/* add removed rows */
	zbx_hashset_iter_reset(&mgroups, &iter);
	while (NULL != (mg = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_snprintf(maintenanceid_s, sizeof(maintenanceid_s), ZBX_FS_UI64, mg->first);
		zbx_snprintf(groupid_s, sizeof(groupid_s), ZBX_FS_UI64, mg->second);
		dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, del_row);
	}

	DBfree_result(result);
	zbx_hashset_destroy(&mgroups);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares maintenances_hosts table with cached configuration data  *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_maintenance_hosts(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_iter_t	iter;
	zbx_dc_maintenance_t	*maintenance;
	zbx_hashset_t		mhosts;
	int			i;
	zbx_uint64_pair_t	mh_local, *mh;
	char			maintenanceid_s[MAX_ID_LEN + 1], hostid_s[MAX_ID_LEN + 1];
	char			*del_row[2] = {maintenanceid_s, hostid_s};

	if (NULL == (result = DBselect("select maintenanceid,hostid from maintenances_hosts order by maintenanceid")))
		return FAIL;

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&mhosts, 100, ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	/* index all maintenance->host links */
	zbx_hashset_iter_reset(&dbsync_env.cache->maintenances, &iter);
	while (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		mh_local.first = maintenance->maintenanceid;

		for (i = 0; i < maintenance->hostids.values_num; i++)
		{
			mh_local.second = maintenance->hostids.values[i];
			zbx_hashset_insert(&mhosts, &mh_local, sizeof(mh_local));
		}
	}

	/* add new rows, remove existing rows from index */
	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(mh_local.first, dbrow[0]);
		ZBX_STR2UINT64(mh_local.second, dbrow[1]);

		if (NULL == (mh = (zbx_uint64_pair_t *)zbx_hashset_search(&mhosts, &mh_local)))
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_ADD, dbrow);
		else
			zbx_hashset_remove_direct(&mhosts, mh);
	}

	/* add removed rows */
	zbx_hashset_iter_reset(&mhosts, &iter);
	while (NULL != (mh = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_snprintf(maintenanceid_s, sizeof(maintenanceid_s), ZBX_FS_UI64, mh->first);
		zbx_snprintf(hostid_s, sizeof(hostid_s), ZBX_FS_UI64, mh->second);
		dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, del_row);
	}

	DBfree_result(result);
	zbx_hashset_destroy(&mhosts);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares hosts_groups table with cached configuration data        *
 *                                                                            *
 * Parameter: sync - [OUT] the changeset                                      *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_group_hosts(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_iter_t	iter, iter_hosts;
	zbx_dc_hostgroup_t	*group;
	zbx_hashset_t		groups;
	zbx_uint64_t		*phostid;
	zbx_uint64_pair_t	gh_local, *gh;
	char			groupid_s[MAX_ID_LEN + 1], hostid_s[MAX_ID_LEN + 1];
	char			*del_row[2] = {groupid_s, hostid_s};

	if (NULL == (result = DBselect(
			"select hg.groupid,hg.hostid"
			" from hosts_groups hg,hosts h"
			" where hg.hostid=h.hostid"
			" and h.status in (%d,%d)"
			" and h.flags<>%d"
			" order by hg.groupid",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&groups, 100, ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	/* index all group->host links */
	zbx_hashset_iter_reset(&dbsync_env.cache->hostgroups, &iter);
	while (NULL != (group = (zbx_dc_hostgroup_t *)zbx_hashset_iter_next(&iter)))
	{
		gh_local.first = group->groupid;

		zbx_hashset_iter_reset(&group->hostids, &iter_hosts);
		while (NULL != (phostid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter_hosts)))
		{
			gh_local.second = *phostid;
			zbx_hashset_insert(&groups, &gh_local, sizeof(gh_local));
		}
	}

	/* add new rows, remove existing rows from index */
	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(gh_local.first, dbrow[0]);
		ZBX_STR2UINT64(gh_local.second, dbrow[1]);

		if (NULL == (gh = (zbx_uint64_pair_t *)zbx_hashset_search(&groups, &gh_local)))
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_ADD, dbrow);
		else
			zbx_hashset_remove_direct(&groups, gh);
	}

	/* add removed rows */
	zbx_hashset_iter_reset(&groups, &iter);
	while (NULL != (gh = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_snprintf(groupid_s, sizeof(groupid_s), ZBX_FS_UI64, gh->first);
		zbx_snprintf(hostid_s, sizeof(hostid_s), ZBX_FS_UI64, gh->second);
		dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, del_row);
	}

	DBfree_result(result);
	zbx_hashset_destroy(&groups);

	return SUCCEED;
}
