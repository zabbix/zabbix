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

#include "common.h"

#include "db.h"
#include "log.h"
#include "dbcache.h"
#include "zbxserver.h"

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		valuemapid;
	zbx_uint64_t		interfaceid;
	zbx_uint64_t		templateid;
	zbx_uint64_t		master_itemid;
	char			*name;
	char			*key;
	char			*delay;
	char			*history;
	char			*trends;
	char			*trapper_hosts;
	char			*units;
	char			*formula;
	char			*logtimefmt;
	char			*params;
	char			*ipmi_sensor;
	char			*snmp_community;
	char			*snmp_oid;
	char			*snmpv3_securityname;
	char			*snmpv3_authpassphrase;
	char			*snmpv3_privpassphrase;
	char			*snmpv3_contextname;
	char			*username;
	char			*password;
	char			*publickey;
	char			*privatekey;
	char			*description;
	char			*lifetime;
	char			*port;
	char			*jmx_endpoint;
	unsigned char		type;
	unsigned char		value_type;
	unsigned char		status;
	unsigned char		snmpv3_securitylevel;
	unsigned char		snmpv3_authprotocol;
	unsigned char		snmpv3_privprotocol;
	unsigned char		authtype;
	unsigned char		flags;
	unsigned char		inventory_link;
	unsigned char		evaltype;
	unsigned char		allow_traps;
	zbx_vector_ptr_t	dependent_items;
}
zbx_template_item_t;

/* lld rule condition */
typedef struct
{
	zbx_uint64_t	item_conditionid;
	char		*macro;
	char		*value;
	unsigned char	op;
}
zbx_lld_rule_condition_t;

/* lld rule */
typedef struct
{
	/* discovery rule source id */
	zbx_uint64_t		templateid;
	/* discovery rule source conditions */
	zbx_vector_ptr_t	conditions;

	/* discovery rule destination id */
	zbx_uint64_t		itemid;
	/* the starting id to be used for destination condition ids */
	zbx_uint64_t		conditionid;
	/* discovery rule destination condition ids */
	zbx_vector_uint64_t	conditionids;
}
zbx_lld_rule_map_t;

