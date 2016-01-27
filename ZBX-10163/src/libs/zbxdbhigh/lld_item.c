/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	zbx_uint64_t		valuemapid;
	zbx_uint64_t		interfaceid;
	char			*name;
	char			*key;
	char			*delay_flex;
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
	char			*port;
	int			delay;
	int			history;
	int			trends;
	unsigned char		type;
	unsigned char		value_type;
	unsigned char		data_type;
	unsigned char		status;
	unsigned char		multiplier;
	unsigned char		delta;
	unsigned char		snmpv3_securitylevel;
	unsigned char		snmpv3_authprotocol;
	unsigned char		snmpv3_privprotocol;
	unsigned char		authtype;
	zbx_vector_ptr_t	lld_rows;
	zbx_vector_ptr_t	applications;
}
zbx_lld_item_prototype_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		parent_itemid;
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
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPROTOCOL	__UINT64_C(0x0000000001000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE	__UINT64_C(0x0000000002000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPROTOCOL	__UINT64_C(0x0000000004000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE	__UINT64_C(0x0000000008000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE		__UINT64_C(0x0000000010000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME		__UINT64_C(0x0000000020000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD		__UINT64_C(0x0000000040000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY		__UINT64_C(0x0000000080000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY		__UINT64_C(0x0000000100000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION		__UINT64_C(0x0000000200000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID		__UINT64_C(0x0000000400000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_CONTEXTNAME	__UINT64_C(0x0000000800000000)
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
		ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPROTOCOL | ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE |	\
		ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPROTOCOL | ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE |	\
		ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE | ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME |				\
		ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD | ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY |			\
		ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY | ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION |			\
		ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID | ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_CONTEXTNAME)
	zbx_uint64_t		flags;
	char			*key_proto;
	char			*name;
	char			*name_proto;
	char			*key;
	char			*key_orig;
	char			*units;
	char			*units_orig;
	char			*params;
	char			*params_orig;
	char			*ipmi_sensor;
	char			*ipmi_sensor_orig;
	char			*snmp_oid;
	char			*snmp_oid_orig;
	char			*description;
	char			*description_orig;
	int			lastcheck;
	int			ts_delete;
	const zbx_lld_row_t	*lld_row;
}
zbx_lld_item_t;

/* item index by prototype (parent) id and lld row */
typedef struct
{
	zbx_uint64_t	parent_itemid;
	zbx_lld_row_t	*lld_row;
	zbx_lld_item_t	*item;
}
zbx_lld_item_index_t;

typedef struct
{
	zbx_uint64_t	application_prototypeid;
	zbx_uint64_t	itemid;
	char		*name;
}
zbx_lld_application_prototype_t;

typedef struct
{
	zbx_uint64_t		applicationid;
	zbx_uint64_t		application_prototypeid;
	zbx_uint64_t		application_discoveryid;
	int			lastcheck;
	int			ts_delete;
#define ZBX_FLAG_LLD_APPLICATION_UNSET			__UINT64_C(0x0000000000000000)
#define ZBX_FLAG_LLD_APPLICATION_DISCOVERED		__UINT64_C(0x0000000000000001)
#define ZBX_FLAG_LLD_APPLICATION_UPDATE_NAME		__UINT64_C(0x0000000000000002)
#define ZBX_FLAG_LLD_APPLICATION_ADD_DISCOVERY		__UINT64_C(0x0000000100000000)
#define ZBX_FLAG_LLD_APPLICATION_REMOVE_DISCOVERY	__UINT64_C(0x0000000200000000)
#define ZBX_FLAG_LLD_APPLICATION_REMOVE			__UINT64_C(0x0000000400000000)
	zbx_uint64_t		flags;
	char			*name;
	char			*name_proto;
	char			*name_orig;
	const zbx_lld_row_t	*lld_row;
}
zbx_lld_application_t;

/* reference to an item either by its id (existing items) or structure (new items) */
typedef struct
{
	zbx_uint64_t	itemid;
	zbx_lld_item_t	*item;
}
zbx_lld_item_ref_t;

/* reference to an application either by its id (existing applications) or structure (new applications) */
typedef struct
{
	zbx_uint64_t		applicationid;
	zbx_lld_application_t	*application;
}
zbx_lld_application_ref_t;

/* item prototype-application link reference by application id (existing applications) */
/* or application prototype structure (application prototypes)                         */
typedef struct
{
	zbx_lld_application_prototype_t	*application_prototype;
	zbx_uint64_t			applicationid;
}
zbx_lld_item_application_ref_t;

/* item-application link */
typedef struct
{
	zbx_uint64_t			itemappid;
	zbx_lld_item_ref_t		item_ref;
	zbx_lld_application_ref_t	application_ref;
#define ZBX_FLAG_LLD_ITEM_APPLICATION_UNSET		__UINT64_C(0x0000000000000000)
#define ZBX_FLAG_LLD_ITEM_APPLICATION_DISCOVERED	__UINT64_C(0x0000000000000001)
	zbx_uint64_t			flags;
}
zbx_lld_item_application_t;

/* application index by prototypeid and lld row */
typedef struct
{
	zbx_uint64_t		application_prototypeid;
	const zbx_lld_row_t	*lld_row;
	zbx_lld_application_t	*application;
}
zbx_lld_application_index_t;

/* items index hashset support functions */
static zbx_hash_t	lld_item_index_hash_func(const void *data)
{
	zbx_lld_item_index_t	*item_index = (zbx_lld_item_index_t *)data;
	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&item_index->parent_itemid,
			sizeof(item_index->parent_itemid), ZBX_DEFAULT_HASH_SEED);
	return ZBX_DEFAULT_PTR_HASH_ALGO(&item_index->lld_row, sizeof(item_index->lld_row), hash);
}

static int	lld_item_index_compare_func(const void *d1, const void *d2)
{
	zbx_lld_item_index_t	*i1 = (zbx_lld_item_index_t *)d1;
	zbx_lld_item_index_t	*i2 = (zbx_lld_item_index_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->parent_itemid, i2->parent_itemid);
	ZBX_RETURN_IF_NOT_EQUAL(i1->lld_row, i2->lld_row);

	return 0;
}

/* application index hashset support functions */
static zbx_hash_t	lld_application_index_hash_func(const void *data)
{
	zbx_lld_application_index_t	*application_index = (zbx_lld_application_index_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&application_index->application_prototypeid,
			sizeof(application_index->application_prototypeid), ZBX_DEFAULT_HASH_SEED);
	return ZBX_DEFAULT_PTR_HASH_ALGO(&application_index->lld_row, sizeof(application_index->lld_row), hash);
}

static int	lld_application_index_compare_func(const void *d1, const void *d2)
{
	zbx_lld_application_index_t	*i1 = (zbx_lld_application_index_t *)d1;
	zbx_lld_application_index_t	*i2 = (zbx_lld_application_index_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->application_prototypeid, i2->application_prototypeid);
	ZBX_RETURN_IF_NOT_EQUAL(i1->lld_row, i2->lld_row);

	return 0;
}

/* comparison function for discovered application lookup by name */
static int	lld_application_compare_name(const void *d1, const void *d2)
{
	const zbx_lld_application_t	*a1 = *(zbx_lld_application_t **)d1;
	const zbx_lld_application_t	*a2 = *(zbx_lld_application_t **)d2;

	if (0 == (a1->flags & a2->flags))
		return -1;

	if (NULL == a1->name || NULL == a2->name)
		return -1;

	return strcmp(a1->name, a2->name);
}

/* comparison function for discovered application lookup by original name name */
static int	lld_application_compare_name_orig(const void *d1, const void *d2)
{
	const zbx_lld_application_t	*a1 = *(zbx_lld_application_t **)d1;
	const zbx_lld_application_t	*a2 = *(zbx_lld_application_t **)d2;

	if (0 == (a1->flags & a2->flags))
		return -1;

	if (NULL == a1->name_orig || NULL == a2->name_orig)
		return -1;

	return strcmp(a1->name_orig, a2->name_orig);
}

/* string pointer hashset (used to check for duplicate item keys) support functions */
static zbx_hash_t	lld_items_keys_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC(*(char **)data);
}

static int	lld_items_keys_compare_func(const void *d1, const void *d2)
{
	return ZBX_DEFAULT_STR_COMPARE_FUNC(d1, d2);
}

/* items - applications hashset support */
static zbx_hash_t	lld_item_application_hash_func(const void *data)
{
	const zbx_lld_item_application_t	*item_application = data;
	zbx_hash_t				hash;

	hash = ZBX_DEFAULT_HASH_ALGO(&item_application->item_ref, sizeof(item_application->item_ref),
			ZBX_DEFAULT_HASH_SEED);
	return ZBX_DEFAULT_HASH_ALGO(&item_application->application_ref, sizeof(item_application->application_ref),
			hash);
}

static int	lld_item_application_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_application_t	*ia1 = (zbx_lld_item_application_t *)d1;
	const zbx_lld_item_application_t	*ia2 = (zbx_lld_item_application_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ia1->item_ref.itemid, ia2->item_ref.itemid);
	ZBX_RETURN_IF_NOT_EQUAL(ia1->item_ref.item, ia2->item_ref.item);
	ZBX_RETURN_IF_NOT_EQUAL(ia1->application_ref.applicationid, ia2->application_ref.applicationid);
	ZBX_RETURN_IF_NOT_EQUAL(ia1->application_ref.application, ia2->application_ref.application);

	return 0;
}

static void	lld_application_prototype_free(zbx_lld_application_prototype_t *application_prototype)
{
	zbx_free(application_prototype->name);
	zbx_free(application_prototype);
}

static void	lld_application_free(zbx_lld_application_t *application)
{
	zbx_free(application->name_orig);
	zbx_free(application->name_proto);
	zbx_free(application->name);
	zbx_free(application);
}

static void	lld_item_prototype_free(zbx_lld_item_prototype_t *item_prototype)
{
	zbx_free(item_prototype->name);
	zbx_free(item_prototype->key);
	zbx_free(item_prototype->delay_flex);
	zbx_free(item_prototype->trapper_hosts);
	zbx_free(item_prototype->units);
	zbx_free(item_prototype->formula);
	zbx_free(item_prototype->logtimefmt);
	zbx_free(item_prototype->params);
	zbx_free(item_prototype->ipmi_sensor);
	zbx_free(item_prototype->snmp_community);
	zbx_free(item_prototype->snmp_oid);
	zbx_free(item_prototype->snmpv3_securityname);
	zbx_free(item_prototype->snmpv3_authpassphrase);
	zbx_free(item_prototype->snmpv3_privpassphrase);
	zbx_free(item_prototype->snmpv3_contextname);
	zbx_free(item_prototype->username);
	zbx_free(item_prototype->password);
	zbx_free(item_prototype->publickey);
	zbx_free(item_prototype->privatekey);
	zbx_free(item_prototype->description);
	zbx_free(item_prototype->port);

	zbx_vector_ptr_destroy(&item_prototype->lld_rows);

	zbx_vector_ptr_clear_ext(&item_prototype->applications, zbx_default_mem_free_func);
	zbx_vector_ptr_destroy(&item_prototype->applications);

	zbx_free(item_prototype);
}

static void	lld_item_free(zbx_lld_item_t *item)
{
	zbx_free(item->key_proto);
	zbx_free(item->name);
	zbx_free(item->name_proto);
	zbx_free(item->key);
	zbx_free(item->key_orig);
	zbx_free(item->units);
	zbx_free(item->units_orig);
	zbx_free(item->params);
	zbx_free(item->params_orig);
	zbx_free(item->ipmi_sensor);
	zbx_free(item->ipmi_sensor_orig);
	zbx_free(item->snmp_oid);
	zbx_free(item->snmp_oid_orig);
	zbx_free(item->description);
	zbx_free(item->description_orig);
	zbx_free(item);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_get                                                    *
 *                                                                            *
 * Purpose: retrieves existing items for the specified item prototypes        *
 *                                                                            *
 * Parameters: item_prototypes - [IN] item prototypes                         *
 *             items           - [OUT] list of items                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(const zbx_vector_ptr_t *item_prototypes, zbx_vector_ptr_t *items)
{
	const char		*__function_name = "lld_items_get";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_item_t		*item;
	zbx_uint64_t		db_valuemapid, db_interfaceid;
	zbx_vector_uint64_t	parent_itemids;
	int			i;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&parent_itemids);
	zbx_vector_uint64_reserve(&parent_itemids, item_prototypes->values_num);

	for (i = 0; i < item_prototypes->values_num; i++)
	{
		const zbx_lld_item_prototype_t	*item_prototype;

		item_prototype = (const zbx_lld_item_prototype_t *)item_prototypes->values[i];

		zbx_vector_uint64_append(&parent_itemids, item_prototype->itemid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select id.itemid,id.key_,id.lastcheck,id.ts_delete,i.name,i.key_,i.type,i.value_type,"
				"i.data_type,i.delay,i.delay_flex,i.history,i.trends,i.trapper_hosts,i.units,"
				"i.multiplier,i.delta,i.formula,i.logtimefmt,i.valuemapid,i.params,i.ipmi_sensor,"
				"i.snmp_community,i.snmp_oid,i.port,i.snmpv3_securityname,i.snmpv3_securitylevel,"
				"i.snmpv3_authprotocol,i.snmpv3_authpassphrase,i.snmpv3_privprotocol,"
				"i.snmpv3_privpassphrase,i.authtype,i.username,i.password,i.publickey,i.privatekey,"
				"i.description,i.interfaceid,i.snmpv3_contextname,id.parent_itemid"
			" from item_discovery id"
				" join items i"
					" on id.itemid=i.itemid"
			" where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "id.parent_itemid", parent_itemids.values,
			parent_itemids.values_num);

	zbx_vector_uint64_destroy(&parent_itemids);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t			parent_itemid;
		int				index;
		zbx_lld_item_prototype_t	*item_prototype;

		ZBX_STR2UINT64(parent_itemid, row[39]);

		if (FAIL == (index = zbx_vector_ptr_bsearch(item_prototypes, &parent_itemid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = (zbx_lld_item_prototype_t *)item_prototypes->values[index];

		item = zbx_malloc(NULL, sizeof(zbx_lld_item_t));

		ZBX_STR2UINT64(item->itemid, row[0]);
		item->parent_itemid = parent_itemid;
		item->key_proto = zbx_strdup(NULL, row[1]);
		item->lastcheck = atoi(row[2]);
		item->ts_delete = atoi(row[3]);
		item->name = zbx_strdup(NULL, row[4]);
		item->name_proto = NULL;
		item->key = zbx_strdup(NULL, row[5]);
		item->key_orig = NULL;
		item->flags = ZBX_FLAG_LLD_ITEM_UNSET;

		if ((unsigned char)atoi(row[6]) != item_prototype->type)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TYPE;

		if ((unsigned char)atoi(row[7]) != item_prototype->value_type)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE;

		if ((unsigned char)atoi(row[8]) != item_prototype->data_type)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DATA_TYPE;

		if (atoi(row[9]) != item_prototype->delay)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DELAY;

		if (0 != strcmp(row[10], item_prototype->delay_flex))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DELAY_FLEX;

		if (atoi(row[11]) != item_prototype->history)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY;

		if (atoi(row[12]) != item_prototype->trends)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS;

		if (0 != strcmp(row[13], item_prototype->trapper_hosts))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS;

		item->units = zbx_strdup(NULL, row[14]);
		item->units_orig = NULL;

		if ((unsigned char)atoi(row[15]) != item_prototype->multiplier)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_MULTIPLIER;

		if ((unsigned char)atoi(row[16]) != item_prototype->delta)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DELTA;

		if (0 != strcmp(row[17], item_prototype->formula))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA;

		if (0 != strcmp(row[18], item_prototype->logtimefmt))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT;

		ZBX_DBROW2UINT64(db_valuemapid, row[19]);
		if (db_valuemapid != item_prototype->valuemapid)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID;

		item->params = zbx_strdup(NULL, row[20]);
		item->params_orig = NULL;

		item->ipmi_sensor = zbx_strdup(NULL, row[21]);
		item->ipmi_sensor_orig = NULL;

		if (0 != strcmp(row[22], item_prototype->snmp_community))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_COMMUNITY;

		item->snmp_oid = zbx_strdup(NULL, row[23]);
		item->snmp_oid_orig = NULL;

		if (0 != strcmp(row[24], item_prototype->port))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PORT;

		if (0 != strcmp(row[25], item_prototype->snmpv3_securityname))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYNAME;

		if ((unsigned char)atoi(row[26]) != item_prototype->snmpv3_securitylevel)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYLEVEL;

		if ((unsigned char)atoi(row[27]) != item_prototype->snmpv3_authprotocol)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPROTOCOL;

		if (0 != strcmp(row[28], item_prototype->snmpv3_authpassphrase))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE;

		if ((unsigned char)atoi(row[29]) != item_prototype->snmpv3_privprotocol)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPROTOCOL;

		if (0 != strcmp(row[30], item_prototype->snmpv3_privpassphrase))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE;

		if ((unsigned char)atoi(row[31]) != item_prototype->authtype)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE;

		if (0 != strcmp(row[32], item_prototype->username))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME;

		if (0 != strcmp(row[33], item_prototype->password))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD;

		if (0 != strcmp(row[34], item_prototype->publickey))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY;

		if (0 != strcmp(row[35], item_prototype->privatekey))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY;

		item->description = zbx_strdup(NULL, row[36]);
		item->description_orig = NULL;

		ZBX_DBROW2UINT64(db_interfaceid, row[37]);
		if (db_interfaceid != item_prototype->interfaceid)
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID;

		if (0 != strcmp(row[38], item_prototype->snmpv3_contextname))
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_CONTEXTNAME;

		item->lld_row = NULL;

		zbx_vector_ptr_append(items, item);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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

	/* only new items or items with changed data will be validated */
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
 * Parameters: items - [IN] list of items; must be sorted by itemid           *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_validate(zbx_uint64_t hostid, zbx_vector_ptr_t *items, char **error)
{
	const char		*__function_name = "lld_items_validate";

	DB_RESULT		result;
	DB_ROW			row;
	int			i;
	zbx_lld_item_t		*item;
	zbx_vector_uint64_t	itemids;
	zbx_vector_str_t	keys;
	zbx_hashset_t		items_keys;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_str_create(&keys);		/* list of item keys */

	/* check an item name validity */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		lld_validate_item_field(item, &item->name, &item->name_proto,
				ZBX_FLAG_LLD_ITEM_UPDATE_NAME, ITEM_NAME_LEN, error);
		lld_validate_item_field(item, &item->key, &item->key_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_KEY, ITEM_KEY_LEN, error);
		lld_validate_item_field(item, &item->units, &item->units_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_UNITS, ITEM_UNITS_LEN, error);
		lld_validate_item_field(item, &item->params, &item->params_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS, ITEM_PARAM_LEN, error);
		lld_validate_item_field(item, &item->ipmi_sensor, &item->ipmi_sensor_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR, ITEM_IPMI_SENSOR_LEN, error);
		lld_validate_item_field(item, &item->snmp_oid, &item->snmp_oid_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID, ITEM_SNMP_OID_LEN, error);
		lld_validate_item_field(item, &item->description, &item->description_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION, ITEM_DESCRIPTION_LEN, error);
	}

	/* check duplicated item keys */

	zbx_hashset_create(&items_keys, items->values_num, lld_items_keys_hash_func, lld_items_keys_compare_func);

	/* add 'good' (existing, discovered and not updated) keys to the hashset */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		/* skip new or updated item keys */
		if (0 == item->itemid || 0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			continue;

		zbx_hashset_insert(&items_keys, &item->key, sizeof(char *));
	}

	/* check new and updated keys for duplicated keys in discovered items */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		/* only new items or items with changed key will be validated */
		if (0 != item->itemid && 0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			continue;

		if (NULL != zbx_hashset_search(&items_keys, &item->key))
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
		}
		else
			zbx_hashset_insert(&items_keys, &item->key, sizeof(char *));
	}

	zbx_hashset_destroy(&items_keys);

	/* check duplicated keys in DB */

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 != item->itemid)
			zbx_vector_uint64_append(&itemids, item->itemid);

		if (0 != item->itemid && 0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			continue;

		zbx_vector_str_append(&keys, item->key);
	}

	if (0 != keys.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 256, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select key_"
				" from items"
				" where hostid=" ZBX_FS_UI64
					" and",
				hostid);
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "key_",
				(const char **)keys.values, keys.values_num);

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

	zbx_vector_str_destroy(&keys);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_formula_macros                                        *
 *                                                                            *
 * Purpose: substitutes lld macros in calculated item formula expression      *
 *                                                                            *
 * Parameters: data   - [IN/OUT] the expression                               *
 *             jp_row - [IN] the lld data row                                 *
 *                                                                            *
 ******************************************************************************/
static void	substitute_formula_macros(char **data, struct zbx_json_parse *jp_row)
{
	char		*exp, *tmp, *e, *func, *key, *host = NULL;
	size_t		exp_alloc = 128, exp_offset = 0, tmp_alloc = 128, tmp_offset = 0, len;
	zbx_function_t	funcdata;
	int		i;

	exp = zbx_malloc(NULL, exp_alloc);
	tmp = zbx_malloc(NULL, tmp_alloc);

	for (e = *data; '\0' != *e; e += len)
	{
		/* get function data or jump over part of the string that is not a function */
		if (FAIL == zbx_function_parse(&funcdata, e, &len))
		{
			zbx_strncpy_alloc(&tmp, &tmp_alloc, &tmp_offset, e, len);
			continue;
		}

		/* substitute LLD macros in the part of the string that was jumped over */
		if (0 != tmp_offset)
		{
			size_t	tmp_len;

			substitute_discovery_macros(&tmp, jp_row, ZBX_MACRO_ANY, NULL, 0);
			tmp_len = strlen(tmp);

			zbx_strncpy_alloc(&exp, &exp_alloc, &exp_offset, tmp, tmp_len);

			if (++tmp_len > tmp_alloc)
				tmp_alloc = tmp_len;

			tmp_offset = 0;
		}

		/* substitute LLD macros in function parameters (if any) */
		if (0 < funcdata.nparam)
		{
			/* substitute LLD macro in the item key (first parameter) the same way as elsewhere */
			if (SUCCEED == parse_host_key(funcdata.params[0], &host, &key))
			{
				zbx_free(funcdata.params[0]);
				substitute_key_macros(&key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

				if (NULL != host)
				{
					funcdata.params[0] = zbx_dsprintf(NULL, "%s:%s", host, key);
					zbx_free(host);
					zbx_free(key);
				}
				else
				{
					funcdata.params[0] = key;
					key = NULL;
				}
			}

			/* substitute LLD macros in the rest of the parameters (simple replacement) */
			for (i = 1; i < funcdata.nparam; i++)
				substitute_discovery_macros(&funcdata.params[i], jp_row, ZBX_MACRO_ANY, NULL, 0);
		}

		/* substitute the original function in the string with the new one (with substituted LLD macros) */
		zbx_function_tostr(&funcdata, e, len, &func);
		zbx_strcpy_alloc(&exp, &exp_alloc, &exp_offset, func);

		/* cleanup */
		zbx_free(func);
		zbx_function_clean(&funcdata);
	}

	/* substitute the LLD macros in the remainder of the string that was jumped over */
	if (0 != tmp_offset)
	{
		substitute_discovery_macros(&tmp, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_strcpy_alloc(&exp, &exp_alloc, &exp_offset, tmp);
	}

	zbx_free(tmp);

	zbx_free(*data);
	*data = exp;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_item_make                                                    *
 *                                                                            *
 * Purpose: creates a new item based on item prototype and lld data row       *
 *                                                                            *
 * Parameters: item_prototype - [IN] the item prototype                       *
 *             lld_row        - [IN] the lld row                              *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_item_t	*lld_item_make(const zbx_lld_item_prototype_t *item_prototype, const zbx_lld_row_t *lld_row)
{
	const char		*__function_name = "lld_item_make";

	zbx_lld_item_t		*item;
	struct zbx_json_parse	*jp_row = (struct zbx_json_parse *)&lld_row->jp_row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	item = zbx_malloc(NULL, sizeof(zbx_lld_item_t));

	item->itemid = 0;
	item->parent_itemid = item_prototype->itemid;
	item->lastcheck = 0;
	item->ts_delete = 0;
	item->key_proto = NULL;

	item->name = zbx_strdup(NULL, item_prototype->name);
	item->name_proto = NULL;
	substitute_discovery_macros(&item->name, jp_row, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->name, ZBX_WHITESPACE);

	item->key = zbx_strdup(NULL, item_prototype->key);
	item->key_orig = NULL;
	substitute_key_macros(&item->key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

	item->units = zbx_strdup(NULL, item_prototype->units);
	item->units_orig = NULL;
	substitute_discovery_macros(&item->units, jp_row, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->units, ZBX_WHITESPACE);

	item->params = zbx_strdup(NULL, item_prototype->params);
	item->params_orig = NULL;

	if (ITEM_TYPE_CALCULATED == item_prototype->type)
		substitute_formula_macros(&item->params, jp_row);
	else
		substitute_discovery_macros(&item->params, jp_row, ZBX_MACRO_ANY, NULL, 0);

	zbx_lrtrim(item->params, ZBX_WHITESPACE);

	item->ipmi_sensor = zbx_strdup(NULL, item_prototype->ipmi_sensor);
	item->ipmi_sensor_orig = NULL;
	substitute_discovery_macros(&item->ipmi_sensor, jp_row, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->ipmi_sensor, ZBX_WHITESPACE); is not missing here */

	item->snmp_oid = zbx_strdup(NULL, item_prototype->snmp_oid);
	item->snmp_oid_orig = NULL;
	substitute_key_macros(&item->snmp_oid, NULL, NULL, jp_row, MACRO_TYPE_SNMP_OID, NULL, 0);
	zbx_lrtrim(item->snmp_oid, ZBX_WHITESPACE);

	item->description = zbx_strdup(NULL, item_prototype->description);
	item->description_orig = NULL;
	substitute_discovery_macros(&item->description, jp_row, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->description, ZBX_WHITESPACE);

	item->flags = ZBX_FLAG_LLD_ITEM_DISCOVERED;
	item->lld_row = lld_row;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return item;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_item_update                                                  *
 *                                                                            *
 * Purpose: updates an existing item based on item prototype and lld data row *
 *                                                                            *
 * Parameters: item_prototype - [IN] the item prototype                       *
 *             lld_row        - [IN] the lld row                              *
 *             item           - [IN] an existing item or NULL                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_item_update(const zbx_lld_item_prototype_t *item_prototype, const zbx_lld_row_t *lld_row,
		zbx_lld_item_t *item)
{
	const char		*__function_name = "lld_item_update";

	char			*buffer = NULL;
	struct zbx_json_parse	*jp_row = (struct zbx_json_parse *)&lld_row->jp_row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	buffer = zbx_strdup(buffer, item_prototype->name);
	substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);
	if (0 != strcmp(item->name, buffer))
	{
		item->name_proto = item->name;
		item->name = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_NAME;
	}

	if (0 != strcmp(item->key_proto, item_prototype->key))
	{
		item->key_orig = item->key;
		item->key = zbx_strdup(NULL, item_prototype->key);
		substitute_key_macros(&item->key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_KEY;
	}

	buffer = zbx_strdup(buffer, item_prototype->units);
	substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);
	if (0 != strcmp(item->units, buffer))
	{
		item->units_orig = item->units;
		item->units = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_UNITS;
	}

	buffer = zbx_strdup(buffer, item_prototype->params);

	if (ITEM_TYPE_CALCULATED == item_prototype->type)
		substitute_formula_macros(&buffer, jp_row);
	else
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);

	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->params, buffer))
	{
		item->params_orig = item->params;
		item->params = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS;
	}

	buffer = zbx_strdup(buffer, item_prototype->ipmi_sensor);
	substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */
	if (0 != strcmp(item->ipmi_sensor, buffer))
	{
		item->ipmi_sensor_orig = item->ipmi_sensor;
		item->ipmi_sensor = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR;
	}

	buffer = zbx_strdup(buffer, item_prototype->snmp_oid);
	substitute_key_macros(&buffer, NULL, NULL, jp_row, MACRO_TYPE_SNMP_OID, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);
	if (0 != strcmp(item->snmp_oid, buffer))
	{
		item->snmp_oid_orig = item->snmp_oid;
		item->snmp_oid = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID;
	}

	buffer = zbx_strdup(buffer, item_prototype->description);
	substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);
	if (0 != strcmp(item->description, buffer))
	{
		item->description_orig = item->description;
		item->description = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION;
	}

	item->flags |= ZBX_FLAG_LLD_ITEM_DISCOVERED;
	item->lld_row = lld_row;

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_make                                                   *
 *                                                                            *
 * Purpose: updates existing items and creates new ones based on item         *
 *          item prototypes and lld data                                      *
 *                                                                            *
 * Parameters: item_prototypes - [IN] the item prototypes                     *
 *             lld_rows        - [IN] the lld data rows                       *
 *             items           - [IN/OUT] sorted list of items                *
 *             items_index     - [OUT] index of items based on prototype ids  *
 *                                     and lld rows. Used to quckly find an   *
 *                                     item by prototype and lld_row.         *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_make(const zbx_vector_ptr_t *item_prototypes, const zbx_vector_ptr_t *lld_rows,
		zbx_vector_ptr_t *items, zbx_hashset_t *items_index)
{
	const char			*__function_name = "lld_items_make";
	int				i, j, index;
	zbx_lld_item_prototype_t	*item_prototype;
	zbx_lld_item_t			*item;
	zbx_lld_row_t			*lld_row;
	zbx_lld_item_index_t		*item_index, item_index_local;
	char				*buffer = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* create the items index */
	for (i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = (zbx_lld_item_prototype_t *)item_prototypes->values[i];

		for (j = 0; j < lld_rows->values_num; j++)
			zbx_vector_ptr_append(&item_prototype->lld_rows, lld_rows->values[j]);
	}

	/* Iterate in reverse order because usually the items are created in the same order as     */
	/* incoming lld rows. Iterating in reverse optimizes lld_row removal from item prototypes. */
	for (i = items->values_num - 1; i >= 0; i--)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (FAIL == (index = zbx_vector_ptr_bsearch(item_prototypes, &item->parent_itemid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = item_prototypes->values[index];

		for (j = item_prototype->lld_rows.values_num - 1; j >= 0; j--)
		{
			lld_row = (zbx_lld_row_t *)item_prototype->lld_rows.values[j];

			buffer = zbx_strdup(buffer, item->key_proto);
			substitute_key_macros(&buffer, NULL, NULL, &lld_row->jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

			if (0 == strcmp(item->key, buffer))
			{
				item_index_local.parent_itemid = item->parent_itemid;
				item_index_local.lld_row = lld_row;
				item_index_local.item = item;
				zbx_hashset_insert(items_index, &item_index_local, sizeof(item_index_local));

				zbx_vector_ptr_remove_noorder(&item_prototype->lld_rows, j);
				break;
			}
		}
	}

	zbx_free(buffer);

	/* update/create discovered items */
	for (i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = (zbx_lld_item_prototype_t *)item_prototypes->values[i];
		item_index_local.parent_itemid = item_prototype->itemid;

		for (j = 0; j < lld_rows->values_num; j++)
		{
			item_index_local.lld_row = (zbx_lld_row_t *)lld_rows->values[j];

			if (NULL == (item_index = zbx_hashset_search(items_index, &item_index_local)))
			{
				item = lld_item_make(item_prototype, item_index_local.lld_row);

				/* add the created item to items vector and update index */
				zbx_vector_ptr_append(items, item);
				item_index_local.item = item;
				zbx_hashset_insert(items_index, &item_index_local, sizeof(item_index_local));
			}
			else
				lld_item_update(item_prototype, item_index_local.lld_row, item_index->item);
		}
	}

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d items", __function_name, items->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_save                                                   *
 *                                                                            *
 * Parameters: hostid          - [IN] parent host id                          *
 *             item_prototypes - [IN] item prototypes                         *
 *             items           - [IN/OUT] items to save                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_save(zbx_uint64_t hostid, const zbx_vector_ptr_t *item_prototypes, zbx_vector_ptr_t *items)
{
	const char			*__function_name = "lld_items_save";

	int				index, i, new_items = 0, upd_items = 0;
	zbx_lld_item_t			*item;
	zbx_lld_item_prototype_t	*item_prototype;
	zbx_uint64_t			itemid = 0, itemdiscoveryid = 0;
	char				*sql = NULL, *value_esc;
	size_t				sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_db_insert_t			db_insert, db_insert_idiscovery;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == items->values_num)
		goto out;

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == item->itemid)
			new_items++;
		else if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE))
			upd_items++;
	}

	if (0 == new_items && 0 == upd_items)
		goto out;

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

		zbx_db_insert_prepare(&db_insert, "items", "itemid", "name", "key_", "hostid", "type", "value_type",
				"data_type", "delay", "delay_flex", "history", "trends", "status", "trapper_hosts",
				"units", "multiplier", "delta", "formula", "logtimefmt", "valuemapid", "params",
				"ipmi_sensor", "snmp_community", "snmp_oid", "port", "snmpv3_securityname",
				"snmpv3_securitylevel", "snmpv3_authprotocol", "snmpv3_authpassphrase",
				"snmpv3_privprotocol", "snmpv3_privpassphrase", "authtype", "username", "password",
				"publickey", "privatekey", "description", "interfaceid", "flags", "snmpv3_contextname",
				NULL);

		zbx_db_insert_prepare(&db_insert_idiscovery, "item_discovery", "itemdiscoveryid", "itemid",
				"parent_itemid", "key_", NULL);
	}

	if (0 != upd_items)
	{
		sql = zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_lld_item_t *)items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (FAIL == (index = zbx_vector_ptr_bsearch(item_prototypes, &item->parent_itemid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = item_prototypes->values[index];

		if (0 == item->itemid)
		{
			item->itemid = itemid++;

			zbx_db_insert_add_values(&db_insert, item->itemid, item->name, item->key, hostid,
					(int)item_prototype->type, (int)item_prototype->value_type,
					(int)item_prototype->data_type, item_prototype->delay,
					item_prototype->delay_flex, item_prototype->history, item_prototype->trends,
					(int)item_prototype->status, item_prototype->trapper_hosts, item->units,
					(int)item_prototype->multiplier, (int)item_prototype->delta,
					item_prototype->formula, item_prototype->logtimefmt, item_prototype->valuemapid,
					item->params, item->ipmi_sensor, item_prototype->snmp_community, item->snmp_oid,
					item_prototype->port, item_prototype->snmpv3_securityname,
					(int)item_prototype->snmpv3_securitylevel,
					(int)item_prototype->snmpv3_authprotocol, item_prototype->snmpv3_authpassphrase,
					(int)item_prototype->snmpv3_privprotocol, item_prototype->snmpv3_privpassphrase,
					(int)item_prototype->authtype, item_prototype->username,
					item_prototype->password, item_prototype->publickey, item_prototype->privatekey,
					item->description, item_prototype->interfaceid, (int)ZBX_FLAG_DISCOVERY_CREATED,
					item_prototype->snmpv3_contextname);

			zbx_db_insert_add_values(&db_insert_idiscovery, itemdiscoveryid++, item->itemid,
					item->parent_itemid, item_prototype->key);
		}
		else
		{
			if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update items set ");
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_NAME))
				{
					value_esc = DBdyn_escape_string(item->name);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
				{
					value_esc = DBdyn_escape_string(item->key);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%skey_='%s'", d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d,
							(int)item_prototype->type);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%svalue_type=%d",
							d, (int)item_prototype->value_type);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DATA_TYPE))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdata_type=%d",
							d, (int)item_prototype->data_type);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELAY))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdelay=%d", d,
							item_prototype->delay);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELAY_FLEX))
				{
					value_esc = DBdyn_escape_string(item_prototype->delay_flex);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdelay_flex='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%shistory=%d",
							d, item_prototype->history);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%strends=%d", d,
							item_prototype->trends);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS))
				{
					value_esc = DBdyn_escape_string(item_prototype->trapper_hosts);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%strapper_hosts='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_UNITS))
				{
					value_esc = DBdyn_escape_string(item->units);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sunits='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_MULTIPLIER))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%smultiplier=%d",
							d, (int)item_prototype->multiplier);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELTA))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdelta=%d",
							d, (int)item_prototype->delta);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA))
				{
					value_esc = DBdyn_escape_string(item_prototype->formula);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sformula='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT))
				{
					value_esc = DBdyn_escape_string(item_prototype->logtimefmt);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%slogtimefmt='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%svaluemapid=%s",
							d, DBsql_id_ins(item_prototype->valuemapid));
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS))
				{
					value_esc = DBdyn_escape_string(item->params);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sparams='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR))
				{
					value_esc = DBdyn_escape_string(item->ipmi_sensor);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sipmi_sensor='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_COMMUNITY))
				{
					value_esc = DBdyn_escape_string(item_prototype->snmp_community);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%ssnmp_community='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID))
				{
					value_esc = DBdyn_escape_string(item->snmp_oid);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%ssnmp_oid='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PORT))
				{
					value_esc = DBdyn_escape_string(item_prototype->port);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sport='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYNAME))
				{
					value_esc = DBdyn_escape_string(item_prototype->snmpv3_securityname);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"%ssnmpv3_securityname='%s'", d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_SECURITYLEVEL))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"%ssnmpv3_securitylevel=%d", d,
							(int)item_prototype->snmpv3_securitylevel);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPROTOCOL))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"%ssnmpv3_authprotocol=%d", d,
							(int)item_prototype->snmpv3_authprotocol);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_AUTHPASSPHRASE))
				{
					value_esc = DBdyn_escape_string(item_prototype->snmpv3_authpassphrase);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"%ssnmpv3_authpassphrase='%s'", d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPROTOCOL))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"%ssnmpv3_privprotocol=%d", d,
							(int)item_prototype->snmpv3_privprotocol);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_PRIVPASSPHRASE))
				{
					value_esc = DBdyn_escape_string(item_prototype->snmpv3_privpassphrase);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"%ssnmpv3_privpassphrase='%s'", d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sauthtype=%d",
							d, (int)item_prototype->authtype);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME))
				{
					value_esc = DBdyn_escape_string(item_prototype->username);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%susername='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD))
				{
					value_esc = DBdyn_escape_string(item_prototype->password);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spassword='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY))
				{
					value_esc = DBdyn_escape_string(item_prototype->publickey);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spublickey='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY))
				{
					value_esc = DBdyn_escape_string(item_prototype->privatekey);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sprivatekey='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION))
				{
					value_esc = DBdyn_escape_string(item->description);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdescription='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";

				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sinterfaceid=%s",
							d, DBsql_id_ins(item_prototype->interfaceid));
					d = ",";
				}
				if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMPV3_CONTEXTNAME))
				{
					value_esc = DBdyn_escape_string(item_prototype->snmpv3_contextname);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"%ssnmpv3_contextname='%s'", d, value_esc);
					zbx_free(value_esc);
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where itemid=" ZBX_FS_UI64 ";\n",
						item->itemid);
			}

			if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			{
				value_esc = DBdyn_escape_string(item_prototype->key);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update item_discovery"
						" set key_='%s'"
						" where itemid=" ZBX_FS_UI64 ";\n",
						value_esc, item->itemid);
				zbx_free(value_esc);
			}
		}

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != upd_items)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		DBexecute("%s", sql);
		zbx_free(sql);
	}

	if (0 != new_items)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_idiscovery);
		zbx_db_insert_clean(&db_insert_idiscovery);

		zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_applications_save                                            *
 *                                                                            *
 * Parameters: hostid                 - [IN] host id                          *
 *             applications           - [IN/OUT] applications to save         *
 *             application_prototypes - [IN] the application prototypes       *
 *                                                                            *
 ******************************************************************************/
