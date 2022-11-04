/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#ifndef ZABBIX_VMWARE_H
#define ZABBIX_VMWARE_H

#include "config.h"
#include "zbxthreads.h"
#include "zbxalgo.h"
#include "zbxjson.h"

/* the vmware service state */
#define ZBX_VMWARE_STATE_NEW		0x001
#define ZBX_VMWARE_STATE_READY		0x002
#define ZBX_VMWARE_STATE_FAILED		0x004

#define ZBX_VMWARE_STATE_MASK		0x0FF

#define ZBX_VMWARE_EVENT_KEY_UNINITIALIZED	__UINT64_C(0xffffffffffffffff)

typedef struct
{
	char		*name;
	zbx_uint64_t	value;
}
zbx_str_uint64_pair_t;

ZBX_PTR_VECTOR_DECL(str_uint64_pair, zbx_str_uint64_pair_t)
int	zbx_str_uint64_pair_name_compare(const void *p1, const void *p2);

#define UC(v)	((unsigned char)v)

/* performance counter data */
typedef struct
{
	/* the counter id */
	zbx_uint64_t			counterid;

	/* the counter values for various instances */
	/*    pair->name  - instance                */
	/*    pair->value - value                   */
	zbx_vector_str_uint64_pair_t	values;

#define ZBX_VMWARE_COUNTER_NEW		UC(0x00)
#define ZBX_VMWARE_COUNTER_READY	UC(0x01)
#define ZBX_VMWARE_COUNTER_UPDATING	UC(0x02)
#define ZBX_VMWARE_COUNTER_CUSTOM	UC(0x10)
#define ZBX_VMWARE_COUNTER_ACCEPTABLE	UC(0x20)
#define ZBX_VMWARE_COUNTER_NOTSUPPORTED	UC(0x40)

#define ZBX_VMWARE_COUNTER_STATE_MASK	0xF0
	/* the vmware performance counter state */
	unsigned char			state;

	/* time of last attempt of poller to use data */
	time_t				last_used;

	/* alternate query instance (for the case when 'entity' query is TOTAL) */
	char				*query_instance;
}
zbx_vmware_perf_counter_t;

/* an entity monitored with performance counters */
typedef struct
{
	/* entity type: HostSystem or VirtualMachine */
	char			*type;

	/* entity id */
	char			*id;

#define ZBX_VMWARE_PERF_INTERVAL_UNKNOWN	0
#define ZBX_VMWARE_PERF_INTERVAL_NONE		-1
	/* the performance counter refresh rate */
	int			refresh;

	/* timestamp when the entity was queried last time */
	time_t			last_seen;

	/* the performance counters to monitor */
	zbx_vector_ptr_t	counters;

#define ZBX_VMWARE_PERF_QUERY_ALL		"*"
#define ZBX_VMWARE_PERF_QUERY_TOTAL		""
	/* the performance counter query instance name */
	char			*query_instance;

	/* error information */
	char			*error;
}
zbx_vmware_perf_entity_t;

typedef struct
{
	char		*ssd;
	char		*local_disk;
	unsigned int	block_size;
	unsigned int	block;
}
zbx_vmware_vsandiskinfo_t;

typedef struct
{
	char				*diskname;
	char				*ds_uuid;
	char				*operational_state;
	char				*lun_type;
	int				queue_depth;
	char				*model;
	char				*vendor;
	char				*revision;
	char				*serial_number;
	zbx_vmware_vsandiskinfo_t	*vsan;
}
zbx_vmware_diskinfo_t;

ZBX_PTR_VECTOR_DECL(vmware_diskinfo, zbx_vmware_diskinfo_t *)

typedef struct
{
	char		*diskname;
	zbx_uint64_t	partitionid;
}
zbx_vmware_diskextent_t;

ZBX_PTR_VECTOR_DECL(vmware_diskextent, zbx_vmware_diskextent_t *)