/* auxiliary function for DBcopy_template_items() */
static void	DBget_interfaces_by_hostid(zbx_uint64_t hostid, zbx_uint64_t *interfaceids)
{
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	type;

	result = DBselect(
			"select type,interfaceid"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type in (%d,%d,%d,%d)"
				" and main=1",
			hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UCHAR(type, row[0]);
		ZBX_STR2UINT64(interfaceids[type - 1], row[1]);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: get_template_items                                               *
 *                                                                            *
 * Purpose: read template items from database                                 *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *             items       - [OUT] the item data                              *
 *                                                                            *
 * Comments: The itemid and key are set depending on whether the item exists  *
 *           for the specified host.                                          *
 *           If item exists itemid will be set to its itemid and key will be  *
 *           set to NULL.                                                     *
 *           If item does not exist, itemid will be set to 0 and key will be  *
 *           set to item key.                                                 *
 *                                                                            *
 ******************************************************************************/
static void	get_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *items)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, i;
	unsigned char		interface_type;
	zbx_template_item_t	*item;
	zbx_uint64_t		interfaceids[4];

	memset(&interfaceids, 0, sizeof(interfaceids));
	DBget_interfaces_by_hostid(hostid, interfaceids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ti.itemid,ti.name,ti.key_,ti.type,ti.value_type,ti.delay,"
				"ti.history,ti.trends,ti.status,ti.trapper_hosts,ti.units,"
				"ti.formula,ti.logtimefmt,ti.valuemapid,ti.params,ti.ipmi_sensor,ti.snmp_community,"
				"ti.snmp_oid,ti.snmpv3_securityname,ti.snmpv3_securitylevel,ti.snmpv3_authprotocol,"
				"ti.snmpv3_authpassphrase,ti.snmpv3_privprotocol,ti.snmpv3_privpassphrase,ti.authtype,"
				"ti.username,ti.password,ti.publickey,ti.privatekey,ti.flags,ti.description,"
				"ti.inventory_link,ti.lifetime,ti.snmpv3_contextname,hi.itemid,ti.evaltype,ti.port,"
				"ti.jmx_endpoint,ti.master_itemid,ti.allow_traps"
			" from items ti"
			" left join items hi on hi.key_=ti.key_"
				" and hi.hostid=" ZBX_FS_UI64
			" where",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		item = (zbx_template_item_t *)zbx_malloc(NULL, sizeof(zbx_template_item_t));

		ZBX_STR2UINT64(item->templateid, row[0]);
		ZBX_STR2UCHAR(item->type, row[3]);
		ZBX_STR2UCHAR(item->value_type, row[4]);
		ZBX_STR2UCHAR(item->status, row[8]);
		ZBX_DBROW2UINT64(item->valuemapid, row[13]);
		ZBX_STR2UCHAR(item->snmpv3_securitylevel, row[19]);
		ZBX_STR2UCHAR(item->snmpv3_authprotocol, row[20]);
		ZBX_STR2UCHAR(item->snmpv3_privprotocol, row[22]);
		ZBX_STR2UCHAR(item->authtype, row[24]);
		ZBX_STR2UCHAR(item->flags, row[29]);
		ZBX_STR2UCHAR(item->inventory_link, row[31]);
		ZBX_STR2UCHAR(item->evaltype, row[35]);
		ZBX_STR2UCHAR(item->allow_traps, row[39]);

		switch (interface_type = get_interface_type_by_item_type(item->type))
		{
			case INTERFACE_TYPE_UNKNOWN:
				item->interfaceid = 0;
				break;
			case INTERFACE_TYPE_ANY:
				for (i = 0; INTERFACE_TYPE_COUNT > i; i++)
				{
					if (0 != interfaceids[INTERFACE_TYPE_PRIORITY[i] - 1])
						break;
				}
				item->interfaceid = interfaceids[INTERFACE_TYPE_PRIORITY[i] - 1];
				break;
			default:
				item->interfaceid = interfaceids[interface_type - 1];
		}

		item->name = zbx_strdup(NULL, row[1]);
		item->delay = zbx_strdup(NULL, row[5]);
		item->history = zbx_strdup(NULL, row[6]);
		item->trends = zbx_strdup(NULL, row[7]);
		item->trapper_hosts = zbx_strdup(NULL, row[9]);
		item->units = zbx_strdup(NULL, row[10]);
		item->formula = zbx_strdup(NULL, row[11]);
		item->logtimefmt = zbx_strdup(NULL, row[12]);
		item->params = zbx_strdup(NULL, row[14]);
		item->ipmi_sensor = zbx_strdup(NULL, row[15]);
		item->snmp_community = zbx_strdup(NULL, row[16]);
		item->snmp_oid = zbx_strdup(NULL, row[17]);
		item->snmpv3_securityname = zbx_strdup(NULL, row[18]);
		item->snmpv3_authpassphrase = zbx_strdup(NULL, row[21]);
		item->snmpv3_privpassphrase = zbx_strdup(NULL, row[23]);
		item->username = zbx_strdup(NULL, row[25]);
		item->password = zbx_strdup(NULL, row[26]);
		item->publickey = zbx_strdup(NULL, row[27]);
		item->privatekey = zbx_strdup(NULL, row[28]);
		item->description = zbx_strdup(NULL, row[30]);
		item->lifetime = zbx_strdup(NULL, row[32]);
		item->snmpv3_contextname = zbx_strdup(NULL, row[33]);
		item->port = zbx_strdup(NULL, row[36]);
		item->jmx_endpoint = zbx_strdup(NULL, row[37]);
		ZBX_DBROW2UINT64(item->master_itemid, row[38]);

		if (SUCCEED != DBis_null(row[34]))
		{
			item->key = NULL;
			ZBX_STR2UINT64(item->itemid, row[34]);
		}
		else
		{
			item->key = zbx_strdup(NULL, row[2]);
			item->itemid = 0;
		}

		zbx_vector_ptr_create(&item->dependent_items);
		zbx_vector_ptr_append(items, item);
	}
	DBfree_result(result);

	zbx_free(sql);

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: get_template_lld_rule_map                                        *
 *                                                                            *
 * Purpose: reads template lld rule conditions and host lld_rule identifiers  *
 *          from database                                                     *
 *                                                                            *
 * Parameters: items - [IN] the host items including lld rules                *
 *             rules - [OUT] the ldd rule mapping                             *
 *                                                                            *
 ******************************************************************************/
static void	get_template_lld_rule_map(const zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules)
{
	zbx_template_item_t		*item;
	zbx_lld_rule_map_t		*rule;
	zbx_lld_rule_condition_t	*condition;
	int				i, index;
	zbx_vector_uint64_t		itemids;
	DB_RESULT			result;
	DB_ROW				row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			itemid, item_conditionid;

	zbx_vector_uint64_create(&itemids);

	/* prepare discovery rules */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			continue;

		rule = (zbx_lld_rule_map_t *)zbx_malloc(NULL, sizeof(zbx_lld_rule_map_t));

		rule->itemid = item->itemid;
		rule->templateid = item->templateid;
		rule->conditionid = 0;
		zbx_vector_uint64_create(&rule->conditionids);
		zbx_vector_ptr_create(&rule->conditions);

		zbx_vector_ptr_append(rules, rule);

		if (0 != rule->itemid)
			zbx_vector_uint64_append(&itemids, rule->itemid);
		zbx_vector_uint64_append(&itemids, rule->templateid);
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_ptr_sort(rules, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select item_conditionid,itemid,operator,macro,value from item_condition where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[1]);

			index = zbx_vector_ptr_bsearch(rules, &itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL != index)
			{
				/* read template lld conditions */

				rule = (zbx_lld_rule_map_t *)rules->values[index];

				condition = (zbx_lld_rule_condition_t *)zbx_malloc(NULL, sizeof(zbx_lld_rule_condition_t));

				ZBX_STR2UINT64(condition->item_conditionid, row[0]);
				ZBX_STR2UCHAR(condition->op, row[2]);
				condition->macro = zbx_strdup(NULL, row[3]);
				condition->value = zbx_strdup(NULL, row[4]);

				zbx_vector_ptr_append(&rule->conditions, condition);
			}
			else
			{
				/* read host lld conditions identifiers */

				for (i = 0; i < rules->values_num; i++)
				{
					rule = (zbx_lld_rule_map_t *)rules->values[i];

					if (itemid != rule->itemid)
						continue;

					ZBX_STR2UINT64(item_conditionid, row[0]);
					zbx_vector_uint64_append(&rule->conditionids, item_conditionid);

					break;
				}

				if (i == rules->values_num)
					THIS_SHOULD_NEVER_HAPPEN;
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&itemids);
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_template_lld_rule_conditionids                         *
 *                                                                            *
 * Purpose: calculate identifiers for new item conditions                     *
 *                                                                            *
 * Parameters: rules - [IN] the ldd rule mapping                              *
 *                                                                            *
 * Return value: The number of new item conditions to be inserted.            *
 *                                                                            *
 ******************************************************************************/
static int	calculate_template_lld_rule_conditionids(zbx_vector_ptr_t *rules)
{
	zbx_lld_rule_map_t	*rule;
	int			i, conditions_num = 0;
	zbx_uint64_t		conditionid;

	/* calculate the number of new conditions to be inserted */
	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		if (rule->conditions.values_num > rule->conditionids.values_num)
			conditions_num += rule->conditions.values_num - rule->conditionids.values_num;
	}

	/* reserve ids for the new conditions to be inserted and assign to lld rules */
	if (0 == conditions_num)
		goto out;

	conditionid = DBget_maxid_num("item_condition", conditions_num);

	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		if (rule->conditions.values_num <= rule->conditionids.values_num)
			continue;

		rule->conditionid = conditionid;
		conditionid += rule->conditions.values_num - rule->conditionids.values_num;
	}
out:
	return conditions_num;
}

