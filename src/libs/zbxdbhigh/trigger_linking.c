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

#include "trigger_linking.h"

#include "db.h"
#include "zbxeval.h"
#include "log.h"
#include "../../libs/zbxaudit/audit.h"
#include "../../libs/zbxaudit/audit_trigger.h"
#include "../../libs/zbxalgo/vectorimpl.h"
#include "trigger_dep_linking.h"

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	triggerid;
	char		*description;
	char		*expression;
	char		*recovery_expression;
	zbx_uint64_t	templateid;
	unsigned char	flags;

	unsigned char	recovery_mode;
	unsigned char	correlation_mode;
	unsigned char	manual_close;
	char		*opdata;
	unsigned char	discover;
	char		*event_name;

	unsigned char	priority;
	char		*comments;
	char		*url;
	char		*correlation_tag;
	unsigned char	status;
	unsigned char	type;
}
zbx_trigger_copy_t;

ZBX_PTR_VECTOR_DECL(trigger_copies_templates, zbx_trigger_copy_t *)
ZBX_PTR_VECTOR_IMPL(trigger_copies_templates, zbx_trigger_copy_t *)

ZBX_PTR_VECTOR_DECL(trigger_copies_insert, zbx_trigger_copy_t *)
ZBX_PTR_VECTOR_IMPL(trigger_copies_insert, zbx_trigger_copy_t *)

/* TARGET HOST TRIGGER DATA */
typedef struct
{
	zbx_uint64_t	triggerid;
	char		*description;
	char		*expression;
	char		*recovery_expression;
	zbx_uint64_t	templateid_orig;
	zbx_uint64_t	templateid;
	unsigned char	flags;

	unsigned char	recovery_mode_orig;
	unsigned char	recovery_mode;
	unsigned char	correlation_mode_orig;
	unsigned char	correlation_mode;
	char		*correlation_tag_orig;
	char		*correlation_tag;
	unsigned char	manual_close_orig;
	unsigned char	manual_close;
	char		*opdata_orig;
	char		*opdata;
	unsigned char	discover_orig;
	unsigned char	discover;
	char		*event_name_orig;
	char		*event_name;
	unsigned char	priority_orig;
	unsigned char	priority;
	char		*comments_orig;
	char		*comments;
	char		*url_orig;
	char		*url;
	unsigned char	status_orig;
	unsigned char	status;
	unsigned char	type_orig;
	unsigned char	type;

#define ZBX_FLAG_LINK_TRIGGER_UNSET			__UINT64_C(0x00000000)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_RECOVERY_MODE	__UINT64_C(0x00000001)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_MODE	__UINT64_C(0x00000002)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_TAG	__UINT64_C(0x00000004)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_MANUAL_CLOSE	__UINT64_C(0x00000008)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_OPDATA		__UINT64_C(0x00000010)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_DISCOVER		__UINT64_C(0x00000020)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_EVENT_NAME		__UINT64_C(0x00000040)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_PRIORITY		__UINT64_C(0x00000080)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_COMMENTS		__UINT64_C(0x00000100)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_URL		__UINT64_C(0x00000200)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_TYPE		__UINT64_C(0x00000400)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_TEMPLATEID		__UINT64_C(0x00000800)
#define ZBX_FLAG_LINK_TRIGGER_UPDATE_STATUS		__UINT64_C(0x00001000)

#define ZBX_FLAG_LINK_TRIGGER_UPDATE									\
	(ZBX_FLAG_LINK_TRIGGER_UPDATE_RECOVERY_MODE | ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_MODE |	\
	ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_TAG | ZBX_FLAG_LINK_TRIGGER_UPDATE_MANUAL_CLOSE |	\
	ZBX_FLAG_LINK_TRIGGER_UPDATE_OPDATA | ZBX_FLAG_LINK_TRIGGER_UPDATE_DISCOVER |			\
	ZBX_FLAG_LINK_TRIGGER_UPDATE_EVENT_NAME | ZBX_FLAG_LINK_TRIGGER_UPDATE_PRIORITY |		\
	ZBX_FLAG_LINK_TRIGGER_UPDATE_COMMENTS | ZBX_FLAG_LINK_TRIGGER_UPDATE_URL |			\
	ZBX_FLAG_LINK_TRIGGER_UPDATE_TYPE | ZBX_FLAG_LINK_TRIGGER_UPDATE_TEMPLATEID |			\
	ZBX_FLAG_LINK_TRIGGER_UPDATE_STATUS)

	zbx_uint64_t	update_flags;
}
zbx_target_host_trigger_entry_t;

ZBX_PTR_VECTOR_DECL(target_host_trigger_data, zbx_target_host_trigger_entry_t *)
ZBX_PTR_VECTOR_IMPL(target_host_trigger_data, zbx_target_host_trigger_entry_t *)

