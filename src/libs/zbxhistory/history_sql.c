/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "common.h"
#include "log.h"
#include "zbxalgo.h"
#include "db.h"
#include "dbcache.h"
#include "zbxhistory.h"
#include "history.h"

#define DB_HISTORY_FIELD_ITEMID_INDEX	0
#define DB_HISTORY_FIELD_CLOCK_INDEX	1
#define DB_HISTORY_FIELD_NS_INDEX	3
#define DB_HISTORY_LOG_FIELD_NS_INDEX	7

typedef struct
{
	unsigned char		initialized;
	zbx_vector_ptr_t	dbinserts;
}
zbx_sql_writer_t;

static zbx_sql_writer_t	writer;

typedef void (*vc_str2value_func_t)(history_value_t *value, DB_ROW row);

/* history table data */
typedef struct
{
	/* table name */
	const char		*name;

	/* field list */
	const char		*fields;

	/* string to value converter function, used to convert string value of DB row */
	/* to the value of appropriate type                                           */
	vc_str2value_func_t	rtov;
}
zbx_vc_history_table_t;

/* row to value converters for all value types */
static void	row2value_str(history_value_t *value, DB_ROW row)
{
	value->str = zbx_strdup(NULL, row[0]);
}

static void	row2value_dbl(history_value_t *value, DB_ROW row)
{
	value->dbl = atof(row[0]);
}

static void	row2value_ui64(history_value_t *value, DB_ROW row)
{
	ZBX_STR2UINT64(value->ui64, row[0]);
}

/* timestamp, logeventid, severity, source, value */
static void	row2value_log(history_value_t *value, DB_ROW row)
{
	value->log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));

	value->log->timestamp = atoi(row[0]);
	value->log->logeventid = atoi(row[1]);
	value->log->severity = atoi(row[2]);
	value->log->source = '\0' == *row[3] ? NULL : zbx_strdup(NULL, row[3]);
	value->log->value = zbx_strdup(NULL, row[4]);
}

/* value_type - history table data mapping */
static zbx_vc_history_table_t	vc_history_tables[] = {
	{"history", "value", row2value_dbl},
	{"history_str", "value", row2value_str},
	{"history_log", "timestamp,logeventid,severity,source,value", row2value_log},
	{"history_uint", "value", row2value_ui64},
	{"history_text", "value", row2value_str}
};

typedef struct
{
	zbx_uint64_t		itemid;
	int			clock;
	int			ns;
}
db_history_value_t;

typedef struct
{
	ZBX_TABLE 		*tbl_flt, *tbl_uint, *tbl_str, *tbl_log, *tbl_text;
	zbx_vector_ptr_t	rows_uint, rows_flt, rows_str, rows_log, rows_text;
	zbx_vector_ptr_t	dup_rows_uint, dup_rows_flt, dup_rows_str, dup_rows_log, dup_rows_text;
}
zbx_db_history_dupl_data_t;

/******************************************************************************************************************
 *                                                                                                                *
 * common sql service support                                                                                     *
 *                                                                                                                *
 ******************************************************************************************************************/

/************************************************************************************
 *                                                                                  *
 * Function: sql_writer_init                                                        *
 *                                                                                  *
 * Purpose: initializes sql writer for a new batch of history values                *
 *                                                                                  *
 ************************************************************************************/
static void	sql_writer_init(void)
{
	if (0 != writer.initialized)
		return;

	zbx_vector_ptr_create(&writer.dbinserts);

	writer.initialized = 1;
}

/************************************************************************************
 *                                                                                  *
 * Function: sql_writer_release                                                     *
 *                                                                                  *
 * Purpose: releases initialized sql writer by freeing allocated resources and      *
 *          setting its state to uninitialized.                                     *
 *                                                                                  *
 ************************************************************************************/
static void	sql_writer_release(void)
{
	int	i;

	for (i = 0; i < writer.dbinserts.values_num; i++)
	{
		zbx_db_insert_t	*db_insert = (zbx_db_insert_t *)writer.dbinserts.values[i];

		zbx_db_insert_clean(db_insert);
		zbx_free(db_insert);
	}
	zbx_vector_ptr_clear(&writer.dbinserts);
	zbx_vector_ptr_destroy(&writer.dbinserts);

	writer.initialized = 0;
}

