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

#include "trapper_device_management.h"
#include "trapper.h"
#include "zbxtrapper.h"

#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxcomms.h"
#include "zbxcommshigh.h"
#include "zbxcrypto.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbx_bridge_adapter_constants.h"

#if defined(HAVE_LIBCURL)
#	include "zbxcurl.h"
#	include "zbxhttp.h"
#endif

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
	int				ret = FAIL;
	zbx_user_role_permission_t	default_access = ROLE_PERM_DENY, permission;
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
		permission = (zbx_user_role_permission_t)atoi(row[1]);

		if (0 == strcmp(required_rule, row[0]))
		{
			ret = (ROLE_PERM_ALLOW == permission ? SUCCEED : FAIL);
			goto out;
		}
		else if (0 == strcmp(ZBX_USER_ROLE_PERMISSION_DEVICES_DEFAULT_ACCESS, row[0]))
			default_access = permission;
		else
			THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected role_rule entry returned while checking device"
					" permissions: roleid:" ZBX_FS_UI64 " userid:" ZBX_FS_UI64
					" target_userid:" ZBX_FS_UI64 " required_rule:\"%s\" name:\"%s\" value:\"%s\"",
					user->roleid, user->userid, target_userid, required_rule,
					ZBX_NULL2EMPTY_STR(row[0]), ZBX_NULL2EMPTY_STR(row[1]));
	}

	ret = (ROLE_PERM_ALLOW == default_access ? SUCCEED : FAIL);
out:
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef ZBX_USER_ROLE_PERMISSION_DEVICES_DEFAULT_ACCESS
#undef ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_OWN
#undef ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_USER
}

static void	trapper_device_send_response(zbx_socket_t *sock, int ret, const char *info, int timeout,
		const char *func, const char *request)
{
	if (SUCCEED != zbx_send_response(sock, ret, info, timeout))
		zabbix_log(LOG_LEVEL_TRACE, "%s() failed to send %s error response", func, request);
}

