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
#include "nodecomms.h"
#include "nodesender.h"

#define	ZBX_NODE_MASTER	0
#define	ZBX_NODE_SLAVE	1

/******************************************************************************
 *                                                                            *
 * Function: send_config_data                                                 *
 *                                                                            *
 * Purpose: send configuration changes to required node                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int send_config_data(int nodeid, int dest_nodeid, zbx_uint64_t maxlogid, int node_type)
{
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;

	char	*xml = NULL, *hex = NULL;
	char	fields[MAX_STRING_LEN];
	int	offset=0;
	int	allocated=1024;

	int	found=0;

	int	i, j, hex_allocated=1024, rowlen;

	xml=zbx_malloc(xml, allocated);
	hex=zbx_malloc(hex, hex_allocated);

	memset(xml,0,allocated);


	zabbix_log( LOG_LEVEL_DEBUG, "In send_config_data(nodeid:%d,dest_node:%d,maxlogid:" ZBX_FS_UI64 ",type:%d)",
		nodeid,
		dest_nodeid,
		maxlogid,
		node_type);

	/* Begin work */
	if(node_type == ZBX_NODE_MASTER)
	{
		result=DBselect("select tablename,recordid,operation from node_configlog where nodeid=%d and sync_master=0 and conflogid<=" ZBX_FS_UI64 " order by tablename,operation",
			nodeid,
			maxlogid);
	}
	else
	{
		result=DBselect("select tablename,recordid,operation from node_configlog where nodeid=%d and sync_slave=0 and conflogid<=" ZBX_FS_UI64 " order by tablename,operation",
			nodeid,
			maxlogid);
	}

	zbx_snprintf_alloc(&xml, &allocated, &offset, 128, "Data%c%d%c%d",
		ZBX_DM_DELIMITER,
		CONFIG_NODEID,
		ZBX_DM_DELIMITER,
		nodeid);

	while((row=DBfetch(result)))
	{
		found = 1;

		zabbix_log( LOG_LEVEL_DEBUG, "Fetched [%s,%s,%s]",row[0],row[1],row[2]);
		/* Special (simpler) processing for operation DELETE */
		if(atoi(row[2]) == NODE_CONFIGLOG_OP_DELETE)
		{
			zbx_snprintf_alloc(&xml, &allocated, &offset, 16*1024, "\n%s%c%s%c%s",
				row[0],
				ZBX_DM_DELIMITER,
				row[1],
				ZBX_DM_DELIMITER,
				row[2]);
				continue;
		}
		for(i=0;tables[i].table!=0;i++)
		{
			if(strcmp(tables[i].table, row[0])==0)	break;
		}

		/* Found table */
		if(tables[i].table!=0)
		{
			fields[0]=0;
			/* for each field */
			for(j=0;tables[i].fields[j].name!=0;j++)
			{
				zbx_strlcat(fields,tables[i].fields[j].name,sizeof(fields));
				zbx_strlcat(fields,",",sizeof(fields));

				zbx_strlcat(fields,"length(",sizeof(fields));
				zbx_strlcat(fields,tables[i].fields[j].name,sizeof(fields));
				zbx_strlcat(fields,"),",sizeof(fields));
			}
			if(fields[0]!=0)	fields[strlen(fields)-1]=0;

			result2=DBselect("select %s from %s where %s=%s",
				fields,
				row[0],
				tables[i].recid,
				row[1]);
 
			row2=DBfetch(result2);

			if(row2)
			{
				zbx_snprintf_alloc(&xml, &allocated, &offset, 16*1024, "\n%s%c%s%c%s",
					row[0],
					ZBX_DM_DELIMITER,
					row[1],
					ZBX_DM_DELIMITER,
					row[2]);
				/* for each field */
				for(j=0;tables[i].fields[j].name!=0;j++)
				{
					if( (tables[i].fields[j].flags & ZBX_SYNC) ==0)	continue;
					/* Fieldname, type, value */
					if(DBis_null(row2[j*2]) == SUCCEED)
					{
/*						zabbix_log( LOG_LEVEL_WARNING, "Field name [%s] [%s]",tables[i].fields[j].name,row2[j*2]);*/
						zbx_snprintf_alloc(&xml, &allocated, &offset, 16*1024, "%c%s%c%d%cNULL",
							ZBX_DM_DELIMITER,
							tables[i].fields[j].name,
							ZBX_DM_DELIMITER,
							tables[i].fields[j].type,
							ZBX_DM_DELIMITER);
					}
					else
					{
						if(tables[i].fields[j].type == ZBX_TYPE_INT ||
						   tables[i].fields[j].type == ZBX_TYPE_UINT ||
						   tables[i].fields[j].type == ZBX_TYPE_ID ||
						   tables[i].fields[j].type == ZBX_TYPE_FLOAT)
						{
							zbx_snprintf_alloc(&xml, &allocated, &offset, 16*1024, "%c%s%c%d%c%s",
								ZBX_DM_DELIMITER,
								tables[i].fields[j].name,
								ZBX_DM_DELIMITER,
								tables[i].fields[j].type,
								ZBX_DM_DELIMITER,
								row2[j*2]);
						}
						else
						{
							rowlen = atoi(row2[j*2+1]);
							zbx_binary2hex((u_char *)row2[j*2], rowlen, &hex, &hex_allocated);
							zbx_snprintf_alloc(&xml, &allocated, &offset, 16*1024, "%c%s%c%d%c%s",
								ZBX_DM_DELIMITER,
								tables[i].fields[j].name,
								ZBX_DM_DELIMITER,
								tables[i].fields[j].type,
								ZBX_DM_DELIMITER,
								hex);
						}
					}
				}
			}
			else
			{
				/* We assume that the record was just deleted, so we change operation to DELETE */
				zabbix_log( LOG_LEVEL_DEBUG, "Cannot select %s from table %s where %s=%s",
					fields,
					row[0],
					tables[i].recid,
					row[1]);

				zbx_snprintf_alloc(&xml, &allocated, &offset, 16*1024, "\n%s%c%s%c%d",
					row[0],
					ZBX_DM_DELIMITER,
					row[1],
					ZBX_DM_DELIMITER,
					NODE_CONFIGLOG_OP_DELETE);
			}
			DBfree_result(result2);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot find table [%s]",
				row[0]);
		}
	}
	zabbix_log( LOG_LEVEL_DEBUG, "DATA [%s]",
		xml);
	if( (found == 1) && send_to_node("configuration changes", dest_nodeid, nodeid, xml) == SUCCEED)
	{
		if(node_type == ZBX_NODE_MASTER)
		{
			DBexecute("update node_configlog set sync_master=1 where nodeid=%d and sync_master=0 and conflogid<=" ZBX_FS_UI64,
				nodeid,
				maxlogid);
		}
		else
		{
			DBexecute("update node_configlog set sync_slave=1 where nodeid=%d and sync_slave=0 and conflogid<=" ZBX_FS_UI64,
				nodeid,
				maxlogid);
		}
	}

	DBfree_result(result);
	zbx_free(xml);
	zbx_free(hex);
	/* Commit */

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_slave_node                                                   *
 *                                                                            *
 * Purpose: send configuration changes to required node                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int get_slave_node(int nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = 0;
	int		m;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_slave_node(%d)",
		nodeid);

	result = DBselect("select masterid from nodes where nodeid=%d",
		nodeid);
	row = DBfetch(result);
	if(row)
	{
		m = atoi(row[0]);
		if(m == CONFIG_NODEID)
		{
			ret = nodeid;
		}
		else if(m ==0)
		{
			ret = m;
		}
		else	ret = get_slave_node(m);
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_master_node                                                  *
 *                                                                            *
 * Purpose: send configuration changes to required node                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int get_master_node(int nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_master_node(%d)",
		nodeid);

	result = DBselect("select masterid from nodes where nodeid=%d",
		CONFIG_NODEID);
	row = DBfetch(result);
	if(row)
	{
		ret = atoi(row[0]);
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_config_data                                                 *
 *                                                                            *
 * Purpose: send configuration changes to required node                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int send_to_master_and_slave(int nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		master_nodeid,
			slave_nodeid,
			master_result = FAIL,
			slave_result = FAIL;
	zbx_uint64_t	maxlogid;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_to_master_and_slave(node:%d)",
		nodeid);

	result = DBselect("select max(conflogid) from node_configlog where nodeid=%d",
		nodeid);

	row = DBfetch(result);

	if(row && DBis_null(row[0]) == SUCCEED)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No configuration changes of node %d",
			nodeid);
		DBfree_result(result);
		return SUCCEED;
	}
	ZBX_STR2UINT64(maxlogid,row[0]);
	DBfree_result(result);


	master_nodeid=get_master_node(nodeid);
	slave_nodeid=get_slave_node(nodeid);

	if(master_nodeid != 0)
	{
		master_result = send_config_data(nodeid, master_nodeid, maxlogid, ZBX_NODE_MASTER);
	}

	if(slave_nodeid != 0)
	{
		slave_result = send_config_data(nodeid, slave_nodeid, maxlogid, ZBX_NODE_SLAVE);
	}

	if( (master_nodeid!=0) && (slave_nodeid != 0))
	{
		if((master_result == SUCCEED) && (slave_result == SUCCEED))
		{
			DBexecute("delete from node_configlog where nodeid=%d and sync_slave=1 and sync_master=1 and conflogid<=" ZBX_FS_UI64,
				nodeid,
				maxlogid);
		}
	}

	if(master_nodeid!=0)
	{
		if(master_result == SUCCEED)
		{
			DBexecute("delete from node_configlog where nodeid=%d and sync_master=1 and conflogid<=" ZBX_FS_UI64,
				nodeid,
				maxlogid);
		}
	}

	if(slave_nodeid!=0)
	{
		if(slave_result == SUCCEED)
		{
			DBexecute("delete from node_configlog where nodeid=%d and sync_slave=1 and conflogid<=" ZBX_FS_UI64,
				nodeid,
				maxlogid);
		}
	}

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: process_node                                                     *
 *                                                                            *
 * Purpose: select all related nodes and send config changes                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int process_node(int nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_node(node:%d)",
		nodeid);

	send_to_master_and_slave(nodeid);

	result = DBselect("select nodeid from nodes where masterid=%d and nodeid not in (%d)",
		nodeid,
		nodeid);
	while((row=DBfetch(result)))
	{
		process_node(atoi(row[0]));
	}
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: main_nodesender                                                  *
 *                                                                            *
 * Purpose: periodically sends config changes and history to related nodes    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void main_nodesender()
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log( LOG_LEVEL_DEBUG, "In main_nodesender()");

	result = DBselect("select nodeid from nodes where nodetype=%d",
		ZBX_NODE_TYPE_LOCAL);

	row = DBfetch(result);

	if(row)
	{
		if(CONFIG_NODEID != atoi(row[0]))
		{
			zabbix_log( LOG_LEVEL_WARNING, "NodeID does not match configuration settings. Processing of the node is disabled.");
		}
		else
		{
			process_node(atoi(row[0]));
		}
	}

	DBfree_result(result);
}
