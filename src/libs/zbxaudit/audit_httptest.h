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

#ifndef ZABBIX_AUDIT_HTTPTEST_H
#define ZABBIX_AUDIT_HTTPTEST_H

#include "common.h"
#include "audit.h"

#include "../zbxdbhigh/template.h"

void	zbx_audit_httptest_create_entry(int audit_action, zbx_uint64_t httptestid, const char *name);

void	zbx_audit_httptest_update_json_add_data(zbx_uint64_t httptestid, zbx_uint64_t templateid, const char *name,
		const char *delay, unsigned char status, const char *agent, unsigned char authentication,
		const char *httpuser, const char *httppassword, const char *http_proxy, int retries,
		zbx_uint64_t hostid);

#define PREPARE_AUDIT_HTTPTEST_UPDATE_H(resource, type1)						\
void	zbx_audit_httptest_update_json_update_##resource(zbx_uint64_t httptestid, type1 resource##_old,	\
		type1 resource##_new);

PREPARE_AUDIT_HTTPTEST_UPDATE_H(templateid, zbx_uint64_t)

void	DBselect_delete_for_httptest(const char *sql, zbx_vector_uint64_t *ids);

void	zbx_audit_httptest_update_json_add_httptest_tag(zbx_uint64_t httptestid, zbx_uint64_t tagid, const char *tag,
		const char *value);

void	zbx_audit_httptest_update_json_add_httptest_httpstep(zbx_uint64_t httptestid, zbx_uint64_t httpstepid,
		const char *name, int no, const char *url, const char *timeout, const char *posts, const char *required,
		const char *status_codes, int follow_redirects, int retrieve_mode, int post_type);

void	zbx_audit_httptest_update_json_add_httptest_field(zbx_uint64_t httptestid, zbx_uint64_t httptestfieldid,
		int type, const char *name, const char *value);

void	zbx_audit_httptest_update_json_add_httpstep_field(zbx_uint64_t httptestid, zbx_uint64_t httpstepid,
		zbx_uint64_t httpstepfieldid, int type, const char *name, const char *value);

#endif	/* ZABBIX_AUDIT_HTTPTEST_H */