/************************************************************************************
 *                                                                                  *
 * Function: sql_writer_add_dbinsert                                                *
 *                                                                                  *
 * Purpose: adds bulk insert data to be flushed later                               *
 *                                                                                  *
 * Parameters: db_insert - [IN] bulk insert data                                    *
 *                                                                                  *
 ************************************************************************************/
static void	sql_writer_add_dbinsert(zbx_db_insert_t *db_insert)
{
	sql_writer_init();
	zbx_vector_ptr_append(&writer.dbinserts, db_insert);
}

static void	create_dupl_selects(char **sql, zbx_vector_ptr_t *hist_values, const char *table)
{
	int	i;
	size_t		sql_alloc = 0, sql_offset = 0;

	for (i = 0; i < hist_values->values_num; i++)
	{
		db_history_value_t *val = hist_values->values[i];

		if (*sql == NULL)
		{
			zbx_snprintf_alloc(sql, &sql_alloc, &sql_offset, "select itemid,clock,ns from %s where (itemid="
					ZBX_FS_UI64 " and clock=%i and ns=%i)", table, val->itemid, val->clock, val->ns);
		}
		else
		{
			zbx_snprintf_alloc(sql, &sql_alloc, &sql_offset, " or (itemid=" ZBX_FS_UI64 " and clock=%i and"
					" ns=%i)", val->itemid, val->clock, val->ns);
		}
	}
}

static int	history_value_compare_func(const void *d1, const void *d2)
{
	const zbx_db_value_t		*h1 = *(const zbx_db_value_t **)d1;
	const db_history_value_t	*h2 = *(const db_history_value_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h2->itemid, h1[0].ui64);
	ZBX_RETURN_IF_NOT_EQUAL(h2->clock, h1[1].i32);
	ZBX_RETURN_IF_NOT_EQUAL(h2->ns, h1[3].i32);

	return 0;
}

static void	init_history_dupl_data(zbx_db_history_dupl_data_t *data)
{
	data->tbl_flt = (ZBX_TABLE *)DBget_table("history");
	data->tbl_uint = (ZBX_TABLE *)DBget_table("history_uint");
	data->tbl_str = (ZBX_TABLE *)DBget_table("history_str");
	data->tbl_log = (ZBX_TABLE *)DBget_table("history_log");
	data->tbl_text = (ZBX_TABLE *)DBget_table("history_text");

	zbx_vector_ptr_create(&data->rows_uint);
	zbx_vector_ptr_create(&data->rows_flt);
	zbx_vector_ptr_create(&data->rows_str);
	zbx_vector_ptr_create(&data->rows_log);
	zbx_vector_ptr_create(&data->rows_text);

	zbx_vector_ptr_create(&data->dup_rows_uint);
	zbx_vector_ptr_create(&data->dup_rows_flt);
	zbx_vector_ptr_create(&data->dup_rows_str);
	zbx_vector_ptr_create(&data->dup_rows_log);
	zbx_vector_ptr_create(&data->dup_rows_text);
}

static void	destroy_history_dupl_data(zbx_db_history_dupl_data_t *data)
{
	zbx_vector_ptr_clear_ext(&data->rows_uint, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->rows_flt, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->rows_str, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->rows_log, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->rows_text, (zbx_clean_func_t)zbx_ptr_free);

	zbx_vector_ptr_clear_ext(&data->dup_rows_uint, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->dup_rows_flt, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->dup_rows_str, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->dup_rows_log, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&data->dup_rows_text, (zbx_clean_func_t)zbx_ptr_free);

	zbx_vector_ptr_destroy(&data->rows_uint);
	zbx_vector_ptr_destroy(&data->rows_flt);
	zbx_vector_ptr_destroy(&data->rows_str);
	zbx_vector_ptr_destroy(&data->rows_log);
	zbx_vector_ptr_destroy(&data->rows_text);

	zbx_vector_ptr_destroy(&data->dup_rows_uint);
	zbx_vector_ptr_destroy(&data->dup_rows_flt);
	zbx_vector_ptr_destroy(&data->dup_rows_str);
	zbx_vector_ptr_destroy(&data->dup_rows_log);
	zbx_vector_ptr_destroy(&data->dup_rows_text);
}

