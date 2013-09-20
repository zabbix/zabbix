/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
#include "log.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "checks_simple_vmware.h"

#define ZBX_XPATH_LN(LN)		"/*[local-name()='" LN "']"
#define ZBX_XPATH_LN1(LN1)		"/" ZBX_XPATH_LN(LN1)
#define ZBX_XPATH_LN2(LN1, LN2)		"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2)
#define ZBX_XPATH_LN3(LN1, LN2, LN3)	"/" ZBX_XPATH_LN(LN1) ZBX_XPATH_LN(LN2) ZBX_XPATH_LN(LN3)

#define	ZBX_XML_HEADER1		"Soapaction:urn:vim25/4.1"
#define ZBX_XML_HEADER2		"Content-Type:text/xml; charset=utf-8"

#define ZBX_VMWARE_CACHE_TTL	SEC_PER_MIN

#define ZBX_VMWARE_FLAG_VCENTER	0x01
#define ZBX_VMWARE_FLAG_VSPHERE	0x02

#define ZBX_POST_VSPHERE_HEADER								\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"				\
		"<SOAP-ENV:Envelope"							\
			" xmlns:ns0=\"urn:vim25\""					\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""	\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""	\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"\
			"<SOAP-ENV:Header/>"						\
			"<ns1:Body>"

#define ZBX_POST_VSPHERE_FOOTER								\
			"</ns1:Body>"							\
		"</SOAP-ENV:Envelope>"

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
ZBX_HTTPPAGE;

static ZBX_HTTPPAGE	page;

static int		vcenters_initialized = 0;
static zbx_vector_ptr_t	vcenters;

static int		vspheres_initialized = 0;
static zbx_vector_ptr_t	vspheres;

typedef struct
{
	int		initialized;

	zbx_uint64_t	nic_packets_rx;
	zbx_uint64_t	nic_packets_tx;
	zbx_uint64_t	nic_received;
	zbx_uint64_t	nic_transmitted;

	zbx_uint64_t	disk_read;
	zbx_uint64_t	disk_write;
	zbx_uint64_t	disk_number_read_averaged;
	zbx_uint64_t	disk_number_write_averaged;

	zbx_uint64_t	datastore_read_latency;
	zbx_uint64_t	datastore_write_latency;
}
zbx_counters_t;

typedef struct
{
	char	*name;
	char	*uuid;
}
zbx_datastore_t;

typedef struct
{
	char			*url;
	char			*username;
	char			*password;
	char			*events;
	zbx_vector_ptr_t	hvs;
	zbx_vector_ptr_t	clusters;
	int			lastcheck;
	zbx_counters_t		counters;
}
zbx_vcenter_t;

typedef struct
{
	char			*url;
	char			*username;
	char			*password;
	char			*events;
	char			*details;
	char			*stats;
	zbx_vector_ptr_t	vms;
	int			lastcheck;
	zbx_counters_t		counters;
	zbx_vector_ptr_t	datastores;
}
zbx_vsphere_t;

typedef struct
{
	char			*uuid;
	char			*id;
	char			*details;
	char			*clusterid;
	char			*stats;
	zbx_vector_ptr_t	vms;
	zbx_vector_ptr_t	datastores;
}
zbx_hv_t;

typedef struct
{
	char			*uuid;
	char			*id;
	char			*status_ex;
	char			*stats;
	zbx_vector_ptr_t	devs;
}
zbx_vm_t;

typedef struct
{
	char			*id;
	char			*name;
	char			*status;
	char			*vmlist;
}
zbx_cluster_t;

typedef struct
{
#define ZBX_DEV_TYPE_NIC	1
#define ZBX_DEV_TYPE_DISK	2
	int			type;
	char			*instance;
	zbx_vector_uint64_t	counterids;
}
zbx_dev_t;

static void	zbx_dev_free(zbx_dev_t *dev)
{
	zbx_vector_uint64_destroy(&dev->counterids);
	zbx_free(dev->instance);
	zbx_free(dev);
}

static void	zbx_datastore_free(zbx_datastore_t *datastore)
{
	zbx_free(datastore->name);
	zbx_free(datastore->uuid);
	zbx_free(datastore);
}

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	zbx_strncpy_alloc(&page.data, &page.alloc, &page.offset, ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	return size * nmemb;
}

/******************************************************************************
 *                                                                            *
 * Function: read_xml_value                                                   *
 *                                                                            *
 * Purpose: populate value from a xml data                                    *
 *                                                                            *
 * Parameters: data   - [IN] XML data                                         *
 *             xpath  - [IN] XML XPath                                        *
 *             values - [OUT] list of requested values                        *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static char	*read_xml_value(const char *data, const char *xpath)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	char		*value = NULL;

	if (NULL == data)
		goto out;

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)xpath, xpathCtx)))
		goto clean;

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	val = xmlNodeListGetString(doc, nodeset->nodeTab[0]->xmlChildrenNode, 1);
	value = zbx_strdup(NULL, (char *)val);
	xmlFree(val);
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);
	xmlCleanupParser();
out:
	return value;
}

/******************************************************************************
 *                                                                            *
 * Function: read_xml_node_value                                              *
 *                                                                            *
 * Purpose: read xml node value relative to the the specified node            *
 *                                                                            *
 * Parameters: doc    - [IN] the XML document                                 *
 *             node   - [IN] the starting XML node                            *
 *             xpath  - [IN] the ML XPath                                     *
 *             values - [OUT] the node value                                  *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static char	*read_xml_node_value(xmlDoc *doc, xmlNode *node, const char *xpath)
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

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	val = xmlNodeListGetString(doc, nodeset->nodeTab[0]->xmlChildrenNode, 1);
	value = zbx_strdup(NULL, (char *)val);
	xmlFree(val);
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	xmlCleanupParser();

	return value;
}

/******************************************************************************
 *                                                                            *
 * Function: read_xml_values                                                  *
 *                                                                            *
 * Purpose: populate array of values from a xml data                          *
 *                                                                            *
 * Parameters: data   - [IN] XML data                                         *
 *             xpath  - [IN] XML XPath                                        *
 *             values - [OUT] list of requested values                        *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	read_xml_values(const char *data, const char *xpath, zbx_vector_str_t *values)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	int		i, ret = FAIL;

	if (NULL == data)
		goto out;

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)xpath, xpathCtx)))
		goto clean;

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		val = xmlNodeListGetString(doc, nodeset->nodeTab[i]->xmlChildrenNode, 1);
		if (NULL != val)
			zbx_vector_str_append(values, zbx_strdup(NULL, (char *)val));
		xmlFree(val);
	}

	ret = SUCCEED;
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);
	xmlCleanupParser();

out:
	return ret;
}

/*
 * key - performance counter reference mapping
 */
typedef struct
{
	const char	*key;
	zbx_uint64_t	*pcounter;
}
zbx_perfcounter_mapping_t;

/******************************************************************************
 *                                                                            *
 * Function: vmware_get_group_perfcounters                                    *
 *                                                                            *
 * Purpose: read the specified performance counter ids filtered by a group    *
 *                                                                            *
 * Parameters: data     - [IN] XML data                                       *
 *             size     - [IN] the size of XML data                           *
 *             grou  p  - [IN] the group name                                 *
 *             counters - [IN/OUT] mapping of counter keys to output values   *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	vmware_get_group_perfcounters(const char *data, int size, const char *group,
		zbx_perfcounter_mapping_t *counters)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	char		*xpath = NULL;
	int		i, ret = FAIL;

	if (NULL == (doc = xmlReadMemory(data, size, "noname.xml", NULL, 0)))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	xpath = zbx_dsprintf(xpath, "//*[local-name()='PerfCounterInfo'][*[local-name()='groupInfo']"
			"/*[local-name()='key']/text()='%s']", group);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)xpath, xpathCtx)))
		goto clean;

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		char				*key, *counterId;
		zbx_perfcounter_mapping_t	*counter;

		if (NULL == (key = read_xml_node_value(doc, nodeset->nodeTab[i],
				"*[local-name()='nameInfo']/*[local-name()='key']")))
		{
			continue;
		}

		for (counter = counters; NULL != counter->key; counter++)
		{
			if (0 != strcmp(counter->key, key))
				continue;

			if (NULL == (counterId = read_xml_node_value(doc, nodeset->nodeTab[i],
					"*[local-name()='key']")))
			{
				continue;
			}

			is_uint64(counterId, counter->pcounter);
			zbx_free(counterId);
		}

		zbx_free(key);
	}

	ret = SUCCEED;
clean:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	zbx_free(xpath);

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);
	xmlCleanupParser();
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_init_perf_counters                                        *
 *                                                                            *
 * Purpose: initializes performance counters                                  *
 *                                                                            *
 * Parameters: data     - [IN] easyhandle                                     *
 *             size     - [OUT] the size of XML data                          *
 *             error    - [OUT] error message                                 *
 *             flags    - [OUT] ZBX_VMWARE_FLAG_VCENTER                       *
 *                              ZBX_VMWARE_FLAG_VCENTER                       *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	vmware_init_perf_counters(CURL *easyhandle, zbx_counters_t *counters, char **error, unsigned flags)
{
#	define ZBX_POST_VMWARE_GET_PERFCOUTNER							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrieveProperties>"							\
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
		"</ns0:RetrieveProperties>"							\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vmware_init_perf_counters";
	char		tmp[MAX_STRING_LEN];
	int		opts, err, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != counters->initialized)
	{
		zbx_perfcounter_mapping_t disk_counters[] = {
				{"read", &counters->disk_read},
				{"write", &counters->disk_write},
				{"numberReadAveraged", &counters->disk_number_read_averaged},
				{"numberWriteAveraged", &counters->disk_number_write_averaged},
				{NULL, NULL}};

		zbx_perfcounter_mapping_t nic_counters[] = {
				{"packetsRx", &counters->nic_packets_rx},
				{"packetsTx", &counters->nic_packets_tx},
				{"received", &counters->nic_received},
				{"transmitted", &counters->nic_transmitted},
				{NULL, NULL}};

		zbx_perfcounter_mapping_t datastore_counters[] = {
				{"totalReadLatency", &counters->datastore_read_latency},
				{"totalWriteLatency", &counters->datastore_write_latency},
				{NULL, NULL}};

		ret = FAIL;

		zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_GET_PERFCOUTNER,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "propertyCollector" : "ha-property-collector",
			ZBX_VMWARE_FLAG_VCENTER == flags ? "PerfMgr" : "ha-perfmgr");

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opts = CURLOPT_POSTFIELDS, tmp)))
		{
			*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opts, curl_easy_strerror(err));
			goto out;
		}

		page.offset = 0;

		if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
		{
			*error = zbx_strdup(*error, curl_easy_strerror(err));
			goto out;
		}

		if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
			goto out;

		if (SUCCEED != vmware_get_group_perfcounters(page.data, page.offset, "virtualDisk", disk_counters))
		{
			*error = zbx_strdup(*error, "Cannot find performance counters for virtualDisk group");
			goto out;
		}

		if (SUCCEED != vmware_get_group_perfcounters(page.data, page.offset, "net", nic_counters))
		{
			*error = zbx_strdup(*error, "Cannot find performance counters for net group");
			goto out;
		}

		if (SUCCEED != vmware_get_group_perfcounters(page.data, page.offset, "datastore", datastore_counters))
		{
			*error = zbx_strdup(*error, "Cannot find performance counters for datastore group");
			goto out;
		}

		counters->initialized = 1;

		/* The counter data uses a lot of memory which is used only once during initialization. */
		/* Reset the download buffer afterwards so the memory is not wasted.                    */
		zbx_free(page.data);
		page.alloc = 0;
		page.offset = 0;

		ret = SUCCEED;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: wmware_get_vm_nic_devices                                        *
 *                                                                            *
 * Purpose: scan vm configuration for virtual networking cards and add to     *
 *          the device vector                                                 *
 *                                                                            *
 * Parameters: vm     - [IN/OUT] virtual machine                              *
 *                                                                            *
 ******************************************************************************/
