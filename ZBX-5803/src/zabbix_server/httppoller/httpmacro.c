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

#include "db.h"
#include "log.h"

#include "httpmacro.h"

/******************************************************************************
 *                                                                            *
 * Function: http_substitute_macros                                           *
 *                                                                            *
 * Purpose: substitute macros in input string by value from http test config  *
 *                                                                            *
 * Parameters: httptest - http test data, data - string to substitute macros  *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	http_substitute_macros(DB_HTTPTEST *httptest, char *data, int data_max_len)
{
	char
		*pl = NULL,
		*pr = NULL,
		str_out[MAX_STRING_LEN],
		replace_to[MAX_STRING_LEN],
		*c,*c2, save,*replacement,save2;
	int
		outlen,
		var_len;

	zabbix_log(LOG_LEVEL_DEBUG, "In http_substitute_macros(httptestid:" ZBX_FS_UI64 ", data:%s)",
		httptest->httptestid,
		data);

	assert(data);

	*str_out = '\0';
	outlen = sizeof(str_out) - 1;
	pl = data;
	while((pr = strchr(pl, '{')) && outlen > 0)
	{
		pr[0] = '\0';
		zbx_strlcat(str_out, pl, outlen);
		outlen -= MIN(strlen(pl), outlen);
		pr[0] = '{';

		zbx_snprintf(replace_to, sizeof(replace_to), "{");
		var_len = 1;


		if(NULL!=(c=strchr(pr,'}')))
		{
			/* Macro in pr */
			save = c[1]; c[1]=0;

			if(NULL != (c2 = strstr(httptest->macros,pr)))
			{
				if(NULL != (replacement = strchr(c2,'=')))
				{
					replacement++;
					if(NULL != (c2 = strchr(replacement,'\r')))
					{
						save2 = c2[0]; c2[0]=0;
						var_len = strlen(pr);
						zbx_snprintf(replace_to, sizeof(replace_to), "%s", replacement);
						c2[0] = save2;
					}
					else
					{
						var_len = strlen(pr);
						zbx_snprintf(replace_to, sizeof(replace_to), "%s", replacement);
					}
				}

			}
/*			result = DBselect("select value from httpmacro where macro='%s' and httptestid=" ZBX_FS_UI64,
				pr, httptest->httptestid);
			row = DBfetch(result);
			if(row)
			{
				var_len = strlen(pr);
				zbx_snprintf(replace_to, sizeof(replace_to), "%s", row[0]);
			}
			DBfree_result(result);*/
			/* Restore pr */
			c[1]=save;
		}

/*		if(strncmp(pr, "TRIGGER.NAME", strlen("TRIGGER.NAME")) == 0)
		{
			var_len = strlen("TRIGGER.NAME");

			zbx_snprintf(replace_to, sizeof(replace_to), "%s", event->trigger_description);
			substitute_simple_macros(event, action, replace_to, sizeof(replace_to), MACRO_TYPE_TRIGGER_DESCRIPTION);
		}*/
		zbx_strlcat(str_out, replace_to, outlen);
		outlen -= MIN(strlen(replace_to), outlen);
		pl = pr + var_len;
	}
	zbx_strlcat(str_out, pl, outlen);
	outlen -= MIN(strlen(pl), outlen);

	zbx_snprintf(data, data_max_len, "%s", str_out);

	zabbix_log( LOG_LEVEL_DEBUG, "Result expression [%s]",
		data);
}