static void	remove_duplicate_values(zbx_vector_ptr_t *duplicates, const ZBX_TABLE *tbl)
{
	int	i, j, idx;

	for (i = 0; i < duplicates->values_num; i++)
	{
		db_history_value_t *dup_val = duplicates->values[i];
		for (j = 0; j < writer.dbinserts.values_num; j++)
		{
			zbx_db_insert_t	*db_insert;

			db_insert = (zbx_db_insert_t *)writer.dbinserts.values[j];

			if (db_insert->table != tbl)
				continue;

			if (SUCCEED == (idx = zbx_vector_ptr_search(&db_insert->rows, dup_val,
					history_value_compare_func)))
			{
				zbx_vector_ptr_remove(&db_insert->rows, idx);
			}
		}
	}
}

static void	select_duplicate_values(char *sql, zbx_vector_ptr_t *duplicates)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		db_history_value_t *hist_value = (db_history_value_t *)zbx_malloc(NULL, sizeof(db_history_value_t));
		ZBX_STR2UINT64(hist_value->itemid, row[0]);
		hist_value->clock = atoi(row[1]);
		hist_value->ns = atoi(row[2]);

		zbx_vector_ptr_append(duplicates, hist_value);
	}
	DBfree_result(result);
}

static void	db_insert_rows_to_values(zbx_db_insert_t *insert, zbx_vector_ptr_t *rows, const int itemid_idx,
		const int clock_idx, const int ns_idx)
{
	int	i;

	for (i = 0; i < insert->rows.values_num; i++) {
		zbx_db_value_t	*dbv = insert->rows.values[i];

		db_history_value_t *hist_value = (db_history_value_t *)zbx_malloc(NULL, sizeof(db_history_value_t));
		hist_value->itemid = dbv[itemid_idx].ui64;
		hist_value->clock = dbv[clock_idx].i32;
		hist_value->ns = dbv[ns_idx].i32;

		zbx_vector_ptr_append(rows, hist_value);
	}
}

static void	sql_writer_reinsert_duplicates()
{
	zbx_db_history_dupl_data_t	data;
	int				i;
	size_t				j;
	char				*select_uint = NULL, *select_flt = NULL, *select_str = NULL,
					*select_log = NULL, *select_text = NULL;

	void				*history_tables[][5] = {
		{&select_flt,	&data.rows_flt,		&data.dup_rows_flt,	"history",	data.tbl_flt},
		{&select_uint,	&data.rows_uint,	&data.dup_rows_uint,	"history_uint",	data.tbl_uint},
		{&select_str,	&data.rows_str,		&data.dup_rows_str,	"history_str",	data.tbl_str},
		{&select_log,	&data.rows_log,		&data.dup_rows_log,	"history_log",	data.tbl_log},
		{&select_text,	&data.rows_text,	&data.dup_rows_text,	"history_text",	data.tbl_text},
	};

	init_history_dupl_data(&data);

	for (i = 0; i < writer.dbinserts.values_num; i++)
	{
		zbx_db_insert_t		*db_insert;

		db_insert = (zbx_db_insert_t *)writer.dbinserts.values[i];

		if (db_insert->table == data.tbl_uint)
		{
			db_insert_rows_to_values(db_insert, &data.rows_uint, DB_HISTORY_FIELD_ITEMID_INDEX,
					DB_HISTORY_FIELD_CLOCK_INDEX, DB_HISTORY_FIELD_NS_INDEX);
		}
		else if (db_insert->table == data.tbl_flt)
		{
			db_insert_rows_to_values(db_insert, &data.rows_flt, DB_HISTORY_FIELD_ITEMID_INDEX,
					DB_HISTORY_FIELD_CLOCK_INDEX, DB_HISTORY_FIELD_NS_INDEX);
		}
		else if (db_insert->table == data.tbl_str)
		{
			db_insert_rows_to_values(db_insert, &data.rows_str, DB_HISTORY_FIELD_ITEMID_INDEX,
					DB_HISTORY_FIELD_CLOCK_INDEX, DB_HISTORY_FIELD_NS_INDEX);
		}
		else if (db_insert->table == data.tbl_log)
		{
			db_insert_rows_to_values(db_insert, &data.rows_log, DB_HISTORY_FIELD_ITEMID_INDEX,
					DB_HISTORY_FIELD_CLOCK_INDEX, DB_HISTORY_LOG_FIELD_NS_INDEX);
		}
		else if (db_insert->table == data.tbl_text)
		{
			db_insert_rows_to_values(db_insert, &data.rows_text, DB_HISTORY_FIELD_ITEMID_INDEX,
					DB_HISTORY_FIELD_CLOCK_INDEX, DB_HISTORY_FIELD_NS_INDEX);
		}
	}

	for (j = 0; j < ARRSIZE(history_tables); j++)
	{
		char			**select;
		zbx_vector_ptr_t	*rows;
		zbx_vector_ptr_t	*dup_rows;
		const char		*table_name;
		const ZBX_TABLE		*tbl;

		select = history_tables[j][0];
		rows = history_tables[j][1];
		dup_rows = history_tables[j][2];
		table_name = history_tables[j][3];
		tbl = history_tables[j][4];

		create_dupl_selects(select, rows, table_name);

		if (NULL == *select)
			continue;

		select_duplicate_values(*select, dup_rows);
		remove_duplicate_values(dup_rows, tbl);
	}

	destroy_history_dupl_data(&data);

	zbx_free(select_uint);
	zbx_free(select_log);
	zbx_free(select_text);
	zbx_free(select_str);
	zbx_free(select_flt);
}

