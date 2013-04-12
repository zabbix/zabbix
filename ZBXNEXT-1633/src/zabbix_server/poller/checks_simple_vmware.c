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

#define	ZBX_XML_HEADER1	"Soapaction:urn:vim25/4.1"
#define ZBX_XML_HEADER2	"Content-Type:text/xml; charset=utf-8"

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
ZBX_HTTPPAGE;

static ZBX_HTTPPAGE	page;

static char	*get_value(char *data, char *xpath)
{
	xmlDoc		*doc;
	xmlNode		*root_element = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlChar		*val;
	char		*ret = NULL;

	if (NULL == data)
		return NULL;

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
		return NULL;

	xpathCtx = xmlXPathNewContext(doc);
	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar*)xpath, xpathCtx)))
	{
		xmlCleanupParser();
		return NULL;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		xmlXPathFreeObject(xpathObj);
		xmlCleanupParser();
		return NULL;
	}

	root_element = xpathObj->nodesetval->nodeTab[0];

	if (root_element->type == XML_ELEMENT_NODE)
	{
		if (NULL != (val = xmlNodeGetContent(root_element)))
		{
			ret = zbx_strdup(NULL, (char *)val);
			xmlFree(val);
		}
	}

	xmlXPathFreeObject(xpathObj);
	xmlCleanupParser();

	return ret;
}

static int	get_ids(char *data, zbx_vector_str_t *guestids)
{
	xmlDoc		*doc;
	xmlNode		*root_element = NULL, *cur_node = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlChar		*val;

	if (NULL == data)
		return FAIL;

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
		return FAIL;

	xpathCtx = xmlXPathNewContext(doc);
	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_LN("ManagedObjectReference") "[@type]",
			xpathCtx)))
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

	root_element = xpathObj->nodesetval->nodeTab[0];

	for (cur_node = root_element; cur_node; cur_node = cur_node->next)
	{
		if (cur_node->type == XML_ELEMENT_NODE)
		{
			if (NULL != (val = xmlNodeGetContent(cur_node)))
			{
				val = xmlNodeGetContent(cur_node);
				zbx_vector_str_append(guestids, zbx_strdup(NULL, (char *)val));
				xmlFree(val);
			}
		}
	}

	xmlXPathFreeObject(xpathObj);
	xmlCleanupParser();

	return SUCCEED;
}


static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	/* first piece of data */
	if (NULL == page.data)
	{
		page.allocated = MAX(8096, r_size);
		page.offset = 0;
		page.data = malloc(page.allocated);
	}

	zbx_strncpy_alloc(&page.data, &page.allocated, &page.offset, ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	return size * nmemb;
}

static int	authenticate(CURL *easyhandle, char *url, char *username, char *userpwd)
{
#	define ZBX_POST_AUTH										\
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

	const char	*__function_name = "authenticate";
	int		err, opt, timeout = 5;
	char		*err_str = NULL, postdata[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:'%s' username:'%s'", __function_name, url, username);

	zbx_snprintf(postdata, sizeof(postdata), ZBX_POST_AUTH, username, userpwd);

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
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare URL \"%s\" error: could not set cURL option [%d]: %s",
				url, opt, err_str);
		goto out;
	}

	memset(&page, 0, sizeof(page));

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare URL \"%s\" error: %s", url, err_str);
		goto out;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}

static int	get_guestids(CURL *easyhandle, zbx_vector_str_t *guestids)
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

	int	err, opt, ret = FAIL;
	char	*err_str = NULL;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_VMLIST)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare error: could not set cURL option [%d]: %s",
				opt, err_str);
		goto clean;
	}

	memset(&page, 0, sizeof(page));

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare error: %s", err_str);
		goto clean;
	}

	if (SUCCEED != get_ids(page.data, guestids))
	{
		err_str = zbx_strdup(err_str, "unable to get list of guest ids");
		zabbix_log(LOG_LEVEL_ERR, "VMWare error: %s", err_str);
		goto clean;
	}

	ret = SUCCEED;
