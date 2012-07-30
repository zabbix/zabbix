/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

#ifdef HAVE_CASSANDRA

#include <thrift.h>
#include <transport/thrift_socket.h>
#include <transport/thrift_framed_transport.h>
#include <protocol/thrift_protocol.h>
#include <protocol/thrift_binary_protocol.h>

#include "cassandra.h"

#include "log.h"
#include "zbxdb.h"
#include "zbxalgo.h"
#include "zbxcassa.h"

#define ZBX_DB_WAIT_DOWN	10

typedef struct
{
	ThriftSocket	*socket;
	ThriftTransport	*transport;
	ThriftProtocol	*protocol;
	CassandraClient	*client;
}
zbx_cassandra_handle_t;

static zbx_cassandra_handle_t	conn;

#define ZBX_CASSANDRA_HISTORY_BATCH_MAX	5

typedef struct
{
	GHashTable	*mutation_map;
	int		values_num;
}
zbx_cassandra_history_t;

static zbx_cassandra_history_t	history;

static void	zbx_cassandra_log_errors(char *description, GError *error,
		InvalidRequestException *ire, NotFoundException *nfe,
		UnavailableException *ue, TimedOutException *toe)
{
	if (NULL != error)
	{
		zabbix_log(LOG_LEVEL_ERR, "CASSANDRA ERROR (%s): %s", description, error->message);
		g_error_free(error);
	}

	if (NULL != ire)
	{
		zabbix_log(LOG_LEVEL_ERR, "CASSANDRA EXCEPTION: InvalidRequestException, %s", ire->why);
		g_object_unref(ire);
	}

	if (NULL != nfe)
	{
		zabbix_log(LOG_LEVEL_ERR, "CASSANDRA EXCEPTION: NotFoundException");
		g_object_unref(nfe);
	}

	if (NULL != ue)
	{
		zabbix_log(LOG_LEVEL_ERR, "CASSANDRA EXCEPTION: UnavailableException");
		g_object_unref(ue);
	}

	if (NULL != toe)
	{
		zabbix_log(LOG_LEVEL_ERR, "CASSANDRA EXCEPTION: TimedOutException");
		g_object_unref(toe);
	}
}

void	zbx_cassandra_parse_hosts(char *hosts, zbx_cassandra_hosts_t *h)
{
	char	*l, *r, *p;

	h->hosts = NULL;
	h->count = 0;

	for (l = hosts; '\0' != *l; l = r)
	{
		if (NULL != (r = strchr(l, ',')))
			*r = '\0';

		if (NULL != (p = strchr(l, ':')))
			*p = '\0';

		h->count++;
		h->hosts = zbx_realloc(h->hosts, sizeof(zbx_cassandra_host_t) * h->count);

		h->hosts[h->count - 1].host = zbx_strdup(NULL, l);

		if (NULL != p)
		{
			*p++ = ':';
			h->hosts[h->count - 1].port = atoi(p);
		}
		else
			h->hosts[h->count - 1].port = 9160;

		if (NULL != r)
			*r++ = ',';
		else
			break;
	}
}

int	__zbx_cassandra_connect(const char *host, int port, const char *keyspace)
{
	int		ret = ZBX_DB_OK;
	char		descr[MAX_STRING_LEN];

	InvalidRequestException	*ire = NULL;
	GError			*error = NULL;

	memset(&conn, 0, sizeof(conn));

	conn.socket = g_object_new(THRIFT_TYPE_SOCKET, "hostname", host, "port", port, NULL);
	conn.transport = g_object_new(THRIFT_TYPE_FRAMED_TRANSPORT, "transport", THRIFT_TRANSPORT(conn.socket), NULL);
	conn.protocol = THRIFT_PROTOCOL(g_object_new(THRIFT_TYPE_BINARY_PROTOCOL, "transport", conn.transport, NULL));

	if (TRUE != thrift_framed_transport_open(conn.transport, &error))
	{
		ret = ZBX_DB_DOWN;
		zbx_snprintf(descr, sizeof(descr), "connecting to '%s':%d", host, port);
		zbx_cassandra_log_errors(descr, error, NULL, NULL, NULL, NULL);
		goto exit;
	}

	conn.client = g_object_new(TYPE_CASSANDRA_CLIENT, "input_protocol", conn.protocol,
			"output_protocol", conn.protocol, NULL);

	if (TRUE != cassandra_client_set_keyspace(CASSANDRA_IF(conn.client), keyspace, &ire, &error))
	{
		ret = ZBX_DB_FAIL;
		zbx_snprintf(descr, sizeof(descr), "setting keyspace to '%s'", keyspace);
		zbx_cassandra_log_errors(descr, error, ire, NULL, NULL, NULL);
		goto exit;
	}
exit:
	if (ZBX_DB_OK != ret)
		zbx_cassandra_close();

	return ret;
}