/************************************************************************************
 *                                                                                  *
 * Function: sql_writer_flush                                                       *
 *                                                                                  *
 * Purpose: commits bulk insert data to be flushed into database                    *
 *                                                                                  *
 ************************************************************************************/
static void	sql_writer_flush_commit(int *txn_error)
{
	int	i;

	do
	{
		DBbegin();

		for (i = 0; i < writer.dbinserts.values_num; i++)
		{
			zbx_db_insert_t	*db_insert = (zbx_db_insert_t *)writer.dbinserts.values[i];
			zbx_db_insert_execute(db_insert);
		}
	}
	while (ZBX_DB_DOWN == (*txn_error = DBcommit()));
}

/************************************************************************************
 *                                                                                  *
 * Function: sql_writer_flush                                                       *
 *                                                                                  *
 * Purpose: flushes bulk insert data into database                                  *
 *                                                                                  *
 ************************************************************************************/
static int	sql_writer_flush(void)
{
	int	txn_error;

	/* The writer might be uninitialized only if the history */
	/* was already flushed. In that case, return SUCCEED */
	if (0 == writer.initialized)
		return SUCCEED;

	sql_writer_flush_commit(&txn_error);

	if (ZBX_DB_FAIL == txn_error && zbx_db_last_errcode() == ERR_Z3008)
	{
		sql_writer_reinsert_duplicates();
		sql_writer_flush_commit(&txn_error);
	}

	sql_writer_release();

	return ZBX_DB_OK == txn_error ? SUCCEED : FAIL;
}

/******************************************************************************************************************
 *                                                                                                                *
 * database writing support                                                                                       *
 *                                                                                                                *
 ******************************************************************************************************************/

typedef void (*add_history_func_t)(const zbx_vector_ptr_t *history);

/******************************************************************************
 *                                                                            *
 * Function: add_history_dbl                                                  *
 *                                                                            *
 ******************************************************************************/