#define ZBX_VMWARE_DS_NONE		0
#define ZBX_VMWARE_DS_MOUNTED		1
#define ZBX_VMWARE_DS_ACCESSIBLE	2
#define ZBX_VMWARE_DS_READ		4
#define ZBX_VMWARE_DS_WRITE		8
#define ZBX_VMWARE_DS_READWRITE		(ZBX_VMWARE_DS_READ | ZBX_VMWARE_DS_WRITE)
#define ZBX_VMWARE_DS_READ_FILTER	(ZBX_VMWARE_DS_MOUNTED | ZBX_VMWARE_DS_ACCESSIBLE | ZBX_VMWARE_DS_READ)
#define ZBX_VMWARE_DS_WRITE_FILTER	(ZBX_VMWARE_DS_MOUNTED | ZBX_VMWARE_DS_ACCESSIBLE | ZBX_VMWARE_DS_READWRITE)

typedef struct
{
	char				*uuid;
	char				*name;
	char				*id;
	char				*type;
	zbx_uint64_t			capacity;
	zbx_uint64_t			free_space;
	zbx_uint64_t			uncommitted;
	zbx_vector_str_uint64_pair_t	hv_uuids_access;
	zbx_vector_vmware_diskextent_t	diskextents;
	zbx_vector_str_t		alarm_ids;
}
zbx_vmware_datastore_t;

int	vmware_ds_uuid_compare(const void *d1, const void *d2);
int	vmware_ds_name_compare(const void *d1, const void *d2);
ZBX_PTR_VECTOR_DECL(vmware_datastore, zbx_vmware_datastore_t *)

typedef struct
{
	zbx_uint64_t	partitionid;
	int		multipath_total;
	int		multipath_active;
}
zbx_vmware_hvdisk_t;

ZBX_VECTOR_DECL(vmware_hvdisk, zbx_vmware_hvdisk_t)

typedef struct
{
	char				*name;
	char				*uuid;
	zbx_vector_vmware_hvdisk_t	hvdisks;
}
zbx_vmware_dsname_t;

int	vmware_dsname_compare(const void *d1, const void *d2);
ZBX_PTR_VECTOR_DECL(vmware_dsname, zbx_vmware_dsname_t *)

typedef struct
{
	char			*name;
	char			*id;
	zbx_vector_str_t	alarm_ids;
}
zbx_vmware_datacenter_t;
int	vmware_dc_id_compare(const void *d1, const void *d2);
ZBX_PTR_VECTOR_DECL(vmware_datacenter, zbx_vmware_datacenter_t *)

typedef struct
{
	char			*uuid;
	char			*id;
	char			*name;
}
zbx_vmware_dvswitch_t;
int	vmware_dvs_uuid_compare(const void *d1, const void *d2);
ZBX_PTR_VECTOR_DECL(vmware_dvswitch, zbx_vmware_dvswitch_t *)

#define ZBX_VMWARE_DEV_TYPE_NIC				1
#define ZBX_VMWARE_DEV_TYPE_DISK			2
#define ZBX_VMWARE_DEV_PROPS_IFMAC			0
#define ZBX_VMWARE_DEV_PROPS_IFCONNECTED		1
#define ZBX_VMWARE_DEV_PROPS_IFTYPE			2
#define ZBX_VMWARE_DEV_PROPS_IFBACKINGDEVICE		3
#define ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_UUID		4
#define ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORTGROUP	5
#define ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORT		6
#define ZBX_VMWARE_DEV_PROPS_NUM			7

typedef struct
{
	int	type;
	char	*instance;
	char	*label;
	char	**props;
}
zbx_vmware_dev_t;

#define ZBX_DUPLEX_FULL		0
#define ZBX_DUPLEX_HALF		1

/* hypervisor physical NIC data */
typedef struct
{
	char		*name;
	zbx_uint64_t	speed;
	int		duplex;
	char		*driver;
	char		*mac;
}
zbx_vmware_pnic_t;

int	vmware_pnic_compare(const void *v1, const void *v2);
ZBX_PTR_VECTOR_DECL(vmware_pnic, zbx_vmware_pnic_t *)

/* Alarm data */
typedef struct
{
	char	*key;
	char	*name;
	char	*system_name;
	char	*description;
	char	*overall_status;
	char	*time;
	int	enabled;
	int	acknowledged;
}
zbx_vmware_alarm_t;
ZBX_PTR_VECTOR_DECL(vmware_alarm, zbx_vmware_alarm_t *)

