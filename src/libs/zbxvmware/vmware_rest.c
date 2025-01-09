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

#include "zbxcommon.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"
#include "vmware_internal.h"

#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "vmware_shmem.h"
#include "zbxshmem.h"

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
	char	*url;
}
ZBX_HTTPPAGE_REST;

/******************************************************************************
 *                                                                            *
 * Purpose: cURL service function                                             *
 *                                                                            *
 ******************************************************************************/
static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t		r_size = size * nmemb;
	ZBX_HTTPPAGE_REST	*page_http = (ZBX_HTTPPAGE_REST *)userdata;

	zbx_strncpy_alloc(&page_http->data, &page_http->alloc, &page_http->offset, (const char *)ptr, r_size);

	return r_size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cURL service function                                             *
 *                                                                            *
 ******************************************************************************/
static size_t	curl_header_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

ZBX_PTR_VECTOR_IMPL(vmware_key_value, zbx_vmware_key_value_t)

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store zbx_vmware_key_value_t         *
 *                                                                            *
 ******************************************************************************/
void	zbx_vmware_key_value_free(zbx_vmware_key_value_t value)
{
	zbx_str_free(value.key);
	zbx_str_free(value.value);
}

ZBX_PTR_VECTOR_IMPL(vmware_entity_tags_ptr, zbx_vmware_entity_tags_t *)
ZBX_PTR_VECTOR_IMPL(vmware_tag_ptr, zbx_vmware_tag_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort zbx_vmware_tag_t vector by id            *
 *                                                                            *
 ******************************************************************************/
static int	zbx_vmware_tag_id_compare(const void *p1, const void *p2)
{
	const zbx_vmware_tag_t	*v1 = *(const zbx_vmware_tag_t * const *)p1;
	const zbx_vmware_tag_t	*v2 = *(const zbx_vmware_tag_t * const *)p2;

	return strcmp(v1->id, v2->id);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store zbx_vmware_tag_t               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_tag_free(zbx_vmware_tag_t *value)
{
	zbx_str_free(value->id);
	zbx_str_free(value->name);
	zbx_str_free(value->category);
	zbx_str_free(value->description);
	zbx_free(value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware_entity_tags_free        *
 *                                                                            *
 ******************************************************************************/
static void	vmware_entity_tags_free(zbx_vmware_entity_tags_t *value)
{
	zbx_vector_vmware_tag_ptr_clear_ext(&value->tags, vmware_tag_free);
	zbx_vector_vmware_tag_ptr_destroy(&value->tags);
	zbx_str_free(value->uuid);
	zbx_str_free(value->error);
	zbx_str_free(value->obj_id->id);
	zbx_str_free(value->obj_id->type);
	zbx_free(value->obj_id);
	zbx_free(value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates entity tag object                                         *
 *                                                                            *
 * Parameters: type - [IN] VirtualMachine, HostSystem etc                     *
 *             id   - [IN] id of vm, hv or ds                                 *
 *             uuid - [IN] uuid of vm, hv or ds                               *
 *                                                                            *
 * Return value: pointer to tag object                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_entity_tags_t	*vmware_entity_tag_create(const char *type, const char *id, const char *uuid)
{
	zbx_vmware_entity_tags_t	*entry_tag;

	entry_tag = (zbx_vmware_entity_tags_t *)zbx_malloc(NULL, sizeof(zbx_vmware_entity_tags_t));
	entry_tag->obj_id = (zbx_vmware_obj_id_t *)zbx_malloc(NULL, sizeof(zbx_vmware_obj_id_t));
	zbx_vector_vmware_tag_ptr_create(&entry_tag->tags);
	entry_tag->obj_id->type = zbx_strdup(NULL, type);
	entry_tag->obj_id->id = zbx_strdup(NULL, id);
	entry_tag->uuid = zbx_strdup(NULL, uuid);
	entry_tag->error = NULL;

	return entry_tag;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware service data     *
 *                                                                            *
 * Parameters: data        - [IN] vmware service data                         *
 *             entity_tags - [OUT] set of query tags objects                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_entry_tags_init(zbx_vmware_data_t *data, zbx_vector_vmware_entity_tags_ptr_t *entity_tags)
{
	zbx_hashset_iter_t	iter;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_vm_index_t	*vmi;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_iter_reset(&data->hvs, &iter);

	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_vmware_entity_tags_ptr_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_HV, hv->id, hv->uuid));
	}

	zbx_hashset_iter_reset(&data->vms_index, &iter);

	while (NULL != (vmi = (zbx_vmware_vm_index_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_vmware_entity_tags_ptr_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_VM, vmi->vm->id, vmi->vm->uuid));
	}

	for (int i = 0; i < data->datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*ds = data->datastores.values[i];

		zbx_vector_vmware_entity_tags_ptr_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_DS, ds->id, ds->uuid));
	}

	for (int i = 0; i < data->datacenters.values_num; i++)
	{
		zbx_vmware_datacenter_t	*dc = data->datacenters.values[i];
		char			uuid[VMWARE_SHORT_STR_LEN];

		zbx_snprintf(uuid, sizeof(uuid),"%s:%s", ZBX_VMWARE_SOAP_DC, dc->id);
		zbx_vector_vmware_entity_tags_ptr_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_DC, dc->id, uuid));
	}

	for (int i = 0; i < data->clusters.values_num; i++)
	{
		zbx_vmware_cluster_t	*cl = (zbx_vmware_cluster_t *)data->clusters.values[i];
		char			uuid[VMWARE_SHORT_STR_LEN];

		zbx_snprintf(uuid, sizeof(uuid),"%s:%s", ZBX_VMWARE_SOAP_CLUSTER, cl->id);
		zbx_vector_vmware_entity_tags_ptr_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_CLUSTER, cl->id, uuid));
	}

	for (int i = 0; i < data->resourcepools.values_num; i++)
	{
		zbx_vmware_resourcepool_t	*rp = data->resourcepools.values[i];
		char				uuid[VMWARE_SHORT_STR_LEN];

		zbx_snprintf(uuid, sizeof(uuid),"%s:%s", ZBX_VMWARE_SOAP_RESOURCEPOOL, rp->id);
		zbx_vector_vmware_entity_tags_ptr_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_RESOURCEPOOL, rp->id, uuid));
	}

	zbx_vector_vmware_entity_tags_ptr_sort(entity_tags, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() entity tags:%d", __func__, entity_tags->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cURL handle prepare                                               *
 *                                                                            *
 * Parameters: url                   - [IN] vmware service url                *
 *             is_new_api            - [IN]                                   *
 *             config_source_ip      - [IN]                                   *
 *             config_vmware_timeout - [IN]                                   *
 *             easyhandle            - [OUT] cURL handle                      *
 *             page                  - [OUT] response buffer for cURL         *
 *             headers               - [OUT] request headers for cURL         *
 *             error                 - [OUT] error message in case of failure *
 *                                                                            *
 * Return value: SUCCEED if cURL prepared, FAIL otherwise                     *
 *                                                                            *
 ******************************************************************************/
static int	vmware_curl_init(const char *url, unsigned char is_new_api, const char *config_source_ip,
		int config_vmware_timeout, CURL **easyhandle, ZBX_HTTPPAGE_REST *page, struct curl_slist **headers,
		char **error)
{
#	define INIT_PERF_REST_SIZE	2 * ZBX_KIBIBYTE
#	define ZBX_XML_HEADER1_REST	"Accept: application/json, text/plain, */*"
#	define ZBX_XML_HEADER2_REST	"Content-Type:application/json;charset=utf-8"

	int		ret = FAIL;
	size_t		url_sz;
	CURLcode	err;
	CURLoption	opt;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	page->alloc = 0;

	if (NULL == (*easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(*error, "cannot initialize cURL library");
		goto out;
	}

	page->alloc = INIT_PERF_REST_SIZE;
	page->data = (char *)zbx_malloc(NULL, page->alloc);
	page->url = zbx_strdup(NULL, url);
	zbx_rtrim(page->url, "/");
	url_sz = strlen(page->url);

	if (url_sz < ZBX_CONST_STRLEN("/sdk") || 0 != strcmp(&page->url[url_sz - ZBX_CONST_STRLEN("/sdk")], "/sdk"))
	{
		*error = zbx_strdup(*error, "cannot initialize rest service url");
		goto out;
	}

	if (0 == is_new_api)
	{
		page->url = zbx_dsprintf(page->url, "%.*s%s", (int)(url_sz - ZBX_CONST_STRLEN("sdk")), page->url,
				"rest/com/vmware");
	}
	else
		memcpy(&page->url[url_sz - ZBX_CONST_STRLEN("api")], "api", ZBX_CONST_STRLEN("api"));

	*headers = curl_slist_append(*headers, ZBX_XML_HEADER1_REST);
	*headers = curl_slist_append(*headers, ZBX_XML_HEADER2_REST);

	if (CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HTTPHEADER, *headers)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_WRITEFUNCTION, curl_write_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_WRITEDATA, page)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_PRIVATE, page)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HEADERFUNCTION,
			curl_header_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			(NULL != config_source_ip && CURLE_OK != (err = curl_easy_setopt(*easyhandle,
			opt = CURLOPT_INTERFACE, config_source_ip))) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_TIMEOUT,
			(long)config_vmware_timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
	}
	else
		ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef INIT_PERF_REST_SIZE
