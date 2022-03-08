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

#include "expression.h"

#include "zbxserver.h"
#include "evalfunc.h"
#include "log.h"
#include "zbxregexp.h"
#include "zbxvariant.h"
#include "zbxeval.h"
#include "valuecache.h"
#include "macrofunc.h"
#include "../zbxalgo/vectorimpl.h"

#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/xpath.h>
#	include <libxml/xmlerror.h>

typedef struct
{
	char	*buf;
	size_t	len;
}
zbx_libxml_error_t;
#endif

typedef struct
{
	char		*host;
	char		*severity;
	char		*tags;
	char		*name;
	int		clock;
	unsigned char	nseverity;
}
zbx_rootcause_t;

ZBX_VECTOR_DECL(rootcause, zbx_rootcause_t)
ZBX_VECTOR_IMPL(rootcause, zbx_rootcause_t)

/* The following definitions are used to identify the request field */
/* for various value getters grouped by their scope:                */

/* DBget_item_value(), get_interface_value() */
#define ZBX_REQUEST_HOST_IP			1
#define ZBX_REQUEST_HOST_DNS			2
#define ZBX_REQUEST_HOST_CONN			3
#define ZBX_REQUEST_HOST_PORT			4

/* DBget_history_log_value() */
#define ZBX_REQUEST_ITEM_LOG_DATE		201
#define ZBX_REQUEST_ITEM_LOG_TIME		202
#define ZBX_REQUEST_ITEM_LOG_AGE		203
#define ZBX_REQUEST_ITEM_LOG_SOURCE		204
#define ZBX_REQUEST_ITEM_LOG_SEVERITY		205
#define ZBX_REQUEST_ITEM_LOG_NSEVERITY		206
#define ZBX_REQUEST_ITEM_LOG_EVENTID		207

static int	substitute_simple_macros_impl(const zbx_uint64_t *actionid, const DB_EVENT *event,
		const DB_EVENT *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host,
		const DC_ITEM *dc_item, const DB_ALERT *alert, const DB_ACKNOWLEDGE *ack,
		const zbx_service_alarm_t *service_alarm, const DB_SERVICE *service, const char *tz, char **data,
		int macro_type, char *error, int maxerrlen);

static int	substitute_key_macros_impl(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen);

/******************************************************************************
 *                                                                            *
 * Purpose: get trigger severity name                                         *
 *                                                                            *
 * Parameters: trigger    - [IN] a trigger data with priority field;          *
 *                               TRIGGER_SEVERITY_*                           *
 *             replace_to - [OUT] pointer to a buffer that will receive       *
 *                          a null-terminated trigger severity string         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_trigger_severity_name(unsigned char priority, char **replace_to)
{
	zbx_config_t	cfg;

	if (TRIGGER_SEVERITY_COUNT <= priority)
		return FAIL;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SEVERITY_NAME);

	*replace_to = zbx_strdup(*replace_to, cfg.severity_name[priority]);

	zbx_config_clean(&cfg);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get human readable list of problem update actions                 *
 *                                                                            *
 * Parameters: ack     - [IN] problem update data                             *
 *             actions - [IN] the required action flags                       *
 *             out     - [OUT] the output buffer                              *
 *                                                                            *
 * Return value: SUCCEED - successfully returned list of problem update       *
 *               FAIL    - no matching actions were made                      *
 *                                                                            *
 ******************************************************************************/
static int	get_problem_update_actions(const DB_ACKNOWLEDGE *ack, int actions, char **out)
{
	const char	*prefixes[] = {"", ", ", ", ", ", ", ", "};
	char		*buf = NULL;
	size_t		buf_alloc = 0, buf_offset = 0;
	int		i, index, flags;

	if (0 == (flags = ack->action & actions))
		return FAIL;

	for (i = 0, index = 0; i < ZBX_PROBLEM_UPDATE_ACTION_COUNT; i++)
	{
		if (0 != (flags & (1 << i)))
			index++;
	}

	if (1 < index)
		prefixes[index - 1] = " and ";

	index = 0;

	if (0 != (flags & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "acknowledged");
		index++;
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "unacknowledged");
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_MESSAGE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "commented");
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_SEVERITY))
	{
		zbx_config_t	cfg;
		const char	*from = "unknown", *to = "unknown";

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SEVERITY_NAME);

		if (TRIGGER_SEVERITY_COUNT > ack->old_severity && 0 <= ack->old_severity)
			from = cfg.severity_name[ack->old_severity];

		if (TRIGGER_SEVERITY_COUNT > ack->new_severity && 0 <= ack->new_severity)
			to = cfg.severity_name[ack->new_severity];

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "changed severity from %s to %s",
				from, to);

		zbx_config_clean(&cfg);
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_CLOSE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "closed");
	}

	zbx_free(*out);
	*out = buf;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request host name by hostid                                       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_host_value(zbx_uint64_t hostid, char **replace_to, const char *field_name)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select %s"
			" from hosts"
			" where hostid=" ZBX_FS_UI64,
			field_name, hostid);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get template trigger ID from which the trigger is inherited       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_templateid_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t *templateid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select templateid"
			" from triggers"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(*templateid, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get comma-space separated trigger template names in which         *
 *          the trigger is defined                                            *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Comments: based on the patch submitted by Hmami Mohamed                    *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_template_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	zbx_uint64_t	templateid;
	char		*sql = NULL;
	size_t		replace_to_alloc = 64, replace_to_offset = 0,
			sql_alloc = 256, sql_offset = 0;
	int		user_type = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != userid)
	{
		result = DBselect("select r.type from users u,role r where u.roleid=r.roleid and"
				" userid=" ZBX_FS_UI64, *userid);

		if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
			user_type = atoi(row[0]);
		DBfree_result(result);

		if (-1 == user_type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __func__);
			goto out;
		}
	}

	/* use parent trigger ID for lld generated triggers */
	result = DBselect(
			"select parent_triggerid"
			" from trigger_discovery"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(triggerid, row[0]);
	DBfree_result(result);

	if (SUCCEED != DBget_templateid_by_triggerid(triggerid, &templateid) || 0 == templateid)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigger not found or not templated", __func__);
		goto out;
	}

	do
	{
		triggerid = templateid;
	}
	while (SUCCEED == (ret = DBget_templateid_by_triggerid(triggerid, &templateid)) && 0 != templateid);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigger not found", __func__);
		goto out;
	}

	*replace_to = (char *)zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct h.name"
			" from hosts h,items i,functions f"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);
	if (NULL != userid && USER_TYPE_SUPER_ADMIN != user_type)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and exists("
					"select null"
					" from hosts_groups hg,rights r,users_groups ug"
					" where h.hostid=hg.hostid"
						" and hg.groupid=r.id"
						" and r.groupid=ug.usrgrpid"
						" and ug.userid=" ZBX_FS_UI64
					" group by hg.hostid"
					" having min(r.permission)>=%d"
				")",
				*userid, PERM_READ);
	}
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by h.name");

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != replace_to_offset)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");
		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, row[0]);
	}
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get comma-space separated host group names in which the trigger   *
 *          is defined                                                        *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_hostgroup_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	char		*sql = NULL;
	size_t		replace_to_alloc = 64, replace_to_offset = 0,
			sql_alloc = 256, sql_offset = 0;
	int		user_type = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != userid)
	{
		result = DBselect("select r.type from users u,role r where u.roleid=r.roleid and"
				" userid=" ZBX_FS_UI64, *userid);

		if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
			user_type = atoi(row[0]);
		DBfree_result(result);

		if (-1 == user_type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __func__);
			goto out;
		}
	}

	*replace_to = (char *)zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.name"
			" from hstgrp g,hosts_groups hg,items i,functions f"
			" where g.groupid=hg.groupid"
				" and hg.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);
	if (NULL != userid && USER_TYPE_SUPER_ADMIN != user_type)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and exists("
					"select null"
					" from rights r,users_groups ug"
					" where g.groupid=r.id"
						" and r.groupid=ug.usrgrpid"
						" and ug.userid=" ZBX_FS_UI64
					" group by r.id"
					" having min(r.permission)>=%d"
				")",
				*userid, PERM_READ);
	}
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by g.name");

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != replace_to_offset)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");
		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular value associated with the interface         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_interface_value(zbx_uint64_t hostid, zbx_uint64_t itemid, char **replace_to, int request)
{
	int		res;
	DC_INTERFACE	interface;

	if (SUCCEED != (res = DCconfig_get_interface(&interface, hostid, itemid)))
		return res;

	switch (request)
	{
		case ZBX_REQUEST_HOST_IP:
			*replace_to = zbx_strdup(*replace_to, interface.ip_orig);
			break;
		case ZBX_REQUEST_HOST_DNS:
			*replace_to = zbx_strdup(*replace_to, interface.dns_orig);
			break;
		case ZBX_REQUEST_HOST_CONN:
			*replace_to = zbx_strdup(*replace_to, interface.addr);
			break;
		case ZBX_REQUEST_HOST_PORT:
			*replace_to = zbx_strdup(*replace_to, interface.port_orig);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			res = FAIL;
	}

	return res;
}

static int	get_host_value(zbx_uint64_t itemid, char **replace_to, int request)
{
	int	ret;
	DC_HOST	host;

	DCconfig_get_hosts_by_itemids(&host, &itemid, &ret, 1);

	if (FAIL == ret)
		return FAIL;

	switch (request)
	{
		case ZBX_REQUEST_HOST_ID:
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, host.hostid);
			break;
		case ZBX_REQUEST_HOST_HOST:
			*replace_to = zbx_strdup(*replace_to, host.host);
			break;
		case ZBX_REQUEST_HOST_NAME:
			*replace_to = zbx_strdup(*replace_to, host.name);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get item key, replace macros in the key                           *
 *                                                                            *
 * Parameters: dc_item    - [IN] item information used in substitution        *
 *             replace_to - [OUT] string with item key with replaced macros   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_substitute_macros_in_item_key(DC_ITEM *dc_item, char **replace_to)
{
	char	*key = zbx_strdup(NULL, dc_item->key_orig);

	substitute_key_macros_impl(&key, NULL, dc_item, NULL, NULL, MACRO_TYPE_ITEM_KEY, NULL, 0);
	zbx_free(*replace_to);
	*replace_to = key;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular value associated with the item              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_item_value(zbx_uint64_t itemid, char **replace_to, int request)
{
	DB_RESULT	result;
	DB_ROW		row;
	DC_ITEM		dc_item;
	zbx_uint64_t	proxy_hostid;
	int		ret = FAIL, errcode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (request)
	{
		case ZBX_REQUEST_HOST_IP:
		case ZBX_REQUEST_HOST_DNS:
		case ZBX_REQUEST_HOST_CONN:
		case ZBX_REQUEST_HOST_PORT:
			return get_interface_value(0, itemid, replace_to, request);
		case ZBX_REQUEST_HOST_ID:
		case ZBX_REQUEST_HOST_HOST:
		case ZBX_REQUEST_HOST_NAME:
			return get_host_value(itemid, replace_to, request);
	}

	result = DBselect(
			"select h.proxy_hostid,h.description,i.itemid,i.name,i.key_,i.description,i.value_type,ir.error"
			" from items i"
				" join hosts h on h.hostid=i.hostid"
				" left join item_rtdata ir on ir.itemid=i.itemid"
			" where i.itemid=" ZBX_FS_UI64, itemid);

	if (NULL != (row = DBfetch(result)))
	{
		switch (request)
		{
			case ZBX_REQUEST_HOST_DESCRIPTION:
				*replace_to = zbx_strdup(*replace_to, row[1]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_ID:
				*replace_to = zbx_strdup(*replace_to, row[2]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_NAME:
				*replace_to = zbx_strdup(*replace_to, row[3]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_KEY:
				DCconfig_get_items_by_itemids(&dc_item, &itemid, &errcode, 1);

				if (SUCCEED == errcode)
				{
					zbx_substitute_macros_in_item_key(&dc_item, replace_to);
					ret = SUCCEED;
				}

				DCconfig_clean_items(&dc_item, &errcode, 1);
				break;
			case ZBX_REQUEST_ITEM_DESCRIPTION:
				DCconfig_get_items_by_itemids(&dc_item, &itemid, &errcode, 1);

				if (SUCCEED == errcode)
				{
					*replace_to = zbx_dc_expand_user_macros(row[5], dc_item.host.hostid);
					ret = SUCCEED;
				}

				DCconfig_clean_items(&dc_item, &errcode, 1);
				break;
			case ZBX_REQUEST_ITEM_NAME_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[3]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_KEY_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[4]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_DESCRIPTION_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[5]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_PROXY_NAME:
				ZBX_DBROW2UINT64(proxy_hostid, row[0]);

				if (0 == proxy_hostid)
				{
					*replace_to = zbx_strdup(*replace_to, "");
					ret = SUCCEED;
				}
				else
					ret = DBget_host_value(proxy_hostid, replace_to, "host");
				break;
			case ZBX_REQUEST_PROXY_DESCRIPTION:
				ZBX_DBROW2UINT64(proxy_hostid, row[0]);

				if (0 == proxy_hostid)
				{
					*replace_to = zbx_strdup(*replace_to, "");
					ret = SUCCEED;
				}
				else
					ret = DBget_host_value(proxy_hostid, replace_to, "description");
				break;
			case ZBX_REQUEST_ITEM_VALUETYPE:
				*replace_to = zbx_strdup(*replace_to, row[6]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_ERROR:
				*replace_to = zbx_strdup(*replace_to, FAIL == DBis_null(row[7]) ? row[7] : "");
				ret = SUCCEED;
				break;
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	DBget_trigger_error(const DB_TRIGGER *trigger, char **replace_to)
{
	int		ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (result = DBselect("select error from triggers where triggerid=" ZBX_FS_UI64,
			trigger->triggerid)))
	{
		ret = FAIL;
		goto out;
	}

	*replace_to = zbx_strdup(*replace_to, (NULL == (row = DBfetch(result))) ?  "" : row[0]);

	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular value associated with the trigger's         *
 *          N_functionid'th function                                          *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	DBget_trigger_value(const DB_TRIGGER *trigger, char **replace_to, int N_functionid, int request)
{
	zbx_uint64_t	itemid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == zbx_db_trigger_get_itemid(trigger, N_functionid, &itemid))
		ret = DBget_item_value(itemid, replace_to, request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve number of events (acknowledged or unacknowledged) for a  *
 *          trigger (in an OK or PROBLEM state) which generated an event      *
 *                                                                            *
 * Parameters: triggerid    - [IN] trigger identifier from database           *
 *             replace_to   - [IN/OUT] pointer to result buffer               *
 *             problem_only - [IN] selected trigger status:                   *
 *                             0 - TRIGGER_VALUE_PROBLEM and TRIGGER_VALUE_OK *
 *                             1 - TRIGGER_VALUE_PROBLEM                      *
 *             acknowledged - [IN] acknowledged event or not                  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_event_count(zbx_uint64_t triggerid, char **replace_to, int problem_only, int acknowledged)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		value[4];
	int		ret = FAIL;

	if (problem_only)
		zbx_snprintf(value, sizeof(value), "%d", TRIGGER_VALUE_PROBLEM);
	else
		zbx_snprintf(value, sizeof(value), "%d,%d", TRIGGER_VALUE_PROBLEM, TRIGGER_VALUE_OK);

	result = DBselect(
			"select count(*)"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
				" and value in (%s)"
				" and acknowledged=%d",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			triggerid,
			value,
			acknowledged);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovered host value by event and field name            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_dhost_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	char		sql[MAX_STRING_LEN];

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			zbx_snprintf(sql, sizeof(sql),
					"select %s"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						" and h.dhostid=" ZBX_FS_UI64
					" order by s.dserviceid",
					fieldname,
					event->objectid);
			break;
		case EVENT_OBJECT_DSERVICE:
			zbx_snprintf(sql, sizeof(sql),
					"select %s"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						" and s.dserviceid=" ZBX_FS_UI64,
					fieldname,
					event->objectid);
			break;
		default:
			return ret;
	}

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, ZBX_NULL2STR(row[0]));
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovery rule check value by event and field name       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_dchecks_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DSERVICE:
			result = DBselect("select %s from dchecks c,dservices s"
					" where c.dcheckid=s.dcheckid and s.dserviceid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovered service value by event and field name         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_dservice_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DSERVICE:
			result = DBselect("select %s from dservices s where s.dserviceid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovery rule value by event and field name             *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_drule_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	if (EVENT_SOURCE_DISCOVERY != event->source)
		return FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			result = DBselect("select r.%s from drules r,dhosts h"
					" where r.druleid=h.druleid and h.dhostid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		case EVENT_OBJECT_DSERVICE:
			result = DBselect("select r.%s from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid and h.dhostid=s.dhostid and s.dserviceid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, ZBX_NULL2STR(row[0]));
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular attribute of a log value                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_history_log_value(zbx_uint64_t itemid, char **replace_to, int request, int clock, int ns,
		const char *tz)
{
	DC_ITEM			item;
	int			ret = FAIL, errcode = FAIL;
	zbx_timespec_t		ts = {clock, ns};
	zbx_history_record_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED != errcode || ITEM_VALUE_TYPE_LOG != item.value_type)
		goto out;

	if (SUCCEED != zbx_vc_get_value(itemid, item.value_type, &ts, &value))
		goto out;

	zbx_vc_flush_stats();

	switch (request)
	{
		case ZBX_REQUEST_ITEM_LOG_DATE:
			*replace_to = zbx_strdup(*replace_to, zbx_date2str((time_t)value.value.log->timestamp, tz));
			goto success;
		case ZBX_REQUEST_ITEM_LOG_TIME:
			*replace_to = zbx_strdup(*replace_to, zbx_time2str((time_t)value.value.log->timestamp, tz));
			goto success;
		case ZBX_REQUEST_ITEM_LOG_AGE:
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - value.value.log->timestamp));
			goto success;
	}

	/* the following attributes are set only for windows eventlog items */
	if (0 != strncmp(item.key_orig, "eventlog[", 9))
		goto clean;

	switch (request)
	{
		case ZBX_REQUEST_ITEM_LOG_SOURCE:
			*replace_to = zbx_strdup(*replace_to, (NULL == value.value.log->source ? "" :
					value.value.log->source));
			break;
		case ZBX_REQUEST_ITEM_LOG_SEVERITY:
			*replace_to = zbx_strdup(*replace_to,
					zbx_item_logtype_string((unsigned char)value.value.log->severity));
			break;
		case ZBX_REQUEST_ITEM_LOG_NSEVERITY:
			*replace_to = zbx_dsprintf(*replace_to, "%d", value.value.log->severity);
			break;
		case ZBX_REQUEST_ITEM_LOG_EVENTID:
			*replace_to = zbx_dsprintf(*replace_to, "%d", value.value.log->logeventid);
			break;
	}
success:
	ret = SUCCEED;
clean:
	zbx_history_record_clear(&value, ITEM_VALUE_TYPE_LOG);
out:
	DCconfig_clean_items(&item, &errcode, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve item value by item id                                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBitem_get_value(zbx_uint64_t itemid, char **lastvalue, int raw, zbx_timespec_t *ts)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select value_type,valuemapid,units"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			itemid);

	if (NULL != (row = DBfetch(result)))
	{
		unsigned char		value_type;
		zbx_uint64_t		valuemapid;
		zbx_history_record_t	vc_value;

		value_type = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(valuemapid, row[1]);

		if (SUCCEED == zbx_vc_get_value(itemid, value_type, ts, &vc_value))
		{
			char	tmp[MAX_BUFFER_LEN];

			zbx_vc_flush_stats();
			zbx_history_value_print(tmp, sizeof(tmp), &vc_value.value, value_type);
			zbx_history_record_clear(&vc_value, value_type);

			if (0 == raw)
				zbx_format_value(tmp, sizeof(tmp), valuemapid, row[2], value_type);

			*lastvalue = zbx_strdup(*lastvalue, tmp);

			ret = SUCCEED;
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve item value by trigger expression and number of function  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBitem_value(const DB_TRIGGER *trigger, char **value, int N_functionid, int clock, int ns, int raw)
{
	zbx_uint64_t	itemid;
	zbx_timespec_t	ts = {clock, ns};
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == (ret = zbx_db_trigger_get_itemid(trigger, N_functionid, &itemid)))
		ret = DBitem_get_value(itemid, value, raw, &ts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve item lastvalue by trigger expression                     *
 *          and number of function                                            *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBitem_lastvalue(const DB_TRIGGER *trigger, char **lastvalue, int N_functionid, int raw)
{
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = DBitem_value(trigger, lastvalue, N_functionid, time(NULL), 999999999, raw);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: formats full user name from name, surname and alias               *
 *                                                                            *
 * Parameters: name    - [IN] the user name, can be empty string              *
 *             surname - [IN] the user surname, can be empty string           *
 *             alias   - [IN] the user alias                                  *
 *                                                                            *
 * Return value: the formatted user fullname                                  *
 *                                                                            *
 ******************************************************************************/
static char	*format_user_fullname(const char *name, const char *surname, const char *alias)
{
	char	*buf = NULL;
	size_t	buf_alloc = 0, buf_offset = 0;

	zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, name);

	if ('\0' != *surname)
	{
		if (0 != buf_offset)
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ' ');

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, surname);
	}

	if ('\0' != *alias)
	{
		size_t	offset = buf_offset;

		if (0 != buf_offset)
			zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " (");

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, alias);

		if (0 != offset)
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ')');
	}

	return buf;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve escalation history                                       *
 *                                                                            *
 ******************************************************************************/
static void	get_escalation_history(zbx_uint64_t actionid, const DB_EVENT *event, const DB_EVENT *r_event,
			char **replace_to, const zbx_uint64_t *recipient_userid, const char *tz)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL, *p;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;
	int		esc_step;
	unsigned char	type, status;
	time_t		now;
	zbx_uint64_t	userid;

	buf = (char *)zbx_malloc(buf, buf_alloc);

	zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Problem started: %s %s Age: %s\n",
			zbx_date2str(event->clock, tz), zbx_time2str(event->clock, tz),
			zbx_age2str(time(NULL) - event->clock));

	result = DBselect("select a.clock,a.alerttype,a.status,mt.name,a.sendto,a.error,a.esc_step,a.userid,a.message"
			" from alerts a"
			" left join media_type mt"
				" on mt.mediatypeid=a.mediatypeid"
			" where a.eventid=" ZBX_FS_UI64
				" and a.actionid=" ZBX_FS_UI64
			" order by a.clock",
			event->eventid, actionid);

	while (NULL != (row = DBfetch(result)))
	{
		int	user_permit;

		now = atoi(row[0]);
		type = (unsigned char)atoi(row[1]);
		status = (unsigned char)atoi(row[2]);
		esc_step = atoi(row[6]);
		ZBX_DBROW2UINT64(userid, row[7]);
		user_permit = zbx_check_user_permissions(&userid, recipient_userid);

		if (0 != esc_step)
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%d. ", esc_step);

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%s %s %-7s %-11s",
				zbx_date2str(now, tz), zbx_time2str(now, tz),	/* date, time */
				zbx_alert_type_string(type),		/* alert type */
				zbx_alert_status_string(type, status));	/* alert status */

		if (ALERT_TYPE_COMMAND == type)
		{
			if (NULL != (p = strchr(row[8], ':')))
			{
				*p = '\0';
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " \"%s\"", row[8]);	/* host */
				*p = ':';
			}
		}
		else
		{
			const char	*media_type_name, *send_to, *user_name;

			media_type_name = (SUCCEED == DBis_null(row[3]) ? "" : row[3]);

			if (SUCCEED == user_permit)
			{
				send_to = row[4];
				user_name = zbx_user_string(userid);
			}
			else
			{
				send_to = "\"Inaccessible recipient details\"";
				user_name = "Inaccessible user";
			}

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s %s \"%s\"",
					media_type_name,
					send_to,	/* historical recipient */
					user_name);	/* alert user full name */
		}

		if (ALERT_STATUS_FAILED == status)
		{
			/* alert error can be generated by SMTP Relay or other media and contain sensitive details */
			if (SUCCEED == user_permit)
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s", row[5]);
			else
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " \"Inaccessible error message\"");
		}

		zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, '\n');
	}
	DBfree_result(result);

	if (NULL != r_event)
	{
		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Problem ended: %s %s\n",
				zbx_date2str(r_event->clock, tz), zbx_time2str(r_event->clock, tz));
	}

	if (0 != buf_offset)
		buf[--buf_offset] = '\0';

	*replace_to = buf;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve event acknowledges history                               *
 *                                                                            *
 ******************************************************************************/
static void	get_event_update_history(const DB_EVENT *event, char **replace_to, const zbx_uint64_t *recipient_userid,
		const char *tz)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;

	buf = (char *)zbx_malloc(buf, buf_alloc);
	*buf = '\0';

	result = DBselect("select clock,userid,message,action,old_severity,new_severity"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64 " order by clock",
			event->eventid);

	while (NULL != (row = DBfetch(result)))
	{
		const char	*user_name;
		char		*actions = NULL;
		DB_ACKNOWLEDGE	ack;

		ack.clock = atoi(row[0]);
		ZBX_STR2UINT64(ack.userid, row[1]);
		ack.message = row[2];
		ack.acknowledgeid = 0;
		ack.action = atoi(row[3]);
		ack.old_severity = atoi(row[4]);
		ack.new_severity = atoi(row[5]);

		if (SUCCEED == zbx_check_user_permissions(&ack.userid, recipient_userid))
			user_name = zbx_user_string(ack.userid);
		else
			user_name = "Inaccessible user";

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
				"%s %s \"%s\"\n",
				zbx_date2str(ack.clock, tz),
				zbx_time2str(ack.clock, tz),
				user_name);

		if (SUCCEED == get_problem_update_actions(&ack, ZBX_PROBLEM_UPDATE_ACKNOWLEDGE |
					ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE |
					ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_SEVERITY, &actions))
		{
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Actions: %s.\n", actions);
			zbx_free(actions);
		}

		if ('\0' != *ack.message)
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%s\n", ack.message);

		zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, '\n');
	}
	DBfree_result(result);

	if (0 != buf_offset)
	{
		buf_offset -= 2;
		buf[buf_offset] = '\0';
	}

	*replace_to = buf;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request value from autoreg_host table by event                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_autoreg_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select %s"
			" from autoreg_host"
			" where autoreg_hostid=" ZBX_FS_UI64, fieldname, event->objectid);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, ZBX_NULL2STR(row[0]));
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

