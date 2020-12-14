/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "dbupgrade.h"

/*
 * 5.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5030000(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.queue.config'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030001(void)
{
	const ZBX_TABLE table =
		{"valuemap", "valuemapid", 0,
			{
				{"valuemapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5030002(void)
{
	return DBcreate_index("valuemap", "valuemap_1", "hostid,name", 1);
}

static int	DBpatch_5030003(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("valuemap", 1, &field);
}

static int	DBpatch_5030004(void)
{
	const ZBX_TABLE table =
		{"valuemap_mapping", "valuemap_mappingid", 0,
			{
				{"valuemap_mappingid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"valuemapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 64, ZBX_TYPE_CHAR,	ZBX_NOTNULL, 0},
				{"newvalue", "", NULL, NULL, 64, ZBX_TYPE_CHAR,	ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5030005(void)
{
	return DBcreate_index("valuemap_mapping", "valuemap_mapping_1", "valuemapid", 0);
}

static int	DBpatch_5030006(void)
{
	const ZBX_FIELD	field = {"valuemapid", NULL, "valuemap", "valuemapid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("valuemap_mapping", 1, &field);
}

static int	DBpatch_5030007(void)
{
	const ZBX_FIELD	field = {"valuemap", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_5030008(void)
{
	int	ret;

	ret = DBexecute("update items set valuemap="
			"(select name from valuemaps where items.valuemapid=valuemaps.valuemapid)"
			" where valuemapid is not NULL");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

typedef struct
{
	zbx_uint64_t		valuemapid;
	char			*name;
	zbx_vector_ptr_pair_t	mappings;
}
valuemap_t;

static int	DBpatch_5030009(void)
{
	DB_RESULT			result;
	DB_ROW				row;
	int				ret = SUCCEED, i, j;
	zbx_hashset_t			valuemaps;
	zbx_hashset_iter_t		iter;
	valuemap_t			valuemap_local, *valuemap;
	zbx_uint64_t			valuemapid;
	zbx_vector_uint64_pair_t	pairs;
	zbx_db_insert_t			db_insert_valuemap, db_insert_valuemap_mapping;

	zbx_hashset_create(&valuemaps, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_pair_create(&pairs);
	zbx_vector_uint64_pair_reserve(&pairs, 1000);

	result = DBselect(
			"select v.valuemapid,v.name,m.value,m.newvalue"
			" from valuemaps v"
			" join mappings m on v.valuemapid=m.valuemapid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_ptr_pair_t	pair;

		ZBX_STR2UINT64(valuemap_local.valuemapid, row[0]);

		if (NULL == (valuemap = (valuemap_t *)zbx_hashset_search(&valuemaps, &valuemap_local)))
		{
			valuemap = zbx_hashset_insert(&valuemaps, &valuemap_local, sizeof(valuemap_local));
			valuemap->name = zbx_strdup(NULL, row[1]);
			zbx_vector_ptr_pair_create(&valuemap->mappings);
		}

		pair.first = zbx_strdup(NULL, row[2]);
		pair.second = zbx_strdup(NULL, row[3]);

		zbx_vector_ptr_pair_append(&valuemap->mappings, pair);
	}
	DBfree_result(result);

	result = DBselect("select hostid,valuemapid"
			" from items"
			" where templateid is null"
				" and valuemapid is not null"
				" and flags in (0,2)");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_pair_t	pair;

		ZBX_STR2UINT64(pair.first, row[0]);
		ZBX_STR2UINT64(pair.second, row[1]);

		zbx_vector_uint64_pair_append(&pairs, pair);
	}
	DBfree_result(result);

	zbx_vector_uint64_pair_sort(&pairs, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);
	zbx_vector_uint64_pair_uniq(&pairs, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	valuemapid = DBget_maxid_num("valuemap", pairs.values_num);

	zbx_db_insert_prepare(&db_insert_valuemap, "valuemap", "valuemapid", "hostid", "name", NULL);
	zbx_db_insert_prepare(&db_insert_valuemap_mapping, "valuemap_mapping", "valuemap_mappingid", "valuemapid",
			"value", "newvalue", NULL);

	for (i = 0; i < pairs.values_num; i++)
	{
		zbx_uint64_pair_t	pair;

		pair = pairs.values[i];

		if (NULL == (valuemap = (valuemap_t *)zbx_hashset_search(&valuemaps, &pair.second)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
			break;
		}

		zbx_db_insert_add_values(&db_insert_valuemap, valuemapid, pair.first, valuemap->name);

		for (j = 0; j < valuemap->mappings.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert_valuemap_mapping, __UINT64_C(0), valuemapid,
					valuemap->mappings.values[j].first,
					valuemap->mappings.values[j].second);
		}

		valuemapid++;
	}

	zbx_hashset_iter_reset(&valuemaps, &iter);
	while (NULL != (valuemap = (valuemap_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_free(valuemap->name);

		for (i = 0; i < valuemap->mappings.values_num; i++)
		{
			zbx_free(valuemap->mappings.values[i].first);
			zbx_free(valuemap->mappings.values[i].second);
		}
		zbx_vector_ptr_pair_destroy(&valuemap->mappings);
	}

	zbx_hashset_destroy(&valuemaps);

	zbx_vector_uint64_pair_destroy(&pairs);

	zbx_db_insert_execute(&db_insert_valuemap);
	zbx_db_insert_clean(&db_insert_valuemap);

	zbx_db_insert_autoincrement(&db_insert_valuemap_mapping, "valuemap_mappingid");
	zbx_db_insert_execute(&db_insert_valuemap_mapping);
	zbx_db_insert_clean(&db_insert_valuemap_mapping);

	return ret;
}

static int	DBpatch_5030010(void)
{
	return DBdrop_foreign_key("items", 3);
}

static int	DBpatch_5030011(void)
{
	return DBdrop_index("items", "items_5");
}

static int	DBpatch_5030012(void)
{
	return DBdrop_field("items", "valuemapid");
}

static int	DBpatch_5030013(void)
{
	return DBdrop_table("mappings");
}

static int	DBpatch_5030014(void)
{
	return DBdrop_table("valuemaps");
}

#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)
DBPATCH_ADD(5030002, 0, 1)
DBPATCH_ADD(5030003, 0, 1)
DBPATCH_ADD(5030004, 0, 1)
DBPATCH_ADD(5030005, 0, 1)
DBPATCH_ADD(5030006, 0, 1)
DBPATCH_ADD(5030007, 0, 1)
DBPATCH_ADD(5030008, 0, 1)
DBPATCH_ADD(5030009, 0, 1)
DBPATCH_ADD(5030010, 0, 1)
DBPATCH_ADD(5030011, 0, 1)
DBPATCH_ADD(5030012, 0, 1)
DBPATCH_ADD(5030013, 0, 1)
DBPATCH_ADD(5030014, 0, 1)

DBPATCH_END()
