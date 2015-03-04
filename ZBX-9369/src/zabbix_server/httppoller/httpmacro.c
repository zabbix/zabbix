/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "db.h"
#include "log.h"

#include "httpmacro.h"

static int	http_get_macro_value(const char *macros, const char *macro, char **replace_to, size_t *replace_to_alloc)
{
	size_t		sz_macro, replace_to_offset = 0;
	const char	*pm, *pv, *p;
	int		res = FAIL;

	sz_macro = strlen(macro);

	for (pm = macros; NULL != (pm = strstr(pm, macro)); pm += sz_macro)
	{
		if (pm != macros && '\r' != *(pm - 1) && '\n' != *(pm - 1))
			continue;

		pv = pm + sz_macro;

		/* skip white spaces */
		while (' ' == *pv || '\t' == *pv)
			pv++;

		if ('=' != *pv++)
			continue;

		/* skip white spaces */
		while (' ' == *pv || '\t' == *pv)
			pv++;

		for (p = pv; '\0' != *p && '\r' != *p && '\n' != *p; p++)
			;

		/* trim white spaces */
		while (p > pv && (' ' == *(p - 1) || '\t' == *(p - 1)))
			p--;

		zbx_strncpy_alloc(replace_to, replace_to_alloc, &replace_to_offset, pv, p - pv);

		res = SUCCEED;
		break;
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: http_substitute_macros                                           *
 *                                                                            *
 * Purpose: substitute macros in input string by value from http test config  *
 *                                                                            *
 * Parameters: macros - [IN]     macros from httptest                         *
 *             data   - [IN\OUT] string to substitute macros                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	http_substitute_macros(const char *macros, char **data)
{
	const char	*__function_name = "http_substitute_macros";

	char		c, *replace_to = NULL;
	size_t		l, r, replace_to_alloc = 64;
	int		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	for (l = 0; '\0' != (*data)[l]; l++)
	{
		if ('{' != (*data)[l])
			continue;

		for (r = l + 1; '\0' != (*data)[r] && '}' != (*data)[r]; r++)
			;

		if ('}' != (*data)[r])
			break;

		if (NULL == replace_to)
			replace_to = zbx_malloc(replace_to, replace_to_alloc);

		c = (*data)[r + 1];
		(*data)[r + 1] = '\0';

		rc = http_get_macro_value(macros, &(*data)[l], &replace_to, &replace_to_alloc);

		(*data)[r + 1] = c;

		if (SUCCEED != rc)
			continue;

		zbx_replace_string(data, l, &r, replace_to);

		l = r;
	}

	zbx_free(replace_to);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data:'%s'", __function_name, *data);
}