static zbx_hash_t	zbx_host_triggers_main_data_hash_func(const void *data)
{
	const zbx_target_host_trigger_entry_t	* trigger_entry = (const zbx_target_host_trigger_entry_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((trigger_entry)->triggerid), sizeof((trigger_entry)->triggerid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_host_triggers_main_data_compare_func(const void *d1, const void *d2)
{
	const zbx_target_host_trigger_entry_t	*trigger_entry_1 = (const zbx_target_host_trigger_entry_t *)d1;
	const zbx_target_host_trigger_entry_t	*trigger_entry_2 = (const zbx_target_host_trigger_entry_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(trigger_entry_1->triggerid, trigger_entry_2->triggerid);

	return 0;
}

static void	zbx_host_triggers_main_data_clean(zbx_hashset_t *h)
{
	zbx_hashset_iter_t		iter;
	zbx_target_host_trigger_entry_t	*trigger_entry;

	zbx_hashset_iter_reset(h, &iter);

	while (NULL != (trigger_entry = (zbx_target_host_trigger_entry_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_free(trigger_entry->description);
		zbx_free(trigger_entry->expression);
		zbx_free(trigger_entry->recovery_expression);
		zbx_free(trigger_entry->correlation_tag_orig);

		if (0 != (trigger_entry->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_TAG))
			zbx_free(trigger_entry->correlation_tag);

		zbx_free(trigger_entry->opdata_orig);

		if (0 != (trigger_entry->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_OPDATA))
			zbx_free(trigger_entry->opdata);

		zbx_free(trigger_entry->event_name_orig);

		if (0 != (trigger_entry->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_EVENT_NAME))
			zbx_free(trigger_entry->event_name);

		zbx_free(trigger_entry->comments_orig);

		if (0 != (trigger_entry->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_COMMENTS))
			zbx_free(trigger_entry->comments);

		zbx_free(trigger_entry->url_orig);

		if (0 != (trigger_entry->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_URL))
			zbx_free(trigger_entry->url);

	}

	zbx_hashset_destroy(h);
}

/* TRIGGER FUNCTIONS DATA */
typedef struct zbx_trigger_functions_entry
{
	zbx_uint64_t		triggerid;

	zbx_vector_str_t	functionids;
	zbx_vector_uint64_t	itemids;
	zbx_vector_str_t	itemkeys;
	zbx_vector_str_t	parameters;
	zbx_vector_str_t	names;

} zbx_trigger_functions_entry_t;

static zbx_hash_t	zbx_triggers_functions_hash_func(const void *data)
{
	const zbx_trigger_functions_entry_t	*trigger_entry = (const zbx_trigger_functions_entry_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((trigger_entry)->triggerid), sizeof((trigger_entry)->triggerid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_triggers_functions_compare_func(const void *d1, const void *d2)
{
	const zbx_trigger_functions_entry_t	*trigger_entry_1 = (const zbx_trigger_functions_entry_t *)d1;
	const zbx_trigger_functions_entry_t	*trigger_entry_2 = (const zbx_trigger_functions_entry_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL((trigger_entry_1)->triggerid, (trigger_entry_2)->triggerid);

	return 0;
}

static void	zbx_triggers_functions_clean(zbx_hashset_t *h)
{
	zbx_hashset_iter_t		iter;
	zbx_trigger_functions_entry_t	*trigger_entry;

	zbx_hashset_iter_reset(h, &iter);

	while (NULL != (trigger_entry = (zbx_trigger_functions_entry_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_str_clear_ext(&((trigger_entry)->functionids), zbx_str_free);
		zbx_vector_str_destroy(&((trigger_entry)->functionids));
		zbx_vector_uint64_destroy(&((trigger_entry)->itemids));
		zbx_vector_str_clear_ext(&((trigger_entry)->itemkeys), zbx_str_free);
		zbx_vector_str_destroy(&((trigger_entry)->itemkeys));
		zbx_vector_str_clear_ext(&((trigger_entry)->parameters), zbx_str_free);
		zbx_vector_str_destroy(&((trigger_entry)->parameters));
		zbx_vector_str_clear_ext(&((trigger_entry)->names), zbx_str_free);
		zbx_vector_str_destroy(&((trigger_entry)->names));
	}

	zbx_hashset_destroy(h);
}

/* TRIGGER DESCRIPTIONS MAP */
typedef struct zbx_trigger_descriptions_entry
{
	const char		*description;
	zbx_vector_uint64_t	triggerids;
} zbx_trigger_descriptions_entry_t;

static zbx_hash_t	zbx_triggers_descriptions_hash_func(const void *data)
{
	const zbx_trigger_descriptions_entry_t	*trigger_entry = (const zbx_trigger_descriptions_entry_t *)data;

	return  ZBX_DEFAULT_STRING_HASH_ALGO(trigger_entry->description, strlen(trigger_entry->description),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_triggers_descriptions_compare_func(const void *d1, const void *d2)
{
	const zbx_trigger_descriptions_entry_t	*trigger_entry_1 = (const zbx_trigger_descriptions_entry_t *)d1;
	const zbx_trigger_descriptions_entry_t	*trigger_entry_2 = (const zbx_trigger_descriptions_entry_t *)d2;

	return strcmp((trigger_entry_1)->description, (trigger_entry_2)->description);
}

static void	zbx_triggers_descriptions_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t			iter;
	zbx_trigger_descriptions_entry_t	*trigger_entry;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (trigger_entry = (zbx_trigger_descriptions_entry_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&(trigger_entry->triggerids));
	}

	zbx_hashset_destroy(x);
}

typedef struct
{
	zbx_uint64_t	triggerid;
	char		*tag;
	char		*value;
	int		flags;
}
zbx_trigger_tag_insert_temp_t;

ZBX_PTR_VECTOR_DECL(trigger_tag_insert_temps, zbx_trigger_tag_insert_temp_t *)
ZBX_PTR_VECTOR_IMPL(trigger_tag_insert_temps, zbx_trigger_tag_insert_temp_t *)

static void	trigger_tag_insert_temp_free(zbx_trigger_tag_insert_temp_t *trigger_tag_insert_temp)
{
	zbx_free(trigger_tag_insert_temp->tag);
	zbx_free(trigger_tag_insert_temp->value);
	zbx_free(trigger_tag_insert_temp);
}

/********************************************************************************
 *                                                                              *
 * Purpose: copies tags from template triggers to created/linked triggers       *
 *                                                                              *
 * Parameters: new_triggerids - the created trigger ids                         *
 *             cur_triggerids - the linked trigfer ids                          *
 *                                                                              *
 * Return value: upon successful completion return SUCCEED, or FAIL on DB error *
 *                                                                              *
 ********************************************************************************/
static int	DBcopy_template_trigger_tags(const zbx_vector_uint64_t *new_triggerids,
		const zbx_vector_uint64_t *cur_triggerids)
{
	DB_RESULT				result;
	DB_ROW					row;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	int					i, ret = SUCCEED;
	zbx_vector_uint64_t			triggerids;
	zbx_db_insert_t				db_insert;
	zbx_vector_trigger_tag_insert_temps_t	trigger_tag_insert_temps;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == new_triggerids->values_num && 0 == cur_triggerids->values_num)
		goto out;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, (size_t)(new_triggerids->values_num + cur_triggerids->values_num));
	zbx_vector_trigger_tag_insert_temps_create(&trigger_tag_insert_temps);

	if (0 != cur_triggerids->values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select tt.triggertagid,tt.triggerid,t.flags"
				" from trigger_tag tt,triggers t"
				" where tt.triggerid=t.triggerid and ");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", cur_triggerids->values,
				cur_triggerids->values_num);

		if (NULL == (result = DBselect("%s", sql)))
		{
			ret = FAIL;
			goto clean;
		}

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t audit_del_triggerid, audit_del_triggertagid;

			ZBX_STR2UINT64(audit_del_triggerid, row[1]);
			ZBX_STR2UINT64(audit_del_triggertagid, row[0]);

			zbx_audit_trigger_update_json_delete_tags(audit_del_triggerid, atoi(row[2]),
					audit_del_triggertagid);
		}

		sql_offset = 0;
		DBfree_result(result);

		/* remove tags from host triggers that were linked to template triggers */
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from trigger_tag where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", cur_triggerids->values,
				cur_triggerids->values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
		{
			ret = FAIL;
			goto clean;
		}

		sql_offset = 0;

		for (i = 0; i < cur_triggerids->values_num; i++)
			zbx_vector_uint64_append(&triggerids, cur_triggerids->values[i]);
	}

	for (i = 0; i < new_triggerids->values_num; i++)
		zbx_vector_uint64_append(&triggerids, new_triggerids->values[i]);

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.triggerid,tt.tag,tt.value,t.flags"
			" from trigger_tag tt,triggers t"
			" where tt.triggerid=t.templateid"
			" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", triggerids.values, triggerids.values_num);

	if (NULL == (result = DBselect("%s", sql)))
	{
		ret = FAIL;
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		zbx_trigger_tag_insert_temp_t	*zbx_trigger_tag_insert_temp;

		zbx_trigger_tag_insert_temp = (zbx_trigger_tag_insert_temp_t *)zbx_malloc(NULL,
				sizeof(zbx_trigger_tag_insert_temp_t));
		ZBX_STR2UINT64(zbx_trigger_tag_insert_temp->triggerid, row[0]);
		zbx_trigger_tag_insert_temp->tag = zbx_strdup(NULL, row[1]);
		zbx_trigger_tag_insert_temp->value = zbx_strdup(NULL, row[2]);
		zbx_trigger_tag_insert_temp->flags = atoi(row[3]);
		zbx_vector_trigger_tag_insert_temps_append(&trigger_tag_insert_temps, zbx_trigger_tag_insert_temp);
	}

	if (0 < trigger_tag_insert_temps.values_num)
	{
		zbx_uint64_t	triggertagid;

		triggertagid = DBget_maxid_num("trigger_tag", trigger_tag_insert_temps.values_num);
		zbx_db_insert_prepare(&db_insert, "trigger_tag", "triggertagid", "triggerid", "tag", "value", NULL);

		for (i = 0; i < trigger_tag_insert_temps.values_num; i++)
		{
			zbx_db_insert_add_values(&db_insert, triggertagid,
					trigger_tag_insert_temps.values[i]->triggerid,
					trigger_tag_insert_temps.values[i]->tag,
					trigger_tag_insert_temps.values[i]->value);

			zbx_audit_trigger_update_json_add_tags_and_values(trigger_tag_insert_temps.values[i]->triggerid,
					trigger_tag_insert_temps.values[i]->flags,
					triggertagid, trigger_tag_insert_temps.values[i]->tag,
					trigger_tag_insert_temps.values[i]->value);

			triggertagid++;
		}
	}

	DBfree_result(result);

	zbx_free(sql);

	if (0 < trigger_tag_insert_temps.values_num)
	{
		zbx_db_insert_autoincrement(&db_insert, "triggertagid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}
clean:
	zbx_vector_trigger_tag_insert_temps_clear_ext(&trigger_tag_insert_temps, trigger_tag_insert_temp_free);
	zbx_vector_trigger_tag_insert_temps_destroy(&trigger_tag_insert_temps);
	zbx_vector_uint64_destroy(&triggerids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	get_trigger_funcs(zbx_vector_uint64_t *triggerids, zbx_hashset_t *funcs_res)
{
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	int		res = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == triggerids->values_num)
		goto end;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select f.triggerid,f.functionid,f.parameter,i.itemid,i.key_"
			" from functions f,items i"
			" where i.itemid=f.itemid"
			" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "f.triggerid", triggerids->values,
			triggerids->values_num);

	if (NULL == (result = DBselect("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		zbx_trigger_functions_entry_t	*found, temp_t;
		zbx_uint64_t			itemid_temp_var;

		ZBX_STR2UINT64(temp_t.triggerid, row[0]);
		ZBX_STR2UINT64(itemid_temp_var, row[3]);

		if (NULL != (found =  (zbx_trigger_functions_entry_t *)zbx_hashset_search(funcs_res,
				&temp_t)))
		{
			zbx_vector_str_append(&(found->functionids), zbx_strdup(NULL, row[1]));
			zbx_vector_uint64_append(&(found->itemids), itemid_temp_var);
			zbx_vector_str_append(&(found->itemkeys), zbx_strdup(NULL, row[4]));
			zbx_vector_str_append(&(found->parameters), zbx_strdup(NULL, row[2]));
		}
		else
		{
			zbx_trigger_functions_entry_t	local_temp_t;

			zbx_vector_str_create(&(local_temp_t.functionids));
			zbx_vector_uint64_create(&(local_temp_t.itemids));
			zbx_vector_str_create(&(local_temp_t.itemkeys));

			/* do not need names, but still initialize it to make it consistent with other funcs */
			zbx_vector_str_create(&(local_temp_t.names));
			zbx_vector_str_create(&(local_temp_t.parameters));

			zbx_vector_str_append(&(local_temp_t.functionids), zbx_strdup(NULL, row[1]));
			zbx_vector_uint64_append(&(local_temp_t.itemids), itemid_temp_var);
			zbx_vector_str_append(&(local_temp_t.itemkeys), zbx_strdup(NULL, row[4]));
			zbx_vector_str_append(&(local_temp_t.parameters), zbx_strdup(NULL, row[2]));

			local_temp_t.triggerid = temp_t.triggerid;
			zbx_hashset_insert(funcs_res, &local_temp_t, sizeof(local_temp_t));
		}
	}
clean:
	zbx_free(sql);
	DBfree_result(result);
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	get_templates_triggers_data(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		zbx_vector_trigger_copies_templates_t *trigger_copies_templates,
		zbx_vector_str_t *templates_triggers_descriptions, zbx_vector_uint64_t *temp_templates_triggerids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset = 0;
	int			res = SUCCEED;
	zbx_trigger_copy_t	*trigger_copy;
	DB_RESULT		result;
	DB_ROW			row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.triggerid,t.description,t.expression,t.status,"
				"t.type,t.priority,t.comments,t.url,t.flags,t.recovery_expression,t.recovery_mode,"
				"t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata,t.discover,t.event_name"
			" from triggers t"
			" where t.triggerid in (select distinct tg.triggerid"
				" from triggers tg,functions f,items i"
				" where tg.triggerid=f.triggerid"
					" and f.itemid=i.itemid"
					" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);
	zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

	if (NULL == (result = DBselect("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		trigger_copy = (zbx_trigger_copy_t *)zbx_malloc(NULL, sizeof(zbx_trigger_copy_t));
		trigger_copy->hostid = hostid;
		ZBX_STR2UINT64(trigger_copy->triggerid, row[0]);
		trigger_copy->description = zbx_strdup(NULL, row[1]);
		trigger_copy->expression = zbx_strdup(NULL, row[2]);
		trigger_copy->recovery_expression = zbx_strdup(NULL, row[9]);
		trigger_copy->templateid = trigger_copy->triggerid;
		trigger_copy->flags = (unsigned char)atoi(row[8]);

		trigger_copy->recovery_mode = (unsigned char)atoi(row[10]);
		trigger_copy->correlation_tag = zbx_strdup(NULL, row[12]);
		trigger_copy->manual_close = (unsigned char)atoi(row[13]);
		trigger_copy->opdata = zbx_strdup(NULL, row[14]);
		trigger_copy->discover = (unsigned char)atoi(row[15]);
		trigger_copy->event_name = zbx_strdup(NULL, row[16]);

		trigger_copy->priority = (unsigned char)atoi(row[5]);
		trigger_copy->comments = zbx_strdup(NULL, row[6]);
		trigger_copy->url = zbx_strdup(NULL, row[7]);
		trigger_copy->correlation_mode = (unsigned char)atoi(row[11]);
		trigger_copy->status = (unsigned char)atoi(row[3]);
		trigger_copy->type = (unsigned char)atoi(row[4]);

		zbx_vector_trigger_copies_templates_append(trigger_copies_templates, trigger_copy);
		zbx_vector_str_append(templates_triggers_descriptions, zbx_strdup(NULL, trigger_copy->description));
		zbx_vector_uint64_append(temp_templates_triggerids, trigger_copy->triggerid);
	}
clean:
	zbx_free(sql);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	get_target_host_main_data(zbx_uint64_t hostid, zbx_vector_str_t *templates_triggers_descriptions,
		zbx_hashset_t *zbx_host_triggers_main_data, zbx_vector_uint64_t *temp_host_triggerids,
		zbx_hashset_t *triggers_descriptions)
{
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	int		res = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
		"select t.triggerid,t.description,t.expression,t.recovery_expression"
			",t.flags,t.recovery_mode,t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata"
			",t.discover,t.event_name,t.priority,t.comments,t.url,t.templateid,t.type,t.status"
		" from triggers t"
		" where t.triggerid in (select distinct tg.triggerid"
			" from triggers tg,functions f,items i"
			" where tg.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and tg.templateid is null"
				" and i.hostid=" ZBX_FS_UI64
				" and", hostid);

	DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "tg.description",
			(const char **)templates_triggers_descriptions->values,
			templates_triggers_descriptions->values_num);
	zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

	if (NULL == (result = DBselect("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		zbx_target_host_trigger_entry_t		target_host_trigger_entry;
		zbx_trigger_descriptions_entry_t	*found, temp_t;

		target_host_trigger_entry.update_flags = ZBX_FLAG_LINK_TRIGGER_UNSET;

		ZBX_STR2UINT64(target_host_trigger_entry.triggerid, row[0]);
		target_host_trigger_entry.description = zbx_strdup(NULL, row[1]);
		target_host_trigger_entry.expression = zbx_strdup(NULL, row[2]);
		target_host_trigger_entry.recovery_expression = zbx_strdup(NULL, row[3]);
		ZBX_DBROW2UINT64(target_host_trigger_entry.templateid_orig, row[15]);
		target_host_trigger_entry.templateid = 0;
		ZBX_STR2UINT64(target_host_trigger_entry.flags, row[4]);

		ZBX_STR2UCHAR(target_host_trigger_entry.recovery_mode_orig, row[5]);
		target_host_trigger_entry.recovery_mode = 0;
		ZBX_STR2UCHAR(target_host_trigger_entry.correlation_mode_orig, row[6]);
		target_host_trigger_entry.correlation_mode = 0;
		target_host_trigger_entry.correlation_tag_orig = zbx_strdup(NULL, row[7]);
		target_host_trigger_entry.correlation_tag = NULL;
		ZBX_STR2UCHAR(target_host_trigger_entry.manual_close_orig, row[8]);
		target_host_trigger_entry.manual_close = 0;
		target_host_trigger_entry.opdata_orig = zbx_strdup(NULL, row[9]);
		target_host_trigger_entry.opdata = NULL;
		ZBX_STR2UCHAR(target_host_trigger_entry.discover_orig, row[10]);
		target_host_trigger_entry.discover = 0;
		target_host_trigger_entry.event_name_orig = zbx_strdup(NULL, row[11]);
		target_host_trigger_entry.event_name = NULL;
		ZBX_STR2UCHAR(target_host_trigger_entry.priority_orig,  row[12]);
		target_host_trigger_entry.priority = 0;
		target_host_trigger_entry.comments_orig = zbx_strdup(NULL, row[13]);
		target_host_trigger_entry.comments = NULL;
		target_host_trigger_entry.url_orig = zbx_strdup(NULL, row[14]);
		target_host_trigger_entry.url = NULL;
		ZBX_STR2UCHAR(target_host_trigger_entry.type_orig, row[16]);
		target_host_trigger_entry.type = 0;
		ZBX_STR2UCHAR(target_host_trigger_entry.status_orig, row[17]);
		target_host_trigger_entry.status = 0;

		zbx_hashset_insert(zbx_host_triggers_main_data, &target_host_trigger_entry,
				sizeof(target_host_trigger_entry));
		zbx_vector_uint64_append(temp_host_triggerids, target_host_trigger_entry.triggerid);
		temp_t.description = target_host_trigger_entry.description;

		if (NULL != (found = (zbx_trigger_descriptions_entry_t *)zbx_hashset_search(
				triggers_descriptions, &temp_t)))
		{
			zbx_vector_uint64_append(&(found->triggerids), target_host_trigger_entry.triggerid);
		}
		else
		{
			zbx_trigger_descriptions_entry_t	local_temp_t;

			zbx_vector_uint64_create(&(local_temp_t.triggerids));
			local_temp_t.description = target_host_trigger_entry.description;
			zbx_vector_uint64_append(&(local_temp_t.triggerids), target_host_trigger_entry.triggerid);
			zbx_hashset_insert(triggers_descriptions, &local_temp_t, sizeof(local_temp_t));
		}
	}
clean:
	zbx_free(sql);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	compare_triggers(zbx_trigger_copy_t * template_trigger, zbx_target_host_trigger_entry_t *main_found,
		zbx_hashset_t *zbx_templates_triggers_funcs, zbx_hashset_t *zbx_host_triggers_funcs)
{
	int				i, j, ret = FAIL;
	char				*expr, *rexpr, *old_expr, search[MAX_ID_LEN + 3], replace[MAX_ID_LEN + 3];
	zbx_trigger_functions_entry_t	*found_template_trigger_funcs, temp_t_template_trigger_funcs,
					*found_host_trigger_funcs, temp_t_host_trigger_funcs;

	expr = zbx_strdup(NULL, main_found->expression);
	rexpr = zbx_strdup(NULL, main_found->recovery_expression);

	temp_t_template_trigger_funcs.triggerid = template_trigger->triggerid;
	temp_t_host_trigger_funcs.triggerid = main_found->triggerid;

	if (NULL != (found_template_trigger_funcs = (zbx_trigger_functions_entry_t *)zbx_hashset_search(
			zbx_templates_triggers_funcs, &temp_t_template_trigger_funcs)) &&
			NULL != (found_host_trigger_funcs = (zbx_trigger_functions_entry_t *)
			zbx_hashset_search(zbx_host_triggers_funcs,
			&temp_t_host_trigger_funcs)))
	{
		for (i = 0; i < found_template_trigger_funcs->functionids.values_num; i++)
		{
			char	*itemkeys_value = found_template_trigger_funcs->itemkeys.values[i];
			char	*parameters_value = found_template_trigger_funcs->parameters.values[i];
			char	*functionid = found_template_trigger_funcs->functionids.values[i];

			for (j = 0; j < found_host_trigger_funcs->functionids.values_num; j++)
			{
				if (0 == strcmp(itemkeys_value, found_host_trigger_funcs->itemkeys.values[j]) &&
						0 == strcmp(parameters_value,
						found_host_trigger_funcs->parameters.values[j]))
				{
					zbx_snprintf(search, sizeof(search), "{%s}",
							found_host_trigger_funcs->functionids.values[j]);
					zbx_snprintf(replace, sizeof(replace), "{%s}", functionid);

					old_expr = expr;
					expr = string_replace(expr, search, replace);
					zbx_free(old_expr);

					old_expr = rexpr;
					rexpr = string_replace(rexpr, search, replace);
					zbx_free(old_expr);
				}
			}
		}
	}

	if (0 != strcmp(template_trigger->expression, expr) || 0 != strcmp(template_trigger->recovery_expression,
			rexpr))
	{
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(rexpr);
	zbx_free(expr);

	return ret;
}

static void	mark_updates_for_host_trigger(zbx_trigger_copy_t *trigger_copy,
		zbx_target_host_trigger_entry_t *main_found)
{
	if (trigger_copy->recovery_mode != main_found->recovery_mode_orig)
	{
		main_found->recovery_mode = trigger_copy->recovery_mode;
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_RECOVERY_MODE;
	}

	if (trigger_copy->correlation_mode != main_found->correlation_mode_orig)
	{
		main_found->correlation_mode = trigger_copy->correlation_mode;
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_MODE;
	}

	if (0 != strcmp(trigger_copy->correlation_tag, main_found->correlation_tag_orig))
	{
		main_found->correlation_tag = zbx_strdup(NULL, trigger_copy->correlation_tag);
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_TAG;
	}

	if (trigger_copy->manual_close != main_found->manual_close_orig)
	{
		main_found->manual_close = trigger_copy->manual_close;
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_MANUAL_CLOSE;
	}

	if (0 != strcmp(trigger_copy->opdata, main_found->opdata_orig))
	{
		main_found->opdata = zbx_strdup(NULL, trigger_copy->opdata);
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_OPDATA;
	}

	if (trigger_copy->discover != main_found->discover_orig)
	{
		main_found->discover = trigger_copy->discover;
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_DISCOVER;
	}

	if (0 != strcmp(trigger_copy->event_name, main_found->event_name_orig))
	{
		main_found->event_name = zbx_strdup(NULL, trigger_copy->event_name);
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_EVENT_NAME;
	}

	if (trigger_copy->priority != main_found->priority_orig)
	{
		main_found->priority = trigger_copy->priority;
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_PRIORITY;
	}

	if (0 != strcmp(trigger_copy->comments, main_found->comments_orig))
	{
		main_found->comments = zbx_strdup(NULL, trigger_copy->comments);
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_COMMENTS;
	}

	if (0 != strcmp(trigger_copy->url, main_found->url_orig))
	{
		main_found->url = zbx_strdup(NULL, trigger_copy->url);
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_URL;
	}

	if (trigger_copy->type != main_found->type_orig)
	{
		main_found->type = trigger_copy->type;
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_TYPE;
	}

	if (trigger_copy->status != main_found->status_orig)
	{
		main_found->status = trigger_copy->status;
		main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_STATUS;
	}

	main_found->templateid = trigger_copy->triggerid;
	main_found->update_flags |= ZBX_FLAG_LINK_TRIGGER_UPDATE_TEMPLATEID;
}

static int	execute_triggers_updates(zbx_hashset_t *zbx_host_triggers_main_data)
{
	int				res = SUCCEED;
	const char			*d;
	char				*sql = NULL;
	size_t				sql_alloc = 512, sql_offset = 0;
	zbx_hashset_iter_t		iter1;
	zbx_target_host_trigger_entry_t	*found;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_iter_reset(zbx_host_triggers_main_data, &iter1);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (found = (zbx_target_host_trigger_entry_t *)zbx_hashset_iter_next(&iter1)))
	{
		d = "";

		zbx_audit_trigger_create_entry(AUDIT_ACTION_UPDATE, found->triggerid, found->description,
				(int)found->flags);

		if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE))
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set ");

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_RECOVERY_MODE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%srecovery_mode=%d", d,
						found->recovery_mode);
				d = ",";

				zbx_audit_trigger_update_json_update_recovery_mode(found->triggerid,
						(int)found->flags, found->recovery_mode_orig,
						found->recovery_mode);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_MODE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scorrelation_mode=%d", d,
						found->correlation_mode);
				d = ",";

				zbx_audit_trigger_update_json_update_correlation_mode(found->triggerid,
						(int)found->flags, found->correlation_mode_orig,
						found->correlation_mode);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_CORRELATION_TAG))
			{
				char	*correlation_tag_esc = DBdyn_escape_string(found->correlation_tag);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scorrelation_tag='%s'", d,
						correlation_tag_esc);
				zbx_free(correlation_tag_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_correlation_tag(found->triggerid,
						(int)found->flags, found->correlation_tag_orig, found->correlation_tag);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_MANUAL_CLOSE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%smanual_close=%d", d,
						found->manual_close);
				d = ",";

				zbx_audit_trigger_update_json_update_manual_close(found->triggerid, (int)found->flags,
						found->manual_close_orig, found->manual_close);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_OPDATA))
			{
				char	*opdata_esc = DBdyn_escape_string(found->opdata);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sopdata='%s'", d, opdata_esc);
				zbx_free(opdata_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_opdata(found->triggerid, (int)found->flags,
						found->opdata_orig, found->opdata);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_DISCOVER))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdiscover=%d", d, found->discover);
				d = ",";

				zbx_audit_trigger_update_json_update_discover(found->triggerid,
						(int)found->flags, found->discover_orig, found->discover);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_EVENT_NAME))
			{
				char	*event_name_esc = DBdyn_escape_string(found->event_name);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sevent_name='%s'", d,
						found->event_name);
				d = ",";
				zbx_free(event_name_esc);

				zbx_audit_trigger_update_json_update_event_name(found->triggerid,
						(int)found->flags, found->event_name_orig, found->event_name);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_PRIORITY))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spriority=%d", d, found->priority);
				d = ",";

				zbx_audit_trigger_update_json_update_priority(found->triggerid,
						(int)found->flags, found->priority_orig, found->priority);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_COMMENTS))
			{
				char	*comments_esc = DBdyn_escape_string(found->comments);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scomments='%s'", d,
						found->comments);
				d = ",";
				zbx_free(comments_esc);

				zbx_audit_trigger_update_json_update_comments(found->triggerid,
						(int)found->flags, found->comments_orig, found->comments);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_URL))
			{
				char	*url_esc = DBdyn_escape_string(found->url);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%surl='%s'", d,
						found->url);
				d = ",";
				zbx_free(url_esc);

				zbx_audit_trigger_update_json_update_url(found->triggerid,
						(int)found->flags, found->url_orig, found->url);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype='%d'", d,
						found->type);
				d = ",";

				zbx_audit_trigger_update_json_update_type(found->triggerid,
						(int)found->flags, found->type_orig, found->type);
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_TRIGGER_UPDATE_STATUS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sstatus='%d'", d,
						found->status);
				d = ",";

				zbx_audit_trigger_update_json_update_status(found->triggerid,
						(int)found->flags, found->status_orig, found->status);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stemplateid=" ZBX_FS_UI64, d,
					found->templateid);

			zbx_audit_trigger_update_json_update_templateid(found->triggerid, (int)found->flags,
					found->templateid_orig, found->templateid);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where triggerid=" ZBX_FS_UI64 ";\n",
					found->triggerid);

			if (FAIL == DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			{
				res = FAIL;
				goto clean;
			}
		}
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			res = FAIL;
	}