static void	wmware_vm_populate_nic_devices(zbx_vm_t *vm)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i;

	if (NULL == (doc = xmlReadMemory(vm->status_ex, strlen(vm->status_ex), "noname.xml", NULL, 0)))
		return;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)"//*[local-name()='hardware']/"
			"*[local-name()='device'][*[local-name()='macAddress']]", xpathCtx)))
	{
		goto out;
	}

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		char		*key;
		zbx_dev_t	*dev;

		if (NULL == (key = read_xml_node_value(doc, nodeset->nodeTab[i], "*[local-name()='key']")))
			continue;

		dev = zbx_malloc(NULL, sizeof(zbx_dev_t));
		dev->type =  ZBX_DEV_TYPE_NIC;
		dev->instance = key;
		zbx_vector_uint64_create(&dev->counterids);

		zbx_vector_ptr_append(&vm->devs, dev);
	}
out:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);
	xmlCleanupParser();
}

/******************************************************************************
 *                                                                            *
 * Function: wmware_vm_populate_disk_devices_by_counterid                     *
 *                                                                            *
 * Purpose: scan disk stats for a specific counter value and add found disks  *
 *          the device vector                                                 *
 *                                                                            *
 * Parameters: vm         - [IN/OUT] virtual machine                          *
 *             counterid  - [IN] the counter id to scan for                   *
 *                                                                            *
 ******************************************************************************/
static void	wmware_vm_populate_disk_devices_by_counterid(zbx_vm_t *vm, zbx_uint64_t counterid)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	char		*xpath = NULL;
	int		i;

	if (NULL == (doc = xmlReadMemory(vm->stats, strlen(vm->stats), "noname.xml", NULL, 0)))
		return;

	xpathCtx = xmlXPathNewContext(doc);

	xpath = zbx_dsprintf(xpath, "//*[local-name()='value']/*[local-name()='id']"
			"[*[local-name()='counterId']/text()='" ZBX_FS_UI64 "']", counterid);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)xpath, xpathCtx)))
		goto out;

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		char		*instance;
		zbx_dev_t	*dev;

		if (NULL == (instance = read_xml_node_value(doc, nodeset->nodeTab[i], "*[local-name()='instance']")))
			continue;

		dev = zbx_malloc(NULL, sizeof(zbx_dev_t));
		dev->type =  ZBX_DEV_TYPE_DISK;
		dev->instance = instance;
		zbx_vector_uint64_create(&dev->counterids);

		zbx_vector_ptr_append(&vm->devs, dev);
	}
out:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);
	xmlCleanupParser();
}

static int	vmware_set_powerstate_result(AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;

	if (NULL != GET_STR_RESULT(result))
	{
		ret = SYSINFO_RET_OK;

		if (0 == strcmp(result->str, "poweredOff"))
			SET_UI64_RESULT(result, 0);
		else if (0 == strcmp(result->str, "poweredOn"))
			SET_UI64_RESULT(result, 1);
		else if (0 == strcmp(result->str, "suspended"))
			SET_UI64_RESULT(result, 2);
		else
			ret = SYSINFO_RET_FAIL;

		UNSET_STR_RESULT(result);
	}

	return ret;

}

/******************************************************************************
 *                                                                            *
 * Function: vmware_authenticate                                              *
 *                                                                            *
 * Purpose: opens new vSphere or vCenter session                              *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL easy handle                             *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	vmware_authenticate(CURL *easyhandle, const char *url, const char *username, const char *password,
		char **error, unsigned flags)
{
#	define ZBX_POST_VMWARE_AUTH						\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:Login xsi:type=\"ns0:LoginRequestType\">"			\
			"<ns0:_this type=\"SessionManager\">%s</ns0:_this>"	\
			"<ns0:userName>%s</ns0:userName>"			\
			"<ns0:password>%s</ns0:password>"			\
		"</ns0:Login>"							\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vmware_authenticate";
	int		err, o, timeout = 10, ret = FAIL;
	char		xml[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s'", __function_name, url, username);

	zbx_snprintf(xml, sizeof(xml), ZBX_POST_VMWARE_AUTH,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "SessionManager" : "ha-sessionmgr", username, password);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_URL, url)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, xml)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vcenter_guesthvids_get                                           *
 *                                                                            *
 * Purpose: populate array of guest VMs                                       *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL easy handle                             *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	vcenter_guesthvids_get(CURL *easyhandle, char **error)
{
#	define ZBX_POST_VCENTER_HV_LIST								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx xsi:type=\"ns0:RetrievePropertiesExRequestType\">"	\
			"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>HostSystem</ns0:type>"			\
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

	const char	*__function_name = "vcenter_guesthvids_get";

	int		err, o, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, ZBX_POST_VCENTER_HV_LIST)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() page.data:'%s'", __function_name, page.data);

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vcenter_clusters_get                                             *
 *                                                                            *
 * Purpose: populate array of guest VMs                                       *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL easy handle                             *
 *             clusters   - [OUT] clusters xml                                *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	vcenter_clusters_get(CURL *easyhandle, char **clusters, char **error)
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

	const char	*__function_name = "vcenter_clusters_get";

	int		err, o, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, ZBX_POST_VCENTER_CLUSTER)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	*clusters = zbx_strdup(*clusters, page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vcenter_cluster_status_get(CURL *easyhandle, const char *clusterid, char **status, char **error)
{
#	define ZBX_POST_VMWARE_CLUSTER_STATUS 								\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"		\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>ClusterComputeResource</ns0:type>"			\
					"<ns0:all>false</ns0:all>"					\
					"<ns0:pathSet>summary</ns0:pathSet>"				\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"ClusterComputeResource\">%s</ns0:obj>"		\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options></ns0:options>"							\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vcenter_cluster_status_get";

	int		err, o, ret = FAIL;
	char		tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() clusterid:'%s'", __function_name, clusterid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_CLUSTER_STATUS, clusterid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	*status = zbx_strdup(*status, page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_hv_guestvmids_get                                         *
 *                                                                            *
 * Purpose: populate array of guest VMs                                       *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL easy handle                             *
 *             guesthvid  - [IN] hypervisor ID                                *
 *             guestvmids - [OUT] list of guest VMs IDs                       *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static int	vmware_hv_guestvmids_get(CURL *easyhandle, const char *guesthvid, zbx_vector_str_t *guestvmids,
		char **error, unsigned flags)
{
#	define ZBX_POST_VMWARE_HV_GUESTVMIDS						\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:RetrieveProperties>"						\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"		\
			"<ns0:specSet>"							\
				"<ns0:propSet>"						\
					"<ns0:type>HostSystem</ns0:type>"		\
					"<ns0:pathSet>vm</ns0:pathSet>"			\
				"</ns0:propSet>"					\
				"<ns0:objectSet>"					\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"	\
				"</ns0:objectSet>"					\
			"</ns0:specSet>"						\
		"</ns0:RetrieveProperties>"						\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vmware_hv_guestvmids_get";

	int		err, o, ret = FAIL;
	char		tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_HV_GUESTVMIDS,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "propertyCollector" : "ha-property-collector", guesthvid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	read_xml_values(page.data, "//*[@type='VirtualMachine']", guestvmids);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vcenter_hv_data_get(CURL *easyhandle, const char *guesthvid, char **hv_details)
{
#	define ZBX_POST_VCENTER_HV_DETAILS 							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrieveProperties>"							\
			"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>HostSystem</ns0:type>"			\
					"<ns0:pathSet>name</ns0:pathSet>"			\
					"<ns0:pathSet>vm</ns0:pathSet>"				\
					"<ns0:pathSet>summary</ns0:pathSet>"			\
					"<ns0:pathSet>parent</ns0:pathSet>"			\
					"<ns0:pathSet>datastore</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"		\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
		"</ns0:RetrieveProperties>"							\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vcenter_hv_data_get";

	int		err, o, ret = FAIL;
	char		*error, tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() guesthvid:'%s'", __function_name, guesthvid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VCENTER_HV_DETAILS, guesthvid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s",
				o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", error);
		zbx_free(error);
		goto out;
	}

	*hv_details = zbx_strdup(*hv_details, page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vmware_perf_counters_refresh_rate_get(CURL *easyhandle, const char *guestvmid, int *refresh_rate,
		char **error, unsigned flags)
{
#	define ZBX_POST_VCENTER_PERF_COUNTERS_REFRESH_RATE			\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:QueryPerfProviderSummary>"				\
			"<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>"	\
			"<ns0:entity type=\"VirtualMachine\">%s</ns0:entity>"	\
		"</ns0:QueryPerfProviderSummary>"				\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vmware_perf_counters_refresh_rate_get";

	int		err, o, ret = FAIL;
	char		tmp[MAX_STRING_LEN], *value = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VCENTER_PERF_COUNTERS_REFRESH_RATE,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "PerfMgr" : "ha-perfmgr", guestvmid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	if (NULL == (value = read_xml_value(page.data, ZBX_XPATH_LN2("returnval", "refreshRate"))))
	{
		*error = zbx_strdup(*error, "Cannot get refreshRate");
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() refresh_rate:%s", __function_name, value);

	if (SUCCEED != (ret = is_uint31(value, refresh_rate)))
		*error = zbx_strdup(*error, "Cannot get refreshRate");

	zbx_free(value);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	add_perf_counter_stat_metric(char **tmp, size_t *tmp_alloc, size_t *tmp_offset, const char *instance,
		zbx_uint64_t counterid)
{
	if (0 == counterid)
		return;

	zbx_strcpy_alloc(tmp, tmp_alloc, tmp_offset, "<ns0:metricId>");
	zbx_snprintf_alloc(tmp, tmp_alloc, tmp_offset, "<ns0:counterId>" ZBX_FS_UI64 "</ns0:counterId>", counterid);
	zbx_snprintf_alloc(tmp, tmp_alloc, tmp_offset, "<ns0:instance>%s</ns0:instance>", instance);
	zbx_strcpy_alloc(tmp, tmp_alloc, tmp_offset, "</ns0:metricId>");
}

static int	vmware_vm_perf_counter_stats_get(CURL *easyhandle, zbx_vm_t *vm, zbx_counters_t *counters, char **error,
		unsigned flags)
{
	const char		*__function_name = "vmware_vm_perf_counter_stats_get";

	int			i, err, o, ret = FAIL, refresh_rate;
	char			*tmp = NULL;
	size_t			tmp_alloc = 0, tmp_offset = 0;
	zbx_dev_t		*dev;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != vmware_perf_counters_refresh_rate_get(easyhandle, vm->id, &refresh_rate, error, flags))
		goto out;

	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_HEADER);
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:QueryPerf>");
	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>",
			ZBX_VMWARE_FLAG_VCENTER == flags ? "PerfMgr" : "ha-perfmgr");
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:querySpec>");
	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:entity type=\"VirtualMachine\">%s</ns0:entity>",
			vm->id);
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:maxSample>1</ns0:maxSample>");

	/* first add networking devices which are already populated in device list */
	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_dev_t *)vm->devs.values[i];

		switch (dev->type)
		{
			case ZBX_DEV_TYPE_NIC:
				add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, dev->instance,
						counters->nic_packets_rx);
				add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, dev->instance,
						counters->nic_packets_tx);
				add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, dev->instance,
						counters->nic_received);
				add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, dev->instance,
						counters->nic_transmitted);
				break;
		}
	}

	/* then add all virtual disk devices */
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "*", counters->disk_read);
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "*", counters->disk_write);
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "*", counters->disk_number_read_averaged);
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "*", counters->disk_number_write_averaged);

	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:intervalId>%d</ns0:intervalId>", refresh_rate);
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:querySpec>");
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:QueryPerf>");
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_FOOTER);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	vm->stats = zbx_strdup(vm->stats, page.data);

	ret = SUCCEED;
