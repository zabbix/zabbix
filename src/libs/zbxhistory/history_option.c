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

#include "history_option.h"
#include "zbxcommon.h"
#include "zbxhistory.h"
#include "zbxstr.h"

ZBX_VECTOR_IMPL(history_option, zbx_history_option_t)

/*******************************************************************************
 *                                                                             *
 * Purpose: create a history option with name and string value                 *
 *                                                                             *
 * Parameters: name  - [IN] option name                                        *
 *             value - [IN] option value                                       *
 *                                                                             *
 * Return value: The created option.                                           *
 *                                                                             *
 *******************************************************************************/
zbx_history_option_t	history_option_str(const char *name, const char *value)
{
	zbx_history_option_t	option;

	option.name = zbx_strdup(NULL, name);
	option.value = zbx_strdup(NULL, value);

	return option;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: create a history option with name and integer value                *
 *                                                                             *
 * Parameters: name  - [IN] option name                                        *
 *             value - [IN] option value                                       *
 *                                                                             *
 * Return value: The created option.                                           *
 *                                                                             *
 *******************************************************************************/
zbx_history_option_t	history_option_int(const char *name, int value)
{
	zbx_history_option_t	option;

	option.name = zbx_strdup(NULL, name);
	option.value = zbx_dsprintf(NULL, "%d", value);

	return option;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: retrieve specified option value                                    *
 *                                                                             *
 * Parameters: options     - [IN] array of history options                     *
 *             options_num - [IN] number of options in the array               *
 *             name        - [IN] name of the option to retrieve               *
 *                                                                             *
 * Return value: The value of the specified option or NULL if not found        *
 *                                                                             *
 *******************************************************************************/
const char	*history_option_value(const zbx_history_option_t *options, int options_num, const char *name)
{
	for (int i = 0; i < options_num; i++)
	{
		if (0 == strcmp(options[i].name, name))
			return options[i].value;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse a parameter from the given text                             *
 *                                                                            *
 * Parameters: text - [IN] string to parse                                    *
 *                                                                            *
 * Return value: number of characters in the parsed parameter                 *
 *                                                                            *
 * Comments: Only alphanumeric, _, -, . characters are accepted               *
 *                                                                            *
 ******************************************************************************/
static ssize_t	history_option_parse_param(const char *text)
{
	const char	*ptr;

	for (ptr = text; '\0' != *ptr; ptr++)
	{
		if (0 == isalnum((int)*ptr) && '_' != *ptr && '-' != *ptr && '.' != *ptr)
			break;
	}

	return ptr - text;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse unquoted value from given text                              *
 *                                                                            *
 * Parameters: text - [IN] string to parse                                    *
 *                                                                            *
 * Return value: number of characters in the parsed value                     *
 *                                                                            *
 * Comments: parsing stops at control characters or delimiters (', ", space)  *
 *                                                                            *
 ******************************************************************************/
static ssize_t	history_option_parse_value(const char *text)
{
	const char	*ptr;
	const char	*delims = "'\" ,";

	for (ptr = text; '\0' != *ptr; ptr++)
	{
		if (0 != iscntrl((int)*ptr) || NULL != strchr(delims, *ptr))
			break;
	}

	return ptr - text;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse quoted value from given text                                *
 *                                                                            *
 * Parameters: text - [IN] string to parse                                    *
 *                                                                            *
 * Return value: number of characters in the parsed value including quotes    *
 *               FAIL if parsing fails                                        *
 *                                                                            *
 ******************************************************************************/
static ssize_t	history_option_parse_quoted_value(const char *text)
{
	const char	*ptr;

	for (ptr = text + 1; '\0' != *ptr; ptr++)
	{
		if ('\\' == *ptr)
		{
			if ('"' == *(ptr + 1) || '\\' == *(ptr + 1))
				ptr++;
			else
				return FAIL;
		}
		else if ('"' == *ptr)
			return ptr - text + 1;
	}

	return FAIL;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: parse history provider options from a string                       *
 *                                                                             *
 * Parameters: text    - [IN] string containing options in "key=value" format  *
 *             options - [OUT] vector to store parsed options                  *
 *             error   - [OUT] error message                                   *
 *                                                                             *
 * Return value: SUCCEED - options were parsed successfully                    *
 *               FAIL    - otherwise                                           *
 *                                                                             *
 *                                                                             *
 * Comments: Options are expected to be in the format:                         *
 *           "key1=value1,key2=value2,..."                                     *
 *           Spaces around commas are ignored.                                 *
 *                                                                             *
 ******************************************************************************/
static int 	history_get_options(const char *text, zbx_vector_history_option_t *options, char **error)
{
	int	ret = FAIL;

	for (const char *ptr = text;;)
	{
		ssize_t			key_len, value_len;
		const char		*key;
		zbx_history_option_t	option;

		key_len = history_option_parse_param(ptr);
		if (0 == key_len || '\0' == ptr[key_len])
		{
			*error = zbx_dsprintf(NULL, "invalid option starting with \"%s\"", ptr);
			goto out;
		}

		key = ptr;
		ptr += key_len;
		while (' ' == *ptr)
			ptr++;

		if ('=' != *ptr)
		{
			*error = zbx_dsprintf(NULL, "invalid option starting with \"%s\"", key);
			goto out;
		}

		while (' ' == *++ptr)
			;

		if ('"' != *ptr)
			value_len = history_option_parse_value(ptr);
		else
			value_len = history_option_parse_quoted_value(ptr);

		if (FAIL == value_len)
		{
			*error = zbx_dsprintf(NULL, "invalid option value starting with \"%s\"", ptr);
			goto out;
		}

		if (FAIL == zbx_str_extract(key, key_len, &option.name))
		{
			*error = zbx_dsprintf(NULL, "invalid option name starting with \"%s\"", key);
			goto out;
		}

		if (FAIL == zbx_str_extract(ptr, value_len, &option.value))
		{
			zbx_free(option.name);
			*error = zbx_dsprintf(NULL, "invalid option value starting with \"%s\"", ptr);
			goto out;
		}

		zbx_vector_history_option_append(options, option);

		ptr += value_len;
		while (' ' == *ptr)
			ptr++;

		if ('\0' == *ptr)
			break;

		if (',' != *ptr)
		{
			*error = zbx_dsprintf(NULL, "invalid option name starting with \"%s\"", ptr);
			goto out;
		}

		ptr++;
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		history_options_clear(options->values, options->values_num);
		zbx_vector_history_option_clear(options);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse history provider configuration string                       *
 *                                                                            *
 * Parameters: conf    - [IN] configuration string                            *
 *             name    - [OUT] provider name                                  *
 *             options - [OUT] parsed provider options                        *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - successfully parsed                                *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
int	history_provider_parse_options(const char *conf, char **name, zbx_vector_history_option_t *options,
		char **error)
{
	const char	*ptr = conf;
	ssize_t		len;

	len = history_option_parse_param(ptr);
	ptr += len;
	while (' ' == *ptr)
		ptr++;

	/* there must be at least one mandatory option */
	if (0 == len || ',' != *ptr)
	{
		*error = zbx_dsprintf(NULL, "invalid HistoryProvider value \"%s\"", conf);
		return FAIL;
	}

	while (' ' == *(++ptr))
		;

	if (SUCCEED != history_get_options(ptr, options, error))
		return FAIL;

	if (NULL == (ptr = history_option_value(options->values, options->values_num, ZBX_HISTORY_OPTION_VALUE_TYPES)))
	{
		for (int i = 0; i < options->values_num; i++)
		{
			zbx_free(options->values[i].name);
			zbx_free(options->values[i].value);
		}
		zbx_vector_history_option_clear(options);

		*error = zbx_dsprintf(NULL, "cannot fond mandatory option \"%s\" in history provider configuration"
				" \"%s\"", ZBX_HISTORY_OPTION_VALUE_TYPES, conf);

		return FAIL;
	}

	/* unquoted string extraction never fails */
	(void)zbx_str_extract(conf, len, name);

	return SUCCEED;
}

void	history_options_clear(zbx_history_option_t *options, int options_num)
{
	for (int i = 0; i < options_num; i++)
	{
		zbx_free(options[i].name);
		zbx_free(options[i].value);
	}
}

zbx_uint64_t	history_options_type_mask(zbx_history_option_t *options, int options_num, const char **value_types)
{
	zbx_uint64_t	mask = 0;
	const char	*types;

	if (NULL == (types = history_option_value(options, options_num, ZBX_HISTORY_OPTION_VALUE_TYPES)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return 0;
	}

	for (int i = 0; i < ITEM_VALUE_TYPE_COUNT; i++)
	{
		if (SUCCEED == zbx_str_in_list(types, value_types[i], ','))
			mask |= (__UINT64_C(1) << i);
	}

	return mask;
}
