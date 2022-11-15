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

#include "vmware.h"

#include "mutexs.h"

/* LIBXML2 is used */
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/xpath.h>
#endif

#include "memalloc.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"
#include "../../libs/zbxalgo/vectorimpl.h"

/*
 * The VMware data (zbx_vmware_service_t structure) are stored in shared memory.
 * This data can be accessed with zbx_vmware_get_service() function and is regularly
 * updated by VMware collector processes.
 *
 * When a new service is requested by poller the zbx_vmware_get_service() function
 * creates a new service object, marks it as new, but still returns NULL object.
 *
 * The collectors check the service object list for new services or services not updated
 * during last CONFIG_VMWARE_FREQUENCY seconds. If such service is found it is marked
 * as updating.
 *
 * The service object is updated by creating a new data object, initializing it
 * with the latest data from VMware vCenter (or Hypervisor), destroying the old data
 * object and replacing it with the new one.
 *
 * The collector must be locked only when accessing service object list and working with
 * a service object. It is not locked for new data object creation during service update,
 * which is the most time consuming task.
 *
 * As the data retrieved by VMware collector can be quite big (for example 1 Hypervisor
 * with 500 Virtual Machines will result in approximately 20 MB of data), VMware collector
 * updates performance data (which is only 10% of the structure data) separately
 * with CONFIG_VMWARE_PERF_FREQUENCY period. The performance data is stored directly
 * in VMware service object entities vector - so the structure data is not affected by
 * performance data updates.
 */

extern int		CONFIG_VMWARE_FREQUENCY;
extern int		CONFIG_VMWARE_PERF_FREQUENCY;
extern zbx_uint64_t	CONFIG_VMWARE_CACHE_SIZE;
extern int		CONFIG_VMWARE_TIMEOUT;

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
extern char				*CONFIG_SOURCE_IP;

#define VMWARE_VECTOR_CREATE(ref, type)	zbx_vector_##type##_create_ext(ref,  __vm_mem_malloc_func, \
		__vm_mem_realloc_func, __vm_mem_free_func)

#define ZBX_VMWARE_CACHE_UPDATE_PERIOD	CONFIG_VMWARE_FREQUENCY
#define ZBX_VMWARE_PERF_UPDATE_PERIOD	CONFIG_VMWARE_PERF_FREQUENCY
#define ZBX_VMWARE_SERVICE_TTL		SEC_PER_HOUR
#define ZBX_XML_DATETIME		26
#define ZBX_INIT_UPD_XML_SIZE		(100 * ZBX_KIBIBYTE)
#define zbx_xml_free_doc(xdoc)		if (NULL != xdoc)\
						xmlFreeDoc(xdoc)
#define ZBX_VMWARE_DS_REFRESH_VERSION	6

static zbx_mutex_t	vmware_lock = ZBX_MUTEX_NULL;

static zbx_mem_info_t	*vmware_mem = NULL;

ZBX_MEM_FUNC_IMPL(__vm, vmware_mem)

static zbx_vmware_t	*vmware = NULL;

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

/* according to libxml2 changelog XML_PARSE_HUGE option was introduced in version 2.7.0 */
#if 20700 <= LIBXML_VERSION	/* version 2.7.0 */
#	define ZBX_XML_PARSE_OPTS	XML_PARSE_HUGE
#else
#	define ZBX_XML_PARSE_OPTS	0
#endif

#define ZBX_VMWARE_COUNTERS_INIT_SIZE	500

#define ZBX_VPXD_STATS_MAXQUERYMETRICS				64
#define ZBX_MAXQUERYMETRICS_UNLIMITED				1000
#define ZBX_VCENTER_LESS_THAN_6_5_0_STATS_MAXQUERYMETRICS	64
#define ZBX_VCENTER_6_5_0_AND_MORE_STATS_MAXQUERYMETRICS	256

ZBX_PTR_VECTOR_IMPL(str_uint64_pair, zbx_str_uint64_pair_t)
ZBX_PTR_VECTOR_IMPL(vmware_datastore, zbx_vmware_datastore_t *)
ZBX_PTR_VECTOR_IMPL(vmware_datacenter, zbx_vmware_datacenter_t *)
ZBX_PTR_VECTOR_IMPL(vmware_diskextent, zbx_vmware_diskextent_t *)
ZBX_VECTOR_IMPL(vmware_hvdisk, zbx_vmware_hvdisk_t)
ZBX_PTR_VECTOR_IMPL(vmware_dsname, zbx_vmware_dsname_t *)

/* VMware service object name mapping for vcenter and vsphere installations */
typedef struct
{
	const char	*performance_manager;
	const char	*session_manager;
	const char	*event_manager;
	const char	*property_collector;
	const char	*root_folder;
}
zbx_vmware_service_objects_t;

static zbx_vmware_service_objects_t	vmware_service_objects[3] =
{
	{NULL, NULL, NULL, NULL, NULL},
	{"ha-perfmgr", "ha-sessionmgr", "ha-eventmgr", "ha-property-collector", "ha-folder-root"},
	{"PerfMgr", "SessionManager", "EventManager", "propertyCollector", "group-d1"}
};

/* mapping of performance counter group/key[rollup type] to its id (net/transmitted[average] -> <id>) */
typedef struct
{
	char		*path;
	zbx_uint64_t	id;
	int		unit;
}
zbx_vmware_counter_t;

/* performance counter value for a specific instance */
typedef struct
{
	zbx_uint64_t	counterid;
	char		*instance;
	zbx_uint64_t	value;
}
zbx_vmware_perf_value_t;

/* performance data for a performance collector entity */
typedef struct
{
	/* entity type: HostSystem, Datastore or VirtualMachine */
	char			*type;

	/* entity id */
	char			*id;

	/* the performance counter values (see zbx_vmware_perfvalue_t) */
	zbx_vector_ptr_t	values;

	/* error information */
	char			*error;
}
zbx_vmware_perf_data_t;

typedef struct
{
	zbx_uint64_t	id;
	xmlNode		*xml_node;
}
zbx_id_xmlnode_t;

/* VMware events host information */
typedef struct
{
	const char	*node_name;
	int		flag;
	char		*name;
}
event_hostinfo_node_t;

#define ZBX_HOSTINFO_NODES_DATACENTER		0x01
#define ZBX_HOSTINFO_NODES_COMPRES		0x02
#define ZBX_HOSTINFO_NODES_HOST			0x04
#define ZBX_HOSTINFO_NODES_MASK_ALL		\
		(ZBX_HOSTINFO_NODES_DATACENTER | ZBX_HOSTINFO_NODES_COMPRES | ZBX_HOSTINFO_NODES_HOST)

ZBX_VECTOR_DECL(id_xmlnode, zbx_id_xmlnode_t)
ZBX_VECTOR_IMPL(id_xmlnode, zbx_id_xmlnode_t)

static zbx_hashset_t	evt_msg_strpool;

static zbx_uint64_t	evt_req_chunk_size;

/*
 * SOAP support
 */
#define	ZBX_XML_HEADER1		"Soapaction:urn:vim25/4.1"
#define ZBX_XML_HEADER2		"Content-Type:text/xml; charset=utf-8"
/* cURL specific attribute to prevent the use of "Expect" directive */
/* according to RFC 7231/5.1.1 if xml request is larger than 1k */
#define ZBX_XML_HEADER3		"Expect:"

#define ZBX_POST_VSPHERE_HEADER									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"					\
		"<SOAP-ENV:Envelope"								\
			" xmlns:ns0=\"urn:vim25\""						\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""		\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""		\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"	\
			"<SOAP-ENV:Header/>"							\
			"<ns1:Body>"
#define ZBX_POST_VSPHERE_FOOTER									\
			"</ns1:Body>"								\
		"</SOAP-ENV:Envelope>"

#define ZBX_XPATH_FAULTSTRING()										\
	"/*/*/*[local-name()='Fault']/*[local-name()='faultstring']"

#define ZBX_XPATH_REFRESHRATE()										\
	"/*/*/*/*/*[local-name()='refreshRate' and ../*[local-name()='currentSupported']='true']"

#define ZBX_XPATH_ISAGGREGATE()										\
	"/*/*/*/*/*[local-name()='entity'][../*[local-name()='summarySupported']='true' and "		\
	"../*[local-name()='currentSupported']='false']"

#define ZBX_XPATH_COUNTERINFO()										\
	"/*/*/*/*/*/*[local-name()='propSet']/*[local-name()='val']/*[local-name()='PerfCounterInfo']"

#define ZBX_XPATH_HV_DATASTORES()									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='datastore']]"		\
	"/*[local-name()='val']/*[@type='Datastore']"

#define ZBX_XPATH_HV_VMS()										\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='vm']]"			\
	"/*[local-name()='val']/*[@type='VirtualMachine']"

#define ZBX_XPATH_DATASTORE_SUMMARY(property)								\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary']]"		\
		"/*[local-name()='val']/*[local-name()='" property "']"

#define ZBX_XPATH_MAXQUERYMETRICS()									\
	"/*/*/*/*[*[local-name()='key']='config.vpxd.stats.maxQueryMetrics']/*[local-name()='value']"

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

#define ZBX_XPATH_HV_SENSOR_STATUS(node, sensor)							\
	ZBX_XPATH_PROP_NAME(node) "/*[local-name()='HostNumericSensorInfo']"				\
		"[*[local-name()='name'][text()='" sensor "']]"						\
		"/*[local-name()='healthState']/*[local-name()='key']"

#define ZBX_XPATH_EVT_INFO(param)									\
	"*[local-name()='" param "']/*[local-name()='name']"

#define ZBX_XPATH_EVT_ARGUMENT(key)									\
	"*[local-name()='arguments'][*[local-name()='key'][text()='" key "']]/*[local-name()='value']"

#define ZBX_XPATH_VMWARE_ABOUT(property)								\
	"/*/*/*/*/*[local-name()='about']/*[local-name()='" property "']"

#define ZBX_XPATH_HV_SCSI_TOPOLOGY									\
		"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name']"				\
		"[text()='config.storageDevice.scsiTopology']][1]"					\
		"/*[local-name()='val']/*[local-name()='adapter']/*[local-name()='target']"		\
		"/*[local-name()='lun']/*[local-name()='scsiLun']"

#define ZBX_XPATH_HV_LUN()										\
		"substring-before(substring-after(/*/*/*/*/*/*[local-name()='propSet']"			\
		"[*[local-name()='val'][text()='%s']][1]/*[local-name()='name'],'\"'),'\"')"

#define ZBX_XPATH_HV_MULTIPATH(state)									\
		"count(/*/*/*/*/*/*[local-name()='propSet'][1]/*[local-name()='val']"			\
		"/*[local-name()='lun'][*[local-name()='lun'][text()='%s']][1]"				\
		"/*[local-name()='path']" state ")"

#define ZBX_XPATH_HV_MULTIPATH_PATHS()	ZBX_XPATH_HV_MULTIPATH("")
#define ZBX_XPATH_HV_MULTIPATH_ACTIVE_PATHS()								\
		ZBX_XPATH_HV_MULTIPATH("[*[local-name()='state'][text()='active']]")

#define ZBX_XPATH_DS_INFO_EXTENT()									\
		ZBX_XPATH_PROP_NAME("info") "/*/*[local-name()='extent']"

#	define ZBX_XNN(NN)			"*[local-name()='" NN "']"
#	define ZBX_XPATH_NN(NN)			ZBX_XNN(NN)
#	define ZBX_XPATH_LN(LN)			"/" ZBX_XPATH_NN(LN)
#	define ZBX_XPATH_LN1(LN1)		"/" ZBX_XPATH_LN(LN1)
#	define ZBX_XPATH_LN2(LN1, LN2)		"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2)
#	define ZBX_XPATH_LN3(LN1, LN2, LN3)	"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2) ZBX_XPATH_LN(LN3)

#define ZBX_XPATH_PROP_OBJECTS_ID(type, id)								\
	"/*/*/*/*/*[local-name()='objects'][*[local-name()='obj'][@type='" type "']" id "][1]"

#define ZBX_XPATH_PROP_OBJECTS(type)	ZBX_XPATH_PROP_OBJECTS_ID(type, "") "/"

#define ZBX_XPATH_PROP_NAME_NODE(property)								\
	"*[local-name()='propSet'][*[local-name()='name'][text()='" property "']]/*[local-name()='val']"

#define ZBX_XPATH_PROP_NAME(property)									\
	"/*/*/*/*/*/" ZBX_XPATH_PROP_NAME_NODE(property)

#define ZBX_XPATH_PROP_SUFFIX(property)									\
	"*[local-name()='propSet'][*[local-name()='name']"						\
	"[substring(text(),string-length(text())-string-length('" property "')+1)='" property "']]"	\
	"/*[local-name()='val']"

#define ZBX_VM_NONAME_XML	"noname.xml"

#define ZBX_HVPROPMAP_EXT(property, func)								\
	{property, ZBX_XPATH_PROP_OBJECTS(ZBX_VMWARE_SOAP_HV) ZBX_XPATH_PROP_NAME_NODE(property), func}
#define ZBX_HVPROPMAP(property)										\
	ZBX_HVPROPMAP_EXT(property, NULL)
#define ZBX_VMPROPMAP(property)										\
	{property, ZBX_XPATH_PROP_OBJECTS(ZBX_VMWARE_SOAP_VM) ZBX_XPATH_PROP_NAME_NODE(property), NULL}

typedef int	(*nodeprocfunc_t)(void *, char **);

typedef struct
{
	const char	*name;
	const char	*xpath;
	nodeprocfunc_t	func;
}
zbx_vmware_propmap_t;

static zbx_vmware_propmap_t	hv_propmap[] = {
	ZBX_HVPROPMAP("summary.quickStats.overallCpuUsage"),	/* ZBX_VMWARE_HVPROP_OVERALL_CPU_USAGE */
	ZBX_HVPROPMAP("summary.config.product.fullName"),	/* ZBX_VMWARE_HVPROP_FULL_NAME */
	ZBX_HVPROPMAP("summary.hardware.numCpuCores"),		/* ZBX_VMWARE_HVPROP_HW_NUM_CPU_CORES */
	ZBX_HVPROPMAP("summary.hardware.cpuMhz"),		/* ZBX_VMWARE_HVPROP_HW_CPU_MHZ */
	ZBX_HVPROPMAP("summary.hardware.cpuModel"),		/* ZBX_VMWARE_HVPROP_HW_CPU_MODEL */
	ZBX_HVPROPMAP("summary.hardware.numCpuThreads"), 	/* ZBX_VMWARE_HVPROP_HW_NUM_CPU_THREADS */
	ZBX_HVPROPMAP("summary.hardware.memorySize"), 		/* ZBX_VMWARE_HVPROP_HW_MEMORY_SIZE */
	ZBX_HVPROPMAP("summary.hardware.model"), 		/* ZBX_VMWARE_HVPROP_HW_MODEL */
	ZBX_HVPROPMAP("summary.hardware.uuid"), 		/* ZBX_VMWARE_HVPROP_HW_UUID */
	ZBX_HVPROPMAP("summary.hardware.vendor"), 		/* ZBX_VMWARE_HVPROP_HW_VENDOR */
	ZBX_HVPROPMAP("summary.quickStats.overallMemoryUsage"),	/* ZBX_VMWARE_HVPROP_MEMORY_USED */
	{NULL, ZBX_XPATH_HV_SENSOR_STATUS("runtime.healthSystemRuntime.systemHealthInfo.numericSensorInfo",
			"VMware Rollup Health State"), NULL},	/* ZBX_VMWARE_HVPROP_HEALTH_STATE */
	ZBX_HVPROPMAP("summary.quickStats.uptime"),		/* ZBX_VMWARE_HVPROP_UPTIME */
	ZBX_HVPROPMAP("summary.config.product.version"),		/* ZBX_VMWARE_HVPROP_VERSION */
	ZBX_HVPROPMAP("summary.config.name"),			/* ZBX_VMWARE_HVPROP_NAME */
	ZBX_HVPROPMAP("overallStatus"),				/* ZBX_VMWARE_HVPROP_STATUS */
	ZBX_HVPROPMAP("runtime.inMaintenanceMode"),		/* ZBX_VMWARE_HVPROP_MAINTENANCE */
	ZBX_HVPROPMAP_EXT("summary.runtime.healthSystemRuntime.systemHealthInfo.numericSensorInfo",
			zbx_xmlnode_to_json),			/* ZBX_VMWARE_HVPROP_SENSOR */
	{"config.network.dnsConfig", "concat("			/* ZBX_VMWARE_HVPROP_NET_NAME */
			ZBX_XPATH_PROP_NAME("config.network.dnsConfig") "/*[local-name()='hostName']" ",'.',"
			ZBX_XPATH_PROP_NAME("config.network.dnsConfig") "/*[local-name()='domainName'])", NULL}
};

