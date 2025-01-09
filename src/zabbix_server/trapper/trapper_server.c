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

#include "trapper_history_push.h"
#include "trapper_server.h"

#include "proxydata.h"

#include "../proxyconfigread/proxyconfigread.h"
#include "../reporter/reporter.h"

#include "zbxtrapper.h"
#include "zbxdbhigh.h"
#include "zbxalerter.h"
#include "zbxipcservice.h"
#include "zbxcommshigh.h"
#include "zbxnum.h"
#include "zbxdb.h"
#include "zbxstr.h"
#include "zbxjson.h"

static void	trapper_process_report_test(zbx_socket_t *sock, const struct zbx_json_parse *jp, int config_timeout,
		zbx_get_config_forks_f get_config_forks)
{
	zbx_user_t		user;
	struct zbx_json_parse	jp_data;
	struct zbx_json		j;

	if (0 == get_config_forks(ZBX_PROCESS_TYPE_REPORTMANAGER))
	{
		zbx_send_response(sock, FAIL, "Report manager is disabled.", config_timeout);
		return;
	}

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		zbx_send_response(sock, FAIL, "Permission denied.", config_timeout);
		goto out;
	}

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		char	*error;

		error = zbx_dsprintf(NULL, "cannot find tag: %s", ZBX_PROTO_TAG_DATA);
		zbx_send_response(sock, FAIL, error, config_timeout);
		zbx_free(error);
		goto out;
	}

	zbx_report_test(&jp_data, user.userid, &j);
	zbx_tcp_send_bytes_to(sock, j.buffer, j.buffer_size, config_timeout);
	zbx_json_clean(&j);
out:
	zbx_user_free(&user);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes alert send request that is used to test media types     *
 *                                                                            *
 * Parameters:  sock           - [IN] request socket                          *
 *              jp             - [IN] request data                            *
 *              config_timeout - [IN]                                         *
 *                                                                            *
 ******************************************************************************/
static void	trapper_process_alert_send(zbx_socket_t *sock, const struct zbx_json_parse *jp, int config_timeout)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			ret = FAIL, errcode;
	char			tmp[ZBX_MAX_UINT64_LEN + 1], *sendto = NULL, *subject = NULL,
				*message = NULL, *error = NULL, *params = NULL, *value = NULL, *debug = NULL;
	zbx_uint64_t		mediatypeid;
	size_t			string_alloc;
	struct zbx_json		json;
	struct zbx_json_parse	jp_data, jp_params;
	unsigned char		*data = NULL, smtp_security, smtp_verify_peer, smtp_verify_host,
				smtp_authentication, message_format, *response = NULL;
	zbx_uint32_t		size;
	zbx_user_t		user;
	unsigned short		smtp_port;
	unsigned char		type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL) || USER_TYPE_SUPER_ADMIN > user.type)
	{
		error = zbx_strdup(NULL, "Permission denied.");
		goto fail;
	}

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		error = zbx_dsprintf(NULL, "Cannot parse request tag: %s.", ZBX_PROTO_TAG_DATA);
		goto fail;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_MEDIATYPEID, tmp, sizeof(tmp), NULL) ||
			SUCCEED != zbx_is_uint64(tmp, &mediatypeid))
	{
		error = zbx_dsprintf(NULL, "Cannot parse request tag: %s.", ZBX_PROTO_TAG_MEDIATYPEID);
		goto fail;
	}

	string_alloc = 0;
	if (SUCCEED == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_SENDTO, &sendto, &string_alloc, NULL))
	{
		zbx_replace_invalid_utf8(sendto);
		string_alloc = 0;
	}
	if (SUCCEED == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_SUBJECT, &subject, &string_alloc, NULL))
	{
		zbx_replace_invalid_utf8(subject);
		string_alloc = 0;
	}
	if (SUCCEED == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_MESSAGE, &message, &string_alloc, NULL))
	{
		zbx_replace_invalid_utf8(message);
		string_alloc = 0;
	}

	if (SUCCEED == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_PARAMETERS, &jp_params))
	{
		size_t	string_offset = 0;

		zbx_strncpy_alloc(&params, &string_alloc, &string_offset, jp_params.start,
				(size_t)(jp_params.end - jp_params.start + 1));
		zbx_replace_invalid_utf8(params);
	}

	result = zbx_db_select(
			"select type,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,passwd,smtp_port"
				",smtp_security,smtp_verify_peer,smtp_verify_host,smtp_authentication,maxsessions"
				",maxattempts,attempt_interval,message_format,script,timeout"
			" from media_type"
			" where mediatypeid=" ZBX_FS_UI64, mediatypeid);

	if (NULL == (row = zbx_db_fetch(result)))
	{
		zbx_db_free_result(result);
		error = zbx_dsprintf(NULL, "Cannot find the specified media type.");
		goto fail;
	}

	if (FAIL == zbx_is_ushort(row[8], &smtp_port))
	{
		zbx_db_free_result(result);
		error = zbx_dsprintf(NULL, "Invalid port value.");
		goto fail;
	}

	ZBX_STR2UCHAR(smtp_security, row[9]);
	ZBX_STR2UCHAR(smtp_verify_peer, row[10]);
	ZBX_STR2UCHAR(smtp_verify_host, row[11]);
	ZBX_STR2UCHAR(smtp_authentication, row[12]);
	ZBX_STR2UCHAR(message_format, row[16]);
	ZBX_STR2UCHAR(type, row[0]);

	size = zbx_alerter_serialize_alert_send(&data, mediatypeid, type, row[1], row[2], row[3], row[4], row[5],
			row[6], row[7], smtp_port, smtp_security, smtp_verify_peer, smtp_verify_host,
			smtp_authentication, atoi(row[13]), atoi(row[14]), row[15], message_format, row[17], row[18],
			sendto, subject, message, params);

	zbx_db_free_result(result);

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_ALERTER, zbx_alerter_send_alert_code(), SEC_PER_MIN, data,
			size, &response, &error))
	{
		goto fail;
	}

	zbx_free(sendto);
	zbx_alerter_deserialize_result_ext(response, &sendto, &value, &errcode, &error, &debug);
	zbx_free(response);

	if (SUCCEED == errcode)
		ret = SUCCEED;
