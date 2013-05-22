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

#define ZBX_XPATH_LN(LN)	"//*[local-name()='" LN "']"
#define ZBX_XPATH_LN2(LN1, LN2)	ZBX_XPATH_LN(LN1) "/*[local-name()='" LN2 "']"

#define	ZBX_XML_HEADER1		"Soapaction:urn:vim25/4.1"
#define ZBX_XML_HEADER2		"Content-Type:text/xml; charset=utf-8"

#define ZBX_VMWARE_CACHE_TTL	SEC_PER_MIN

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
ZBX_HTTPPAGE;

static int		page_initialized = 0;
static ZBX_HTTPPAGE	page;

static int		vcenters_initialized = 0;
static zbx_vector_ptr_t	vcenters;

static int		vspheres_initialized = 0;
static zbx_vector_ptr_t	vspheres;

typedef struct
{
	char			*url;
	char			*username;
	char			*password;
	zbx_vector_ptr_t	vms;
	int			lastcheck;
}
zbx_vcenter_t;

typedef struct
{
	char			*url;
	char			*username;
	char			*password;
	char			*details;
	zbx_vector_ptr_t	vms;
	int			lastcheck;
}
zbx_vsphere_t;

typedef struct
{
	char			*uuid;
	char			*details;
}
zbx_vm_t;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	/* first piece of data */
	if (0 == page_initialized)
	{
		page.alloc = 0;
		page.data = NULL;

		page_initialized = 1;
	}

	zbx_strncpy_alloc(&page.data, &page.alloc, &page.offset, ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	return size * nmemb;
}

static int	vcenter_authenticate(CURL *easyhandle, const char *url, const char *username, const char *password,
		char **error)
{
#	define ZBX_POST_VCENTER_AUTH									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"						\
		"<SOAP-ENV:Envelope"									\
			" xmlns:SOAP-ENC=\"http://schemas.xmlsoap.org/soap/encoding/\""			\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\""			\
			" xmlns:ZSI=\"http://www.zolera.com/schemas/ZSI/\""				\
			" xmlns:soapenc=\"http://schemas.xmlsoap.org/soap/encoding/\""			\
			" xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""			\
			" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\""				\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">"			\
			"<SOAP-ENV:Header></SOAP-ENV:Header>"						\
			"<SOAP-ENV:Body xmlns:ns1=\"urn:vim25\">"					\
				"<ns1:Login xsi:type=\"ns1:LoginRequestType\">"				\
					"<ns1:_this type=\"SessionManager\">SessionManager</ns1:_this>"	\
					"<ns1:userName>%s</ns1:userName>"				\
					"<ns1:password>%s</ns1:password>"				\
				"</ns1:Login>"								\
			"</SOAP-ENV:Body>"								\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vcenter_authenticate";
	int		err, opt, timeout = 10, ret = FAIL;
	char		postdata[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s'", __function_name, url, username);
	zabbix_log(LOG_LEVEL_DEBUG, "PAGE.REQUEST: %s", ZBX_POST_VCENTER_AUTH);

	zbx_snprintf(postdata, sizeof(postdata), ZBX_POST_VCENTER_AUTH, username, password);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, url)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, postdata)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "PAGE.DATA: %s", page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
static char	*read_xml_value(const char *data, char *xpath)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	char		*value;

	if (NULL == data)
		return NULL;

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
		return NULL;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)xpath, xpathCtx)))
	{
		xmlFreeDoc(doc);
		xmlCleanupParser();
		return NULL;
	}

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		xmlXPathFreeObject(xpathObj);
		xmlFreeDoc(doc);
		xmlCleanupParser();
		return NULL;
	}

	nodeset = xpathObj->nodesetval;

	val = xmlNodeListGetString(doc, nodeset->nodeTab[0]->xmlChildrenNode, 1);
	value = zbx_strdup(NULL, (char *)val);
	xmlFree(val);

	xmlXPathFreeObject(xpathObj);
	xmlFreeDoc(doc);
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
static int	read_xml_values(const char *data, char *xpath, zbx_vector_str_t *values)
{
	const char	*__function_name = "read_xml_values";

	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	int		i;

	if (NULL == data)
		return FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() data:'%s'", __function_name, data);

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
		return FAIL;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)xpath, xpathCtx)))
	{
		xmlCleanupParser();
		return FAIL;
	}

	if (xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		xmlXPathFreeObject(xpathObj);
		xmlCleanupParser();
		return FAIL;
	}

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		val = xmlNodeListGetString(doc, nodeset->nodeTab[i]->xmlChildrenNode, 1);
		zbx_vector_str_append(values, zbx_strdup(NULL, (char *)val));
		xmlFree(val);
	}

	xmlXPathFreeObject(xpathObj);
	xmlCleanupParser();

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: vcenter_guestvmids_get                                           *
 *                                                                            *
 * Purpose: populate array of guest VMs                                       *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL easy handle                             *
 *             guestvmids - [OUT] list of guest VMs IDs                       *
 *                                                                            *
 * Return: Upon successful completion the function return SUCCEED.            *
 *         Otherwise, FAIL is returned.                                       *
 *                                                                            *
 * Comments: auxiliary function for vcenter_guestvmids_get()                  *
 *                                                                            *
 ******************************************************************************/
