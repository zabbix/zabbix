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
#include "zbxcrypto.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbx_bridge_adapter_constants.h"

#if defined(HAVE_LIBCURL)
#	include "zbxhttp.h"
#endif

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
	const char			*required_rule;
	zbx_db_result_t			result = NULL;
	zbx_db_row_t			row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 " target_userid:" ZBX_FS_UI64,
			__func__, user->userid, target_userid);

	if (user->userid == target_userid)
		required_rule = ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_OWN;
	else if (USER_TYPE_SUPER_ADMIN == user->type)
		required_rule = ZBX_USER_ROLE_PERMISSION_DEVICES_MANAGE_USER;
	else
		goto out;

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

static void	trapper_device_send_response(zbx_socket_t *sock, int ret, const char *message, const char *reason,
		const char *domain, int timeout, const char *func, const char *request)
{
	struct zbx_json	json;
	const char	*response;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	response = (SUCCEED == ret ? ZBX_PROTO_VALUE_SUCCESS : ZBX_PROTO_VALUE_FAILED);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, response, ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&json, ZBX_PROTO_TAG_INFO);

	if (NULL != reason && NULL != domain)
	{
		zbx_json_addstring(&json, "reason", reason, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "domain", domain, ZBX_JSON_TYPE_STRING);
	}
	else
		zbx_json_addstring(&json, "message", message, ZBX_JSON_TYPE_STRING);

	zbx_json_close(&json);

	if (SUCCEED != zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size, timeout))
		zabbix_log(LOG_LEVEL_TRACE, "%s() failed to send %s error response", func, request);

	zbx_json_free(&json);
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
		ZBX_DBROW2UINT64(*target_userid, row[0]);
		ret = SUCCEED;
	}

	zbx_db_free_result(result);
	zbx_free(uuid_esc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates a device management request and resolves the device     *
 *          owner's userid                                                    *
 *                                                                            *
 * Parameters: sock                       - [IN]                              *
 *             jp                         - [IN]                              *
 *             config_comms               - [IN]                              *
 *             config_frontend_allowed_ip - [IN]                              *
 *             request                    - [IN] ZBX_PROTO_VALUE_DEVICE_*     *
 *             action                     - [IN] "initialize" or "offboard",  *
 *                                                for log messages            *
 *             id_field                   - [IN] "userid" or "uuid"           *
 *             id_is_uuid                 - [IN] 0 - id_field is a userid;    *
 *                                                otherwise a device uuid,    *
 *                                                resolved to its owner       *
 *             user                       - [OUT]                             *
 *             target_userid              - [OUT] device owner userid         *
 *                                                                            *
 * Return value:  SUCCEED - caller may proceed with the request               *
 *                FAIL    - otherwise, an error response has already been     *
 *                          sent to the caller, except when the connection    *
 *                          itself was rejected                               *
 *                                                                            *
 ******************************************************************************/
static int	trapper_device_authorize(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const char *config_frontend_allowed_ip,
		const char *request, const char *action, const char *id_field, int id_is_uuid, zbx_user_t *user,
		zbx_uint64_t *target_userid)
{
	struct zbx_json_parse	jp_data;
	char			id_str[ZBX_UUID_LEN];

	if (SUCCEED != zbx_check_frontend_conn_accept(sock, config_comms->config_tls, config_frontend_allowed_ip))
		return FAIL;

	if (FAIL == device_mobile_devices_enabled())
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot %s device: mobile devices are disabled", action);
		trapper_device_send_response(sock, FAIL, "Mobile devices are disabled.", NULL, NULL,
				config_comms->config_timeout, __func__, request);
		return FAIL;
	}

	if (FAIL == zbx_get_user_from_json(jp, user, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot %s device: failed to get user from request", action);
		trapper_device_send_response(sock, FAIL, "Permission denied.", NULL, NULL,
				config_comms->config_timeout, __func__, request);
		return FAIL;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot %s device: missing data object in request", action);
		trapper_device_send_response(sock, FAIL, "Permission denied.", NULL, NULL,
				config_comms->config_timeout, __func__, request);
		return FAIL;
	}

	if (FAIL == zbx_json_value_by_name(&jp_data, id_field, id_str, sizeof(id_str), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot %s device: missing %s in request data", action, id_field);
		trapper_device_send_response(sock, FAIL, "Permission denied.", NULL, NULL,
				config_comms->config_timeout, __func__, request);
		return FAIL;
	}

	if (0 != id_is_uuid)
	{
		if (FAIL == device_get_userid_by_uuid(id_str, target_userid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot %s device: failed to resolve device owner by uuid:"
					" %s", action, id_str);
			trapper_device_send_response(sock, FAIL, "Permission denied.", NULL, NULL,
					config_comms->config_timeout, __func__, request);
			return FAIL;
		}
	}
	else
		ZBX_STR2UINT64(*target_userid, id_str);

	if (FAIL == device_check_permissions(user, *target_userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot %s device: permission denied for userid:" ZBX_FS_UI64
				" target_userid:" ZBX_FS_UI64, action, user->userid, *target_userid);
		trapper_device_send_response(sock, FAIL, "Permission denied.", NULL, NULL,
				config_comms->config_timeout, __func__, request);
		return FAIL;
	}

	return SUCCEED;
}

#if defined(HAVE_LIBCURL)
typedef enum
{
	ZBX_DEVICE_BA_ERR_NOT_CONFIGURED,
	ZBX_DEVICE_BA_ERR_CONNECT,
	ZBX_DEVICE_BA_ERR_INVALID_RESPONSE,
	ZBX_DEVICE_BA_ERR_RETURNED_ERROR
}
zbx_device_ba_error_t;

static zbx_device_ba_error_t	trapper_device_map_http_jsonrpc_error(zbx_http_jsonrpc_error_t error)
{
	switch (error)
	{
		case ZBX_HTTP_JSONRPC_ERR_CONNECT:
			return ZBX_DEVICE_BA_ERR_CONNECT;
		case ZBX_HTTP_JSONRPC_ERR_INVALID_RESPONSE:
			return ZBX_DEVICE_BA_ERR_INVALID_RESPONSE;
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected HTTP JSON-RPC error: %d", (int)error);

	return ZBX_DEVICE_BA_ERR_INVALID_RESPONSE;
}

static int	trapper_device_bridge_adapter_error_info(const struct zbx_json_parse *jp_error, char **reason,
		char **domain)
{
	struct zbx_json_parse	jp_data, jp_details, jp_detail;
	const char		*p = NULL;
	char			type[ZBX_BRIDGE_MESSAGE_LEN];

	if (SUCCEED != zbx_json_brackets_by_name(jp_error, "data", &jp_data) ||
			ZBX_JSON_TYPE_OBJECT != zbx_json_valuetype(jp_data.start) ||
			SUCCEED != zbx_json_brackets_by_name(&jp_data, "details", &jp_details) ||
			ZBX_JSON_TYPE_ARRAY != zbx_json_valuetype(jp_details.start))
	{
		return FAIL;
	}

	while (NULL != (p = zbx_json_next(&jp_details, p)))
	{
		char		*detail_reason = NULL, *detail_domain = NULL;
		size_t		detail_reason_alloc = 0, detail_domain_alloc = 0;
		zbx_json_type_t	reason_type, domain_type;

		if (ZBX_JSON_TYPE_OBJECT != zbx_json_valuetype(p) ||
				SUCCEED != zbx_json_brackets_open(p, &jp_detail) ||
				SUCCEED != zbx_json_value_by_name(&jp_detail, "@type", type, sizeof(type), NULL) ||
				0 != strcmp(type, "bridge_jsonrpc.ErrorInfo"))
		{
			continue;
		}

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_detail, "reason", &detail_reason,
				&detail_reason_alloc, &reason_type) && ZBX_JSON_TYPE_STRING == reason_type &&
				SUCCEED == zbx_json_value_by_name_dyn(&jp_detail, "domain", &detail_domain,
				&detail_domain_alloc, &domain_type) && ZBX_JSON_TYPE_STRING == domain_type)
		{
			*reason = detail_reason;
			*domain = detail_domain;

			return SUCCEED;
		}

		zbx_free(detail_reason);
		zbx_free(detail_domain);
	}

	return FAIL;
}

static const char	*trapper_device_bridge_adapter_error(const char *request, zbx_device_ba_error_t error)
{
	if (0 == strcmp(request, ZBX_PROTO_VALUE_DEVICE_INIT))
	{
		switch (error)
		{
			case ZBX_DEVICE_BA_ERR_NOT_CONFIGURED:
				return "Cannot initialize mobile device, bridge-adapter is not configured.";
			case ZBX_DEVICE_BA_ERR_CONNECT:
				return "Cannot initialize mobile device, cannot connect to bridge-adapter.";
			case ZBX_DEVICE_BA_ERR_INVALID_RESPONSE:
				return "Cannot initialize mobile device, bridge-adapter returned an invalid response.";
			case ZBX_DEVICE_BA_ERR_RETURNED_ERROR:
				return "Cannot initialize mobile device, bridge-adapter returned an error.";
		}
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_DEVICE_OFFBOARD))
	{
		switch (error)
		{
			case ZBX_DEVICE_BA_ERR_NOT_CONFIGURED:
				return "Cannot remove mobile device, bridge-adapter is not configured.";
			case ZBX_DEVICE_BA_ERR_CONNECT:
				return "Cannot remove mobile device, cannot connect to bridge-adapter.";
			case ZBX_DEVICE_BA_ERR_INVALID_RESPONSE:
				return "Cannot remove mobile device, bridge-adapter returned an invalid response.";
			case ZBX_DEVICE_BA_ERR_RETURNED_ERROR:
				return "Cannot remove mobile device, bridge-adapter returned an error.";
		}
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected bridge-adapter error mapping: request:\"%s\" error:%d", request,
			(int)error);

	return "Cannot initialize mobile device, bridge-adapter returned an invalid response.";
}