/******************************************************************************
 *                                                                            *
 * Function: update_template_lld_rule_formulas                                *
 *                                                                            *
 * Purpose: translate template item condition identifiers in expression type  *
 *          discovery rule formulas to refer the host item condition          *
 *          identifiers instead.                                              *
 *                                                                            *
 * Parameters:  items  - [IN] the template items                              *
 *              rules  - [IN] the ldd rule mapping                            *
 *                                                                            *
 ******************************************************************************/
static void	update_template_lld_rule_formulas(zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules)
{
	zbx_lld_rule_map_t	*rule;
	int			i, j, index;
	char			*formula;
	zbx_uint64_t		conditionid;

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags) || CONDITION_EVAL_TYPE_EXPRESSION != item->evaltype)
			continue;

		index = zbx_vector_ptr_bsearch(rules, &item->templateid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		if (FAIL == index)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule = (zbx_lld_rule_map_t *)rules->values[index];

		formula = zbx_strdup(NULL, item->formula);

		conditionid = rule->conditionid;

		for (j = 0; j < rule->conditions.values_num; j++)
		{
			zbx_uint64_t			id;
			char				srcid[64], dstid[64], *ptr;
			size_t				pos = 0, len;

			zbx_lld_rule_condition_t	*condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			if (j < rule->conditionids.values_num)
				id = rule->conditionids.values[j];
			else
				id = conditionid++;

			zbx_snprintf(srcid, sizeof(srcid), "{" ZBX_FS_UI64 "}", condition->item_conditionid);
			zbx_snprintf(dstid, sizeof(dstid), "{" ZBX_FS_UI64 "}", id);

			len = strlen(srcid);

			while (NULL != (ptr = strstr(formula + pos, srcid)))
			{
				pos = ptr - formula + len - 1;
				zbx_replace_string(&formula, ptr - formula, &pos, dstid);
			}
		}

		zbx_free(item->formula);
		item->formula = formula;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_item                                               *
 *                                                                            *
 * Purpose: save (insert or update) template item                             *
 *                                                                            *
 * Parameters: hostid     - [IN] parent host id                               *
 *             itemid     - [IN/OUT] item id used for insert operations       *
 *             item       - [IN] item to be saved                             *
 *             db_insert  - [IN] prepared item bulk insert                    *
 *             sql        - [IN/OUT] sql buffer pointer used for update       *
 *                                   operations                               *
 *             sql_alloc  - [IN/OUT] sql buffer already allocated memory      *
 *             sql_offset - [IN/OUT] offset for writing within sql buffer     *
 *                                                                            *
 ******************************************************************************/
static void	save_template_item(zbx_uint64_t hostid, zbx_uint64_t *itemid, zbx_template_item_t *item,
		zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	int			i;
	zbx_template_item_t	*dependent;

	if (NULL == item->key) /* existing item */
	{
		char	*name_esc, *delay_esc, *history_esc, *trends_esc, *trapper_hosts_esc, *units_esc, *formula_esc,
			*logtimefmt_esc, *params_esc, *ipmi_sensor_esc, *snmp_community_esc, *snmp_oid_esc,
			*snmpv3_securityname_esc, *snmpv3_authpassphrase_esc, *snmpv3_privpassphrase_esc, *username_esc,
			*password_esc, *publickey_esc, *privatekey_esc, *description_esc, *lifetime_esc,
			*snmpv3_contextname_esc, *port_esc, *jmx_endpoint_esc;

		name_esc = DBdyn_escape_string(item->name);
		delay_esc = DBdyn_escape_string(item->delay);
		history_esc = DBdyn_escape_string(item->history);
		trends_esc = DBdyn_escape_string(item->trends);
		trapper_hosts_esc = DBdyn_escape_string(item->trapper_hosts);
		units_esc = DBdyn_escape_string(item->units);
		formula_esc = DBdyn_escape_string(item->formula);
		logtimefmt_esc = DBdyn_escape_string(item->logtimefmt);
		params_esc = DBdyn_escape_string(item->params);
		ipmi_sensor_esc = DBdyn_escape_string(item->ipmi_sensor);
		snmp_community_esc = DBdyn_escape_string(item->snmp_community);
		snmp_oid_esc = DBdyn_escape_string(item->snmp_oid);
		snmpv3_securityname_esc = DBdyn_escape_string(item->snmpv3_securityname);
		snmpv3_authpassphrase_esc = DBdyn_escape_string(item->snmpv3_authpassphrase);
		snmpv3_privpassphrase_esc = DBdyn_escape_string(item->snmpv3_privpassphrase);
		username_esc = DBdyn_escape_string(item->username);
		password_esc = DBdyn_escape_string(item->password);
		publickey_esc = DBdyn_escape_string(item->publickey);
		privatekey_esc = DBdyn_escape_string(item->privatekey);
		description_esc = DBdyn_escape_string(item->description);
		lifetime_esc = DBdyn_escape_string(item->lifetime);
		snmpv3_contextname_esc = DBdyn_escape_string(item->snmpv3_contextname);
		port_esc = DBdyn_escape_string(item->port);
		jmx_endpoint_esc = DBdyn_escape_string(item->jmx_endpoint);

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update items"
				" set name='%s',"
					"type=%d,"
					"value_type=%d,"
					"delay='%s',"
					"history='%s',"
					"trends='%s',"
					"status=%d,"
					"trapper_hosts='%s',"
					"units='%s',"
					"formula='%s',"
					"logtimefmt='%s',"
					"valuemapid=%s,"
					"params='%s',"
					"ipmi_sensor='%s',"
					"snmp_community='%s',"
					"snmp_oid='%s',"
					"snmpv3_securityname='%s',"
					"snmpv3_securitylevel=%d,"
					"snmpv3_authprotocol=%d,"
					"snmpv3_authpassphrase='%s',"
					"snmpv3_privprotocol=%d,"
					"snmpv3_privpassphrase='%s',"
					"snmpv3_contextname='%s',"
					"authtype=%d,"
					"username='%s',"
					"password='%s',"
					"publickey='%s',"
					"privatekey='%s',"
					"templateid=" ZBX_FS_UI64 ","
					"flags=%d,"
					"description='%s',"
					"inventory_link=%d,"
					"interfaceid=%s,"
					"lifetime='%s',"
					"evaltype=%d,"
					"port='%s',"
					"jmx_endpoint='%s',"
					"master_itemid=%s,"
					"allow_traps=%d"
				" where itemid=" ZBX_FS_UI64 ";\n",
				name_esc, (int)item->type, (int)item->value_type, delay_esc,
				history_esc, trends_esc, (int)item->status, trapper_hosts_esc, units_esc,
				formula_esc, logtimefmt_esc, DBsql_id_ins(item->valuemapid), params_esc,
				ipmi_sensor_esc, snmp_community_esc, snmp_oid_esc, snmpv3_securityname_esc,
				(int)item->snmpv3_securitylevel, (int)item->snmpv3_authprotocol,
				snmpv3_authpassphrase_esc, (int)item->snmpv3_privprotocol, snmpv3_privpassphrase_esc,
				snmpv3_contextname_esc, (int)item->authtype, username_esc, password_esc, publickey_esc,
				privatekey_esc, item->templateid, (int)item->flags, description_esc,
				(int)item->inventory_link, DBsql_id_ins(item->interfaceid), lifetime_esc,
				(int)item->evaltype, port_esc, jmx_endpoint_esc, DBsql_id_ins(item->master_itemid),
				item->allow_traps, item->itemid);

		zbx_free(jmx_endpoint_esc);
		zbx_free(port_esc);
		zbx_free(snmpv3_contextname_esc);
		zbx_free(lifetime_esc);
		zbx_free(description_esc);
		zbx_free(privatekey_esc);
		zbx_free(publickey_esc);
		zbx_free(password_esc);
		zbx_free(username_esc);
		zbx_free(snmpv3_privpassphrase_esc);
		zbx_free(snmpv3_authpassphrase_esc);
		zbx_free(snmpv3_securityname_esc);
		zbx_free(snmp_oid_esc);
		zbx_free(snmp_community_esc);
		zbx_free(ipmi_sensor_esc);
		zbx_free(params_esc);
		zbx_free(logtimefmt_esc);
		zbx_free(formula_esc);
		zbx_free(units_esc);
		zbx_free(trapper_hosts_esc);
		zbx_free(trends_esc);
		zbx_free(history_esc);
		zbx_free(delay_esc);
		zbx_free(name_esc);
	}
	else
	{
		zbx_db_insert_add_values(db_insert, *itemid, item->name, item->key, hostid, (int)item->type,
				(int)item->value_type, item->delay, item->history, item->trends,
				(int)item->status, item->trapper_hosts, item->units, item->formula, item->logtimefmt,
				item->valuemapid, item->params, item->ipmi_sensor, item->snmp_community, item->snmp_oid,
				item->snmpv3_securityname, (int)item->snmpv3_securitylevel,
				(int)item->snmpv3_authprotocol, item->snmpv3_authpassphrase,
				(int)item->snmpv3_privprotocol, item->snmpv3_privpassphrase, (int)item->authtype,
				item->username, item->password, item->publickey, item->privatekey, item->templateid,
				(int)item->flags, item->description, (int)item->inventory_link, item->interfaceid,
				item->lifetime, item->snmpv3_contextname, (int)item->evaltype, item->port,
				item->jmx_endpoint, item->master_itemid, item->allow_traps);

		item->itemid = (*itemid)++;
	}

	for (i = 0; i < item->dependent_items.values_num; i++)
	{
		dependent = (zbx_template_item_t *)item->dependent_items.values[i];
		dependent->master_itemid = item->itemid;
		save_template_item(hostid, itemid, dependent, db_insert, sql, sql_alloc, sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_items                                              *
 *                                                                            *
 * Purpose: saves template items to the target host in database               *
 *                                                                            *
 * Parameters:  hostid - [IN] the target host                                 *
 *              items  - [IN] the template items                              *
 *              rules  - [IN] the ldd rule mapping                            *
 *                                                                            *
 ******************************************************************************/
static void	save_template_items(zbx_uint64_t hostid, zbx_vector_ptr_t *items)
{
	char			*sql = NULL;
	size_t			sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;
	int			new_items = 0, upd_items = 0, i;
	zbx_uint64_t		itemid = 0;
	zbx_db_insert_t		db_insert;
	zbx_template_item_t	*item;

	if (0 == items->values_num)
		return;

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (NULL == item->key)
			upd_items++;
		else
			new_items++;
	}

	if (0 != new_items)
	{
		itemid = DBget_maxid_num("items", new_items);

		zbx_db_insert_prepare(&db_insert, "items", "itemid", "name", "key_", "hostid", "type", "value_type",
				"delay", "history", "trends", "status", "trapper_hosts", "units",
				"formula", "logtimefmt", "valuemapid", "params", "ipmi_sensor",
				"snmp_community", "snmp_oid", "snmpv3_securityname", "snmpv3_securitylevel",
				"snmpv3_authprotocol", "snmpv3_authpassphrase", "snmpv3_privprotocol",
				"snmpv3_privpassphrase", "authtype", "username", "password", "publickey", "privatekey",
				"templateid", "flags", "description", "inventory_link", "interfaceid", "lifetime",
				"snmpv3_contextname", "evaltype", "port", "jmx_endpoint", "master_itemid",
				"allow_traps", NULL);
	}

	if (0 != upd_items)
	{
		sql = (char *)zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		/* dependent items are saved within recursive save_template_item calls while saving master */
		if (0 == item->master_itemid)
			save_template_item(hostid, &itemid, item, &db_insert, &sql, &sql_alloc, &sql_offset);
	}

	if (0 != new_items)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != upd_items)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);

		zbx_free(sql);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_lld_rules                                          *
 *                                                                            *
 * Purpose: saves template lld rule item conditions to the target host in     *
 *          database                                                          *
 *                                                                            *
 * Parameters:  items          - [IN] the template items                      *
 *              rules          - [IN] the ldd rule mapping                    *
 *              new_conditions - [IN] the number of new item conditions to    *
 *                                    be inserted                             *
 *                                                                            *
 ******************************************************************************/
static void	save_template_lld_rules(zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules, int new_conditions)
{
	char				*macro_esc, *value_esc;
	int				i, j, index;
	zbx_db_insert_t			db_insert;
	zbx_lld_rule_map_t		*rule;
	zbx_lld_rule_condition_t	*condition;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		item_conditionids;

	if (0 == rules->values_num)
		return;

	zbx_vector_uint64_create(&item_conditionids);

	if (0 != new_conditions)
	{
		zbx_db_insert_prepare(&db_insert, "item_condition", "item_conditionid", "itemid", "operator", "macro",
				"value", NULL);

		/* insert lld rule conditions for new items */
		for (i = 0; i < items->values_num; i++)
		{
			zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

			if (NULL == item->key)
				continue;

			if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
				continue;

			index = zbx_vector_ptr_bsearch(rules, &item->templateid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL == index)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			rule = (zbx_lld_rule_map_t *)rules->values[index];

			for (j = 0; j < rule->conditions.values_num; j++)
			{
				condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

				zbx_db_insert_add_values(&db_insert, rule->conditionid++, item->itemid,
						(int)condition->op, condition->macro, condition->value);
			}
		}
	}

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* update lld rule conditions for existing items */
	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		/* skip lld rules of new items */
		if (0 == rule->itemid)
			continue;

		index = MIN(rule->conditions.values_num, rule->conditionids.values_num);

		/* update intersecting rule conditions */
		for (j = 0; j < index; j++)
		{
			condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			macro_esc = DBdyn_escape_string(condition->macro);
			value_esc = DBdyn_escape_string(condition->value);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update item_condition"
					" set operator=%d,macro='%s',value='%s'"
					" where item_conditionid=" ZBX_FS_UI64 ";\n",
					(int)condition->op, macro_esc, value_esc, rule->conditionids.values[j]);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			zbx_free(value_esc);
			zbx_free(macro_esc);
		}

		/* delete removed rule conditions */
		for (j = index; j < rule->conditionids.values_num; j++)
			zbx_vector_uint64_append(&item_conditionids, rule->conditionids.values[j]);

		/* insert new rule conditions */
		for (j = index; j < rule->conditions.values_num; j++)
		{
			condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			zbx_db_insert_add_values(&db_insert, rule->conditionid++, rule->itemid,
					(int)condition->op, condition->macro, condition->value);
		}
	}

	/* delete removed item conditions */
	if (0 != item_conditionids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_condition where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "item_conditionid", item_conditionids.values,
				item_conditionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
		DBexecute("%s", sql);

	if (0 != new_conditions)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&item_conditionids);
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_item_applications                                  *
 *                                                                            *
 * Purpose: saves new item applications links in database                     *
 *                                                                            *
 * Parameters:  items   - [IN] the template items                             *
 *                                                                            *
 ******************************************************************************/