static zbx_vmware_propmap_t	vm_propmap[] = {
	ZBX_VMPROPMAP("summary.config.numCpu"),			/* ZBX_VMWARE_VMPROP_CPU_NUM */
	ZBX_VMPROPMAP("summary.quickStats.overallCpuUsage"),	/* ZBX_VMWARE_VMPROP_CPU_USAGE */
	ZBX_VMPROPMAP("summary.config.name"),			/* ZBX_VMWARE_VMPROP_NAME */
	ZBX_VMPROPMAP("summary.config.memorySizeMB"),		/* ZBX_VMWARE_VMPROP_MEMORY_SIZE */
	ZBX_VMPROPMAP("summary.quickStats.balloonedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED */
	ZBX_VMPROPMAP("summary.quickStats.compressedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_COMPRESSED */
	ZBX_VMPROPMAP("summary.quickStats.swappedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_SWAPPED */
	ZBX_VMPROPMAP("summary.quickStats.guestMemoryUsage"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_GUEST */
	ZBX_VMPROPMAP("summary.quickStats.hostMemoryUsage"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_HOST */
	ZBX_VMPROPMAP("summary.quickStats.privateMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_PRIVATE */
	ZBX_VMPROPMAP("summary.quickStats.sharedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_SHARED */
	ZBX_VMPROPMAP("summary.runtime.powerState"),		/* ZBX_VMWARE_VMPROP_POWER_STATE */
	ZBX_VMPROPMAP("summary.storage.committed"),		/* ZBX_VMWARE_VMPROP_STORAGE_COMMITED */
	ZBX_VMPROPMAP("summary.storage.unshared"),		/* ZBX_VMWARE_VMPROP_STORAGE_UNSHARED */
	ZBX_VMPROPMAP("summary.storage.uncommitted"),		/* ZBX_VMWARE_VMPROP_STORAGE_UNCOMMITTED */
	ZBX_VMPROPMAP("summary.quickStats.uptimeSeconds"),	/* ZBX_VMWARE_VMPROP_UPTIME */
	ZBX_VMPROPMAP("guest.ipAddress"),			/* ZBX_VMWARE_VMPROP_IPADDRESS */
	ZBX_VMPROPMAP("guest.hostName"),			/* ZBX_VMWARE_VMPROP_GUESTHOSTNAME */
	ZBX_VMPROPMAP("guest.guestFamily"),			/* ZBX_VMWARE_VMPROP_GUESTFAMILY */
	ZBX_VMPROPMAP("guest.guestFullName"),			/* ZBX_VMWARE_VMPROP_GUESTFULLNAME */
	ZBX_VMPROPMAP("parent")					/* ZBX_VMWARE_VMPROP_FOLDER */
};

#define ZBX_XPATH_OBJECTS_BY_TYPE(type)									\
	"/*/*/*/*/*[local-name()='objects'][*[local-name()='obj'][@type='" type "']]"

#define ZBX_XPATH_OBJS_BY_TYPE(type)									\
	"/*/*/*/*/*[local-name()='objects']/*[local-name()='obj'][@type='" type "']"

#define ZBX_XPATH_NAME_BY_TYPE(type)									\
	ZBX_XPATH_PROP_OBJECTS(type) "*[local-name()='propSet'][*[local-name()='name']]"		\
	"/*[local-name()='val']"

#define ZBX_XPATH_HV_PARENTID										\
	ZBX_XPATH_PROP_OBJECTS(ZBX_VMWARE_SOAP_HV) ZBX_XPATH_PROP_NAME_NODE("parent")

#define ZBX_XPATH_HV_PARENTFOLDERNAME(parent_id)							\
	"/*/*/*/*/*[local-name()='objects']["								\
		"*[local-name()='obj'][@type='Folder'] and "						\
		"*[local-name()='propSet'][*[local-name()='name'][text()='childEntity']]"		\
		"/*[local-name()='val']/*[local-name()='ManagedObjectReference']=" parent_id " and "	\
		"*[local-name()='propSet'][*[local-name()='name'][text()='parent']]"			\
		"/*[local-name()='val'][@type!='Datacenter']"						\
	"]/*[local-name()='propSet'][*[local-name()='name'][text()='name']]/*[local-name()='val']"

#define ZBX_XPATH_GET_FOLDER_NAME(id)									\
		ZBX_XPATH_PROP_OBJECTS_ID(ZBX_VMWARE_SOAP_FOLDER, "[text()='" id "']") "/"		\
		ZBX_XPATH_PROP_NAME_NODE("name")

#define ZBX_XPATH_GET_FOLDER_PARENTID(id)								\
		ZBX_XPATH_PROP_OBJECTS_ID(ZBX_VMWARE_SOAP_FOLDER, "[text()='" id "']") "/"		\
		ZBX_XPATH_PROP_NAME_NODE("parent") "[@type='Folder']"

/* hypervisor hashset support */
static zbx_hash_t	vmware_hv_hash(const void *data)
{
	const zbx_vmware_hv_t	*hv = (const zbx_vmware_hv_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(hv->uuid, strlen(hv->uuid), ZBX_DEFAULT_HASH_SEED);
}

static int	vmware_hv_compare(const void *d1, const void *d2)
{
	const zbx_vmware_hv_t	*hv1 = (const zbx_vmware_hv_t *)d1;
	const zbx_vmware_hv_t	*hv2 = (const zbx_vmware_hv_t *)d2;

	return strcmp(hv1->uuid, hv2->uuid);
}

/* virtual machine index support */
static zbx_hash_t	vmware_vm_hash(const void *data)
{
	const zbx_vmware_vm_index_t	*vmi = (const zbx_vmware_vm_index_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(vmi->vm->uuid, strlen(vmi->vm->uuid), ZBX_DEFAULT_HASH_SEED);
}

static int	vmware_vm_compare(const void *d1, const void *d2)
{
	const zbx_vmware_vm_index_t	*vmi1 = (const zbx_vmware_vm_index_t *)d1;
	const zbx_vmware_vm_index_t	*vmi2 = (const zbx_vmware_vm_index_t *)d2;

	return strcmp(vmi1->vm->uuid, vmi2->vm->uuid);
}

/* string pool support */

#define REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

#define evt_msg_strpool_strdup(str, len)	vmware_strpool_strdup(str, &evt_msg_strpool, len)

static zbx_hash_t	vmware_strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((const char *)data + REFCOUNT_FIELD_SIZE);
}

static int	vmware_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp((const char *)d1 + REFCOUNT_FIELD_SIZE, (const char *)d2 + REFCOUNT_FIELD_SIZE);
}

static int	vmware_shared_strsearch(const char *str)
{
	return NULL == zbx_hashset_search(&vmware->strpool, str - REFCOUNT_FIELD_SIZE) ? FAIL : SUCCEED;
}

static char	*vmware_strpool_strdup(const char *str, zbx_hashset_t *strpool, zbx_uint64_t *len)
{
	void	*ptr;

	if (NULL != len)
		*len = 0;

	if (NULL == str)
		return NULL;

	ptr = zbx_hashset_search(strpool, str - REFCOUNT_FIELD_SIZE);

	if (NULL == ptr)
	{
		zbx_uint64_t	sz;

		sz = REFCOUNT_FIELD_SIZE + strlen(str) + 1;
		ptr = zbx_hashset_insert_ext(strpool, str - REFCOUNT_FIELD_SIZE, sz, REFCOUNT_FIELD_SIZE);

		*(zbx_uint32_t *)ptr = 0;

		if (NULL != len)
			*len = sz + ZBX_HASHSET_ENTRY_OFFSET;
	}

	(*(zbx_uint32_t *)ptr)++;

	return (char *)ptr + REFCOUNT_FIELD_SIZE;
}

static char	*vmware_shared_strdup(const char *str)
{
	char		*strdup;
	zbx_uint64_t	len;

	strdup = vmware_strpool_strdup(str, &vmware->strpool, &len);

	if (0 < len)
		vmware->strpool_sz += zbx_mem_required_chunk_size(len);

	return strdup;
}

static void	vmware_strpool_strfree(char *str, zbx_hashset_t *strpool, zbx_uint64_t *len)
{
	if (NULL != len)
		*len = 0;

	if (NULL != str)
	{
		void	*ptr = str - REFCOUNT_FIELD_SIZE;

		if (0 == --(*(zbx_uint32_t *)ptr))
		{
			if (NULL != len)
				*len = REFCOUNT_FIELD_SIZE + strlen(str) + 1 + ZBX_HASHSET_ENTRY_OFFSET;

			zbx_hashset_remove_direct(strpool, ptr);
		}
	}
}

static void	vmware_shared_strfree(char *str)
{
	zbx_uint64_t	len;

	vmware_strpool_strfree(str, &vmware->strpool, &len);

	if (0 < len)
		vmware->strpool_sz -= zbx_mem_required_chunk_size(len);
}

static void	evt_msg_strpool_strfree(char *str)
{
	vmware_strpool_strfree(str, &evt_msg_strpool, NULL);
}

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
ZBX_HTTPPAGE;

static int	zbx_xml_read_values(xmlDoc *xdoc, const char *xpath, zbx_vector_str_t *values);
static int	zbx_xml_node_read_values(xmlDoc *xdoc, xmlNode *node, const char *xpath, zbx_vector_str_t *values);
static int	zbx_xml_try_read_value(const char *data, size_t len, const char *xpath, xmlDoc **xdoc, char **value,
		char **error);
static int	zbx_xml_doc_read_num(xmlDoc *xdoc, const char *xpath, int *num);
static char	*zbx_xml_node_read_value(xmlDoc *doc, xmlNode *node, const char *xpath);
static char	*zbx_xml_doc_read_value(xmlDoc *xdoc, const char *xpath);

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t		r_size = size * nmemb;
	ZBX_HTTPPAGE	*page_http = (ZBX_HTTPPAGE *)userdata;

	zbx_strncpy_alloc(&page_http->data, &page_http->alloc, &page_http->offset, (const char *)ptr, r_size);

	return r_size;
}

static size_t	curl_header_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free memory of vector element                                     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_str_uint64_pair_free(zbx_str_uint64_pair_t data)
{
	zbx_free(data.name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort zbx_str_uint64_pair_t vector by name     *
 *                                                                            *
 ******************************************************************************/
int	zbx_str_uint64_pair_name_compare(const void *p1, const void *p2)
{
	const zbx_str_uint64_pair_t	*v1 = (const zbx_str_uint64_pair_t *)p1;
	const zbx_str_uint64_pair_t	*v2 = (const zbx_str_uint64_pair_t *)p2;

	return strcmp(v1->name, v2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: abstracts the curl_easy_setopt/curl_easy_perform call pair        *
 *                                                                            *
 * Parameters: easyhandle - [IN] the CURL handle                              *
 *             request    - [IN] the http request                             *
 *             response   - [OUT] the http response                           *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the http request was completed successfully        *
 *               FAIL    - the http request has failed                        *
 ******************************************************************************/
static int	zbx_http_post(CURL *easyhandle, const char *request, ZBX_HTTPPAGE **response, char **error)
{
	CURLoption	opt;
	CURLcode	err;
	ZBX_HTTPPAGE	*resp;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, request)))
	{
		if (NULL != error)
			*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));

		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_PRIVATE, (char **)&resp)))
	{
		if (NULL != error)
			*error = zbx_dsprintf(*error, "Cannot get response buffer: %s.", curl_easy_strerror(err));

		return FAIL;
	}

	resp->offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		if (NULL != error)
			*error = zbx_strdup(*error, curl_easy_strerror(err));

		return FAIL;
	}

	*response = resp;

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Purpose: unification of vmware web service call with SOAP error validation *
 *                                                                            *
 * Parameters: fn_parent  - [IN] the parent function name for Log records     *
 *             easyhandle - [IN] the CURL handle                              *
 *             request    - [IN] the http request                             *
 *             xdoc       - [OUT] the xml document response (optional)        *
 *             token      - [OUT] the soap token for next query (optional)    *
 *             error      - [OUT] the error message in the case of failure    *
 *                                (optional)                                  *
 *                                                                            *
 * Return value: SUCCEED - the SOAP request was completed successfully        *
 *               FAIL    - the SOAP request has failed                        *
 ******************************************************************************/
static int	zbx_soap_post(const char *fn_parent, CURL *easyhandle, const char *request, xmlDoc **xdoc,
		char **token , char **error)
{
#	define ZBX_XPATH_RETRIEVE_PROPERTIES_TOKEN			\
		"/*[local-name()='Envelope']/*[local-name()='Body']"	\
		"/*[local-name()='RetrievePropertiesExResponse']"	\
		"/*[local-name()='returnval']/*[local-name()='token'][1]"

	xmlDoc		*doc;
	ZBX_HTTPPAGE	*resp;
	int		ret = SUCCEED;
	char		*val = NULL;

	if (SUCCEED != zbx_http_post(easyhandle, request, &resp, error))
		return FAIL;

	if (NULL != fn_parent)
		zabbix_log(LOG_LEVEL_TRACE, "%s() SOAP response: %s", fn_parent, resp->data);

	if (SUCCEED == zbx_xml_try_read_value(resp->data, resp->offset, ZBX_XPATH_FAULTSTRING(), &doc, &val, error))
	{
		if (NULL != val)
		{
			zbx_free(*error);
			*error = val;
			ret = FAIL;
		}

		if (NULL != xdoc)
		{
			*xdoc = doc;
		}
		else
		{
			zbx_xml_free_doc(doc);
		}
	}
	else
		ret = FAIL;

	if (SUCCEED == ret && NULL != xdoc)
	{
		char	*tkn;

		tkn = zbx_xml_doc_read_value(*xdoc, ZBX_XPATH_RETRIEVE_PROPERTIES_TOKEN);

		if (NULL != token)
		{
			*token = tkn;
			tkn = NULL;
		}
		else if (NULL != tkn && NULL != fn_parent)
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() SOAP response has next unprocessed page: %s", fn_parent,
					tkn);
		}

		zbx_str_free(tkn);
	}

	return ret;

#	undef ZBX_XPATH_RETRIEVE_PROPERTIES_TOKEN
}

/******************************************************************************
 *                                                                            *
 * performance counter hashset support functions                              *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	vmware_counter_hash_func(const void *data)
{
	const zbx_vmware_counter_t	*counter = (const zbx_vmware_counter_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(counter->path, strlen(counter->path), ZBX_DEFAULT_HASH_SEED);
}

static int	vmware_counter_compare_func(const void *d1, const void *d2)
{
	const zbx_vmware_counter_t	*c1 = (const zbx_vmware_counter_t *)d1;
	const zbx_vmware_counter_t	*c2 = (const zbx_vmware_counter_t *)d2;

	return strcmp(c1->path, c2->path);
}

/******************************************************************************
 *                                                                            *
 * performance entities hashset support functions                             *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	vmware_perf_entity_hash_func(const void *data)
{
	zbx_hash_t	seed;

	const zbx_vmware_perf_entity_t	*entity = (const zbx_vmware_perf_entity_t *)data;

	seed = ZBX_DEFAULT_STRING_HASH_ALGO(entity->type, strlen(entity->type), ZBX_DEFAULT_HASH_SEED);

	return ZBX_DEFAULT_STRING_HASH_ALGO(entity->id, strlen(entity->id), seed);
}

static int	vmware_perf_entity_compare_func(const void *d1, const void *d2)
{
	int	ret;

	const zbx_vmware_perf_entity_t	*e1 = (const zbx_vmware_perf_entity_t *)d1;
	const zbx_vmware_perf_entity_t	*e2 = (const zbx_vmware_perf_entity_t *)d2;

	if (0 == (ret = strcmp(e1->type, e2->type)))
		ret = strcmp(e1->id, e2->id);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees perfvalue data structure                                    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_free_perfvalue(zbx_vmware_perf_value_t *value)
{
	zbx_free(value->instance);
	zbx_free(value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees perfdata data structure                                     *
 *                                                                            *
 ******************************************************************************/
static void	vmware_free_perfdata(zbx_vmware_perf_data_t *data)
{
	zbx_free(data->id);
	zbx_free(data->type);
	zbx_free(data->error);
	zbx_vector_ptr_clear_ext(&data->values, (zbx_mem_free_func_t)vmware_free_perfvalue);
	zbx_vector_ptr_destroy(&data->values);

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads the vmware object properties by their xpaths from xml data  *
 *                                                                            *
 * Parameters: xdoc      - [IN] the xml document                              *
 *             propmap   - [IN] the xpaths of the properties to read          *
 *             props_num - [IN] the number of properties to read              *
 *                                                                            *
 * Return value: an array of property values                                  *
 *                                                                            *
 * Comments: The array with property values must be freed by the caller.      *
 *                                                                            *
 ******************************************************************************/
static char	**xml_read_props(xmlDoc *xdoc, const zbx_vmware_propmap_t *propmap, int props_num)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	char		**props;
	int		i;

	props = (char **)zbx_malloc(NULL, sizeof(char *) * props_num);
	memset(props, 0, sizeof(char *) * props_num);

	for (i = 0; i < props_num; i++)
	{
		xpathCtx = xmlXPathNewContext(xdoc);

		if (NULL != (xpathObj = xmlXPathEvalExpression((const xmlChar *)propmap[i].xpath, xpathCtx)))
		{
			if (XPATH_STRING == xpathObj->type)
			{
				if (NULL != propmap[i].func)
					propmap[i].func((void *)xpathObj->stringval, &props[i]);
				else if ('.' != *xpathObj->stringval)
					props[i] = zbx_strdup(NULL, (const char *)xpathObj->stringval);
			}
			else if (0 == xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
			{
				nodeset = xpathObj->nodesetval;

				if (NULL != propmap[i].func)
				{
					propmap[i].func((void *)nodeset->nodeTab[0], &props[i]);
				}
				else if (NULL != (val = xmlNodeListGetString(xdoc,
						nodeset->nodeTab[0]->xmlChildrenNode, 1)))
				{
					props[i] = zbx_strdup(NULL, (const char *)val);
					xmlFree(val);
				}
			}

			xmlXPathFreeObject(xpathObj);
		}

		xmlXPathFreeContext(xpathCtx);
	}

	return props;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies performance counter vector into shared memory hashset      *
 *                                                                            *
 * Parameters: dst - [IN] the destination hashset                             *
 *             src - [IN] the source vector                                   *
 *                                                                            *
 ******************************************************************************/
static void	vmware_counters_shared_copy(zbx_hashset_t *dst, const zbx_vector_ptr_t *src)
{
	int			i;
	zbx_vmware_counter_t	*csrc, *cdst;

	if (SUCCEED != zbx_hashset_reserve(dst, src->values_num))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	for (i = 0; i < src->values_num; i++)
	{
		csrc = (zbx_vmware_counter_t *)src->values[i];

		cdst = (zbx_vmware_counter_t *)zbx_hashset_insert(dst, csrc, sizeof(zbx_vmware_counter_t));

		/* check if the counter was inserted - copy path only for inserted counters */
		if (cdst->path == csrc->path)
			cdst->path = vmware_shared_strdup(csrc->path);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store instance performance    *
 *          counter values                                                    *
 *                                                                            *
 * Parameters: pairs - [IN] vector of performance counter pairs               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vector_str_uint64_pair_shared_clean(zbx_vector_str_uint64_pair_t *pairs)
{
	int	i;

	for (i = 0; i < pairs->values_num; i++)
	{
		zbx_str_uint64_pair_t	*pair = &pairs->values[i];

		if (NULL != pair->name)
			vmware_shared_strfree(pair->name);
	}

	pairs->values_num = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store performance counter     *
 *          data                                                              *
 *                                                                            *
 * Parameters: counter - [IN] the performance counter data                    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_perf_counter_shared_free(zbx_vmware_perf_counter_t *counter)
{
	vmware_vector_str_uint64_pair_shared_clean(&counter->values);
	zbx_vector_str_uint64_pair_destroy(&counter->values);
	__vm_mem_free_func(counter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes statistics data from vmware entities                      *
 *                                                                            *
 ******************************************************************************/
static void	vmware_entities_shared_clean_stats(zbx_hashset_t *entities)
{
	int				i;
	zbx_vmware_perf_entity_t	*entity;
	zbx_vmware_perf_counter_t	*counter;
	zbx_hashset_iter_t		iter;

	zbx_hashset_iter_reset(entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
	{
		for (i = 0; i < entity->counters.values_num; i++)
		{
			counter = (zbx_vmware_perf_counter_t *)entity->counters.values[i];
			vmware_vector_str_uint64_pair_shared_clean(&counter->values);

			if (0 != (counter->state & ZBX_VMWARE_COUNTER_UPDATING))
				counter->state = ZBX_VMWARE_COUNTER_READY;
		}
		vmware_shared_strfree(entity->error);
		entity->error = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store diskextent data         *
 *                                                                            *
 * Parameters: diskextent   - [IN] the diskextent                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_diskextent_shared_free(zbx_vmware_diskextent_t *diskextent)
{
	vmware_shared_strfree(diskextent->diskname);

	__vm_mem_free_func(diskextent);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store datastore data          *
 *                                                                            *
 * Parameters: datastore   - [IN] the datastore                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datastore_shared_free(zbx_vmware_datastore_t *datastore)
{
	vmware_shared_strfree(datastore->name);
	vmware_shared_strfree(datastore->id);
	vmware_shared_strfree(datastore->uuid);

	vmware_vector_str_uint64_pair_shared_clean(&datastore->hv_uuids_access);
	zbx_vector_str_uint64_pair_destroy(&datastore->hv_uuids_access);

	zbx_vector_vmware_diskextent_clear_ext(&datastore->diskextents, vmware_diskextent_shared_free);
	zbx_vector_vmware_diskextent_destroy(&datastore->diskextents);

	__vm_mem_free_func(datastore);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store datacenter data         *
 *                                                                            *
 * Parameters: datacenter   - [IN] the datacenter                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datacenter_shared_free(zbx_vmware_datacenter_t *datacenter)
{
	vmware_shared_strfree(datacenter->name);
	vmware_shared_strfree(datacenter->id);

	__vm_mem_free_func(datacenter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store properties list         *
 *                                                                            *
 * Parameters: props     - [IN] the properties list                           *
 *             props_num - [IN] the number of properties in the list          *
 *                                                                            *
 ******************************************************************************/
static void	vmware_props_shared_free(char **props, int props_num)
{
	int	i;

	if (NULL == props)
		return;

	for (i = 0; i < props_num; i++)
	{
		if (NULL != props[i])
			vmware_shared_strfree(props[i]);
	}

	__vm_mem_free_func(props);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vm device data          *
 *                                                                            *
 * Parameters: dev   - [IN] the vm device                                     *
 *                                                                            *
 ******************************************************************************/
static void	vmware_dev_shared_free(zbx_vmware_dev_t *dev)
{
	if (NULL != dev->instance)
		vmware_shared_strfree(dev->instance);

	if (NULL != dev->label)
		vmware_shared_strfree(dev->label);

	__vm_mem_free_func(dev);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store file system object      *
 *                                                                            *
 * Parameters: fs   - [IN] the file system                                    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_fs_shared_free(zbx_vmware_fs_t *fs)
{
	if (NULL != fs->path)
		vmware_shared_strfree(fs->path);

	__vm_mem_free_func(fs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store virtual machine         *
 *                                                                            *
 * Parameters: vm   - [IN] the virtual machine                                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_shared_free(zbx_vmware_vm_t *vm)
{
	zbx_vector_ptr_clear_ext(&vm->devs, (zbx_clean_func_t)vmware_dev_shared_free);
	zbx_vector_ptr_destroy(&vm->devs);

	zbx_vector_ptr_clear_ext(&vm->file_systems, (zbx_mem_free_func_t)vmware_fs_shared_free);
	zbx_vector_ptr_destroy(&vm->file_systems);

	if (NULL != vm->uuid)
		vmware_shared_strfree(vm->uuid);

	if (NULL != vm->id)
		vmware_shared_strfree(vm->id);

	vmware_props_shared_free(vm->props, ZBX_VMWARE_VMPROPS_NUM);

	__vm_mem_free_func(vm);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store datastore names data    *
 *                                                                            *
 * Parameters: dsname  - [IN] the datastore name                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_dsname_shared_free(zbx_vmware_dsname_t *dsname)
{
	vmware_shared_strfree(dsname->name);
	vmware_shared_strfree(dsname->uuid);
	zbx_vector_vmware_hvdisk_destroy(&dsname->hvdisks);

	__vm_mem_free_func(dsname);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware hypervisor       *
 *                                                                            *
 * Parameters: hv   - [IN] the vmware hypervisor                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_hv_shared_clean(zbx_vmware_hv_t *hv)
{
	zbx_vector_vmware_dsname_clear_ext(&hv->dsnames, vmware_dsname_shared_free);
	zbx_vector_vmware_dsname_destroy(&hv->dsnames);

	zbx_vector_ptr_clear_ext(&hv->vms, (zbx_clean_func_t)vmware_vm_shared_free);
	zbx_vector_ptr_destroy(&hv->vms);

	if (NULL != hv->uuid)
		vmware_shared_strfree(hv->uuid);

	if (NULL != hv->id)
		vmware_shared_strfree(hv->id);

	if (NULL != hv->clusterid)
		vmware_shared_strfree(hv->clusterid);

	if (NULL != hv->datacenter_name)
		vmware_shared_strfree(hv->datacenter_name);

	if (NULL != hv->parent_name)
		vmware_shared_strfree(hv->parent_name);

	if (NULL != hv->parent_type)
		vmware_shared_strfree(hv->parent_type);

	if (NULL != hv->ip)
		vmware_shared_strfree(hv->ip);

	vmware_props_shared_free(hv->props, ZBX_VMWARE_HVPROPS_NUM);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware cluster          *
 *                                                                            *
 * Parameters: cluster   - [IN] the vmware cluster                            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_cluster_shared_free(zbx_vmware_cluster_t *cluster)
{
	if (NULL != cluster->name)
		vmware_shared_strfree(cluster->name);

	if (NULL != cluster->id)
		vmware_shared_strfree(cluster->id);

	if (NULL != cluster->status)
		vmware_shared_strfree(cluster->status);

	__vm_mem_free_func(cluster);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware event            *
 *                                                                            *
 * Parameters: event - [IN] the vmware event                                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_event_shared_free(zbx_vmware_event_t *event)
{
	if (NULL != event->message)
		vmware_shared_strfree(event->message);

	__vm_mem_free_func(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service data     *
 *                                                                            *
 * Parameters: data   - [IN] the vmware service data                          *
 *                                                                            *
 ******************************************************************************/
static void	vmware_data_shared_free(zbx_vmware_data_t *data)
{
	if (NULL != data)
	{
		zbx_hashset_iter_t	iter;
		zbx_vmware_hv_t		*hv;

		zbx_hashset_iter_reset(&data->hvs, &iter);
		while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
			vmware_hv_shared_clean(hv);

		zbx_hashset_destroy(&data->hvs);
		zbx_hashset_destroy(&data->vms_index);

		zbx_vector_ptr_clear_ext(&data->clusters, (zbx_clean_func_t)vmware_cluster_shared_free);
		zbx_vector_ptr_destroy(&data->clusters);

		zbx_vector_ptr_clear_ext(&data->events, (zbx_clean_func_t)vmware_event_shared_free);
		zbx_vector_ptr_destroy(&data->events);

		zbx_vector_vmware_datastore_clear_ext(&data->datastores, vmware_datastore_shared_free);
		zbx_vector_vmware_datastore_destroy(&data->datastores);

		zbx_vector_vmware_datacenter_clear_ext(&data->datacenters, vmware_datacenter_shared_free);
		zbx_vector_vmware_datacenter_destroy(&data->datacenters);

		if (NULL != data->error)
			vmware_shared_strfree(data->error);

		__vm_mem_free_func(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans resources allocated by vmware performance entity in vmware *
 *          cache                                                             *
 *                                                                            *
 * Parameters: entity - [IN] the entity to free                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_shared_perf_entity_clean(zbx_vmware_perf_entity_t *entity)
{
	zbx_vector_ptr_clear_ext(&entity->counters, (zbx_mem_free_func_t)vmware_perf_counter_shared_free);
	zbx_vector_ptr_destroy(&entity->counters);

	vmware_shared_strfree(entity->query_instance);
	vmware_shared_strfree(entity->type);
	vmware_shared_strfree(entity->id);
	vmware_shared_strfree(entity->error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated by vmware performance counter           *
 *                                                                            *
 * Parameters: counter - [IN] the performance counter to free                 *
 *                                                                            *
 ******************************************************************************/
static void	vmware_counter_shared_clean(zbx_vmware_counter_t *counter)
{
	vmware_shared_strfree(counter->path);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service          *
 *                                                                            *
 * Parameters: data   - [IN] the vmware service data                          *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_shared_free(zbx_vmware_service_t *service)
{
	zbx_hashset_iter_t		iter;
	zbx_vmware_counter_t		*counter;
	zbx_vmware_perf_entity_t	*entity;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	vmware_shared_strfree(service->url);
	vmware_shared_strfree(service->username);
	vmware_shared_strfree(service->password);

	if (NULL != service->version)
		vmware_shared_strfree(service->version);

	if (NULL != service->fullname)
		vmware_shared_strfree(service->fullname);

	vmware_data_shared_free(service->data);

	zbx_hashset_iter_reset(&service->entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
		vmware_shared_perf_entity_clean(entity);

	zbx_hashset_destroy(&service->entities);

	zbx_hashset_iter_reset(&service->counters, &iter);
	while (NULL != (counter = (zbx_vmware_counter_t *)zbx_hashset_iter_next(&iter)))
		vmware_counter_shared_clean(counter);

	zbx_hashset_destroy(&service->counters);

	__vm_mem_free_func(service);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware cluster object into shared memory                   *
 *                                                                            *
 * Parameters: src   - [IN] the vmware cluster object                         *
 *                                                                            *
 * Return value: a copied vmware cluster object                               *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_cluster_t	*vmware_cluster_shared_dup(const zbx_vmware_cluster_t *src)
{
	zbx_vmware_cluster_t	*cluster;

	cluster = (zbx_vmware_cluster_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_cluster_t));
	cluster->id = vmware_shared_strdup(src->id);
	cluster->name = vmware_shared_strdup(src->name);
	cluster->status = vmware_shared_strdup(src->status);

	return cluster;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware event object into shared memory                     *
 *                                                                            *
 * Parameters: src - [IN] the vmware event object                             *
 *                                                                            *
 * Return value: a copied vmware event object                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_event_t	*vmware_event_shared_dup(const zbx_vmware_event_t *src)
{
	zbx_vmware_event_t	*event;

	event = (zbx_vmware_event_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_event_t));
	event->key = src->key;
	event->message = vmware_shared_strdup(src->message);
	event->timestamp = src->timestamp;

	return event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor diskextent object into shared memory     *
 *                                                                            *
 * Parameters: src   - [IN] the vmware diskextent object                      *
 *                                                                            *
 * Return value: a duplicated vmware diskextent object                        *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_diskextent_t	*vmware_diskextent_shared_dup(const zbx_vmware_diskextent_t *src)
{
	zbx_vmware_diskextent_t	*diskextent;

	diskextent = (zbx_vmware_diskextent_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_diskextent_t));
	diskextent->diskname = vmware_shared_strdup(src->diskname);
	diskextent->partitionid = src->partitionid;

	return diskextent;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor datastore object into shared memory      *
 *                                                                            *
 * Parameters: src   - [IN] the vmware datastore object                       *
 *                                                                            *
 * Return value: a duplicated vmware datastore object                         *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_datastore_t	*vmware_datastore_shared_dup(const zbx_vmware_datastore_t *src)
{
	int			i;
	zbx_vmware_datastore_t	*datastore;

	datastore = (zbx_vmware_datastore_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_datastore_t));
	datastore->uuid = vmware_shared_strdup(src->uuid);
	datastore->name = vmware_shared_strdup(src->name);
	datastore->id = vmware_shared_strdup(src->id);
	VMWARE_VECTOR_CREATE(&datastore->hv_uuids_access, str_uint64_pair);
	zbx_vector_str_uint64_pair_reserve(&datastore->hv_uuids_access, src->hv_uuids_access.values_num);
	VMWARE_VECTOR_CREATE(&datastore->diskextents, vmware_diskextent);
	zbx_vector_vmware_diskextent_reserve(&datastore->diskextents, src->diskextents.values_num);

	datastore->capacity = src->capacity;
	datastore->free_space = src->free_space;
	datastore->uncommitted = src->uncommitted;

	for (i = 0; i < src->hv_uuids_access.values_num; i++)
	{
		zbx_str_uint64_pair_t	val;

		val.name = vmware_shared_strdup(src->hv_uuids_access.values[i].name);
		val.value = src->hv_uuids_access.values[i].value;
		zbx_vector_str_uint64_pair_append_ptr(&datastore->hv_uuids_access, &val);
	}

	for (i = 0; i < src->diskextents.values_num; i++)
	{
		zbx_vector_vmware_diskextent_append(&datastore->diskextents,
				vmware_diskextent_shared_dup(src->diskextents.values[i]));
	}

	return datastore;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware datacenter object into shared memory                *
 *                                                                            *
 * Parameters: src   - [IN] the vmware datacenter object                      *
 *                                                                            *
 * Return value: a duplicated vmware datacenter object                        *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_datacenter_t	*vmware_datacenter_shared_dup(const zbx_vmware_datacenter_t *src)
{
	zbx_vmware_datacenter_t	*datacenter;

	datacenter = (zbx_vmware_datacenter_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_datacenter_t));
	datacenter->name = vmware_shared_strdup(src->name);
	datacenter->id = vmware_shared_strdup(src->id);

	return datacenter;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware virtual machine device object into shared memory    *
 *                                                                            *
 * Parameters: src   - [IN] the vmware device object                          *
 *                                                                            *
 * Return value: a duplicated vmware device object                            *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_dev_t	*vmware_dev_shared_dup(const zbx_vmware_dev_t *src)
{
	zbx_vmware_dev_t	*dev;

	dev = (zbx_vmware_dev_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_dev_t));
	dev->type = src->type;
	dev->instance = vmware_shared_strdup(src->instance);
	dev->label = vmware_shared_strdup(src->label);

	return dev;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware virtual machine file system object into shared      *
 *          memory                                                            *
 *                                                                            *
 * Parameters: src   - [IN] the vmware device object                          *
 *                                                                            *
 * Return value: a duplicated vmware device object                            *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_fs_t	*vmware_fs_shared_dup(const zbx_vmware_fs_t *src)
{
	zbx_vmware_fs_t	*fs;

	fs = (zbx_vmware_fs_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_fs_t));
	fs->path = vmware_shared_strdup(src->path);
	fs->capacity = src->capacity;
	fs->free_space = src->free_space;

	return fs;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies object properties list into shared memory                  *
 *                                                                            *
 * Parameters: src       - [IN] the properties list                           *
 *             props_num - [IN] the number of properties in the list          *
 *                                                                            *
 * Return value: a duplicated object properties list                          *
 *                                                                            *
 ******************************************************************************/
static char	**vmware_props_shared_dup(char ** const src, int props_num)
{
	char	**props;
	int	i;

	props = (char **)__vm_mem_malloc_func(NULL, sizeof(char *) * props_num);

	for (i = 0; i < props_num; i++)
		props[i] = vmware_shared_strdup(src[i]);

	return props;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware virtual machine object into shared memory           *
 *                                                                            *
 * Parameters: src   - [IN] the vmware virtual machine object                 *
 *                                                                            *
 * Return value: a duplicated vmware virtual machine object                   *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_vm_t	*vmware_vm_shared_dup(const zbx_vmware_vm_t *src)
{
	zbx_vmware_vm_t	*vm;
	int		i;

	vm = (zbx_vmware_vm_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_vm_t));

	VMWARE_VECTOR_CREATE(&vm->devs, ptr);
	VMWARE_VECTOR_CREATE(&vm->file_systems, ptr);
	zbx_vector_ptr_reserve(&vm->devs, src->devs.values_num);
	zbx_vector_ptr_reserve(&vm->file_systems, src->file_systems.values_num);

	vm->uuid = vmware_shared_strdup(src->uuid);
	vm->id = vmware_shared_strdup(src->id);
	vm->props = vmware_props_shared_dup(src->props, ZBX_VMWARE_VMPROPS_NUM);

	for (i = 0; i < src->devs.values_num; i++)
		zbx_vector_ptr_append(&vm->devs, vmware_dev_shared_dup((zbx_vmware_dev_t *)src->devs.values[i]));

	for (i = 0; i < src->file_systems.values_num; i++)
		zbx_vector_ptr_append(&vm->file_systems, vmware_fs_shared_dup((zbx_vmware_fs_t *)src->file_systems.values[i]));

	return vm;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor datastore name object into shared memory *
 *                                                                            *
 * Parameters: src   - [IN] the vmware datastore name object                  *
 *                                                                            *
 * Return value: a duplicated vmware datastore name object                    *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_dsname_t	*vmware_dsname_shared_dup(const zbx_vmware_dsname_t *src)
{
	zbx_vmware_dsname_t	*dsname;
	int	i;

	dsname = (zbx_vmware_dsname_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_dsname_t));

	dsname->name = vmware_shared_strdup(src->name);
	dsname->uuid = vmware_shared_strdup(src->uuid);

	VMWARE_VECTOR_CREATE(&dsname->hvdisks, vmware_hvdisk);
	zbx_vector_vmware_hvdisk_reserve(&dsname->hvdisks, src->hvdisks.values_num);

	for (i = 0; i < src->hvdisks.values_num; i++)
	{
		zbx_vector_vmware_hvdisk_append(&dsname->hvdisks, src->hvdisks.values[i]);
	}

	return dsname;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware hypervisor object into shared memory                *
 *                                                                            *
 * Parameters: dst - [OUT] the vmware hypervisor object into shared memory    *
 *             src - [IN] the vmware hypervisor object                        *
 *                                                                            *
 ******************************************************************************/
static	void	vmware_hv_shared_copy(zbx_vmware_hv_t *dst, const zbx_vmware_hv_t *src)
{
	int	i;

	VMWARE_VECTOR_CREATE(&dst->dsnames, vmware_dsname);
	VMWARE_VECTOR_CREATE(&dst->vms, ptr);
	zbx_vector_vmware_dsname_reserve(&dst->dsnames, src->dsnames.values_num);
	zbx_vector_ptr_reserve(&dst->vms, src->vms.values_num);

	dst->uuid = vmware_shared_strdup(src->uuid);
	dst->id = vmware_shared_strdup(src->id);
	dst->clusterid = vmware_shared_strdup(src->clusterid);

	dst->props = vmware_props_shared_dup(src->props, ZBX_VMWARE_HVPROPS_NUM);
	dst->datacenter_name = vmware_shared_strdup(src->datacenter_name);
	dst->parent_name = vmware_shared_strdup(src->parent_name);
	dst->parent_type = vmware_shared_strdup(src->parent_type);
	dst->ip = vmware_shared_strdup(src->ip);

	for (i = 0; i < src->dsnames.values_num; i++)
		zbx_vector_vmware_dsname_append(&dst->dsnames, vmware_dsname_shared_dup(src->dsnames.values[i]));

	for (i = 0; i < src->vms.values_num; i++)
		zbx_vector_ptr_append(&dst->vms, vmware_vm_shared_dup((zbx_vmware_vm_t *)src->vms.values[i]));
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware data object into shared memory                      *
 *                                                                            *
 * Parameters: src   - [IN] the vmware data object                            *
 *                                                                            *
 * Return value: a duplicated vmware data object                              *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_data_t	*vmware_data_shared_dup(zbx_vmware_data_t *src)
{
	zbx_vmware_data_t	*data;
	int			i;
	zbx_hashset_iter_t	iter;
	zbx_vmware_hv_t		*hv, hv_local;

	data = (zbx_vmware_data_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_data_t));
	zbx_hashset_create_ext(&data->hvs, 1, vmware_hv_hash, vmware_hv_compare, NULL, __vm_mem_malloc_func,
			__vm_mem_realloc_func, __vm_mem_free_func);
	VMWARE_VECTOR_CREATE(&data->clusters, ptr);
	VMWARE_VECTOR_CREATE(&data->events, ptr);
	VMWARE_VECTOR_CREATE(&data->datastores, vmware_datastore);
	VMWARE_VECTOR_CREATE(&data->datacenters, vmware_datacenter);
	zbx_vector_ptr_reserve(&data->clusters, src->clusters.values_num);
	zbx_vector_ptr_reserve(&data->events, src->events.values_alloc);
	zbx_vector_vmware_datastore_reserve(&data->datastores, src->datastores.values_num);
	zbx_vector_vmware_datacenter_reserve(&data->datacenters, src->datacenters.values_num);

	zbx_hashset_create_ext(&data->vms_index, 100, vmware_vm_hash, vmware_vm_compare, NULL, __vm_mem_malloc_func,
			__vm_mem_realloc_func, __vm_mem_free_func);

	data->error = vmware_shared_strdup(src->error);

	for (i = 0; i < src->clusters.values_num; i++)
		zbx_vector_ptr_append(&data->clusters, vmware_cluster_shared_dup((zbx_vmware_cluster_t *)src->clusters.values[i]));

	for (i = 0; i < src->events.values_num; i++)
		zbx_vector_ptr_append(&data->events, vmware_event_shared_dup((zbx_vmware_event_t *)src->events.values[i]));

	for (i = 0; i < src->datastores.values_num; i++)
		zbx_vector_vmware_datastore_append(&data->datastores, vmware_datastore_shared_dup(src->datastores.values[i]));

	for (i = 0; i < src->datacenters.values_num; i++)
	{
		zbx_vector_vmware_datacenter_append(&data->datacenters,
				vmware_datacenter_shared_dup(src->datacenters.values[i]));
	}

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

		for (i = 0; i < hv->vms.values_num; i++)
		{
			zbx_vmware_vm_index_t	vmi_local = {(zbx_vmware_vm_t *)hv->vms.values[i], hv};

			zbx_hashset_insert(&data->vms_index, &vmi_local, sizeof(vmi_local));
		}
	}

	data->max_query_metrics = src->max_query_metrics;

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store diskextent data                *
 *                                                                            *
 * Parameters: diskextent   - [IN] the diskextent                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_diskextent_free(zbx_vmware_diskextent_t *diskextent)
{
	zbx_free(diskextent->diskname);
	zbx_free(diskextent);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store datastore data                 *
 *                                                                            *
 * Parameters: datastore   - [IN] the datastore                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datastore_free(zbx_vmware_datastore_t *datastore)
{
	zbx_vector_str_uint64_pair_clear_ext(&datastore->hv_uuids_access, zbx_str_uint64_pair_free);
	zbx_vector_str_uint64_pair_destroy(&datastore->hv_uuids_access);

	zbx_vector_vmware_diskextent_clear_ext(&datastore->diskextents, vmware_diskextent_free);
	zbx_vector_vmware_diskextent_destroy(&datastore->diskextents);

	zbx_free(datastore->name);
	zbx_free(datastore->uuid);
	zbx_free(datastore->id);
	zbx_free(datastore);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store datacenter data                *
 *                                                                            *
 * Parameters: datacenter   - [IN] the datacenter                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datacenter_free(zbx_vmware_datacenter_t *datacenter)
{
	zbx_free(datacenter->name);
	zbx_free(datacenter->id);
	zbx_free(datacenter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store properties list         *
 *                                                                            *
 * Parameters: props     - [IN] the properties list                           *
 *             props_num - [IN] the number of properties in the list          *
 *                                                                            *
 ******************************************************************************/
static void	vmware_props_free(char **props, int props_num)
{
	int	i;

	if (NULL == props)
		return;

	for (i = 0; i < props_num; i++)
		zbx_free(props[i]);

	zbx_free(props);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vm device object               *
 *                                                                            *
 * Parameters: dev   - [IN] the vm device                                     *
 *                                                                            *
 ******************************************************************************/
static void	vmware_dev_free(zbx_vmware_dev_t *dev)
{
	zbx_free(dev->instance);
	zbx_free(dev->label);
	zbx_free(dev);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vm file system object          *
 *                                                                            *
 * Parameters: fs    - [IN] the file system                                   *
 *                                                                            *
 ******************************************************************************/
static void	vmware_fs_free(zbx_vmware_fs_t *fs)
{
	zbx_free(fs->path);
	zbx_free(fs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store virtual machine                *
 *                                                                            *
 * Parameters: vm   - [IN] the virtual machine                                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_free(zbx_vmware_vm_t *vm)
{
	zbx_vector_ptr_clear_ext(&vm->devs, (zbx_clean_func_t)vmware_dev_free);
	zbx_vector_ptr_destroy(&vm->devs);

	zbx_vector_ptr_clear_ext(&vm->file_systems, (zbx_mem_free_func_t)vmware_fs_free);
	zbx_vector_ptr_destroy(&vm->file_systems);

	zbx_free(vm->uuid);
	zbx_free(vm->id);
	vmware_props_free(vm->props, ZBX_VMWARE_VMPROPS_NUM);
	zbx_free(vm);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store Datastore name data            *
 *                                                                            *
 * Parameters: dsname   - [IN] the Datastore name                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_dsname_free(zbx_vmware_dsname_t *dsname)
{
	zbx_vector_vmware_hvdisk_destroy(&dsname->hvdisks);
	zbx_free(dsname->name);
	zbx_free(dsname->uuid);
	zbx_free(dsname);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware hypervisor              *
 *                                                                            *
 * Parameters: hv   - [IN] the vmware hypervisor                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_hv_clean(zbx_vmware_hv_t *hv)
{
	zbx_vector_vmware_dsname_clear_ext(&hv->dsnames, vmware_dsname_free);
	zbx_vector_vmware_dsname_destroy(&hv->dsnames);

	zbx_vector_ptr_clear_ext(&hv->vms, (zbx_clean_func_t)vmware_vm_free);
	zbx_vector_ptr_destroy(&hv->vms);

	zbx_free(hv->uuid);
	zbx_free(hv->id);
	zbx_free(hv->clusterid);
	zbx_free(hv->datacenter_name);
	zbx_free(hv->parent_name);
	zbx_free(hv->parent_type);
	zbx_free(hv->ip);
	vmware_props_free(hv->props, ZBX_VMWARE_HVPROPS_NUM);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware cluster                 *
 *                                                                            *
 * Parameters: cluster   - [IN] the vmware cluster                            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_cluster_free(zbx_vmware_cluster_t *cluster)
{
	zbx_free(cluster->name);
	zbx_free(cluster->id);
	zbx_free(cluster->status);
	zbx_free(cluster);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware event                   *
 *                                                                            *
 * Parameters: event - [IN] the vmware event                                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_event_free(zbx_vmware_event_t *event)
{
	evt_msg_strpool_strfree(event->message);
	zbx_free(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware service data            *
 *                                                                            *
 * Parameters: data   - [IN] the vmware service data                          *
 *                                                                            *
 ******************************************************************************/
static void	vmware_data_free(zbx_vmware_data_t *data)
{
	zbx_hashset_iter_t	iter;
	zbx_vmware_hv_t		*hv;

	zbx_hashset_iter_reset(&data->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
		vmware_hv_clean(hv);

	zbx_hashset_destroy(&data->hvs);

	zbx_vector_ptr_clear_ext(&data->clusters, (zbx_clean_func_t)vmware_cluster_free);
	zbx_vector_ptr_destroy(&data->clusters);

	zbx_vector_ptr_clear_ext(&data->events, (zbx_clean_func_t)vmware_event_free);
	zbx_vector_ptr_destroy(&data->events);

	zbx_vector_vmware_datastore_clear_ext(&data->datastores, vmware_datastore_free);
	zbx_vector_vmware_datastore_destroy(&data->datastores);

	zbx_vector_vmware_datacenter_clear_ext(&data->datacenters, vmware_datacenter_free);
	zbx_vector_vmware_datacenter_destroy(&data->datacenters);

	zbx_free(data->error);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees vmware performance counter and the resources allocated by   *
 *          it                                                                *
 *                                                                            *
 * Parameters: counter - [IN] the performance counter to free                 *
 *                                                                            *
 ******************************************************************************/
static void	vmware_counter_free(zbx_vmware_counter_t *counter)
{
	zbx_free(counter->path);
	zbx_free(counter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: authenticates vmware service                                      *
 *                                                                            *
 * Parameters: service    - [IN] the vmware service                           *
 *             easyhandle - [IN] the CURL handle                              *
 *             page       - [IN] the CURL output buffer                       *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the authentication was completed successfully      *
 *               FAIL    - the authentication process has failed              *
 *                                                                            *
 * Comments: If service type is unknown this function will attempt to         *
 *           determine the right service type by trying to login with vCenter *
 *           and vSphere session managers.                                    *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_authenticate(zbx_vmware_service_t *service, CURL *easyhandle, ZBX_HTTPPAGE *page,
		char **error)
{
#	define ZBX_POST_VMWARE_AUTH						\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:Login xsi:type=\"ns0:LoginRequestType\">"			\
			"<ns0:_this type=\"SessionManager\">%s</ns0:_this>"	\
			"<ns0:userName>%s</ns0:userName>"			\
			"<ns0:password>%s</ns0:password>"			\
		"</ns0:Login>"							\
		ZBX_POST_VSPHERE_FOOTER

	char		xml[MAX_STRING_LEN], *error_object = NULL, *username_esc = NULL, *password_esc = NULL;
	CURLoption	opt;
	CURLcode	err;
	xmlDoc		*doc = NULL;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEFUNCTION, curl_write_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEDATA, page)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_PRIVATE, page)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HEADERFUNCTION, curl_header_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, service->url)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT,
					(long)CONFIG_VMWARE_TIMEOUT)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = ZBX_CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_INTERFACE, CONFIG_SOURCE_IP)))
		{
			*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
			goto out;
		}
	}

	username_esc = xml_escape_dyn(service->username);
	password_esc = xml_escape_dyn(service->password);

	if (ZBX_VMWARE_TYPE_UNKNOWN == service->type)
	{
		/* try to detect the service type first using vCenter service manager object */
		zbx_snprintf(xml, sizeof(xml), ZBX_POST_VMWARE_AUTH,
				vmware_service_objects[ZBX_VMWARE_TYPE_VCENTER].session_manager,
				username_esc, password_esc);

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, xml, &doc, NULL, error) && NULL == doc)
			goto out;

		if (NULL == *error)
		{
			/* Successfully authenticated with vcenter service manager. */
			/* Set the service type and return with success.            */
			service->type = ZBX_VMWARE_TYPE_VCENTER;
			ret = SUCCEED;
			goto out;
		}

		/* If the wrong service manager was used, set the service type as vsphere and */
		/* try again with vsphere service manager. Otherwise return with failure.     */
		if (NULL == (error_object = zbx_xml_doc_read_value(doc,
				ZBX_XPATH_LN3("detail", "NotAuthenticatedFault", "object"))))
		{
			goto out;
		}

		if (0 != strcmp(error_object, vmware_service_objects[ZBX_VMWARE_TYPE_VCENTER].session_manager))
			goto out;

		service->type = ZBX_VMWARE_TYPE_VSPHERE;
		zbx_free(*error);
	}

	zbx_snprintf(xml, sizeof(xml), ZBX_POST_VMWARE_AUTH, vmware_service_objects[service->type].session_manager,
			username_esc, password_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, xml, NULL, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zbx_free(error_object);
	zbx_free(username_esc);
	zbx_free(password_esc);
	zbx_xml_free_doc(doc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Close unused connection with vCenter                              *
 *                                                                            *
 * Parameters: service    - [IN] the vmware service                           *
 *             easyhandle - [IN] the CURL handle                              *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_logout(zbx_vmware_service_t *service, CURL *easyhandle, char **error)
{
#	define ZBX_POST_VMWARE_LOGOUT						\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:Logout>"							\
			"<ns0:_this type=\"SessionManager\">%s</ns0:_this>"	\
		"</ns0:Logout>"							\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN];

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_LOGOUT, vmware_service_objects[service->type].session_manager);
	return zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error);
}

typedef struct
{
	const char	*property_collector;
	CURL		*easyhandle;
	char		*token;
}
zbx_property_collection_iter;

static int	zbx_property_collection_init(CURL *easyhandle, const char *property_collection_query,
		const char *property_collector, const char *fn_parent, zbx_property_collection_iter **iter,
		xmlDoc **xdoc, char **error)
{
	*iter = (zbx_property_collection_iter *)zbx_malloc(*iter, sizeof(zbx_property_collection_iter));
	(*iter)->property_collector = property_collector;
	(*iter)->easyhandle = easyhandle;
	(*iter)->token = NULL;

	if (SUCCEED != zbx_soap_post(fn_parent, (*iter)->easyhandle, property_collection_query, xdoc, &(*iter)->token,
			error))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	zbx_property_collection_next(const char *fn_parent, zbx_property_collection_iter *iter, xmlDoc **xdoc,
		char **error)
{
#	define ZBX_POST_CONTINUE_RETRIEVE_PROPERTIES								\
		ZBX_POST_VSPHERE_HEADER										\
		"<ns0:ContinueRetrievePropertiesEx xsi:type=\"ns0:ContinueRetrievePropertiesExRequestType\">"	\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"					\
			"<ns0:token>%s</ns0:token>"								\
		"</ns0:ContinueRetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

#	define ZBX_XPATH_CONTINUE_RETRIEVE_PROPERTIES_TOKEN			\
		"/*[local-name()='Envelope']/*[local-name()='Body']"		\
		"/*[local-name()='ContinueRetrievePropertiesExResponse']"	\
		"/*[local-name()='returnval']/*[local-name()='token'][1]"

	char	*token_esc, post[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "%s() continue retrieving properties with token: '%s'", __func__,
			iter->token);

	token_esc = xml_escape_dyn(iter->token);
	zbx_snprintf(post, sizeof(post), ZBX_POST_CONTINUE_RETRIEVE_PROPERTIES, iter->property_collector, token_esc);
	zbx_free(token_esc);

	if (SUCCEED != zbx_soap_post(fn_parent, iter->easyhandle, post, xdoc, NULL, error))
		return FAIL;

	zbx_free(iter->token);
	iter->token = zbx_xml_doc_read_value(*xdoc, ZBX_XPATH_CONTINUE_RETRIEVE_PROPERTIES_TOKEN);

	return SUCCEED;

#	undef ZBX_POST_CONTINUE_RETRIEVE_PROPERTIES
#	undef ZBX_XPATH_CONTINUE_RETRIEVE_PROPERTIES_TOKEN
}

static void	zbx_property_collection_free(zbx_property_collection_iter *iter)
{
	if (NULL != iter)
	{
		zbx_free(iter->token);
		zbx_free(iter);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves vmware service instance contents                        *
 *                                                                            *
 * Parameters: easyhandle - [IN] the CURL handle                              *
 *             version    - [OUT] the version of the instance                 *
 *             fullname   - [OUT] the fullname of the instance                *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the contents were retrieved successfully           *
 *               FAIL    - the content retrieval failed                       *
 *                                                                            *
 ******************************************************************************/
static	int	vmware_service_get_contents(CURL *easyhandle, char **version, char **fullname, char **error)
{
#	define ZBX_POST_VMWARE_CONTENTS 							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrieveServiceContent>"							\
			"<ns0:_this type=\"ServiceInstance\">ServiceInstance</ns0:_this>"	\
		"</ns0:RetrieveServiceContent>"							\
		ZBX_POST_VSPHERE_FOOTER

	xmlDoc	*doc = NULL;

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_VMWARE_CONTENTS, &doc, NULL, error))
	{
		zbx_xml_free_doc(doc);
		return FAIL;
	}

	*version = zbx_xml_doc_read_value(doc, ZBX_XPATH_VMWARE_ABOUT("version"));
	*fullname = zbx_xml_doc_read_value(doc, ZBX_XPATH_VMWARE_ABOUT("fullName"));
	zbx_xml_free_doc(doc);

	if (NULL == *version)
	{
		*error = zbx_strdup(*error, "VMware Virtual Center is not ready.");
		return FAIL;
	}

	return SUCCEED;

#	undef ZBX_POST_VMWARE_CONTENTS
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the performance counter refreshrate for the specified entity  *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             type         - [IN] the entity type (HostSystem, Datastore or  *
 *                                 VirtualMachine)                            *
 *             id           - [IN] the entity id                              *
 *             refresh_rate - [OUT] a pointer to variable to store the        *
 *                                  regresh rate                              *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the authentication was completed successfully      *
 *               FAIL    - the authentication process has failed              *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_perf_counter_refreshrate(zbx_vmware_service_t *service, CURL *easyhandle,
		const char *type, const char *id, int *refresh_rate, char **error)
{
#	define ZBX_POST_VCENTER_PERF_COUNTERS_REFRESH_RATE			\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:QueryPerfProviderSummary>"				\
			"<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>"	\
			"<ns0:entity type=\"%s\">%s</ns0:entity>"		\
		"</ns0:QueryPerfProviderSummary>"				\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN], *value = NULL, *id_esc;
	int	ret = FAIL;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type: %s id: %s", __func__, type, id);

	id_esc = xml_escape_dyn(id);
	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VCENTER_PERF_COUNTERS_REFRESH_RATE,
			vmware_service_objects[service->type].performance_manager, type, id_esc);
	zbx_free(id_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_ISAGGREGATE())))
	{
		zbx_free(value);
		*refresh_rate = ZBX_VMWARE_PERF_INTERVAL_NONE;
		ret = SUCCEED;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() refresh_rate: unused", __func__);
		goto out;
	}
	else if (NULL == (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_REFRESHRATE())))
	{
		*error = zbx_strdup(*error, "Cannot find refreshRate.");
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() refresh_rate:%s", __func__, value);

	if (SUCCEED != (ret = is_uint31(value, refresh_rate)))
		*error = zbx_dsprintf(*error, "Cannot convert refreshRate from %s.",  value);

	zbx_free(value);
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the performance counter ids                                   *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             counters     - [IN/OUT] the vector the created performance     *
 *                                     counter object should be added to      *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_perf_counters(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_ptr_t *counters, char **error)
{
#	define ZBX_POST_VMWARE_GET_PERFCOUNTER							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>PerformanceManager</ns0:type>"		\
					"<ns0:pathSet>perfCounter</ns0:pathSet>"		\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"PerformanceManager\">%s</ns0:obj>"	\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

#	define STR2UNIT(unit, val)								\
		if (0 == strcmp("joule",val))							\
			unit = ZBX_VMWARE_UNIT_JOULE;						\
		else if (0 == strcmp("kiloBytes",val))						\
			unit = ZBX_VMWARE_UNIT_KILOBYTES;					\
		else if (0 == strcmp("kiloBytesPerSecond",val))					\
			unit = ZBX_VMWARE_UNIT_KILOBYTESPERSECOND;				\
		else if (0 == strcmp("megaBytes",val))						\
			unit = ZBX_VMWARE_UNIT_MEGABYTES;					\
		else if (0 == strcmp("megaBytesPerSecond",val))					\
			unit = ZBX_VMWARE_UNIT_MEGABYTESPERSECOND;				\
		else if (0 == strcmp("megaHertz",val))						\
			unit = ZBX_VMWARE_UNIT_MEGAHERTZ;					\
		else if (0 == strcmp("microsecond",val))					\
			unit = ZBX_VMWARE_UNIT_MICROSECOND;					\
		else if (0 == strcmp("millisecond",val))					\
			unit = ZBX_VMWARE_UNIT_MILLISECOND;					\
		else if (0 == strcmp("number",val))						\
			unit = ZBX_VMWARE_UNIT_NUMBER;						\
		else if (0 == strcmp("percent",val))						\
			unit = ZBX_VMWARE_UNIT_PERCENT;						\
		else if (0 == strcmp("second",val))						\
			unit = ZBX_VMWARE_UNIT_SECOND;						\
		else if (0 == strcmp("teraBytes",val))						\
			unit = ZBX_VMWARE_UNIT_TERABYTES;					\
		else if (0 == strcmp("watt",val))						\
			unit = ZBX_VMWARE_UNIT_WATT;						\
		else if (0 == strcmp("celsius",val))						\
			unit = ZBX_VMWARE_UNIT_CELSIUS;						\
		else										\
			unit = ZBX_VMWARE_UNIT_UNDEFINED

	char		tmp[MAX_STRING_LEN], *group = NULL, *key = NULL, *rollup = NULL, *stats = NULL,
			*counterid = NULL, *unit = NULL;
	xmlDoc		*doc = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_GET_PERFCOUNTER,
			vmware_service_objects[service->type].property_collector,
			vmware_service_objects[service->type].performance_manager);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_COUNTERINFO(), xpathCtx)))
	{
		*error = zbx_strdup(*error, "Cannot make performance counter list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		*error = zbx_strdup(*error, "Cannot find items in performance counter list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(counters, 2 * nodeset->nodeNr + counters->values_alloc);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_counter_t	*counter;

		group = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
				"*[local-name()='groupInfo']/*[local-name()='key']");

		key = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
						"*[local-name()='nameInfo']/*[local-name()='key']");

		rollup = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], "*[local-name()='rollupType']");
		stats = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], "*[local-name()='statsType']");
		counterid = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], "*[local-name()='key']");
		unit = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
				"*[local-name()='unitInfo']/*[local-name()='key']");

		if (NULL != group && NULL != key && NULL != rollup && NULL != counterid && NULL != unit)
		{
			counter = (zbx_vmware_counter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_counter_t));
			counter->path = zbx_dsprintf(NULL, "%s/%s[%s]", group, key, rollup);
			ZBX_STR2UINT64(counter->id, counterid);
			STR2UNIT(counter->unit, unit);

			if (ZBX_VMWARE_UNIT_UNDEFINED == counter->unit)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unknown performance counter " ZBX_FS_UI64
						" type of unitInfo:%s", counter->id, unit);
			}

			zbx_vector_ptr_append(counters, counter);

			zabbix_log(LOG_LEVEL_DEBUG, "adding performance counter %s:" ZBX_FS_UI64, counter->path,
					counter->id);
		}

		if (NULL != group && NULL != key && NULL != rollup && NULL != counterid && NULL != stats &&
				NULL != unit)
		{
			counter = (zbx_vmware_counter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_counter_t));
			counter->path = zbx_dsprintf(NULL, "%s/%s[%s,%s]", group, key, rollup, stats);
			ZBX_STR2UINT64(counter->id, counterid);
			STR2UNIT(counter->unit, unit);

			if (ZBX_VMWARE_UNIT_UNDEFINED == counter->unit)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unknown performance counter " ZBX_FS_UI64
						" type of unitInfo:%s", counter->id, unit);
			}

			zbx_vector_ptr_append(counters, counter);

			zabbix_log(LOG_LEVEL_DEBUG, "adding performance counter %s:" ZBX_FS_UI64, counter->path,
					counter->id);
		}

		zbx_free(counterid);
		zbx_free(stats);
		zbx_free(rollup);
		zbx_free(key);
		zbx_free(group);
		zbx_free(unit);
	}

	ret = SUCCEED;
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef STR2UNIT
#	undef ZBX_POST_VMWARE_GET_PERFCOUNTER
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets virtual machine network interface devices                    *
 *                                                                            *
 * Parameters: vm      - [OUT] the virtual machine                            *
 *             details - [IN] a xml document containing virtual machine data  *
 *                                                                            *
 * Comments: The network interface devices are taken from vm device list      *
 *           filtered by macAddress key.                                      *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_nic_devices(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i, nics = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_VM_HARDWARE("device")
			"[*[local-name()='macAddress']]", xpathCtx)))
	{
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(&vm->devs, nodeset->nodeNr + vm->devs.values_alloc);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		char			*key;
		zbx_vmware_dev_t	*dev;

		if (NULL == (key = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='key']")))
			continue;

		dev = (zbx_vmware_dev_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dev_t));
		dev->type =  ZBX_VMWARE_DEV_TYPE_NIC;
		dev->instance = key;
		dev->label = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
				"*[local-name()='deviceInfo']/*[local-name()='label']");

		zbx_vector_ptr_append(&vm->devs, dev);
		nics++;
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, nics);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets virtual machine virtual disk devices                         *
 *                                                                            *
 * Parameters: vm      - [OUT] the virtual machine                            *
 *             details - [IN] a xml document containing virtual machine data  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_disk_devices(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i, disks = 0;
	char		*xpath = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	/* select all hardware devices of VirtualDisk type */
	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_VM_HARDWARE("device")
			"[string(@*[local-name()='type'])='VirtualDisk']", xpathCtx)))
	{
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(&vm->devs, nodeset->nodeNr + vm->devs.values_alloc);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_dev_t	*dev;
		char			*unitNumber = NULL, *controllerKey = NULL, *busNumber = NULL,
					*controllerLabel = NULL, *controllerType = NULL,
					*scsiCtlrUnitNumber = NULL;
		xmlXPathObject		*xpathObjController = NULL;

		do
		{
			if (NULL == (unitNumber = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
					"*[local-name()='unitNumber']")))
			{
				break;
			}

			if (NULL == (controllerKey = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
					"*[local-name()='controllerKey']")))
			{
				break;
			}

			/* find the controller (parent) device */
			xpath = zbx_dsprintf(xpath, ZBX_XPATH_VM_HARDWARE("device")
					"[*[local-name()='key']/text()='%s']", controllerKey);

			if (NULL == (xpathObjController = xmlXPathEvalExpression((xmlChar *)xpath, xpathCtx)))
				break;

			if (0 != xmlXPathNodeSetIsEmpty(xpathObjController->nodesetval))
				break;

			if (NULL == (busNumber = zbx_xml_node_read_value(details,
					xpathObjController->nodesetval->nodeTab[0], "*[local-name()='busNumber']")))
			{
				break;
			}

			/* scsiCtlrUnitNumber property is simply used to determine controller type. */
			/* For IDE controllers it is not set.                                       */
			scsiCtlrUnitNumber = zbx_xml_node_read_value(details, xpathObjController->nodesetval->nodeTab[0],
				"*[local-name()='scsiCtlrUnitNumber']");

			dev = (zbx_vmware_dev_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dev_t));
			dev->type =  ZBX_VMWARE_DEV_TYPE_DISK;

			/* the virtual disk instance has format <controller type><busNumber>:<unitNumber>     */
			/* where controller type is either ide, sata or scsi depending on the controller type */

			dev->label = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
					"*[local-name()='deviceInfo']/*[local-name()='label']");

			controllerLabel = zbx_xml_node_read_value(details, xpathObjController->nodesetval->nodeTab[0],
				"*[local-name()='deviceInfo']/*[local-name()='label']");

			if (NULL != scsiCtlrUnitNumber ||
				(NULL != controllerLabel && NULL != strstr(controllerLabel, "SCSI")))
			{
				controllerType = "scsi";
			}
			else if (NULL != controllerLabel && NULL != strstr(controllerLabel, "SATA"))
			{
				controllerType = "sata";
			}
			else
			{
				controllerType = "ide";
			}

			dev->instance = zbx_dsprintf(NULL, "%s%s:%s", controllerType, busNumber, unitNumber);
			zbx_vector_ptr_append(&vm->devs, dev);

			disks++;

		}
		while (0);

		xmlXPathFreeObject(xpathObjController);

		zbx_free(controllerLabel);
		zbx_free(scsiCtlrUnitNumber);
		zbx_free(busNumber);
		zbx_free(unitNumber);
		zbx_free(controllerKey);

	}
clean:
	zbx_free(xpath);

	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, disks);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the parameters of virtual machine disks                      *
 *                                                                            *
 * Parameters: vm      - [OUT] the virtual machine                            *
 *             details - [IN] a xml document containing virtual machine data  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_file_systems(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_VM_GUESTDISKS(), xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(&vm->file_systems, nodeset->nodeNr + vm->file_systems.values_alloc);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_fs_t	*fs;
		char		*value;

		if (NULL == (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='diskPath']")))
			continue;

		fs = (zbx_vmware_fs_t *)zbx_malloc(NULL, sizeof(zbx_vmware_fs_t));
		memset(fs, 0, sizeof(zbx_vmware_fs_t));

		fs->path = value;

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='capacity']")))
		{
			ZBX_STR2UINT64(fs->capacity, value);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='freeSpace']")))
		{
			ZBX_STR2UINT64(fs->free_space, value);
			zbx_free(value);
		}

		zbx_vector_ptr_append(&vm->file_systems, fs);
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, vm->file_systems.values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the virtual machine data                                     *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             vmid         - [IN] the virtual machine id                     *
 *             propmap      - [IN] the xpaths of the properties to read       *
 *             props_num    - [IN] the number of properties to read           *
 *             xdoc         - [OUT] a reference to output xml document        *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_vm_data(zbx_vmware_service_t *service, CURL *easyhandle, const char *vmid,
		const zbx_vmware_propmap_t *propmap, int props_num, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_VMWARE_VM_STATUS_EX 						\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:RetrievePropertiesEx>"						\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"		\
			"<ns0:specSet>"							\
				"<ns0:propSet>"						\
					"<ns0:type>VirtualMachine</ns0:type>"		\
					"<ns0:pathSet>config.hardware</ns0:pathSet>"	\
					"<ns0:pathSet>config.uuid</ns0:pathSet>"	\
					"<ns0:pathSet>config.instanceUuid</ns0:pathSet>"\
					"<ns0:pathSet>guest.disk</ns0:pathSet>"		\
					"%s"						\
				"</ns0:propSet>"					\
				"<ns0:propSet>"						\
					"<ns0:type>Folder</ns0:type>"			\
					"<ns0:pathSet>name</ns0:pathSet>"		\
					"<ns0:pathSet>parent</ns0:pathSet>"		\
				"</ns0:propSet>"					\
				"<ns0:objectSet>"					\
					"<ns0:obj type=\"VirtualMachine\">%s</ns0:obj>"	\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"\
						"<ns0:name>vm</ns0:name>"		\
						"<ns0:type>VirtualMachine</ns0:type>"	\
						"<ns0:path>parent</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"		\
						"<ns0:selectSet>"			\
							"<ns0:name>fl</ns0:name>"	\
						"</ns0:selectSet>"			\
					"</ns0:selectSet>"				\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"\
						"<ns0:name>fl</ns0:name>"		\
						"<ns0:type>Folder</ns0:type>"		\
						"<ns0:path>parent</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"		\
						"<ns0:selectSet>"			\
							"<ns0:name>fl</ns0:name>"	\
						"</ns0:selectSet>"			\
					"</ns0:selectSet>"				\
				"</ns0:objectSet>"					\
			"</ns0:specSet>"						\
			"<ns0:options/>"						\
		"</ns0:RetrievePropertiesEx>"						\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN + ZBX_VMWARE_VMPROPS_NUM * 100], props[MAX_STRING_LEN], *vmid_esc;
	int	i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __func__, vmid);
	props[0] = '\0';

	for (i = 0; i < props_num; i++)
	{
		zbx_strlcat(props, "<ns0:pathSet>", sizeof(props));
		zbx_strlcat(props, propmap[i].name, sizeof(props));
		zbx_strlcat(props, "</ns0:pathSet>", sizeof(props));
	}

	vmid_esc = xml_escape_dyn(vmid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_VM_STATUS_EX,
			vmware_service_objects[service->type].property_collector, props, vmid_esc);

	zbx_free(vmid_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert vm folder id to chain of folder names divided by '/'      *
 *                                                                            *
 * Parameters: xdoc      - [IN] the xml with all vm details                   *
 *             vm_folder - [IN/OUT] the vm property with folder id            *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_vm_folder(xmlDoc *xdoc, char **vm_folder)
{
	char	tmp[MAX_STRING_LEN], *id, *fl, *folder = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() folder id:'%s'", __func__, *vm_folder);
	id = zbx_strdup(NULL, *vm_folder);

	do
	{
		char	*id_esc;

		id_esc = xml_escape_dyn(id);
		zbx_free(id);
		zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_GET_FOLDER_NAME("%s"), id_esc);

		if (NULL == (fl = zbx_xml_doc_read_value(xdoc , tmp)))
		{
			zbx_free(folder);
			zbx_free(id_esc);
			return FAIL;
		}

		zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_GET_FOLDER_PARENTID("%s"), id_esc);
		zbx_free(id_esc);
		id = zbx_xml_doc_read_value(xdoc , tmp);

		if (NULL == folder)	/* we always resolve the first 'Folder' name */
		{
			folder = fl;
			fl = NULL;
		}
		else if (NULL != id)	/* we do not include the last default 'Folder' */
			folder = zbx_dsprintf(folder, "%s/%s", fl, folder);

		zbx_free(fl);
	}
	while (NULL != id);

	zbx_free(*vm_folder);
	*vm_folder = folder;
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): vm folder:%s", __func__, *vm_folder);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create virtual machine object                                     *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             id           - [IN] the virtual machine id                     *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: The created virtual machine object or NULL if an error was   *
 *               detected.                                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_vm_t	*vmware_service_create_vm(zbx_vmware_service_t *service,  CURL *easyhandle,
		const char *id, char **error)
{
	zbx_vmware_vm_t	*vm;
	char		*value;
	xmlDoc		*details = NULL;
	const char	*uuid_xpath[3] = {NULL, ZBX_XPATH_VM_UUID(), ZBX_XPATH_VM_INSTANCE_UUID()};
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __func__, id);

	vm = (zbx_vmware_vm_t *)zbx_malloc(NULL, sizeof(zbx_vmware_vm_t));
	memset(vm, 0, sizeof(zbx_vmware_vm_t));

	zbx_vector_ptr_create(&vm->devs);
	zbx_vector_ptr_create(&vm->file_systems);

	if (SUCCEED != vmware_service_get_vm_data(service, easyhandle, id, vm_propmap,
			ZBX_VMWARE_VMPROPS_NUM, &details, error))
	{
		goto out;
	}

	if (NULL == (value = zbx_xml_doc_read_value(details, uuid_xpath[service->type])))
		goto out;

	vm->uuid = value;
	vm->id = zbx_strdup(NULL, id);

	if (NULL == (vm->props = xml_read_props(details, vm_propmap, ZBX_VMWARE_VMPROPS_NUM)))
		goto out;

	if (NULL != vm->props[ZBX_VMWARE_VMPROP_FOLDER] &&
			SUCCEED != vmware_service_get_vm_folder(details, &vm->props[ZBX_VMWARE_VMPROP_FOLDER]))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): can't find vm folder name for id:%s", __func__,
				vm->props[ZBX_VMWARE_VMPROP_FOLDER]);
	}

	vmware_vm_get_nic_devices(vm, details);
	vmware_vm_get_disk_devices(vm, details);
	vmware_vm_get_file_systems(vm, details);

	ret = SUCCEED;
out:
	zbx_xml_free_doc(details);

	if (SUCCEED != ret)
	{
		vmware_vm_free(vm);
		vm = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return vm;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Refreshes all storage related information including free-space,   *
 *          capacity, and detailed usage of virtual machines.                 *
 *                                                                            *
 * Parameters: easyhandle   - [IN] the CURL handle                            *
 *             id           - [IN] the datastore id                           *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Comments: This is required for ESX/ESXi hosts version < 6.0 only           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_refresh_datastore_info(CURL *easyhandle, const char *id, char **error)
{
#	define ZBX_POST_REFRESH_DATASTORE							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RefreshDatastoreStorageInfo>"						\
			"<ns0:_this type=\"Datastore\">%s</ns0:_this>"				\
		"</ns0:RefreshDatastoreStorageInfo>"						\
		ZBX_POST_VSPHERE_FOOTER

	char		tmp[MAX_STRING_LEN];
	int		ret = FAIL;

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_REFRESH_DATASTORE, id);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves a list of vmware service datastore diskextents          *
 *                                                                            *
 * Parameters: doc        - [IN] XML document                                 *
 *             diskextents  - [OUT] list of vmware diskextents                *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_diskextents_list(xmlDoc *doc, zbx_vector_vmware_diskextent_t *diskextents)
{
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	char			*name, *partition;
	zbx_vmware_diskextent_t	*diskextent;
	int			i, ret = FAIL;

	if (NULL == doc)
		return ret;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *) ZBX_XPATH_DS_INFO_EXTENT(), xpathCtx)))
		goto out;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		if (NULL == (name = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XPATH_NN("diskName"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get diskName.", __func__);
			continue;
		}

		diskextent = (zbx_vmware_diskextent_t *)zbx_malloc(NULL, sizeof(zbx_vmware_diskextent_t));
		diskextent->diskname = name;

		if (NULL != (partition = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XPATH_NN("partition"))))
		{
			diskextent->partitionid = (unsigned int) atoi(partition);
			zbx_free(partition);
		}
		else
			diskextent->partitionid = 0;

		zbx_vector_vmware_diskextent_append(diskextents, diskextent);
	}

	zbx_vector_vmware_diskextent_sort(diskextents, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	ret = SUCCEED;
out:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create vmware hypervisor datastore object                         *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             id           - [IN] the datastore id                           *
 *                                                                            *
 * Return value: The created datastore object or NULL if an error was         *
 *                detected                                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_datastore_t	*vmware_service_create_datastore(const zbx_vmware_service_t *service, CURL *easyhandle,
		const char *id)
{
#	define ZBX_POST_DATASTORE_GET								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>Datastore</ns0:type>"			\
					"<ns0:pathSet>summary</ns0:pathSet>"			\
					"<ns0:pathSet>info</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"Datastore\">%s</ns0:obj>"		\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	char			tmp[MAX_STRING_LEN], *uuid = NULL, *name = NULL, *path, *id_esc, *value, *error = NULL;
	zbx_vmware_datastore_t	*datastore = NULL;
	zbx_uint64_t		capacity = ZBX_MAX_UINT64, free_space = ZBX_MAX_UINT64, uncommitted = ZBX_MAX_UINT64;
	xmlDoc			*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() datastore:'%s'", __func__, id);

	id_esc = xml_escape_dyn(id);

	if (ZBX_VMWARE_TYPE_VSPHERE == service->type && NULL != service->version &&
			ZBX_VMWARE_DS_REFRESH_VERSION > service->major_version && SUCCEED !=
			vmware_service_refresh_datastore_info(easyhandle, id_esc, &error))
	{
		zbx_free(id_esc);
		goto out;
	}

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_DATASTORE_GET,
			vmware_service_objects[service->type].property_collector, id_esc);

	zbx_free(id_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, &error))
		goto out;

	name = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("name"));

	if (NULL != (path = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("url"))))
	{
		if ('\0' != *path)
		{
			size_t	len;
			char	*ptr;

			len = strlen(path);

			if ('/' == path[len - 1])
				path[len - 1] = '\0';

			for (ptr = path + len - 2; ptr > path && *ptr != '/'; ptr--)
				;

			uuid = zbx_strdup(NULL, ptr + 1);
		}
		zbx_free(path);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() datastore uuid not present for id:'%s'", __func__, id);
		zbx_free(name);
		goto out;
	}

	if (ZBX_VMWARE_TYPE_VSPHERE == service->type)
	{
		if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("capacity"))))
		{
			is_uint64(value, &capacity);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("freeSpace"))))
		{
			is_uint64(value, &free_space);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("uncommitted"))))
		{
			is_uint64(value, &uncommitted);
			zbx_free(value);
		}
	}

	datastore = (zbx_vmware_datastore_t *)zbx_malloc(NULL, sizeof(zbx_vmware_datastore_t));
	datastore->name = (NULL != name) ? name : zbx_strdup(NULL, id);
	datastore->uuid = uuid;
	datastore->id = zbx_strdup(NULL, id);
	datastore->capacity = capacity;
	datastore->free_space = free_space;
	datastore->uncommitted = uncommitted;
	zbx_vector_str_uint64_pair_create(&datastore->hv_uuids_access);
	zbx_vector_vmware_diskextent_create(&datastore->diskextents);
	vmware_service_get_diskextents_list(doc, &datastore->diskextents);
