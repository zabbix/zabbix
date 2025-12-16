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

#include "zbxasyncpoller.h"
#include "zbxcommon.h"

#ifdef HAVE_LIBCURL

#include "async_http.h"
#include "discoverer_int.h"
#include "zbxsysinc.h"
#include "zbxcurl.h"
#include "zbxip.h"
#include "zbx_discoverer_constants.h"

static int	process_task_http(short event, void *data, int *fd, zbx_vector_address_t *addresses,
		const char *reverse_dns, char *dnserr, struct event *timeout_event)
{
	int					 task_ret = ZBX_ASYNC_TASK_STOP;
	zbx_discovery_async_http_context_t	*http_context = (zbx_discovery_async_http_context_t *)data;

	ZBX_UNUSED(fd);
	ZBX_UNUSED(dnserr);
	ZBX_UNUSED(timeout_event);
	ZBX_UNUSED(addresses);

	if (ZBX_ASYNC_HTTP_STEP_RDNS == http_context->step)
	{
		if (NULL != reverse_dns)
			http_context->reverse_dns = zbx_strdup(NULL, reverse_dns);

		goto stop;
	}

	if (0 != (event & EV_TIMEOUT))
		goto stop;

	if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == http_context->resolve_reverse_dns)
	{
		task_ret = ZBX_ASYNC_TASK_RESOLVE_REVERSE;
		http_context->step = ZBX_ASYNC_HTTP_STEP_RDNS;
	}
stop:
	return task_ret;
}

void	process_http_response(CURL *easy_handle, CURLcode err, void *arg)
{
	zbx_discovery_async_http_context_t	*http_context;
	discovery_poller_config_t		*poller_config = (discovery_poller_config_t *)arg;
	CURLcode				err_info;

	if (CURLE_OK != (err_info = curl_easy_getinfo(easy_handle, CURLINFO_PRIVATE, &http_context)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot get pointer to private data: %s", curl_easy_strerror(err_info));
		THIS_SHOULD_NEVER_HAPPEN;
	}

	if (CURLE_OK != err)
	{
		http_context->res = FAIL;
		process_result_http(http_context);
	}
	else
	{
		http_context->res = SUCCEED;
		zbx_async_poller_add_task(poller_config->base, NULL, poller_config->dnsbase,
				http_context->async_result->dresult->ip, http_context, http_context->config_timeout,
				process_task_http, process_result_http);
	}
}

void	zbx_discovery_async_http_context_destroy(zbx_discovery_async_http_context_t *http_ctx)
{
	curl_easy_cleanup(http_ctx->easyhandle);
	zbx_free(http_ctx->reverse_dns);
	zbx_free(http_ctx);
}

int	zbx_discovery_async_check_http(CURLM *curl_mhandle, const char *config_source_ip, int timeout, const char *ip,
		unsigned short port, unsigned char type, zbx_discovery_async_http_context_t *http_ctx, char **error)
{
	CURLcode	err;
	CURLMcode	merr;
	char		url[MAX_STRING_LEN];
	CURLoption	opt;

	if (NULL == (http_ctx->easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(*error, "cannot initialize cURL library");
		return FAIL;
	}

	zbx_snprintf(url, sizeof(url), SUCCEED == zbx_is_ip6(ip) ? "%s[%s]" : "%s%s",
			SVC_HTTPS == type ? "https://" : "http://", ip);

	if (CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_USERAGENT,
			"Zabbix " ZABBIX_VERSION)) ||
			CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_URL, url)) ||
			CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_PORT, (long)port)) ||
			CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_NOBODY, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
			(NULL != config_source_ip && CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle,
					opt = CURLOPT_INTERFACE, config_source_ip))) ||
			CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_TIMEOUT,
			(long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, opt = CURLOPT_ACCEPT_ENCODING,
			"")))
	{
		*error = zbx_dsprintf(*error, "cannot set cURL option [%d]: %s", (int)opt, curl_easy_strerror(err));
		goto fail;
	}

	if (SUCCEED != zbx_curl_setopt_https(http_ctx->easyhandle, error))
		goto fail;

	if (CURLE_OK != (err = curl_easy_setopt(http_ctx->easyhandle, CURLOPT_PRIVATE, http_ctx)))
	{
		*error = zbx_dsprintf(*error, "cannot set pointer to private data: %s", curl_easy_strerror(err));
		goto fail;
	}

	if (CURLM_OK != (merr = curl_multi_add_handle(curl_mhandle, http_ctx->easyhandle)))
	{
		*error = zbx_dsprintf(*error, "cannot add a standard curl handle to the multi stack: %s",
				curl_multi_strerror(merr));
		goto fail;
	}

	return SUCCEED;
fail:
	curl_easy_cleanup(http_ctx->easyhandle);
	return FAIL;
}
#endif
