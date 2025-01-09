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