out:
	zbx_xml_free_doc(doc);

	if (NULL != error)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot get Datastore info: %s.", error);
		zbx_free(error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return datastore;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the vmware hypervisor data                                   *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hvid         - [IN] the vmware hypervisor id                   *
 *             propmap      - [IN] the xpaths of the properties to read       *
 *             props_num    - [IN] the number of properties to read           *
 *             xdoc         - [OUT] a reference to output xml document        *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_hv_data(const zbx_vmware_service_t *service, CURL *easyhandle, const char *hvid,
		const zbx_vmware_propmap_t *propmap, int props_num, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_HV_DETAILS 									\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>HostSystem</ns0:type>"				\
					"<ns0:pathSet>vm</ns0:pathSet>"					\
					"<ns0:pathSet>parent</ns0:pathSet>"				\
					"<ns0:pathSet>datastore</ns0:pathSet>"				\
					"<ns0:pathSet>config.virtualNicManagerInfo.netConfig</ns0:pathSet>"\
					"<ns0:pathSet>config.network.ipRouteConfig.defaultGateway</ns0:pathSet>"\
					"<ns0:pathSet>summary.managementServerIp</ns0:pathSet>"		\
					"<ns0:pathSet>config.storageDevice.scsiTopology</ns0:pathSet>"	\
					"%s"								\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"					\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN + ZBX_VMWARE_HVPROPS_NUM * 100], props[MAX_STRING_LEN], *hvid_esc;
	int	i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() guesthvid:'%s'", __func__, hvid);
	props[0] = '\0';

	for (i = 0; i < props_num; i++)
	{
		if (NULL == propmap[i].name)
			continue;

		zbx_strlcat(props, "<ns0:pathSet>", sizeof(props));
		zbx_strlcat(props, propmap[i].name, sizeof(props));
		zbx_strlcat(props, "</ns0:pathSet>", sizeof(props));
	}

	hvid_esc = xml_escape_dyn(hvid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_HV_DETAILS, vmware_service_objects[service->type].property_collector,
			props, hvid_esc);

	zbx_free(hvid_esc);

	zabbix_log(LOG_LEVEL_TRACE, "%s() SOAP request: %s", __func__, tmp);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef	ZBX_POST_HV_DETAILS
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the vmware hypervisor datacenter, parent folder or cluster   *
 *          name                                                              *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hv           - [IN/OUT] the vmware hypervisor                  *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_hv_get_parent_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vmware_hv_t *hv, char **error)
{
#	define ZBX_POST_HV_DATACENTER_NAME									\
		ZBX_POST_VSPHERE_HEADER										\
			"<ns0:RetrievePropertiesEx>"								\
				"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
				"<ns0:specSet>"									\
					"<ns0:propSet>"								\
						"<ns0:type>Datacenter</ns0:type>"				\
						"<ns0:pathSet>name</ns0:pathSet>"				\
					"</ns0:propSet>"							\
					"%s"									\
					"<ns0:objectSet>"							\
						"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
						"<ns0:skip>false</ns0:skip>"					\
						"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
							"<ns0:name>parentObject</ns0:name>"			\
							"<ns0:type>HostSystem</ns0:type>"			\
							"<ns0:path>parent</ns0:path>"				\
							"<ns0:skip>false</ns0:skip>"				\
							"<ns0:selectSet>"					\
								"<ns0:name>parentComputeResource</ns0:name>"	\
							"</ns0:selectSet>"					\
							"<ns0:selectSet>"					\
								"<ns0:name>parentFolder</ns0:name>"		\
							"</ns0:selectSet>"					\
						"</ns0:selectSet>"						\
						"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
							"<ns0:name>parentComputeResource</ns0:name>"		\
							"<ns0:type>ComputeResource</ns0:type>"			\
							"<ns0:path>parent</ns0:path>"				\
							"<ns0:skip>false</ns0:skip>"				\
							"<ns0:selectSet>"					\
								"<ns0:name>parentFolder</ns0:name>"		\
							"</ns0:selectSet>"					\
						"</ns0:selectSet>"						\
						"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
							"<ns0:name>parentFolder</ns0:name>"			\
							"<ns0:type>Folder</ns0:type>"				\
							"<ns0:path>parent</ns0:path>"				\
							"<ns0:skip>false</ns0:skip>"				\
							"<ns0:selectSet>"					\
								"<ns0:name>parentFolder</ns0:name>"		\
							"</ns0:selectSet>"					\
							"<ns0:selectSet>"					\
								"<ns0:name>parentComputeResource</ns0:name>"	\
							"</ns0:selectSet>"					\
						"</ns0:selectSet>"						\
					"</ns0:objectSet>"							\
				"</ns0:specSet>"								\
				"<ns0:options/>"								\
			"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

#	define ZBX_POST_SOAP_FOLDER										\
		"<ns0:propSet>"											\
			"<ns0:type>Folder</ns0:type>"								\
			"<ns0:pathSet>name</ns0:pathSet>"							\
			"<ns0:pathSet>parent</ns0:pathSet>"							\
			"<ns0:pathSet>childEntity</ns0:pathSet>"						\
		"</ns0:propSet>"										\
		"<ns0:propSet>"											\
			"<ns0:type>HostSystem</ns0:type>"							\
			"<ns0:pathSet>parent</ns0:pathSet>"							\
		"</ns0:propSet>"

#	define ZBX_POST_SOAP_CUSTER										\
		"<ns0:propSet>"											\
			"<ns0:type>ClusterComputeResource</ns0:type>"						\
			"<ns0:pathSet>name</ns0:pathSet>"							\
		"</ns0:propSet>"

	char	tmp[MAX_STRING_LEN];
	int	ret = FAIL;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() id:'%s'", __func__, hv->id);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_HV_DATACENTER_NAME,
			vmware_service_objects[service->type].property_collector,
			NULL != hv->clusterid ? ZBX_POST_SOAP_CUSTER : ZBX_POST_SOAP_FOLDER, hv->id);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	if (NULL == (hv->datacenter_name = zbx_xml_doc_read_value(doc,
			ZBX_XPATH_NAME_BY_TYPE(ZBX_VMWARE_SOAP_DATACENTER))))
	{
		hv->datacenter_name = zbx_strdup(NULL, "");
	}

	if (NULL != hv->clusterid && (NULL != (hv->parent_name = zbx_xml_doc_read_value(doc,
			ZBX_XPATH_NAME_BY_TYPE(ZBX_VMWARE_SOAP_CLUSTER)))))
	{
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_CLUSTER);
	}
	else if (NULL != (hv->parent_name = zbx_xml_doc_read_value(doc,
			ZBX_XPATH_HV_PARENTFOLDERNAME(ZBX_XPATH_HV_PARENTID))))
	{
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_FOLDER);
	}
	else if ('\0' != *hv->datacenter_name)
	{
		hv->parent_name = zbx_strdup(NULL, hv->datacenter_name);
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_DATACENTER);
	}
	else
	{
		hv->parent_name = zbx_strdup(NULL, ZBX_VMWARE_TYPE_VCENTER == service->type ? "Vcenter" : "ESXi");
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_DEFAULT);
	}

	ret = SUCCEED;
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef	ZBX_POST_HV_DATACENTER_NAME
#	undef	ZBX_POST_SOAP_FOLDER
#	undef	ZBX_POST_SOAP_CUSTER
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the vmware hypervisor data about ds multipath                *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hv_data      - [IN] the hv data with scsi topology info        *
 *             hvid         - [IN] the vmware hypervisor id                   *
 *             xdoc         - [OUT] a reference to output xml document        *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_hv_get_multipath_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		xmlDoc *hv_data, const char *hvid, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_HV_MP_DETAILS									\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>HostSystem</ns0:type>"				\
					"<ns0:pathSet>config.storageDevice.multipathInfo</ns0:pathSet>"	\
					"%s"								\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"					\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

