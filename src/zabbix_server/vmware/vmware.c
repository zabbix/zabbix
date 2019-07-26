/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

#include "common.h"

/* LIBXML2 is used */
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/tree.h>
#	include <libxml/xpath.h>
#endif

#include "ipc.h"
#include "memalloc.h"
#include "log.h"
#include "zbxalgo.h"
#include "daemon.h"
#include "zbxself.h"

#include "vmware.h"
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

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;
extern char		*CONFIG_SOURCE_IP;

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

#define ZBX_VPXD_STATS_MAXQUERYMETRICS	64
#define ZBX_MAXQUERYMETRICS_UNLIMITED	1000

ZBX_VECTOR_IMPL(str_uint64_pair, zbx_str_uint64_pair_t)
ZBX_PTR_VECTOR_IMPL(vmware_datastore, zbx_vmware_datastore_t *)

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

ZBX_VECTOR_DECL(id_xmlnode, zbx_id_xmlnode_t)
ZBX_VECTOR_IMPL(id_xmlnode, zbx_id_xmlnode_t)

/*
 * SOAP support
 */
#define	ZBX_XML_HEADER1		"Soapaction:urn:vim25/4.1"
#define ZBX_XML_HEADER2		"Content-Type:text/xml; charset=utf-8"
/* cURL specific atribute to prevent the use of "Expect" derective */
/* acording RFC 7231/5.1.1 if xml request with a size is large than 1k */
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

#define ZBX_XPATH_DATASTORE_MOUNT()									\
	"/*/*/*/*/*/*[local-name()='propSet']/*/*[local-name()='DatastoreHostMount']"			\
	"/*[local-name()='mountInfo']/*[local-name()='path']"

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

#define ZBX_XPATH_HV_SENSOR_STATUS(sensor)								\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name']"					\
		"[text()='runtime.healthSystemRuntime.systemHealthInfo']]"				\
		"/*[local-name()='val']/*[local-name()='numericSensorInfo']"				\
		"[*[local-name()='name'][text()='" sensor "']]"						\
		"/*[local-name()='healthState']/*[local-name()='key']"

#define ZBX_XPATH_VMWARE_ABOUT(property)								\
	"/*/*/*/*/*[local-name()='about']/*[local-name()='" property "']"

#	define ZBX_XPATH_NN(NN)			"*[local-name()='" NN "']"
#	define ZBX_XPATH_LN(LN)			"/" ZBX_XPATH_NN(LN)
#	define ZBX_XPATH_LN1(LN1)		"/" ZBX_XPATH_LN(LN1)
#	define ZBX_XPATH_LN2(LN1, LN2)		"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2)
#	define ZBX_XPATH_LN3(LN1, LN2, LN3)	"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2) ZBX_XPATH_LN(LN3)

#define ZBX_XPATH_PROP_NAME(property)									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='" property "']]"		\
		"/*[local-name()='val']"

#define ZBX_VM_NONAME_XML	"noname.xml"

#define ZBX_PROPMAP(property)		{property, ZBX_XPATH_PROP_NAME(property)}

typedef struct
{
	const char	*name;
	const char	*xpath;
}
zbx_vmware_propmap_t;

static zbx_vmware_propmap_t	hv_propmap[] = {
	ZBX_PROPMAP("summary.quickStats.overallCpuUsage"),	/* ZBX_VMWARE_HVPROP_OVERALL_CPU_USAGE */
	ZBX_PROPMAP("summary.config.product.fullName"),		/* ZBX_VMWARE_HVPROP_FULL_NAME */
	ZBX_PROPMAP("summary.hardware.numCpuCores"),		/* ZBX_VMWARE_HVPROP_HW_NUM_CPU_CORES */
	ZBX_PROPMAP("summary.hardware.cpuMhz"),			/* ZBX_VMWARE_HVPROP_HW_CPU_MHZ */
	ZBX_PROPMAP("summary.hardware.cpuModel"),		/* ZBX_VMWARE_HVPROP_HW_CPU_MODEL */
	ZBX_PROPMAP("summary.hardware.numCpuThreads"), 		/* ZBX_VMWARE_HVPROP_HW_NUM_CPU_THREADS */
	ZBX_PROPMAP("summary.hardware.memorySize"), 		/* ZBX_VMWARE_HVPROP_HW_MEMORY_SIZE */
	ZBX_PROPMAP("summary.hardware.model"), 			/* ZBX_VMWARE_HVPROP_HW_MODEL */
	ZBX_PROPMAP("summary.hardware.uuid"), 			/* ZBX_VMWARE_HVPROP_HW_UUID */
	ZBX_PROPMAP("summary.hardware.vendor"), 		/* ZBX_VMWARE_HVPROP_HW_VENDOR */
	ZBX_PROPMAP("summary.quickStats.overallMemoryUsage"),	/* ZBX_VMWARE_HVPROP_MEMORY_USED */
	{"runtime.healthSystemRuntime.systemHealthInfo", 	/* ZBX_VMWARE_HVPROP_HEALTH_STATE */
			ZBX_XPATH_HV_SENSOR_STATUS("VMware Rollup Health State")},
	ZBX_PROPMAP("summary.quickStats.uptime"),		/* ZBX_VMWARE_HVPROP_UPTIME */
	ZBX_PROPMAP("summary.config.product.version"),		/* ZBX_VMWARE_HVPROP_VERSION */
	ZBX_PROPMAP("summary.config.name"),			/* ZBX_VMWARE_HVPROP_NAME */
	ZBX_PROPMAP("overallStatus")				/* ZBX_VMWARE_HVPROP_STATUS */
};

