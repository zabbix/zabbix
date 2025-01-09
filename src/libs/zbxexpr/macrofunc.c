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

/* strptime() on newer and older GNU/Linux systems */
#define _GNU_SOURCE

#include "zbxexpr.h"

#include "zbxcommon.h"
#include "zbxregexp.h"
#include "zbxtime.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxcrypto.h"

#define ZBX_RULE_BUFF_LEN 512

typedef struct
{
	char		character;
	const char*	html_entity;
}
zbx_htmlentity_t;

static const zbx_htmlentity_t zbx_html_translation[] =
{
	{'&',	"&amp;"},
	{'"',	"&quot;"},
	{'\'',	"&#39;"},
	{'<',	"&lt;"},
	{'>',	"&gt;"},
	{'\'',	"&#039;"}
};

/******************************************************************************
 *                                                                            *
 * Purpose: calculates regular expression substitution.                       *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *               FAIL    - the function calculation failed                    *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_regsub(char **params, size_t nparam, char **out)
{
	char	*value = NULL;

	if (2 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	if (FAIL == zbx_regexp_sub(*out, params[0], params[1], &value))
		return FAIL;

	if (NULL == value)
		value = zbx_strdup(NULL, "");

	zbx_free(*out);
	*out = value;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fills rules array according to parameter.                         *
 *                                                                            *
 * Parameters: param  - [IN] function parameter                               *
 *             out    - [OUT] calculated result array of rules                *
 *                                                                            *
 * Return value: length of output value                                       *
 *               FAIL    - the function calculation failed                    *
 *                                                                            *
 ******************************************************************************/
