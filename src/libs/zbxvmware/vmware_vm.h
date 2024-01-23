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
#ifndef ZABBIX_VMWARE_VM_H
#define ZABBIX_VMWARE_VM_H

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"
#include "vmware_internal.h"

void	vmware_vm_shared_free(zbx_vmware_vm_t *vm);
void	vmware_vm_free(zbx_vmware_vm_t *vm);
zbx_vmware_vm_t	*vmware_service_create_vm(zbx_vmware_service_t *service, CURL *easyhandle,
		const char *id, zbx_vector_vmware_resourcepool_ptr_t *rpools, zbx_vector_cq_value_ptr_t *cq_values,
		zbx_vmware_alarms_data_t *alarms_data, char **error);

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_VM_H */