static zbx_vmware_propmap_t	vm_propmap[] = {
	ZBX_PROPMAP("summary.config.numCpu"),			/* ZBX_VMWARE_VMPROP_CPU_NUM */
	ZBX_PROPMAP("summary.quickStats.overallCpuUsage"),	/* ZBX_VMWARE_VMPROP_CPU_USAGE */
	ZBX_PROPMAP("summary.config.name"),			/* ZBX_VMWARE_VMPROP_NAME */
	ZBX_PROPMAP("summary.config.memorySizeMB"),		/* ZBX_VMWARE_VMPROP_MEMORY_SIZE */
	ZBX_PROPMAP("summary.quickStats.balloonedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED */
	ZBX_PROPMAP("summary.quickStats.compressedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_COMPRESSED */
	ZBX_PROPMAP("summary.quickStats.swappedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_SWAPPED */
	ZBX_PROPMAP("summary.quickStats.guestMemoryUsage"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_GUEST */
	ZBX_PROPMAP("summary.quickStats.hostMemoryUsage"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_HOST */
	ZBX_PROPMAP("summary.quickStats.privateMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_PRIVATE */
	ZBX_PROPMAP("summary.quickStats.sharedMemory"),		/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_SHARED */
	ZBX_PROPMAP("summary.runtime.powerState"),		/* ZBX_VMWARE_VMPROP_POWER_STATE */
	ZBX_PROPMAP("summary.storage.committed"),		/* ZBX_VMWARE_VMPROP_STORAGE_COMMITED */
	ZBX_PROPMAP("summary.storage.unshared"),		/* ZBX_VMWARE_VMPROP_STORAGE_UNSHARED */
	ZBX_PROPMAP("summary.storage.uncommitted"),		/* ZBX_VMWARE_VMPROP_STORAGE_UNCOMMITTED */
	ZBX_PROPMAP("summary.quickStats.uptimeSeconds")		/* ZBX_VMWARE_VMPROP_UPTIME */
};

/* hypervisor hashset support */
static zbx_hash_t	vmware_hv_hash(const void *data)
{
	zbx_vmware_hv_t	*hv = (zbx_vmware_hv_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(hv->uuid, strlen(hv->uuid), ZBX_DEFAULT_HASH_SEED);
}

static int	vmware_hv_compare(const void *d1, const void *d2)
{
	zbx_vmware_hv_t	*hv1 = (zbx_vmware_hv_t *)d1;
	zbx_vmware_hv_t	*hv2 = (zbx_vmware_hv_t *)d2;

	return strcmp(hv1->uuid, hv2->uuid);
}

/* virtual machine index support */
static zbx_hash_t	vmware_vm_hash(const void *data)
{
	zbx_vmware_vm_index_t	*vmi = (zbx_vmware_vm_index_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(vmi->vm->uuid, strlen(vmi->vm->uuid), ZBX_DEFAULT_HASH_SEED);
}

static int	vmware_vm_compare(const void *d1, const void *d2)
{
	zbx_vmware_vm_index_t	*vmi1 = (zbx_vmware_vm_index_t *)d1;
	zbx_vmware_vm_index_t	*vmi2 = (zbx_vmware_vm_index_t *)d2;

	return strcmp(vmi1->vm->uuid, vmi2->vm->uuid);
}

/* string pool support */

#define REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

static zbx_hash_t	vmware_strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((char *)data + REFCOUNT_FIELD_SIZE);
}

static int	vmware_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp((char *)d1 + REFCOUNT_FIELD_SIZE, (char *)d2 + REFCOUNT_FIELD_SIZE);
}

static char	*vmware_shared_strdup(const char *str)
{
	void	*ptr;

	if (NULL == str)
		return NULL;

	ptr = zbx_hashset_search(&vmware->strpool, str - REFCOUNT_FIELD_SIZE);

	if (NULL == ptr)
	{
		ptr = zbx_hashset_insert_ext(&vmware->strpool, str - REFCOUNT_FIELD_SIZE,
				REFCOUNT_FIELD_SIZE + strlen(str) + 1, REFCOUNT_FIELD_SIZE);

		*(zbx_uint32_t *)ptr = 0;
	}

	(*(zbx_uint32_t *)ptr)++;

	return (char *)ptr + REFCOUNT_FIELD_SIZE;
}

static void	vmware_shared_strfree(char *str)
{
	if (NULL != str)
	{
		void	*ptr = str - REFCOUNT_FIELD_SIZE;

		if (0 == --(*(zbx_uint32_t *)ptr))
			zbx_hashset_remove_direct(&vmware->strpool, ptr);
	}
}

#define ZBX_XPATH_NAME_BY_TYPE(type)									\
	"/*/*/*/*/*[local-name()='objects'][*[local-name()='obj'][@type='" type "']]"			\
	"/*[local-name()='propSet'][*[local-name()='name']]/*[local-name()='val']"

#define ZBX_XPATH_HV_PARENTFOLDERNAME(parent_id)							\
	"/*/*/*/*/*[local-name()='objects']["								\
		"*[local-name()='obj'][@type='Folder'] and "						\
		"*[local-name()='propSet'][*[local-name()='name'][text()='childEntity']]"		\
		"/*[local-name()='val']/*[local-name()='ManagedObjectReference']=" parent_id " and "	\
		"*[local-name()='propSet'][*[local-name()='name'][text()='parent']]"			\
		"/*[local-name()='val'][@type!='Datacenter']"						\
	"]/*[local-name()='propSet'][*[local-name()='name'][text()='name']]/*[local-name()='val']"

#define ZBX_XPATH_HV_PARENTID										\
	"/*/*/*/*/*[local-name()='objects'][*[local-name()='obj'][@type='HostSystem']]"			\
	"/*[local-name()='propSet'][*[local-name()='name'][text()='parent']]/*[local-name()='val']"

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
ZBX_HTTPPAGE;

static int	zbx_xml_read_values(xmlDoc *xdoc, const char *xpath, zbx_vector_str_t *values);
static int	zbx_xml_try_read_value(const char *data, size_t len, const char *xpath, xmlDoc **xdoc, char **value,
		char **error);
static char	*zbx_xml_read_node_value(xmlDoc *doc, xmlNode *node, const char *xpath);
static char	*zbx_xml_read_doc_value(xmlDoc *xdoc, const char *xpath);

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
 * Function: zbx_http_post                                                    *
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
 * Function: zbx_soap_post                                                    *
 *                                                                            *
 * Purpose: unification of vmware web service call with SOAP error validation *
 *                                                                            *
 * Parameters: fn_parent  - [IN] the parent function name for Log records     *
 *             easyhandle - [IN] the CURL handle                              *
 *             request    - [IN] the http request                             *
 *             xdoc       - [OUT] the xml document response (optional)        *
 *             error      - [OUT] the error message in the case of failure    *
 *                                (optional)                                  *
 *                                                                            *
 * Return value: SUCCEED - the SOAP request was completed successfully        *
 *               FAIL    - the SOAP request has failed                        *
 ******************************************************************************/
static int	zbx_soap_post(const char *fn_parent, CURL *easyhandle, const char *request, xmlDoc **xdoc, char **error)
{
	xmlDoc		*doc;
	ZBX_HTTPPAGE	*resp;
	int		ret = SUCCEED;

	if (SUCCEED != zbx_http_post(easyhandle, request, &resp, error))
		return FAIL;

	if (NULL != fn_parent)
		zabbix_log(LOG_LEVEL_TRACE, "%s() SOAP response: %s", fn_parent, resp->data);

	if (SUCCEED != zbx_xml_try_read_value(resp->data, resp->offset, ZBX_XPATH_FAULTSTRING(), &doc, error, error)
			|| NULL != *error)
	{
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

	return ret;
}

/******************************************************************************
 *                                                                            *
 * performance counter hashset support functions                              *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	vmware_counter_hash_func(const void *data)
{
	zbx_vmware_counter_t	*counter = (zbx_vmware_counter_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(counter->path, strlen(counter->path), ZBX_DEFAULT_HASH_SEED);
}

static int	vmware_counter_compare_func(const void *d1, const void *d2)
{
	zbx_vmware_counter_t	*c1 = (zbx_vmware_counter_t *)d1;
	zbx_vmware_counter_t	*c2 = (zbx_vmware_counter_t *)d2;

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

	zbx_vmware_perf_entity_t	*entity = (zbx_vmware_perf_entity_t *)data;

	seed = ZBX_DEFAULT_STRING_HASH_ALGO(entity->type, strlen(entity->type), ZBX_DEFAULT_HASH_SEED);

	return ZBX_DEFAULT_STRING_HASH_ALGO(entity->id, strlen(entity->id), seed);
}

static int	vmware_perf_entity_compare_func(const void *d1, const void *d2)
{
	int	ret;

	zbx_vmware_perf_entity_t	*e1 = (zbx_vmware_perf_entity_t *)d1;
	zbx_vmware_perf_entity_t	*e2 = (zbx_vmware_perf_entity_t *)d2;

	if (0 == (ret = strcmp(e1->type, e2->type)))
		ret = strcmp(e1->id, e2->id);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_free_perfvalue                                            *
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
 * Function: vmware_free_perfdata                                             *
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
 * Function: xml_read_props                                                   *
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
			if (0 == xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
			{
				nodeset = xpathObj->nodesetval;

				if (NULL != (val = xmlNodeListGetString(xdoc, nodeset->nodeTab[0]->xmlChildrenNode, 1)))
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
 * Function: vmware_counters_shared_copy                                      *
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
 * Function: vmware_vector_str_uint64_pair_shared_clean                       *
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
 * Function: vmware_perf_counter_shared_free                                  *
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
 * Function: vmware_entities_shared_clean_stats                               *
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
 * Function: vmware_datastore_shared_free                                     *
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

	if (NULL != datastore->uuid)
		vmware_shared_strfree(datastore->uuid);

	zbx_vector_str_clear_ext(&datastore->hv_uuids, vmware_shared_strfree);
	zbx_vector_str_destroy(&datastore->hv_uuids);

	__vm_mem_free_func(datastore);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_props_shared_free                                         *
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
 * Function: vmware_dev_shared_free                                           *
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
 * Function: vmware_fs_shared_free                                            *
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
 * Function: vmware_vm_shared_free                                            *
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
 * Function: vmware_hv_shared_clean                                           *
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware hypervisor       *
 *                                                                            *
 * Parameters: hv   - [IN] the vmware hypervisor                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_hv_shared_clean(zbx_vmware_hv_t *hv)
{
	zbx_vector_str_clear_ext(&hv->ds_names, vmware_shared_strfree);
	zbx_vector_str_destroy(&hv->ds_names);

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

	vmware_props_shared_free(hv->props, ZBX_VMWARE_HVPROPS_NUM);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_cluster_shared_free                                       *
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
 * Function: vmware_event_shared_free                                         *
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
 * Function: vmware_data_shared_free                                          *
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

		if (NULL != data->error)
			vmware_shared_strfree(data->error);

		__vm_mem_free_func(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_shared_perf_entity_clean                                  *
 *                                                                            *
 * Purpose: cleans resources allocated by vmware peformance entity in vmware  *
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
 * Function: vmware_counter_shared_clean                                      *
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
 * Function: vmware_service_shared_free                                       *
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
 * Function: vmware_cluster_shared_dup                                        *
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
 * Function: vmware_event_shared_dup                                          *
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
 * Function: vmware_datastore_shared_dup                                      *
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
	VMWARE_VECTOR_CREATE(&datastore->hv_uuids, str);
	zbx_vector_str_reserve(&datastore->hv_uuids, src->hv_uuids.values_num);

	datastore->capacity = src->capacity;
	datastore->free_space = src->free_space;
	datastore->uncommitted = src->uncommitted;

	for (i = 0; i < src->hv_uuids.values_num; i++)
		zbx_vector_str_append(&datastore->hv_uuids, vmware_shared_strdup(src->hv_uuids.values[i]));

	return datastore;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_dev_shared_dup                                            *
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
 * Function: vmware_fs_shared_dup                                             *
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
 * Function: vmware_props_shared_dup                                          *
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
 * Function: vmware_vm_shared_dup                                             *
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
 * Function: vmware_hv_shared_copy                                            *
 *                                                                            *
 * Purpose: copies vmware hypervisor object into shared memory                *
 *                                                                            *
 * Parameters: src   - [IN] the vmware hypervisor object                      *
 *                                                                            *
 * Return value: a duplicated vmware hypervisor object                        *
 *                                                                            *
 ******************************************************************************/
static	void	vmware_hv_shared_copy(zbx_vmware_hv_t *dst, const zbx_vmware_hv_t *src)
{
	int	i;

	VMWARE_VECTOR_CREATE(&dst->ds_names, str);
	VMWARE_VECTOR_CREATE(&dst->vms, ptr);
	zbx_vector_str_reserve(&dst->ds_names, src->ds_names.values_num);
	zbx_vector_ptr_reserve(&dst->vms, src->vms.values_num);

	dst->uuid = vmware_shared_strdup(src->uuid);
	dst->id = vmware_shared_strdup(src->id);
	dst->clusterid = vmware_shared_strdup(src->clusterid);

	dst->props = vmware_props_shared_dup(src->props, ZBX_VMWARE_HVPROPS_NUM);
	dst->datacenter_name = vmware_shared_strdup(src->datacenter_name);
	dst->parent_name = vmware_shared_strdup(src->parent_name);
	dst->parent_type= vmware_shared_strdup(src->parent_type);

	for (i = 0; i < src->ds_names.values_num; i++)
		zbx_vector_str_append(&dst->ds_names, vmware_shared_strdup(src->ds_names.values[i]));

	for (i = 0; i < src->vms.values_num; i++)
		zbx_vector_ptr_append(&dst->vms, vmware_vm_shared_dup((zbx_vmware_vm_t *)src->vms.values[i]));
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_data_shared_dup                                           *
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
	zbx_vector_ptr_reserve(&data->clusters, src->clusters.values_num);
	zbx_vector_ptr_reserve(&data->events, src->events.values_num);
	zbx_vector_vmware_datastore_reserve(&data->datastores, src->datastores.values_num);

	zbx_hashset_create_ext(&data->vms_index, 100, vmware_vm_hash, vmware_vm_compare, NULL, __vm_mem_malloc_func,
			__vm_mem_realloc_func, __vm_mem_free_func);

	data->error = vmware_shared_strdup(src->error);

	for (i = 0; i < src->clusters.values_num; i++)
		zbx_vector_ptr_append(&data->clusters, vmware_cluster_shared_dup((zbx_vmware_cluster_t *)src->clusters.values[i]));

	for (i = 0; i < src->events.values_num; i++)
		zbx_vector_ptr_append(&data->events, vmware_event_shared_dup((zbx_vmware_event_t *)src->events.values[i]));

	for (i = 0; i < src->datastores.values_num; i++)
		zbx_vector_vmware_datastore_append(&data->datastores, vmware_datastore_shared_dup(src->datastores.values[i]));

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
 * Function: vmware_datastore_free                                            *
 *                                                                            *
 * Purpose: frees resources allocated to store datastore data                 *
 *                                                                            *
 * Parameters: datastore   - [IN] the datastore                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datastore_free(zbx_vmware_datastore_t *datastore)
{
	zbx_vector_str_clear_ext(&datastore->hv_uuids, zbx_str_free);
	zbx_vector_str_destroy(&datastore->hv_uuids);

	zbx_free(datastore->name);
	zbx_free(datastore->uuid);
	zbx_free(datastore->id);
	zbx_free(datastore);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_props_free                                                *
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
 * Function: vmware_dev_free                                                  *
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
 * Function: vmware_fs_free                                                   *
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
 * Function: vmware_vm_free                                                   *
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
 * Function: vmware_hv_clean                                                  *
 *                                                                            *
 * Purpose: frees resources allocated to store vmware hypervisor              *
 *                                                                            *
 * Parameters: hv   - [IN] the vmware hypervisor                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_hv_clean(zbx_vmware_hv_t *hv)
{
	zbx_vector_str_clear_ext(&hv->ds_names, zbx_str_free);
	zbx_vector_str_destroy(&hv->ds_names);

	zbx_vector_ptr_clear_ext(&hv->vms, (zbx_clean_func_t)vmware_vm_free);
	zbx_vector_ptr_destroy(&hv->vms);

	zbx_free(hv->uuid);
	zbx_free(hv->id);
	zbx_free(hv->clusterid);
	zbx_free(hv->datacenter_name);
	zbx_free(hv->parent_name);
	zbx_free(hv->parent_type);
	vmware_props_free(hv->props, ZBX_VMWARE_HVPROPS_NUM);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_cluster_free                                              *
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
 * Function: vmware_event_free                                                *
 *                                                                            *
 * Purpose: frees resources allocated to store vmware event                   *
 *                                                                            *
 * Parameters: event - [IN] the vmware event                                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_event_free(zbx_vmware_event_t *event)
{
	zbx_free(event->message);
	zbx_free(event);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_data_free                                                 *
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

	zbx_free(data->error);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_counter_free                                              *
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
 * Function: vmware_service_authenticate                                      *
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
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)))
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

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, xml, &doc, error) && NULL == doc)
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
		if (NULL == (error_object = zbx_xml_read_doc_value(doc,
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, xml, NULL, error))
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
 * Function: vmware_service_logout                                            *
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
	return zbx_soap_post(__func__, easyhandle, tmp, NULL, error);
}

typedef struct
{
	const char	*property_collector;
	CURL		*easyhandle;
	char		*token;
}
zbx_property_collection_iter;

static int	zbx_property_collection_init(CURL *easyhandle, const char *property_collection_query,
		const char *property_collector, zbx_property_collection_iter **iter, xmlDoc **xdoc, char **error)
{
#	define ZBX_XPATH_RETRIEVE_PROPERTIES_TOKEN			\
		"/*[local-name()='Envelope']/*[local-name()='Body']"	\
		"/*[local-name()='RetrievePropertiesExResponse']"	\
		"/*[local-name()='returnval']/*[local-name()='token']"

	*iter = (zbx_property_collection_iter *)zbx_malloc(*iter, sizeof(zbx_property_collection_iter));
	(*iter)->property_collector = property_collector;
	(*iter)->easyhandle = easyhandle;
	(*iter)->token = NULL;

	if (SUCCEED != zbx_soap_post("zbx_property_collection_init", (*iter)->easyhandle, property_collection_query, xdoc, error))
		return FAIL;

	(*iter)->token = zbx_xml_read_doc_value(*xdoc, ZBX_XPATH_RETRIEVE_PROPERTIES_TOKEN);

	return SUCCEED;
}

static int	zbx_property_collection_next(zbx_property_collection_iter *iter, xmlDoc **xdoc, char **error)
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
		"/*[local-name()='returnval']/*[local-name()='token']"

	char	*token_esc, post[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "%s() continue retrieving properties with token: '%s'", __func__,
			iter->token);

	token_esc = xml_escape_dyn(iter->token);
	zbx_snprintf(post, sizeof(post), ZBX_POST_CONTINUE_RETRIEVE_PROPERTIES, iter->property_collector, token_esc);
	zbx_free(token_esc);

	if (SUCCEED != zbx_soap_post(__func__, iter->easyhandle, post, xdoc, error))
		return FAIL;

	zbx_free(iter->token);
	iter->token = zbx_xml_read_doc_value(*xdoc, ZBX_XPATH_CONTINUE_RETRIEVE_PROPERTIES_TOKEN);

	return SUCCEED;
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
 * Function: vmware_service_get_contents                                      *
 *                                                                            *
 * Purpose: retrieves vmware service instance contents                        *
 *                                                                            *
 * Parameters: easyhandle - [IN] the CURL handle                              *
 *             version    - [OUT] the version of the instance                 *
 *             fullname   - [OUT] the fullname of the instance                *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the contents were retrieved successfully           *
 *               FAIL    - the content retrieval faield                       *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_VMWARE_CONTENTS, &doc, error))
	{
		zbx_xml_free_doc(doc);
		return FAIL;
	}

	*version = zbx_xml_read_doc_value(doc, ZBX_XPATH_VMWARE_ABOUT("version"));
	*fullname = zbx_xml_read_doc_value(doc, ZBX_XPATH_VMWARE_ABOUT("fullName"));
	zbx_xml_free_doc(doc);

	return SUCCEED;

#	undef ZBX_POST_VMWARE_CONTENTS
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_perf_counter_refreshrate                      *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, error))
		goto out;

	if (NULL != (value = zbx_xml_read_doc_value(doc, ZBX_XPATH_ISAGGREGATE())))
	{
		zbx_free(value);
		*refresh_rate = ZBX_VMWARE_PERF_INTERVAL_NONE;
		ret = SUCCEED;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() refresh_rate: unused", __func__);
		goto out;
	}
	else if (NULL == (value = zbx_xml_read_doc_value(doc, ZBX_XPATH_REFRESHRATE())))
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
 * Function: vmware_service_get_perf_counters                                 *
 *                                                                            *
 * Purpose: get the performance counter ids                                   *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
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

	char		tmp[MAX_STRING_LEN], *group = NULL, *key = NULL, *rollup = NULL, *stats = NULL,
			*counterid = NULL;
	xmlDoc		*doc = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_GET_PERFCOUNTER,
			vmware_service_objects[service->type].property_collector,
			vmware_service_objects[service->type].performance_manager);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, error))
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

		group = zbx_xml_read_node_value(doc, nodeset->nodeTab[i],
				"*[local-name()='groupInfo']/*[local-name()='key']");

		key = zbx_xml_read_node_value(doc, nodeset->nodeTab[i],
						"*[local-name()='nameInfo']/*[local-name()='key']");

		rollup = zbx_xml_read_node_value(doc, nodeset->nodeTab[i], "*[local-name()='rollupType']");
		stats = zbx_xml_read_node_value(doc, nodeset->nodeTab[i], "*[local-name()='statsType']");
		counterid = zbx_xml_read_node_value(doc, nodeset->nodeTab[i], "*[local-name()='key']");

		if (NULL != group && NULL != key && NULL != rollup && NULL != counterid)
		{
			counter = (zbx_vmware_counter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_counter_t));
			counter->path = zbx_dsprintf(NULL, "%s/%s[%s]", group, key, rollup);
			ZBX_STR2UINT64(counter->id, counterid);

			zbx_vector_ptr_append(counters, counter);

			zabbix_log(LOG_LEVEL_DEBUG, "adding performance counter %s:" ZBX_FS_UI64, counter->path,
					counter->id);
		}

		if (NULL != group && NULL != key && NULL != rollup && NULL != counterid && NULL != stats)
		{
			counter = (zbx_vmware_counter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_counter_t));
			counter->path = zbx_dsprintf(NULL, "%s/%s[%s,%s]", group, key, rollup, stats);
			ZBX_STR2UINT64(counter->id, counterid);

			zbx_vector_ptr_append(counters, counter);

			zabbix_log(LOG_LEVEL_DEBUG, "adding performance counter %s:" ZBX_FS_UI64, counter->path,
					counter->id);
		}

		zbx_free(counterid);
		zbx_free(stats);
		zbx_free(rollup);
		zbx_free(key);
		zbx_free(group);
	}

	ret = SUCCEED;
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_vm_get_nic_devices                                        *
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

		if (NULL == (key = zbx_xml_read_node_value(details, nodeset->nodeTab[i], "*[local-name()='key']")))
			continue;

		dev = (zbx_vmware_dev_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dev_t));
		dev->type =  ZBX_VMWARE_DEV_TYPE_NIC;
		dev->instance = key;
		dev->label = zbx_xml_read_node_value(details, nodeset->nodeTab[i],
				"*[local-name()='deviceInfo']/*[local-name()='label']");

		zbx_vector_ptr_append(&vm->devs, dev);
		nics++;
	}
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, nics);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_vm_get_disk_devices                                       *
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
					*scsiCtlrUnitNumber = NULL;
		xmlXPathObject		*xpathObjController = NULL;

		do
		{
			if (NULL == (unitNumber = zbx_xml_read_node_value(details, nodeset->nodeTab[i],
					"*[local-name()='unitNumber']")))
			{
				break;
			}

			if (NULL == (controllerKey = zbx_xml_read_node_value(details, nodeset->nodeTab[i],
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

			if (NULL == (busNumber = zbx_xml_read_node_value(details,
					xpathObjController->nodesetval->nodeTab[0], "*[local-name()='busNumber']")))
			{
				break;
			}

			/* scsiCtlrUnitNumber property is simply used to determine controller type. */
			/* For IDE controllers it is not set.                                       */
			scsiCtlrUnitNumber = zbx_xml_read_node_value(details, xpathObjController->nodesetval->nodeTab[0],
				"*[local-name()='scsiCtlrUnitNumber']");

			dev = (zbx_vmware_dev_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dev_t));
			dev->type =  ZBX_VMWARE_DEV_TYPE_DISK;

			/* the virtual disk instance has format <controller type><busNumber>:<unitNumber> */
			/* where controller type is either ide or scsi depending on the controller type   */
			dev->instance = zbx_dsprintf(NULL, "%s%s:%s", (NULL == scsiCtlrUnitNumber ? "ide" : "scsi"),
					busNumber, unitNumber);

			dev->label = zbx_xml_read_node_value(details, nodeset->nodeTab[i],
					"*[local-name()='deviceInfo']/*[local-name()='label']");

			zbx_vector_ptr_append(&vm->devs, dev);

			disks++;

		}
		while (0);

		if (NULL != xpathObjController)
			xmlXPathFreeObject(xpathObjController);

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
 * Function: vmware_vm_get_file_systems                                       *
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

		if (NULL == (value = zbx_xml_read_node_value(details, nodeset->nodeTab[i], "*[local-name()='diskPath']")))
			continue;

		fs = (zbx_vmware_fs_t *)zbx_malloc(NULL, sizeof(zbx_vmware_fs_t));
		memset(fs, 0, sizeof(zbx_vmware_fs_t));

		fs->path = value;

		if (NULL != (value = zbx_xml_read_node_value(details, nodeset->nodeTab[i], "*[local-name()='capacity']")))
		{
			ZBX_STR2UINT64(fs->capacity, value);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_read_node_value(details, nodeset->nodeTab[i], "*[local-name()='freeSpace']")))
		{
			ZBX_STR2UINT64(fs->free_space, value);
			zbx_free(value);
		}

		zbx_vector_ptr_append(&vm->file_systems, fs);
	}
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, vm->file_systems.values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_vm_data                                       *
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
				"<ns0:objectSet>"					\
					"<ns0:obj type=\"VirtualMachine\">%s</ns0:obj>"	\
				"</ns0:objectSet>"					\
			"</ns0:specSet>"						\
			"<ns0:options/>"						\
		"</ns0:RetrievePropertiesEx>"						\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN], props[MAX_STRING_LEN], *vmid_esc;
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_create_vm                                         *
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

	if (NULL == (value = zbx_xml_read_doc_value(details, uuid_xpath[service->type])))
		goto out;

	vm->uuid = value;
	vm->id = zbx_strdup(NULL, id);

	if (NULL == (vm->props = xml_read_props(details, vm_propmap, ZBX_VMWARE_VMPROPS_NUM)))
		goto out;

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
 * Function: vmware_service_refresh_datastore_info                            *
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
	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_create_datastore                                  *
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
					"<ns0:pathSet>host</ns0:pathSet>"			\
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

	if (ZBX_VMWARE_TYPE_VSPHERE == service->type &&
			NULL != service->version && ZBX_VMWARE_DS_REFRESH_VERSION > atoi(service->version) &&
			SUCCEED != vmware_service_refresh_datastore_info(easyhandle, id_esc, &error))
	{
		zbx_free(id_esc);
		goto out;
	}

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_DATASTORE_GET,
			vmware_service_objects[service->type].property_collector, id_esc);

	zbx_free(id_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, &error))
		goto out;

	name = zbx_xml_read_doc_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("name"));

	if (NULL != (path = zbx_xml_read_doc_value(doc, ZBX_XPATH_DATASTORE_MOUNT())))
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

	if (ZBX_VMWARE_TYPE_VSPHERE == service->type)
	{
		if (NULL != (value = zbx_xml_read_doc_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("capacity"))))
		{
			is_uint64(value, &capacity);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_read_doc_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("freeSpace"))))
		{
			is_uint64(value, &free_space);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_read_doc_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("uncommitted"))))
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
	zbx_vector_str_create(&datastore->hv_uuids);
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
 * Function: vmware_service_get_hv_data                                       *
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
#	define ZBX_POST_HV_DETAILS 								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>HostSystem</ns0:type>"			\
					"<ns0:pathSet>vm</ns0:pathSet>"				\
					"<ns0:pathSet>parent</ns0:pathSet>"			\
					"<ns0:pathSet>datastore</ns0:pathSet>"			\
					"%s"							\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"		\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN], props[MAX_STRING_LEN], *hvid_esc;
	int	i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() guesthvid:'%s'", __func__, hvid);
	props[0] = '\0';

	for (i = 0; i < props_num; i++)
	{
		zbx_strlcat(props, "<ns0:pathSet>", sizeof(props));
		zbx_strlcat(props, propmap[i].name, sizeof(props));
		zbx_strlcat(props, "</ns0:pathSet>", sizeof(props));
	}

	hvid_esc = xml_escape_dyn(hvid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_HV_DETAILS,
			vmware_service_objects[service->type].property_collector, props, hvid_esc);

	zbx_free(hvid_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_hv_get_parent_data                                        *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, error))
		goto out;

	if (NULL == (hv->datacenter_name = zbx_xml_read_doc_value(doc,
			ZBX_XPATH_NAME_BY_TYPE(ZBX_VMWARE_SOAP_DATACENTER))))
	{
		hv->datacenter_name = zbx_strdup(NULL, "");
	}

	if (NULL != hv->clusterid && (NULL != (hv->parent_name = zbx_xml_read_doc_value(doc,
			ZBX_XPATH_NAME_BY_TYPE(ZBX_VMWARE_SOAP_CLUSTER)))))
	{
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_CLUSTER);
	}
	else if (NULL != (hv->parent_name = zbx_xml_read_doc_value(doc,
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
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t **)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t **)d2;

	return strcmp(ds1->name, ds2->name);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_ds_id_compare                                             *
 *                                                                            *
 * Purpose: sorting function to sort Datastore vector by id                   *
 *                                                                            *
 ******************************************************************************/
static int	vmware_ds_id_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t **)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t **)d2;

	return strcmp(ds1->id, ds2->id);
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
	xmlDoc			*details = NULL;
	zbx_vector_str_t	datastores, vms;
	int			i, j, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hvid:'%s'", __func__, id);

	memset(hv, 0, sizeof(zbx_vmware_hv_t));

	zbx_vector_str_create(&hv->ds_names);
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

	if (NULL != (value = zbx_xml_read_doc_value(details, "//*[@type='" ZBX_VMWARE_SOAP_CLUSTER "']")))
		hv->clusterid = value;

	if (SUCCEED != vmware_hv_get_parent_data(service, easyhandle, hv, error))
		goto out;

	zbx_xml_read_values(details, ZBX_XPATH_HV_DATASTORES(), &datastores);
	zbx_vector_str_reserve(&hv->ds_names, datastores.values_num);

	for (i = 0; i < datastores.values_num; i++)
	{
		zbx_vmware_datastore_t *ds;
		zbx_vmware_datastore_t ds_cmp;

		ds_cmp.id = datastores.values[i];

		if (FAIL == (j = zbx_vector_vmware_datastore_bsearch(dss, &ds_cmp, vmware_ds_id_compare)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Datastore \"%s\" not found on hypervisor \"%s\".", __func__,
					datastores.values[i], hv->id);
			continue;
		}

		ds = dss->values[j];
		zbx_vector_str_append(&ds->hv_uuids, zbx_strdup(NULL, hv->uuid));
		zbx_vector_str_append(&hv->ds_names, zbx_strdup(NULL, ds->name));
	}

	zbx_vector_str_sort(&hv->ds_names, ZBX_DEFAULT_STR_COMPARE_FUNC);
	zbx_xml_read_values(details, ZBX_XPATH_HV_VMS(), &vms);
	zbx_vector_ptr_reserve(&hv->vms, vms.values_num + hv->vms.values_alloc);

	for (i = 0; i < vms.values_num; i++)
	{
		zbx_vmware_vm_t	*vm;

		if (NULL != (vm = vmware_service_create_vm(service, easyhandle, vms.values[i], error)))
			zbx_vector_ptr_append(&hv->vms, vm);
	}

	ret = SUCCEED;