/* file system data */
typedef struct
{
	char		*path;
	zbx_uint64_t	capacity;
	zbx_uint64_t	free_space;
}
zbx_vmware_fs_t;

typedef struct
{
	char		*name;
	char		*value;
}
zbx_vmware_custom_attr_t;
ZBX_PTR_VECTOR_DECL(vmware_custom_attr, zbx_vmware_custom_attr_t *)
int	vmware_custom_attr_compare_name(const void *a1, const void *a2);

/* the vmware virtual machine data */
typedef struct
{
	char				*uuid;
	char				*id;
	char				**props;
	zbx_vector_ptr_t		devs;
	zbx_vector_ptr_t		file_systems;
	unsigned int			snapshot_count;
	zbx_vector_vmware_custom_attr_t	custom_attrs;
	zbx_vector_str_t		alarm_ids;
}
zbx_vmware_vm_t;

/* the vmware hypervisor data */
typedef struct
{
	char				*uuid;
	char				*id;
	char				*clusterid;
	char				*datacenter_name;
	char				*parent_name;
	char				*parent_type;
	char				*ip;
	char				**props;
	zbx_vector_vmware_dsname_t	dsnames;
	zbx_vector_ptr_t		vms;
	zbx_vector_vmware_pnic_t	pnics;
	zbx_vector_str_t		alarm_ids;
	zbx_vector_vmware_diskinfo_t	diskinfo;
}
zbx_vmware_hv_t;

/* index virtual machines by uuids */
typedef struct
{
	zbx_vmware_vm_t	*vm;
	zbx_vmware_hv_t	*hv;
}
zbx_vmware_vm_index_t;

/* the vmware cluster data */
typedef struct
{
	char			*id;
	char			*name;
	char			*status;
	zbx_vector_str_t	dss_uuid;
	zbx_vector_str_t	alarm_ids;
}
zbx_vmware_cluster_t;

/* the vmware resource pool data */
typedef struct
{
	char			*id;
	char			*parentid;
	char			*path;
	zbx_uint64_t		vm_num;
}
zbx_vmware_resourcepool_t;

ZBX_PTR_VECTOR_DECL(vmware_resourcepool, zbx_vmware_resourcepool_t *)

/* the vmware eventlog state */
typedef struct
{
	zbx_uint64_t	last_key;	/* lastlogsize when vmware.eventlog[] item was polled last time */
	unsigned char	skip_old;	/* skip old event log records */
	unsigned char	oom;		/* no enough memory to store new events */
	zbx_uint64_t	req_sz;		/* memory size required to store events */
}
zbx_vmware_eventlog_state_t;

/* the vmware event data */
typedef struct
{
	zbx_uint64_t	key;		/* event's key, used to fill logeventid */
	char		*message;	/* event's fullFormattedMessage */
	int		timestamp;	/* event's time stamp */
}
zbx_vmware_event_t;

/* the vmware service data object */
typedef struct
{
	char	*error;

	zbx_hashset_t				hvs;
	zbx_hashset_t				vms_index;
	zbx_vector_ptr_t			clusters;
	zbx_vector_ptr_t			events;			/* vector of pointers to zbx_vmware_event_t structures */
	int					max_query_metrics;	/* max count of Datastore perfCounters in one request */
	zbx_vector_vmware_datastore_t		datastores;
	zbx_vector_vmware_datacenter_t		datacenters;
	zbx_vector_vmware_resourcepool_t	resourcepools;
	zbx_vector_vmware_dvswitch_t		dvswitches;
	zbx_vector_vmware_alarm_t		alarms;
	zbx_vector_str_t			alarm_ids;
}
zbx_vmware_data_t;

typedef enum
{
	VMWARE_OBJECT_PROPERTY,
	VMWARE_DVSWITCH_FETCH_DV_PORTS
}
zbx_vmware_custom_query_type_t;

typedef struct
{
	char	*name;
	char	*value;
}
zbx_vmware_custquery_param_t;
ZBX_PTR_VECTOR_DECL(custquery_param, zbx_vmware_custquery_param_t)
void	zbx_vmware_cq_param_free(zbx_vmware_custquery_param_t cq_param);

