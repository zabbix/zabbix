/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "common.h"
#include "threads.h"

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

/* performance counter data */
typedef struct
{
	/* the counter id */
	zbx_uint64_t		counterid;

	/* the counter values for various instances */
	/*    pair->first  - instance               */
	/*    pair->second - value                  */
	zbx_vector_ptr_pair_t	values;

	/* the counter state, see ZBX_VMAWRE_COUNTER_* defines */
	unsigned char		state;
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
}
zbx_vmware_perf_entity_t;

typedef struct
{
	char	*name;
	char	*uuid;
}
zbx_vmware_datastore_t;

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
	char			*uuid;
	char			*id;
	char			*clusterid;
	char			*datacenter_name;
	char			**props;
	zbx_vector_ptr_t	datastores;
	zbx_vector_ptr_t	vms;
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

/* the vmware service data object */
typedef struct
{
	char	*error;
	char	*events;

	zbx_hashset_t		hvs;
	zbx_hashset_t		vms_index;
	zbx_vector_ptr_t	clusters;
}
zbx_vmware_data_t;

/* the vmware service data */
typedef struct
{
	char			*url;
	char			*username;
	char			*password;

	/* the service type - vCenter or vSphere */
	unsigned char		type;

	/* the service state - see ZBX_VMWARE_STATE_* defines */
	int			state;

	int			lastcheck;
	int			lastperfcheck;

	/* The last vmware service access time. If a service is not accessed for a day it is removed */
	int			lastaccess;

	/* the vmware service instance contents */
	char			*contents;

	/* the performance counters */
	zbx_hashset_t 		counters;

	/* list of entities to monitor with performance counters */
	zbx_hashset_t		entities;

	/* the service data object that is swapped with a new one during service update */
	zbx_vmware_data_t	*data;
}
zbx_vmware_service_t;

/* the vmware collector data */
typedef struct
{
	zbx_vector_ptr_t	services;
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

void	zbx_vmware_init(void);
void	zbx_vmware_destroy(void);

void	zbx_vmware_lock(void);
void	zbx_vmware_unlock(void);

int	zbx_vmware_get_statistics(zbx_vmware_stats_t *stats);

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

zbx_vmware_service_t	*zbx_vmware_get_service(const char* url, const char* username, const char* password);

int	zbx_vmware_service_get_counterid(zbx_vmware_service_t *service, const char *path, zbx_uint64_t *counterid);
int	zbx_vmware_service_add_perf_counter(zbx_vmware_service_t *service, const char *type, const char *id,
		zbx_uint64_t counterid);
zbx_vmware_perf_entity_t	*zbx_vmware_service_get_perf_entity(zbx_vmware_service_t *service, const char *type,
		const char *id);

#define ZBX_VM_NONAME_XML	"noname.xml"

#define ZBX_XPATH_VM_QUICKSTATS(property)								\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary']]"		\
		"/*[local-name()='val']/*[local-name()='quickStats']/*[local-name()='" property "']"

#define ZBX_XPATH_VM_RUNTIME(property)									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary']]"		\
		"/*[local-name()='val']/*[local-name()='runtime']/*[local-name()='" property "']"

#define ZBX_XPATH_VM_CONFIG(property)									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary']]"		\
		"/*[local-name()='val']/*[local-name()='config']/*[local-name()='" property "']"

#define ZBX_XPATH_VM_STORAGE(property)									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary']]"		\
		"/*[local-name()='val']/*[local-name()='storage']/*[local-name()='" property "']"

#define ZBX_XPATH_VM_HARDWARE(property)									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.hardware']]"	\
		"/*[local-name()='val']/*[local-name()='" property "']"

#define ZBX_XPATH_VM_GUESTDISKS()									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='guest.disk']]"		\
	"/*/*[local-name()='GuestDiskInfo']"

#define ZBX_XPATH_VM_UUID()										\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.uuid']]"		\
		"/*[local-name()='val']"

#define ZBX_XPATH_VM_INSTANCE_UUID()									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.instanceUuid']]"	\
		"/*[local-name()='val']"

#define ZBX_XPATH_HV_QUICKSTATS(property)								\
	"/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary.quickStats']]"	\
		"/*[local-name()='val']/*[local-name()='" property "']"

#define ZBX_XPATH_HV_CONFIG(property)									\
	"/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary.config']]"		\
		"/*[local-name()='val']/*[local-name()='" property "']"

#define ZBX_XPATH_HV_CONFIG_PRODUCT(property)								\
	"/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary.config']]"		\
		"/*[local-name()='val']/*[local-name()='product']"					\
		"/*[local-name()='" property "']"

#define ZBX_XPATH_HV_HARDWARE(property)									\
	"/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary.hardware']]"		\
		"/*[local-name()='val']/*[local-name()='" property "']"

#define ZBX_XPATH_HV_SENSOR_STATUS(sensor)								\
	"/*/*/*/*/*[local-name()='propSet'][*[local-name()='name']"					\
		"[text()='runtime.healthSystemRuntime.systemHealthInfo']]"				\
		"/*[local-name()='val']/*[local-name()='numericSensorInfo']"				\
		"[*[local-name()='name'][text()='" sensor "']]"						\
		"/*[local-name()='healthState']/*[local-name()='key']"

#define ZBX_XPATH_HV_STATUS()										\
	"/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='overallStatus']]"		\
		"/*[local-name()='val']"

#define ZBX_XPATH_VMWARE_ABOUT(property)								\
	"/*/*/*/*/*[local-name()='about']/*[local-name()='" property "']"

#	define ZBX_XPATH_LN(LN)			"/*[local-name()='" LN "']"
#	define ZBX_XPATH_LN1(LN1)		"/" ZBX_XPATH_LN(LN1)
#	define ZBX_XPATH_LN2(LN1, LN2)		"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2)
#	define ZBX_XPATH_LN3(LN1, LN2, LN3)	"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2) ZBX_XPATH_LN(LN3)

char	*zbx_xml_read_value(const char *data, const char *xpath);
int	zbx_xml_read_values(const char *data, const char *xpath, zbx_vector_str_t *values);

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

#define ZBX_VMWARE_HVPROPS_NUM				16

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

#define ZBX_VMWARE_VMPROPS_NUM				16

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_H */
