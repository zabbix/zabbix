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

#include "async_httpagent.h"

#ifdef HAVE_LIBCURL
static void	httpagent_context_create(zbx_httpagent_context *httpagent_context)
{
	zbx_http_context_create(&httpagent_context->http_context);
}

void	zbx_async_check_httpagent_clean(zbx_httpagent_context *httpagent_context)
{
	zbx_free(httpagent_context->item_context.status_codes);
	zbx_free(httpagent_context->item_context.posts);
	zbx_http_context_destroy(&httpagent_context->http_context);
}

int	zbx_async_check_httpagent(zbx_dc_item_t *item, AGENT_RESULT *result, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, CURLM *curl_handle)
{
	char			*error = NULL;
	zbx_httpagent_context	*httpagent_context = zbx_malloc(NULL, sizeof(zbx_httpagent_context));
	CURLcode		err;
	CURLMcode		merr;

	httpagent_context_create(httpagent_context);

	httpagent_context->item_context.itemid = item->itemid;
	httpagent_context->item_context.hostid = item->host.hostid;
	httpagent_context->item_context.value_type = item->value_type;
	httpagent_context->item_context.flags = item->flags;
	httpagent_context->item_context.state = item->state;
	httpagent_context->item_context.posts = item->posts;
	item->posts = NULL;
	httpagent_context->item_context.status_codes = item->status_codes;
	item->status_codes = NULL;

	if (SUCCEED != zbx_http_request_prepare(&httpagent_context->http_context, item->request_method,
			item->url, item->query_fields, item->headers, httpagent_context->item_context.posts,
			item->retrieve_mode, item->http_proxy, item->follow_redirects, item->timeout, 1,
			item->ssl_cert_file, item->ssl_key_file, item->ssl_key_password, item->verify_peer,
			item->verify_host, item->authtype, item->username, item->password, NULL, item->post_type,
			item->output_format, config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
			config_ssl_key_location, &error))
	{
		SET_MSG_RESULT(result, error);

		goto fail;
	}

	if (CURLE_OK != (err = curl_easy_setopt(httpagent_context->http_context.easyhandle, CURLOPT_PRIVATE,
			httpagent_context)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set pointer to private data: %s",
				curl_easy_strerror(err)));

		goto fail;
	}

	if (CURLM_OK != (merr = curl_multi_add_handle(curl_handle, httpagent_context->http_context.easyhandle)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot add a standard curl handle to the multi stack: %s",
				curl_multi_strerror(merr)));

		goto fail;
	}

	/* httpagent_context is associated with this curl handle and will be freed when handle is freed */
	return SUCCEED;
fail:
	zbx_async_check_httpagent_clean(httpagent_context);
	zbx_free(httpagent_context);

	return NOTSUPPORTED;
}
#endif
