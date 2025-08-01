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

#include "zbxhistory.h"
#include "history.h"
#include "history_sql.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtypes.h"

ZBX_PTR_VECTOR_DECL(db_insert_ptr, zbx_db_insert_t *)
ZBX_PTR_VECTOR_IMPL(db_insert_ptr, zbx_db_insert_t *)

typedef struct
{
	zbx_vector_db_insert_ptr_t	db_inserts;
}
zbx_history_sql_data_t;

typedef void (*vc_str2value_func_t)(zbx_history_value_t *value, zbx_db_row_t row);

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
static void	row2value_str(zbx_history_value_t *value, zbx_db_row_t row)
{
	value->str = zbx_strdup(NULL, row[0]);
}

static void	row2value_dbl(zbx_history_value_t *value, zbx_db_row_t row)
{
	value->dbl = atof(row[0]);
}

static void	row2value_ui64(zbx_history_value_t *value, zbx_db_row_t row)
{
	ZBX_STR2UINT64(value->ui64, row[0]);
}

/* timestamp, logeventid, severity, source, value */
static void	row2value_log(zbx_history_value_t *value, zbx_db_row_t row)
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
	{"history_text", "value", row2value_str},
	{"history_bin", "value", row2value_str}
};

/******************************************************************************************************************
 *                                                                                                                *
 * common sql service support                                                                                     *
 *                                                                                                                *
 ******************************************************************************************************************/

static void	*history_sql_create_data(void)
{
	zbx_history_sql_data_t	*data;

	data = (zbx_history_sql_data_t *)zbx_malloc(NULL, sizeof(zbx_history_sql_data_t));
	zbx_vector_db_insert_ptr_create(&data->db_inserts);

	return (void *)data;
}

static void	history_sql_close(void *data)
{
	zbx_history_sql_data_t	*d = (zbx_history_sql_data_t *)data;

	zbx_vector_db_insert_ptr_destroy(&d->db_inserts);
	zbx_free(d);
}

static void	db_insert_free(zbx_db_insert_t *db_insert)
{
	zbx_db_insert_clean(db_insert);
	zbx_free(db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Create a bitmask of flush errors for SQL inserts based on batch   *
 *          value types                                                       *
 *                                                                            *
 * Parameters:                                                                *
 *     data - [IN] SQL history data                                           *
 *     ret  - [IN] return value from the previous flush operation             *
 *                                                                            *
 * Return value:                                                              *
 *     A bitmask containing flush error statuses for each value type.         *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	history_sql_make_flush_error(zbx_history_sql_data_t *data, int ret)
{
	zbx_uint64_t	err = 0;

	if (ZBX_HISTORY_FLUSH_SUCCEED == ret)
		return 0;

	for (int i = 0; i < data->db_inserts.values_num; i++)
	{
		zbx_db_insert_t        *db_insert = data->db_inserts.values[i];

		if (0 == strcmp(db_insert->table->table, "history"))
			err |= history_make_flush_error(ret, 0);
		else if (0 == strcmp(db_insert->table->table, "history_str"))
			err |= history_make_flush_error(ret, 1);
		else if (0 == strcmp(db_insert->table->table, "history_log"))
			err |= history_make_flush_error(ret, 2);
		else if (0 == strcmp(db_insert->table->table, "history_uint"))
			err |= history_make_flush_error(ret, 3);
		else if (0 == strcmp(db_insert->table->table, "history_text"))
			err |= history_make_flush_error(ret, 4);
		else
			THIS_SHOULD_NEVER_HAPPEN_MSG("unknown history table: %s", db_insert->table->table);
	}

	return err;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: flushes bulk insert data into database                                  *
 *                                                                                  *
 ************************************************************************************/
static zbx_uint64_t	history_sql_flush(void *data)
{
	int			txn_error, ret;
	zbx_history_sql_data_t	*d = (zbx_history_sql_data_t *)data;
	zbx_uint64_t		flush_err;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	do
	{
		zbx_db_begin();

		for (int i = 0; i < d->db_inserts.values_num; i++)
			zbx_db_insert_execute(d->db_inserts.values[i]);
	}
	while (ZBX_DB_DOWN == (txn_error = zbx_db_commit()));

	if (ZBX_DB_OK == txn_error)
	{
		ret = ZBX_HISTORY_FLUSH_SUCCEED;
		goto out;
	}
	else
	{
		if (ZBX_DB_FAIL == txn_error && ERR_Z3008 == zbx_db_last_errcode())
			ret = ZBX_HISTORY_FLUSH_DUPL_REJECTED;
		else
			ret = ZBX_HISTORY_FLUSH_FAIL;
	}

out:
	flush_err = history_sql_make_flush_error(data, ret);

	zbx_vector_db_insert_ptr_clear_ext(&d->db_inserts, db_insert_free);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d (%lx)", __func__, ret, flush_err);

	return flush_err;
}

/******************************************************************************************************************
 *                                                                                                                *
 * database writing support                                                                                       *
 *                                                                                                                *
 ******************************************************************************************************************/

static void	sql_write_dbl(zbx_db_insert_t *db_insert, const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_db_insert_prepare(db_insert, "history", "itemid", "clock", "ns", "value", (char *)NULL);

	for (int i = 0; i < entries_num; i++)
	{
		const zbx_history_entry_t	*h = entries[i];

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.dbl);
	}
}

static void	sql_write_uint(zbx_db_insert_t *db_insert, const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_db_insert_prepare(db_insert, "history_uint", "itemid", "clock", "ns", "value", (char *)NULL);

	for (int i = 0; i < entries_num; i++)
	{
		const zbx_history_entry_t	*h = entries[i];

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.ui64);
	}
}

static void	sql_write_str(zbx_db_insert_t *db_insert, const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_db_insert_prepare(db_insert, "history_str", "itemid", "clock", "ns", "value", (char *)NULL);

	for (int i = 0; i < entries_num; i++)
	{
		const zbx_history_entry_t	*h = entries[i];

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.str);
	}
}