#	define ZBX_POST_SCSI_INFO									\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].canonicalName</ns0:pathSet>"

	zbx_vector_str_t	scsi_luns;
	char			*scsi_req = NULL;
	int			i, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hvid:'%s'", __func__, hvid);

	zbx_vector_str_create(&scsi_luns);
	zbx_xml_read_values(hv_data, ZBX_XPATH_HV_SCSI_TOPOLOGY, &scsi_luns);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() count of scsiLun:%d", __func__, scsi_luns.values_num);

	for (i = 0; i < scsi_luns.values_num; i++)
	{
		scsi_req = zbx_strdcatf(scsi_req , ZBX_POST_SCSI_INFO, scsi_luns.values[i]);
	}

	if (0 != scsi_luns.values_num)
	{
		char	*tmp, *hvid_esc;

		zbx_vector_str_clear_ext(&scsi_luns, zbx_str_free);
		hvid_esc = xml_escape_dyn(hvid);
		tmp = zbx_dsprintf(NULL, ZBX_POST_HV_MP_DETAILS,
				vmware_service_objects[service->type].property_collector, scsi_req, hvid_esc);
		zbx_free(hvid_esc);
		zbx_free(scsi_req);

		ret = zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error);
		zbx_free(tmp);
	}
	else
		ret = SUCCEED;

	zbx_vector_str_destroy(&scsi_luns);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef	ZBX_POST_SCSI_INFO
#	undef	ZBX_POST_HV_MP_DETAILS
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_ds_uuid_compare                                           *
 *                                                                            *
 * Purpose: sorting function to sort Datastore vector by uuid                 *
 *                                                                            *
 ******************************************************************************/
int	vmware_ds_uuid_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->uuid, ds2->uuid);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_ds_name_compare                                           *
 *                                                                            *
 * Purpose: sorting function to sort Datastore vector by name                 *
 *                                                                            *
 ******************************************************************************/
int	vmware_ds_name_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->name, ds2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort Datastore vector by id                   *
 *                                                                            *
 ******************************************************************************/
static int	vmware_ds_id_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->id, ds2->id);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort Datacenter vector by id                  *
 *                                                                            *
 ******************************************************************************/
