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

#include "audit/zbxaudit_httptest.h"

#include "audit/zbxaudit.h"
#include "log.h"
#include "zbxalgo.h"
#include "audit.h"
#include "zbxdbhigh.h"
#include "zbxdb.h"

void	zbx_audit_httptest_create_entry(int audit_action, zbx_uint64_t httptestid, const char *name)
{
	zbx_audit_entry_t	local_audit_httptest_entry, **found_audit_httptest_entry;
	zbx_audit_entry_t	*local_audit_httptest_entry_x = &local_audit_httptest_entry;

	RETURN_IF_AUDIT_OFF();

	local_audit_httptest_entry.id = httptestid;
	local_audit_httptest_entry.cuid = NULL;
	local_audit_httptest_entry.id_table = AUDIT_HTTPTEST_ID;

	found_audit_httptest_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),
			&(local_audit_httptest_entry_x));

	if (NULL == found_audit_httptest_entry)
	{
		zbx_audit_entry_t	*local_audit_httptest_entry_insert;

		local_audit_httptest_entry_insert = zbx_audit_entry_init(httptestid, AUDIT_HTTPTEST_ID, name,
				audit_action, AUDIT_RESOURCE_SCENARIO);

		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_httptest_entry_insert,
				sizeof(local_audit_httptest_entry_insert));
	}
}

void	zbx_audit_httptest_update_json_add_data(zbx_uint64_t httptestid, zbx_uint64_t templateid, const char *name,
		const char *delay, unsigned char status, const char *agent, unsigned char authentication,
		const char *httpuser, const char *httppassword, const char *http_proxy, int retries,
		const char *ssl_cert_file, const char *ssl_key_file, const char *ssl_key_password, int verify_peer,
		int verify_host, zbx_uint64_t hostid)
{
	char	audit_key_templateid[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_delay[AUDIT_DETAILS_KEY_LEN], audit_key_status[AUDIT_DETAILS_KEY_LEN],
		audit_key_agent[AUDIT_DETAILS_KEY_LEN], audit_key_authentication[AUDIT_DETAILS_KEY_LEN],
		audit_key_httpuser[AUDIT_DETAILS_KEY_LEN], audit_key_http_proxy[AUDIT_DETAILS_KEY_LEN],
		audit_key_retries[AUDIT_DETAILS_KEY_LEN], audit_key_ssl_cert_file[AUDIT_DETAILS_KEY_LEN],
		audit_key_ssl_key_file[AUDIT_DETAILS_KEY_LEN], audit_key_verify_peer[AUDIT_DETAILS_KEY_LEN],
		audit_key_verify_host[AUDIT_DETAILS_KEY_LEN], audit_key_hostid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

#define AUDIT_KEY_SNPRINTF(r) zbx_snprintf(audit_key_##r, sizeof(audit_key_##r), "httptest."#r);
#define AUDIT_TABLE_NAME	"httptest"
	zbx_audit_update_json_append_uint64(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			"httptest.httptestid", httptestid, AUDIT_TABLE_NAME, "httptestid");
	AUDIT_KEY_SNPRINTF(templateid)
	AUDIT_KEY_SNPRINTF(name)
	AUDIT_KEY_SNPRINTF(delay)
	AUDIT_KEY_SNPRINTF(status)
	AUDIT_KEY_SNPRINTF(agent)
	AUDIT_KEY_SNPRINTF(authentication)
	AUDIT_KEY_SNPRINTF(httpuser)
	AUDIT_KEY_SNPRINTF(http_proxy)
	AUDIT_KEY_SNPRINTF(retries)
	AUDIT_KEY_SNPRINTF(ssl_cert_file)
	AUDIT_KEY_SNPRINTF(ssl_key_file)
	AUDIT_KEY_SNPRINTF(verify_peer)
	AUDIT_KEY_SNPRINTF(verify_host)
	AUDIT_KEY_SNPRINTF(hostid)
#undef AUDIT_KEY_SNPRINTF
#define ADD_STR(r, t, f) zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, \
		audit_key_##r, r, t, f);
#define ADD_UINT64(r, t, f) zbx_audit_update_json_append_uint64(httptestid, AUDIT_HTTPTEST_ID, \
		AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r, t, f);
#define ADD_INT(r, t, f) zbx_audit_update_json_append_int(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, \
		audit_key_##r, r, t, f);
	ADD_UINT64(templateid, AUDIT_TABLE_NAME, "templateid")
	ADD_STR(name, AUDIT_TABLE_NAME, "name")
	ADD_STR(delay, AUDIT_TABLE_NAME, "delay")
	ADD_INT(status, AUDIT_TABLE_NAME, "status")
	ADD_STR(agent, AUDIT_TABLE_NAME, "agent")
	ADD_INT(authentication, AUDIT_TABLE_NAME, "authentication")
	ADD_STR(httpuser, AUDIT_TABLE_NAME, "http_user")
	zbx_audit_update_json_append_string_secret(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			"httptest.httppassword", httppassword, AUDIT_TABLE_NAME, "http_password");
	ADD_STR(http_proxy, AUDIT_TABLE_NAME, "http_proxy")
	ADD_INT(retries, AUDIT_TABLE_NAME, "retries")
	ADD_STR(ssl_cert_file, AUDIT_TABLE_NAME, "ssl_cert_file")
	ADD_STR(ssl_key_file, AUDIT_TABLE_NAME, "ssl_key_file")
	zbx_audit_update_json_append_string_secret(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			"httptest.ssl_key_password", ssl_key_password, AUDIT_TABLE_NAME, "ssl_key_password");
	ADD_INT(verify_peer, AUDIT_TABLE_NAME, "verify_peer")
	ADD_INT(verify_host, AUDIT_TABLE_NAME, "verify_host")
	ADD_UINT64(hostid, AUDIT_TABLE_NAME, "hostid")
#undef AUDIT_TABLE_NAME
#undef ADD_STR
#undef ADD_UINT64
#undef ADD_INT
}

