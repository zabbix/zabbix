/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "log.h"
#include "cfg.h"
#include "alias.h"

#if defined(WITH_COMMON_METRICS)
#	include "common/common.h"
#endif

#if defined(WITH_SIMPLE_METRICS)
#	include "simple/simple.h"
#endif

#if defined(WITH_SPECIFIC_METRICS)
#	include "specsysinfo.h"
#endif

static ZBX_METRIC	*commands = NULL;

void	add_metric(ZBX_METRIC *new)
{
	int	i = 0;

	assert(new);

	if (NULL == new->key)
		return;

	while (NULL != commands[i].key)
		i++;

	commands[i].key = zbx_strdup(NULL, new->key);
	commands[i].flags = new->flags;
	commands[i].function = new->function;
	commands[i].main_param = (NULL == new->main_param ? NULL : zbx_strdup(NULL, new->main_param));
	commands[i].test_param = (NULL == new->test_param ? NULL : zbx_strdup(NULL, new->test_param));

	commands = zbx_realloc(commands, (i + 2) * sizeof(ZBX_METRIC));
	memset(&commands[i + 1], 0, sizeof(ZBX_METRIC));
}

int	add_user_parameter(char *key, char *command)
{
	int		i;
	char		usr_cmd[MAX_STRING_LEN];
	char		usr_param[MAX_STRING_LEN];
	unsigned	flag = 0;

	if (0 == (i = parse_command(key, usr_cmd, sizeof(usr_cmd), usr_param, sizeof(usr_param))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't add user specified key \"%s\". Can't parse key!", key);
		return FAIL;
	}
	else if (2 == i)				/* with specified parameters */
	{
		if (0 != strcmp(usr_param, "*"))	/* must be '*' parameters */
		{
			zabbix_log(LOG_LEVEL_WARNING, "Can't add user specified key \"%s\". Incorrect key!", key);
			return FAIL;
		}
		flag |= CF_USEUPARAM;
	}

	for (i = 0;; i++)
	{
		/* add new parameters */
		if (NULL == commands[i].key)
		{
			commands[i].key = zbx_strdup(NULL, usr_cmd);
			commands[i].flags = flag;
			commands[i].function = &EXECUTE_STR;
			commands[i].main_param = zbx_strdup(NULL, command);
			commands[i].test_param = NULL;
			commands = zbx_realloc(commands, (i + 2) * sizeof(ZBX_METRIC));
			commands[i + 1].key = NULL;
			break;
		}

		/* replace existing parameters */
		if (0 == strcmp(commands[i].key, key))
		{
			zbx_free(commands[i].main_param);
			zbx_free(commands[i].test_param);

			commands[i].flags = flag;
			commands[i].function = &EXECUTE_STR;
			commands[i].main_param = zbx_strdup(NULL, command);
			commands[i].test_param = NULL;
			break;
		}
	}

	return SUCCEED;
}

void	init_metrics()
{
	int	i;

	commands = zbx_malloc(commands, sizeof(ZBX_METRIC));
	commands[0].key = NULL;

#if defined(WITH_COMMON_METRICS)
	for (i = 0; NULL != parameters_common[i].key; i++)
		add_metric(&parameters_common[i]);
#endif /* USE_COMMON_METRICS */

#if defined(WITH_SPECIFIC_METRICS)
	for (i = 0; NULL != parameters_specific[i].key; i++)
		add_metric(&parameters_specific[i]);
#endif /* USE_SPECIFIC_METRICS */

#if defined(WITH_SIMPLE_METRICS)
	for (i = 0; NULL != parameters_simple[i].key; i++)
		add_metric(&parameters_simple[i]);
#endif /* USE_SIMPLE_METRICS */
}

void	free_metrics()
{
	if (NULL != commands)
	{
		int	i;

		for (i = 0; NULL != commands[i].key; i++)
		{
			zbx_free(commands[i].key);
			zbx_free(commands[i].main_param);
			zbx_free(commands[i].test_param);
		}

		zbx_free(commands);
	}
}

void	init_result(AGENT_RESULT *result)
{
	result->type = 0;

	result->ui64 = 0;
	result->dbl = 0;
	result->str = NULL;
	result->text = NULL;
	result->msg = NULL;
}

void	free_result(AGENT_RESULT *result)
{
	UNSET_UI64_RESULT(result);
	UNSET_DBL_RESULT(result);
	UNSET_STR_RESULT(result);
	UNSET_TEXT_RESULT(result);
	UNSET_MSG_RESULT(result);
}

/*
 * return value: 0 - error;
 *               1 - command without parameters;
 *               2 - command with parameters
 */
int	parse_command(const char *command, char *cmd, int cmd_max_len, char *param, int param_max_len)
{
	char	*pl, *pr;
	size_t	sz;

	pl = strchr(command, '[');
	pr = strrchr(command, ']');

	if (pl > pr)
		return 0;

	if (NULL != pl && NULL == pr)
		return 0;

	if (NULL == pl && NULL != pr)
		return 0;

	if (NULL == pl && NULL == pr) /* simple check? */
	{
		if (NULL != (pl = strchr(command, ',')))
		{
			for (pr = pl + 1; '\0' != *pr; pr++);
		}
	}

	if (NULL != cmd)
	{
		if (NULL != pl)
		{
			if (cmd_max_len < (sz = (size_t)(pl - command) + 1))
				sz = cmd_max_len;
			memcpy(cmd, command, sz - 1);
			cmd[sz - 1] = '\0';
		}
		else
			zbx_strlcpy(cmd, command, cmd_max_len);
	}

	if (NULL != param)
		*param = '\0';

	if (NULL != pl && NULL != pr)
	{
		if (NULL != param)
		{
			if (param_max_len < (sz = (size_t)(pr - pl)))
				sz = param_max_len;
			memcpy(param, pl + 1, sz - 1);
			param[sz - 1] = '\0';
		}
	}
	else
		return 1;

	return 2;
}

void	test_parameter(const char *key, unsigned flags)
{
	AGENT_RESULT	result;

	init_result(&result);

	process(key, flags, &result);

	if (0 != (result.type & AR_UINT64))
		printf(" [u|" ZBX_FS_UI64 "]", result.ui64);

	if (0 != (result.type & AR_DOUBLE))
		printf(" [d|" ZBX_FS_DBL "]", result.dbl);

	if (0 != (result.type & AR_STRING))
		printf(" [s|%s]", result.str);

	if (0 != (result.type & AR_TEXT))
		printf(" [t|%s]", result.text);

	if (0 != (result.type & AR_MESSAGE))
		printf(" [m|%s]", result.msg);

	free_result(&result);

	printf("\n");

	fflush(stdout);
}

void	test_parameters()
{
	int	i;

	for (i = 0; NULL != commands[i].key; i++)
		test_parameter(commands[i].key, PROCESS_TEST | PROCESS_USE_TEST_PARAM);
}

static int	replace_param(const char *cmd, const char *param, char *out, int outlen, char *error, int max_err_len)
{
	int		ret = SUCCEED;
	char		buf[MAX_STRING_LEN];
	char		command[MAX_STRING_LEN];
	char		*pl, *pr;
	const char	suppressed_chars[] = "\\'\"`*?[]{}~$!&;()<>|#@\0", *c;

	assert(out);

	out[0] = '\0';

	if (NULL == cmd && NULL == param)
		return ret;

	strscpy(command, cmd);

	pl = command;

	while (NULL != (pr = strchr(pl, '$')) && outlen > 0)
	{
		pr[0] = '\0';
		zbx_strlcat(out, pl, outlen);
		outlen -= MIN((int)strlen(pl), (int)outlen);
		pr[0] = '$';

		if ('0' <= pr[1] && pr[1] <= '9')
		{
			buf[0] = '\0';

			if ('0' == pr[1])
			{
				strscpy(buf, command);
			}
			else
			{
				get_param(param, (int)(pr[1] - '0'), buf, sizeof(buf));

				if (0 == CONFIG_UNSAFE_USER_PARAMETERS)
				{
					for (c = suppressed_chars; '\0' != *c; c++)
					{
						if (NULL != strchr(buf, *c))
						{
							zbx_snprintf(error, max_err_len, "Special characters '%s'"
									" are not allowed in the parameters",
									suppressed_chars);
							ret = FAIL;
							break;
						}
					}
				}
			}

			if (FAIL == ret)
				break;

			zbx_strlcat(out, buf, outlen);
			outlen -= MIN((int)strlen(buf), (int)outlen);

			pl = pr + 2;
			continue;
		}
		else if ('$' == pr[1])
		{
			pr++; /* remove second '$' symbol */
		}

		pl = pr + 1;
		zbx_strlcat(out, "$", outlen);
		outlen -= 1;
	}

	zbx_strlcat(out, pl, outlen);
	outlen -= MIN((int)strlen(pl), (int)outlen);

	return ret;
}

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result)
{
	char	*p;
	int	i = 0;

	int	(*function)() = NULL;
	int	ret = SUCCEED;
	int	err = SYSINFO_RET_OK;

	char	usr_cmd[MAX_STRING_LEN];
	char	usr_param[MAX_STRING_LEN];

	char	usr_command[MAX_STRING_LEN];
	int	usr_command_len;

	char	param[MAX_STRING_LEN], error[MAX_STRING_LEN];

	assert(result);
	init_result(result);

	*error = '\0';

	alias_expand(in_command, usr_command, sizeof(usr_command));

	usr_command_len = (int)strlen(usr_command);

	for (p = usr_command + usr_command_len - 1; p > usr_command && NULL != strchr(ZBX_WHITESPACE, *p); --p)
		;

	if (NULL != strchr(ZBX_WHITESPACE, p[1]))
		p[1] = '\0';

	if (0 != parse_command(usr_command, usr_cmd, sizeof(usr_cmd), usr_param, sizeof(usr_param)))
	{
		for (i = 0; NULL != commands[i].key; i++)
		{
			if (0 == strcmp(commands[i].key, usr_cmd))
			{
				function = commands[i].function;
				break;
			}
		}
	}

	param[0] = '\0';

	if (NULL != function)
	{
		if (0 != (commands[i].flags & CF_USEUPARAM))
		{
			if (0 != (flags & PROCESS_TEST) &&
					0 != (flags & PROCESS_USE_TEST_PARAM) &&
					NULL != commands[i].test_param)
			{
				strscpy(usr_param, commands[i].test_param);
			}
		}
		else
			usr_param[0] = '\0';

		if (NULL != commands[i].main_param)
		{
			if (0 != (commands[i].flags & CF_USEUPARAM))
			{
				err = replace_param(commands[i].main_param, usr_param,
						param, sizeof(param), error, sizeof(error));
			}
			else
				strscpy(param, commands[i].main_param);
		}
		else
			strscpy(param, usr_param);

		if (FAIL != err)
		{
			err = function(usr_command, param, flags, result);

			if (SYSINFO_RET_FAIL == err)
				err = NOTSUPPORTED;
			else if (SYSINFO_RET_TIMEOUT == err)
				err = TIMEOUT_ERROR;
		}
		else
		{
			err = NOTSUPPORTED;
			if ('\0' != *error)
				zabbix_log(LOG_LEVEL_WARNING, "Item [%s] error: %s", in_command, error);
		}
	}
	else
		err = NOTSUPPORTED;

	if (0 != (flags & PROCESS_TEST))
	{
		printf("%s", usr_cmd);

		if (0 != (commands[i].flags & CF_USEUPARAM))
		{
			printf("[]");
			i = 2;
		}
		else
			i = 0;

		i += (int)strlen(usr_cmd);

#define COLUMN_2_X 45 /* max space count */
		i = (i > COLUMN_2_X ? 1 : COLUMN_2_X - i);

		printf("%-*.*s", i, i, " "); /* print spaces */
	}

	if (NOTSUPPORTED == err)
	{
		if (0 == (result->type & AR_MESSAGE))
			SET_MSG_RESULT(result, zbx_strdup(NULL, "ZBX_NOTSUPPORTED"));

		ret = NOTSUPPORTED;
	}
	else if (TIMEOUT_ERROR == err)
	{
		if (0 == (result->type & AR_MESSAGE))
			SET_MSG_RESULT(result, zbx_strdup(NULL, "ZBX_ERROR"));

		ret = TIMEOUT_ERROR;
	}

	return ret;
}