#define MVAR_ACTION			"{ACTION."			/* a prefix for all action macros */
#define MVAR_ACTION_ID			MVAR_ACTION "ID}"
#define MVAR_ACTION_NAME		MVAR_ACTION "NAME}"
#define MVAR_DATE			"{DATE}"
#define MVAR_EVENT			"{EVENT."			/* a prefix for all event macros */
#define MVAR_EVENT_ACK_HISTORY		MVAR_EVENT "ACK.HISTORY}"	/* deprecated */
#define MVAR_EVENT_ACK_STATUS		MVAR_EVENT "ACK.STATUS}"
#define MVAR_EVENT_AGE			MVAR_EVENT "AGE}"
#define MVAR_EVENT_DATE			MVAR_EVENT "DATE}"
#define MVAR_EVENT_DURATION		MVAR_EVENT "DURATION}"
#define MVAR_EVENT_ID			MVAR_EVENT "ID}"
#define MVAR_EVENT_NAME			MVAR_EVENT "NAME}"
#define MVAR_EVENT_STATUS		MVAR_EVENT "STATUS}"
#define MVAR_EVENT_TAGS			MVAR_EVENT "TAGS}"
#define MVAR_EVENT_TAGSJSON		MVAR_EVENT "TAGSJSON}"
#define MVAR_EVENT_TAGS_PREFIX		MVAR_EVENT "TAGS."
#define MVAR_EVENT_TIME			MVAR_EVENT "TIME}"
#define MVAR_EVENT_VALUE		MVAR_EVENT "VALUE}"
#define MVAR_EVENT_SEVERITY		MVAR_EVENT "SEVERITY}"
#define MVAR_EVENT_NSEVERITY		MVAR_EVENT "NSEVERITY}"
#define MVAR_EVENT_OBJECT		MVAR_EVENT "OBJECT}"
#define MVAR_EVENT_SOURCE		MVAR_EVENT "SOURCE}"
#define MVAR_EVENT_OPDATA		MVAR_EVENT "OPDATA}"
#define MVAR_EVENT_RECOVERY		MVAR_EVENT "RECOVERY."		/* a prefix for all recovery event macros */
#define MVAR_EVENT_RECOVERY_DATE	MVAR_EVENT_RECOVERY "DATE}"
#define MVAR_EVENT_RECOVERY_ID		MVAR_EVENT_RECOVERY "ID}"
#define MVAR_EVENT_RECOVERY_STATUS	MVAR_EVENT_RECOVERY "STATUS}"	/* deprecated */
#define MVAR_EVENT_RECOVERY_TAGS	MVAR_EVENT_RECOVERY "TAGS}"
#define MVAR_EVENT_RECOVERY_TAGSJSON	MVAR_EVENT_RECOVERY "TAGSJSON}"
#define MVAR_EVENT_RECOVERY_TIME	MVAR_EVENT_RECOVERY "TIME}"
#define MVAR_EVENT_RECOVERY_VALUE	MVAR_EVENT_RECOVERY "VALUE}"	/* deprecated */
#define MVAR_EVENT_RECOVERY_NAME	MVAR_EVENT_RECOVERY "NAME}"
#define MVAR_EVENT_UPDATE		MVAR_EVENT "UPDATE."
#define MVAR_EVENT_UPDATE_ACTION	MVAR_EVENT_UPDATE "ACTION}"
#define MVAR_EVENT_UPDATE_DATE		MVAR_EVENT_UPDATE "DATE}"
#define MVAR_EVENT_UPDATE_HISTORY	MVAR_EVENT_UPDATE "HISTORY}"
#define MVAR_EVENT_UPDATE_MESSAGE	MVAR_EVENT_UPDATE "MESSAGE}"
#define MVAR_EVENT_UPDATE_TIME		MVAR_EVENT_UPDATE "TIME}"
#define MVAR_EVENT_UPDATE_STATUS	MVAR_EVENT_UPDATE "STATUS}"
#define MVAR_EVENT_UPDATE_NSEVERITY	MVAR_EVENT_UPDATE "NSEVERITY}"
#define MVAR_EVENT_UPDATE_SEVERITY	MVAR_EVENT_UPDATE "SEVERITY}"

#define MVAR_ESC_HISTORY		"{ESC.HISTORY}"
#define MVAR_PROXY_NAME			"{PROXY.NAME}"
#define MVAR_PROXY_DESCRIPTION		"{PROXY.DESCRIPTION}"
#define MVAR_HOST_DNS			"{HOST.DNS}"
#define MVAR_HOST_CONN			"{HOST.CONN}"
#define MVAR_HOST_HOST			"{HOST.HOST}"
#define MVAR_HOST_ID			"{HOST.ID}"
#define MVAR_HOST_IP			"{HOST.IP}"
#define MVAR_IPADDRESS			"{IPADDRESS}"			/* deprecated */
#define MVAR_HOST_METADATA		"{HOST.METADATA}"
#define MVAR_HOST_NAME			"{HOST.NAME}"
#define MVAR_HOSTNAME			"{HOSTNAME}"			/* deprecated */
#define MVAR_HOST_DESCRIPTION		"{HOST.DESCRIPTION}"
#define MVAR_HOST_PORT			"{HOST.PORT}"
#define MVAR_HOST_TARGET_DNS		"{HOST.TARGET.DNS}"
#define MVAR_HOST_TARGET_CONN		"{HOST.TARGET.CONN}"
#define MVAR_HOST_TARGET_HOST		"{HOST.TARGET.HOST}"
#define MVAR_HOST_TARGET_IP		"{HOST.TARGET.IP}"
#define MVAR_HOST_TARGET_NAME		"{HOST.TARGET.NAME}"
#define MVAR_TIME			"{TIME}"
#define MVAR_ITEM_LASTVALUE		"{ITEM.LASTVALUE}"
#define MVAR_ITEM_VALUE			"{ITEM.VALUE}"
#define MVAR_ITEM_VALUETYPE		"{ITEM.VALUETYPE}"
#define MVAR_ITEM_ID			"{ITEM.ID}"
#define MVAR_ITEM_NAME			"{ITEM.NAME}"
#define MVAR_ITEM_NAME_ORIG		"{ITEM.NAME.ORIG}"
#define MVAR_ITEM_KEY			"{ITEM.KEY}"
#define MVAR_ITEM_KEY_ORIG		"{ITEM.KEY.ORIG}"
#define MVAR_ITEM_STATE			"{ITEM.STATE}"
#define MVAR_TRIGGER_KEY		"{TRIGGER.KEY}"			/* deprecated */
#define MVAR_ITEM_DESCRIPTION		"{ITEM.DESCRIPTION}"
#define MVAR_ITEM_DESCRIPTION_ORIG	"{ITEM.DESCRIPTION.ORIG}"
#define MVAR_ITEM_LOG			"{ITEM.LOG."
#define MVAR_ITEM_LOG_DATE		MVAR_ITEM_LOG "DATE}"
#define MVAR_ITEM_LOG_TIME		MVAR_ITEM_LOG "TIME}"
#define MVAR_ITEM_LOG_AGE		MVAR_ITEM_LOG "AGE}"
#define MVAR_ITEM_LOG_SOURCE		MVAR_ITEM_LOG "SOURCE}"
#define MVAR_ITEM_LOG_SEVERITY		MVAR_ITEM_LOG "SEVERITY}"
#define MVAR_ITEM_LOG_NSEVERITY		MVAR_ITEM_LOG "NSEVERITY}"
#define MVAR_ITEM_LOG_EVENTID		MVAR_ITEM_LOG "EVENTID}"
#define	MVAR_ITEM_STATE_ERROR		"{ITEM.STATE.ERROR}"

#define MVAR_SERVICE				"{SERVICE."
#define MVAR_SERVICE_NAME			MVAR_SERVICE "NAME}"
#define MVAR_SERVICE_DESCRIPTION		MVAR_SERVICE "DESCRIPTION}"
#define MVAR_SERVICE_ROOTCAUSE			MVAR_SERVICE "ROOTCAUSE}"
#define MVAR_SERVICE_TAGS			MVAR_SERVICE "TAGS}"
#define MVAR_SERVICE_TAGSJSON			MVAR_SERVICE "TAGSJSON}"
#define MVAR_SERVICE_TAGS_PREFIX		MVAR_SERVICE "TAGS."

#define MVAR_TRIGGER_DESCRIPTION		"{TRIGGER.DESCRIPTION}"
#define MVAR_TRIGGER_COMMENT			"{TRIGGER.COMMENT}"		/* deprecated */
#define MVAR_TRIGGER_ID				"{TRIGGER.ID}"
#define MVAR_TRIGGER_NAME			"{TRIGGER.NAME}"
#define MVAR_TRIGGER_NAME_ORIG			"{TRIGGER.NAME.ORIG}"
#define MVAR_TRIGGER_EXPRESSION			"{TRIGGER.EXPRESSION}"
#define MVAR_TRIGGER_EXPRESSION_RECOVERY	"{TRIGGER.EXPRESSION.RECOVERY}"
#define MVAR_TRIGGER_SEVERITY			"{TRIGGER.SEVERITY}"
#define MVAR_TRIGGER_NSEVERITY			"{TRIGGER.NSEVERITY}"
#define MVAR_TRIGGER_STATUS			"{TRIGGER.STATUS}"
#define MVAR_TRIGGER_STATE			"{TRIGGER.STATE}"
#define MVAR_TRIGGER_TEMPLATE_NAME		"{TRIGGER.TEMPLATE.NAME}"
#define MVAR_TRIGGER_HOSTGROUP_NAME		"{TRIGGER.HOSTGROUP.NAME}"
#define MVAR_FUNCTION_VALUE			"{FUNCTION.VALUE}"
#define MVAR_FUNCTION_RECOVERY_VALUE		"{FUNCTION.RECOVERY.VALUE}"
#define MVAR_TRIGGER_EXPRESSION_EXPLAIN		"{TRIGGER.EXPRESSION.EXPLAIN}"
#define MVAR_TRIGGER_EXPRESSION_RECOVERY_EXPLAIN	"{TRIGGER.EXPRESSION.RECOVERY.EXPLAIN}"

#define MVAR_STATUS				"{STATUS}"			/* deprecated */
#define MVAR_TRIGGER_VALUE			"{TRIGGER.VALUE}"
#define MVAR_TRIGGER_URL			"{TRIGGER.URL}"

#define MVAR_TRIGGER_EVENTS_ACK			"{TRIGGER.EVENTS.ACK}"
#define MVAR_TRIGGER_EVENTS_UNACK		"{TRIGGER.EVENTS.UNACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_ACK		"{TRIGGER.EVENTS.PROBLEM.ACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_UNACK	"{TRIGGER.EVENTS.PROBLEM.UNACK}"
#define	MVAR_TRIGGER_STATE_ERROR		"{TRIGGER.STATE.ERROR}"

#define MVAR_LLDRULE_DESCRIPTION		"{LLDRULE.DESCRIPTION}"
#define MVAR_LLDRULE_DESCRIPTION_ORIG		"{LLDRULE.DESCRIPTION.ORIG}"
#define MVAR_LLDRULE_ID				"{LLDRULE.ID}"
#define MVAR_LLDRULE_KEY			"{LLDRULE.KEY}"
#define MVAR_LLDRULE_KEY_ORIG			"{LLDRULE.KEY.ORIG}"
#define MVAR_LLDRULE_NAME			"{LLDRULE.NAME}"
#define MVAR_LLDRULE_NAME_ORIG			"{LLDRULE.NAME.ORIG}"
#define MVAR_LLDRULE_STATE			"{LLDRULE.STATE}"
#define MVAR_LLDRULE_STATE_ERROR		"{LLDRULE.STATE.ERROR}"

