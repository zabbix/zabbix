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

#include "zbxdbhigh.h"

#include "log.h"
#include "zbxsysinfo.h"
#include "zbxserver.h"
#include "zbxtasks.h"
#include "zbxdiscovery.h"
#include "zbxalgo.h"
#include "preproc.h"
#include "zbxcrypto.h"
#include "../zbxkvs/kvs.h"
#include "zbxlld.h"
#include "events.h"
#include "../zbxvault/vault.h"
#include "zbxavailability.h"
#include "zbxcommshigh.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxip.h"
#include "version.h"
#include "zbxversion.h"

extern char	*CONFIG_SERVER;

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Check access rights to a passive proxy for the given connection and    *
 *     send a response if denied.                                             *
 *                                                                            *
 * Parameters:                                                                *
 *     sock           - [IN] connection socket context                        *
 *     send_response  - [IN] to send or not to send a response to server.     *
 *                          Value: ZBX_SEND_RESPONSE or                       *
 *                          ZBX_DO_NOT_SEND_RESPONSE                          *
 *     req            - [IN] request, included into error message             *
 *     zbx_config_tls - [IN] configured requirements to allow access          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - access is allowed                                            *
 *     FAIL    - access is denied                                             *
 *                                                                            *
 ******************************************************************************/
int	check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req,
		const zbx_config_tls_t *zbx_config_tls)
{
	char	*msg = NULL;

	if (FAIL == zbx_tcp_check_allowed_peers(sock, CONFIG_SERVER))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer,
				zbx_socket_strerror());

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "connection is not allowed", CONFIG_TIMEOUT);

		return FAIL;
	}

	if (0 == (zbx_config_tls->accept_modes & sock->connection_type))
	{
		msg = zbx_dsprintf(NULL, "%s over connection of type \"%s\" is not allowed", req,
				zbx_tcp_connection_type_name(sock->connection_type));

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" by proxy configuration parameter \"TLSAccept\"",
				msg, sock->peer);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, msg, CONFIG_TIMEOUT);

		zbx_free(msg);
		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED == zbx_check_server_issuer_subject(sock, zbx_config_tls->server_cert_issuer,
				zbx_config_tls->server_cert_subject, &msg))
		{
			return SUCCEED;
		}

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer, msg);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "certificate issuer or subject mismatch", CONFIG_TIMEOUT);

		zbx_free(msg);
		return FAIL;
	}
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (0 != (ZBX_PSK_FOR_PROXY & zbx_tls_get_psk_usage()))
			return SUCCEED;

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: it used PSK which is not"
				" configured for proxy communication with server", req, sock->peer);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "wrong PSK used", CONFIG_TIMEOUT);

		return FAIL;
	}
#endif
	return SUCCEED;
}
#if 0
void	proxy_get_lastid(const char *table_name, const char *lastidfield, zbx_uint64_t *lastid)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() field:'%s.%s'", __func__, table_name, lastidfield);

	result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = DBfetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,	__func__, *lastid);
}

static void	proxy_set_lastid(const char *table_name, const char *lastidfield, const zbx_uint64_t lastid)
{
	DB_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]", __func__, table_name, lastidfield, lastid);

	result = DBselect("select 1 from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == DBfetch(result))
	{
		DBexecute("insert into ids (table_name,field_name,nextid) values ('%s','%s'," ZBX_FS_UI64 ")",
				table_name, lastidfield, lastid);
	}
	else
	{
		DBexecute("update ids set nextid=" ZBX_FS_UI64 " where table_name='%s' and field_name='%s'",
				lastid, table_name, lastidfield);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	proxy_set_hist_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid("proxy_history", "history_lastid", lastid);
}

void	proxy_set_dhis_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(dht.table, dht.lastidfield, lastid);
}

void	proxy_set_areg_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(areg.table, areg.lastidfield, lastid);
}

int	proxy_get_delay(const zbx_uint64_t lastid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		ts = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [lastid=" ZBX_FS_UI64 "]", __func__, lastid);

	sql = zbx_dsprintf(sql, "select write_clock from proxy_history where id>" ZBX_FS_UI64 " order by id asc",
			lastid);

	result = DBselectN(sql, 1);
	zbx_free(sql);

	if (NULL != (row = DBfetch(result)))
		ts = (int)time(NULL) - atoi(row[0]);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ts;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get history data from the database.                               *
 *                                                                            *
 ******************************************************************************/
static void	proxy_get_history_data_simple(struct zbx_json *j, const char *proto_tag, const zbx_history_table_t *ht,
		zbx_uint64_t *lastid, zbx_uint64_t *id, int *records_num, int *more)
{
	size_t		offset = 0;
	int		f, records_num_last = *records_num, retries = 1;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	struct timespec	t_sleep = { 0, 100000000L }, t_rem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __func__, ht->table);

	*more = ZBX_PROXY_DATA_DONE;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select id");

	for (f = 0; NULL != ht->fields[f].field; f++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s", ht->fields[f].field);
try_again:
	zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s where id>" ZBX_FS_UI64 " order by id",
			ht->table, *id);

	result = DBselectN(sql, ZBX_MAX_HRECORDS);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(*lastid, row[0]);

		if (1 < *lastid - *id)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				DBfree_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__func__, *lastid - *id - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__func__, *lastid - *id - 1);
			}
		}

		if (0 == *records_num)
			zbx_json_addarray(j, proto_tag);

		zbx_json_addobject(j, NULL);

		for (f = 0; NULL != ht->fields[f].field; f++)
		{
			if (NULL != ht->fields[f].default_value && 0 == strcmp(row[f + 1], ht->fields[f].default_value))
				continue;

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 1], ht->fields[f].jt);
		}

		(*records_num)++;

		zbx_json_close(j);

		/* stop gathering data to avoid exceeding the maximum packet size */
		if (ZBX_DATA_JSON_RECORD_LIMIT < j->buffer_offset)
		{
			*more = ZBX_PROXY_DATA_MORE;
			break;
		}

		*id = *lastid;
	}
	DBfree_result(result);

	if (ZBX_MAX_HRECORDS == *records_num - records_num_last)
		*more = ZBX_PROXY_DATA_MORE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d lastid:" ZBX_FS_UI64 " more:%d size:" ZBX_FS_SIZE_T,
			__func__, *records_num - records_num_last, *lastid, *more,
			(zbx_fs_size_t)j->buffer_offset);
}