void	zbx_cassandra_connect(int flag, zbx_cassandra_hosts_t *h, const char *keyspace)
{
	static int	n = -1;
	int		err, retry = 0;

	if (++n == h->count)
		n = 0;

	while (ZBX_DB_OK != (err = __zbx_cassandra_connect(h->hosts[n].host, h->hosts[n].port, keyspace)))
	{
		if (ZBX_DB_FAIL == err)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to the CASSANDRA database. Exiting...");
			exit(EXIT_FAILURE);
		}

		if (++n == h->count)
			n = 0;

		if (++retry == h->count)
		{
			if (ZBX_CASSANDRA_CONNECT_EXIT == flag)
			{
				zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to the CASSANDRA database. Exiting...");
				exit(EXIT_FAILURE);
			}

			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Reconnecting in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
			retry = 0;
		}
	}
}

void	zbx_cassandra_close()
{
	thrift_framed_transport_close(conn.transport, NULL);

	g_object_unref(conn.client);
	g_object_unref(conn.protocol);
	g_object_unref(conn.transport);
	g_object_unref(conn.socket);

	memset(&conn, 0, sizeof(conn));
}

static GByteArray	*zbx_cassandra_encode_composite_type(zbx_uint64_t comp1, zbx_uint64_t comp2)
{
	GByteArray	*array;

	comp1 = zbx_htobe_uint64(comp1);
	comp2 = zbx_htobe_uint64(comp2);

	array = g_byte_array_sized_new(22);			/* encoding CompositeType(LongType, LongType): */
	g_byte_array_append(array, "\0\10", 2);			/* <--- length of the first component: 8 bytes */
	g_byte_array_append(array, (const guint8 *)&comp1, 8);	/* <--- first component in big-endian format */
	g_byte_array_append(array, "\0", 1);			/* <--- end-of-component byte: zero for inserts */
	g_byte_array_append(array, "\0\10", 2);			/* <--- length of the second component: 8 bytes */
	g_byte_array_append(array, (const guint8 *)&comp2, 8);	/* <--- second component in big-endian format */
	g_byte_array_append(array, "\0", 1);			/* <--- end-of-component byte: zero for inserts */

	return array;
}

static void	zbx_cassandra_decode_composite_type(zbx_uint64_t *comp1, zbx_uint64_t *comp2, const GByteArray *value)
{
	assert(22 == value->len);

	if (NULL != comp1)
	{
		memcpy(comp1, &value->data[2], 8);
		*comp1 = zbx_betoh_uint64(*comp1);
	}

	if (NULL != comp2)
	{
		memcpy(comp2, &value->data[13], 8);
		*comp2 = zbx_betoh_uint64(*comp2);
	}
}

static GByteArray	*zbx_cassandra_encode_long_type(zbx_uint64_t value)
{
	GByteArray	*array;

	value = zbx_htobe_uint64(value);

	array = g_byte_array_sized_new(8);
	g_byte_array_append(array, (const guint8 *)&value, 8);

	return array;
}

static zbx_uint64_t	zbx_cassandra_decode_long_type(const GByteArray *value)
{
	zbx_uint64_t	number;

	memcpy(&number, value->data, 8);
	number = zbx_betoh_uint64(number);

	return number;
}

static GByteArray	*zbx_cassandra_encode_integer_type(uint32_t value)
{
	GByteArray	*array;

	value = htonl(value);

	array = g_byte_array_sized_new(4);
	g_byte_array_append(array, (const guint8 *)&value, 4);

	return array;
}

static uint32_t	zbx_cassandra_decode_integer_type(const GByteArray *value)
{
	uint32_t	number;

	memcpy(&number, value->data, 4);
	number = ntohl(number);

	return number;
}

