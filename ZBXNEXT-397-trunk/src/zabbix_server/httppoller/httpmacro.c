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
#include "db.h"
#include "log.h"

#include "httpmacro.h"

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
void	http_substitute_macros(char *macros, char *data, size_t data_max_len)
{
	const char	*__function_name = "http_substitute_macros";

	char		*pl = NULL, *pr = NULL, str_out[MAX_STRING_LEN], replace_to[MAX_STRING_LEN],
			*c, *c2, save, *replacement, save2;
	int		outlen, var_len;

	assert(data);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, data);

	*str_out = '\0';
	outlen = sizeof(str_out) - 1;
	pl = data;
	while (NULL != (pr = strchr(pl, '{')) && 0 < outlen)
	{
		*pr = '\0';
		zbx_strlcat(str_out, pl, outlen);
		outlen -= MIN(strlen(pl), outlen);
		*pr = '{';

		zbx_snprintf(replace_to, sizeof(replace_to), "{");
		var_len = 1;

		if (NULL != (c = strchr(pr, '}')))
		{
			/* macro in pr */
			save = c[1]; c[1] = 0;

			if (NULL != (c2 = strstr(macros, pr)))
			{
				if (NULL != (replacement = strchr(c2, '=')))
				{
					replacement++;
					if (NULL != (c2 = strchr(replacement, '\r')))
					{
						save2 = *c2; *c2 = 0;
						var_len = strlen(pr);
						strscpy(replace_to, replacement);
						*c2 = save2;
					}
					else
					{
						var_len = strlen(pr);
						strscpy(replace_to, replacement);
					}
				}

			}
			/* restore pr */
			c[1] = save;
		}

		zbx_strlcat(str_out, replace_to, outlen);
		outlen -= MIN(strlen(replace_to), outlen);
		pl = pr + var_len;
	}
	zbx_strlcat(str_out, pl, outlen);
	outlen -= MIN(strlen(pl), outlen);

	zbx_strlcpy(data, str_out, data_max_len);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data:'%s'", __function_name, data);
}
