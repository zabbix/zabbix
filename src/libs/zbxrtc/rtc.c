/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "rtc.h"

#include "zbxnum.h"
#include "zbxprof.h"

static int	rtc_parse_scope(const char *str, int *scope)
{
	if (NULL == scope)
		return FAIL;

	if (0 == strcmp(str, "rwlock"))
		*scope = ZBX_PROF_RWLOCK;
	else if (0 == strcmp(str, "mutex"))
		*scope = ZBX_PROF_MUTEX;
	else if (0 == strcmp(str, "processing"))
		*scope = ZBX_PROF_PROCESSING;
	else
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse runtime control option                                      *
 *                                                                            *
 * Parameters: opt       - [IN] runtime control option                        *
 *             len       - [IN] runtime control option length without         *
 *                              parameter                                     *
 *             pid       - [OUT] target pid (if specified)                    *
 *             proc_type - [OUT] target process type (if specified)           *
 *             proc_num  - [OUT] target process num (if specified)            *
 *             scope     - [OUT] scope (if specified)                         *
 *             error     - [OUT] error message                                *
 *                                                                            *
 * Return value: SUCCEED - runtime control option was processed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_parse_option(const char *opt, size_t len, pid_t *pid, int *proc_type, int *proc_num,
		int *scope, char **error)
{
	const char	*rtc_options;

	rtc_options = opt + len;

	if ('\0' == *rtc_options)
		return SUCCEED;

	if ('=' != *rtc_options)
	{
		*error = zbx_dsprintf(NULL, "invalid runtime control option \"%s\"", opt);
		return FAIL;
	}
	else if (0 != isdigit(*(++rtc_options)))
	{
		char	*scope_ptr;

		if (NULL != (scope_ptr = strchr(rtc_options, ',')))
			*scope_ptr++ = '\0';
		/* convert PID */
		if (FAIL == zbx_is_uint32(rtc_options, pid) || 0 == *pid)
		{
			*error = zbx_dsprintf(NULL, "invalid control target - invalid or unsupported process"
					" identifier");
			return FAIL;
		}

		if (NULL != scope_ptr)
		{
			if (FAIL == rtc_parse_scope(scope_ptr, scope))
			{
				*error = zbx_dsprintf(NULL, "invalid control target -"
						" invalid or unsupported scope \"%s\"", scope_ptr);
				return FAIL;
			}
		}
	}
	else if (FAIL == rtc_parse_scope(rtc_options, scope))
	{
		char	proc_name[MAX_STRING_LEN], *proc_num_ptr;

		if ('\0' == *rtc_options)
		{
			*error = zbx_dsprintf(NULL, "invalid control target - unspecified process identifier or type");
			return FAIL;
		}

		zbx_strlcpy(proc_name, rtc_options, sizeof(proc_name));

		if (NULL != (proc_num_ptr = strchr(proc_name, ',')))
			*proc_num_ptr++ = '\0';

		if ('\0' == *proc_name)
		{
			*error = zbx_dsprintf(NULL, "invalid control target - unspecified process type");
			return FAIL;
		}

		if (ZBX_PROCESS_TYPE_UNKNOWN == (*proc_type = get_process_type_by_name(proc_name)))
		{
			*error = zbx_dsprintf(NULL, "invalid control target - unknown process type \"%s\"",
					proc_name);
			return FAIL;
		}

		if (NULL != proc_num_ptr)
		{
			if ('\0' == *proc_num_ptr)
			{
				*error = zbx_dsprintf(NULL, "invalid control target - unspecified process number");
				return FAIL;
			}

			if (FAIL == rtc_parse_scope(proc_num_ptr, scope))
			{
				char	*scope_ptr;

				if (NULL != (scope_ptr = strchr(proc_num_ptr, ',')))
					*scope_ptr++ = '\0';

				if (FAIL == zbx_is_uint32(proc_num_ptr, proc_num) || 0 == *proc_num)
				{
					/* convert Zabbix process number (e.g. "2" in "poller,2") */
					*error = zbx_dsprintf(NULL, "invalid control target -"
							" invalid or unsupported process number \"%s\"", proc_num_ptr);
					return FAIL;
				}

				if (NULL != scope_ptr)
				{
					if (FAIL == rtc_parse_scope(scope_ptr, scope))
					{
						*error = zbx_dsprintf(NULL, "invalid control target"
								" invalid or unsupported scope \"%s\"", scope_ptr);
						return FAIL;
					}
				}
			}
		}
	}

	return SUCCEED;
}
