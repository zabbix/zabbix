/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "log.h"
#include "cfg.h"
#include "alias.h"

#ifdef WITH_AGENT_METRICS
#	include "agent/agent.h"
#endif

#ifdef WITH_COMMON_METRICS
#	include "common/common.h"
#endif

#ifdef WITH_SIMPLE_METRICS
#	include "simple/simple.h"
#endif

#ifdef WITH_SPECIFIC_METRICS
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

int	add_user_parameter(const char *key, char *command)
{
	int		i;
	char		usr_cmd[MAX_STRING_LEN], usr_param[MAX_STRING_LEN];
	unsigned	flag = 0;

	if (0 == (i = parse_command(key, usr_cmd, sizeof(usr_cmd), usr_param, sizeof(usr_param))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to add UserParameter \"%s\": parsing error", key);
		return FAIL;
	}
	else if (2 == i)				/* with specified parameters */
	{
		if (0 != strcmp(usr_param, "*"))	/* must be '*' parameters */
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to add UserParameter \"%s\": invalid key", key);
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
			commands[i].function = &EXECUTE_USER_PARAMETER;
			commands[i].main_param = zbx_strdup(NULL, command);
			commands[i].test_param = 0;

			commands = zbx_realloc(commands, (i + 2) * sizeof(ZBX_METRIC));
			commands[i + 1].key = NULL;
			break;
		}

		/* treat duplicate UserParameters as error */
		if (0 == strcmp(commands[i].key, usr_cmd))
		{
			zabbix_log(LOG_LEVEL_CRIT, "failed to add UserParameter \"%s\": duplicate key", key);
			exit(FAIL);
		}
	}

	return SUCCEED;
}

void	init_metrics()
{
	int	i;

	commands = zbx_malloc(commands, sizeof(ZBX_METRIC));
	commands[0].key = NULL;

#ifdef WITH_AGENT_METRICS
	for (i = 0; NULL != parameters_agent[i].key; i++)
		add_metric(&parameters_agent[i]);
#endif

#ifdef WITH_COMMON_METRICS
	for (i = 0; NULL != parameters_common[i].key; i++)
		add_metric(&parameters_common[i]);
#endif

#ifdef WITH_SPECIFIC_METRICS
	for (i = 0; NULL != parameters_specific[i].key; i++)
		add_metric(&parameters_specific[i]);
#endif

#ifdef WITH_SIMPLE_METRICS
	for (i = 0; NULL != parameters_simple[i].key; i++)
		add_metric(&parameters_simple[i]);
#endif
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
int	parse_command(const char *command, char *cmd, size_t cmd_max_len, char *param, size_t param_max_len)
{
	const char	*pl, *pr;
	size_t		sz;

	for (pl = command; SUCCEED == is_key_char(*pl); pl++)
		;

	if (pl == command)
		return 0;

	if (NULL != cmd)
	{
		if (cmd_max_len <= (sz = (size_t)(pl - command)))
			return 0;

		memcpy(cmd, command, sz);
		cmd[sz] = '\0';
	}

	if ('\0' == *pl)	/* no parameters specified */
	{
		if (NULL != param)
			*param = '\0';
		return 1;
	}

	if ('[' != *pl)		/* unsupported character */
		return 0;

	for (pr = ++pl; '\0' != *pr; pr++)
		;

	if (']' != *--pr)
		return 0;

	if (NULL != param)
	{
		if (param_max_len <= (sz = (size_t)(pr - pl)))
			return 0;

		memcpy(param, pl, sz);
		param[sz] = '\0';
	}

	return 2;
}

void	test_parameter(const char *key, unsigned flags)
{
	AGENT_RESULT	result;

	init_result(&result);

	process(key, flags, &result);

	if (ISSET_UI64(&result))
		printf(" [u|" ZBX_FS_UI64 "]", result.ui64);

	if (ISSET_DBL(&result))
		printf(" [d|" ZBX_FS_DBL "]", result.dbl);

	if (ISSET_STR(&result))
		printf(" [s|%s]", result.str);

	if (ISSET_TEXT(&result))
		printf(" [t|%s]", result.text);

	if (ISSET_MSG(&result))
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

static int	replace_param(const char *cmd, const char *param, char *out, int outlen, char *error, int max_error_len)
{
	int		ret = SUCCEED;
	char		buf[MAX_STRING_LEN];
	char		command[MAX_STRING_LEN];
	char		*pl, *pr;
	const char	suppressed_chars[] = "\\'\"`*?[]{}~$!&;()<>|#@", *c;

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
							zbx_snprintf(error, max_error_len, "Special characters '%s'"
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
			pr++;	/* remove second '$' symbol */
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
	int		rc, ret = NOTSUPPORTED;
	char		usr_cmd[MAX_STRING_LEN];
	char		usr_param[MAX_STRING_LEN];
	char		usr_command[MAX_STRING_LEN];
	char		param[MAX_STRING_LEN], error[MAX_STRING_LEN];

	ZBX_METRIC	*command = NULL;

	assert(result);
	init_result(result);

	alias_expand(in_command, usr_command, sizeof(usr_command));

	if (0 == (rc = parse_command(usr_command, usr_cmd, sizeof(usr_cmd), usr_param, sizeof(usr_param))))
		goto notsupported;

	for (command = commands; NULL != command->key; command++)
	{
		if (0 == strcmp(command->key, usr_cmd))
			break;
	}

	if (NULL != command->key)
	{
		if (0 != (command->flags & CF_USEUPARAM))
		{
			if (0 != (flags & PROCESS_USE_TEST_PARAM) && NULL != command->test_param)
				strscpy(usr_param, command->test_param);
		}
		else if (2 == rc)
			goto notsupported;

		*param = '\0';
		*error = '\0';

		if (NULL != command->main_param)
		{
			if (0 != (command->flags & CF_USEUPARAM))
			{
				if (FAIL == replace_param(command->main_param, usr_param,
						param, sizeof(param), error, sizeof(error)))
				{
					if ('\0' != *error)
						zabbix_log(LOG_LEVEL_WARNING, "item [%s] error: %s", in_command, error);
					goto notsupported;
				}
			}
			else
				strscpy(param, command->main_param);
		}
		else
			strscpy(param, usr_param);

		if (SYSINFO_RET_OK != command->function(usr_command, param, flags, result))
		{
			/* "return NOTSUPPORTED;" would be more appropriate here for preserving original error */
			/* message in "result" but would break things relying on ZBX_NOTSUPPORTED message. */
			goto notsupported;
		}
	}
	else
		goto notsupported;

	ret = SUCCEED;

notsupported:

	if (0 != (flags & PROCESS_TEST))
	{

#define	COL_WIDTH	45

		int	n1, n2 = 0;

		n1 = printf("%s", in_command);

		if (0 < n1 && '\0' != *param)
			n2 = printf("[%s]", param);

		if (0 < n1 && 0 <= n2 && COL_WIDTH > n1 + n2)
			printf("%-*s", COL_WIDTH - n1 - n2, " ");
	}

	if (NOTSUPPORTED == ret)
		SET_MSG_RESULT(result, zbx_strdup(NULL, ZBX_NOTSUPPORTED));

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
		zbx_replace_invalid_utf8(c);

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