#define MVAR_INVENTORY				"{INVENTORY."			/* a prefix for all inventory macros */
#define MVAR_INVENTORY_TYPE			MVAR_INVENTORY "TYPE}"
#define MVAR_INVENTORY_TYPE_FULL		MVAR_INVENTORY "TYPE.FULL}"
#define MVAR_INVENTORY_NAME			MVAR_INVENTORY "NAME}"
#define MVAR_INVENTORY_ALIAS			MVAR_INVENTORY "ALIAS}"
#define MVAR_INVENTORY_OS			MVAR_INVENTORY "OS}"
#define MVAR_INVENTORY_OS_FULL			MVAR_INVENTORY "OS.FULL}"
#define MVAR_INVENTORY_OS_SHORT			MVAR_INVENTORY "OS.SHORT}"
#define MVAR_INVENTORY_SERIALNO_A		MVAR_INVENTORY "SERIALNO.A}"
#define MVAR_INVENTORY_SERIALNO_B		MVAR_INVENTORY "SERIALNO.B}"
#define MVAR_INVENTORY_TAG			MVAR_INVENTORY "TAG}"
#define MVAR_INVENTORY_ASSET_TAG		MVAR_INVENTORY "ASSET.TAG}"
#define MVAR_INVENTORY_MACADDRESS_A		MVAR_INVENTORY "MACADDRESS.A}"
#define MVAR_INVENTORY_MACADDRESS_B		MVAR_INVENTORY "MACADDRESS.B}"
#define MVAR_INVENTORY_HARDWARE			MVAR_INVENTORY "HARDWARE}"
#define MVAR_INVENTORY_HARDWARE_FULL		MVAR_INVENTORY "HARDWARE.FULL}"
#define MVAR_INVENTORY_SOFTWARE			MVAR_INVENTORY "SOFTWARE}"
#define MVAR_INVENTORY_SOFTWARE_FULL		MVAR_INVENTORY "SOFTWARE.FULL}"
#define MVAR_INVENTORY_SOFTWARE_APP_A		MVAR_INVENTORY "SOFTWARE.APP.A}"
#define MVAR_INVENTORY_SOFTWARE_APP_B		MVAR_INVENTORY "SOFTWARE.APP.B}"
#define MVAR_INVENTORY_SOFTWARE_APP_C		MVAR_INVENTORY "SOFTWARE.APP.C}"
#define MVAR_INVENTORY_SOFTWARE_APP_D		MVAR_INVENTORY "SOFTWARE.APP.D}"
#define MVAR_INVENTORY_SOFTWARE_APP_E		MVAR_INVENTORY "SOFTWARE.APP.E}"
#define MVAR_INVENTORY_CONTACT			MVAR_INVENTORY "CONTACT}"
#define MVAR_INVENTORY_LOCATION			MVAR_INVENTORY "LOCATION}"
#define MVAR_INVENTORY_LOCATION_LAT		MVAR_INVENTORY "LOCATION.LAT}"
#define MVAR_INVENTORY_LOCATION_LON		MVAR_INVENTORY "LOCATION.LON}"
#define MVAR_INVENTORY_NOTES			MVAR_INVENTORY "NOTES}"
#define MVAR_INVENTORY_CHASSIS			MVAR_INVENTORY "CHASSIS}"
#define MVAR_INVENTORY_MODEL			MVAR_INVENTORY "MODEL}"
#define MVAR_INVENTORY_HW_ARCH			MVAR_INVENTORY "HW.ARCH}"
#define MVAR_INVENTORY_VENDOR			MVAR_INVENTORY "VENDOR}"
#define MVAR_INVENTORY_CONTRACT_NUMBER		MVAR_INVENTORY "CONTRACT.NUMBER}"
#define MVAR_INVENTORY_INSTALLER_NAME		MVAR_INVENTORY "INSTALLER.NAME}"
#define MVAR_INVENTORY_DEPLOYMENT_STATUS	MVAR_INVENTORY "DEPLOYMENT.STATUS}"
#define MVAR_INVENTORY_URL_A			MVAR_INVENTORY "URL.A}"
#define MVAR_INVENTORY_URL_B			MVAR_INVENTORY "URL.B}"
#define MVAR_INVENTORY_URL_C			MVAR_INVENTORY "URL.C}"
#define MVAR_INVENTORY_HOST_NETWORKS		MVAR_INVENTORY "HOST.NETWORKS}"
#define MVAR_INVENTORY_HOST_NETMASK		MVAR_INVENTORY "HOST.NETMASK}"
#define MVAR_INVENTORY_HOST_ROUTER		MVAR_INVENTORY "HOST.ROUTER}"
#define MVAR_INVENTORY_OOB_IP			MVAR_INVENTORY "OOB.IP}"
#define MVAR_INVENTORY_OOB_NETMASK		MVAR_INVENTORY "OOB.NETMASK}"
#define MVAR_INVENTORY_OOB_ROUTER		MVAR_INVENTORY "OOB.ROUTER}"
#define MVAR_INVENTORY_HW_DATE_PURCHASE		MVAR_INVENTORY "HW.DATE.PURCHASE}"
#define MVAR_INVENTORY_HW_DATE_INSTALL		MVAR_INVENTORY "HW.DATE.INSTALL}"
#define MVAR_INVENTORY_HW_DATE_EXPIRY		MVAR_INVENTORY "HW.DATE.EXPIRY}"
#define MVAR_INVENTORY_HW_DATE_DECOMM		MVAR_INVENTORY "HW.DATE.DECOMM}"
#define MVAR_INVENTORY_SITE_ADDRESS_A		MVAR_INVENTORY "SITE.ADDRESS.A}"
#define MVAR_INVENTORY_SITE_ADDRESS_B		MVAR_INVENTORY "SITE.ADDRESS.B}"
#define MVAR_INVENTORY_SITE_ADDRESS_C		MVAR_INVENTORY "SITE.ADDRESS.C}"
#define MVAR_INVENTORY_SITE_CITY		MVAR_INVENTORY "SITE.CITY}"
#define MVAR_INVENTORY_SITE_STATE		MVAR_INVENTORY "SITE.STATE}"
#define MVAR_INVENTORY_SITE_COUNTRY		MVAR_INVENTORY "SITE.COUNTRY}"
#define MVAR_INVENTORY_SITE_ZIP			MVAR_INVENTORY "SITE.ZIP}"
#define MVAR_INVENTORY_SITE_RACK		MVAR_INVENTORY "SITE.RACK}"
#define MVAR_INVENTORY_SITE_NOTES		MVAR_INVENTORY "SITE.NOTES}"
#define MVAR_INVENTORY_POC_PRIMARY_NAME		MVAR_INVENTORY "POC.PRIMARY.NAME}"
#define MVAR_INVENTORY_POC_PRIMARY_EMAIL	MVAR_INVENTORY "POC.PRIMARY.EMAIL}"
#define MVAR_INVENTORY_POC_PRIMARY_PHONE_A	MVAR_INVENTORY "POC.PRIMARY.PHONE.A}"
#define MVAR_INVENTORY_POC_PRIMARY_PHONE_B	MVAR_INVENTORY "POC.PRIMARY.PHONE.B}"
#define MVAR_INVENTORY_POC_PRIMARY_CELL		MVAR_INVENTORY "POC.PRIMARY.CELL}"
#define MVAR_INVENTORY_POC_PRIMARY_SCREEN	MVAR_INVENTORY "POC.PRIMARY.SCREEN}"
#define MVAR_INVENTORY_POC_PRIMARY_NOTES	MVAR_INVENTORY "POC.PRIMARY.NOTES}"
#define MVAR_INVENTORY_POC_SECONDARY_NAME	MVAR_INVENTORY "POC.SECONDARY.NAME}"
#define MVAR_INVENTORY_POC_SECONDARY_EMAIL	MVAR_INVENTORY "POC.SECONDARY.EMAIL}"
#define MVAR_INVENTORY_POC_SECONDARY_PHONE_A	MVAR_INVENTORY "POC.SECONDARY.PHONE.A}"
#define MVAR_INVENTORY_POC_SECONDARY_PHONE_B	MVAR_INVENTORY "POC.SECONDARY.PHONE.B}"
#define MVAR_INVENTORY_POC_SECONDARY_CELL	MVAR_INVENTORY "POC.SECONDARY.CELL}"
#define MVAR_INVENTORY_POC_SECONDARY_SCREEN	MVAR_INVENTORY "POC.SECONDARY.SCREEN}"
#define MVAR_INVENTORY_POC_SECONDARY_NOTES	MVAR_INVENTORY "POC.SECONDARY.NOTES}"

/* PROFILE.* is deprecated, use INVENTORY.* instead */
#define MVAR_PROFILE			"{PROFILE."			/* prefix for profile macros */
#define MVAR_PROFILE_DEVICETYPE		MVAR_PROFILE "DEVICETYPE}"
#define MVAR_PROFILE_NAME		MVAR_PROFILE "NAME}"
#define MVAR_PROFILE_OS			MVAR_PROFILE "OS}"
#define MVAR_PROFILE_SERIALNO		MVAR_PROFILE "SERIALNO}"
#define MVAR_PROFILE_TAG		MVAR_PROFILE "TAG}"
#define MVAR_PROFILE_MACADDRESS		MVAR_PROFILE "MACADDRESS}"
#define MVAR_PROFILE_HARDWARE		MVAR_PROFILE "HARDWARE}"
#define MVAR_PROFILE_SOFTWARE		MVAR_PROFILE "SOFTWARE}"
#define MVAR_PROFILE_CONTACT		MVAR_PROFILE "CONTACT}"
#define MVAR_PROFILE_LOCATION		MVAR_PROFILE "LOCATION}"
#define MVAR_PROFILE_NOTES		MVAR_PROFILE "NOTES}"

#define MVAR_DISCOVERY_RULE_NAME	"{DISCOVERY.RULE.NAME}"
#define MVAR_DISCOVERY_SERVICE_NAME	"{DISCOVERY.SERVICE.NAME}"
#define MVAR_DISCOVERY_SERVICE_PORT	"{DISCOVERY.SERVICE.PORT}"
#define MVAR_DISCOVERY_SERVICE_STATUS	"{DISCOVERY.SERVICE.STATUS}"
#define MVAR_DISCOVERY_SERVICE_UPTIME	"{DISCOVERY.SERVICE.UPTIME}"
#define MVAR_DISCOVERY_DEVICE_IPADDRESS	"{DISCOVERY.DEVICE.IPADDRESS}"
#define MVAR_DISCOVERY_DEVICE_DNS	"{DISCOVERY.DEVICE.DNS}"
#define MVAR_DISCOVERY_DEVICE_STATUS	"{DISCOVERY.DEVICE.STATUS}"
#define MVAR_DISCOVERY_DEVICE_UPTIME	"{DISCOVERY.DEVICE.UPTIME}"

#define MVAR_ALERT_SENDTO		"{ALERT.SENDTO}"
#define MVAR_ALERT_SUBJECT		"{ALERT.SUBJECT}"
#define MVAR_ALERT_MESSAGE		"{ALERT.MESSAGE}"

#define MVAR_ACK_MESSAGE		"{ACK.MESSAGE}"			/* deprecated */
#define MVAR_ACK_TIME			"{ACK.TIME}"			/* deprecated */
#define MVAR_ACK_DATE			"{ACK.DATE}"			/* deprecated */
#define MVAR_USER_ALIAS			"{USER.ALIAS}"			/* deprecated */
#define MVAR_USER_USERNAME		"{USER.USERNAME}"
#define MVAR_USER_NAME			"{USER.NAME}"
#define MVAR_USER_SURNAME		"{USER.SURNAME}"
#define MVAR_USER_FULLNAME		"{USER.FULLNAME}"

#define STR_UNKNOWN_VARIABLE		"*UNKNOWN*"

/* macros that can be indexed */
static const char	*ex_macros[] =
{
	MVAR_INVENTORY_TYPE, MVAR_INVENTORY_TYPE_FULL,
	MVAR_INVENTORY_NAME, MVAR_INVENTORY_ALIAS, MVAR_INVENTORY_OS, MVAR_INVENTORY_OS_FULL, MVAR_INVENTORY_OS_SHORT,
	MVAR_INVENTORY_SERIALNO_A, MVAR_INVENTORY_SERIALNO_B, MVAR_INVENTORY_TAG,
	MVAR_INVENTORY_ASSET_TAG, MVAR_INVENTORY_MACADDRESS_A, MVAR_INVENTORY_MACADDRESS_B,
	MVAR_INVENTORY_HARDWARE, MVAR_INVENTORY_HARDWARE_FULL, MVAR_INVENTORY_SOFTWARE, MVAR_INVENTORY_SOFTWARE_FULL,
	MVAR_INVENTORY_SOFTWARE_APP_A, MVAR_INVENTORY_SOFTWARE_APP_B, MVAR_INVENTORY_SOFTWARE_APP_C,
	MVAR_INVENTORY_SOFTWARE_APP_D, MVAR_INVENTORY_SOFTWARE_APP_E, MVAR_INVENTORY_CONTACT, MVAR_INVENTORY_LOCATION,
	MVAR_INVENTORY_LOCATION_LAT, MVAR_INVENTORY_LOCATION_LON, MVAR_INVENTORY_NOTES, MVAR_INVENTORY_CHASSIS,
	MVAR_INVENTORY_MODEL, MVAR_INVENTORY_HW_ARCH, MVAR_INVENTORY_VENDOR, MVAR_INVENTORY_CONTRACT_NUMBER,
	MVAR_INVENTORY_INSTALLER_NAME, MVAR_INVENTORY_DEPLOYMENT_STATUS, MVAR_INVENTORY_URL_A, MVAR_INVENTORY_URL_B,
	MVAR_INVENTORY_URL_C, MVAR_INVENTORY_HOST_NETWORKS, MVAR_INVENTORY_HOST_NETMASK, MVAR_INVENTORY_HOST_ROUTER,
	MVAR_INVENTORY_OOB_IP, MVAR_INVENTORY_OOB_NETMASK, MVAR_INVENTORY_OOB_ROUTER, MVAR_INVENTORY_HW_DATE_PURCHASE,
	MVAR_INVENTORY_HW_DATE_INSTALL, MVAR_INVENTORY_HW_DATE_EXPIRY, MVAR_INVENTORY_HW_DATE_DECOMM,
	MVAR_INVENTORY_SITE_ADDRESS_A, MVAR_INVENTORY_SITE_ADDRESS_B, MVAR_INVENTORY_SITE_ADDRESS_C,
	MVAR_INVENTORY_SITE_CITY, MVAR_INVENTORY_SITE_STATE, MVAR_INVENTORY_SITE_COUNTRY, MVAR_INVENTORY_SITE_ZIP,
	MVAR_INVENTORY_SITE_RACK, MVAR_INVENTORY_SITE_NOTES, MVAR_INVENTORY_POC_PRIMARY_NAME,
	MVAR_INVENTORY_POC_PRIMARY_EMAIL, MVAR_INVENTORY_POC_PRIMARY_PHONE_A, MVAR_INVENTORY_POC_PRIMARY_PHONE_B,
	MVAR_INVENTORY_POC_PRIMARY_CELL, MVAR_INVENTORY_POC_PRIMARY_SCREEN, MVAR_INVENTORY_POC_PRIMARY_NOTES,
	MVAR_INVENTORY_POC_SECONDARY_NAME, MVAR_INVENTORY_POC_SECONDARY_EMAIL, MVAR_INVENTORY_POC_SECONDARY_PHONE_A,
	MVAR_INVENTORY_POC_SECONDARY_PHONE_B, MVAR_INVENTORY_POC_SECONDARY_CELL, MVAR_INVENTORY_POC_SECONDARY_SCREEN,
	MVAR_INVENTORY_POC_SECONDARY_NOTES,
	/* PROFILE.* is deprecated, use INVENTORY.* instead */
	MVAR_PROFILE_DEVICETYPE, MVAR_PROFILE_NAME, MVAR_PROFILE_OS, MVAR_PROFILE_SERIALNO,
	MVAR_PROFILE_TAG, MVAR_PROFILE_MACADDRESS, MVAR_PROFILE_HARDWARE, MVAR_PROFILE_SOFTWARE,
	MVAR_PROFILE_CONTACT, MVAR_PROFILE_LOCATION, MVAR_PROFILE_NOTES,
	MVAR_HOST_HOST, MVAR_HOSTNAME, MVAR_HOST_NAME, MVAR_HOST_DESCRIPTION, MVAR_PROXY_NAME, MVAR_PROXY_DESCRIPTION,
	MVAR_HOST_CONN, MVAR_HOST_DNS, MVAR_HOST_IP, MVAR_HOST_PORT, MVAR_IPADDRESS, MVAR_HOST_ID,
	MVAR_ITEM_ID, MVAR_ITEM_NAME, MVAR_ITEM_NAME_ORIG, MVAR_ITEM_DESCRIPTION, MVAR_ITEM_DESCRIPTION_ORIG,
	MVAR_ITEM_KEY, MVAR_ITEM_KEY_ORIG, MVAR_TRIGGER_KEY,
	MVAR_ITEM_LASTVALUE,
	MVAR_ITEM_STATE,
	MVAR_ITEM_VALUE, MVAR_ITEM_VALUETYPE,
	MVAR_ITEM_LOG_DATE, MVAR_ITEM_LOG_TIME, MVAR_ITEM_LOG_AGE, MVAR_ITEM_LOG_SOURCE,
	MVAR_ITEM_LOG_SEVERITY, MVAR_ITEM_LOG_NSEVERITY, MVAR_ITEM_LOG_EVENTID,
	MVAR_FUNCTION_VALUE, MVAR_FUNCTION_RECOVERY_VALUE,
	NULL
};

/* macros that are supported as host macro */
static const char	*host_macros[] = {MVAR_HOST_HOST, MVAR_HOSTNAME, NULL};

typedef struct
{
	char	*macro;
	char	*functions;
}
zbx_macro_functions_t;

/* macros that can be modified using macro functions */
static zbx_macro_functions_t	mod_macros[] =
{
	{MVAR_ITEM_VALUE, "regsub,iregsub,fmtnum"},
	{MVAR_ITEM_LASTVALUE, "regsub,iregsub,fmtnum"},
	{MVAR_TIME, "fmttime"},
	{"{?}", "fmtnum"},
	{NULL, NULL}
};

typedef struct
{
	const char	*macro;
	int		idx;
} inventory_field_t;

static inventory_field_t	inventory_fields[] =
{
	{MVAR_INVENTORY_TYPE, 0},
	{MVAR_PROFILE_DEVICETYPE, 0},	/* deprecated */
	{MVAR_INVENTORY_TYPE_FULL, 1},
	{MVAR_INVENTORY_NAME, 2},
	{MVAR_PROFILE_NAME, 2},	/* deprecated */
	{MVAR_INVENTORY_ALIAS, 3},
	{MVAR_INVENTORY_OS, 4},
	{MVAR_PROFILE_OS, 4},	/* deprecated */
	{MVAR_INVENTORY_OS_FULL, 5},
	{MVAR_INVENTORY_OS_SHORT, 6},
	{MVAR_INVENTORY_SERIALNO_A, 7},
	{MVAR_PROFILE_SERIALNO, 7},	/* deprecated */
	{MVAR_INVENTORY_SERIALNO_B, 8},
	{MVAR_INVENTORY_TAG, 9},
	{MVAR_PROFILE_TAG, 9},	/* deprecated */
	{MVAR_INVENTORY_ASSET_TAG, 10},
	{MVAR_INVENTORY_MACADDRESS_A, 11},
	{MVAR_PROFILE_MACADDRESS, 11},	/* deprecated */
	{MVAR_INVENTORY_MACADDRESS_B, 12},
	{MVAR_INVENTORY_HARDWARE, 13},
	{MVAR_PROFILE_HARDWARE, 13},	/* deprecated */
	{MVAR_INVENTORY_HARDWARE_FULL, 14},
	{MVAR_INVENTORY_SOFTWARE, 15},
	{MVAR_PROFILE_SOFTWARE, 15},	/* deprecated */
	{MVAR_INVENTORY_SOFTWARE_FULL, 16},
	{MVAR_INVENTORY_SOFTWARE_APP_A, 17},
	{MVAR_INVENTORY_SOFTWARE_APP_B, 18},
	{MVAR_INVENTORY_SOFTWARE_APP_C, 19},
	{MVAR_INVENTORY_SOFTWARE_APP_D, 20},
	{MVAR_INVENTORY_SOFTWARE_APP_E, 21},
	{MVAR_INVENTORY_CONTACT, 22},
	{MVAR_PROFILE_CONTACT, 22},	/* deprecated */
	{MVAR_INVENTORY_LOCATION, 23},
	{MVAR_PROFILE_LOCATION, 23},	/* deprecated */
	{MVAR_INVENTORY_LOCATION_LAT, 24},
	{MVAR_INVENTORY_LOCATION_LON, 25},
	{MVAR_INVENTORY_NOTES, 26},
	{MVAR_PROFILE_NOTES, 26},	/* deprecated */
	{MVAR_INVENTORY_CHASSIS, 27},
	{MVAR_INVENTORY_MODEL, 28},
	{MVAR_INVENTORY_HW_ARCH, 29},
	{MVAR_INVENTORY_VENDOR, 30},
	{MVAR_INVENTORY_CONTRACT_NUMBER, 31},
	{MVAR_INVENTORY_INSTALLER_NAME, 32},
	{MVAR_INVENTORY_DEPLOYMENT_STATUS, 33},
	{MVAR_INVENTORY_URL_A, 34},
	{MVAR_INVENTORY_URL_B, 35},
	{MVAR_INVENTORY_URL_C, 36},
	{MVAR_INVENTORY_HOST_NETWORKS, 37},
	{MVAR_INVENTORY_HOST_NETMASK, 38},
	{MVAR_INVENTORY_HOST_ROUTER, 39},
	{MVAR_INVENTORY_OOB_IP, 40},
	{MVAR_INVENTORY_OOB_NETMASK, 41},
	{MVAR_INVENTORY_OOB_ROUTER, 42},
	{MVAR_INVENTORY_HW_DATE_PURCHASE, 43},
	{MVAR_INVENTORY_HW_DATE_INSTALL, 44},
	{MVAR_INVENTORY_HW_DATE_EXPIRY, 45},
	{MVAR_INVENTORY_HW_DATE_DECOMM, 46},
	{MVAR_INVENTORY_SITE_ADDRESS_A, 47},
	{MVAR_INVENTORY_SITE_ADDRESS_B, 48},
	{MVAR_INVENTORY_SITE_ADDRESS_C, 49},
	{MVAR_INVENTORY_SITE_CITY, 50},
	{MVAR_INVENTORY_SITE_STATE, 51},
	{MVAR_INVENTORY_SITE_COUNTRY, 52},
	{MVAR_INVENTORY_SITE_ZIP, 53},
	{MVAR_INVENTORY_SITE_RACK, 54},
	{MVAR_INVENTORY_SITE_NOTES, 55},
	{MVAR_INVENTORY_POC_PRIMARY_NAME, 56},
	{MVAR_INVENTORY_POC_PRIMARY_EMAIL, 57},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_A, 58},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_B, 59},
	{MVAR_INVENTORY_POC_PRIMARY_CELL, 60},
	{MVAR_INVENTORY_POC_PRIMARY_SCREEN, 61},
	{MVAR_INVENTORY_POC_PRIMARY_NOTES, 62},
	{MVAR_INVENTORY_POC_SECONDARY_NAME, 63},
	{MVAR_INVENTORY_POC_SECONDARY_EMAIL, 64},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_A, 65},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_B, 66},
	{MVAR_INVENTORY_POC_SECONDARY_CELL, 67},
	{MVAR_INVENTORY_POC_SECONDARY_SCREEN, 68},
	{MVAR_INVENTORY_POC_SECONDARY_NOTES, 69},
	{NULL}
};

/******************************************************************************
 *                                                                            *
 * Purpose: request action value by macro                                     *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_action_value(const char *macro, zbx_uint64_t actionid, char **replace_to)
{
	int	ret = SUCCEED;

	if (0 == strcmp(macro, MVAR_ACTION_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, actionid);
	}
	else if (0 == strcmp(macro, MVAR_ACTION_NAME))
	{
		DB_RESULT	result;
		DB_ROW		row;

		result = DBselect("select name from actions where actionid=" ZBX_FS_UI64, actionid);

		if (NULL != (row = DBfetch(result)))
			*replace_to = zbx_strdup(*replace_to, row[0]);
		else
			ret = FAIL;

		DBfree_result(result);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request host inventory value by macro and trigger                 *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_host_inventory(const char *macro, const DB_TRIGGER *trigger, char **replace_to,
		int N_functionid)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
		{
			zbx_uint64_t	itemid;

			if (SUCCEED != zbx_db_trigger_get_itemid(trigger, N_functionid, &itemid))
				return FAIL;

			return DCget_host_inventory_value_by_itemid(itemid, replace_to, inventory_fields[i].idx);
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request host inventory value by macro and itemid                  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_host_inventory_by_itemid(const char *macro, zbx_uint64_t itemid, char **replace_to)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
			return DCget_host_inventory_value_by_itemid(itemid, replace_to, inventory_fields[i].idx);
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request host inventory value by macro and hostid                  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_host_inventory_by_hostid(const char *macro, zbx_uint64_t hostid, char **replace_to)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
			return DCget_host_inventory_value_by_hostid(hostid, replace_to, inventory_fields[i].idx);
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: comparison function to sort tags by tag/value                     *
 *                                                                            *
 ******************************************************************************/