static int	vcenter_guestvmids_get(CURL *easyhandle, zbx_vector_str_t *guestvmids, char **error)
{
#	define ZBX_POST_VCENTER_VMLIST									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"						\
		"<SOAP-ENV:Envelope"									\
		" xmlns:SOAP-ENC=\"http://schemas.xmlsoap.org/soap/encoding/\""				\
		" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
		" xmlns:ZSI=\"http://www.zolera.com/schemas/ZSI/\""					\
		" xmlns:soapenc=\"http://schemas.xmlsoap.org/soap/encoding/\""				\
		" xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
		" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\""					\
		" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">"				\
		"<SOAP-ENV:Header/>"									\
		"<SOAP-ENV:Body xmlns:ns1=\"urn:vim25\">"						\
			"<ns1:RetrievePropertiesEx xsi:type=\"ns1:RetrievePropertiesExRequestType\">"	\
				"<ns1:_this type=\"PropertyCollector\">propertyCollector</ns1:_this>"	\
				"<ns1:specSet>"								\
					"<ns1:propSet>"							\
						"<ns1:type>VirtualMachine</ns1:type>"			\
						"<ns1:pathSet>config.files.vmPathName</ns1:pathSet>"	\
					"</ns1:propSet>"						\
					"<ns1:objectSet>"						\
						"<ns1:obj type=\"Folder\">group-d1</ns1:obj>"		\
						"<ns1:skip>false</ns1:skip>"				\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>visitFolders</ns1:name>"		\
							"<ns1:type>Folder</ns1:type>"			\
							"<ns1:path>childEntity</ns1:path>"		\
							"<ns1:skip>false</ns1:skip>"			\
							"<ns1:selectSet>"				\
								"<ns1:name>visitFolders</ns1:name>"	\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>dcToHf</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>dcToVmf</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>crToH</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>crToRp</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>dcToDs</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>hToVm</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>rpToVm</ns1:name>"		\
							"</ns1:selectSet>"				\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>dcToVmf</ns1:name>"			\
							"<ns1:type>Datacenter</ns1:type>"		\
							"<ns1:path>vmFolder</ns1:path>"			\
							"<ns1:skip>false</ns1:skip>"			\
							"<ns1:selectSet>"				\
								"<ns1:name>visitFolders</ns1:name>"	\
							"</ns1:selectSet>"				\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>dcToDs</ns1:name>"			\
							"<ns1:type>Datacenter</ns1:type>"		\
							"<ns1:path>datastore</ns1:path>"		\
							"<ns1:skip>false</ns1:skip>"			\
							"<ns1:selectSet>"				\
								"<ns1:name>visitFolders</ns1:name>"	\
							"</ns1:selectSet>"				\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>dcToHf</ns1:name>"			\
							"<ns1:type>Datacenter</ns1:type>"		\
							"<ns1:path>hostFolder</ns1:path>"		\
							"<ns1:skip>false</ns1:skip>"			\
							"<ns1:selectSet>"				\
								"<ns1:name>visitFolders</ns1:name>"	\
							"</ns1:selectSet>"				\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>crToH</ns1:name>"			\
							"<ns1:type>ComputeResource</ns1:type>"		\
							"<ns1:path>host</ns1:path>"			\
							"<ns1:skip>false</ns1:skip>"			\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>crToRp</ns1:name>"			\
							"<ns1:type>ComputeResource</ns1:type>"		\
							"<ns1:path>resourcePool</ns1:path>"		\
							"<ns1:skip>false</ns1:skip>"			\
							"<ns1:selectSet>"				\
								"<ns1:name>rpToRp</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>rpToVm</ns1:name>"		\
							"</ns1:selectSet>"				\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>rpToRp</ns1:name>"			\
							"<ns1:type>ResourcePool</ns1:type>"		\
							"<ns1:path>resourcePool</ns1:path>"		\
							"<ns1:skip>false</ns1:skip>"			\
							"<ns1:selectSet>"				\
								"<ns1:name>rpToRp</ns1:name>"		\
							"</ns1:selectSet>"				\
							"<ns1:selectSet>"				\
								"<ns1:name>rpToVm</ns1:name>"		\
							"</ns1:selectSet>"				\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>hToVm</ns1:name>"			\
							"<ns1:type>HostSystem</ns1:type>"		\
							"<ns1:path>vm</ns1:path>"			\
							"<ns1:skip>false</ns1:skip>"			\
							"<ns1:selectSet>"				\
								"<ns1:name>visitFolders</ns1:name>"	\
							"</ns1:selectSet>"				\
						"</ns1:selectSet>"					\
						"<ns1:selectSet xsi:type=\"ns1:TraversalSpec\">"	\
							"<ns1:name>rpToVm</ns1:name>"			\
							"<ns1:type>ResourcePool</ns1:type>"		\
							"<ns1:path>vm</ns1:path>"			\
							"<ns1:skip>false</ns1:skip>"			\
						"</ns1:selectSet>"					\
					"</ns1:objectSet>"						\
				"</ns1:specSet>"							\
				"<ns1:options>"								\
				"</ns1:options>"							\
			"</ns1:RetrievePropertiesEx>"							\
		"</SOAP-ENV:Body>"									\
		"</SOAP-ENV:Envelope>\n"

	const char	*__function_name = "vcenter_guestvmids_get";

	int		err, opt, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_VCENTER_VMLIST)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (SUCCEED != read_xml_values(page.data, "//*[@type='VirtualMachine']", guestvmids))
	{
		*error = zbx_strdup(*error, "Cannot get list of guest VMs");
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vcenter_vmdata_get(CURL *easyhandle, const char *guestvmid)
{
#	define ZBX_POST_VCENTER_VMDETAILS 									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"							\
		"<SOAP-ENV:Envelope"										\
			" xmlns:SOAP-ENC=\"http://schemas.xmlsoap.org/soap/encoding/\""				\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:ZSI=\"http://www.zolera.com/schemas/ZSI/\""					\
			" xmlns:soapenc=\"http://schemas.xmlsoap.org/soap/encoding/\""				\
			" xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\""					\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">"				\
			"<SOAP-ENV:Header>"									\
			"</SOAP-ENV:Header>"									\
			"<SOAP-ENV:Body xmlns:ns1=\"urn:vim25\">"						\
				"<ns1:RetrieveProperties>"							\
					"<ns1:_this type=\"PropertyCollector\">propertyCollector</ns1:_this>"	\
					"<ns1:specSet>"								\
						"<ns1:propSet>"							\
							"<ns1:type>VirtualMachine</ns1:type>"			\
							"<ns1:all>false</ns1:all>"				\
							"<ns1:pathSet>summary</ns1:pathSet>"			\
						"</ns1:propSet>"						\
						"<ns1:objectSet>"						\
							"<ns1:obj type=\"VirtualMachine\">%s</ns1:obj>"		\
							"<ns1:skip>false</ns1:skip>"				\
						"</ns1:objectSet>"						\
					"</ns1:specSet>"							\
				"</ns1:RetrieveProperties>"							\
			"</SOAP-ENV:Body>"									\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vcenter_vmdata_get";

	int		err, opt, ret = FAIL;
	char		tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() guestvmid:'%s'", __function_name, guestvmid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VCENTER_VMDETAILS, guestvmid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, tmp)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s",
				opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", curl_easy_strerror(err));
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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

static int	vcenter_update(const char *url, const char *username, const char *password, char **error)
{
	const char		*__function_name = "vcenter_update";

	CURL			*easyhandle = NULL;
	int			opt, i, err, ret = FAIL;
	zbx_vector_str_t	guestvmids;
	struct curl_slist	*headers = NULL;
	zbx_vcenter_t		*vcenter;
	zbx_vm_t		*vm;
	char			*uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s' password:'%s'",
			__function_name, url, username, password);

	if (0 == vcenters_initialized)
	{
		zbx_vector_ptr_create(&vcenters);
		vcenters_initialized = 1;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
	{
		vcenter = zbx_malloc(NULL, sizeof(zbx_vcenter_t));
		vcenter->url = zbx_strdup(NULL, url);
		vcenter->username = zbx_strdup(NULL, username);
		vcenter->password = zbx_strdup(NULL, password);
		vcenter->lastcheck = 0;
		zbx_vector_ptr_create(&vcenter->vms);

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

	zbx_vector_str_create(&guestvmids);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != vcenter_authenticate(easyhandle, url, username, password, error))
		goto clean;

	if (SUCCEED != vcenter_guestvmids_get(easyhandle, &guestvmids, error))
		goto clean;

	for (i = 0; i < guestvmids.values_num; i++)
	{
		if (SUCCEED != vcenter_vmdata_get(easyhandle, guestvmids.values[i]))
			continue;

		if (NULL == (uuid = read_xml_value(page.data, ZBX_XPATH_LN("uuid"))))
			continue;

		if (NULL == (vm = vm_get(&vcenter->vms, uuid)))
		{
			vm = zbx_malloc(NULL, sizeof(zbx_vm_t));
			vm->uuid = uuid;
			vm->details = NULL;

			zbx_vector_ptr_append(&vcenter->vms, vm);
		}
		else
			zbx_free(uuid);

		vm->details = zbx_strdup(vm->details, page.data);
	}

	vcenter->lastcheck = time(NULL);

	ret = SUCCEED;
clean:
	for (i = 0; i < guestvmids.values_num; i++)
		zbx_free(guestvmids.values[i]);
	zbx_vector_str_destroy(&guestvmids);

	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
out:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare URL \"%s\" error: %s", url, *error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vsphere_authenticate(CURL *easyhandle, const char *url, const char *username, const char *password,
		char **error)
{
#	define ZBX_POST_VSPHERE_AUTH									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"						\
		"<SOAP-ENV:Envelope"									\
			" xmlns:ns0=\"urn:vim25\""							\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""			\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""			\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"		\
			"<SOAP-ENV:Header/>"								\
			"<ns1:Body>"									\
				"<ns0:Login>"								\
					"<ns0:_this type=\"SessionManager\">ha-sessionmgr</ns0:_this>"	\
					"<ns0:userName>%s</ns0:userName>"				\
					"<ns0:password>%s</ns0:password>"				\
				"</ns0:Login>"								\
			"</ns1:Body>"									\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vsphere_authenticate";
	int		err, opt, timeout = 5, ret = FAIL;
	char		postdata[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s'", __function_name, url, username);
	zabbix_log(LOG_LEVEL_DEBUG, "PAGE.REQUEST: %s", ZBX_POST_VSPHERE_AUTH);

	zbx_snprintf(postdata, sizeof(postdata), ZBX_POST_VSPHERE_AUTH, username, password);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, url)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, postdata)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "PAGE.DATA: %s", page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vsphere_guestvmids_get(CURL *easyhandle, zbx_vector_str_t *guestvmids, char **error)
{
#	define ZBX_POST_VMLIST											\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"							\
		"<SOAP-ENV:Envelope"										\
			" xmlns:ns0=\"urn:vim25\""								\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""				\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"			\
			"<SOAP-ENV:Header/>"									\
			"<ns1:Body>"										\
				"<ns0:RetrieveProperties>"							\
					"<ns0:_this type=\"PropertyCollector\">ha-property-collector</ns0:_this>"\
					"<ns0:specSet>"								\
						"<ns0:propSet>"							\
							"<ns0:type>HostSystem</ns0:type>"			\
							"<ns0:all>false</ns0:all>"				\
							"<ns0:pathSet>vm</ns0:pathSet>"				\
						"</ns0:propSet>"						\
						"<ns0:objectSet>"						\
							"<ns0:obj type=\"HostSystem\">ha-host</ns0:obj>"	\
						"</ns0:objectSet>"						\
					"</ns0:specSet>"							\
				"</ns0:RetrieveProperties>"							\
			"</ns1:Body>"										\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vsphere_guestvmids_get";

	int		err, opt, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_VMLIST)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (SUCCEED != read_xml_values(page.data, "//*[@type='VirtualMachine']", guestvmids))
	{
		*error = zbx_strdup(*error, "Cannot get list of guest VMs");
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	vsphere_hostdata_get(CURL *easyhandle, char **details, char **error)
{
#	define ZBX_POST_HOSTDETAILS										\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"							\
		"<SOAP-ENV:Envelope"										\
			" xmlns:ns0=\"urn:vim25\""								\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""				\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"			\
			"<SOAP-ENV:Header/>"									\
			"<ns1:Body>"										\
				"<ns0:RetrieveProperties>"							\
					"<ns0:_this type=\"PropertyCollector\">ha-property-collector</ns0:_this>"\
					"<ns0:specSet>"								\
						"<ns0:propSet>"							\
							"<ns0:type>HostSystem</ns0:type>"			\
							"<ns0:all>false</ns0:all>"				\
							"<ns0:pathSet>summary</ns0:pathSet>"			\
						"</ns0:propSet>"						\
						"<ns0:objectSet>"						\
							"<ns0:obj type=\"HostSystem\">ha-host</ns0:obj>"	\
						"</ns0:objectSet>"						\
					"</ns0:specSet>"							\
				"</ns0:RetrieveProperties>"							\
			"</ns1:Body>"										\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vsphere_hostdata_get";

	int		err, opt, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_HOSTDETAILS)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	*details = zbx_strdup(*details, page.data);

	ret = SUCCEED;
out:
	return ret;
}

static int	vsphere_vmdata_get(CURL *easyhandle, const char *vmid)
{
#	define ZBX_POST_VSPHERE_VMDETAILS 								\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"						\
		"<SOAP-ENV:Envelope "									\
			" xmlns:ns0=\"urn:vim25\" "							\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\" "			\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" "			\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"		\
			"<SOAP-ENV:Header/>"								\
			"<ns1:Body>"									\
				"<ns0:RetrieveProperties>"						\
					"<ns0:_this type=\"PropertyCollector\">ha-property-collector"	\
						"</ns0:_this>"						\
					"<ns0:specSet>"							\
						"<ns0:propSet>"						\
							"<ns0:type>VirtualMachine</ns0:type>"		\
							"<ns0:all>false</ns0:all>"			\
							"<ns0:pathSet>summary</ns0:pathSet>"		\
						"</ns0:propSet>"					\
						"<ns0:objectSet>"					\
							"<ns0:obj type=\"VirtualMachine\">%s</ns0:obj>"	\
						"</ns0:objectSet>"					\
					"</ns0:specSet>"						\
				"</ns0:RetrieveProperties>"						\
			"</ns1:Body>"									\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vsphere_vmdata_get";

	int		err, opt, ret = FAIL;
	char		*error = NULL, tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __function_name, vmid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VSPHERE_VMDETAILS, vmid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, tmp)))
	{
		error = zbx_strdup(error, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s", opt, error);
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		error = zbx_strdup(error, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", error);
		goto out;
	}

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
	int			opt, i, err, ret = FAIL;
	zbx_vector_str_t	guestvmids;
	struct curl_slist	*headers = NULL;
	zbx_vsphere_t		*vsphere;
	zbx_vm_t		*vm;
	char			*uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s' password:'%s'",
			__function_name, url, username, password);

	if (0 == vspheres_initialized)
	{
		zbx_vector_ptr_create(&vspheres);
		vspheres_initialized = 1;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
	{
		vsphere = zbx_malloc(NULL, sizeof(zbx_vsphere_t));
		vsphere->url = zbx_strdup(NULL, url);
		vsphere->username = zbx_strdup(NULL, username);
		vsphere->password = zbx_strdup(NULL, password);
		vsphere->details = NULL;
		vsphere->lastcheck = 0;
		zbx_vector_ptr_create(&vsphere->vms);

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

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != vsphere_authenticate(easyhandle, url, username, password, error))
		goto clean;

	if (SUCCEED != vsphere_guestvmids_get(easyhandle, &guestvmids, error))
		goto clean;

	if (SUCCEED != vsphere_hostdata_get(easyhandle, &vsphere->details, error))
		goto clean;

	for (i = 0; i < guestvmids.values_num; i++)
	{
		if (SUCCEED != vsphere_vmdata_get(easyhandle, guestvmids.values[i]))
			continue;

		if (NULL == (uuid = read_xml_value(page.data, ZBX_XPATH_LN("uuid"))))
			continue;

		if (NULL == (vm = vm_get(&vsphere->vms, uuid)))
		{
			vm = zbx_malloc(NULL, sizeof(zbx_vm_t));
			vm->uuid = uuid;
			vm->details = NULL;

			zbx_vector_ptr_append(&vsphere->vms, vm);
		}
		else
			zbx_free(uuid);

		vm->details = zbx_strdup(vm->details, page.data);
	}

	vsphere->lastcheck = time(NULL);

	ret = SUCCEED;
clean:
	for (i = 0; i < guestvmids.values_num; i++)
		zbx_free(guestvmids.values[i]);
	zbx_vector_str_destroy(&guestvmids);

	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
out:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare URL \"%s\" error: %s", url, *error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_vcenter_*                                                  *
 *                                                                            *
 * Purpose: vCenter related checks                                            *
 *                                                                            *
 ******************************************************************************/

static int	get_vcenter_vmstat(AGENT_REQUEST *request, char *xpath, AGENT_RESULT *result)
{
	zbx_vcenter_t	*vcenter;
	zbx_vm_t	*vm;
	char		*url, *username, *password, *uuid, *value, *error = NULL;

	if (4 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	password = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	if ('\0' == *url || '\0' == *username || '\0' == *uuid)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vcenter = vcenter_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (vm = vm_get(&vcenter->vms, uuid)))
		return SYSINFO_RET_FAIL;

	if (NULL == (value = read_xml_value(vm->details, xpath)))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	check_vcenter_vm_cpu_num(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("config", "numCpu"), result);
}

int	check_vcenter_vm_cpu_usage(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("runtime", "maxCpuUsage"), result);
}

int	check_vcenter_vm_list(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct zbx_json	j;
	int		i;
	char		*url, *username, *password, *name, *host, *error = NULL;
	zbx_vcenter_t	*vcenter = NULL;
	zbx_vm_t	*vm = NULL;

	if (3 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	password = get_rparam(request, 2);

	if ('\0' == *url || '\0' == *username)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vcenter_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (vcenter = vcenter_get(url, username, password)))
	{
		for (i = 0; i < vcenter->vms.values_num; i++)
		{
			vm = (zbx_vm_t *)vcenter->vms.values[i];

			if (NULL == (name = read_xml_value(vm->details, ZBX_XPATH_LN2("config", "name"))))
				continue;

			if (NULL == (host = read_xml_value(vm->details, ZBX_XPATH_LN("host"))))
			{
				zbx_free(name);
				continue;
			}

			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#VM.UUID}", vm->uuid, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#VM.NAME}", name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#HYPERVISOR.NAME}", host, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);

			zbx_free(host);
			zbx_free(name);
		}
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	check_vcenter_vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("config", "memorySizeMB"), result);
}

int	check_vcenter_vm_memory_size_ballooned(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("quickStats", "balloonedMemory"), result);
}

int	check_vcenter_vm_memory_size_compressed(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("quickStats", "compressedMemory"), result);
}