clean:
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	get_funcs_for_insert(zbx_uint64_t hostid, zbx_vector_uint64_t *insert_templateid_triggerids,
		zbx_hashset_t *zbx_insert_triggers_funcs, int *funcs_insert_count)
{
	int		res = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == insert_templateid_triggerids->values_num)
		goto out;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			" select hi.itemid,tf.functionid,tf.name,tf.parameter,ti.key_,tf.triggerid"
			" from functions tf,items ti"
			" left join items hi"
				" on hi.key_=ti.key_"
					" and hi.hostid=" ZBX_FS_UI64
				" where tf.itemid=ti.itemid"
					" and", hostid);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "tf.triggerid", insert_templateid_triggerids->values,
			insert_templateid_triggerids->values_num);

	if (NULL == (result = DBselect("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (SUCCEED == res && NULL != (row = DBfetch(result)))
	{
		zbx_trigger_functions_entry_t	*found, temp_t;

		if (SUCCEED != DBis_null(row[0]))
		{
			ZBX_STR2UINT64(itemid, row[0]);
			ZBX_STR2UINT64(temp_t.triggerid, row[5]);

			if (NULL != (found = (zbx_trigger_functions_entry_t *)zbx_hashset_search(
					zbx_insert_triggers_funcs, &temp_t)))
			{
				zbx_vector_uint64_append(&(found->itemids), itemid);
				zbx_vector_str_append(&(found->functionids), zbx_strdup(NULL, row[1]));
				zbx_vector_str_append(&(found->itemkeys), zbx_strdup(NULL, row[4]));
				zbx_vector_str_append(&(found->names), DBdyn_escape_string(row[2]));
				zbx_vector_str_append(&(found->parameters), DBdyn_escape_string(row[3]));
			}
			else
			{
				zbx_trigger_functions_entry_t	local_temp_t;

				zbx_vector_str_create(&(local_temp_t.functionids));
				zbx_vector_uint64_create(&(local_temp_t.itemids));
				zbx_vector_str_create(&(local_temp_t.itemkeys));
				zbx_vector_str_create(&(local_temp_t.names));
				zbx_vector_str_create(&(local_temp_t.parameters));

				zbx_vector_uint64_append(&(local_temp_t.itemids), itemid);
				zbx_vector_str_append(&(local_temp_t.functionids), zbx_strdup(NULL, row[1]));
				zbx_vector_str_append(&(local_temp_t.itemkeys), zbx_strdup(NULL, row[4]));
				zbx_vector_str_append(&(local_temp_t.names), DBdyn_escape_string(row[2]));
				zbx_vector_str_append(&(local_temp_t.parameters), DBdyn_escape_string(row[3]));

				local_temp_t.triggerid = temp_t.triggerid;

				zbx_hashset_insert(zbx_insert_triggers_funcs, &local_temp_t, sizeof(local_temp_t));
			}

			(*funcs_insert_count)++;
		}
		else
			res = FAIL;
	}
clean:
	zbx_free(sql);
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	execute_triggers_inserts(zbx_vector_trigger_copies_insert_t *trigger_copies_insert,
		zbx_hashset_t *zbx_insert_triggers_funcs, zbx_vector_uint64_t *new_triggerids, int *funcs_insert_count,
		char **error)
{
	int				i, j, res;
	char				*sql_update_triggers_expr = NULL;
	size_t				sql_update_triggers_expr_alloc = 512, sql_update_triggers_expr_offset = 0;
	zbx_uint64_t			triggerid, triggerid2, functionid;
	zbx_db_insert_t			db_insert, db_insert_funcs;
	zbx_trigger_functions_entry_t	*found, temp_t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	DBbegin_multiple_update(&sql_update_triggers_expr, &sql_update_triggers_expr_alloc,
			&sql_update_triggers_expr_offset);

	zbx_db_insert_prepare(&db_insert, "triggers", "triggerid", "description", "priority", "status", "comments",
			"url", "type", "value", "state", "templateid", "flags", "recovery_mode", "correlation_mode",
			"correlation_tag", "manual_close", "opdata", "discover", "event_name", NULL);

	zbx_db_insert_prepare(&db_insert_funcs, "functions", "functionid", "itemid", "triggerid", "name",
			"parameter", NULL);

	triggerid = triggerid2 = DBget_maxid_num("triggers", trigger_copies_insert->values_num);
	functionid = DBget_maxid_num("functions", *funcs_insert_count);

	for (i = 0; i < trigger_copies_insert->values_num; i++)
	{
		zbx_trigger_copy_t	*trigger_copy_template = trigger_copies_insert->values[i];

		zbx_db_insert_add_values(&db_insert, triggerid, trigger_copy_template->description,
				(int)trigger_copy_template->priority, (int)trigger_copy_template->status,
				trigger_copy_template->comments, trigger_copy_template->url,
				(int)trigger_copy_template->type, (int)TRIGGER_VALUE_OK, (int)TRIGGER_STATE_NORMAL,
				trigger_copy_template->templateid, (int)trigger_copy_template->flags,
				(int)trigger_copy_template->recovery_mode, (int)trigger_copy_template->correlation_mode,
				trigger_copy_template->correlation_tag, (int)trigger_copy_template->manual_close,
				trigger_copy_template->opdata, trigger_copy_template->discover,
				trigger_copy_template->event_name);

		zbx_vector_uint64_append(new_triggerids, triggerid);

		zbx_audit_trigger_create_entry(AUDIT_ACTION_ADD, triggerid, trigger_copy_template->description,
				(int)trigger_copy_template->flags);
		zbx_audit_trigger_update_json_add_data(triggerid, trigger_copy_template->templateid,
				trigger_copy_template->recovery_mode, trigger_copy_template->status,
				trigger_copy_template->type, TRIGGER_VALUE_OK, TRIGGER_STATE_NORMAL,
				trigger_copy_template->priority, trigger_copy_template->comments,
				trigger_copy_template->url, trigger_copy_template->flags,
				trigger_copy_template->correlation_mode, trigger_copy_template->correlation_tag,
				trigger_copy_template->manual_close, trigger_copy_template->opdata,
				trigger_copy_template->discover, trigger_copy_template->event_name);

		triggerid++;
	}

	res = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	for (i = 0; res == SUCCEED && i < trigger_copies_insert->values_num; i++)
	{
		zbx_eval_context_t	ctx, ctx_r;
		zbx_trigger_copy_t	*trigger_copy_template = trigger_copies_insert->values[i];
		zbx_uint64_t            parse_rules = ZBX_EVAL_PARSE_TRIGGER_EXPRESSION | ZBX_EVAL_COMPOSE_FUNCTIONID;

		if (0 != (trigger_copy_template->flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
			parse_rules |= ZBX_EVAL_PARSE_LLDMACRO | ZBX_EVAL_COMPOSE_LLD;

		if (SUCCEED != (res = zbx_eval_parse_expression(&ctx, trigger_copy_template->expression, parse_rules,
				error)))
		{
			goto func_out;
		}

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == (int)trigger_copy_template->recovery_mode &&
				(SUCCEED != (res = zbx_eval_parse_expression(&ctx_r,
				trigger_copy_template->recovery_expression, parse_rules, error))))
		{
			zbx_eval_clear(&ctx);
			goto func_out;
		}

		temp_t.triggerid = trigger_copy_template->templateid;

		if (NULL != (found =  (zbx_trigger_functions_entry_t *)zbx_hashset_search(zbx_insert_triggers_funcs,
				&temp_t)))
		{
			for (j = 0; j < found->functionids.values_num; j++)
			{
				zbx_uint64_t	old_functionid;

				ZBX_DBROW2UINT64(old_functionid, found->functionids.values[j]);
				zbx_eval_replace_functionid(&ctx, old_functionid, functionid);

				if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION ==
						(int)trigger_copy_template->recovery_mode)
				{
					zbx_eval_replace_functionid(&ctx_r, old_functionid, functionid);
				}

				zbx_db_insert_add_values(&db_insert_funcs, functionid++,
						found->itemids.values[j], triggerid2, found->names.values[j],
						found->parameters.values[j]);
			}
		}

		if (SUCCEED == (res = zbx_eval_validate_replaced_functionids(&ctx, error)) &&
				TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == (int)trigger_copy_template->recovery_mode)
		{
			res = zbx_eval_validate_replaced_functionids(&ctx_r, error);
		}

		if (SUCCEED == res)
		{
			char	*new_expression = NULL, *esc;

			zbx_eval_compose_expression(&ctx, &new_expression);
			esc = DBdyn_escape_field("triggers", "expression", new_expression);
			zbx_snprintf_alloc(&sql_update_triggers_expr, &sql_update_triggers_expr_alloc,
					&sql_update_triggers_expr_offset,
					"update triggers set expression='%s'", esc);

			/* technically this is an update SQL operation, but logically it is an add, so we audit it */
			/* as such */
			zbx_audit_trigger_update_json_add_expr(triggerid2, trigger_copy_template->flags,
					new_expression);

			zbx_free(esc);
			zbx_free(new_expression);

			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == (int)trigger_copy_template->recovery_mode)
			{
				zbx_eval_compose_expression(&ctx_r, &new_expression);
				esc = DBdyn_escape_field("triggers", "recovery_expression", new_expression);
				zbx_snprintf_alloc(&sql_update_triggers_expr,
						&sql_update_triggers_expr_alloc, &sql_update_triggers_expr_offset,
						",recovery_expression='%s'", esc);

				zbx_audit_trigger_update_json_add_rexpr(triggerid2, trigger_copy_template->flags,
						new_expression);

				zbx_free(esc);
				zbx_free(new_expression);
			}

			zbx_snprintf_alloc(&sql_update_triggers_expr, &sql_update_triggers_expr_alloc,
					&sql_update_triggers_expr_offset,
					" where triggerid=" ZBX_FS_UI64 ";\n",
					triggerid2);

			if (FAIL == DBexecute_overflowed_sql(&sql_update_triggers_expr, &sql_update_triggers_expr_alloc,
					&sql_update_triggers_expr_offset))
			{
				res = FAIL;
			}
		}

		zbx_eval_clear(&ctx);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == (int)trigger_copy_template->recovery_mode)
			zbx_eval_clear(&ctx_r);
func_out:
		triggerid2++;
	}

	if (SUCCEED == res)
		res = zbx_db_insert_execute(&db_insert_funcs);
	zbx_db_insert_clean(&db_insert_funcs);

	DBend_multiple_update(&sql_update_triggers_expr, &sql_update_triggers_expr_alloc,
			&sql_update_triggers_expr_offset);

	if (SUCCEED == res && 16 < sql_update_triggers_expr_offset)	/* In ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql_update_triggers_expr))
			res = FAIL;
	}

	zbx_free(sql_update_triggers_expr);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static void	process_triggers(zbx_trigger_copy_t *trigger_copy_template, zbx_hashset_t *host_triggers_descriptions,
		zbx_hashset_t *zbx_host_triggers_main_data, zbx_hashset_t *zbx_templates_triggers_funcs,
		zbx_hashset_t *zbx_host_triggers_funcs, int *upd_triggers, zbx_vector_uint64_t *cur_triggerids,
		zbx_vector_trigger_copies_insert_t *trigger_copies_insert,
		zbx_vector_uint64_t *insert_templateid_triggerids)
{
	int					j, found_descriptions_match = FAIL;
	zbx_trigger_descriptions_entry_t	*found, temp_t;

	temp_t.description = trigger_copy_template->description;

	if (NULL != (found =  (zbx_trigger_descriptions_entry_t *)zbx_hashset_search(
			host_triggers_descriptions, &temp_t)))
	{
		for (j = 0; j < found->triggerids.values_num; j++)
		{
			zbx_target_host_trigger_entry_t	main_temp_t, *main_found;

			main_temp_t.triggerid = found->triggerids.values[j];

			if (NULL != (main_found = (zbx_target_host_trigger_entry_t *)zbx_hashset_search(
					zbx_host_triggers_main_data, &main_temp_t)) &&
					SUCCEED == compare_triggers(trigger_copy_template, main_found,
					zbx_templates_triggers_funcs, zbx_host_triggers_funcs))
			{
				found_descriptions_match = SUCCEED;

				mark_updates_for_host_trigger(trigger_copy_template, main_found);
				(*upd_triggers)++;
				zbx_vector_uint64_append(cur_triggerids, found->triggerids.values[j]);

				break;
			}
		}
	}

	/* not found any entries on host with the same description, expression and recovery expression, so insert it */
	if (FAIL == found_descriptions_match)
	{
		zbx_trigger_copy_t	*trigger_copy_insert;

		/* save data for trigger */
		trigger_copy_insert = (zbx_trigger_copy_t *)zbx_malloc(NULL, sizeof(zbx_trigger_copy_t));
		trigger_copy_insert->description = zbx_strdup(NULL, trigger_copy_template->description);
		trigger_copy_insert->expression= zbx_strdup(NULL, trigger_copy_template->expression);
		trigger_copy_insert->recovery_expression= zbx_strdup(NULL,
				trigger_copy_template->recovery_expression);
		trigger_copy_insert->templateid = trigger_copy_template->triggerid;
		trigger_copy_insert->flags = trigger_copy_template->flags;

		trigger_copy_insert->recovery_mode = trigger_copy_template->recovery_mode;
		trigger_copy_insert->correlation_mode = trigger_copy_template->correlation_mode;
		trigger_copy_insert->manual_close = trigger_copy_template->manual_close;
		trigger_copy_insert->opdata = zbx_strdup(NULL, trigger_copy_template->opdata);
		trigger_copy_insert->discover = trigger_copy_template->discover;
		trigger_copy_insert->event_name = zbx_strdup(NULL, trigger_copy_template->event_name);

		trigger_copy_insert->priority = trigger_copy_template->priority;
		trigger_copy_insert->comments =  DBdyn_escape_string(trigger_copy_template->comments);
		trigger_copy_insert->url = DBdyn_escape_string(trigger_copy_template->url);
		trigger_copy_insert->correlation_tag = zbx_strdup(NULL, trigger_copy_template->correlation_tag);
		trigger_copy_insert->status = trigger_copy_template->status;
		trigger_copy_insert->type = trigger_copy_template->type;

		zbx_vector_trigger_copies_insert_append(trigger_copies_insert, trigger_copy_insert);
		zbx_vector_uint64_append(insert_templateid_triggerids, trigger_copy_template->triggerid);
	}
}

static void	trigger_copies_free(zbx_trigger_copy_t *trigger_copy)
{
	zbx_free(trigger_copy->comments);
	zbx_free(trigger_copy->url);
	zbx_free(trigger_copy->expression);
	zbx_free(trigger_copy->recovery_expression);
	zbx_free(trigger_copy->event_name);
	zbx_free(trigger_copy->correlation_tag);
	zbx_free(trigger_copy->description);
	zbx_free(trigger_copy->opdata);
	zbx_free(trigger_copy);
}

/********************************************************************************
 *                                                                              *
 * Purpose: Copy template triggers to host                                      *
 *                                                                              *
 * Parameters: hostid      - [IN] host identifier from database                 *
 *             templateids - [IN] array of template IDs                         *
 *             error       - [IN] the error message                             *
 *                                                                              *
 * Return value: upon successful completion return SUCCEED, or FAIL on DB error *
 *                                                                              *
 * Comments: !!! Don't forget to sync the code with PHP !!!                     *
 *                                                                              *
 ********************************************************************************/
int	DBcopy_template_triggers(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, char **error)
{
	int					i, upd_triggers = 0, funcs_insert_count = 0, res = SUCCEED;
	zbx_vector_uint64_t			new_triggerids, cur_triggerids;
	zbx_vector_trigger_copies_templates_t	trigger_copies_templates;
	zbx_vector_trigger_copies_insert_t	trigger_copies_insert;
	zbx_vector_str_t			templates_triggers_descriptions;
	zbx_vector_uint64_t			temp_templates_triggerids, temp_host_triggerids,
						insert_templateid_triggerids;
	zbx_hashset_t				zbx_templates_triggers_funcs, zbx_host_triggers_funcs,
						zbx_host_triggers_main_data, host_triggers_descriptions,
						zbx_insert_triggers_funcs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&new_triggerids);
	zbx_vector_uint64_create(&cur_triggerids);
	zbx_vector_uint64_create(&insert_templateid_triggerids);
	zbx_vector_uint64_create(&temp_templates_triggerids);
	zbx_vector_uint64_create(&temp_host_triggerids);
	zbx_vector_str_create(&templates_triggers_descriptions);
	zbx_vector_trigger_copies_insert_create(&trigger_copies_insert);
	zbx_vector_trigger_copies_templates_create(&trigger_copies_templates);
#define	TRIGGER_FUNCS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&host_triggers_descriptions, TRIGGER_FUNCS_HASHSET_DEF_SIZE,
			zbx_triggers_descriptions_hash_func,
			zbx_triggers_descriptions_compare_func);
	zbx_hashset_create(&zbx_templates_triggers_funcs, TRIGGER_FUNCS_HASHSET_DEF_SIZE,
			zbx_triggers_functions_hash_func,
			zbx_triggers_functions_compare_func);
	zbx_hashset_create(&zbx_host_triggers_funcs, TRIGGER_FUNCS_HASHSET_DEF_SIZE,
			zbx_triggers_functions_hash_func,
			zbx_triggers_functions_compare_func);
	zbx_hashset_create(&zbx_host_triggers_main_data, TRIGGER_FUNCS_HASHSET_DEF_SIZE,
			zbx_host_triggers_main_data_hash_func,
			zbx_host_triggers_main_data_compare_func);
	zbx_hashset_create(&zbx_insert_triggers_funcs, TRIGGER_FUNCS_HASHSET_DEF_SIZE,
			zbx_triggers_functions_hash_func,
			zbx_triggers_functions_compare_func);
