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

#include "supervisor_client.h"
#include "zbxsupervisor_client.h"

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxstr.h"

typedef struct
{
	char	*str;
	size_t	alloc;
}
zbx_strbuf_t;

ZBX_VECTOR_DECL(strbuf, zbx_strbuf_t)
ZBX_VECTOR_IMPL(strbuf, zbx_strbuf_t)

typedef struct
{
	zbx_vector_strbuf_t	activities;
	pthread_mutex_t		sync;
	int			initialized;
}
zbx_component_worklog_t;

static zbx_component_worklog_t	worklog;

static ZBX_THREAD_LOCAL int	component_index = -1;

static int	compare_activities(const void *d1, const void *d2)
{
	const char	*s1 = *(const char **)d1;
	const char	*s2 = *(const char **)d2;

	return strcmp(s1, s2);
}

void	zbx_supervisor_worklog_init(void)
{
	int	err;

	if (0 != (err = pthread_mutex_init(&worklog.sync, NULL)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize timekeeper mutex: %s", zbx_strerror(err));
		zbx_exit(EXIT_FAILURE);
	}

	zbx_vector_strbuf_create(&worklog.activities);
	worklog.initialized = 1;
}

void	zbx_supervisor_worklog_clear(void)
{
	if (0 == worklog.initialized)
		return;

	pthread_mutex_destroy(&worklog.sync);

	for (int i = 0; i < worklog.activities.values_num; i++)
		zbx_free(worklog.activities.values[i].str);

	zbx_vector_strbuf_destroy(&worklog.activities);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update the current component's activity status in the worklog     *
 *                                                                            *
 * Parameters: fmt - [IN] format string for the activity description          *
 *             ... - [IN] variable arguments for the format string            *
 *                                                                            *
 * Comments: This function is thread-safe and automatically registers new     *
 *           components on their first call.                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_supervisor_update_activity(const char *fmt, ...)
{
	va_list	args;

	if (0 == worklog.initialized)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("supervisor worklog has not been initialized");
		exit(EXIT_FAILURE);
	}

	size_t	offset = 0;

	va_start(args, fmt);
	pthread_mutex_lock(&worklog.sync);

	if (-1 == component_index)
	{
		zbx_strbuf_t	buf_local = {0};

		component_index = worklog.activities.values_num;
		zbx_vector_strbuf_append(&worklog.activities, buf_local);
	}

	zbx_strbuf_t	*buf = &worklog.activities.values[component_index];

	zbx_vsnprintf_alloc(&buf->str, &buf->alloc, &offset, fmt, args);

	pthread_mutex_unlock(&worklog.sync);
	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve all component activities as a sorted newline-separated   *
 *          string                                                            *
 *                                                                            *
 * Return value: allocated string containing all activities, one per line     *
 *                                                                            *
 * Comments: If worklog is not initialized, delegates to IPC client request.  *
 *           The caller is responsible for freeing the returned string.       *
 *                                                                            *
 ******************************************************************************/
char	*zbx_supervisor_get_activities(void)
{
	char			*str = NULL;
	size_t			str_alloc = 0, str_offset = 0;
	zbx_vector_str_t	activities;

	if (0 == worklog.initialized)
	{
		/* switch to client IPC request for external queries */
		return supervisor_client_get_activities();
	}

	zbx_vector_str_create(&activities);

	pthread_mutex_lock(&worklog.sync);

	for (int i = 0; i < worklog.activities.values_num; i++)
		zbx_vector_str_append(&activities, worklog.activities.values[i].str);

	zbx_vector_str_sort(&activities, compare_activities);

	for (int i = 0; i < activities.values_num; i++)
	{
		zbx_strcpy_alloc(&str, &str_alloc, &str_offset, activities.values[i]);
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, '\n');
	}

	pthread_mutex_unlock(&worklog.sync);

	zbx_vector_str_destroy(&activities);

	return str;
}
