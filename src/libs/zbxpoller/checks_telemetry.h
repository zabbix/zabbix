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

#ifndef ZABBIX_CHECKS_TELEMETRY_H
#define ZABBIX_CHECKS_TELEMETRY_H

#include "zbxcacheconfig.h"

#ifdef HAVE_LIBCURL
typedef struct telemetry_query_conn_params_clickhouse
{
	const char	*url;
	const char	*http_proxy;
	int		timeout;
	int 		max_attempts;
	const char	*ssl_cert_file;
	const char	*ssl_key_file;
	const char	*ssl_key_password;
	unsigned char	verify_peer;
	unsigned char	verify_host;
	unsigned char	authtype;
	const char	*username;
	const char	*password;
	const char	*token;
	unsigned char	post_type;
	unsigned char	output_format;
}
telemetry_query_http_conn_params_t;
#endif

int	get_value_telemetry(const zbx_dc_item_t *item, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, AGENT_RESULT *result);

#endif