/* the vmware custom request */
typedef struct
{
	/* entity type: HostSystem, VirtualMachine etc */
	char				*soap_type;

	/* queried object id: host-18, vm-15 etc */
	char				*id;

	/* id of query: "summary.totalMemory" etc */
	char				*key;

	/* the mode of output value for custom query */
	char				*mode;

	/* the type of query OBJECT or DVSWITCH */
	zbx_vmware_custom_query_type_t	query_type;

	/* the fields name and values of query */
	zbx_vector_custquery_param_t	*query_params;

	/* timestamp when the entity was pooled last time */
	time_t				last_pooled;

	/* the result of query */
	char				*value;

#define ZBX_VMWARE_CQ_NEW		UC(0x01)
#define ZBX_VMWARE_CQ_READY		UC(0x02)
#define ZBX_VMWARE_CQ_ERROR		UC(0x04)
#define ZBX_VMWARE_CQ_PAUSED		UC(0x08)
#define ZBX_VMWARE_CQ_SEPARATE		UC(0x10)
	/* the state of query */
	unsigned char			state;

	/* error information */
	char				*error;
}
zbx_vmware_cust_query_t;

/* the vmware tags data */
typedef struct
{
	char	*name;
	char	*description;
	char	*category;
	char	*id;
}
zbx_vmware_tag_t;
ZBX_PTR_VECTOR_DECL(vmware_tag, zbx_vmware_tag_t *)

/* the vmware tags data for entity (hv, vm etc) */
typedef struct
{
	char	*id;
	char	*type;
}
zbx_vmware_obj_id_t;

typedef struct
{
	char			*uuid;
	zbx_vmware_obj_id_t	*obj_id;
	char			*error;
	zbx_vector_vmware_tag_t	tags;
}
zbx_vmware_entity_tags_t;
ZBX_PTR_VECTOR_DECL(vmware_entity_tags, zbx_vmware_entity_tags_t *)

typedef struct
{
	char				*error;
	zbx_vector_vmware_entity_tags_t	entity_tags;
}
zbx_vmware_data_tags_t;

/* the vmware service data */
typedef struct
{
	char				*url;
	char				*username;
	char				*password;

	/* the service type - vCenter or vSphere */
	unsigned char			type;

	/* the service state - see ZBX_VMWARE_STATE_* defines */
	int				state;

	time_t				lastcheck;

	/* The last vmware service access time. If a service is not accessed for a day it is removed */
	time_t				lastaccess;

	/* the vmware service instance version */
	char				*version;

	/* the vmware service instance version numeric */
	unsigned short			major_version;
	unsigned short			minor_version;
	unsigned short			update_version;

	/* the vmware service instance fullname */
	char				*fullname;

	/* the performance counters dictionary */
	zbx_hashset_t			counters;

	/* list of entities to monitor with performance counters */
	zbx_hashset_t			entities;

	/* the service data object that is swapped with a new one during service update */
	zbx_vmware_data_t		*data;

	/* lastlogsize when vmware.eventlog[] item was polled last time and skip old flag*/
	zbx_vmware_eventlog_state_t	eventlog;

	/* list of custom queries to monitor */
	zbx_hashset_t			cust_queries;

	/* linked jobs count */
	int				jobs_num;

	/* the vmware entity (vm, hv etc) and linked tags */
	zbx_vmware_data_tags_t		data_tags;
}
zbx_vmware_service_t;

/* the vmware collector data */
typedef struct
{
	zbx_vector_ptr_t	services;
	zbx_hashset_t		strpool;
	zbx_uint64_t		strpool_sz;
	zbx_binary_heap_t	jobs_queue;
}
zbx_vmware_t;

typedef struct
{
	time_t			nextcheck;
#define ZBX_VMWARE_UPDATE_CONF		1
#define ZBX_VMWARE_UPDATE_PERFCOUNTERS	2
#define ZBX_VMWARE_UPDATE_REST_TAGS	3
	int			type;
	int			expired;
	zbx_vmware_service_t	*service;
}
zbx_vmware_job_t;

/* the vmware collector statistics */
typedef struct
{
	zbx_uint64_t	memory_used;
	zbx_uint64_t	memory_total;
}
zbx_vmware_stats_t;

