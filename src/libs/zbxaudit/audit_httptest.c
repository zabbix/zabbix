/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "dbcache.h"

#include "log.h"
#include "audit_httptest.h"

void	zbx_audit_httptest_create_entry(int audit_action, zbx_uint64_t httptestid, const char *name)
{
	zbx_audit_entry_t	local_audit_httptest_entry, **found_audit_httptest_entry;
	zbx_audit_entry_t	*local_audit_httptest_entry_x = &local_audit_httptest_entry;

	RETURN_IF_AUDIT_OFF();

	local_audit_httptest_entry.id = httptestid;

	found_audit_httptest_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),
			&(local_audit_httptest_entry_x));
	if (NULL == found_audit_httptest_entry)
	{
		zbx_audit_entry_t	*local_audit_httptest_entry_insert;

		local_audit_httptest_entry_insert = (zbx_audit_entry_t*)zbx_malloc(NULL,
				sizeof(zbx_audit_entry_t));
		local_audit_httptest_entry_insert->id = httptestid;
		local_audit_httptest_entry_insert->name = zbx_strdup(NULL, name);
		local_audit_httptest_entry_insert->audit_action = audit_action;
		local_audit_httptest_entry_insert->resource_type = AUDIT_RESOURCE_SCENARIO;
		zbx_json_init(&(local_audit_httptest_entry_insert->details_json), ZBX_JSON_STAT_BUF_LEN);
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_httptest_entry_insert,
				sizeof(local_audit_httptest_entry_insert));

		if (AUDIT_ACTION_ADD == audit_action)
		{
			zbx_audit_update_json_append_uint64(httptestid, AUDIT_DETAILS_ACTION_ADD,
					"httptest.httptestid", httptestid);
		}
	}
}

void	zbx_audit_httptest_update_json_add_data(zbx_uint64_t httptestid, zbx_uint64_t templateid, const char *name,
		const char *delay, unsigned char status, const char *agent, unsigned char authentication,
		const char *httpuser, const char *httppassword, const char *http_proxy, int retries,
		zbx_uint64_t hostid)
{
	char	audit_key_templateid[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_delay[AUDIT_DETAILS_KEY_LEN], audit_key_status[AUDIT_DETAILS_KEY_LEN],
		audit_key_agent[AUDIT_DETAILS_KEY_LEN], audit_key_authentication[AUDIT_DETAILS_KEY_LEN],
		audit_key_httpuser[AUDIT_DETAILS_KEY_LEN], audit_key_httppassword[AUDIT_DETAILS_KEY_LEN],
		audit_key_http_proxy[AUDIT_DETAILS_KEY_LEN], audit_key_retries[AUDIT_DETAILS_KEY_LEN],
		audit_key_hostid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

#define AUDIT_KEY_SNPRINTF(r) zbx_snprintf(audit_key_##r, sizeof(audit_key_##r), "httptest."#r);
	AUDIT_KEY_SNPRINTF(templateid)
	AUDIT_KEY_SNPRINTF(name)
	AUDIT_KEY_SNPRINTF(delay)
	AUDIT_KEY_SNPRINTF(status)
	AUDIT_KEY_SNPRINTF(agent)
	AUDIT_KEY_SNPRINTF(authentication)
	AUDIT_KEY_SNPRINTF(httpuser)
	AUDIT_KEY_SNPRINTF(httppassword)
	AUDIT_KEY_SNPRINTF(http_proxy)
	AUDIT_KEY_SNPRINTF(retries)
	AUDIT_KEY_SNPRINTF(hostid)
#undef AUDIT_KEY_SNPRINTF
	zbx_audit_update_json_append_no_value(httptestid, AUDIT_DETAILS_ACTION_ADD, "httptest");
#define ADD_STR(r) zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r);
#define ADD_UINT64(r) zbx_audit_update_json_append_uint64(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r);
#define ADD_INT(r) zbx_audit_update_json_append_int(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r);
	ADD_UINT64(templateid)
	ADD_STR(name)
	ADD_STR(delay)
	ADD_INT(status)
	ADD_STR(agent)
	ADD_INT(authentication)
	ADD_STR(httpuser)
	ADD_STR(httppassword)
	ADD_STR(http_proxy)
	ADD_INT(retries)
	ADD_UINT64(hostid)
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
	zbx_audit_update_json_update_##type2(httptestid, buf, resource##_old, resource##_new);			\
}

PREPARE_AUDIT_HTTPTEST_UPDATE(templateid, zbx_uint64_t, uint64)

#undef PREPARE_AUDIT_HTTPTEST_UPDATE

void	zbx_audit_DBselect_delete_for_httptest(const char *sql, zbx_vector_uint64_t *ids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(ids, id);

		zbx_audit_httptest_create_entry(AUDIT_ACTION_DELETE, id, row[1]);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

void	zbx_audit_httptest_update_json_add_httptest_tag(zbx_uint64_t httptestid, zbx_uint64_t tagid, const char *tag,
		const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "httptest.tags[" ZBX_FS_UI64 "]", tagid);
	zbx_snprintf(audit_key_tag, AUDIT_DETAILS_KEY_LEN, "httptest.tags[" ZBX_FS_UI64 "].tag", tagid);
	zbx_snprintf(audit_key_value, AUDIT_DETAILS_KEY_LEN, "httptest.tags[" ZBX_FS_UI64 "].value",
			tagid);

	zbx_audit_update_json_append_no_value(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value);
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

	zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "]", httpstepid);
	zbx_snprintf(audit_key_name,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].name", httpstepid);
	zbx_snprintf(audit_key_no,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].no", httpstepid);
	zbx_snprintf(audit_key_url,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].url", httpstepid);
	zbx_snprintf(audit_key_timeout,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].timeout", httpstepid);
	zbx_snprintf(audit_key_posts,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].posts", httpstepid);
	zbx_snprintf(audit_key_required,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].required",
			httpstepid);
	zbx_snprintf(audit_key_status_codes,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].status_codes",
			httpstepid);
	zbx_snprintf(audit_key_follow_redirects,  AUDIT_DETAILS_KEY_LEN,
			"httptest.steps[" ZBX_FS_UI64 "].follow_redirects", httpstepid);
	zbx_snprintf(audit_key_retrieve_mode,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].retrieve_mode",
			httpstepid);
	zbx_snprintf(audit_key_post_type,  AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].post_type",
			httpstepid);

	zbx_audit_update_json_append_no_value(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_name, name);
	zbx_audit_update_json_append_int(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_no, no);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_url, url);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_timeout, timeout);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_posts, posts);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_required, required);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_status_codes, status_codes);
	zbx_audit_update_json_append_int(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_follow_redirects,
			follow_redirects);
	zbx_audit_update_json_append_int(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_retrieve_mode, retrieve_mode);
	zbx_audit_update_json_append_int(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_post_type, post_type);
}

