/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	zbx_uint64_t		itemid;
#define ZBX_FLAG_LLD_ITEM_UNSET				__UINT64_C(0x0000000000000000)
#define ZBX_FLAG_LLD_ITEM_DISCOVERED			__UINT64_C(0x0000000000000001)
#define ZBX_FLAG_LLD_ITEM_UPDATE_NAME			__UINT64_C(0x0000000000000002)
#define ZBX_FLAG_LLD_ITEM_UPDATE_KEY			__UINT64_C(0x0000000000000004)
#define ZBX_FLAG_LLD_ITEM_UPDATE_TYPE			__UINT64_C(0x0000000000000008)
#define ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE		__UINT64_C(0x0000000000000010)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DATA_TYPE		__UINT64_C(0x0000000000000020)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DELAY			__UINT64_C(0x0000000000000040)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DELAY_FLEX		__UINT64_C(0x0000000000000080)
#define ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY		__UINT64_C(0x0000000000000100)
#define ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS			__UINT64_C(0x0000000000000200)
#define ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS		__UINT64_C(0x0000000000000400)
#define ZBX_FLAG_LLD_ITEM_UPDATE_UNITS			__UINT64_C(0x0000000000000800)
#define ZBX_FLAG_LLD_ITEM_UPDATE_MULTIPLIER		__UINT64_C(0x0000000000001000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DELTA			__UINT64_C(0x0000000000002000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA		__UINT64_C(0x0000000000004000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT		__UINT64_C(0x0000000000008000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID		__UINT64_C(0x0000000000010000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS			__UINT64_C(0x0000000000020000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR		__UINT64_C(0x0000000000040000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_COMMUNITY		__UINT64_C(0x0000000000080000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID		__UINT64_C(0x0000000000100000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PORT			__UINT64_C(0x0000000000200000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYNAME	__UINT64_C(0x0000000000400000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYLEVEL	__UINT64_C(0x0000000000800000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE	__UINT64_C(0x0000000001000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE	__UINT64_C(0x0000000002000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE		__UINT64_C(0x0000000004000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME		__UINT64_C(0x0000000008000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD		__UINT64_C(0x0000000010000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY		__UINT64_C(0x0000000020000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY		__UINT64_C(0x0000000040000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION		__UINT64_C(0x0000000080000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID		__UINT64_C(0x0000000100000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE										\
		(ZBX_FLAG_LLD_ITEM_UPDATE_NAME | ZBX_FLAG_LLD_ITEM_UPDATE_KEY | ZBX_FLAG_LLD_ITEM_UPDATE_TYPE |	\
		ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE | ZBX_FLAG_LLD_ITEM_UPDATE_DATA_TYPE |			\
		ZBX_FLAG_LLD_ITEM_UPDATE_DELAY | ZBX_FLAG_LLD_ITEM_UPDATE_DELAY_FLEX |				\
		ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY | ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS |				\
		ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS | ZBX_FLAG_LLD_ITEM_UPDATE_UNITS |			\
		ZBX_FLAG_LLD_ITEM_UPDATE_MULTIPLIER | ZBX_FLAG_LLD_ITEM_UPDATE_DELTA |				\
		ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA | ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT |			\
		ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID | ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS |				\
		ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR | ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_COMMUNITY |		\
		ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID | ZBX_FLAG_LLD_ITEM_UPDATE_PORT |				\
		ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYNAME | ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYLEVEL |	\
		ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE |						\
		ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE |						\
		ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE | ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME |				\
		ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD | ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY |			\
		ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY | ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION |			\
		ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID)
	zbx_uint64_t		flags;
	char			*key_proto;
	char			*name;
	char			*name_orig;
	char			*key;
	char			*key_orig;
	char			*params;
	char			*params_orig;
	char			*snmp_oid;
	char			*snmp_oid_orig;
	zbx_vector_uint64_t	new_applicationids;
	int			lastcheck;
	int			ts_delete;
	struct zbx_json_parse	*jp_row;
}
zbx_lld_item_t;

