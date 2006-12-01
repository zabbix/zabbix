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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>

#include <string.h>

/* Required for getpwuid */
#include <pwd.h>

#include <time.h>

#include "common.h"
#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "dbsync.h"

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
int change_nodeid(zbx_uint64_t old_id, zbx_uint64_t new_id)
{
	int i,j;

	if(old_id!=0)
	{
		printf("Conversion from non-zero node id is not supported.\n");
		return FAIL;
	}

	zabbix_set_log_level(LOG_LEVEL_WARNING);

	DBconnect();

	for(i=0;tables[i].table!=0;i++)
	{
		printf("Converting table %s...\n", tables[i].table);

		j=0;
		while(tables[i].fields[j].name != 0)
		{
			if(tables[i].fields[j].type == ZBX_TYPE_ID)
			{
				DBexecute("update %s set %s=%s+" ZBX_FS_UI64 "\n",
					tables[i].table, tables[i].fields[j].name, tables[i].fields[j].name,
					(zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)new_id);
			}
			j++;
		}
	}
	DBclose();
	printf("Done.\n");

	return SUCCEED;
}
