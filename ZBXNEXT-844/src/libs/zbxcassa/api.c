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
#include "zbxcassa.h"

typedef struct
{
	ThriftSocket	*socket;
	ThriftTransport	*transport;
	ThriftProtocol	*protocol;
	CassandraClient	*client;
}
zbx_cassandra_handle_t;

static zbx_cassandra_handle_t	conn;

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

int	zbx_cassandra_connect(const char *host, const char *keyspace, int port)
{
	int			ret = ZBX_DB_OK;
	char			description[MAX_STRING_LEN];

	InvalidRequestException	*ire = NULL;
	GError			*error = NULL;

	memset(&conn, 0, sizeof(conn));

	conn.socket = g_object_new(THRIFT_TYPE_SOCKET, "hostname", host, "port", port, NULL);
	conn.transport = g_object_new(THRIFT_TYPE_FRAMED_TRANSPORT, "transport", THRIFT_TRANSPORT(conn.socket), NULL);
	conn.protocol = THRIFT_PROTOCOL(g_object_new(THRIFT_TYPE_BINARY_PROTOCOL, "transport", conn.transport, NULL));

	if (TRUE != thrift_framed_transport_open(conn.transport, &error))
	{
		ret = ZBX_DB_FAIL;
		zbx_snprintf(description, sizeof(description), "connecting to '%s':%d", host, port);
		zbx_cassandra_log_errors(description, error, NULL, NULL, NULL, NULL);
		goto exit;
	}

	conn.client = g_object_new(TYPE_CASSANDRA_CLIENT, "input_protocol", conn.protocol,
			"output_protocol", conn.protocol, NULL);

	if (TRUE != cassandra_client_set_keyspace(CASSANDRA_IF(conn.client), keyspace, &ire, &error))
	{
		ret = ZBX_DB_FAIL;
		zbx_snprintf(description, sizeof(description), "setting keyspace to '%s'", keyspace);
		zbx_cassandra_log_errors(description, error, ire, NULL, NULL, NULL);
		goto exit;
	}
exit:
	if (ZBX_DB_OK != ret)
		zbx_cassandra_close();

	return ret;
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

static GByteArray	*zbx_cassandra_get_composite_type(zbx_uint64_t comp1, zbx_uint64_t comp2)
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

static GByteArray	*zbx_cassandra_get_long_type(zbx_uint64_t value)
{
	GByteArray	*array;

	value = zbx_htobe_uint64(value);

	array = g_byte_array_sized_new(8);
	g_byte_array_append(array, (const guint8 *)&value, 8);

	return array;
}

static GByteArray	*zbx_cassandra_get_string_type(const char *value)
{
	int		length;
	GByteArray	*array;

	length = strlen(value);

	array = g_byte_array_sized_new(length);
	g_byte_array_append(array, value, length);

	return array;
}

static const char	*zbx_cassandra_decode_type(const GByteArray *value)
{
	static char	buffer[MAX_STRING_LEN];

	if (22 == value->len && '\10' == value->data[1] && '\10' == value->data[12])
	{
		/* assume it is CompositeType(LongType, LongType) */

		zbx_uint64_t	comp1, comp2;

		memcpy(&comp1, &value->data[2], 8);
		memcpy(&comp2, &value->data[13], 8);

		comp1 = zbx_betoh_uint64(comp1);
		comp2 = zbx_betoh_uint64(comp2);

		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64 ":" ZBX_FS_UI64, comp1, comp2);
	}
	else if (8 == value->len && '\0' == value->data[0])
	{
		/* assume it is LongType */

		zbx_uint64_t	number;

		memcpy(&number, value->data, 8);
		number = zbx_betoh_uint64(number);
		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, number);
	}
	else
	{
		/* assume it is just a string */

		zbx_snprintf(buffer, sizeof(buffer), "%s", value->data);
	}

	return buffer;
}

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
				" to ['%s']", zbx_cassandra_decode_type(value));
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
		result = zbx_malloc(result, __result->column->value->len + 1);
		memcpy(result, __result->column->value->data, __result->column->value->len);
		result[__result->column->value->len] = '\0';
	}

	g_object_unref(__result);
	g_object_unref(__column_path);

	return result;
}

int	zbx_cassandra_save_history_value(zbx_uint64_t itemid, zbx_uint64_t clock, const char *value)
{
	GByteArray	*__key;
	GByteArray	*__column;
	GByteArray	*__value;

	__key = zbx_cassandra_get_composite_type(itemid, clock - clock % SEC_PER_DAY);
	__column = zbx_cassandra_get_long_type(clock * 1000);
	__value = zbx_cassandra_get_string_type(value);

	zbx_cassandra_set_value(__key, "metric", __column, __value);

	g_byte_array_free(__value, TRUE);
	g_byte_array_free(__column, TRUE);
	g_byte_array_free(__key, TRUE);
}

#endif
