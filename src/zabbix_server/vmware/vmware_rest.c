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

extern int	CONFIG_VMWARE_TIMEOUT;
#define		VMWARE_SHORT_STR_LEN	MAX_STRING_LEN / 8

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
	char	*url;

}
ZBX_HTTPPAGE;

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

typedef struct
{
	char	*key;
	char	*value;
}
zbx_vmware_key_value_t;
ZBX_PTR_VECTOR_DECL(vmware_key_value, zbx_vmware_key_value_t *)
ZBX_PTR_VECTOR_IMPL(vmware_key_value, zbx_vmware_key_value_t *)

static void	vmware_key_value_free(zbx_vmware_key_value_t *value)
{
	zbx_str_free(value->key);
	zbx_str_free(value->value);
	zbx_free(value);
}

ZBX_PTR_VECTOR_IMPL(vmware_entity_tags, zbx_vmware_entity_tags_t *)
ZBX_PTR_VECTOR_IMPL(vmware_tag, zbx_vmware_tag_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort zbx_vmware_tag_t vector by name          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_vmware_tag_id_compare(const void *p1, const void *p2)
{
	const zbx_vmware_tag_t	*v1 = *(const zbx_vmware_tag_t * const *)p1;
	const zbx_vmware_tag_t	*v2 = *(const zbx_vmware_tag_t * const *)p2;

	return strcmp(v1->id, v2->id);
}

static void	vmware_tag_free(zbx_vmware_tag_t *value)
{
	zbx_str_free(value->id);
	zbx_str_free(value->name);
	zbx_str_free(value->category);
	zbx_str_free(value->description);
	zbx_free(value);
}

static void	vmware_entity_tags_free(zbx_vmware_entity_tags_t *value)
{

	zbx_vector_vmware_tag_clear_ext(&value->tags, vmware_tag_free);
	zbx_vector_vmware_tag_destroy(&value->tags);
	zbx_str_free(value->uuid);
	zbx_str_free(value->error);
	zbx_str_free(value->obj_id->id);
	zbx_str_free(value->obj_id->type);
	zbx_free(value->obj_id);
	zbx_free(value);
}

static zbx_vmware_entity_tags_t	*vmware_entity_tag_create(const char *type, const char *id, const char *uuid)
{
	zbx_vmware_entity_tags_t	*entry_tag;

	entry_tag = (zbx_vmware_entity_tags_t *)zbx_malloc(NULL, sizeof(zbx_vmware_entity_tags_t));
	entry_tag->obj_id = (zbx_vmware_obj_id_t *)zbx_malloc(NULL, sizeof(zbx_vmware_obj_id_t));
	zbx_vector_vmware_tag_create(&entry_tag->tags);
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
 * Parameters: data   - [IN] the vmware service data                          *
 *                                                                            *
 ******************************************************************************/
static void	vmware_entry_tags_init(zbx_vmware_data_t *data, zbx_vector_vmware_entity_tags_t *entity_tags)
{
	int			i;
	zbx_hashset_iter_t	iter;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_vm_index_t	*vmi;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_iter_reset(&data->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_vmware_entity_tags_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_HV, hv->id, hv->uuid));
	}

	zbx_hashset_iter_reset(&data->vms_index, &iter);
	while (NULL != (vmi = (zbx_vmware_vm_index_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_vmware_entity_tags_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_VM, vmi->vm->id, vmi->vm->uuid));
	}

	for (i = 0; i < data->datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*ds = data->datastores.values[i];

		zbx_vector_vmware_entity_tags_append(entity_tags,
				vmware_entity_tag_create(ZBX_VMWARE_SOAP_DS, ds->id, ds->uuid));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() entity tags:%d", __func__, entity_tags->values_num);

}

static int	vmware_curl_init(const char *url, CURL **easyhandle, ZBX_HTTPPAGE *page, struct curl_slist **headers,
		char **error)
{
#	define INIT_PERF_REST_SIZE	2 * ZBX_KIBIBYTE
#	define ZBX_XML_HEADER1		"Accept: application/json, text/plain, */*"
#	define ZBX_XML_HEADER2		"Content-Type:application/json;charset=utf-8"

	int		url_sz, ret = FAIL;
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

	if (url_sz < 5 || 0 != strcmp(&page->url[url_sz - 4], "/sdk"))
	{
		*error = zbx_strdup(*error, "cannot initialize rest service url");
		goto out;
	}

	strscpy(&page->url[url_sz - 3], "api");
	*headers = curl_slist_append(*headers, ZBX_XML_HEADER1);
	*headers = curl_slist_append(*headers, ZBX_XML_HEADER2);

	if (CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HTTPHEADER, *headers)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_WRITEFUNCTION, curl_write_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_WRITEDATA, page)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_PRIVATE, page)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HEADERFUNCTION, curl_header_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_TIMEOUT,
					(long)CONFIG_VMWARE_TIMEOUT)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = ZBX_CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
	}
	else
		ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef INIT_PERF_REST_SIZE