out:
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
 * Function: vmware_service_get_hv_ds_list                                    *
 *                                                                            *
 * Purpose: retrieves a list of all vmware service hypervisor ids             *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hvs          - [OUT] list of vmware hypervisor ids             *
 *             dss          - [OUT] list of vmware datastore ids              *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_hv_ds_list(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_str_t *hvs, zbx_vector_str_t *dss, char **error)
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

	if (SUCCEED != zbx_property_collection_init(easyhandle, tmp, "propertyCollector", &iter, &doc, error))
	{
		goto out;
	}

	if (ZBX_VMWARE_TYPE_VCENTER == service->type)
		zbx_xml_read_values(doc, "//*[@type='HostSystem']", hvs);
	else
		zbx_vector_str_append(hvs, zbx_strdup(NULL, "ha-host"));

	zbx_xml_read_values(doc, "//*[@type='Datastore']", dss);

	while (NULL != iter->token)
	{
		zbx_xml_free_doc(doc);
		doc = NULL;

		if (SUCCEED != zbx_property_collection_next(iter, &doc, error))
			goto out;

		if (ZBX_VMWARE_TYPE_VCENTER == service->type)
			zbx_xml_read_values(doc, "//*[@type='HostSystem']", hvs);

		zbx_xml_read_values(doc, "//*[@type='Datastore']", dss);
	}

	ret = SUCCEED;