static void	add_history_dbl(const zbx_vector_ptr_t *history)
{
	int		i;
	zbx_db_insert_t	*db_insert;

	db_insert = (zbx_db_insert_t *)zbx_malloc(NULL, sizeof(zbx_db_insert_t));
	zbx_db_insert_prepare(db_insert, "history", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history->values_num; i++)
	{
		const ZBX_DC_HISTORY	*h = (ZBX_DC_HISTORY *)history->values[i];

		if (ITEM_VALUE_TYPE_FLOAT != h->value_type)
			continue;

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.dbl);
	}

	sql_writer_add_dbinsert(db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: add_history_uint                                                 *
 *                                                                            *
 ******************************************************************************/
static void	add_history_uint(zbx_vector_ptr_t *history)
{
	int		i;
	zbx_db_insert_t	*db_insert;

	db_insert = (zbx_db_insert_t *)zbx_malloc(NULL, sizeof(zbx_db_insert_t));
	zbx_db_insert_prepare(db_insert, "history_uint", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history->values_num; i++)
	{
		const ZBX_DC_HISTORY	*h = (ZBX_DC_HISTORY *)history->values[i];

		if (ITEM_VALUE_TYPE_UINT64 != h->value_type)
			continue;

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.ui64);
	}

	sql_writer_add_dbinsert(db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: add_history_str                                                  *
 *                                                                            *
 ******************************************************************************/
static void	add_history_str(zbx_vector_ptr_t *history)
{
	int		i;
	zbx_db_insert_t	*db_insert;

	db_insert = (zbx_db_insert_t *)zbx_malloc(NULL, sizeof(zbx_db_insert_t));
	zbx_db_insert_prepare(db_insert, "history_str", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history->values_num; i++)
	{
		const ZBX_DC_HISTORY	*h = (ZBX_DC_HISTORY *)history->values[i];

		if (ITEM_VALUE_TYPE_STR != h->value_type)
			continue;

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.str);
	}

	sql_writer_add_dbinsert(db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: add_history_text                                                 *
 *                                                                            *
 ******************************************************************************/
static void	add_history_text(zbx_vector_ptr_t *history)
{
	int		i;
	zbx_db_insert_t	*db_insert;

	db_insert = (zbx_db_insert_t *)zbx_malloc(NULL, sizeof(zbx_db_insert_t));
	zbx_db_insert_prepare(db_insert, "history_text", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history->values_num; i++)
	{
		const ZBX_DC_HISTORY	*h = (ZBX_DC_HISTORY *)history->values[i];

		if (ITEM_VALUE_TYPE_TEXT != h->value_type)
			continue;

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.str);
	}

	sql_writer_add_dbinsert(db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: add_history_log                                                  *
 *                                                                            *
 ******************************************************************************/
static void	add_history_log(zbx_vector_ptr_t *history)
{
	int			i;
	zbx_db_insert_t	*db_insert;

	db_insert = (zbx_db_insert_t *)zbx_malloc(NULL, sizeof(zbx_db_insert_t));
	zbx_db_insert_prepare(db_insert, "history_log", "itemid", "clock", "ns", "timestamp", "source", "severity",
			"value", "logeventid", NULL);

	for (i = 0; i < history->values_num; i++)
	{
		const ZBX_DC_HISTORY	*h = (ZBX_DC_HISTORY *)history->values[i];
		const zbx_log_value_t	*log;

		if (ITEM_VALUE_TYPE_LOG != h->value_type)
			continue;

		log = h->value.log;

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, log->timestamp,
				ZBX_NULL2EMPTY_STR(log->source), log->severity, log->value, log->logeventid);
	}

	sql_writer_add_dbinsert(db_insert);
}

/******************************************************************************************************************
 *                                                                                                                *
 * database reading support                                                                                       *
 *                                                                                                                *
 ******************************************************************************************************************/

/*********************************************************************************
 *                                                                               *
 * Function: db_read_values_by_time                                              *
 *                                                                               *
 * Purpose: reads item history data from database                                *
 *                                                                               *
 * Parameters:  itemid        - [IN] the itemid                                  *
 *              value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs) *
 *              values        - [OUT] the item history data values               *
 *              seconds       - [IN] the time period to read                     *
 *              end_timestamp - [IN] the value timestamp to start reading with   *
 *                                                                               *
 * Return value: SUCCEED - the history data were read successfully               *
 *               FAIL - otherwise                                                *
 *                                                                               *
 * Comments: This function reads all values with timestamps in range:            *
 *             end_timestamp - seconds < <value timestamp> <= end_timestamp      *
 *                                                                               *
 *********************************************************************************/
static int	db_read_values_by_time(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values,
		int seconds, int end_timestamp)
{
	char			*sql = NULL;
	size_t	 		sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select clock,ns,%s"
			" from %s"
			" where itemid=" ZBX_FS_UI64,
			table->fields, table->name, itemid);

	if (ZBX_JAN_2038 == end_timestamp)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>%d", end_timestamp - seconds);
	}
	else if (1 == seconds)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock=%d", end_timestamp);
	}
	else
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>%d and clock<=%d",
				end_timestamp - seconds, end_timestamp);
	}

	result = DBselect("%s", sql);

	zbx_free(sql);

	if (NULL == result)
		goto out;

	while (NULL != (row = DBfetch(result)))
	{
		zbx_history_record_t	value;

		value.timestamp.sec = atoi(row[0]);
		value.timestamp.ns = atoi(row[1]);
		table->rtov(&value.value, row + 2);

		zbx_vector_history_record_append_ptr(values, &value);
	}
	DBfree_result(result);
out:
	return SUCCEED;
}

