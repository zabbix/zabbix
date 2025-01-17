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

#include "zbxvmware.h"

#include "vmware_internal.h"
#include "vmware_shmem.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
#	include "vmware_hv.h"
#	include "vmware_ds.h"
#	include "vmware_event.h"
#	include "vmware_perfcntr.h"
#	include "vmware_service_cfglists.h"
#endif

#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

#include "zbxmutexs.h"
#include "zbxshmem.h"
#include "zbxsysinc.h"
#include "zbxalgo.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
#	include "zbxcurl.h"
#	include "zbxstr.h"
#	include "zbxxml.h"
#endif

/*
 * The VMware data (zbx_vmware_service_t structure) are stored in shared memory.
 * This data can be accessed with zbx_vmware_get_service() function and is regularly
 * updated by VMware collector processes.
 *
 * When a new service is requested by poller the zbx_vmware_get_service() function
 * creates a new service object, marks it as new, but still returns NULL object.
 *
 * The collectors check the service object list for new services or services not updated
 * during last config_vmware_frequency seconds. If such service is found it is marked
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
 * with config_vmware_perf_frequency period. The performance data is stored directly
 * in VMware service object entities vector - so the structure data is not affected by
 * performance data updates.
 */


static zbx_mutex_t	vmware_lock = ZBX_MUTEX_NULL;

static zbx_vmware_t	*vmware = NULL;

ZBX_PTR_VECTOR_IMPL(vmware_service_ptr, zbx_vmware_service_t *)

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

ZBX_PTR_VECTOR_IMPL(str_uint64_pair, zbx_str_uint64_pair_t)
ZBX_PTR_VECTOR_IMPL(vmware_datacenter_ptr, zbx_vmware_datacenter_t *)
ZBX_PTR_VECTOR_IMPL(vmware_diskextent_ptr, zbx_vmware_diskextent_t *)
ZBX_VECTOR_IMPL(vmware_hvdisk, zbx_vmware_hvdisk_t)
ZBX_PTR_VECTOR_IMPL(vmware_dsname_ptr, zbx_vmware_dsname_t *)
ZBX_PTR_VECTOR_IMPL(vmware_pnic_ptr, zbx_vmware_pnic_t *)
ZBX_PTR_VECTOR_IMPL(vmware_custom_attr_ptr, zbx_vmware_custom_attr_t *)
ZBX_PTR_VECTOR_IMPL(custquery_param, zbx_vmware_custquery_param_t)
ZBX_PTR_VECTOR_IMPL(vmware_dvswitch_ptr, zbx_vmware_dvswitch_t *)
ZBX_PTR_VECTOR_IMPL(vmware_alarm_ptr, zbx_vmware_alarm_t *)
ZBX_PTR_VECTOR_IMPL(vmware_diskinfo_ptr, zbx_vmware_diskinfo_t *)
ZBX_PTR_VECTOR_IMPL(vmware_cluster_ptr, zbx_vmware_cluster_t *)
ZBX_PTR_VECTOR_IMPL(vmware_perf_counter_ptr, zbx_vmware_perf_counter_t *)
ZBX_PTR_VECTOR_IMPL(vmware_dev_ptr, zbx_vmware_dev_t *)
ZBX_PTR_VECTOR_IMPL(vmware_fs_ptr, zbx_vmware_fs_t *)
ZBX_PTR_VECTOR_IMPL(vmware_vm_ptr, zbx_vmware_vm_t *)
ZBX_PTR_VECTOR_IMPL(vmware_event_ptr, zbx_vmware_event_t *)
ZBX_PTR_VECTOR_IMPL(vmware_perf_data_ptr, zbx_vmware_perf_data_t *)
ZBX_PTR_VECTOR_IMPL(vmware_perf_entity_ptr, zbx_vmware_perf_entity_t *)

zbx_vmware_service_objects_t	*get_vmware_service_objects(void)
{
	static zbx_vmware_service_objects_t	vmware_service_objects[VMWARE_SERVICE_OBJECTS_ARR_SIZE] =
		{
			{NULL, NULL, NULL, NULL, NULL},
			{"ha-perfmgr", "ha-sessionmgr", "ha-eventmgr", "ha-property-collector", "ha-folder-root"},
			{"PerfMgr", "SessionManager", "EventManager", "propertyCollector", "group-d1"}
		};
	return vmware_service_objects;
}

ZBX_PTR_VECTOR_IMPL(cq_value_ptr, zbx_vmware_cq_value_t *)
ZBX_PTR_VECTOR_IMPL(vmware_alarm_details_ptr, zbx_vmware_alarm_details_t *)
ZBX_PTR_VECTOR_IMPL(vmware_resourcepool_ptr, zbx_vmware_resourcepool_t *)

static zbx_uint64_t	evt_req_chunk_size;

/******************************************************************************
 *                                                                            *
 * Purpose: getting information about the filling of shared memory            *
 *                                                                            *
 * Comment: we ignore services with any error, such as                        *
 *          incorrect url, login or password                                  *
 *                                                                            *
 ******************************************************************************/
