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

#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxcomms.h"
#include "zbxtrapper.h"
#include "zbxcommshigh.h"

#if defined(HAVE_LIBCURL)
#	include "zbxcurl.h"
#	include "zbxhttp.h"
#endif

#define ZBX_UUID_LEN			32
#define ZBX_ERROR_CODE_LEN		32
#define ZBX_MESSAGE_LEN			256
#define ZBX_INFO_LEN			512

static int	trapper_device_init(const struct zbx_json_parse *jp, const char *config_adapter_url,
		const char *config_tls_ca_file, const char *config_tls_cert_file,
		const char *config_tls_key_file, struct zbx_json *json)
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
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	zbx_http_response_t		body = {0}, response_header = {0};
	CURL				*curl = NULL;
	CURLcode			err;
	CURLoption			opt;
	struct curl_slist		*headers = NULL;
	long				http_code = 0;
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result, jp_bek;
	char				*payload = NULL, *error = NULL, *bek_raw = NULL, errbuf[CURL_ERROR_SIZE],
					device_id[ZBX_UUID_LEN], server_id[ZBX_UUID_LEN], met[ZBX_ENROLL_TOKE_LEN],
					bek[ZBX_BRIDGE_ENCRYPTION_KEY_LEN], enroll_url[ZBX_ENROLL_URL_LEN],
					code[ZBX_ERROR_CODE_LEN], message[ZBX_MESSAGE_LEN], error_data[ZBX_MESSAGE_LEN];
	int				ret = FAIL;
	size_t				bek_len;

	if (FAIL == zbx_json_value_by_name(jp, "device_id", device_id, sizeof(device_id), NULL))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "missing device_id in device.init request");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(jp, "server_id", server_id, sizeof(server_id), NULL))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "missing server_id in device.init request");
		goto out;
	}

	zbx_json_init(&request, 512);

	zbx_json_addstring(&request, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "method", "device.init", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&request, "params");
	zbx_json_addstring(&request, "device_id", device_id, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "server_id", server_id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);
	zbx_json_adduint64(&request, "id", 1);
	zbx_json_close(&request);

	payload = zbx_strdup(NULL, request.buffer);

	if (NULL == (curl = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed initialize cURL library");
		goto out;
	}

	if (SUCCEED != zbx_http_prepare_callbacks(curl, &response_header, &body, zbx_curl_ignore_cb,
			zbx_curl_write_cb, errbuf, &error))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "cannot prepare HTTP callbacks: %s", ZBX_NULL2EMPTY_STR(error));
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
		zabbix_log(LOG_LEVEL_INFORMATION, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_https(curl, &error))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed zbx_curl_setopt_https: %s",
				ZBX_NULL2EMPTY_STR(error));
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_ssl_version(curl, &error))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed zbx_curl_setopt_ssl_version: %s",
				ZBX_NULL2EMPTY_STR(error));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CAINFO, config_tls_ca_file)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLCERT, config_tls_cert_file)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLKEY, config_tls_key_file)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(curl)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed connect to bridge-adapter: %s", curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &http_code)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed obtain bridge-adapter response code: %s",
				curl_easy_strerror(err));
		goto out;
	}

	if (FAIL == zbx_json_open(body.data, &jp_body))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "invalid bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		goto out;
	}

	if (http_code < 200 || http_code >= 300)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "bridge-adapter returned HTTP %ld: %s", http_code,
				ZBX_NULL2EMPTY_STR(body.data));

		zbx_json_addstring(json, "response", "failed", ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json, "info", "cannot process device.init request", ZBX_JSON_TYPE_STRING);
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

			zabbix_log(LOG_LEVEL_INFORMATION, "bridge-adapter returned %s: message: %s data %s", code,
					message, error_data);
			zbx_snprintf(info, sizeof(info), "code: %s, message: %s data: %s", code, message, error_data);

			zbx_json_addstring(json, "response", "failed", ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(json, "info", info, ZBX_JSON_TYPE_STRING);
		}

		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_body, "result", &jp_result))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "missing result in bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_result, "met", met, sizeof(met), NULL) ||
			FAIL == zbx_json_brackets_by_name(&jp_result, "bek", &jp_bek) ||
			FAIL == zbx_json_value_by_name(&jp_result, "enroll_url", enroll_url,
			sizeof(enroll_url), NULL))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "missing met/bek/enroll_url in bridge-adapter result: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		goto out;
	}

	bek_len = (size_t)(jp_bek.end - jp_bek.start + 1);
	bek_raw = (char *)zbx_malloc(NULL, bek_len + 1);
	memcpy(bek_raw, jp_bek.start, bek_len);
	bek_raw[bek_len] = '\0';

	zbx_json_addstring(json, "response", "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(json, "data");
	zbx_json_addstring(json, "met", met, ZBX_JSON_TYPE_STRING);
	zbx_json_addraw(json, "bek", bek_raw);
	zbx_json_addstring(json, "enroll_url", enroll_url, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);

	ret = SUCCEED;
out:
	curl_slist_free_all(headers);
	curl_easy_cleanup(curl);
	zbx_json_free(&request);
	zbx_free(error);
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

void	zbx_trapper_device_init(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const zbx_config_tls_t *config_tls,
		const char *config_frontend_allowed_ip, const char *config_adapter_url, const char *config_tls_ca_file,
		const char *config_tls_cert_file, const char *config_tls_key_file)
{
	struct zbx_json	json;
	int		ret;
	char		*error = NULL;
	zbx_user_t	user;

	zabbix_log(LOG_LEVEL_INFORMATION, "In %s()", __func__);

	if (SUCCEED != zbx_check_frontend_conn_accept(sock, config_tls, config_frontend_allowed_ip))
		goto out;

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL) || USER_TYPE_SUPER_ADMIN > user.type)
	{
		zbx_send_response(sock, ret, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	zbx_json_init(&json, 1024);

	if (SUCCEED == (ret = trapper_device_init(jp, config_adapter_url, config_tls_ca_file, config_tls_cert_file,
			config_tls_key_file, &json)))
	{
		if (SUCCEED != zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size,
				config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%s() failed sending device.init response", __func__);
		}
	}
	else
	{
		if (SUCCEED != zbx_send_response(sock, ret, json.buffer, config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%s() failed sending device.init error response",
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
		struct zbx_json *json)
{
#if !defined(HAVE_LIBCURL)
	ZBX_UNUSED(jp);
	ZBX_UNUSED(config_adapter_url);
	ZBX_UNUSED(config_tls_ca_file);
	ZBX_UNUSED(config_tls_cert_file);
	ZBX_UNUSED(config_tls_key_file);
	ZBX_UNUSED(json);

	zabbix_log(LOG_LEVEL_WARNING, "application compiled without cURL library");

	return FAIL;
#else
	zbx_http_response_t		body = {0}, response_header = {0};
	CURL				*curl = NULL;
	CURLcode			err;
	CURLoption			opt;
	struct curl_slist		*headers = NULL;
	long				http_code = 0;
	struct zbx_json			request;
	struct zbx_json_parse		jp_body, jp_result;
	char				*payload = NULL, *error = NULL, errbuf[CURL_ERROR_SIZE],
					device_id[ZBX_UUID_LEN], server_id[ZBX_UUID_LEN], code[ZBX_ERROR_CODE_LEN],
					message[ZBX_MESSAGE_LEN], error_data[ZBX_MESSAGE_LEN];
	int				ret = FAIL;

	if (FAIL == zbx_json_value_by_name(jp, "device_id", device_id, sizeof(device_id), NULL))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "missing device_id in device.offboard request");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(jp, "server_id", server_id, sizeof(server_id), NULL))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "missing server_id in device.init request");
		goto out;
	}

	zbx_json_init(&request, 512);

	zbx_json_addstring(&request, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "method", "device.offboard", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&request, "params");
	zbx_json_addstring(&request, "device_id", device_id, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&request, "server_id", server_id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&request);
	zbx_json_adduint64(&request, "id", 1);
	zbx_json_close(&request);

	payload = zbx_strdup(NULL, request.buffer);

	if (NULL == (curl = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed initialize cURL library");
		goto out;
	}

	if (SUCCEED != zbx_http_prepare_callbacks(curl, &response_header, &body, zbx_curl_ignore_cb,
			zbx_curl_write_cb, errbuf, &error))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "cannot prepare HTTP callbacks: %s",
				ZBX_NULL2EMPTY_STR(error));
		goto out;
	}

	headers = curl_slist_append(headers, "Content-Type: application/json");

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_URL, config_adapter_url)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_HTTPHEADER, headers)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDS, payload)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDSIZE, strlen(payload))) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CONNECTTIMEOUT_MS, 2000L)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_TIMEOUT_MS, 5000L)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "cannot set cURL option %d: %s", (int)opt,
				curl_easy_strerror(err));
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_https(curl, &error))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed zbx_curl_setopt_https: %s",
				ZBX_NULL2EMPTY_STR(error));
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_ssl_version(curl, &error))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed zbx_curl_setopt_ssl_version: %s",
				ZBX_NULL2EMPTY_STR(error));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_CAINFO, config_tls_ca_file)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLCERT, config_tls_cert_file)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_SSLKEY, config_tls_key_file)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed set cURL option %d: %s", (int)opt,
				curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(curl)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed connect to bridge-adapter: %s",
				curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &http_code)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "failed obtain bridge-adapter response code: %s",
				curl_easy_strerror(err));
		goto out;
	}

	if (FAIL == zbx_json_open(body.data, &jp_body))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "invalid bridge-adapter response body: %s",
				ZBX_NULL2EMPTY_STR(body.data));
		goto out;
	}

	if (http_code < 200 || http_code >= 300)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "bridge-adapter returned HTTP %ld: %s", http_code,
				ZBX_NULL2EMPTY_STR(body.data));
		zbx_json_addstring(json, "response", "failed", ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json, "info", "cannot process device.offboard request", ZBX_JSON_TYPE_STRING);
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

			zabbix_log(LOG_LEVEL_INFORMATION, "bridge-adapter returned %s: message: %s data %s", code,
					message, error_data);
			zbx_snprintf(info, sizeof(info), "code: %s, message: %s data: %s", code, message, error_data);

			zbx_json_addstring(json, "response", "failed", ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(json, "info", info, ZBX_JSON_TYPE_STRING);
		}

		goto out;
	}

	zbx_json_addstring(json, "response", "success", ZBX_JSON_TYPE_STRING);

	ret = SUCCEED;