static void	sql_write_text(zbx_db_insert_t *db_insert, const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_db_insert_prepare(db_insert, "history_text", "itemid", "clock", "ns", "value", (char *)NULL);

	for (int i = 0; i < entries_num; i++)
	{
		const zbx_history_entry_t	*h = entries[i];

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.str);
	}
}

static void	sql_write_log(zbx_db_insert_t *db_insert, const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_db_insert_prepare(db_insert, "history_log", "itemid", "clock", "ns", "timestamp", "source", "severity",
			"value", "logeventid", (char *)NULL);

	for (int i = 0; i < entries_num; i++)
	{
		const zbx_history_entry_t	*h = entries[i];
		const zbx_log_value_t	*log;

		log = h->value.log;

		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, log->timestamp,
				ZBX_NULL2EMPTY_STR(log->source), log->severity, log->value, log->logeventid);
	}
}

static void	sql_write_bin(zbx_db_insert_t *db_insert, const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_db_insert_prepare(db_insert, "history_bin", "itemid", "clock", "ns", "value", (char *)NULL);

	for (int i = 0; i < entries_num; i++)
	{
		const zbx_history_entry_t	*h = entries[i];
		zbx_db_insert_add_values(db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.str);
	}
}

/******************************************************************************************************************
 *                                                                                                                *
 * database reading support                                                                                       *
 *                                                                                                                *
 ******************************************************************************************************************/