static GByteArray	*zbx_cassandra_encode_ascii_type(const char *value)
{
	int		length;
	GByteArray	*array;

	length = strlen(value);

	array = g_byte_array_sized_new(length);
	g_byte_array_append(array, value, length);

	return array;
}

static char	*zbx_cassandra_decode_ascii_type(const GByteArray *value)
{
	char	*string;

	string = zbx_malloc(NULL, value->len + 1);
	memcpy(string, value->data, value->len);
	string[value->len] = '\0';

	return string;
}

static const char	*zbx_cassandra_decode_type(const GByteArray *value)
{
	static char	buffer[MAX_STRING_LEN];

	if (22 == value->len && '\10' == value->data[1] && '\10' == value->data[12])
	{
		/* assume it is CompositeType(LongType, LongType) */

		zbx_uint64_t	comp1, comp2;

		zbx_cassandra_decode_composite_type(&comp1, &comp2, value);

		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64 ":" ZBX_FS_UI64, comp1, comp2);
	}
	else if (8 == value->len && '\0' == value->data[0])
	{
		/* assume it is LongType */

		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, zbx_cassandra_decode_long_type(value));
	}
	else if (4 == value->len && '\0' == value->data[0])
	{
		/* assume it is 32-bit IntegerType */

		zbx_snprintf(buffer, sizeof(buffer), "%u", zbx_cassandra_decode_integer_type(value));
	}
	else
	{
		/* assume it is just a string */

		zbx_strlcpy(buffer, value->data, MIN(sizeof(buffer), value->len + 1));
	}

	return buffer;
}

static void	zbx_cassandra_free_column_or_supercolumn(gpointer data)
{
	ColumnOrSuperColumn	*__cosc;
	Column			*__column;

	__cosc = COLUMN_OR_SUPER_COLUMN(data);
	assert(TRUE == __cosc->__isset_column);

	__column = COLUMN(__cosc->column);
	assert(TRUE == __column->__isset_value);
	assert(TRUE == __column->__isset_timestamp);

	g_byte_array_unref(__column->value);
	g_byte_array_unref(__column->name);
	g_object_unref(__column);
	g_object_unref(__cosc);
}

static void	zbx_cassandra_free_mutation(gpointer data)
{
	Mutation	*__mutation;

	__mutation = MUTATION(data);
	assert(TRUE == __mutation->__isset_column_or_supercolumn);

	zbx_cassandra_free_column_or_supercolumn(__mutation->column_or_supercolumn);

	g_object_unref(__mutation);
}

static void	zbx_cassandra_add_mutation(GByteArray *key, char *column_family, GByteArray *column, GByteArray *value)
{
	GHashTable		*__cf_map;
	ColumnOrSuperColumn	*__cf_cosc;
	Column			*__cf_column;
	GPtrArray		*__cf_mutlist;
	Mutation		*__cf_mutation;

	if (NULL == (__cf_map = g_hash_table_lookup(history.mutation_map, key)))
	{
		__cf_map = g_hash_table_new_full(g_str_hash, g_str_equal, NULL, (GDestroyNotify)g_ptr_array_unref);
		g_hash_table_insert(history.mutation_map, key, __cf_map);
	}

	if (NULL == (__cf_mutlist = g_hash_table_lookup(__cf_map, column_family)))
	{
		__cf_mutlist = g_ptr_array_new_with_free_func(zbx_cassandra_free_mutation);
		g_hash_table_insert(__cf_map, column_family, __cf_mutlist);
	}

	__cf_column = g_object_new(TYPE_COLUMN, NULL);
	__cf_column->name = column;
	__cf_column->value = value;
	__cf_column->__isset_value = TRUE;
	__cf_column->timestamp = time(NULL);
	__cf_column->__isset_timestamp = TRUE;

	__cf_cosc = g_object_new(TYPE_COLUMN_OR_SUPER_COLUMN, NULL);
	__cf_cosc->column = __cf_column;
	__cf_cosc->__isset_column = TRUE;

	__cf_mutation = g_object_new(TYPE_MUTATION, NULL);
	__cf_mutation->column_or_supercolumn = __cf_cosc;
	__cf_mutation->__isset_column_or_supercolumn = TRUE;

	g_ptr_array_add(__cf_mutlist, __cf_mutation);
}