fail:
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, SUCCEED == ret ? ZBX_PROTO_VALUE_SUCCESS :
				ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == ret)
	{
		if (NULL != value)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_DATA, value, ZBX_JSON_TYPE_STRING);
	}
	else
	{
		if (NULL != error && '\0' != *error)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, error, ZBX_JSON_TYPE_STRING);
	}

	if (NULL != debug)
		zbx_json_addraw(&json, "debug", debug);

	(void)zbx_tcp_send_to(sock, json.buffer, config_timeout);

	zbx_free(params);
	zbx_free(message);
	zbx_free(subject);
	zbx_free(sendto);
	zbx_free(data);
	zbx_free(value);
	zbx_free(error);
	zbx_free(debug);
	zbx_json_free(&json);

	zbx_user_free(&user);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
}

int	zbx_trapper_process_request_server(const char *request, zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_timespec_t *ts, const zbx_config_comms_args_t *config_comms,
		const zbx_config_vault_t *config_vault, int proxydata_frequency,
		zbx_get_program_type_f get_program_type_cb, const zbx_events_funcs_t *events_cbs,
		zbx_get_config_forks_f get_config_forks)
{
	ZBX_UNUSED(get_program_type_cb);

	if (0 == strcmp(request, ZBX_PROTO_VALUE_REPORT_TEST))
	{
		trapper_process_report_test(sock, jp, config_comms->config_timeout, get_config_forks);
		return SUCCEED;
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_ZABBIX_ALERT_SEND))
	{
		trapper_process_alert_send(sock, jp, config_comms->config_timeout);
		return SUCCEED;
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_PROXY_CONFIG))
	{
		zbx_send_proxyconfig(sock, jp, config_vault, config_comms->config_timeout,
				config_comms->config_trapper_timeout, config_comms->config_source_ip,
				config_comms->config_ssl_ca_location, config_comms->config_ssl_cert_location,
				config_comms->config_ssl_key_location);
		return SUCCEED;
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_PROXY_DATA))
	{
		recv_proxy_data(sock, jp, ts, events_cbs, config_comms->config_timeout, proxydata_frequency);
		return SUCCEED;
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_HISTORY_PUSH))
	{
		trapper_process_history_push(sock, jp, config_comms->config_timeout);
		return SUCCEED;
	}

	return FAIL;
}