/*********************************************************************************
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
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];
	time_t			time_from;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select clock,ns,%s"
			" from %s"
			" where itemid=" ZBX_FS_UI64,
			table->fields, table->name, itemid);

	time_from = end_timestamp - seconds;

	zbx_recalc_time_period(&time_from, ZBX_RECALC_TIME_PERIOD_HISTORY);

	if (ZBX_JAN_2038 == end_timestamp)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>" ZBX_FS_TIME_T,
				(zbx_fs_time_t)time_from);
	}
	else if (1 == seconds)
	{
		if (time_from != end_timestamp - seconds)
		{
			zbx_free(sql);
			goto out;
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock=%d", end_timestamp);
	}
	else
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>" ZBX_FS_TIME_T " and clock<=%d",
				(zbx_fs_time_t)time_from, end_timestamp);
	}

	result = zbx_db_select("%s", sql);

	zbx_free(sql);

	if (NULL == result)
		goto out;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_history_record_t	value;

		value.timestamp.sec = atoi(row[0]);
		value.timestamp.ns = atoi(row[1]);
		table->rtov(&value.value, row + 2);

		zbx_vector_history_record_append_ptr(values, &value);
	}
	zbx_db_free_result(result);
out:
	return SUCCEED;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: reads item history data from database                                   *
 *                                                                                  *
 * Parameters:  itemid        - [IN] the itemid                                     *
 *              value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs)    *
 *              values        - [OUT] the item history data values                  *
 *              count         - [IN] the number of values to read + 1               *
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
	time_t			clock_from;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	int			clock_to, step = 0, ret = FAIL;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];
	const int		periods[] = {SEC_PER_HOUR, 12 * SEC_PER_HOUR, SEC_PER_DAY, SEC_PER_DAY, SEC_PER_WEEK,
					SEC_PER_MONTH, 0, -1};

	clock_to = end_timestamp;

	while (-1 != periods[step] && 1 < count)
	{
		if (0 > (clock_from = clock_to - periods[step]))
		{
			clock_from = clock_to;

			step = ARRSIZE(periods) - 1;
		}

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select clock,ns,%s"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock<=%d",
				table->fields, table->name, itemid, clock_to);

		if (clock_from != clock_to)
		{
			zbx_recalc_time_period(&clock_from, ZBX_RECALC_TIME_PERIOD_HISTORY);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>" ZBX_FS_TIME_T,
					(zbx_fs_time_t)clock_from);
		}

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by clock desc");

		result = zbx_db_select_n(sql, count);

		if (NULL == result)
			goto out;

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_history_record_t	value;

			value.timestamp.sec = atoi(row[0]);
			value.timestamp.ns = atoi(row[1]);
			table->rtov(&value.value, row + 2);

			zbx_vector_history_record_append_ptr(values, &value);

			count--;
		}
		zbx_db_free_result(result);

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
	size_t			sql_alloc = 0, sql_offset;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
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

	result = zbx_db_select_n(sql, count);

	zbx_free(sql);

	if (NULL == result)
		goto out;

	while (NULL != (row = zbx_db_fetch(result)) && 0 < count--)
	{
		zbx_history_record_t	value;

		value.timestamp.sec = atoi(row[0]);
		value.timestamp.ns = atoi(row[1]);
		table->rtov(&value.value, row + 2);

		zbx_vector_history_record_append_ptr(values, &value);
	}
	zbx_db_free_result(result);

	if (0 < count)
	{
		/* no more data in the specified time period, return success */
		ret = SUCCEED;
		goto out;
	}

	/* Drop data from the last second and read the whole second again  */
	/* to ensure that data is cached by seconds.                       */
	/* Because the initial select has limit option (zbx_db_select_n()) */
	/* we have to perform another select to read the last second data. */
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
 * history backend support                                                                                        *
 *                                                                                                                *
 ******************************************************************************************************************/

/******************************************************************************
 *                                                                            *
 * Purpose: write history data to SQL database                                *
 *                                                                            *
 * Parameters:                                                                *
 *     data        - [IN] SQL provider internal data                          *
 *     value_type  - [IN] type of values being written                        *
 *     entries     - [IN] array of history entry pointers to write            *
 *     entries_num - [IN] number of entries in the array                      *
 *                                                                            *
 ******************************************************************************/