#undef TRIGGER_FUNCS_HASHSET_DEF_SIZE
	res = get_templates_triggers_data(hostid, templateids, &trigger_copies_templates,
			&templates_triggers_descriptions, &temp_templates_triggerids);

	if (0 == templates_triggers_descriptions.values_num)
		goto end;

	if (SUCCEED == res)
	{
		res = get_target_host_main_data(hostid, &templates_triggers_descriptions, &zbx_host_triggers_main_data,
				&temp_host_triggerids, &host_triggers_descriptions);
	}

	if (SUCCEED == res)
		res = get_trigger_funcs(&temp_templates_triggerids, &zbx_templates_triggers_funcs);

	if (SUCCEED == res)
		res = get_trigger_funcs(&temp_host_triggerids, &zbx_host_triggers_funcs);

	if (SUCCEED == res)
	{
		for (i = 0; i < trigger_copies_templates.values_num; i++)
		{
			process_triggers(trigger_copies_templates.values[i], &host_triggers_descriptions,
					&zbx_host_triggers_main_data, &zbx_templates_triggers_funcs,
					&zbx_host_triggers_funcs, &upd_triggers, &cur_triggerids,
					&trigger_copies_insert, &insert_templateid_triggerids);
		}
	}

	if (SUCCEED == res)
	{
		res = get_funcs_for_insert(hostid, &insert_templateid_triggerids, &zbx_insert_triggers_funcs,
				&funcs_insert_count);
	}

	if (SUCCEED == res && 0 < upd_triggers)
		res = execute_triggers_updates(&zbx_host_triggers_main_data);

	if (SUCCEED == res && 0 < trigger_copies_insert.values_num)
	{
		res = execute_triggers_inserts(&trigger_copies_insert, &zbx_insert_triggers_funcs,
				&new_triggerids, &funcs_insert_count, error);
	}

	if (SUCCEED == res)
	{
		res = DBsync_template_dependencies_for_triggers(hostid, &temp_host_triggerids,
				TRIGGER_DEP_SYNC_UPDATE_OP);
	}

	if (SUCCEED == res)
		res = DBsync_template_dependencies_for_triggers(hostid, &new_triggerids, TRIGGER_DEP_SYNC_INSERT_OP);

	if (SUCCEED == res)
		res = DBcopy_template_trigger_tags(&new_triggerids, &cur_triggerids);

	if (FAIL == res && NULL == *error)
		*error = zbx_strdup(NULL, "unknown error while linking triggers");