static int	compare_tags(const void *d1, const void *d2)
{
	int	ret;

	const zbx_tag_t	*tag1 = *(const zbx_tag_t **)d1;
	const zbx_tag_t	*tag2 = *(const zbx_tag_t **)d2;

	if (0 == (ret = zbx_strcmp_natural(tag1->tag, tag2->tag)))
		ret = zbx_strcmp_natural(tag1->value, tag2->value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: format event tags string in format <tag1>[:<value1>], ...         *
 *                                                                            *
 * Parameters: event        [IN] the event                                    *
 *             replace_to - [OUT] replacement string                          *
 *                                                                            *
 ******************************************************************************/
static void	get_event_tags(const DB_EVENT *event, char **replace_to)
{
	size_t			replace_to_offset = 0, replace_to_alloc = 0;
	int			i;
	zbx_vector_ptr_t	tags;

	if (0 == event->tags.values_num)
	{
		*replace_to = zbx_strdup(*replace_to, "");
		return;
	}

	zbx_free(*replace_to);

	/* copy tags to temporary vector for sorting */

	zbx_vector_ptr_create(&tags);
	zbx_vector_ptr_reserve(&tags, event->tags.values_num);

	for (i = 0; i < event->tags.values_num; i++)
		zbx_vector_ptr_append(&tags, event->tags.values[i]);

	zbx_vector_ptr_sort(&tags, compare_tags);

	for (i = 0; i < tags.values_num; i++)
	{
		const zbx_tag_t	*tag = (const zbx_tag_t *)tags.values[i];

		if (0 != i)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");

		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, tag->tag);

		if ('\0' != *tag->value)
		{
			zbx_chrcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ':');
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, tag->value);
		}
	}

	zbx_vector_ptr_destroy(&tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: format event tags string in JSON format                           *
 *                                                                            *
 * Parameters: event        [IN] the event                                    *
 *             replace_to - [OUT] replacement string                          *
 *                                                                            *
 ******************************************************************************/
static void	get_event_tags_json(const DB_EVENT *event, char **replace_to)
{
	struct zbx_json	json;
	int		i;

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < event->tags.values_num; i++)
	{
		const zbx_tag_t	*tag = (const zbx_tag_t *)event->tags.values[i];

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, "tag", tag->tag, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "value", tag->value, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);
	}

	zbx_json_close(&json);
	*replace_to = zbx_strdup(*replace_to, json.buffer);
	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get event tag value by name                                       *
 *                                                                            *
 * Parameters: macro      - [IN] the macro                                    *
 *             event      - [IN] event                                        *
 *             replace_to - [OUT] replacement string                          *
 *                                                                            *
 ******************************************************************************/
static void	get_event_tag_by_name(const char *text, const DB_EVENT *event, char **replace_to)
{
	char	*name;

	if (SUCCEED == zbx_str_extract(text, strlen(text) - 1, &name))
	{
		if (0 < event->tags.values_num)
		{
			int			i;
			zbx_tag_t		*tag;
			zbx_vector_ptr_t	ptr_tags;

			zbx_vector_ptr_create(&ptr_tags);
			zbx_vector_ptr_append_array(&ptr_tags, event->tags.values,
					event->tags.values_num);
			zbx_vector_ptr_sort(&ptr_tags, compare_tags);

			for (i = 0; i < ptr_tags.values_num; i++)
			{
				tag = (zbx_tag_t *)ptr_tags.values[i];

				if (0 == strcmp(name, tag->tag))
				{
					*replace_to = zbx_strdup(*replace_to, tag->value);
					break;
				}
			}

			zbx_vector_ptr_destroy(&ptr_tags);
		}

		zbx_free(name);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: request recovery event value by macro                             *
 *                                                                            *
 ******************************************************************************/
static void	get_recovery_event_value(const char *macro, const DB_EVENT *r_event, char **replace_to, const char *tz)
{
	if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_DATE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_date2str(r_event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, r_event->eventid);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_STATUS))
	{
		*replace_to = zbx_strdup(*replace_to,
				zbx_event_value_string(r_event->source, r_event->object, r_event->value));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TIME))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_time2str(r_event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_VALUE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", r_event->value);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_NAME))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%s", r_event->name);
	}
	else if (EVENT_SOURCE_TRIGGERS == r_event->source || EVENT_SOURCE_SERVICE == r_event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TAGS))
			get_event_tags(r_event, replace_to);
		else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TAGSJSON))
			get_event_tags_json(r_event, replace_to);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: request current event value by macro                              *
 *                                                                            *
 ******************************************************************************/
static void	get_current_event_value(const char *macro, const DB_EVENT *event, char **replace_to)
{
	if (0 == strcmp(macro, MVAR_EVENT_STATUS))
	{
		*replace_to = zbx_strdup(*replace_to,
				zbx_event_value_string(event->source, event->object, event->value));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_VALUE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", event->value);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: request event value by macro                                      *
 *                                                                            *
 ******************************************************************************/
static void	get_event_value(const char *macro, const DB_EVENT *event, char **replace_to,
			const zbx_uint64_t *recipient_userid, const DB_EVENT *r_event, const char *tz)
{
	if (0 == strcmp(macro, MVAR_EVENT_AGE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_DATE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_date2str(event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_DURATION))
	{
		if (NULL == r_event)
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - event->clock));
		else
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(r_event->clock - event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, event->eventid);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_TIME))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_time2str(event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_SOURCE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", event->source);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_OBJECT))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", event->object);
	}
	else if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_ACK_HISTORY) || 0 == strcmp(macro, MVAR_EVENT_UPDATE_HISTORY))
		{
			get_event_update_history(event, replace_to, recipient_userid, tz);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_ACK_STATUS))
		{
			*replace_to = zbx_strdup(*replace_to, event->acknowledged ? "Yes" : "No");
		}
		else if (0 == strcmp(macro, MVAR_EVENT_NSEVERITY))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", (int)event->severity);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_SEVERITY))
		{
			if (FAIL == get_trigger_severity_name(event->severity, replace_to))
				*replace_to = zbx_strdup(*replace_to, "unknown");
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGS))
		{
			get_event_tags(event, replace_to);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGSJSON))
		{
			get_event_tags_json(event, replace_to);
		}
		else if (0 == strncmp(macro, MVAR_EVENT_TAGS_PREFIX, ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX)))
		{
			get_event_tag_by_name(macro + ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX), event, replace_to);
		}
	}
	else if (EVENT_SOURCE_SERVICE == event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_NSEVERITY))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", (int)event->severity);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_SEVERITY))
		{
			if (FAIL == get_trigger_severity_name(event->severity, replace_to))
				*replace_to = zbx_strdup(*replace_to, "unknown");
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGS))
		{
			get_event_tags(event, replace_to);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGSJSON))
		{
			get_event_tags_json(event, replace_to);
		}
		else if (0 == strncmp(macro, MVAR_EVENT_TAGS_PREFIX, ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX)))
		{
			get_event_tag_by_name(macro + ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX), event, replace_to);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: free memory allocated for root cause                              *
 *                                                                            *
 ******************************************************************************/
static void	rootcause_free(zbx_rootcause_t *rootcause)
{
	zbx_free(rootcause->host);
	zbx_free(rootcause->severity);
	zbx_free(rootcause->tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare root cause to sort by highest severity and host name      *
 *                                                                            *
 ******************************************************************************/
static int	rootcause_compare(const zbx_rootcause_t *d1, const zbx_rootcause_t *d2)
{
	ZBX_RETURN_IF_NOT_EQUAL(d2->nseverity, d1->nseverity);

	return strcmp(d1->host, d2->host);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get root cause of service being in problem state                  *
 *                                                                            *
 ******************************************************************************/
static void	get_rootcause(const DB_SERVICE *service, char **replace_to)
{
	int			i;
	char			*d = "";
	zbx_vector_rootcause_t	rootcauses;

	zbx_vector_rootcause_create(&rootcauses);

	for (i = 0; i < service->events.values_num; i++)
	{
		DB_EVENT		*event;
		zbx_rootcause_t		rootcause = {0};
		int			ret;

		event = (DB_EVENT *)service->events.values[i];

		if (FAIL == (ret = DBget_trigger_value(&event->trigger, &rootcause.host, 1, ZBX_REQUEST_HOST_HOST)))
			goto fail;

		rootcause.nseverity = event->severity;
		if (FAIL == (ret = get_trigger_severity_name(event->severity, &rootcause.severity)))
			goto fail;

		get_event_tags(event, &rootcause.tags);
		rootcause.name = event->name;
		rootcause.clock = event->clock;
fail:
		if (FAIL == ret)
			rootcause_free(&rootcause);
		else
			zbx_vector_rootcause_append(&rootcauses, rootcause);
	}

	zbx_vector_rootcause_sort(&rootcauses, (zbx_compare_func_t)rootcause_compare);

	for (i = 0; i < rootcauses.values_num; i++)
	{
		zbx_rootcause_t	*rootcause = &rootcauses.values[i];

		*replace_to = zbx_strdcatf(*replace_to, "%sHost: \"%s\" Problem name: \"%s\" Severity: \"%s\""
				" Age: %s Problem tags: \"%s\"", d, rootcause->host, rootcause->name,
				rootcause->severity, zbx_age2str(time(NULL) - rootcause->clock), rootcause->tags);
		d = "\n";
	}

	for (i = 0; i < rootcauses.values_num; i++)
		rootcause_free(&rootcauses.values[i]);

	zbx_vector_rootcause_destroy(&rootcauses);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular attribute of a log value                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_history_log_value(const char *m, const DB_TRIGGER *trigger, char **replace_to, int N_functionid,
		int clock, int ns, const char *tz)
{
	zbx_uint64_t	itemid;
	int		ret, request;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == strcmp(m, MVAR_ITEM_LOG_AGE))
	{
		request = ZBX_REQUEST_ITEM_LOG_AGE;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_DATE))
	{
		request = ZBX_REQUEST_ITEM_LOG_DATE;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_EVENTID))
	{
		request = ZBX_REQUEST_ITEM_LOG_EVENTID;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_NSEVERITY))
	{
		request = ZBX_REQUEST_ITEM_LOG_NSEVERITY;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_SEVERITY))
	{
		request = ZBX_REQUEST_ITEM_LOG_SEVERITY;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_SOURCE))
	{
		request = ZBX_REQUEST_ITEM_LOG_SOURCE;
	}
	else	/* MVAR_ITEM_LOG_TIME */
		request = ZBX_REQUEST_ITEM_LOG_TIME;

	if (SUCCEED == (ret = zbx_db_trigger_get_itemid(trigger, N_functionid, &itemid)))
		ret = DBget_history_log_value(itemid, replace_to, request, clock, ns, tz);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if a token contains indexed macro                           *
 *                                                                            *
 ******************************************************************************/
