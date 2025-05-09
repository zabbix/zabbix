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

#include "zbxexpr.h"

#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: replaces an item key, SNMP OID or their parameters when callback  *
 *          function returns a new string                                     *
 *                                                                            *
 * Parameters: data        - [IN/OUT] item key or SNMP OID data               *
 *             l           - [IN] left index of item key or SNMP OID          *
 *                                parameter                                   *
 *             r           - [IN/OUT] right index of item key or SNMP OID     *
 *                                parameter                                   *
 *             level       - [IN] level of [] brackets                        *
 *             num         - [IN] number of parameter                         *
 *             is_quoted   - [IN]                                             *
 *             is_item_key - [IN] is item key or SNMP OID                     *
 *             cb          - [IN] callback of substitution function           *
 *             args        - [IN] additional arguments for callback           *
 *                                                                            *
 * Comments: auxiliary function for zbx_substitute_X_params()                 *
 *                                                                            *
 ******************************************************************************/
static int	substitute_item_key(char **data, size_t l, size_t *r, int level, int num, int is_quoted,
		int is_item_key, zbx_subst_func_t cb, va_list args)
{
	char	c = (*data)[*r], *param = NULL;
	int	ret;
	va_list	pargs;

	(*data)[*r] = '\0';
	va_copy(pargs, args);

	if (1 == is_item_key && 0 == level)
		ret = SUCCEED;
	else
		ret = cb(*data + l, level, num, is_quoted, &param, pargs);

	(*data)[*r] = c;
	va_end(pargs);

	if (NULL != param)
	{
		(*r)--;
		zbx_replace_string(data, l, r, param);
		(*r)++;

		zbx_free(param);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: common part of item key/SNMP OID parameter parsing                *
 *                                                                            *
 ******************************************************************************/
static int	parse_params(char **data, size_t *i, int *level, int is_item_key, zbx_subst_func_t cb, va_list args)
{
	typedef enum
	{
		ZBX_STATE_NEW,
		ZBX_STATE_END,
		ZBX_STATE_UNQUOTED,
		ZBX_STATE_QUOTED
	}
	zbx_parser_state_t;

	size_t			l = 0;
	zbx_parser_state_t	state = ZBX_STATE_NEW;
	int			num = 0;

	int	ret = substitute_item_key(data, 0, i, *level, num, 0, is_item_key, cb, args);

	for (; '\0' != (*data)[*i] && FAIL != ret; (*i)++)
	{
		switch (state)
		{
			case ZBX_STATE_NEW:	/* a new parameter started */
				switch ((*data)[*i])
				{
					case ' ':
						break;
					case ',':
						ret = substitute_item_key(data, *i, i, *level, num, 0, is_item_key, cb,
								args);
						if (1 == *level)
							num++;
						break;
					case '[':
						if (2 == *level)
							goto out;	/* incorrect syntax: multi-level array */
						(*level)++;
						if (1 == *level)
							num++;
						break;
					case ']':
						ret = substitute_item_key(data, *i, i, *level, num, 0, is_item_key, cb,
								args);
						(*level)--;
						state = ZBX_STATE_END;
						break;
					case '"':
						state = ZBX_STATE_QUOTED;
						l = *i;
						break;
					default:
						state = ZBX_STATE_UNQUOTED;
						l = *i;
				}
				break;
			case ZBX_STATE_END:	/* end of parameter */
				switch ((*data)[*i])
				{
					case ' ':
						break;
					case ',':
						state = ZBX_STATE_NEW;
						if (1 == *level)
							num++;
						break;
					case ']':
						if (0 == *level)
							goto out;	/* incorrect syntax: redundant ']' */
						(*level)--;
						break;
					default:
						goto out;
				}
				break;
			case ZBX_STATE_UNQUOTED:	/* an unquoted parameter */
				if (']' == (*data)[*i] || ',' == (*data)[*i])
				{
					ret = substitute_item_key(data, l, i, *level, num, 0, is_item_key, cb, args);

					(*i)--;
					state = ZBX_STATE_END;
				}
				break;
			case ZBX_STATE_QUOTED:	/* a quoted parameter */
				if ('"' == (*data)[*i] && '\\' != (*data)[*i - 1])
				{
					(*i)++;
					ret = substitute_item_key(data, l, i, *level, num, 1, is_item_key, cb, args);
					(*i)--;

					state = ZBX_STATE_END;
				}
				break;
		}
	}

out:
	return ret;
}

static int	substitute_item_key_params_args(char **data, char *error, size_t maxerrlen, zbx_subst_func_t cb,
		va_list args)
{
	size_t	i = 0;
	int	level = 0, ret = SUCCEED;

	for (; SUCCEED == zbx_is_key_char((*data)[i]); i++)
		;

	if (0 == i)
		goto out;

	if ('[' != (*data)[i] && '\0' != (*data)[i])
		goto out;

	ret = parse_params(data, &i, &level, 1, cb, args);
out:
	if (0 == i || '\0' != (*data)[i] || 0 != level)
	{
		if (NULL != error)
		{
			zbx_snprintf(error, maxerrlen, "Invalid item key at position " ZBX_FS_SIZE_T, (zbx_fs_size_t)i);
		}
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: safely substitutes macros in item key parameters                  *
 *                                                                            *
 * Parameters:                                                                *
 *      data      - [IN/OUT] item key                                         *
 *      error     - [OUT] error buffer (can be NULL)                          *
 *      maxerrlen - [IN] size of error buffer                                 *
 *      cb        - [IN] callback function                                    *
 *      ...       - [IN/OUT] variadic arguments passed to callback function   *
 *                                                                            *
 * Return value: SUCCEED - function executed successfully                     *
 *               FAIL - otherwise, error will contain error message           *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_item_key_params(char **data, char *error, size_t maxerrlen, zbx_subst_func_t cb, ...)
{
	va_list	args;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): data:%s", __func__, *data);

	va_start(args, cb);

	int	ret = substitute_item_key_params_args(data, error, maxerrlen, cb, args);

	va_end(args);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes macros in SNMP OID or their parameters by using       *
 *          callback function                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *      data      - [IN/OUT] SNMP OID                                         *
 *      error     - [OUT] error buffer (can be NULL)                          *
 *      maxerrlen - [IN] size of error buffer                                 *
 *      cb        - [IN] callback function                                    *
 *      ...       - [IN/OUT] variadic arguments passed to callback function   *
 *                                                                            *
 * Return value: SUCCEED - function executed successfully                     *
 *               FAIL - otherwise, error will contain error message           *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_snmp_oid_params(char **data, char *error, size_t maxerrlen, zbx_subst_func_t cb, ...)
{
	size_t		i = 0;
	int		len, c_l, c_r, level = 0;
	zbx_token_t	token;
	va_list		args;

	va_start(args, cb);

	while ('\0' != (*data)[i])
	{
		if ('{' == (*data)[i] && '$' == (*data)[i + 1] &&
				SUCCEED == zbx_user_macro_parse(&(*data)[i], &len, &c_l, &c_r, NULL))
		{
			i += (size_t)len + 1;	/* skip to the position after user macro */
		}
		else if ('{' == (*data)[i] && '{' == (*data)[i + 1] && '#' == (*data)[i + 2] &&
				SUCCEED == zbx_token_parse_nested_macro(&(*data)[i], &(*data)[i], 0, &token))
		{
			i += token.loc.r - token.loc.l + 1;
		}
		else if ('[' != (*data)[i])
		{
			i++;
		}
		else
			break;
	}

	int	ret = parse_params(data, &i, &level, 0, cb, args);

	if (0 == i || '\0' != (*data)[i] || 0 != level)
	{
		if (NULL != error)
		{
			zbx_snprintf(error, maxerrlen, "Invalid SNMP OID at position " ZBX_FS_SIZE_T, (zbx_fs_size_t)i);
		}
		ret = FAIL;
	}

	va_end(args);

	return ret;
}