static int	trapper_device_bridge_adapter_request(const zbx_config_comms_args_t *config_comms,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to,
		const char *payload, const char *request, char **body_data, struct zbx_json_parse *jp_body,
		char **error)
{
	const zbx_config_tls_t		*config_tls = config_comms->config_tls;
	zbx_http_jsonrpc_error_t	http_error;
	zbx_device_ba_error_t		device_error;
	int				ret;

	if (NULL == config_bridge_adapter_url || '\0' == *config_bridge_adapter_url)
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to connect to bridge-adapter: \"BridgeAdapterURL\""
				" configuration parameter is not set");
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(request,
				ZBX_DEVICE_BA_ERR_NOT_CONFIGURED));
		return FAIL;
	}

	ret = zbx_http_post_json_rpc(config_bridge_adapter_url, config_tls->ca_file, config_tls->crl_file,
			config_tls->cert_file, config_tls->key_file, config_bridge_adapter_connect_to, payload, request,
			ZBX_BRIDGE_ADAPTER_SERVICE_NAME, (long)ZBX_BRIDGE_ADAPTER_TIMEOUT, body_data, jp_body,
			&http_error);

	if (SUCCEED != ret)
	{
		device_error = trapper_device_map_http_jsonrpc_error(http_error);
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(request, device_error));
	}

	return ret;
}

