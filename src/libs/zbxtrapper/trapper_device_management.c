/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "trapper_device_managment.h"
#include "zbxtrapper.h"

#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxcomms.h"
#include "zbxcommshigh.h"
#include "zbxcrypto.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"

#if defined(HAVE_LIBCURL)
#	include "zbxcurl.h"
#	include "zbxhttp.h"
#endif

#define ZBX_ERROR_CODE_LEN		32
#define ZBX_MESSAGE_LEN			256
#define ZBX_INFO_LEN			512
#define ZBX_USER_ID_LEN			21

/******************************************************************************
 *                                                                            *
 * Purpose: checks if calling user has permission to manage devices for the   *
 *          specified user                                                    *
 *                                                                            *
 * Parameters: user          - [IN] authenticated user requesting the action  *
 *             target_userid - [IN] user ID of the target device owner        *
 *                                                                            *
 * Return value:  SUCCEED - user has access                                   *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	device_check_permissions(const zbx_user_t *user, zbx_uint64_t target_userid)
{
#define ZBX_USER_ROLE_PERMISSION_DEVICES_DEFAULT_ACCESS	"devices.actions.default_access"
#define ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_OWN	"devices.actions.manage_own"
#define ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_USER	"devices.actions.manage_user"
#define ROLE_PERM_ALLOW					1
	int			ret = FAIL, default_access = 0;
	const char		*required_rule;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 " target_userid:" ZBX_FS_UI64,
			__func__, user->userid, target_userid);

	required_rule = (user->userid == target_userid ? ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_OWN :
			ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_USER);

	result = zbx_db_select(
			"select name,value_int"
			" from role_rule"
			" where roleid=" ZBX_FS_UI64
				" and (name='%s' or name='%s')",
			user->roleid, required_rule, ZBX_USER_ROLE_PERMISSION_DEVICES_DEFAULT_ACCESS);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 == strcmp(required_rule, row[0]))
		{
			ret = (ROLE_PERM_ALLOW == atoi(row[1]) ? SUCCEED : FAIL);
			goto out;
		}
		else if (0 == strcmp(ZBX_USER_ROLE_PERMISSION_DEVICES_DEFAULT_ACCESS, row[0]))
			default_access = atoi(row[1]);
		else
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ret = (ROLE_PERM_ALLOW == default_access ? SUCCEED : FAIL);
out:
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef ZBX_USER_ROLE_PERMISSION_DEVICES_DEFAULT_ACCESS
#undef ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_OWN
#undef ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_USER
#undef ROLE_PERM_ALLOW
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets device owner userid by device uuid                           *
 *                                                                            *
 * Return value:  SUCCEED - userid found                                      *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	device_get_userid_by_uuid(const char *uuid, zbx_uint64_t *target_userid)
{
	int			ret = FAIL;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:%s", __func__, ZBX_NULL2EMPTY_STR(uuid));

	if (NULL == uuid || '\0' == *uuid || NULL == target_userid)
		goto out;

	result = zbx_db_select(
			"select userid"
			" from device"
			" where uuid='%s'",
			uuid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(*target_userid, row[0]);
		ret = SUCCEED;
	}

	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	trapper_device_init(const struct zbx_json_parse *jp, const char *config_adapter_url,
		const char *config_tls_ca_file, const char *config_tls_cert_file,
		const char *config_tls_key_file, const char *config_adapter_connect_to, char **error,
		struct zbx_json *json)
{
#define ZBX_ENROLL_URL_LEN		2048
#define ZBX_BRIDGE_ENCRYPTION_KEY_LEN	256
#define ZBX_ENROLL_TOKE_LEN		128

#if !defined(HAVE_LIBCURL)
	ZBX_UNUSED(jp);
	ZBX_UNUSED(config_adapter_url);
	ZBX_UNUSED(config_tls_ca_file);
	ZBX_UNUSED(config_tls_cert_file);
	ZBX_UNUSED(config_tls_key_file);
	ZBX_UNUSED(config_adapter_connect_to);
	ZBX_UNUSED(error);
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	zbx_http_response_t		body = {0}, response_header = {0};
	CURL				*curl = NULL;
	CURLcode			err;
	CURLoption			opt;
	struct curl_slist		*headers = NULL, *connect_to = NULL;
	long				http_code = 0;
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result, jp_bek;
	char				*payload = NULL, *error_curl = NULL, *bek_raw = NULL, errbuf[CURL_ERROR_SIZE],
					device_id[ZBX_UUID_LEN], met[ZBX_ENROLL_TOKE_LEN],
					enroll_url[ZBX_ENROLL_URL_LEN], code[ZBX_ERROR_CODE_LEN],
					message[ZBX_MESSAGE_LEN], error_data[ZBX_MESSAGE_LEN], *uuid7id;
	int				ret = FAIL;
	size_t				bek_len;
	zbx_config_t			cfg;
	const char			*serverid;

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_body))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing data object in device.init request");
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_body, "uuid", device_id, sizeof(device_id), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing uuid in device.init request");
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SERVER_ID);
	serverid = cfg.serverid;

	zbx_json_init(&request, 512);

	zbx_json_addstring(&request, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "method", "device.init", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&request, "params");
	zbx_json_addstring(&request, "server_id", serverid, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "device_id", device_id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);
	uuid7id = zbx_gen_uuid7();
	zbx_json_addstring(&request, "id", uuid7id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);

	payload = zbx_strdup(NULL, request.buffer);

	if (NULL == (curl = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed initialize cURL library");
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	if (SUCCEED != zbx_http_prepare_callbacks(curl, &response_header, &body, zbx_curl_ignore_cb,
			zbx_curl_write_cb, errbuf, &error_curl))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot prepare HTTP callbacks: %s",
				ZBX_NULL2EMPTY_STR(error_curl));
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	headers = curl_slist_append(headers, "Content-Type: application/json");
	headers = curl_slist_append(headers, "X-Trace-Id: test-trace-1");

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_URL, config_adapter_url)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_HTTPHEADER, headers)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDS, payload)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDSIZE, strlen(payload))) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CONNECTTIMEOUT_MS, 2000L)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_TIMEOUT_MS, 5000L)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	if (NULL != config_tls_ca_file && NULL != config_tls_cert_file && NULL != config_tls_key_file)
	{

		if (SUCCEED != zbx_curl_setopt_https(curl, &error_curl))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed zbx_curl_setopt_https: %s",
					ZBX_NULL2EMPTY_STR(error_curl));
			*error = zbx_strdup(NULL, "Failed process device.init request");
			goto out;
		}

		if (SUCCEED != zbx_curl_setopt_ssl_version(curl, &error_curl))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed zbx_curl_setopt_ssl_version: %s",
					ZBX_NULL2EMPTY_STR(error_curl));
			*error = zbx_strdup(NULL, "Failed process device.init request");
			goto out;
		}

		if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CAINFO, config_tls_ca_file)) ||
				CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLCERT, config_tls_cert_file))
				||
				CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLKEY, config_tls_key_file))
				||
				CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSL_VERIFYPEER, 1L)) ||
				CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSL_VERIFYHOST, 2L)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
			*error = zbx_strdup(NULL, "Failed process device.init request");
			goto out;
		}
	}

	if (NULL != config_adapter_connect_to)
	{
		connect_to = curl_slist_append(connect_to, config_adapter_connect_to);

		if (NULL == connect_to)
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to prepare CURLOPT_CONNECT_TO value");
			*error = zbx_strdup(NULL, "Failed process device.init request");
			goto out;
		}

		if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CONNECT_TO, connect_to)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed set cURL option %d: %s.", (int)opt,
				curl_easy_strerror(err));
			*error = zbx_strdup(NULL, "Failed process device.init request");
			goto out;
		}
	}

	if (CURLE_OK != (err = curl_easy_perform(curl)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed connect to bridge-adapter: %s", curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed connect to bridge-adapter");
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &http_code)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed obtain bridge-adapter response code: %s",
				curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	if (FAIL == zbx_json_open(body.data, &jp_body))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	if (http_code < 200 || http_code >= 300)
	{
		zabbix_log(LOG_LEVEL_WARNING, "bridge-adapter returned HTTP %ld: %s", http_code,
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	if (SUCCEED == zbx_json_brackets_by_name(&jp_body, "error", &jp_result))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp_result, "code", code, sizeof(code), NULL) &&
				SUCCEED == zbx_json_value_by_name(&jp_result, "message", message, sizeof(message),
				NULL) && SUCCEED == zbx_json_value_by_name(&jp_result, "data", error_data,
				sizeof(error_data), NULL))
		{
			char	info[ZBX_INFO_LEN];

			zabbix_log(LOG_LEVEL_WARNING, "bridge-adapter returned %s: message: %s data %s", code,
					message, error_data);
			zbx_snprintf(info, sizeof(info), "Bridge-adapter returned code: %s, message: %s data: %s",
					code, message, error_data);
			*error = zbx_strdup(NULL, info);
		}
		else
			*error = zbx_strdup(NULL, "Failed process device.init request");

		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_body, "result", &jp_result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing result in bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_result, "met", met, sizeof(met), NULL) ||
			FAIL == zbx_json_brackets_by_name(&jp_result, "bek", &jp_bek) ||
			FAIL == zbx_json_value_by_name(&jp_result, "enroll_url", enroll_url,
			sizeof(enroll_url), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing met/bek/enroll_url in bridge-adapter result: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_strdup(NULL, "Failed process device.init request");
		goto out;
	}

	bek_len = (size_t)(jp_bek.end - jp_bek.start + 1);
	bek_raw = (char *)zbx_malloc(NULL, bek_len + 1);
	memcpy(bek_raw, jp_bek.start, bek_len);
	bek_raw[bek_len] = '\0';

	zbx_json_addstring(json, "response", "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(json, "data");
	zbx_json_addstring(json, "mobile_enrollment_token", met, ZBX_JSON_TYPE_STRING);
	zbx_json_addraw(json, "bridge_encryption_key", bek_raw);
	zbx_json_addstring(json, "bridge_enrollment_url", enroll_url, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);

	ret = SUCCEED;
out:
	curl_slist_free_all(headers);
	curl_easy_cleanup(curl);
	zbx_json_free(&request);
	zbx_free(error_curl);
	zbx_free(payload);
	zbx_free(body.data);
	zbx_free(response_header.data);
	zbx_free(bek_raw);

	return ret;

#endif
#undef ZBX_ENROLL_URL_LEN
#undef ZBX_BRIDGE_ENCRYPTION_KEY_LEN
#undef ZBX_ENROLL_TOKE_LEN
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes device.init request                                     *
 *                                                                            *
 * Comments: validates caller permissions for requested target user and       *
 *           forwards request to bridge adapter                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_trapper_device_init(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const zbx_config_tls_t *config_tls,
		const char *config_frontend_allowed_ip, const char *config_adapter_url, const char *config_tls_ca_file,
		const char *config_tls_cert_file, const char *config_tls_key_file, const char *config_connect_to)
{
	struct zbx_json		json;
	struct zbx_json_parse	jp_data;
	int			ret;
	char			*error = NULL, target_userid_str[ZBX_USER_ID_LEN];
	zbx_user_t		user;
	zbx_uint64_t		target_userid;


	zabbix_log(LOG_LEVEL_INFORMATION, "In %s()", __func__);

	if (SUCCEED != zbx_check_frontend_conn_accept(sock, config_tls, config_frontend_allowed_ip))
		goto out;

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() failed to get user from request", __func__);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() missing data object in request", __func__);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_data, "userid", target_userid_str, sizeof(target_userid_str), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() missing userid in request data", __func__);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	ZBX_STR2UINT64(target_userid, target_userid_str);

	if (FAIL == device_check_permissions(&user, target_userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() permission denied for userid:" ZBX_FS_UI64 " target_userid:"
				ZBX_FS_UI64, __func__, user.userid, target_userid);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	zbx_json_init(&json, 1024);

	if (SUCCEED == (ret = trapper_device_init(jp, config_adapter_url, config_tls_ca_file, config_tls_cert_file,
			config_tls_key_file, config_connect_to, &error, &json)))
	{
		if (SUCCEED != zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size,
				config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() failed sending device.init response", __func__);
		}
	}
	else
	{
		if (SUCCEED != zbx_send_response(sock, ret, ZBX_NULL2EMPTY_STR(error), config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() failed sending device.init error response",
					__func__);
		}

		zbx_free(error);
	}

	zbx_json_free(&json);
out:
	zabbix_log(LOG_LEVEL_INFORMATION, "End of %s()", __func__);
}

static int	trapper_device_offboard(const struct zbx_json_parse *jp, const char *config_adapter_url,
		const char *config_tls_ca_file, const char *config_tls_cert_file, const char *config_tls_key_file,
		const char *config_adapter_connect_to, char **error, struct zbx_json *json)
{
#if !defined(HAVE_LIBCURL)
	ZBX_UNUSED(jp);
	ZBX_UNUSED(config_adapter_url);
	ZBX_UNUSED(config_tls_ca_file);
	ZBX_UNUSED(config_tls_cert_file);
	ZBX_UNUSED(config_tls_key_file);
	ZBX_UNUSED(config_adapter_connect_to);
	ZBX_UNUSED(error);
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	zbx_http_response_t		body = {0}, response_header = {0};
	CURL				*curl = NULL;
	CURLcode			err;
	CURLoption			opt;
	struct curl_slist		*headers = NULL, *connect_to = NULL;
	long				http_code = 0;
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result;
	char				*payload = NULL, *error_curl = NULL, errbuf[CURL_ERROR_SIZE],
					device_id[ZBX_UUID_LEN], code[ZBX_ERROR_CODE_LEN], message[ZBX_MESSAGE_LEN],
					error_data[ZBX_MESSAGE_LEN], *uuid7id;
	int				ret = FAIL;
	zbx_config_t			cfg;
	const char			*serverid;

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_body))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing data object in device.init request");
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_body, "uuid", device_id, sizeof(device_id), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing uuid in device.init request");
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SERVER_ID);
	serverid = cfg.serverid;

	zbx_json_init(&request, 512);

	zbx_json_addstring(&request, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "method", "device.offboard", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&request, "params");
	zbx_json_addstring(&request, "server_id", serverid, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "device_id", device_id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);
	uuid7id = zbx_gen_uuid7();
	zbx_json_addstring(&request, "id", uuid7id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);

	payload = zbx_strdup(NULL, request.buffer);

	if (NULL == (curl = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed initialize cURL library");
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (SUCCEED != zbx_http_prepare_callbacks(curl, &response_header, &body, zbx_curl_ignore_cb,
			zbx_curl_write_cb, errbuf, &error_curl))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot prepare HTTP callbacks: %s",
				ZBX_NULL2EMPTY_STR(error_curl));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	headers = curl_slist_append(headers, "Content-Type: application/json");
	headers = curl_slist_append(headers, "X-Trace-Id: test-trace-1");

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_URL, config_adapter_url)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_HTTPHEADER, headers)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDS, payload)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDSIZE, strlen(payload))) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CONNECTTIMEOUT_MS, 2000L)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_TIMEOUT_MS, 5000L)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set cURL option %d: %s", (int)opt,
				curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_https(curl, &error_curl))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed zbx_curl_setopt_https: %s",
				ZBX_NULL2EMPTY_STR(error_curl));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_ssl_version(curl, &error_curl))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed zbx_curl_setopt_ssl_version: %s",
				ZBX_NULL2EMPTY_STR(error_curl));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CAINFO, config_tls_ca_file)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLCERT, config_tls_cert_file)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLKEY, config_tls_key_file)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSL_VERIFYPEER, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSL_VERIFYHOST, 2L)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed set cURL option %d: %s", (int)opt,
				curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (NULL != config_adapter_connect_to)
	{
		connect_to = curl_slist_append(connect_to, config_adapter_connect_to);

		if (NULL == connect_to)
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to prepare CURLOPT_CONNECT_TO value");
			*error = zbx_strdup(NULL, "Failed process device.offboard request");
			goto out;
		}

		if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CONNECT_TO, connect_to)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed set cURL option %d: %s.", (int)opt,
				curl_easy_strerror(err));
			*error = zbx_strdup(NULL, "Failed process device.offboard request");
			goto out;
		}
	}

	if (CURLE_OK != (err = curl_easy_perform(curl)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed connect to bridge-adapter: %s",
				curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed connect to bridge-adapter");
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &http_code)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed obtain bridge-adapter response code: %s",
				curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (FAIL == zbx_json_open(body.data, &jp_body))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (http_code < 200 || http_code >= 300)
	{
		zabbix_log(LOG_LEVEL_WARNING, "bridge-adapter returned HTTP %ld: %s", http_code,
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_strdup(NULL, "Failed process device.offboard request");
		goto out;
	}

	if (SUCCEED == zbx_json_brackets_by_name(&jp_body, "error", &jp_result))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp_result, "code", code, sizeof(code), NULL) &&
				SUCCEED == zbx_json_value_by_name(&jp_result, "message", message,
				sizeof(message), NULL) && SUCCEED == zbx_json_value_by_name(&jp_result, "data",
				error_data, sizeof(error_data), NULL))
		{
			char	info[ZBX_INFO_LEN];

			zabbix_log(LOG_LEVEL_WARNING, "bridge-adapter returned %s: message: %s data %s", code,
					message, error_data);
			zbx_snprintf(info, sizeof(info), "Bridge-adapter returned code: %s, message: %s data: %s",
					code, message, error_data);
			*error = zbx_strdup(NULL, info);
		}
		else
			*error = zbx_strdup(NULL, "Failed process device.offboard request");

		goto out;
	}

	ret = SUCCEED;
out:
	curl_slist_free_all(headers);
	curl_easy_cleanup(curl);
	zbx_json_free(&request);
	zbx_free(payload);
	zbx_free(error_curl);
	zbx_free(body.data);
	zbx_free(response_header.data);

	return ret;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes device.offboard request                                 *
 *                                                                            *
 * Comments: resolves device owner, validates caller permissions and forwards *
 *           request to bridge adapter                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_trapper_device_offboard(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const zbx_config_tls_t *config_tls,
		const char *config_frontend_allowed_ip, const char *config_adapter_url, const char *config_tls_ca_file,
		const char *config_tls_cert_file, const char *config_tls_key_file, const char *config_connect_to)
{
	struct zbx_json		json;
	struct zbx_json_parse	jp_data;
	int			ret;
	zbx_user_t		user;
	zbx_uint64_t		target_userid;
	char			deviceid[ZBX_UUID_LEN], *error = NULL;

	zabbix_log(LOG_LEVEL_INFORMATION, "In %s()", __func__);

	if (SUCCEED != zbx_check_frontend_conn_accept(sock, config_tls, config_frontend_allowed_ip))
		goto out;

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() failed to get user from request", __func__);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() missing data object in request", __func__);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_data, "uuid", deviceid, sizeof(deviceid), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() missing uuid in request data", __func__);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	if (FAIL == device_get_userid_by_uuid(deviceid, &target_userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() failed to resolve device owner by uuid: %s", __func__, deviceid);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	if (FAIL == device_check_permissions(&user, target_userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() permission denied for userid:" ZBX_FS_UI64 " target_userid:"
				ZBX_FS_UI64, __func__, user.userid, target_userid);
		zbx_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	zbx_json_init(&json, 1024);

	if (SUCCEED == (ret = trapper_device_offboard(jp, config_adapter_url, config_tls_ca_file, config_tls_cert_file,
			config_tls_key_file, config_connect_to, &error, &json)))
	{
		if (SUCCEED != zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size,
				config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() failed sending device.offboard response",
					__func__);
		}
	}
	else
	{
		if (SUCCEED != zbx_send_response(sock, ret, error, config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() failed sending device.offboard error response",
					__func__);
		}

		zbx_free(error);
	}

	zbx_json_free(&json);
out:
	zabbix_log(LOG_LEVEL_INFORMATION, "End of %s()", __func__);
}
