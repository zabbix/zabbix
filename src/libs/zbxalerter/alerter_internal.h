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

#ifndef ZABBIX_ALERTER_INTERNAL_H
#define ZABBIX_ALERTER_INTERNAL_H

#include "zbxtypes.h"

int	zbx_oauth_get(zbx_uint64_t mediatypeid, const char *mediatype_name, int timeout, int maxattempts,
		int expire_offset, const char *config_source_ip, const char *config_ssl_ca_location,
		char **oauthbearer, int *expires, char **error);

#endif
