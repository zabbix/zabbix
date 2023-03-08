/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "checks_http.h"
#include "zbxhttp.h"
#include "zbxcacheconfig.h"

#ifdef HAVE_LIBCURL
int	get_value_http(const DC_ITEM *item, AGENT_RESULT *result)
{
	char	*out = NULL, *error = NULL;
	int	ret;

	if (SUCCEED == (ret = zbx_http_request(item->request_method, item->url, item->query_fields, item->headers,
			item->posts, item->retrieve_mode, item->http_proxy, item->follow_redirects, item->timeout, 1,
			item->ssl_cert_file, item->ssl_key_file, item->ssl_key_password, item->verify_peer,
			item->verify_host, item->authtype, item->username, item->password, NULL, item->post_type,
			item->status_codes, item->output_format, &out, &error)))
	{
		SET_TEXT_RESULT(result, out);
		out = NULL;
	}
	else
	{
		SET_MSG_RESULT(result, error);
		error = NULL;
	}

	zbx_free(error);
	zbx_free(out);

	return ret;
}
#endif