/************************************************************************************
 *                                                                                  *
 * Function: db_read_values_by_count                                                *
 *                                                                                  *
 * Purpose: reads item history data from database                                   *
 *                                                                                  *
 * Parameters:  itemid        - [IN] the itemid                                     *
 *              value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs)    *
 *              values        - [OUT] the item history data values                  *
 *              count         - [IN] the number of values to read                   *
 *              end_timestamp - [IN] the value timestamp to start reading with      *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: this function reads <count> values before <count_timestamp> (including)*
 *           plus all values in range:                                              *
 *             count_timestamp < <value timestamp> <= read_timestamp                *
 *                                                                                  *
 *           To speed up the reading time with huge data loads, data is read by     *
 *           smaller time segments (hours, day, week, month) and the next (larger)  *
 *           time segment is read only if the requested number of values (<count>)  *
 *           is not yet retrieved.                                                  *
 *                                                                                  *
 ************************************************************************************/
static int	db_read_values_by_count(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values,
		int count, int end_timestamp)
{
	char			*sql = NULL;
	size_t	 		sql_alloc = 0, sql_offset;
	int			clock_to, clock_from, step = 0, ret = FAIL;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];
	const int		periods[] = {SEC_PER_HOUR, SEC_PER_DAY, SEC_PER_WEEK, SEC_PER_MONTH, 0, -1};

	clock_to = end_timestamp;

	while (-1 != periods[step] && 0 < count)
	{
		if (0 > (clock_from = clock_to - periods[step]))
		{
			clock_from = clock_to;
			step = 4;
		}

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select clock,ns,%s"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock<=%d",
				table->fields, table->name, itemid, clock_to);

		if (clock_from != clock_to)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>%d", clock_from);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by clock desc");

		result = DBselectN(sql, count);

		if (NULL == result)
			goto out;

		while (NULL != (row = DBfetch(result)))
		{
			zbx_history_record_t	value;

			value.timestamp.sec = atoi(row[0]);
			value.timestamp.ns = atoi(row[1]);
			table->rtov(&value.value, row + 2);

			zbx_vector_history_record_append_ptr(values, &value);

			count--;
		}
		DBfree_result(result);

		clock_to -= periods[step];
		step++;
	}


	if (0 < count)
	{
		/* no more data in database, return success */
		ret = SUCCEED;
		goto out;
	}

	/* drop data from the last second and read the whole second again  */
	/* to ensure that data is cached by seconds                        */
	end_timestamp = values->values[values->values_num - 1].timestamp.sec;

	while (0 < values->values_num && values->values[values->values_num - 1].timestamp.sec == end_timestamp)
	{
		values->values_num--;
		zbx_history_record_clear(&values->values[values->values_num], value_type);
	}

	ret = db_read_values_by_time(itemid, value_type, values, 1, end_timestamp);
out:
	zbx_free(sql);

	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Function: db_read_values_by_time_and_count                                       *
 *                                                                                  *
 * Purpose: reads item history data from database                                   *
 *                                                                                  *
 * Parameters:  itemid        - [IN] the itemid                                     *
 *              value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs)    *
 *              values        - [OUT] the item history data values                  *
 *              seconds       - [IN] the time period to read                        *
 *              count         - [IN] the number of values to read                   *
 *              end_timestamp - [IN] the value timestamp to start reading with      *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: this function reads <count> values from <seconds> period before        *
 *           <count_timestamp> (including) plus all values in range:                *
 *             count_timestamp < <value timestamp> <= read_timestamp                *
 *                                                                                  *
 ************************************************************************************/
static int	db_read_values_by_time_and_count(zbx_uint64_t itemid, int value_type,
		zbx_vector_history_record_t *values, int seconds, int count, int end_timestamp)
{
	int			ret = FAIL;
	char			*sql = NULL;
	size_t	 		sql_alloc = 0, sql_offset;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select clock,ns,%s"
			" from %s"
			" where itemid=" ZBX_FS_UI64,
			table->fields, table->name, itemid);

	if (1 == seconds)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock=%d", end_timestamp);
	}
	else
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>%d and clock<=%d order by clock desc",
				end_timestamp - seconds, end_timestamp);
	}

	result = DBselectN(sql, count);

	zbx_free(sql);

	if (NULL == result)
		goto out;

	while (NULL != (row = DBfetch(result)) && 0 < count--)
	{
		zbx_history_record_t	value;

		value.timestamp.sec = atoi(row[0]);
		value.timestamp.ns = atoi(row[1]);
		table->rtov(&value.value, row + 2);

		zbx_vector_history_record_append_ptr(values, &value);
	}
	DBfree_result(result);

	if (0 < count)
	{
		/* no more data in the specified time period, return success */
		ret = SUCCEED;
		goto out;
	}

	/* Drop data from the last second and read the whole second again     */
	/* to ensure that data is cached by seconds.                          */
	/* Because the initial select has limit option (DBselectN()) we have  */
	/* to perform another select to read the last second data.            */
	end_timestamp = values->values[values->values_num - 1].timestamp.sec;

	while (0 < values->values_num && values->values[values->values_num - 1].timestamp.sec == end_timestamp)
	{
		values->values_num--;
		zbx_history_record_clear(&values->values[values->values_num], value_type);
	}

	ret = db_read_values_by_time(itemid, value_type, values, 1, end_timestamp);
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************************************************
 *                                                                                                                *
 * history interface support                                                                                      *
 *                                                                                                                *
 ******************************************************************************************************************/

