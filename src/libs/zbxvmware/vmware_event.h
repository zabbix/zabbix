/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
#ifndef ZABBIX_VMWARE_EVENT_H
#define ZABBIX_VMWARE_EVENT_H

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"
#include "vmware_internal.h"

void	vmware_event_free(zbx_vmware_event_t *event);
int	vmware_service_get_evt_severity(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_vmware_key_value_t *evt_severities, char **error);
int	zbx_vmware_service_eventlog_update(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout);

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_EVENT_H */