#define PREPARE_AUDIT_HTTPTEST_UPDATE(resource, type1, type2)							\
void	zbx_audit_httptest_update_json_update_##resource(zbx_uint64_t httptestid, type1 resource##_old,		\
		type1 resource##_new)										\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(buf, sizeof(buf), "httptest."#resource);							\
														\
	zbx_audit_update_json_update_##type2(httptestid, AUDIT_HTTPTEST_ID, buf, resource##_old,		\
			resource##_new);									\
}

PREPARE_AUDIT_HTTPTEST_UPDATE(templateid, zbx_uint64_t, uint64)
PREPARE_AUDIT_HTTPTEST_UPDATE(delay, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(agent, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(http_user, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(http_password, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(http_proxy, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(retries, int, int)
PREPARE_AUDIT_HTTPTEST_UPDATE(status, int, int)
PREPARE_AUDIT_HTTPTEST_UPDATE(authentication, int, int)
PREPARE_AUDIT_HTTPTEST_UPDATE(ssl_cert_file, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(ssl_key_file, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(ssl_key_password, const char*, string)
PREPARE_AUDIT_HTTPTEST_UPDATE(verify_peer, int, int)
PREPARE_AUDIT_HTTPTEST_UPDATE(verify_host, int, int)

#undef PREPARE_AUDIT_HTTPTEST_UPDATE

int	zbx_audit_DBselect_delete_for_httptest(const char *sql, zbx_vector_uint64_t *ids)
{
	DB_RESULT	result;
	DB_ROW		row;

	if (NULL == (result = DBselect("%s", sql)))
		return FAIL;

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	id;

		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(ids, id);

		zbx_audit_httptest_create_entry(ZBX_AUDIT_ACTION_DELETE, id, row[1]);
	}

	DBfree_result(result);

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	return SUCCEED;
}

void	zbx_audit_httptest_update_json_add_httptest_tag(zbx_uint64_t httptestid, zbx_uint64_t tagid, const char *tag,
		const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "httptest.tags[" ZBX_FS_UI64 "]", tagid);
	zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "httptest.tags[" ZBX_FS_UI64 "].tag", tagid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "httptest.tags[" ZBX_FS_UI64 "].value", tagid);

#define AUDIT_TABLE_NAME	"httptest_tag"
	zbx_audit_update_json_append_no_value(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_tag,
			tag, AUDIT_TABLE_NAME, "tag");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value,
			value, AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_httptest_update_json_delete_tags(zbx_uint64_t httptestid, zbx_uint64_t tagid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "httptest.tags[" ZBX_FS_UI64 "]", tagid);

	zbx_audit_update_json_append_no_value(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_httptest_update_json_add_httptest_httpstep(zbx_uint64_t httptestid, zbx_uint64_t httpstepid,
		const char *name, int no, const char *url, const char *timeout, const char *posts, const char *required,
		const char *status_codes, int follow_redirects, int retrieve_mode, int post_type)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_no[AUDIT_DETAILS_KEY_LEN], audit_key_url[AUDIT_DETAILS_KEY_LEN],
		audit_key_timeout[AUDIT_DETAILS_KEY_LEN], audit_key_posts[AUDIT_DETAILS_KEY_LEN],
		audit_key_required[AUDIT_DETAILS_KEY_LEN], audit_key_status_codes[AUDIT_DETAILS_KEY_LEN],
		audit_key_follow_redirects[AUDIT_DETAILS_KEY_LEN], audit_key_retrieve_mode[AUDIT_DETAILS_KEY_LEN],
		audit_key_post_type[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "httptest.steps[" ZBX_FS_UI64 "]", httpstepid);
	zbx_snprintf(audit_key_name, sizeof(audit_key_name), "httptest.steps[" ZBX_FS_UI64 "].name", httpstepid);
	zbx_snprintf(audit_key_no, sizeof(audit_key_no), "httptest.steps[" ZBX_FS_UI64 "].no", httpstepid);
	zbx_snprintf(audit_key_url, sizeof(audit_key_url), "httptest.steps[" ZBX_FS_UI64 "].url", httpstepid);
	zbx_snprintf(audit_key_timeout, sizeof(audit_key_timeout), "httptest.steps[" ZBX_FS_UI64 "].timeout",
			httpstepid);
	zbx_snprintf(audit_key_posts, sizeof(audit_key_posts), "httptest.steps[" ZBX_FS_UI64 "].posts", httpstepid);
	zbx_snprintf(audit_key_required, sizeof(audit_key_required), "httptest.steps[" ZBX_FS_UI64 "].required",
			httpstepid);
	zbx_snprintf(audit_key_status_codes, sizeof(audit_key_status_codes),
			"httptest.steps[" ZBX_FS_UI64 "].status_codes", httpstepid);
	zbx_snprintf(audit_key_follow_redirects, sizeof(audit_key_follow_redirects),
			"httptest.steps[" ZBX_FS_UI64 "].follow_redirects", httpstepid);
	zbx_snprintf(audit_key_retrieve_mode, sizeof(audit_key_retrieve_mode),
			"httptest.steps[" ZBX_FS_UI64 "].retrieve_mode", httpstepid);
	zbx_snprintf(audit_key_post_type, sizeof(audit_key_post_type), "httptest.steps[" ZBX_FS_UI64 "].post_type",
			httpstepid);

#define AUDIT_TABLE_NAME	"httpstep"
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name,
			name, AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_int(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_no, no,
			AUDIT_TABLE_NAME, "no");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_url,
			url, AUDIT_TABLE_NAME, "url");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_timeout,
			timeout, AUDIT_TABLE_NAME, "timeout");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_posts,
			posts, AUDIT_TABLE_NAME, "posts");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_required, required, AUDIT_TABLE_NAME, "required");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_status_codes, status_codes, AUDIT_TABLE_NAME, "status_codes");
	zbx_audit_update_json_append_int(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_follow_redirects, follow_redirects, AUDIT_TABLE_NAME, "follow_redirects");
	zbx_audit_update_json_append_int(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_retrieve_mode, retrieve_mode, AUDIT_TABLE_NAME, "retrieve_mode");
	zbx_audit_update_json_append_int(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_post_type, post_type, AUDIT_TABLE_NAME, "post_type");
#undef AUDIT_TABLE_NAME
}

