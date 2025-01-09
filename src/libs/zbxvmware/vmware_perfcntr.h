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
#ifndef ZABBIX_VMWARE_PERFCNTR_H
#define ZABBIX_VMWARE_PERFCNTR_H

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"

#include "zbxalgo.h"

/* performance counter value for a specific instance */
typedef struct
{
	zbx_uint64_t	counterid;
	char		*instance;
	zbx_uint64_t	value;
}
zbx_vmware_perf_value_t;

ZBX_PTR_VECTOR_DECL(vmware_perf_value_ptr, zbx_vmware_perf_value_t *)

/* performance data for a performance collector entity */
typedef struct
{
	/* entity type: HostSystem, datastore or VirtualMachine */
	char			*type;

	/* entity id */
	char			*id;

	/* performance counter values */
	zbx_vector_vmware_perf_value_ptr_t	values;

	/* error information */
	char			*error;
}
zbx_vmware_perf_data_t;

ZBX_PTR_VECTOR_DECL(vmware_perf_data_ptr, zbx_vmware_perf_data_t *)

/* VMware performance counters available per object (information cache) */
ZBX_VECTOR_DECL(uint16, uint16_t)

typedef struct
{
	char			*type;
	char			*id;
	zbx_vector_uint16_t	list;
}
zbx_vmware_perf_available_t;

ZBX_PTR_VECTOR_DECL(perf_available_ptr, zbx_vmware_perf_available_t *)

/* mapping of performance counter group/key[rollup type] to its id (net/transmitted[average] -> <id>) */
typedef struct
{
	char		*path;
	zbx_uint64_t	id;
	int		unit;
}
zbx_vmware_counter_t;

ZBX_PTR_VECTOR_DECL(vmware_counter_ptr, zbx_vmware_counter_t *)

zbx_hash_t	vmware_counter_hash_func(const void *data);
int	vmware_counter_compare_func(const void *d1, const void *d2);
zbx_hash_t	vmware_perf_entity_hash_func(const void *data);
int	vmware_perf_entity_compare_func(const void *d1, const void *d2);
void	vmware_counters_shared_copy(zbx_hashset_t *dst, const zbx_vector_vmware_counter_ptr_t *src);
void	vmware_vector_str_uint64_pair_shared_clean(zbx_vector_str_uint64_pair_t *pairs);
void	vmware_shared_perf_entity_clean(zbx_vmware_perf_entity_t *entity);
void	vmware_counter_shared_clean(zbx_vmware_counter_t *counter);
void	vmware_counter_free(zbx_vmware_counter_t *counter);
int	vmware_service_get_perf_counters(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_vmware_counter_ptr_t *counters, char **error);
void	vmware_service_update_perf_entities(zbx_vmware_service_t *service);

int	zbx_vmware_service_update_perf(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout);
#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_PERFCNTR_H */


