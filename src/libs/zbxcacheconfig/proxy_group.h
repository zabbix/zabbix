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

#ifndef ZABBIX_PROXY_GROUP_H
#define ZABBIX_PROXY_GROUP_H

#include "dbconfig.h"
#include "zbxcomms.h"
#include "zbxtypes.h"

void	dc_sync_proxy_group(zbx_dbsync_t *sync, zbx_uint64_t revision);
void	dc_sync_host_proxy(zbx_dbsync_t *sync, zbx_uint64_t revision);
void	dc_update_host_proxy(const char *host_old, const char *host_new);
int	dc_get_host_redirect(const char *host, const zbx_tls_conn_attr_t *attr, zbx_comms_redirect_t *redirect);
void	dc_update_proxy_failover_delay(void);

#endif