static int	is_indexed_macro(const char *str, const zbx_token_t *token)
{
	const char	*p;

	switch (token->type)
	{
		case ZBX_TOKEN_MACRO:
			p = str + token->loc.r - 1;
			break;
		case ZBX_TOKEN_FUNC_MACRO:
			p = str + token->data.func_macro.macro.r - 1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	return '1' <= *p && *p <= '9' ? 1 : 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if a macro in string is one of the list and extract index   *
 *                                                                            *
 * Parameters: str          - [IN] string containing potential macro          *
 *             strloc       - [IN] part of the string to check                *
 *             macros       - [IN] list of allowed macros (without indices)   *
 *             N_functionid - [OUT] index of the macro in string (if valid)   *
 *                                                                            *
 * Return value: unindexed macro from the allowed list or NULL                *
 *                                                                            *
 * Comments: example: N_functionid is untouched if function returns NULL, for *
 *           a valid unindexed macro N_function is 1.                         *
 *                                                                            *
 ******************************************************************************/
static const char	*macro_in_list(const char *str, zbx_strloc_t strloc, const char **macros, int *N_functionid)
{
	const char	**macro, *m;
	size_t		i;

	for (macro = macros; NULL != *macro; macro++)
	{
		for (m = *macro, i = strloc.l; '\0' != *m && i <= strloc.r && str[i] == *m; m++, i++)
			;

		/* check whether macro has ended while strloc hasn't or vice-versa */
		if (('\0' == *m && i <= strloc.r) || ('\0' != *m && i > strloc.r))
			continue;

		/* strloc either fully matches macro... */
		if ('\0' == *m)
		{
			if (NULL != N_functionid)
				*N_functionid = 1;

			break;
		}

		/* ...or there is a mismatch, check if it's in a pre-last character and it's an index */
		if (i == strloc.r - 1 && '1' <= str[i] && str[i] <= '9' && str[i + 1] == *m && '\0' == *(m + 1))
		{
			if (NULL != N_functionid)
				*N_functionid = str[i] - '0';

			break;
		}
	}

	return *macro;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if a macro function one in the list for the macro           *
 *                                                                            *
 * Parameters: str          - [IN] string containing potential macro          *
 *             fm           - [IN] function macro to check                    *
 *             N_functionid - [OUT] index of the macro in string (if valid)   *
 *                                                                            *
 * Return value: unindexed macro from the allowed list or NULL                *
 *                                                                            *
 ******************************************************************************/
static const char	*func_macro_in_list(const char *str, zbx_token_func_macro_t *fm, int *N_functionid)
{
	int	i;

	for (i = 0; NULL != mod_macros[i].macro; i++)
	{
		size_t	len, fm_len;

		len = strlen(mod_macros[i].macro);
		fm_len = fm->macro.r - fm->macro.l + 1;

		if (len > fm_len || 0 != strncmp(mod_macros[i].macro, str + fm->macro.l, len - 1))
			continue;

		if ('?' != mod_macros[i].macro[1] && len != fm_len)
		{
			if (SUCCEED != is_uint_n_range(str + fm->macro.l + len - 1, fm_len - len, N_functionid,
					sizeof(*N_functionid), 1, 9))
			{
				continue;
			}
		}
		else if (mod_macros[i].macro[len - 1] != str[fm->macro.l + fm_len - 1])
			continue;

		if (SUCCEED == str_n_in_list(mod_macros[i].functions, str + fm->func.l, fm->func_param.l - fm->func.l,
				','))
		{
			return mod_macros[i].macro;
		}
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate result of expression macro                              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_expression_macro_result(const DB_EVENT *event, char *data, zbx_strloc_t *loc,
		zbx_timespec_t *ts, char **replace_to, char **error)
{
	int				ret = FAIL;
	zbx_eval_context_t		ctx;
	const zbx_vector_uint64_t	*hostids;
	zbx_variant_t			value;
	zbx_expression_eval_t		eval;
	char				*expression = NULL;
	size_t				exp_alloc = 0, exp_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strncpy_alloc(&expression, &exp_alloc, &exp_offset, data + loc->l, loc->r - loc->l + 1);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() expression: '%s'", __func__, expression);

	if (SUCCEED != zbx_eval_parse_expression(&ctx, expression, ZBX_EVAL_PARSE_EXPRESSION_MACRO, error))
		goto out;

	if (SUCCEED != zbx_db_trigger_get_all_hostids(&event->trigger, &hostids))
	{
		*error = zbx_strdup(NULL, "cannot obtain host identifiers for the expression macro");
		goto out;
	}

	if (SUCCEED != zbx_eval_expand_user_macros(&ctx, hostids->values, hostids->values_num,
			zbx_dc_expand_user_macros_len, error))
	{
		goto out;
	}

	zbx_expression_eval_init(&eval, ZBX_EXPRESSION_NORMAL, &ctx);
	zbx_expression_eval_resolve_trigger_hosts(&eval, &event->trigger);

	if (SUCCEED == (ret = zbx_expression_eval_execute(&eval, ts, &value, error)))
	{
		*replace_to = zbx_strdup(NULL, zbx_variant_value_desc(&value));
		zbx_variant_clear(&value);
	}

	zbx_expression_eval_clear(&eval);
out:
	zbx_eval_clear(&ctx);
	zbx_free(expression);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cache host identifier referenced by an item or a lld-rule         *
 *                                                                            *
 * Parameters: hostids - [OUT] the host identifier cache                      *
 *             itemid  - [IN]  the item identifier                            *
 *                                                                            *
 ******************************************************************************/
static void	cache_item_hostid(zbx_vector_uint64_t *hostids, zbx_uint64_t itemid)
{
	if (0 == hostids->values_num)
	{
		DC_ITEM	item;
		int	errcode;

		DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

		if (SUCCEED == errcode)
			zbx_vector_uint64_append(hostids, item.host.hostid);

		DCconfig_clean_items(&item, &errcode, 1);
	}
}

static const char	*zbx_dobject_status2str(int st)
{
	switch (st)
	{
		case DOBJECT_STATUS_UP:
			return "UP";
		case DOBJECT_STATUS_DOWN:
			return "DOWN";
		case DOBJECT_STATUS_DISCOVER:
			return "DISCOVERED";
		case DOBJECT_STATUS_LOST:
			return "LOST";
		default:
			return "UNKNOWN";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve {EVENT.OPDATA} macro                                      *
 *                                                                            *
 ******************************************************************************/
static void	resolve_opdata(const DB_EVENT *event, char **replace_to, const char *tz, char *error, int maxerrlen)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if ('\0' == *event->trigger.opdata)
	{
		int			i;
		zbx_vector_uint64_t	itemids;
		zbx_timespec_t		ts;

		ts.sec = time(NULL);
		ts.ns = 999999999;

		zbx_vector_uint64_create(&itemids);
		zbx_db_trigger_get_itemids(&event->trigger, &itemids);

		for (i = 0; i < itemids.values_num; i++)
		{
			char	*val = NULL;

			if (NULL != *replace_to)
				*replace_to = zbx_strdcat(*replace_to, ", ");

			if (SUCCEED == DBitem_get_value(itemids.values[i], &val, 0, &ts))
			{
				*replace_to = zbx_strdcat(*replace_to, val);
				zbx_free(val);
			}
			else
				*replace_to = zbx_strdcat(*replace_to, STR_UNKNOWN_VARIABLE);
		}

		zbx_vector_uint64_destroy(&itemids);
	}
	else
	{
		*replace_to = zbx_strdup(*replace_to, event->trigger.opdata);
		substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, tz,
				replace_to, MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve {USER.*} macros                                           *
 *                                                                            *
 ******************************************************************************/
static void	resolve_user_macros(zbx_uint64_t userid, const char *m, char **user_username, char **user_name,
		char **user_surname, int *user_names_found, char **replace_to)
{
	/* use only one DB request for all occurrences of 5 macros */
	if (0 == *user_names_found)
	{
		if (SUCCEED == DBget_user_names(userid, user_username, user_name, user_surname))
			*user_names_found = 1;
		else
			return;
	}

	if (0 == strcmp(m, MVAR_USER_USERNAME) || 0 == strcmp(m, MVAR_USER_ALIAS))
	{
		*replace_to = zbx_strdup(*replace_to, *user_username);
	}
	else if (0 == strcmp(m, MVAR_USER_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, *user_name);
	}
	else if (0 == strcmp(m, MVAR_USER_SURNAME))
	{
		*replace_to = zbx_strdup(*replace_to, *user_surname);
	}
	else if (0 == strcmp(m, MVAR_USER_FULLNAME))
	{
		zbx_free(*replace_to);
		*replace_to = format_user_fullname(*user_name, *user_surname, *user_username);
	}
}

static int	resolve_host_target_macros(const char *m, const DC_HOST *dc_host, DC_INTERFACE *interface,
		int *require_address, char **replace_to)
{
	int	ret = SUCCEED;

	if (NULL == dc_host)
		return SUCCEED;

	if (0 == strcmp(m, MVAR_HOST_TARGET_DNS))
	{
		if (SUCCEED == (ret = DCconfig_get_interface(interface, dc_host->hostid, 0)))
			*replace_to = zbx_strdup(*replace_to, interface->dns_orig);

		*require_address = 1;
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_CONN))
	{
		if (SUCCEED == (ret = DCconfig_get_interface(interface, dc_host->hostid, 0)))
			*replace_to = zbx_strdup(*replace_to, interface->addr);

		*require_address = 1;

	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_HOST))
	{
		*replace_to = zbx_strdup(*replace_to, dc_host->host);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_IP))
	{
		if (SUCCEED == (ret = DCconfig_get_interface(interface, dc_host->hostid, 0)))
			*replace_to = zbx_strdup(*replace_to, interface->ip_orig);

		*require_address = 1;
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, dc_host->name);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values          *
 *                                                                            *
 ******************************************************************************/
static int	substitute_simple_macros_impl(const zbx_uint64_t *actionid, const DB_EVENT *event,
		const DB_EVENT *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host,
		const DC_ITEM *dc_item, const DB_ALERT *alert, const DB_ACKNOWLEDGE *ack,
		const zbx_service_alarm_t *service_alarm, const DB_SERVICE *service, const char *tz, char **data,
		int macro_type, char *error, int maxerrlen)
{
	char				c, *replace_to = NULL, sql[64];
	const char			*m;
	int				N_functionid, indexed_macro, require_address, ret, res = SUCCEED,
					pos = 0, found, user_names_found = 0, raw_value;
	size_t				data_alloc, data_len;
	DC_INTERFACE			interface;
	zbx_vector_uint64_t		hostids;
	const zbx_vector_uint64_t	*phostids;
	zbx_token_t			token, inner_token;
	zbx_token_search_t		token_search = ZBX_TOKEN_SEARCH_BASIC;
	char				*expression = NULL, *user_username = NULL, *user_name = NULL, *user_surname = NULL;

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:EMPTY", __func__);
		return res;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, *data);

	if (0 != (macro_type & (MACRO_TYPE_TRIGGER_DESCRIPTION | MACRO_TYPE_EVENT_NAME)))
		token_search |= ZBX_TOKEN_SEARCH_REFERENCES;

	if (0 != (macro_type & (MACRO_TYPE_MESSAGE_NORMAL | MACRO_TYPE_MESSAGE_RECOVERY | MACRO_TYPE_MESSAGE_UPDATE |
			MACRO_TYPE_EVENT_NAME)))
	{
		token_search |= ZBX_TOKEN_SEARCH_EXPRESSION_MACRO;
	}

	if (SUCCEED != zbx_token_find(*data, pos, &token, token_search))
		return res;

	zbx_vector_uint64_create(&hostids);

	data_alloc = data_len = strlen(*data) + 1;

	for (found = SUCCEED; SUCCEED == res && SUCCEED == found;
			found = zbx_token_find(*data, pos, &token, token_search))
	{
		indexed_macro = 0;
		require_address = 0;
		N_functionid = 1;
		raw_value = 0;
		pos = token.loc.l;
		inner_token = token;

		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
			case ZBX_TOKEN_LLD_MACRO:
			case ZBX_TOKEN_LLD_FUNC_MACRO:
				/* neither lld nor {123123} macros are processed by this function, skip them */
				pos = token.loc.r + 1;
				continue;
			case ZBX_TOKEN_MACRO:
				if (0 != is_indexed_macro(*data, &token) &&
						NULL != (m = macro_in_list(*data, token.loc, ex_macros, &N_functionid)))
				{
					indexed_macro = 1;
				}
				else
				{
					m = *data + token.loc.l;
					c = (*data)[token.loc.r + 1];
					(*data)[token.loc.r + 1] = '\0';
				}
				break;
			case ZBX_TOKEN_FUNC_MACRO:
				raw_value = 1;
				indexed_macro = is_indexed_macro(*data, &token);
				if (NULL == (m = func_macro_in_list(*data, &token.data.func_macro, &N_functionid)) ||
						SUCCEED != zbx_token_find(*data, token.data.func_macro.macro.l,
								&inner_token, token_search))
				{
					/* Ignore functions with macros not supporting them, but do not skip the */
					/* whole token, nested macro should be resolved in this case. */
					pos++;
					continue;
				}
				break;
			case ZBX_TOKEN_USER_MACRO:
				/* To avoid *data modification DCget_user_macro() should be replaced with a function */
				/* that takes initial *data string and token.data.user_macro instead of m as params. */
				m = *data + token.loc.l;
				c = (*data)[token.loc.r + 1];
				(*data)[token.loc.r + 1] = '\0';
				break;
			case ZBX_TOKEN_REFERENCE:
			case ZBX_TOKEN_EXPRESSION_MACRO:
				/* These macros (and probably all other in the future) must be resolved using only */
				/* information stored in token.data union. For now, force crash if they rely on m. */
				m = NULL;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				res = FAIL;
				continue;
		}

		ret = SUCCEED;

		if (0 != (macro_type & (MACRO_TYPE_MESSAGE_NORMAL | MACRO_TYPE_MESSAGE_RECOVERY |
				MACRO_TYPE_MESSAGE_UPDATE |
				MACRO_TYPE_SCRIPT_NORMAL | MACRO_TYPE_SCRIPT_RECOVERY)))
		/* MACRO_TYPE_SCRIPT_NORMAL and MACRO_TYPE_SCRIPT_RECOVERY behave pretty similar to */
		/* MACRO_TYPE_MESSAGE_NORMAL and MACRO_TYPE_MESSAGE_RECOVERY. Therefore the code is not duplicated */
		/* but few conditions are added below where behavior differs. */
		{
			const DB_EVENT	*c_event;

			c_event = ((NULL != r_event) ? r_event : event);

			if (EVENT_SOURCE_TRIGGERS == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (NULL == dc_host)
					{
						if (SUCCEED == zbx_db_trigger_get_all_hostids(&c_event->trigger,
								&phostids))
						{
							DCget_user_macro(phostids->values, phostids->values_num, m,
									&replace_to);
						}
					}
					else
						DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);

					pos = token.loc.r;
				}
				else if (ZBX_TOKEN_EXPRESSION_MACRO == token.type)
				{
					zbx_timespec_t	ts;
					char		*errmsg = NULL;

					zbx_timespec(&ts);

					if (SUCCEED != (ret = get_expression_macro_result(event, *data,
							&inner_token.data.expression_macro.expression, &ts, &replace_to,
							&errmsg)))
					{
						*errmsg = tolower(*errmsg);
						zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot evaluate"
								" expression macro: %s", __func__, errmsg);
						zbx_strlcpy(error, errmsg, maxerrlen);
						zbx_free(errmsg);
					}
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid, tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strcmp(m, MVAR_EVENT_OPDATA))
				{
					resolve_opdata(c_event, &replace_to, tz, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_ACK_MESSAGE) || 0 == strcmp(m, MVAR_EVENT_UPDATE_MESSAGE))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, ack->message);
				}
				else if (0 == strcmp(m, MVAR_ACK_TIME) || 0 == strcmp(m, MVAR_EVENT_UPDATE_TIME))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, zbx_time2str(ack->clock, tz));
				}
				else if (0 == strcmp(m, MVAR_ACK_DATE) || 0 == strcmp(m, MVAR_EVENT_UPDATE_DATE))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, zbx_date2str(ack->clock, tz));
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_ACTION))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
					{
						get_problem_update_actions(ack, ZBX_PROBLEM_UPDATE_ACKNOWLEDGE |
								ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE |
								ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_MESSAGE |
								ZBX_PROBLEM_UPDATE_SEVERITY, &replace_to);
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_STATUS))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, "1");
					else
						replace_to = zbx_strdup(replace_to, "0");
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = get_host_inventory(m, &c_event->trigger, &replace_to, N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION_ORIG))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_ID);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = DBitem_lastvalue(&c_event->trigger, &replace_to, N_functionid,
							raw_value);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = DBitem_value(&c_event->trigger, &replace_to, N_functionid,
							c_event->clock, c_event->ns, raw_value);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = get_history_log_value(m, &c_event->trigger, &replace_to,
							N_functionid, c_event->clock, c_event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUETYPE))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_VALUETYPE);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == indexed_macro && 0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, &replace_to, MACRO_TYPE_TRIGGER_COMMENTS,
							error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_ACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 0, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_ACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 1, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_UNACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 1, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_UNACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 0, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION))
				{
					zbx_db_trigger_get_expression(&c_event->trigger, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_get_recovery_expression(&c_event->trigger, &replace_to);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
				{
					zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
							evaluate_function2, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY_EXPLAIN))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
								evaluate_function2, 1);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (1 == indexed_macro && 0 == strcmp(m, MVAR_FUNCTION_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, evaluate_function2, 0);
				}
				else if (1 == indexed_macro && 0 == strcmp(m, MVAR_FUNCTION_RECOVERY_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, evaluate_function2, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_HOSTGROUP_NAME))
				{
					ret = DBget_trigger_hostgroup_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, &replace_to,
							MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME_ORIG))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NSEVERITY))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", (int)c_event->trigger.priority);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATUS) || 0 == strcmp(m, MVAR_STATUS))
				{
					replace_to = zbx_strdup(replace_to,
							zbx_trigger_value_string(c_event->trigger.value));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
				{
					ret = get_trigger_severity_name(c_event->trigger.priority, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_TEMPLATE_NAME))
				{
					ret = DBget_trigger_template_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					replace_to = zbx_strdup(replace_to, event->trigger.url);
					substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, &replace_to, MACRO_TYPE_TRIGGER_URL,
							error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", c_event->trigger.value);
				}
				else if (0 != (macro_type & MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack &&
						0 == strcmp(m, MVAR_USER_FULLNAME))
				{
					const char	*user_name1;

					if (SUCCEED == zbx_check_user_permissions(&ack->userid, userid))
						user_name1 = zbx_user_string(ack->userid);
					else
						user_name1 = "Inaccessible user";

					replace_to = zbx_strdup(replace_to, user_name1);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else if (0 != (macro_type & (MACRO_TYPE_SCRIPT_NORMAL | MACRO_TYPE_SCRIPT_RECOVERY)) &&
						NULL != userid && (0 == strcmp(m, MVAR_USER_USERNAME) ||
						0 == strcmp(m, MVAR_USER_NAME) || 0 == strcmp(m, MVAR_USER_SURNAME) ||
						0 == strcmp(m, MVAR_USER_FULLNAME) || 0 == strcmp(m, MVAR_USER_ALIAS)))
				{
					resolve_user_macros(*userid, m, &user_username, &user_name, &user_surname,
							&user_names_found, &replace_to);
				}
				else if (0 == (macro_type & (MACRO_TYPE_SCRIPT_NORMAL | MACRO_TYPE_SCRIPT_RECOVERY)))
				{
					ret = resolve_host_target_macros(m, dc_host, &interface, &require_address,
							&replace_to);
				}
			}
			else if (EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_TRIGGER == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&c_event->trigger, &phostids))
						DCget_user_macro(phostids->values, phostids->values_num, m, &replace_to);
					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid, tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = get_host_inventory(m, &c_event->trigger, &replace_to,
							N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION_ORIG))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_ID);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUETYPE))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_VALUETYPE);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == indexed_macro && 0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, &replace_to, MACRO_TYPE_TRIGGER_COMMENTS,
							error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION))
				{
					zbx_db_trigger_get_expression(&c_event->trigger, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_get_recovery_expression(&c_event->trigger, &replace_to);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
				{
					zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
							evaluate_function2, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY_EXPLAIN))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
								evaluate_function2, 1);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (1 == indexed_macro && 0 == strcmp(m, MVAR_FUNCTION_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, evaluate_function2, 0);
				}
				else if (1 == indexed_macro && 0 == strcmp(m, MVAR_FUNCTION_RECOVERY_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, evaluate_function2, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_HOSTGROUP_NAME))
				{
					ret = DBget_trigger_hostgroup_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, &replace_to,
							MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME_ORIG))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NSEVERITY))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", (int)c_event->trigger.priority);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
				{
					ret = get_trigger_severity_name(c_event->trigger.priority, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_trigger_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATE_ERROR))
				{
					ret = DBget_trigger_error(&event->trigger, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_TEMPLATE_NAME))
				{
					ret = DBget_trigger_template_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					replace_to = zbx_strdup(replace_to, event->trigger.url);
					substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, &replace_to, MACRO_TYPE_TRIGGER_URL,
							error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_DISCOVERY == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (NULL == dc_host)
						DCget_user_macro(NULL, 0, m, &replace_to);
					else
						DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);

					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)) &&
						0 != strcmp(m, MVAR_EVENT_DURATION))
				{
					get_event_value(m, event, &replace_to, userid, NULL, tz);
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_IPADDRESS))
				{
					ret = DBget_dhost_value_by_event(c_event, &replace_to, "s.ip");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_DNS))
				{
					ret = DBget_dhost_value_by_event(c_event, &replace_to, "s.dns");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_STATUS))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to,
							"h.status")))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_dobject_status2str(atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql),
							"case when h.status=%d then h.lastup else h.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to, sql)))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_age2str(time(NULL) - atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_RULE_NAME))
				{
					ret = DBget_drule_value_by_event(c_event, &replace_to, "name");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_NAME))
				{
					if (SUCCEED == (ret = DBget_dchecks_value_by_event(c_event, &replace_to,
							"c.type")))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_dservice_type_string(atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_PORT))
				{
					ret = DBget_dservice_value_by_event(c_event, &replace_to, "s.port");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_STATUS))
				{
					if (SUCCEED == (ret = DBget_dservice_value_by_event(c_event, &replace_to,
							"s.status")))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_dobject_status2str(atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql),
							"case when s.status=%d then s.lastup else s.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = DBget_dservice_value_by_event(c_event, &replace_to, sql)))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_age2str(time(NULL) - atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to,
							"r.proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = DBget_host_value(proxy_hostid, &replace_to, "host");
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to,
							"r.proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
						{
							replace_to = zbx_strdup(replace_to, "");
						}
						else
						{
							ret = DBget_host_value(proxy_hostid, &replace_to,
									"description");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else
				{
					ret = resolve_host_target_macros(m, dc_host, &interface, &require_address,
							&replace_to);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_AUTOREGISTRATION == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (NULL == dc_host)
						DCget_user_macro(NULL, 0, m, &replace_to);
					else
						DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);

					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)) &&
						0 != strcmp(m, MVAR_EVENT_DURATION))
				{
					get_event_value(m, event, &replace_to, userid, NULL, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_METADATA))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "host_metadata");
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "host");
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "listen_ip");
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "listen_port");
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = get_autoreg_value_by_event(c_event, &replace_to,
							"proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = DBget_host_value(proxy_hostid, &replace_to, "host");
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					if (SUCCEED == (ret = get_autoreg_value_by_event(c_event, &replace_to,
							"proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
						{
							replace_to = zbx_strdup(replace_to, "");
						}
						else
						{
							ret = DBget_host_value(proxy_hostid, &replace_to,
									"description");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else
				{
					ret = resolve_host_target_macros(m, dc_host, &interface, &require_address,
							&replace_to);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_INTERNAL == c_event->source &&
					EVENT_OBJECT_ITEM == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_item_hostid(&hostids, c_event->objectid);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid, tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = get_host_inventory_by_itemid(m, c_event->objectid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_STATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_item_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUETYPE))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_VALUETYPE);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else if (0 == strcmp(m, MVAR_ITEM_STATE_ERROR))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_ERROR);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_INTERNAL == c_event->source &&
					EVENT_OBJECT_LLDRULE == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_item_hostid(&hostids, c_event->objectid);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid, tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = get_host_inventory_by_itemid(m, c_event->objectid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_DESCRIPTION_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_KEY))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_KEY_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_NAME_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_STATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_item_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_STATE_ERROR))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_ERROR);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_SERVICE == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (NULL == dc_host)
						DCget_user_macro(NULL, 0, m, &replace_to);
					else
						DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);

					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid, tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_NSEVERITY))
				{
					if (NULL != service_alarm)
						replace_to = zbx_dsprintf(replace_to, "%d", (int)service_alarm->value);
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_SEVERITY))
				{
					if (NULL != service_alarm)
					{
						if (FAIL == get_trigger_severity_name(service_alarm->value,
								&replace_to))
						{
							replace_to = zbx_strdup(replace_to, "unknown");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_DATE))
				{
					if (NULL != service_alarm)
					{
						replace_to = zbx_strdup(replace_to, zbx_date2str(service_alarm->clock,
								tz));
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_TIME))
				{
					if (NULL != service_alarm)
					{
						replace_to = zbx_strdup(replace_to, zbx_time2str(service_alarm->clock,
								tz));
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_NAME))
				{
					replace_to = zbx_strdup(replace_to, service->name);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_DESCRIPTION))
				{
					replace_to = zbx_strdup(replace_to, service->description);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_ROOTCAUSE))
				{
					get_rootcause(service, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_TAGS))
				{
					get_event_tags(event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_TAGSJSON))
				{
					get_event_tags_json(event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_SERVICE_TAGS_PREFIX,
						ZBX_CONST_STRLEN(MVAR_SERVICE_TAGS_PREFIX)))
				{
					get_event_tag_by_name(m + ZBX_CONST_STRLEN(MVAR_SERVICE_TAGS_PREFIX), event,
							&replace_to);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
			}
		}
		else if (0 != (macro_type & (MACRO_TYPE_TRIGGER_DESCRIPTION | MACRO_TYPE_TRIGGER_COMMENTS |
					MACRO_TYPE_EVENT_NAME)))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
						DCget_user_macro(phostids->values, phostids->values_num, m, &replace_to);
					pos = token.loc.r;
				}
				else if (ZBX_TOKEN_REFERENCE == token.type)
				{
					if (SUCCEED != zbx_db_trigger_get_constant(&event->trigger,
							token.data.reference.index, &replace_to))
					{
						/* expansion failed, reference substitution is impossible */
						token_search &= ~ZBX_TOKEN_SEARCH_REFERENCES;
						continue;
					}
				}
				else if (ZBX_TOKEN_EXPRESSION_MACRO == inner_token.type)
				{
					if (0 != (macro_type & MACRO_TYPE_EVENT_NAME))
					{
						zbx_timespec_t	ts;
						char		*errmsg = NULL;

						ts.sec = event->clock;
						ts.ns = event->ns;

						if (SUCCEED != (ret = get_expression_macro_result(event, *data,
								&inner_token.data.expression_macro.expression, &ts,
								&replace_to, &errmsg)))
						{
							*errmsg = tolower(*errmsg);
							zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot evaluate"
									" expression macro: %s", __func__, errmsg);
							zbx_strlcpy(error, errmsg, maxerrlen);
							zbx_free(errmsg);
						}
					}
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = DBitem_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = get_history_log_value(m, &event->trigger, &replace_to,
							N_functionid, event->clock, event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = DBitem_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value);
				}
				else if (0 != (macro_type & MACRO_TYPE_EVENT_NAME))
				{
					if (0 == strcmp(m, MVAR_TIME))
					{
						replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
					}
					else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
					{
						zbx_db_trigger_explain_expression(&event->trigger, &replace_to,
								evaluate_function2, 0);
					}
					else if (1 == indexed_macro && 0 == strcmp(m, MVAR_FUNCTION_VALUE))
					{
						zbx_db_trigger_get_function_value(&event->trigger, N_functionid,
								&replace_to, evaluate_function2, 0);
					}
				}
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_TRIGGER_EXPRESSION))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
					replace_to = zbx_dsprintf(replace_to, "%d", event->value);
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_TRIGGER_URL))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
						DCget_user_macro(phostids->values, phostids->values_num, m, &replace_to);
					pos = token.loc.r;
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = DBitem_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = DBitem_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = get_history_log_value(m, &event->trigger, &replace_to,
							N_functionid, event->clock, event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_ID))
				{
					get_event_value(m, event, &replace_to, userid, NULL, NULL);
				}
			}
		}
		else if (0 == indexed_macro &&
				0 != (macro_type & (MACRO_TYPE_ITEM_KEY | MACRO_TYPE_PARAMS_FIELD |
						MACRO_TYPE_LLD_FILTER | MACRO_TYPE_ALLOWED_HOSTS |
						MACRO_TYPE_SCRIPT_PARAMS_FIELD | MACRO_TYPE_QUERY_FILTER)))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type && 0 == (MACRO_TYPE_QUERY_FILTER & macro_type))
			{
				DCget_user_macro(&dc_item->host.hostid, 1, m, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
			{
				replace_to = zbx_strdup(replace_to, dc_item->host.host);
			}
			else if (0 == strcmp(m, MVAR_HOST_NAME))
			{
				replace_to = zbx_strdup(replace_to, dc_item->host.name);
			}
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.ip_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.dns_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.addr);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
			}
			else if (0 != (macro_type & MACRO_TYPE_SCRIPT_PARAMS_FIELD))
			{
				if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, dc_item->itemid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY))
				{
					replace_to = zbx_strdup(replace_to, dc_item->key);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					replace_to = zbx_strdup(replace_to, dc_item->key_orig);
				}
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_INTERFACE_ADDR))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface,
						dc_host->hostid, INTERFACE_TYPE_AGENT)))
				{
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
				}
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface,
						dc_host->hostid, INTERFACE_TYPE_AGENT)))
				{
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface,
						dc_host->hostid, INTERFACE_TYPE_AGENT)))
				{
					replace_to = zbx_strdup(replace_to, interface.addr);
				}
			}
		}
		else if (0 != (macro_type & (MACRO_TYPE_COMMON | MACRO_TYPE_SNMP_OID)))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				if (NULL != hostid)
					DCget_user_macro(hostid, 1, m, &replace_to);
				else
					DCget_user_macro(NULL, 0, m, &replace_to);

				pos = token.loc.r;
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_SCRIPT))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
				require_address = 1;
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
				require_address = 1;
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.addr);
				require_address = 1;
			}
			else if (NULL != userid)
			{
				if (0 == strcmp(m, MVAR_USER_USERNAME) || 0 == strcmp(m, MVAR_USER_NAME) ||
						0 == strcmp(m, MVAR_USER_SURNAME) ||
						0 == strcmp(m, MVAR_USER_FULLNAME) || 0 == strcmp(m, MVAR_USER_ALIAS))
				{
					resolve_user_macros(*userid, m, &user_username, &user_name, &user_surname,
							&user_names_found, &replace_to);
				}
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_HTTPTEST_FIELD))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.addr);
			}
		}
		else if (0 == indexed_macro && (0 != (macro_type & (MACRO_TYPE_HTTP_RAW | MACRO_TYPE_HTTP_JSON |
				MACRO_TYPE_HTTP_XML))))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
			{
				replace_to = zbx_strdup(replace_to, dc_host->host);
			}
			else if (0 == strcmp(m, MVAR_HOST_NAME))
			{
				replace_to = zbx_strdup(replace_to, dc_host->name);
			}
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.addr);
			}
			else if (0 == strcmp(m, MVAR_ITEM_ID))
			{
				replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, dc_item->itemid);
			}
			else if (0 == strcmp(m, MVAR_ITEM_KEY))
			{
				replace_to = zbx_strdup(replace_to, dc_item->key);
			}
			else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
			{
				replace_to = zbx_strdup(replace_to, dc_item->key_orig);
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_ALERT))
		{
			if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				replace_to = zbx_strdup(replace_to, alert->sendto);
			else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				replace_to = zbx_strdup(replace_to, alert->subject);
			else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				replace_to = zbx_strdup(replace_to, alert->message);
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_JMX_ENDPOINT))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_item->host.hostid, 1, m, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.ip_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.dns_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.addr);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_PORT))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_dsprintf(replace_to, "%u", dc_item->interface.port);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_PORT);
				}
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_TRIGGER_TAG))
		{
			if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
						DCget_user_macro(phostids->values, phostids->values_num, m, &replace_to);
					pos = token.loc.r;
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
				{
					ret = get_host_inventory(m, &event->trigger, &replace_to,
							N_functionid);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_PORT);
				}

				if (EVENT_SOURCE_TRIGGERS == event->source)
				{
					if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
					{
						ret = DBitem_lastvalue(&event->trigger, &replace_to, N_functionid,
								raw_value);
					}
					else if (0 == strcmp(m, MVAR_ITEM_VALUE))
					{
						ret = DBitem_value(&event->trigger, &replace_to, N_functionid,
								event->clock, event->ns, raw_value);
					}
					else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
					{
						ret = get_history_log_value(m, &event->trigger, &replace_to,
								N_functionid, event->clock, event->ns, tz);
					}
					else if (0 == strcmp(m, MVAR_TRIGGER_ID))
					{
						replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
					}
				}
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_ITEM_TAG))
		{
			/* Using dc_item to pass itemid and hostid only, all other fields are not initialized! */

			if (EVENT_SOURCE_TRIGGERS == event->source && 0 == strcmp(m, MVAR_TRIGGER_ID))
			{
				replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
			}
			else if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					DCget_user_macro(&dc_item->host.hostid, 1, m, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
				{
					get_host_inventory_by_hostid(m, dc_item->host.hostid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					get_host_value(dc_item->itemid, &replace_to, ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					get_host_value(dc_item->itemid, &replace_to, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					get_host_value(dc_item->itemid, &replace_to, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
				{
					get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_PORT);
				}
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_REPORT))
		{
			if (0 == strcmp(m, MVAR_TIME))
			{
				replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
			}
		}

		if (0 != (macro_type & MACRO_TYPE_HTTP_JSON) && NULL != replace_to)
			zbx_json_escape(&replace_to);

		if (0 != (macro_type & MACRO_TYPE_QUERY_FILTER) && NULL != replace_to)
		{
			char	*esc;

			esc = zbx_dyn_escape_string(replace_to, "\\");
			zbx_free(replace_to);
			replace_to = esc;
		}

		if (ZBX_TOKEN_FUNC_MACRO == token.type && NULL != replace_to)
		{
			if (SUCCEED != (ret = zbx_calculate_macro_function(*data, &token.data.func_macro, &replace_to)))
				zbx_free(replace_to);
		}

		if (NULL != replace_to)
		{
			if (1 == require_address && NULL != strstr(replace_to, "{$"))
			{
				/* Macros should be already expanded. An unexpanded user macro means either unknown */
				/* macro or macro value validation failure.                                         */
				zbx_snprintf(error, maxerrlen, "Invalid macro '%.*s' value",
						(int)(token.loc.r - token.loc.l + 1), *data + token.loc.l);
				res = FAIL;
			}
		}

		if (FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve macro '%.*s'",
					(int)(token.loc.r - token.loc.l + 1), *data + token.loc.l);
			replace_to = zbx_strdup(replace_to, STR_UNKNOWN_VARIABLE);
		}

		if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_MACRO == token.type && 0 == indexed_macro))
			(*data)[token.loc.r + 1] = c;

		if (NULL != replace_to)
		{
			pos = token.loc.r;

			pos += zbx_replace_mem_dyn(data, &data_alloc, &data_len, token.loc.l,
					token.loc.r - token.loc.l + 1, replace_to, strlen(replace_to));
			zbx_free(replace_to);
		}

		pos++;
	}

	zbx_vc_flush_stats();

	zbx_free(user_username);
	zbx_free(user_name);
	zbx_free(user_surname);
	zbx_free(expression);
	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End %s() data:'%s'", __func__, *data);

	return res;
}

