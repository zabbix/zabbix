/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "common.h"
#include "log.h"
#include "cfg.h"
#include "zbxjson.h"
#include "trapper_request.h"
#include "zbxreport.h"

static void	trapper_process_report_test(zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	char	*error = NULL;
	int	ret;

	ret = zbx_report_test(jp, &error);
	zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);

	zbx_free(error);
}

int	trapper_process_request(const char *request, zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	if (0 == strcmp(request, ZBX_PROTO_VALUE_REPORT_TEST))
	{
		trapper_process_report_test(sock, jp);
		return SUCCEED;
	}

	return FAIL;
}
