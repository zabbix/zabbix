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

#include "checks_http.h"

#ifdef HAVE_LIBCURL

#include "zbxcacheconfig.h"
#include "zbxhttp.h"

int	get_value_http(const zbx_dc_item_t *item, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, AGENT_RESULT *result)
{
	char			*out = NULL, *error = NULL;
	int			ret;
	long			response_code;
	zbx_http_context_t	context;

	zbx_http_context_create(&context);

	if (SUCCEED == zbx_http_request_prepare(&context, item->request_method, item->url,
			item->query_fields, item->headers, item->posts, item->retrieve_mode, item->http_proxy,
			item->follow_redirects, item->timeout, 1, item->ssl_cert_file, item->ssl_key_file,
			item->ssl_key_password, item->verify_peer, item->verify_host, item->authtype, item->username,
			item->password, NULL, item->post_type, item->output_format, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &error))
	{
		CURLcode	err = zbx_http_request_sync_perform(context.easyhandle, &context, 0,
				ZBX_HTTP_IGNORE_RESPONSE_CODE);

		if (SUCCEED == zbx_http_handle_response(context.easyhandle, &context, err, &response_code, &out, &error)
				&& SUCCEED == zbx_handle_response_code(item->status_codes, response_code, out, &error))
		{

			SET_TEXT_RESULT(result, out);
			out = NULL;
			ret = SUCCEED;
		}
		else
		{
			SET_MSG_RESULT(result, error);
			error = NULL;
			ret = NOTSUPPORTED;
		}
	}
	else
	{
		SET_MSG_RESULT(result, error);
		error = NULL;
		ret = NOTSUPPORTED;
	}

	zbx_free(error);
	zbx_free(out);

	zbx_http_context_destroy(&context);

	return ret;
}

#endif