static void	history_sql_write(void *data, unsigned char value_type, const zbx_history_entry_t * const *entries,
		int entries_num)
{
	zbx_history_sql_data_t	*d = (zbx_history_sql_data_t *)data;
	zbx_db_insert_t			*db_insert;

	db_insert = (zbx_db_insert_t *)zbx_malloc(NULL, sizeof(zbx_db_insert_t));

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			sql_write_dbl(db_insert, entries, entries_num);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			sql_write_uint(db_insert, entries, entries_num);
			break;
		case ITEM_VALUE_TYPE_STR:
			sql_write_str(db_insert, entries, entries_num);
			break;
		case ITEM_VALUE_TYPE_TEXT:
			sql_write_text(db_insert, entries, entries_num);
			break;
		case ITEM_VALUE_TYPE_LOG:
			sql_write_log(db_insert, entries, entries_num);
			break;
		case ITEM_VALUE_TYPE_BIN:
			sql_write_bin(db_insert, entries, entries_num);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	zbx_vector_db_insert_ptr_append(&d->db_inserts, db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch item history data from SQL database                         *
 *                                                                            *
 * Parameters: data       - [IN] history provider data                        *
 *             itemid     - [IN] the itemid                                   *
 *             value_type - [IN] the item value type                          *
 *             start      - [IN] the period start timestamp                   *
 *             end        - [IN] the period end timestamp                     *
 *             count      - [IN] the number of values to read                 *
 *             values     - [OUT] the item history records                    *
 *             error      - [OUT] error message                               *
 *                                                                            *
 * Return value: >=0      - number of records retrieved                       *
 *               FAIL     - otherwise                                         *
 *                                                                            *
 * Comments: The                                                              *
 *                                                                            *
 ******************************************************************************/
static int	history_sql_fetch(void *data, zbx_uint64_t itemid, unsigned char value_type, time_t start, time_t end,
		int count, zbx_history_record_t **values, char **error)
{
	zbx_vector_history_record_t	result;

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);

	zbx_vector_history_record_create(&result);

	if (0 == count)
		db_read_values_by_time(itemid, value_type, &result, end - start, end);
	else if (0 == start)
		db_read_values_by_count(itemid, value_type, &result, count, end);
	else
		db_read_values_by_time_and_count(itemid, value_type, &result, end - start, count, end);

	*values = result.values;

	return result.values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve information about the SQL history storage provider       *
 *                                                                            *
 * Parameters:                                                                *
 *     info  - [OUT] pointer to structure for storing module information      *
 *     error - [OUT] error message in case of failure                         *
 *                                                                            *
 * Return value: SUCCEED - information retrieved successfully                 *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
static int	history_sql_get_info(void *data, zbx_history_provider_info_t *info, char **error)
{
	struct zbx_db_version_info_t	vi = {0};

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);

	zbx_db_extract_version_info(&vi);

	if (NULL != vi.database)
		info->database = zbx_strdup(NULL, vi.database);

	if (NULL != vi.friendly_current_version)
	{
		info->friendly_current_version = zbx_strdup(NULL, vi.friendly_current_version);
		zbx_free(vi.friendly_current_version);
	}
	if (NULL != vi.friendly_max_version)
		info->friendly_max_version = zbx_strdup(NULL, vi.friendly_max_version);
	if (NULL != vi.friendly_min_version)
		info->friendly_min_version = zbx_strdup(NULL, vi.friendly_min_version);
	if (NULL != vi.friendly_min_supported_version)
		info->friendly_min_supported_version = zbx_strdup(NULL, vi.friendly_min_supported_version);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: open and initialize SQL history provider                          *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of history storage options                    *
 *     options_num - [IN] number of elements in the options array             *
 *     error       - [OUT] error message if function fails                    *
 *                                                                            *
 * Return value: history provider or NULL if initialization fails             *
 *                                                                            *
 ******************************************************************************/
zbx_history_provider_t	*history_sql_open(const zbx_history_option_t *options, int options_num, char **error)
{
	zbx_history_provider_t	*provider;

	ZBX_UNUSED(options);
	ZBX_UNUSED(options_num);
	ZBX_UNUSED(error);

	provider = (zbx_history_provider_t *)zbx_malloc(NULL, sizeof(zbx_history_provider_t));

	provider->name = zbx_strdup(NULL, HISTORY_PROVIDER_SQL);
	provider->traits = ZBX_HISTORY_TRAIT_REQUIRES_TRENDS | ZBX_HISTORY_TRAIT_REQUIRES_HOUSEKEEPING |
			ZBX_HISTORY_TRAIT_TYPES_ALL | ZBX_HISTORY_TRAIT_DEFAULT_PROVIDER;
	provider->impl.write = history_sql_write;
	provider->impl.flush = history_sql_flush;
	provider->impl.fetch = history_sql_fetch;
	provider->impl.close = history_sql_close;
	provider->impl.get_info = history_sql_get_info;

	provider->data = history_sql_create_data();

	return provider;
}