int	check_vcenter_vm_memory_size_swapped(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("quickStats", "swappedMemory"), result);
}

int	check_vcenter_vm_powerstate(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("runtime", "powerState"), result);
}

int	check_vcenter_vm_storage_committed(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("storage", "committed"), result);
}

int	check_vcenter_vm_storage_unshared(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("storage", "unshared"), result);
}

int	check_vcenter_vm_storage_uncommitted(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("storage", "uncommitted"), result);
}

int	check_vcenter_vm_uptime(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vcenter_vmstat(request, ZBX_XPATH_LN2("quickStats", "uptimeSeconds"), result);
}

/******************************************************************************
 *                                                                            *
 * Function: check_vsphere_*                                                  *
 *                                                                            *
 * Purpose: vSphere related checks                                            *
 *                                                                            *
 ******************************************************************************/

static int	get_vsphere_hoststat(AGENT_REQUEST *request, char *xpath, AGENT_RESULT *result)
{
	zbx_vsphere_t	*vsphere;
	char		*url, *username, *password, *value, *error = NULL;

	if (3 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	password = get_rparam(request, 2);

	if ('\0' == *url || '\0' == *username)
		return SYSINFO_RET_FAIL;

	if (SUCCEED != vsphere_update(url, username, password, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vsphere = vsphere_get(url, username, password)))
		return SYSINFO_RET_FAIL;

	if (NULL == (value = read_xml_value(vsphere->details, xpath)))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	get_vsphere_vmstat(AGENT_REQUEST *request, char *xpath, AGENT_RESULT *result)
{
	zbx_vsphere_t	*vsphere;
	zbx_vm_t	*vm;
	char		*url, *username, *password, *uuid, *value, *error = NULL;

	if (4 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	password = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	if ('\0' == *url || '\0' == *username || '\0' == *uuid)
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

	if (NULL == (value = read_xml_value(vm->details, xpath)))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	check_vsphere_cpu_usage(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("quickStats", "overallCpuUsage"), result);
}

int	check_vsphere_fullname(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("product", "fullName"), result);
}

int	check_vsphere_hw_cpu_cores(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "numCpuCores"), result);
}