out:
	zbx_property_collection_free(iter);
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s found hv:%d ds:%d", __func__, zbx_result_string(ret),
			hvs->values_num, dss->values_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_event_session                                 *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, error))
		goto out;

	if (NULL == (*event_session = zbx_xml_read_doc_value(doc, "/*/*/*/*[@type='EventHistoryCollector']")))
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
 * Function: vmware_service_reset_event_history_collector                     *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_read_previous_events                              *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_destroy_event_session                             *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_put_event_data                                    *
 *                                                                            *
 * Purpose: read event data by id from xml and put to array of events         *
 *                                                                            *
 * Parameters: events    - [IN/OUT] the array of parsed events                *
 *             xml_event - [IN] the xml node and id of parsed event           *
 *             xdoc      - [IN] xml document with eventlog records            *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 ******************************************************************************/
static int	vmware_service_put_event_data(zbx_vector_ptr_t *events, zbx_id_xmlnode_t xml_event, xmlDoc *xdoc)
{
	zbx_vmware_event_t	*event = NULL;
	char			*message, *time_str;
	int			timestamp = 0;

	if (NULL == (message = zbx_xml_read_node_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("fullFormattedMessage"))))
	{
		zabbix_log(LOG_LEVEL_TRACE, "skipping event key '" ZBX_FS_UI64 "', fullFormattedMessage"
				" is missing", xml_event.id);
		return FAIL;
	}

	zbx_replace_invalid_utf8(message);

	if (NULL == (time_str = zbx_xml_read_node_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("createdTime"))))
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
	event->message = message;
	event->timestamp = timestamp;
	zbx_vector_ptr_append(events, event);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_parse_event_data                                  *
 *                                                                            *
 * Purpose: parse multiple events data                                        *
 *                                                                            *
 * Parameters: events   - [IN/OUT] the array of parsed events                 *
 *             last_key - [IN] the key of last parsed event                   *
 *             xdoc     - [IN] xml document with eventlog records             *
 *                                                                            *
 * Return value: The count of events successfully parsed                      *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_parse_event_data(zbx_vector_ptr_t *events, zbx_uint64_t last_key, xmlDoc *xdoc)
{
	zbx_vector_id_xmlnode_t	ids;
	int			i, parsed_num = 0;
	char			*value;
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() last_key:" ZBX_FS_UI64, __func__, last_key);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)"/*/*/*"ZBX_XPATH_LN("returnval"), xpathCtx)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot make evenlog list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find items in evenlog list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_id_xmlnode_create(&ids);
	zbx_vector_id_xmlnode_reserve(&ids, nodeset->nodeNr);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_id_xmlnode_t	xml_event;
		zbx_uint64_t		key;

		if (NULL == (value = zbx_xml_read_node_value(xdoc, nodeset->nodeTab[i], ZBX_XPATH_NN("key"))))
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping eventlog record without key, xml number '%d'", i);
			continue;
		}

		if (SUCCEED != is_uint64(value, &key))
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

		/* we are reading "scrollable views" in reverse chronological order, */
		/* so inside a "scrollable view" latest events should come first too */
		for (i = ids.values_num - 1; i >= 0; i--)
		{
			if (SUCCEED == vmware_service_put_event_data(events, ids.values[i], xdoc))
				parsed_num++;
		}
	}

	zbx_vector_id_xmlnode_destroy(&ids);
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() parsed:%d", __func__, parsed_num);

	return parsed_num;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_event_data                                    *
 *                                                                            *
 * Purpose: retrieves event data                                              *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             events       - [OUT] a pointer to the output variable          *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_ptr_t *events, char **error)
{
	char		*event_session = NULL;
	int		ret = FAIL, soap_count = 5; /* 10 - initial value of eventlog records number in one response */
	xmlDoc		*doc = NULL;
	zbx_uint64_t	eventlog_last_key;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != vmware_service_get_event_session(service, easyhandle, &event_session, error))
		goto out;

	if (SUCCEED != vmware_service_reset_event_history_collector(easyhandle, event_session, error))
		goto end_session;

	if (NULL != service->data && 0 != service->data->events.values_num &&
			((const zbx_vmware_event_t *)service->data->events.values[0])->key > service->eventlog.last_key)
	{
		eventlog_last_key = ((const zbx_vmware_event_t *)service->data->events.values[0])->key;
	}
	else
		eventlog_last_key = service->eventlog.last_key;

	do
	{
		zbx_xml_free_doc(doc);
		doc = NULL;

		if ((ZBX_MAXQUERYMETRICS_UNLIMITED / 2) >= soap_count)
			soap_count = soap_count * 2;
		else if (ZBX_MAXQUERYMETRICS_UNLIMITED != soap_count)
			soap_count = ZBX_MAXQUERYMETRICS_UNLIMITED;

		if (0 != events->values_num &&
				(((const zbx_vmware_event_t *)events->values[events->values_num - 1])->key -
				eventlog_last_key -1) < (unsigned int)soap_count)
		{
			soap_count = ((const zbx_vmware_event_t *)events->values[events->values_num - 1])->key -
					eventlog_last_key - 1;
		}

		if (0 < soap_count && SUCCEED != vmware_service_read_previous_events(easyhandle, event_session,
				soap_count, &doc, error))
		{
			goto end_session;
		}
	}
	while (0 < vmware_service_parse_event_data(events, eventlog_last_key, doc));

	ret = SUCCEED;