#define PREPARE_AUDIT_HTTPSTEP_UPDATE(resource, type1, type2)							\
void	zbx_audit_httptest_update_json_httpstep_update_##resource(zbx_uint64_t httptestid,			\
		zbx_uint64_t httpstepid, type1 resource##_old, type1 resource##_new)				\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(buf, sizeof(buf), "httptest.steps[" ZBX_FS_UI64 "]."#resource, httpstepid);		\
														\
	zbx_audit_update_json_update_##type2(httptestid, AUDIT_HTTPTEST_ID, buf, resource##_old,		\
			resource##_new);									\
}

PREPARE_AUDIT_HTTPSTEP_UPDATE(url, const char*, string)
PREPARE_AUDIT_HTTPSTEP_UPDATE(posts, const char*, string)
PREPARE_AUDIT_HTTPSTEP_UPDATE(required, const char*, string)
PREPARE_AUDIT_HTTPSTEP_UPDATE(status_codes, const char*, string)
PREPARE_AUDIT_HTTPSTEP_UPDATE(timeout, const char*, string)
PREPARE_AUDIT_HTTPSTEP_UPDATE(follow_redirects, int, int)
PREPARE_AUDIT_HTTPSTEP_UPDATE(retrieve_mode, int, int)
PREPARE_AUDIT_HTTPSTEP_UPDATE(post_type, int, int)