int	set_result_type(AGENT_RESULT *result, int value_type, int data_type, char *c)
{
	int		ret = FAIL;
	zbx_uint64_t	value_uint64;
	double		value_double;

	assert(result);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			zbx_rtrim(c, " \"");
			zbx_ltrim(c, " \"+");
			del_zeroes(c);

			switch (data_type)
			{
				case ITEM_DATA_TYPE_BOOLEAN:
					if (SUCCEED == is_boolean(c, &value_uint64))
					{
						SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				case ITEM_DATA_TYPE_OCTAL:
					if (SUCCEED == is_uoct(c))
					{
						ZBX_OCT2UINT64(value_uint64, c);
						SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				case ITEM_DATA_TYPE_DECIMAL:
					if (SUCCEED == is_uint64(c, &value_uint64))
					{
						SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				case ITEM_DATA_TYPE_HEXADECIMAL:
					if (SUCCEED == is_uhex(c))
					{
						ZBX_HEX2UINT64(value_uint64, c);
						SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					else if (SUCCEED == is_hex_string(c))
					{
						zbx_remove_whitespace(c);
						ZBX_HEX2UINT64(value_uint64, c);
						SET_UI64_RESULT(result, value_uint64);
						ret = SUCCEED;
					}
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					break;
			}
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_rtrim(c, " \"");
			zbx_ltrim(c, " \"+");

			if (SUCCEED != is_double(c))
				break;
			value_double = atof(c);

			SET_DBL_RESULT(result, value_double);
			ret = SUCCEED;
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_LOG:
			zbx_replace_invalid_utf8(c);
			SET_STR_RESULT(result, strdup(c));
			ret = SUCCEED;
			break;
		case ITEM_VALUE_TYPE_TEXT:
			zbx_replace_invalid_utf8(c);
			SET_TEXT_RESULT(result, strdup(c));
			ret = SUCCEED;
			break;
	}

	if (SUCCEED != ret)
	{
		char	*error = NULL;

		zbx_remove_chars(c, "\r\n");

		if (ITEM_VALUE_TYPE_UINT64 == value_type)
			error = zbx_dsprintf(error,
					"Received value [%s] is not suitable for value type [%s] and data type [%s]",
					c, zbx_item_value_type_string(value_type), zbx_item_data_type_string(data_type));
		else
			error = zbx_dsprintf(error,
					"Received value [%s] is not suitable for value type [%s]",
					c, zbx_item_value_type_string(value_type));

		SET_MSG_RESULT(result, error);
	}

	return ret;
}

static zbx_uint64_t	*get_result_ui64_value(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	assert(result);

	if (ISSET_UI64(result))
	{
		/* nothing to do */
	}
	else if (ISSET_DBL(result))
	{
		SET_UI64_RESULT(result, result->dbl);
	}
	else if (ISSET_STR(result))
	{
		zbx_rtrim(result->str, " \"");
		zbx_ltrim(result->str, " \"+");
		del_zeroes(result->str);

		if (SUCCEED != is_uint64(result->str, &value))
			return NULL;

		SET_UI64_RESULT(result, value);
	}
	else if (ISSET_TEXT(result))
	{
		zbx_rtrim(result->text, " \"");
		zbx_ltrim(result->text, " \"+");
		del_zeroes(result->text);

		if (SUCCEED != is_uint64(result->text, &value))
			return NULL;

		SET_UI64_RESULT(result, value);
	}
	/* skip AR_MESSAGE - it is information field */

	if (ISSET_UI64(result))
		return &result->ui64;

	return NULL;
}

static double	*get_result_dbl_value(AGENT_RESULT *result)
{
	double	value;

	assert(result);

	if (ISSET_DBL(result))
	{
		/* nothing to do */
	}
	else if (ISSET_UI64(result))
	{
		SET_DBL_RESULT(result, result->ui64);
	}
	else if (ISSET_STR(result))
	{
		zbx_rtrim(result->str, " \"");
		zbx_ltrim(result->str, " \"+");

		if (SUCCEED != is_double(result->str))
			return NULL;
		value = atof(result->str);

		SET_DBL_RESULT(result, value);
	}
	else if (ISSET_TEXT(result))
	{
		zbx_rtrim(result->text, " \"");
		zbx_ltrim(result->text, " \"+");

		if (SUCCEED != is_double(result->text))
			return NULL;
		value = atof(result->text);

		SET_DBL_RESULT(result, value);
	}
	/* skip AR_MESSAGE - it is information field */

	if (ISSET_DBL(result))
		return &result->dbl;

	return NULL;
}

static char	**get_result_str_value(AGENT_RESULT *result)
{
	char	*p, tmp;

	assert(result);

	if (ISSET_STR(result))
	{
		/* nothing to do */
	}
	else if (ISSET_TEXT(result))
	{
		/* NOTE: copy only line */
		for (p = result->text; '\0' != *p && '\r' != *p && '\n' != *p; p++);
		tmp = *p; /* remember result->text character */
		*p = '\0'; /* replace to NUL */
		SET_STR_RESULT(result, zbx_strdup(NULL, result->text)); /* copy line */
		*p = tmp; /* restore result->text character */

	}
	else if (ISSET_UI64(result))
	{
		SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64));
	}
	else if (ISSET_DBL(result))
	{
		SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl));
	}
	/* skip AR_MESSAGE - it is information field */

	if (ISSET_STR(result))
		return &result->str;

	return NULL;
}

