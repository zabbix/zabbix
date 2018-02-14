/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "checks_http.h"
#include "zbxhttp.h"
#include "zbxjson.h"
#include "log.h"
#include "zbxserver.h"
#ifdef HAVE_LIBCURL
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/tree.h>
#	include <libxml/xpath.h>
#endif

#define HTTPCHECK_REQUEST_GET	0
#define HTTPCHECK_REQUEST_POST	1
#define HTTPCHECK_REQUEST_PUT	2
#define HTTPCHECK_REQUEST_HEAD	3

#define HTTPCHECK_RETRIEVE_MODE_CONTENT	0
#define HTTPCHECK_RETRIEVE_MODE_HEADERS	1
#define HTTPCHECK_RETRIEVE_MODE_BOTH	2

#define HTTPCHECK_STORE_RAW		0
#define HTTPCHECK_STORE_JSON		1

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_http_response_t;

static const char	*zbx_request_string(int result)
{
	switch (result)
	{
		case HTTPCHECK_REQUEST_GET:
			return "GET";
		case HTTPCHECK_REQUEST_POST:
			return "POST";
		case HTTPCHECK_REQUEST_PUT:
			return "PUT";
		case HTTPCHECK_REQUEST_HEAD:
			return "HEAD";
		default:
			return "unknown";
	}
}

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_http_response_t	*response;

	response = (zbx_http_response_t*)userdata;
	zbx_strncpy_alloc(&response->data, &response->allocated, &response->offset, (const char *)ptr, r_size);

	return r_size;
}

static size_t	curl_ignore_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

static int	prepare_request(CURL *easyhandle, const char *posts, unsigned char request_method, AGENT_RESULT *result)
{
	CURLcode	err;

	switch (request_method)
	{
		case HTTPCHECK_REQUEST_POST:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify data to POST: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		case HTTPCHECK_REQUEST_GET:
			if ('\0' == *posts)
				return SUCCEED;

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify data to POST: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CUSTOMREQUEST, "GET")))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify custom GET request: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		case HTTPCHECK_REQUEST_HEAD:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_NOBODY, 1L)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify HEAD request: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		case HTTPCHECK_REQUEST_PUT:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify data to POST: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CUSTOMREQUEST, "PUT")))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify custom GET request: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported request method"));
			return FAIL;
	}

	return SUCCEED;
}