out:
	zbx_free(tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vmware_perf_counter_stats_get(CURL *easyhandle, const char *host, zbx_counters_t *counters, char **stats,
		char **error, unsigned flags)
{
	const char		*__function_name = "vmware_perf_counter_stats_get";

	int			err, o, ret = FAIL, refresh_rate;
	char			*tmp = NULL;
	size_t			tmp_alloc = 0, tmp_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != vmware_perf_counters_refresh_rate_get(easyhandle, host, &refresh_rate, error, flags))
		goto out;

	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_HEADER);
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:QueryPerf>");
	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>",
			ZBX_VMWARE_FLAG_VCENTER == flags ? "PerfMgr" : "ha-perfmgr");
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:querySpec>");
	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:entity type=\"HostSystem\">%s</ns0:entity>",
			host);
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:maxSample>1</ns0:maxSample>");

	/* add total host networking stats */
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "", counters->nic_received);
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "", counters->nic_transmitted);

	/* add datastore stats */
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "*", counters->datastore_read_latency);
	add_perf_counter_stat_metric(&tmp, &tmp_alloc, &tmp_offset, "*", counters->datastore_write_latency);

	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:intervalId>%d</ns0:intervalId>", refresh_rate);
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:querySpec>");
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:QueryPerf>");
	zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_FOOTER);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	*stats = zbx_strdup(*stats, page.data);

	ret = SUCCEED;
out:
	zbx_free(tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}