static void	save_template_item_applications(zbx_vector_ptr_t *items)
{
	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	applicationid;
	}
	zbx_itemapp_t;

	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_vector_ptr_t	itemapps;
	zbx_itemapp_t		*itemapp;
	int			i;
	zbx_db_insert_t		db_insert;

	zbx_vector_ptr_create(&itemapps);
	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		zbx_vector_uint64_append(&itemids, item->itemid);
	}

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hi.itemid,ha.applicationid"
			" from items_applications tia"
				" join items hi on hi.templateid=tia.itemid"
					" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.itemid", itemids.values, itemids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" join application_template hat on hat.templateid=tia.applicationid"
				" join applications ha on ha.applicationid=hat.applicationid"
					" and ha.hostid=hi.hostid"
					" left join items_applications hia on hia.applicationid=ha.applicationid"
						" and hia.itemid=hi.itemid"
			" where hia.itemappid is null");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		itemapp = (zbx_itemapp_t *)zbx_malloc(NULL, sizeof(zbx_itemapp_t));

		ZBX_STR2UINT64(itemapp->itemid, row[0]);
		ZBX_STR2UINT64(itemapp->applicationid, row[1]);

		zbx_vector_ptr_append(&itemapps, itemapp);
	}
	DBfree_result(result);

	if (0 == itemapps.values_num)
		goto out;

	zbx_db_insert_prepare(&db_insert, "items_applications", "itemappid", "itemid", "applicationid", NULL);

	for (i = 0; i < itemapps.values_num; i++)
	{
		itemapp = (zbx_itemapp_t *)itemapps.values[i];

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), itemapp->itemid, itemapp->applicationid);
	}

	zbx_db_insert_autoincrement(&db_insert, "itemappid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_free(sql);

	zbx_vector_uint64_destroy(&itemids);

	zbx_vector_ptr_clear_ext(&itemapps, zbx_ptr_free);
	zbx_vector_ptr_destroy(&itemapps);
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_discovery_prototypes                               *
 *                                                                            *
 * Purpose: saves host item prototypes in database                            *
 *                                                                            *
 * Parameters:  hostid  - [IN] the target host                                *
 *              items   - [IN] the template items                             *
 *                                                                            *
 ******************************************************************************/
static void	save_template_discovery_prototypes(zbx_uint64_t hostid, zbx_vector_ptr_t *items)
{
	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	parent_itemid;
	}
	zbx_proto_t;

	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_vector_ptr_t	prototypes;
	zbx_proto_t		*proto;
	int			i;
	zbx_db_insert_t		db_insert;

	zbx_vector_ptr_create(&prototypes);
	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		/* process only new prototype items */
		if (NULL == item->key || 0 == (ZBX_FLAG_DISCOVERY_PROTOTYPE & item->flags))
			continue;

		zbx_vector_uint64_append(&itemids, item->itemid);
	}

	if (0 == itemids.values_num)
		goto out;

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid,r.itemid"
			" from items i,item_discovery id,items r"
			" where i.templateid=id.itemid"
				" and id.parent_itemid=r.templateid"
				" and r.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", itemids.values, itemids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		proto = (zbx_proto_t *)zbx_malloc(NULL, sizeof(zbx_proto_t));

		ZBX_STR2UINT64(proto->itemid, row[0]);
		ZBX_STR2UINT64(proto->parent_itemid, row[1]);

		zbx_vector_ptr_append(&prototypes, proto);
	}
	DBfree_result(result);

	if (0 == prototypes.values_num)
		goto out;

	zbx_db_insert_prepare(&db_insert, "item_discovery", "itemdiscoveryid", "itemid",
					"parent_itemid", NULL);

	for (i = 0; i < prototypes.values_num; i++)
	{
		proto = (zbx_proto_t *)prototypes.values[i];

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), proto->itemid, proto->parent_itemid);
	}

	zbx_db_insert_autoincrement(&db_insert, "itemdiscoveryid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_free(sql);

	zbx_vector_uint64_destroy(&itemids);

	zbx_vector_ptr_clear_ext(&prototypes, zbx_ptr_free);
	zbx_vector_ptr_destroy(&prototypes);
}