int	get_value_http(const DC_ITEM *item, AGENT_RESULT *result)
{
	const char		*__function_name = "get_value_http";
	CURL			*easyhandle;
	CURLcode		err;
	char			errbuf[CURL_ERROR_SIZE], *error = NULL, *headers, *line;
	int			ret = NOTSUPPORTED, timeout_seconds;
	long			response_code;
	struct curl_slist	*headers_slist = NULL;
	struct zbx_json		json;
	zbx_http_response_t	body = {0}, header = {0};
	size_t			(*curl_header_cb)(void *ptr, size_t size, size_t nmemb, void *userdata);
	size_t			(*curl_body_cb)(void *ptr, size_t size, size_t nmemb, void *userdata);
	char			application_json[] = {"Content-Type: application/json"};
	char			application_xml[] = {"Content-Type: application/xml"};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request method '%s' URL '%s' headers '%s' message body '%s'",
			__function_name, zbx_request_string(item->request_method), item->url, item->headers,
			item->posts);

	if (NULL == (easyhandle = curl_easy_init()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize cURL library"));
		goto clean;
	}

	switch (item->retrieve_mode)
	{
		case HTTPCHECK_RETRIEVE_MODE_CONTENT:
			curl_header_cb = curl_ignore_cb;
			curl_body_cb = curl_write_cb;
			break;
		case HTTPCHECK_RETRIEVE_MODE_HEADERS:
			curl_header_cb = curl_write_cb;
			curl_body_cb = curl_ignore_cb;
			break;
		case HTTPCHECK_RETRIEVE_MODE_BOTH:
			curl_header_cb = curl_write_cb;
			curl_body_cb = curl_write_cb;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid retrieve mode"));
			goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERFUNCTION, curl_header_cb)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set header function: %s",
				curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERDATA, &header)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set header callback: %s",
				curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEFUNCTION, curl_body_cb)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set write function: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEDATA, &body)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set write callback: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set error buffer: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROXY, item->http_proxy)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set proxy: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION,
			0 == item->follow_redirects ? 0L : 1L)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set follow redirects: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (0 != item->follow_redirects &&
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAXREDIRS, ZBX_CURLOPT_MAXREDIRS)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set number of redirects allowed: %s",
				curl_easy_strerror(err)));
		goto clean;
	}

	if (FAIL == is_time_suffix(item->timeout, &timeout_seconds, strlen(item->timeout)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid timeout: %s", item->timeout));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)timeout_seconds)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify timeout: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (SUCCEED != zbx_prepare_https(easyhandle, item->ssl_cert_file, item->ssl_key_file, item->ssl_key_password,
			item->verify_peer, item->verify_host, &error))
	{
		SET_MSG_RESULT(result, error);
		goto clean;
	}

	if (SUCCEED != zbx_prepare_httpauth(easyhandle, item->authtype, item->username, item->password, &error))
	{
		SET_MSG_RESULT(result, error);
		goto clean;
	}

	if (SUCCEED != prepare_request(easyhandle, item->posts, item->request_method, result))
		goto clean;

	if ('\0' == *item->headers)
	{
		if (ZBX_POSTTYPE_JSON == item->post_type)
			zbx_add_httpheaders(application_json, &headers_slist);
		else if (ZBX_POSTTYPE_XML == item->post_type)
			zbx_add_httpheaders(application_xml, &headers_slist);
	}
	else
		zbx_add_httpheaders(item->headers, &headers_slist);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER, headers_slist)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify headers: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, item->url)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify URL: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot perform request: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &response_code)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get the response code: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if ('\0' != *item->status_codes && FAIL == int_in_list(item->status_codes, response_code))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Response code \"%ld\" did not match any of the"
				" required status codes \"%s\"", response_code, item->status_codes));
		goto clean;
	}

	switch (item->retrieve_mode)
	{
		case HTTPCHECK_RETRIEVE_MODE_CONTENT:
			if (NULL == body.data)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Server returned empty content"));
				goto clean;
			}

			if (HTTPCHECK_STORE_JSON == item->output_format)
			{
				zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
				zbx_json_addstring(&json, "body", body.data, ZBX_JSON_TYPE_STRING);
				SET_TEXT_RESULT(result, zbx_strdup(NULL, json.buffer));
				zbx_json_free(&json);
			}
			else
			{
				SET_TEXT_RESULT(result, body.data);
				body.data = NULL;
			}
			break;
		case HTTPCHECK_RETRIEVE_MODE_HEADERS:
			if (NULL == header.data)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Server returned empty header"));
				goto clean;
			}

			if (HTTPCHECK_STORE_JSON == item->output_format)
			{
				zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
				zbx_json_addarray(&json, "header");
				headers = header.data;
				while (NULL != (line = zbx_get_httpheader(&headers)))
				{
					zbx_json_addstring(&json, NULL, line, ZBX_JSON_TYPE_STRING);
					zbx_free(line);
				}
				SET_TEXT_RESULT(result, zbx_strdup(NULL, json.buffer));
				zbx_json_free(&json);
			}
			else
			{
				SET_TEXT_RESULT(result, header.data);
				header.data = NULL;
			}
			break;
		case HTTPCHECK_RETRIEVE_MODE_BOTH:
			if (NULL == header.data)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Server returned empty header"));
				goto clean;
			}

			if (HTTPCHECK_STORE_JSON == item->output_format)
			{
				unsigned char	json_content = 0;

				zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
				zbx_json_addarray(&json, "header");
				headers = header.data;
				while (NULL != (line = zbx_get_httpheader(&headers)))
				{
					zbx_json_addstring(&json, NULL, line, ZBX_JSON_TYPE_STRING);

					if (0 == json_content && 0 == strcmp(line, application_json))
						json_content = 1;

					zbx_free(line);
				}
				zbx_json_close(&json);

				if (NULL != body.data)
				{
					if (1 == json_content)
					{
						zbx_lrtrim(body.data, ZBX_WHITESPACE);
						if ('\0' != *body.data)
							zbx_json_addraw(&json, "body", body.data);
					}
					else
						zbx_json_addstring(&json, "body", body.data, ZBX_JSON_TYPE_STRING);
				}

				SET_TEXT_RESULT(result, zbx_strdup(NULL, json.buffer));
				zbx_json_free(&json);
			}
			else
			{
				zbx_strncpy_alloc(&header.data, &header.allocated, &header.offset,
						body.data, body.offset);
				SET_TEXT_RESULT(result, header.data);
				header.data = NULL;
			}
			break;
	}

	ret = SUCCEED;
