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

#include "zbxparam.h"

#include "zbxexpr.h"
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *                                                                            *
 * Parameters:                                                                *
 *      p       - [IN]  parameter list                                        *
 *      num     - [IN]  requested parameter index                             *
 *      buf     - [OUT] pointer of output buffer                              *
 *      max_len - [IN]  size of output buffer                                 *
 *      type    - [OUT] parameter type (may be NULL)                          *
 *                                                                            *
 * Return value:                                                              *
 *      1 - requested parameter missing or buffer overflow                    *
 *      0 - requested parameter found (value - 'buf' can be empty string)     *
 *                                                                            *
 * Comments:  delimiter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_param(const char *p, int num, char *buf, size_t max_len, zbx_request_parameter_type_t *type)
{
#define ZBX_ASSIGN_PARAM					\
	do							\
	{							\
		if (buf_i == max_len)				\
			return 1;	/* buffer overflow */	\
		buf[buf_i++] = *p;				\
	}							\
	while(0)

	int	state;	/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	array, idx = 1;
	size_t	buf_i = 0;

	if (NULL != type)
		*type = REQUEST_PARAMETER_TYPE_UNDEFINED;

	if (0 == max_len)
		return 1;	/* buffer overflow */

	max_len--;	/* '\0' */

	for (state = 0, array = 0; '\0' != *p && idx <= num; p++)
	{
		switch (state)
		{
			/* init state */
			case 0:
				if (',' == *p)
				{
					if (0 == array)
						idx++;
					else if (idx == num)
						ZBX_ASSIGN_PARAM;
				}
				else if ('"' == *p)
				{
					state = 1;

					if (idx == num)
					{
						if (NULL != type && REQUEST_PARAMETER_TYPE_UNDEFINED == *type)
							*type = REQUEST_PARAMETER_TYPE_STRING;

						if (0 != array)
							ZBX_ASSIGN_PARAM;
					}
				}
				else if ('[' == *p)
				{
					if (idx == num)
					{
						if (NULL != type && REQUEST_PARAMETER_TYPE_UNDEFINED == *type)
							*type = REQUEST_PARAMETER_TYPE_ARRAY;

						if (0 != array)
							ZBX_ASSIGN_PARAM;
					}
					array++;
				}
				else if (']' == *p && 0 != array)
				{
					array--;
					if (0 != array && idx == num)
						ZBX_ASSIGN_PARAM;

					/* skip spaces */
					while (' ' == p[1])
						p++;

					if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
						return 1;	/* incorrect syntax */
				}
				else if (' ' != *p)
				{
					if (idx == num)
					{
						if (NULL != type && REQUEST_PARAMETER_TYPE_UNDEFINED == *type)
							*type = REQUEST_PARAMETER_TYPE_STRING;

						ZBX_ASSIGN_PARAM;
					}

					state = 2;
				}
				break;
			case 1:
				/* quoted */

				if ('"' == *p)
				{
					if (0 != array && idx == num)
						ZBX_ASSIGN_PARAM;

					/* skip spaces */
					while (' ' == p[1])
						p++;

					if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
						return 1;	/* incorrect syntax */

					state = 0;
				}
				else if ('\\' == *p && '"' == p[1])
				{
					if (idx == num && 0 != array)
						ZBX_ASSIGN_PARAM;

					p++;

					if (idx == num)
						ZBX_ASSIGN_PARAM;
				}
				else if (idx == num)
					ZBX_ASSIGN_PARAM;
				break;
			case 2:
				/* unquoted */

				if (',' == *p || (']' == *p && 0 != array))
				{
					p--;
					state = 0;
				}
				else if (idx == num)
					ZBX_ASSIGN_PARAM;
				break;
		}

		if (idx > num)
			break;
	}
#undef ZBX_ASSIGN_PARAM

	/* missing terminating '"' character */
	if (1 == state)
		return 1;

	/* missing terminating ']' character */
	if (0 != array)
		return 1;

	buf[buf_i] = '\0';

	if (idx >= num)
		return 0;

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find number of parameters in parameter list                       *
 *                                                                            *
 * Parameters:                                                                *
 *      p - [IN] parameter list                                               *
 *                                                                            *
 * Return value: number of parameters (starting from 1) or                    *
 *               0 if syntax error                                            *
 *                                                                            *
 * Comments:  delimiter for parameters is ','. Empty parameter list or a list *
 *            containing only spaces is handled as having one empty parameter *
 *            and 1 is returned.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_num_param(const char *p)
{
/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	ret = 1, state, array;

	if (p == NULL)
		return 0;

	for (state = 0, array = 0; '\0' != *p; p++)
	{
		switch (state) {
		/* Init state */
		case 0:
			if (',' == *p)
			{
				if (0 == array)
					ret++;
			}
			else if ('"' == *p)
				state = 1;
			else if ('[' == *p)
			{
				if (0 == array)
					array = 1;
				else
					return 0;	/* incorrect syntax: multi-level array */
			}
			else if (']' == *p && 0 != array)
			{
				array = 0;

				while (' ' == p[1])	/* skip trailing spaces after closing ']' */
					p++;

				if (',' != p[1] && '\0' != p[1])
					return 0;	/* incorrect syntax */
			}
			else if (']' == *p && 0 == array)
				return 0;		/* incorrect syntax */
			else if (' ' != *p)
				state = 2;
			break;
		/* Quoted */
		case 1:
			if ('"' == *p)
			{
				while (' ' == p[1])	/* skip trailing spaces after closing quotes */
					p++;

				if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
					return 0;	/* incorrect syntax */

				state = 0;
			}
			else if ('\\' == *p && '"' == p[1])
				p++;
			break;
		/* Unquoted */
		case 2:
			if (',' == *p || (']' == *p && 0 != array))
			{
				p--;
				state = 0;
			}
			else if (']' == *p && 0 == array)
				return 0;		/* incorrect syntax */
			break;
		}
	}

	/* missing terminating '"' character */
	if (state == 1)
		return 0;

	/* missing terminating ']' character */
	if (array != 0)
		return 0;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return length of the parameter by index (num)                     *
 *          from parameter list (param)                                       *
 *                                                                            *
 * Parameters:                                                                *
 *      p   - [IN]  parameter list                                            *
 *      num - [IN]  requested parameter index                                 *
 *      sz  - [OUT] length of requested parameter                             *
 *                                                                            *
 * Return value:                                                              *
 *      1 - requested parameter missing                                       *
 *      0 - requested parameter found                                         *
 *          (for first parameter result is always 0)                          *
 *                                                                            *
 * Comments: delimiter for parameters is ','                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_param_len(const char *p, int num, size_t *sz)
{
/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	state, array, idx = 1;

	*sz = 0;

	for (state = 0, array = 0; '\0' != *p && idx <= num; p++)
	{
		switch (state) {
		/* Init state */
		case 0:
			if (',' == *p)
			{
				if (0 == array)
					idx++;
				else if (idx == num)
					(*sz)++;
			}
			else if ('"' == *p)
			{
				state = 1;
				if (0 != array && idx == num)
					(*sz)++;
			}
			else if ('[' == *p)
			{
				if (0 != array && idx == num)
					(*sz)++;
				array++;
			}
			else if (']' == *p && 0 != array)
			{
				array--;
				if (0 != array && idx == num)
					(*sz)++;

				/* skip spaces */
				while (' ' == p[1])
					p++;

				if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
					return 1;	/* incorrect syntax */
			}
			else if (' ' != *p)
			{
				if (idx == num)
					(*sz)++;
				state = 2;
			}
			break;
		/* Quoted */
		case 1:
			if ('"' == *p)
			{
				if (0 != array && idx == num)
					(*sz)++;

				/* skip spaces */
				while (' ' == p[1])
					p++;

				if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
					return 1;	/* incorrect syntax */

				state = 0;
			}
			else if ('\\' == *p && '"' == p[1])
			{
				if (idx == num && 0 != array)
					(*sz)++;

				p++;

				if (idx == num)
					(*sz)++;
			}
			else if (idx == num)
				(*sz)++;
			break;
		/* Unquoted */
		case 2:
			if (',' == *p || (']' == *p && 0 != array))
			{
				p--;
				state = 0;
			}
			else if (idx == num)
				(*sz)++;
			break;
		}

		if (idx > num)
			break;
	}

	/* missing terminating '"' character */
	if (state == 1)
		return 1;

	/* missing terminating ']' character */
	if (array != 0)
		return 1;

	if (idx >= num)
		return 0;

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *                                                                            *
 * Parameters:                                                                *
 *      p    - [IN] parameter list                                            *
 *      num  - [IN] requested parameter index                                 *
 *      type - [OUT] parameter type (may be NULL)                             *
 *                                                                            *
 * Return value:                                                              *
 *      NULL - requested parameter missing                                    *
 *      otherwise - requested parameter                                       *
 *          (for first parameter result is not NULL)                          *
 *                                                                            *
 * Comments:  delimiter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
char	*zbx_get_param_dyn(const char *p, int num, zbx_request_parameter_type_t *type)
{
	char	*buf = NULL;
	size_t	sz;

	if (0 != get_param_len(p, num, &sz))
		return buf;

	buf = (char *)zbx_malloc(buf, sz + 1);

	if (0 != zbx_get_param(p, num, buf, sz + 1, type))
		zbx_free(buf);

	return buf;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replaces an item key, SNMP OID or their parameters when callback  *
 *          function returns a new string                                     *
 *                                                                            *
 * Comments: auxiliary function for zbx_replace_key_params_dyn()              *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param(char **data, int key_type, size_t l, size_t *r, int level, int num, int quoted,
		zbx_replace_key_param_f cb, void *cb_data)
{
	char	c = (*data)[*r], *param = NULL;
	int	ret;

	(*data)[*r] = '\0';
	ret = cb(*data + l, key_type, level, num, quoted, cb_data, &param);
	(*data)[*r] = c;

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
 * Purpose: replaces an item key, SNMP OID or their parameters by using       *
 *          callback function                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *      data      - [IN/OUT] item key or SNMP OID                             *
 *      key_type  - [IN] ZBX_KEY_TYPE_*                                       *
 *      cb        - [IN] callback function                                    *
 *      cb_data   - [IN] callback function custom data                        *
 *      error     - [OUT] error message                                       *
 *      maxerrlen - [IN] error size                                           *
 *                                                                            *
 * Return value: SUCCEED - function executed successfully                     *
 *               FAIL - otherwise, error will contain error message           *
 *                                                                            *
 ******************************************************************************/
int	zbx_replace_key_params_dyn(char **data, int key_type, zbx_replace_key_param_f cb, void *cb_data, char *error,
		size_t maxerrlen)
{
	typedef enum
	{
		ZBX_STATE_NEW,
		ZBX_STATE_END,
		ZBX_STATE_UNQUOTED,
		ZBX_STATE_QUOTED
	}
	zbx_parser_state_t;

	size_t			i = 0, l = 0;
	int			level = 0, num = 0, ret = SUCCEED;
	zbx_parser_state_t	state = ZBX_STATE_NEW;

	if (ZBX_KEY_TYPE_ITEM == key_type)
	{
		for (; SUCCEED == zbx_is_key_char((*data)[i]) && '\0' != (*data)[i]; i++)
			;

		if (0 == i)
			goto clean;

		if ('[' != (*data)[i] && '\0' != (*data)[i])
			goto clean;
	}
	else
	{
		zbx_token_t	token;
		int		len, c_l, c_r;

		while ('\0' != (*data)[i])
		{
			if ('{' == (*data)[i] && '$' == (*data)[i + 1] &&
					SUCCEED == zbx_user_macro_parse(&(*data)[i], &len, &c_l, &c_r, NULL))
			{
				i += len + 1;	/* skip to the position after user macro */
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
	}

	ret = replace_key_param(data, key_type, 0, &i, level, num, 0, cb, cb_data);

	for (; '\0' != (*data)[i] && FAIL != ret; i++)
	{
		switch (state)
		{
			case ZBX_STATE_NEW:	/* a new parameter started */
				switch ((*data)[i])
				{
					case ' ':
						break;
					case ',':
						ret = replace_key_param(data, key_type, i, &i, level, num, 0, cb,
								cb_data);
						if (1 == level)
							num++;
						break;
					case '[':
						if (2 == level)
							goto clean;	/* incorrect syntax: multi-level array */
						level++;
						if (1 == level)
							num++;
						break;
					case ']':
						ret = replace_key_param(data, key_type, i, &i, level, num, 0, cb,
								cb_data);
						level--;
						state = ZBX_STATE_END;
						break;
					case '"':
						state = ZBX_STATE_QUOTED;
						l = i;
						break;
					default:
						state = ZBX_STATE_UNQUOTED;
						l = i;
				}
				break;
			case ZBX_STATE_END:	/* end of parameter */
				switch ((*data)[i])
				{
					case ' ':
						break;
					case ',':
						state = ZBX_STATE_NEW;
						if (1 == level)
							num++;
						break;
					case ']':
						if (0 == level)
							goto clean;	/* incorrect syntax: redundant ']' */
						level--;
						break;
					default:
						goto clean;
				}
				break;
			case ZBX_STATE_UNQUOTED:	/* an unquoted parameter */
				if (']' == (*data)[i] || ',' == (*data)[i])
				{
					ret = replace_key_param(data, key_type, l, &i, level, num, 0, cb, cb_data);

					i--;
					state = ZBX_STATE_END;
				}
				break;
			case ZBX_STATE_QUOTED:	/* a quoted parameter */
				if ('"' == (*data)[i] && '\\' != (*data)[i - 1])
				{
					i++;
					ret = replace_key_param(data, key_type, l, &i, level, num, 1, cb, cb_data);
					i--;

					state = ZBX_STATE_END;
				}
				break;
		}
	}
clean:
	if (0 == i || '\0' != (*data)[i] || 0 != level)
	{
		if (NULL != error)
		{
			zbx_snprintf(error, maxerrlen, "Invalid %s at position " ZBX_FS_SIZE_T,
					(ZBX_KEY_TYPE_ITEM == key_type ? "item key" : "SNMP OID"), (zbx_fs_size_t)i);
		}
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *          to be used for keys: key[param1,param2]                           *
 *                                                                            *
 * Parameters:                                                                *
 *      param   - parameter list                                              *
 *      num     - requested parameter index                                   *
 *      buf     - pointer of output buffer                                    *
 *      max_len - size of output buffer                                       *
 *                                                                            *
 * Return value:                                                              *
 *      1 - requested parameter missing                                       *
 *      0 - requested parameter found (value - 'buf' can be empty string)     *
 *                                                                            *
 * Comments:  delimiter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_key_param(char *param, int num, char *buf, size_t max_len)
{
	int	ret;
	char	*pl, *pr;

	pl = strchr(param, '[');
	pr = strrchr(param, ']');

	if (NULL == pl || NULL == pr || pl > pr)
		return 1;

	*pr = '\0';
	ret = zbx_get_param(pl + 1, num, buf, max_len, NULL);
	*pr = ']';

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate count of parameters from parameter list (param)         *
 *          to be used for keys: key[param1,param2]                           *
 *                                                                            *
 * Parameters:                                                                *
 *      param  - parameter list                                               *
 *                                                                            *
 * Return value: count of parameters                                          *
 *                                                                            *
 * Comments:  delimiter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_num_key_param(char *param)
{
	int	ret;
	char	*pl, *pr;

	if (NULL == param)
		return 0;

	pl = strchr(param, '[');
	pr = strrchr(param, ']');

	if (NULL == pl || NULL == pr || pl > pr)
		return 0;

	*pr = '\0';
	ret = zbx_num_param(pl + 1);
	*pr = ']';

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unquotes special symbols in item key parameter                    *
 *                                                                            *
 * Parameters: param - [IN/OUT] item key parameter                            *
 *                                                                            *
 * Comments:                                                                  *
 *   "param"     => param                                                     *
 *   "\"param\"" => "param"                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_unquote_key_param(char *param)
{
	char	*dst;

	if ('"' != *param)
		return;

	for (dst = param++; '\0' != *param; param++)
	{
		if ('\\' == *param && '"' == param[1])
			continue;

		*dst++ = *param;
	}
	*--dst = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: quotes special symbols in item key parameter                      *
 *                                                                            *
 * Parameters: param   - [IN/OUT] item key parameter                          *
 *             forced  - [IN] 2 - enclose parameter in " even if it does not  *
 *                                contain any special characters              *
 *                            1 - enclose parameter in " even if it does not  *
 *                                contain any special characters, except case *
 *                                when parameter ends with backslash          *
 *                            0 - do nothing if the parameter does not        *
 *                                contain any special characters              *
 *                                                                            *
 * Return value: SUCCEED - if parameter was successfully quoted or quoting    *
 *                         was not necessary                                  *
 *               FAIL    - if parameter needs to but cannot be quoted due to  *
 *                         backslash in the end                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_quote_key_param(char **param, int forced)
{
	size_t	sz_src, sz_dst;
	int	req;

	req = ('"' == **param || ' ' == **param || '[' == **param || NULL != strchr(*param, ',') ||
			NULL != strchr(*param, ']')) ? 1 : 0;

	if (0 == req && 0 == forced)
		return SUCCEED;

	if (0 != (sz_src = strlen(*param)) && '\\' == (*param)[sz_src - 1])
	{
		if (0 == req && 2 != forced)
			return SUCCEED;
		else
			return FAIL;
	}

	sz_dst = zbx_get_escape_string_len(*param, "\"") + 3;

	*param = (char *)zbx_realloc(*param, sz_dst);

	(*param)[--sz_dst] = '\0';
	(*param)[--sz_dst] = '"';

	while (0 < sz_src)
	{
		(*param)[--sz_dst] = (*param)[--sz_src];
		if ('"' == (*param)[sz_src])
			(*param)[--sz_dst] = '\\';
	}
	(*param)[--sz_dst] = '"';

	return SUCCEED;
}