static void	lld_applications_save(zbx_uint64_t hostid, zbx_vector_ptr_t *applications,
		const zbx_vector_ptr_t *application_prototypes)
{
	const char			*__function_name = "lld_applications_save";
	int				i, new_applications = 0, new_discoveries = 0, index;
	zbx_lld_application_t		*application;
	zbx_lld_application_prototype_t	*application_prototype;
	zbx_uint64_t			applicationid, application_discoveryid;
	zbx_db_insert_t			db_insert, db_insert_discovery;
	zbx_vector_uint64_t		del_applicationids, del_discoveryids;
	char				*sql_a = NULL, *sql_ad = NULL, *name;
	size_t				sql_a_alloc = 0, sql_a_offset = 0, sql_ad_alloc = 0, sql_ad_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == applications->values_num)
		goto out;

	zbx_vector_uint64_create(&del_applicationids);
	zbx_vector_uint64_create(&del_discoveryids);

	/* Count new applications and application discoveries.                      */
	/* Note that an application might have been discovered by another lld rule. */
	/* In this case the discovered items will be linked to this application and */
	/* new application discovery record, linking the prototype to this          */
	/* application, will be created.                                            */
	for (i = 0; i < applications->values_num; i++)
	{
		application = (zbx_lld_application_t *)applications->values[i];

		if (0 != (application->flags & ZBX_FLAG_LLD_APPLICATION_REMOVE))
		{
			zbx_vector_uint64_append(&del_applicationids, application->applicationid);
			continue;
		}

		if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_DISCOVERED))
			continue;

		if (0 == application->applicationid)
			new_applications++;

		if (0 != (application->flags & ZBX_FLAG_LLD_APPLICATION_ADD_DISCOVERY))
			new_discoveries++;
	}

	/* insert new applications, application discoveries and prepare a list of applications to be removed */

	if (0 != new_applications)
	{
		applicationid = DBget_maxid_num("applications", new_applications);
		zbx_db_insert_prepare(&db_insert, "applications", "applicationid", "hostid", "name", "flags", NULL);
	}

	if (0 != new_discoveries)
	{
		application_discoveryid = DBget_maxid_num("application_discovery", new_discoveries);
		zbx_db_insert_prepare(&db_insert_discovery, "application_discovery", "application_discoveryid",
				"applicationid", "application_prototypeid", "name", NULL);
	}

	for (i = 0; i < applications->values_num; i++)
	{
		DBexecute_overflowed_sql(&sql_a, &sql_a_alloc, &sql_a_offset);
		DBexecute_overflowed_sql(&sql_ad, &sql_ad_alloc, &sql_ad_offset);

		application = (zbx_lld_application_t *)applications->values[i];

		if (0 != (application->flags & ZBX_FLAG_LLD_APPLICATION_REMOVE_DISCOVERY))
		{
			zbx_vector_uint64_append(&del_discoveryids, application->application_discoveryid);
			continue;
		}

		if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_DISCOVERED))
			continue;

		if (FAIL == (index = zbx_vector_ptr_search(application_prototypes,
				&application->application_prototypeid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		application_prototype = (zbx_lld_application_prototype_t *)application_prototypes->values[index];

		if (0 == application->applicationid)
		{
			application->applicationid = applicationid++;
			zbx_db_insert_add_values(&db_insert, application->applicationid, hostid, application->name,
					ZBX_FLAG_DISCOVERY_CREATED);
		}

		if (0 != (application->flags & ZBX_FLAG_LLD_APPLICATION_UPDATE_NAME))
		{
			name = DBdyn_escape_string(application->name);
			zbx_snprintf_alloc(&sql_a, &sql_a_alloc, &sql_a_offset,
					"update applications set name='%s'"
					" where applicationid=" ZBX_FS_UI64 ";\n",
					name, application->applicationid);
			zbx_free(name);

			name = DBdyn_escape_string(application_prototype->name);
			zbx_snprintf_alloc(&sql_ad, &sql_ad_alloc, &sql_ad_offset,
					"update application_discovery set name='%s'"
					" where application_discoveryid=" ZBX_FS_UI64 ";\n",
					name, application->application_discoveryid);
			zbx_free(name);
			continue;
		}

		if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_ADD_DISCOVERY))
			continue;

		application->application_discoveryid = application_discoveryid++;
		zbx_db_insert_add_values(&db_insert_discovery, application->application_discoveryid,
				application->applicationid, application->application_prototypeid,
				application_prototype->name);
	}

	if (0 != del_applicationids.values_num)
	{
		zbx_strcpy_alloc(&sql_a, &sql_a_alloc, &sql_a_offset, "delete from applications where");
		DBadd_condition_alloc(&sql_a, &sql_a_alloc, &sql_a_offset, "applicationid", del_applicationids.values,
				del_applicationids.values_num);
		zbx_strcpy_alloc(&sql_a, &sql_a_alloc, &sql_a_offset, ";\n");
	}

	if (0 != del_discoveryids.values_num)
	{
		zbx_strcpy_alloc(&sql_a, &sql_a_alloc, &sql_a_offset, "delete from application_discovery where");
		DBadd_condition_alloc(&sql_a, &sql_a_alloc, &sql_a_offset, "application_discoveryid",
				del_discoveryids.values, del_discoveryids.values_num);
		zbx_strcpy_alloc(&sql_a, &sql_a_alloc, &sql_a_offset, ";\n");
	}

	if (NULL != sql_a)
	{
		DBexecute("%s", sql_a);
		zbx_free(sql_a);
	}

	if (NULL != sql_ad)
	{
		DBexecute("%s", sql_ad);
		zbx_free(sql_ad);
	}

	if (0 != new_applications)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_vector_ptr_sort(applications, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	if (0 != new_discoveries)
	{
		zbx_db_insert_execute(&db_insert_discovery);
		zbx_db_insert_clean(&db_insert_discovery);
	}

	zbx_vector_uint64_destroy(&del_discoveryids);
	zbx_vector_uint64_destroy(&del_applicationids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_item_application_validate                                    *
 *                                                                            *
 * Purpose: validates undiscovered item-application link to determine if it   *
 *          should be removed                                                 *
 *                                                                            *
 * Parameters: items_application - [IN] an item-application link to validate  *
 *             items             - [IN] the related items                     *
 *             applications      - [IN] the related applications              *
 *                                                                            *
 * Return value: SUCCEED - item-application link should not be removed        *
 *               FAIL    - item-application link should be removed            *
 *                                                                            *
 * Comments: Item-application link should be removed if either the            *
 *           application was not discovered or item was discovered.           *
 *           The only case when undiscovered item-application link is not     *
 *           removed is when we have valid application and undiscovered item. *
 *           In this case we leave item-application link untouched and it     *
 *           will 'expire' together with item.                                *
 *                                                                            *
 ******************************************************************************/
static int	lld_item_application_validate(const zbx_lld_item_application_t *item_application,
		const zbx_vector_ptr_t *items, const zbx_vector_ptr_t *applications)
{
	const zbx_lld_application_t	*application;
	const zbx_lld_item_t		*item;
	int				index;

	if (FAIL == (index = zbx_vector_ptr_bsearch(applications, &item_application->application_ref.applicationid,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		/* Applications vector contains only discovered applications and */
		/* apparently the item was linked to non-discovered application. */
		/* Item-application links to normal application must be removed  */
		/* if not discovered.                                            */
		return FAIL;
	}

	application = (zbx_lld_application_t *)applications->values[index];

	if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_DISCOVERED))
		return FAIL;

	if (FAIL == (index = zbx_vector_ptr_bsearch(items, &item_application->item_ref.itemid,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	item = (zbx_lld_item_t *)items->values[index];

	if (0 != (item->flags & ZBX_FLAG_LLD_APPLICATION_DISCOVERED))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_applications_save                                      *
 *                                                                            *
 * Parameters: items_applications - [IN] item-application links               *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_applications_save(zbx_hashset_t *items_applications, const zbx_vector_ptr_t *items,
		const zbx_vector_ptr_t *applications)
{
	const char			*__function_name = "lld_items_applications_save";
	zbx_hashset_iter_t		iter;
	zbx_lld_item_application_t	*item_application;
	zbx_vector_uint64_t		del_itemappids;
	int				new_item_applications = 0;
	zbx_uint64_t			itemappid, applicationid, itemid;
	zbx_db_insert_t			db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == items_applications->num_data)
		goto out;

	zbx_vector_uint64_create(&del_itemappids);

	/* count new item-application links */
	zbx_hashset_iter_reset(items_applications, &iter);

	while (NULL != (item_application = zbx_hashset_iter_next(&iter)))
	{
		if (0 == item_application->itemappid)
			new_item_applications++;
	}

	if (0 != new_item_applications)
	{
		itemappid = DBget_maxid_num("items_applications", new_item_applications);
		zbx_db_insert_prepare(&db_insert, "items_applications", "itemappid", "applicationid", "itemid", NULL);
	}

	zbx_hashset_iter_reset(items_applications, &iter);

	while (NULL != (item_application = zbx_hashset_iter_next(&iter)))
	{
		if (0 != item_application->itemappid)
		{
			/* add for removal the old links that aren't discovered and can be removed */
			if (0 == (item_application->flags & ZBX_FLAG_LLD_ITEM_APPLICATION_DISCOVERED) &&
					FAIL == lld_item_application_validate(item_application, items, applications))
			{
				zbx_vector_uint64_append(&del_itemappids, item_application->itemappid);
			}

			continue;
		}

		if (0 == (applicationid = item_application->application_ref.applicationid))
			applicationid = item_application->application_ref.application->applicationid;

		if (0 == (itemid = item_application->item_ref.itemid))
			itemid = item_application->item_ref.item->itemid;

		item_application->itemappid = itemappid++;
		zbx_db_insert_add_values(&db_insert, item_application->itemappid, applicationid, itemid);
	}

	if (0 != new_item_applications)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	/* remove deprecated links */
	if (0 != del_itemappids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from items_applications where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemappid", del_itemappids.values,
				del_itemappids.values_num);

		DBexecute("%s", sql);

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&del_itemappids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_remove_lost_items                                            *
 *                                                                            *
 * Purpose: updates item_discovery.lastcheck and item_discovery.ts_delete     *
 *          fields; removes lost resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_remove_lost_items(const zbx_vector_ptr_t *items, unsigned short lifetime, int lastcheck)
{
	const char			*__function_name = "lld_remove_lost_items";
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_lld_item_t			*item;
	zbx_vector_uint64_t		del_itemids, lc_itemids, ts_itemids;
	zbx_vector_uint64_pair_t	discovery_itemts;
	int				i, lifetime_sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == items->values_num)
		goto out;

	lifetime_sec = lifetime * SEC_PER_DAY;

	zbx_vector_uint64_create(&del_itemids);
	zbx_vector_uint64_create(&lc_itemids);
	zbx_vector_uint64_create(&ts_itemids);
	zbx_vector_uint64_pair_create(&discovery_itemts);

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
				zbx_uint64_pair_t	itemts;

				itemts.first = item->itemid;
				itemts.second = item->lastcheck + lifetime_sec;
				zbx_vector_uint64_pair_append(&discovery_itemts, itemts);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_itemids, item->itemid);
			if (0 != item->ts_delete)
				zbx_vector_uint64_append(&ts_itemids, item->itemid);
		}
	}

	if (0 == discovery_itemts.values_num && 0 == lc_itemids.values_num && 0 == ts_itemids.values_num &&
			0 == del_itemids.values_num)
	{
		goto clean;
	}

	/* update item discovery table */

	DBbegin();

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < discovery_itemts.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update item_discovery"
				" set ts_delete=%d"
				" where itemid=" ZBX_FS_UI64 ";\n",
				(int)discovery_itemts.values[i].second, discovery_itemts.values[i].first);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lc_itemids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update item_discovery set lastcheck=%d where",
				lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid",
				lc_itemids.values, lc_itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
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
		DBexecute("%s", sql);

	zbx_free(sql);

	/* remove 'lost' items */
	if (0 != del_itemids.values_num)
	{
		zbx_vector_uint64_sort(&del_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBdelete_items(&del_itemids);
	}

	DBcommit();
clean:
	zbx_vector_uint64_pair_destroy(&discovery_itemts);
	zbx_vector_uint64_destroy(&ts_itemids);
	zbx_vector_uint64_destroy(&lc_itemids);
	zbx_vector_uint64_destroy(&del_itemids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_remove_lost_applications                                     *
 *                                                                            *
 * Purpose: updates application_discovery lastcheck and ts_delete fields,     *
 *          removes lost resources                                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_remove_lost_applications(zbx_uint64_t lld_ruleid, const zbx_vector_ptr_t *applications,
		unsigned short lifetime, int lastcheck)
{
	const char			*__function_name = "lld_remove_lost_applications";
	DB_RESULT			result;
	DB_ROW				row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		del_applicationids, del_discoveryids, ts_discoveryids, lc_discoveryids;
	zbx_vector_uint64_pair_t	discovery_applicationts;
	int				i, lifetime_sec, index;
	const zbx_lld_application_t	*application;
	zbx_uint64_t			applicationid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == applications->values_num)
		goto out;

	lifetime_sec = lifetime * SEC_PER_DAY;

	zbx_vector_uint64_create(&del_applicationids);
	zbx_vector_uint64_create(&del_discoveryids);
	zbx_vector_uint64_create(&ts_discoveryids);
	zbx_vector_uint64_create(&lc_discoveryids);
	zbx_vector_uint64_pair_create(&discovery_applicationts);

	/* prepare application discovery update vector */
	for (i = 0; i < applications->values_num; i++)
	{
		application = (const zbx_lld_application_t *)applications->values[i];

		if (0 == application->applicationid)
			continue;

		if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_DISCOVERED))
		{
			if (application->lastcheck < lastcheck - lifetime_sec)
			{
				zbx_vector_uint64_append(&del_applicationids, application->applicationid);
				zbx_vector_uint64_append(&del_discoveryids, application->application_discoveryid);
			}
			else if (application->ts_delete != application->lastcheck + lifetime_sec)
			{
				zbx_uint64_pair_t	applicationts;

				applicationts.first = application->application_discoveryid;
				applicationts.second = application->lastcheck + lifetime_sec;
				zbx_vector_uint64_pair_append(&discovery_applicationts, applicationts);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_discoveryids, application->application_discoveryid);
			if (0 != application->ts_delete)
				zbx_vector_uint64_append(&ts_discoveryids, application->application_discoveryid);
		}
	}

	/* check if the applications are really 'lost' (not discovered by other discovery rules) */
	if (0 != del_applicationids.values_num)
	{
		zbx_vector_uint64_sort(&del_applicationids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select ad.applicationid from application_discovery ad,application_prototype ap"
				" where ad.application_prototypeid=ap.application_prototypeid"
					" and ap.itemid<>" ZBX_FS_UI64
					" and",
				lld_ruleid);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ad.applicationid", del_applicationids.values,
				del_applicationids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by ad.applicationid desc");

		result = DBselect("%s", sql);

		sql_offset = 0;

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(applicationid, row[0]);

			if (FAIL != (index = zbx_vector_uint64_bsearch(&del_applicationids, applicationid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_remove(&del_applicationids, index);
			}
		}

		DBfree_result(result);
	}

	if (0 == discovery_applicationts.values_num && 0 == del_applicationids.values_num &&
			0 == del_discoveryids.values_num && 0 == ts_discoveryids.values_num &&
			0 == lc_discoveryids.values_num)
	{
		goto clean;
	}

	/* remove lost applications and update application discovery table */

	DBbegin();

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < discovery_applicationts.values_num; i++)
	{
		zbx_uint64_pair_t	*applicationts = &(discovery_applicationts.values[i]);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update application_discovery"
				" set ts_delete=%d"
				" where application_discoveryid=" ZBX_FS_UI64 ";\n",
				(int)applicationts->second, applicationts->first);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != del_discoveryids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from application_discovery where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "application_discoveryid",
				del_discoveryids.values, del_discoveryids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != ts_discoveryids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update application_discovery"
				" set ts_delete=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "application_discoveryid",
				ts_discoveryids.values, ts_discoveryids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lc_discoveryids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update application_discovery"
				" set lastcheck=%d where", lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "application_discoveryid",
				lc_discoveryids.values, lc_discoveryids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != del_applicationids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from applications where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "applicationid", del_applicationids.values,
				del_applicationids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	DBcommit();
clean:
	zbx_free(sql);

	zbx_vector_uint64_pair_destroy(&discovery_applicationts);
	zbx_vector_uint64_destroy(&lc_discoveryids);
	zbx_vector_uint64_destroy(&ts_discoveryids);
	zbx_vector_uint64_destroy(&del_discoveryids);
	zbx_vector_uint64_destroy(&del_applicationids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	lld_item_links_populate(const zbx_vector_ptr_t *item_prototypes, zbx_vector_ptr_t *lld_rows,
		zbx_hashset_t *items_index)
{
	int				i, j;
	zbx_lld_item_prototype_t	*item_prototype;
	zbx_lld_item_index_t		*item_index, item_index_local;
	zbx_lld_item_link_t		*item_link;

	for (i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = (zbx_lld_item_prototype_t *)item_prototypes->values[i];
		item_index_local.parent_itemid = item_prototype->itemid;

		for (j = 0; j < lld_rows->values_num; j++)
		{
			item_index_local.lld_row = (zbx_lld_row_t *)lld_rows->values[j];

			if (NULL == (item_index = zbx_hashset_search(items_index, &item_index_local)))
				continue;

			if (0 == (item_index->item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
				continue;

			item_link = (zbx_lld_item_link_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_link_t));

			item_link->parent_itemid = item_index->item->parent_itemid;
			item_link->itemid = item_index->item->itemid;

			zbx_vector_ptr_append(&item_index_local.lld_row->item_links, item_link);
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
 * Function: lld_application_prototypes_get                                   *
 *                                                                            *
 * Purpose: gets the discovery rule application prototypes from database      *
 *                                                                            *
 * Parameters: lld_ruleid             - [IN] the discovery rule id            *
 *             application_prototypes - [OUT] the applications prototypes     *
 *                                            defined for the discovery rule, *
 *                                            sorted by prototype id          *
 *                                                                            *
 ******************************************************************************/
static void	lld_application_prototypes_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *application_prototypes)
{
	const char			*__function_name = "lld_application_prototypes_get";
	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_application_prototype_t	*application_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select application_prototypeid,name"
			" from application_prototype"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		application_prototype = (zbx_lld_application_prototype_t *)zbx_malloc(NULL,
				sizeof(zbx_lld_application_prototype_t));

		ZBX_STR2UINT64(application_prototype->application_prototypeid, row[0]);
		application_prototype->itemid = lld_ruleid;
		application_prototype->name = zbx_strdup(NULL, row[1]);

		zbx_vector_ptr_append(application_prototypes, application_prototype);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(application_prototypes, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d prototypes", __function_name, application_prototypes->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_item_application_prototypes_get                              *
 *                                                                            *
 * Purpose: gets the discovery rule item-application link prototypes from     *
 *          database                                                          *
 *                                                                            *
 * Parameters: item_prototypes        - [IN/OUT] item prototypes              *
 *             application_prototypes - [IN] the application prototypes       *
 *                                           defined for the discovery rule   *
 *                                                                            *
 ******************************************************************************/
static void	lld_item_application_prototypes_get(const zbx_vector_ptr_t *item_prototypes,
		const zbx_vector_ptr_t *application_prototypes)
{
	const char			*__function_name = "lld_item_application_prototypes_get";
	DB_RESULT			result;
	DB_ROW				row;
	int				i, index;
	zbx_uint64_t			application_prototypeid, itemid;
	zbx_vector_uint64_t		item_prototypeids;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_lld_item_application_ref_t	*item_application_prototype;
	zbx_lld_item_prototype_t	*item_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&item_prototypeids);

	/* get item prototype links to application prototypes */

	for (i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = (zbx_lld_item_prototype_t *)item_prototypes->values[i];

		zbx_vector_uint64_append(&item_prototypeids, item_prototype->itemid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select application_prototypeid,itemid"
			" from item_application_prototype"
			" where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid",
			item_prototypeids.values, item_prototypeids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(application_prototypeid, row[0]);

		if (FAIL == (index = zbx_vector_ptr_search(application_prototypes, &application_prototypeid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_application_prototype = (zbx_lld_item_application_ref_t *)zbx_malloc(NULL,
				sizeof(zbx_lld_item_application_ref_t));

		item_application_prototype->application_prototype = application_prototypes->values[index];
		item_application_prototype->applicationid = 0;

		ZBX_STR2UINT64(itemid, row[1]);
		index = zbx_vector_ptr_bsearch(item_prototypes, &itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		item_prototype = (zbx_lld_item_prototype_t *)item_prototypes->values[index];

		zbx_vector_ptr_append(&item_prototype->applications, item_application_prototype);
	}
	DBfree_result(result);

	/* get item prototype links to real applications */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select applicationid,itemid"
			" from items_applications"
			" where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid",
			item_prototypeids.values, item_prototypeids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		item_application_prototype = (zbx_lld_item_application_ref_t *)zbx_malloc(NULL,
				sizeof(zbx_lld_item_application_ref_t));

		item_application_prototype->application_prototype = NULL;
		ZBX_STR2UINT64(item_application_prototype->applicationid, row[0]);

		ZBX_STR2UINT64(itemid, row[1]);
		index = zbx_vector_ptr_bsearch(item_prototypes, &itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		item_prototype = (zbx_lld_item_prototype_t *)item_prototypes->values[index];

		zbx_vector_ptr_append(&item_prototype->applications, item_application_prototype);
	}
	DBfree_result(result);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&item_prototypeids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_applications_get                                             *
 *                                                                            *
 * Purpose: gets applications previously discovered by the discovery rule     *
 *                                                                            *
 * Parameters: lld_ruleid   - [IN] the discovery rule id                      *
 *             applications - [OUT] the applications                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_applications_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *applications)
{
	const char		*__function_name = "lld_applications_get";
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_application_t	*application;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select a.applicationid,a.name,ap.application_prototypeid,ad.lastcheck,ad.ts_delete,ad.name,"
				"ad.application_discoveryid"
			" from applications a,application_discovery ad,application_prototype ap"
			" where ap.itemid=" ZBX_FS_UI64
				" and ad.application_prototypeid=ap.application_prototypeid"
				" and a.applicationid=ad.applicationid",
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		application = (zbx_lld_application_t *)zbx_malloc(NULL, sizeof(zbx_lld_application_t));

		ZBX_STR2UINT64(application->applicationid, row[0]);
		ZBX_STR2UINT64(application->application_prototypeid, row[2]);
		ZBX_STR2UINT64(application->application_discoveryid, row[6]);
		application->name = zbx_strdup(NULL, row[1]);
		application->lastcheck = atoi(row[3]);
		application->ts_delete = atoi(row[4]);
		application->name_proto = zbx_strdup(NULL, row[5]);
		application->name_orig = NULL;
		application->flags = ZBX_FLAG_LLD_APPLICATION_UNSET;
		application->lld_row = NULL;

		zbx_vector_ptr_append(applications, application);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d applications", __function_name, applications->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_application_make                                             *
 *                                                                            *
 * Purpose: create a new application or mark an existing application as       *
 *          discovered based on prototype and lld row                         *
 *                                                                            *
 * Parameters: application_prototype - [IN] the application prototype         *
 *             lld_row               - [IN] the lld row                       *
 *             applications          - [IN/OUT] the applications              *
 *             applications_index    - [IN/OUT] the application index by      *
 *                                              prototype id and lld row      *
 *                                                                            *
 ******************************************************************************/
static void	lld_application_make(const zbx_lld_application_prototype_t *application_prototype,
		const zbx_lld_row_t *lld_row, zbx_vector_ptr_t *applications, zbx_hashset_t *applications_index)
{
	zbx_lld_application_t		*application;
	zbx_lld_application_index_t	*application_index, application_index_local;
	struct zbx_json_parse		*jp_row = (struct zbx_json_parse *)&lld_row->jp_row;
	char				*buffer = NULL;

	application_index_local.application_prototypeid = application_prototype->application_prototypeid;
	application_index_local.lld_row = lld_row;

	if (NULL == (application_index = zbx_hashset_search(applications_index, &application_index_local)))
	{
		application = (zbx_lld_application_t *)zbx_malloc(NULL, sizeof(zbx_lld_application_t));
		application->applicationid = 0;
		application->application_prototypeid = application_prototype->application_prototypeid;
		application->application_discoveryid = 0;

		application->name = zbx_strdup(NULL, application_prototype->name);
		substitute_discovery_macros(&application->name, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(application->name, ZBX_WHITESPACE);

		application->name_proto = zbx_strdup(NULL, application_prototype->name);
		application->name_orig = NULL;
		application->flags = ZBX_FLAG_LLD_APPLICATION_ADD_DISCOVERY;
		application->lld_row = lld_row;

		zbx_vector_ptr_append(applications, application);

		application_index_local.application = application;
		zbx_hashset_insert(applications_index, &application_index_local, sizeof(zbx_lld_application_index_t));
	}
	else
	{
		application = application_index->application;

		if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_UPDATE_NAME))
		{
			buffer = zbx_strdup(NULL, application_prototype->name);
			substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
			zbx_lrtrim(buffer, ZBX_WHITESPACE);

			if (0 != strcmp(application->name, buffer))
			{
				application->name_orig = application->name;
				application->name = buffer;
				application->flags |= ZBX_FLAG_LLD_APPLICATION_UPDATE_NAME;
			}
			else
				zbx_free(buffer);
		}
	}

	application->flags |= ZBX_FLAG_LLD_APPLICATION_DISCOVERED;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_applications_make                                            *
 *                                                                            *
 * Purpose: makes new applications and marks old applications as discovered   *
 *          based on application prototypes and lld rows                      *
 *                                                                            *
 * Parameters: application_prototypes - [IN] the application prototypes       *
 *             lld_rows               - [IN] the lld rows                     *
 *             applications           - [IN/OUT] the applications             *
 *             applications_index     - [OUT] the application index by        *
 *                                            prototype id and lld row        *
 *                                                                            *
 ******************************************************************************/
static void	lld_applications_make(const zbx_vector_ptr_t *application_prototypes,
		const zbx_vector_ptr_t *lld_rows, zbx_vector_ptr_t *applications, zbx_hashset_t *applications_index)
{
	const char			*__function_name = "lld_applications_make";
	int				i, j;
	zbx_lld_application_t		*application;
	zbx_lld_row_t			*lld_row;
	zbx_lld_application_index_t	application_index_local;
	char				*buffer = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* index existing applications */

	for (i = 0; i < applications->values_num; i++)
	{
		application = (zbx_lld_application_t *)applications->values[i];

		for (j = 0; j < lld_rows->values_num; j++)
		{
			lld_row = (zbx_lld_row_t *)lld_rows->values[j];

			buffer = zbx_strdup(buffer, application->name_proto);
			substitute_discovery_macros(&buffer, &lld_row->jp_row, ZBX_MACRO_ANY, NULL, 0);
			zbx_lrtrim(buffer, ZBX_WHITESPACE);

			if (0 == strcmp(application->name, buffer))
			{
				application_index_local.application_prototypeid = application->application_prototypeid;
				application_index_local.lld_row = lld_row;
				application_index_local.application = application;
				zbx_hashset_insert(applications_index, &application_index_local,
						sizeof(application_index_local));

				application->lld_row = lld_row;
			}
		}
	}

	zbx_free(buffer);

	/* make the applications */
	for (i = 0; i < application_prototypes->values_num; i++)
	{
		for (j = 0; j < lld_rows->values_num; j++)
		{
			lld_application_make(application_prototypes->values[i], lld_rows->values[j], applications,
					applications_index);
		}
	}

	zbx_vector_ptr_sort(applications, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d applications", __function_name, applications->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_applications_validate                                        *
 *                                                                            *
 * Purpose: validates the discovered and renamed applications                 *
 *                                                                            *
 * Parameters: hostid             - [IN] host id                              *
 *             lld_ruleid         - [IN] the discovery rule id                *
 *             applications       - [IN/OUT] the applications                 *
 *             applications_index - [OUT] the application index by            *
 *                                        prototype id and lld row            *
 *             error              - [IN/OUT] the lld error message            *
 *                                                                            *
 ******************************************************************************/
static void	lld_applications_validate(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *applications,
		zbx_hashset_t *applications_index, char **error)
{
	const char			*__function_name = "lld_applications_validate";
	int				i, j, index;
	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_application_t		*application, *new_application, application_local;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_str_t		names_new, names_old;
	zbx_lld_application_index_t	*application_index, application_index_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == applications->values_num)
		goto out;

	zbx_vector_str_create(&names_new);
	zbx_vector_str_create(&names_old);

	/* check for conflicting application names in the discovered applications */

	for (i = 0; i < applications->values_num; i++)
	{
		application = (zbx_lld_application_t *)applications->values[i];

		if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_DISCOVERED))
			continue;

		if (0 != application->applicationid && 0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_UPDATE_NAME))
			continue;

		/* iterate in reverse order so existing applications would have more priority */
		/* than new applications which have 0 applicationid and therefore are located */
		/* at the beginning of applications vector which is sorted by applicationid   */
		for (j = applications->values_num - 1; j > i; j--)
		{
			zbx_lld_application_t	*application_compare = (zbx_lld_application_t *)applications->values[j];

			if (0 != strcmp(application->name, application_compare->name))
				continue;

			/* Applications with matching names are validated depending on their prototypes. */
			/* If they are discovered by different prototypes we must fail with appropriate  */
			/* lld error.                                                                    */
			/* Otherwise we 'merge' application by updating index of the validated           */
			/* validated application to point at the application with the same name.         */
			/* In both cases the validated application is flagged as non-discovered.         */
			application->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;

			/* fail if application has different prototype */
			if (application->application_prototypeid != application_compare->application_prototypeid)
			{
				*error = zbx_strdcatf(*error, "Cannot %s application:"
						" application with the same name \"%s\" already exists.\n",
						(0 != application->applicationid ? "update" : "create"),
						application->name);

				break;
			}

			/* update application index to use the matching application */

			application_index_local.application_prototypeid = application->application_prototypeid;
			application_index_local.lld_row = application->lld_row;

			if (NULL == (application_index = zbx_hashset_search(applications_index,
					&application_index_local)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				break;
			}

			application_index->application = application_compare;
			break;
		}

		/* Prepare name lists to resolve naming conflicts with applications */
		/* discovered by other discovery rules:                             */
		/*   names_new - to check if discovered/renamed application names   */
		/*               don't match existing applications discovered by    */
		/*               other discovery rules                              */
		/*   names_old - to check if renamed applications were also         */
		/*               discovered by other discovery rules                */
		if (i == j)
		{
			zbx_vector_str_append(&names_new, application->name);

			if (NULL != application->name_orig)
				zbx_vector_str_append(&names_old, application->name_orig);
		}
	}

	/* validate new/renamed application names against applications discovered */
	/* by other discovery rules                                               */

	if (0 != names_new.values_num)
	{
		zbx_vector_str_sort(&names_new, ZBX_DEFAULT_STR_COMPARE_FUNC);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select applicationid,name,flags"
				" from applications"
				" where hostid=" ZBX_FS_UI64
					" and",
				hostid);
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
				(const char **)names_new.values, names_new.values_num);

		result = DBselect("%s", sql);

		application_local.flags = ZBX_FLAG_LLD_APPLICATION_DISCOVERED;

		while (NULL != (row = DBfetch(result)))
		{
			application_local.name = row[1];

			if (FAIL == (index = zbx_vector_ptr_search(applications, &application_local,
					lld_application_compare_name)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			application = applications->values[index];

			/* only discovered applications can be 'shared' between discovery rules */
			if (ZBX_FLAG_DISCOVERY_CREATED != atoi(row[2]))
			{
				/* conflicting application name, reset discovery flags */
				application->flags = ZBX_FLAG_LLD_APPLICATION_UNSET;

				*error = zbx_strdcatf(*error, "Cannot create application:"
						" non-discovered application"
						" with the same name \"%s\" already exists.\n",
						application->name);

				continue;
			}

			if (0 != (application->flags & ZBX_FLAG_LLD_APPLICATION_UPDATE_NAME))
			{
				/* During discovery process the application was renamed to an */
				/* application already discovered by another discovery rule.  */
				/* In this case we must delete the old application and relink */
				/* its items to the application we have found.                */

				/* create a pseudo application to remove the renamed application */

				new_application = (zbx_lld_application_t *)zbx_malloc(NULL,
						sizeof(zbx_lld_application_t));

				memset(new_application, 0, sizeof(zbx_lld_application_t));
				new_application->applicationid = application->applicationid;
				new_application->flags = ZBX_FLAG_LLD_APPLICATION_REMOVE;

				zbx_vector_ptr_append(applications, new_application);

				/* update application flags so that instead of renaming it a new */
				/* discovery record is created                                   */

				application->application_discoveryid = 0;
				application->flags &= ~ZBX_FLAG_LLD_APPLICATION_UPDATE_NAME;
				application->flags |= ZBX_FLAG_LLD_APPLICATION_ADD_DISCOVERY;
			}

			/* reuse application created by another discovery rule */
			ZBX_STR2UINT64(application->applicationid, row[0]);
		}
		DBfree_result(result);
	}

	/* if an application shared with other discovery rule has been renamed we must */
	/* create a new application with the new name instead of renaming the old one  */

	if (0 != names_old.values_num)
	{
		sql_offset = 0;

		zbx_vector_str_sort(&names_old, ZBX_DEFAULT_STR_COMPARE_FUNC);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select a.name"
				" from applications a,application_discovery ad,application_prototype ap"
				" where a.applicationid=ad.applicationid"
					" and ad.application_prototypeid=ap.application_prototypeid"
					" and a.hostid=" ZBX_FS_UI64
					" and ap.itemid<>" ZBX_FS_UI64
					" and",
				hostid, lld_ruleid);
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "a.name",
				(const char **)names_old.values, names_old.values_num);

		result = DBselect("%s", sql);

		application_local.flags = ZBX_FLAG_LLD_APPLICATION_DISCOVERED;

		while (NULL != (row = DBfetch(result)))
		{
			application_local.name_orig = row[0];

			if (FAIL == (index = zbx_vector_ptr_search(applications, &application_local,
					lld_application_compare_name_orig)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			application = applications->values[index];

			/* add a pseudo application to remove the application discovery record */
			/* of the shared application and current discovery rule                */
			new_application = (zbx_lld_application_t *)zbx_malloc(NULL, sizeof(zbx_lld_application_t));
			memset(new_application, 0, sizeof(zbx_lld_application_t));
			new_application->applicationid = application->applicationid;
			new_application->application_prototypeid = application->application_prototypeid;
			new_application->application_discoveryid = application->application_discoveryid;
			new_application->flags = ZBX_FLAG_LLD_APPLICATION_REMOVE_DISCOVERY;
			zbx_vector_ptr_append(applications, new_application);

			/* reset applicationid, application_discoveryid and flags             */
			/* so a new application is created instead of renaming the shared one */
			application->applicationid = 0;
			application->application_discoveryid = 0;
			application->flags = ZBX_FLAG_LLD_APPLICATION_ADD_DISCOVERY |
					ZBX_FLAG_LLD_APPLICATION_DISCOVERED;
		}
		DBfree_result(result);
	}

	zbx_vector_str_destroy(&names_old);
	zbx_vector_str_destroy(&names_new);

	zbx_free(sql);

	zbx_vector_ptr_sort(applications, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_applications_get                                       *
 *                                                                            *
 * Purpose: gets item-application links for the lld rule                      *
 *                                                                            *
 * Parameters: lld_rule           - [IN] the lld rule                         *
 *             items_applications - [OUT] the item-application links          *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_applications_get(zbx_uint64_t lld_ruleid, zbx_hashset_t *items_applications)
{
	const char			*__function_name = "lld_items_applications_get";
	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_item_application_t	item_application;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select ia.itemappid,ia.itemid,ia.applicationid"
			" from items_applications ia,item_discovery id1,item_discovery id2"
			" where id1.itemid=ia.itemid"
				" and id1.parent_itemid=id2.itemid"
				" and id2.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	item_application.application_ref.application = NULL;
	item_application.item_ref.item = NULL;

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(item_application.itemappid, row[0]);
		ZBX_STR2UINT64(item_application.item_ref.itemid, row[1]);
		ZBX_STR2UINT64(item_application.application_ref.applicationid, row[2]);
		item_application.flags = ZBX_FLAG_LLD_ITEM_APPLICATION_UNSET;

		zbx_hashset_insert(items_applications, &item_application, sizeof(zbx_lld_item_application_t));
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d links", __function_name, items_applications->num_data);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_applications_make                                      *
 *                                                                            *
 * Purpose: makes new item-application links and marks existing links as      *
 *          discovered based item_prototypes applications links               *
 *                                                                            *
 * Parameters: item_prototypes    - [IN] the item prototypes                  *
 *             items              - [IN] the items                            *
 *             applications       - [IN] the applications                     *
 *             applications_index - [IN] the application index by             *
 *                                       prototype id and lld row             *
 *             items_applications - [IN/OUT] the item-application links       *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_applications_make(const zbx_vector_ptr_t *item_prototypes, const zbx_vector_ptr_t *items,
		const zbx_vector_ptr_t *applications, zbx_hashset_t *applications_index,
		zbx_hashset_t *items_applications)
{
	const char			*__function_name = "lld_items_applications_make";
	int				i, j, index;
	zbx_lld_item_application_t	*item_application, item_application_local;
	zbx_lld_application_t		*application;
	zbx_lld_item_prototype_t	*item_prototype;
	zbx_lld_item_t			*item;
	zbx_lld_item_application_ref_t	*itemapp_prototype;
	zbx_lld_application_index_t	*application_index, application_index_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	item_application_local.itemappid = 0;
	item_application_local.flags = ZBX_FLAG_LLD_ITEM_APPLICATION_DISCOVERED;

	for (i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		/* if item is discovered its prototype must be in item_prototypes vector */
		index = zbx_vector_ptr_bsearch(item_prototypes, &item->parent_itemid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		item_prototype = item_prototypes->values[index];

		application_index_local.lld_row = item->lld_row;

		if (0 == (item_application_local.item_ref.itemid = item->itemid))
			item_application_local.item_ref.item = item;
		else
			item_application_local.item_ref.item = NULL;

		for (j = 0; j < item_prototype->applications.values_num; j++)
		{
			itemapp_prototype = item_prototype->applications.values[j];

			if (NULL != itemapp_prototype->application_prototype)
			{
				application_index_local.application_prototypeid =
						itemapp_prototype->application_prototype->application_prototypeid;

				if (NULL == (application_index = zbx_hashset_search(applications_index,
						&application_index_local)))
				{
					continue;
				}

				application = application_index->application;

				if (0 == (application->flags & ZBX_FLAG_LLD_APPLICATION_DISCOVERED))
					continue;

				if (0 == (item_application_local.application_ref.applicationid =
						application->applicationid))
				{
					item_application_local.application_ref.application = application;
				}
				else
					item_application_local.application_ref.application = NULL;
			}
			else
			{
				item_application_local.application_ref.application = NULL;
				item_application_local.application_ref.applicationid = itemapp_prototype->applicationid;
			}

			if (NULL == (item_application = zbx_hashset_search(items_applications,
					&item_application_local)))
			{
				item_application = zbx_hashset_insert(items_applications, &item_application_local,
						sizeof(zbx_lld_item_application_t));
			}

			item_application->flags = ZBX_FLAG_LLD_ITEM_APPLICATION_DISCOVERED;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d links", __function_name, items_applications->num_data);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_item_prototypes_get                                          *
 *                                                                            *
 * Purpose: load discovery rule item prototypes                               *
 *                                                                            *
 * Parameters: lld_ruleid      - [IN] the discovery rule id                   *
 *             item_prototypes - [OUT] the item prototypes                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_item_prototypes_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *item_prototypes)
{
	const char			*__function_name = "lld_item_prototypes_get";
	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_item_prototype_t	*item_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select i.itemid,i.name,i.key_,i.type,i.value_type,i.data_type,i.delay,i.delay_flex,"
				"i.history,i.trends,i.status,i.trapper_hosts,i.units,i.multiplier,i.delta,i.formula,"
				"i.logtimefmt,i.valuemapid,i.params,i.ipmi_sensor,i.snmp_community,i.snmp_oid,"
				"i.port,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authprotocol,"
				"i.snmpv3_authpassphrase,i.snmpv3_privprotocol,i.snmpv3_privpassphrase,i.authtype,"
				"i.username,i.password,i.publickey,i.privatekey,i.description,i.interfaceid,"
				"i.snmpv3_contextname"
			" from items i,item_discovery id"
			" where i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		item_prototype = (zbx_lld_item_prototype_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_prototype_t));

		ZBX_STR2UINT64(item_prototype->itemid, row[0]);
		item_prototype->name = zbx_strdup(NULL, row[1]);
		item_prototype->key = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(item_prototype->type, row[3]);
		ZBX_STR2UCHAR(item_prototype->value_type, row[4]);
		ZBX_STR2UCHAR(item_prototype->data_type, row[5]);
		item_prototype->delay = atoi(row[6]);
		item_prototype->delay_flex = zbx_strdup(NULL, row[7]);
		item_prototype->history = atoi(row[8]);
		item_prototype->trends = atoi(row[9]);
		ZBX_STR2UCHAR(item_prototype->status, row[10]);
		item_prototype->trapper_hosts = zbx_strdup(NULL, row[11]);
		item_prototype->units = zbx_strdup(NULL, row[12]);
		ZBX_STR2UCHAR(item_prototype->multiplier, row[13]);
		ZBX_STR2UCHAR(item_prototype->delta, row[14]);
		item_prototype->formula = zbx_strdup(NULL, row[15]);
		item_prototype->logtimefmt = zbx_strdup(NULL, row[16]);
		ZBX_DBROW2UINT64(item_prototype->valuemapid, row[17]);
		item_prototype->params = zbx_strdup(NULL, row[18]);
		item_prototype->ipmi_sensor = zbx_strdup(NULL, row[19]);
		item_prototype->snmp_community = zbx_strdup(NULL, row[20]);
		item_prototype->snmp_oid = zbx_strdup(NULL, row[21]);
		item_prototype->port = zbx_strdup(NULL, row[22]);
		item_prototype->snmpv3_securityname = zbx_strdup(NULL, row[23]);
		ZBX_STR2UCHAR(item_prototype->snmpv3_securitylevel, row[24]);
		ZBX_STR2UCHAR(item_prototype->snmpv3_authprotocol, row[25]);
		item_prototype->snmpv3_authpassphrase = zbx_strdup(NULL, row[26]);
		ZBX_STR2UCHAR(item_prototype->snmpv3_privprotocol, row[27]);
		item_prototype->snmpv3_privpassphrase = zbx_strdup(NULL, row[28]);
		ZBX_STR2UCHAR(item_prototype->authtype, row[29]);
		item_prototype->username = zbx_strdup(NULL, row[30]);
		item_prototype->password = zbx_strdup(NULL, row[31]);
		item_prototype->publickey = zbx_strdup(NULL, row[32]);
		item_prototype->privatekey = zbx_strdup(NULL, row[33]);
		item_prototype->description = zbx_strdup(NULL, row[34]);
		ZBX_DBROW2UINT64(item_prototype->interfaceid, row[35]);
		item_prototype->snmpv3_contextname = zbx_strdup(NULL, row[36]);

		zbx_vector_ptr_create(&item_prototype->lld_rows);
		zbx_vector_ptr_create(&item_prototype->applications);

		zbx_vector_ptr_append(item_prototypes, item_prototype);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(item_prototypes, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d prototypes", __function_name, item_prototypes->values_num);
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

	zbx_vector_ptr_t	applications, application_prototypes, items, item_prototypes;
	zbx_hashset_t		applications_index, items_index, items_applications;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&item_prototypes);

	lld_item_prototypes_get(lld_ruleid, &item_prototypes);

	if (0 == item_prototypes.values_num)
		goto out;

	zbx_vector_ptr_create(&application_prototypes);

	lld_application_prototypes_get(lld_ruleid, &application_prototypes);

	zbx_vector_ptr_create(&applications);
	zbx_hashset_create(&applications_index, application_prototypes.values_num * lld_rows->values_num,
			lld_application_index_hash_func, lld_application_index_compare_func);

	zbx_vector_ptr_create(&items);
	zbx_hashset_create(&items_index, item_prototypes.values_num * lld_rows->values_num, lld_item_index_hash_func,
			lld_item_index_compare_func);

	zbx_hashset_create(&items_applications, 100, lld_item_application_hash_func, lld_item_application_compare_func);

	lld_applications_get(lld_ruleid, &applications);
	lld_applications_make(&application_prototypes, lld_rows, &applications, &applications_index);
	lld_applications_validate(hostid, lld_ruleid, &applications, &applications_index, error);

	lld_item_application_prototypes_get(&item_prototypes, &application_prototypes);

	lld_items_get(&item_prototypes, &items);
	lld_items_make(&item_prototypes, lld_rows, &items, &items_index);
	lld_items_validate(hostid, &items, error);

	lld_items_applications_get(lld_ruleid, &items_applications);
	lld_items_applications_make(&item_prototypes, &items, &applications, &applications_index, &items_applications);

	DBbegin();

	lld_items_save(hostid, &item_prototypes, &items);
	lld_applications_save(hostid, &applications, &application_prototypes);
	lld_items_applications_save(&items_applications, &items, &applications);

	DBcommit();

	lld_item_links_populate(&item_prototypes, lld_rows, &items_index);
	lld_remove_lost_items(&items, lifetime, lastcheck);
	lld_remove_lost_applications(lld_ruleid, &applications, lifetime, lastcheck);
	lld_item_links_sort(lld_rows);

	zbx_hashset_destroy(&items_applications);
	zbx_hashset_destroy(&items_index);

	zbx_vector_ptr_clear_ext(&items, (zbx_clean_func_t)lld_item_free);
	zbx_vector_ptr_destroy(&items);

	zbx_hashset_destroy(&applications_index);

	zbx_vector_ptr_clear_ext(&applications, (zbx_clean_func_t)lld_application_free);
	zbx_vector_ptr_destroy(&applications);

	zbx_vector_ptr_clear_ext(&application_prototypes, (zbx_clean_func_t)lld_application_prototype_free);
	zbx_vector_ptr_destroy(&application_prototypes);

	zbx_vector_ptr_clear_ext(&item_prototypes, (zbx_clean_func_t)lld_item_prototype_free);
out:
	zbx_vector_ptr_destroy(&item_prototypes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