/******************************************************************************
 *                                                                            *
 * Function: free_template_item                                               *
 *                                                                            *
 * Purpose: frees template item                                               *
 *                                                                            *
 * Parameters:  item  - [IN] the template item                                *
 *                                                                            *
 ******************************************************************************/
static void	free_template_item(zbx_template_item_t *item)
{
	zbx_free(item->jmx_endpoint);
	zbx_free(item->port);
	zbx_free(item->snmpv3_contextname);
	zbx_free(item->lifetime);
	zbx_free(item->description);
	zbx_free(item->privatekey);
	zbx_free(item->publickey);
	zbx_free(item->password);
	zbx_free(item->username);
	zbx_free(item->snmpv3_privpassphrase);
	zbx_free(item->snmpv3_authpassphrase);
	zbx_free(item->snmpv3_securityname);
	zbx_free(item->snmp_oid);
	zbx_free(item->snmp_community);
	zbx_free(item->ipmi_sensor);
	zbx_free(item->params);
	zbx_free(item->logtimefmt);
	zbx_free(item->formula);
	zbx_free(item->units);
	zbx_free(item->trapper_hosts);
	zbx_free(item->trends);
	zbx_free(item->history);
	zbx_free(item->delay);
	zbx_free(item->name);
	zbx_free(item->key);

	zbx_vector_ptr_destroy(&item->dependent_items);

	zbx_free(item);
}

