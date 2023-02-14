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

#ifndef ZABBIX_DISCOVERY_H
#define ZABBIX_DISCOVERY_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

typedef struct
{
	zbx_uint64_t	dcheckid;
	unsigned short	port;
	char		dns[ZBX_INTERFACE_DNS_LEN_MAX];
	char		value[ZBX_MAX_DISCOVERED_VALUE_SIZE];
	int		status;
	time_t		itemtime;
}
zbx_dservice_t;

ZBX_PTR_VECTOR_DECL(discoverer_net_check, DC_DCHECK *)

typedef struct
{
	zbx_uint64_t				druleid;
	DC_DRULE				*drule;
	zbx_vector_discoverer_net_check_t	dchecks;
	char					*ip;
	zbx_vector_str_t			*ips;
	unsigned short				port;
	int					now;
	int					config_timeout;
}
zbx_discoverer_net_check_job_t;

void	zbx_discovery_update_host(zbx_db_dhost *dhost, int status, int now);
void	zbx_discovery_update_service(zbx_uint64_t druleid, zbx_uint64_t dcheckid, zbx_uint64_t unique_dcheckid,
		zbx_db_dhost *dhost, const char *ip, const char *dns, int port, int status, const char *value, int now);
void	zbx_discovery_dcheck_free(DC_DCHECK *dcheck);
void	zbx_discovery_drule_free(DC_DRULE *drule);
#endif