clean:
	zbx_free(err_str);

	return ret;
}

static int	get_hostdata(CURL *easyhandle)
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

	const char	*__function_name = "get_hostdata";
	int		err, opt, ret = FAIL;
	char		*err_str = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, ZBX_POST_HOSTDETAILS)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare error: could not set cURL option [%d]: %s",
				opt, err_str);
		goto out;
	}

	memset(&page, 0, sizeof(page));

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare \"%s\" error: %s", err_str);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	get_vmdata(CURL *easyhandle, const char *vmid)
{
#	define ZBX_POST_VMDETAILS 									\
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

	const char	*__function_name = "get_vmdata";
	int		err, opt, ret = FAIL;
	char		*err_str = NULL, tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __function_name, vmid);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMDETAILS, vmid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, tmp)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare error: could not set cURL option [%d]: %s", opt, err_str);
		goto out;
	}

	memset(&page, 0, sizeof(page));

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "VMWare \"%s\" error: %s", err_str);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	get_vmware_hoststat(char *url, char *username, char *userpwd, char *xpath, AGENT_RESULT *result)
{
	const char		*__function_name = "get_vmware_hoststat";
	char			*value, *err_str = NULL;
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == url || '\0' == *url || NULL == username || '\0' == *username || NULL == userpwd)
		goto out;

	headers = curl_slist_append(headers, ZBX_XML_HEADER1);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);

	if (NULL == (easyhandle = curl_easy_init()))
	{
		err_str = zbx_strdup(err_str, "could not init cURL library");
		zabbix_log(LOG_LEVEL_ERR, "VMWare URL \"%s\" error: %s", url, err_str);
		goto clean;
	}

	if (SUCCEED != authenticate(easyhandle, url, username, userpwd))
		goto clean;

	if (SUCCEED != get_hostdata(easyhandle))
		goto clean;

	if (NULL != (value = get_value(page.data, xpath)))
	{
		SET_STR_RESULT(result, value);
		ret = SUCCEED;
	}
clean:
	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

static int	get_vmware_vmstat(char *url, char *username, char *userpwd, char *uuid, char *xpath, AGENT_RESULT *result)
{
	const char		*__function_name = "get_vmware_vmstat";
	CURL			*easyhandle = NULL;
	int			i;
	char			*uuidtmp, *value, *err_str = NULL;
	zbx_vector_str_t	guestids;
	struct curl_slist	*headers = NULL;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == url || '\0' == *url || NULL == username || '\0' == *username || NULL == userpwd ||
			NULL == uuid || '\0' == *uuid)
		goto out;

	headers = curl_slist_append(headers, ZBX_XML_HEADER1);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);

	zbx_vector_str_create(&guestids);

	if (NULL == (easyhandle = curl_easy_init()))
	{
		err_str = zbx_strdup(err_str, "could not init cURL library");
		zabbix_log(LOG_LEVEL_ERR, "VMWare URL \"%s\" error: %s", url, err_str);
		goto clean;
	}

	if (SUCCEED != authenticate(easyhandle, url, username, userpwd))
		goto clean;

	if (SUCCEED != get_guestids(easyhandle, &guestids))
		goto clean;

	for (i = 0; i < guestids.values_num; i++)
	{
		if (SUCCEED != get_vmdata(easyhandle, guestids.values[i]))
			goto clean;

		uuidtmp = get_value(page.data, ZBX_XPATH_LN("uuid"));
		if (NULL == uuidtmp)
			continue;

		if (0 == strcmp(uuid, uuidtmp))
		{
			if (NULL != (value = get_value(page.data, xpath)))
			{
				SET_STR_RESULT(result, value);
				ret = SYSINFO_RET_OK;
			}
			break;
		}
	}
clean:
	zbx_vector_str_destroy(&guestids);

	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

