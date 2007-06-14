/* 
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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

#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "dbsync.h"

/******************************************************************************
 *                                                                            *
 * Function: convert_trigger_expression                                       *
 *                                                                            *
 * Purpose: convert trigger expression to new node ID                         *
 *                                                                            *
 * Parameters: old_id - old id, new_id - new node id                          *
 *             old_exp - old expression, new_exp - new expression             *
 *                                                                            *
 * Return value: SUCCESS - converted succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int convert_trigger_expression(int old_id, int new_id, char *old_exp, char *new_exp)
{
	int	i;
	char	id[MAX_STRING_LEN];
	enum	state_t {NORMAL, ID} state = NORMAL;
	char	*p,
		*p_id = NULL;
	zbx_uint64_t	tmp;

	p = new_exp;

	for(i=0;old_exp[i]!=0;i++)
	{
		if(state == ID)
		{
			if(old_exp[i]=='}')
			{
				state = NORMAL;
				ZBX_STR2UINT64(tmp,id);
				tmp+=(zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)new_id;
				p+=zbx_snprintf(p,MAX_STRING_LEN,ZBX_FS_UI64, tmp);
				p[0] = old_exp[i];
				p++;
			}
			else
			{
				p_id[0] = old_exp[i];
				p_id++;
			}
		}
		else if(old_exp[i]=='{')
		{
			state = ID;
			memset(id,0,MAX_STRING_LEN);
			p_id = id;
			p[0] = old_exp[i];
			p++;
		}
		else
		{
			p[0] = old_exp[i];
			p++;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: change_nodeid                                                    *
 *                                                                            *
 * Purpose: convert database data to new node ID                              *
 *                                                                            *
 * Parameters: old_id - old id, new_id - new node id                          *
 *                                                                            *
 * Return value: SUCCESS - converted succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int change_nodeid(int old_id, int new_id)
{
	int i,j;
	DB_RESULT	result;
	DB_ROW		row;
	char		new_expression[MAX_STRING_LEN];
	char		new_expression_esc[MAX_STRING_LEN];

	if(old_id!=0)
	{
		printf("Conversion from non-zero node id is not supported.\n");
		return FAIL;
	}

	if(new_id>999 || new_id<0)
	{
		printf("Node ID must be in range of 0-999.\n");
		return FAIL;
	}

	zabbix_set_log_level(LOG_LEVEL_WARNING);

	DBconnect(ZBX_DB_CONNECT_EXIT);

	DBbegin();

	printf("Converting tables ");
	fflush(stdout);

	for(i=0;tables[i].table!=0;i++)
	{
		printf(".");
		fflush(stdout);

		j=0;
		while(tables[i].fields[j].name != 0)
		{
			if(tables[i].fields[j].type == ZBX_TYPE_ID)
			{
				DBexecute("update %s set %s=%s+" ZBX_FS_UI64 " where %s>0\n",
					tables[i].table,
					tables[i].fields[j].name,
					tables[i].fields[j].name,
					(zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)new_id,
					tables[i].fields[j].name);
			}
			j++;
		}
	}
/* Special processing for trigger expressions */

	result=DBselect("select expression,triggerid from triggers");
	while((row=DBfetch(result)))
	{
		memset(new_expression, 0, MAX_STRING_LEN);
		convert_trigger_expression(old_id, new_id, row[0], new_expression);
		DBescape_string(new_expression, new_expression_esc,MAX_STRING_LEN);
		DBexecute("update triggers set expression='%s' where triggerid=%s",
				new_expression_esc,
				row[1]);
	}
	DBfree_result(result);

	DBexecute("insert into nodes (nodeid,name,ip,nodetype) values (%d,'Local node','127.0.0.1',1)",
		new_id);
	DBcommit();
	
	DBclose();
	printf(" done.\n\nConversion completed.\n");

	return SUCCEED;
}