static char	**get_result_text_value(AGENT_RESULT *result)
{
	assert(result);

	if (ISSET_TEXT(result))
	{
		/* nothing to do */
	}
	else if (ISSET_STR(result))
	{
		SET_TEXT_RESULT(result, zbx_strdup(NULL, result->str));
	}
	else if (ISSET_UI64(result))
	{
		SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64));
	}
	else if (ISSET_DBL(result))
	{
		SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl));
	}
	/* skip AR_MESSAGE - it is information field */

	if (ISSET_TEXT(result))
		return &result->text;

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: get_result_value_by_type                                         *
 *                                                                            *
 * Purpose: return value of result in special type                            *
 *          if value missing, convert existing value to requested type        *
 *                                                                            *
 * Return value:                                                              *
 *         NULL - if value is missing or can't be converted                   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  better use definitions                                          *
 *                GET_UI64_RESULT                                             *
 *                GET_DBL_RESULT                                              *
 *                GET_STR_RESULT                                              *
 *                GET_TEXT_RESULT                                             *
 *                GET_MSG_RESULT                                              *
 *                                                                            *
 *    AR_MESSAGE - skipped in conversion                                      *
 *                                                                            *
 ******************************************************************************/
void	*get_result_value_by_type(AGENT_RESULT *result, int require_type)
{
	assert(result);

	switch (require_type)
	{
		case AR_UINT64:
			return (void *)get_result_ui64_value(result);
		case AR_DOUBLE:
			return (void *)get_result_dbl_value(result);
		case AR_STRING:
			return (void *)get_result_str_value(result);
		case AR_TEXT:
			return (void *)get_result_text_value(result);
		case AR_MESSAGE:
			if (ISSET_MSG(result))
				return (void *)(&result->msg);
			break;
		default:
			break;
	}

	return NULL;
}