int	check_vsphere_hw_cpu_freq(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "cpuMhz"), result);
}

int	check_vsphere_hw_cpu_model(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "cpuModel"), result);
}

int	check_vsphere_hw_cpu_threads(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "numCpuThreads"), result);
}

int	check_vsphere_hw_memory(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "memorySize"), result);
}

int	check_vsphere_hw_model(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "model"), result);
}

int	check_vsphere_hw_uuid(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "uuid"), result);
}

int	check_vsphere_hw_vendor(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("hardware", "vendor"), result);
}

int	check_vsphere_memory_used(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("quickStats", "overallMemoryUsage"), result);
}

int	check_vsphere_status(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("val", "overallStatus"), result);
}

int	check_vsphere_uptime(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("quickStats", "uptime"), result);
}

int	check_vsphere_version(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_hoststat(request, ZBX_XPATH_LN2("product", "version"), result);
}

int	check_vsphere_vm_cpu_num(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("config", "numCpu"), result);
}

int	check_vsphere_vm_cpu_usage(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("runtime", "maxCpuUsage"), result);
}

int	check_vsphere_vm_list(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct zbx_json	j;
	int		i;
	char		*url, *username, *password, *name, *error = NULL;
	zbx_vsphere_t	*vsphere = NULL;
	zbx_vm_t	*vm = NULL;

	if (3 != request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	password = get_rparam(request, 2);

	if ('\0' == *url || '\0' == *username)
		return SYSINFO_RET_FAIL;

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

			if (NULL == (name = read_xml_value(vm->details, ZBX_XPATH_LN2("config", "name"))))
				continue;

			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#VM.UUID}", vm->uuid, ZBX_JSON_TYPE_STRING);
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

int	check_vsphere_vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("config", "memorySizeMB"), result);
}