static int	vmware_vm_status_ex_get(CURL *easyhandle, const char *vmid, unsigned flags)
{
#	define ZBX_POST_VMWARE_VM_STATUS_EX 						\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:RetrievePropertiesEx>"						\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"		\
			"<ns0:specSet>"							\
				"<ns0:propSet>"						\
					"<ns0:type>VirtualMachine</ns0:type>"		\
					"<ns0:all>true</ns0:all>"			\
				"</ns0:propSet>"					\
				"<ns0:objectSet>"					\
					"<ns0:obj type=\"VirtualMachine\">%s</ns0:obj>"	\
				"</ns0:objectSet>"					\
			"</ns0:specSet>"						\
			"<ns0:options></ns0:options>"					\
		"</ns0:RetrievePropertiesEx>"						\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vmware_vm_status_ex_get";

	int		err, o, ret = FAIL;
	char		*error, tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __function_name, vmid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_VM_STATUS_EX,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "propertyCollector" : "ha-property-collector", vmid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s",
				o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", error);
		zbx_free(error);
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vmware_event_session_get(CURL *easyhandle, char **event_session, char **error, unsigned flags)
{
#	define ZBX_POST_VMWARE_EVENT_FILTER					\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:CreateCollectorForEvents>"				\
			"<ns0:_this type=\"EventManager\">%s</ns0:_this>"	\
			"<ns0:filter/>"						\
		"</ns0:CreateCollectorForEvents>"				\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vmware_event_session_get";

	int		err, o, ret = FAIL;
	char		tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_EVENT_FILTER,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "EventManager" : "ha-eventmgr");

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	if (NULL == (*event_session = read_xml_value(page.data, "//*[@type='EventHistoryCollector']")))
	{
		*error = zbx_strdup(*error, "Cannot get EventHistoryCollector session");
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vmware_events_get(CURL *easyhandle, const char *event_session, char **events, char **error,
		unsigned flags)
{
#	define ZBX_POST_VMWARE_EVENTS_GET							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>EventHistoryCollector</ns0:type>"		\
					"<ns0:all>true</ns0:all>"				\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"EventHistoryCollector\">%s</ns0:obj>"	\
					"<ns0:skip>false</ns0:skip>"				\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vmware_events_get";

	int		err, o, ret = FAIL;
	char		tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() event_session:'%s'", __function_name, event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_EVENTS_GET,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "propertyCollector" : "ha-property-collector",
			event_session);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, tmp)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	*events = zbx_strdup(*events, page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_datastores_add                                            *
 *                                                                            *
 * Purpose: add the specified datastore to a list                             *
 *                                                                            *
 * Parameters: easyhandle   - [IN] connection handle                          *
 *             datastores   - [OUT] the vector of datastores found in details *
 *             id           - [IN] an id of the datastore to add              *
 *             flags        - [OUT] ZBX_VMWARE_FLAG_VCENTER                   *
 *                                  ZBX_VMWARE_FLAG_VCENTER                   *
 *                                                                            *
 * Comments: This function retrieves datastore name/vmfs uuid from the server.*
 *           If an error occured then id is used as datastore name.           *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datastores_add(CURL *easyhandle, zbx_vector_ptr_t *datastores, const char *id, unsigned flags)
{
#	define ZBX_POST_DATASTORE_GET								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>Datastore</ns0:type>"			\
					"<ns0:pathSet>summary</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"Datastore\">%s</ns0:obj>"		\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	char		tmp[MAX_STRING_LEN], *uuid = NULL, *name = NULL, *url;
	zbx_datastore_t	*datastore;

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_DATASTORE_GET,
			ZBX_VMWARE_FLAG_VCENTER == flags ? "propertyCollector" : "ha-property-collector", id);

	if (CURLE_OK != curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, tmp))
		goto out;

	page.offset = 0;

	if (CURLE_OK != curl_easy_perform(easyhandle))
		goto out;

	name = read_xml_value(page.data, ZBX_XPATH_LN2("val", "name"));

	if (NULL != (url = read_xml_value(page.data, ZBX_XPATH_LN2("val", "url"))))
	{
		if ('\0' != *url)
		{
			size_t	len;
			char	*ptr;

			len = strlen(url);

			if ('/' == url[len - 1])
				url[len - 1] = '\0';

			for (ptr = url + len - 2; ptr > url && *ptr != '/'; ptr--)
				;

			uuid = zbx_strdup(NULL, ptr + 1);
		}
		zbx_free(url);
	}
out:
	datastore = zbx_malloc(NULL, sizeof(zbx_datastore_t));
	datastore->name = (NULL != name ? name : zbx_strdup(NULL, id));
	datastore->uuid = uuid;

	zbx_vector_ptr_append(datastores, datastore);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_datastores_update                                         *
 *                                                                            *
 * Purpose: update datastore list from datastores listed in host details      *
 *                                                                            *
 * Parameters: easyhandle   - [IN] connection handle                          *
 *             datastores   - [OUT] the vector of datastores found in details *
 *             flags        - [OUT] ZBX_VMWARE_FLAG_VCENTER                   *
 *                                  ZBX_VMWARE_FLAG_VCENTER                   *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datastores_update(CURL *easyhandle, zbx_vector_ptr_t *datastores, const char *details,
		unsigned flags)
{
	int			i;
	zbx_vector_str_t	values;

	zbx_vector_ptr_clean(datastores, (zbx_mem_free_func_t)zbx_datastore_free);

	zbx_vector_str_create(&values);

	read_xml_values(details, "//*[@type='Datastore']", &values);

	for (i = 0; i < values.values_num; i++)
		vmware_datastores_add(easyhandle, datastores, values.values[i], flags);

	zbx_vector_str_clean(&values);
	zbx_vector_str_destroy(&values);
}

static zbx_vcenter_t	*vcenter_get(const char *url, const char *username, const char *password)
{
	zbx_vcenter_t	*vcenter;
	int		i;

	for (i = 0; i < vcenters.values_num; i++)
	{
		vcenter = (zbx_vcenter_t *)vcenters.values[i];

		if (0 != strcmp(vcenter->url, url))
			continue;
		if (0 != strcmp(vcenter->username, username))
			continue;
		if (0 != strcmp(vcenter->password, password))
			continue;

		return vcenter;
	}

	return NULL;
}

static zbx_hv_t	*hv_get(zbx_vector_ptr_t *hvs, const char *uuid)
{
	zbx_hv_t	*hv;
	int		i;

	for (i = 0; i < hvs->values_num; i++)
	{
		hv = (zbx_hv_t *)hvs->values[i];

		if (0 == strcmp(hv->uuid, uuid))
			return hv;
	}

	return NULL;
}

static zbx_vm_t	*vm_get(zbx_vector_ptr_t *vms, const char *uuid)
{
	zbx_vm_t	*vm;
	int		i;

	for (i = 0; i < vms->values_num; i++)
	{
		vm = (zbx_vm_t *)vms->values[i];

		if (0 == strcmp(vm->uuid, uuid))
			return vm;
	}

	return NULL;
}

static zbx_cluster_t	*cluster_get(zbx_vector_ptr_t *clusters, const char *clusterid)
{
	zbx_cluster_t	*cluster;
	int		i;

	for (i = 0; i < clusters->values_num; i++)
	{
		cluster = (zbx_cluster_t *)clusters->values[i];

		if (0 == strcmp(cluster->id, clusterid))
			return cluster;
	}

	return NULL;
}

static zbx_cluster_t	*cluster_get_by_name(zbx_vector_ptr_t *clusters, const char *name)
{
	zbx_cluster_t	*cluster;
	int		i;

	for (i = 0; i < clusters->values_num; i++)
	{
		cluster = (zbx_cluster_t *)clusters->values[i];

		if (0 == strcmp(cluster->name, name))
			return cluster;
	}

	return NULL;
}

static int	vcenter_update(const char *url, const char *username, const char *password, char **error)
{
	const char		*__function_name = "vcenter_update";

	CURL			*easyhandle = NULL;
	int			o, i, j, err, ret = FAIL;
	zbx_vector_str_t	guesthvids, guestvmids, clusterids;
	struct curl_slist	*headers = NULL;
	zbx_vcenter_t		*vcenter;
	zbx_hv_t		*hv;
	zbx_vm_t		*vm;
	zbx_cluster_t		*cluster;
	char			*uuid, *name, *event_session = NULL, *clusters = NULL,
				xpath[MAX_STRING_LEN];
	zbx_vector_uint64_t	counterids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s' password:'%s'",
			__function_name, url, username, password);

	zbx_vector_uint64_create(&counterids);

	if (0 == vcenters_initialized)
	{
		zbx_vector_ptr_create(&vcenters);
		vcenters_initialized = 1;
	}

	if ('\0' == *url)
	{
		*error = zbx_strdup(*error, "URL cannot be empty.");
		goto out;
	}

	if ('\0' == *username)
	{
		*error = zbx_strdup(*error, "User name cannot be empty.");
		goto out;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
	{
		vcenter = zbx_malloc(NULL, sizeof(zbx_vcenter_t));
		vcenter->url = zbx_strdup(NULL, url);
		vcenter->username = zbx_strdup(NULL, username);
		vcenter->password = zbx_strdup(NULL, password);
		vcenter->events = NULL;
		vcenter->lastcheck = 0;
		zbx_vector_ptr_create(&vcenter->hvs);
		zbx_vector_ptr_create(&vcenter->clusters);
		memset(&vcenter->counters, 0, sizeof(zbx_counters_t));

		zbx_vector_ptr_append(&vcenters, vcenter);
	}
	else if (vcenter->lastcheck + ZBX_VMWARE_CACHE_TTL > time(NULL))
	{
		ret = SUCCEED;
		goto out;
	}

	if (NULL == (easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(*error, "Cannot init cURL library");
		goto out;
	}

	headers = curl_slist_append(headers, ZBX_XML_HEADER1);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);

	zbx_vector_str_create(&guesthvids);
	zbx_vector_str_create(&guestvmids);
	zbx_vector_str_create(&clusterids);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_HTTPHEADER, headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != vmware_authenticate(easyhandle, url, username, password, error, ZBX_VMWARE_FLAG_VCENTER))
		goto clean;

	if (SUCCEED != vmware_init_perf_counters(easyhandle, &vcenter->counters, error, ZBX_VMWARE_FLAG_VCENTER))
		goto clean;

	if (SUCCEED != vcenter_guesthvids_get(easyhandle, error))
		goto clean;

	read_xml_values(page.data, "//*[@type='HostSystem']", &guesthvids);

	for (i = 0; i < guesthvids.values_num; i++)
	{
		char	*hv_details = NULL;

		if (SUCCEED != vcenter_hv_data_get(easyhandle, guesthvids.values[i], &hv_details))
			continue;

		if (NULL == (uuid = read_xml_value(hv_details, ZBX_XPATH_LN2("hardware", "uuid"))))
		{
			zbx_free(hv_details);
			continue;
		}

		if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		{
			hv = zbx_malloc(NULL, sizeof(zbx_hv_t));
			hv->uuid = uuid;
			hv->id = NULL;
			hv->details = NULL;
			hv->clusterid = NULL;
			hv->stats = NULL;
			zbx_vector_ptr_create(&hv->vms);
			zbx_vector_ptr_create(&hv->datastores);

			zbx_vector_ptr_append(&vcenter->hvs, hv);
		}
		else
		{
			zbx_free(uuid);
			zbx_free(hv->id);
			zbx_free(hv->details);
			zbx_free(hv->clusterid);
		}

		hv->id = zbx_strdup(hv->id, guesthvids.values[i]);
		hv->details = hv_details;
		hv->clusterid = read_xml_value(hv_details, "//*[@type='ClusterComputeResource']");

		vmware_datastores_update(easyhandle, &hv->datastores, hv->details, ZBX_VMWARE_FLAG_VCENTER);

		if (SUCCEED != vmware_perf_counter_stats_get(easyhandle, hv->id, &vcenter->counters, &hv->stats,
				error, ZBX_VMWARE_FLAG_VCENTER))
		{
			goto clean;
		}

		read_xml_values(hv_details, "//*[@type='VirtualMachine']", &guestvmids);

		for (j = 0; j < guestvmids.values_num; j++)
		{
			if (SUCCEED != vmware_vm_status_ex_get(easyhandle, guestvmids.values[j],
					ZBX_VMWARE_FLAG_VCENTER))
			{
				continue;
			}

			if (NULL == (uuid = read_xml_value(page.data, ZBX_XPATH_LN1("uuid"))))
				continue;

			if (NULL == (vm = vm_get(&hv->vms, uuid)))
			{
				vm = zbx_malloc(NULL, sizeof(zbx_vm_t));
				vm->uuid = uuid;
				vm->id = NULL;
				vm->status_ex = NULL;
				vm->stats = NULL;
				zbx_vector_ptr_create(&vm->devs);

				zbx_vector_ptr_append(&hv->vms, vm);
			}
			else
			{
				zbx_free(uuid);
				zbx_free(vm->id);
				zbx_free(vm->status_ex);
			}

			vm->id = zbx_strdup(vm->id, guestvmids.values[j]);
			vm->status_ex = zbx_strdup(vm->status_ex, page.data);

			zbx_vector_ptr_clean(&vm->devs, (zbx_mem_free_func_t)zbx_dev_free);

			wmware_vm_populate_nic_devices(vm);

			if (SUCCEED != vmware_vm_perf_counter_stats_get(easyhandle, vm, &vcenter->counters, error,
					ZBX_VMWARE_FLAG_VCENTER))
			{
				goto clean;
			}

			wmware_vm_populate_disk_devices_by_counterid(vm, vcenter->counters.disk_read);
		}

		zbx_vector_str_clean(&guestvmids);
	}

	if (SUCCEED != vmware_event_session_get(easyhandle, &event_session, error, ZBX_VMWARE_FLAG_VCENTER))
		goto clean;

	if (SUCCEED != vmware_events_get(easyhandle, event_session, &vcenter->events, error, ZBX_VMWARE_FLAG_VCENTER))
		goto clean;

	/* clusters */

	if (SUCCEED != vcenter_clusters_get(easyhandle, &clusters, error))
		goto clean;

	read_xml_values(clusters, "//*[@type='ClusterComputeResource']", &clusterids);

	for (i = 0; i < clusterids.values_num; i++)
	{
		zbx_snprintf(xpath, sizeof(xpath), "//*[@type='ClusterComputeResource'][.='%s']"
				"/.." ZBX_XPATH_LN2("propSet", "val"), clusterids.values[i]);

		if (NULL == (name = read_xml_value(clusters, xpath)))
			continue;

		if (NULL == (cluster = cluster_get(&vcenter->clusters, clusterids.values[i])))
		{
			cluster = zbx_malloc(NULL, sizeof(zbx_cluster_t));
			cluster->id = zbx_strdup(NULL, clusterids.values[i]);
			cluster->name = NULL;
			cluster->status = NULL;
			cluster->vmlist = NULL;

			zbx_vector_ptr_append(&vcenter->clusters, cluster);
		}
		else
			zbx_free(cluster->name);

		cluster->name = name;

		if (SUCCEED != vcenter_cluster_status_get(easyhandle, clusterids.values[i], &cluster->status, error))
			goto clean;
	}

	vcenter->lastcheck = time(NULL);

	ret = SUCCEED;
clean:
	zbx_free(clusters);
	zbx_free(event_session);

	zbx_vector_str_clean(&clusterids);
	zbx_vector_str_destroy(&clusterids);

	zbx_vector_str_clean(&guesthvids);
	zbx_vector_str_destroy(&guesthvids);

	for (i = 0; i < guestvmids.values_num; i++)
		zbx_free(guestvmids.values[i]);
	zbx_vector_str_destroy(&guestvmids);

	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);

	zbx_vector_uint64_destroy(&counterids);
out:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare URL \"%s\" error: %s", url, *error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vcenter_vm_counter_get(zbx_vcenter_t *vcenter, const char *uuid, const char *instance,
		zbx_uint64_t counterid, int coeff, AGENT_RESULT *result)
{
	int		i, ret = SYSINFO_RET_FAIL;
	char		xpath[MAX_STRING_LEN], *value;
	zbx_hv_t	*hv;
	zbx_vm_t	*vm = NULL;
	zbx_dev_t	*dev;
	zbx_uint64_t	value_ui64;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != (vm = vm_get(&hv->vms, uuid)))
			break;
	}

	if (i == vcenter->hvs.values_num)
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_dev_t *)vm->devs.values[i];

		if (0 == strcmp(dev->instance, instance))
			break;
	}

	if (i == vm->devs.values_num)
		return SYSINFO_RET_FAIL;

	zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_LN3("value", "id", "counterId") "[.='" ZBX_FS_UI64 "']/.."
				ZBX_XPATH_LN("instance") "[.='%s']/../.." ZBX_XPATH_LN("value"), counterid, instance);

	if (NULL == (value = read_xml_value(vm->stats, xpath)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED == is_uint64(value, &value_ui64))
	{
		SET_UI64_RESULT(result, value_ui64 * coeff);

		ret = SYSINFO_RET_OK;
	}

	zbx_free(value);

	return ret;
}

static int	vsphere_hostdata_get(CURL *easyhandle, char **details, char **error)
{
#	define ZBX_POST_VSPHERE_HOSTDETAILS								\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrieveProperties>"								\
			"<ns0:_this type=\"PropertyCollector\">ha-property-collector</ns0:_this>"	\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>HostSystem</ns0:type>"				\
					"<ns0:all>false</ns0:all>"					\
					"<ns0:pathSet>summary</ns0:pathSet>"				\
					"<ns0:pathSet>datastore</ns0:pathSet>"				\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"HostSystem\">ha-host</ns0:obj>"		\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
		"</ns0:RetrieveProperties>"								\
		ZBX_POST_VSPHERE_FOOTER

	const char	*__function_name = "vsphere_hostdata_get";

	int		err, o, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_POSTFIELDS, ZBX_POST_VSPHERE_HOSTDETAILS)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = read_xml_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	*details = zbx_strdup(*details, page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static zbx_vsphere_t	*vsphere_get(const char *url, const char *username, const char *password)
{
	zbx_vsphere_t	*vsphere;
	int		i;

	for (i = 0; i < vspheres.values_num; i++)
	{
		vsphere = (zbx_vsphere_t *)vspheres.values[i];

		if (0 != strcmp(vsphere->url, url))
			continue;
		if (0 != strcmp(vsphere->username, username))
			continue;
		if (0 != strcmp(vsphere->password, password))
			continue;

		return vsphere;
	}

	return NULL;
}

static int	vsphere_update(const char *url, const char *username, const char *password, char **error)
{
	const char		*__function_name = "vsphere_update";

	CURL			*easyhandle = NULL;
	int			o, i, err, ret = FAIL;
	zbx_vector_str_t	guestvmids;
	struct curl_slist	*headers = NULL;
	zbx_vsphere_t		*vsphere;
	zbx_vm_t		*vm;
	char			*uuid, *event_session = NULL;
	zbx_vector_uint64_t	counterids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s' password:'%s'",
			__function_name, url, username, password);

	zbx_vector_uint64_create(&counterids);

	if (0 == vspheres_initialized)
	{
		zbx_vector_ptr_create(&vspheres);
		vspheres_initialized = 1;
	}

	if ('\0' == *url)
	{
		*error = zbx_strdup(*error, "URL cannot be empty.");
		goto out;
	}

	if ('\0' == *username)
	{
		*error = zbx_strdup(*error, "User name cannot be empty.");
		goto out;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
	{
		vsphere = zbx_malloc(NULL, sizeof(zbx_vsphere_t));
		vsphere->url = zbx_strdup(NULL, url);
		vsphere->username = zbx_strdup(NULL, username);
		vsphere->password = zbx_strdup(NULL, password);
		vsphere->events = NULL;
		vsphere->details = NULL;
		vsphere->stats = NULL;
		vsphere->lastcheck = 0;
		zbx_vector_ptr_create(&vsphere->vms);
		zbx_vector_ptr_create(&vsphere->datastores);
		memset(&vsphere->counters, 0, sizeof(zbx_counters_t));

		zbx_vector_ptr_append(&vspheres, vsphere);
	}
	else if (vsphere->lastcheck + ZBX_VMWARE_CACHE_TTL > time(NULL))
	{
		ret = SUCCEED;
		goto out;
	}

	if (NULL == (easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(*error, "Cannot init cURL library");
		goto out;
	}

	headers = curl_slist_append(headers, ZBX_XML_HEADER1);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);

	zbx_vector_str_create(&guestvmids);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, o = CURLOPT_HTTPHEADER, headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", o, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != vmware_authenticate(easyhandle, url, username, password, error, ZBX_VMWARE_FLAG_VSPHERE))
		goto clean;

	if (SUCCEED != vmware_init_perf_counters(easyhandle, &vsphere->counters, error, ZBX_VMWARE_FLAG_VSPHERE))
		goto clean;

	if (SUCCEED != vmware_hv_guestvmids_get(easyhandle, "ha-host", &guestvmids, error, ZBX_VMWARE_FLAG_VSPHERE))
		goto clean;

	if (SUCCEED != vsphere_hostdata_get(easyhandle, &vsphere->details, error))
		goto clean;

	vmware_datastores_update(easyhandle, &vsphere->datastores, vsphere->details, ZBX_VMWARE_FLAG_VSPHERE);

	if (SUCCEED != vmware_perf_counter_stats_get(easyhandle, "ha-host", &vsphere->counters, &vsphere->stats,
			error, ZBX_VMWARE_FLAG_VSPHERE))
	{
		goto clean;
	}

	for (i = 0; i < guestvmids.values_num; i++)
	{
		if (SUCCEED != vmware_vm_status_ex_get(easyhandle, guestvmids.values[i], ZBX_VMWARE_FLAG_VSPHERE))
			continue;

		if (NULL == (uuid = read_xml_value(page.data, ZBX_XPATH_LN1("uuid"))))
			continue;

		if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		{
			vm = zbx_malloc(NULL, sizeof(zbx_vm_t));
			vm->uuid = uuid;
			vm->id = NULL;
			vm->status_ex = NULL;
			vm->stats = NULL;
			zbx_vector_ptr_create(&vm->devs);

			zbx_vector_ptr_append(&vsphere->vms, vm);
		}
		else
		{
			zbx_free(uuid);
			zbx_free(vm->id);
			zbx_free(vm->status_ex);
		}

		vm->id = zbx_strdup(vm->id, guestvmids.values[i]);
		vm->status_ex = zbx_strdup(vm->status_ex, page.data);

		zbx_vector_ptr_clean(&vm->devs, (zbx_mem_free_func_t)zbx_dev_free);

		wmware_vm_populate_nic_devices(vm);

		if (SUCCEED != vmware_vm_perf_counter_stats_get(easyhandle, vm, &vsphere->counters, error,
				ZBX_VMWARE_FLAG_VSPHERE))
		{
			goto clean;
		}

		wmware_vm_populate_disk_devices_by_counterid(vm, vsphere->counters.disk_read);
	}

	if (SUCCEED != vmware_event_session_get(easyhandle, &event_session, error, ZBX_VMWARE_FLAG_VSPHERE))
		goto clean;

	if (SUCCEED != vmware_events_get(easyhandle, event_session, &vsphere->events, error, ZBX_VMWARE_FLAG_VSPHERE))
		goto clean;

	vsphere->lastcheck = time(NULL);

	ret = SUCCEED;
clean:
	zbx_free(event_session);

	zbx_vector_str_clean(&guestvmids);
	zbx_vector_str_destroy(&guestvmids);

	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);

	zbx_vector_uint64_destroy(&counterids);
out:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare URL \"%s\" error: %s", url, *error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vmware_counter_get(const char *stats, const char *instance, zbx_uint64_t counterid, int coeff,
		AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		xpath[MAX_STRING_LEN], *value;
	zbx_uint64_t	value_ui64;

	zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_LN3("value", "id", "counterId") "[.='" ZBX_FS_UI64 "']/.."
				ZBX_XPATH_LN("instance") "[.='%s']/../.." ZBX_XPATH_LN("value"), counterid, instance);

	if (NULL == (value = read_xml_value(stats, xpath)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED == is_uint64(value, &value_ui64))
	{
		SET_UI64_RESULT(result, value_ui64 * coeff);

		ret = SYSINFO_RET_OK;
	}

	zbx_free(value);

	return ret;
}

static int	vsphere_vm_counter_get(zbx_vsphere_t *vsphere, const char *uuid, const char *instance,
		zbx_uint64_t counterid, int coeff, AGENT_RESULT *result)
{
	zbx_vm_t	*vm = NULL;

	if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		return SYSINFO_RET_FAIL;

	return  vmware_counter_get(vm->stats, instance, counterid, coeff, result);
}

int	vmware_get_events(const char *events, zbx_uint64_t lastlogsize, AGENT_RESULT *result)
{
	zbx_vector_str_t	keys;
	zbx_uint64_t		key;
	char			*value, xpath[MAX_STRING_LEN];
	int			i;
	zbx_log_t		*log;
	struct tm		tm;
	time_t			t;

	zbx_vector_str_create(&keys);

	if (SUCCEED != read_xml_values(events, ZBX_XPATH_LN2("Event", "key"), &keys))
	{
		zbx_vector_str_destroy(&keys);
		return SYSINFO_RET_FAIL;
	}

	for (i = keys.values_num - 1; i >= 0; i--)
	{
		if (SUCCEED != is_uint64(keys.values[i], &key))
			continue;

		if (key <= lastlogsize)
			continue;

		/* value */

		zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_LN2("Event", "key") "[.='" ZBX_FS_UI64 "']/.."
				ZBX_XPATH_LN("fullFormattedMessage"), key);

		if (NULL == (value = read_xml_value(events, xpath)))
			continue;

		zbx_replace_invalid_utf8(value);
		log = add_log_result(result, value);
		log->logeventid = key;
		log->lastlogsize = key;

		zbx_free(value);

		/* timestamp */

		zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_LN2("Event", "key") "[.='" ZBX_FS_UI64 "']/.."
				ZBX_XPATH_LN("createdTime"), key);

		if (NULL == (value = read_xml_value(events, xpath)))
			continue;

		/* 2013-06-04T14:19:23.406298Z */
		if (6 == sscanf(value, "%d-%d-%dT%d:%d:%d.%*s", &tm.tm_year, &tm.tm_mon, &tm.tm_mday, &tm.tm_hour,
				&tm.tm_min, &tm.tm_sec))
		{
			tm.tm_year -= 1900;
			tm.tm_mon--;
			tm.tm_isdst = -1;

			if (0 < (t = mktime(&tm)))
				log->timestamp = (int)t - timezone;
		}

		zbx_free(value);
	}

	if (!ISSET_LOG(result))
		set_log_result_empty(result);

	zbx_vector_str_clean(&keys);
	zbx_vector_str_destroy(&keys);

	return SYSINFO_RET_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: check_vcenter_*                                                  *
 *                                                                            *
 * Purpose: vCenter related checks                                            *
 *                                                                            *
 ******************************************************************************/