static int	zbx_tr_rule_create(const unsigned char *param, unsigned char *dst)
{
	unsigned char		c, range_from = 0;
	const unsigned char	*ptr;
	int			i, len = 0;

	/* construct string to replace */
	for (ptr = param; '\0' != *ptr; ptr++)
	{
		if ('\\' == *ptr)
		{
			ptr++;
			switch (*ptr)
			{
				case '\0':
					dst[len++] = '\\';
					return len;
				case '\\':
					c = '\\';
					break;
				case 'a':
					c = '\a';
					break;
				case 'b':
					c = '\b';
					break;
				case 'f':
					c = '\f';
					break;
				case 'n':
					c = '\n';
					break;
				case 'r':
					c = '\r';
					break;
				case 't':
					c = '\t';
					break;
				case 'v':
					c = '\v';
					break;
				default:
					c = *ptr;
					break;
			}
			if ('-' == *(ptr + 1) && '\0' != *(ptr + 2) && 0 == range_from)
			{
				range_from = c;
				ptr++;
				/* continue allow last range char to be escaped */
				continue;
			}
		}
		else
		{
			c = *ptr;
			if ('-' == *(ptr + 1) && '\0' != *(ptr + 2) && 0 == range_from)
			{
				range_from = c;
				ptr++;
				/* continue allow last range char to be escaped */
				continue;
			}
		}

		if ('\0' != range_from)
		{
			if (range_from > c)
			{
				zabbix_log(LOG_LEVEL_DEBUG,
					"%s() range-endpoints are in reverse collating sequence order: %s",
					__func__, param);
				return FAIL;
			}

			for (i = 0; i < c - range_from + 1; i++)
			{
				dst[len++] = range_from + i;
				if (ZBX_RULE_BUFF_LEN <= len)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s() too big parameter rule: %s", __func__, param);
					return FAIL;
				}
			}
			range_from = 0;
		}
		else
		{
			dst[len++] = c;
			if (ZBX_RULE_BUFF_LEN <= len)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() parameter rule overflow %s", __func__, param);
				return FAIL;
			}
		}
	}

	return len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates translation expression.                                *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *               FAIL    - the function calculation failed                    *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_tr(char **params, size_t nparam, char **out)
{
	unsigned char	translate[UCHAR_MAX+1], *ptr, buff_from[ZBX_RULE_BUFF_LEN], buff_to[ZBX_RULE_BUFF_LEN];
	int		buff_from_len, buff_to_len;
	size_t		i;

	if (2 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	if (FAIL == (buff_from_len = zbx_tr_rule_create((unsigned char*)params[0], buff_from)))
		return FAIL;

	if (FAIL == (buff_to_len = zbx_tr_rule_create((unsigned char*)params[1], buff_to)))
		return FAIL;

	if (0 == buff_from_len || 0 == buff_to_len)
		return SUCCEED;
	/* prepare and fill translation table according rules */
	for (i = 0; i < sizeof(translate); i++)
		translate[i] = i;

	for (i = 0; i < (size_t)buff_from_len; i++)
	{
		if (i < (size_t)buff_to_len)
			translate[buff_from[i]] = buff_to[i];
		else
			translate[buff_from[i]] = buff_to[buff_to_len - 1];
	}

	/* translate */
	ptr = (unsigned char*)*out;
	while ('\0' != *ptr)
	{
		*ptr = translate[*ptr];
		ptr++;
	}

	zbx_replace_invalid_utf8(*out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose:  zbx_base64_encode wrapper.                                       *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_btoa(char **params, size_t nparam, char **out)
{
	char	*buffer = NULL;

	ZBX_UNUSED(params);
	if (0 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	zbx_base64_encode_dyn(*out, &buffer, strlen(*out));
	zbx_free(*out);
	*out = buffer;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: zbx_url_encode wrapper.                                           *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_urlencode(char **params, size_t nparam, char **out)
{
	ZBX_UNUSED(params);

	if (0 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	zbx_url_encode(*out, out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: zbx_url_decode wrapper.                                           *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_urldecode(char **params, size_t nparam, char **out)
{
	ZBX_UNUSED(params);

	if (0 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	zbx_url_decode(*out, out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: zbx_strlower wrapper.                                             *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_lowercase(char **params, size_t nparam, char **out)
{
	ZBX_UNUSED(params);

	if (0 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	zbx_strlower(*out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: zbx_strupper wrapper.                                             *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_uppercase(char **params, size_t nparam, char **out)
{
	ZBX_UNUSED(params);

	if (0 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	zbx_strupper(*out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Converts a string into an HTML-encoded string.                    *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_htmlencode(char **params, size_t nparam, char **out)
{
	zbx_htmlentity_t	*pentity;
	char			ch;

	ZBX_UNUSED(params);

	if (0 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	for (size_t idx = 0; '\0' != (*out)[idx]; idx++)
	{
		for (pentity = (zbx_htmlentity_t *)zbx_html_translation;
			pentity < zbx_html_translation + ARRSIZE(zbx_html_translation); pentity++)
		{
			ch = pentity->character;
			if ((*out)[idx] == ch)
			{
				zbx_replace_string(out, idx, &idx, pentity->html_entity);
				break;
			}
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Converts HTML-encoded string into a decoded string.               *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_htmldecode(char **params, size_t nparam, char **out)
{
	char	*entity, *ptr;

	ZBX_UNUSED(params);

	if (0 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	for (ptr = *out; '\0' != *ptr; ptr++)
	{
		if ('&' == *ptr)
		{
			for (size_t i = 0; i < ARRSIZE(zbx_html_translation); i++)
			{
				entity = (char *)zbx_html_translation[i].html_entity;
				if (0 == strncmp(ptr, entity, strlen(entity)))
				{
					*ptr = zbx_html_translation[i].character;
					memmove(ptr + 1, ptr + strlen(entity), strlen(ptr + strlen(entity)) + 1);
					break;
				}
			}

		}
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates case insensitive regular expression replacement.       *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_regrepl(char **params, size_t nparam, char **out)
{
	char	*value = NULL;
	size_t	i;

	if (0 == nparam || 0 != nparam % 2)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	for (i = 0; i < nparam; i += 2)
	{
		int	ret;

		ret = zbx_regexp_repl(*out, params[i], params[i + 1], &value);
		zbx_free(*out);
		*out = value;
		value = NULL;

		if (FAIL == ret)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates case insensitive regular expression substitution.      *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *               FAIL    - function calculation failed                        *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_iregsub(char **params, size_t nparam, char **out)
{
	char	*value = NULL;

	if (2 != nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	if (FAIL == zbx_iregexp_sub(*out, params[0], params[1], &value))
		return FAIL;

	if (NULL == value)
		value = zbx_strdup(NULL, "");

	zbx_free(*out);
	*out = value;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: time formatting macro function.                                   *
 *                                                                            *
 * Parameters: params - [IN] function parameters                              *
 *             nparam - [IN] function parameter count                         *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully           *
 *               FAIL    - the function calculation failed                    *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_fmttime(char **params, size_t nparam, char **out)
{
	struct tm	local_time;
	time_t		time_new;
	char		*buf = NULL;

	if (0 == nparam || 2 < nparam)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid parameters number", __func__);
		return FAIL;
	}

	time_new = time(&time_new);
	localtime_r(&time_new, &local_time);

	if (NULL == strptime(*out, "%H:%M:%S", &local_time) &&
			NULL == strptime(*out, "%Y-%m-%dT%H:%M:%S", &local_time) &&
			NULL == strptime(*out, "%Y-%m-%dT%H:%M:%S%z", &local_time))
	{
		if (0 == (time_new = atoi(*out)))
			return FAIL;

		localtime_r(&time_new, &local_time);
	}

	if (2 == nparam)
	{
		char	*p = params[1];
		size_t	len;

		while ('\0' != *p)
		{
			zbx_time_unit_t	unit;

			if ('/' == *p)
			{
				if (ZBX_TIME_UNIT_UNKNOWN == (unit = zbx_tm_str_to_unit(++p)))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "unexpected character starting with \"%s\"", p);
					return FAIL;
				}

				zbx_tm_round_down(&local_time, unit);

				p++;
			}
			else if ('+' == *p || '-' == *p)
			{
				int	num;
				char	op, *error = NULL;

				op = *(p++);

				if (FAIL == zbx_tm_parse_period(p, &len, &num, &unit, &error))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "failed to parse time period: %s", error);
					zbx_free(error);
					return FAIL;
				}

				if ('+' == op)
					zbx_tm_add(&local_time, num, unit);
				else
					zbx_tm_sub(&local_time, num, unit);

				p += len;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "unexpected character starting with \"%s\"", p);
				return FAIL;
			}
		}
	}

	buf = zbx_malloc(NULL, MAX_STRING_LEN);

	if (0 == strftime(buf, MAX_STRING_LEN, params[0], &local_time))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "invalid first parameter \"%s\"", params[0]);
		zbx_free(buf);
		return FAIL;
	}

	zbx_free(*out);
	*out = buf;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: number formatting macro function.                                 *
 *                                                                            *
 * Parameters: params - [IN] function data                                    *
 *             nparam - [IN] parameter count                                  *
 *             out    - [IN/OUT] input/output value                           *
 *                                                                            *
 * Return value: SUCCEED - function was calculated successfully               *
 *               FAIL    - function calculation failed                        *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_fmtnum(char **params, size_t nparam, char **out)
{
	double	value;
	int	precision;

	if (1 != nparam)
		return FAIL;

	if (SUCCEED == zbx_is_uint32(*out, &value))
		return SUCCEED;

	if (FAIL == zbx_is_double(*out, &value))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "macro \"%s\" is not a number", *out);
		return FAIL;
	}

	if (FAIL == zbx_is_uint_range(params[0], &precision, 0, 20))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "invalid parameter \"%s\"", params[0]);
		return FAIL;
	}

	*out = zbx_dsprintf(*out, "%.*f", precision, value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates macro function value.                                  *
 *                                                                            *
 * Parameters: expression - [IN] expression containing macro function         *
 *             func_macro - [IN] information about macro function token       *
 *             out        - [IN/OUT] input/output value                       *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully           *
 *               FAIL    - the function calculation failed                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_calculate_macro_function(const char *expression, const zbx_token_func_macro_t *func_macro, char **out)
{
	char			**params;
	const char		*ptr;
	size_t			nparam = 0, param_alloc = 8, len, sep_pos = 0;
	int			(*macrofunc)(char **params, size_t nparam, char **out), ret = FAIL;
	zbx_strloc_t		loc = func_macro->func_param;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	if (NULL == *out)
		goto out;

	ptr = expression + func_macro->func.l;
	len = func_macro->func_param.l - func_macro->func.l;

	if (ZBX_CONST_STRLEN("regsub") == len && 0 == strncmp(ptr, "regsub", len))
		macrofunc = macrofunc_regsub;
	else if (ZBX_CONST_STRLEN("iregsub") == len && 0 == strncmp(ptr, "iregsub", len))
		macrofunc = macrofunc_iregsub;
	else if (ZBX_CONST_STRLEN("fmttime") == len && 0 == strncmp(ptr, "fmttime", len))
		macrofunc = macrofunc_fmttime;
	else if (ZBX_CONST_STRLEN("fmtnum") == len && 0 == strncmp(ptr, "fmtnum", len))
		macrofunc = macrofunc_fmtnum;
	else if (ZBX_CONST_STRLEN("tr") == len && 0 == strncmp(ptr, "tr", len))
		macrofunc = macrofunc_tr;
	else if (ZBX_CONST_STRLEN("btoa") == len && 0 == strncmp(ptr, "btoa", len))
		macrofunc = macrofunc_btoa;
	else if (ZBX_CONST_STRLEN("urlencode") == len && 0 == strncmp(ptr, "urlencode", len))
		macrofunc = macrofunc_urlencode;
	else if (ZBX_CONST_STRLEN("urldecode") == len && 0 == strncmp(ptr, "urldecode", len))
		macrofunc = macrofunc_urldecode;
	else if (ZBX_CONST_STRLEN("lowercase") == len && 0 == strncmp(ptr, "lowercase", len))
		macrofunc = macrofunc_lowercase;
	else if (ZBX_CONST_STRLEN("uppercase") == len && 0 == strncmp(ptr, "uppercase", len))
		macrofunc = macrofunc_uppercase;
	else if (ZBX_CONST_STRLEN("htmlencode") == len && 0 == strncmp(ptr, "htmlencode", len))
		macrofunc = macrofunc_htmlencode;
	else if (ZBX_CONST_STRLEN("htmldecode") == len && 0 == strncmp(ptr, "htmldecode", len))
		macrofunc = macrofunc_htmldecode;
	else if (ZBX_CONST_STRLEN("regrepl") == len && 0 == strncmp(ptr, "regrepl", len))
		macrofunc = macrofunc_regrepl;
	else
		goto out;

	params = (char **)zbx_malloc(NULL, sizeof(char *) * param_alloc);

	for (ptr = expression + loc.l + 1; ptr <= expression + loc.r; ptr += sep_pos + 1)
	{
		size_t	param_pos, param_len;
		int	quoted;

		if (nparam == param_alloc)
		{
			param_alloc *= 2;
			params = (char **)zbx_realloc(params, sizeof(char *) * param_alloc);
		}

		zbx_function_param_parse(ptr, &param_pos, &param_len, &sep_pos);

		if (')' == ptr[sep_pos] && 0 == nparam && 0 == param_len)
			break;

		params[nparam++] = zbx_function_param_unquote_dyn_compat(ptr + param_pos, param_len, &quoted);
	}

	ret = macrofunc(params, nparam, out);

	while (0 < nparam--)
		zbx_free(params[nparam]);

	zbx_free(params);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), ret: %s", __func__, zbx_result_string(ret));

	return ret;
}