static int	vmware_dc_id_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datacenter_t	*dc1 = *(const zbx_vmware_datacenter_t * const *)d1;
	const zbx_vmware_datacenter_t	*dc2 = *(const zbx_vmware_datacenter_t * const *)d2;

	return strcmp(dc1->id, dc2->id);
}

/******************************************************************************
 * Purpose: populate array of values from a xml data                          *
 *                                                                            *
 * Parameters: xdoc    - [IN] XML document                                    *
 *             ds_node - [IN] xml node with datastore info                    *
 *             ds_id   - [IN] datastore id (for logging)                      *
 *                                                                            *
 * Return: bitmap value of HV access mode to DS                               *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_hv_get_ds_access(xmlDoc *xdoc, xmlNode *ds_node, const char *ds_id)
{

	zbx_uint64_t	mi_access = ZBX_VMWARE_DS_NONE;
	char		*value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() for DS:%s", __func__, ds_id);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, ds_node, ZBX_XPATH_PROP_SUFFIX("mounted"))))
	{
		if (0 == strcmp(value, "true"))
			mi_access |= ZBX_VMWARE_DS_MOUNTED;

		zbx_free(value);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find item 'mounted' in mountinfo for DS:%s", ds_id);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, ds_node, ZBX_XPATH_PROP_SUFFIX("accessible"))))
	{
		if (0 == strcmp(value, "true"))
			mi_access |= ZBX_VMWARE_DS_ACCESSIBLE;

		zbx_free(value);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find item 'accessible' in accessible for DS:%s", ds_id);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, ds_node, ZBX_XPATH_PROP_SUFFIX("accessMode"))))
	{
		if (0 == strcmp(value, "readWrite"))
			mi_access |= ZBX_VMWARE_DS_READWRITE;
		else
			mi_access |= ZBX_VMWARE_DS_READ;

		zbx_free(value);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find item 'accessMode' in mountinfo for DS:%s", ds_id);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() mountinfo:" ZBX_FS_UI64, __func__, mi_access);

	return mi_access;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Convert ipv4 netmask to cidr prefix                               *
 *                                                                            *
 * Parameters: mask      - [IN] net mask string                               *
 *                                                                            *
 * Return value: size of v4 netmask prefix                                    *
 *                                                                            *
 ******************************************************************************/
static int	vmware_v4mask2pefix(const char *mask)
{
#	define	V4MASK_MAX	32

	struct in_addr	inaddr;
	int		p = 0;

	if (-1 == inet_pton(AF_INET, mask, &inaddr))
		return V4MASK_MAX;

	while (inaddr.s_addr > 0)
	{
		inaddr.s_addr = inaddr.s_addr >> 1;
		p++;
	}

	return p;

#	undef	V4MASK_MAX
}

/******************************************************************************
 *                                                                            *
 * Purpose: Search HV management interface ip value from a xml data           *
 *                                                                            *
 * Parameters: xdoc   - [IN] XML document                                     *
 *                                                                            *
 * Return: Upon successful completion the function return string with ip.     *
 *         Otherwise, NULL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static char	*vmware_hv_ip_search(xmlDoc *xdoc)
{
#define ZBX_XPATH_HV_IP(nicType, addr)									\
		ZBX_XNN("VirtualNicManagerNetConfig") "[" ZBX_XNN("nicType") "[text()='"nicType "']]/"	\
		ZBX_XNN("candidateVnic") "[" ZBX_XNN("key") "=../" ZBX_XNN("selectedVnic") "]//"	\
		ZBX_XNN("ip") ZBX_XPATH_LN(addr)

#define ZBX_XPATH_HV_IPV4(nicType)	ZBX_XPATH_HV_IP(nicType, "ipAddress")
#define ZBX_XPATH_HV_IPV6(nicType)	ZBX_XPATH_HV_IP(nicType, "ipV6Config")				\
		ZBX_XPATH_LN("ipV6Address") ZBX_XPATH_LN("ipAddress")

#define ZBX_XPATH_HV_NIC(nicType, param)								\
		ZBX_XNN("VirtualNicManagerNetConfig") "[" ZBX_XNN("nicType") "[text()='"nicType "']]/"	\
		ZBX_XNN("candidateVnic") "[" ZBX_XNN("key") "='%s']//" ZBX_XNN("ip") ZBX_XPATH_LN(param)

#define ZBX_XPATH_HV_NIC_IPV4(nicType)	ZBX_XPATH_HV_NIC(nicType, "ipAddress")
#define ZBX_XPATH_HV_NIC_IPV6(nicType)	ZBX_XPATH_HV_NIC(nicType, "ipV6Config")				\
		ZBX_XPATH_LN("ipV6Address") ZBX_XPATH_LN("ipAddress")
#define ZBX_XPATH_HV_NIC_V4MASK(nicType)	ZBX_XPATH_HV_NIC(nicType, "subnetMask")
#define ZBX_XPATH_HV_NIC_V6MASK(nicType)	ZBX_XPATH_HV_NIC(nicType, "ipV6Config")			\
		ZBX_XPATH_LN("ipV6Address") ZBX_XPATH_LN("prefixLength")

	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNode			*node;
	zbx_vector_str_t	selected_ifs, selected_ips;
	char			*value = NULL, *ip_vc = NULL, *ip_gw = NULL, *end;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&selected_ifs);
	zbx_vector_str_create(&selected_ips);
	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression(
			(const xmlChar *)ZBX_XPATH_PROP_NAME("config.virtualNicManagerInfo.netConfig"), xpathCtx)))
	{
		goto out;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	node = xpathObj->nodesetval->nodeTab[0];

	if (SUCCEED != zbx_xml_node_read_values(xdoc, node, ZBX_XNN("VirtualNicManagerNetConfig")
			"[" ZBX_XNN("nicType") "[text()='management']]/" ZBX_XNN("selectedVnic"),
			&selected_ifs) || 0 == selected_ifs.values_num)
	{
		goto out;
	}

	if (1 == selected_ifs.values_num)
	{
		if (NULL == (value = zbx_xml_node_read_value(xdoc, node, ZBX_XPATH_HV_IPV4("management"))))
			value = zbx_xml_node_read_value(xdoc, node, ZBX_XPATH_HV_IPV6("management"));

		goto out;
	}

	zbx_vector_str_sort(&selected_ifs, zbx_natural_str_compare_func);

	/* prefer IP which shares the IP-subnet with the vCenter IP */

	ip_vc = zbx_xml_doc_read_value(xdoc, ZBX_XPATH_PROP_NAME("summary.managementServerIp"));
	zabbix_log(LOG_LEVEL_DEBUG, "%s() managementServerIp rule; selected_ifs:%d ip_vc:%s", __func__,
			selected_ifs.values_num, ZBX_NULL2EMPTY_STR(ip_vc));

	for (i = 0; i < selected_ifs.values_num; i++)
	{
		char	*ip_hv = NULL, *mask = NULL, buff[MAX_STRING_LEN];
		int	ipv6 = 0;

		zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_IPV4("management"), selected_ifs.values[i]);

		if (NULL == (ip_hv = zbx_xml_node_read_value(xdoc, node, buff)))
		{
			zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_IPV6("management"), selected_ifs.values[i]);
			ip_hv = zbx_xml_node_read_value(xdoc, node, buff);
			ipv6 = 1;
		}

		if (NULL == ip_hv)
			continue;

		if (0 == ipv6)
			zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_V4MASK("management"), selected_ifs.values[i]);
		else
			zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_V6MASK("management"), selected_ifs.values[i]);

		if (NULL == (mask = zbx_xml_node_read_value(xdoc, node, buff)))
		{
			zbx_free(ip_hv);
			continue;
		}

		if (0 == ipv6)
			zbx_snprintf(buff, sizeof(buff), "%s/%d", ip_hv, vmware_v4mask2pefix(mask));
		else
			zbx_snprintf(buff, sizeof(buff), "%s/%s", ip_hv, mask);

		zbx_free(mask);
		zbx_vector_str_append(&selected_ips, zbx_strdup(NULL, buff));

		if (NULL != ip_vc && SUCCEED == ip_in_list(buff, ip_vc))
		{
			value = ip_hv;
			goto out;
		}

		zbx_free(ip_hv);
		zabbix_log(LOG_LEVEL_TRACE, "%s() managementServerIp fail; ip_vc:%s ip_hv:%s", __func__,
				ZBX_NULL2EMPTY_STR(ip_vc), buff);
	}

	if (0 == selected_ips.values_num)
		goto out;

	/* prefer IP from IP-subnet with default gateway */

	ip_gw = zbx_xml_doc_read_value(xdoc, ZBX_XPATH_PROP_NAME("config.network.ipRouteConfig.defaultGateway"));
	zabbix_log(LOG_LEVEL_DEBUG, "%s() default gateway rule; selected_ips:%d ip_gw:%s", __func__,
			selected_ips.values_num, ZBX_NULL2EMPTY_STR(ip_gw));

	for (i = 0; NULL != ip_gw && i < selected_ips.values_num; i++)
	{
		if (SUCCEED != ip_in_list(selected_ips.values[i], ip_gw))
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s() default gateway fail; ip_gw:%s ip_hv:%s", __func__,
					ip_gw, selected_ips.values[i]);
			continue;
		}

		if (NULL != (end = strchr(selected_ips.values[i], '/')))
			*end = '\0';

		value = zbx_strdup(NULL, selected_ips.values[i]);
		goto out;
	}

	/* prefer IP from interface with lowest id */

	zabbix_log(LOG_LEVEL_DEBUG, "%s() lowest interface id rule", __func__);

	if (NULL != (end = strchr(selected_ips.values[0], '/')))
		*end = '\0';

	value = zbx_strdup(NULL, selected_ips.values[0]);
out:
	zbx_vector_str_clear_ext(&selected_ifs, zbx_str_free);
	zbx_vector_str_clear_ext(&selected_ips, zbx_str_free);
	zbx_vector_str_destroy(&selected_ifs);
	zbx_vector_str_destroy(&selected_ips);
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zbx_free(ip_vc);
	zbx_free(ip_gw);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ip:%s", __func__, ZBX_NULL2EMPTY_STR(value));

	return value;
}

/******************************************************************************
 * Function: vmware_hv_ds_access_parse                                        *
 *                                                                            *
 * Purpose: read access state of hv to ds                                     *
 *                                                                            *
 * Parameters: xdoc    - [IN] the xml data with DS access info                *
 *             hv_dss  - [IN] the vector with all DS connected to HV          *
 *             hv_uuid - [IN] the uuid of HV                                  *
 *             hv_id   - [IN] the id of HV (for logging)                      *
 *             dss     - [IN/OUT] the vector with all Datastores              *
 *                                                                            *
 * Return value: count of updated DS                                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_hv_ds_access_parse(xmlDoc *xdoc, const zbx_vector_str_t *hv_dss, const char *hv_uuid,
		const char *hv_id, zbx_vector_vmware_datastore_t *dss)
{
	int		i, parsed_num = 0;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_OBJECTS_BY_TYPE(ZBX_VMWARE_SOAP_DS),
			xpathCtx)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot make xpath for Datastore list query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find Datastores in the list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		int				j;
		char				*value;
		zbx_vmware_datastore_t		*ds, ds_cmp;
		zbx_str_uint64_pair_t		hv_ds_access;

		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XPATH_NN("obj"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "skipping DS record without ID, xml number '%d'", i);
			continue;
		}

		if (FAIL == (j = zbx_vector_str_bsearch(hv_dss, value, ZBX_DEFAULT_STR_COMPARE_FUNC)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "DS:%s not connected to HV:%s", value, hv_id);
			zbx_str_free(value);
			continue;
		}

		zbx_str_free(value);

		ds_cmp.id = hv_dss->values[j];

		if (FAIL == (j = zbx_vector_vmware_datastore_bsearch(dss, &ds_cmp, vmware_ds_id_compare)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Datastore \"%s\" not found on hypervisor \"%s\".", __func__,
					ds_cmp.id, hv_id);
			continue;
		}

		ds = dss->values[j];
		hv_ds_access.name = zbx_strdup(NULL, hv_uuid);
		hv_ds_access.value = vmware_hv_get_ds_access(xdoc, nodeset->nodeTab[i], ds->id);
		zbx_vector_str_uint64_pair_append_ptr(&ds->hv_uuids_access, &hv_ds_access);
		parsed_num++;
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() parsed:%d", __func__, parsed_num);

	return parsed_num;
}

/******************************************************************************
 * Function: vmware_hv_ds_access_update                                       *
 *                                                                            *
 * Purpose: update access state of hv to ds                                   *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hv_uuid      - [IN] the vmware hypervisor uuid                 *
 *             hv_id        - [IN] the vmware hypervisor id                   *
 *             hv_dss       - [IN] the vector with all DS connected to HV     *
 *             dss          - [IN/OUT] the vector with all Datastores         *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the access state was updated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_hv_ds_access_update(zbx_vmware_service_t *service, CURL *easyhandle, const char *hv_uuid,
		const char *hv_id, const zbx_vector_str_t *hv_dss, zbx_vector_vmware_datastore_t *dss, char **error)
{
#	define ZBX_POST_HV_DS_ACCESS 									\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>Datastore</ns0:type>"				\
					"<ns0:pathSet>host[\"%s\"].mountInfo.mounted</ns0:pathSet>"	\
					"<ns0:pathSet>host[\"%s\"].mountInfo.accessible</ns0:pathSet>"	\
					"<ns0:pathSet>host[\"%s\"].mountInfo.accessMode</ns0:pathSet>"	\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
						"<ns0:name>DSObject</ns0:name>"				\
						"<ns0:type>HostSystem</ns0:type>"			\
						"<ns0:path>datastore</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"				\
					"</ns0:selectSet>"						\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char				*hvid_esc, tmp[MAX_STRING_LEN];
	int				ret = FAIL, updated = 0;
	xmlDoc				*doc = NULL;
	zbx_property_collection_iter	*iter = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hv id:%s hv dss:%d dss:%d", __func__, hv_id, hv_dss->values_num,
			dss->values_num);

	hvid_esc = xml_escape_dyn(hv_id);
	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_HV_DS_ACCESS, vmware_service_objects[service->type].property_collector,
			hvid_esc, hvid_esc, hvid_esc, hvid_esc);
	zbx_free(hvid_esc);

	if (SUCCEED != zbx_property_collection_init(easyhandle, tmp, "propertyCollector", __func__, &iter, &doc, error))
		goto out;

	updated += vmware_hv_ds_access_parse(doc, hv_dss, hv_uuid, hv_id, dss);

	while (NULL != iter->token)
	{
		zbx_xml_free_doc(doc);
		doc = NULL;

		if (SUCCEED != zbx_property_collection_next(__func__, iter, &doc, error))
			goto out;

		updated += vmware_hv_ds_access_parse(doc, hv_dss, hv_uuid, hv_id, dss);
	}

	ret = SUCCEED;
out:
	zbx_property_collection_free(iter);
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s for %d / %d", __func__, zbx_result_string(ret), updated,
			hv_dss->values_num);

	return ret;

#	undef ZBX_POST_HV_DS_ACCESS
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort Datastore names vector by name           *
 *                                                                            *
 ******************************************************************************/
int	vmware_dsname_compare(const void *d1, const void *d2)
{
	const zbx_vmware_dsname_t	*ds1 = *(const zbx_vmware_dsname_t * const *)d1;
	const zbx_vmware_dsname_t	*ds2 = *(const zbx_vmware_dsname_t * const *)d2;

	return strcmp(ds1->name, ds2->name);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_init_hv                                           *
 *                                                                            *
 * Purpose: initialize vmware hypervisor object                               *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             id           - [IN] the vmware hypervisor id                   *
 *             dss          - [IN/OUT] the vector with all Datastores         *
 *             hv           - [OUT] the hypervisor object (must be allocated) *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the hypervisor object was initialized successfully *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_init_hv(zbx_vmware_service_t *service, CURL *easyhandle, const char *id,
		zbx_vector_vmware_datastore_t *dss, zbx_vmware_hv_t *hv, char **error)
{
	char			*value;
	xmlDoc			*details = NULL, *multipath_data = NULL;
	zbx_vector_str_t	datastores, vms;
	int			i, j, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hvid:'%s'", __func__, id);

	memset(hv, 0, sizeof(zbx_vmware_hv_t));

	zbx_vector_vmware_dsname_create(&hv->dsnames);
	zbx_vector_ptr_create(&hv->vms);

	zbx_vector_str_create(&datastores);
	zbx_vector_str_create(&vms);

	if (SUCCEED != vmware_service_get_hv_data(service, easyhandle, id, hv_propmap,
			ZBX_VMWARE_HVPROPS_NUM, &details, error))
	{
		goto out;
	}

	if (NULL == (hv->props = xml_read_props(details, hv_propmap, ZBX_VMWARE_HVPROPS_NUM)))
		goto out;

	if (NULL == hv->props[ZBX_VMWARE_HVPROP_HW_UUID])
		goto out;

	hv->uuid = zbx_strdup(NULL, hv->props[ZBX_VMWARE_HVPROP_HW_UUID]);
	hv->id = zbx_strdup(NULL, id);

	if (NULL != (value = zbx_xml_doc_read_value(details, "//*[@type='" ZBX_VMWARE_SOAP_CLUSTER "']")))
		hv->clusterid = value;

	hv->ip = vmware_hv_ip_search(details);

	if (SUCCEED != vmware_hv_get_parent_data(service, easyhandle, hv, error))
		goto out;

	zbx_xml_read_values(details, ZBX_XPATH_HV_DATASTORES(), &datastores);
	zbx_vector_str_sort(&datastores, ZBX_DEFAULT_STR_COMPARE_FUNC);
	zbx_vector_vmware_dsname_reserve(&hv->dsnames, datastores.values_num);
	zabbix_log(LOG_LEVEL_DEBUG, "%s(): %d datastores are connected to hypervisor \"%s\"", __func__,
			datastores.values_num, hv->id);

	if (SUCCEED != vmware_service_hv_get_multipath_data(service, easyhandle, details, id, &multipath_data, error))
		goto out;

	if (SUCCEED != vmware_hv_ds_access_update(service, easyhandle, hv->uuid, hv->id, &datastores, dss, error))
		goto out;

	for (i = 0; i < datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*ds, ds_cmp;
		zbx_vmware_dsname_t	*dsname;

		ds_cmp.id = datastores.values[i];

		if (FAIL == (j = zbx_vector_vmware_datastore_bsearch(dss, &ds_cmp, vmware_ds_id_compare)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Datastore \"%s\" not found on hypervisor \"%s\".", __func__,
					datastores.values[i], hv->id);
			continue;
		}

		ds = dss->values[j];
		dsname = (zbx_vmware_dsname_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dsname_t));
		dsname->name = zbx_strdup(NULL, ds->name);
		dsname->uuid = zbx_strdup(NULL, ds->uuid);
		zbx_vector_vmware_hvdisk_create(&dsname->hvdisks);
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): for %d diskextents check multipath at ds:\"%s\"", __func__,
				ds->diskextents.values_num, ds->name);

		for (j = 0; NULL != multipath_data && j < ds->diskextents.values_num; j++)
		{
			zbx_vmware_diskextent_t	*diskextent = ds->diskextents.values[j];
			zbx_vmware_hvdisk_t	hvdisk;
			char			tmp[MAX_STRING_LEN], *lun;

			zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_HV_LUN(), diskextent->diskname);

			if (NULL == (lun = zbx_xml_doc_read_value(multipath_data, tmp)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): not found diskextent: %s",
						__func__, diskextent->diskname);
				continue;
			}

			zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_HV_MULTIPATH_PATHS(), lun);

			if (SUCCEED != zbx_xml_doc_read_num(multipath_data, tmp, &hvdisk.multipath_total) ||
					0 == hvdisk.multipath_total)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): for diskextent: %s and lun: %s"
						" multipath data is not found", __func__, diskextent->diskname, lun);
				zbx_free(lun);
				continue;
			}

			zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_HV_MULTIPATH_ACTIVE_PATHS(), lun);

			if (SUCCEED != zbx_xml_doc_read_num(multipath_data, tmp, &hvdisk.multipath_active))
				hvdisk.multipath_active = 0;

			hvdisk.partitionid = diskextent->partitionid;
			zbx_vector_vmware_hvdisk_append(&dsname->hvdisks, hvdisk);
			zbx_free(lun);
		}

		zbx_vector_vmware_hvdisk_sort(&dsname->hvdisks, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_vmware_dsname_append(&hv->dsnames, dsname);
	}

	zbx_vector_vmware_dsname_sort(&hv->dsnames, vmware_dsname_compare);
	zbx_xml_read_values(details, ZBX_XPATH_HV_VMS(), &vms);
	zbx_vector_ptr_reserve(&hv->vms, vms.values_num + hv->vms.values_alloc);

	for (i = 0; i < vms.values_num; i++)
	{
		zbx_vmware_vm_t	*vm;

		if (NULL != (vm = vmware_service_create_vm(service, easyhandle, vms.values[i], error)))
		{
			zbx_vector_ptr_append(&hv->vms, vm);
		}
		else if (NULL != *error)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Unable initialize vm %s: %s.", vms.values[i], *error);
			zbx_free(*error);
		}

	}

	ret = SUCCEED;
out:
	zbx_xml_free_doc(multipath_data);
	zbx_xml_free_doc(details);

	zbx_vector_str_clear_ext(&vms, zbx_str_free);
	zbx_vector_str_destroy(&vms);

	zbx_vector_str_clear_ext(&datastores, zbx_str_free);
	zbx_vector_str_destroy(&datastores);

	if (SUCCEED != ret)
		vmware_hv_clean(hv);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves a list of vmware service datacenters                    *
 *                                                                            *
 * Parameters: doc          - [IN] XML document                               *
 *             datacenters  - [OUT] list of vmware datacenters                *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_datacenters_list(xmlDoc *doc, zbx_vector_vmware_datacenter_t *datacenters)
{
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	char			*id, *name;
	zbx_vmware_datacenter_t	*datacenter;
	int			i, ret = FAIL;

	if (NULL == doc)
		return ret;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *) ZBX_XPATH_OBJECTS_BY_TYPE("Datacenter"), xpathCtx)))
		goto out;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		xmlXPathFreeObject(xpathObj);
		goto out;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_datacenter_reserve(datacenters, nodeset->nodeNr);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		if (NULL == (id = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XPATH_NN("obj"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get datacenter id.", __func__);
			continue;
		}

		if (NULL == (name = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XPATH_PROP_NAME_NODE("name"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get datacenter name for id: %s.", __func__, id);
			zbx_free(id);
			continue;
		}

		datacenter = (zbx_vmware_datacenter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_datacenter_t));
		datacenter->id = id;
		datacenter->name = name;
		zbx_vector_vmware_datacenter_append(datacenters, datacenter);
	}

	zbx_vector_vmware_datacenter_sort(datacenters, vmware_dc_id_compare);

	ret = SUCCEED;
	xmlXPathFreeObject(xpathObj);
out:
	xmlXPathFreeContext(xpathCtx);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves a list of all vmware service hypervisor ids             *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hvs          - [OUT] list of vmware hypervisor ids             *
 *             dss          - [OUT] list of vmware datastore ids              *
 *             datacenters  - [OUT] list of vmware datacenters                *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_hv_ds_dc_list(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_str_t *hvs, zbx_vector_str_t *dss, zbx_vector_vmware_datacenter_t *datacenters, char **error)
{
#	define ZBX_POST_VCENTER_HV_DS_LIST							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx xsi:type=\"ns0:RetrievePropertiesExRequestType\">"	\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>HostSystem</ns0:type>"			\
				"</ns0:propSet>"						\
				"<ns0:propSet>"							\
					"<ns0:type>Datastore</ns0:type>"			\
				"</ns0:propSet>"						\
				"<ns0:propSet>"							\
					"<ns0:type>Datacenter</ns0:type>"			\
					"<ns0:pathSet>name</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"Folder\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"				\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>visitFolders</ns0:name>"		\
						"<ns0:type>Folder</ns0:type>"			\
						"<ns0:path>childEntity</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToHf</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToVmf</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>crToH</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>crToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToDs</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>hToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToVmf</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>vmFolder</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToDs</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>datastore</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToHf</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>hostFolder</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>crToH</ns0:name>"			\
						"<ns0:type>ComputeResource</ns0:type>"		\
						"<ns0:path>host</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>crToRp</ns0:name>"			\
						"<ns0:type>ComputeResource</ns0:type>"		\
						"<ns0:path>resourcePool</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>rpToRp</ns0:name>"			\
						"<ns0:type>ResourcePool</ns0:type>"		\
						"<ns0:path>resourcePool</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>hToVm</ns0:name>"			\
						"<ns0:type>HostSystem</ns0:type>"		\
						"<ns0:path>vm</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>rpToVm</ns0:name>"			\
						"<ns0:type>ResourcePool</ns0:type>"		\
						"<ns0:path>vm</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
					"</ns0:selectSet>"					\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	char				tmp[MAX_STRING_LEN * 2];
	int				ret = FAIL;
	xmlDoc				*doc = NULL;
	zbx_property_collection_iter	*iter = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VCENTER_HV_DS_LIST,
			vmware_service_objects[service->type].property_collector,
			vmware_service_objects[service->type].root_folder);

	if (SUCCEED != zbx_property_collection_init(easyhandle, tmp, "propertyCollector", __func__, &iter, &doc, error))
	{
		goto out;
	}

	if (ZBX_VMWARE_TYPE_VCENTER == service->type)
		zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_HV) , hvs);
	else
		zbx_vector_str_append(hvs, zbx_strdup(NULL, "ha-host"));

	zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_DS), dss);
	vmware_service_get_datacenters_list(doc, datacenters);

	while (NULL != iter->token)
	{
		zbx_xml_free_doc(doc);
		doc = NULL;

		if (SUCCEED != zbx_property_collection_next(__func__, iter, &doc, error))
			goto out;

		if (ZBX_VMWARE_TYPE_VCENTER == service->type)
			zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_HV), hvs);

		zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_DS), dss);
		vmware_service_get_datacenters_list(doc, datacenters);
	}

	ret = SUCCEED;