void	zbx_cassandra_add_history_value(zbx_uint64_t itemid, zbx_uint64_t clock, const char *value)
{
	GByteArray	*__key;
	GByteArray	*__column;
	GByteArray	*__value;
	zbx_uint64_t	day, offset;

	if (NULL == history.mutation_map)
	{
		history.mutation_map = g_hash_table_new_full(g_direct_hash, g_direct_equal,
				(GDestroyNotify)g_byte_array_unref, (GDestroyNotify)g_hash_table_unref);
	}

	offset = clock % SEC_PER_DAY;
	day = clock - offset;
	offset *= (zbx_uint64_t)__UINT64_C(1000);
	day *= (zbx_uint64_t)__UINT64_C(1000);

	__key = zbx_cassandra_encode_composite_type(itemid, day);
	__column = zbx_cassandra_encode_integer_type(offset);
	__value = zbx_cassandra_encode_ascii_type(value);

	zbx_cassandra_add_mutation(__key, "metric", __column, __value);

	__key = zbx_cassandra_encode_long_type(itemid);
	__column = zbx_cassandra_encode_composite_type(itemid, day);
	__value = zbx_cassandra_encode_ascii_type("\1");

	zbx_cassandra_add_mutation(__key, "metric_by_parameter", __column, __value);

	__key = zbx_cassandra_encode_long_type(day);
	__column = zbx_cassandra_encode_composite_type(itemid, day);
	__value = zbx_cassandra_encode_ascii_type("\1");

	zbx_cassandra_add_mutation(__key, "metric_by_date", __column, __value);

	if (ZBX_CASSANDRA_HISTORY_BATCH_MAX == ++history.values_num)
		zbx_cassandra_save_history_values();
}

void	zbx_cassandra_save_history_values()
{
	InvalidRequestException	*ire = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	if (0 == history.values_num)
		return;

	if (TRUE != cassandra_client_batch_mutate(CASSANDRA_IF(conn.client), history.mutation_map,
				CONSISTENCY_LEVEL_ONE, &ire, &ue, &toe, &error))
	{
		zbx_cassandra_log_errors("saving a bunch of history values", error, ire, NULL, ue, toe);
	}

	g_hash_table_unref(history.mutation_map);
	history.mutation_map = NULL;
	history.values_num = 0;
}

static GPtrArray	*zbx_cassandra_get_values(GByteArray *key, char *column_family, SlicePredicate *predicate)
{
	InvalidRequestException	*ire = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	ColumnParent		*__column_parent;
	GPtrArray		*__result;

	__column_parent = g_object_new(TYPE_COLUMN_PARENT, NULL);
	__column_parent->column_family = column_family;

	__result = g_ptr_array_new_with_free_func(zbx_cassandra_free_column_or_supercolumn);

	if (TRUE != cassandra_client_get_slice(CASSANDRA_IF(conn.client), &__result, key, __column_parent,
				predicate, CONSISTENCY_LEVEL_ONE, &ire, &ue, &toe, &error))
	{
		int	descr_offset = 0;
		char	descr[MAX_STRING_LEN];

		g_ptr_array_unref(__result);
		__result = NULL;

		descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				"getting values for %s", column_family);
		descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				"['%s']", zbx_cassandra_decode_type(key));
		descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				"['%s'..", zbx_cassandra_decode_type(predicate->slice_range->start));
		descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				"'%s']", zbx_cassandra_decode_type(predicate->slice_range->finish));
		zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				" with limit %d", predicate->slice_range->count);

		zbx_cassandra_log_errors(descr, error, ire, NULL, ue, toe);
	}

	g_object_unref(__column_parent);

	return __result;
}

