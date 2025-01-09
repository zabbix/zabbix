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

#include "rtc.h"

#include "zbxnum.h"
#include "zbxprof.h"
#include "zbxstr.h"

static int	rtc_parse_scope(const char *str, int *scope)
{
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
 * Purpose: get rtc option parameter                                          *
 *                                                                            *
 * Parameters: opt   - [IN] runtime control option parameter                  *
 *             size  - [OUT] number of parsed bytes                           *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - parameter was found or not specified               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	rtc_option_get_parameter(const char *param, size_t *size, char **error)
{
	switch (*param)
	{
		case '\0':
			*size = 0;
			return SUCCEED;
		case '=':
			/* check for empty parameter */
			if ('\0' == param[1])
				break;

			*size = 1;
			return SUCCEED;
	}

	*error = zbx_dsprintf(*error, "unspecified process identifier or type: \"%s\"", param);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get unsigned value from parameter                                 *
 *                                                                            *
 * Parameters: param - [IN] runtime control option parameter                  *
 *             size  - [OUT] number of parsed bytes                           *
 *             value - [OUT] parsed value                                     *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - value was parsed or not specified                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	rtc_option_get_ui64(const char *param, size_t *size, zbx_uint64_t *value)
{
	const char	*ptr = param;

	while (0 != isdigit(*ptr))
		ptr++;

	if (ptr != param)
	{
		if (FAIL == zbx_is_uint64_n(param, (size_t)(ptr - param), value))
			return FAIL;
	}

	*size = (size_t)(ptr - param);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get pid parameter                                                 *
 *                                                                            *
 * Parameters: param - [IN] runtime control option parameter                  *
 *             size  - [OUT] number of parsed bytes                           *
 *             pid   - [OUT] parsed pid                                       *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - pid was parsed or not specified                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	rtc_option_get_pid(const char *param, size_t *size, pid_t *pid, char **error)
{
	zbx_uint64_t	pid_ui64 = 0;

	if (SUCCEED != rtc_option_get_ui64(param, size, &pid_ui64) || (0 != *size && 0 == pid_ui64))
	{
		*error = zbx_dsprintf(*error, "invalid process identifier: \"%s\"", param);
		return FAIL;
	}

	*pid = (pid_t)pid_ui64;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get process type                                                  *
 *                                                                            *
 * Parameters: param     - [IN] runtime control option parameter              *
 *             size      - [OUT] number of parsed bytes                       *
 *             proc_type - [OUT] parsed process type                          *
 *             error     - [OUT] error message                                *
 *                                                                            *
 * Return value: SUCCEED - pid was parsed or not specified                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	rtc_option_get_process_type(const char *param, size_t *size, int *proc_type, char **error)
{
	char		*str = NULL;
	const char	*ptr;
	size_t		str_alloc = 0, str_offset = 0;
	int		ret = FAIL;

	if (NULL != (ptr = strchr(param, ',')))
		*size = (size_t)(ptr - param);
	else
		*size = strlen(param);

	zbx_strncpy_alloc(&str, &str_alloc, &str_offset, param, *size);

	if (ZBX_PROCESS_TYPE_UNKNOWN == (*proc_type = get_process_type_by_name(str)))
		*error = zbx_dsprintf(*error, "invalid process type: \"%s\"", param);
	else
		ret = SUCCEED;

	zbx_free(str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get process number                                                *
 *                                                                            *
 * Parameters: param     - [IN] runtime control option parameter              *
 *             size      - [OUT] number of parsed bytes                       *
 *             proc_num  - [OUT] parsed process number                        *
 *             error     - [OUT] error message                                *
 *                                                                            *
 * Return value: SUCCEED - pid was parsed or not specified                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	rtc_option_get_process_num(const char *param, size_t *size, int *proc_num, char **error)
{
	zbx_uint64_t	value = 0;

	switch (*param)
	{
		case '\0':
			*size = 0;
			return SUCCEED;
		case ',':

			if (SUCCEED != rtc_option_get_ui64(param + 1, size, &value) || 0 == *size || 0 == value ||
					INT_MAX < value)
			{
				break;
			}

			*proc_num = (int)value;

			/* add ',' to the parsed number */
			(*size)++;
			return SUCCEED;
	}

	*error = zbx_dsprintf(*error, "invalid process number: \"%s\"", param);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get profiler scope                                                *
 *                                                                            *
 * Parameters: param     - [IN] runtime control option parameter              *
 *             pos       - [IN] position in parameter string (after '=')      *
 *             scope     - [OUT] parsed profiler scope                        *
 *                                                                            *
 * Return value: SUCCEED - pid was parsed or not specified                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	rtc_option_get_prof_scope(const char *param, size_t pos, int *scope)
{
	if (',' == *param)
	{
		if (1 == pos)
			return FAIL;
		param++;
	}
	else if (1 != pos)
		return FAIL;

	return rtc_parse_scope(param, scope);
}