int	check_vmware_vmlist(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char		*__function_name = "check_vmware_vmlist";
	struct zbx_json		j;
	CURL			*easyhandle = NULL;
	int			i, ret = SYSINFO_RET_FAIL;
	char			*url, *username, *userpwd, *err_str = NULL;
	zbx_vector_str_t	guestids;
	struct curl_slist	*headers = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, request->key);

	if (3 < request->nparam)
		goto out;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	if (NULL == url || '\0' == *url || NULL == username || '\0' == *username || NULL == userpwd)
		goto out;

	headers = curl_slist_append(headers, ZBX_XML_HEADER1);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);

	zbx_vector_str_create(&guestids);

	if (NULL == (easyhandle = curl_easy_init()))
	{
		err_str = zbx_strdup(err_str, "could not init cURL library");
		zabbix_log(LOG_LEVEL_ERR, "VMWare URL \"%s\" error: %s", url, err_str);
		goto clean;
	}

	if (SUCCEED != authenticate(easyhandle, url, username, userpwd))
		goto clean;

	if (SUCCEED != get_guestids(easyhandle, &guestids))
		goto clean;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < guestids.values_num; i++)
	{
		if (SUCCEED != get_vmdata(easyhandle, guestids.values[i]))
		{
			zbx_json_free(&j);
			goto clean;
		}

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#UUID}", get_value(page.data, ZBX_XPATH_LN("uuid")), ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, "{#NAME}", get_value(page.data, ZBX_XPATH_LN2("config", "name")),
				ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	ret = SYSINFO_RET_OK;

	zbx_json_free(&j);
clean:
	zbx_vector_str_destroy(&guestids);

	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

int	check_vmware_vmcpunum(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("config", "numCpu"), result);
}

int	check_vmware_vmmemsize(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("config", "memorySizeMB"), result);
}

int	check_vmware_vmuptime(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("quickStats", "uptimeSeconds"), result);
}

int	check_vmware_vmmemsizeballooned(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("quickStats", "balloonedMemory"), result);
}

int	check_vmware_vmmemsizecompressed(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("quickStats", "compressedMemory"), result);
}

int	check_vmware_vmmemsizeswapped(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("quickStats", "swappedMemory"), result);
}

int	check_vmware_vmstoragecommitted(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("storage", "committed"), result);
}

int	check_vmware_vmstorageuncommitted(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("storage", "uncommitted"), result);
}

int	check_vmware_vmstorageunshared(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("storage", "unshared"), result);
}

int	check_vmware_vmpowerstate(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("runtime", "powerState"), result);
}

int	check_vmware_vmcpuusage(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd, *uuid;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);
	uuid = get_rparam(request, 3);

	return get_vmware_vmstat(url, username, userpwd, uuid, ZBX_XPATH_LN2("runtime", "maxCpuUsage"), result);
}

int	check_vmware_hostuptime(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("quickStats", "uptime"), result);
}

int	check_vmware_hostmemoryused(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("quickStats", "overallMemoryUsage"), result);
}

int	check_vmware_hostcpuusage(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("quickStats", "overallCpuUsage"), result);
}

int	check_vmware_hostfullname(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("product", "fullName"), result);
}

int	check_vmware_hostversion(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("product", "version"), result);
}

int	check_vmware_hosthwvendor(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "vendor"), result);
}

int	check_vmware_hosthwmodel(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "model"), result);
}

int	check_vmware_hosthwuuid(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "uuid"), result);
}

int	check_vmware_hosthwmemory(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "memorySize"), result);
}

int	check_vmware_hosthwcpumodel(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "cpuModel"), result);
}

int	check_vmware_hosthwcpufreq(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "cpuMhz"), result);
}

int	check_vmware_hosthwcpucores(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "numCpuCores"), result);
}

int	check_vmware_hosthwcputhreads(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("hardware", "numCpuThreads"), result);
}

int	check_vmware_hoststatus(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*url, *username, *userpwd;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	url = get_rparam(request, 0);
	username = get_rparam(request, 1);
	userpwd = get_rparam(request, 2);

	return get_vmware_hoststat(url, username, userpwd, ZBX_XPATH_LN2("val", "overallStatus"), result);
}
#endif
