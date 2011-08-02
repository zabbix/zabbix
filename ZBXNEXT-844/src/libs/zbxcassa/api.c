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

static GByteArray	*zbx_cassandra_get_composite_type(const zbx_uint64_pair_t *key)
{
	GByteArray	*__key;
	zbx_uint64_t	key1, key2;

	key1 = zbx_htobe_uint64(key->first);
	key2 = zbx_htobe_uint64(key->second);

	__key = g_byte_array_sized_new(22);			/* encoding CompositeType(LongType, LongType): */
	g_byte_array_append(__key, "\0\10", 2);			/* <--- length of the first component: 8 bytes */
	g_byte_array_append(__key, (const guint8 *)&key1, 8);	/* <--- first component in big-endian format */
	g_byte_array_append(__key, "\0", 1);			/* <--- end-of-component byte: zero for inserts */
	g_byte_array_append(__key, "\0\10", 2);			/* <--- length of the second component: 8 bytes */
	g_byte_array_append(__key, (const guint8 *)&key2, 8);	/* <--- second component in big-endian format */
	g_byte_array_append(__key, "\0", 1);			/* <--- end-of-component byte: zero for inserts */

	return __key;
}

int	zbx_cassandra_set_value(const zbx_uint64_pair_t *key, char *column_family, const char *column, const char *value)
{
	int			ret = ZBX_DB_OK;
	char			description[MAX_STRING_LEN];

	InvalidRequestException	*ire = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	GByteArray		*__key;
	GByteArray		*__name;
	GByteArray		*__value;
	Column			*__column;
	ColumnParent		*__column_parent;

	__key = zbx_cassandra_get_composite_type(key);

	__name = g_byte_array_new();
	g_byte_array_append(__name, column, strlen(column));

	__value = g_byte_array_new();
	g_byte_array_append(__value, value, strlen(value));

	__column_parent = g_object_new(TYPE_COLUMN_PARENT, NULL);
	__column_parent->column_family = column_family;

	__column = g_object_new(TYPE_COLUMN, NULL);
	__column->name = __name;
	__column->value = __value;
	__column->__isset_value = TRUE;
	__column->timestamp = time(NULL);
	__column->__isset_timestamp = TRUE;

	if (TRUE != cassandra_client_insert(CASSANDRA_IF(conn.client), __key, __column_parent, __column,
				CONSISTENCY_LEVEL_ONE, &ire, &ue, &toe, &error))
	{
		ret = ZBX_DB_DOWN;
		zbx_snprintf(description, sizeof(description), "setting value for"
				" %s['" ZBX_FS_UI64 ":" ZBX_FS_UI64 "']['%s'] to '%s'",
				column_family, key->first, key->second, column, value);
		zbx_cassandra_log_errors(description, error, ire, NULL, ue, toe);
	}

	g_object_unref(__column);
	g_object_unref(__column_parent);
	g_byte_array_free(__value, TRUE);
	g_byte_array_free(__name, TRUE);
	g_byte_array_free(__key, TRUE);

	return ret;
}

char	*zbx_cassandra_get_value(const zbx_uint64_pair_t *key, char *column_family, const char *column)
{
	char			*result = NULL;
	char			description[MAX_STRING_LEN];

	InvalidRequestException	*ire = NULL;
	NotFoundException	*nfe = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	GByteArray		*__key;
	GByteArray		*__column;
	ColumnPath		*__column_path;
	ColumnOrSuperColumn	*__result = NULL;

	__key = zbx_cassandra_get_composite_type(key);

	__column = g_byte_array_new();
	g_byte_array_append(__column, column, strlen(column));

	__column_path = g_object_new(TYPE_COLUMN_PATH, NULL);
	__column_path->column_family = column_family;
	__column_path->column = __column;
	__column_path->__isset_column = TRUE;

	if (TRUE != cassandra_client_get(CASSANDRA_IF(conn.client), &__result, __key, __column_path,
				CONSISTENCY_LEVEL_ONE, &ire, &nfe, &ue, &toe, &error))
	{
		if (NULL == nfe)
		{
			result = (char *)ZBX_DB_DOWN;
			zbx_snprintf(description, sizeof(description), "getting value for"
					" %s['" ZBX_FS_UI64 ":" ZBX_FS_UI64 "']['%s']",
					column_family, key->first, key->second, column);
			zbx_cassandra_log_errors(description, error, ire, nfe, ue, toe);
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
	g_byte_array_free(__column, TRUE);
	g_byte_array_free(__key, TRUE);

	return result;
}

#endif