int	vmware_shared_is_ready(void)
{
	int	i;

	for (i = 0; i < vmware->services.values_num; i++)
	{
		if (0 == (vmware->services.values[i]->state & (ZBX_VMWARE_STATE_SHMEM_READY | ZBX_VMWARE_STATE_FAILED)))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: getting size of shared memory available for events of VC instance *
 *                                                                            *
 ******************************************************************************/
float	vmware_shared_evtpart_size(const int num)
{
#	define	DEFAULT_FACTOR(n)	((float)1/n)
	int	i, total = 0, vc_active = 0;

	for (i = 0; i < vmware->services.values_num; i++)
	{
		if (0 != (vmware->services.values[i]->state & ZBX_VMWARE_STATE_FAILED))
			continue;

		vc_active++;
	}

	if (1 >= vc_active)
		return 1;

	if (0 == num)
		return DEFAULT_FACTOR(vc_active);

	for (i = 0; i < vmware->services.values_num; i++)
	{
		if (0 == (vmware->services.values[i]->state & ZBX_VMWARE_STATE_SHMEM_READY))
			return DEFAULT_FACTOR(vc_active);

		if (NULL == vmware->services.values[i]->eventlog.data)
			return DEFAULT_FACTOR(vc_active);

		total += vmware->services.values[i]->eventlog.expect_num;
	}

	if (0 != total)
	{
#		define	MIN_FACTOR	(((float)1 / vc_active) / 4)
		float	factor = (float)num / total;

		if (factor < MIN_FACTOR)
			factor = MIN_FACTOR;
		else if (factor > (1 - MIN_FACTOR * (vc_active - 1)))
			factor = 1 - MIN_FACTOR * (vc_active - 1);

		return factor;
#		undef	MIN_FACTOR
	}
	else
		return DEFAULT_FACTOR(vc_active);

#	undef	DEFAULT_FACTOR
}

/* the vmware resource pool chunk */
typedef struct
{
	char			*id;
	char			*first_parentid;
	char			*name;
	const char		*path;
	const char		*parentid;
	unsigned char		parent_is_rp;
}
zbx_vmware_rpool_chunk_t;

ZBX_PTR_VECTOR_DECL(vmware_rpool_chunk_ptr, zbx_vmware_rpool_chunk_t *)
ZBX_PTR_VECTOR_IMPL(vmware_rpool_chunk_ptr, zbx_vmware_rpool_chunk_t *)

/* string pool support */

zbx_uint64_t	vmware_shared_str_sz(const char *str)
{
	if (SUCCEED == vmware_shared_strsearch(str))
		return 0;

	return zbx_shmem_required_chunk_size(strlen(str) + REFCOUNT_FIELD_SIZE + 1 + ZBX_HASHSET_ENTRY_OFFSET);
}

int	vmware_shared_strsearch(const char *str)
{
	return NULL == zbx_hashset_search(&vmware->strpool, str - REFCOUNT_FIELD_SIZE) ? FAIL : SUCCEED;
}

char	*vmware_strpool_strdup(const char *str, zbx_hashset_t *strpool, zbx_uint64_t *len)
{
	void	*ptr;

	if (NULL != len)
		*len = 0;

	if (NULL == str)
		return NULL;

	zbx_uint64_t	sz;

	sz = REFCOUNT_FIELD_SIZE + strlen(str) + 1;
	ptr = zbx_hashset_insert_ext(strpool, str - REFCOUNT_FIELD_SIZE, sz, REFCOUNT_FIELD_SIZE, sz,
			ZBX_HASHSET_UNIQ_FALSE);

	(*(zbx_uint32_t *)ptr)++;

	if (NULL != len && 1 == *(zbx_uint32_t *)ptr)
		*len = sz + ZBX_HASHSET_ENTRY_OFFSET;

	return (char *)ptr + REFCOUNT_FIELD_SIZE;
}

char	*vmware_shared_strdup(const char *str)
{
	char		*strdup;
	zbx_uint64_t	len;

	strdup = vmware_strpool_strdup(str, &vmware->strpool, &len);

	if (0 < len)
		vmware->strpool_sz += zbx_shmem_required_chunk_size(len);

	return strdup;
}

void	vmware_strpool_strfree(char *str, zbx_hashset_t *strpool, zbx_uint64_t *len)
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

void	vmware_shared_strfree(char *str)
{
	zbx_uint64_t	len;

	vmware_strpool_strfree(str, &vmware->strpool, &len);

	if (0 < len)
		vmware->strpool_sz -= zbx_shmem_required_chunk_size(len);
}

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
 * Purpose: frees resources allocated to store custom query params value data *
 *                                                                            *
 * Parameters: cq_value - [IN] custom query value data                        *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vmware_cq_value_free(zbx_vmware_cq_value_t *cq_value)
{
	zbx_str_free(cq_value->response);
	zbx_free(cq_value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: abstracts curl_easy_setopt/curl_easy_perform call pair            *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL handle                                  *
 *             request    - [IN] http request                                 *
 *             response   - [OUT] http response                               *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - http request was completed successfully            *
 *               FAIL    - http request has failed                            *
 *                                                                            *
 ******************************************************************************/
static int	zbx_http_post(CURL *easyhandle, const char *request, ZBX_HTTPPAGE **response, char **error)
{
	CURLoption	opt;
	CURLcode	err;
	ZBX_HTTPPAGE	*resp;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, request)))
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
		}

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
 * Parameters: fn_parent  - [IN] parent function name for Log records         *
 *             easyhandle - [IN] CURL handle                                  *
 *             request    - [IN] http request                                 *
 *             xdoc       - [OUT] xml document response (optional)            *
 *             token      - [OUT] soap token for next query (optional)        *
 *             error      - [OUT] error message in case of failure (optional) *
 *                                                                            *
 * Return value: SUCCEED - SOAP request was completed successfully            *
 *               FAIL    - SOAP request has failed                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_soap_post(const char *fn_parent, CURL *easyhandle, const char *request, xmlDoc **xdoc,
		char **token , char **error)
{
#	define ZBX_XPATH_RETRIEVE_PROPERTIES_TOKEN			\
		"/*[local-name()='Envelope']/*[local-name()='Body']"	\
		"/*[local-name()='RetrievePropertiesExResponse']"	\
		"/*[local-name()='returnval']/*[local-name()='token'][1]"

#	define ZBX_XPATH_FAULT_SLOW(max_len)									\
		"concat(substring(" ZBX_XPATH_FAULT_FAST("faultstring")",1," ZBX_STR(max_len) "),"		\
		"substring(concat(local-name(" ZBX_XPATH_FAULT_FAST("detail") "/*[1]),':',"			\
		ZBX_XPATH_FAULT_FAST("detail")"//*[local-name()='name']),1,"					\
		ZBX_STR(max_len) " * number(string-length(" ZBX_XPATH_FAULT_FAST("faultstring") ")=0)"		\
		"* number(string-length(local-name(" ZBX_XPATH_FAULT_FAST("detail") "/*[1]) )>0)))"

#	define ZBX_XPATH_FAULTSTRING(sz)									\
		(MAX_STRING_LEN < sz ? ZBX_XPATH_FAULT_FAST("faultstring") : ZBX_XPATH_FAULT_SLOW(MAX_STRING_LEN))

#	define	ZBX_XPATH_FAULT_FAST(name)									\
		"/*/*/*[local-name()='Fault'][1]/*[local-name()='" name "'][1]"

	xmlDoc		*doc;
	ZBX_HTTPPAGE	*resp;
	int		ret = SUCCEED;
	char		*val = NULL;

	if (SUCCEED != zbx_http_post(easyhandle, request, &resp, error))
		return FAIL;

	if (NULL != fn_parent)
		zabbix_log(LOG_LEVEL_TRACE, "%s() SOAP response: %s", fn_parent, resp->data);

	if (SUCCEED == zbx_xml_try_read_value(resp->data, resp->offset, ZBX_XPATH_FAULTSTRING(resp->offset), &doc,
			&val, error))
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
			zbx_xml_doc_free(doc);
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
#	undef ZBX_XPATH_FAULTSTRING
#	undef ZBX_XPATH_FAULT_SLOW
#	undef ZBX_XPATH_FAULT_FAST
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads vmware object properties by their xpaths from xml data      *
 *                                                                            *
 * Parameters: xdoc      - [IN] xml document                                  *
 *             propmap   - [IN] xpaths of properties to read                  *
 *             props_num - [IN] number of properties to read                  *
 *                                                                            *
 * Return value: array of property values                                     *
 *                                                                            *
 * Comments: The array with property values must be freed by the caller.      *
 *                                                                            *
 ******************************************************************************/
char	**xml_read_props(xmlDoc *xdoc, const zbx_vmware_propmap_t *propmap, int props_num)
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
 * Purpose: frees shared resources allocated to store custom query params     *
 *          data                                                              *
 *                                                                            *
 * Parameters: cq_param - [IN] custom query params data                       *
 *                                                                            *
 ******************************************************************************/
static void	vmware_cq_param_shared_free(zbx_vmware_custquery_param_t cq_param)
{
	vmware_shared_strfree(cq_param.name);
	vmware_shared_strfree(cq_param.value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store custom query params data       *
 *                                                                            *
 * Parameters: cq_param - [IN] custom query params data                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_cq_param_free(zbx_vmware_custquery_param_t cq_param)
{
	zbx_free(cq_param.name);
	zbx_free(cq_param.value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store datastore data          *
 *                                                                            *
 * Parameters: datastore - [IN]                                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datastore_shared_free(zbx_vmware_datastore_t *datastore)
{
	vmware_shared_strfree(datastore->name);
	vmware_shared_strfree(datastore->id);
	vmware_shared_strfree(datastore->uuid);
	vmware_shared_strfree(datastore->type);

	vmware_vector_str_uint64_pair_shared_clean(&datastore->hv_uuids_access);
	zbx_vector_str_uint64_pair_destroy(&datastore->hv_uuids_access);

	zbx_vector_vmware_diskextent_ptr_clear_ext(&datastore->diskextents, vmware_shmem_diskextent_free);
	zbx_vector_vmware_diskextent_ptr_destroy(&datastore->diskextents);

	zbx_vector_str_clear_ext(&datastore->alarm_ids, vmware_shared_strfree);
	zbx_vector_str_destroy(&datastore->alarm_ids);

	vmware_shmem_free_datastore(datastore);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware cluster          *
 *                                                                            *
 * Parameters: cluster - [IN] vmware cluster                                  *
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

	zbx_vector_str_clear_ext(&cluster->dss_uuid, vmware_shared_strfree);
	zbx_vector_str_destroy(&cluster->dss_uuid);

	zbx_vector_str_clear_ext(&cluster->alarm_ids, vmware_shared_strfree);
	zbx_vector_str_destroy(&cluster->alarm_ids);

	vmware_shmem_cluster_free(cluster);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service data     *
 *                                                                            *
 * Parameters: data - [IN] vmware service data                                *
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

		zbx_vector_vmware_cluster_ptr_clear_ext(&data->clusters, vmware_cluster_shared_free);
		zbx_vector_vmware_cluster_ptr_destroy(&data->clusters);

		zbx_vector_vmware_datastore_ptr_clear_ext(&data->datastores, vmware_datastore_shared_free);
		zbx_vector_vmware_datastore_ptr_destroy(&data->datastores);

		zbx_vector_vmware_datacenter_ptr_clear_ext(&data->datacenters, vmware_shmem_datacenter_free);
		zbx_vector_vmware_datacenter_ptr_destroy(&data->datacenters);

		zbx_vector_vmware_resourcepool_ptr_clear_ext(&data->resourcepools, vmware_shmem_resourcepool_free);
		zbx_vector_vmware_resourcepool_ptr_destroy(&data->resourcepools);

		zbx_vector_vmware_dvswitch_ptr_clear_ext(&data->dvswitches, vmware_shmem_dvswitch_free);
		zbx_vector_vmware_dvswitch_ptr_destroy(&data->dvswitches);

		zbx_vector_vmware_alarm_ptr_clear_ext(&data->alarms, vmware_shmem_alarm_free);
		zbx_vector_vmware_alarm_ptr_destroy(&data->alarms);

		zbx_vector_str_clear_ext(&data->alarm_ids, vmware_shared_strfree);
		zbx_vector_str_destroy(&data->alarm_ids);

		if (NULL != data->error)
			vmware_shared_strfree(data->error);

		vmware_shmem_data_free(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service event    *
 *          log messages                                                      *
 *                                                                            *
 * Parameters: events - [IN] vmware service event vector of messages          *
 *                                                                            *
 ******************************************************************************/
void	vmware_eventlog_msg_shared_free(zbx_vector_vmware_event_ptr_t *events)
{
	if (NULL != events)
	{
		zbx_vector_vmware_event_ptr_clear_ext(events, vmware_shmem_event_free);
		zbx_vector_vmware_event_ptr_destroy(events);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service event    *
 *          log data                                                          *
 *                                                                            *
 * Parameters: evt_data - [IN] vmware service event log data                  *
 *                                                                            *
 ******************************************************************************/
void	vmware_eventlog_data_shared_free(zbx_vmware_eventlog_data_t *evt_data)
{
	if (NULL != evt_data)
	{
		vmware_eventlog_msg_shared_free(&evt_data->events);

		if (NULL != evt_data->error)
			vmware_shared_strfree(evt_data->error);

		vmware_shmem_eventlog_data_free(evt_data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans resources allocated by vmware custom query in vmware       *
 *                                                                            *
 * Parameters: cust_query - [IN] entity to free                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_shared_cust_query_clean(zbx_vmware_cust_query_t *cust_query)
{
	if (NULL != cust_query->query_params)
	{
		zbx_vector_custquery_param_clear_ext(cust_query->query_params, vmware_cq_param_shared_free);
		zbx_vector_custquery_param_destroy(cust_query->query_params);
		vmware_shmem_cust_query_clean(cust_query);
	}

	vmware_shared_strfree(cust_query->soap_type);
	vmware_shared_strfree(cust_query->id);
	vmware_shared_strfree(cust_query->key);
	vmware_shared_strfree(cust_query->mode);
	vmware_shared_strfree(cust_query->value);
	vmware_shared_strfree(cust_query->error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service          *
 *                                                                            *
 * Parameters: service - [IN] vmware service data                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_shared_free(zbx_vmware_service_t *service)
{
	zbx_hashset_iter_t		iter;
	zbx_vmware_counter_t		*counter;
	zbx_vmware_perf_entity_t	*entity;
	zbx_vmware_cust_query_t		*cust_query;
	zbx_vmware_key_value_t		*evt_severity;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	vmware_shared_strfree(service->url);
	vmware_shared_strfree(service->username);
	vmware_shared_strfree(service->password);

	if (NULL != service->version)
		vmware_shared_strfree(service->version);

	if (NULL != service->fullname)
		vmware_shared_strfree(service->fullname);

	vmware_data_shared_free(service->data);
	vmware_eventlog_data_shared_free(service->eventlog.data);

	zbx_hashset_iter_reset(&service->entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
		vmware_shared_perf_entity_clean(entity);

	zbx_hashset_destroy(&service->entities);

	zbx_hashset_iter_reset(&service->cust_queries, &iter);
	while (NULL != (cust_query = (zbx_vmware_cust_query_t *)zbx_hashset_iter_next(&iter)))
		vmware_shared_cust_query_clean(cust_query);

	zbx_hashset_destroy(&service->cust_queries);

	zbx_hashset_iter_reset(&service->counters, &iter);
	while (NULL != (counter = (zbx_vmware_counter_t *)zbx_hashset_iter_next(&iter)))
		vmware_counter_shared_clean(counter);

	zbx_hashset_destroy(&service->counters);

	zbx_hashset_iter_reset(&service->eventlog.evt_severities, &iter);
	while (NULL != (evt_severity = (zbx_vmware_key_value_t *)zbx_hashset_iter_next(&iter)))
		zbx_shmem_vmware_key_value_free(evt_severity);

	zbx_hashset_destroy(&service->eventlog.evt_severities);

	zbx_vector_vmware_entity_tags_ptr_clear_ext(&service->data_tags.entity_tags, vmware_shared_entity_tags_free);
	zbx_vector_vmware_entity_tags_ptr_destroy(&service->data_tags.entity_tags);
	vmware_shared_strfree(service->data_tags.error);

	vmware_shmem_service_free(service);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store datacenter data                *
 *                                                                            *
 * Parameters: datacenter - [IN]                                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_datacenter_free(zbx_vmware_datacenter_t *datacenter)
{
	zbx_free(datacenter->name);
	zbx_free(datacenter->id);
	zbx_vector_str_clear_ext(&datacenter->alarm_ids, zbx_str_free);
	zbx_vector_str_destroy(&datacenter->alarm_ids);
	zbx_free(datacenter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store rp_chunk data                  *
 *                                                                            *
 * Parameters: rp_chunk - [IN] resourcepool chunk                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_rp_chunk_free(zbx_vmware_rpool_chunk_t *rp_chunk)
{
	zbx_free(rp_chunk->id);
	zbx_free(rp_chunk->name);
	zbx_free(rp_chunk->first_parentid);
	/* path and parent are not cleared */
	zbx_free(rp_chunk);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store resourcepool data              *
 *                                                                            *
 * Parameters: resourcepool - [IN]                                            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_resourcepool_free(zbx_vmware_resourcepool_t *resourcepool)
{
	zbx_free(resourcepool->id);
	zbx_free(resourcepool->parentid);
	zbx_free(resourcepool->path);
	zbx_free(resourcepool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store dvswitch data                  *
 *                                                                            *
 * Parameters: dvs - [IN] dvswitch                                            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_dvswitch_free(zbx_vmware_dvswitch_t *dvs)
{
	zbx_free(dvs->uuid);
	zbx_free(dvs->id);
	zbx_free(dvs->name);
	zbx_free(dvs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store properties list         *
 *                                                                            *
 * Parameters: props     - [IN] properties list                               *
 *             props_num - [IN] number of properties in list                  *
 *                                                                            *
 ******************************************************************************/
void	vmware_props_free(char **props, int props_num)
{
	if (NULL == props)
		return;

	for (int i = 0; i < props_num; i++)
		zbx_free(props[i]);

	zbx_free(props);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store alarm data                     *
 *                                                                            *
 * Parameters: alarm - [IN] alarm object                                      *
 *                                                                            *
 ******************************************************************************/
static void	vmware_alarm_free(zbx_vmware_alarm_t *alarm)
{
	zbx_str_free(alarm->key);
	zbx_str_free(alarm->name);
	zbx_str_free(alarm->system_name);
	zbx_str_free(alarm->description);
	zbx_str_free(alarm->overall_status);
	zbx_str_free(alarm->time);
	zbx_free(alarm);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store alarm details data             *
 *                                                                            *
 * Parameters: details - [IN] alarm details object                            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_alarm_details_free(zbx_vmware_alarm_details_t *details)
{
	zbx_str_free(details->alarm);
	zbx_str_free(details->name);
	zbx_str_free(details->system_name);
	zbx_str_free(details->description);
	zbx_free(details);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware cluster                 *
 *                                                                            *
 * Parameters: cluster - [IN] vmware cluster                                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_cluster_free(zbx_vmware_cluster_t *cluster)
{
	zbx_free(cluster->name);
	zbx_free(cluster->id);
	zbx_free(cluster->status);
	zbx_vector_str_clear_ext(&cluster->dss_uuid, zbx_str_free);
	zbx_vector_str_destroy(&cluster->dss_uuid);
	zbx_vector_str_clear_ext(&cluster->alarm_ids, zbx_str_free);
	zbx_vector_str_destroy(&cluster->alarm_ids);
	zbx_free(cluster);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware service data            *
 *                                                                            *
 * Parameters: data - [IN] vmware service data                                *
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

	zbx_vector_vmware_cluster_ptr_clear_ext(&data->clusters, vmware_cluster_free);
	zbx_vector_vmware_cluster_ptr_destroy(&data->clusters);

	zbx_vector_vmware_datastore_ptr_clear_ext(&data->datastores, vmware_datastore_free);
	zbx_vector_vmware_datastore_ptr_destroy(&data->datastores);

	zbx_vector_vmware_datacenter_ptr_clear_ext(&data->datacenters, vmware_datacenter_free);
	zbx_vector_vmware_datacenter_ptr_destroy(&data->datacenters);

	zbx_vector_vmware_resourcepool_ptr_clear_ext(&data->resourcepools, vmware_resourcepool_free);
	zbx_vector_vmware_resourcepool_ptr_destroy(&data->resourcepools);

	zbx_vector_vmware_dvswitch_ptr_clear_ext(&data->dvswitches, vmware_dvswitch_free);
	zbx_vector_vmware_dvswitch_ptr_destroy(&data->dvswitches);

	zbx_vector_vmware_alarm_ptr_clear_ext(&data->alarms, vmware_alarm_free);
	zbx_vector_vmware_alarm_ptr_destroy(&data->alarms);

	zbx_vector_str_clear_ext(&data->alarm_ids, zbx_str_free);
	zbx_vector_str_destroy(&data->alarm_ids);

	zbx_free(data->error);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: validation of url suffix                                          *
 *                                                                            *
 * Parameters: url   - [IN]                                                   *
 * Parameters: error - [OUT]                                                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_validate_url_suffix(const char *url, char **error)
{
#define VMWARE_URL_SUFFIX_SDK	"/sdk"
	size_t	len = strlen(url);

	if (ZBX_CONST_STRLEN(VMWARE_URL_SUFFIX_SDK) > len ||
			0 != strcmp(url + (len - ZBX_CONST_STRLEN(VMWARE_URL_SUFFIX_SDK)), VMWARE_URL_SUFFIX_SDK))
	{
		*error = zbx_strdup(*error, "Invalid URL: missing '" VMWARE_URL_SUFFIX_SDK "'.");
	}
#undef VMWARE_URL_SUFFIX_SDK
}

#define VMWARE_VALIDATE_EMPTY(field, field_str)						\
do											\
{											\
	if ('\0' == *field)								\
	{										\
		if (NULL == *error)							\
			*error = zbx_dsprintf(*error, "Empty %s", field_str);		\
		else									\
			*error = zbx_dsprintf(*error, "%s, %s", *error, field_str);	\
	}										\
} while(0)

/*******************************************************************************
 *                                                                             *
 * Parameters: service               - [IN] vmware service                     *
 *             easyhandle            - [IN] CURL handle                        *
 *             page                  - [IN] CURL output buffer                 *
 *             config_source_ip      - [IN]                                    *
 *             config_vmware_timeout - [IN]                                    *
 *             error                 - [OUT] error message in case of failure  *
 *                                                                             *
 * Return value: SUCCEED - authentication was completed successfully           *
 *               FAIL    - authentication process has failed                   *
 *                                                                             *
 * Comments: If service type is unknown this function will attempt to          *
 *           determine the right service type by trying to login with vCenter  *
 *           and vSphere session managers.                                     *
 *                                                                             *
 *******************************************************************************/
int	vmware_service_authenticate(zbx_vmware_service_t *service, CURL *easyhandle, ZBX_HTTPPAGE *page,
		const char *config_source_ip, int config_vmware_timeout, char **error)
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

	VMWARE_VALIDATE_EMPTY(service->url, "URL");
	VMWARE_VALIDATE_EMPTY(service->username, "username");
	VMWARE_VALIDATE_EMPTY(service->password, "password");

	if (NULL != *error)
	{
		*error = zbx_strdcat(*error, ".");
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEFUNCTION, curl_write_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEDATA, page)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_PRIVATE, page)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HEADERFUNCTION,
					curl_header_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, service->url)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT,
					(long)config_vmware_timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_https(easyhandle, error))
		goto out;

	if (NULL != config_source_ip)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_INTERFACE, config_source_ip)))
		{
			*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
			goto out;
		}
	}

	username_esc = zbx_xml_escape_dyn(service->username);
	password_esc = zbx_xml_escape_dyn(service->password);

	if (ZBX_VMWARE_TYPE_UNKNOWN == service->type)
	{
		/* try to detect the service type first using vCenter service manager object */
		zbx_snprintf(xml, sizeof(xml), ZBX_POST_VMWARE_AUTH,
				get_vmware_service_objects()[ZBX_VMWARE_TYPE_VCENTER].session_manager,
				username_esc, password_esc);

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, xml, &doc, NULL, error) && NULL == doc)
		{
			vmware_validate_url_suffix(service->url, error);
			goto out;
		}

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

		if (0 != strcmp(error_object, get_vmware_service_objects()[ZBX_VMWARE_TYPE_VCENTER].session_manager))
			goto out;

		service->type = ZBX_VMWARE_TYPE_VSPHERE;
		zbx_free(*error);
	}

	zbx_snprintf(xml, sizeof(xml), ZBX_POST_VMWARE_AUTH,
			get_vmware_service_objects()[service->type].session_manager, username_esc, password_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, xml, NULL, NULL, error))
	{
		vmware_validate_url_suffix(service->url, error);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(error_object);
	zbx_free(username_esc);
	zbx_free(password_esc);
	zbx_xml_doc_free(doc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#	undef ZBX_POST_VMWARE_AUTH
}

#undef VMWARE_VALIDATE_EMPTY

/******************************************************************************
 *                                                                            *
 * Purpose: closes unused connection with vCenter                             *
 *                                                                            *
 * Parameters: service    - [IN] vmware service                               *
 *             easyhandle - [IN] CURL handle                                  *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_logout(zbx_vmware_service_t *service, CURL *easyhandle, char **error)
{
#	define ZBX_POST_VMWARE_LOGOUT						\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:Logout>"							\
			"<ns0:_this type=\"SessionManager\">%s</ns0:_this>"	\
		"</ns0:Logout>"							\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN];

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_LOGOUT,
			get_vmware_service_objects()[service->type].session_manager);

	return zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error);

#	undef ZBX_POST_VMWARE_LOGOUT
}

int	zbx_property_collection_init(CURL *easyhandle, const char *property_collection_query,
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

int	zbx_property_collection_next(const char *fn_parent, zbx_property_collection_iter *iter, xmlDoc **xdoc,
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

	token_esc = zbx_xml_escape_dyn(iter->token);
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

void	zbx_property_collection_free(zbx_property_collection_iter *iter)
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
 * Parameters: easyhandle - [IN] CURL handle                                  *
 *             version    - [OUT] version of instance                         *
 *             fullname   - [OUT] fullname of instance                        *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - contents were retrieved successfully               *
 *               FAIL    - content retrieval failed                           *
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

#	define ZBX_XPATH_VMWARE_ABOUT(property)							\
		"/*/*/*/*/*[local-name()='about']/*[local-name()='" property "']"

	xmlDoc	*doc = NULL;

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_VMWARE_CONTENTS, &doc, NULL, error))
	{
		zbx_xml_doc_free(doc);
		return FAIL;
	}

	*version = zbx_xml_doc_read_value(doc, ZBX_XPATH_VMWARE_ABOUT("version"));
	*fullname = zbx_xml_doc_read_value(doc, ZBX_XPATH_VMWARE_ABOUT("fullName"));
	zbx_xml_doc_free(doc);

	if (NULL == *version)
	{
		*error = zbx_strdup(*error, "VMware Virtual Center is not ready.");
		return FAIL;
	}

	return SUCCEED;

#	undef ZBX_POST_VMWARE_CONTENTS
#	undef ZBX_XPATH_VMWARE_ABOUT
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets alarm details                                                *
 *                                                                            *
 * Parameters: service    - [IN] vmware service                               *
 *             easyhandle - [IN] CURL handle                                  *
 *             alarm_id   - [IN] alarm details                                *
 *             details    - [IN/OUT] alarms cache data                        *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: index - element id in vector                                 *
 *               FAIL  - operation has failed                                 *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_alarm_details_update(const zbx_vmware_service_t *service, CURL *easyhandle,
		const char * alarm_id, zbx_vector_vmware_alarm_details_ptr_t *details, char **error)
{
#	define ZBX_POST_VMWARE_GET_ALARMS						\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:RetrievePropertiesEx>"						\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"		\
			"<ns0:specSet>"							\
				"<ns0:propSet>"						\
					"<ns0:type>Alarm</ns0:type>"			\
					"<ns0:pathSet>info.name</ns0:pathSet>"		\
					"<ns0:pathSet>info.systemName</ns0:pathSet>"	\
					"<ns0:pathSet>info.description</ns0:pathSet>"	\
					"<ns0:pathSet>info.enabled</ns0:pathSet>"	\
				"</ns0:propSet>"					\
				"<ns0:objectSet>"					\
					"<ns0:obj type=\"Alarm\">%s</ns0:obj>"		\
				"</ns0:objectSet>"					\
			"</ns0:specSet>"						\
			"<ns0:options/>"						\
		"</ns0:RetrievePropertiesEx>"						\
		ZBX_POST_VSPHERE_FOOTER

	xmlDoc				*doc_details = NULL;
	zbx_vmware_alarm_details_t	*detail, cmp = {.alarm = (char *)alarm_id};
	int				ret = FAIL;
	char				tmp[MAX_STRING_LEN], *value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() alarm:%s", __func__, alarm_id);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_GET_ALARMS,
			get_vmware_service_objects()[service->type].property_collector, alarm_id);

	if (FAIL == zbx_soap_post(__func__, easyhandle, tmp, &doc_details, NULL, error))
		goto out;

	detail = (zbx_vmware_alarm_details_t *)zbx_malloc(NULL, sizeof(zbx_vmware_alarm_details_t));
	detail->alarm = zbx_strdup(NULL, alarm_id);

	if (NULL == (detail->name = zbx_xml_doc_read_value(doc_details, ZBX_XPATH_PROP_NAME("info.name"))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() alarm:%s not present 'info.name'", __func__, alarm_id);
		detail->name = zbx_strdup(NULL, "");
	}

	if (NULL == (detail->system_name = zbx_xml_doc_read_value(doc_details,
			ZBX_XPATH_PROP_NAME("info.systemName"))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() alarm:%s not present 'info.systemName'", __func__, alarm_id);
		detail->system_name = zbx_strdup(NULL, "");
	}

	if (NULL == (detail->description = zbx_xml_doc_read_value(doc_details,
			ZBX_XPATH_PROP_NAME("info.description"))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() alarm:%s not present 'info.description'", __func__, alarm_id);
		detail->description = zbx_strdup(NULL, "");
	}

	if (NULL == (value = zbx_xml_doc_read_value(doc_details, ZBX_XPATH_PROP_NAME("info.enabled"))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() alarm:%s not present 'info.enabled'", __func__, alarm_id);
		detail->enabled = 0;
	}
	else
	{
		detail->enabled = 0 == strcmp(value, "true") ? 1 : 0;
		zbx_free(value);
	}

	zbx_vector_vmware_alarm_details_ptr_append(details, detail);
	zbx_vector_vmware_alarm_details_ptr_sort(details, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC);

	if (FAIL == (ret = zbx_vector_vmware_alarm_details_ptr_bsearch(details, &cmp,
			ZBX_DEFAULT_STR_PTR_COMPARE_FUNC)))
	{
		*error = zbx_dsprintf(*error, "Cannot update alarm details:%s", alarm_id);
	}
out:
	zbx_xml_doc_free(doc_details);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() index:%d", __func__, ret);

	return ret;

#	undef ZBX_POST_VMWARE_GET_ALARMS
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets open alarms and their details                                *
 *                                                                            *
 * Parameters: func_parent - [IN] parent function name                        *
 *             service     - [IN] vmware service                              *
 *             easyhandle  - [IN] CURL handle                                 *
 *             xdoc        - [IN] xml doc with info about alarms              *
 *             node        - [IN] xml node with info about alarms             *
 *             ids         - [IN] linked alarms ids                           *
 *             alarms_data - [IN/OUT] all alarms with cache                   *
 *             error       - [OUT] error message in case of failure           *
 *                                                                            *
 * Return value: SUCCEED   - operation has completed successfully             *
 *               FAIL      - operation has failed                             *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_alarms_data(const char *func_parent, const zbx_vmware_service_t *service,
		CURL *easyhandle, xmlDoc *xdoc, xmlNode *node, zbx_vector_str_t *ids,
		zbx_vmware_alarms_data_t *alarms_data, char **error)
{
	int 		ret = SUCCEED;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj = NULL;
	xmlNodeSetPtr	nodeset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), func_parent:'%s'", __func__, func_parent);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == node && NULL == (node = zbx_xml_doc_get(xdoc, ZBX_XPATH_PROP_NAME("triggeredAlarmState"))))
		goto clean;

	xpathCtx->node = node;

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XNN("AlarmState"), xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_alarm_ptr_reserve(alarms_data->alarms,
			(size_t)(alarms_data->alarms->values_num + nodeset->nodeNr));

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		char				*value;
		int				j;
		zbx_vmware_alarm_t		*alarm;
		zbx_vmware_alarm_details_t	detail_cmp;

		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XNN("alarm"))))
		{
			ret = FAIL;
			*error = zbx_strdup(*error, "Cannot get alarms info.");
			break;
		}

		detail_cmp.alarm = value;

		if (FAIL == (j = zbx_vector_vmware_alarm_details_ptr_bsearch(&alarms_data->details, &detail_cmp,
				ZBX_DEFAULT_STR_PTR_COMPARE_FUNC)) &&
				FAIL == (j = vmware_service_alarm_details_update(service, easyhandle, value,
				&alarms_data->details, error)))
		{
			zbx_str_free(value);
			ret = FAIL;
			break;
		}

		zbx_str_free(value);

		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XNN("key"))))
		{
			*error = zbx_strdup(*error, "Cannot get alarms key.");
			ret = FAIL;
			break;
		}

		alarm = (zbx_vmware_alarm_t*)zbx_malloc(NULL, sizeof(zbx_vmware_alarm_t));
		alarm->key = value;
		alarm->name = zbx_strdup(NULL, alarms_data->details.values[j]->name);
		alarm->system_name = zbx_strdup(NULL, alarms_data->details.values[j]->system_name);
		alarm->description = zbx_strdup(NULL, alarms_data->details.values[j]->description);

		if (NULL == (alarm->overall_status = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i],
				ZBX_XNN("overallStatus"))))
		{
			alarm->overall_status = zbx_strdup(NULL, "");
		}

		if (NULL == (alarm->time = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XNN("time"))))
			alarm->time = zbx_strdup(NULL, "");

		alarm->enabled = alarms_data->details.values[j]->enabled;
		value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XNN("acknowledged"));
		alarm->acknowledged = (NULL != value && 0 == strcmp(value, "true") ? 1 : 0);
		zbx_free(value);

		zbx_vector_vmware_alarm_ptr_append(alarms_data->alarms, alarm);
		zbx_vector_str_append(ids, zbx_strdup(NULL, alarm->key));
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() func_parent:'%s' found:%d total:%d", __func__, func_parent,
			ids->values_num, alarms_data->alarms->values_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort Datacenter vector by id                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_dc_id_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datacenter_t	*dc1 = *(const zbx_vmware_datacenter_t * const *)d1;
	const zbx_vmware_datacenter_t	*dc2 = *(const zbx_vmware_datacenter_t * const *)d2;

	return strcmp(dc1->id, dc2->id);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort custom attributes vector by name         *
 *                                                                            *
 ******************************************************************************/
int	vmware_custom_attr_compare_name(const void *a1, const void *a2)
{
	const zbx_vmware_custom_attr_t	*attr1 = *(const zbx_vmware_custom_attr_t * const *)a1;
	const zbx_vmware_custom_attr_t	*attr2 = *(const zbx_vmware_custom_attr_t * const *)a2;

	return strcmp(attr1->name, attr2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort DVSwitch vector by uuid                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_dvs_uuid_compare(const void *d1, const void *d2)
{
	const zbx_vmware_dvswitch_t	*dvs1 = *(const zbx_vmware_dvswitch_t * const *)d1;
	const zbx_vmware_dvswitch_t	*dvs2 = *(const zbx_vmware_dvswitch_t * const *)d2;

	return strcmp(dvs1->uuid, dvs2->uuid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort DVSwitch vector by uuid                  *
 *                                                                            *
 ******************************************************************************/
static int	vmware_cq_instance_id_compare(const void *d1, const void *d2)
{
	int	ret;

	const zbx_vmware_cq_value_t	*prop1 = *(const zbx_vmware_cq_value_t * const *)d1;
	const zbx_vmware_cq_value_t	*prop2 = *(const zbx_vmware_cq_value_t * const *)d2;

	if (0 == (ret = strcmp(prop1->instance->soap_type, prop2->instance->soap_type)))
		ret = strcmp(prop1->instance->id, prop2->instance->id);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: comparison function to sort datastore names vector by name        *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_dsname_compare(const void *d1, const void *d2)
{
	const zbx_vmware_dsname_t	*ds1 = *(const zbx_vmware_dsname_t * const *)d1;
	const zbx_vmware_dsname_t	*ds2 = *(const zbx_vmware_dsname_t * const *)d2;

	return strcmp(ds1->name, ds2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: comparison function to sort Datastore names vector by UUID        *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_dsname_compare_uuid(const void *d1, const void *d2)
{
	const zbx_vmware_dsname_t	*ds1 = *(const zbx_vmware_dsname_t * const *)d1;
	const zbx_vmware_dsname_t	*ds2 = *(const zbx_vmware_dsname_t * const *)d2;

	return strcmp(ds1->uuid, ds2->uuid);
}

int	zbx_vmware_pnic_compare(const void *v1, const void *v2)
{
	const zbx_vmware_pnic_t		*nic1 = *(const zbx_vmware_pnic_t * const *)v1;
	const zbx_vmware_pnic_t		*nic2 = *(const zbx_vmware_pnic_t * const *)v2;

	return strcmp(nic1->name, nic2->name);
}

/*******************************************************************************
 *                                                                             *
 * Purpose: retrieves list of vmware service clusters and resource pools       *
 *                                                                             *
 * Parameters: service      - [IN] vmware service                              *
 *             easyhandle   - [IN] CURL handle                                 *
 *             cluster_data - [OUT] pointer to output variable                 *
 *             clusters     - [OUT] pointer to resulting clusters vector       *
 *             rp_chunks    - [OUT] pointer to resulting resource pool vector  *
 *             alarms_data  - [OUT] vector with all alarms                     *
 *             error        - [OUT] error message in case of failure           *
 *                                                                             *
 * Return value: SUCCEED - operation has completed successfully                *
 *               FAIL    - operation has failed                                *
 *                                                                             *
 *******************************************************************************/
static int	vmware_service_process_cluster_data(zbx_vmware_service_t *service, CURL *easyhandle,
		xmlDoc *cluster_data, zbx_vector_vmware_cluster_ptr_t *clusters,
		zbx_vector_vmware_rpool_chunk_ptr_t *rp_chunks, zbx_vmware_alarms_data_t *alarms_data, char **error)
{
#	define ZBX_XPATH_GET_RESOURCEPOOL_PARENTID								\
		ZBX_XPATH_PROP_NAME_NODE("parent") "[@type='ResourcePool']"
#	define ZBX_XPATH_GET_NON_RESOURCEPOOL_PARENTID								\
		ZBX_XPATH_PROP_NAME_NODE("parent") "[@type!='ResourcePool']"

	int			ret;
	char			*id_esc, tmp[MAX_STRING_LEN * 2];
	zbx_vmware_cluster_t	*cluster;
	zbx_vector_str_t	rp_ids, ids;
	xmlNode			*node;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&ids);

	zbx_xml_read_values(cluster_data, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_CLUSTER), &ids);
	zbx_vector_vmware_cluster_ptr_reserve(clusters, (size_t)(clusters->values_alloc + ids.values_num));

	for (int i = 0; i < ids.values_num; i++)
	{
		char	*name;

		id_esc = zbx_xml_escape_dyn(ids.values[i]);
		zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_PROP_OBJECT_ID(ZBX_VMWARE_SOAP_CLUSTER, "[text()='%s']"),
				id_esc);
		zbx_str_free(id_esc);

		if (NULL == (node = zbx_xml_doc_get(cluster_data, tmp)) ||
				NULL == (name = zbx_xml_node_read_value(cluster_data, node,
				ZBX_XPATH_PROP_NAME_NODE("name"))))
		{
			continue;
		}

		cluster = (zbx_vmware_cluster_t *)zbx_malloc(NULL, sizeof(zbx_vmware_cluster_t));
		cluster->id = zbx_strdup(NULL, ids.values[i]);
		cluster->name = name;
		cluster->status = NULL;
		zbx_vector_str_create(&cluster->dss_uuid);
		zbx_vector_str_create(&cluster->alarm_ids);

		if (SUCCEED != vmware_service_get_alarms_data(__func__, service, easyhandle, cluster_data,
				zbx_xml_node_get(cluster_data, node, ZBX_XPATH_PROP_NAME_NODE("triggeredAlarmState")),
				&cluster->alarm_ids, alarms_data, error))
		{
			vmware_cluster_free(cluster);
			ret = FAIL;
			goto out;
		}

		zbx_vector_vmware_cluster_ptr_append(clusters, cluster);
	}

	zbx_vector_str_create(&rp_ids);
	zbx_xml_read_values(cluster_data, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_RESOURCEPOOL), &rp_ids);
	zbx_vector_vmware_rpool_chunk_ptr_reserve(rp_chunks, (size_t)(rp_chunks->values_num + rp_ids.values_num));

	for (int i = 0; i < rp_ids.values_num; i++)
	{
		zbx_vmware_rpool_chunk_t	*rp_chunk;

		rp_chunk = (zbx_vmware_rpool_chunk_t *)zbx_malloc(NULL, sizeof(zbx_vmware_rpool_chunk_t));

		id_esc = zbx_xml_escape_dyn(rp_ids.values[i]);
		zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_PROP_OBJECT_ID(ZBX_VMWARE_SOAP_RESOURCEPOOL, "[text()='%s']"),
				id_esc);
		zbx_str_free(id_esc);

		if (NULL == (node = zbx_xml_doc_get(cluster_data, tmp)) || NULL == (
				rp_chunk->name = zbx_xml_node_read_value(cluster_data, node,
				ZBX_XPATH_PROP_NAME_NODE("name"))))
		{
			zbx_free(rp_chunk);
			continue;
		}

		if (NULL == (rp_chunk->first_parentid = zbx_xml_node_read_value(cluster_data , node,
				ZBX_XPATH_GET_RESOURCEPOOL_PARENTID)))
		{
			if (NULL == (rp_chunk->first_parentid = zbx_xml_node_read_value(cluster_data , node,
					ZBX_XPATH_GET_NON_RESOURCEPOOL_PARENTID)))
			{
				zbx_free(rp_chunk->name);
				zbx_free(rp_chunk);
				continue;
			}

			rp_chunk->parent_is_rp = 0;
		}
		else
			rp_chunk->parent_is_rp = 1;

		rp_chunk->id = zbx_strdup(NULL, rp_ids.values[i]);
		rp_chunk->path = rp_chunk->parentid = NULL;
		zbx_vector_vmware_rpool_chunk_ptr_append(rp_chunks, rp_chunk);
	}

	zbx_vector_str_clear_ext(&rp_ids, zbx_str_free);
	zbx_vector_str_destroy(&rp_ids);

	ret = SUCCEED;
out:
	zbx_vector_str_clear_ext(&ids, zbx_str_free);
	zbx_vector_str_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s cl:%d rp:%d", __func__, zbx_result_string(ret),
			clusters->values_num, rp_chunks->values_num);

	return ret;

#	undef ZBX_XPATH_GET_RESOURCEPOOL_PARENTID
#	undef ZBX_XPATH_GET_NON_RESOURCEPOOL_PARENTID
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves status of specified vmware cluster                      *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL handle                                  *
 *             datastores - [IN] all available datastores                     *
 *             cluster    - [IN/OUT]                                          *
 *             cq_values  - [IN/OUT] vector with custom query entries         *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_cluster_state(CURL *easyhandle, const zbx_vector_vmware_datastore_ptr_t *datastores,
		zbx_vmware_cluster_t *cluster, zbx_vector_cq_value_ptr_t *cq_values, char **error)
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
					"<ns0:pathSet>datastore</ns0:pathSet>"				\
					"%s"								\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"ClusterComputeResource\">%s</ns0:obj>"		\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options></ns0:options>"							\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char				*tmp, *clusterid_esc, *cq_prop;
	int				ret;
	xmlDoc				*doc = NULL;
	zbx_vector_cq_value_ptr_t	cqvs;
	zbx_vector_str_t		ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() clusterid:'%s'", __func__, cluster->id);

	zbx_vector_str_create(&ids);
	zbx_vector_cq_value_ptr_create(&cqvs);
	clusterid_esc = zbx_xml_escape_dyn(cluster->id);
	cq_prop = vmware_cq_prop_soap_request(cq_values, ZBX_VMWARE_SOAP_CLUSTER, cluster->id, &cqvs);

	tmp = zbx_dsprintf(NULL, ZBX_POST_VMWARE_CLUSTER_STATUS, cq_prop, clusterid_esc);

	zbx_str_free(cq_prop);
	zbx_str_free(clusterid_esc);
	ret = zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error);
	zbx_str_free(tmp);

	if (FAIL == ret)
		goto out;

	cluster->status = zbx_xml_doc_read_value(doc, ZBX_XPATH_PROP_NAME("summary.overallStatus"));

	if (0 != cqvs.values_num)
		vmware_service_cq_prop_value(__func__, doc, &cqvs);

	zbx_xml_read_values(doc, ZBX_XPATH_PROP_NAME("datastore") "/*", &ids);

	for (int i = 0; i < ids.values_num; i++)
	{
		int			j;
		zbx_vmware_datastore_t	ds_cmp;

		ds_cmp.id = ids.values[i];

		if (FAIL == (j = zbx_vector_vmware_datastore_ptr_bsearch(datastores, &ds_cmp, vmware_ds_id_compare)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Datastore \"%s\" not found on cluster \"%s\".", __func__,
					ds_cmp.id, cluster->id);
			continue;
		}

		zbx_vector_str_append(&cluster->dss_uuid, zbx_strdup(NULL, datastores->values[j]->uuid));
	}
out:
	zbx_vector_cq_value_ptr_destroy(&cqvs);
	zbx_vector_str_clear_ext(&ids, zbx_str_free);
	zbx_vector_str_destroy(&ids);
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_CLUSTER_STATUS
}

/*******************************************************************************
 *                                                                             *
 * Purpose: creates lists of vmware cluster and resource pool objects          *
 *                                                                             *
 * Parameters: service       - [IN] vmware service                             *
 *             easyhandle    - [IN] CURL handle                                *
 *             datastores    - [IN] all available datastores                   *
 *             cq_values     - [IN/OUT] vector with custom query entries       *
 *             clusters      - [OUT] pointer to resulting clusters vector      *
 *             resourcepools - [OUT] pointer to resulting resource pool vector *
 *             alarms_data   - [OUT] vector with all alarms                    *
 *             error         - [OUT] error message in case of failure          *
 *                                                                             *
 * Return value: SUCCEED - operation has completed successfully                *
 *               FAIL    - operation has failed                                *
 *                                                                             *
 *******************************************************************************/
static int	vmware_service_get_clusters_and_resourcepools(zbx_vmware_service_t *service, CURL *easyhandle,
		const zbx_vector_vmware_datastore_ptr_t *datastores, zbx_vector_cq_value_ptr_t *cq_values,
		zbx_vector_vmware_cluster_ptr_t *clusters, zbx_vector_vmware_resourcepool_ptr_t *resourcepools,
		zbx_vmware_alarms_data_t *alarms_data, char **error)
{
#	define ZBX_POST_VCENTER_CLUSTER								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx xsi:type=\"ns0:RetrievePropertiesExRequestType\">"	\
			"<ns0:_this type=\"PropertyCollector\">propertyCollector</ns0:_this>"	\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>ClusterComputeResource</ns0:type>"		\
					"<ns0:pathSet>name</ns0:pathSet>"			\
					"<ns0:pathSet>triggeredAlarmState</ns0:pathSet>"	\
				"</ns0:propSet>"						\
				"<ns0:propSet>"							\
					"<ns0:type>ResourcePool</ns0:type>"			\
					"<ns0:pathSet>resourcePool</ns0:pathSet>"		\
					"<ns0:pathSet>name</ns0:pathSet>"			\
					"<ns0:pathSet>parent</ns0:pathSet>"			\
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
						"<ns0:selectSet>"				\
							"<ns0:name>rp</ns0:name>"		\
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
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>rp</ns0:name>"			\
						"<ns0:type>ResourcePool</ns0:type>"		\
						"<ns0:path>resourcePool</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>rp</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	int					ret = FAIL;
	xmlDoc					*cluster_data = NULL;
	zbx_property_collection_iter		*iter = NULL;
	zbx_vector_vmware_rpool_chunk_ptr_t	rp_chunks;
	zbx_vector_vmware_cluster_ptr_t		cl_chunks;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_property_collection_init(easyhandle, ZBX_POST_VCENTER_CLUSTER, "propertyCollector",
			__func__, &iter, &cluster_data, error))
	{
		goto out;
	}

	zbx_vector_vmware_rpool_chunk_ptr_create(&rp_chunks);
	zbx_vector_vmware_cluster_ptr_create(&cl_chunks);

	if (SUCCEED != vmware_service_process_cluster_data(service, easyhandle, cluster_data, &cl_chunks, &rp_chunks,
			alarms_data, error))
	{
		goto clean;
	}

	while (NULL != iter->token)
	{
		zbx_xml_doc_free(cluster_data);

		if (SUCCEED != zbx_property_collection_next(__func__, iter, &cluster_data, error))
			goto clean;

		if (SUCCEED != vmware_service_process_cluster_data(service, easyhandle, cluster_data, &cl_chunks,
				&rp_chunks, alarms_data, error))
		{
			goto clean;
		}
	}

	zbx_vector_vmware_rpool_chunk_ptr_sort(&rp_chunks, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC);

	for (int i = 0; i < rp_chunks.values_num; i++)
	{
		int				k;
		zbx_vmware_resourcepool_t	*rpool;
		zbx_vmware_rpool_chunk_t	rp_parent, *rp_chunk = rp_chunks.values[i];

		if (0 == rp_chunk->parent_is_rp)	/* skipped the top (default) resource pool name */
			continue;

		rpool = (zbx_vmware_resourcepool_t*)zbx_malloc(NULL, sizeof(zbx_vmware_resourcepool_t));
		rpool->id = zbx_strdup(NULL, rp_chunk->id);
		rpool->path = zbx_strdup(NULL, rp_chunk->name);
		rpool->vm_num = 0;

		rp_parent.id = rp_chunk->first_parentid;

		while (FAIL != (k = zbx_vector_vmware_rpool_chunk_ptr_bsearch(&rp_chunks, &rp_parent,
				ZBX_DEFAULT_STR_PTR_COMPARE_FUNC)))
		{
			zbx_vmware_rpool_chunk_t	*rp_next = rp_chunks.values[k];

			if (NULL != rp_next->path)
				rpool->path = zbx_dsprintf(rpool->path, "%s/%s", rp_next->path, rpool->path);

			if (0 == rp_next->parent_is_rp || NULL != rp_next->path)
			{
				rpool->parentid = zbx_strdup(NULL, 0 == rp_next->parent_is_rp ?
						rp_next->first_parentid : rp_next->parentid);
				zbx_vector_vmware_resourcepool_ptr_append(resourcepools, rpool);
				rp_chunk->path = rpool->path;
				rp_chunk->parentid = rpool->parentid;
				break;
			}

			rpool->path = zbx_dsprintf(rpool->path, "%s/%s", rp_next->name, rpool->path);
			rp_parent.id = rp_next->first_parentid;
		}

		/* free rpool if it was not added to resourcepool vector */
		if (NULL == rp_chunk->path)
			vmware_resourcepool_free(rpool);
	}

	zbx_vector_vmware_resourcepool_ptr_sort(resourcepools, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC);
	zbx_vector_vmware_cluster_ptr_reserve(clusters, (size_t)(clusters->values_alloc + cl_chunks.values_num));

	for (int i = cl_chunks.values_num - 1; i >= 0 ; i--)
	{
		zbx_vmware_cluster_t	*cluster = (zbx_vmware_cluster_t*)(cl_chunks.values[i]);

		if (SUCCEED != vmware_service_get_cluster_state(easyhandle, datastores, cluster, cq_values, error))
			goto clean;

		zbx_vector_vmware_cluster_ptr_append(clusters, cluster);
		zbx_vector_vmware_cluster_ptr_remove_noorder(&cl_chunks, i);
	}

	ret = SUCCEED;
clean:
	zbx_xml_doc_free(cluster_data);
	zbx_vector_vmware_rpool_chunk_ptr_clear_ext(&rp_chunks, vmware_rp_chunk_free);
	zbx_vector_vmware_rpool_chunk_ptr_destroy(&rp_chunks);
	zbx_vector_vmware_cluster_ptr_clear_ext(&cl_chunks, vmware_cluster_free);
	zbx_vector_vmware_cluster_ptr_destroy(&cl_chunks);
out:
	zbx_property_collection_free(iter);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s found cl:%d rp:%d", __func__, zbx_result_string(ret),
			clusters->values_num, resourcepools->values_num);

	return ret;

#	undef ZBX_POST_VCENTER_CLUSTER
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes vmware service object                                 *
 *                                                                            *
 * Parameters: service    - [IN] vmware service                               *
 *             easyhandle - [IN] CURL handle                                  *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 * Comments: While the service object can't be accessed from other processes  *
 *           during initialization it's still processed outside vmware locks  *
 *           and therefore must not allocate/free shared memory.              *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_initialize(zbx_vmware_service_t *service, CURL *easyhandle, char **error)
{
	char				*version_without_major, *version_update, *version = NULL, *fullname = NULL;
	zbx_vector_vmware_counter_ptr_t	counters;
	zbx_vector_vmware_key_value_t	evt_severities;
	int				ret = FAIL;

	zbx_vector_vmware_counter_ptr_create(&counters);
	zbx_vector_vmware_key_value_create(&evt_severities);

	if (SUCCEED != vmware_service_get_contents(easyhandle, &version, &fullname, error))
		goto out;

	if (0 != (service->state & ZBX_VMWARE_STATE_READY) && 0 == strcmp(service->version, version))
	{
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != vmware_service_get_perf_counters(service, easyhandle, &counters, error))
		goto out;

	if (SUCCEED != vmware_service_get_evt_severity(service, easyhandle, &evt_severities, error))
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
		zbx_hashset_iter_t	iter;
		zbx_vmware_counter_t	*counter;

		zbx_hashset_iter_reset(&service->counters, &iter);

		while (NULL != (counter = (zbx_vmware_counter_t *)zbx_hashset_iter_next(&iter)))
			vmware_counter_shared_clean(counter);

		zbx_hashset_clear(&service->counters);
	}

	if (0 != service->eventlog.evt_severities.num_data)
	{
		zbx_hashset_iter_t	iter;
		zbx_vmware_key_value_t	*evt_severity;

		zbx_hashset_iter_reset(&service->eventlog.evt_severities, &iter);

		while (NULL != (evt_severity = (zbx_vmware_key_value_t *)zbx_hashset_iter_next(&iter)))
			zbx_shmem_vmware_key_value_free(evt_severity);

		zbx_hashset_clear(&service->eventlog.evt_severities);
	}

	service->fullname = vmware_shared_strdup(fullname);
	vmware_counters_shared_copy(&service->counters, &counters);
	service->version = vmware_shared_strdup(version);
	service->major_version = (unsigned short)atoi(version);
	vmware_shmem_evtseverity_copy(&service->eventlog.evt_severities , &evt_severities);

	/* version should have the "x.y.z" format, but there is also an "x.y Un" format in nature */
	/* according to https://www.vmware.com/support/policies/version.html */
	if (NULL == (version_without_major = strchr(version, '.')) ||
			NULL == (version_update = strpbrk(++version_without_major, ".U")))
	{
		*error = zbx_dsprintf(*error, "Invalid version: %s.", version);
		goto unlock;
	}

	service->minor_version = (unsigned short)atoi(version_without_major);
	service->update_version = (unsigned short)atoi(++version_update);

	ret = SUCCEED;
unlock:
	zbx_vmware_unlock();
out:
	zbx_free(version);
	zbx_free(fullname);

	zbx_vector_vmware_counter_ptr_clear_ext(&counters, vmware_counter_free);
	zbx_vector_vmware_counter_ptr_destroy(&counters);
	zbx_vector_vmware_key_value_clear_ext(&evt_severities, zbx_vmware_key_value_free);
	zbx_vector_vmware_key_value_destroy(&evt_severities);

	return ret;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: moves custom query response to shared memory                       *
 *                                                                             *
 * Parameters: cq_values - [IN] vector with custom query entries and responses *
 *                                                                             *
 *******************************************************************************/
static void	vmware_service_copy_cust_query_response(zbx_vector_cq_value_ptr_t *cq_values)
{
	for (int i = 0; i < cq_values->values_num; i++)
	{
		if (ZBX_VMWARE_CQV_ERROR == cq_values->values[i]->status)
		{
			vmware_shared_strfree(cq_values->values[i]->instance->error);
			cq_values->values[i]->instance->error = vmware_shared_strdup(cq_values->values[i]->response);
			cq_values->values[i]->instance->state = ZBX_VMWARE_CQ_ERROR | ZBX_VMWARE_CQ_SEPARATE;
		}
		else if (ZBX_VMWARE_CQV_VALUE == cq_values->values[i]->status)
		{
			vmware_shared_strfree(cq_values->values[i]->instance->value);
			cq_values->values[i]->instance->value = vmware_shared_strdup(cq_values->values[i]->response);
			cq_values->values[i]->instance->state = ZBX_VMWARE_CQ_READY |
					(cq_values->values[i]->instance->state & ZBX_VMWARE_CQ_SEPARATE);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: collects custom requests of selected type                         *
 *                                                                            *
 * Parameters: cust_queries        - [IN] hashset with all type custom        *
 *                                        queries                             *
 *             type                - [IN] type of custom query                *
 *             cq_values           - [OUT] vector with custom query entries   *
 *                                         and responses                      *
 *             cache_update_period - [IN]                                     *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_cust_query_prep(zbx_hashset_t *cust_queries, const zbx_vmware_custom_query_type_t type,
		zbx_vector_cq_value_ptr_t *cq_values, int cache_update_period)
{
	zbx_hashset_iter_t	iter;
	zbx_vmware_cust_query_t	*instance;
	time_t			now = time(NULL);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() cust_queries:%d", __func__, cust_queries->num_data);

	zbx_hashset_iter_reset(cust_queries, &iter);

	while (NULL != (instance = (zbx_vmware_cust_query_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vmware_cq_value_t	*cqv;

		if (instance->query_type != type)
			continue;

		if (0 == (instance->state & ZBX_VMWARE_CQ_NEW) && now - instance->last_pooled > SEC_PER_DAY)
		{
			vmware_shared_cust_query_clean(instance);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		if (0 != (instance->state & ZBX_VMWARE_CQ_PAUSED))
			continue;

		if (0 == (instance->state & ZBX_VMWARE_CQ_NEW) &&
				now - instance->last_pooled > 2 * cache_update_period)
		{
			instance->state |= ZBX_VMWARE_CQ_PAUSED;
			continue;
		}

		cqv = (zbx_vmware_cq_value_t *)zbx_malloc(NULL, sizeof(zbx_vmware_cq_value_t));
		cqv->status = ZBX_VMWARE_CQV_EMPTY;
		cqv->instance = instance;
		cqv->response = NULL;
		zbx_vector_cq_value_ptr_append(cq_values, cqv);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() cq_values:%d", __func__, cq_values->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: loads DVSwitch info from VC                                       *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL handle                                  *
 *             cq_values  - [IN/OUT] vector with custom query entries and     *
 *                                   responses                                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_dvswitch_load(CURL *easyhandle, zbx_vector_cq_value_ptr_t *cq_values)
{
#	define ZBX_POST_FETCH_DV_PORTS										\
		ZBX_POST_VSPHERE_HEADER										\
			"<ns0:FetchDVPorts>"									\
				"<ns0:_this type=\"%s\">%s</ns0:_this>"						\
				"<ns0:criteria>%s</ns0:criteria>"						\
			"</ns0:FetchDVPorts>"									\
		ZBX_POST_VSPHERE_FOOTER

	size_t	offset;
	char	*error, tmp[MAX_STRING_LEN], criteria[MAX_STRING_LEN];
	int	count = 0;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() dvs count:%d", __func__, cq_values->values_num);

	for (int i = 0; i < cq_values->values_num; i++)
	{
		zbx_vmware_cq_value_t	*cqv = cq_values->values[i];
		xmlNode			*node;

		criteria[0] = '\0';
		offset = 0;
		zbx_xml_doc_free(doc);

		for (int j = 0; j < cqv->instance->query_params->values_num; j++)
		{
			char		*name_esc, *value_esc;
			const char	*host_type;

			if (0 == strcmp(cqv->instance->query_params->values[j].name, "host"))
				host_type = " type=\"HostSystem\"";
			else
				host_type = NULL;

			name_esc = zbx_xml_escape_dyn(cqv->instance->query_params->values[j].name);
			value_esc = zbx_xml_escape_dyn(cqv->instance->query_params->values[j].value);
			offset += zbx_snprintf(criteria + offset, sizeof(criteria) - offset, "<ns0:%s%s>%s</ns0:%s>",
					name_esc, ZBX_NULL2EMPTY_STR(host_type), value_esc, name_esc);
			zbx_free(name_esc);
			zbx_free(value_esc);
		}

		zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_FETCH_DV_PORTS, cqv->instance->soap_type, cqv->instance->id,
				criteria);

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, &error))
		{
			cqv->status = ZBX_VMWARE_CQV_ERROR;
			cqv->response = error;
			error = NULL;
			continue;
		}

		if (NULL == (node = zbx_xml_doc_get(doc, "/*/*" ZBX_XPATH_LN("FetchDVPortsResponse"))))
			continue;

		if (0 == strcmp(cqv->instance->mode, "state"))	/* ignore node remove error for empty result */
			zbx_xml_node_remove(doc ,node, ZBX_XNN("returnval") ZBX_XPATH_LN("config"));

		if (SUCCEED != zbx_xmlnode_to_json(node, &cqv->response))
		{
			cqv->response = zbx_strdup(NULL, "Cannot parse FetchDVPortsResponse.");
			cqv->status = ZBX_VMWARE_CQV_ERROR;
			continue;
		}

		cqv->status = ZBX_VMWARE_CQV_VALUE;
		count++;
		zabbix_log(LOG_LEVEL_DEBUG, "%s() SUCCEED id:%s response:%d", __func__, cqv->instance->id,
				(int)strlen(cqv->response));
	}

	zbx_xml_doc_free(doc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() count: %d / %d", __func__, count, cq_values->values_num);
#	undef	ZBX_POST_FETCH_DV_PORTS
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads from xml document property value                            *
 *                                                                            *
 * Parameters: fn_parent - [IN] parent function name                          *
 *             xdoc      - [IN] xml document                                  *
 *             cqvs      - [IN/OUT] custom query entries                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_service_cq_prop_value(const char *fn_parent, xmlDoc *xdoc, zbx_vector_cq_value_ptr_t *cqvs)
{
	for (int i = 0; i < cqvs->values_num; i++)
	{
		char			xpath[MAX_STRING_LEN];
		xmlNode			*node;
		zbx_vmware_cq_value_t	*cqv = cqvs->values[i];

		zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_PROP_NAME("%s"), cqv->instance->key);

		if (NULL == (node = zbx_xml_doc_get(xdoc, xpath)))
		{
			cqv->response = NULL;
			cqv->status = ZBX_VMWARE_CQV_VALUE;
		}
		else if ('\0' != *cqv->instance->mode && 0 == strcmp(cqv->instance->mode, "json"))
		{
			zbx_xmlnode_to_json(node, &cqv->response);
			cqv->status = ZBX_VMWARE_CQV_VALUE;
		}
		else if (NULL != node->xmlChildrenNode && XML_TEXT_NODE == node->xmlChildrenNode->type)
		{
			cqv->response = zbx_xml_node_read_value(xdoc, node, ".");
			cqv->status = ZBX_VMWARE_CQV_VALUE;
		}
		else
		{
			cqv->response = zbx_strdup(NULL, "only scalar values can be returned.");
			cqv->status = ZBX_VMWARE_CQV_ERROR;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() %s id:%s key:%s response length:%d node type:%d", fn_parent,
				ZBX_VMWARE_CQV_ERROR == cqv->status ? "FAIL" : "SUCCEED", cqv->instance->id,
				cqv->instance->key, NULL == cqv->response ? -1 : (int)strlen(cqv->response),
				NULL != node && NULL != node->xmlChildrenNode ? (int)node->xmlChildrenNode->type : -1);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: loads vmware object property info from VC                         *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL handle                                  *
 *             collector  - [IN] name of vmware property collector            *
 *             cq_values  - [IN/OUT] vector with custom query entries and     *
 *                                   responses                                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_props_load(CURL *easyhandle, const char *collector, zbx_vector_cq_value_ptr_t *cq_values)
{
#	define ZBX_POST_OBJ_PROP									\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>%s</ns0:type>"					\
					"<ns0:all>false</ns0:all>"					\
					"<ns0:pathSet>%s</ns0:pathSet>"					\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"%s\">%s</ns0:obj>"				\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options></ns0:options>"							\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	int				total = 0, count = 0;
	xmlDoc				*doc = NULL;
	zbx_vector_cq_value_ptr_t	cq_resp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() props total:%d", __func__, cq_values->values_num);

	zbx_vector_cq_value_ptr_create(&cq_resp);
	zbx_vector_cq_value_ptr_append(&cq_resp, NULL);

	for (int i = 0; i < cq_values->values_num; i++)
	{
		char			*error = NULL, tmp[MAX_STRING_LEN];
		zbx_vmware_cq_value_t	*cqv = cq_values->values[i];

		if (0 == (cqv->instance->state & ZBX_VMWARE_CQ_SEPARATE))
			continue;

		zbx_xml_doc_free(doc);
		zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_OBJ_PROP, collector, cqv->instance->soap_type,
				cqv->instance->key, cqv->instance->soap_type, cqv->instance->id);
		total++;

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, &error))
		{
			cqv->status = ZBX_VMWARE_CQV_ERROR;
			cqv->response = error;
			error = NULL;
			continue;
		}

		cq_resp.values[0] = cqv;
		vmware_service_cq_prop_value(__func__, doc, &cq_resp);
		count++;
	}

	zbx_xml_doc_free(doc);

	zbx_vector_cq_value_ptr_destroy(&cq_resp);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() count: %d / %d", __func__, count, total);

#	undef ZBX_POST_OBJ_PROP
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates part of xml query for soap request                        *
 *                                                                            *
 * Parameters: cq_values - [IN] vector with custom query entries              *
 *             soap_type - [IN] soap type of hv, vm etc                       *
 *             obj_id    - [IN] vmware instance id (hv, vm etc)               *
 *             cqvs      - [OUT] custom query entry                           *
 *                                                                            *
 * Return value: pointer to string with soap sub query                        *
 *                                                                            *
 ******************************************************************************/
char	*vmware_cq_prop_soap_request(const zbx_vector_cq_value_ptr_t *cq_values, const char *soap_type,
		const char *obj_id, zbx_vector_cq_value_ptr_t *cqvs)
{
	char	*buff = zbx_strdup(NULL, "");

	for (int i = 0; i < cq_values->values_num; i++)
	{
		zbx_vmware_cq_value_t	*cq = cq_values->values[i];
		char			tmp[MAX_STRING_LEN / 4];

		if (0 != cqvs->values_num && 0 != strcmp(cq->instance->id, obj_id))
			break;

		if (0 != (cq->instance->state & ZBX_VMWARE_CQ_SEPARATE) || 0 != strcmp(cq->instance->id, obj_id) ||
				0 != strcmp(cq->instance->soap_type, soap_type))
		{
			continue;
		}

		zbx_snprintf(tmp, sizeof(tmp), "<ns0:pathSet>%s</ns0:pathSet>", cq->instance->key);
		buff = zbx_strdcat(buff, tmp);
		zbx_vector_cq_value_ptr_append(cqvs, cq);
	}

	return buff;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets CURL headers for soap request                                *
 *                                                                            *
 * Parameters: easyhandle - [IN] prepared cURL connection handle              *
 *             vc_version - [IN] major version of vc                          *
 *             headers    - [IN/OUT] CURL headers                             *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - headers were set successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	vmware_curl_set_header(CURL *easyhandle, int vc_version, struct curl_slist **headers, char **error)
{
	const char	*soapver;
	CURLoption	opt;
	CURLcode	err;

	if (6 > vc_version)
		soapver = ZBX_XML_HEADER1_V4;
	else
		soapver = ZBX_XML_HEADER1_V6;

	if (NULL != *headers && (*headers)->data[ZBX_XML_HEADER1_VERSION] == soapver[ZBX_XML_HEADER1_VERSION])
		return SUCCEED;

	curl_slist_free_all(*headers);
	*headers = NULL;
	*headers = curl_slist_append(*headers, soapver);
	*headers = curl_slist_append(*headers, ZBX_XML_HEADER2);
	*headers = curl_slist_append(*headers, ZBX_XML_HEADER3);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, *headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Purpose: updates object with new data from vmware service                  *
 *                                                                            *
 * Parameters: service               - [IN] vmware service                    *
 *             config_source_ip      - [IN]                                   *
 *             config_vmware_timeout - [IN]                                   *
 *             cache_update_period   - [IN]                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_update(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout, int cache_update_period)
{
#define ZBX_INIT_UPD_XML_SIZE		(100 * ZBX_KIBIBYTE)
	CURL				*easyhandle = NULL;
	struct curl_slist		*headers = NULL;
	zbx_vmware_data_t		*data;
	zbx_vector_str_t		hvs, dss;
	zbx_vector_cq_value_ptr_t	dvs_query_values, prop_query_values, cust_query_values;
	zbx_vmware_alarms_data_t	alarms_data;
	int				ret = FAIL;
	ZBX_HTTPPAGE			page;	/* 347K/87K */
	char				msg[VMWARE_SHORT_STR_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	data = (zbx_vmware_data_t *)zbx_malloc(NULL, sizeof(zbx_vmware_data_t));
	memset(data, 0, sizeof(zbx_vmware_data_t));
	page.alloc = 0;

	zbx_hashset_create(&data->hvs, 1, vmware_hv_hash, vmware_hv_compare);
	zbx_vector_vmware_cluster_ptr_create(&data->clusters);
	zbx_vector_vmware_datastore_ptr_create(&data->datastores);
	zbx_vector_vmware_datacenter_ptr_create(&data->datacenters);
	zbx_vector_vmware_resourcepool_ptr_create(&data->resourcepools);
	zbx_vector_vmware_dvswitch_ptr_create(&data->dvswitches);
	zbx_vector_cq_value_ptr_create(&dvs_query_values);
	zbx_vector_cq_value_ptr_create(&prop_query_values);
	zbx_vector_str_create(&data->alarm_ids);
	zbx_vector_vmware_alarm_ptr_create(&data->alarms);
	alarms_data.alarms = &data->alarms;
	zbx_vector_vmware_alarm_details_ptr_create(&alarms_data.details);
	zbx_vector_cq_value_ptr_create(&cust_query_values);
	zbx_vector_str_create(&hvs);
	zbx_vector_str_create(&dss);

	zbx_vmware_lock();
	vmware_service_cust_query_prep(&service->cust_queries, VMWARE_DVSWITCH_FETCH_DV_PORTS, &dvs_query_values,
			cache_update_period);
	vmware_service_cust_query_prep(&service->cust_queries, VMWARE_OBJECT_PROPERTY, &prop_query_values,
			cache_update_period);
	zbx_vmware_unlock();

	zbx_vector_cq_value_ptr_sort(&prop_query_values, vmware_cq_instance_id_compare);

	if (NULL == (easyhandle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot initialize cURL library");
		goto out;
	}

	page.alloc = ZBX_INIT_UPD_XML_SIZE;
	page.data = (char *)zbx_malloc(NULL, page.alloc);

	if (SUCCEED != vmware_curl_set_header(easyhandle, service->major_version, &headers, &data->error))
		goto clean;

	if (SUCCEED != vmware_service_authenticate(service, easyhandle, &page, config_source_ip, config_vmware_timeout,
			&data->error))
	{
		goto clean;
	}

	if (SUCCEED != vmware_service_initialize(service, easyhandle, &data->error))
		goto clean;

	/* update headers after VC version detection */
	if (SUCCEED != vmware_curl_set_header(easyhandle, service->major_version, &headers, &data->error))
		goto clean;

	if (SUCCEED != vmware_service_get_hv_ds_dc_dvs_list(service, easyhandle, &alarms_data, &hvs, &dss,
			&data->datacenters, &data->dvswitches, &data->alarm_ids, &data->error))
	{
		goto clean;
	}

	zbx_vector_vmware_datastore_ptr_reserve(&data->datastores, (size_t)(dss.values_num +
			data->datastores.values_alloc));

	for (int i = 0; i < dss.values_num; i++)
	{
		zbx_vmware_datastore_t	*datastore;

		if (NULL != (datastore = vmware_service_create_datastore(service, easyhandle, dss.values[i],
				&prop_query_values, &alarms_data)))
		{
			zbx_vector_vmware_datastore_ptr_append(&data->datastores, datastore);
		}
	}

	zbx_vector_vmware_datastore_ptr_sort(&data->datastores, vmware_ds_id_compare);

	if (ZBX_VMWARE_TYPE_VCENTER == service->type &&
			SUCCEED != vmware_service_get_clusters_and_resourcepools(service, easyhandle, &data->datastores,
			&prop_query_values, &data->clusters, &data->resourcepools, &alarms_data, &data->error))
	{
		goto clean;
	}

	if (SUCCEED != zbx_hashset_reserve(&data->hvs, hvs.values_num))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	for (int i = 0; i < hvs.values_num; i++)
	{
		zbx_vmware_hv_t	hv_local, *hv;

		if (SUCCEED == vmware_service_init_hv(service, easyhandle, hvs.values[i], &data->datastores,
				&data->resourcepools, &prop_query_values, &alarms_data, &hv_local, &data->error))
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

	for (int i = 0; i < data->datastores.values_num; i++)
	{
		zbx_vector_str_uint64_pair_sort(&data->datastores.values[i]->hv_uuids_access,
				zbx_str_uint64_pair_name_compare);
	}

	zbx_vector_vmware_datastore_ptr_sort(&data->datastores, zbx_vmware_ds_uuid_compare);

	vmware_service_dvswitch_load(easyhandle, &dvs_query_values);
	vmware_service_props_load(easyhandle, get_vmware_service_objects()[service->type].property_collector,
			&prop_query_values);
	zbx_vector_vmware_alarm_ptr_sort(&data->alarms, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC);

	if (ZBX_VMWARE_TYPE_VCENTER != service->type)
	{
		data->max_query_metrics = ZBX_VPXD_STATS_MAXQUERYMETRICS;
	}
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

	zbx_vector_vmware_alarm_details_ptr_clear_ext(&alarms_data.details, vmware_alarm_details_free);
	zbx_vector_vmware_alarm_details_ptr_destroy(&alarms_data.details);
	zbx_vector_str_clear_ext(&hvs, zbx_str_free);
	zbx_vector_str_destroy(&hvs);
	zbx_vector_str_clear_ext(&dss, zbx_str_free);
	zbx_vector_str_destroy(&dss);
out:
	zbx_vmware_lock();

	/* remove UPDATING flag and set READY or FAILED flag */
#define ZBX_VMWARE_STATE_MASK		0x0FF
	service->state &= ~ZBX_VMWARE_STATE_MASK;
#undef ZBX_VMWARE_STATE_MASK
	service->state |= (SUCCEED == ret) ? ZBX_VMWARE_STATE_READY : ZBX_VMWARE_STATE_FAILED;

	vmware_data_shared_free(service->data);
	service->data = vmware_shmem_data_dup(data);

	service->lastcheck = time(NULL);
	vmware_service_update_perf_entities(service);
	vmware_service_copy_cust_query_response(&dvs_query_values);
	vmware_service_copy_cust_query_response(&prop_query_values);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		zbx_shmem_dump_stats(LOG_LEVEL_DEBUG, vmware_shmem_get_vmware_mem());

	zbx_snprintf(msg, sizeof(msg), "DC:%d DS:%d CL:%d HV:%d VM:%d DVS:%d Alarms:%d"
			" VMwareCache memory usage (free/strpool/total): " ZBX_FS_UI64 " / " ZBX_FS_UI64 " / "
			ZBX_FS_UI64,
			NULL != service->data ? service->data->datacenters.values_num : 0 ,
			NULL != service->data ? service->data->datastores.values_num : 0 ,
			NULL != service->data ? service->data->clusters.values_num : 0 ,
			NULL != service->data ? service->data->hvs.num_data : 0 ,
			NULL != service->data ? service->data->vms_index.num_data : 0 ,
			NULL != service->data ? service->data->dvswitches.values_num : 0 ,
			NULL != service->data ? service->data->alarms.values_num : 0 ,
			vmware_shmem_get_vmware_mem()->free_size, vmware->strpool_sz,
			vmware_shmem_get_vmware_mem()->total_size);

	zbx_vmware_unlock();

	vmware_data_free(data);
	zbx_vector_cq_value_ptr_clear_ext(&dvs_query_values, zbx_vmware_cq_value_free);
	zbx_vector_cq_value_ptr_destroy(&dvs_query_values);
	zbx_vector_cq_value_ptr_clear_ext(&prop_query_values, zbx_vmware_cq_value_free);
	zbx_vector_cq_value_ptr_destroy(&prop_query_values);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s \tprocessed:" ZBX_FS_SIZE_T " bytes of data. %s", __func__,
			zbx_result_string(ret), (zbx_fs_size_t)page.alloc, msg);

	return ret;
#undef ZBX_INIT_UPD_XML_SIZE
}

/******************************************************************************
 *                                                                            *
 * Parameters: service - [IN] vmware service                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vmware_service_remove(zbx_vmware_service_t *service)
{
	int	index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	zbx_vmware_lock();

	if (FAIL != (index = zbx_vector_vmware_service_ptr_search(&vmware->services, service,
			ZBX_DEFAULT_PTR_COMPARE_FUNC)))
	{
		zbx_vector_vmware_service_ptr_remove(&vmware->services, index);
		vmware_service_shared_free(service);
	}

	zbx_vmware_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	zbx_vmware_jobs_create(zbx_vmware_t *vmw, zbx_vmware_service_t *service);

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware service object                                        *
 *                                                                            *
 * Parameters: url      - [IN] VMware service URL                             *
 *             username - [IN] VMware service username                        *
 *             password - [IN] VMware service password                        *
 *                                                                            *
 * Return value: requested service object or NULL if object is not yet ready  *
 *                                                                            *
 * Comments: VMware lock must be locked with zbx_vmware_lock() function       *
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
	int			now;
	zbx_vmware_service_t	*service = NULL;
	zbx_vmware_t		*vmw = zbx_vmware_get_vmware();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, username, url);

	if (NULL == vmw)
		goto out;

	now = time(NULL);

	for (int i = 0; i < vmw->services.values_num; i++)
	{
		service = (zbx_vmware_service_t *)vmw->services.values[i];

		if (0 == strcmp(service->url, url) && 0 == strcmp(service->username, username) &&
				0 == strcmp(service->password, password))
		{
			service->lastaccess = now;

			/* return NULL if the service is not ready yet */
			if (0 == (service->state & (ZBX_VMWARE_STATE_READY | ZBX_VMWARE_STATE_FAILED)))
				service = NULL;

			if (NULL != service)
				zbx_vmware_jobs_create(vmw, service);

			goto out;
		}
	}

	service = vmware_shmem_vmware_service_malloc();
	memset(service, 0, sizeof(zbx_vmware_service_t));

	service->url = vmware_shared_strdup(url);
	service->username = vmware_shared_strdup(username);
	service->password = vmware_shared_strdup(password);
	service->type = ZBX_VMWARE_TYPE_UNKNOWN;
	service->state = ZBX_VMWARE_STATE_NEW;
	service->lastaccess = now;
	service->eventlog.last_key = ZBX_VMWARE_EVENT_KEY_UNINITIALIZED;
	service->eventlog.skip_old = 0;
	service->eventlog.severity = 0;
	service->eventlog.req_sz = 0;
	service->eventlog.oom = 0;
	service->eventlog.job_revision = 0;
	service->eventlog.expect_num = 0;
	service->jobs_num = 0;
	vmware_shmem_vector_vmware_entity_tags_ptr_create_ext(&service->data_tags.entity_tags);
	service->data_tags.error = NULL;
	service->jobs_flag = ZBX_VMWARE_REQ_UPDATE_ALL;

	vmware_shmem_service_hashset_create(service);

	zbx_vector_vmware_service_ptr_append(&vmw->services, service);
	zbx_vmware_jobs_create(vmw, service);

	/* new service does not have any data - return NULL */
	service = NULL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__,
			zbx_result_string(NULL != service ? SUCCEED : FAIL));

	return service;
}

/******************************************************************************
 *                                                                            *
 * Purpose: starts monitoring custom query of specified entity                *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             soap_type    - [IN] entity type                                *
 *             id           - [IN] entity id                                  *
 *             key          - [IN] custom query id                            *
 *             query_type   - [IN]                                            *
 *             mode         - [IN] mode of output value for custom query      *
 *             query_params - [IN] array of name and value for custom         *
 *                                 query filter                               *
 *                                                                            *
 * Return value: SUCCEED - entity counter was added to monitoring list        *
 *               FAIL    - custom query of specified entity is already being  *
 *                         monitored                                          *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_cust_query_t	*zbx_vmware_service_add_cust_query(zbx_vmware_service_t *service, const char *soap_type,
		const char *id, const char *key, zbx_vmware_custom_query_type_t query_type, const char *mode,
		zbx_vector_custquery_param_t *query_params)
{
	zbx_vmware_cust_query_t	cq, *pcq;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() soap_type:%s id:%s query_type:%u key:%s", __func__, soap_type, id,
			query_type, key);

	cq.soap_type = vmware_shared_strdup(soap_type);
	cq.id = vmware_shared_strdup(id);
	cq.key = vmware_shared_strdup(key);
	cq.query_type = query_type;
	cq.mode = vmware_shared_strdup(mode);
	cq.value = NULL;
	cq.error = NULL;
	cq.state = (ZBX_VMWARE_CQ_NEW | ZBX_VMWARE_CQ_SEPARATE);
	cq.last_pooled = time(NULL);

	if (VMWARE_DVSWITCH_FETCH_DV_PORTS == query_type)
	{
		cq.query_params = vmware_shmem_custquery_malloc();
		vmware_shmem_vector_custquery_param_create_ext(cq.query_params);
	}
	else
	{
		cq.query_params = NULL;
	}

	for (int i = 0; NULL != cq.query_params && i < query_params->values_num; i++)
	{
		zbx_vmware_custquery_param_t	cqp;

		cqp.name = vmware_shared_strdup(query_params->values[i].name);
		cqp.value = vmware_shared_strdup(query_params->values[i].value);
		zbx_vector_custquery_param_append(cq.query_params, cqp);
	}

	pcq = (zbx_vmware_cust_query_t *)zbx_hashset_insert(&service->cust_queries, &cq,
			sizeof(zbx_vmware_cust_query_t));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return pcq;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets performance entity by type and id                            *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             soap_type    - [IN] entity type                                *
 *             id           - [IN] entity id                                  *
 *             key          - [IN] custom query id                            *
 *             query_type   - [IN]                                            *
 *             mode         - [IN] mode of output value for custom query      *
 *                                                                            *
 * Return value: custom query entity or NULL if not found                     *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_cust_query_t	*zbx_vmware_service_get_cust_query(zbx_vmware_service_t *service, const char *soap_type,
		const char *id, const char *key, zbx_vmware_custom_query_type_t query_type, const char *mode)
{
	zbx_vmware_cust_query_t	*pcq, cq = {.soap_type = (char *)soap_type, .id = (char *)id, .key = (char *)key,
			.query_type = query_type, .mode = (char *)mode};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s query_type:%u key:%s", __func__, soap_type, id, query_type,
			key);

	pcq = (zbx_vmware_cust_query_t *)zbx_hashset_search(&service->cust_queries, &cq);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() cust_query:%p", __func__, (void *)pcq);

	return pcq;
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: destroys vmware collector service                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_destroy(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != vmware_shmem_get_vmware_mem())
	{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
		zbx_hashset_destroy(&vmware->strpool);
#endif
		zbx_shmem_destroy(vmware_shmem_get_vmware_mem());
		vmware_shmem_set_vmware_mem_NULL();
		zbx_mutex_destroy(&vmware_lock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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
 * Parameters: stats - [OUT] vmware collector statistics                      *
 *                                                                            *
 * Return value: SUCCEED - statistics were retrieved successfully             *
 *               FAIL    - no vmware collectors are running                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_get_statistics(zbx_vmware_stats_t *stats)
{
	if (NULL == vmware_shmem_get_vmware_mem())
		return FAIL;

	zbx_vmware_lock();

	stats->memory_total = vmware_shmem_get_vmware_mem()->total_size;
	stats->memory_used = vmware_shmem_get_vmware_mem()->total_size - vmware_shmem_get_vmware_mem()->free_size;

	zbx_vmware_unlock();

	return SUCCEED;
}

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
/******************************************************************************
 *                                                                            *
 * Purpose: creates job to update vmware data periodically and increase       *
 *          service ref counter                                               *
 *                                                                            *
 * Parameters: vmw      - [IN] vmware object                                  *
 *             service  - [IN] vmware service                                 *
 *             job_type - [IN] vmware job type                                *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vmware_job_create(zbx_vmware_t *vmw, zbx_vmware_service_t *service, int job_flag)
{
	zbx_vmware_job_t	*job;
	zbx_binary_heap_elem_t	elem_new = {.key = 0};

	job = vmware_shmem_vmware_job_malloc();
	job->nextcheck = 0;
	job->ttl = 0;
	job->type = job_flag;
	job->revision = (ZBX_VMWARE_UPDATE_EVENTLOG == job->type) ? ++service->eventlog.job_revision : 0;
	job->service = service;
	service->jobs_num++;
	service->jobs_flag |= job_flag;
	job->expired = FAIL;
	elem_new.data = job;
	zbx_binary_heap_insert(&vmw->jobs_queue, &elem_new);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates array of jobs to update vmware data periodically          *
 *                                                                            *
 * Parameters: vmw      - [IN] vmware object                                  *
 *             service  - [IN] vmware service                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vmware_jobs_create(zbx_vmware_t *vmw, zbx_vmware_service_t *service)
{
	int	req_flag = 0x1, jobs_req = ((service->jobs_flag & ZBX_VMWARE_REQ_MASK) >> ZBX_VMWARE_REQ);

	while (0 != jobs_req)
	{
		if (0 != (jobs_req & req_flag))
		{
			zbx_vmware_job_create(vmw, service, req_flag);
			service->jobs_flag &= ~ (req_flag << ZBX_VMWARE_REQ);
			jobs_req &= ~ req_flag;
		}

		req_flag <<= 0x1;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys vmware job and service removing                          *
 *                                                                            *
 * Parameters: job - [IN] job object                                          *
 *                                                                            *
 * Return value: count of removed services                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_job_remove(zbx_vmware_job_t *job)
{
	zbx_vmware_service_t	*service = job->service;
	int			jobs_num = 0, job_type, revision;

	zbx_vmware_lock();

	job_type = job->type;
	jobs_num = --job->service->jobs_num;
	revision = job->revision;

	if (0 == job->revision || job->revision == job->service->eventlog.job_revision)
		job->service->jobs_flag &= ~ (job->type);

	vmware_shmem_vmware_job_free(job);

	zbx_vmware_unlock();

	if (0 == jobs_num)
		zbx_vmware_service_remove(service);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() service jobs_num:%d job_type:%X revision:%d", __func__, jobs_num,
			job_type, revision);

	return 0 == jobs_num ? 1 : 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets shared error of vmware job tags update                       *
 *                                                                            *
 * Parameters: error     - [IN] error message of failure                      *
 *             data_tags - [OUT] data_tags container                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_shared_tags_error_set(const char *error, zbx_vmware_data_tags_t *data_tags)
{
	zbx_vmware_lock();
	vmware_shared_strfree(data_tags->error);
	data_tags->error = vmware_shared_strdup(error);
	zbx_vmware_unlock();
}

/******************************************************************************
 *                                                                            *
 * Purpose: replaces shared tags info                                         *
 *                                                                            *
 * Parameters: src - [IN] collected tags info                                 *
 *             dst - [OUT] shared tags container                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_shared_tags_replace(const zbx_vector_vmware_entity_tags_ptr_t *src, zbx_vmware_data_tags_t *dst)
{
	zbx_vector_vmware_entity_tags_ptr_clear_ext(&dst->entity_tags, vmware_shared_entity_tags_free);
	vmware_shared_strfree(dst->error);
	dst->error = NULL;

	for (int i = 0; i < src->values_num; i++)
	{
		zbx_vmware_entity_tags_t	*to_entity, *from_entity = src->values[i];

		if (0 == from_entity->tags.values_num && NULL == from_entity->error)
			continue;

		to_entity = vmware_shmem_entity_tags_malloc();
		vmware_shmem_vector_vmware_tag_ptr_create_ext(&to_entity->tags);
		to_entity->uuid = vmware_shared_strdup(from_entity->uuid);
		to_entity->obj_id = NULL;

		if (NULL != from_entity->error)
		{
			to_entity->error = vmware_shared_strdup(from_entity->error);
			continue;
		}
		else
			to_entity->error = NULL;

		zbx_vector_vmware_tag_ptr_reserve(&to_entity->tags, (size_t)from_entity->tags.values_num);

		for (int j = 0; j < from_entity->tags.values_num; j++)
		{
			zbx_vmware_tag_t	*to_tag, *from_tag = from_entity->tags.values[j];

			to_tag = vmware_shmem_tag_malloc();
			to_tag->name = vmware_shared_strdup(from_tag->name);
			to_tag->description = vmware_shared_strdup(from_tag->description);
			to_tag->category = vmware_shared_strdup(from_tag->category);
			to_tag->id = NULL;
			zbx_vector_vmware_tag_ptr_append(&to_entity->tags, to_tag);
		}

		zbx_vector_vmware_entity_tags_ptr_append(&dst->entity_tags, to_entity);
	}
}

zbx_uint64_t	zbx_vmware_get_evt_req_chunk_sz(void)
{
	return evt_req_chunk_size;
}
#endif

zbx_vmware_t	*zbx_vmware_get_vmware(void)
{
	return vmware;
}

int	zbx_vmware_init(zbx_uint64_t *config_vmware_cache_size, char **error)
{
	if (SUCCEED != zbx_mutex_create(&vmware_lock, ZBX_MUTEX_VMWARE, error))
		return FAIL;

	if (SUCCEED == vmware_shmem_init(config_vmware_cache_size, &vmware, error))
	{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
		evt_req_chunk_size = zbx_shmem_required_chunk_size(sizeof(zbx_vmware_event_t));
#endif
	}

	return SUCCEED;
}
