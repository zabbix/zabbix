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

#include "rtc.h"

#include "common.h"

/******************************************************************************
 *                                                                            *
 * Purpose: parse loglevel runtime control option                             *
 *                                                                            *
 * Parameters: opt       - [IN] the runtime control option                    *
 *             len       - [IN] the runtime control option length without     *
 *                              parameter                                     *
 *             pid       - [OUT] the target pid (if specified)                *
 *             proc_type - [OUT] the target process type (if specified)       *
 *             proc_num  - [OUT] the target process num (if specified)        *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the runtime control option was processed           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_parse_loglevel_option(const char *opt, size_t len, pid_t *pid, int *proc_type, int *proc_num,
		char **error)
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
		/* convert PID */
		if (FAIL == is_uint32(rtc_options, pid) || 0 == *pid)
		{
			*error = zbx_dsprintf(NULL, "invalid log level control target -"
					" invalid or unsupported process identifier");
			return FAIL;
		}
	}
	else
	{
		char	proc_name[MAX_STRING_LEN], *proc_num_ptr;

		if ('\0' == *rtc_options)
		{
			*error = zbx_dsprintf(NULL, "invalid log level control target -"
					" unspecified process identifier or type");
			return FAIL;
		}

		zbx_strlcpy(proc_name, rtc_options, sizeof(proc_name));

		if (NULL != (proc_num_ptr = strchr(proc_name, ',')))
			*proc_num_ptr++ = '\0';

		if ('\0' == *proc_name)
		{
			*error = zbx_dsprintf(NULL, "invalid log level control target - unspecified process type");
			return FAIL;
		}

		if (ZBX_PROCESS_TYPE_UNKNOWN == (*proc_type = get_process_type_by_name(proc_name)))
		{
			*error = zbx_dsprintf(NULL, "invalid log level control target - unknown process type \"%s\"",
					proc_name);
			return FAIL;
		}

		if (NULL != proc_num_ptr)
		{
			if ('\0' == *proc_num_ptr)
			{
				*error = zbx_dsprintf(NULL, "invalid log level control target -"
						" unspecified process number");
				return FAIL;
			}

			/* convert Zabbix process number (e.g. "2" in "poller,2") */
			if (FAIL == is_uint32(proc_num_ptr, proc_num) || 0 == *proc_num)
			{
				*error = zbx_dsprintf(NULL, "invalid log level control target -"
						" invalid or unsupported process number \"%s\"", proc_num_ptr);
				return FAIL;
			}
		}
	}

	return SUCCEED;
}