static int	get_vcenter_vmstat(AGENT_REQUEST *request, const char *username, const char *password,
		const char *xpath, AGENT_RESULT *result)
{
	zbx_vcenter_t	*vcenter;
	zbx_vm_t	*vm = NULL;
	zbx_hv_t	*hv;
	char		*url, *uuid, *value, *error = NULL;
	int		i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != (vm = vm_get(&hv->vms, uuid)))
			break;
	}

	if (NULL == vm)
		return SYSINFO_RET_FAIL;

	if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, value);

	return SYSINFO_RET_OK;
}

#define ZBX_OPT_XPATH		0
#define ZBX_OPT_VM_NUM		1
#define ZBX_OPT_MEM_BALLOONED	2
static int	get_vcenter_hv_stat(AGENT_REQUEST *request, const char *username, const char *password,
		int opt, const char *xpath, AGENT_RESULT *result)
{
	zbx_vcenter_t	*vcenter;
	char		*url, *value, *uuid, *error = NULL;
	int		i;
	zbx_hv_t	*hv;
	zbx_vm_t	*vm;
	zbx_uint64_t	value_uint64, value_uint64_sum;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		return SYSINFO_RET_FAIL;

	switch (opt)
	{
		case ZBX_OPT_XPATH:
			if (NULL == (value = read_xml_value(hv->details, xpath)))
				return SYSINFO_RET_FAIL;

			SET_STR_RESULT(result, value);
			break;
		case ZBX_OPT_VM_NUM:
			SET_UI64_RESULT(result, hv->vms.values_num);
			break;
		case ZBX_OPT_MEM_BALLOONED:
			xpath = ZBX_XPATH_LN2("quickStats", "balloonedMemory");
			value_uint64_sum = 0;

			for (i = 0; i < hv->vms.values_num; i++)
			{
				vm = (zbx_vm_t *)hv->vms.values[i];

				if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
					continue;

				if (SUCCEED == is_uint64(value, &value_uint64))
					value_uint64_sum += value_uint64;

				zbx_free(value);
			}

			SET_UI64_RESULT(result, value_uint64_sum);
			break;
	}

	return SYSINFO_RET_OK;
}

int	check_vcenter_cluster_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json	j;
	int		i;
	char		*url, *error = NULL;
	zbx_vcenter_t	*vcenter = NULL;
	zbx_cluster_t	*cluster = NULL;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (vcenter = vcenter_get(url, username, password)))
	{
		for (i = 0; i < vcenter->clusters.values_num; i++)
		{
			cluster = (zbx_cluster_t *)vcenter->clusters.values[i];

			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#CLUSTER.ID}", cluster->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#CLUSTER.NAME}", cluster->name, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);
		}
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_cluster_status(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *name, *status, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_cluster_t	*cluster;
	int		ret;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	name = get_rparam(request, 1);

	if ('\0' == *name)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (cluster = cluster_get_by_name(&vcenter->clusters, name)))
		return SYSINFO_RET_FAIL;

	if (NULL == (status = read_xml_value(cluster->status, ZBX_XPATH_LN2("val", "overallStatus"))))
		return SYSINFO_RET_FAIL;

	ret = SYSINFO_RET_OK;

	if (0 == strcmp(status, "gray"))
		SET_UI64_RESULT(result, 0);
	else if (0 == strcmp(status, "green"))
		SET_UI64_RESULT(result, 1);
	else if (0 == strcmp(status, "yellow"))
		SET_UI64_RESULT(result, 2);
	else if (0 == strcmp(status, "red"))
		SET_UI64_RESULT(result, 3);
	else
		ret =  SYSINFO_RET_FAIL;

	zbx_free(status);

	return ret;
}

int	check_vcenter_eventlog(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vcenter_t	*vcenter;
	char		*url, *error = NULL;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	return vmware_get_events(vcenter->events, request->lastlogsize, result);
}

int	check_vcenter_hv_cluster_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	zbx_cluster_t	*cluster = NULL;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		return SYSINFO_RET_FAIL;

	if (NULL != hv->clusterid)
		cluster = cluster_get(&vcenter->clusters, hv->clusterid);

	SET_STR_RESULT(result, zbx_strdup(NULL, NULL != cluster ? cluster->name : ""));

	return SYSINFO_RET_OK;
}

int	check_vcenter_hv_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH,
			ZBX_XPATH_LN2("quickStats", "overallCpuUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	return ret;
}

int	check_vcenter_hv_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json	j;
	int		i;
	char		*url, *name, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (vcenter = vcenter_get(url, username, password)))
	{
		for (i = 0; i < vcenter->hvs.values_num; i++)
		{
			zbx_cluster_t	*cluster = NULL;

			hv = (zbx_hv_t *)vcenter->hvs.values[i];

			if (NULL == (name = read_xml_value(hv->details, ZBX_XPATH_LN2("config", "name"))))
				continue;

			if (NULL != hv->clusterid)
				cluster = cluster_get(&vcenter->clusters, hv->clusterid);

			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#HV.UUID}", hv->uuid, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#HV.ID}", hv->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#HV.NAME}", name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#CLUSTER.NAME}",
					(NULL != cluster ? cluster->name : ""), ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);

			zbx_free(name);
		}
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_hv_fullname(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("product", "fullName"),
			result);
}

int	check_vcenter_hv_hw_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "numCpuCores"),
			result);
}

int	check_vcenter_hv_hw_cpu_freq(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "cpuMhz"),
			result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	return ret;
}

int	check_vcenter_hv_hw_cpu_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "cpuModel"),
			result);
}

int	check_vcenter_hv_hw_cpu_threads(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH,
			ZBX_XPATH_LN2("hardware", "numCpuThreads"), result);
}

int	check_vcenter_hv_hw_memory(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "memorySize"),
			result);
}

int	check_vcenter_hv_hw_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "model"),
			result);
}

int	check_vcenter_hv_hw_uuid(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "uuid"),
			result);
}

int	check_vcenter_hv_hw_vendor(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "vendor"),
			result);
}

int	check_vcenter_hv_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_hv_stat(request, username, password, ZBX_OPT_MEM_BALLOONED, NULL, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_hv_memory_used(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH,
			ZBX_XPATH_LN2("quickStats", "overallMemoryUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_hv_status(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("val", "overallStatus"),
			result);

	if (SYSINFO_RET_OK == ret)
	{
		if (0 == strcmp(result->str, "gray"))
			SET_UI64_RESULT(result, 0);
		else if (0 == strcmp(result->str, "green"))
			SET_UI64_RESULT(result, 1);
		else if (0 == strcmp(result->str, "yellow"))
			SET_UI64_RESULT(result, 2);
		else if (0 == strcmp(result->str, "red"))
			SET_UI64_RESULT(result, 3);
		else
			ret = SYSINFO_RET_FAIL;

		UNSET_STR_RESULT(result);
	}

	return ret;
}

int	check_vcenter_hv_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("quickStats", "uptime"),
			result);
}

int	check_vcenter_hv_version(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("product", "version"),
			result);
}

int	check_vcenter_hv_vm_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_hv_stat(request, username, password, ZBX_OPT_VM_NUM, NULL, result);
}

int	check_vcenter_hv_network_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *uuid, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;

	if (2 > request->nparam || request->nparam > 3)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "bps"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		return SYSINFO_RET_FAIL;

	return vmware_counter_get(hv->stats, "", vcenter->counters.nic_received, ZBX_KIBIBYTE, result);
}

int	check_vcenter_hv_network_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *uuid, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;

	if (2 > request->nparam || request->nparam > 3)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "bps"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		return SYSINFO_RET_FAIL;

	return vmware_counter_get(hv->stats, "", vcenter->counters.nic_transmitted, ZBX_KIBIBYTE, result);
}

int	check_vcenter_hv_datastore_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	struct zbx_json	j;
	int		i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		return SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < hv->datastores.values_num; i++)
	{
		zbx_datastore_t	*datastore = hv->datastores.values[i];

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#DATASTORE}", datastore->name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_hv_datastore_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *uuid, *name, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	int		i;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	name = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "latency"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < hv->datastores.values_num; i++)
	{
		zbx_datastore_t	*datastore = hv->datastores.values[i];

		if (0 == strcmp(name, datastore->name))
		{
			if (NULL == datastore->uuid)
				break;

			return vmware_counter_get(hv->stats, datastore->uuid,
					vcenter->counters.datastore_read_latency, 1, result);
		}
	}

	return SYSINFO_RET_FAIL;
}

int	check_vcenter_hv_datastore_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *uuid, *name, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	int		i;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	name = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "latency"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (hv = hv_get(&vcenter->hvs, uuid)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < hv->datastores.values_num; i++)
	{
		zbx_datastore_t	*datastore = hv->datastores.values[i];

		if (0 == strcmp(name, datastore->name))
		{
			if (NULL == datastore->uuid)
				break;

			return vmware_counter_get(hv->stats, datastore->uuid,
					vcenter->counters.datastore_write_latency, 1, result);
		}
	}

	return SYSINFO_RET_FAIL;
}

int	check_vcenter_vm_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("config", "numCpu"), result);
}

