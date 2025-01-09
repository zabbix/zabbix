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

#include "vmware_shmem.h"
#include "zbxshmem.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
#	include "vmware_hv.h"
#	include "vmware_perfcntr.h"
#endif

#define VMWARE_VECTOR_CREATE(ref, type)	zbx_vector_##type##_create_ext(ref, __vm_shmem_malloc_func, \
		__vm_shmem_realloc_func, __vm_shmem_free_func)

static zbx_shmem_info_t	*vmware_mem = NULL;

zbx_shmem_info_t	*vmware_shmem_get_vmware_mem(void)
{
	return vmware_mem;
}

void	vmware_shmem_set_vmware_mem_NULL(void)
{
	vmware_mem = NULL;
}
ZBX_SHMEM_FUNC_IMPL(__vm, vmware_mem)

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#define VMWARE_SHMEM_VECTOR_CREATE_IMPL(ref, type) void	vmware_shmem_vector_##type##_create_ext(ref x)	\
{													\
	VMWARE_VECTOR_CREATE(x, type);					\
}

VMWARE_SHMEM_VECTOR_CREATE_IMPL(zbx_vector_str_t*, str)
VMWARE_SHMEM_VECTOR_CREATE_IMPL(zbx_vector_vmware_entity_tags_ptr_t*, vmware_entity_tags_ptr)
VMWARE_SHMEM_VECTOR_CREATE_IMPL(zbx_vector_custquery_param_t*, custquery_param)
VMWARE_SHMEM_VECTOR_CREATE_IMPL(zbx_vector_vmware_tag_ptr_t*, vmware_tag_ptr)
VMWARE_SHMEM_VECTOR_CREATE_IMPL(zbx_vector_vmware_perf_counter_ptr_t*, vmware_perf_counter_ptr)

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store performance counter     *
 *          data                                                              *
 *                                                                            *
 * Parameters: counter - [IN] performance counter data                        *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_perf_counter_free(zbx_vmware_perf_counter_t *counter)
{
	vmware_vector_str_uint64_pair_shared_clean(&counter->values);
	zbx_vector_str_uint64_pair_destroy(&counter->values);
	vmware_shared_strfree(counter->query_instance);
	__vm_shmem_free_func(counter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates new performance counter object in shared memory and       *
 *          adds it to specified vector                                       *
 *                                                                            *
 * Parameters: counters  - [IN/OUT] vector, created performance counter       *
 *                                  object should be added to                 *
 *             counterid - [IN] performance counter id                        *
 *             state     - [IN] performance counter first state               *
 *                                                                            *
 ******************************************************************************/
void	vmware_perf_counters_add_new(zbx_vector_vmware_perf_counter_ptr_t *counters, zbx_uint64_t counterid,
		unsigned char state)
{
	zbx_vmware_perf_counter_t	*counter;

	counter = (zbx_vmware_perf_counter_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_perf_counter_t));

	counter->counterid = counterid;
	counter->state = state;
	counter->last_used = 0;
	counter->query_instance = NULL;

	VMWARE_VECTOR_CREATE(&counter->values, str_uint64_pair);

	zbx_vector_vmware_perf_counter_ptr_append(counters, counter);
}

void	vmware_perf_counters_vector_ptr_create_ext(zbx_vmware_perf_entity_t *pentity)
{
	VMWARE_VECTOR_CREATE(&pentity->counters, vmware_perf_counter_ptr);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store diskextent data         *
 *                                                                            *
 * Parameters: diskextent - [IN]                                              *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_diskextent_free(zbx_vmware_diskextent_t *diskextent)
{
	vmware_shared_strfree(diskextent->diskname);
	__vm_shmem_free_func(diskextent);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store datastore data          *
 *                                                                            *
 * Parameters: datastore - [IN]                                               *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_free_datastore(zbx_vmware_datastore_t *datastore)
{
	__vm_shmem_free_func(datastore);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store datacenter data         *
 *                                                                            *
 * Parameters: datacenter - [IN]                                              *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_datacenter_free(zbx_vmware_datacenter_t *datacenter)
{
	vmware_shared_strfree(datacenter->name);
	vmware_shared_strfree(datacenter->id);
	zbx_vector_str_clear_ext(&datacenter->alarm_ids, vmware_shared_strfree);
	zbx_vector_str_destroy(&datacenter->alarm_ids);

	__vm_shmem_free_func(datacenter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store resourcepool data       *
 *                                                                            *
 * Parameters: resourcepool - [IN]                                            *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_resourcepool_free(zbx_vmware_resourcepool_t *resourcepool)
{
	vmware_shared_strfree(resourcepool->id);
	vmware_shared_strfree(resourcepool->parentid);
	vmware_shared_strfree(resourcepool->path);

	__vm_shmem_free_func(resourcepool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store dvswitch data           *
 *                                                                            *
 * Parameters: dvswitch - [IN]                                                *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_dvswitch_free(zbx_vmware_dvswitch_t *dvswitch)
{
	vmware_shared_strfree(dvswitch->uuid);
	vmware_shared_strfree(dvswitch->id);
	vmware_shared_strfree(dvswitch->name);

	__vm_shmem_free_func(dvswitch);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store properties list         *
 *                                                                            *
 * Parameters: props     - [IN] properties list                               *
 *             props_num - [IN] number of properties in list                  *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_props_free(char **props, int props_num)
{
	if (NULL == props)
		return;

	for (int i = 0; i < props_num; i++)
	{
		if (NULL != props[i])
			vmware_shared_strfree(props[i]);
	}

	__vm_shmem_free_func(props);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vm device data          *
 *                                                                            *
 * Parameters: dev - [IN] vm device                                           *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_dev_free(zbx_vmware_dev_t *dev)
{
	if (NULL != dev->instance)
		vmware_shared_strfree(dev->instance);

	if (NULL != dev->label)
		vmware_shared_strfree(dev->label);

	vmware_shmem_props_free(dev->props, ZBX_VMWARE_DEV_PROPS_NUM);

	__vm_shmem_free_func(dev);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store file system object      *
 *                                                                            *
 * Parameters: fs - [IN] file system                                          *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_fs_free(zbx_vmware_fs_t *fs)
{
	if (NULL != fs->path)
		vmware_shared_strfree(fs->path);

	__vm_shmem_free_func(fs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store attributes object       *
 *                                                                            *
 * Parameters: custom_attr - [IN] custom attributes object                    *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_custom_attr_free(zbx_vmware_custom_attr_t *custom_attr)
{
	if (NULL != custom_attr->name)
		vmware_shared_strfree(custom_attr->name);

	if (NULL != custom_attr->value)
		vmware_shared_strfree(custom_attr->value);

	__vm_shmem_free_func(custom_attr);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store virtual machine         *
 *                                                                            *
 * Parameters: vm - [IN] virtual machine                                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_vm_free(zbx_vmware_vm_t *vm)
{
	__vm_shmem_free_func(vm);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store datastore names data    *
 *                                                                            *
 * Parameters: dsname - [IN] datastore name                                   *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_dsname_free(zbx_vmware_dsname_t *dsname)
{
	vmware_shared_strfree(dsname->name);
	vmware_shared_strfree(dsname->uuid);
	zbx_vector_vmware_hvdisk_destroy(&dsname->hvdisks);

	__vm_shmem_free_func(dsname);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store physical NIC data       *
 *                                                                            *
 * Parameters: nic - [IN] physical NIC of hv                                  *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_pnic_free(zbx_vmware_pnic_t *nic)
{
	vmware_shared_strfree(nic->name);
	vmware_shared_strfree(nic->driver);
	vmware_shared_strfree(nic->mac);

	__vm_shmem_free_func(nic);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store alarm data              *
 *                                                                            *
 * Parameters: alarm - [IN] alarm object                                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_alarm_free(zbx_vmware_alarm_t *alarm)
{
	vmware_shared_strfree(alarm->key);
	vmware_shared_strfree(alarm->name);
	vmware_shared_strfree(alarm->system_name);
	vmware_shared_strfree(alarm->description);
	vmware_shared_strfree(alarm->overall_status);
	vmware_shared_strfree(alarm->time);

	__vm_shmem_free_func(alarm);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store disk info data          *
 *                                                                            *
 * Parameters: di - [IN] disk info object                                     *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_diskinfo_free(zbx_vmware_diskinfo_t *di)
{
	vmware_shared_strfree(di->diskname);
	vmware_shared_strfree(di->ds_uuid);
	vmware_shared_strfree(di->operational_state);
	vmware_shared_strfree(di->lun_type);
	vmware_shared_strfree(di->model);
	vmware_shared_strfree(di->vendor);
	vmware_shared_strfree(di->revision);
	vmware_shared_strfree(di->serial_number);

	if (NULL != di->vsan)
	{
		vmware_shared_strfree(di->vsan->ssd);
		vmware_shared_strfree(di->vsan->local_disk);
		__vm_shmem_free_func(di->vsan);
	}

	__vm_shmem_free_func(di);
}

void	vmware_shmem_cluster_free(zbx_vmware_cluster_t *cluster)
{
	__vm_shmem_free_func(cluster);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware event            *
 *                                                                            *
 * Parameters: event - [IN] vmware event                                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_event_free(zbx_vmware_event_t *event)
{
	if (NULL != event->message)
		vmware_shared_strfree(event->message);

	__vm_shmem_free_func(event);
}

void	vmware_shmem_data_free(zbx_vmware_data_t *data)
{
	__vm_shmem_free_func(data);
}

void	vmware_shmem_eventlog_data_free(zbx_vmware_eventlog_data_t *evt_data)
{
	__vm_shmem_free_func(evt_data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans resources allocated by vmware custom query in vmware       *
 *                                                                            *
 * Parameters: cust_query - [IN] entity to free                               *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_cust_query_clean(zbx_vmware_cust_query_t *cust_query)
{
	__vm_shmem_free_func(cust_query->query_params);
}

void	vmware_shared_tag_free(zbx_vmware_tag_t *value)
{
	vmware_shared_strfree(value->name);
	vmware_shared_strfree(value->category);
	vmware_shared_strfree(value->description);
	__vm_shmem_free_func(value);
}

void	vmware_shared_entity_tags_free(zbx_vmware_entity_tags_t *value)
{
	zbx_vector_vmware_tag_ptr_clear_ext(&value->tags, vmware_shared_tag_free);
	zbx_vector_vmware_tag_ptr_destroy(&value->tags);
	vmware_shared_strfree(value->uuid);
	vmware_shared_strfree(value->error);
	__vm_shmem_free_func(value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service          *
 *                                                                            *
 * Parameters: data - [IN] vmware service data                                *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_service_free(zbx_vmware_service_t *service)
{
	__vm_shmem_free_func(service);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware event object into shared memory                     *
 *                                                                            *
 * Parameters: src - [IN] vmware event object                                 *
 *                                                                            *
 * Return value: copied vmware event object                                   *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_event_t	*vmware_shmem_event_dup(const zbx_vmware_event_t *src)
{
	zbx_vmware_event_t	*event;

	event = (zbx_vmware_event_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_event_t));
	event->key = src->key;
	event->message = vmware_shared_strdup(src->message);
	event->timestamp = src->timestamp;

	return event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor diskextent object into shared memory     *
 *                                                                            *
 * Parameters: src - [IN] vmware diskextent object                            *
 *                                                                            *
 * Return value: duplicated vmware diskextent object                          *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_diskextent_t	*vmware_shmem_diskextent_dup(const zbx_vmware_diskextent_t *src)
{
	zbx_vmware_diskextent_t	*diskextent;

	diskextent = (zbx_vmware_diskextent_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_diskextent_t));
	diskextent->partitionid = src->partitionid;
	diskextent->diskname = vmware_shared_strdup(src->diskname);

	return diskextent;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware resourcepool object into shared memory              *
 *                                                                            *
 * Parameters: src - [IN] vmware resourcepool object                          *
 *                                                                            *
 * Return value: duplicated vmware resourcepool object                        *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_resourcepool_t	*vmware_shmem_resourcepool_dup(const zbx_vmware_resourcepool_t *src)
{
	zbx_vmware_resourcepool_t	*resourcepool;

	resourcepool = (zbx_vmware_resourcepool_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_resourcepool_t));
	resourcepool->id = vmware_shared_strdup(src->id);
	resourcepool->parentid = vmware_shared_strdup(src->parentid);
	resourcepool->path = vmware_shared_strdup(src->path);
	resourcepool->vm_num = src->vm_num;

	return resourcepool;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware dvswitch object into shared memory                  *
 *                                                                            *
 * Parameters: src - [IN] vmware dvswitch object                              *
 *                                                                            *
 * Return value: duplicated vmware dvswitch object                            *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_dvswitch_t	*vmware_shmem_dvswitch_dup(const zbx_vmware_dvswitch_t *src)
{
	zbx_vmware_dvswitch_t	*dvs;

	dvs = (zbx_vmware_dvswitch_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_dvswitch_t));
	dvs->uuid = vmware_shared_strdup(src->uuid);
	dvs->id = vmware_shared_strdup(src->id);
	dvs->name = vmware_shared_strdup(src->name);

	return dvs;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware virtual machine file system object into shared      *
 *          memory                                                            *
 *                                                                            *
 * Parameters: src - [IN] vmware file system object                           *
 *                                                                            *
 * Return value: duplicated vmware file system object                         *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_fs_t	*vmware_shmem_fs_dup(const zbx_vmware_fs_t *src)
{
	zbx_vmware_fs_t	*fs;

	fs = (zbx_vmware_fs_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_fs_t));
	fs->path = vmware_shared_strdup(src->path);
	fs->capacity = src->capacity;
	fs->free_space = src->free_space;

	return fs;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware virtual machine custom attribute object into shared *
 *          memory                                                            *
 *                                                                            *
 * Parameters: src - [IN] vmware custom attribute object                      *
 *                                                                            *
 * Return value: duplicated vmware custom attribute object                    *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_custom_attr_t	*vmware_shmem_attr_dup(const zbx_vmware_custom_attr_t *src)
{
	zbx_vmware_custom_attr_t	*custom_attr;

	custom_attr = (zbx_vmware_custom_attr_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_custom_attr_t));
	custom_attr->name = vmware_shared_strdup(src->name);
	custom_attr->value = vmware_shared_strdup(src->value);

	return custom_attr;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies object properties list into shared memory                  *
 *                                                                            *
 * Parameters: src       - [IN] properties list                               *
 *             props_num - [IN] number of properties in list                  *
 *                                                                            *
 * Return value: duplicated object properties list                            *
 *                                                                            *
 ******************************************************************************/
static char	**vmware_props_shared_dup(char ** const src, int props_num)
{
	if (NULL == src)
		return NULL;

	char	**props = (char **)__vm_shmem_malloc_func(NULL, sizeof(char *) * props_num);

	for (int i = 0; i < props_num; i++)
		props[i] = vmware_shared_strdup(src[i]);

	return props;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware virtual machine device object into shared memory    *
 *                                                                            *
 * Parameters: src - [IN] vmware device object                                *
 *                                                                            *
 * Return value: duplicated vmware device object                              *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_dev_t	*vmware_shmem_dev_dup(const zbx_vmware_dev_t *src)
{
	zbx_vmware_dev_t	*dev;

	dev = (zbx_vmware_dev_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_dev_t));
	dev->type = src->type;
	dev->instance = vmware_shared_strdup(src->instance);
	dev->label = vmware_shared_strdup(src->label);
	dev->props = vmware_props_shared_dup(src->props, ZBX_VMWARE_DEV_PROPS_NUM);

	return dev;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware virtual machine object into shared memory           *
 *                                                                            *
 * Parameters: src - [IN] vmware virtual machine object                       *
 *                                                                            *
 * Return value: duplicated vmware virtual machine object                     *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_vm_t	*vmware_shmem_vm_dup(const zbx_vmware_vm_t *src)
{
	zbx_vmware_vm_t	*vm = (zbx_vmware_vm_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_vm_t));

	VMWARE_VECTOR_CREATE(&vm->devs, vmware_dev_ptr);
	VMWARE_VECTOR_CREATE(&vm->file_systems, vmware_fs_ptr);
	VMWARE_VECTOR_CREATE(&vm->custom_attrs, vmware_custom_attr_ptr);
	VMWARE_VECTOR_CREATE(&vm->alarm_ids, str);
	zbx_vector_vmware_dev_ptr_reserve(&vm->devs, (size_t)src->devs.values_num);
	zbx_vector_vmware_fs_ptr_reserve(&vm->file_systems, (size_t)src->file_systems.values_num);
	zbx_vector_vmware_custom_attr_ptr_reserve(&vm->custom_attrs, (size_t)src->custom_attrs.values_num);
	zbx_vector_str_reserve(&vm->alarm_ids, (size_t)src->alarm_ids.values_num);

	vm->uuid = vmware_shared_strdup(src->uuid);
	vm->id = vmware_shared_strdup(src->id);
	vm->props = vmware_props_shared_dup(src->props, ZBX_VMWARE_VMPROPS_NUM);
	vm->snapshot_count = src->snapshot_count;

	for (int i = 0; i < src->devs.values_num; i++)
		zbx_vector_vmware_dev_ptr_append(&vm->devs, vmware_shmem_dev_dup(src->devs.values[i]));

	for (int i = 0; i < src->file_systems.values_num; i++)
		zbx_vector_vmware_fs_ptr_append(&vm->file_systems, vmware_shmem_fs_dup(src->file_systems.values[i]));

	for (int i = 0; i < src->custom_attrs.values_num; i++)
	{
		zbx_vector_vmware_custom_attr_ptr_append(&vm->custom_attrs,
				vmware_shmem_attr_dup(src->custom_attrs.values[i]));
	}

	for (int i = 0; i < src->alarm_ids.values_num; i++)
		zbx_vector_str_append(&vm->alarm_ids, vmware_shared_strdup(src->alarm_ids.values[i]));

	return vm;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor datastore name object into shared memory *
 *                                                                            *
 * Parameters: src - [IN] vmware datastore name object                        *
 *                                                                            *
 * Return value: duplicated vmware datastore name object                      *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_dsname_t	*vmware_dsname_shared_dup(const zbx_vmware_dsname_t *src)
{
	zbx_vmware_dsname_t	*dsname;

	dsname = (zbx_vmware_dsname_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_dsname_t));

	dsname->name = vmware_shared_strdup(src->name);
	dsname->uuid = vmware_shared_strdup(src->uuid);

	VMWARE_VECTOR_CREATE(&dsname->hvdisks, vmware_hvdisk);
	zbx_vector_vmware_hvdisk_reserve(&dsname->hvdisks, (size_t)src->hvdisks.values_num);

	for (int i = 0; i < src->hvdisks.values_num; i++)
	{
		zbx_vector_vmware_hvdisk_append(&dsname->hvdisks, src->hvdisks.values[i]);
	}

	return dsname;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware physical NIC object into shared memory              *
 *                                                                            *
 * Parameters: src - [IN] vmware physical NIC object                          *
 *                                                                            *
 * Return value: duplicated vmware physical NIC object                        *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_pnic_t	*vmware_pnic_shared_dup(const zbx_vmware_pnic_t *src)
{
	zbx_vmware_pnic_t	*pnic;

	pnic = (zbx_vmware_pnic_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_pnic_t));
	pnic->name = vmware_shared_strdup(src->name);
	pnic->speed = src->speed;
	pnic->duplex = src->duplex;
	pnic->driver = vmware_shared_strdup(src->driver);
	pnic->mac = vmware_shared_strdup(src->mac);

	return pnic;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor disks object into shared memory          *
 *                                                                            *
 * Parameters: src - [IN] vmware disk info object                             *
 *                                                                            *
 * Return value: duplicated vmware disk info object                           *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_diskinfo_t	*vmware_diskinfo_shared_dup(const zbx_vmware_diskinfo_t *src)
{
	zbx_vmware_diskinfo_t	*di;

	di = (zbx_vmware_diskinfo_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_diskinfo_t));

	di->diskname = vmware_shared_strdup(src->diskname);
	di->ds_uuid = vmware_shared_strdup(src->ds_uuid);
	di->operational_state = vmware_shared_strdup(src->operational_state);
	di->lun_type = vmware_shared_strdup(src->lun_type);
	di->queue_depth = src->queue_depth;
	di->model = vmware_shared_strdup(src->model);
	di->vendor = vmware_shared_strdup(src->vendor);
	di->revision = vmware_shared_strdup(src->revision);
	di->serial_number = vmware_shared_strdup(src->serial_number);

	if (NULL != src->vsan)
	{
		di->vsan = (zbx_vmware_vsandiskinfo_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_vsandiskinfo_t));
		di->vsan->ssd = vmware_shared_strdup(src->vsan->ssd);
		di->vsan->local_disk = vmware_shared_strdup(src->vsan->local_disk);
		di->vsan->block = src->vsan->block;
		di->vsan->block_size = src->vsan->block_size;
	}
	else
		di->vsan = NULL;

	return di;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor object into shared memory                *
 *                                                                            *
 * Parameters: dst - [OUT] vmware hypervisor object into shared memory        *
 *             src - [IN] vmware hypervisor object                            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_hv_shared_copy(zbx_vmware_hv_t *dst, const zbx_vmware_hv_t *src)
{
	VMWARE_VECTOR_CREATE(&dst->dsnames, vmware_dsname_ptr);
	VMWARE_VECTOR_CREATE(&dst->vms, vmware_vm_ptr);
	VMWARE_VECTOR_CREATE(&dst->pnics, vmware_pnic_ptr);
	VMWARE_VECTOR_CREATE(&dst->alarm_ids, str);
	VMWARE_VECTOR_CREATE(&dst->diskinfo, vmware_diskinfo_ptr);
	zbx_vector_vmware_dsname_ptr_reserve(&dst->dsnames, (size_t)src->dsnames.values_num);
	zbx_vector_vmware_vm_ptr_reserve(&dst->vms, (size_t)src->vms.values_num);
	zbx_vector_vmware_pnic_ptr_reserve(&dst->pnics, (size_t)src->pnics.values_num);
	zbx_vector_str_reserve(&dst->alarm_ids, (size_t)src->alarm_ids.values_num);
	zbx_vector_vmware_diskinfo_ptr_reserve(&dst->diskinfo, (size_t)src->diskinfo.values_num);

	dst->uuid = vmware_shared_strdup(src->uuid);
	dst->id = vmware_shared_strdup(src->id);
	dst->clusterid = vmware_shared_strdup(src->clusterid);

	dst->props = vmware_props_shared_dup(src->props, ZBX_VMWARE_HVPROPS_NUM);
	dst->datacenter_name = vmware_shared_strdup(src->datacenter_name);
	dst->parent_name = vmware_shared_strdup(src->parent_name);
	dst->parent_type = vmware_shared_strdup(src->parent_type);
	dst->ip = vmware_shared_strdup(src->ip);

	for (int i = 0; i < src->dsnames.values_num; i++)
		zbx_vector_vmware_dsname_ptr_append(&dst->dsnames, vmware_dsname_shared_dup(src->dsnames.values[i]));

	for (int i = 0; i < src->vms.values_num; i++)
		zbx_vector_vmware_vm_ptr_append(&dst->vms, vmware_shmem_vm_dup((zbx_vmware_vm_t *)src->vms.values[i]));

	for (int i = 0; i < src->pnics.values_num; i++)
		zbx_vector_vmware_pnic_ptr_append(&dst->pnics, vmware_pnic_shared_dup(src->pnics.values[i]));

	for (int i = 0; i < src->alarm_ids.values_num; i++)
		zbx_vector_str_append(&dst->alarm_ids, vmware_shared_strdup(src->alarm_ids.values[i]));

	for (int i = 0; i < src->diskinfo.values_num; i++)
	{
		zbx_vector_vmware_diskinfo_ptr_append(&dst->diskinfo,
				vmware_diskinfo_shared_dup(src->diskinfo.values[i]));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware alarm object into shared memory                     *
 *                                                                            *
 * Parameters: src - [IN] vmware alarm object                                 *
 *                                                                            *
 * Return value: duplicated vmware alarm object                               *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_alarm_t	*vmware_alarm_shared_dup(const zbx_vmware_alarm_t *src)
{
	zbx_vmware_alarm_t	*alarm;

	alarm = (zbx_vmware_alarm_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_alarm_t));
	alarm->key = vmware_shared_strdup(src->key);
	alarm->name = vmware_shared_strdup(src->name);
	alarm->system_name = vmware_shared_strdup(src->system_name);
	alarm->description = vmware_shared_strdup(src->description);
	alarm->overall_status = vmware_shared_strdup(src->overall_status);
	alarm->time = vmware_shared_strdup(src->time);
	alarm->enabled = src->enabled;
	alarm->acknowledged = src->acknowledged;

	return alarm;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware datacenter object into shared memory                *
 *                                                                            *
 * Parameters: src - [IN] vmware datacenter object                            *
 *                                                                            *
 * Return value: duplicated vmware datacenter object                          *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_datacenter_t	*vmware_datacenter_shared_dup(const zbx_vmware_datacenter_t *src)
{
	zbx_vmware_datacenter_t	*datacenter;

	datacenter = (zbx_vmware_datacenter_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_datacenter_t));
	datacenter->name = vmware_shared_strdup(src->name);
	datacenter->id = vmware_shared_strdup(src->id);
	vmware_shmem_vector_str_create_ext(&datacenter->alarm_ids);
	zbx_vector_str_reserve(&datacenter->alarm_ids, (size_t)src->alarm_ids.values_num);

	for (int i = 0; i < src->alarm_ids.values_num; i++)
		zbx_vector_str_append(&datacenter->alarm_ids, vmware_shared_strdup(src->alarm_ids.values[i]));

	return datacenter;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware cluster object into shared memory                   *
 *                                                                            *
 * Parameters: src - [IN] vmware cluster object                               *
 *                                                                            *
 * Return value: copied vmware cluster object                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_cluster_t	*vmware_cluster_shared_dup(const zbx_vmware_cluster_t *src)
{
	zbx_vmware_cluster_t	*cluster;

	cluster = (zbx_vmware_cluster_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_cluster_t));
	cluster->id = vmware_shared_strdup(src->id);
	cluster->name = vmware_shared_strdup(src->name);
	cluster->status = vmware_shared_strdup(src->status);
	vmware_shmem_vector_str_create_ext(&cluster->dss_uuid);
	zbx_vector_str_reserve(&cluster->dss_uuid, (size_t)src->dss_uuid.values_num);
	vmware_shmem_vector_str_create_ext(&cluster->alarm_ids);
	zbx_vector_str_reserve(&cluster->alarm_ids, (size_t)src->alarm_ids.values_num);

	for (int i = 0; i < src->dss_uuid.values_num; i++)
		zbx_vector_str_append(&cluster->dss_uuid, vmware_shared_strdup(src->dss_uuid.values[i]));

	for (int i = 0; i < src->alarm_ids.values_num; i++)
		zbx_vector_str_append(&cluster->alarm_ids, vmware_shared_strdup(src->alarm_ids.values[i]));

	return cluster;
}

static int	vmware_vm_compare(const void *d1, const void *d2)
{
	const zbx_vmware_vm_index_t	*vmi1 = (const zbx_vmware_vm_index_t *)d1;
	const zbx_vmware_vm_index_t	*vmi2 = (const zbx_vmware_vm_index_t *)d2;

	return strcmp(vmi1->vm->uuid, vmi2->vm->uuid);
}

/* virtual machine index support */
static zbx_hash_t	vmware_vm_hash(const void *data)
{
	const zbx_vmware_vm_index_t	*vmi = (const zbx_vmware_vm_index_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(vmi->vm->uuid, strlen(vmi->vm->uuid), ZBX_DEFAULT_HASH_SEED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor datastore object into shared memory      *
 *                                                                            *
 * Parameters: src - [IN] vmware datastore object                             *
 *                                                                            *
 * Return value: duplicated vmware datastore object                           *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_datastore_t	*vmware_datastore_shared_dup(const zbx_vmware_datastore_t *src)
{
	zbx_vmware_datastore_t	*datastore;

	datastore = (zbx_vmware_datastore_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_datastore_t));
	datastore->uuid = vmware_shared_strdup(src->uuid);
	datastore->name = vmware_shared_strdup(src->name);
	datastore->id = vmware_shared_strdup(src->id);
	datastore->type = vmware_shared_strdup(src->type);
	VMWARE_VECTOR_CREATE(&datastore->hv_uuids_access, str_uint64_pair);

	zbx_vector_str_uint64_pair_reserve(&datastore->hv_uuids_access, (size_t)src->hv_uuids_access.values_num);

	VMWARE_VECTOR_CREATE(&datastore->diskextents, vmware_diskextent_ptr);
	zbx_vector_vmware_diskextent_ptr_reserve(&datastore->diskextents, (size_t)src->diskextents.values_num);
	VMWARE_VECTOR_CREATE(&datastore->alarm_ids, str);
	zbx_vector_str_reserve(&datastore->alarm_ids, (size_t)src->alarm_ids.values_num);

	datastore->capacity = src->capacity;
	datastore->free_space = src->free_space;
	datastore->uncommitted = src->uncommitted;

	for (int i = 0; i < src->hv_uuids_access.values_num; i++)
	{
		zbx_str_uint64_pair_t	val;

		val.name = vmware_shared_strdup(src->hv_uuids_access.values[i].name);
		val.value = src->hv_uuids_access.values[i].value;
		zbx_vector_str_uint64_pair_append_ptr(&datastore->hv_uuids_access, &val);
	}

	for (int i = 0; i < src->diskextents.values_num; i++)
	{
		zbx_vector_vmware_diskextent_ptr_append(&datastore->diskextents,
				vmware_shmem_diskextent_dup(src->diskextents.values[i]));
	}

	for (int i = 0; i < src->alarm_ids.values_num; i++)
		zbx_vector_str_append(&datastore->alarm_ids, vmware_shared_strdup(src->alarm_ids.values[i]));

	return datastore;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware data object into shared memory                      *
 *                                                                            *
 * Parameters: src - [IN] vmware data object                                  *
 *                                                                            *
 * Return value: duplicated vmware data object                                *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_data_t	*vmware_shmem_data_dup(zbx_vmware_data_t *src)
{
	zbx_vmware_data_t	*data;
	zbx_hashset_iter_t	iter;
	zbx_vmware_hv_t		*hv, hv_local;

	data = (zbx_vmware_data_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_data_t));
	zbx_hashset_create_ext(&data->hvs, 1, vmware_hv_hash, vmware_hv_compare, NULL, __vm_shmem_malloc_func,
			__vm_shmem_realloc_func, __vm_shmem_free_func);

	VMWARE_VECTOR_CREATE(&data->clusters, vmware_cluster_ptr);
	VMWARE_VECTOR_CREATE(&data->datastores, vmware_datastore_ptr);
	VMWARE_VECTOR_CREATE(&data->datacenters, vmware_datacenter_ptr);
	VMWARE_VECTOR_CREATE(&data->resourcepools, vmware_resourcepool_ptr);
	VMWARE_VECTOR_CREATE(&data->dvswitches, vmware_dvswitch_ptr);
	VMWARE_VECTOR_CREATE(&data->alarms, vmware_alarm_ptr);
	VMWARE_VECTOR_CREATE(&data->alarm_ids, str);
	zbx_vector_vmware_cluster_ptr_reserve(&data->clusters, (size_t)src->clusters.values_num);
	zbx_vector_vmware_datastore_ptr_reserve(&data->datastores, (size_t)src->datastores.values_num);
	zbx_vector_vmware_datacenter_ptr_reserve(&data->datacenters, (size_t)src->datacenters.values_num);
	zbx_vector_vmware_resourcepool_ptr_reserve(&data->resourcepools, (size_t)src->resourcepools.values_num);
	zbx_vector_vmware_dvswitch_ptr_reserve(&data->dvswitches, (size_t)src->dvswitches.values_num);
	zbx_vector_vmware_alarm_ptr_reserve(&data->alarms, (size_t)src->alarms.values_num);
	zbx_vector_str_reserve(&data->alarm_ids, (size_t)src->alarm_ids.values_num);

	zbx_hashset_create_ext(&data->vms_index, 100, vmware_vm_hash, vmware_vm_compare, NULL, __vm_shmem_malloc_func,
			__vm_shmem_realloc_func, __vm_shmem_free_func);

	data->error = vmware_shared_strdup(src->error);

	for (int i = 0; i < src->clusters.values_num; i++)
	{
		zbx_vector_vmware_cluster_ptr_append(&data->clusters,
				vmware_cluster_shared_dup((zbx_vmware_cluster_t *)src->clusters.values[i]));
	}

	for (int i = 0; i < src->datastores.values_num; i++)
	{
		zbx_vector_vmware_datastore_ptr_append(&data->datastores,
				vmware_datastore_shared_dup(src->datastores.values[i]));
	}

	for (int i = 0; i < src->datacenters.values_num; i++)
	{
		zbx_vector_vmware_datacenter_ptr_append(&data->datacenters,
				vmware_datacenter_shared_dup(src->datacenters.values[i]));
	}

	for (int i = 0; i < src->resourcepools.values_num; i++)
	{
		zbx_vector_vmware_resourcepool_ptr_append(&data->resourcepools,
				vmware_shmem_resourcepool_dup(src->resourcepools.values[i]));
	}

	for (int i = 0; i < src->dvswitches.values_num; i++)
	{
		zbx_vector_vmware_dvswitch_ptr_append(&data->dvswitches,
				vmware_shmem_dvswitch_dup(src->dvswitches.values[i]));
	}

	for (int i = 0; i < src->alarms.values_num; i++)
	{
		zbx_vector_vmware_alarm_ptr_append(&data->alarms,
				vmware_alarm_shared_dup(src->alarms.values[i]));
	}

	for (int i = 0; i < src->alarm_ids.values_num; i++)
		zbx_vector_str_append(&data->alarm_ids, vmware_shared_strdup(src->alarm_ids.values[i]));

	zbx_hashset_iter_reset(&src->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		vmware_hv_shared_copy(&hv_local, hv);
		hv = (zbx_vmware_hv_t *)zbx_hashset_insert(&data->hvs, &hv_local, sizeof(hv_local));

		if (SUCCEED != zbx_hashset_reserve(&data->vms_index, hv->vms.values_num))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		for (int i = 0; i < hv->vms.values_num; i++)
		{
			zbx_vmware_vm_index_t	vmi_local = {hv->vms.values[i], hv};

			zbx_hashset_insert(&data->vms_index, &vmi_local, sizeof(vmi_local));
		}
	}

	data->max_query_metrics = src->max_query_metrics;

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware event log data object into shared memory            *
 *                                                                            *
 * Parameters: src - [IN] vmware event log data object                        *
 *                                                                            *
 * Return value: duplicated vmware event log data object                      *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_eventlog_data_t	*vmware_shmem_eventlog_data_dup(zbx_vmware_eventlog_data_t *src)
{
	zbx_vmware_eventlog_data_t	*evt_data;

	evt_data = (zbx_vmware_eventlog_data_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_eventlog_data_t));

	VMWARE_VECTOR_CREATE(&evt_data->events, vmware_event_ptr);
	zbx_vector_vmware_event_ptr_reserve(&evt_data->events, (size_t)src->events.values_alloc);

	evt_data->error = vmware_shared_strdup(src->error);

	for (int i = 0; i < src->events.values_num; i++)
	{
		zbx_vector_vmware_event_ptr_append(&evt_data->events,
				vmware_shmem_event_dup(src->events.values[i]));
	}

	return evt_data;
}

zbx_vmware_service_t	*vmware_shmem_vmware_service_malloc(void)
{
	return (zbx_vmware_service_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_service_t));
}

static int	vmware_cust_query_compare_func(const void *d1, const void *d2)
{
	int	ret;

	const zbx_vmware_cust_query_t	*e1 = (const zbx_vmware_cust_query_t *)d1;
	const zbx_vmware_cust_query_t	*e2 = (const zbx_vmware_cust_query_t *)d2;

	if (0 == (ret = strcmp(e1->soap_type, e2->soap_type)) && 0 == (ret = strcmp(e1->id, e2->id)) &&
			0 == (ret = strcmp(e1->key, e2->key)) && 0 == (ret = strcmp(e1->mode, e2->mode)))
	{
		ret = (int)e1->query_type - (int)e2->query_type;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * custom query hashset support functions                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	vmware_cust_query_hash_func(const void *data)
{
	zbx_hash_t			seed;
	const zbx_vmware_cust_query_t	*cust_query = (const zbx_vmware_cust_query_t *)data;

	seed = ZBX_DEFAULT_STRING_HASH_ALGO(cust_query->soap_type, strlen(cust_query->soap_type),
			ZBX_DEFAULT_HASH_SEED);
	seed = ZBX_DEFAULT_STRING_HASH_ALGO(cust_query->id, strlen(cust_query->id), seed);
	seed = ZBX_DEFAULT_STRING_HASH_ALGO(cust_query->key, strlen(cust_query->key), seed);
	seed = ZBX_DEFAULT_STRING_HASH_ALGO(cust_query->mode, strlen(cust_query->mode), seed);

	return ZBX_DEFAULT_HASH_ALGO(&cust_query->query_type, sizeof(cust_query->query_type), seed);
}

void	vmware_shmem_service_hashset_create(zbx_vmware_service_t *service)
{
#define ZBX_VMWARE_COUNTERS_INIT_SIZE	500
	zbx_hashset_create_ext(&service->entities, 100, vmware_perf_entity_hash_func,  vmware_perf_entity_compare_func,
			NULL, __vm_shmem_malloc_func, __vm_shmem_realloc_func, __vm_shmem_free_func);

	zbx_hashset_create_ext(&service->counters, ZBX_VMWARE_COUNTERS_INIT_SIZE, vmware_counter_hash_func,
			vmware_counter_compare_func, NULL, __vm_shmem_malloc_func, __vm_shmem_realloc_func,
			__vm_shmem_free_func);

	zbx_hashset_create_ext(&service->cust_queries, 100, vmware_cust_query_hash_func, vmware_cust_query_compare_func,
			NULL, __vm_shmem_malloc_func, __vm_shmem_realloc_func, __vm_shmem_free_func);

	zbx_hashset_create_ext(&service->eventlog.evt_severities, 100, ZBX_DEFAULT_STRING_PTR_HASH_FUNC,
			ZBX_DEFAULT_STR_COMPARE_FUNC, NULL, __vm_shmem_malloc_func, __vm_shmem_realloc_func,
			__vm_shmem_free_func);
#undef ZBX_VMWARE_COUNTERS_INIT_SIZE
}

zbx_vector_custquery_param_t *vmware_shmem_custquery_malloc(void)
{
	return (zbx_vector_custquery_param_t *) __vm_shmem_malloc_func(NULL,
				sizeof(zbx_vector_custquery_param_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort zbx_binary_heap_elem_t by nextcheck      *
 *                                                                            *
 ******************************************************************************/
static int	vmware_job_compare_nextcheck(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	return ((const zbx_vmware_job_t *)e1->data)->nextcheck - ((const zbx_vmware_job_t *)e2->data)->nextcheck;
}

#define REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

int	vmware_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp((const char *)d1 + REFCOUNT_FIELD_SIZE, (const char *)d2 + REFCOUNT_FIELD_SIZE);
}

zbx_hash_t	vmware_strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((const char *)data + REFCOUNT_FIELD_SIZE);
}
#undef REFCOUNT_FIELD_SIZE

zbx_vmware_job_t	*vmware_shmem_vmware_job_malloc(void)
{
	return (zbx_vmware_job_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_job_t));

}

void	vmware_shmem_vmware_job_free(zbx_vmware_job_t *job)
{
	__vm_shmem_free_func(job);
}

zbx_vmware_entity_tags_t	*vmware_shmem_entity_tags_malloc(void)
{
	return (zbx_vmware_entity_tags_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_entity_tags_t));
}

zbx_vmware_tag_t	*vmware_shmem_tag_malloc(void)
{
	return (zbx_vmware_tag_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_tag_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies events severity vector into shared memory hashset          *
 *                                                                            *
 * Parameters: dst - [IN] destination hashset                                 *
 *             src - [IN] source vector                                       *
 *                                                                            *
 ******************************************************************************/
void	vmware_shmem_evtseverity_copy(zbx_hashset_t *dst, const zbx_vector_vmware_key_value_t *src)
{
	if (SUCCEED != zbx_hashset_reserve(dst, src->values_num))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	for (int i = 0; i < src->values_num; i++)
	{
		zbx_vmware_key_value_t	*es_dst, *es_src = &src->values[i];

		es_dst = (zbx_vmware_key_value_t *)zbx_hashset_insert(dst, es_src, sizeof(zbx_vmware_key_value_t));

		/* check if the event type was inserted - copy severity only for inserted event types */
		if (es_dst->key == es_src->key)
		{
			es_dst->key = vmware_shared_strdup(es_src->key);
			es_dst->value = vmware_shared_strdup(es_src->value);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store zbx_vmware_key_value_t  *
 *                                                                            *
 ******************************************************************************/
void	zbx_shmem_vmware_key_value_free(zbx_vmware_key_value_t *value)
{
	vmware_shared_strfree(value->key);
	vmware_shared_strfree(value->value);
}
#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

/******************************************************************************
 *                                                                            *
 * Purpose: initializes vmware collector service                              *
 *                                                                            *
 * Comments: This function must be called before worker threads are forked.   *
 *                                                                            *
 ******************************************************************************/
int	vmware_shmem_init(zbx_uint64_t *config_vmware_cache_size, zbx_vmware_t **vmware, char **error)
{
	int		ret = FAIL;
	zbx_uint64_t	size_reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	size_reserved = zbx_shmem_required_size(1, "vmware cache size", "VMwareCacheSize");

	*config_vmware_cache_size -= size_reserved;

	if (SUCCEED != zbx_shmem_create(&vmware_mem, *config_vmware_cache_size, "vmware cache size", "VMwareCacheSize",
			0, error))
	{
		goto out;
	}

	*vmware = (zbx_vmware_t *)__vm_shmem_malloc_func(NULL, sizeof(zbx_vmware_t));

	memset(*vmware, 0, sizeof(zbx_vmware_t));

	VMWARE_VECTOR_CREATE(&(*vmware)->services, vmware_service_ptr);
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	(*vmware)->strpool_sz = 0;

	zbx_hashset_create_ext(&(*vmware)->strpool, 100, vmware_strpool_hash_func, vmware_strpool_compare_func, NULL,
		__vm_shmem_malloc_func, __vm_shmem_realloc_func, __vm_shmem_free_func);
	zbx_binary_heap_create_ext(&(*vmware)->jobs_queue, vmware_job_compare_nextcheck, ZBX_BINARY_HEAP_OPTION_EMPTY,
			__vm_shmem_malloc_func, __vm_shmem_realloc_func, __vm_shmem_free_func);
#endif
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}