#undef PREPARE_AUDIT_HTTPTEST_UPDATE

static const char *field_type_to_name(int type)
{
	if (ZBX_HTTPFIELD_HEADER == type)
	{
		return "header";
	}
	else if (ZBX_HTTPFIELD_VARIABLE == type)
	{
		return "variable";
	}
	else if (ZBX_HTTPFIELD_POST_FIELD == type)
	{
		return "post_field";
	}
	else if (ZBX_HTTPFIELD_QUERY_FIELD == type)
	{
		return "query_field";
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "unexpected http field type: ->%d<-", type);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

void	zbx_audit_httptest_update_json_add_httptest_field(zbx_uint64_t httptestid, zbx_uint64_t httptestfieldid,
		int type, const char *name, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],
		audit_key_name[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key_type, sizeof(audit_key_type), "%s", field_type_to_name(type));
	zbx_snprintf(audit_key, sizeof(audit_key), "httptest.%s[" ZBX_FS_UI64 "]", audit_key_type, httptestfieldid);
	zbx_snprintf(audit_key_name, sizeof(audit_key_name), "httptest.%s[" ZBX_FS_UI64 "].name", audit_key_type,
			httptestfieldid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "httptest.%s[" ZBX_FS_UI64 "].value", audit_key_type,
			httptestfieldid);

#define AUDIT_TABLE_NAME	"httpstep_field"
	zbx_audit_update_json_append_no_value(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name,
			name, AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value,
			value, AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_httptest_update_json_delete_httptest_field(zbx_uint64_t httptestid, zbx_uint64_t fieldid, int type)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key_type, sizeof(audit_key_type), "%s", field_type_to_name(type));
	zbx_snprintf(audit_key, sizeof(audit_key), "httptest.%s[" ZBX_FS_UI64 "]",audit_key_type, fieldid);

	zbx_audit_update_json_append_no_value(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key);
}

void	zbx_audit_httptest_update_json_add_httpstep_field(zbx_uint64_t httptestid, zbx_uint64_t httpstepid,
		zbx_uint64_t httpstepfieldid, int type, const char *name, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],
		audit_key_name[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key_type, sizeof(audit_key_type), "%s", field_type_to_name(type));
	zbx_snprintf(audit_key, sizeof(audit_key), "httptest.steps[" ZBX_FS_UI64 "].%s[" ZBX_FS_UI64 "]",
			httpstepid, audit_key_type, httpstepfieldid);
	zbx_snprintf(audit_key_name, sizeof(audit_key_name), "httptest.steps[" ZBX_FS_UI64 "].%s[" ZBX_FS_UI64 "].name",
			httpstepid, audit_key_type,  httpstepfieldid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value),
			"httptest.steps[" ZBX_FS_UI64 "].%s[" ZBX_FS_UI64 "].value", httpstepid, audit_key_type,
			httpstepfieldid);
#define AUDIT_TABLE_NAME	"httpstep_field"
	zbx_audit_update_json_append_no_value(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name,
			name, AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_string(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value,
			value, AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_httptest_update_json_delete_httpstep_field(zbx_uint64_t httptestid, zbx_uint64_t httpstepid,
		zbx_uint64_t fieldid, int type)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key_type, sizeof(audit_key_type), "%s", field_type_to_name(type));
	zbx_snprintf(audit_key, sizeof(audit_key), "httptest.steps[" ZBX_FS_UI64 "].%s[" ZBX_FS_UI64 "]",
			httpstepid, audit_key_type, fieldid);

	zbx_audit_update_json_append_no_value(httptestid, AUDIT_HTTPTEST_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key);
}