void	zbx_cassandra_fetch_history_values(zbx_vector_str_t *values, zbx_uint64_t itemid,
		zbx_uint64_t clock_from, zbx_uint64_t clock_to, int last_n)
{
	int		i, j;
	zbx_uint64_t	date;

	GByteArray	*__index_key;
	SlicePredicate	*__index_predicate;
	GPtrArray	*__index_result;

	GByteArray	*__value_key;
	SlicePredicate	*__value_predicate;
	GPtrArray	*__value_result;

	if (0 != clock_from)
		clock_from++;

	if (0 == clock_to)
		clock_to = time(NULL);

	__index_key = zbx_cassandra_encode_long_type(itemid);

	__index_predicate = g_object_new(TYPE_SLICE_PREDICATE, NULL);
	__index_predicate->slice_range = g_object_new(TYPE_SLICE_RANGE, NULL);
	__index_predicate->slice_range->start = zbx_cassandra_encode_composite_type(
			itemid, clock_to - clock_to % SEC_PER_DAY);
	__index_predicate->slice_range->finish = zbx_cassandra_encode_composite_type(
			itemid, clock_from - clock_from % SEC_PER_DAY);
	__index_predicate->slice_range->reversed = TRUE;
	__index_predicate->slice_range->count = INT_MAX;
	__index_predicate->__isset_slice_range = TRUE;

	__value_predicate = g_object_new(TYPE_SLICE_PREDICATE, NULL);
	__value_predicate->slice_range = g_object_new(TYPE_SLICE_RANGE, NULL);
	__value_predicate->slice_range->reversed = TRUE;
	__value_predicate->slice_range->count = INT_MAX;
	__value_predicate->__isset_slice_range = TRUE;

	while (1)
	{
		if (0 != last_n)
			__index_predicate->slice_range->count = MAX(1, last_n / 5);

		__index_result = zbx_cassandra_get_values(__index_key, "metric_by_parameter", __index_predicate);

		for (i = 0; i < __index_result->len; i++)
		{
			__value_key = COLUMN_OR_SUPER_COLUMN(__index_result->pdata[i])->column->name;

			zbx_cassandra_decode_composite_type(NULL, &date, __value_key);

			__value_predicate->slice_range->start = zbx_cassandra_encode_integer_type(
					date == clock_to - clock_to % SEC_PER_DAY ?
					clock_to % SEC_PER_DAY * 1000 : (SEC_PER_DAY - 1) * 1000);
			__value_predicate->slice_range->finish = zbx_cassandra_encode_integer_type(
					date == clock_from - clock_from % SEC_PER_DAY ?
					clock_from % SEC_PER_DAY * 1000 : 0);

			if (0 != last_n)
				__value_predicate->slice_range->count = last_n;

			__value_result = zbx_cassandra_get_values(__value_key, "metric", __value_predicate);

			for (j = 0; j < __value_result->len; j++)
			{
				GByteArray	*__array;

				__array = COLUMN_OR_SUPER_COLUMN(__value_result->pdata[j])->column->value;
				zbx_vector_str_append(values, zbx_cassandra_decode_ascii_type(__array));
			}

			g_byte_array_unref(__value_predicate->slice_range->finish);
			g_byte_array_unref(__value_predicate->slice_range->start);

			if (0 != last_n && 0 == (last_n -= __value_result->len))
			{
				g_ptr_array_unref(__value_result);
				break;
			}
			else
				g_ptr_array_unref(__value_result);
		}

		if (0 == last_n || __index_predicate->slice_range->count != __index_result->len)
		{
			g_ptr_array_unref(__index_result);
			break;
		}
		else
		{
			g_byte_array_unref(__index_predicate->slice_range->start);

			/* continue our search with the lowest date found */
			/* byte "\xff" (-1) at the end makes it exclusive */
			__index_predicate->slice_range->start = g_byte_array_ref(
					__index_result->pdata[__index_result->len - 1]);
			g_byte_array_remove_index_fast(__index_predicate->slice_range->start, 21);
			g_byte_array_append(__index_predicate->slice_range->start, "\xff", 1);

			g_ptr_array_unref(__index_result);
		}
	}

	g_object_unref(__value_predicate->slice_range);
	g_object_unref(__value_predicate);

	g_byte_array_unref(__index_predicate->slice_range->finish);
	g_byte_array_unref(__index_predicate->slice_range->start);
	g_object_unref(__index_predicate->slice_range);
	g_object_unref(__index_predicate);

	g_byte_array_unref(__index_key);
}

/* code that is currently not being used: */