int	check_vcenter_vm_cluster_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int		i;
	char		*url, *uuid, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	zbx_cluster_t	*cluster = NULL;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != vm_get(&hv->vms, uuid))
			break;
	}

	if (i == vcenter->hvs.values_num)
		return SYSINFO_RET_FAIL;

	if (NULL != hv->clusterid)
		cluster = cluster_get(&vcenter->clusters, hv->clusterid);

	SET_STR_RESULT(result, zbx_strdup(NULL, NULL != cluster ? cluster->name : ""));

	return SYSINFO_RET_OK;
}

int	check_vcenter_vm_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "overallCpuUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	return ret;
}

int	check_vcenter_vm_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json	j;
	int		i, k;
	char		*url, *name, *host, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	zbx_vm_t	*vm;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (vcenter = vcenter_get(url, username, password)))
	{
		for (i = 0; i < vcenter->hvs.values_num; i++)
		{
			zbx_cluster_t	*cluster = NULL;

			hv = (zbx_hv_t *)vcenter->hvs.values[i];

			if (NULL != hv->clusterid)
				cluster = cluster_get(&vcenter->clusters, hv->clusterid);

			for (k = 0; k < hv->vms.values_num; k++)
			{
				vm = (zbx_vm_t *)hv->vms.values[k];

				if (NULL == (name = read_xml_value(vm->status_ex, ZBX_XPATH_LN2("config", "name"))))
					continue;

				if (NULL == (host = read_xml_value(hv->details, ZBX_XPATH_LN2("config", "name"))))
				{
					zbx_free(name);
					continue;
				}

				zbx_json_addobject(&j, NULL);
				zbx_json_addstring(&j, "{#VM.UUID}", vm->uuid, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&j, "{#VM.ID}", vm->id, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&j, "{#VM.NAME}", name, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&j, "{#HV.NAME}", host, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&j, "{#CLUSTER.NAME}",
						(NULL != cluster ? cluster->name : ""), ZBX_JSON_TYPE_STRING);
				zbx_json_close(&j);

				zbx_free(host);
				zbx_free(name);
			}
		}
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_vm_hv_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	char		*url, *uuid, *error = NULL, *name;
	int		i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != vm_get(&hv->vms, uuid))
			break;
	}

	if (i != vcenter->hvs.values_num)
	{
		name = read_xml_value(hv->details, ZBX_XPATH_LN2("config", "name"));

		SET_STR_RESULT(result, name);
		ret = SYSINFO_RET_OK;
	}

	return ret;
}

int	check_vcenter_vm_memory_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("config", "memorySizeMB"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "balloonedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_memory_size_compressed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "compressedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_memory_size_swapped(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "swappedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_memory_size_usage_guest(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "guestMemoryUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_memory_size_usage_host(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "hostMemoryUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_memory_size_private(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "privateMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_memory_size_shared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "sharedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vcenter_vm_powerstate(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("runtime", "powerState"), result);

	if (SYSINFO_RET_OK == ret)
		ret = vmware_set_powerstate_result(result);

	return ret;
}

int	check_vcenter_vm_net_if_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		j;
	zbx_vcenter_t		*vcenter;
	zbx_hv_t		*hv;
	zbx_vm_t		*vm = NULL;
	zbx_dev_t		*dev;
	char			*url, *uuid, *error = NULL;
	int			i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != (vm = vm_get(&hv->vms, uuid)))
			break;
	}

	if (NULL == vm)
		return SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_dev_t *)vm->devs.values[i];

		if (ZBX_DEV_TYPE_NIC != dev->type)
			continue;

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", dev->instance, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_vm_net_if_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_uint64_t	counterid;
	int 		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vcenter->counters.nic_received;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "pps"))
	{
		counterid = vcenter->counters.nic_packets_rx;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vcenter_vm_counter_get(vcenter, uuid, instance, counterid, coeff, result);
}

int	check_vcenter_vm_net_if_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_uint64_t	counterid;
	int		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vcenter->counters.nic_transmitted;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "pps"))
	{
		counterid = vcenter->counters.nic_packets_tx;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vcenter_vm_counter_get(vcenter, uuid, instance, counterid, coeff, result);
}

int	check_vcenter_vm_storage_committed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("storage", "committed"), result);
}

int	check_vcenter_vm_storage_unshared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("storage", "unshared"), result);
}

int	check_vcenter_vm_storage_uncommitted(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("storage", "uncommitted"), result);
}

int	check_vcenter_vm_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "uptimeSeconds"), result);
}

int	check_vcenter_vm_vfs_dev_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		j;
	zbx_vcenter_t		*vcenter;
	zbx_hv_t		*hv;
	zbx_vm_t		*vm = NULL;
	zbx_dev_t		*dev;
	char			*url, *uuid, *error = NULL;
	int			i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != (vm = vm_get(&hv->vms, uuid)))
			break;
	}

	if (NULL == vm)
		return SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_dev_t *)vm->devs.values[i];

		if (ZBX_DEV_TYPE_DISK != dev->type)
			continue;

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#DISKNAME}", dev->instance, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_vm_vfs_dev_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_uint64_t	counterid;
	int		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vcenter->counters.disk_read;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "ops"))
	{
		counterid = vcenter->counters.disk_number_read_averaged;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vcenter_vm_counter_get(vcenter, uuid, instance, counterid, coeff, result);
}

int	check_vcenter_vm_vfs_dev_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vcenter_t	*vcenter;
	zbx_uint64_t	counterid;
	int		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vcenter->counters.disk_write;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "ops"))
	{
		counterid = vcenter->counters.disk_number_write_averaged;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vcenter_vm_counter_get(vcenter, uuid, instance, counterid, coeff, result);
}

int	check_vcenter_vm_vfs_fs_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		j;
	zbx_vcenter_t		*vcenter;
	zbx_hv_t		*hv;
	zbx_vm_t		*vm = NULL;
	char			*url, *uuid, *error = NULL;
	zbx_vector_str_t	disks;
	int			i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != (vm = vm_get(&hv->vms, uuid)))
			break;
	}

	if (NULL == vm)
		return SYSINFO_RET_FAIL;

	zbx_vector_str_create(&disks);

	read_xml_values(vm->status_ex, ZBX_XPATH_LN2("disk", "diskPath"), &disks);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < disks.values_num; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#FSNAME}", disks.values[i], ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	zbx_vector_str_clean(&disks);
	zbx_vector_str_destroy(&disks);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_vm_vfs_fs_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vcenter_t	*vcenter;
	zbx_hv_t	*hv;
	zbx_vm_t	*vm = NULL;
	char		*url, *uuid, *fsname, *mode, *value, *error = NULL, xpath[MAX_STRING_LEN];
	zbx_uint64_t	value_total, value_free;
	int		i;

	if (4 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	fsname = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vcenter->hvs.values_num; i++)
	{
		hv = (zbx_hv_t *)vcenter->hvs.values[i];

		if (NULL != (vm = vm_get(&hv->vms, uuid)))
			break;
	}

	if (NULL == vm)
		return SYSINFO_RET_FAIL;

	zbx_snprintf(xpath, sizeof(xpath),
			ZBX_XPATH_LN2("disk", "diskPath") "[.='%s']/.." ZBX_XPATH_LN("capacity"), fsname);

	if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != is_uint64(value, &value_total))
	{
		zbx_free(value);
		return SYSINFO_RET_FAIL;
	}

	zbx_free(value);

	zbx_snprintf(xpath, sizeof(xpath),
			ZBX_XPATH_LN2("disk", "diskPath") "[.='%s']/.." ZBX_XPATH_LN("freeSpace"), fsname);

	if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != is_uint64(value, &value_free))
	{
		zbx_free(value);
		return SYSINFO_RET_FAIL;
	}

	zbx_free(value);

	if ('\0' == *mode || 0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, value_total);
	else if (0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, value_free);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, value_total - value_free);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, (0 != value_total ? (double)(100.0 * value_free) / value_total : 0));
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, 100.0 - (0 != value_total ? (double)(100.0 * value_free) / value_total : 0));
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: check_vsphere_*                                                  *
 *                                                                            *
 * Purpose: vSphere related checks                                            *
 *                                                                            *
 ******************************************************************************/
static int	get_vsphere_stat(AGENT_REQUEST *request, const char *username, const char *password,
		int opt, const char *xpath, AGENT_RESULT *result)
{
	zbx_vsphere_t	*vsphere;
	char		*url, *value, *error = NULL;
	int		i;
	zbx_vm_t	*vm;
	zbx_uint64_t	value_uint64, value_uint64_sum;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	switch (opt)
	{
		case ZBX_OPT_XPATH:
			if (NULL == (value = read_xml_value(vsphere->details, xpath)))
				return SYSINFO_RET_FAIL;

			SET_STR_RESULT(result, value);
			break;
		case ZBX_OPT_VM_NUM:
			SET_UI64_RESULT(result, vsphere->vms.values_num);
			break;
		case ZBX_OPT_MEM_BALLOONED:
			xpath = ZBX_XPATH_LN2("quickStats", "balloonedMemory");
			value_uint64_sum = 0;

			for (i = 0; i < vsphere->vms.values_num; i++)
			{
				vm = (zbx_vm_t *)vsphere->vms.values[i];

				if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
					continue;

				if (SUCCEED == is_uint64(value, &value_uint64))
					value_uint64_sum += value_uint64;

				zbx_free(value);
			}

			SET_UI64_RESULT(result, value_uint64_sum);
			break;
	}

	return SYSINFO_RET_OK;
}

static int	get_vsphere_vmstat(AGENT_REQUEST *request, const char *username, const char *password,
		const char *xpath, AGENT_RESULT *result)
{
	zbx_vsphere_t	*vsphere;
	zbx_vm_t	*vm;
	char		*url, *uuid, *value, *error = NULL;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		return SYSINFO_RET_FAIL;

	if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	check_vsphere_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_stat(request, username, password, ZBX_OPT_XPATH,
			ZBX_XPATH_LN2("quickStats", "overallCpuUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	return ret;
}

int	check_vsphere_eventlog(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vsphere_t	*vsphere;
	char		*url, *error = NULL;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	return vmware_get_events(vsphere->events, request->lastlogsize, result);
}

int	check_vsphere_fullname(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("product", "fullName"),
			result);
}

int	check_vsphere_hw_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "numCpuCores"),
			result);
}

int	check_vsphere_hw_cpu_freq(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "cpuMhz"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	return ret;
}

