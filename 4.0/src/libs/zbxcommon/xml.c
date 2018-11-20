/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"

static char	data_static[ZBX_MAX_B64_LEN];

/******************************************************************************
 *                                                                            *
 * Purpose: get DATA from <tag>DATA</tag>                                     *
 *                                                                            *
 ******************************************************************************/
int	xml_get_data_dyn(const char *xml, const char *tag, char **data)
{
	size_t	len, sz;
	const char	*start, *end;

	sz = sizeof(data_static);

	len = zbx_snprintf(data_static, sz, "<%s>", tag);
	if (NULL == (start = strstr(xml, data_static)))
		return FAIL;

	zbx_snprintf(data_static, sz, "</%s>", tag);
	if (NULL == (end = strstr(xml, data_static)))
		return FAIL;

	if (end < start)
		return FAIL;

	start += len;
	len = end - start;

	if (len > sz - 1)
		*data = (char *)zbx_malloc(*data, len + 1);
	else
		*data = data_static;

	zbx_strlcpy(*data, start, len + 1);

	return SUCCEED;
}

void	xml_free_data_dyn(char **data)
{
	if (*data == data_static)
		*data = NULL;
	else
		zbx_free(*data);
}

/******************************************************************************
 *                                                                            *
 * Function: xml_escape_dyn                                                   *
 *                                                                            *
 * Purpose: replace <> symbols in string with &lt;&gt; so the resulting       *
 *          string can be written into xml field                              *
 *                                                                            *
 * Parameters: data - [IN] the input string                                   *
 *                                                                            *
 * Return value: an allocated string containing escaped input string          *
 *                                                                            *
 * Comments: The caller must free the returned string after it has been used. *
 *                                                                            *
 ******************************************************************************/
char	*xml_escape_dyn(const char *data)
{
	char		*out, *ptr_out;
	const char	*ptr_in;
	int		size = 0;

	if (NULL == data)
		return zbx_strdup(NULL, "");

	for (ptr_in = data; '\0' != *ptr_in; ptr_in++)
	{
		switch (*ptr_in)
		{
			case '<':
			case '>':
				size += 4;
				break;
			case '&':
				size += 5;
				break;
			case '"':
			case '\'':
				size += 6;
				break;
			default:
				size++;
		}
	}
	size++;

	out = (char *)zbx_malloc(NULL, size);

	for (ptr_out = out, ptr_in = data; '\0' != *ptr_in; ptr_in++)
	{
		switch (*ptr_in)
		{
			case '<':
				*ptr_out++ = '&';
				*ptr_out++ = 'l';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '>':
				*ptr_out++ = '&';
				*ptr_out++ = 'g';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '&':
				*ptr_out++ = '&';
				*ptr_out++ = 'a';
				*ptr_out++ = 'm';
				*ptr_out++ = 'p';
				*ptr_out++ = ';';
				break;
			case '"':
				*ptr_out++ = '&';
				*ptr_out++ = 'q';
				*ptr_out++ = 'u';
				*ptr_out++ = 'o';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '\'':
				*ptr_out++ = '&';
				*ptr_out++ = 'a';
				*ptr_out++ = 'p';
				*ptr_out++ = 'o';
				*ptr_out++ = 's';
				*ptr_out++ = ';';
				break;
			default:
				*ptr_out++ = *ptr_in;
		}

	}
	*ptr_out = '\0';

	return out;
}

/**********************************************************************************
 *                                                                                *
 * Function: xml_escape_xpath_stringsize                                          *
 *                                                                                *
 * Purpose: calculate a string size after symbols escaping                        *
 *                                                                                *
 * Parameters: string - [IN] the string to check                                  *
 *                                                                                *
 * Return value: new size of the string                                           *
 *                                                                                *
 **********************************************************************************/
static size_t	xml_escape_xpath_stringsize(const char *string)
{
	size_t		len = 0;
	const char	*sptr;

	if (NULL == string )
		return 0;

	for (sptr = string; '\0' != *sptr; sptr++)
		len += (('"' == *sptr) ? 2 : 1);

	return len;
}

/**********************************************************************************
 *                                                                                *
 * Function: xml_escape_xpath_insstring                                           *
 *                                                                                *
 * Purpose: replace " symbol in string with ""                                    *
 *                                                                                *
 * Parameters: string - [IN/OUT] the string to update                             *
 *                                                                                *
 **********************************************************************************/
static void xml_escape_xpath_string(char *p, const char *string)
{
	const char	*sptr = string;

	while ('\0' != *sptr)
	{
		if ('"' == *sptr)
			*p++ = '"';

		*p++ = *sptr++;
	}
}

/**********************************************************************************
 *                                                                                *
 * Function: xml_escape_xpath                                                     *
 *                                                                                *
 * Purpose: escaping of symbols for using in xpath expression                     *
 *                                                                                *
 * Parameters: data - [IN/OUT] the string to update                               *
 *                                                                                *
 **********************************************************************************/
void xml_escape_xpath(char **data)
{
	size_t	size;
	char	*buffer;

	if (0 == (size = xml_escape_xpath_stringsize(*data)))
		return;

	buffer = zbx_malloc(NULL, size + 1);
	buffer[size] = '\0';
	xml_escape_xpath_string(buffer, *data);
	zbx_free(*data);
	*data = buffer;
}
