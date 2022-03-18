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

#include "trapper_request.h"

#include "log.h"
#include "cfg.h"
#include "trapper_auth.h"
#include "zbxreport.h"
#include "db.h"
#include "../alerter/alerter_protocol.h"
#include "zbxipcservice.h"

extern int	CONFIG_REPORTMANAGER_FORKS;

static void	trapper_process_report_test(zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	zbx_user_t		user;
	struct zbx_json_parse	jp_data;
	struct zbx_json		j;

	if (0 == CONFIG_REPORTMANAGER_FORKS)
	{
		zbx_send_response(sock, FAIL, "Report manager is disabled.", CONFIG_TIMEOUT);
		return;
	}

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		zbx_send_response(sock, FAIL, "Permission denied.", CONFIG_TIMEOUT);
		goto out;
	}

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		char	*error;

		error = zbx_dsprintf(NULL, "cannot find tag: %s", ZBX_PROTO_TAG_DATA);
		zbx_send_response(sock, FAIL, error, CONFIG_TIMEOUT);
		zbx_free(error);
		goto out;
	}

	zbx_report_test(&jp_data, user.userid, &j);
	zbx_tcp_send_bytes_to(sock, j.buffer, j.buffer_size, CONFIG_TIMEOUT);
	zbx_json_clean(&j);
out:
	zbx_user_free(&user);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process alert send request that is used to test media types       *
 *                                                                            *
 * Parameters:  sock  - [IN] the request socket                               *
 *              jp    - [IN] the request data                                 *
 *                                                                            *
 ******************************************************************************/
static void	trapper_process_alert_send(zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = FAIL, errcode;
	char			tmp[ZBX_MAX_UINT64_LEN + 1], *sendto = NULL, *subject = NULL,
				*message = NULL, *error = NULL, *params = NULL, *value = NULL, *debug = NULL;
	zbx_uint64_t		mediatypeid;
	size_t			string_alloc;
	struct zbx_json		json;
	struct zbx_json_parse	jp_data, jp_params;
	unsigned char		*data = NULL, smtp_security, smtp_verify_peer, smtp_verify_host,
				smtp_authentication, content_type, *response = NULL;
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
			SUCCEED != is_uint64(tmp, &mediatypeid))
	{
		error = zbx_dsprintf(NULL, "Cannot parse request tag: %s.", ZBX_PROTO_TAG_MEDIATYPEID);
		goto fail;
	}

	string_alloc = 0;
	if (SUCCEED == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_SENDTO, &sendto, &string_alloc, NULL))
		string_alloc = 0;
	if (SUCCEED == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_SUBJECT, &subject, &string_alloc, NULL))
		string_alloc = 0;
	if (SUCCEED == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_MESSAGE, &message, &string_alloc, NULL))
		string_alloc = 0;

	if (SUCCEED == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_PARAMETERS, &jp_params))
	{
		size_t	string_offset = 0;

		zbx_strncpy_alloc(&params, &string_alloc, &string_offset, jp_params.start,
				(size_t)(jp_params.end - jp_params.start + 1));
	}

	result = DBselect("select type,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,"
				"passwd,smtp_port,smtp_security,smtp_verify_peer,smtp_verify_host,smtp_authentication,"
				"exec_params,maxsessions,maxattempts,attempt_interval,content_type,script,timeout"
			" from media_type"
			" where mediatypeid=" ZBX_FS_UI64, mediatypeid);

	if (NULL == (row = DBfetch(result)))
	{
		DBfree_result(result);
		error = zbx_dsprintf(NULL, "Cannot find the specified media type.");
		goto fail;
	}

	if (FAIL == is_ushort(row[8], &smtp_port))
	{
		DBfree_result(result);
		error = zbx_dsprintf(NULL, "Invalid port value.");
		goto fail;
	}

	ZBX_STR2UCHAR(smtp_security, row[9]);
	ZBX_STR2UCHAR(smtp_verify_peer, row[10]);
	ZBX_STR2UCHAR(smtp_verify_host, row[11]);
	ZBX_STR2UCHAR(smtp_authentication, row[12]);
	ZBX_STR2UCHAR(content_type, row[17]);
	ZBX_STR2UCHAR(type, row[0]);

	size = zbx_alerter_serialize_alert_send(&data, mediatypeid, type, row[1], row[2], row[3], row[4],
			row[5], row[6], row[7], smtp_port, smtp_security, smtp_verify_peer, smtp_verify_host,
			smtp_authentication, row[13], atoi(row[14]), atoi(row[15]), row[16], content_type, row[18],
			row[19], sendto, subject, message, params);

	DBfree_result(result);

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_ALERTER, ZBX_IPC_ALERTER_SEND_ALERT, SEC_PER_MIN, data,
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

	(void)zbx_tcp_send(sock, json.buffer);

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

int	trapper_process_request(const char *request, zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	if (0 == strcmp(request, ZBX_PROTO_VALUE_REPORT_TEST))
	{
		trapper_process_report_test(sock, jp);
		return SUCCEED;
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_ZABBIX_ALERT_SEND))
	{
		trapper_process_alert_send(sock, jp);
		return SUCCEED;
	}

	return FAIL;
}
