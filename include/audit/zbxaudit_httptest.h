/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_AUDIT_HTTPTEST_H
#define ZABBIX_AUDIT_HTTPTEST_H

#include "zbxalgo.h"

void	zbx_audit_httptest_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t httptestid,
		const char *name);

void	zbx_audit_httptest_update_json_add_data(int audit_context_mode, zbx_uint64_t httptestid,
		zbx_uint64_t templateid, const char *name, const char *delay, unsigned char status, const char *agent,
		unsigned char authentication, const char *httpuser, const char *http_proxy, int retries,
		const char *ssl_cert_file, const char *ssl_key_file, int verify_peer, int verify_host,
		zbx_uint64_t hostid);

#define PREPARE_AUDIT_HTTPTEST_UPDATE_H(resource, type1)							\
void	zbx_audit_httptest_update_json_update_##resource(int audit_context_mode, zbx_uint64_t httptestid,	\
		type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_HTTPTEST_UPDATE_H(templateid, zbx_uint64_t)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(delay, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(agent, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(http_user, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(http_password, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(http_proxy, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(retries, int)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(status, int)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(authentication, int)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(ssl_cert_file, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(ssl_key_file, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(ssl_key_password, const char*)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(verify_peer, int)
PREPARE_AUDIT_HTTPTEST_UPDATE_H(verify_host, int)

int	zbx_audit_DBselect_delete_for_httptest(int audit_context_mode, const char *sql, zbx_vector_uint64_t *ids);

void	zbx_audit_httptest_update_json_add_httptest_tag(int audit_context_mode, zbx_uint64_t httptestid,
		zbx_uint64_t tagid, const char *tag, const char *value);

void	zbx_audit_httptest_update_json_delete_tags(int audit_context_mode, zbx_uint64_t httptestid, zbx_uint64_t tagid);

void	zbx_audit_httptest_update_json_add_httptest_httpstep(int audit_context_mode, zbx_uint64_t httptestid,
		zbx_uint64_t httpstepid, const char *name, int no, const char *url, const char *timeout,
		const char *posts, const char *required, const char *status_codes, int follow_redirects,
		int retrieve_mode, int post_type);

#define PREPARE_AUDIT_HTTPSTEP_UPDATE_H(resource, type1)							\
void	zbx_audit_httptest_update_json_httpstep_update_##resource(int audit_context_mode,			\
		zbx_uint64_t httptestid, zbx_uint64_t httpstepid, type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_HTTPSTEP_UPDATE_H(url, const char*)
PREPARE_AUDIT_HTTPSTEP_UPDATE_H(posts, const char*)
PREPARE_AUDIT_HTTPSTEP_UPDATE_H(required, const char*)
PREPARE_AUDIT_HTTPSTEP_UPDATE_H(status_codes, const char*)
PREPARE_AUDIT_HTTPSTEP_UPDATE_H(timeout, const char*)
PREPARE_AUDIT_HTTPSTEP_UPDATE_H(follow_redirects, int)
PREPARE_AUDIT_HTTPSTEP_UPDATE_H(retrieve_mode, int)
PREPARE_AUDIT_HTTPSTEP_UPDATE_H(post_type, int)

void	zbx_audit_httptest_update_json_add_httptest_field(int audit_context_mode, zbx_uint64_t httptestid,
		zbx_uint64_t httptestfieldid, int type, const char *name, const char *value);

void	zbx_audit_httptest_update_json_delete_httptest_field(int audit_context_mode, zbx_uint64_t httptestid,
		zbx_uint64_t fieldid, int type);

void	zbx_audit_httptest_update_json_add_httpstep_field(int audit_context_mode, zbx_uint64_t httptestid,
		zbx_uint64_t httpstepid, zbx_uint64_t httpstepfieldid, int type, const char *name, const char *value);

void	zbx_audit_httptest_update_json_delete_httpstep_field(int audit_context_mode, zbx_uint64_t httptestid,
		zbx_uint64_t httpstepid, zbx_uint64_t fieldid, int type);

#endif	/* ZABBIX_AUDIT_HTTPTEST_H */