int	check_vsphere_hw_cpu_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "cpuModel"),
			result);
}

int	check_vsphere_hw_cpu_threads(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "numCpuThreads"),
			result);
}

int	check_vsphere_hw_memory(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "memorySize"),
			result);
}

int	check_vsphere_hw_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "model"), result);
}

int	check_vsphere_hw_uuid(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "uuid"), result);
}

int	check_vsphere_hw_vendor(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("hardware", "vendor"),
			result);
}

int	check_vsphere_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_stat(request, username, password, ZBX_OPT_MEM_BALLOONED, NULL, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_memory_used(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_stat(request, username, password, ZBX_OPT_XPATH,
			ZBX_XPATH_LN2("quickStats", "overallMemoryUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_status(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("val", "overallStatus"),
			result);

	if (SYSINFO_RET_OK == ret && NULL != GET_STR_RESULT(result))
	{
		if (0 == strcmp(result->str, "gray"))
			SET_UI64_RESULT(result, 0);
		else if (0 == strcmp(result->str, "green"))
			SET_UI64_RESULT(result, 1);
		else if (0 == strcmp(result->str, "yellow"))
			SET_UI64_RESULT(result, 2);
		else if (0 == strcmp(result->str, "red"))
			SET_UI64_RESULT(result, 3);
		else
			ret = SYSINFO_RET_FAIL;

		UNSET_STR_RESULT(result);
	}

	return ret;
}

int	check_vsphere_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("quickStats", "uptime"),
			result);
}

int	check_vsphere_version(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_XPATH, ZBX_XPATH_LN2("product", "version"),
			result);
}

int	check_vsphere_vm_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_stat(request, username, password, ZBX_OPT_VM_NUM, NULL, result);
}

int	check_vsphere_vm_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("config", "numCpu"), result);
}

int	check_vsphere_vm_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "overallCpuUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	return ret;
}

int	check_vsphere_hv_network_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *error = NULL;
	zbx_vsphere_t	*vsphere;

	if (1 > request->nparam || request->nparam > 2)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "bps"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	return vmware_counter_get(vsphere->stats, "", vsphere->counters.nic_received, ZBX_KIBIBYTE, result);
}

int	check_vsphere_hv_network_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *error = NULL;
	zbx_vsphere_t	*vsphere;

	if (1 > request->nparam || request->nparam > 2)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "bps"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	return vmware_counter_get(vsphere->stats, "", vsphere->counters.nic_transmitted, ZBX_KIBIBYTE, result);
}

int	check_vsphere_hv_datastore_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		j;
	zbx_vsphere_t		*vsphere;
	char			*url, *error = NULL;
	int			i;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vsphere->datastores.values_num; i++)
	{
		zbx_datastore_t	*datastore = vsphere->datastores.values[i];

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#DATASTORE}", datastore->name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vsphere_hv_datastore_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *name, *error = NULL;
	zbx_vsphere_t	*vsphere;
	int		i;

	if (2 > request->nparam || request->nparam > 3)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	name = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "latency"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vsphere->datastores.values_num; i++)
	{
		zbx_datastore_t	*datastore = vsphere->datastores.values[i];

		if (0 == strcmp(name, datastore->name))
		{
			if (NULL == datastore->uuid)
				break;

			return vmware_counter_get(vsphere->stats, datastore->uuid,
					vsphere->counters.datastore_read_latency, 1, result);
		}
	}

	return SYSINFO_RET_FAIL;
}

int	check_vsphere_hv_datastore_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *mode, *name, *error = NULL;
	zbx_vsphere_t	*vsphere;
	int		i;

	if (2 > request->nparam || request->nparam > 3)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	name = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "latency"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	for (i = 0; i < vsphere->datastores.values_num; i++)
	{
		zbx_datastore_t	*datastore = vsphere->datastores.values[i];

		if (0 == strcmp(name, datastore->name))
		{
			if (NULL == datastore->uuid)
				break;

			return vmware_counter_get(vsphere->stats, datastore->uuid,
					vsphere->counters.datastore_write_latency, 1, result);
		}
	}

	return SYSINFO_RET_FAIL;
}

int	check_vsphere_vm_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json	j;
	int		i;
	char		*url, *name, *error = NULL;
	zbx_vsphere_t	*vsphere = NULL;
	zbx_vm_t	*vm = NULL;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (vsphere = vsphere_get(url, username, password)))
	{
		for (i = 0; i < vsphere->vms.values_num; i++)
		{
			vm = (zbx_vm_t *)vsphere->vms.values[i];

			if (NULL == (name = read_xml_value(vm->status_ex, ZBX_XPATH_LN2("config", "name"))))
				continue;

			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#VM.UUID}", vm->uuid, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#VM.ID}", vm->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#VM.NAME}", name, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);

			zbx_free(name);
		}
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vsphere_vm_hv_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("runtime", "host"), result);
}

int	check_vsphere_vm_memory_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("config", "memorySizeMB"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "balloonedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_memory_size_compressed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "compressedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_memory_size_swapped(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "swappedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_memory_size_usage_guest(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "guestMemoryUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_memory_size_usage_host(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "hostMemoryUsage"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_memory_size_private(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "privateMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_memory_size_shared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "sharedMemory"), result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	return ret;
}

int	check_vsphere_vm_powerstate(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	ret = get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("runtime", "powerState"), result);

	if (SYSINFO_RET_OK == ret)
		ret = vmware_set_powerstate_result(result);

	return ret;
}

int	check_vsphere_vm_net_if_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		j;
	zbx_vsphere_t		*vsphere;
	zbx_vm_t		*vm = NULL;
	zbx_dev_t		*dev;
	char			*url, *uuid, *error = NULL;
	int			i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		return SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_dev_t *)vm->devs.values[i];

		if (ZBX_DEV_TYPE_NIC != dev->type)
			continue;

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", dev->instance, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vsphere_vm_net_if_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vsphere_t	*vsphere;
	zbx_uint64_t	counterid;
	int		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vsphere->counters.nic_received;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "pps"))
	{
		counterid = vsphere->counters.nic_packets_rx;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vsphere_vm_counter_get(vsphere, uuid, instance, counterid, coeff, result);
}

int	check_vsphere_vm_net_if_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vsphere_t	*vsphere;
	zbx_uint64_t	counterid;
	int		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vsphere->counters.nic_transmitted;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "pps"))
	{
		counterid = vsphere->counters.nic_packets_tx;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vsphere_vm_counter_get(vsphere, uuid, instance, counterid, coeff, result);
}

int	check_vsphere_vm_storage_committed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("storage", "committed"), result);
}

int	check_vsphere_vm_storage_unshared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("storage", "unshared"), result);
}

int	check_vsphere_vm_storage_uncommitted(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("storage", "uncommitted"), result);
}

int	check_vsphere_vm_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, username, password, ZBX_XPATH_LN2("quickStats", "uptimeSeconds"), result);
}

int	check_vsphere_vm_vfs_dev_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		j;
	zbx_vsphere_t		*vsphere;
	zbx_vm_t		*vm = NULL;
	zbx_dev_t		*dev;
	char			*url, *uuid, *error = NULL;
	int			i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		return SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_dev_t *)vm->devs.values[i];

		if (ZBX_DEV_TYPE_DISK != dev->type)
			continue;

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#DISKNAME}", dev->instance, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vsphere_vm_vfs_dev_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vsphere_t	*vsphere;
	zbx_uint64_t	counterid;
	int		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vsphere->counters.disk_read;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "ops"))
	{
		counterid = vsphere->counters.disk_number_read_averaged;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vsphere_vm_counter_get(vsphere, uuid, instance, counterid, coeff, result);
}

int	check_vsphere_vm_vfs_dev_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char		*url, *uuid, *instance, *mode, *error = NULL;
	zbx_vsphere_t	*vsphere;
	zbx_uint64_t	counterid;
	int		coeff;

	if (3 > request->nparam || request->nparam > 4)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid || '\0' == *instance)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counterid = vsphere->counters.disk_write;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "ops"))
	{
		counterid = vsphere->counters.disk_number_write_averaged;
		coeff = 1;
	}
	else
		return SYSINFO_RET_FAIL;

	return vsphere_vm_counter_get(vsphere, uuid, instance, counterid, coeff, result);
}

int	check_vsphere_vm_vfs_fs_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		j;
	zbx_vsphere_t		*vsphere;
	zbx_vm_t		*vm;
	char			*url, *uuid, *error = NULL;
	zbx_vector_str_t	disks;
	int			i;

	if (2 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		return SYSINFO_RET_FAIL;

	zbx_vector_str_create(&disks);

	read_xml_values(vm->status_ex, ZBX_XPATH_LN2("disk", "diskPath"), &disks);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < disks.values_num; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#FSNAME}", disks.values[i], ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	zbx_vector_str_clean(&disks);
	zbx_vector_str_destroy(&disks);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vsphere_vm_vfs_fs_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vsphere_t	*vsphere;
	zbx_vm_t	*vm;
	char		*url, *uuid, *fsname, *mode, *value, *error = NULL, xpath[MAX_STRING_LEN];
	zbx_uint64_t	value_total, value_free;

	if (4 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	fsname = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		return SYSINFO_RET_FAIL;

	zbx_snprintf(xpath, sizeof(xpath),
			ZBX_XPATH_LN2("disk", "diskPath") "[.='%s']/.." ZBX_XPATH_LN("capacity"), fsname);

	if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != is_uint64(value, &value_total))
	{
		zbx_free(value);
		return SYSINFO_RET_FAIL;
	}

	zbx_free(value);

	zbx_snprintf(xpath, sizeof(xpath),
			ZBX_XPATH_LN2("disk", "diskPath") "[.='%s']/.." ZBX_XPATH_LN("freeSpace"), fsname);

	if (NULL == (value = read_xml_value(vm->status_ex, xpath)))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != is_uint64(value, &value_free))
	{
		zbx_free(value);
		return SYSINFO_RET_FAIL;
	}

	zbx_free(value);

	if ('\0' == *mode || 0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, value_total);
	else if (0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, value_free);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, value_total - value_free);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, (0 != value_total ? (double)(100.0 * value_free) / value_total : 0));
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, 100.0 - (0 != value_total ? (double)(100.0 * value_free) / value_total : 0));
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}
#endif