/************************************************************************************
 *                                                                                  *
 * Function: sql_destroy                                                            *
 *                                                                                  *
 * Purpose: destroys history storage interface                                      *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *                                                                                  *
 ************************************************************************************/
static void	sql_destroy(zbx_history_iface_t *hist)
{
	ZBX_UNUSED(hist);
}

/************************************************************************************
 *                                                                                  *
 * Function: sql_get_values                                                         *
 *                                                                                  *
 * Purpose: gets item history data from history storage                             *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              itemid  - [IN] the itemid                                           *
 *              start   - [IN] the period start timestamp                           *
 *              count   - [IN] the number of values to read                         *
 *              end     - [IN] the period end timestamp                             *
 *              values  - [OUT] the item history data values                        *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
static int	sql_get_values(zbx_history_iface_t *hist, zbx_uint64_t itemid, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	if (0 == count)
		return db_read_values_by_time(itemid, hist->value_type, values, end - start, end);

	if (0 == start)
		return db_read_values_by_count(itemid, hist->value_type, values, count, end);

	return db_read_values_by_time_and_count(itemid, hist->value_type, values, end - start, count, end);
}

/************************************************************************************
 *                                                                                  *
 * Function: sql_add_values                                                         *
 *                                                                                  *
 * Purpose: sends history data to the storage                                       *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              history - [IN] the history data vector (may have mixed value types) *
 *                                                                                  *
 ************************************************************************************/
static int	sql_add_values(zbx_history_iface_t *hist, const zbx_vector_ptr_t *history)
{
	int	i, h_num = 0;

	for (i = 0; i < history->values_num; i++)
	{
		const ZBX_DC_HISTORY	*h = (ZBX_DC_HISTORY *)history->values[i];

		if (h->value_type == hist->value_type)
			h_num++;
	}

	if (0 != h_num)
	{
		add_history_func_t	add_history_func = (add_history_func_t)hist->data;
		add_history_func(history);
	}

	return h_num;
}

/************************************************************************************
 *                                                                                  *
 * Function: sql_flush                                                              *
 *                                                                                  *
 * Purpose: flushes the history data to storage                                     *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *                                                                                  *
 * Comments: This function will try to flush the data until it succeeds or          *
 *           unrecoverable error occurs                                             *
 *                                                                                  *
 ************************************************************************************/
static int	sql_flush(zbx_history_iface_t *hist)
{
	ZBX_UNUSED(hist);

	return sql_writer_flush();
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_sql_init                                                   *
 *                                                                                  *
 * Purpose: initializes history storage interface                                   *
 *                                                                                  *
 * Parameters:  hist       - [IN] the history storage interface                     *
 *              value_type - [IN] the target value type                             *
 *              error      - [OUT] the error message                                *
 *                                                                                  *
 * Return value: SUCCEED - the history storage interface was initialized            *
 *               FAIL    - otherwise                                                *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_sql_init(zbx_history_iface_t *hist, unsigned char value_type, char **error)
{
	ZBX_UNUSED(error);

	hist->value_type = value_type;

	hist->destroy = sql_destroy;
	hist->add_values = sql_add_values;
	hist->flush = sql_flush;
	hist->get_values = sql_get_values;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			hist->data = (void *)add_history_dbl;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			hist->data = (void *)add_history_uint;
			break;
		case ITEM_VALUE_TYPE_STR:
			hist->data = (void *)add_history_str;
			break;
		case ITEM_VALUE_TYPE_TEXT:
			hist->data = (void *)add_history_text;
			break;
		case ITEM_VALUE_TYPE_LOG:
			hist->data = (void *)add_history_log;
			break;
	}

	hist->requires_trends = 1;

	return SUCCEED;
}