/******************************************************************************
 *                                                                            *
 * Function: free_lld_rule_condition                                          *
 *                                                                            *
 * Purpose: frees lld rule condition                                          *
 *                                                                            *
 * Parameters:  item  - [IN] the lld rule condition                           *
 *                                                                            *
 ******************************************************************************/
static void	free_lld_rule_condition(zbx_lld_rule_condition_t *condition)
{
	zbx_free(condition->macro);
	zbx_free(condition->value);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Function: free_lld_rule_map                                                *
 *                                                                            *
 * Purpose: frees lld rule mapping                                            *
 *                                                                            *
 * Parameters:  item  - [IN] the lld rule mapping                             *
 *                                                                            *
 ******************************************************************************/
static void	free_lld_rule_map(zbx_lld_rule_map_t *rule)
{
	zbx_vector_ptr_clear_ext(&rule->conditions, (zbx_clean_func_t)free_lld_rule_condition);
	zbx_vector_ptr_destroy(&rule->conditions);

	zbx_vector_uint64_destroy(&rule->conditionids);

	zbx_free(rule);
}

static zbx_hash_t	template_item_hash_func(const void *d)
{
	const zbx_template_item_t	*item = *(const zbx_template_item_t **)d;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&item->templateid);
}

static int	template_item_compare_func(const void *d1, const void *d2)
{
	const zbx_template_item_t	*item1 = *(const zbx_template_item_t **)d1;
	const zbx_template_item_t	*item2 = *(const zbx_template_item_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item1->templateid, item2->templateid);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: copy_template_items_preproc                                      *
 *                                                                            *
 * Purpose: copy template item preprocessing options                          *
 *                                                                            *
 * Parameters: templateids - [IN] array of template IDs                       *
 *             items       - [IN] array of new/updated items                  *
 *                                                                            *
 ******************************************************************************/
static void	copy_template_items_preproc(const zbx_vector_uint64_t *templateids, const zbx_vector_ptr_t *items)
{
	zbx_vector_uint64_t		itemids;
	zbx_hashset_t			items_t;
	int				i;
	const zbx_template_item_t	*item, **pitem;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	DB_ROW				row;
	DB_RESULT			result;
	zbx_db_insert_t			db_insert;

	if (0 == items->values_num)
		return;

	zbx_vector_uint64_create(&itemids);
	zbx_hashset_create(&items_t, items->values_num, template_item_hash_func, template_item_compare_func);

	/* remove old item preprocessing options */

	for (i = 0; i < items->values_num; i++)
	{
		item = (const zbx_template_item_t *)items->values[i];

		if (NULL == item->key)
			zbx_vector_uint64_append(&itemids, item->itemid);

		zbx_hashset_insert(&items_t, &item, sizeof(zbx_template_item_t *));
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_preproc where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);
		DBexecute("%s", sql);
		sql_offset = 0;
	}

	zbx_db_insert_prepare(&db_insert, "item_preproc", "item_preprocid", "itemid", "step", "type", "params", NULL);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ip.itemid,ip.step,ip.type,ip.params"
				" from item_preproc ip,items ti"
				" where ip.itemid=ti.itemid"
				" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);
	while (NULL != (row = DBfetch(result)))
	{
		zbx_template_item_t	item_local, *pitem_local = &item_local;

		ZBX_STR2UINT64(item_local.templateid, row[0]);
		if (NULL == (pitem = (const zbx_template_item_t **)zbx_hashset_search(&items_t, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), (*pitem)->itemid, atoi(row[1]), atoi(row[2]),
				row[3]);

	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "item_preprocid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(sql);
	zbx_hashset_destroy(&items_t);
	zbx_vector_uint64_destroy(&itemids);
}

/******************************************************************************
 *                                                                            *
 * Function: compare_template_items                                           *
 *                                                                            *
 * Purpose: compare templateid of two template items                          *
 *                                                                            *
 * Parameters: d1 - [IN] first template item                                  *
 *             d2 - [IN] second template item                                 *
 *                                                                            *
 * Return value: compare result (-1 for d1<d2, 1 for d1>d2, 0 for d1==d2)     *
 *                                                                            *
 ******************************************************************************/
static int	compare_template_items(const void *d1, const void *d2)
{
	const zbx_template_item_t	*i1 = *(const zbx_template_item_t **)d1;
	const zbx_template_item_t	*i2 = *(const zbx_template_item_t **)d2;

	return zbx_default_uint64_compare_func(&i1->templateid, &i2->templateid);
}

/******************************************************************************
 *                                                                            *
 * Function: link_template_dependent_items                                    *
 *                                                                            *
 * Purpose: create dependent item index in master item data                   *
 *                                                                            *
 * Parameters: items       - [IN/OUT] the template items                      *
 *                                                                            *
 ******************************************************************************/
static void	link_template_dependent_items(zbx_vector_ptr_t *items)
{
	const char			*__function_name = "link_template_dependent_items";
	zbx_template_item_t		*item, *master, item_local;
	int				i, index;
	zbx_vector_ptr_t		template_index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&template_index);
	zbx_vector_ptr_append_array(&template_index, items->values, items->values_num);
	zbx_vector_ptr_sort(&template_index, compare_template_items);

	for (i = items->values_num - 1; i >= 0; i--)
	{
		item = (zbx_template_item_t *)items->values[i];
		if (0 != item->master_itemid)
		{
			item_local.templateid = item->master_itemid;
			if (FAIL == (index = zbx_vector_ptr_bsearch(&template_index, &item_local,
					compare_template_items)))
			{
				/* dependent item without master item should be removed */
				THIS_SHOULD_NEVER_HAPPEN;
				free_template_item(item);
				zbx_vector_ptr_remove(items, i);
			}
			else
			{
				master = (zbx_template_item_t *)template_index.values[index];
				zbx_vector_ptr_append(&master->dependent_items, item);
			}
		}
	}

	zbx_vector_ptr_destroy(&template_index);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_items                                            *
 *                                                                            *
 * Purpose: copy template items to host                                       *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 ******************************************************************************/
void	DBcopy_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBcopy_template_items";

	zbx_vector_ptr_t	items, lld_rules;
	int			new_conditions = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&items);
	zbx_vector_ptr_create(&lld_rules);

	get_template_items(hostid, templateids, &items);

	if (0 == items.values_num)
		goto out;

	get_template_lld_rule_map(&items, &lld_rules);

	new_conditions = calculate_template_lld_rule_conditionids(&lld_rules);
	update_template_lld_rule_formulas(&items, &lld_rules);

	link_template_dependent_items(&items);
	save_template_items(hostid, &items);
	save_template_lld_rules(&items, &lld_rules, new_conditions);
	save_template_item_applications(&items);
	save_template_discovery_prototypes(hostid, &items);
	copy_template_items_preproc(templateids, &items);
out:
	zbx_vector_ptr_clear_ext(&lld_rules, (zbx_clean_func_t)free_lld_rule_map);
	zbx_vector_ptr_destroy(&lld_rules);

	zbx_vector_ptr_clear_ext(&items, (zbx_clean_func_t)free_template_item);
	zbx_vector_ptr_destroy(&items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