end:
	zbx_vector_uint64_destroy(&new_triggerids);
	zbx_vector_uint64_destroy(&cur_triggerids);
	zbx_vector_uint64_destroy(&insert_templateid_triggerids);
	zbx_vector_uint64_destroy(&temp_templates_triggerids);
	zbx_vector_uint64_destroy(&temp_host_triggerids);
	zbx_vector_str_clear_ext(&templates_triggers_descriptions, zbx_str_free);
	zbx_vector_str_destroy(&templates_triggers_descriptions);
	zbx_vector_trigger_copies_templates_clear_ext(&trigger_copies_templates, trigger_copies_free);
	zbx_vector_trigger_copies_templates_destroy(&trigger_copies_templates);
	zbx_vector_trigger_copies_insert_clear_ext(&trigger_copies_insert, trigger_copies_free);
	zbx_vector_trigger_copies_insert_destroy(&trigger_copies_insert);
	zbx_triggers_descriptions_clean(&host_triggers_descriptions);
	zbx_host_triggers_main_data_clean(&zbx_host_triggers_main_data);
	zbx_triggers_functions_clean(&zbx_insert_triggers_funcs);
	zbx_triggers_functions_clean(&zbx_templates_triggers_funcs);
	zbx_triggers_functions_clean(&zbx_host_triggers_funcs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}