#	undef ZBX_XML_HEADER1
#	undef ZBX_XML_HEADER2
}

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

	return SUCCEED;
}

static int	vmware_service_rest_authenticate(zbx_vmware_service_t *service, CURL *easyhandle,
		struct curl_slist *headers, ZBX_HTTPPAGE *page, char **error)
{
	int		ret = FAIL;
	char		tmp[VMWARE_SHORT_STR_LEN];
	CURLcode	err;
	CURLoption	opt;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	zbx_snprintf(tmp, sizeof(tmp),"%s/session", page->url);

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

	if ('"' != page->data[0] && FAIL == vmware_rest_response_open(page->data, NULL, error))
		goto out;

	zbx_ltrim(page->data, "\"");
	zbx_rtrim(page->data, "\"");
	zbx_snprintf(tmp, sizeof(tmp),"vmware-api-session-id: %s", page->data);
	headers = curl_slist_append(headers, tmp);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
	else
		ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Purpose: unification of vmware web service call with REST error validation *
 *                                                                            *
 * Parameters: easyhandle - [IN] the CURL handle                              *
 *             url_suffix - [IN] the second part of url request               *
 *             page       - [OUT] the http response                           *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the SOAP request was completed successfully        *
 *               FAIL    - the SOAP request has failed                        *
 ******************************************************************************/
static int	vmware_http_request(const char *fn_parent, CURL *easyhandle, const char *url_suffix,
		struct zbx_json_parse *jp, char **error)
{
	char		url[VMWARE_SHORT_STR_LEN];
	CURLcode	err;
	CURLoption	opt;
	ZBX_HTTPPAGE	*page;

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

	if (NULL != fn_parent)
		zabbix_log(LOG_LEVEL_TRACE, "%s() REST response: %s", fn_parent, page->data);

	return vmware_rest_response_open(page->data, jp, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: vmware web service GET call with REST error validation           *
 *                                                                            *
 * Parameters: fn_parent  - [IN] the parent function name for Log records     *
 *             easyhandle - [IN] the CURL handle                              *
 *             request    - [IN] the http request                             *
 *             jdoc       - [OUT] the json document response                  *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the SOAP request was completed successfully        *
 *               FAIL    - the SOAP request has failed                        *
 ******************************************************************************/
static int	vmware_rest_get(const char *fn_parent, CURL *easyhandle, const char *url_suffix, const char *param,
		struct zbx_json_parse *jp, char **error)
{
	CURLcode	err;
	CURLoption	opt;
	char		url[VMWARE_SHORT_STR_LEN];

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPGET, 1L)))
		return FAIL;

	zbx_snprintf(url, sizeof(url),"%s%s", url_suffix, param);

