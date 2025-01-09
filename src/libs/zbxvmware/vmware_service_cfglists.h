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
#ifndef ZABBIX_VMWARE_SERVICE_CFGLISTS_H
#define ZABBIX_VMWARE_SERVICE_CFGLISTS_H

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"
#include "vmware_internal.h"

#include "zbxalgo.h"

int	vmware_service_get_hv_ds_dc_dvs_list(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vmware_alarms_data_t *alarms_data, zbx_vector_str_t *hvs, zbx_vector_str_t *dss,
		zbx_vector_vmware_datacenter_ptr_t *datacenters, zbx_vector_vmware_dvswitch_ptr_t *dvswitches,
		zbx_vector_str_t *vc_alarm_ids, char **error);

int	vmware_service_get_diskextents_list(xmlDoc *doc, zbx_vector_vmware_diskextent_ptr_t *diskextents);

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_SERVICE_CFGLISTS_H */
