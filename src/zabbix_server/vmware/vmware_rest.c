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

ZBX_PTR_VECTOR_IMPL(vmware_tag, zbx_vmware_tag_t *)
ZBX_PTR_VECTOR_IMPL(vmware_entity_tags, zbx_vmware_entity_tags_t *)

static zbx_vmware_entity_tags_t	*vmware_entry_tag_create(const char *type, const char *id, const char *uuid)
{
	zbx_vmware_entity_tags_t	*entry_tag;

	entry_tag = (zbx_vmware_entity_tags_t *)zbx_malloc(NULL, sizeof(zbx_vmware_entity_tags_t));
	entry_tag->obj_id = (zbx_vmware_obj_id_t *)zbx_malloc(NULL, sizeof(zbx_vmware_obj_id_t));
	zbx_vector_vmware_tag_create(&entry_tag->tags);
	entry_tag->obj_id->type = zbx_strdup(NULL, type);
	entry_tag->obj_id->id = zbx_strdup(NULL, id);
	entry_tag->uuid = zbx_strdup(NULL, uuid);

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
	zbx_vmware_vm_t		*vm;

	zbx_hashset_iter_reset(&data->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_vmware_entity_tags_append(entity_tags,
				vmware_entry_tag_create(ZBX_VMWARE_SOAP_HV, hv->id, hv->uuid));
	}

	zbx_hashset_iter_reset(&data->vms_index, &iter);
	while (NULL != (vm = (zbx_vmware_vm_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_vmware_entity_tags_append(entity_tags,
				vmware_entry_tag_create(ZBX_VMWARE_SOAP_VM, vm->id, vm->uuid));
	}

	for (i = 0; i < data->datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*ds = data->datastores.values[i];

		zbx_vector_vmware_entity_tags_append(entity_tags,
				vmware_entry_tag_create(ZBX_VMWARE_SOAP_DS, ds->id, ds->uuid));
	}
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

	if (url_sz < 5 || 0 != strcmp(&page->url[url_sz - 5], "/sdk"))
	{
		*error = zbx_strdup(*error, "cannot initialize rest service url");
		goto out;
	}

	strscpy(&page->url[url_sz - 4], "api");
	*headers = curl_slist_append(*headers, ZBX_XML_HEADER1);
	*headers = curl_slist_append(*headers, ZBX_XML_HEADER2);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, *headers)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEFUNCTION, curl_write_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEDATA, page)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_PRIVATE, page)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HEADERFUNCTION, curl_header_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT,
					(long)CONFIG_VMWARE_TIMEOUT)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = ZBX_CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}
out:
	return ret;

#	undef INIT_PERF_REST_SIZE
#	undef ZBX_XML_HEADER1
#	undef ZBX_XML_HEADER2
}

static int	vmware_rest_responce_error(const char *data, char **error)
{
	struct zbx_json_parse	jp;
	char			err_type[VMWARE_SHORT_STR_LEN];

	if (SUCCEED == zbx_json_open(data, &jp) &&
			SUCCEED == zbx_json_value_by_name(&jp, "error_type", err_type, sizeof(err_type), NULL))
	{
		char	err_msg[VMWARE_SHORT_STR_LEN];

		if (SUCCEED != zbx_json_value_by_name(&jp, "default_message", err_msg, sizeof(err_msg), NULL))
			err_msg[0] = '\0';

		*error = zbx_dsprintf(*error, "%s:%s", err_type, err_msg);

		return SUCCEED;
	}

	return FAIL;
}

static int	vmware_service_authenticate(zbx_vmware_service_t *service, CURL *easyhandle, ZBX_HTTPPAGE *page,
		 struct curl_slist **headers, char **error)
{
	int		ret = FAIL;
	char		tmp[VMWARE_SHORT_STR_LEN];
	CURLcode	err;
	CURLoption	opt;

	zbx_snprintf(tmp, sizeof(tmp),"%s/session", page->url);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPGET, 1L)) ||
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

	if (SUCCEED == vmware_rest_responce_error(page->data, error))
		goto out;

	zbx_ltrim(page->data, "\"");
	zbx_rtrim(page->data, "\"");
	zbx_snprintf(tmp, sizeof(tmp),"vmware-api-session-id: %s", page->data);
	*headers = curl_slist_append(*headers, tmp);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, *headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}
out:
	return ret;
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
	int				i, ret = FAIL;
	zbx_vector_vmware_entity_tags_t	entity_tags;
	zbx_vector_vmware_key_value_t	tags, categories;
	CURL				*easyhandle = NULL;
	struct curl_slist		*headers = NULL;
	static ZBX_HTTPPAGE		page;
	char				*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	zbx_vector_vmware_key_value_create(&tags);
	zbx_vector_vmware_key_value_create(&categories);
	zbx_vector_vmware_entity_tags_create(&entity_tags);

	zbx_vmware_lock();
	vmware_entry_tags_init(service->data, &entity_tags);
	zbx_vmware_unlock();

	if (0 != entity_tags.values_num && (SUCCEED != vmware_curl_init(service->url, &easyhandle, &page, &headers,
			&error) || SUCCEED != vmware_service_authenticate(service, easyhandle, &page, &headers,
			&error)))
	{
		goto clean;
	}

	for (i = 0; i < entity_tags.values_num; i++)
	{

	}

clean:
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(page.data);

	return ret;
}