	return vmware_http_request(fn_parent, easyhandle, url, jp, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: vmware web service POST call with REST error validation           *
 *                                                                            *
 * Parameters: fn_parent  - [IN] the parent function name for Log records     *
 *             easyhandle - [IN] the CURL handle                              *
 *             request    - [IN] the http request                             *
 *             jdoc       - [OUT] the json document response                  *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the SOAP request was completed successfully        *
 *               FAIL    - the SOAP request has failed                        *
 ******************************************************************************/
static int	vmware_rest_post(const char *fn_parent, CURL *easyhandle, const char *url_suffix, const char *request,
		struct zbx_json_parse *jp, char **error)
{
	CURLcode	err;
	CURLoption	opt;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST, 1L)))
		return FAIL;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, request)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		return FAIL;
	}

	return vmware_http_request(fn_parent, easyhandle, url_suffix, jp, error);
}

static int	vmware_tags_linked_id_get(zbx_vmware_obj_id_t *obj_id, CURL *easyhandle, struct zbx_json_parse *jp,
		char **error)
{
	char	req[VMWARE_SHORT_STR_LEN];

	zbx_snprintf(req, sizeof(req),"{\"object_id\":{\"id\":\"%s\",\"type\":\"%s\"}}", obj_id->id, obj_id->type);

	return vmware_rest_post(__func__, easyhandle, "/cis/tagging/tag-association?action=list-attached-tags",
			req, jp, error);
}

