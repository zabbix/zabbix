/*
** Copyright (C) 2001-2025 Zabbix SIA
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
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: save the trigger changes to database                              *
 *                                                                            *
 * Parameters: trigger_diff - [IN] the trigger changeset                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_save_trigger_changes(const zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	int				i;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	const zbx_trigger_diff_t	*diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < trigger_diff->values_num; i++)
	{
		char	delim = ' ';
		diff = trigger_diff->values[i];

		if (0 == (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE))
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set");

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%clastchange=%d", delim, diff->lastchange);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cvalue=%d", delim, diff->value);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cstate=%d", delim, diff->state);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR))
		{
			char	*error_esc;

			error_esc = zbx_db_dyn_escape_field("triggers", "error", diff->error);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cerror='%s'", delim, error_esc);
			zbx_free(error_esc);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where triggerid=" ZBX_FS_UI64 ";\n",
				diff->triggerid);

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees trigger changeset                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_trigger_diff_free(zbx_trigger_diff_t *diff)
{
	zbx_free(diff->error);
	zbx_free(diff);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Adds a new trigger diff to trigger changeset vector               *
 *                                                                            *
 ******************************************************************************/
void	zbx_append_trigger_diff(zbx_vector_trigger_diff_ptr_t *trigger_diff, zbx_uint64_t triggerid,
		unsigned char priority, zbx_uint64_t flags, unsigned char value, unsigned char state, int lastchange,
		const char *error)
{
	zbx_trigger_diff_t	*diff;

	diff = (zbx_trigger_diff_t *)zbx_malloc(NULL, sizeof(zbx_trigger_diff_t));
	diff->triggerid = triggerid;
	diff->priority = priority;
	diff->flags = flags;
	diff->value = value;
	diff->state = state;
	diff->lastchange = lastchange;
	diff->error = (NULL != error ? zbx_strdup(NULL, error) : NULL);

	diff->problem_count = 0;

	zbx_vector_trigger_diff_ptr_append(trigger_diff, diff);
}