static int	device_mobile_devices_enabled(void)
{
	zbx_config_t	cfg;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_ENABLE_MOBILE_DEVICES);

	return (0 != cfg.enable_mobile_devices ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets device owner userid by device uuid                           *
 *                                                                            *
 * Parameters: uuid          - [IN] device UUID                               *
 *             target_userid - [OUT] device owner user ID                     *
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
	char			*uuid_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:%s", __func__, uuid);

	uuid_esc = zbx_db_dyn_escape_string(uuid);

	result = zbx_db_select("select userid from device where uuid='%s'", uuid_esc);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(*target_userid, row[0]);
		ret = SUCCEED;
	}

	zbx_db_free_result(result);
	zbx_free(uuid_esc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#if defined(HAVE_LIBCURL)
static int	trapper_device_bridge_adapter_request(const zbx_config_comms_args_t *config_comms,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to,
		const char *payload, const char *request, char **body_data, struct zbx_json_parse *jp_body,
		char **error)
{
	zbx_http_response_t	body = {0}, response_header = {0};
	CURL			*curl = NULL;
	CURLcode		err;
	CURLoption		opt;
	struct curl_slist	*headers = NULL, *connect_to = NULL;
	char			*error_curl = NULL, errbuf[CURL_ERROR_SIZE], jsonrpc[ZBX_MAX_UINT64_LEN];
	long			http_code = 0;
	int			ret = FAIL;
	const zbx_config_tls_t	*config_tls = config_comms->config_tls;

	*body_data = NULL;

	if (NULL == config_bridge_adapter_url || '\0' == *config_bridge_adapter_url)
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to connect to bridge-adapter: \"BridgeAdapterURL\""
				" configuration parameter is not set");
		*error = zbx_strdup(NULL, "Failed to connect to bridge-adapter");
		goto out;
	}

	if (NULL == (curl = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to initialize cURL library");
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	if (SUCCEED != zbx_http_prepare_callbacks(curl, &response_header, &body, zbx_curl_ignore_cb,
			zbx_curl_write_cb, errbuf, &error_curl))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot prepare HTTP callbacks: %s",
				ZBX_NULL2EMPTY_STR(error_curl));
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	headers = curl_slist_append(headers, "Content-Type: application/json");

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_URL, config_bridge_adapter_url)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_HTTPHEADER, headers)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDS, payload)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDSIZE, strlen(payload))) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_TIMEOUT,
					(long)ZBX_BRIDGE_ADAPTER_TIMEOUT)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set cURL option %d: %s.", (int)opt,
				curl_easy_strerror(err));
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	if (NULL != config_tls->ca_file && NULL != config_tls->cert_file && NULL != config_tls->key_file)
	{
		if (SUCCEED != zbx_curl_setopt_https(curl, &error_curl))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to set cURL HTTPS options: %s",
					ZBX_NULL2EMPTY_STR(error_curl));
			*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
			goto out;
		}

		if (SUCCEED != zbx_curl_setopt_ssl_version(curl, &error_curl))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to set cURL SSL version: %s",
					ZBX_NULL2EMPTY_STR(error_curl));
			*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
			goto out;
		}

		if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CAINFO, config_tls->ca_file)) ||
				CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLCERT,
				config_tls->cert_file)) ||
				CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLKEY, config_tls->key_file))
				|| CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSL_VERIFYPEER, 1L)) ||
				CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSL_VERIFYHOST, 2L)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
			*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
			goto out;
		}
	}
	else
	{
		if (NULL != config_tls->ca_file || NULL != config_tls->cert_file || NULL != config_tls->key_file)
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (NULL != config_bridge_adapter_connect_to)
	{
		connect_to = curl_slist_append(connect_to, config_bridge_adapter_connect_to);

		if (NULL == connect_to)
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to prepare CURLOPT_CONNECT_TO value");
			*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
			goto out;
		}

		if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CONNECT_TO, connect_to)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
			*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
			goto out;
		}
	}

	if (CURLE_OK != (err = curl_easy_perform(curl)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to connect to bridge-adapter: %s", curl_easy_strerror(err));
		*error = zbx_strdup(NULL, "Failed to connect to bridge-adapter");
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &http_code)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to obtain bridge-adapter response code: %s",
				curl_easy_strerror(err));
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	if (http_code < 200 || http_code >= 300)
	{
		zabbix_log(LOG_LEVEL_WARNING, "bridge-adapter returned HTTP %ld: %s", http_code,
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	if (FAIL == zbx_json_open(body.data, jp_body))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(jp_body, "jsonrpc", jsonrpc, sizeof(jsonrpc), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing JSON-RPC version in bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	if (0 != strcmp(jsonrpc, "2.0"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid JSON-RPC version in bridge-adapter response body: %s,"
				" expected 2.0", jsonrpc);
		*error = zbx_dsprintf(NULL, "Failed to process %s request", request);
		goto out;
	}

	*body_data = body.data;
	body.data = NULL;
	ret = SUCCEED;
out:
	curl_slist_free_all(connect_to);
	curl_slist_free_all(headers);
	if (NULL != curl)
		curl_easy_cleanup(curl);
	zbx_free(error_curl);
	zbx_free(body.data);
	zbx_free(response_header.data);

	return ret;
}

#endif

static int	trapper_device_init(const struct zbx_json_parse *jp, const zbx_config_comms_args_t *config_comms,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to, char **error,
		struct zbx_json *json)
{
#define ZBX_ENROLL_URL_LEN		2048
#define ZBX_BRIDGE_ENCRYPTION_KEY_LEN	256
#define ZBX_ENROLL_TOKEN_LEN		128

#if !defined(HAVE_LIBCURL)
	ZBX_UNUSED(jp);
	ZBX_UNUSED(config_comms);
	ZBX_UNUSED(config_bridge_adapter_url);
	ZBX_UNUSED(config_bridge_adapter_connect_to);
	ZBX_UNUSED(error);
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result, jp_bek;
	char				*body_data = NULL, *bek_raw = NULL,
					device_id[ZBX_UUID_LEN], met[ZBX_ENROLL_TOKEN_LEN],
					enroll_url[ZBX_ENROLL_URL_LEN], code[ZBX_BRIDGE_ERROR_CODE_LEN],
					message[ZBX_BRIDGE_MESSAGE_LEN], error_data[ZBX_BRIDGE_MESSAGE_LEN],
					*uuid7id = NULL;
	int				ret = FAIL;
	size_t				bek_len;

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_body))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing data object in " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		goto out2;
	}

	if (FAIL == zbx_json_value_by_name(&jp_body, "uuid", device_id, sizeof(device_id), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing uuid in " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		goto out2;
	}

	zbx_json_init(&request, 512);

	zbx_json_addstring(&request, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "method", ZBX_PROTO_VALUE_DEVICE_INIT, ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&request, "params");
	zbx_json_addstring(&request, "device_id", device_id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);
	uuid7id = zbx_gen_uuid7_hyphenated();
	zbx_json_addstring(&request, "id", uuid7id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);

	if (SUCCEED != trapper_device_bridge_adapter_request(config_comms, config_bridge_adapter_url,
			config_bridge_adapter_connect_to, request.buffer, ZBX_PROTO_VALUE_DEVICE_INIT, &body_data,
			&jp_body, error))
		goto out;

	if (SUCCEED == zbx_json_brackets_by_name(&jp_body, "error", &jp_result))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp_result, "code", code, sizeof(code), NULL) &&
				SUCCEED == zbx_json_value_by_name(&jp_result, "message", message, sizeof(message),
				NULL) && SUCCEED == zbx_json_value_by_name(&jp_result, "data", error_data,
				sizeof(error_data), NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Bridge-adapter returned code: %s, message: %s data: %s",
					code, message, error_data);
			*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "incomplete error in bridge-adapter response body: %s",
					ZBX_NULL2EMPTY_STR(body_data));
			*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		}

		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_body, "result", &jp_result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing result in bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body_data));
		*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_result, "enrollment_token", met, sizeof(met), NULL) ||
			FAIL == zbx_json_brackets_by_name(&jp_result, "adapter_enc_key", &jp_bek) ||
			FAIL == zbx_json_value_by_name(&jp_result, "enroll_url", enroll_url,
			sizeof(enroll_url), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing enrollment_token/adapter_enc_key/enroll_url in bridge-adapter"
				" result: %s", ZBX_NULL2EMPTY_STR(body_data));
		*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_INIT " request");
		goto out;
	}

	bek_len = (size_t)(jp_bek.end - jp_bek.start + 1);
	bek_raw = (char *)zbx_malloc(NULL, bek_len + 1);
	memcpy(bek_raw, jp_bek.start, bek_len);
	bek_raw[bek_len] = '\0';

	zbx_json_addstring(json, "response", "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(json, "data");
	zbx_json_addstring(json, "mobile_enrollment_token", met, ZBX_JSON_TYPE_STRING);
	zbx_json_addraw(json, "bridge_adapter_encryption_key", bek_raw);
	zbx_json_addstring(json, "bridge_enrollment_url", enroll_url, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);

	ret = SUCCEED;
out:
	zbx_json_free(&request);
out2:
	zbx_free(body_data);
	zbx_free(bek_raw);
	zbx_free(uuid7id);

	return ret;

#endif
#undef ZBX_ENROLL_URL_LEN
#undef ZBX_BRIDGE_ENCRYPTION_KEY_LEN
#undef ZBX_ENROLL_TOKEN_LEN
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes device initialization request                           *
 *                                                                            *
 * Parameters: sock                             - [IN]                        *
 *             jp                               - [IN]                        *
 *             config_comms                     - [IN]                        *
 *             config_frontend_allowed_ip       - [IN]                        *
 *             config_bridge_adapter_url        - [IN]                        *
 *             config_bridge_adapter_connect_to - [IN]                        *
 *                                                                            *
 * Comments: validates caller permissions for requested target user and       *
 *           forwards request to bridge adapter                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_trapper_device_init(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const char *config_frontend_allowed_ip,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to)
{
	struct zbx_json		json;
	struct zbx_json_parse	jp_data;
	int			ret;
	char			*error = NULL, target_userid_str[ZBX_USER_ID_LEN];
	zbx_user_t		user;
	zbx_uint64_t		target_userid;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_user_init(&user);

	if (SUCCEED != zbx_check_frontend_conn_accept(sock, config_comms->config_tls, config_frontend_allowed_ip))
		goto out;

	if (FAIL == device_mobile_devices_enabled())
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot initialize device: mobile devices are disabled");
		trapper_device_send_response(sock, FAIL, "Mobile devices are disabled.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_INIT);
		goto out;
	}

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot initialize device: failed to get user from request");
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_INIT);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot initialize device: missing data object in request");
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_INIT);
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_data, "userid", target_userid_str, sizeof(target_userid_str), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot initialize device: missing userid in request data");
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_INIT);
		goto out;
	}

	ZBX_STR2UINT64(target_userid, target_userid_str);

	if (FAIL == device_check_permissions(&user, target_userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot initialize device: permission denied for userid:"
				ZBX_FS_UI64 " target_userid:" ZBX_FS_UI64, user.userid, target_userid);
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_INIT);
		goto out;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED == (ret = trapper_device_init(jp, config_comms, config_bridge_adapter_url,
			config_bridge_adapter_connect_to, &error, &json)))
	{
		if (SUCCEED != zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size,
				config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot initialize device: failed to send "
					ZBX_PROTO_VALUE_DEVICE_INIT " response");
		}
	}
	else
	{
		trapper_device_send_response(sock, ret, ZBX_NULL2EMPTY_STR(error), config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_INIT);

		zbx_free(error);
	}

	zbx_json_free(&json);