void	zbx_audit_httptest_update_json_add_httptest_field(zbx_uint64_t httptestid, zbx_uint64_t httptestfieldid,
		int type, const char *name, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],
		audit_key_name[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "httptest.fields[" ZBX_FS_UI64 "]", httptestfieldid);
	zbx_snprintf(audit_key_type, AUDIT_DETAILS_KEY_LEN, "httptest.fields[" ZBX_FS_UI64 "].type", httptestfieldid);
	zbx_snprintf(audit_key_name, AUDIT_DETAILS_KEY_LEN, "httptest.fields[" ZBX_FS_UI64 "].name", httptestfieldid);
	zbx_snprintf(audit_key_value, AUDIT_DETAILS_KEY_LEN, "httptest.fields[" ZBX_FS_UI64 "].value", httptestfieldid);

	zbx_audit_update_json_append_no_value(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_int(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_type, type);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_name, name);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value);
}

void	zbx_audit_httptest_update_json_add_httpstep_field(zbx_uint64_t httptestid, zbx_uint64_t httpstepid,
		zbx_uint64_t httpstepfieldid, int type, const char *name, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],
		audit_key_name[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "httptest.steps[" ZBX_FS_UI64 "].fields[" ZBX_FS_UI64 "]",
			httpstepid, httpstepfieldid);
	zbx_snprintf(audit_key_type, AUDIT_DETAILS_KEY_LEN,
			"httptest.steps[" ZBX_FS_UI64 "].fields[" ZBX_FS_UI64 "].type", httpstepid, httpstepfieldid);
	zbx_snprintf(audit_key_name, AUDIT_DETAILS_KEY_LEN,
			"httptest.steps[" ZBX_FS_UI64 "].fields[" ZBX_FS_UI64 "].name", httpstepid, httpstepfieldid);
	zbx_snprintf(audit_key_value, AUDIT_DETAILS_KEY_LEN,
			"httptest.steps[" ZBX_FS_UI64 "].fields[" ZBX_FS_UI64 "].value", httpstepid, httpstepfieldid);

	zbx_audit_update_json_append_no_value(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_int(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_type, type);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_name, name);
	zbx_audit_update_json_append_string(httptestid, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value);
}

