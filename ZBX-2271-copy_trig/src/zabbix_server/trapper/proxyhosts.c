/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "trapper.h"
#include "proxyconfig.h"
#include "proxyhosts.h"

/******************************************************************************
 *                                                                            *
 * Function: process_host_availability                                        *
 *                                                                            *
 * Purpose: update proxy hosts availability                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	process_host_availability(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "process_host_availability";
	zbx_uint64_t		proxy_hostid, hostid;
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p = NULL;
	char			tmp[HOST_ERROR_LEN_MAX];
	char			*sql = NULL, *error_esc;
	int			sql_alloc = 4096, sql_offset = 0,
				ret = FAIL, tmp_offset, no_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == get_proxy_id(jp, &proxy_hostid))
		goto exit;

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Invalid host availability data. %s",
				zbx_json_strerror());
		goto exit;
	}

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin();

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
/* {"data":[{"hostid":12345,...,...},{...},...]}
 *          ^----------------------^
 */ 		if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid host availability data. %s",
					zbx_json_strerror());
			continue;
		}

		tmp_offset = sql_offset;
		no_data = 1;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32, "update hosts set ");

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_AVAILABLE, tmp, sizeof(tmp)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32, "available=%d,", atoi(tmp));
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_SNMP_AVAILABLE, tmp, sizeof(tmp)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32, "snmp_available=%d,", atoi(tmp));
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IPMI_AVAILABLE, tmp, sizeof(tmp)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32, "snmp_available=%d,", atoi(tmp));
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, strlen(error_esc) + 16,
					"error='%s',", error_esc);
			zbx_free(error_esc);
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_SNMP_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, strlen(error_esc) + 16,
					"snmp_error='%s',", error_esc);
			zbx_free(error_esc);
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IPMI_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, strlen(error_esc) + 16,
					"ipmi_error='%s',", error_esc);
			zbx_free(error_esc);
			no_data = 0;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid host availability data. %s",
					zbx_json_strerror());
			sql_offset = tmp_offset;
			continue;
		}

		if (FAIL == is_uint64(tmp, &hostid) || 1 == no_data)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid host availability data.");
			sql_offset = tmp_offset;
			continue;
		}

		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 40,
				" where hostid=" ZBX_FS_UI64 ";\n", hostid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
	DBcommit();

	zbx_free(sql);

	ret = SUCCEED;
exit:
	if (SUCCEED != send_result(sock, ret, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Error sending result back");
		zabbix_syslog("Trapper: error sending result back");
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