ZBX_THREAD_ENTRY(vmware_thread, args);

int	zbx_vmware_init(char **error);
void	zbx_vmware_destroy(void);

void	zbx_vmware_lock(void);
void	zbx_vmware_unlock(void);

int	zbx_vmware_get_statistics(zbx_vmware_stats_t *stats);
char	*zbx_vmware_get_vm_resourcepool_path(zbx_vector_vmware_resourcepool_t *rp, char *id);

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

int	zbx_vmware_service_update(zbx_vmware_service_t *service);
int	zbx_vmware_service_update_perf(zbx_vmware_service_t *service);
int	zbx_vmware_service_update_tags(zbx_vmware_service_t *service);
void	zbx_vmware_service_remove(zbx_vmware_service_t *service);
void	zbx_vmware_job_create(zbx_vmware_t *vmw, zbx_vmware_service_t *service, int job_type);
int	zbx_vmware_job_remove(zbx_vmware_job_t *job);
void	zbx_vmware_shared_tags_error_set(const char *error, zbx_vmware_data_tags_t *data_tags);
void	zbx_vmware_shared_tags_replace(const zbx_vector_vmware_entity_tags_t *src,
		zbx_vector_vmware_entity_tags_t *dst);

zbx_vmware_service_t	*zbx_vmware_get_service(const char* url, const char* username, const char* password);

int	zbx_vmware_service_get_counterid(zbx_vmware_service_t *service, const char *path, zbx_uint64_t *counterid,
		int *unit);
int	zbx_vmware_service_add_perf_counter(zbx_vmware_service_t *service, const char *type, const char *id,
		zbx_uint64_t counterid, const char *instance);
zbx_vmware_perf_entity_t	*zbx_vmware_service_get_perf_entity(zbx_vmware_service_t *service, const char *type,
		const char *id);

zbx_vmware_cust_query_t *zbx_vmware_service_add_cust_query(zbx_vmware_service_t *service, const char *soap_type,
		const char *id, const char *key, zbx_vmware_custom_query_type_t query_type, const char *mode,
		zbx_vector_custquery_param_t *query_params);
zbx_vmware_cust_query_t	*zbx_vmware_service_get_cust_query(zbx_vmware_service_t *service, const char *type,
		const char *id, const char *key, zbx_vmware_custom_query_type_t query_type, const char *mode);

/* hypervisor properties */
#define ZBX_VMWARE_HVPROP_OVERALL_CPU_USAGE		0
#define ZBX_VMWARE_HVPROP_FULL_NAME			1
#define ZBX_VMWARE_HVPROP_HW_NUM_CPU_CORES		2
#define ZBX_VMWARE_HVPROP_HW_CPU_MHZ			3
#define ZBX_VMWARE_HVPROP_HW_CPU_MODEL			4
#define ZBX_VMWARE_HVPROP_HW_NUM_CPU_THREADS		5
#define ZBX_VMWARE_HVPROP_HW_MEMORY_SIZE		6
#define ZBX_VMWARE_HVPROP_HW_MODEL			7
#define ZBX_VMWARE_HVPROP_HW_UUID			8
#define ZBX_VMWARE_HVPROP_HW_VENDOR			9
#define ZBX_VMWARE_HVPROP_MEMORY_USED			10
#define ZBX_VMWARE_HVPROP_HEALTH_STATE			11
#define ZBX_VMWARE_HVPROP_UPTIME			12
#define ZBX_VMWARE_HVPROP_VERSION			13
#define ZBX_VMWARE_HVPROP_NAME				14
#define ZBX_VMWARE_HVPROP_STATUS			15
#define ZBX_VMWARE_HVPROP_MAINTENANCE			16
#define ZBX_VMWARE_HVPROP_SENSOR			17
#define ZBX_VMWARE_HVPROP_NET_NAME			18
#define ZBX_VMWARE_HVPROP_PARENT			19
#define ZBX_VMWARE_HVPROP_CONNECTIONSTATE		20
#define ZBX_VMWARE_HVPROP_HW_SERIALNUMBER		21
#define ZBX_VMWARE_HVPROP_HW_SENSOR			22