static void	zbx_extract_functionids(zbx_vector_uint64_t *functionids, zbx_vector_ptr_t *triggers)
{
	DC_TRIGGER	*tr;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __func__, triggers->values_num);

	zbx_vector_uint64_reserve(functionids, triggers->values_num);

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		zbx_eval_get_functionids(tr->eval_ctx, functionids);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode)
			zbx_eval_get_functionids(tr->eval_ctx_r, functionids);
	}

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() functionids_num:%d", __func__, functionids->values_num);
}

typedef struct
{
	DC_TRIGGER	*trigger;
	int		start_index;
	int		count;
}
zbx_trigger_func_position_t;

/******************************************************************************
 *                                                                            *
 * Purpose: expand macros in a trigger expression                             *
 *                                                                            *
 * Parameters: event - The trigger event structure                            *
 *             trigger - The trigger where to expand macros in                *
 *                                                                            *
 ******************************************************************************/
static int	expand_trigger_macros(zbx_eval_context_t *ctx, const DB_EVENT *event, char *error, size_t maxerrlen)
{
	int 	i;

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (ZBX_EVAL_TOKEN_VAR_MACRO != token->type && ZBX_EVAL_TOKEN_VAR_STR != token->type)
			continue;

		/* all trigger macros are already extracted into strings */
		if (ZBX_VARIANT_STR != token->value.type)
			continue;

		if (FAIL == substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				NULL, NULL, &token->value.data.str, MACRO_TYPE_TRIGGER_EXPRESSION, error, maxerrlen))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: triggers links with functions                                     *
 *                                                                            *
 * Parameters: triggers_func_pos - [IN/OUT] pointer to the list of triggers   *
 *                                 with functions position in functionids     *
 *                                 array                                      *
 *             functionids       - [IN/OUT] array of function IDs             *
 *             trigger_order     - [IN] array of triggers                     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_link_triggers_with_functions(zbx_vector_ptr_t *triggers_func_pos, zbx_vector_uint64_t *functionids,
		zbx_vector_ptr_t *trigger_order)
{
	zbx_vector_uint64_t	funcids;
	DC_TRIGGER		*tr;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trigger_order_num:%d", __func__, trigger_order->values_num);

	zbx_vector_uint64_create(&funcids);
	zbx_vector_uint64_reserve(&funcids, functionids->values_num);

	for (i = 0; i < trigger_order->values_num; i++)
	{
		zbx_trigger_func_position_t	*tr_func_pos;

		tr = (DC_TRIGGER *)trigger_order->values[i];

		if (NULL != tr->new_error)
			continue;

		zbx_eval_get_functionids(tr->eval_ctx, &funcids);

		tr_func_pos = (zbx_trigger_func_position_t *)zbx_malloc(NULL, sizeof(zbx_trigger_func_position_t));
		tr_func_pos->trigger = tr;
		tr_func_pos->start_index = functionids->values_num;
		tr_func_pos->count = funcids.values_num;

		zbx_vector_uint64_append_array(functionids, funcids.values, funcids.values_num);
		zbx_vector_ptr_append(triggers_func_pos, tr_func_pos);

		zbx_vector_uint64_clear(&funcids);
	}

	zbx_vector_uint64_destroy(&funcids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() triggers_func_pos_num:%d", __func__, triggers_func_pos->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: mark triggers that use one of the items in problem expression     *
 *          with ZBX_DC_TRIGGER_PROBLEM_EXPRESSION flag                       *
 *                                                                            *
 * Parameters: trigger_order - [IN/OUT] pointer to the list of triggers       *
 *             itemids       - [IN] array of item IDs                         *
 *             item_num      - [IN] number of items                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_determine_items_in_expressions(zbx_vector_ptr_t *trigger_order, const zbx_uint64_t *itemids, int item_num)
{
	zbx_vector_ptr_t	triggers_func_pos;
	zbx_vector_uint64_t	functionids, itemids_sorted;
	DC_FUNCTION		*functions = NULL;
	int			*errcodes = NULL, t, f;

	zbx_vector_uint64_create(&itemids_sorted);
	zbx_vector_uint64_append_array(&itemids_sorted, itemids, item_num);
	zbx_vector_uint64_sort(&itemids_sorted, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&triggers_func_pos);
	zbx_vector_ptr_reserve(&triggers_func_pos, trigger_order->values_num);

	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_reserve(&functionids, item_num);

	zbx_link_triggers_with_functions(&triggers_func_pos, &functionids, trigger_order);

	functions = (DC_FUNCTION *)zbx_malloc(functions, sizeof(DC_FUNCTION) * functionids.values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * functionids.values_num);

	DCconfig_get_functions_by_functionids(functions, functionids.values, errcodes, functionids.values_num);

	for (t = 0; t < triggers_func_pos.values_num; t++)
	{
		zbx_trigger_func_position_t	*func_pos = (zbx_trigger_func_position_t *)triggers_func_pos.values[t];

		for (f = func_pos->start_index; f < func_pos->start_index + func_pos->count; f++)
		{
			if (SUCCEED == errcodes[f] && FAIL != zbx_vector_uint64_bsearch(&itemids_sorted,
					functions[f].itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				func_pos->trigger->flags |= ZBX_DC_TRIGGER_PROBLEM_EXPRESSION;
				break;
			}
		}
	}

	DCconfig_clean_functions(functions, errcodes, functionids.values_num);
	zbx_free(errcodes);
	zbx_free(functions);

	zbx_vector_ptr_clear_ext(&triggers_func_pos, zbx_ptr_free);
	zbx_vector_ptr_destroy(&triggers_func_pos);

	zbx_vector_uint64_clear(&functionids);
	zbx_vector_uint64_destroy(&functionids);

	zbx_vector_uint64_clear(&itemids_sorted);
	zbx_vector_uint64_destroy(&itemids_sorted);
}

typedef struct
{
	/* input data */
	zbx_uint64_t	itemid;
	char		*function;
	char		*parameter;
	zbx_timespec_t	timespec;
	unsigned char	type;

	/* output data */
	zbx_variant_t	value;
	char		*error;
}
zbx_func_t;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_func_t	*func;
}
zbx_ifunc_t;

static zbx_hash_t	func_hash_func(const void *data)
{
	const zbx_func_t	*func = (const zbx_func_t *)data;
	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&func->itemid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(func->function, strlen(func->function), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(func->parameter, strlen(func->parameter), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&func->timespec.sec, sizeof(func->timespec.sec), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&func->timespec.ns, sizeof(func->timespec.ns), hash);

	return hash;
}

static int	func_compare_func(const void *d1, const void *d2)
{
	const zbx_func_t	*func1 = (const zbx_func_t *)d1;
	const zbx_func_t	*func2 = (const zbx_func_t *)d2;
	int			ret;

	ZBX_RETURN_IF_NOT_EQUAL(func1->itemid, func2->itemid);

	if (0 != (ret = strcmp(func1->function, func2->function)))
		return ret;

	if (0 != (ret = strcmp(func1->parameter, func2->parameter)))
		return ret;

	ZBX_RETURN_IF_NOT_EQUAL(func1->timespec.sec, func2->timespec.sec);
	ZBX_RETURN_IF_NOT_EQUAL(func1->timespec.ns, func2->timespec.ns);

	return 0;
}

static void	func_clean(void *ptr)
{
	zbx_func_t	*func = (zbx_func_t *)ptr;

	zbx_free(func->function);
	zbx_free(func->parameter);
	zbx_free(func->error);

	zbx_variant_clear(&func->value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare hashset of functions to evaluate                          *
 *                                                                            *
 * Parameters: functionids - [IN] function identifiers                        *
 *             funcs       - [OUT] functions indexed by itemid, name,         *
 *                                 parameter, timestamp                       *
 *             ifuncs      - [OUT] function index by functionid               *
 *             trigger     - [IN] vector of triggers, sorted by triggerid     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_populate_function_items(const zbx_vector_uint64_t *functionids, zbx_hashset_t *funcs,
		zbx_hashset_t *ifuncs, const zbx_vector_ptr_t *triggers)
{
	int		i, j;
	DC_TRIGGER	*tr;
	DC_FUNCTION	*functions = NULL;
	int		*errcodes = NULL;
	zbx_ifunc_t	ifunc_local;
	zbx_func_t	*func, func_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() functionids_num:%d", __func__, functionids->values_num);

	zbx_variant_set_none(&func_local.value);
	func_local.error = NULL;

	functions = (DC_FUNCTION *)zbx_malloc(functions, sizeof(DC_FUNCTION) * functionids->values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * functionids->values_num);

	DCconfig_get_functions_by_functionids(functions, functionids->values, errcodes, functionids->values_num);

	for (i = 0; i < functionids->values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		func_local.itemid = functions[i].itemid;

		if (FAIL != (j = zbx_vector_ptr_bsearch(triggers, &functions[i].triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			tr = (DC_TRIGGER *)triggers->values[j];
			func_local.timespec = tr->timespec;
		}
		else
		{
			func_local.timespec.sec = 0;
			func_local.timespec.ns = 0;
		}

		func_local.function = functions[i].function;
		func_local.parameter = functions[i].parameter;

		if (NULL == (func = (zbx_func_t *)zbx_hashset_search(funcs, &func_local)))
		{
			func = (zbx_func_t *)zbx_hashset_insert(funcs, &func_local, sizeof(func_local));
			func->function = zbx_strdup(NULL, func_local.function);
			func->parameter = zbx_strdup(NULL, func_local.parameter);
			func->type = functions[i].type;
			zbx_variant_set_none(&func->value);
		}

		ifunc_local.functionid = functions[i].functionid;
		ifunc_local.func = func;
		zbx_hashset_insert(ifuncs, &ifunc_local, sizeof(ifunc_local));
	}

	DCconfig_clean_functions(functions, errcodes, functionids->values_num);

	zbx_free(errcodes);
	zbx_free(functions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ifuncs_num:%d", __func__, ifuncs->num_data);
}

static void	zbx_evaluate_item_functions(zbx_hashset_t *funcs, const zbx_vector_uint64_t *history_itemids,
		const DC_ITEM *history_items, const int *history_errcodes)
{
	DC_ITEM			*items = NULL;
	char			*error = NULL;
	int			i;
	zbx_func_t		*func;
	zbx_vector_uint64_t	itemids;
	int			*errcodes = NULL;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() funcs_num:%d", __func__, funcs->num_data);

	zbx_vector_uint64_create(&itemids);

	zbx_hashset_iter_reset(funcs, &iter);
	while (NULL != (func = (zbx_func_t *)zbx_hashset_iter_next(&iter)))
	{
		if (FAIL == zbx_vector_uint64_bsearch(history_itemids, func->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			zbx_vector_uint64_append(&itemids, func->itemid);
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		items = (DC_ITEM *)zbx_malloc(items, sizeof(DC_ITEM) * (size_t)itemids.values_num);
		errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * (size_t)itemids.values_num);

		DCconfig_get_items_by_itemids_partial(items, itemids.values, errcodes, itemids.values_num,
				ZBX_ITEM_GET_SYNC);
	}

	zbx_hashset_iter_reset(funcs, &iter);
	while (NULL != (func = (zbx_func_t *)zbx_hashset_iter_next(&iter)))
	{
		int		errcode;
		const DC_ITEM	*item;

		/* avoid double copying from configuration cache if already retrieved when saving history */
		if (FAIL != (i = zbx_vector_uint64_bsearch(history_itemids, func->itemid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			item = history_items + i;
			errcode = history_errcodes[i];
		}
		else
		{
			i = zbx_vector_uint64_bsearch(&itemids, func->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			item = items + i;
			errcode = errcodes[i];
		}

		if (SUCCEED != errcode)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, NULL, NULL, func->parameter,
					"item does not exist");
			continue;
		}

		/* do not evaluate if the item is disabled or belongs to a disabled host */

		if (ITEM_STATUS_ACTIVE != item->status)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host,
					item->key_orig, func->parameter, "item is disabled");
			continue;
		}

		if ((ZBX_FUNCTION_TYPE_HISTORY == func->type || ZBX_FUNCTION_TYPE_TIMER == func->type) &&
				0 == item->history)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host,
					item->key_orig, func->parameter, "item history is disabled");
			continue;
		}

		if (ZBX_FUNCTION_TYPE_TRENDS == func->type && 0 == item->trends)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host,
					item->key_orig, func->parameter, "item trends are disabled");
			continue;
		}

		if (HOST_STATUS_MONITORED != item->host.status)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host,
					item->key_orig, func->parameter, "item belongs to a disabled host");
			continue;
		}

		if (ITEM_STATE_NOTSUPPORTED == item->state &&
				FAIL == zbx_evaluatable_for_notsupported(func->function))
		{
			/* set 'unknown' error value */
			zbx_variant_set_error(&func->value,
					zbx_eval_format_function_error(func->function, item->host.host,
							item->key_orig, func->parameter, "item is not supported"));
			continue;
		}

		if (SUCCEED != evaluate_function2(&func->value, (DC_ITEM *)item, func->function, func->parameter,
				&func->timespec, &error))
		{
			/* compose and store error message for future use */
			zbx_variant_set_error(&func->value,
					zbx_eval_format_function_error(func->function, item->host.host,
							item->key_orig, func->parameter, error));
			zbx_free(error);
			continue;
		}
	}

	zbx_vc_flush_stats();

	DCconfig_clean_items(items, errcodes, itemids.values_num);
	zbx_vector_uint64_destroy(&itemids);

	zbx_free(errcodes);
	zbx_free(items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	substitute_expression_functions_results(zbx_hashset_t *ifuncs, zbx_eval_context_t *ctx, char **error)
{
	zbx_uint64_t		functionid;
	zbx_func_t		*func;
	zbx_ifunc_t		*ifunc;
	int			i;

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (ZBX_VARIANT_UI64 != token->value.type)
		{
			/* functionids should be already extracted into uint64 vars */
			THIS_SHOULD_NEVER_HAPPEN;
			*error = zbx_dsprintf(*error, "Cannot parse function at: \"%s\"",
					ctx->expression + token->loc.l);
			return FAIL;
		}

		functionid = token->value.data.ui64;
		if (NULL == (ifunc = (zbx_ifunc_t *)zbx_hashset_search(ifuncs, &functionid)))
		{
			*error = zbx_dsprintf(*error, "Cannot obtain function"
					" and item for functionid: " ZBX_FS_UI64, functionid);
			return FAIL;
		}

		func = ifunc->func;

		if (NULL != func->error)
		{
			*error = zbx_strdup(*error, func->error);
			return FAIL;
		}

		if (ZBX_VARIANT_NONE == func->value.type)
		{
			*error = zbx_strdup(*error, "Unexpected error while processing a trigger expression");
			return FAIL;
		}

		zbx_variant_copy(&token->value, &func->value);
	}

	return SUCCEED;
}

static void	log_expression(const char *prefix, int index, const zbx_eval_context_t *ctx)
{
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	*expression = NULL;

		zbx_eval_compose_expression(ctx, &expression);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expression[%d]:'%s' => '%s'", prefix, index, ctx->expression,
				expression);
		zbx_free(expression);
	}
}

static void	zbx_substitute_functions_results(zbx_hashset_t *ifuncs, zbx_vector_ptr_t *triggers)
{
	DC_TRIGGER	*tr;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ifuncs_num:%d tr_num:%d",
			__func__, ifuncs->num_data, triggers->values_num);

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		if( SUCCEED != substitute_expression_functions_results(ifuncs, tr->eval_ctx, &tr->new_error))
		{
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
			continue;
		}

		log_expression(__func__, i, tr->eval_ctx);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode)
		{
			if (SUCCEED != substitute_expression_functions_results(ifuncs, tr->eval_ctx_r, &tr->new_error))
			{
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				continue;
			}

			log_expression(__func__, i, tr->eval_ctx_r);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute expression functions with their values                 *
 *                                                                            *
 * Parameters: triggers - [IN] vector of DC_TRIGGER pointers, sorted by      *
 *                             triggerids                                     *
 *             unknown_msgs - vector for storing messages for NOTSUPPORTED    *
 *                            items and failed functions                      *
 *                                                                            *
 * Comments: example: "({15}>10) or ({123}=1)" => "(26.416>10) or (0=1)"      *
 *                                                                            *
 ******************************************************************************/
static void	substitute_functions(zbx_vector_ptr_t *triggers, const zbx_vector_uint64_t *history_itemids,
		const DC_ITEM *history_items, const int *history_errcodes)
{
	zbx_vector_uint64_t	functionids;
	zbx_hashset_t		ifuncs, funcs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&functionids);
	zbx_extract_functionids(&functionids, triggers);

	if (0 == functionids.values_num)
		goto empty;

	zbx_hashset_create(&ifuncs, triggers->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create_ext(&funcs, triggers->values_num, func_hash_func, func_compare_func, func_clean,
				ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_populate_function_items(&functionids, &funcs, &ifuncs, triggers);

	if (0 != ifuncs.num_data)
	{
		zbx_evaluate_item_functions(&funcs, history_itemids, history_items, history_errcodes);
		zbx_substitute_functions_results(&ifuncs, triggers);
	}

	zbx_hashset_destroy(&ifuncs);
	zbx_hashset_destroy(&funcs);
empty:
	zbx_vector_uint64_destroy(&functionids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare triggers for evaluation                                   *
 *                                                                            *
 * Parameters: triggers     - [IN] array of DC_TRIGGER pointers               *
 *             triggres_num - [IN] the number of triggers to prepare          *
 *                                                                            *
 ******************************************************************************/
void	prepare_triggers(DC_TRIGGER **triggers, int triggers_num)
{
	int	i;

	for (i = 0; i < triggers_num; i++)
	{
		DC_TRIGGER	*tr = triggers[i];

		tr->eval_ctx = zbx_eval_deserialize_dyn(tr->expression_bin, tr->expression, ZBX_EVAL_EXTRACT_ALL);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode)
		{
			tr->eval_ctx_r = zbx_eval_deserialize_dyn(tr->recovery_expression_bin, tr->recovery_expression,
					ZBX_EVAL_EXTRACT_ALL);
		}
	}
}

static int	evaluate_expression(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, double *result,
		char **error)
{
	zbx_variant_t	 value;

	if (SUCCEED != zbx_eval_execute(ctx, ts, &value, error))
		return FAIL;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	*expression = NULL;

		zbx_eval_compose_expression(ctx, &expression);
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): %s => %s", __func__, expression, zbx_variant_value_desc(&value));
		zbx_free(expression);
	}

	if (SUCCEED != zbx_variant_convert(&value, ZBX_VARIANT_DBL))
	{
		*error = zbx_dsprintf(*error, "Cannot convert expression result of type \"%s\" to"
				" floating point value", zbx_variant_type_desc(&value));
		zbx_variant_clear(&value);

		return FAIL;
	}

	*result = value.data.dbl;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate trigger expressions                                      *
 *                                                                            *
 * Parameters: triggers - [IN] vector of DC_TRIGGER pointers, sorted by       *
 *                             triggerids                                     *
 *                                                                            *
 ******************************************************************************/
void	evaluate_expressions(zbx_vector_ptr_t *triggers, const zbx_vector_uint64_t *history_itemids,
		const DC_ITEM *history_items, const int *history_errcodes)
{
	DB_EVENT		event;
	DC_TRIGGER		*tr;
	int			i;
	double			expr_result;
	char			err[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __func__, triggers->values_num);

	event.object = EVENT_OBJECT_TRIGGER;

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		event.value = tr->value;

		if (SUCCEED != expand_trigger_macros(tr->eval_ctx, &event, err, sizeof(err)))
		{
			tr->new_error = zbx_dsprintf(tr->new_error, "Cannot evaluate expression: %s", err);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
		}

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode &&
				SUCCEED != expand_trigger_macros(tr->eval_ctx_r, &event, err, sizeof(err)))
		{
			tr->new_error = zbx_dsprintf(tr->new_error, "Cannot evaluate expression: %s", err);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
		}
	}

	substitute_functions(triggers, history_itemids, history_items, history_errcodes);

	/* calculate new trigger values based on their recovery modes and expression evaluations */
	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		if (SUCCEED != evaluate_expression(tr->eval_ctx, &tr->timespec, &expr_result, &tr->new_error))
			continue;

		/* trigger expression evaluates to true, set PROBLEM value */
		if (SUCCEED != zbx_double_compare(expr_result, 0.0))
		{
			if (0 == (tr->flags & ZBX_DC_TRIGGER_PROBLEM_EXPRESSION))
			{
				/* trigger value should remain unchanged and no PROBLEM events should be generated if */
				/* problem expression evaluates to true, but trigger recalculation was initiated by a */
				/* time-based function or a new value of an item in recovery expression */
				tr->new_value = TRIGGER_VALUE_NONE;
			}
			else
				tr->new_value = TRIGGER_VALUE_PROBLEM;

			continue;
		}

		/* otherwise try to recover trigger by setting OK value */
		if (TRIGGER_VALUE_PROBLEM == tr->value && TRIGGER_RECOVERY_MODE_NONE != tr->recovery_mode)
		{
			if (TRIGGER_RECOVERY_MODE_EXPRESSION == tr->recovery_mode)
			{
				tr->new_value = TRIGGER_VALUE_OK;
				continue;
			}

			/* processing recovery expression mode */
			if (SUCCEED != evaluate_expression(tr->eval_ctx_r, &tr->timespec, &expr_result, &tr->new_error))
			{
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				continue;
			}

			if (SUCCEED != zbx_double_compare(expr_result, 0.0))
			{
				tr->new_value = TRIGGER_VALUE_OK;
				continue;
			}
		}

		/* no changes, keep the old value */
		tr->new_value = TRIGGER_VALUE_NONE;
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		for (i = 0; i < triggers->values_num; i++)
		{
			tr = (DC_TRIGGER *)triggers->values[i];

			if (NULL != tr->new_error)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s():expression [%s] cannot be evaluated: %s",
						__func__, tr->expression, tr->new_error);
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: expand discovery macro in expression                              *
 *                                                                            *
 * Parameters: data      - [IN/OUT] the expression containing lld macro       *
 *             token     - [IN/OUT] the token with lld macro location data    *
 *             flags     - [IN] the flags passed to                           *
 *                                  subtitute_discovery_macros() function     *
 *             jp_row    - [IN] discovery data                                *
 * cur_token_inside_quote - [IN] used in autoquoting for trigger prototypes   *
 *                                                                            *
 ******************************************************************************/
static void	process_lld_macro_token(char **data, zbx_token_t *token, int flags, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, int cur_token_inside_quote)
{
	char	c, *replace_to = NULL;
	int	l ,r;

	if (ZBX_TOKEN_LLD_FUNC_MACRO == token->type)
	{
		l = token->data.lld_func_macro.macro.l;
		r = token->data.lld_func_macro.macro.r;
	}
	else
	{
		l = token->loc.l;
		r = token->loc.r;
	}

	c = (*data)[r + 1];
	(*data)[r + 1] = '\0';

	if (SUCCEED != zbx_lld_macro_value_by_name(jp_row, lld_macro_paths, *data + l, &replace_to))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot substitute macro \"%s\": not found in value set", *data + l);

		(*data)[r + 1] = c;
		zbx_free(replace_to);

		return;
	}

	(*data)[r + 1] = c;

	if (ZBX_TOKEN_LLD_FUNC_MACRO == token->type)
	{
		if (SUCCEED != (zbx_calculate_macro_function(*data, &token->data.lld_func_macro, &replace_to)))
		{
			int	len = token->data.lld_func_macro.func.r - token->data.lld_func_macro.func.l + 1;

			zabbix_log(LOG_LEVEL_DEBUG, "cannot execute function \"%.*s\"", len,
					*data + token->data.lld_func_macro.func.l);

			zbx_free(replace_to);

			return;
		}
	}

	if (0 != (flags & ZBX_TOKEN_JSON))
	{
		zbx_json_escape(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_REGEXP))
	{
		zbx_regexp_escape(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_REGEXP_OUTPUT))
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_dyn_escape_string(replace_to, "\\");
		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}
	else if (0 != (flags & ZBX_TOKEN_XPATH))
	{
		xml_escape_xpath(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_PROMETHEUS))
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_dyn_escape_string(replace_to, "\\\n\"");
		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}
	else if (0 != (flags & ZBX_TOKEN_JSONPATH) && ZBX_TOKEN_LLD_MACRO == token->type)
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_dyn_escape_string(replace_to, "\\\"");
		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}
	else if (0 != (flags & ZBX_TOKEN_STRING))
	{
		if (1 == cur_token_inside_quote)
		{
			char	*replace_to_esc;

			replace_to_esc = zbx_dyn_escape_string(replace_to, "\\\"");
			zbx_free(replace_to);
			replace_to = replace_to_esc;
		}
	}
	else if (0 != (flags & ZBX_TOKEN_STR_REPLACE))
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_str_printable_dyn(replace_to);

		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}

	if (NULL != replace_to)
	{
		size_t	data_alloc, data_len;

		data_alloc = data_len = strlen(*data) + 1;
		token->loc.r += zbx_replace_mem_dyn(data, &data_alloc, &data_len, token->loc.l,
				token->loc.r - token->loc.l + 1, replace_to, strlen(replace_to));
		zbx_free(replace_to);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: expand discovery macro in user macro context                      *
 *                                                                            *
 * Parameters: data          - [IN/OUT] the expression containing lld macro   *
 *             token         - [IN/OUT] the token with user macro location    *
 *                                      data                                  *
 *             jp_row        - [IN] discovery data                            *
 *             error         - [OUT]error buffer                              *
 *             max_error_len - [IN] the size of error buffer                  *
 *                                                                            *
 ******************************************************************************/
static int	process_user_macro_token(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths,  char *error, size_t max_error_len)
{
	int			force_quote, ret;
	size_t			context_r;
	char			*context, *context_esc, *errmsg = NULL;
	zbx_token_user_macro_t	*macro = &token->data.user_macro;

	/* user macro without context, nothing to replace */
	if (0 == token->data.user_macro.context.l)
		return SUCCEED;

	force_quote = ('"' == (*data)[macro->context.l]);
	context = zbx_user_macro_unquote_context_dyn(*data + macro->context.l, macro->context.r - macro->context.l + 1);

	/* substitute_lld_macros() can't fail with ZBX_TOKEN_LLD_MACRO or ZBX_TOKEN_LLD_FUNC_MACRO flags set */
	substitute_lld_macros(&context, jp_row, lld_macro_paths, ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO, NULL,
			0);

	if (NULL != (context_esc = zbx_user_macro_quote_context_dyn(context, force_quote, &errmsg)))
	{
		context_r = macro->context.r;
		zbx_replace_string(data, macro->context.l, &context_r, context_esc);

		token->loc.r += context_r - macro->context.r;

		zbx_free(context_esc);
		ret = SUCCEED;
	}
	else
	{
		zbx_strlcpy(error, errmsg, max_error_len);
		zbx_free(errmsg);
		ret = FAIL;
	}

	zbx_free(context);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute lld macros in calculated item query filter             *
 *                                                                            *
 * Parameters: filter          - [IN/OUT] the filter                          *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             error           - [OUT] the error message                      *
 *                                                                            *
 *  Return value: SUCCEED - the macros were expanded successfully.            *
 *                FAIL    - otherwise.                                        *
 *                                                                            *
 ******************************************************************************/
static int	substitute_query_filter_lld_macros(char **filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **error)
{
	char			*errmsg = NULL, err[128], *new_filter = NULL;
	int			i, ret = FAIL;
	zbx_eval_context_t	ctx;

	if (SUCCEED != zbx_eval_parse_expression(&ctx, *filter,
			ZBX_EVAL_PARSE_QUERY_EXPRESSION | ZBX_EVAL_COMPOSE_QUOTE | ZBX_EVAL_PARSE_LLDMACRO, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot parse item query filter: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	for (i = 0; i < ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx.stack.values[i];
		char			*value;

		switch (token->type)
		{
			case ZBX_EVAL_TOKEN_VAR_LLDMACRO:
			case ZBX_EVAL_TOKEN_VAR_USERMACRO:
			case ZBX_EVAL_TOKEN_VAR_STR:
				value = zbx_substr_unquote(ctx.expression, token->loc.l, token->loc.r);

				if (FAIL == substitute_lld_macros(&value, jp_row, lld_macro_paths, ZBX_MACRO_ANY, err,
						sizeof(err)))
				{
					*error = zbx_strdup(NULL, err);
					zbx_free(value);

					goto clean;
				}
				break;
			default:
				continue;
		}

		zbx_variant_set_str(&token->value, value);
	}

	zbx_eval_compose_expression(&ctx, &new_filter);
	zbx_free(*filter);
	*filter = new_filter;

	ret = SUCCEED;
clean:
	zbx_eval_clear(&ctx);
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute lld macros in history function item query argument     *
 *          /host/key?[filter]                                                *
 *                                                                            *
 * Parameters: ctx             - [IN] the calculated item formula             *
 *             token           - [IN] the item query token                    *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             itemquery       - [OUT] the item query with expanded macros    *
 *             error           - [OUT] the error message                      *
 *                                                                            *
 *  Return value: SUCCEED - the macros were expanded successfully.            *
 *                FAIL    - otherwise.                                        *
 *                                                                            *
 ******************************************************************************/
static int	substitute_item_query_lld_macros(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, char **itemquery,
		char **error)
{
	zbx_item_query_t	query;
	char			err[128];
	int			ret = FAIL;
	size_t			itemquery_alloc = 0, itemquery_offset = 0;

	if (0 == zbx_eval_parse_query(ctx->expression + token->loc.l, token->loc.r - token->loc.l + 1, &query))
	{
		*error = zbx_strdup(NULL, "invalid item reference");
		return FAIL;
	}

	if (SUCCEED != substitute_key_macros(&query.key, NULL, NULL, jp_row, lld_macro_paths, MACRO_TYPE_ITEM_KEY,
			err, sizeof(err)))
	{
		*error = zbx_strdup(NULL, err);
		goto out;
	}

	if (NULL != query.filter && SUCCEED != substitute_query_filter_lld_macros(&query.filter, jp_row,
			lld_macro_paths, error))
	{
		goto out;
	}

	zbx_snprintf_alloc(itemquery, &itemquery_alloc, &itemquery_offset, "/%s/%s", ZBX_NULL2EMPTY_STR(query.host),
			query.key);
	if (NULL != query.filter)
		zbx_snprintf_alloc(itemquery, &itemquery_alloc, &itemquery_offset, "?[%s]", query.filter);

	ret = SUCCEED;
out:
	zbx_eval_clear_query(&query);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes lld macros in an expression                           *
 *                                                                            *
 * Parameters: data            - [IN/OUT] the expression                      *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             error           - [IN] pointer to string for reporting errors  *
 *             max_error_len   - [IN] size of 'error' string                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_expression_lld_macros(char **data, zbx_uint64_t rules, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **error)
{
	char			*exp = NULL;
	int			i, ret = FAIL;
	zbx_eval_context_t	ctx;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:%s", __func__, *data);

	if (SUCCEED != zbx_eval_parse_expression(&ctx, *data, rules, error))
		goto out;

	for (i = 0; i < ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx.stack.values[i];
		char			*value = NULL, err[128];

		switch(token->type)
		{
			case ZBX_EVAL_TOKEN_ARG_QUERY:
				if (FAIL == substitute_item_query_lld_macros(&ctx, token, jp_row, lld_macro_paths,
						&value, error))
				{
					goto clean;
				}
				break;
			case ZBX_EVAL_TOKEN_VAR_LLDMACRO:
			case ZBX_EVAL_TOKEN_VAR_USERMACRO:
			case ZBX_EVAL_TOKEN_VAR_STR:
			case ZBX_EVAL_TOKEN_VAR_NUM:
			case ZBX_EVAL_TOKEN_ARG_PERIOD:
				value = zbx_substr_unquote(ctx.expression, token->loc.l, token->loc.r);

				if (FAIL == substitute_lld_macros(&value, jp_row, lld_macro_paths, ZBX_MACRO_ANY, err,
						sizeof(err)))
				{
					*error = zbx_strdup(NULL, err);
					zbx_free(value);
					goto clean;
				}
				break;
			default:
				continue;
		}

		zbx_variant_clear(&token->value);
		zbx_variant_set_str(&token->value, value);
	}

	zbx_eval_compose_expression(&ctx, &exp);

	zbx_free(*data);
	*data = exp;
	exp = NULL;

	ret = SUCCEED;
clean:
	zbx_free(exp);
	zbx_eval_clear(&ctx);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:%s", __func__, *data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: expand discovery macro in expression macro                        *
 *                                                                            *
 * Parameters: data            - [IN/OUT] the expression containing macro     *
 *             token           - [IN/OUT] the macro token                     *
 *             jp_row          - [IN] discovery data                          *
 *             lld_macro_paths - [IN] discovery data                          *
 *             error           - [OUT] error message                          *
 *             max_error_len   - [IN] the size of error buffer                *
 *                                                                            *
 ******************************************************************************/
static int	process_expression_macro_token(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, size_t error_len)
{
	char	*errmsg = NULL, *expression;
	size_t	right = token->data.expression_macro.expression.r;

	expression = zbx_substr(*data, token->data.expression_macro.expression.l,
			token->data.expression_macro.expression.r);

	if (FAIL == zbx_substitute_expression_lld_macros(&expression, ZBX_EVAL_EXPRESSION_MACRO_LLD, jp_row,
			lld_macro_paths, &errmsg))
	{
		zbx_free(expression);
		zbx_strlcpy(error, errmsg, error_len);
		zbx_free(errmsg);

		return FAIL;
	}

	zbx_replace_string(data, token->data.expression_macro.expression.l, &right, expression);
	token->loc.r += right - token->data.expression_macro.expression.r;
	zbx_free(expression);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute lld macros in function macro parameters                *
 *                                                                            *
 * Parameters: data   - [IN/OUT] pointer to a buffer                          *
 *             token  - [IN/OUT] the token with function macro location data  *
 *             jp_row - [IN] discovery data                                   *
 *             error  - [OUT] error message                                   *
 *             max_error_len - [IN] the size of error buffer                  *
 *                                                                            *
 * Return value: SUCCEED - the lld macros were resolved successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	substitute_func_macro(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, size_t max_error_len)
{
	int		ret, offset = 0;
	char		*exp = NULL;
	size_t		exp_alloc = 0, exp_offset = 0, right;
	size_t		par_l = token->data.func_macro.func_param.l, par_r = token->data.func_macro.func_param.r;
	zbx_token_t	tok;

	if (SUCCEED == zbx_token_find(*data, (int)token->data.func_macro.macro.l, &tok,
			ZBX_TOKEN_SEARCH_EXPRESSION_MACRO) && ZBX_TOKEN_EXPRESSION_MACRO == tok.type &&
			tok.loc.r <= token->data.func_macro.macro.r)
	{
		offset = (int)tok.loc.r;

		if (SUCCEED == process_expression_macro_token(data, &tok, jp_row, lld_macro_paths, error,
				max_error_len))
		{
			offset = tok.loc.r - offset;
		}
	}

	ret = substitute_function_lld_param(*data + par_l + offset + 1, par_r - (par_l + 1), 0, &exp, &exp_alloc,
			&exp_offset, jp_row, lld_macro_paths, error, max_error_len);

	if (SUCCEED == ret)
	{
		right = par_r + offset - 1;
		zbx_replace_string(data, par_l + offset + 1, &right, exp);
		token->loc.r = right + 1;
	}

	zbx_free(exp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Parameters: data   - [IN/OUT] pointer to a buffer                          *
 *             jp_row - [IN] discovery data                                   *
 *             flags  - [IN] ZBX_MACRO_ANY - all LLD macros will be resolved  *
 *                            without validation of the value type            *
 *                           ZBX_MACRO_NUMERIC - values for LLD macros should *
 *                            be numeric                                      *
 *                           ZBX_MACRO_FUNC - function macros will be         *
 *                            skipped (lld macros inside function macros will *
 *                            be ignored) for macros specified in func_macros *
 *                            array                                           *
 *             error  - [OUT] should be not NULL if ZBX_MACRO_NUMERIC flag is *
 *                            set                                             *
 *             max_error_len - [IN] the size of error buffer                  *
 *                                                                            *
 * Return value: Always SUCCEED if numeric flag is not set, otherwise SUCCEED *
 *               if all discovery macros resolved to numeric values,          *
 *               otherwise FAIL with an error message.                        *
 *                                                                            *
 ******************************************************************************/
int	substitute_lld_macros(char **data, const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths,
		int flags, char *error, size_t max_error_len)
{
	int		ret = SUCCEED, pos = 0, prev_token_loc_r = -1, cur_token_inside_quote = 0;
	size_t		i;
	zbx_token_t	token;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, *data);

	while (SUCCEED == ret && SUCCEED == zbx_token_find(*data, pos, &token, ZBX_TOKEN_SEARCH_EXPRESSION_MACRO))
	{
		for (i = prev_token_loc_r + 1; i < token.loc.l; i++)
		{
			switch ((*data)[i])
			{
				case '\\':
					if (0 != cur_token_inside_quote)
						i++;
					break;
				case '"':
					cur_token_inside_quote = !cur_token_inside_quote;
					break;
			}
		}

		if (0 != (token.type & flags))
		{
			switch (token.type)
			{
				case ZBX_TOKEN_LLD_MACRO:
				case ZBX_TOKEN_LLD_FUNC_MACRO:
					process_lld_macro_token(data, &token, flags, jp_row, lld_macro_paths,
							cur_token_inside_quote);
					pos = token.loc.r;
					break;
				case ZBX_TOKEN_USER_MACRO:
					ret = process_user_macro_token(data, &token, jp_row, lld_macro_paths, error,
							max_error_len);
					pos = token.loc.r;
					break;
				case ZBX_TOKEN_FUNC_MACRO:
					if (NULL != func_macro_in_list(*data, &token.data.func_macro, NULL))
					{
						ret = substitute_func_macro(data, &token, jp_row, lld_macro_paths,
								error, max_error_len);
						pos = token.loc.r;
					}
					break;
				case ZBX_TOKEN_EXPRESSION_MACRO:
					if (SUCCEED == process_expression_macro_token(data, &token, jp_row,
							lld_macro_paths, error, max_error_len))
					{
						pos = token.loc.r;
					}
					break;
			}
		}
		prev_token_loc_r = token.loc.r;
		pos++;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s data:'%s'", __func__, zbx_result_string(ret), *data);

	return ret;
}

typedef struct
{
	zbx_uint64_t			*hostid;
	DC_ITEM				*dc_item;
	const struct zbx_json_parse	*jp_row;
	const zbx_vector_ptr_t		*lld_macro_paths;
	int				macro_type;
}
replace_key_param_data_t;

/******************************************************************************
 *                                                                            *
 * Comments: auxiliary function for substitute_key_macros()                   *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param_cb(const char *data, int key_type, int level, int num, int quoted, void *cb_data,
			char **param)
{
	replace_key_param_data_t	*replace_key_param_data = (replace_key_param_data_t *)cb_data;
	zbx_uint64_t			*hostid = replace_key_param_data->hostid;
	DC_ITEM				*dc_item = replace_key_param_data->dc_item;
	const struct zbx_json_parse	*jp_row = replace_key_param_data->jp_row;
	const zbx_vector_ptr_t		*lld_macros = replace_key_param_data->lld_macro_paths;
	int				macro_type = replace_key_param_data->macro_type, ret = SUCCEED;

	ZBX_UNUSED(num);

	if (ZBX_KEY_TYPE_ITEM == key_type && 0 == level)
		return ret;

	if (NULL == strchr(data, '{'))
		return ret;

	*param = zbx_strdup(NULL, data);

	if (0 != level)
		unquote_key_param(*param);

	if (NULL == jp_row)
		substitute_simple_macros_impl(NULL, NULL, NULL, NULL, hostid, NULL, dc_item, NULL, NULL, NULL, NULL,
				NULL, param, macro_type, NULL, 0);
	else
		substitute_lld_macros(param, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);

	if (0 != level)
	{
		if (FAIL == (ret = quote_key_param(param, quoted)))
			zbx_free(*param);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: safely substitutes macros in parameters of an item key and OID    *
 *                                                                            *
 * Example:  key                     | macro  | result            | return    *
 *          -------------------------+--------+-------------------+---------  *
 *           echo.sh[{$MACRO}]       | a      | echo.sh[a]        | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a\     | echo.sh[a\]       | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a      | echo.sh["a"]      | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a\     | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       |  a     | echo.sh[" a"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       |  a\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     |  a     | echo.sh[" a"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     |  a\    | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | "a"    | echo.sh["\"a\""]  | SUCCEED   *
 *           echo.sh[{$MACRO}]       | "a"\   | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | "a"    | echo.sh["\"a\""]  | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | "a"\   | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | a,b    | echo.sh["a,b"]    | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a,b\   | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | a,b    | echo.sh["a,b"]    | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a,b\   | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | a]     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a]\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | a]     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a]\    | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | [a     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       | [a\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | [a     | echo.sh["[a"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | [a\    | undefined         | FAIL      *
 *           ifInOctets.{#SNMPINDEX} | 1      | ifInOctets.1      | SUCCEED   *
 *                                                                            *
 ******************************************************************************/
static int	substitute_key_macros_impl(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen)
{
	replace_key_param_data_t	replace_key_param_data;
	int				key_type, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, *data);

	replace_key_param_data.hostid = hostid;
	replace_key_param_data.dc_item = dc_item;
	replace_key_param_data.jp_row = jp_row;
	replace_key_param_data.lld_macro_paths = lld_macro_paths;
	replace_key_param_data.macro_type = macro_type;

	switch (macro_type)
	{
		case MACRO_TYPE_ITEM_KEY:
			key_type = ZBX_KEY_TYPE_ITEM;
			break;
		case MACRO_TYPE_SNMP_OID:
			key_type = ZBX_KEY_TYPE_OID;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	ret = replace_key_params_dyn(data, key_type, replace_key_param_cb, &replace_key_param_data, error, maxerrlen);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s data:'%s'", __func__, zbx_result_string(ret), *data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute lld macros in function parameters                      *
 *                                                                            *
 * Parameters: e            - [IN] the function parameter list without        *
 *                                 enclosing parentheses:                     *
 *                                       <p1>, <p2>, ...<pN>                  *
 *             len          - [IN] the length of function parameter list      *
 *             key_in_param - [IN] 1 - the first parameter must be host:key   *
 *                                 0 - otherwise                              *
 *             exp          - [IN/OUT] output buffer                          *
 *             exp_alloc    - [IN/OUT] the size of output buffer              *
 *             exp_offset   - [IN/OUT] the current position in output buffer  *
 *             jp_row - [IN] discovery data                                   *
 *             error  - [OUT] error message                                   *
 *             max_error_len - [IN] the size of error buffer                  *
 *                                                                            *
 * Return value: SUCCEED - the lld macros were resolved successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, size_t max_error_len)
{
	int		ret = SUCCEED;
	size_t		sep_pos;
	char		*param = NULL;
	const char	*p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == len)
	{
		zbx_strcpy_alloc(exp, exp_alloc, exp_offset, "");
		goto out;
	}

	for (p = e; p < len + e ; p += sep_pos + 1)
	{
		size_t	param_pos, param_len, rel_len = len - (p - e);
		int	quoted;

		zbx_function_param_parse(p, &param_pos, &param_len, &sep_pos);

		/* copy what was before the parameter */
		zbx_strncpy_alloc(exp, exp_alloc, exp_offset, p, param_pos);

		/* prepare the parameter (macro substitutions and quoting) */

		zbx_free(param);
		param = zbx_function_param_unquote_dyn(p + param_pos, param_len, &quoted);

		if (1 == key_in_param && p == e)
		{
			char	*key = NULL, *host = NULL;

			if (SUCCEED != parse_host_key(param, &host, &key) ||
					SUCCEED != substitute_key_macros_impl(&key, NULL, NULL, jp_row, lld_macro_paths,
							MACRO_TYPE_ITEM_KEY, NULL, 0))
			{
				zbx_snprintf(error, max_error_len, "Invalid first parameter \"%s\"", param);
				zbx_free(host);
				zbx_free(key);
				ret = FAIL;
				goto out;
			}

			zbx_free(param);
			if (NULL != host)
			{
				param = zbx_dsprintf(NULL, "%s:%s", host, key);
				zbx_free(host);
				zbx_free(key);
			}
			else
				param = key;
		}
		else
			substitute_lld_macros(&param, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);

		if (SUCCEED != zbx_function_param_quote(&param, quoted))
		{
			zbx_snprintf(error, max_error_len, "Cannot quote parameter \"%s\"", param);
			ret = FAIL;
			goto out;
		}

		/* copy the parameter */
		zbx_strcpy_alloc(exp, exp_alloc, exp_offset, param);

		/* copy what was after the parameter (including separator) */
		if (sep_pos < rel_len)
			zbx_strncpy_alloc(exp, exp_alloc, exp_offset, p + param_pos + param_len,
					sep_pos - param_pos - param_len + 1);
	}
out:
	zbx_free(param);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute LLD macros in JSON pairs                               *
 *                                                                            *
 * Parameters: data   -    [IN/OUT] pointer to a buffer that JSON pair        *
 *             jp_row -    [IN] discovery data for LLD macro substitution     *
 *             error  -    [OUT] reason for JSON pair parsing failure         *
 *             maxerrlen - [IN] the size of error buffer                      *
 *                                                                            *
 * Return value: SUCCEED or FAIL if cannot parse JSON pair                    *
 *                                                                            *
 ******************************************************************************/
int	substitute_macros_in_json_pairs(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
	struct zbx_json_parse	jp_array, jp_object;
	struct zbx_json		json;
	const char		*member, *element = NULL;
	char			name[MAX_STRING_LEN], value[MAX_STRING_LEN], *p_name = NULL, *p_value = NULL;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if ('\0' == **data)
		goto exit;

	if (SUCCEED != zbx_json_open(*data, &jp_array))
	{
		zbx_snprintf(error, maxerrlen, "cannot parse query fields: %s", zbx_json_strerror());
		ret = FAIL;
		goto exit;
	}

	if (NULL == (element = zbx_json_next(&jp_array, element)))
	{
		zbx_strlcpy(error, "cannot parse query fields: array is empty", maxerrlen);
		ret = FAIL;
		goto exit;
	}

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	do
	{
		if (SUCCEED != zbx_json_brackets_open(element, &jp_object) ||
				NULL == (member = zbx_json_pair_next(&jp_object, NULL, name, sizeof(name))) ||
				NULL == zbx_json_decodevalue(member, value, sizeof(value), NULL))
		{
			zbx_snprintf(error, maxerrlen, "cannot parse query fields: %s", zbx_json_strerror());
			ret = FAIL;
			goto clean;
		}

		p_name = zbx_strdup(NULL, name);
		p_value = zbx_strdup(NULL, value);

		substitute_lld_macros(&p_name, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
		substitute_lld_macros(&p_value, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, p_name, p_value, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);
		zbx_free(p_name);
		zbx_free(p_value);
	}
	while (NULL != (element = zbx_json_next(&jp_array, element)));

	zbx_free(*data);
	*data = zbx_strdup(NULL, json.buffer);
clean:
	zbx_json_free(&json);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Comments: auxiliary function for substitute_macros_xml()                   *
 *                                                                            *
 ******************************************************************************/
static void	substitute_macros_in_xml_elements(const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, xmlNode *node)
{
	xmlChar	*value;
	xmlAttr	*attr;
	char	*value_tmp;

	for (;NULL != node; node = node->next)
	{
		switch (node->type)
		{
			case XML_TEXT_NODE:
				if (NULL == (value = xmlNodeGetContent(node)))
					break;

				value_tmp = zbx_strdup(NULL, (const char *)value);

				if (NULL != item)
				{
					substitute_simple_macros_impl(NULL, NULL, NULL, NULL, NULL, &item->host, item,
							NULL, NULL, NULL, NULL, NULL, &value_tmp, MACRO_TYPE_HTTP_XML,
							NULL, 0);
				}
				else
				{
					substitute_lld_macros(&value_tmp, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL,
							0);
				}

				xmlNodeSetContent(node, NULL);
				xmlNodeAddContent(node, (xmlChar *)value_tmp);

				zbx_free(value_tmp);
				xmlFree(value);
				break;
			case XML_CDATA_SECTION_NODE:
				if (NULL == (value = xmlNodeGetContent(node)))
					break;

				value_tmp = zbx_strdup(NULL, (const char *)value);

				if (NULL != item)
				{
					substitute_simple_macros_impl(NULL, NULL, NULL, NULL, NULL, &item->host, item, NULL,
							NULL, NULL, NULL, NULL, &value_tmp, MACRO_TYPE_HTTP_RAW, NULL,
							0);
				}
				else
				{
					substitute_lld_macros(&value_tmp, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL,
							0);
				}

				xmlNodeSetContent(node, NULL);
				xmlNodeAddContent(node, (xmlChar *)value_tmp);

				zbx_free(value_tmp);
				xmlFree(value);
				break;
			case XML_ELEMENT_NODE:
				for (attr = node->properties; NULL != attr; attr = attr->next)
				{
					if (NULL == attr->name || NULL == (value = xmlGetProp(node, attr->name)))
						continue;

					value_tmp = zbx_strdup(NULL, (const char *)value);

					if (NULL != item)
					{
						substitute_simple_macros_impl(NULL, NULL, NULL, NULL, NULL, &item->host,
								item, NULL, NULL, NULL, NULL, NULL, &value_tmp,
								MACRO_TYPE_HTTP_XML, NULL, 0);
					}
					else
					{
						substitute_lld_macros(&value_tmp, jp_row, lld_macro_paths,
								ZBX_MACRO_ANY, NULL, 0);
					}

					xmlSetProp(node, attr->name, (xmlChar *)value_tmp);

					zbx_free(value_tmp);
					xmlFree(value);
				}
				break;
			default:
				break;
		}

		substitute_macros_in_xml_elements(item, jp_row, lld_macro_paths, node->children);
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: substitute simple or LLD macros in XML text nodes, attributes of  *
 *          a node or in CDATA section, validate XML                          *
 *                                                                            *
 * Parameters: data   - [IN/OUT] pointer to a buffer that contains XML        *
 *             item   - [IN] item for simple macro substitution               *
 *             jp_row - [IN] discovery data for LLD macro substitution        *
 *             error  - [OUT] reason for XML parsing failure                  *
 *             maxerrlen - [IN] the size of error buffer                      *
 *                                                                            *
 * Return value: SUCCEED or FAIL if XML validation has failed                 *
 *                                                                            *
 ******************************************************************************/
static int	substitute_macros_xml_impl(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(data);
	ZBX_UNUSED(item);
	ZBX_UNUSED(jp_row);
	ZBX_UNUSED(lld_macro_paths);
	zbx_snprintf(error, maxerrlen, "Support for XML was not compiled in");
	return FAIL;
#else
	xmlDoc		*doc;
	xmlNode		*root_element;
	xmlChar		*mem;
	int		size, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_open_xml(*data, 0, maxerrlen, (void **)&doc, (void **)&root_element, &error))
	{
		if (NULL == doc)
			goto exit;

		if (NULL == root_element)
			goto clean;
	}

	substitute_macros_in_xml_elements(item, jp_row, lld_macro_paths, root_element);
	xmlDocDumpMemory(doc, &mem, &size);

	if (FAIL == zbx_check_xml_memory((char *)mem, maxerrlen, &error))
		goto clean;

	zbx_free(*data);
	*data = zbx_malloc(NULL, size + 1);
	memcpy(*data, (const char *)mem, size + 1);
	xmlFree(mem);
	ret = SUCCEED;
clean:
	xmlFreeDoc(doc);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#endif
}

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Purpose: libxml2 callback function for error handle                        *
 *                                                                            *
 * Parameters: user_data - [IN/OUT] the user context                          *
 *             err       - [IN] the libxml2 error message                     *
 *                                                                            *
 ******************************************************************************/
static void	libxml_handle_error(void *user_data, xmlErrorPtr err)
{
	zbx_libxml_error_t	*err_ctx;

	if (NULL == user_data)
		return;

	err_ctx = (zbx_libxml_error_t *)user_data;
	zbx_strlcat(err_ctx->buf, err->message, err_ctx->len);

	if (NULL != err->str1)
		zbx_strlcat(err_ctx->buf, err->str1, err_ctx->len);

	if (NULL != err->str2)
		zbx_strlcat(err_ctx->buf, err->str2, err_ctx->len);

	if (NULL != err->str3)
		zbx_strlcat(err_ctx->buf, err->str3, err_ctx->len);
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: validate xpath string                                             *
 *                                                                            *
 * Parameters: xpath  - [IN] the xpath value                                  *
 *             error  - [OUT] the error message buffer                        *
 *             errlen - [IN] the size of error message buffer                 *
 *                                                                            *
 * Return value: SUCCEED - the xpath component was parsed successfully        *
 *               FAIL    - xpath parsing error                                *
 *                                                                            *
 ******************************************************************************/
int	xml_xpath_check(const char *xpath, char *error, size_t errlen)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(xpath);
	ZBX_UNUSED(error);
	ZBX_UNUSED(errlen);
	return FAIL;
#else
	zbx_libxml_error_t	err;
	xmlXPathContextPtr	ctx;
	xmlXPathCompExprPtr	p;

	err.buf = error;
	err.len = errlen;

	ctx = xmlXPathNewContext(NULL);
	xmlSetStructuredErrorFunc(&err, &libxml_handle_error);

	p = xmlXPathCtxtCompile(ctx, (xmlChar *)xpath);
	xmlSetStructuredErrorFunc(NULL, NULL);

	if (NULL == p)
	{
		xmlXPathFreeContext(ctx);
		return FAIL;
	}

	xmlXPathFreeCompExpr(p);
	xmlXPathFreeContext(ctx);
	return SUCCEED;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_simple_macros with masked secret macros                *
 *          (default setting)                                                 *
 *                                                                            *
 ******************************************************************************/
int	substitute_simple_macros(const zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		const zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		const DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const DB_SERVICE *service, const char *tz, char **data, int macro_type, char *error, int maxerrlen)
{
	return substitute_simple_macros_impl(actionid, event, r_event, userid, hostid, dc_host, dc_item, alert, ack,
			service_alarm, service, tz, data, macro_type, error, maxerrlen);

}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_simple_macros with unmasked secret macros              *
 *                                                                            *
 ******************************************************************************/
int	substitute_simple_macros_unmasked(const zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		const zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		const DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const DB_SERVICE *service, const char *tz, char **data, int macro_type, char *error, int maxerrlen)
{
	unsigned char	old_macro_env;
	int		ret;

	old_macro_env = zbx_dc_set_macro_env(ZBX_MACRO_ENV_SECURE);
	ret = substitute_simple_macros_impl(actionid, event, r_event, userid, hostid, dc_host, dc_item, alert, ack,
			service_alarm, service, tz, data, macro_type, error, maxerrlen);
	zbx_dc_set_macro_env(old_macro_env);
	return ret;

}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_macros_xml with masked secret macros                   *
 *                                                                            *
 ******************************************************************************/
int	substitute_macros_xml(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
	return substitute_macros_xml_impl(data, item, jp_row, lld_macro_paths, error, maxerrlen);
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_macros_xml with unmasked secret macros                 *
 *                                                                            *
 ******************************************************************************/
int	substitute_macros_xml_unmasked(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
	unsigned char	old_macro_env;
	int		ret;

	old_macro_env = zbx_dc_set_macro_env(ZBX_MACRO_ENV_SECURE);
	ret = substitute_macros_xml_impl(data, item, jp_row, lld_macro_paths, error, maxerrlen);
	zbx_dc_set_macro_env(old_macro_env);
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_key_macros with masked secret macros                   *
 *                                                                            *
 ******************************************************************************/
int	substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, int macro_type, char *error, size_t maxerrlen)
{
	return substitute_key_macros_impl(data, hostid, dc_item, jp_row, lld_macro_paths, macro_type, error, maxerrlen);
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_key_macros with unmasked secret macros                 *
 *                                                                            *
 ******************************************************************************/
int	substitute_key_macros_unmasked(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen)
{
	unsigned char	old_macro_env;
	int		ret;

	old_macro_env = zbx_dc_set_macro_env(ZBX_MACRO_ENV_SECURE);
	ret = substitute_key_macros_impl(data, hostid, dc_item, jp_row, lld_macro_paths, macro_type, error, maxerrlen);
	zbx_dc_set_macro_env(old_macro_env);
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extract index from valid indexed host macro                       *
 *                                                                            *
 * Return value: The index or -1 if it was not valid indexed host macro       *
 *                                                                            *
 ******************************************************************************/
int	zbx_host_macro_index(const char *macro)
{
	zbx_strloc_t	loc;
	int		func_num;

	loc.l = 0;
	loc.r = strlen(macro) - 1;

	if (NULL != macro_in_list(macro, loc, host_macros, &func_num))
		return func_num;

	return -1;
}