out:
	curl_slist_free_all(headers);
	curl_easy_cleanup(curl);
	zbx_json_free(&request);
	zbx_free(payload);
	zbx_free(error);
	zbx_free(body.data);
	zbx_free(response_header.data);

	return ret;
#endif
}

void	zbx_trapper_device_offboard(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const zbx_config_tls_t *config_tls,
		const char *config_frontend_allowed_ip, const char *config_adapter_url, const char *config_tls_ca_file,
		const char *config_tls_cert_file, const char *config_tls_key_file)
{
	struct zbx_json	json;
	int		ret;
	zbx_user_t	user;

	zabbix_log(LOG_LEVEL_INFORMATION, "In %s()", __func__);

	if (SUCCEED != zbx_check_frontend_conn_accept(sock, config_tls, config_frontend_allowed_ip))
		goto out;

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL) || USER_TYPE_SUPER_ADMIN > user.type)
	{
		zbx_send_response(sock, ret, "Permission denied.", config_comms->config_timeout);
		goto out;
	}

	zbx_json_init(&json, 1024);

	if (SUCCEED == (ret = trapper_device_offboard(jp, config_adapter_url, config_tls_ca_file, config_tls_cert_file,
			config_tls_key_file, &json)))
	{
		if (SUCCEED != zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size,
				config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%s() failed sending device.offboard response",
					__func__);
		}
	}
	else
	{
		if (SUCCEED != zbx_send_response(sock, FAIL, json.buffer,
				config_comms->config_timeout))
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%s() failed sending device.offboard error response",
					__func__);
		}
	}

	zbx_json_free(&json);
out:
	zabbix_log(LOG_LEVEL_INFORMATION, "End of %s()", __func__);
}