out:
	zbx_property_collection_free(iter);
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s found hv:%d ds:%d dc:%d", __func__, zbx_result_string(ret),
			hvs->values_num, dss->values_num, datacenters->values_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves event session name                                      *
 *                                                                            *
 * Parameters: service        - [IN] the vmware service                       *
 *             easyhandle     - [IN] the CURL handle                          *
 *             event_session  - [OUT] a pointer to the output variable        *
 *             error          - [OUT] the error message in the case of failure*
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_session(const zbx_vmware_service_t *service, CURL *easyhandle,
		char **event_session, char **error)
{
#	define ZBX_POST_VMWARE_CREATE_EVENT_COLLECTOR				\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:CreateCollectorForEvents>"				\
			"<ns0:_this type=\"EventManager\">%s</ns0:_this>"	\
			"<ns0:filter/>"						\
		"</ns0:CreateCollectorForEvents>"				\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN];
	int	ret = FAIL;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_CREATE_EVENT_COLLECTOR,
			vmware_service_objects[service->type].event_manager);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	if (NULL == (*event_session = zbx_xml_doc_read_value(doc, "/*/*/*/*[@type='EventHistoryCollector']")))
	{
		*error = zbx_strdup(*error, "Cannot get EventHistoryCollector session.");
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s event_session:'%s'", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*event_session));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resets "scrollable view" to the latest events                     *
 *                                                                            *
 * Parameters: easyhandle     - [IN] the CURL handle                          *
 *             event_session  - [IN] event session (EventHistoryCollector)    *
 *                                   identifier                               *
 *             error          - [OUT] the error message in the case of failure*
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_reset_event_history_collector(CURL *easyhandle, const char *event_session, char **error)
{
#	define ZBX_POST_VMWARE_RESET_EVENT_COLLECTOR					\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:ResetCollector>"							\
			"<ns0:_this type=\"EventHistoryCollector\">%s</ns0:_this>"	\
		"</ns0:ResetCollector>"							\
		ZBX_POST_VSPHERE_FOOTER

	int		ret = FAIL;
	char		tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	event_session_esc = xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_RESET_EVENT_COLLECTOR, event_session_esc);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads events from "scrollable view" and moves it back in time     *
 *                                                                            *
 * Parameters: easyhandle     - [IN] the CURL handle                          *
 *             event_session  - [IN] event session (EventHistoryCollector)    *
 *                                   identifier                               *
 *             soap_count     - [IN] max count of events in response          *
 *             xdoc           - [OUT] the result as xml document              *
 *             error          - [OUT] the error message in the case of failure*
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_read_previous_events(CURL *easyhandle, const char *event_session, int soap_count,
		xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_VMWARE_READ_PREVIOUS_EVENTS					\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:ReadPreviousEvents>"						\
			"<ns0:_this type=\"EventHistoryCollector\">%s</ns0:_this>"	\
			"<ns0:maxCount>%d</ns0:maxCount>"				\
		"</ns0:ReadPreviousEvents>"						\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() soap_count: %d", __func__, soap_count);

	event_session_esc = xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_READ_PREVIOUS_EVENTS, event_session_esc, soap_count);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads events from "latest page" and moves it back in time         *
 *                                                                            *
 * Parameters: service        - [IN] the vmware service                       *
 *             easyhandle     - [IN] the CURL handle                          *
 *             event_session  - [IN] event session (EventHistoryCollector)    *
 *                                   identifier                               *
 *             xdoc           - [OUT] the result as xml document              *
 *             error          - [OUT] the error message in the case of failure*
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_latestpage(const zbx_vmware_service_t *service, CURL *easyhandle,
		const char *event_session, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_VMWARE_READ_EVENT_LATEST_PAGE						\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>EventHistoryCollector</ns0:type>"		\
					"<ns0:pathSet>latestPage</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"EventHistoryCollector\">%s</ns0:obj>"	\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	event_session_esc = xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_READ_EVENT_LATEST_PAGE,
			vmware_service_objects[service->type].property_collector, event_session_esc);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_READ_EVENT_LATEST_PAGE
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys event session                                            *
 *                                                                            *
 * Parameters: easyhandle     - [IN] the CURL handle                          *
 *             event_session  - [IN] event session (EventHistoryCollector)    *
 *                                   identifier                               *
 *             error          - [OUT] the error message in the case of failure*
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_destroy_event_session(CURL *easyhandle, const char *event_session, char **error)
{
#	define ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR					\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:DestroyCollector>"						\
			"<ns0:_this type=\"EventHistoryCollector\">%s</ns0:_this>"	\
		"</ns0:DestroyCollector>"						\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	event_session_esc = xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR, event_session_esc);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: read event data by id from xml and put to array of events         *
 *                                                                            *
 * Parameters: events    - [IN/OUT] the array of parsed events                *
 *             xml_event - [IN] the xml node and id of parsed event           *
 *             xdoc      - [IN] xml document with eventlog records            *
 *             alloc_sz  - [OUT] allocated memory size for events             *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 ******************************************************************************/
static int	vmware_service_put_event_data(zbx_vector_ptr_t *events, zbx_id_xmlnode_t xml_event, xmlDoc *xdoc,
		zbx_uint64_t *alloc_sz)
{
	zbx_vmware_event_t		*event = NULL;
	char				*message, *time_str, *ip;
	int				timestamp = 0, nodes_det = 0;
	unsigned int			i;
	zbx_uint64_t			sz;
	static event_hostinfo_node_t	host_nodes[] =
	{
		{ ZBX_XPATH_EVT_INFO("datacenter"),		ZBX_HOSTINFO_NODES_DATACENTER,	NULL },
		{ ZBX_XPATH_EVT_INFO("computeResource"),	ZBX_HOSTINFO_NODES_COMPRES,	NULL },
		{ ZBX_XPATH_EVT_INFO("host"),			ZBX_HOSTINFO_NODES_HOST,	NULL },
		{ ZBX_XPATH_EVT_ARGUMENT("_sourcehost_"),	ZBX_HOSTINFO_NODES_HOST,	NULL },
		{ ZBX_XPATH_EVT_ARGUMENT("entityName"),		ZBX_HOSTINFO_NODES_HOST,	NULL }
	};

	if (NULL == (message = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("fullFormattedMessage"))))
	{
		zabbix_log(LOG_LEVEL_TRACE, "skipping event key '" ZBX_FS_UI64 "', fullFormattedMessage"
				" is missing", xml_event.id);
		return FAIL;
	}

	for (i = 0; i < ARRSIZE(host_nodes); i++)
	{
		if (0 == (nodes_det & host_nodes[i].flag) && NULL != (host_nodes[i].name =
				zbx_xml_node_read_value(xdoc, xml_event.xml_node, host_nodes[i].node_name)))
		{
			nodes_det |= host_nodes[i].flag;

			if (ZBX_HOSTINFO_NODES_MASK_ALL == (nodes_det & ZBX_HOSTINFO_NODES_MASK_ALL))
				break;
		}
	}

	if (0 != (nodes_det & ZBX_HOSTINFO_NODES_HOST))
	{
		message = zbx_strdcat(message, "\n\nsource: ");

		for (i = 0; i < ARRSIZE(host_nodes); i++)
		{
			if (NULL == host_nodes[i].name)
				continue;

			message = zbx_dsprintf(message, "%s%s%s", message, host_nodes[i].name,
					0 != (host_nodes[i].flag & ZBX_HOSTINFO_NODES_HOST) ? "" : "/");
			zbx_free(host_nodes[i].name);
		}
	}
	else
	{
		if (0 != (nodes_det & ZBX_HOSTINFO_NODES_MASK_ALL))
		{
			for (i = 0; i < ARRSIZE(host_nodes); i++)
				zbx_free(host_nodes[i].name);
		}

		if (NULL != (ip = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("ipAddress"))))
		{
			message = zbx_dsprintf(message, "%s\n\nsource: %s", message, ip);
			zbx_free(ip);
		}
	}

	zbx_replace_invalid_utf8(message);

	if (NULL == (time_str = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("createdTime"))))
	{
		zabbix_log(LOG_LEVEL_TRACE, "createdTime is missing for event key '" ZBX_FS_UI64 "'", xml_event.id);
	}
	else
	{
		int	year, mon, mday, hour, min, sec, t;

		/* 2013-06-04T14:19:23.406298Z */
		if (6 != sscanf(time_str, "%d-%d-%dT%d:%d:%d.%*s", &year, &mon, &mday, &hour, &min, &sec))
		{
			zabbix_log(LOG_LEVEL_TRACE, "unexpected format of createdTime '%s' for event"
					" key '" ZBX_FS_UI64 "'", time_str, xml_event.id);
		}
		else if (SUCCEED != zbx_utc_time(year, mon, mday, hour, min, sec, &t))
		{
			zabbix_log(LOG_LEVEL_TRACE, "cannot convert createdTime '%s' for event key '"
					ZBX_FS_UI64 "'", time_str, xml_event.id);
		}
		else
			timestamp = t;

		zbx_free(time_str);
	}

	event = (zbx_vmware_event_t *)zbx_malloc(event, sizeof(zbx_vmware_event_t));
	event->key = xml_event.id;
	event->timestamp = timestamp;
	event->message = evt_msg_strpool_strdup(message, &sz);
	zbx_free(message);
	zbx_vector_ptr_append(events, event);

	if (0 < sz)
		*alloc_sz += zbx_mem_required_chunk_size(sz);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse multiple events data                                        *
 *                                                                            *
 * Parameters: events     - [IN/OUT] the array of parsed events               *
 *             last_key   - [IN] the key of last parsed event                 *
 *             is_prop    - [IN] read events from RetrieveProperties XML      *
 *             xdoc       - [IN] xml document with eventlog records           *
 *             alloc_sz   - [OUT] allocated memory size for events            *
 *             node_count - [OUT] count of xml event nodes                    *
 *                                                                            *
 * Return value: The count of events successfully parsed                      *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_parse_event_data(zbx_vector_ptr_t *events, zbx_uint64_t last_key, const int is_prop,
		xmlDoc *xdoc, zbx_uint64_t *alloc_sz, int *node_count)
{
#	define LAST_KEY(evs)	(((const zbx_vmware_event_t *)evs->values[evs->values_num - 1])->key)

	zbx_vector_id_xmlnode_t	ids;
	int			i, parsed_num = 0;
	char			*value;
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	static int		is_clear = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() last_key:" ZBX_FS_UI64, __func__, last_key);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)(0 == is_prop ? "/*/*/*"ZBX_XPATH_LN("returnval") :
			"/*/*/*"ZBX_XPATH_LN("returnval")"/*/*/*"ZBX_XPATH_LN("Event")), xpathCtx)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot make evenlog list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		if (NULL != node_count)
			*node_count = 0;

		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find items in evenlog list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_id_xmlnode_create(&ids);
	zbx_vector_id_xmlnode_reserve(&ids, nodeset->nodeNr);

	if (NULL != node_count)
		*node_count = nodeset->nodeNr;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_id_xmlnode_t	xml_event;
		zbx_uint64_t		key;

		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XPATH_NN("key"))))
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping eventlog record without key, xml number '%d'", i);
			continue;
		}

		key = (unsigned int) atoi(value);

		if (0 == key && 0 == isdigit(value[('-' == *value || '+' == *value) ? 1 : 0 ]))
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping eventlog key '%s', not a number", value);
			zbx_free(value);
			continue;
		}

		zbx_free(value);

		if (key <= last_key)
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping event key '" ZBX_FS_UI64 "', has been processed", key);
			continue;
		}

		xml_event.id = key;
		xml_event.xml_node = nodeset->nodeTab[i];
		zbx_vector_id_xmlnode_append(&ids, xml_event);
	}

	if (0 != ids.values_num)
	{
		zbx_vector_id_xmlnode_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_ptr_reserve(events, ids.values_num + events->values_alloc);

		/* validate that last event from "latestPage" is connected with first event from ReadPreviousEvents */
		if (0 != events->values_num && LAST_KEY(events) != ids.values[ids.values_num -1].id + 1)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d is_clear:%d id gap:%d", __func__,
					events->values_num, is_clear,
					(int)(LAST_KEY(events) - (ids.values[ids.values_num -1].id + 1)));

			/* if sequence of events is not continuous, ignore events from "latestPage" property */
			if (0 != is_clear)
				zbx_vector_ptr_clear_ext(events, (zbx_clean_func_t)vmware_event_free);
		}

		/* we are reading "scrollable views" in reverse chronological order, */
		/* so inside a "scrollable view" latest events should come first too */
		for (i = ids.values_num - 1; i >= 0; i--)
		{
			if (SUCCEED == vmware_service_put_event_data(events, ids.values[i], xdoc, alloc_sz))
				parsed_num++;
		}
	}
	else if (0 != last_key && 0 != events->values_num && LAST_KEY(events) != last_key + 1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d is_clear:%d last_key id gap:%d", __func__,
				events->values_num, is_clear, (int)(LAST_KEY(events) - (last_key + 1)));

		/* if sequence of events is not continuous, ignore events from "latestPage" property */
		if (0 != is_clear)
			zbx_vector_ptr_clear_ext(events, (zbx_clean_func_t)vmware_event_free);
	}

	zbx_vector_id_xmlnode_destroy(&ids);
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	is_clear = is_prop;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() parsed:%d", __func__, parsed_num);

	return parsed_num;

#	undef LAST_KEY
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves event data                                              *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             last_key     - [IN] the ID of last processed event             *
 *             events       - [OUT] a pointer to the output variable          *
 *             alloc_sz     - [OUT] allocated memory size for events          *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_uint64_t last_key, zbx_vector_ptr_t *events, zbx_uint64_t *alloc_sz, char **error)
{
#	define ATTEMPTS_NUM	4
#	define EVENT_TAG	1
#	define RETURNVAL_TAG	0
#	define LAST_KEY(evs)	(((const zbx_vmware_event_t *)evs->values[evs->values_num - 1])->key)

	char		*event_session = NULL, *err = NULL;
	int		ret = FAIL, node_count = 1, soap_retry = ATTEMPTS_NUM,
			soap_count = 5; /* 10 - initial value of eventlog records number in one response */
	xmlDoc		*doc = NULL;
	zbx_uint64_t	eventlog_last_key;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != vmware_service_get_event_session(service, easyhandle, &event_session, error))
		goto out;

	if (SUCCEED != vmware_service_reset_event_history_collector(easyhandle, event_session, error))
		goto end_session;

	if (NULL != service->data && 0 != service->data->events.values_num &&
			((const zbx_vmware_event_t *)service->data->events.values[0])->key > last_key)
	{
		eventlog_last_key = ((const zbx_vmware_event_t *)service->data->events.values[0])->key;
	}
	else
		eventlog_last_key = last_key;

	if (SUCCEED != vmware_service_get_event_latestpage(service, easyhandle, event_session, &doc, error))
		goto end_session;

	if (0 < vmware_service_parse_event_data(events, eventlog_last_key, EVENT_TAG, doc, alloc_sz, NULL) &&
			LAST_KEY(events) == eventlog_last_key + 1)
	{
		zabbix_log(LOG_LEVEL_TRACE, "%s() latestPage events:%d", __func__, events->values_num);

		ret = SUCCEED;
		goto end_session;
	}

	do
	{
		zbx_xml_free_doc(doc);
		doc = NULL;

		if ((ZBX_MAXQUERYMETRICS_UNLIMITED / 2) >= soap_count)
			soap_count = soap_count * 2;
		else if (ZBX_MAXQUERYMETRICS_UNLIMITED != soap_count)
			soap_count = ZBX_MAXQUERYMETRICS_UNLIMITED;

		if (0 != events->values_num && (LAST_KEY(events) - eventlog_last_key -1) < (unsigned int)soap_count)
		{
			soap_count = (int)(LAST_KEY(events) - eventlog_last_key - 1);
		}

		if (!ZBX_IS_RUNNING() || (0 < soap_count && SUCCEED != vmware_service_read_previous_events(easyhandle,
				event_session, soap_count, &doc, error)))
		{
			goto end_session;
		}

		if (0 != node_count)
			soap_retry = ATTEMPTS_NUM;
	}
	while (0 < vmware_service_parse_event_data(events, eventlog_last_key, RETURNVAL_TAG, doc, alloc_sz,
			&node_count) || (0 == node_count && 0 < soap_retry--));

	if (0 != eventlog_last_key && 0 != events->values_num && LAST_KEY(events) != eventlog_last_key + 1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d id gap:%d", __func__, events->values_num,
				(int)(LAST_KEY(events) - (eventlog_last_key + 1)));
	}

	ret = SUCCEED;
end_session:
	if (SUCCEED != vmware_service_destroy_event_session(easyhandle, event_session, &err))
	{
		*error = zbx_strdcatf(*error, "%s%s", NULL != *error ? "; " : "", err);
		zbx_free(err);
		ret = FAIL;
	}
out:
	zbx_free(event_session);
	zbx_xml_free_doc(doc);

	if (SUCCEED == ret && 10 == soap_count && 0 == events->values_num)
		zabbix_log(LOG_LEVEL_WARNING, "vmware events collector returned empty result");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s events:%d", __func__, zbx_result_string(ret), events->values_num);

	return ret;

#	undef ATTEMPTS_NUM
#	undef EVENT_TAG
#	undef RETURNVAL_TAG
#	undef LAST_KEY
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves data only last event                                    *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             events       - [OUT] a pointer to the output variable          *
 *             alloc_sz     - [OUT] allocated memory size for events          *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_last_event_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_ptr_t *events, zbx_uint64_t *alloc_sz, char **error)
{
#	define ZBX_POST_VMWARE_LASTEVENT 								\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>EventManager</ns0:type>"				\
					"<ns0:all>false</ns0:all>"					\
					"<ns0:pathSet>latestEvent</ns0:pathSet>"			\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"EventManager\">%s</ns0:obj>"			\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char			tmp[MAX_STRING_LEN], *value;
	int			ret = FAIL;
	xmlDoc			*doc = NULL;
	zbx_id_xmlnode_t	xml_event;
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_LASTEVENT,
			vmware_service_objects[service->type].property_collector,
			vmware_service_objects[service->type].event_manager);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_PROP_NAME("latestEvent"), xpathCtx)))
	{
		*error = zbx_strdup(*error, "Cannot make lastevenlog list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		*error = zbx_strdup(*error, "Cannot find items in lastevenlog list.");
		goto clean;
	}

	xml_event.xml_node = xpathObj->nodesetval->nodeTab[0];

	if (NULL == (value = zbx_xml_node_read_value(doc, xml_event.xml_node, ZBX_XPATH_NN("key"))))
	{
		*error = zbx_strdup(*error, "Cannot find last event key");
		goto clean;
	}

	xml_event.id = (unsigned int) atoi(value);

	if (0 == xml_event.id && 0 == isdigit(value[('-' == *value || '+' == *value) ? 1 : 0 ]))
	{
		*error = zbx_dsprintf(*error, "Cannot convert eventlog key from %s", value);
		zbx_free(value);
		goto clean;
	}

	zbx_free(value);

	if (SUCCEED != vmware_service_put_event_data(events, xml_event, doc, alloc_sz))
	{
		*error = zbx_dsprintf(*error, "Cannot retrieve last eventlog data for key "ZBX_FS_UI64, xml_event.id);
		goto clean;
	}

	ret = SUCCEED;
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s last_key:" ZBX_FS_UI64, __func__, zbx_result_string(ret),
			(SUCCEED == ret ? xml_event.id : 0));
	return ret;