clean:
	curl_slist_free_all(headers_slist);	/* must be called after curl_easy_perform() */
	curl_easy_cleanup(easyhandle);
	zbx_free(body.data);
	zbx_free(header.data);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#ifdef HAVE_LIBXML2
static void	substitute_simple_macros_in_xml_elements(DC_ITEM *item, int macro_type, xmlNode *node)
{
	xmlChar	*value;
	char	*value_tmp, *value_esc;

	for (;NULL != node; node = node->next)
	{
		if (XML_TEXT_NODE == node->type)
		{
			if (NULL != (value = xmlNodeGetContent(node)))
			{
				value_tmp = zbx_strdup(NULL, (const char *)value);
				substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &item->host, item, NULL, NULL,
						&value_tmp, macro_type, NULL, 0);
				value_esc = xml_escape_dyn(value_tmp);

				xmlNodeSetContent(node, (xmlChar *)value_esc);

				zbx_free(value_esc);
				zbx_free(value_tmp);
				xmlFree(value);
			}
		}
		else if (XML_ELEMENT_NODE == node->type)
		{
			xmlAttr	*attr;

			for (attr = node->properties; NULL != attr; attr = attr->next)
			{
				if (NULL == (value = xmlGetProp(node, attr->name)))
					continue;

				value_tmp = zbx_strdup(NULL, (const char *)value);
				substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &item->host, item, NULL, NULL,
						&value_tmp, macro_type, NULL, 0);
				value_esc = xml_escape_dyn(value_tmp);

				xmlSetProp(node, attr->name, (xmlChar *)value_esc);

				zbx_free(value_esc);
				zbx_free(value_tmp);
				xmlFree(value);
			}
		}

		substitute_simple_macros_in_xml_elements(item, macro_type, node->children);
	}
}

int	zbx_substitute_simple_macros_in_xml(char **data, DC_ITEM *item, int macro_type, char *error, int maxerrlen)
{
	xmlDoc		*doc;
	xmlErrorPtr	pErr;
	xmlNode		*root_element;
	xmlChar		*mem;
	int		size, ret = FAIL;

	if (NULL == (doc = xmlReadMemory(*data, strlen(*data), "noname.xml", NULL, 0)))
	{
		if (NULL != (pErr = xmlGetLastError()))
			zbx_snprintf(error, maxerrlen, "Cannot parse XML value: %s", pErr->message);
		else
			zbx_snprintf(error, maxerrlen, "Cannot parse XML value");

		return FAIL;
	}

	if (NULL == (root_element = xmlDocGetRootElement(doc)))
	{
		zbx_snprintf(error, maxerrlen, "Cannot parse XML root");
		goto clean;
	}

	substitute_simple_macros_in_xml_elements(item, macro_type, root_element);
	xmlDocDumpMemory(doc, &mem, &size);

	if (NULL == mem)
	{
		zbx_snprintf(error, maxerrlen, "Cannot save XML");
		goto clean;
	}

	*data = zbx_strdup(*data, (const char *)mem);
	xmlFree(mem);
	ret = SUCCEED;
clean:
	xmlFreeDoc(doc);

	return ret;
}
#endif
#endif