static int	vmware_vectors_update(const char *tag_id, CURL *easyhandle, zbx_vector_vmware_tag_t *tags,
		zbx_vector_vmware_key_value_t *categories, char **error)
{
	struct zbx_json_parse	jp;
	int			i;
	char			cid[VMWARE_SHORT_STR_LEN], name[MAX_STRING_LEN], desc[MAX_STRING_LEN];
	zbx_vmware_key_value_t	cat_cmp;
	zbx_vmware_tag_t	*tag;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() tag_id:%s", __func__, tag_id);

	if (FAIL == vmware_rest_get(__func__, easyhandle, "/cis/tagging/tag/", tag_id, &jp, error))
		return FAIL;

	if (FAIL == zbx_json_value_by_name(&jp, "name", name, sizeof(name), NULL))
	{
		*error = zbx_dsprintf(*error, "Cannot read vmware response: %s", zbx_json_strerror());
		return FAIL;
	}

	if (FAIL == zbx_json_value_by_name(&jp, "description", desc, sizeof(desc), NULL))
	{
		*error = zbx_dsprintf(*error, "Cannot read vmware response: %s", zbx_json_strerror());
		return FAIL;
	}

	if (FAIL == zbx_json_value_by_name(&jp, "category_id", cid, sizeof(cid), NULL))
	{
		*error = zbx_dsprintf(*error, "Cannot read vmware response: %s", zbx_json_strerror());
		return FAIL;
	}

	cat_cmp.key = cid;

	if (FAIL == (i = zbx_vector_vmware_key_value_bsearch(categories, &cat_cmp, ZBX_DEFAULT_STR_COMPARE_FUNC)))
	{
		zbx_vmware_key_value_t	*category;
		char			value[MAX_STRING_LEN];

		if (FAIL == vmware_rest_get(__func__, easyhandle, "/cis/tagging/category/", cid, &jp, error))
			return FAIL;

		if (FAIL == zbx_json_value_by_name(&jp, "name", value, sizeof(value), NULL))
		{
			*error = zbx_dsprintf(*error, "Cannot read vmware response: %s", zbx_json_strerror());
			return FAIL;
		}

		category = (zbx_vmware_key_value_t *)zbx_malloc(NULL, sizeof(zbx_vmware_key_value_t));
		category->key = zbx_strdup(NULL, cid);
		category->value = zbx_strdup(NULL, value);
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
	tag->category  = zbx_strdup(NULL, categories->values[i]->value);
	zbx_vector_vmware_tag_append(tags, tag);
	zbx_vector_vmware_tag_sort(tags, zbx_vmware_tag_id_compare);

	if (FAIL == (i = zbx_vector_vmware_tag_bsearch(tags, tag, zbx_vmware_tag_id_compare)))
	{
		*error = zbx_strdup(NULL, "Cannot append tag info");
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() tag name:'%s' description:'%s' category:'%s'", __func__,
			tags->values[i]->name, tags->values[i]->description, tags->values[i]->category);

	return i;
}

static int	vmware_tags_get(zbx_vmware_entity_tags_t *entity_tags, zbx_vector_vmware_tag_t *tags,
		zbx_vector_vmware_key_value_t *categories, CURL *easyhandle)
{
	struct zbx_json_parse	jp;
	const char		*p = NULL;
	int			found_tags = 0;
	char			tag_id[VMWARE_SHORT_STR_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() obj_id:%s", __func__, entity_tags->obj_id->id);

	if (FAIL == vmware_tags_linked_id_get(entity_tags->obj_id, easyhandle, &jp, &entity_tags->error))
		goto out;

	while (NULL != (p = zbx_json_next_value(&jp, p, tag_id, sizeof(tag_id), NULL)))
	{
		int			i;
		zbx_vmware_tag_t	*tag, cmp = {.id = tag_id};

		if (FAIL == (i = zbx_vector_vmware_tag_bsearch(tags, &cmp, zbx_vmware_tag_id_compare))
				&& FAIL == (i = vmware_vectors_update(tag_id, easyhandle, tags, categories,
				&entity_tags->error)))
		{
			continue;
		}

		tag = (zbx_vmware_tag_t *) zbx_malloc(NULL, sizeof(zbx_vmware_tag_t));
		tag->name = zbx_strdup(NULL, tags->values[i]->name);
		tag->description = zbx_strdup(NULL, tags->values[i]->description);
		tag->category = zbx_strdup(NULL, tags->values[i]->category);
		tag->id = NULL;
		zbx_vector_vmware_tag_append(&entity_tags->tags, tag);
		found_tags++;
	}

	zbx_vector_vmware_tag_sort(&entity_tags->tags, ZBX_DEFAULT_STR_COMPARE_FUNC);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found tags:%d", __func__, found_tags);

	return found_tags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware tags data                                          *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_update_tags(zbx_vmware_service_t *service)
{
	int				i, found_tags = 0, ret = FAIL;
	zbx_vector_vmware_entity_tags_t	entity_tags;
	zbx_vector_vmware_tag_t	tags;
	zbx_vector_vmware_key_value_t	categories;
	CURL				*easyhandle = NULL;
	struct curl_slist		*headers = NULL;
	static ZBX_HTTPPAGE		page;
	char				*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_vmware_tag_create(&tags);
	zbx_vector_vmware_key_value_create(&categories);
	zbx_vector_vmware_entity_tags_create(&entity_tags);

	zbx_vmware_lock();
	vmware_entry_tags_init(service->data, &entity_tags);
	zbx_vmware_unlock();

	if (0 != entity_tags.values_num && (
			SUCCEED != vmware_curl_init(service->url, &easyhandle, &page, &headers, &error) ||
			SUCCEED != vmware_service_rest_authenticate(service, easyhandle, headers, &page, &error)))
	{
		goto clean;
	}

	for (i = 0; i < entity_tags.values_num; i++)
	{
		found_tags += vmware_tags_get(entity_tags.values[i], &tags, &categories, easyhandle);
	}

	zbx_vmware_shared_tags_replace(&entity_tags, &service->data_tags.entity_tags);

	ret = SUCCEED;
clean:
	if (FAIL == ret)
		zbx_vmware_shared_tags_error_set(error, &service->data_tags);

	zbx_vector_vmware_tag_clear_ext(&tags, vmware_tag_free);
	zbx_vector_vmware_key_value_clear_ext(&categories, vmware_key_value_free);
	zbx_vector_vmware_entity_tags_clear_ext(&entity_tags, vmware_entity_tags_free);
	zbx_vector_vmware_tag_destroy(&tags);
	zbx_vector_vmware_key_value_destroy(&categories);
	zbx_vector_vmware_entity_tags_destroy(&entity_tags);
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(page.data);

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, error);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s tags:%d", __func__, zbx_result_string(ret), found_tags);

	zbx_str_free(error);

	return ret;
}
