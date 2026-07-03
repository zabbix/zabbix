/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxdbhigh.h"
#include "zbxdb.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxstr.h"

ZBX_VECTOR_IMPL(event_tags, zbx_event_tags_t)
ZBX_PTR_VECTOR_IMPL(event_tags_ptr, zbx_event_tags_t *)

void	zbx_db_write_tags(zbx_dbconn_t *db, const zbx_vector_event_tags_ptr_t *etags, const char *table,
		const char *field, const char *tag_table, const char *tag_key, zbx_vector_uint64_t *eventids)
{
	zbx_db_insert_t	db_insert;

	zbx_dbconn_prepare_insert(db, &db_insert, tag_table, tag_key, "eventid", "tag", "value", NULL);
	zbx_dbconn_lock_ids(db, table, field, eventids);

	for (int i = 0; i < etags->values_num; i++)
	{
		zbx_event_tags_t	*et = etags->values[i];

		if (FAIL == zbx_vector_uint64_bsearch(eventids, et->eventid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		for (int j = 0; j < et->tags.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), et->eventid, et->tags.values[j].tag,
					et->tags.values[j].value);
		}
	}

	zbx_db_insert_autoincrement(&db_insert, tag_key);
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

void	zbx_db_validate_tags(zbx_dbconn_t *db, zbx_vector_event_tags_ptr_t *event_tags)
{
	zbx_vector_uint64_t	eventids;

	zbx_vector_uint64_create(&eventids);

	for (int i = 0; i < event_tags->values_num; i++)
		zbx_vector_uint64_append(&eventids, event_tags->values[i]->eventid);

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_db_row_t	row;
	zbx_db_result_t	result;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid,tag,value from event_tag where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);

	result = zbx_dbconn_select(db, "%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	eventid;

		ZBX_STR2UINT64(eventid, row[0]);

		for (int i = 0; i < event_tags->values_num; )
		{
			zbx_event_tags_t	*et = event_tags->values[i];

			if (et->eventid != eventid)
			{
				i++;
				continue;
			}

			for (int j = 0; j < et->tags.values_num; j++)
			{
				zbx_tag_t	*tag = &et->tags.values[j];

				if (0 == strcmp(tag->tag, row[1]) && 0 == strcmp(tag->value, row[2]))
				{
					zbx_free(tag->tag);
					zbx_free(tag->value);
					zbx_vector_tag_remove_noorder(&et->tags, j);

					break;
				}
			}

			if (0 == et->tags.values_num)
			{
				zbx_vector_tag_destroy(&et->tags);
				zbx_vector_event_tags_ptr_remove_noorder(event_tags, i);
			}
			else
				i++;
		}
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_destroy(&eventids);
}

void	zbx_event_tags_clear(zbx_event_tags_t *event_tags)
{
	for (int i = 0; i < event_tags->tags.values_num; i++)
	{
		zbx_free(event_tags->tags.values[i].tag);
		zbx_free(event_tags->tags.values[i].value);
	}
	zbx_vector_tag_destroy(&event_tags->tags);
}

int	zbx_event_tags_compare(const void *d1, const void *d2)
{
	const zbx_event_tags_t	*event_tags_1 = (const zbx_event_tags_t *)d1;
	const zbx_event_tags_t	*event_tags_2 = (const zbx_event_tags_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(event_tags_1->eventid, event_tags_2->eventid);

	return 0;
}