int	proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	proxy_get_lastid(dht.table, dht.lastidfield, &id);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		proxy_get_history_data_simple(j, ZBX_PROTO_TAG_DISCOVERY_DATA, &dht, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	proxy_get_lastid(areg.table, areg.lastidfield, &id);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		proxy_get_history_data_simple(j, ZBX_PROTO_TAG_AUTOREGISTRATION, &areg, lastid, &id, &records_num,
				more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	proxy_get_host_active_availability(struct zbx_json *j)
{
	zbx_ipc_message_t	response;
	int			records_num = 0;

	zbx_ipc_message_init(&response);
	zbx_availability_send(ZBX_IPC_AVAILMAN_ACTIVE_HOSTDATA, 0, 0, &response);

	if (0 != response.size)
	{
		zbx_vector_proxy_hostdata_ptr_t	hostdata;

		zbx_vector_proxy_hostdata_ptr_create(&hostdata);
		zbx_availability_deserialize_hostdata(response.data, &hostdata);
		zbx_availability_serialize_json_hostdata(&hostdata, j);

		records_num = hostdata.values_num;

		zbx_vector_proxy_hostdata_ptr_clear_ext(&hostdata, (zbx_proxy_hostdata_ptr_free_func_t)zbx_ptr_free);
		zbx_vector_proxy_hostdata_ptr_destroy(&hostdata);
	}

	zbx_ipc_message_clean(&response);

	return records_num;
}
#endif
void	calc_timestamp(const char *line, int *timestamp, const char *format)
{
	int		hh, mm, ss, yyyy, dd, MM;
	int		hhc = 0, mmc = 0, ssc = 0, yyyyc = 0, ddc = 0, MMc = 0;
	int		i, num;
	struct tm	tm;
	time_t		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	hh = mm = ss = yyyy = dd = MM = 0;

	for (i = 0; '\0' != format[i] && '\0' != line[i]; i++)
	{
		if (0 == isdigit(line[i]))
			continue;

		num = (int)line[i] - 48;

		switch ((char)format[i])
		{
			case 'h':
				hh = 10 * hh + num;
				hhc++;
				break;
			case 'm':
				mm = 10 * mm + num;
				mmc++;
				break;
			case 's':
				ss = 10 * ss + num;
				ssc++;
				break;
			case 'y':
				yyyy = 10 * yyyy + num;
				yyyyc++;
				break;
			case 'd':
				dd = 10 * dd + num;
				ddc++;
				break;
			case 'M':
				MM = 10 * MM + num;
				MMc++;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() %02d:%02d:%02d %02d/%02d/%04d", __func__, hh, mm, ss, MM, dd, yyyy);

	/* seconds can be ignored, no ssc here */
	if (0 != hhc && 0 != mmc && 0 != yyyyc && 0 != ddc && 0 != MMc)
	{
		tm.tm_sec = ss;
		tm.tm_min = mm;
		tm.tm_hour = hh;
		tm.tm_mday = dd;
		tm.tm_mon = MM - 1;
		tm.tm_year = yyyy - 1900;
		tm.tm_isdst = -1;

		if (0 < (t = mktime(&tm)))
			*timestamp = t;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() timestamp:%d", __func__, *timestamp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts protocol version from json data                          *
 *                                                                            *
 * Parameters:                                                                *
 *     jp      - [IN] JSON with the proxy version                             *
 *                                                                            *
 * Return value: The protocol version in textual representation, for example, *
 *               "6.4.0alpha1",                                               *
 *     actual proxy version - if proxy version was successfully extracted     *
 *     undefined version    - otherwise                                       *
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
char	*zbx_get_proxy_protocol_version_str(struct zbx_json_parse *jp)
{
	char	value[MAX_STRING_LEN];

	if (NULL != jp && SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, value, sizeof(value), NULL))
		return strdup(value);

	return strdup(ZBX_VERSION_UNDEFINED_STR);
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts protocol version fom textual to numeric representation   *
 *          for version comparison. The function truncates release candidate  *
 *          part of the version.                                              *
 *                                                                            *
 * Parameters:                                                                *
 *     version_str - [IN] proxy version, for example "6.4.0alpha1".           *
 *                                                                            *
 * Return value: The protocol version in numeric representation, for example, *
 *               060400                                                       *
 *     actual proxy version - if proxy version was successfully extracted     *
 *     proxy version 3.2    - otherwise                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_proxy_protocol_version_int(const char *version_str)
{
	int	version_int;

	if (0 != strcmp(ZBX_VERSION_UNDEFINED_STR, version_str) &&
			FAIL != (version_int = zbx_get_component_version(version_str)))
	{
		return version_int;
	}

	return ZBX_COMPONENT_VERSION(3, 2, 0);
}

