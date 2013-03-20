/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "lld.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxserver.h"

typedef struct
{
	zbx_uint64_t	itemid;
	char		*key;
	char		*name;
	char		*snmp_oid;
	char		*params;
	zbx_uint64_t	*new_appids;
	zbx_uint64_t	*del_appids;
	int		new_appids_num;
	int		del_appids_num;
}
zbx_lld_item_t;

static void	DBlld_clean_items(zbx_vector_ptr_t *items)
{
	zbx_lld_item_t	*item;

	while (0 != items->values_num)
	{
		item = (zbx_lld_item_t *)items->values[--items->values_num];

		zbx_free(item->key);
		zbx_free(item->name);
		zbx_free(item->snmp_oid);
		zbx_free(item->params);
		zbx_free(item->new_appids);
		zbx_free(item->del_appids);
		zbx_free(item);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_applications_by_itemid                                     *
 *                                                                            *
 * Purpose: retrieve applications for specified item                          *
 *                                                                            *
 * Parameters:  itemid - item identificator from database                     *
 *              appids - result buffer                                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBget_applications_by_itemid(zbx_uint64_t itemid,
		zbx_uint64_t **appids, int *appids_alloc, int *appids_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	applicationid;

	result = DBselect(
			"select applicationid"
			" from items_applications"
			" where itemid=" ZBX_FS_UI64,
			itemid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);
		uint64_array_add(appids, appids_alloc, appids_num, applicationid, 4);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_item_exists                                                *
 *                                                                            *
 * Purpose: check if item exists either in triggers list or in the database   *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             itemid - item identificator from database                      *
 *             key - new key descriptor with substituted macros               *
 *             items - list of already checked items                          *
 *                                                                            *
 * Return value: SUCCEED if item exists otherwise FAIL                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	DBlld_item_exists(zbx_uint64_t hostid, zbx_uint64_t itemid, const char *key, zbx_vector_ptr_t *items)
{
	char		*key_esc, *sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	DB_RESULT	result;
	int		i, res = FAIL;

	for (i = 0; i < items->values_num; i++)
	{
		if (0 == strcmp(key, ((zbx_lld_item_t *)items->values[i])->key))
			return SUCCEED;
	}

	sql = zbx_malloc(sql, sql_alloc);
	key_esc = DBdyn_escape_string_len(key, ITEM_KEY_LEN);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid"
			" from items"
			" where hostid=" ZBX_FS_UI64
				" and key_='%s'",
			hostid, key_esc);
	if (0 != itemid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and itemid<>" ZBX_FS_UI64, itemid);

	result = DBselect("%s", sql);

	if (NULL != DBfetch(result))
		res = SUCCEED;
	DBfree_result(result);

	zbx_free(key_esc);
	zbx_free(sql);

	return res;
}

static int	DBlld_make_item(zbx_uint64_t hostid, zbx_uint64_t parent_itemid, zbx_vector_ptr_t *items,
		const char *name_proto, const char *key_proto, unsigned char type, const char *params_proto,
		const char *snmp_oid_proto, struct zbx_json_parse *jp_row, char **error)
{
	const char	*__function_name = "DBlld_make_item";

	DB_RESULT	result;
	DB_ROW		row;
	char		*key_esc;
	int		new_appids_alloc = 0, del_appids_alloc = 0,
			res = SUCCEED;
	zbx_lld_item_t	*item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	item = zbx_calloc(NULL, 1, sizeof(zbx_lld_item_t));
	item->key = zbx_strdup(NULL, key_proto);
	substitute_key_macros(&item->key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

	key_esc = DBdyn_escape_string_len(item->key, ITEM_KEY_LEN);

	result = DBselect(
			"select distinct i.itemid"
			" from items i,item_discovery id"
			" where i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64
				" and i.key_='%s'",
			parent_itemid, key_esc);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(item->itemid, row[0]);
	DBfree_result(result);

	if (0 == item->itemid)
	{
		result = DBselect(
				"select distinct i.itemid,id.key_,i.key_"
				" from items i,item_discovery id"
				" where i.itemid=id.itemid"
					" and id.parent_itemid=" ZBX_FS_UI64,
				parent_itemid);

		while (NULL != (row = DBfetch(result)))
		{
			char	*old_key = NULL;

			old_key = zbx_strdup(old_key, row[1]);
			substitute_key_macros(&old_key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

			if (0 == strcmp(old_key, row[2]))
				ZBX_STR2UINT64(item->itemid, row[0]);

			zbx_free(old_key);

			if (0 != item->itemid)
				break;
		}
		DBfree_result(result);
	}

	if (SUCCEED == DBlld_item_exists(hostid, item->itemid, item->key, items))
	{
		*error = zbx_strdcatf(*error, "Cannot %s item [%s]: item already exists\n",
				0 != item->itemid ? "update" : "create", item->key);
		res = FAIL;
		goto out;
	}

	item->name = zbx_strdup(NULL, name_proto);
	substitute_discovery_macros(&item->name, jp_row);

	item->snmp_oid = zbx_strdup(NULL, snmp_oid_proto);
	substitute_key_macros(&item->snmp_oid, NULL, NULL, jp_row, MACRO_TYPE_SNMP_OID, NULL, 0);

	item->params = zbx_strdup(NULL, params_proto);
	if (ITEM_TYPE_DB_MONITOR == type || ITEM_TYPE_SSH == type ||
			ITEM_TYPE_TELNET == type || ITEM_TYPE_CALCULATED == type)
	{
		substitute_discovery_macros(&item->params, jp_row);
	}

	zbx_vector_ptr_append(items, item);

	DBget_applications_by_itemid(parent_itemid, &item->new_appids, &new_appids_alloc, &item->new_appids_num);

	if (0 != item->itemid)
	{
		DBget_applications_by_itemid(item->itemid, &item->del_appids, &del_appids_alloc, &item->del_appids_num);

		uint64_array_remove_both(item->new_appids, &item->new_appids_num,
				item->del_appids, &item->del_appids_num);
	}
out:
	if (FAIL == res)
	{
		zbx_free(item->key);
		zbx_free(item);
	}

	zbx_free(key_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

static void	DBlld_save_items(zbx_uint64_t hostid, zbx_vector_ptr_t *items, unsigned char type,
		unsigned char value_type, unsigned char data_type, int delay, const char *delay_flex_esc,
		int history, int trends, unsigned char status, const char *trapper_hosts_esc, const char *units_esc,
		int multiplier, int delta, const char *formula_esc, const char *logtimefmt_esc, zbx_uint64_t valuemapid,
		const char *ipmi_sensor_esc, const char *snmp_community_esc, const char *port_esc,
		const char *snmpv3_securityname_esc, unsigned char snmpv3_securitylevel,
		const char *snmpv3_authpassphrase_esc, const char *snmpv3_privpassphrase_esc, unsigned char authtype,
		const char *username_esc, const char *password_esc, const char *publickey_esc,
		const char *privatekey_esc, const char *description_esc, zbx_uint64_t interfaceid,
		zbx_uint64_t parent_itemid, const char *key_proto_esc, int lastcheck)
{
	int		i, j, new_items = 0, new_apps = 0;
	zbx_lld_item_t	*item;
	zbx_uint64_t	itemid = 0, itemdiscoveryid = 0, itemappid = 0;
	char		*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL,
			*name_esc, *key_esc, *snmp_oid_esc, *params_esc;
	size_t		sql1_alloc = 8 * ZBX_KIBIBYTE, sql1_offset = 0,
			sql2_alloc = 2 * ZBX_KIBIBYTE, sql2_offset = 0,
			sql3_alloc = 2 * ZBX_KIBIBYTE, sql3_offset = 0,
			sql4_alloc = 8 * ZBX_KIBIBYTE, sql4_offset = 0;
	const char	*ins_items_sql =
			"insert into items"
			" (itemid,name,key_,hostid,type,value_type,data_type,"
				"delay,delay_flex,history,trends,status,trapper_hosts,units,"
				"multiplier,delta,formula,logtimefmt,valuemapid,params,"
				"ipmi_sensor,snmp_community,snmp_oid,port,"
				"snmpv3_securityname,snmpv3_securitylevel,"
				"snmpv3_authpassphrase,snmpv3_privpassphrase,"
				"authtype,username,password,publickey,privatekey,"
				"description,interfaceid,flags)"
			" values ";
	const char	*ins_item_discovery_sql =
			"insert into item_discovery"
			" (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck)"
			" values ";
	const char	*ins_items_applications_sql =
			"insert into items_applications"
			" (itemappid,itemid,applicationid)"
			" values ";

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == item->itemid)
			new_items++;
		new_apps += item->new_appids_num;
	}

	if (0 != new_items)
	{
		itemid = DBget_maxid_num("items", new_items);
		itemdiscoveryid = DBget_maxid_num("item_discovery", new_items);

		sql1 = zbx_malloc(sql1, sql1_alloc);
		sql2 = zbx_malloc(sql2, sql2_alloc);
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBbegin_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_items_sql);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_item_discovery_sql);
#endif
	}

	if (0 != new_apps)
	{
		itemappid = DBget_maxid_num("items_applications", new_apps);

		sql3 = zbx_malloc(sql3, sql3_alloc);
		DBbegin_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_items_applications_sql);
#endif
	}

	if (new_items < items->values_num)
	{
		sql4 = zbx_malloc(sql4, sql4_alloc);
		DBbegin_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		name_esc = DBdyn_escape_string_len(item->name, ITEM_NAME_LEN);
		key_esc = DBdyn_escape_string_len(item->key, ITEM_KEY_LEN);
		snmp_oid_esc = DBdyn_escape_string_len(item->snmp_oid, ITEM_SNMP_OID_LEN);
		params_esc = DBdyn_escape_string_len(item->params, ITEM_PARAM_LEN);

		if (0 == item->itemid)
		{
			item->itemid = itemid++;
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_items_sql);
#endif
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"(" ZBX_FS_UI64 ",'%s','%s'," ZBX_FS_UI64 ",%d,%d,%d,%d,'%s',%d,%d,%d,'%s',"
						"'%s',%d,%d,'%s','%s',%s,'%s','%s','%s','%s','%s','%s',%d,'%s','%s',"
						"%d,'%s','%s','%s','%s','%s',%s,%d)" ZBX_ROW_DL,
					item->itemid, name_esc, key_esc, hostid, (int)type, (int)value_type,
					(int)data_type, delay, delay_flex_esc, history, trends, (int)status,
					trapper_hosts_esc, units_esc, multiplier, delta, formula_esc,
					logtimefmt_esc, DBsql_id_ins(valuemapid), params_esc, ipmi_sensor_esc,
					snmp_community_esc, snmp_oid_esc, port_esc, snmpv3_securityname_esc,
					(int)snmpv3_securitylevel, snmpv3_authpassphrase_esc,
					snmpv3_privpassphrase_esc, (int)authtype, username_esc, password_esc,
					publickey_esc, privatekey_esc, description_esc, DBsql_id_ins(interfaceid),
					ZBX_FLAG_DISCOVERY_CREATED);

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_item_discovery_sql);
#endif
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s',%d)" ZBX_ROW_DL,
					itemdiscoveryid, item->itemid, parent_itemid, key_proto_esc, lastcheck);

			itemdiscoveryid++;
		}
		else
		{
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update items"
					" set name='%s',"
						"key_='%s',"
						"type=%d,"
						"value_type=%d,"
						"data_type=%d,"
						"delay=%d,"
						"delay_flex='%s',"
						"history=%d,"
						"trends=%d,"
						"trapper_hosts='%s',"
						"units='%s',"
						"multiplier=%d,"
						"delta=%d,"
						"formula='%s',"
						"logtimefmt='%s',"
						"valuemapid=%s,"
						"params='%s',"
						"ipmi_sensor='%s',"
						"snmp_community='%s',"
						"snmp_oid='%s',"
						"port='%s',"
						"snmpv3_securityname='%s',"
						"snmpv3_securitylevel=%d,"
						"snmpv3_authpassphrase='%s',"
						"snmpv3_privpassphrase='%s',"
						"authtype=%d,"
						"username='%s',"
						"password='%s',"
						"publickey='%s',"
						"privatekey='%s',"
						"description='%s',"
						"interfaceid=%s,"
						"flags=%d"
					" where itemid=" ZBX_FS_UI64 ";\n",
					name_esc, key_esc, (int)type, (int)value_type, (int)data_type, delay,
					delay_flex_esc, history, trends, trapper_hosts_esc, units_esc,
					multiplier, delta, formula_esc, logtimefmt_esc,
					DBsql_id_ins(valuemapid), params_esc, ipmi_sensor_esc,
					snmp_community_esc, snmp_oid_esc, port_esc, snmpv3_securityname_esc,
					(int)snmpv3_securitylevel, snmpv3_authpassphrase_esc,
					snmpv3_privpassphrase_esc, (int)authtype, username_esc, password_esc,
					publickey_esc, privatekey_esc, description_esc, DBsql_id_ins(interfaceid),
					ZBX_FLAG_DISCOVERY_CREATED, item->itemid);

			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update item_discovery"
					" set key_='%s',"
						"lastcheck=%d,ts_delete=0"
					" where itemid=" ZBX_FS_UI64
						" and parent_itemid=" ZBX_FS_UI64 ";\n",
					key_proto_esc, lastcheck, item->itemid, parent_itemid);

			if (0 != item->del_appids_num)
			{
				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"delete from items_applications"
						" where itemid=" ZBX_FS_UI64
							" and",
						item->itemid);
				DBadd_condition_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"applicationid", item->del_appids, item->del_appids_num);
				zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ";\n");
			}
		}

		for (j = 0; j < item->new_appids_num; j++)
		{
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_items_applications_sql);
#endif
			zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")" ZBX_ROW_DL,
					itemappid, item->itemid, item->new_appids[j]);

			itemappid++;
		}

		zbx_free(params_esc);
		zbx_free(snmp_oid_esc);
		zbx_free(key_esc);
		zbx_free(name_esc);
	}

	if (0 != new_items)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql1_offset--;
		sql2_offset--;
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ";\n");
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
#endif
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBend_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
		DBexecute("%s", sql1);
		DBexecute("%s", sql2);
		zbx_free(sql1);
		zbx_free(sql2);
	}

	if (0 != new_apps)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql3_offset--;
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ";\n");
#endif
		DBend_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
		DBexecute("%s", sql3);
		zbx_free(sql3);
	}

	if (new_items < items->values_num)
	{
		DBend_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
		DBexecute("%s", sql4);
		zbx_free(sql4);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_update_items                                               *
 *                                                                            *
 * Purpose: add or update items for discovered items                          *
 *                                                                            *
 ******************************************************************************/
void	DBlld_update_items(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, struct zbx_json_parse *jp_data, char **error,
		const char *f_macro, const char *f_regexp, ZBX_REGEXP *regexps, int regexps_num, int lastcheck)
{
	const char		*__function_name = "DBlld_update_items";

	struct zbx_json_parse	jp_row;
	const char		*p;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&items);

	result = DBselect(
			"select i.itemid,i.name,i.key_,i.type,i.value_type,i.data_type,i.delay,i.delay_flex,"
				"i.history,i.trends,i.status,i.trapper_hosts,i.units,i.multiplier,i.delta,i.formula,"
				"i.logtimefmt,i.valuemapid,i.params,i.ipmi_sensor,i.snmp_community,i.snmp_oid,"
				"i.port,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,"
				"i.snmpv3_privpassphrase,i.authtype,i.username,i.password,i.publickey,i.privatekey,"
				"i.description,i.interfaceid"
			" from items i,item_discovery id"
			" where i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	parent_itemid, valuemapid, interfaceid;
		char		*key_proto_esc, *delay_flex_esc, *trapper_hosts_esc, *units_esc, *formula_esc,
				*logtimefmt_esc, *ipmi_sensor_esc, *snmp_community_esc, *port_esc,
				*snmpv3_securityname_esc, *snmpv3_authpassphrase_esc, *snmpv3_privpassphrase_esc,
				*username_esc, *password_esc, *publickey_esc, *privatekey_esc, *description_esc;
		const char	*name_proto, *key_proto, *params_proto, *snmp_oid_proto;
		unsigned char	type, value_type, data_type, status, snmpv3_securitylevel, authtype;
		int		delay, history, trends, multiplier, delta;

		ZBX_STR2UINT64(parent_itemid, row[0]);
		name_proto = row[1];
		key_proto = row[2];
		key_proto_esc = DBdyn_escape_string(key_proto);
		type = (unsigned char)atoi(row[3]);
		value_type = (unsigned char)atoi(row[4]);
		data_type = (unsigned char)atoi(row[5]);
		delay = atoi(row[6]);
		delay_flex_esc = DBdyn_escape_string(row[7]);
		history = atoi(row[8]);
		trends = atoi(row[9]);
		status = (unsigned char)atoi(row[10]);
		trapper_hosts_esc = DBdyn_escape_string(row[11]);
		units_esc = DBdyn_escape_string(row[12]);
		multiplier = atoi(row[13]);
		delta = atoi(row[14]);
		formula_esc = DBdyn_escape_string(row[15]);
		logtimefmt_esc = DBdyn_escape_string(row[16]);
		ZBX_DBROW2UINT64(valuemapid, row[17]);
		params_proto = row[18];
		ipmi_sensor_esc = DBdyn_escape_string(row[19]);
		snmp_community_esc = DBdyn_escape_string(row[20]);
		snmp_oid_proto = row[21];
		port_esc = DBdyn_escape_string(row[22]);
		snmpv3_securityname_esc = DBdyn_escape_string(row[23]);
		snmpv3_securitylevel = (unsigned char)atoi(row[24]);
		snmpv3_authpassphrase_esc = DBdyn_escape_string(row[25]);
		snmpv3_privpassphrase_esc = DBdyn_escape_string(row[26]);
		authtype = (unsigned char)atoi(row[27]);
		username_esc = DBdyn_escape_string(row[28]);
		password_esc = DBdyn_escape_string(row[29]);
		publickey_esc = DBdyn_escape_string(row[30]);
		privatekey_esc = DBdyn_escape_string(row[31]);
		description_esc = DBdyn_escape_string(row[32]);
		ZBX_DBROW2UINT64(interfaceid, row[33]);

		p = NULL;
		/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
		/*                      ^                                             */
		while (NULL != (p = zbx_json_next(jp_data, p)))
		{
			/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
			/*                      ^------------------^                          */
			if (FAIL == zbx_json_brackets_open(p, &jp_row))
				continue;

			if (SUCCEED != lld_check_record(&jp_row, f_macro, f_regexp, regexps, regexps_num))
				continue;

			DBlld_make_item(hostid, parent_itemid, &items, name_proto, key_proto, type,
					params_proto, snmp_oid_proto, &jp_row, error);
		}

		zbx_vector_ptr_sort(&items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBlld_save_items(hostid, &items, type, value_type, data_type, delay, delay_flex_esc, history, trends,
				status, trapper_hosts_esc, units_esc, multiplier, delta, formula_esc, logtimefmt_esc,
				valuemapid, ipmi_sensor_esc, snmp_community_esc, port_esc, snmpv3_securityname_esc,
				snmpv3_securitylevel, snmpv3_authpassphrase_esc, snmpv3_privpassphrase_esc, authtype,
				username_esc, password_esc, publickey_esc, privatekey_esc, description_esc,
				interfaceid, parent_itemid, key_proto_esc, lastcheck);

		zbx_free(description_esc);
		zbx_free(privatekey_esc);
		zbx_free(publickey_esc);
		zbx_free(password_esc);
		zbx_free(username_esc);
		zbx_free(snmpv3_privpassphrase_esc);
		zbx_free(snmpv3_authpassphrase_esc);
		zbx_free(snmpv3_securityname_esc);
		zbx_free(port_esc);
		zbx_free(snmp_community_esc);
		zbx_free(ipmi_sensor_esc);
		zbx_free(logtimefmt_esc);
		zbx_free(formula_esc);
		zbx_free(units_esc);
		zbx_free(trapper_hosts_esc);
		zbx_free(delay_flex_esc);
		zbx_free(key_proto_esc);

		DBlld_clean_items(&items);
	}
	DBfree_result(result);

	zbx_vector_ptr_destroy(&items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