static void	lld_items_free(zbx_vector_ptr_t *items)
{
	zbx_lld_item_t	*item;

	while (0 != items->values_num)
	{
		item = (zbx_lld_item_t *)items->values[--items->values_num];

		zbx_free(item->key_proto);
		zbx_free(item->name);
		zbx_free(item->name_orig);
		zbx_free(item->key);
		zbx_free(item->key_orig);
		zbx_free(item->params);
		zbx_free(item->params_orig);
		zbx_free(item->snmp_oid);
		zbx_free(item->snmp_oid_orig);
		zbx_vector_uint64_destroy(&item->new_applicationids);
		zbx_free(item);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_get                                                    *
 *                                                                            *
 * Purpose: retrieves existing items for the specified item prototype         *
 *                                                                            *
 * Parameters: parent_itemid - [IN] item prototype identificator              *
 *             items         - [OUT] list of items                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(zbx_uint64_t parent_itemid, zbx_vector_ptr_t *items,
		unsigned char type, unsigned char value_type, unsigned char data_type, int delay,
		const char *delay_flex, int history, int trends, const char *trapper_hosts, const char *units,
		unsigned char multiplier, unsigned char delta, const char *formula, const char *logtimefmt,
		zbx_uint64_t valuemapid, const char *ipmi_sensor, const char *snmp_community, const char *port,
		const char *snmpv3_securityname, unsigned char snmpv3_securitylevel, const char *snmpv3_authpassphrase,
		const char *snmpv3_privpassphrase, unsigned char authtype, const char *username, const char *password,
		const char *publickey, const char *privatekey, const char *description, zbx_uint64_t interfaceid)
{
	const char	*__function_name = "lld_items_get";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_lld_item_t	*item;
	zbx_uint64_t	db_valuemapid, db_interfaceid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select id.itemid,id.key_,id.lastcheck,id.ts_delete,i.name,i.key_,i.type,i.value_type,"
				"i.data_type,i.delay,i.delay_flex,i.history,i.trends,i.trapper_hosts,i.units,"
				"i.multiplier,i.delta,i.formula,i.logtimefmt,i.valuemapid,i.params,i.ipmi_sensor,"
				"i.snmp_community,i.snmp_oid,i.port,i.snmpv3_securityname,i.snmpv3_securitylevel,"
				"i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.authtype,i.username,i.password,"
				"i.publickey,i.privatekey,i.description,i.interfaceid"
			" from item_discovery id"
				" join items i"
					" on id.itemid=i.itemid"
			" where id.parent_itemid=" ZBX_FS_UI64,
			parent_itemid);

	while (NULL != (row = DBfetch(result)))
	{
		item = zbx_malloc(NULL, sizeof(zbx_lld_item_t));

		ZBX_STR2UINT64(item->itemid, row[0]);
		item->key_proto = zbx_strdup(NULL, row[1]);
		item->lastcheck = atoi(row[2]);
		item->ts_delete = atoi(row[3]);
		item->name = zbx_strdup(NULL, row[4]);
		item->name_orig = NULL;
		item->key = zbx_strdup(NULL, row[5]);
		item->key_orig = NULL;
		item->flags = ZBX_FLAG_LLD_ITEM_UNSET;

		if ((unsigned char)atoi(row[6]) != type)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TYPE;

		if ((unsigned char)atoi(row[7]) != value_type)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE;

		if ((unsigned char)atoi(row[8]) != data_type)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DATA_TYPE;

		if (atoi(row[9]) != delay)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DELAY;

		if (0 != strcmp(row[10], delay_flex))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DELAY_FLEX;

		if (atoi(row[11]) != history)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY;

		if (atoi(row[12]) != trends)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS;

		if (0 != strcmp(row[13], trapper_hosts))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS;

		if (0 != strcmp(row[14], units))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_UNITS;

		if ((unsigned char)atoi(row[15]) != multiplier)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_MULTIPLIER;

		if ((unsigned char)atoi(row[16]) != delta)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DELTA;

		if (0 != strcmp(row[17], formula))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA;

		if (0 != strcmp(row[18], logtimefmt))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT;

		ZBX_DBROW2UINT64(db_valuemapid, row[19]);
		if (db_valuemapid != valuemapid)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID;

		item->params = zbx_strdup(NULL, row[20]);
		item->params_orig = NULL;

		if (0 != strcmp(row[21], ipmi_sensor))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR;

		if (0 != strcmp(row[22], snmp_community))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_COMMUNITY;

		item->snmp_oid = zbx_strdup(NULL, row[23]);
		item->snmp_oid_orig = NULL;

		if (0 != strcmp(row[24], port))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PORT;

		if (0 != strcmp(row[25], snmpv3_securityname))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYNAME;

		if ((unsigned char)atoi(row[26]) != snmpv3_securitylevel)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYLEVEL;

		if (0 != strcmp(row[27], snmpv3_authpassphrase))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE;

		if (0 != strcmp(row[28], snmpv3_privpassphrase))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE;

		if ((unsigned char)atoi(row[29]) != authtype)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE;

		if (0 != strcmp(row[30], username))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME;

		if (0 != strcmp(row[31], password))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD;

		if (0 != strcmp(row[32], publickey))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY;

		if (0 != strcmp(row[33], privatekey))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY;

		if (0 != strcmp(row[34], description))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION;

		ZBX_DBROW2UINT64(db_interfaceid, row[35]);
		if (db_interfaceid != interfaceid)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID;

		zbx_vector_uint64_create(&item->new_applicationids);

		item->jp_row = NULL;

		zbx_vector_ptr_append(items, item);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_applications_get                                             *
 *                                                                            *
 * Purpose: retrieve list of application which should be assigned to the each *
 *          discovered item                                                   *
 *                                                                            *
 * Parameters: parent_itemid  - [IN] item prototype id                        *
 *             applicationids - [OUT] sorted list of applications             *
 *                                                                            *
 ******************************************************************************/
static void	lld_applications_get(zbx_uint64_t parent_itemid, zbx_vector_uint64_t *applicationids)
{
	const char	*__function_name = "lld_applications_get";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	applicationid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select applicationid"
			" from items_applications"
			" where itemid=" ZBX_FS_UI64,
			parent_itemid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);
		zbx_vector_uint64_append(applicationids, applicationid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(applicationids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values_num:%d", __function_name, applicationids->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_validate_item_field                                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_validate_item_field(zbx_lld_item_t *item, char **field, char **field_orig, zbx_uint64_t flag,
		size_t field_len, char **error)
{
	if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
		return;

	/* only new items or items with a new data will be validated */
	if (0 != item->itemid && 0 == (item->flags & flag))
		return;

	if (SUCCEED != zbx_is_utf8(*field))
	{
		zbx_replace_invalid_utf8(*field);
		*error = zbx_strdcatf(*error, "Cannot %s item: value \"%s\" has invalid UTF-8 sequence.\n",
				(0 != item->itemid ? "update" : "create"), *field);
	}
	else if (zbx_strlen_utf8(*field) > field_len)
	{
		*error = zbx_strdcatf(*error, "Cannot %s item: value \"%s\" is too long.\n",
				(0 != item->itemid ? "update" : "create"), *field);
	}
	else
		return;

	if (0 != item->itemid)
		lld_field_str_rollback(field, field_orig, &item->flags, flag);
	else
		item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_validate                                               *
 *                                                                            *
 * Parameters: items - [IN] list of items; should be sorted by itemid         *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_validate(zbx_uint64_t hostid, zbx_vector_ptr_t *items, char **error)
{
	const char		*__function_name = "lld_items_validate";

	char			*keys = NULL, *key_esc;
	size_t			keys_alloc = 256, keys_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_lld_item_t		*item, *item_b;
	zbx_vector_uint64_t	itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&itemids);

	keys = zbx_malloc(keys, keys_alloc);	/* list of item keys */

	/* check an item name validity */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		lld_validate_item_field(item, &item->name, &item->name_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_NAME, ITEM_NAME_LEN, error);
		lld_validate_item_field(item, &item->key, &item->key_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_KEY, ITEM_KEY_LEN, error);
		lld_validate_item_field(item, &item->params, &item->params_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS, ITEM_PARAM_LEN, error);
		lld_validate_item_field(item, &item->snmp_oid, &item->snmp_oid_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID, ITEM_SNMP_OID_LEN, error);
	}

	/* checking duplicated item keys */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		/* only new items or items with a new key will be validated */
		if (0 != item->itemid && 0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			continue;

		for (j = 0; j < items->values_num; j++)
		{
			item_b = (zbx_lld_item_t *)items->values[j];

			if (0 == (item_b->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(item->key, item_b->key))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s item:"
						" item with the same key \"%s\" already exists.\n",
						(0 != item->itemid ? "update" : "create"), item->key);

			if (0 != item->itemid)
			{
				lld_field_str_rollback(&item->key, &item->key_orig, &item->flags,
						ZBX_FLAG_LLD_ITEM_UPDATE_KEY);
			}
			else
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
			break;
		}
	}

	/* checking duplicated keys in DB */

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 != item->itemid)
			zbx_vector_uint64_append(&itemids, item->itemid);

		if (0 != item->itemid && 0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			continue;

		key_esc = DBdyn_escape_string(item->key);

		if (0 != keys_offset)
			zbx_chrcpy_alloc(&keys, &keys_alloc, &keys_offset, ',');
		zbx_chrcpy_alloc(&keys, &keys_alloc, &keys_offset, '\'');
		zbx_strcpy_alloc(&keys, &keys_alloc, &keys_offset, key_esc);
		zbx_chrcpy_alloc(&keys, &keys_alloc, &keys_offset, '\'');

		zbx_free(key_esc);
	}

	if (0 != keys_offset)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 256, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select key_"
				" from items"
				" where hostid=" ZBX_FS_UI64
					" and key_ in (%s)",
				hostid, keys);
		if (0 != itemids.values_num)
		{
			zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid",
					itemids.values, itemids.values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < items->values_num; i++)
			{
				item = (zbx_lld_item_t *)items->values[i];

				if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
					continue;

				if (0 == strcmp(item->key, row[0]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s item:"
							" item with the same key \"%s\" already exists.\n",
							(0 != item->itemid ? "update" : "create"), item->key);

					if (0 != item->itemid)
					{
						lld_field_str_rollback(&item->key, &item->key_orig, &item->flags,
								ZBX_FLAG_LLD_ITEM_UPDATE_KEY);
					}
					else
						item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;

					continue;
				}
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_free(keys);

	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	lld_item_make(zbx_vector_ptr_t *items, const char *name_proto, const char *key_proto,
		const char *params_proto, const char *snmp_oid_proto, struct zbx_json_parse *jp_row)
{
	const char	*__function_name = "lld_make_item";

	char		*buffer = NULL;
	int		i;
	zbx_lld_item_t	*item = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		buffer = zbx_strdup(buffer, item->key_proto);
		substitute_key_macros(&buffer, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

		if (0 == strcmp(item->key, buffer))
			break;
	}

	if (i == items->values_num)	/* no item found */
	{
		item = zbx_malloc(NULL, sizeof(zbx_lld_item_t));

		item->itemid = 0;
		item->lastcheck = 0;
		item->ts_delete = 0;
		item->key_proto = NULL;

		item->name = zbx_strdup(NULL, name_proto);
		item->name_orig = NULL;
		substitute_discovery_macros(&item->name, jp_row);
		zbx_lrtrim(item->name, ZBX_WHITESPACE);

		item->key = zbx_strdup(NULL, key_proto);
		item->key_orig = NULL;
		substitute_key_macros(&item->key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

		item->params = zbx_strdup(NULL, params_proto);
		item->params_orig = NULL;
		substitute_discovery_macros(&item->params, jp_row);
		zbx_lrtrim(item->params, ZBX_WHITESPACE);

		item->snmp_oid = zbx_strdup(NULL, snmp_oid_proto);
		item->snmp_oid_orig = NULL;
		substitute_key_macros(&item->snmp_oid, NULL, NULL, jp_row, MACRO_TYPE_SNMP_OID, NULL, 0);
		zbx_lrtrim(item->snmp_oid, ZBX_WHITESPACE);

		zbx_vector_uint64_create(&item->new_applicationids);
		item->flags = ZBX_FLAG_LLD_ITEM_DISCOVERED;

		zbx_vector_ptr_append(items, item);
	}
	else
	{
		buffer = zbx_strdup(buffer, name_proto);
		substitute_discovery_macros(&buffer, jp_row);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(item->name, buffer))
		{
			item->name_orig = item->name;
			item->name = buffer;
			buffer = NULL;
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_NAME;
		}

		if (0 != strcmp(item->key_proto, key_proto))
		{
			item->key_orig = item->key;
			item->key = zbx_strdup(NULL, key_proto);
			substitute_key_macros(&item->key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_KEY;
		}

		buffer = zbx_strdup(buffer, params_proto);
		substitute_discovery_macros(&buffer, jp_row);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(item->params, buffer))
		{
			item->params_orig = item->params;
			item->params = buffer;
			buffer = NULL;
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS;
		}

		buffer = zbx_strdup(buffer, snmp_oid_proto);
		substitute_key_macros(&buffer, NULL, NULL, jp_row, MACRO_TYPE_SNMP_OID, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(item->snmp_oid, buffer))
		{
			item->snmp_oid_orig = item->snmp_oid;
			item->snmp_oid = buffer;
			buffer = NULL;
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID;
		}

		item->flags |= ZBX_FLAG_LLD_ITEM_DISCOVERED;
	}

	item->jp_row = jp_row;

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	lld_items_make(zbx_vector_ptr_t *items, const char *name_proto, const char *key_proto,
		const char *params_proto, const char *snmp_oid_proto, zbx_vector_ptr_t *lld_rows)
{
	int	i;

	for (i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[i];

		lld_item_make(items, name_proto, key_proto, params_proto, snmp_oid_proto, &lld_row->jp_row);
	}

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_applications_make                                            *
 *                                                                            *
 * Parameters: parent_itemid  - [IN] item prototype id                        *
 *             items          - [IN/OUT] sorted list of items                 *
 *             del_itemappids - [OUT] items_applications which should be      *
 *                                    deleted                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_applications_make(zbx_uint64_t parent_itemid, zbx_vector_ptr_t *items,
		zbx_vector_uint64_t *del_itemappids)
{
	const char		*__function_name = "lld_applications_make";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	applicationids, itemids;
	zbx_uint64_t		itemappid, applicationid, itemid;
	zbx_lld_item_t		*item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&applicationids);
	zbx_vector_uint64_create(&itemids);

	lld_applications_get(parent_itemid, &applicationids);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		zbx_vector_uint64_reserve(&item->new_applicationids, applicationids.values_num);
		for (j = 0; j < applicationids.values_num; j++)
			zbx_vector_uint64_append(&item->new_applicationids, applicationids.values[j]);

		if (0 != item->itemid)
			zbx_vector_uint64_append(&itemids, item->itemid);
	}

	if (0 != itemids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 256, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemappid,applicationid,itemid"
				" from items_applications"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(applicationid, row[1]);
			ZBX_STR2UINT64(itemid, row[2]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(items, &itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = (zbx_lld_item_t *)items->values[i];

			if (FAIL == (i = zbx_vector_uint64_bsearch(&item->new_applicationids, applicationid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* item applications which should be deleted */
				ZBX_STR2UINT64(itemappid, row[0]);
				zbx_vector_uint64_append(del_itemappids, itemappid);
			}
			else
			{
				/* item applications which are already added */
				zbx_vector_uint64_remove(&item->new_applicationids, i);
			}
		}
		DBfree_result(result);

		zbx_vector_uint64_sort(del_itemappids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_uint64_destroy(&applicationids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_save                                                   *
 *                                                                            *
 * Parameters: hostid         - [IN] parent host id                           *
 *             parent_itemid  - [IN] item prototype id                        *
 *             status         - [IN] initial item satatus                     *
 *             del_itemappids - [IN] items_applications which should be       *
 *                                   deleted                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_save(zbx_uint64_t hostid, zbx_uint64_t parent_itemid, zbx_vector_ptr_t *items,
		const char *key_proto, unsigned char type, unsigned char value_type, unsigned char data_type, int delay,
		const char *delay_flex, int history, int trends, unsigned char status, const char *trapper_hosts,
		const char *units, unsigned char multiplier, unsigned char delta, const char *formula,
		const char *logtimefmt, zbx_uint64_t valuemapid, const char *ipmi_sensor, const char *snmp_community,
		const char *port, const char *snmpv3_securityname, unsigned char snmpv3_securitylevel,
		const char *snmpv3_authpassphrase, const char *snmpv3_privpassphrase, unsigned char authtype,
		const char *username, const char *password, const char *publickey, const char *privatekey,
		const char *description, zbx_uint64_t interfaceid, zbx_vector_uint64_t *del_itemappids)
{
	const char	*__function_name = "lld_items_save";

	int		i, j, new_items = 0, upd_items = 0, new_applications = 0;
	zbx_lld_item_t	*item;
	zbx_uint64_t	itemid = 0, itemdiscoveryid = 0, itemappid = 0, flags = ZBX_FLAG_LLD_ITEM_UNSET;
	char		*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL,
			*key_proto_esc = NULL, *delay_flex_esc = NULL, *trapper_hosts_esc = NULL, *units_esc = NULL,
			*formula_esc = NULL, *logtimefmt_esc = NULL, *ipmi_sensor_esc = NULL,
			*snmp_community_esc = NULL, *port_esc = NULL, *snmpv3_securityname_esc = NULL,
			*snmpv3_authpassphrase_esc = NULL, *snmpv3_privpassphrase_esc = NULL, *username_esc = NULL,
			*password_esc = NULL, *publickey_esc = NULL, *privatekey_esc = NULL, *description_esc = NULL,
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
			"insert into item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) values ";
	const char	*ins_items_applications_sql =
			"insert into items_applications (itemappid,itemid,applicationid) values ";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == item->itemid)
		{
			new_items++;
		}
		else if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE))
		{
			upd_items++;
			flags |= item->flags;
		}

		new_applications += item->new_applicationids.values_num;
	}

	if (0 == new_items && 0 == new_applications && 0 == upd_items && 0 == del_itemappids->values_num)
		goto out;

	DBbegin();

	if (SUCCEED != DBlock_hostid(hostid))
	{
		/* the host was removed while processing lld rule */
		DBrollback();
		goto out;
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
		flags |= ZBX_FLAG_LLD_ITEM_UPDATE;
	}

	if (0 != new_applications)
	{
		itemappid = DBget_maxid_num("items_applications", new_applications);

		sql3 = zbx_malloc(sql3, sql3_alloc);
		DBbegin_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_items_applications_sql);
#endif
	}

	if (0 != upd_items || 0 != del_itemappids->values_num)
	{
		sql4 = zbx_malloc(sql4, sql4_alloc);
		DBbegin_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
	}

	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
		key_proto_esc = DBdyn_escape_string(key_proto);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELAY_FLEX))
		delay_flex_esc = DBdyn_escape_string(delay_flex);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS))
		trapper_hosts_esc = DBdyn_escape_string(trapper_hosts);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_UNITS))
		units_esc = DBdyn_escape_string(units);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA))
		formula_esc = DBdyn_escape_string(formula);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT))
		logtimefmt_esc = DBdyn_escape_string(logtimefmt);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR))
		ipmi_sensor_esc = DBdyn_escape_string(ipmi_sensor);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_COMMUNITY))
		snmp_community_esc = DBdyn_escape_string(snmp_community);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_PORT))
		port_esc = DBdyn_escape_string(port);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYNAME))
		snmpv3_securityname_esc = DBdyn_escape_string(snmpv3_securityname);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE))
		snmpv3_authpassphrase_esc = DBdyn_escape_string(snmpv3_authpassphrase);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE))
		snmpv3_privpassphrase_esc = DBdyn_escape_string(snmpv3_privpassphrase);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME))
		username_esc = DBdyn_escape_string(username);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD))
		password_esc = DBdyn_escape_string(password);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY))
		publickey_esc = DBdyn_escape_string(publickey);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY))
		privatekey_esc = DBdyn_escape_string(privatekey);
	if (0 != (flags & ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION))
		description_esc = DBdyn_escape_string(description);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == item->itemid)
		{
			item->itemid = itemid++;
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_items_sql);
#endif
			name_esc = DBdyn_escape_string(item->name);
			key_esc = DBdyn_escape_string(item->key);
			params_esc = DBdyn_escape_string(item->params);
			snmp_oid_esc = DBdyn_escape_string(item->snmp_oid);

			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"(" ZBX_FS_UI64 ",'%s','%s'," ZBX_FS_UI64 ",%d,%d,%d,%d,'%s',%d,%d,%d,'%s',"
						"'%s',%d,%d,'%s','%s',%s,'%s','%s','%s','%s','%s','%s',%d,'%s','%s',"
						"%d,'%s','%s','%s','%s','%s',%s,%d)" ZBX_ROW_DL,
					item->itemid, name_esc, key_esc, hostid, (int)type, (int)value_type,
					(int)data_type, delay, delay_flex_esc, history, trends, (int)status,
					trapper_hosts_esc, units_esc, (int)multiplier, (int)delta, formula_esc,
					logtimefmt_esc, DBsql_id_ins(valuemapid), params_esc, ipmi_sensor_esc,
					snmp_community_esc, snmp_oid_esc, port_esc, snmpv3_securityname_esc,
					(int)snmpv3_securitylevel, snmpv3_authpassphrase_esc,
					snmpv3_privpassphrase_esc, (int)authtype, username_esc, password_esc,
					publickey_esc, privatekey_esc, description_esc, DBsql_id_ins(interfaceid),
					ZBX_FLAG_DISCOVERY_CREATED);

			zbx_free(snmp_oid_esc);
			zbx_free(params_esc);
			zbx_free(key_esc);
			zbx_free(name_esc);

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_item_discovery_sql);
#endif
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')" ZBX_ROW_DL,
					itemdiscoveryid++, item->itemid, parent_itemid, key_proto_esc);
		}
		else
		{
			if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, "update items set ");
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_NAME))
				{
					name_esc = DBdyn_escape_string(item->name);
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "name='%s'", name_esc);
					zbx_free(name_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
				{
					key_esc = DBdyn_escape_string(item->key);
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%skey_='%s'", d, key_esc);
					zbx_free(key_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%stype=%d", d, (int)type);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%svalue_type=%d",
							d, (int)value_type);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DATA_TYPE))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sdata_type=%d",
							d, (int)data_type);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELAY))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sdelay=%d", d, delay);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELAY_FLEX))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sdelay_flex='%s'",
							d, delay_flex_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%shistory=%d",
							d, history);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%strends=%d", d, trends);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%strapper_hosts='%s'",
							d, trapper_hosts_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_UNITS))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sunits='%s'",
							d, units_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_MULTIPLIER))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%smultiplier=%d",
							d, (int)multiplier);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELTA))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sdelta=%d",
							d, (int)delta);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sformula='%s'",
							d, formula_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%slogtimefmt='%s'",
							d, logtimefmt_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%svaluemapid=%s",
							d, DBsql_id_ins(valuemapid));
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS))
				{
					params_esc = DBdyn_escape_string(item->params);
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sparams='%s'",
							d, params_esc);
					zbx_free(params_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sipmi_sensor='%s'",
							d, ipmi_sensor_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_COMMUNITY))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%ssnmp_community='%s'",
							d, snmp_community_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID))
				{
					snmp_oid_esc = DBdyn_escape_string(item->snmp_oid);
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%ssnmp_oid='%s'",
							d, snmp_oid_esc);
					zbx_free(snmp_oid_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PORT))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sport='%s'",
							d, port_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYNAME))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
							"%ssnmpv3_securityname='%s'", d, snmpv3_securityname_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYLEVEL))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
							"%ssnmpv3_securitylevel=%d", d, (int)snmpv3_securitylevel);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
							"%ssnmpv3_authpassphrase='%s'", d, snmpv3_authpassphrase_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
							"%ssnmpv3_privpassphrase='%s'", d, snmpv3_privpassphrase_esc);
					d = ",";
				}

				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sauthtype=%d",
							d, (int)authtype);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%susername='%s'",
							d, username_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%spassword='%s'",
							d, password_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%spublickey='%s'",
							d, publickey_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sprivatekey='%s'",
							d, privatekey_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sdescription='%s'",
							d, description_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID))
				{
					zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, "%sinterfaceid=%s",
							d, DBsql_id_ins(interfaceid));
				}

				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset, " where itemid=" ZBX_FS_UI64 ";\n",
						item->itemid);
			}

			if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			{
				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"update item_discovery"
						" set key_='%s'"
						" where itemid=" ZBX_FS_UI64 ";\n",
						key_proto_esc, item->itemid);
			}
		}

		for (j = 0; j < item->new_applicationids.values_num; j++)
		{
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_items_applications_sql);
#endif
			zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")" ZBX_ROW_DL,
					itemappid++, item->itemid, item->new_applicationids.values[j]);
		}
	}

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

	if (0 != new_applications)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql3_offset--;
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ";\n");
#endif
		DBend_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
		DBexecute("%s", sql3);
		zbx_free(sql3);
	}

	if (0 != upd_items || 0 != del_itemappids->values_num)
	{
		if (0 != del_itemappids->values_num)
		{
			zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, "delete from items_applications where");
			DBadd_condition_alloc(&sql4, &sql4_alloc, &sql4_offset, "itemappid",
					del_itemappids->values, del_itemappids->values_num);
			zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ";\n");
		}

		DBend_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
		DBexecute("%s", sql4);
		zbx_free(sql4);
	}

	DBcommit();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_remove_lost_resources                                        *
 *                                                                            *
 * Purpose: updates item_discovery.lastcheck and item_discovery.ts_delete     *
 *          fields; removes lost resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_remove_lost_resources(zbx_vector_ptr_t *items, unsigned short lifetime, int lastcheck)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_lld_item_t		*item;
	zbx_vector_uint64_t	del_itemids, lc_itemids, ts_itemids;
	int			i, lifetime_sec;

	if (0 == items->values_num)
		return;

	lifetime_sec = lifetime * SEC_PER_DAY;

	zbx_vector_uint64_create(&del_itemids);
	zbx_vector_uint64_create(&lc_itemids);
	zbx_vector_uint64_create(&ts_itemids);

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == item->itemid)
			continue;

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
		{
			if (item->lastcheck < lastcheck - lifetime_sec)
			{
				zbx_vector_uint64_append(&del_itemids, item->itemid);
			}
			else if (item->ts_delete != item->lastcheck + lifetime_sec)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update item_discovery"
						" set ts_delete=%d"
						" where itemid=" ZBX_FS_UI64 ";\n",
						item->lastcheck + lifetime_sec, item->itemid);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_itemids, item->itemid);
			if (0 != item->ts_delete)
				zbx_vector_uint64_append(&ts_itemids, item->itemid);
		}
	}

	if (0 != lc_itemids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update item_discovery set lastcheck=%d where",
				lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid",
				lc_itemids.values, lc_itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != ts_itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_discovery set ts_delete=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid",
				ts_itemids.values, ts_itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		DBbegin();

		DBexecute("%s", sql);

		DBcommit();
	}

	zbx_free(sql);

	zbx_vector_uint64_sort(&del_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != del_itemids.values_num)
	{
		DBbegin();

		DBdelete_items(&del_itemids);

		DBcommit();
	}

	zbx_vector_uint64_destroy(&ts_itemids);
	zbx_vector_uint64_destroy(&lc_itemids);
	zbx_vector_uint64_destroy(&del_itemids);
}