#define ZBX_VMWARE_HVPROPS_NUM				23

/* virtual machine properties */
#define ZBX_VMWARE_VMPROP_CPU_NUM			0
#define ZBX_VMWARE_VMPROP_CPU_USAGE			1
#define ZBX_VMWARE_VMPROP_NAME				2
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE			3
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED		4
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE_COMPRESSED	5
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE_SWAPPED		6
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_GUEST	7
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_HOST	8
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE_PRIVATE		9
#define ZBX_VMWARE_VMPROP_MEMORY_SIZE_SHARED		10
#define ZBX_VMWARE_VMPROP_POWER_STATE			11
#define ZBX_VMWARE_VMPROP_STORAGE_COMMITED		12
#define ZBX_VMWARE_VMPROP_STORAGE_UNSHARED		13
#define ZBX_VMWARE_VMPROP_STORAGE_UNCOMMITTED		14
#define ZBX_VMWARE_VMPROP_UPTIME			15
#define ZBX_VMWARE_VMPROP_IPADDRESS			16
#define ZBX_VMWARE_VMPROP_GUESTHOSTNAME			17
#define ZBX_VMWARE_VMPROP_GUESTFAMILY			18
#define ZBX_VMWARE_VMPROP_GUESTFULLNAME			19
#define ZBX_VMWARE_VMPROP_FOLDER			20
#define ZBX_VMWARE_VMPROP_SNAPSHOT			21
#define ZBX_VMWARE_VMPROP_DATASTOREID			22
#define ZBX_VMWARE_VMPROP_CONSOLIDATION_NEEDED		23
#define ZBX_VMWARE_VMPROP_RESOURCEPOOL			24
#define ZBX_VMWARE_VMPROP_TOOLS_VERSION			25
#define ZBX_VMWARE_VMPROP_TOOLS_RUNNING_STATUS		26
#define ZBX_VMWARE_VMPROP_STATE				27

#define ZBX_VMWARE_VMPROPS_NUM				28

/* vmware service types */
#define ZBX_VMWARE_TYPE_UNKNOWN	0
#define ZBX_VMWARE_TYPE_VSPHERE	1
#define ZBX_VMWARE_TYPE_VCENTER	2

#define ZBX_VMWARE_SOAP_DATACENTER	"Datacenter"
#define ZBX_VMWARE_SOAP_FOLDER		"Folder"
#define ZBX_VMWARE_SOAP_CLUSTER		"ClusterComputeResource"
#define ZBX_VMWARE_SOAP_DEFAULT		"VMware"
#define ZBX_VMWARE_SOAP_DS		"Datastore"
#define ZBX_VMWARE_SOAP_HV		"HostSystem"
#define ZBX_VMWARE_SOAP_VM		"VirtualMachine"
#define ZBX_VMWARE_SOAP_DC		"Datacenter"
#define ZBX_VMWARE_SOAP_RESOURCEPOOL	"ResourcePool"
#define ZBX_VMWARE_SOAP_DVS		"VmwareDistributedVirtualSwitch"

/* Indicates the unit of measure represented by a counter or statistical value */
#define ZBX_VMWARE_UNIT_UNDEFINED		0
#define ZBX_VMWARE_UNIT_JOULE			1
#define ZBX_VMWARE_UNIT_KILOBYTES		2
#define ZBX_VMWARE_UNIT_KILOBYTESPERSECOND	3
#define ZBX_VMWARE_UNIT_MEGABYTES		4
#define ZBX_VMWARE_UNIT_MEGABYTESPERSECOND	5
#define ZBX_VMWARE_UNIT_MEGAHERTZ		6
#define ZBX_VMWARE_UNIT_MICROSECOND		7
#define ZBX_VMWARE_UNIT_MILLISECOND		8
#define ZBX_VMWARE_UNIT_NUMBER			9
#define ZBX_VMWARE_UNIT_PERCENT			10
#define ZBX_VMWARE_UNIT_SECOND			11
#define ZBX_VMWARE_UNIT_TERABYTES		12
#define ZBX_VMWARE_UNIT_WATT			13
#define ZBX_VMWARE_UNIT_CELSIUS			14

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_H */