#	undef ZBX_POST_VMWARE_LASTEVENT
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves a list of vmware service clusters                       *
 *                                                                            *
 * Parameters: easyhandle   - [IN] the CURL handle                            *
 *             clusters     - [OUT] a pointer to the output variable          *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_clusters(CURL *easyhandle, xmlDoc **clusters, char **error)
{
#	define ZBX_POST_VCENTER_CLUSTER								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx xsi:type=\"ns0:RetrievePropertiesExRequestType\">"	\
			"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>ClusterComputeResource</ns0:type>"		\
					"<ns0:pathSet>name</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"Folder\">group-d1</ns0:obj>"		\
					"<ns0:skip>false</ns0:skip>"				\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>visitFolders</ns0:name>"		\
						"<ns0:type>Folder</ns0:type>"			\
						"<ns0:path>childEntity</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToHf</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToVmf</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>crToH</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>crToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToDs</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>hToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToVmf</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>vmFolder</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToDs</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>datastore</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToHf</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>hostFolder</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>crToH</ns0:name>"			\
						"<ns0:type>ComputeResource</ns0:type>"		\
						"<ns0:path>host</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>crToRp</ns0:name>"			\
						"<ns0:type>ComputeResource</ns0:type>"		\
						"<ns0:path>resourcePool</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>rpToRp</ns0:name>"			\
						"<ns0:type>ResourcePool</ns0:type>"		\
						"<ns0:path>resourcePool</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>hToVm</ns0:name>"			\
						"<ns0:type>HostSystem</ns0:type>"		\
						"<ns0:path>vm</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>rpToVm</ns0:name>"			\
						"<ns0:type>ResourcePool</ns0:type>"		\
						"<ns0:path>vm</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
					"</ns0:selectSet>"					\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_VCENTER_CLUSTER, clusters, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VCENTER_CLUSTER
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves status of the specified vmware cluster                  *
 *                                                                            *
 * Parameters: easyhandle   - [IN] the CURL handle                            *
 *             clusterid    - [IN] the cluster id                             *
 *             status       - [OUT] a pointer to the output variable          *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_cluster_status(CURL *easyhandle, const char *clusterid, char **status, char **error)
{
#	define ZBX_POST_VMWARE_CLUSTER_STATUS 								\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"		\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>ClusterComputeResource</ns0:type>"			\
					"<ns0:all>false</ns0:all>"					\
					"<ns0:pathSet>summary.overallStatus</ns0:pathSet>"		\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"ClusterComputeResource\">%s</ns0:obj>"		\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options></ns0:options>"							\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN], *clusterid_esc;
	int	ret = FAIL;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() clusterid:'%s'", __func__, clusterid);

	clusterid_esc = xml_escape_dyn(clusterid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_CLUSTER_STATUS, clusterid_esc);

	zbx_free(clusterid_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	*status = zbx_xml_doc_read_value(doc, ZBX_XPATH_PROP_NAME("summary.overallStatus"));

	ret = SUCCEED;
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_CLUSTER_STATUS
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates list of vmware cluster objects                            *
 *                                                                            *
 * Parameters: easyhandle   - [IN] the CURL handle                            *
 *             clusters     - [OUT] a pointer to the resulting cluster vector *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_cluster_list(CURL *easyhandle, zbx_vector_ptr_t *clusters, char **error)
{
	char			xpath[MAX_STRING_LEN], *name;
	xmlDoc			*cluster_data = NULL;
	zbx_vector_str_t	ids;
	zbx_vmware_cluster_t	*cluster;
	int			i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&ids);

	if (SUCCEED != vmware_service_get_clusters(easyhandle, &cluster_data, error))
		goto out;

	zbx_xml_read_values(cluster_data, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_CLUSTER), &ids);
	zbx_vector_ptr_reserve(clusters, ids.values_num + clusters->values_alloc);

	for (i = 0; i < ids.values_num; i++)
	{
		char	*status;

		zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_PROP_OBJECTS_ID(ZBX_VMWARE_SOAP_CLUSTER, "[text()='%s']")
						"/" ZBX_XPATH_PROP_NAME_NODE("name"), ids.values[i]);

		if (NULL == (name = zbx_xml_doc_read_value(cluster_data, xpath)))
			continue;

		if (SUCCEED != vmware_service_get_cluster_status(easyhandle, ids.values[i], &status, error))
		{
			zbx_free(name);
			goto out;
		}

		cluster = (zbx_vmware_cluster_t *)zbx_malloc(NULL, sizeof(zbx_vmware_cluster_t));
		cluster->id = zbx_strdup(NULL, ids.values[i]);
		cluster->name = name;
		cluster->status = status;

		zbx_vector_ptr_append(clusters, cluster);
	}

	ret = SUCCEED;
out:
	zbx_xml_free_doc(cluster_data);
	zbx_vector_str_clear_ext(&ids, zbx_str_free);
	zbx_vector_str_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s found:%d", __func__, zbx_result_string(ret),
			clusters->values_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get statically defined default value for maxquerymetrics for      *
 *          vcenter when it could not be retrieved from soap, depending on    *
 *          vcenter version (https://kb.vmware.com/s/article/2107096)         *
 * Parameters: service   - [IN] the vmware service                            *
 *                                                                            *
 * Return value: maxquerymetrics                                              *
 *                                                                            *
 ******************************************************************************/
static unsigned int	get_default_maxquerymetrics_for_vcenter(const zbx_vmware_service_t *service)
{
	if ((6 == service->major_version && 5 <= service->minor_version) ||
			6 < service->major_version)
	{
		return ZBX_VCENTER_6_5_0_AND_MORE_STATS_MAXQUERYMETRICS;
	}
	else
		return ZBX_VCENTER_LESS_THAN_6_5_0_STATS_MAXQUERYMETRICS;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get vpxd.stats.maxquerymetrics parameter from vcenter only        *
 *                                                                            *
 * Parameters: easyhandle   - [IN] the CURL handle                            *
 *             service      - [IN] the vmware service                         *
 *             max_qm       - [OUT] max count of Datastore metrics in one     *
 *                                  request                                   *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_maxquerymetrics(CURL *easyhandle, zbx_vmware_service_t *service, int *max_qm,
		char **error)
{
#	define ZBX_POST_MAXQUERYMETRICS								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:QueryOptions>"								\
			"<ns0:_this type=\"OptionManager\">VpxSettings</ns0:_this>"		\
			"<ns0:name>config.vpxd.stats.maxQueryMetrics</ns0:name>"		\
		"</ns0:QueryOptions>"								\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	*val;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_MAXQUERYMETRICS, &doc, NULL, error))
	{
		if (NULL == doc)	/* if not SOAP error */
			goto out;

		zabbix_log(LOG_LEVEL_DEBUG, "Error of query maxQueryMetrics: %s.", *error);
		zbx_free(*error);
	}

	ret = SUCCEED;

	if (NULL == (val = zbx_xml_doc_read_value(doc, ZBX_XPATH_MAXQUERYMETRICS())))
	{
		*max_qm = get_default_maxquerymetrics_for_vcenter(service);
		zabbix_log(LOG_LEVEL_DEBUG, "maxQueryMetrics defaults to %d", *max_qm);
		goto out;
	}

	/* vmware article 2107096                                                                    */
	/* Edit the config.vpxd.stats.maxQueryMetrics key in the advanced settings of vCenter Server */
	/* To disable the limit, set a value to -1                                                   */
	/* Edit the web.xml file. To disable the limit, set a value 0                                */
	if (-1 == atoi(val))
	{
		*max_qm = ZBX_MAXQUERYMETRICS_UNLIMITED;
	}
	else if (SUCCEED != is_uint31(val, max_qm))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot convert maxQueryMetrics from %s.", val);
		*max_qm = get_default_maxquerymetrics_for_vcenter(service);
		zabbix_log(LOG_LEVEL_DEBUG, "maxQueryMetrics defaults to %d", *max_qm);
	}
	else if (0 == *max_qm)
	{
		*max_qm = ZBX_MAXQUERYMETRICS_UNLIMITED;
	}

	zbx_free(val);
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
/******************************************************************************
 *                                                                            *
 * Purpose: creates a new performance counter object in shared memory and     *
 *          adds to the specified vector                                      *
 *                                                                            *
 * Parameters: counters  - [IN/OUT] the vector the created performance        *
 *                                  counter object should be added to         *
 *             counterid - [IN] the performance counter id                    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_counters_add_new(zbx_vector_ptr_t *counters, zbx_uint64_t counterid)
{
	zbx_vmware_perf_counter_t	*counter;

	counter = (zbx_vmware_perf_counter_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_perf_counter_t));
	counter->counterid = counterid;
	counter->state = ZBX_VMWARE_COUNTER_NEW;

	zbx_vector_str_uint64_pair_create_ext(&counter->values, __vm_mem_malloc_func, __vm_mem_realloc_func,
			__vm_mem_free_func);

	zbx_vector_ptr_append(counters, counter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes vmware service object                                 *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 * Comments: While the service object can't be accessed from other processes  *
 *           during initialization it's still processed outside vmware locks  *
 *           and therefore must not allocate/free shared memory.              *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_initialize(zbx_vmware_service_t *service, CURL *easyhandle, char **error)
{
#	define UNPARSED_SERVICE_MAJOR_VERSION_DELIM	"."

	char			*version_without_major, *version = NULL, *fullname = NULL;
	zbx_vector_ptr_t	counters;
	int			ret = FAIL;

	zbx_vector_ptr_create(&counters);

	if (SUCCEED != vmware_service_get_contents(easyhandle, &version, &fullname, error))
		goto out;

	if (0 != (service->state & ZBX_VMWARE_STATE_READY) && 0 == strcmp(service->version, version))
	{
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != vmware_service_get_perf_counters(service, easyhandle, &counters, error))
		goto out;

	zbx_vmware_lock();

	if (NULL != service->version)
		vmware_shared_strfree(service->version);

	if (NULL != service->fullname)
		vmware_shared_strfree(service->fullname);

	if (0 != service->entities.num_data)
	{
		zbx_hashset_iter_t		iter;
		zbx_vmware_perf_entity_t	*entity;

		zbx_hashset_iter_reset(&service->entities, &iter);

		while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
			vmware_shared_perf_entity_clean(entity);

		zbx_hashset_clear(&service->entities);
	}

	if (0 != service->counters.num_data)
	{
		zbx_hashset_iter_t		iter;
		zbx_vmware_counter_t		*counter;

		zbx_hashset_iter_reset(&service->counters, &iter);

		while (NULL != (counter = (zbx_vmware_counter_t *)zbx_hashset_iter_next(&iter)))
			vmware_counter_shared_clean(counter);

		zbx_hashset_clear(&service->counters);
	}

	service->fullname = vmware_shared_strdup(fullname);
	vmware_counters_shared_copy(&service->counters, &counters);
	service->version = vmware_shared_strdup(version);
	service->major_version = atoi(version);

	/* version should have the "x.y.z" format, but there is also an "x.y Un" format in nature */
	/* according to https://www.vmware.com/support/policies/version.html */
	if (NULL == (version_without_major = strstr(version, UNPARSED_SERVICE_MAJOR_VERSION_DELIM)))
	{
		*error = zbx_dsprintf(*error, "Invalid version: %s.", version);
		goto unlock;
	}

	service->minor_version = atoi(strlen(UNPARSED_SERVICE_MAJOR_VERSION_DELIM) + version_without_major);

	ret = SUCCEED;
unlock:
	zbx_vmware_unlock();
out:
	zbx_free(version);
	zbx_free(fullname);

	zbx_vector_ptr_clear_ext(&counters, (zbx_mem_free_func_t)vmware_counter_free);
	zbx_vector_ptr_destroy(&counters);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds entity to vmware service performance entity list             *
 *                                                                            *
 * Parameters: service  - [IN] the vmware service                             *
 *             type     - [IN] the performance entity type (HostSystem,       *
 *                             (Datastore, VirtualMachine...)                 *
 *             id       - [IN] the performance entity id                      *
 *             counters - [IN] NULL terminated list of performance counters   *
 *                             to be monitored for this entity                *
 *             instance - [IN] the performance counter instance name          *
 *             now      - [IN] the current timestamp                          *
 *                                                                            *
 * Comments: The performance counters are specified by their path:            *
 *             <group>/<key>[<rollup type>]                                   *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_add_perf_entity(zbx_vmware_service_t *service, const char *type, const char *id,
		const char **counters, const char *instance, int now)
{
	zbx_vmware_perf_entity_t	entity, *pentity;
	zbx_uint64_t			counterid;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s", __func__, type, id);

	if (NULL == (pentity = zbx_vmware_service_get_perf_entity(service, type, id)))
	{
		entity.type = vmware_shared_strdup(type);
		entity.id = vmware_shared_strdup(id);

		pentity = (zbx_vmware_perf_entity_t *)zbx_hashset_insert(&service->entities, &entity, sizeof(zbx_vmware_perf_entity_t));

		zbx_vector_ptr_create_ext(&pentity->counters, __vm_mem_malloc_func, __vm_mem_realloc_func,
				__vm_mem_free_func);

		for (i = 0; NULL != counters[i]; i++)
		{
			if (SUCCEED == zbx_vmware_service_get_counterid(service, counters[i], &counterid, NULL))
				vmware_counters_add_new(&pentity->counters, counterid);
			else
				zabbix_log(LOG_LEVEL_DEBUG, "cannot find performance counter %s", counters[i]);
		}

		zbx_vector_ptr_sort(&pentity->counters, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		pentity->refresh = ZBX_VMWARE_PERF_INTERVAL_UNKNOWN;
		pentity->query_instance = vmware_shared_strdup(instance);
		pentity->error = NULL;
	}

	pentity->last_seen = now;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() perfcounters:%d", __func__, pentity->counters.values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds new or remove old entities (hypervisors, virtual machines)   *
 *          from service performance entity list                              *
 *                                                                            *
 * Parameters: service - [IN] the vmware service                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_update_perf_entities(zbx_vmware_service_t *service)
{
	int			i;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_vm_t		*vm;
	zbx_hashset_iter_t	iter;

	const char			*hv_perfcounters[] = {
						"net/packetsRx[summation]", "net/packetsTx[summation]",
						"net/received[average]", "net/transmitted[average]",
						"datastore/totalReadLatency[average]",
						"datastore/totalWriteLatency[average]", "cpu/usage[average]",
						"cpu/utilization[average]", "power/power[average]",
						"power/powerCap[average]",
						NULL
					};

	const char			*vm_perfcounters[] = {
						"virtualDisk/read[average]", "virtualDisk/write[average]",
						"virtualDisk/numberReadAveraged[average]",
						"virtualDisk/numberWriteAveraged[average]",
						"net/packetsRx[summation]", "net/packetsTx[summation]",
						"net/received[average]", "net/transmitted[average]",
						"cpu/ready[summation]", "net/usage[average]", "cpu/usage[average]",
						"cpu/latency[average]", "cpu/readiness[average]",
						"cpu/swapwait[summation]", "sys/osUptime[latest]",
						"mem/consumed[average]", "mem/usage[average]", "mem/swapped[average]",
						"net/usage[average]", "virtualDisk/readOIO[latest]",
						"virtualDisk/writeOIO[latest]",
						"virtualDisk/totalWriteLatency[average]",
						"virtualDisk/totalReadLatency[average]",
						NULL
					};

	const char			*ds_perfcounters[] = {
						"disk/used[latest]", "disk/provisioned[latest]",
						"disk/capacity[latest]", NULL
					};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* update current performance entities */
	zbx_hashset_iter_reset(&service->data->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		vmware_service_add_perf_entity(service, ZBX_VMWARE_SOAP_HV, hv->id, hv_perfcounters, "*",
				service->lastcheck);

		for (i = 0; i < hv->vms.values_num; i++)
		{
			vm = (zbx_vmware_vm_t *)hv->vms.values[i];
			vmware_service_add_perf_entity(service, ZBX_VMWARE_SOAP_VM, vm->id, vm_perfcounters, "*",
					service->lastcheck);
			zabbix_log(LOG_LEVEL_TRACE, "%s() for type: VirtualMachine hv id: %s hv uuid: %s linked vm id:"
					" %s vm uuid: %s", __func__, hv->id, hv->uuid, vm->id, vm->uuid);
		}
	}

	if (ZBX_VMWARE_TYPE_VCENTER == service->type)
	{
		for (i = 0; i < service->data->datastores.values_num; i++)
		{
			zbx_vmware_datastore_t	*ds = service->data->datastores.values[i];
			vmware_service_add_perf_entity(service, ZBX_VMWARE_SOAP_DS, ds->id, ds_perfcounters, "",
					service->lastcheck);
			zabbix_log(LOG_LEVEL_TRACE, "%s() for type: Datastore id: %s name: %s uuid: %s", __func__,
					ds->id, ds->name, ds->uuid);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() entities:%d", __func__, service->entities.num_data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates object with a new data from vmware service                *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_update(zbx_vmware_service_t *service)
{
	CURL			*easyhandle = NULL;
	CURLoption		opt;
	CURLcode		err;
	struct curl_slist	*headers = NULL;
	zbx_vmware_data_t	*data;
	zbx_vector_str_t	hvs, dss;
	zbx_vector_ptr_t	events;
	int			i, ret = FAIL;
	ZBX_HTTPPAGE		page;	/* 347K/87K */
	unsigned char		evt_pause = 0, evt_skip_old;
	zbx_uint64_t		evt_last_key, events_sz = 0;
	char			msg[MAX_STRING_LEN / 8];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	data = (zbx_vmware_data_t *)zbx_malloc(NULL, sizeof(zbx_vmware_data_t));
	memset(data, 0, sizeof(zbx_vmware_data_t));
	page.alloc = 0;

	zbx_hashset_create(&data->hvs, 1, vmware_hv_hash, vmware_hv_compare);
	zbx_vector_ptr_create(&data->clusters);
	zbx_vector_ptr_create(&data->events);
	zbx_vector_vmware_datastore_create(&data->datastores);
	zbx_vector_vmware_datacenter_create(&data->datacenters);

	zbx_vector_str_create(&hvs);
	zbx_vector_str_create(&dss);

	zbx_vmware_lock();
	evt_last_key = service->eventlog.last_key;
	evt_skip_old = service->eventlog.skip_old;
	zbx_vmware_unlock();

	if (NULL == (easyhandle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot initialize cURL library");
		goto out;
	}

	page.alloc = ZBX_INIT_UPD_XML_SIZE;
	page.data = (char *)zbx_malloc(NULL, page.alloc);
	headers = curl_slist_append(headers, ZBX_XML_HEADER1);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);
	headers = curl_slist_append(headers, ZBX_XML_HEADER3);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != vmware_service_authenticate(service, easyhandle, &page, &data->error))
		goto clean;

	if (SUCCEED != vmware_service_initialize(service, easyhandle, &data->error))
		goto clean;

	if (NULL != service->data && 0 != service->data->events.values_num && 0 == evt_skip_old &&
			((const zbx_vmware_event_t *)service->data->events.values[0])->key > evt_last_key)
	{
		evt_pause = 1;
	}

	if (SUCCEED != vmware_service_get_hv_ds_dc_list(service, easyhandle, &hvs, &dss, &data->datacenters,
			&data->error))
	{
		goto clean;
	}

	zbx_vector_vmware_datastore_reserve(&data->datastores, dss.values_num + data->datastores.values_alloc);

	for (i = 0; i < dss.values_num; i++)
	{
		zbx_vmware_datastore_t	*datastore;

		if (NULL != (datastore = vmware_service_create_datastore(service, easyhandle, dss.values[i])))
			zbx_vector_vmware_datastore_append(&data->datastores, datastore);
	}

	zbx_vector_vmware_datastore_sort(&data->datastores, vmware_ds_id_compare);

	if (SUCCEED != zbx_hashset_reserve(&data->hvs, hvs.values_num))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	for (i = 0; i < hvs.values_num; i++)
	{
		zbx_vmware_hv_t	hv_local, *hv;

		if (SUCCEED == vmware_service_init_hv(service, easyhandle, hvs.values[i], &data->datastores, &hv_local,
				&data->error))
		{
			if (NULL != (hv = zbx_hashset_search(&data->hvs, &hv_local)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Duplicate uuid of new hv id:%s name:%s uuid:%s and"
					" discovered hv with id:%s name:%s", hv_local.id,
					ZBX_NULL2EMPTY_STR(hv_local.props[ZBX_VMWARE_HVPROP_NAME]), hv_local.uuid,
					hv->id, ZBX_NULL2EMPTY_STR(hv->props[ZBX_VMWARE_HVPROP_NAME]));
				vmware_hv_clean(&hv_local);
				continue;
			}

			zbx_hashset_insert(&data->hvs, &hv_local, sizeof(hv_local));
		}
		else if (NULL != data->error)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Unable initialize hv %s: %s.", hvs.values[i], data->error);
			zbx_free(data->error);
		}
	}

	for (i = 0; i < data->datastores.values_num; i++)
	{
		zbx_vector_str_uint64_pair_sort(&data->datastores.values[i]->hv_uuids_access,
				zbx_str_uint64_pair_name_compare);
	}

	zbx_vector_vmware_datastore_sort(&data->datastores, vmware_ds_uuid_compare);

	if (0 == service->eventlog.req_sz && 0 == evt_pause)
	{
		/* skip collection of event data if we don't know where	*/
		/* we stopped last time or item can't accept values 	*/
		if (ZBX_VMWARE_EVENT_KEY_UNINITIALIZED != evt_last_key && 0 == evt_skip_old &&
				SUCCEED != vmware_service_get_event_data(service, easyhandle, evt_last_key,
				&data->events, &events_sz, &data->error))
		{
			goto clean;
		}

		if (0 != evt_skip_old)
		{
			char	*error = NULL;

			/* May not be present */
			if (SUCCEED != vmware_service_get_last_event_data(service, easyhandle, &data->events,
					&events_sz, &error))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Unable retrieve lastevent value: %s.", error);
				zbx_free(error);
			}
			else
				evt_skip_old = 0;
		}
	}
	else if (0 != service->eventlog.req_sz)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Postponed VMware events requires up to " ZBX_FS_UI64
				" bytes of free VMwareCache memory. Reading events skipped", service->eventlog.req_sz);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Previous events have not been read. Reading new events skipped");
	}

	if (ZBX_VMWARE_TYPE_VCENTER == service->type &&
			SUCCEED != vmware_service_get_cluster_list(easyhandle, &data->clusters, &data->error))
	{
		goto clean;
	}

	if (ZBX_VMWARE_TYPE_VCENTER != service->type)
		data->max_query_metrics = ZBX_VPXD_STATS_MAXQUERYMETRICS;
	else if (SUCCEED != vmware_service_get_maxquerymetrics(easyhandle, service, &data->max_query_metrics,
			&data->error))
	{
		goto clean;
	}

	if (SUCCEED != vmware_service_logout(service, easyhandle, &data->error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot close vmware connection: %s.", data->error);
		zbx_free(data->error);
	}

	ret = SUCCEED;
clean:
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(page.data);

	zbx_vector_str_clear_ext(&hvs, zbx_str_free);
	zbx_vector_str_destroy(&hvs);
	zbx_vector_str_clear_ext(&dss, zbx_str_free);
	zbx_vector_str_destroy(&dss);
out:
	zbx_vector_ptr_create(&events);
	zbx_vmware_lock();

	/* remove UPDATING flag and set READY or FAILED flag */
	service->state &= ~(ZBX_VMWARE_STATE_MASK | ZBX_VMWARE_STATE_UPDATING);
	service->state |= (SUCCEED == ret) ? ZBX_VMWARE_STATE_READY : ZBX_VMWARE_STATE_FAILED;

	if (0 < data->events.values_num)
	{
		if (0 != service->eventlog.oom)
			service->eventlog.oom = 0;

		events_sz += evt_req_chunk_size * data->events.values_num +
				zbx_mem_required_chunk_size(data->events.values_alloc * sizeof(zbx_vmware_event_t*));

		if (0 == service->eventlog.last_key || vmware_mem->free_size < events_sz ||
				SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		{
			for (i = 0; i < data->events.values_num; i++)
			{
				zbx_vmware_event_t	*event = data->events.values[i];

				if (SUCCEED == vmware_shared_strsearch(event->message))
				{
					events_sz -= zbx_mem_required_chunk_size(strlen(event->message) +
							REFCOUNT_FIELD_SIZE + 1 + ZBX_HASHSET_ENTRY_OFFSET);
				}
			}

			if (vmware_mem->free_size < events_sz)
			{
				service->eventlog.req_sz = events_sz;
				service->eventlog.oom = 1;
				zbx_vector_ptr_clear_ext(&data->events, (zbx_clean_func_t)vmware_event_free);

				zabbix_log(LOG_LEVEL_WARNING, "Postponed VMware events requires up to " ZBX_FS_UI64
						" bytes of free VMwareCache memory, while currently only " ZBX_FS_UI64
						" bytes are free. VMwareCache memory usage (free/strpool/total): "
						ZBX_FS_UI64 " / " ZBX_FS_UI64 " / " ZBX_FS_UI64, events_sz,
						vmware_mem->free_size, vmware_mem->free_size, vmware->strpool_sz,
						vmware_mem->total_size);
			}
			else if (0 == evt_pause)
			{
				int	level;

				level = 0 == service->eventlog.last_key ? LOG_LEVEL_WARNING : LOG_LEVEL_DEBUG;

				zabbix_log(level, "Processed VMware events requires up to " ZBX_FS_UI64
						" bytes of free VMwareCache memory. VMwareCache memory usage"
						" (free/strpool/total): " ZBX_FS_UI64 " / " ZBX_FS_UI64 " / "
						ZBX_FS_UI64, events_sz, vmware_mem->free_size, vmware->strpool_sz,
						vmware_mem->total_size);
			}
		}
	}
	else if (0 < service->eventlog.req_sz && service->eventlog.req_sz <= vmware_mem->free_size)
	{
		service->eventlog.req_sz = 0;
	}

	if (0 != evt_pause)
	{
		zbx_vector_ptr_append_array(&events, service->data->events.values, service->data->events.values_num);
		zbx_vector_ptr_reserve(&data->events, data->events.values_num + service->data->events.values_num);
		zbx_vector_ptr_clear(&service->data->events);
	}

	vmware_data_shared_free(service->data);
	service->data = vmware_data_shared_dup(data);
	service->eventlog.skip_old = evt_skip_old;

	if (0 != events.values_num)
		zbx_vector_ptr_append_array(&service->data->events, events.values, events.values_num);

	service->lastcheck = time(NULL);

	vmware_service_update_perf_entities(service);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		zbx_mem_dump_stats(LOG_LEVEL_DEBUG, vmware_mem);

	zbx_snprintf(msg, sizeof(msg), "Events:%d DC:%d DS:%d CL:%d HV:%d VM:%d"
			" VMwareCache memory usage (free/strpool/total): " ZBX_FS_UI64 " / " ZBX_FS_UI64 " / "
			ZBX_FS_UI64, NULL != service->data ? service->data->events.values_num : 0 ,
			NULL != service->data ? service->data->datacenters.values_num : 0 ,
			NULL != service->data ? service->data->datastores.values_num : 0 ,
			NULL != service->data ? service->data->clusters.values_num : 0 ,
			NULL != service->data ? service->data->hvs.num_data : 0 ,
			NULL != service->data ? service->data->vms_index.num_data : 0 ,
			vmware_mem->free_size, vmware->strpool_sz, vmware_mem->total_size);

	zbx_vmware_unlock();

	vmware_data_free(data);
	zbx_vector_ptr_destroy(&events);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s \tprocessed:" ZBX_FS_SIZE_T " bytes of data. %s", __func__,
			zbx_result_string(ret), (zbx_fs_size_t)page.alloc, msg);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware performance statistics data                        *
 *                                                                            *
 * Parameters: perfdata  - [OUT] the performance counter values               *
 *             xdoc      - [IN] the XML document containing performance       *
 *                              counter values for all entities               *
 *             node      - [IN] the XML node containing performance counter   *
 *                              values for the specified entity               *
 *                                                                            *
 * Return value: SUCCEED - the performance entity data was parsed             *
 *               FAIL    - the perofmance entity data did not contain valid   *
 *                         values                                             *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_process_perf_entity_data(zbx_vmware_perf_data_t *perfdata, xmlDoc *xdoc, xmlNode *node)
{
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	char			*instance, *counter, *value;
	int			i, values = 0, ret = FAIL;
	zbx_vector_ptr_t	*pervalues = &perfdata->values;
	zbx_vmware_perf_value_t	*perfvalue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);
	xpathCtx->node = node;

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)"*[local-name()='value']", xpathCtx)))
		goto out;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(pervalues, nodeset->nodeNr + pervalues->values_alloc);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i],
				"*[local-name()='value'][text() != '-1'][last()]")))
		{
			value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='value'][last()]");
		}

		instance = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='id']"
				"/*[local-name()='instance']");
		counter = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='id']"
				"/*[local-name()='counterId']");

		if (NULL != value && NULL != counter)
		{
			perfvalue = (zbx_vmware_perf_value_t *)zbx_malloc(NULL, sizeof(zbx_vmware_perf_value_t));

			ZBX_STR2UINT64(perfvalue->counterid, counter);
			perfvalue->instance = (NULL != instance ? instance : zbx_strdup(NULL, ""));

			if (0 == strcmp(value, "-1") || SUCCEED != is_uint64(value, &perfvalue->value))
			{
				perfvalue->value = ZBX_MAX_UINT64;
				zabbix_log(LOG_LEVEL_DEBUG, "PerfCounter inaccessible. type:%s object id:%s "
						"counter id:" ZBX_FS_UI64 " instance:%s value:%s", perfdata->type,
						perfdata->id, perfvalue->counterid, perfvalue->instance, value);
			}
			else if (FAIL == ret)
				ret = SUCCEED;

			zbx_vector_ptr_append(pervalues, perfvalue);

			instance = NULL;
			values++;
		}

		zbx_free(counter);
		zbx_free(instance);
		zbx_free(value);
	}

out:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values:%d", __func__, values);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware performance statistics data                        *
 *                                                                            *
 * Parameters: perfdata - [OUT] performance entity data                       *
 *             xdoc     - [IN] the performance data xml document              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_parse_perf_data(zbx_vector_ptr_t *perfdata, xmlDoc *xdoc)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)"/*/*/*/*", xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(perfdata, nodeset->nodeNr + perfdata->values_alloc);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_perf_data_t 	*data;
		int			ret = FAIL;

		data = (zbx_vmware_perf_data_t *)zbx_malloc(NULL, sizeof(zbx_vmware_perf_data_t));

		data->id = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='entity']");
		data->type = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='entity']/@type");
		data->error = NULL;
		zbx_vector_ptr_create(&data->values);

		if (NULL != data->type && NULL != data->id)
			ret = vmware_service_process_perf_entity_data(data, xdoc, nodeset->nodeTab[i]);

		if (SUCCEED == ret)
			zbx_vector_ptr_append(perfdata, data);
		else
			vmware_free_perfdata(data);
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds error for the specified perf entity                          *
 *                                                                            *
 * Parameters: perfdata - [OUT] the collected performance counter data        *
 *             type     - [IN] the performance entity type (HostSystem,       *
 *                             (Datastore, VirtualMachine...)                 *
 *             id       - [IN] the performance entity id                      *
 *             error    - [IN] the error to add                               *
 *                                                                            *
 * Comments: The performance counters are specified by their path:            *
 *             <group>/<key>[<rollup type>]                                   *
 *                                                                            *
 ******************************************************************************/
static void	vmware_perf_data_add_error(zbx_vector_ptr_t *perfdata, const char *type, const char *id,
		const char *error)
{
	zbx_vmware_perf_data_t	*data;

	data = zbx_malloc(NULL, sizeof(zbx_vmware_perf_data_t));

	data->type = zbx_strdup(NULL, type);
	data->id = zbx_strdup(NULL, id);
	data->error = zbx_strdup(NULL, error);
	zbx_vector_ptr_create(&data->values);

	zbx_vector_ptr_append(perfdata, data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware performance statistics of specified service         *
 *                                                                            *
 * Parameters: service  - [IN] the vmware service                             *
 *             perfdata - [IN/OUT] the performance data                       *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_copy_perf_data(zbx_vmware_service_t *service, zbx_vector_ptr_t *perfdata)
{
	int				i, j, index;
	zbx_vmware_perf_data_t		*data;
	zbx_vmware_perf_value_t		*value;
	zbx_vmware_perf_entity_t	*entity;
	zbx_vmware_perf_counter_t	*perfcounter;
	zbx_str_uint64_pair_t		perfvalue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < perfdata->values_num; i++)
	{
		data = (zbx_vmware_perf_data_t *)perfdata->values[i];

		if (NULL == (entity = zbx_vmware_service_get_perf_entity(service, data->type, data->id)))
			continue;

		if (NULL != data->error)
		{
			entity->error = vmware_shared_strdup(data->error);
			continue;
		}

		for (j = 0; j < data->values.values_num; j++)
		{
			value = (zbx_vmware_perf_value_t *)data->values.values[j];

			if (FAIL == (index = zbx_vector_ptr_bsearch(&entity->counters, &value->counterid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				continue;
			}

			perfcounter = (zbx_vmware_perf_counter_t *)entity->counters.values[index];

			perfvalue.name = vmware_shared_strdup(value->instance);
			perfvalue.value = value->value;

			zbx_vector_str_uint64_pair_append_ptr(&perfcounter->values, &perfvalue);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves performance counter values from vmware service          *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] prepared cURL connection handle            *
 *             entities     - [IN] the performance collector entities to      *
 *                                 retrieve counters for                      *
 *             counters_max - [IN] the maximum number of counters per query.  *
 *             perfdata     - [OUT] the performance counter values            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_retrieve_perf_counters(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_ptr_t *entities, int counters_max, zbx_vector_ptr_t *perfdata)
{
	char				*tmp = NULL, *error = NULL;
	size_t				tmp_alloc = 0, tmp_offset;
	int				i, j, start_counter = 0;
	zbx_vmware_perf_entity_t	*entity;
	xmlDoc				*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() counters_max:%d", __func__, counters_max);

	while (0 != entities->values_num)
	{
		int	counters_num = 0;

		tmp_offset = 0;
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_HEADER);
		zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:QueryPerf>"
				"<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>",
				vmware_service_objects[service->type].performance_manager);

		zbx_vmware_lock();

		for (i = entities->values_num - 1; 0 <= i && counters_num < counters_max;)
		{
			char	*id_esc;

			entity = (zbx_vmware_perf_entity_t *)entities->values[i];

			id_esc = xml_escape_dyn(entity->id);

			/* add entity performance counter request */
			zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:querySpec>"
					"<ns0:entity type=\"%s\">%s</ns0:entity>", entity->type, id_esc);

			zbx_free(id_esc);

			if (ZBX_VMWARE_PERF_INTERVAL_NONE == entity->refresh)
			{
				time_t	st_raw;
				struct	tm st;
				char	st_str[ZBX_XML_DATETIME];

				/* add startTime for entity performance counter request for decrease XML data load */
				st_raw = zbx_time() - SEC_PER_HOUR;
				gmtime_r(&st_raw, &st);
				strftime(st_str, sizeof(st_str), "%Y-%m-%dT%TZ", &st);
				zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:startTime>%s</ns0:startTime>",
						st_str);
			}

			zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:maxSample>2</ns0:maxSample>");

			for (j = start_counter; j < entity->counters.values_num && counters_num < counters_max; j++)
			{
				zbx_vmware_perf_counter_t	*counter;

				counter = (zbx_vmware_perf_counter_t *)entity->counters.values[j];

				zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset,
						"<ns0:metricId><ns0:counterId>" ZBX_FS_UI64
						"</ns0:counterId><ns0:instance>%s</ns0:instance></ns0:metricId>",
						counter->counterid, entity->query_instance);

				counter->state |= ZBX_VMWARE_COUNTER_UPDATING;

				counters_num++;
			}

			if (j == entity->counters.values_num)
			{
				start_counter = 0;
				i--;
			}
			else
				start_counter = j;

			if (ZBX_VMWARE_PERF_INTERVAL_NONE != entity->refresh)
			{
				zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:intervalId>%d</ns0:intervalId>",
					entity->refresh);
			}

			zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:querySpec>");
		}

		zbx_vmware_unlock();
		zbx_xml_free_doc(doc);
		doc = NULL;

		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:QueryPerf>");
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_FOOTER);

		zabbix_log(LOG_LEVEL_TRACE, "%s() SOAP request: %s", __func__, tmp);

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, &error))
		{
			for (j = i + 1; j < entities->values_num; j++)
			{
				entity = (zbx_vmware_perf_entity_t *)entities->values[j];
				vmware_perf_data_add_error(perfdata, entity->type, entity->id, error);
			}

			zbx_free(error);
			break;
		}

		/* parse performance data into local memory */
		vmware_service_parse_perf_data(perfdata, doc);

		while (entities->values_num > i + 1)
			zbx_vector_ptr_remove_noorder(entities, entities->values_num - 1);
	}

	zbx_free(tmp);
	zbx_xml_free_doc(doc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware statistics data                                    *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_update_perf(zbx_vmware_service_t *service)
{
#	define INIT_PERF_XML_SIZE	200 * ZBX_KIBIBYTE

	CURL				*easyhandle = NULL;
	CURLoption			opt;
	CURLcode			err;
	struct curl_slist		*headers = NULL;
	int				i, ret = FAIL;
	char				*error = NULL;
	zbx_vector_ptr_t		entities, hist_entities;
	zbx_vmware_perf_entity_t	*entity;
	zbx_hashset_iter_t		iter;
	zbx_vector_ptr_t		perfdata;
	static ZBX_HTTPPAGE		page;	/* 173K */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	zbx_vector_ptr_create(&entities);
	zbx_vector_ptr_create(&hist_entities);
	zbx_vector_ptr_create(&perfdata);
	page.alloc = 0;

	if (NULL == (easyhandle = curl_easy_init()))
	{
		error = zbx_strdup(error, "cannot initialize cURL library");
		goto out;
	}

	page.alloc = INIT_PERF_XML_SIZE;
	page.data = (char *)zbx_malloc(NULL, page.alloc);
	headers = curl_slist_append(headers, ZBX_XML_HEADER1);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);
	headers = curl_slist_append(headers, ZBX_XML_HEADER3);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
	{
		error = zbx_dsprintf(error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != vmware_service_authenticate(service, easyhandle, &page, &error))
		goto clean;

	/* update performance counter refresh rate for entities */

	zbx_vmware_lock();

	zbx_hashset_iter_reset(&service->entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
	{
		/* remove old entities */
		if (0 != entity->last_seen && entity->last_seen < service->lastcheck)
		{
			vmware_shared_perf_entity_clean(entity);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		if (ZBX_VMWARE_PERF_INTERVAL_UNKNOWN != entity->refresh)
			continue;

		/* Entities are removed only during performance counter update and no two */
		/* performance counter updates for one service can happen simultaneously. */
		/* This means for refresh update we can safely use reference to entity    */
		/* outside vmware lock.                                                   */
		zbx_vector_ptr_append(&entities, entity);
	}

	zbx_vmware_unlock();

	/* get refresh rates */
	for (i = 0; i < entities.values_num; i++)
	{
		entity = entities.values[i];

		if (SUCCEED != vmware_service_get_perf_counter_refreshrate(service, easyhandle, entity->type,
				entity->id, &entity->refresh, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get refresh rate for %s \"%s\": %s", entity->type,
					entity->id, error);
			zbx_free(error);
		}
	}

	zbx_vector_ptr_clear(&entities);

	zbx_vmware_lock();

	zbx_hashset_iter_reset(&service->entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_VMWARE_PERF_INTERVAL_UNKNOWN == entity->refresh)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "skipping performance entity with zero refresh rate "
					"type:%s id:%s", entity->type, entity->id);
			continue;
		}

		if (ZBX_VMWARE_PERF_INTERVAL_NONE == entity->refresh)
			zbx_vector_ptr_append(&hist_entities, entity);
		else
			zbx_vector_ptr_append(&entities, entity);
	}

	zbx_vmware_unlock();

	vmware_service_retrieve_perf_counters(service, easyhandle, &entities, ZBX_MAXQUERYMETRICS_UNLIMITED, &perfdata);
	vmware_service_retrieve_perf_counters(service, easyhandle, &hist_entities, service->data->max_query_metrics,
			&perfdata);

	if (SUCCEED != vmware_service_logout(service, easyhandle, &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot close vmware connection: %s.", error);
		zbx_free(error);
	}

	ret = SUCCEED;
clean:
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(page.data);
out:
	zbx_vmware_lock();

	if (FAIL == ret)
	{
		zbx_hashset_iter_reset(&service->entities, &iter);
		while (NULL != (entity = zbx_hashset_iter_next(&iter)))
			entity->error = vmware_shared_strdup(error);

		zbx_free(error);
	}
	else
	{
		/* clean old performance data and copy the new data into shared memory */
		vmware_entities_shared_clean_stats(&service->entities);
		vmware_service_copy_perf_data(service, &perfdata);
	}

	service->state &= ~(ZBX_VMWARE_STATE_UPDATING_PERF);
	service->lastperfcheck = time(NULL);

	zbx_vmware_unlock();

	zbx_vector_ptr_clear_ext(&perfdata, (zbx_mem_free_func_t)vmware_free_perfdata);
	zbx_vector_ptr_destroy(&perfdata);

	zbx_vector_ptr_destroy(&hist_entities);
	zbx_vector_ptr_destroy(&entities);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s \tprocessed " ZBX_FS_SIZE_T " bytes of data", __func__,
			zbx_result_string(ret), (zbx_fs_size_t)page.alloc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes vmware service                                            *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_remove(zbx_vmware_service_t *service)
{
	int	index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	zbx_vmware_lock();

	if (FAIL != (index = zbx_vector_ptr_search(&vmware->services, service, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
	{
		zbx_vector_ptr_remove(&vmware->services, index);
		vmware_service_shared_free(service);
	}

	zbx_vmware_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware service object                                        *
 *                                                                            *
 * Parameters: url      - [IN] the vmware service URL                         *
 *             username - [IN] the vmware service username                    *
 *             password - [IN] the vmware service password                    *
 *                                                                            *
 * Return value: the requested service object or NULL if the object is not    *
 *               yet ready.                                                   *
 *                                                                            *
 * Comments: vmware lock must be locked with zbx_vmware_lock() function       *
 *           before calling this function.                                    *
 *           If the service list does not contain the requested service object*
 *           then a new object is created, marked as new, added to the list   *
 *           and a NULL value is returned.                                    *
 *           If the object is in list, but is not yet updated also a NULL     *
 *           value is returned.                                               *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_service_t	*zbx_vmware_get_service(const char* url, const char* username, const char* password)
{
	int			i, now;
	zbx_vmware_service_t	*service = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, username, url);

	if (NULL == vmware)
		goto out;

	now = time(NULL);

	for (i = 0; i < vmware->services.values_num; i++)
	{
		service = (zbx_vmware_service_t *)vmware->services.values[i];

		if (0 == strcmp(service->url, url) && 0 == strcmp(service->username, username) &&
				0 == strcmp(service->password, password))
		{
			service->lastaccess = now;

			/* return NULL if the service is not ready yet */
			if (0 == (service->state & (ZBX_VMWARE_STATE_READY | ZBX_VMWARE_STATE_FAILED)))
				service = NULL;

			goto out;
		}
	}

	service = (zbx_vmware_service_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_service_t));
	memset(service, 0, sizeof(zbx_vmware_service_t));

	service->url = vmware_shared_strdup(url);
	service->username = vmware_shared_strdup(username);
	service->password = vmware_shared_strdup(password);
	service->type = ZBX_VMWARE_TYPE_UNKNOWN;
	service->state = ZBX_VMWARE_STATE_NEW;
	service->lastaccess = now;
	service->eventlog.last_key = ZBX_VMWARE_EVENT_KEY_UNINITIALIZED;
	service->eventlog.skip_old = 0;
	service->eventlog.req_sz = 0;
	service->eventlog.oom = 0;

	zbx_hashset_create_ext(&service->entities, 100, vmware_perf_entity_hash_func,  vmware_perf_entity_compare_func,
			NULL, __vm_mem_malloc_func, __vm_mem_realloc_func, __vm_mem_free_func);

	zbx_hashset_create_ext(&service->counters, ZBX_VMWARE_COUNTERS_INIT_SIZE, vmware_counter_hash_func,
			vmware_counter_compare_func, NULL, __vm_mem_malloc_func, __vm_mem_realloc_func,
			__vm_mem_free_func);

	zbx_vector_ptr_append(&vmware->services, service);

	/* new service does not have any data - return NULL */
	service = NULL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__,
			zbx_result_string(NULL != service ? SUCCEED : FAIL));

	return service;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware performance counter id and unit info by the path      *
 *                                                                            *
 * Parameters: service   - [IN] the vmware service                            *
 *             path      - [IN] the path of counter to retrieve in format     *
 *                              <group>/<key>[<rollup type>]                  *
 *             counterid - [OUT] the counter id                               *
 *             unit      - [OUT] the counter unit info (kilo, mega, % etc)    *
 *                                                                            *
 * Return value: SUCCEED if the counter was found, FAIL otherwise             *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_get_counterid(zbx_vmware_service_t *service, const char *path,
		zbx_uint64_t *counterid, int *unit)
{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	zbx_vmware_counter_t	*counter;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s", __func__, path);

	if (NULL == (counter = (zbx_vmware_counter_t *)zbx_hashset_search(&service->counters, &path)))
		goto out;

	*counterid = counter->id;

	if (NULL != unit)
		*unit = counter->unit;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() counterid:" ZBX_FS_UI64, __func__, *counterid);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#else
	return FAIL;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: start monitoring performance counter of the specified entity      *
 *                                                                            *
 * Parameters: service   - [IN] the vmware service                            *
 *             type      - [IN] the entity type                               *
 *             id        - [IN] the entity id                                 *
 *             counterid - [IN] the performance counter id                    *
 *             instance  - [IN] the performance counter instance name         *
 *                                                                            *
 * Return value: SUCCEED - the entity counter was added to monitoring list.   *
 *               FAIL    - the performance counter of the specified entity    *
 *                         is already being monitored.                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_add_perf_counter(zbx_vmware_service_t *service, const char *type, const char *id,
		zbx_uint64_t counterid, const char *instance)
{
	zbx_vmware_perf_entity_t	*pentity, entity;
	int				ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s counterid:" ZBX_FS_UI64, __func__, type, id,
			counterid);

	if (NULL == (pentity = zbx_vmware_service_get_perf_entity(service, type, id)))
	{
		entity.refresh = ZBX_VMWARE_PERF_INTERVAL_UNKNOWN;
		entity.last_seen = 0;
		entity.query_instance = vmware_shared_strdup(instance);
		entity.type = vmware_shared_strdup(type);
		entity.id = vmware_shared_strdup(id);
		entity.error = NULL;
		zbx_vector_ptr_create_ext(&entity.counters, __vm_mem_malloc_func, __vm_mem_realloc_func,
				__vm_mem_free_func);

		pentity = (zbx_vmware_perf_entity_t *)zbx_hashset_insert(&service->entities, &entity,
				sizeof(zbx_vmware_perf_entity_t));
	}

	if (FAIL == zbx_vector_ptr_search(&pentity->counters, &counterid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
	{
		vmware_counters_add_new(&pentity->counters, counterid);
		zbx_vector_ptr_sort(&pentity->counters, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets performance entity by type and id                            *
 *                                                                            *
 * Parameters: service - [IN] the vmware service                              *
 *             type    - [IN] the performance entity type                     *
 *             id      - [IN] the performance entity id                       *
 *                                                                            *
 * Return value: the performance entity or NULL if not found                  *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_perf_entity_t	*zbx_vmware_service_get_perf_entity(zbx_vmware_service_t *service, const char *type,
		const char *id)
{
	zbx_vmware_perf_entity_t	*pentity, entity = {.type = (char *)type, .id = (char *)id};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s", __func__, type, id);

	pentity = (zbx_vmware_perf_entity_t *)zbx_hashset_search(&service->entities, &entity);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() entity:%p", __func__, (void *)pentity);

	return pentity;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: initializes vmware collector service                              *
 *                                                                            *
 * Comments: This function must be called before worker threads are forked.   *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_init(char **error)
{
	int		ret = FAIL;
	zbx_uint64_t	size_reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_mutex_create(&vmware_lock, ZBX_MUTEX_VMWARE, error))
		goto out;

	size_reserved = zbx_mem_required_size(1, "vmware cache size", "VMwareCacheSize");

	CONFIG_VMWARE_CACHE_SIZE -= size_reserved;

	if (SUCCEED != zbx_mem_create(&vmware_mem, CONFIG_VMWARE_CACHE_SIZE, "vmware cache size", "VMwareCacheSize", 0,
			error))
	{
		goto out;
	}

	vmware = (zbx_vmware_t *)__vm_mem_malloc_func(NULL, sizeof(zbx_vmware_t));
	memset(vmware, 0, sizeof(zbx_vmware_t));

	VMWARE_VECTOR_CREATE(&vmware->services, ptr);
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	vmware->strpool_sz = 0;
	zbx_hashset_create_ext(&vmware->strpool, 100, vmware_strpool_hash_func, vmware_strpool_compare_func, NULL,
		__vm_mem_malloc_func, __vm_mem_realloc_func, __vm_mem_free_func);
	zbx_hashset_create(&evt_msg_strpool, 100, vmware_strpool_hash_func, vmware_strpool_compare_func);
	evt_req_chunk_size = zbx_mem_required_chunk_size(sizeof(zbx_vmware_event_t));
#endif
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys vmware collector service                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_destroy(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	if (NULL != vmware_mem)
	{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
		zbx_hashset_destroy(&vmware->strpool);
		zbx_hashset_destroy(&evt_msg_strpool);
#endif
		zbx_mem_destroy(vmware_mem);
		vmware_mem = NULL;
		zbx_mutex_destroy(&vmware_lock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

#define	ZBX_VMWARE_TASK_IDLE		1
#define	ZBX_VMWARE_TASK_UPDATE		2
#define	ZBX_VMWARE_TASK_UPDATE_PERF	3
#define	ZBX_VMWARE_TASK_REMOVE		4

/******************************************************************************
 *                                                                            *
 * Purpose: the vmware collector main loop                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(vmware_thread, args)
{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	int			i, now, task, next_update, updated_services = 0, removed_services = 0,
				old_updated_services = 0, old_removed_services = 0, sleeptime = -1;
	zbx_vmware_service_t	*service = NULL;
	double			sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t			last_stat_time;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	last_stat_time = time(NULL);

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [updated %d, removed %d VMware services in " ZBX_FS_DBL " sec, "
					"querying VMware services]", get_process_type_string(process_type), process_num,
					old_updated_services, old_removed_services, old_total_sec);
		}

		do
		{
			task = ZBX_VMWARE_TASK_IDLE;

			now = time(NULL);
			next_update = now + POLLER_DELAY;

			zbx_vmware_lock();

			/* find a task to be performed on a vmware service */
			for (i = 0; i < vmware->services.values_num; i++)
			{
				service = (zbx_vmware_service_t *)vmware->services.values[i];

				/* check if the service isn't used and should be removed */
				if (0 == (service->state & ZBX_VMWARE_STATE_BUSY) &&
						now - service->lastaccess > ZBX_VMWARE_SERVICE_TTL)
				{
					service->state |= ZBX_VMWARE_STATE_REMOVING;
					task = ZBX_VMWARE_TASK_REMOVE;
					break;
				}

				/* check if the performance statistics should be updated */
				if (0 != (service->state & ZBX_VMWARE_STATE_READY) &&
						0 == (service->state & ZBX_VMWARE_STATE_UPDATING_PERF) &&
						now - service->lastperfcheck >= ZBX_VMWARE_PERF_UPDATE_PERIOD)
				{
					service->state |= ZBX_VMWARE_STATE_UPDATING_PERF;
					task = ZBX_VMWARE_TASK_UPDATE_PERF;
					break;
				}

				/* check if the service data should be updated */
				if (0 == (service->state & ZBX_VMWARE_STATE_UPDATING) &&
						now - service->lastcheck >= ZBX_VMWARE_CACHE_UPDATE_PERIOD)
				{
					service->state |= ZBX_VMWARE_STATE_UPDATING;
					task = ZBX_VMWARE_TASK_UPDATE;
					break;
				}

				/* don't calculate nextcheck for services that are already updating something */
				if (0 != (service->state & ZBX_VMWARE_STATE_BUSY))
						continue;

				/* calculate next service update time */

				if (service->lastcheck + ZBX_VMWARE_CACHE_UPDATE_PERIOD < next_update)
					next_update = service->lastcheck + ZBX_VMWARE_CACHE_UPDATE_PERIOD;

				if (0 != (service->state & ZBX_VMWARE_STATE_READY))
				{
					if (service->lastperfcheck + ZBX_VMWARE_PERF_UPDATE_PERIOD < next_update)
						next_update = service->lastperfcheck + ZBX_VMWARE_PERF_UPDATE_PERIOD;
				}
			}

			zbx_vmware_unlock();

			switch (task)
			{
				case ZBX_VMWARE_TASK_UPDATE:
					vmware_service_update(service);
					updated_services++;
					break;
				case ZBX_VMWARE_TASK_UPDATE_PERF:
					vmware_service_update_perf(service);
					updated_services++;
					break;
				case ZBX_VMWARE_TASK_REMOVE:
					vmware_service_remove(service);
					removed_services++;
					break;
			}
		}
		while (ZBX_VMWARE_TASK_IDLE != task && ZBX_IS_RUNNING());

		total_sec += zbx_time() - sec;
		now = time(NULL);

		sleeptime = 0 < next_update - now ? next_update - now : 0;

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [updated %d, removed %d VMware services in " ZBX_FS_DBL " sec,"
						" querying VMware services]", get_process_type_string(process_type),
						process_num, updated_services, removed_services, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [updated %d, removed %d VMware services in " ZBX_FS_DBL " sec,"
						" idle %d sec]", get_process_type_string(process_type), process_num,
						updated_services, removed_services, total_sec, sleeptime);
				old_updated_services = updated_services;
				old_removed_services = removed_services;
				old_total_sec = total_sec;
			}
			updated_services = 0;
			removed_services = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
#else
	ZBX_UNUSED(args);
	THIS_SHOULD_NEVER_HAPPEN;
	zbx_thread_exit(EXIT_SUCCESS);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks vmware collector                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_lock(void)
{
	zbx_mutex_lock(vmware_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlocks vmware collector                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_unlock(void)
{
	zbx_mutex_unlock(vmware_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware collector statistics                                  *
 *                                                                            *
 * Parameters: stats   - [OUT] the vmware collector statistics                *
 *                                                                            *
 * Return value: SUCCEED - the statistics were retrieved successfully         *
 *               FAIL     - no vmware collectors are running                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_get_statistics(zbx_vmware_stats_t *stats)
{
	if (NULL == vmware_mem)
		return FAIL;

	zbx_vmware_lock();

	stats->memory_total = vmware_mem->total_size;
	stats->memory_used = vmware_mem->total_size - vmware_mem->free_size;

	zbx_vmware_unlock();

	return SUCCEED;
}

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

/*
 * XML support
 */
/******************************************************************************
 *                                                                            *
 * Purpose: libxml2 callback function for error handle                        *
 *                                                                            *
 * Parameters: user_data - [IN/OUT] the user context                          *
 *             err       - [IN] the libxml2 error message                     *
 *                                                                            *
 ******************************************************************************/
static void	libxml_handle_error(void *user_data, xmlErrorPtr err)
{
	ZBX_UNUSED(user_data);
	ZBX_UNUSED(err);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a value from xml data and return status of operation     *
 *                                                                            *
 * Parameters: data   - [IN] XML data                                         *
 *             len    - [IN] XML data length (optional)                       *
 *             xpath  - [IN] XML XPath                                        *
 *             xdoc   - [OUT] parsed xml document                             *
 *             value  - [OUT] selected xml node value                         *
 *             error  - [OUT] error of xml or xpath formats                   *
 *                                                                            *
 * Return: SUCCEED - select xpath successfully, result stored in 'value'      *
 *         FAIL - failed select xpath expression                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_xml_try_read_value(const char *data, size_t len, const char *xpath, xmlDoc **xdoc, char **value,
		char **error)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	int		ret = FAIL;

	if (NULL == data)
		goto out;

	xmlSetStructuredErrorFunc(NULL, &libxml_handle_error);

	if (NULL == (*xdoc = xmlReadMemory(data, (0 == len ? strlen(data) : len), ZBX_VM_NONAME_XML, NULL,
			ZBX_XML_PARSE_OPTS)))
	{
		if (NULL != error)
			*error = zbx_dsprintf(*error, "Received response has no valid XML data.");

		xmlSetStructuredErrorFunc(NULL, NULL);
		goto out;
	}

	xpathCtx = xmlXPathNewContext(*xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)xpath, xpathCtx)))
	{
		if (NULL != error)
			*error = zbx_dsprintf(*error, "Invalid xpath expression: \"%s\".", xpath);

		goto clean;
	}

	ret = SUCCEED;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	if (NULL != (val = xmlNodeListGetString(*xdoc, nodeset->nodeTab[0]->xmlChildrenNode, 1)))
	{
		*value = zbx_strdup(*value, (const char *)val);
		xmlFree(val);
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlSetStructuredErrorFunc(NULL, NULL);
	xmlXPathFreeContext(xpathCtx);
	xmlResetLastError();
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a value from xml data relative to the specified node     *
 *                                                                            *
 * Parameters: doc    - [IN] the XML document                                 *
 *             node   - [IN] the XML node                                     *
 *             xpath  - [IN] the XML XPath                                    *
 *                                                                            *
 * Return: The allocated value string or NULL if the xml data does not        *
 *         contain the value specified by xpath.                              *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_xml_node_read_value(xmlDoc *doc, xmlNode *node, const char *xpath)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	char		*value = NULL;

	xpathCtx = xmlXPathNewContext(doc);

	xpathCtx->node = node;

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)xpath, xpathCtx)))
		goto clean;

	if (XPATH_STRING == xpathObj->type)
	{
		value = zbx_strdup(NULL, (const char *)xpathObj->stringval);
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	if (NULL != (val = xmlNodeListGetString(doc, nodeset->nodeTab[0]->xmlChildrenNode, 1)))
	{
		value = zbx_strdup(NULL, (const char *)val);
		xmlFree(val);
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	return value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a value from xml document relative to the root node      *
 *                                                                            *
 * Parameters: xdoc   - [IN] the XML document                                 *
 *             xpath  - [IN] the XML XPath                                    *
 *                                                                            *
 * Return: The allocated value string or NULL if the xml data does not        *
 *         contain the value specified by xpath.                              *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_xml_doc_read_value(xmlDoc *xdoc, const char *xpath)
{
	xmlNode	*root_element;

	root_element = xmlDocGetRootElement(xdoc);
	return zbx_xml_node_read_value(xdoc, root_element, xpath);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves numeric xpath value                                     *
 *                                                                            *
 * Parameters: xdoc  - [IN] xml document                                      *
 *             xpath - [IN] xpath                                             *
 *             num   - [OUT] numeric value                                    *
 *                                                                            *
 * Return value: SUCCEED - the count was retrieved successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_xml_doc_read_num(xmlDoc *xdoc, const char *xpath, int *num)
{
	int		ret = FAIL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)xpath, xpathCtx)))
		goto out;

	if (XPATH_NUMBER == xpathObj->type)
	{
		*num = (int)xpathObj->floatval;
		ret = SUCCEED;
	}

	xmlXPathFreeObject(xpathObj);
out:
	xmlXPathFreeContext(xpathCtx);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: populate array of values from an xml data                         *
 *                                                                            *
 * Parameters: xdoc   - [IN] XML document                                     *
 *             xpath  - [IN] XML XPath                                        *
 *             values - [OUT] list of requested values                        *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	zbx_xml_read_values(xmlDoc *xdoc, const char *xpath, zbx_vector_str_t *values)
{
	return zbx_xml_node_read_values(xdoc, NULL, xpath, values);
}

/******************************************************************************
 *                                                                            *
 * Purpose: populate array of values from an xml data                         *
 *                                                                            *
 * Parameters: xdoc   - [IN] XML document                                     *
 *             node   - [IN] the XML node                                     *
 *             xpath  - [IN] XML XPath                                        *
 *             values - [OUT] list of requested values                        *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	zbx_xml_node_read_values(xmlDoc *xdoc, xmlNode *node, const char *xpath, zbx_vector_str_t *values)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	int		i, ret = FAIL;

	if (NULL == xdoc)
		goto out;

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL != node)
		xpathCtx->node = node;

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)xpath, xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		if (NULL != (val = xmlNodeListGetString(xdoc, nodeset->nodeTab[i]->xmlChildrenNode, 1)))
		{
			zbx_vector_str_append(values, zbx_strdup(NULL, (const char *)val));
			xmlFree(val);
		}
	}

	ret = SUCCEED;
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
out:
	return ret;
}

#endif