#	undef ZBX_XML_HEADER1_REST
#	undef ZBX_XML_HEADER2_REST
}

/******************************************************************************
 *                                                                            *
 * Purpose: opens json document and checks errors                             *
 *                                                                            *
 * Parameters: data  - [IN] json data                                         *
 *             jp    - [OUT] prepared json document (optional)                *
 *             error - [OUT] error message in case of failure                 *
 *                                                                            *
 * Return value: SUCCEED if json prepared, FAIL otherwise                     *
 *                                                                            *
 ******************************************************************************/
static int	vmware_rest_response_open(const char *data, struct zbx_json_parse *jp, char **error)
{
	struct zbx_json_parse	jp_loc, jp_data;
	char			err[VMWARE_SHORT_STR_LEN];

	if (NULL == jp)
		jp = &jp_loc;

	if (FAIL == zbx_json_open(data, jp))
	{
		*error = zbx_dsprintf(*error, "Cannot open vmware response: %s", zbx_json_strerror());
		return FAIL;
	}

	if (SUCCEED == zbx_json_value_by_name(jp, "error_type", err, sizeof(err), NULL))
	{
		char	err_msg[VMWARE_SHORT_STR_LEN];

		if (FAIL == zbx_json_value_by_name(jp, "default_message", err_msg, sizeof(err_msg), NULL))
			err_msg[0] = '\0';

		*error = zbx_dsprintf(*error, "%s:%s", err, err_msg);

		return FAIL;
	}

	if (SUCCEED == zbx_json_brackets_by_name(jp, "error", &jp_data))
	{
		char	err_data[VMWARE_SHORT_STR_LEN];

		if (FAIL == zbx_json_value_by_name(&jp_data, "message", err, sizeof(err), NULL))
		{
			*error = zbx_dsprintf(*error, "error:%.*s", (int)(jp_data.end - jp_data.start), jp_data.start);
			return FAIL;
		}

		if (FAIL == zbx_json_value_by_name(&jp_data, "data", err_data, sizeof(err_data), NULL))
			err_data[0] = '\0';

		*error = zbx_dsprintf(*error, "%s:%s", err, err_data);

		return FAIL;
	}

	if (SUCCEED == zbx_json_value_by_name(jp, "majorErrorCode", err, sizeof(err), NULL))
	{
		char			err_msg[VMWARE_SHORT_STR_LEN];
		const char		*p = NULL;
		struct zbx_json_parse	jp_row;

		zbx_json_value_by_name(jp, "name", err, sizeof(err), NULL);

		if (SUCCEED != zbx_json_brackets_by_name(jp, "localizableMessages", &jp_data) ||
				NULL == (p = zbx_json_next(&jp_data, p)) ||
				SUCCEED != zbx_json_brackets_open(p, &jp_row) ||
				SUCCEED != zbx_json_value_by_name(&jp_row, "defaultMessage", err_msg, sizeof(err_msg),
				NULL))
		{
			err_msg[0] = '\0';
		}

		*error = zbx_dsprintf(*error, "%s:%s", err, err_msg);

		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: service    - [IN] vmware service                               *
 *             is_new_api - [IN]                                              *
 *             easyhandle - [IN/OUT] cURL handle                              *
 *             headers    - [IN/OUT] request headers for cURL                 *
 *             page       - [IN/OUT] response buffer for cURL                 *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED if rest authenticated, FAIL otherwise                *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_rest_authenticate(const zbx_vmware_service_t *service, unsigned char is_new_api,
		CURL *easyhandle, struct curl_slist **headers, ZBX_HTTPPAGE_REST *page, char **error)
{
	int		ret = FAIL;
	char		tmp[MAX_STRING_LEN];
	CURLcode	err;
	CURLoption	opt;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	zbx_snprintf(tmp, sizeof(tmp), 0 != is_new_api ? "%s/session" : "%s/cis/session", page->url);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, tmp)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_USERNAME, service->username)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_PASSWORD, service->password)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	page->offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (0 == page->offset)
	{
		*error = zbx_strdup(*error, "Cannot authenticate, received empty response.");
		goto out;
	}

	if (0 == is_new_api)
	{
		char			token[MAX_STRING_LEN];
		struct zbx_json_parse	jp;

		if (FAIL == vmware_rest_response_open(page->data, &jp, error))
		{
			*error = zbx_dsprintf(*error, "Cannot authenticate: %s.", *error);
			goto out;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp, "value", token, sizeof(token), NULL))
		{
			*error = zbx_dsprintf(*error, "Cannot authenticate, cannot read vmware response: %s.",
					zbx_json_strerror());
			goto out;
		}

		zbx_snprintf(tmp, sizeof(tmp),"vmware-api-session-id: %s", token);
	}
	else
	{
		if ('"' != page->data[0] && FAIL == vmware_rest_response_open(page->data, NULL, error))
		{
			*error = zbx_dsprintf(*error, "Authentication fail, %s.", *error);
			goto out;
		}

		zbx_lrtrim(page->data, "\"");
		zbx_snprintf(tmp, sizeof(tmp),"vmware-api-session-id: %s", page->data);
	}

	*headers = curl_slist_append(*headers, tmp);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, *headers)))
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
	else
		ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Parameters: easyhandle - [IN/OUT] cURL handle                              *
 *             page       - [IN/OUT] response buffer for cURL                 *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_rest_logout(CURL *easyhandle, ZBX_HTTPPAGE_REST *page)
{
	char		tmp[MAX_STRING_LEN];
	CURLcode	err;
	CURLoption	opt;

	zbx_snprintf(tmp, sizeof(tmp),"%s/session", page->url);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, tmp)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_CUSTOMREQUEST, "DELETE")))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot set cURL option %d: %s.",
				__func__, (int)opt, curl_easy_strerror(err));
	}
	else
	{
		page->offset = 0;

		if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() error:%s", __func__, curl_easy_strerror(err));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: unification of vmware web service call with REST error validation *
 *                                                                            *
 * Parameters: fn_parent  - [IN] parent function name                         *
 *             easyhandle - [IN] CURL handle                                  *
 *             url_suffix - [IN] second part of url request                   *
 *             jp         - [OUT] json response document                      *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - REST request was completed successfully            *
 *               FAIL    - REST request has failed                            *
 *                                                                            *
 ******************************************************************************/
static int	vmware_http_request(const char *fn_parent, CURL *easyhandle, const char *url_suffix,
		struct zbx_json_parse *jp, char **error)
{
	char		url[MAX_STRING_LEN];
	CURLcode	err;
	CURLoption	opt;
	ZBX_HTTPPAGE_REST	*page;

	if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_PRIVATE, (char **)&page)))
	{
		*error = zbx_dsprintf(*error, "Cannot get response buffer: %s.", curl_easy_strerror(err));
		return FAIL;
	}

	zbx_snprintf(url, sizeof(url),"%s%s", page->url, url_suffix);

	if (NULL != fn_parent)
		zabbix_log(LOG_LEVEL_TRACE, "%s() request url:%s", fn_parent, url);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, url)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		return FAIL;
	}

	page->offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		return FAIL;
	}
	else if (0 == page->offset)
		*page->data = '\0';

	if (NULL != fn_parent)
		zabbix_log(LOG_LEVEL_TRACE, "%s() REST response: %s", fn_parent, page->data);

	return vmware_rest_response_open(page->data, jp, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: vmware web service GET call with REST error validation            *
 *                                                                            *
 * Parameters: fn_parent  - [IN] parent function name for Log records         *
 *             easyhandle - [IN] CURL handle                                  *
 *             url_suffix - [IN] second part of url request                   *
 *             param      - [IN] url parameter                                *
 *             jp         - [OUT] json document response                      *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - SOAP request was completed successfully            *
 *               FAIL    - SOAP request has failed                            *
 *                                                                            *
 ******************************************************************************/
static int	vmware_rest_get(const char *fn_parent, CURL *easyhandle, const char *url_suffix, const char *param,
		struct zbx_json_parse *jp, char **error)
{
	CURLcode	err;
	CURLoption	opt;
	char		url[MAX_STRING_LEN];

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPGET, 1L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		return FAIL;
	}

	zbx_snprintf(url, sizeof(url),"%s%s", url_suffix, param);

	return vmware_http_request(fn_parent, easyhandle, url, jp, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: vmware web service POST call with REST error validation           *
 *                                                                            *
 * Parameters: fn_parent  - [IN] parent function name for Log records         *
 *             easyhandle - [IN] CURL handle                                  *
 *             url_suffix - [IN]                                              *
 *             request    - [IN] http request                                 *
 *             jp         - [OUT]                                             *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - POST rest request was completed successfully       *
 *               FAIL    - POST rest request has failed                       *
 *                                                                            *
 ******************************************************************************/
static int	vmware_rest_post(const char *fn_parent, CURL *easyhandle, const char *url_suffix, const char *request,
		struct zbx_json_parse *jp, char **error)
{
	CURLcode	err;
	CURLoption	opt;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, request)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		return FAIL;
	}

	if (SUCCEED != vmware_http_request(fn_parent, easyhandle, url_suffix, jp, error))
		return FAIL;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, (char *)NULL)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", CURLOPT_POSTFIELDS,
				curl_easy_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets list of tags linked with object                              *
 *                                                                            *
 * Parameters: obj_id     - [IN] parent function name for Log records         *
 *             easyhandle - [IN] CURL handle                                  *
 *             is_new_api - [IN] flag to use new api version syntax           *
 *             ids        - [OUT] vector with tags id                         *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED if receive list of tags id, FAIL otherwise           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_tags_linked_id(const zbx_vmware_obj_id_t *obj_id, CURL *easyhandle, unsigned char is_new_api,
		zbx_vector_str_t *ids, char **error)
{
	int			ret;
	char			tmp[MAX_STRING_LEN];
	const char		*url, *p = NULL;
	struct zbx_json_parse	jp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() obj_id:%s", __func__, obj_id->id);

	zbx_snprintf(tmp, sizeof(tmp),"{\"object_id\":{\"id\":\"%s\",\"type\":\"%s\"}}", obj_id->id, obj_id->type);

	if (0 != is_new_api)
		url = "/cis/tagging/tag-association?action=list-attached-tags";
	else
		url = "/cis/tagging/tag-association?~action=list-attached-tags";

	if (SUCCEED == (ret = vmware_rest_post(__func__, easyhandle, url, tmp, &jp, error)))
	{
		struct zbx_json_parse	jp_step;

		if (0 == is_new_api)
		{
			if (SUCCEED != (ret = zbx_json_brackets_by_name(&jp, "value", &jp_step)))
				goto out;
		}
		else
			jp_step = jp;

		while (NULL != (p = zbx_json_next_value(&jp_step, p, tmp, sizeof(tmp), NULL)))
			zbx_vector_str_append(ids, zbx_strdup(NULL, tmp));
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ids:%d", __func__, ids->values_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets tag details and saves it to cache vectors                    *
 *                                                                            *
 * Parameters: tag_id     - [IN]                                              *
 *             easyhandle - [IN] CURL handle                                  *
 *             is_new_api - [IN]                                              *
 *             tags       - [OUT]                                             *
 *             categories - [OUT]                                             *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED if received tag details, FAIL otherwise              *
 *                                                                            *
 ******************************************************************************/
static int	vmware_vectors_update(const char *tag_id, CURL *easyhandle, unsigned char is_new_api,
		zbx_vector_vmware_tag_ptr_t *tags, zbx_vector_vmware_key_value_t *categories, char **error)
{
	struct zbx_json_parse	jp, jp_data;
	int			i;
	char			cid[VMWARE_SHORT_STR_LEN], name[MAX_STRING_LEN], desc[MAX_STRING_LEN];
	const char		*url_tag, *url_cat;
	zbx_vmware_key_value_t	cat_cmp;
	zbx_vmware_tag_t	*tag;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() tag_id:%s", __func__, tag_id);

	if (0 == is_new_api)
	{
		url_tag = "/cis/tagging/tag/id:";
		url_cat = "/cis/tagging/category/id:";
	}
	else
	{
		url_tag = "/cis/tagging/tag/";
		url_cat = "/cis/tagging/category/";
	}

	if (FAIL == vmware_rest_get(__func__, easyhandle, url_tag, tag_id, &jp_data, error))
		return FAIL;

	if (0 == is_new_api)
	{
		if (SUCCEED != zbx_json_brackets_by_name(&jp_data, "value", &jp))
			goto json_err;
	}
	else
		jp = jp_data;

	if (FAIL == zbx_json_value_by_name(&jp, "name", name, sizeof(name), NULL) ||
			FAIL == zbx_json_value_by_name(&jp, "description", desc, sizeof(desc), NULL) ||
			FAIL == zbx_json_value_by_name(&jp, "category_id", cid, sizeof(cid), NULL))
	{
		goto json_err;
	}

	cat_cmp.key = cid;

	if (FAIL == (i = zbx_vector_vmware_key_value_bsearch(categories, cat_cmp, ZBX_DEFAULT_STR_COMPARE_FUNC)))
	{
		zbx_vmware_key_value_t	category;
		char			value[MAX_STRING_LEN];

		if (FAIL == vmware_rest_get(__func__, easyhandle, url_cat, cid, &jp_data, error))
			return FAIL;

		if (0 == is_new_api)
		{
			if (SUCCEED != zbx_json_brackets_by_name(&jp_data, "value", &jp))
				goto json_err;
		}
		else
			jp = jp_data;

		if (FAIL == zbx_json_value_by_name(&jp, "name", value, sizeof(value), NULL))
			goto json_err;

		category.key = zbx_strdup(NULL, cid);
		category.value = zbx_strdup(NULL, value);
		zbx_vector_vmware_key_value_append(categories, category);
		zbx_vector_vmware_key_value_sort(categories, ZBX_DEFAULT_STR_COMPARE_FUNC);

		if (FAIL == (i = zbx_vector_vmware_key_value_bsearch(categories, category,
				ZBX_DEFAULT_STR_COMPARE_FUNC)))
		{
			*error = zbx_strdup(NULL, "Cannot append tag category name");
			return FAIL;
		}
	}

	tag = (zbx_vmware_tag_t *)zbx_malloc(NULL, sizeof(zbx_vmware_tag_t));
	tag->id = zbx_strdup(NULL, tag_id);
	tag->name = zbx_strdup(NULL, name);
	tag->description = zbx_strdup(NULL, desc);
	tag->category  = zbx_strdup(NULL, categories->values[i].value);
	zbx_vector_vmware_tag_ptr_append(tags, tag);
	zbx_vector_vmware_tag_ptr_sort(tags, zbx_vmware_tag_id_compare);

	if (FAIL == (i = zbx_vector_vmware_tag_ptr_bsearch(tags, tag, zbx_vmware_tag_id_compare)))
	{
		*error = zbx_strdup(NULL, "Cannot append tag info");
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() tag name:'%s' description:'%s' category:'%s'", __func__,
			tags->values[i]->name, tags->values[i]->description, tags->values[i]->category);

	return i;
json_err:
	*error = zbx_dsprintf(*error, "Cannot read vmware response: %s", zbx_json_strerror());
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates vector with tags details                                  *
 *                                                                            *
 * Parameters: is_new_api  - [IN]                                             *
 *             entity_tags - [IN/OUT]                                         *
 *             tags        - [IN/OUT]                                         *
 *             categories  - [IN/OUT]                                         *
 *             easyhandle  - [IN/OUT] CURL handle                             *
 *                                                                            *
 * Return value: SUCCEED if create tags vector, FAIL otherwise                *
 *                                                                            *
 ******************************************************************************/
static int	vmware_tags_get(unsigned char is_new_api, zbx_vmware_entity_tags_t *entity_tags,
		zbx_vector_vmware_tag_ptr_t *tags, zbx_vector_vmware_key_value_t *categories, CURL *easyhandle)
{
	int			found_tags = 0;
	zbx_vector_str_t	tag_ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() obj_id:%s", __func__, entity_tags->obj_id->id);

	zbx_vector_str_create(&tag_ids);

	if (FAIL == vmware_tags_linked_id(entity_tags->obj_id, easyhandle, is_new_api, &tag_ids, &entity_tags->error))
		goto out;

	for (int i = 0; i < tag_ids.values_num; i++)
	{
		int			j;
		zbx_vmware_tag_t	*tag, cmp = {.id = tag_ids.values[i]};

		if (FAIL == (j = zbx_vector_vmware_tag_ptr_bsearch(tags, &cmp, zbx_vmware_tag_id_compare)) &&
				FAIL == (j = vmware_vectors_update(tag_ids.values[i], easyhandle, is_new_api, tags,
				categories, &entity_tags->error)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() problem with tag_id:%s error:%s", __func__, tag_ids.values[i],
					entity_tags->error);
			break;
		}

		tag = (zbx_vmware_tag_t *) zbx_malloc(NULL, sizeof(zbx_vmware_tag_t));
		tag->name = zbx_strdup(NULL, tags->values[j]->name);
		tag->description = zbx_strdup(NULL, tags->values[j]->description);
		tag->category = zbx_strdup(NULL, tags->values[j]->category);
		tag->id = NULL;
		zbx_vector_vmware_tag_ptr_append(&entity_tags->tags, tag);
		found_tags++;
	}

	zbx_vector_vmware_tag_ptr_sort(&entity_tags->tags, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC);
out:
	zbx_vector_str_clear_ext(&tag_ids, zbx_str_free);
	zbx_vector_str_destroy(&tag_ids);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found tags:%d", __func__, found_tags);

	return found_tags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate required shared memory size of the perfCounter values   *
 *                                                                            *
 * Parameters: perfdata - [IN] performance counter values                     *
 *                                                                            *
 * Return value: size of shared memory in bytes                               *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_tags_shmem_size(zbx_vector_vmware_entity_tags_ptr_t *entity_tags)
{
	zbx_uint64_t		req_sz = 0,
				tags_chunk_sz = zbx_shmem_required_chunk_size(sizeof(zbx_vmware_entity_tags_t)),
				tag_chunk_sz = zbx_shmem_required_chunk_size(sizeof(zbx_vmware_tag_t));

	for (int i = 0; i < entity_tags->values_num; i++)
	{
		zbx_vmware_entity_tags_t	*entity = entity_tags->values[i];

		if (0 == entity->tags.values_num && NULL == entity->error)
			continue;

		req_sz += tags_chunk_sz;
		req_sz += vmware_shared_str_sz(entity->uuid);

		if (NULL != entity->error)
		{
			req_sz += vmware_shared_str_sz(entity->error);
			continue;
		}

		req_sz += tag_chunk_sz * (zbx_uint64_t)entity->tags.values_num + zbx_shmem_required_chunk_size(
				(zbx_uint64_t)entity->tags.values_alloc * sizeof(zbx_vmware_tag_t*));

		for (int j = 0; j < entity->tags.values_num; j++)
		{
			zbx_vmware_tag_t	*tag = entity->tags.values[j];

			req_sz += tag_chunk_sz;
			req_sz += vmware_shared_str_sz(tag->name);
			req_sz += vmware_shared_str_sz(tag->description);
			req_sz += vmware_shared_str_sz(tag->category);
		}
	}

	return req_sz;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware tags data                                          *
 *                                                                            *
 * Parameters: service               - [IN] vmware service                   *
 *             config_source_ip      - [IN]                                   *
 *             config_vmware_timeout - [IN]                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_update_tags(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout)
{
	int					version, found_tags = 0, ret = FAIL;
	char					*error = NULL;
	unsigned char				is_new_api;
	zbx_uint64_t				tags_sz = 0;
	zbx_vector_vmware_entity_tags_ptr_t	entity_tags;
	zbx_vector_vmware_tag_ptr_t		tags;
	zbx_vector_vmware_key_value_t		categories;
	CURL					*easyhandle = NULL;
	struct curl_slist			*headers = NULL;
	ZBX_HTTPPAGE_REST			page = {.data = NULL, .url = NULL};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_vmware_entity_tags_ptr_create(&entity_tags);

	zbx_vmware_lock();
	version = service->major_version * 100 + service->minor_version * 10 + service->update_version;

	if (650 > version || ZBX_VMWARE_TYPE_VCENTER != service->type)
	{
		zbx_vmware_unlock();
		error = zbx_strdup(error, "Tags are supported since VMware virtual center version 6.5.");
		goto out;
	}

	vmware_entry_tags_init(service->data, &entity_tags);
	zbx_vmware_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "%s() vc version:%d", __func__, version);

	zbx_vector_vmware_tag_ptr_create(&tags);
	zbx_vector_vmware_key_value_create(&categories);
	is_new_api = (702 <= version) ? 1 : 0;

	if (0 != entity_tags.values_num && (
			SUCCEED != vmware_curl_init(service->url, is_new_api, config_source_ip, config_vmware_timeout,
					&easyhandle, &page, &headers, &error) ||
			SUCCEED != vmware_service_rest_authenticate(service, is_new_api, easyhandle, &headers, &page,
			&error)))
	{
		goto clean;
	}

	for (int i = 0; i < entity_tags.values_num; i++)
		found_tags += vmware_tags_get(is_new_api, entity_tags.values[i], &tags, &categories, easyhandle);

	if (NULL != headers)
		vmware_service_rest_logout(easyhandle, &page);

	zbx_vmware_lock();

	if (vmware_shmem_get_vmware_mem()->free_size > (tags_sz = vmware_tags_shmem_size(&entity_tags)))
	{
		zbx_vmware_shared_tags_replace(&entity_tags, &service->data_tags);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Postponed VMware tags require up to " ZBX_FS_UI64
				" bytes of free VMwareCache memory. Available " ZBX_FS_UI64 " bytes."
				" Reading tags skipped", tags_sz, vmware_shmem_get_vmware_mem()->free_size);
	}

	zbx_vmware_unlock();

	ret = SUCCEED;
clean:
	zbx_vector_vmware_tag_ptr_clear_ext(&tags, vmware_tag_free);
	zbx_vector_vmware_key_value_clear_ext(&categories, zbx_vmware_key_value_free);
	zbx_vector_vmware_tag_ptr_destroy(&tags);
	zbx_vector_vmware_key_value_destroy(&categories);
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(page.data);
	zbx_free(page.url);
out:
	zbx_vector_vmware_entity_tags_ptr_clear_ext(&entity_tags, vmware_entity_tags_free);
	zbx_vector_vmware_entity_tags_ptr_destroy(&entity_tags);

	if (FAIL == ret)
	{
		zbx_vmware_shared_tags_error_set(error, &service->data_tags);
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, error);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s tags:%d tags size:" ZBX_FS_UI64, __func__,
				zbx_result_string(ret), found_tags, tags_sz);

	zbx_str_free(error);

	return ret;
}

#endif