end_session:
	if (SUCCEED != vmware_service_destroy_event_session(easyhandle, event_session, error))
		ret = FAIL;
out:
	zbx_free(event_session);
	zbx_xml_free_doc(doc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_last_event_data                               *
 *                                                                            *
 * Purpose: retrieves data only last event                                    *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             events       - [OUT] a pointer to the output variable          *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_last_event_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_ptr_t *events, char **error)
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, error))
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

	if (NULL == (value = zbx_xml_read_node_value(doc, xml_event.xml_node, ZBX_XPATH_NN("key"))))
	{
		*error = zbx_strdup(*error, "Cannot find last event key");
		goto clean;
	}

	if (SUCCEED != is_uint64(value, &xml_event.id))
	{
		*error = zbx_dsprintf(*error, "Cannot convert eventlog key from %s", value);
		zbx_free(value);
		goto clean;
	}

	zbx_free(value);

	if (SUCCEED != vmware_service_put_event_data(events, xml_event, doc))
	{
		*error = zbx_dsprintf(*error, "Cannot retrieve last eventlog data for key "ZBX_FS_UI64, xml_event.id);
		goto clean;
	}

	ret = SUCCEED;
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_LASTEVENT
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_clusters                                      *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_VCENTER_CLUSTER, clusters, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VCENTER_CLUSTER
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_cluster_status                                *
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, error))
		goto out;

	*status = zbx_xml_read_doc_value(doc, ZBX_XPATH_PROP_NAME("summary.overallStatus"));

	ret = SUCCEED;
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_CLUSTER_STATUS
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_get_cluster_list                                  *
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

	zbx_xml_read_values(cluster_data, "//*[@type='ClusterComputeResource']", &ids);
	zbx_vector_ptr_reserve(clusters, ids.values_num + clusters->values_alloc);

	for (i = 0; i < ids.values_num; i++)
	{
		char	*status;

		zbx_snprintf(xpath, sizeof(xpath), "//*[@type='ClusterComputeResource'][.='%s']"
				"/.." ZBX_XPATH_LN2("propSet", "val"), ids.values[i]);

		if (NULL == (name = zbx_xml_read_doc_value(cluster_data, xpath)))
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
 * Function: vmware_service_get_maxquerymetrics                               *
 *                                                                            *
 * Purpose: get vpxd.stats.maxquerymetrics parameter from vcenter only        *
 *                                                                            *
 * Parameters: easyhandle   - [IN] the CURL handle                            *
 *             max_qm       - [OUT] max count of Datastore metrics in one     *
 *                                  request                                   *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_maxquerymetrics(CURL *easyhandle, int *max_qm, char **error)
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

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_MAXQUERYMETRICS, &doc, error))
	{
		if (NULL == doc)	/* if not SOAP error */
			goto out;

		zabbix_log(LOG_LEVEL_WARNING, "Error of query maxQueryMetrics: %s.", *error);
		zbx_free(*error);
	}

	ret = SUCCEED;

	if (NULL == (val = zbx_xml_read_doc_value(doc, ZBX_XPATH_MAXQUERYMETRICS())))
	{
		*max_qm = ZBX_VPXD_STATS_MAXQUERYMETRICS;
		zabbix_log(LOG_LEVEL_DEBUG, "maxQueryMetrics used default value %d", ZBX_VPXD_STATS_MAXQUERYMETRICS);
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
		*max_qm = ZBX_VPXD_STATS_MAXQUERYMETRICS;
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
 * Function: vmware_counters_add_new                                          *
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
 * Function: vmware_service_initialize                                        *
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
	char			*version = NULL, *fullname = NULL;
	zbx_vector_ptr_t	counters;
	int			ret = FAIL;

	zbx_vector_ptr_create(&counters);

	if (SUCCEED != vmware_service_get_perf_counters(service, easyhandle, &counters, error))
		goto out;

	if (SUCCEED != vmware_service_get_contents(easyhandle, &version, &fullname, error))
		goto out;

	zbx_vmware_lock();

	service->version = vmware_shared_strdup(version);
	service->fullname = vmware_shared_strdup(fullname);
	vmware_counters_shared_copy(&service->counters, &counters);

	zbx_vmware_unlock();

	ret = SUCCEED;
out:
	zbx_free(version);
	zbx_free(fullname);

	zbx_vector_ptr_clear_ext(&counters, (zbx_mem_free_func_t)vmware_counter_free);
	zbx_vector_ptr_destroy(&counters);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_add_perf_entity                                   *
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
			if (SUCCEED == zbx_vmware_service_get_counterid(service, counters[i], &counterid))
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
 * Function: vmware_service_update_perf_entities                              *
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
						"datastore/totalWriteLatency[average]", NULL
					};
	const char			*vm_perfcounters[] = {
						"virtualDisk/read[average]", "virtualDisk/write[average]",
						"virtualDisk/numberReadAveraged[average]",
						"virtualDisk/numberWriteAveraged[average]",
						"net/packetsRx[summation]", "net/packetsTx[summation]",
						"net/received[average]", "net/transmitted[average]",
						"cpu/ready[summation]", NULL
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
		vmware_service_add_perf_entity(service, "HostSystem", hv->id, hv_perfcounters, "*", service->lastcheck);

		for (i = 0; i < hv->vms.values_num; i++)
		{
			vm = (zbx_vmware_vm_t *)hv->vms.values[i];
			vmware_service_add_perf_entity(service, "VirtualMachine", vm->id, vm_perfcounters, "*",
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
			vmware_service_add_perf_entity(service, "Datastore", ds->id, ds_perfcounters, "",
					service->lastcheck);
			zabbix_log(LOG_LEVEL_TRACE, "%s() for type: Datastore id: %s name: %s uuid: %s", __func__,
					ds->id, ds->name, ds->uuid);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() entities:%d", __func__, service->entities.num_data);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_update                                            *
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
	unsigned char		skip_old = service->eventlog.skip_old;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	data = (zbx_vmware_data_t *)zbx_malloc(NULL, sizeof(zbx_vmware_data_t));
	memset(data, 0, sizeof(zbx_vmware_data_t));
	page.alloc = 0;

	zbx_hashset_create(&data->hvs, 1, vmware_hv_hash, vmware_hv_compare);
	zbx_vector_ptr_create(&data->clusters);
	zbx_vector_ptr_create(&data->events);
	zbx_vector_vmware_datastore_create(&data->datastores);

	zbx_vector_str_create(&hvs);
	zbx_vector_str_create(&dss);

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

	if (0 != (service->state & ZBX_VMWARE_STATE_NEW) &&
			SUCCEED != vmware_service_initialize(service, easyhandle, &data->error))
	{
		goto clean;
	}

	if (SUCCEED != vmware_service_get_hv_ds_list(service, easyhandle, &hvs, &dss, &data->error))
		goto clean;

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
		zbx_vmware_hv_t	hv_local;

		if (SUCCEED == vmware_service_init_hv(service, easyhandle, hvs.values[i], &data->datastores, &hv_local,
				&data->error))
		{
			zbx_hashset_insert(&data->hvs, &hv_local, sizeof(hv_local));
		}
	}

	for (i = 0; i < data->datastores.values_num; i++)
	{
		zbx_vector_str_sort(&data->datastores.values[i]->hv_uuids, ZBX_DEFAULT_STR_COMPARE_FUNC);
	}

	zbx_vector_vmware_datastore_sort(&data->datastores, vmware_ds_name_compare);

	/* skip collection of event data if we don't know where we stopped last time or item can't accept values */
	if (ZBX_VMWARE_EVENT_KEY_UNINITIALIZED != service->eventlog.last_key && 0 == service->eventlog.skip_old &&
			SUCCEED != vmware_service_get_event_data(service, easyhandle, &data->events, &data->error))
	{
		goto clean;
	}

	if (0 != service->eventlog.skip_old)
	{
		char	*error = NULL;

		/* May not be present */
		if (SUCCEED != vmware_service_get_last_event_data(service, easyhandle, &data->events, &error))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Unable retrieve lastevent value: %s.", error);
			zbx_free(error);
		}
		else
			skip_old = 0;
	}

	if (ZBX_VMWARE_TYPE_VCENTER == service->type &&
			SUCCEED != vmware_service_get_cluster_list(easyhandle, &data->clusters, &data->error))
	{
		goto clean;
	}

	if (ZBX_VMWARE_TYPE_VCENTER != service->type)
		data->max_query_metrics = ZBX_VPXD_STATS_MAXQUERYMETRICS;
	else if (SUCCEED != vmware_service_get_maxquerymetrics(easyhandle, &data->max_query_metrics, &data->error))
		goto clean;

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

	if (NULL != service->data && 0 != service->data->events.values_num &&
			((const zbx_vmware_event_t *)service->data->events.values[0])->key > service->eventlog.last_key)
	{
		zbx_vector_ptr_append_array(&events, service->data->events.values, service->data->events.values_num);
		zbx_vector_ptr_clear(&service->data->events);
	}

	vmware_data_shared_free(service->data);
	service->data = vmware_data_shared_dup(data);
	service->eventlog.skip_old = skip_old;

	if (0 != events.values_num)
		zbx_vector_ptr_append_array(&service->data->events, events.values, events.values_num);

	service->lastcheck = time(NULL);

	vmware_service_update_perf_entities(service);

	zbx_vmware_unlock();

	vmware_data_free(data);
	zbx_vector_ptr_destroy(&events);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s \tprocessed:" ZBX_FS_SIZE_T " bytes of data", __func__,
			zbx_result_string(ret), (zbx_fs_size_t)page.alloc);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_process_perf_entity_data                          *
 *                                                                            *
 * Purpose: updates vmware performance statistics data                        *
 *                                                                            *
 * Parameters: pervalues - [OUT] the performance counter values               *
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
static int	vmware_service_process_perf_entity_data(zbx_vector_ptr_t *pervalues, xmlDoc *xdoc, xmlNode *node)
{
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	char			*instance, *counter, *value;
	int			i, values = 0, ret = FAIL;
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
		value = zbx_xml_read_node_value(xdoc, nodeset->nodeTab[i], "*[local-name()='value'][last()]");
		instance = zbx_xml_read_node_value(xdoc, nodeset->nodeTab[i], "*[local-name()='id']"
				"/*[local-name()='instance']");
		counter = zbx_xml_read_node_value(xdoc, nodeset->nodeTab[i], "*[local-name()='id']"
				"/*[local-name()='counterId']");

		if (NULL != value && NULL != counter)
		{
			perfvalue = (zbx_vmware_perf_value_t *)zbx_malloc(NULL, sizeof(zbx_vmware_perf_value_t));

			ZBX_STR2UINT64(perfvalue->counterid, counter);
			perfvalue->instance = (NULL != instance ? instance : zbx_strdup(NULL, ""));

			if (0 == strcmp(value, "-1") || SUCCEED != is_uint64(value, &perfvalue->value))
				perfvalue->value = UINT64_MAX;
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
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values:%d", __func__, values);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_service_parse_perf_data                                   *
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

		data->id = zbx_xml_read_node_value(xdoc, nodeset->nodeTab[i], "*[local-name()='entity']");
		data->type = zbx_xml_read_node_value(xdoc, nodeset->nodeTab[i], "*[local-name()='entity']/@type");
		data->error = NULL;
		zbx_vector_ptr_create(&data->values);

		if (NULL != data->type && NULL != data->id)
			ret = vmware_service_process_perf_entity_data(&data->values, xdoc, nodeset->nodeTab[i]);

		if (SUCCEED == ret)
			zbx_vector_ptr_append(perfdata, data);
		else
			vmware_free_perfdata(data);
	}
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_perf_data_add_error                                       *
 *                                                                            *
 * Purpose: adds error for the specified perf entity                          *
 *                                                                            *
 * Parameters: perfdata - [OUT] the collected performance counter data        *
 *             type     - [IN] the performance entity type (HostSystem,       *
 *                             (Datastore, VirtualMachine...)                 *
 *             id       - [IN] the performance entity id                      *
 *             error    - [IN] the error to add                               *
 *             instance - [IN] the performance counter instance name          *
 *             now      - [IN] the current timestamp                          *
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
 * Function: vmware_service_parse_perf_data                                   *
 *                                                                            *
 * Purpose: updates vmware performance statistics data                        *
 *                                                                            *
 * Parameters: service  - [IN] the vmware service                             *
 *             perfdata - [IN] the performance data                           *
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
 * Function: vmware_service_retrieve_perf_counters                            *
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

			zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:maxSample>1</ns0:maxSample>");

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

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, &error))
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
 * Function: vmware_service_update_perf                                       *
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
 * Function: vmware_service_remove                                            *
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
 * Function: vmware_get_service                                               *
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
 * Function: zbx_vmware_service_get_counterid                                 *
 *                                                                            *
 * Purpose: gets vmware performance counter id by the path                    *
 *                                                                            *
 * Parameters: service   - [IN] the vmware service                            *
 *             path      - [IN] the path of counter to retrieve in format     *
 *                              <group>/<key>[<rollup type>]                  *
 *             counterid - [OUT] the counter id                               *
 *                                                                            *
 * Return value: SUCCEED if the counter was found, FAIL otherwise             *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_get_counterid(zbx_vmware_service_t *service, const char *path,
		zbx_uint64_t *counterid)
{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	zbx_vmware_counter_t	*counter;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s", __func__, path);

	if (NULL == (counter = (zbx_vmware_counter_t *)zbx_hashset_search(&service->counters, &path)))
		goto out;

	*counterid = counter->id;

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
 * Function: zbx_vmware_service_add_perf_counter                              *
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
 * Function: zbx_vmware_service_get_perf_entity                               *
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
 * Function: zbx_vmware_init                                                  *
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
	zbx_hashset_create_ext(&vmware->strpool, 100, vmware_strpool_hash_func, vmware_strpool_compare_func, NULL,
		__vm_mem_malloc_func, __vm_mem_realloc_func, __vm_mem_free_func);
#endif
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vmware_destroy                                               *
 *                                                                            *
 * Purpose: destroys vmware collector service                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_destroy(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	zbx_hashset_destroy(&vmware->strpool);
#endif
	zbx_mutex_destroy(&vmware_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

#define	ZBX_VMWARE_TASK_IDLE		1
#define	ZBX_VMWARE_TASK_UPDATE		2
#define	ZBX_VMWARE_TASK_UPDATE_PERF	3
#define	ZBX_VMWARE_TASK_REMOVE		4

/******************************************************************************
 *                                                                            *
 * Function: main_vmware_loop                                                 *
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
 * Function: zbx_vmware_lock                                                  *
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
 * Function: zbx_vmware_unlock                                                *
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
 * Function: zbx_vmware_get_statistics                                        *
 *                                                                            *
 * Purpose: gets vmware collector statistics                                  *
 *                                                                            *
 * Parameters: stats   - [OUT] the vmware collector statistics                *
 *                                                                            *
 * Return value: SUCCEEED - the statistics were retrieved successfully        *
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
 * Function: libxml_handle_error                                              *
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
 * Function: zbx_xml_try_read_value                                           *
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
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlSetStructuredErrorFunc(NULL, NULL);
	xmlXPathFreeContext(xpathCtx);
	xmlResetLastError();
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xml_read_node_value                                          *
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
static char	*zbx_xml_read_node_value(xmlDoc *doc, xmlNode *node, const char *xpath)
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

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	if (NULL != (val = xmlNodeListGetString(doc, nodeset->nodeTab[0]->xmlChildrenNode, 1)))
	{
		value = zbx_strdup(NULL, (const char *)val);
		xmlFree(val);
	}
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);

	return value;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xml_read_doc_value                                           *
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
static char	*zbx_xml_read_doc_value(xmlDoc *xdoc, const char *xpath)
{
	xmlNode	*root_element;

	root_element = xmlDocGetRootElement(xdoc);
	return zbx_xml_read_node_value(xdoc, root_element, xpath);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xml_read_values                                              *
 *                                                                            *
 * Purpose: populate array of values from a xml data                          *
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
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	int		i, ret = FAIL;

	if (NULL == xdoc)
		goto out;

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)xpath, xpathCtx)))
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
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
out:
	return ret;
}

#endif
