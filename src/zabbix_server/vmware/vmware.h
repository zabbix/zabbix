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
#include "threads.h"
#include "zbxalgo.h"

/* the vmware service state */
#define ZBX_VMWARE_STATE_NEW		0x001
#define ZBX_VMWARE_STATE_READY		0x002
#define ZBX_VMWARE_STATE_FAILED		0x004

#define ZBX_VMWARE_STATE_MASK		0x0FF

#define ZBX_VMWARE_STATE_UPDATING	0x100
#define ZBX_VMWARE_STATE_UPDATING_PERF	0x200
#define ZBX_VMWARE_STATE_REMOVING	0x400

#define ZBX_VMWARE_STATE_BUSY		(ZBX_VMWARE_STATE_UPDATING | ZBX_VMWARE_STATE_UPDATING_PERF \
							| ZBX_VMWARE_STATE_REMOVING)

/* the vmware performance counter state */
#define ZBX_VMWARE_COUNTER_NEW		0x00
#define ZBX_VMWARE_COUNTER_READY	0x01
#define ZBX_VMWARE_COUNTER_UPDATING	0x10

#define ZBX_VMWARE_EVENT_KEY_UNINITIALIZED	__UINT64_C(0xffffffffffffffff)

typedef struct
{
	char		*name;
	zbx_uint64_t	value;
}
zbx_str_uint64_pair_t;

ZBX_PTR_VECTOR_DECL(str_uint64_pair, zbx_str_uint64_pair_t)
int	zbx_str_uint64_pair_name_compare(const void *p1, const void *p2);

/* performance counter data */
typedef struct
{
	/* the counter id */
	zbx_uint64_t			counterid;

	/* the counter values for various instances */
	/*    pair->name  - instance                */
	/*    pair->value - value                   */
	zbx_vector_str_uint64_pair_t	values;

	/* the counter state, see ZBX_VMAWRE_COUNTER_* defines */
	unsigned char			state;
}
zbx_vmware_perf_counter_t;

/* an entity monitored with performance counters */
typedef struct
{
	/* entity type: HostSystem or VirtualMachine */
	char			*type;

	/* entity id */
	char			*id;

	/* the performance counter refresh rate */
	int			refresh;

	/* timestamp when the entity was queried last time */
	int			last_seen;

	/* the performance counters to monitor */
	zbx_vector_ptr_t	counters;

	/* the performance counter query instance name */
	char			*query_instance;

	/* error information */
	char			*error;
}
zbx_vmware_perf_entity_t;

typedef struct
{
	zbx_uint64_t	partitionid;
	char		*diskname;
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
	zbx_uint64_t			capacity;
	zbx_uint64_t			free_space;
	zbx_uint64_t			uncommitted;
	zbx_vector_str_uint64_pair_t	hv_uuids_access;
	zbx_vector_vmware_diskextent_t	diskextents;
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
}
zbx_vmware_datacenter_t;

int	vmware_dc_name_compare(const void *d1, const void *d2);
ZBX_PTR_VECTOR_DECL(vmware_datacenter, zbx_vmware_datacenter_t *)

#define ZBX_VMWARE_DEV_TYPE_NIC		1
#define ZBX_VMWARE_DEV_TYPE_DISK	2
typedef struct
{
	int	type;
	char	*instance;
	char	*label;
}
zbx_vmware_dev_t;

/* file system data */
typedef struct
{
	char		*path;
	zbx_uint64_t	capacity;
	zbx_uint64_t	free_space;
}
zbx_vmware_fs_t;

/* the vmware virtual machine data */
typedef struct
{
	char			*uuid;
	char			*id;
	char			**props;
	zbx_vector_ptr_t	devs;
	zbx_vector_ptr_t	file_systems;
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
	char	*id;
	char	*name;
	char	*status;
}
zbx_vmware_cluster_t;

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

	zbx_hashset_t			hvs;
	zbx_hashset_t			vms_index;
	zbx_vector_ptr_t		clusters;
	zbx_vector_ptr_t		events;			/* vector of pointers to zbx_vmware_event_t structures */
	int				max_query_metrics;	/* max count of Datastore perfCounters in one request */
	zbx_vector_vmware_datastore_t	datastores;
	zbx_vector_vmware_datacenter_t	datacenters;
}
zbx_vmware_data_t;

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

	int				lastcheck;
	int				lastperfcheck;

	/* The last vmware service access time. If a service is not accessed for a day it is removed */
	int				lastaccess;

	/* the vmware service instance version */
	char				*version;

	/* the vmware service instance version numeric */
	unsigned short			major_version;
	unsigned short			minor_version;

	/* the vmware service instance fullname */
	char				*fullname;

	/* the performance counters */
	zbx_hashset_t			counters;

	/* list of entities to monitor with performance counters */
	zbx_hashset_t			entities;

	/* the service data object that is swapped with a new one during service update */
	zbx_vmware_data_t		*data;

	/* lastlogsize when vmware.eventlog[] item was polled last time and skip old flag*/
	zbx_vmware_eventlog_state_t	eventlog;
}
zbx_vmware_service_t;

#define ZBX_VMWARE_PERF_INTERVAL_UNKNOWN	0
#define ZBX_VMWARE_PERF_INTERVAL_NONE		-1

/* the vmware collector data */
typedef struct
{
	zbx_vector_ptr_t	services;
	zbx_hashset_t		strpool;
	zbx_uint64_t		strpool_sz;
}
zbx_vmware_t;

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

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

zbx_vmware_service_t	*zbx_vmware_get_service(const char* url, const char* username, const char* password);

int	zbx_vmware_service_get_counterid(zbx_vmware_service_t *service, const char *path, zbx_uint64_t *counterid,
		int *unit);
int	zbx_vmware_service_add_perf_counter(zbx_vmware_service_t *service, const char *type, const char *id,
		zbx_uint64_t counterid, const char *instance);
zbx_vmware_perf_entity_t	*zbx_vmware_service_get_perf_entity(zbx_vmware_service_t *service, const char *type,
		const char *id);

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

#define ZBX_VMWARE_HVPROPS_NUM				19

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

#define ZBX_VMWARE_VMPROPS_NUM				21

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