static void	lld_item_links_populate(zbx_vector_ptr_t *lld_rows, zbx_uint64_t parent_itemid,
		zbx_vector_ptr_t *items)
{
	int	i, j;

	for (i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[i];

		for (j = 0; j < items->values_num; j++)
		{
			zbx_lld_item_t		*item = (zbx_lld_item_t *)items->values[j];
			zbx_lld_item_link_t	*item_link;

			if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
				continue;

			if (item->jp_row != &lld_row->jp_row)
				continue;

			item_link = (zbx_lld_item_link_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_link_t));

			item_link->parent_itemid = parent_itemid;
			item_link->itemid = item->itemid;

			zbx_vector_ptr_append(&lld_row->item_links, item_link);

			break;
		}
	}
}

static void	lld_item_links_sort(zbx_vector_ptr_t *lld_rows)
{
	int	i;

	for (i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[i];

		zbx_vector_ptr_sort(&lld_row->item_links, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: lld_update_items                                                 *
 *                                                                            *
 * Purpose: add or update discovered items                                    *
 *                                                                            *
 ******************************************************************************/
void	lld_update_items(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *lld_rows, char **error,
		unsigned short lifetime, int lastcheck)
{
	const char		*__function_name = "lld_update_items";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	items;
	zbx_vector_uint64_t	del_itemappids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&items);
	zbx_vector_uint64_create(&del_itemappids);

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
		const char	*name_proto, *key_proto, *params_proto, *snmp_oid_proto, *delay_flex, *trapper_hosts,
				*units, *formula, *logtimefmt, *ipmi_sensor, *snmp_community, *port,
				*snmpv3_securityname, *snmpv3_authpassphrase, *snmpv3_privpassphrase, *username,
				*password, *publickey, *privatekey, *description;
		unsigned char	type, value_type, data_type, status, multiplier, delta, snmpv3_securitylevel, authtype;
		int		delay, history, trends;

		ZBX_STR2UINT64(parent_itemid, row[0]);
		name_proto = row[1];
		key_proto = row[2];
		ZBX_STR2UCHAR(type, row[3]);
		ZBX_STR2UCHAR(value_type, row[4]);
		ZBX_STR2UCHAR(data_type, row[5]);
		delay = atoi(row[6]);
		delay_flex = row[7];
		history = atoi(row[8]);
		trends = atoi(row[9]);
		ZBX_STR2UCHAR(status, row[10]);
		trapper_hosts = row[11];
		units = row[12];
		ZBX_STR2UCHAR(multiplier, row[13]);
		ZBX_STR2UCHAR(delta, row[14]);
		formula = row[15];
		logtimefmt = row[16];
		ZBX_DBROW2UINT64(valuemapid, row[17]);
		params_proto = row[18];
		ipmi_sensor = row[19];
		snmp_community = row[20];
		snmp_oid_proto = row[21];
		port = row[22];
		snmpv3_securityname = row[23];
		ZBX_STR2UCHAR(snmpv3_securitylevel, row[24]);
		snmpv3_authpassphrase = row[25];
		snmpv3_privpassphrase = row[26];
		ZBX_STR2UCHAR(authtype, row[27]);
		username = row[28];
		password = row[29];
		publickey = row[30];
		privatekey = row[31];
		description = row[32];
		ZBX_DBROW2UINT64(interfaceid, row[33]);

		lld_items_get(parent_itemid, &items, type, value_type, data_type, delay, delay_flex, history, trends,
				trapper_hosts, units, multiplier, delta, formula, logtimefmt, valuemapid, ipmi_sensor,
				snmp_community, port, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase,
				snmpv3_privpassphrase, authtype, username, password, publickey, privatekey, description,
				interfaceid);

		lld_items_make(&items, name_proto, key_proto, params_proto, snmp_oid_proto, lld_rows);

		lld_items_validate(hostid, &items, error);

		lld_applications_make(parent_itemid, &items, &del_itemappids);

		lld_items_save(hostid, parent_itemid, &items, key_proto, type, value_type, data_type, delay,
				delay_flex, history, trends, status, trapper_hosts, units, multiplier, delta, formula,
				logtimefmt, valuemapid, ipmi_sensor, snmp_community, port, snmpv3_securityname,
				snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, authtype, username,
				password, publickey, privatekey, description, interfaceid, &del_itemappids);

		lld_item_links_populate(lld_rows, parent_itemid, &items);

		lld_remove_lost_resources(&items, lifetime, lastcheck);

		lld_items_free(&items);
		del_itemappids.values_num = 0;
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&del_itemappids);
	zbx_vector_ptr_destroy(&items);

	lld_item_links_sort(lld_rows);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