out:
	zbx_user_free(&user);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	trapper_device_offboard(const struct zbx_json_parse *jp, const zbx_config_comms_args_t *config_comms,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to, char **error,
		struct zbx_json *json)
{
#if !defined(HAVE_LIBCURL)
	ZBX_UNUSED(jp);
	ZBX_UNUSED(config_comms);
	ZBX_UNUSED(config_bridge_adapter_url);
	ZBX_UNUSED(config_bridge_adapter_connect_to);
	ZBX_UNUSED(error);
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result;
	char				*body_data = NULL,
					device_id[ZBX_UUID_LEN], code[ZBX_BRIDGE_ERROR_CODE_LEN],
					message[ZBX_BRIDGE_MESSAGE_LEN], error_data[ZBX_BRIDGE_MESSAGE_LEN],
					*uuid7id = NULL;
	int				ret = FAIL;

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_body))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing data object in " ZBX_PROTO_VALUE_DEVICE_OFFBOARD " request");
		*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_OFFBOARD " request");
		goto out2;
	}

	if (FAIL == zbx_json_value_by_name(&jp_body, "uuid", device_id, sizeof(device_id), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing uuid in " ZBX_PROTO_VALUE_DEVICE_OFFBOARD " request");
		*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_OFFBOARD " request");
		goto out2;
	}

	zbx_json_init(&request, 512);

	zbx_json_addstring(&request, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "method", "device.deactivate", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&request, "params");
	zbx_json_addstring(&request, "device_id", device_id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);
	uuid7id = zbx_gen_uuid7_hyphenated();
	zbx_json_addstring(&request, "id", uuid7id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);

	if (SUCCEED != trapper_device_bridge_adapter_request(config_comms, config_bridge_adapter_url,
			config_bridge_adapter_connect_to, request.buffer, ZBX_PROTO_VALUE_DEVICE_OFFBOARD, &body_data,
			&jp_body, error))
		goto out;

	if (SUCCEED == zbx_json_brackets_by_name(&jp_body, "error", &jp_result))
	{
		if (SUCCEED == zbx_json_value_by_name(&jp_result, "code", code, sizeof(code), NULL) &&
				SUCCEED == zbx_json_value_by_name(&jp_result, "message", message,
				sizeof(message), NULL) && SUCCEED == zbx_json_value_by_name(&jp_result, "data",
				error_data, sizeof(error_data), NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Bridge-adapter returned code: %s, message: %s data: %s",
					code, message, error_data);
			*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_OFFBOARD " request");
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "incomplete error in bridge-adapter response body: %s",
					ZBX_NULL2EMPTY_STR(body_data));
			*error = zbx_strdup(NULL, "Failed to process " ZBX_PROTO_VALUE_DEVICE_OFFBOARD " request");
		}

		goto out;
	}

	zbx_json_addstring(json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	ret = SUCCEED;
out:
	zbx_json_free(&request);
out2:
	zbx_free(body_data);
	zbx_free(uuid7id);

	return ret;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes device offboarding request                              *
 *                                                                            *
 * Parameters: sock                             - [IN]                        *
 *             jp                               - [IN]                        *
 *             config_comms                     - [IN]                        *
 *             config_frontend_allowed_ip       - [IN]                        *
 *             config_bridge_adapter_url        - [IN]                        *
 *             config_bridge_adapter_connect_to - [IN]                        *
 *                                                                            *
 * Comments: resolves device owner, validates caller permissions and forwards *
 *           request to bridge adapter                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_trapper_device_offboard(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const char *config_frontend_allowed_ip,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to)
{
	struct zbx_json		json;
	struct zbx_json_parse	jp_data;
	int			ret;
	zbx_user_t		user;
	zbx_uint64_t		target_userid;
	char			deviceid[ZBX_UUID_LEN], *error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_user_init(&user);

	if (SUCCEED != zbx_check_frontend_conn_accept(sock, config_comms->config_tls, config_frontend_allowed_ip))
		goto out;

	if (FAIL == device_mobile_devices_enabled())
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot offboard device: mobile devices are disabled");
		trapper_device_send_response(sock, FAIL, "Mobile devices are disabled.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);
		goto out;
	}

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot offboard device: failed to get user from request");
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot offboard device: missing data object in request");
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_data, "uuid", deviceid, sizeof(deviceid), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot offboard device: missing uuid in request data");
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);
		goto out;
	}

	if (FAIL == device_get_userid_by_uuid(deviceid, &target_userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot offboard device: failed to resolve device owner by uuid:"
				" %s", deviceid);
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);
		goto out;
	}

	if (FAIL == device_check_permissions(&user, target_userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot offboard device: permission denied for userid:"
				ZBX_FS_UI64 " target_userid:" ZBX_FS_UI64, user.userid, target_userid);
		trapper_device_send_response(sock, FAIL, "Permission denied.", config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);
		goto out;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED == (ret = trapper_device_offboard(jp, config_comms, config_bridge_adapter_url,
			config_bridge_adapter_connect_to, &error, &json)))
	{
		if (SUCCEED != zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size,
				config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot offboard device: failed to send "
					ZBX_PROTO_VALUE_DEVICE_OFFBOARD " response");
		}
	}
	else
	{
		trapper_device_send_response(sock, ret, ZBX_NULL2EMPTY_STR(error), config_comms->config_timeout,
				__func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);

		zbx_free(error);
	}

	zbx_json_free(&json);
out:
	zbx_user_free(&user);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
