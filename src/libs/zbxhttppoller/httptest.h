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

#ifndef ZABBIX_HTTPTEST_H
#define ZABBIX_HTTPTEST_H

#include "zbxcommon.h"

int	process_httptests(int now, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, time_t *nextcheck);

#endif