static int	trapper_device_get_device_id(const struct zbx_json_parse *jp, const char *request, char *device_id,
		size_t device_id_len, char **error)
{
	struct zbx_json_parse	jp_data;

	if (FAIL == zbx_json_brackets_by_name(jp, "data", &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing data object in %s request", request);
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(request,
				ZBX_DEVICE_BA_ERR_INVALID_RESPONSE));
		return FAIL;
	}

	if (FAIL == zbx_json_value_by_name(&jp_data, "uuid", device_id, device_id_len, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing uuid in %s request", request);
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(request,
				ZBX_DEVICE_BA_ERR_INVALID_RESPONSE));
		return FAIL;
	}

	return SUCCEED;
}

static void	trapper_device_bridge_adapter_handle_error(const struct zbx_json_parse *jp_result, const char *request,
		const char *body_data, char **error, char **reason, char **domain)
{
	char	code[ZBX_BRIDGE_ERROR_CODE_LEN], message[ZBX_BRIDGE_MESSAGE_LEN], *error_data = NULL;

	if (SUCCEED == zbx_json_value_by_name(jp_result, "code", code, sizeof(code), NULL) &&
			SUCCEED == zbx_json_value_by_name(jp_result, "message", message, sizeof(message), NULL))
	{
		error_data = zbx_json_raw_value_by_path_dyn(jp_result, "$.data");
#ifdef ZBX_DEBUG
		zabbix_log(LOG_LEVEL_WARNING, "Bridge-adapter returned code: %s, message: %s data: %s",
				code, message, ZBX_NULL2EMPTY_STR(error_data));
#else
		zabbix_log(LOG_LEVEL_WARNING, "Bridge-adapter returned code: %s, message: %s", code, message);
#endif
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(request,
				ZBX_DEVICE_BA_ERR_RETURNED_ERROR));
		(void)trapper_device_bridge_adapter_error_info(jp_result, reason, domain);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "incomplete error in bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body_data));
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(request,
				ZBX_DEVICE_BA_ERR_INVALID_RESPONSE));
	}

	zbx_free(error_data);
}

#endif

