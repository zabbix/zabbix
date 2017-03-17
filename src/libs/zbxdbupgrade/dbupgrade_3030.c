/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * 3.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3030000(void)
{
	const ZBX_FIELD	field = {"ipmi_authtype", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("hosts", &field);
}

static int	DBpatch_3030001(void)
{
	const ZBX_FIELD field = {"snmp_oid", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030002(void)
{
	const ZBX_FIELD field = {"key_", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("dchecks", &field);
}

static int	DBpatch_3030003(void)
{
	return DBdrop_field("proxy_dhistory", "type");
}

static int	DBpatch_3030004(void)
{
	return DBdrop_field("proxy_dhistory", "key_");
}

static int	DBpatch_3030005(void)
{
	return DBdrop_foreign_key("dservices", 2);
}

static int	DBpatch_3030006(void)
{
	return DBdrop_index("dservices", "dservices_1");
}

static int	DBpatch_3030007(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_vector_uint64_t	dserviceids;
	zbx_uint64_t		dserviceid;
	int			ret = SUCCEED;

	zbx_vector_uint64_create(&dserviceids);

	/* After dropping fields type and key_ from table dservices there is no guarantee that a unique
	index with fields dcheckid, ip and port can be created. To create a unique index for the same
	fields later this will delete rows where all three of them are identical only leaving the latest. */
	result = DBselect(
			"select ds.dserviceid"
			" from dservices ds"
			" where not exists ("
				"select null"
				" from dchecks dc"
				" where ds.dcheckid = dc.dcheckid"
					" and ds.type = dc.type"
					" and ds.key_ = dc.key_"
			")");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(dserviceid, row[0]);

		zbx_vector_uint64_append(&dserviceids, dserviceid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != dserviceids.values_num)
		ret = DBexecute_multiple_query("delete from dservices where", "dserviceid", &dserviceids);

	zbx_vector_uint64_destroy(&dserviceids);

	return ret;
}

static int	DBpatch_3030008(void)
{
	return DBdrop_field("dservices", "type");
}

static int	DBpatch_3030009(void)
{
	return DBdrop_field("dservices", "key_");
}

static int	DBpatch_3030010(void)
{
	return DBcreate_index("dservices", "dservices_1", "dcheckid,ip,port", 1);
}

static int	DBpatch_3030011(void)
{
	const ZBX_FIELD	field = {"dcheckid", NULL, "dchecks", "dcheckid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dservices", 2, &field);
}

static int	DBpatch_3030012(void)
{
	const ZBX_FIELD field = {"snmp_lastsize", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("globalvars", &field);
}

static int	DBpatch_3030013(void)
{
	const ZBX_FIELD field = {"period", "1-7,00:00-24:00", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("media", &field);
}

static int	DBpatch_3030014(void)
{
	const ZBX_FIELD field = {"delay_flex", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030015(void)
{
	const ZBX_TABLE table =
			{"item_preproc", "item_preprocid", 0,
				{
					{"item_preprocid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"step", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030016(void)
{
	return DBcreate_index("item_preproc", "item_preproc_1", "itemid, step", 0);
}

static int	DBpatch_3030017(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_preproc", 1, &field);
}

static void	DBpatch_3030018_add_numeric_preproc_steps(zbx_db_insert_t *db_insert, zbx_uint64_t itemid,
		unsigned char data_type, const char *formula, unsigned char delta)
{
	int	step = 1;

	switch (data_type)
	{
		case ITEM_DATA_TYPE_BOOLEAN:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_BOOL2DEC, "");
			break;
		case ITEM_DATA_TYPE_OCTAL:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_OCT2DEC, "");
			break;
		case ITEM_DATA_TYPE_HEXADECIMAL:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_HEX2DEC, "");
			break;
	}

	switch (delta)
	{
		case ITEM_STORE_SPEED_PER_SECOND:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_DELTA_SPEED, "");
			break;
		case ITEM_STORE_SIMPLE_CHANGE:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_DELTA_VALUE, "");
			break;
	}

	if (NULL != formula)
		zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_MULTIPLIER, formula);

}

static int	DBpatch_3030018(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	unsigned char	value_type, data_type, delta;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	itemid;
	const char	*formula;
	int		ret;

	zbx_db_insert_prepare(&db_insert, "item_preproc", "item_preprocid", "itemid", "step", "type", "params", NULL);

	result = DBselect("select itemid,value_type,data_type,multiplier,formula,delta from items");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UCHAR(value_type, row[1]);

		switch (value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UCHAR(data_type, row[2]);
				formula = (1 == atoi(row[3]) ? row[4] : NULL);
				ZBX_STR2UCHAR(delta, row[5]);
				DBpatch_3030018_add_numeric_preproc_steps(&db_insert, itemid, data_type, formula,
						delta);
				break;
		}
	}

	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "item_preprocid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_3030019(void)
{
	return DBdrop_field("items", "multiplier");
}

static int	DBpatch_3030020(void)
{
	return DBdrop_field("items", "data_type");
}

static int	DBpatch_3030021(void)
{
	return DBdrop_field("items", "delta");
}

static int	DBpatch_3030022(void)
{
	if (ZBX_DB_OK > DBexecute("update items set formula='' where flags<>1 or evaltype<>3"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3030023(void)
{
	if (ZBX_DB_OK > DBexecute("delete from profiles where idx like 'web.dashboard.widget.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3030024(void)
{
	const ZBX_TABLE table =
			{"httptest_field", "httptest_fieldid", 0,
				{
					{"httptest_fieldid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"httptestid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030025(void)
{
	return DBcreate_index("httptest_field", "httptest_field_1", "httptestid", 0);
}

static int	DBpatch_3030026(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest_field", 1, &field);
}

static int	DBpatch_3030027(void)
{
	const ZBX_TABLE table =
			{"httpstep_field", "httpstep_fieldid", 0,
				{
					{"httpstep_fieldid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"httpstepid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030028(void)
{
	return DBcreate_index("httpstep_field", "httpstep_field_1", "httpstepid", 0);
}

static int	DBpatch_3030029(void)
{
	const ZBX_FIELD	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httpstep_field", 1, &field);
}

static int 	DBpatch_3030030_pair_cmp_func(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*pair1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*pair2 = (const zbx_ptr_pair_t *)d2;

	return strcmp((char *)pair1->first, (char *)pair2->first);
}

#define TRIM_LEADING_WHITESPACE(ptr)	while (' ' == *ptr || '\t' == *ptr) ptr++;
#define TRIM_TRAILING_WHITESPACE(ptr)	do { ptr--; } while (' ' == *ptr || '\t' == *ptr);

static void	DBpatch_3030030_append_pairs(zbx_db_insert_t *db_insert, zbx_uint64_t parentid, int type,
		const char *source, const char separator, int unique, int allow_empty)
{
	char			*buffer, *key = buffer, *value, replace;
	zbx_vector_ptr_pair_t	pairs;
	zbx_ptr_pair_t		pair;
	int			index;

	buffer = zbx_strdup(NULL, source);
	zbx_vector_ptr_pair_create(&pairs);

	while ('\0' != *key)
	{
		char	*ptr = key;

		/* find end of the line */
		while ('\0' != *ptr && '\n' != *ptr && '\r' != *ptr)
			ptr++;

		replace = *ptr;
		*ptr = '\0';

		/* parse line */
		value = strchr(key, separator);

		/* if separator is absent and empty values are allowed, consider that value is empty */
		if (0 != allow_empty && NULL == value)
			value = ptr;

		if (NULL != value)
		{
			char	*tail = value;

			if (ptr != value)
				value++;

			TRIM_LEADING_WHITESPACE(key);
			if (key != tail)
			{
				TRIM_TRAILING_WHITESPACE(tail);
				tail[1] = '\0';
			}
			else
				goto skip;	/* no key */

			tail = ptr;
			TRIM_LEADING_WHITESPACE(value);
			if (value != tail)
			{
				TRIM_TRAILING_WHITESPACE(tail);
				tail[1] = '\0';
			}
			else
			{
				if (0 == allow_empty)
					goto skip;	/* no value */
			}

			pair.first = key;

			if (0 == unique || FAIL == (index = zbx_vector_ptr_pair_search(&pairs, pair,
					DBpatch_3030030_pair_cmp_func)))
			{
				pair.second = value;
				zbx_vector_ptr_pair_append(&pairs, pair);
			}
			else
				pairs.values[index].second = value;
		}
skip:
		if ('\0' != replace)
			ptr++;

		/* skip LF/CR symbols until the next nonempty line */
		while ('\n' == *ptr || '\r' == *ptr)
			ptr++;

		key = ptr;
	}

	for (index = 0; index < pairs.values_num; index++)
	{
		pair = pairs.values[index];
		zbx_db_insert_add_values(db_insert, __UINT64_C(0), parentid, type, pair.first, pair.second);
	}

	zbx_vector_ptr_pair_destroy(&pairs);
	zbx_free(buffer);
}

static int	DBpatch_3030030_migrate_pairs(const char *table, const char *field, int type, char separator,
		int unique, int allow_empty)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	parentid;
	char		*target, *target_id, *source_id;
	int		len, ret;

	len = strlen(table) + 1;
	target = zbx_malloc(NULL, len + ZBX_CONST_STRLEN("_field"));
	zbx_strlcpy(target, table, len);
	zbx_strlcat(target, "_field", ZBX_CONST_STRLEN("_field"));

	target_id = zbx_malloc(NULL, len + ZBX_CONST_STRLEN("_fieldid"));
	zbx_strlcpy(target_id, table, len);
	zbx_strlcat(target_id, "_fieldid", ZBX_CONST_STRLEN("_field"));

	source_id = zbx_malloc(NULL, len + ZBX_CONST_STRLEN("id"));
	zbx_strlcpy(source_id, table, len);
	zbx_strlcat(source_id, "id", ZBX_CONST_STRLEN("id"));

	zbx_db_insert_prepare(&db_insert, target, target_id, source_id, "type", "name", "value", NULL);

	result = DBselect("select %s, %s from %s", source_id, field, table);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(parentid, row[0]);

		if (0 != strlen(row[1]))
		{
			DBpatch_3030030_append_pairs(&db_insert, parentid, type, row[1], separator, unique,
					allow_empty);
		}
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, target_id);
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(target);
	zbx_free(target_id);
	zbx_free(source_id);

	return ret;
}

static int	DBpatch_3030030(void)
{
	return DBpatch_3030030_migrate_pairs("httptest", "variables", ZBX_HTTPFIELD_VARIABLE, '=', 1, 1);
}

static int	DBpatch_3030031(void)
{
	return DBdrop_field("httptest", "variables");
}

static int	DBpatch_3030032(void)
{
	/* headers without value are not allowed by rfc7230 */
	return DBpatch_3030030_migrate_pairs("httptest", "headers", ZBX_HTTPFIELD_HEADER, ':', 0, 0);
}

static int	DBpatch_3030033(void)
{
	return DBdrop_field("httptest", "headers");
}

static int	DBpatch_3030034(void)
{
	return DBpatch_3030030_migrate_pairs("httpstep", "variables", ZBX_HTTPFIELD_VARIABLE, '=', 1, 1);
}

static int	DBpatch_3030035(void)
{
	return DBdrop_field("httpstep", "variables");
}

static int	DBpatch_3030036(void)
{
	return DBpatch_3030030_migrate_pairs("httpstep", "headers", ZBX_HTTPFIELD_HEADER, ':', 0, 0);
}

static int	DBpatch_3030037(void)
{
	return DBdrop_field("httpstep", "headers");
}

static int	DBpatch_3030038(void)
{
	const ZBX_FIELD	field = {"post_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("httpstep", &field);
}

#endif

DBPATCH_START(3030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3030000, 0, 1)
DBPATCH_ADD(3030001, 0, 1)
DBPATCH_ADD(3030002, 0, 1)
DBPATCH_ADD(3030003, 0, 1)
DBPATCH_ADD(3030004, 0, 1)
DBPATCH_ADD(3030005, 0, 1)
DBPATCH_ADD(3030006, 0, 1)
DBPATCH_ADD(3030007, 0, 1)
DBPATCH_ADD(3030008, 0, 1)
DBPATCH_ADD(3030009, 0, 1)
DBPATCH_ADD(3030010, 0, 1)
DBPATCH_ADD(3030011, 0, 1)
DBPATCH_ADD(3030012, 0, 1)
DBPATCH_ADD(3030013, 0, 1)
DBPATCH_ADD(3030014, 0, 1)
DBPATCH_ADD(3030015, 0, 1)
DBPATCH_ADD(3030016, 0, 1)
DBPATCH_ADD(3030017, 0, 1)
DBPATCH_ADD(3030018, 0, 1)
DBPATCH_ADD(3030019, 0, 1)
DBPATCH_ADD(3030020, 0, 1)
DBPATCH_ADD(3030021, 0, 1)
DBPATCH_ADD(3030022, 0, 1)
DBPATCH_ADD(3030023, 0, 0)
DBPATCH_ADD(3030024, 0, 1)
DBPATCH_ADD(3030025, 0, 1)
DBPATCH_ADD(3030026, 0, 1)
DBPATCH_ADD(3030027, 0, 1)
DBPATCH_ADD(3030028, 0, 1)
DBPATCH_ADD(3030029, 0, 1)
DBPATCH_ADD(3030030, 0, 1)
DBPATCH_ADD(3030031, 0, 1)
DBPATCH_ADD(3030032, 0, 1)
DBPATCH_ADD(3030033, 0, 1)
DBPATCH_ADD(3030034, 0, 1)
DBPATCH_ADD(3030035, 0, 1)
DBPATCH_ADD(3030036, 0, 1)
DBPATCH_ADD(3030037, 0, 1)
DBPATCH_ADD(3030038, 0, 1)

DBPATCH_END()
