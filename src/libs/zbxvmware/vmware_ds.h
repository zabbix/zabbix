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
#ifndef ZABBIX_VMWARE_DS_H
#define ZABBIX_VMWARE_DS_H

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"
#include "vmware_internal.h"

#include "zbxalgo.h"

void	vmware_dsname_free(zbx_vmware_dsname_t *dsname);
char	*vmware_datastores_diskname_search(const zbx_vector_vmware_datastore_ptr_t *dss, char *diskname);
int	vmware_hv_ds_access_update(zbx_vmware_service_t *service, CURL *easyhandle, const char *hv_uuid,
		const char *hv_id, const zbx_vector_str_t *hv_dss, zbx_vector_vmware_datastore_ptr_t *dss, char **error);
int	vmware_service_get_maxquerymetrics(CURL *easyhandle, zbx_vmware_service_t *service, int *max_qm, char **error);
int	vmware_service_refresh_datastore_info(CURL *easyhandle, const char *id, char **error);
zbx_vmware_datastore_t	*vmware_service_create_datastore(const zbx_vmware_service_t *service, CURL *easyhandle,
		const char *id, zbx_vector_cq_value_ptr_t *cq_values, zbx_vmware_alarms_data_t *alarms_data);
void	vmware_datastore_free(zbx_vmware_datastore_t *datastore);

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_DS_H */
