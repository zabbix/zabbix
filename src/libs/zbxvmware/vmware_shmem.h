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
#ifndef ZABBIX_VMWARE_SHMEM_H
#define ZABBIX_VMWARE_SHMEM_H

#include "zbxvmware.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
#	include "vmware_internal.h"
#endif

#include "zbxshmem.h"
#include "zbxalgo.h"

zbx_shmem_info_t	*vmware_shmem_get_vmware_mem(void);
void	vmware_shmem_set_vmware_mem_NULL(void);
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#define VMWARE_SHMEM_VECTOR_CREATE_DECL(ref,type) void	vmware_shmem_vector_##type##_create_ext(ref);

VMWARE_SHMEM_VECTOR_CREATE_DECL(zbx_vector_str_t*, str)
VMWARE_SHMEM_VECTOR_CREATE_DECL(zbx_vector_vmware_entity_tags_ptr_t*, vmware_entity_tags_ptr)
VMWARE_SHMEM_VECTOR_CREATE_DECL(zbx_vector_custquery_param_t*, custquery_param)
VMWARE_SHMEM_VECTOR_CREATE_DECL(zbx_vector_vmware_tag_ptr_t*, vmware_tag_ptr)
VMWARE_SHMEM_VECTOR_CREATE_DECL(zbx_vector_vmware_perf_counter_ptr_t*, vmware_perf_counter_ptr)

void	vmware_shmem_perf_counter_free(zbx_vmware_perf_counter_t *counter);
void	vmware_perf_counters_add_new(zbx_vector_vmware_perf_counter_ptr_t *counters, zbx_uint64_t counterid,
		unsigned char state);
void	vmware_perf_counters_vector_ptr_create_ext(zbx_vmware_perf_entity_t *pentity);
void	vmware_shmem_diskextent_free(zbx_vmware_diskextent_t *diskextent);
void	vmware_shmem_free_datastore(zbx_vmware_datastore_t *datastore);
void	vmware_shmem_datacenter_free(zbx_vmware_datacenter_t *datacenter);
void	vmware_shmem_resourcepool_free(zbx_vmware_resourcepool_t *resourcepool);
void	vmware_shmem_dvswitch_free(zbx_vmware_dvswitch_t *dvswitch);
void	vmware_shmem_props_free(char **props, int props_num);
void	vmware_shmem_dev_free(zbx_vmware_dev_t *dev);
void	vmware_shmem_fs_free(zbx_vmware_fs_t *fs);
void	vmware_shmem_custom_attr_free(zbx_vmware_custom_attr_t *custom_attr);
void	vmware_shmem_vm_free(zbx_vmware_vm_t *vm);
void	vmware_shmem_dsname_free(zbx_vmware_dsname_t *dsname);
void	vmware_shmem_pnic_free(zbx_vmware_pnic_t *nic);
void	vmware_shmem_alarm_free(zbx_vmware_alarm_t *alarm);
void	vmware_shmem_diskinfo_free(zbx_vmware_diskinfo_t *di);
void	vmware_shmem_cluster_free(zbx_vmware_cluster_t *cluster);
void	vmware_shmem_event_free(zbx_vmware_event_t *event);
void	vmware_shmem_data_free(zbx_vmware_data_t *data);
void	vmware_shmem_eventlog_data_free(zbx_vmware_eventlog_data_t *data_eventlog);
void	vmware_shmem_cust_query_clean(zbx_vmware_cust_query_t *cust_query);
void	vmware_shared_tag_free(zbx_vmware_tag_t *value);
void	vmware_shared_entity_tags_free(zbx_vmware_entity_tags_t *value);
void	vmware_shmem_service_free(zbx_vmware_service_t *service);
void	vmware_shmem_evtseverity_copy(zbx_hashset_t *dst, const zbx_vector_vmware_key_value_t *src);
void	zbx_shmem_vmware_key_value_free(zbx_vmware_key_value_t *value);

int		vmware_strpool_compare_func(const void *d1, const void *d2);
zbx_hash_t	vmware_strpool_hash_func(const void *data);

zbx_vmware_event_t			*vmware_shmem_event_dup(const zbx_vmware_event_t *src);
zbx_vmware_diskextent_t			*vmware_shmem_diskextent_dup(const zbx_vmware_diskextent_t *src);
zbx_vmware_resourcepool_t		*vmware_shmem_resourcepool_dup(const zbx_vmware_resourcepool_t *src);
zbx_vmware_dvswitch_t			*vmware_shmem_dvswitch_dup(const zbx_vmware_dvswitch_t *src);
zbx_vmware_fs_t				*vmware_shmem_fs_dup(const zbx_vmware_fs_t *src);
zbx_vmware_custom_attr_t		*vmware_shmem_attr_dup(const zbx_vmware_custom_attr_t *src);
zbx_vmware_dev_t			*vmware_shmem_dev_dup(const zbx_vmware_dev_t *src);
zbx_vmware_vm_t				*vmware_shmem_vm_dup(const zbx_vmware_vm_t *src);
zbx_vmware_data_t			*vmware_shmem_data_dup(zbx_vmware_data_t *src);
zbx_vmware_eventlog_data_t		*vmware_shmem_eventlog_data_dup(zbx_vmware_eventlog_data_t *src);
zbx_vmware_service_t			*vmware_shmem_vmware_service_malloc(void);
void					vmware_shmem_service_hashset_create(zbx_vmware_service_t *service);
zbx_vector_custquery_param_t		*vmware_shmem_custquery_malloc(void);
zbx_vmware_job_t			*vmware_shmem_vmware_job_malloc(void);
void					vmware_shmem_vmware_job_free(zbx_vmware_job_t *job);
zbx_vmware_entity_tags_t		*vmware_shmem_entity_tags_malloc(void);
zbx_vmware_tag_t			*vmware_shmem_tag_malloc(void);
#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
int					vmware_shmem_init(zbx_uint64_t *config_vmware_cache_size, zbx_vmware_t **vmware,
							char **error);

#endif	/* ZABBIX_VMWARE_SHMEM_H */
