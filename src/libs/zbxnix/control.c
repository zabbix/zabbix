/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "control.h"

/******************************************************************************
 *                                                                            *
 * Function: get_log_level_message                                            *
 *                                                                            *
 * Purpose: create runtime control message for log level changes based on     *
 *          the command line arguments                                        *
 *                                                                            *
 * Parameters: opt     - [IN] the command line argument                       *
 *                            (command with/without options)                  *
 *             command - [IN] the command for log level change                *
 *                            (increase/decrease)                             *
 *             message - [OUT] the message containing options                 *
 *                             for log level change                           *
 *                                                                            *
 * Return value: SUCCEEED - the message was created successfully              *
 *               FAIL     - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
int	get_log_level_message(const char *opt, int command, int *message)
{
	int	num = 0, scope, data;

	if ('\0' == *opt)
	{
		scope = ZBX_RTC_LOG_SCOPE_FLAG | ZBX_RTC_LOG_SCOPE_PID;
		data = 0;
	}
	else if ('=' != *opt)
	{
		zbx_error("unknown log level control option: %s", opt);
		return FAIL;
	}
	else if (0 != isdigit(*(++opt)))
	{
		if (FAIL == is_ushort(opt, &num) || 0 == num)
		{
			zbx_error("invalid log level control option: process identifier must be unsigned short"
					" non-zero value");
			return FAIL;
		}

		scope = ZBX_RTC_LOG_SCOPE_FLAG | ZBX_RTC_LOG_SCOPE_PID;
		data = num;
	}
	else
	{
		char	*proc_name = NULL, *ptr;
		int	proc_type;

		proc_name = zbx_strdup(proc_name, opt);

		if (NULL != (ptr = strchr(proc_name, ',')))
		{
			*ptr++ = '\0';

			if (FAIL == is_ushort(ptr, &num) || 0 == num)
			{
				zbx_error("invalid log level control option: process number must be unsigned short"
						" non-zero value");
				zbx_free(proc_name);
				return FAIL;
			}
		}

		if (ZBX_PROCESS_TYPE_UNKNOWN == (proc_type = get_process_type_by_name(proc_name)))
		{
			zbx_error("invalid log level control option: unknown process type");
			zbx_free(proc_name);
			return FAIL;
		}

		zbx_free(proc_name);

		scope = ZBX_RTC_LOG_SCOPE_PROC | proc_type;
		data = num;
	}

	*message = ZBX_RTC_MAKE_MESSAGE(command, scope, data);

	return SUCCEED;
}