static int	zbx_cassandra_set_value(GByteArray *key, char *column_family, GByteArray *column, GByteArray *value)
{
	int			ret = ZBX_DB_OK;

	InvalidRequestException	*ire = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	Column			*__column;
	ColumnParent		*__column_parent;

	__column_parent = g_object_new(TYPE_COLUMN_PARENT, NULL);
	__column_parent->column_family = column_family;

	__column = g_object_new(TYPE_COLUMN, NULL);
	__column->name = column;
	__column->value = value;
	__column->__isset_value = TRUE;
	__column->timestamp = time(NULL);
	__column->__isset_timestamp = TRUE;

	if (TRUE != cassandra_client_insert(CASSANDRA_IF(conn.client), key, __column_parent, __column,
				CONSISTENCY_LEVEL_ONE, &ire, &ue, &toe, &error))
	{
		int	descr_offset = 0;
		char	descr[MAX_STRING_LEN];

		ret = ZBX_DB_DOWN;

		descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				"setting value for %s", column_family);
		descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				"['%s']", zbx_cassandra_decode_type(key));
		descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				"['%s']", zbx_cassandra_decode_type(column));
		zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
				" to '%s'", zbx_cassandra_decode_type(value));

		zbx_cassandra_log_errors(descr, error, ire, NULL, ue, toe);
	}

	g_object_unref(__column);
	g_object_unref(__column_parent);

	return ret;
}

static char	*zbx_cassandra_get_value(GByteArray *key, char *column_family, GByteArray *column)
{
	char			*result = NULL;

	InvalidRequestException	*ire = NULL;
	NotFoundException	*nfe = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	ColumnPath		*__column_path;
	ColumnOrSuperColumn	*__result = NULL;

	__column_path = g_object_new(TYPE_COLUMN_PATH, NULL);
	__column_path->column_family = column_family;
	__column_path->column = column;
	__column_path->__isset_column = TRUE;

	if (TRUE != cassandra_client_get(CASSANDRA_IF(conn.client), &__result, key, __column_path,
				CONSISTENCY_LEVEL_ONE, &ire, &nfe, &ue, &toe, &error))
	{
		if (NULL == nfe)
		{
			int	descr_offset = 0;
			char	descr[MAX_STRING_LEN];

			result = (char *)ZBX_DB_DOWN;

			descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
					"getting value for %s", column_family);
			descr_offset += zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
					"['%s']", zbx_cassandra_decode_type(key));
			zbx_snprintf(descr + descr_offset, sizeof(descr) - descr_offset,
					"['%s']", zbx_cassandra_decode_type(column));

			zbx_cassandra_log_errors(descr, error, ire, nfe, ue, toe);
		}
	}
	else
	{
		result = zbx_cassandra_decode_ascii_type(__result->column->value);

		g_object_unref(__result);
	}

	g_object_unref(__column_path);

	return result;
}

void	zbx_cassandra_save_history_value(zbx_uint64_t itemid, zbx_uint64_t clock, const char *value)
{
	GByteArray	*__key;
	GByteArray	*__column;
	GByteArray	*__value;

	__key = zbx_cassandra_encode_composite_type(itemid, clock - clock % SEC_PER_DAY);
	__column = zbx_cassandra_encode_integer_type((clock % SEC_PER_DAY) * 1000);
	__value = zbx_cassandra_encode_ascii_type(value);

	zbx_cassandra_set_value(__key, "metric", __column, __value);

	g_byte_array_unref(__value);
	g_byte_array_unref(__column);
	g_byte_array_unref(__key);

	__key = zbx_cassandra_encode_long_type(itemid);
	__column = zbx_cassandra_encode_composite_type(itemid, clock - clock % SEC_PER_DAY);
	__value = zbx_cassandra_encode_ascii_type("\1");

	zbx_cassandra_set_value(__key, "metric_by_parameter", __column, __value);

	g_byte_array_unref(__value);
	g_byte_array_unref(__column);
	g_byte_array_unref(__key);

	__key = zbx_cassandra_encode_long_type((clock % SEC_PER_DAY) * 1000);
	__column = zbx_cassandra_encode_composite_type(itemid, clock - clock % SEC_PER_DAY);
	__value = zbx_cassandra_encode_ascii_type("\1");

	zbx_cassandra_set_value(__key, "metric_by_date", __column, __value);

	g_byte_array_unref(__value);
	g_byte_array_unref(__column);
	g_byte_array_unref(__key);
}

#endif