static int	trapper_device_init(const struct zbx_json_parse *jp, const zbx_config_comms_args_t *config_comms,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to, char **error,
		char **reason, char **domain, struct zbx_json *json)
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
	ZBX_UNUSED(reason);
	ZBX_UNUSED(domain);
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result, jp_bek;
	char				*body_data = NULL, *bek_raw = NULL,
					device_id[ZBX_UUID_LEN], met[ZBX_ENROLL_TOKEN_LEN],
					enroll_url[ZBX_ENROLL_URL_LEN], *uuid7id = NULL;
	int				ret = FAIL;
	size_t				bek_len;

	if (FAIL == trapper_device_get_device_id(jp, ZBX_PROTO_VALUE_DEVICE_INIT, device_id, sizeof(device_id),
			error))
	{
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
		trapper_device_bridge_adapter_handle_error(&jp_result, ZBX_PROTO_VALUE_DEVICE_INIT, body_data, error,
				reason, domain);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_body, "result", &jp_result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing result in bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body_data));
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(ZBX_PROTO_VALUE_DEVICE_INIT,
				ZBX_DEVICE_BA_ERR_INVALID_RESPONSE));
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_result, "enrollment_token", met, sizeof(met), NULL) ||
			FAIL == zbx_json_brackets_by_name(&jp_result, "adapter_enc_key", &jp_bek) ||
			FAIL == zbx_json_value_by_name(&jp_result, "bridge_url", enroll_url,
			sizeof(enroll_url), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "missing enrollment_token/adapter_enc_key/bridge_url in bridge-adapter"
				" result: %s", ZBX_NULL2EMPTY_STR(body_data));
		*error = zbx_strdup(NULL, trapper_device_bridge_adapter_error(ZBX_PROTO_VALUE_DEVICE_INIT,
				ZBX_DEVICE_BA_ERR_INVALID_RESPONSE));
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
	int			ret;
	char			*error = NULL, *reason = NULL, *domain = NULL;
	zbx_user_t		user;
	zbx_uint64_t		target_userid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_user_init(&user);

	if (FAIL == trapper_device_authorize(sock, jp, config_comms, config_frontend_allowed_ip,
			ZBX_PROTO_VALUE_DEVICE_INIT, "initialize", "userid", 0, &user, &target_userid))
	{
		goto out;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED == (ret = trapper_device_init(jp, config_comms, config_bridge_adapter_url,
			config_bridge_adapter_connect_to, &error, &reason, &domain, &json)))
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
		trapper_device_send_response(sock, ret, ZBX_NULL2EMPTY_STR(error), reason, domain,
				config_comms->config_timeout, __func__, ZBX_PROTO_VALUE_DEVICE_INIT);

		zbx_free(error);
		zbx_free(reason);
		zbx_free(domain);
	}

	zbx_json_free(&json);
out:
	zbx_user_free(&user);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	trapper_device_offboard(const struct zbx_json_parse *jp, const zbx_config_comms_args_t *config_comms,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to, char **error,
		char **reason, char **domain, struct zbx_json *json)
{
#if !defined(HAVE_LIBCURL)
	ZBX_UNUSED(jp);
	ZBX_UNUSED(config_comms);
	ZBX_UNUSED(config_bridge_adapter_url);
	ZBX_UNUSED(config_bridge_adapter_connect_to);
	ZBX_UNUSED(error);
	ZBX_UNUSED(reason);
	ZBX_UNUSED(domain);
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result;
	char				*body_data = NULL, device_id[ZBX_UUID_LEN], *uuid7id = NULL;
	int				ret = FAIL;

	if (FAIL == trapper_device_get_device_id(jp, ZBX_PROTO_VALUE_DEVICE_OFFBOARD, device_id, sizeof(device_id),
			error))
	{
		goto out2;
	}

	zbx_json_init(&request, 512);

	zbx_json_addstring(&request, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "method", ZBX_PROTO_VALUE_DEVICE_DEACTIVATE, ZBX_JSON_TYPE_STRING);
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
		trapper_device_bridge_adapter_handle_error(&jp_result, ZBX_PROTO_VALUE_DEVICE_OFFBOARD, body_data,
				error, reason, domain);
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
	int			ret;
	zbx_user_t		user;
	zbx_uint64_t		target_userid;
	char			*error = NULL, *reason = NULL, *domain = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_user_init(&user);

	if (FAIL == trapper_device_authorize(sock, jp, config_comms, config_frontend_allowed_ip,
			ZBX_PROTO_VALUE_DEVICE_OFFBOARD, "offboard", "uuid", 1, &user, &target_userid))
	{
		goto out;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED == (ret = trapper_device_offboard(jp, config_comms, config_bridge_adapter_url,
			config_bridge_adapter_connect_to, &error, &reason, &domain, &json)))
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
		trapper_device_send_response(sock, ret, ZBX_NULL2EMPTY_STR(error), reason, domain,
				config_comms->config_timeout, __func__, ZBX_PROTO_VALUE_DEVICE_OFFBOARD);

		zbx_free(error);
		zbx_free(reason);
		zbx_free(domain);
	}

	zbx_json_free(&json);
out:
	zbx_user_free(&user);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