int	check_vsphere_vm_memory_size_ballooned(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("quickStats", "balloonedMemory"), result);
}

int	check_vsphere_vm_memory_size_compressed(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("quickStats", "compressedMemory"), result);
}

int	check_vsphere_vm_memory_size_swapped(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("quickStats", "swappedMemory"), result);
}

int	check_vsphere_vm_powerstate(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("runtime", "powerState"), result);
}

int	check_vsphere_vm_storage_committed(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("storage", "committed"), result);
}

int	check_vsphere_vm_storage_unshared(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("storage", "unshared"), result);
}

int	check_vsphere_vm_storage_uncommitted(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("storage", "uncommitted"), result);
}

int	check_vsphere_vm_uptime(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return get_vsphere_vmstat(request, ZBX_XPATH_LN2("quickStats", "uptimeSeconds"), result);
}

/*static int	vcenter_get_datacenter(CURL *easyhandle)
{
#	define ZBX_POST_VCENTER_GET_DATACENTER									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"							\
		"<SOAP-ENV:Envelope"										\
			" xmlns:ns0=\"urn:vim25\""								\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""				\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"			\
			"<SOAP-ENV:Header/>"									\
			"<ns1:Body>"										\
				"<ns0:RetrieveProperties>"							\
					"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
					"<ns0:specSet>"								\
						"<ns0:propSet>"							\
							"<ns0:type>Folder</ns0:type>"				\
							"<ns0:all>false</ns0:all>"				\
							"<ns0:pathSet>childEntity</ns0:pathSet>"		\
						"</ns0:propSet>"						\
						"<ns0:objectSet>"						\
							"<ns0:obj type=\"Folder\">group-d1</ns0:obj>"		\
						"</ns0:objectSet>"						\
					"</ns0:specSet>"							\
				"</ns0:RetrieveProperties>"							\
			"</ns1:Body>"										\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vcenter_get_datacenter";
	int		err, opt, ret = FAIL;
	char		*err_str = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_VCENTER_GET_DATACENTER)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s",
				opt, err_str);
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", err_str);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	vcenter_get_groupfolder(CURL *easyhandle)
{
#	define ZBX_POST_VCENTER_GET_GROUPFOLDER									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"							\
		"<SOAP-ENV:Envelope"										\
			" xmlns:ns0=\"urn:vim25\""								\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""				\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"			\
			"<SOAP-ENV:Header/>"									\
			"<ns1:Body>"										\
				"<ns0:RetrieveProperties>"							\
					"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
					"<ns0:specSet>"								\
						"<ns0:propSet>"							\
							"<ns0:type>Datacenter</ns0:type>"			\
							"<ns0:all>false</ns0:all>"				\
							"<ns0:pathSet>hostFolder</ns0:pathSet>"			\
						"</ns0:propSet>"						\
						"<ns0:objectSet>"						\
							"<ns0:obj type=\"Datacenter\">datacenter-21</ns0:obj>"	\
						"</ns0:objectSet>"						\
					"</ns0:specSet>"							\
				"</ns0:RetrieveProperties>"							\
			"</ns1:Body>"										\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vcenter_get_groupfolder";
	int		err, opt, ret = FAIL;
	char		*err_str = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_VCENTER_GET_GROUPFOLDER)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s",
				opt, err_str);
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", err_str);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	vcenter_get_hostdomain(CURL *easyhandle)
{
#	define ZBX_POST_VCENTER_GET_HOSTDOMAIN									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"							\
		"<SOAP-ENV:Envelope"										\
			" xmlns:ns0=\"urn:vim25\""								\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""				\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"			\
			"<SOAP-ENV:Header/>"									\
			"<ns1:Body>"										\
				"<ns0:RetrieveProperties>"							\
					"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
					"<ns0:specSet>"								\
						"<ns0:propSet>"							\
							"<ns0:type>Folder</ns0:type>"				\
							"<ns0:all>false</ns0:all>"				\
							"<ns0:pathSet>childEntity</ns0:pathSet>"		\
						"</ns0:propSet>"						\
						"<ns0:objectSet>"						\
							"<ns0:obj type=\"Folder\">group-h23</ns0:obj>"		\
						"</ns0:objectSet>"						\
					"</ns0:specSet>"							\
				"</ns0:RetrieveProperties>"							\
			"</ns1:Body>"										\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vcenter_get_hostdomain";
	int		err, opt, ret = FAIL;
	char		*err_str = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_VCENTER_GET_HOSTDOMAIN)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s",
				opt, err_str);
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", err_str);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	vcenter_get_hostsystem(CURL *easyhandle)
{
#	define ZBX_POST_VCENTER_GET_HOSTSYSTEM									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"							\
		"<SOAP-ENV:Envelope"										\
			" xmlns:ns0=\"urn:vim25\""								\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""				\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""				\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"			\
			"<SOAP-ENV:Header/>"									\
			"<ns1:Body>"										\
				"<ns0:RetrieveProperties>"							\
					"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
					"<ns0:specSet>"								\
						"<ns0:propSet>"							\
							"<ns0:type>ComputeResource</ns0:type>"			\
							"<ns0:all>false</ns0:all>"				\
							"<ns0:pathSet>host</ns0:pathSet>"			\
						"</ns0:propSet>"						\
						"<ns0:objectSet>"						\
							"<ns0:obj type=\"ComputeResource\">domain-s26</ns0:obj>"\
						"</ns0:objectSet>"						\
					"</ns0:specSet>"							\
				"</ns0:RetrieveProperties>"							\
			"</ns1:Body>"										\
		"</SOAP-ENV:Envelope>"

	const char	*__function_name = "vcenter_get_hostsystem";
	int		err, opt, ret = FAIL;
	char		*err_str = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_VCENTER_GET_HOSTSYSTEM)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: cannot set cURL option [%d]: %s",
				opt, err_str);
		goto out;
	}

	page.offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_DEBUG, "VMWare error: %s", err_str);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}
*/
#endif
