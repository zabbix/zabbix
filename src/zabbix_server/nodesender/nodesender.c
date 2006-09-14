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
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <sys/wait.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include "common.h"
#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "dbsync.h"
#include "nodesender.h"

#define	ZBX_NODE_MASTER	0
#define	ZBX_NODE_SLAVE	1

extern	int	CONFIG_NODEID;

/******************************************************************************
 *                                                                            *
 * Function: send_to_node                                                     *
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
static int send_to_node(int dest_nodeid, int nodeid, char *xml)
{
	int	i,s;
	char	answer[MAX_STRING_LEN];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;
	char	ip[MAX_STRING_LEN];
	int	port;
	int	ret = FAIL;
	int	written;

	DB_RESULT	result;
	DB_ROW		row;

//	zabbix_log( LOG_LEVEL_WARNING, "In send_to_node (%d,%d)", nodeid, strlen(xml));
//	zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Sending data of node %d to node %d", CONFIG_NODEID, nodeid, dest_nodeid);

	result = DBselect("select ip, port from nodes where nodeid=%d", dest_nodeid);
	row = DBfetch(result);
	if(!row)
	{
		DBfree_result(result);
		zabbix_log( LOG_LEVEL_WARNING, "Node [%d] in unknown", dest_nodeid);
		return FAIL;
	}
	strncpy(ip,row[0],sizeof(ip));
	port=atoi(row[1]);
	DBfree_result(result);

//	zabbix_log( LOG_LEVEL_WARNING, "In send_to_node IP (%s:%d)", ip, port);
	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(ip);

	if(hp==NULL)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot resolve [%s] for node [%d]", ip, dest_nodeid);
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot create socket [%s] for node [%d]", ip, dest_nodeid);
		return	FAIL;
	}

	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot connect [%s] to node [%d]", ip, dest_nodeid);
		close(s);
		return	FAIL;
	}

	written = 0;

	while(written<strlen(xml))
	{
		i=write(s, xml+written,strlen(xml)-written);
		if(i == -1)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Error writing to node [%d] [%s]", dest_nodeid, strerror(errno));
			close(s);
			return	FAIL;
		}
		written+=i;
//		zabbix_log( LOG_LEVEL_WARNING, "Wrote %d of %d bytes to node %d", written, strlen(xml), nodeid);
	}
	i=sizeof(struct sockaddr_in);
/*	i=recvfrom(s,result,MAX_STRING_LEN-1,0,(struct sockaddr *)&servaddr_in,(socklen_t *)&i);*/
	i=read(s,answer,MAX_STRING_LEN-1);
	if(i==-1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error reading from node [%d]", dest_nodeid);
		close(s);
		return	FAIL;
	}

	answer[i-1]=0;

	if(strcmp(answer,"OK") == 0)
	{
		ret = SUCCEED;
	}
 
	if( close(s)!=0 )
	{
	}

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
static int send_config_data(int nodeid, int dest_nodeid, int maxlogid, int node_type)
{
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;

#define	ZBX_XML_MAX	161024*1024
	char	*xml;
	char	tmp[MAX_STRING_LEN];
	char	fields[MAX_STRING_LEN];

	int	i,j;

	xml=malloc(ZBX_XML_MAX);

	xml[0]=0;

//	zabbix_log( LOG_LEVEL_WARNING, "In send_config_data(local:%d,nodeid:%d,dest_node:%d,maxlogid:%d,type:%d)",local_nodeid, nodeid, dest_nodeid,maxlogid,node_type);

	/* Begin work */
	if(node_type == ZBX_NODE_MASTER)
	{
		result=DBselect("select tablename,recordid,operation from node_configlog where nodeid=%d and sync_master=0 and conflogid<=%d order by tablename,operation", nodeid, maxlogid);
	}
	else
	{
		result=DBselect("select tablename,recordid,operation from node_configlog where nodeid=%d and sync_slave=0 and conflogid<=%d order by tablename,operation", nodeid, maxlogid);
	}

//	snprintf(tmp,sizeof(tmp),"<Data type='config'>\n<Node id='%d'>\n</Node>\n<Version>1.4</Version>\n<Records>\n", nodeid);
	snprintf(tmp,sizeof(tmp),"Data|%d|%d\n", CONFIG_NODEID, nodeid);
	strncat(xml,tmp,ZBX_XML_MAX);

	while((row=DBfetch(result)))
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Fetched [%s,%s,%s]",row[0],row[1],row[2]);
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
				strncat(fields,tables[i].fields[j].name,sizeof(fields));
				strncat(fields,",",sizeof(fields));
			}
			if(fields[0]!=0)	fields[strlen(fields)-1]=0;

			result2=DBselect("select %s from %s where %s=%s", fields, row[0], tables[i].recid,row[1]);
//			zabbix_log( LOG_LEVEL_WARNING,"select %s from %s where %s=%s",fields, row[0], tables[i].recid,row[1]);
			row2=DBfetch(result2);

			if(row2)
			{
				snprintf(tmp,sizeof(tmp),"%s|%s|%s",
					row[0], row[1], row[2]);
//					zabbix_log( LOG_LEVEL_WARNING, "TMP [%s]",tmp);
				strncat(xml,tmp,ZBX_XML_MAX);
				/* for each field */
				for(j=0;tables[i].fields[j].name!=0;j++)
				{
//					snprintf(tmp,sizeof(tmp),"<Record table='%s' field='%s' op='%s' recid='%s'>%s<Record>\n",
//						row[0], tables[i].fields[j].name, row[2], row[1], row2[j]);
//					// Fieldname, type, value
					if(DBis_null(row2[j]) == SUCCEED)
					{
//						zabbix_log( LOG_LEVEL_WARNING, "Field name [%s] [%s]",tables[i].fields[j].name,row2[j]);
//						if(strcmp("snmpv3_securityname",tables[i].fields[j].name)==0)
//						{
//							zabbix_log( LOG_LEVEL_WARNING, "snmpv3_securityname [%s]",row2[j]);
//						}
						snprintf(tmp,sizeof(tmp),"|%s|%d|NULL",
							tables[i].fields[j].name,tables[i].fields[j].type);
					}
					else
					{
						snprintf(tmp,sizeof(tmp),"|%s|%d|%s",
							tables[i].fields[j].name,tables[i].fields[j].type,row2[j]);
//						if(strcmp("snmpv3_securityname",tables[i].fields[j].name)==0)
//						{
//							zabbix_log( LOG_LEVEL_WARNING, "snmpv3_securityname 2[%s]",row2[j]);
//						}
					}
//					zabbix_log( LOG_LEVEL_WARNING, "TMP [%s]",tmp);
					strncat(xml,tmp,ZBX_XML_MAX);
				}
				strncat(xml,"\n",ZBX_XML_MAX);

			}
			else
			{
				zabbix_log( LOG_LEVEL_WARNING, "Cannot select %s from table [%s]",tables[i].fields[j],row[0]);
			}
			DBfree_result(result2);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot find table [%s]",row[0]);
		}
	}
//	snprintf(tmp,sizeof(tmp),"</Records></Data>\n");
//	strncat(xml,tmp,ZBX_XML_MAX);
//
//	zabbix_log( LOG_LEVEL_WARNING, "DATA [%s]",xml);
	if(send_to_node(dest_nodeid, nodeid, xml) == SUCCEED)
	{
		if(node_type == ZBX_NODE_MASTER)
		{
			DBexecute("update node_configlog set sync_master=1 where nodeid=%d and sync_master=0 and conflogid<=%d", nodeid, maxlogid);
		}
		else
		{
			DBexecute("update node_configlog set sync_slave=1 where nodeid=%d and sync_slave=0 and conflogid<=%d", nodeid, maxlogid);
		}
	}

	DBfree_result(result);
	free(xml);
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

//	zabbix_log( LOG_LEVEL_WARNING, "In get_slave_node(%d,%d)",local_nodeid, nodeid);
	/* Begin work */

	result = DBselect("select masterid from nodes where nodeid=%d", nodeid);
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
static int get_master_node(int nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = 0;

//	zabbix_log( LOG_LEVEL_WARNING, "In get_master_node(%d,%d)",local_nodeid, nodeid);
	/* Begin work */

	result = DBselect("select masterid from nodes where nodeid=%d", CONFIG_NODEID);
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
	int		master_nodeid, slave_nodeid;
	int		master_result, slave_result;
	int		maxlogid;

//	zabbix_log( LOG_LEVEL_WARNING, "In send_to_master_and_slave(local:%d,node:%d)",local_nodeid, nodeid);
	/* Begin work */

	result = DBselect("select min(conflogid),max(conflogid) from node_configlog where nodeid=%d", nodeid);

	row = DBfetch(result);

	if(DBis_null(row[0]) == SUCCEED)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "No configuration changes of node %d on node %d", nodeid, local_nodeid);
		DBfree_result(result);
		return SUCCEED;
	}
	if(atoi(row[1])-atoi(row[0])>200)
	{
		maxlogid = atoi(row[0])+200;
	}
	else
	{
		maxlogid = atoi(row[1]);
	}
	DBfree_result(result);


	/* Send data to master and slave if required */
//	zabbix_log( LOG_LEVEL_WARNING, "Node [%d]", nodeid);
//	zabbix_log( LOG_LEVEL_WARNING, "Local node [%d]", local_nodeid);
	master_nodeid=get_master_node(nodeid);
//	zabbix_log( LOG_LEVEL_WARNING, "Master node [%d]", master_nodeid);
	slave_nodeid=get_slave_node(nodeid);
//	zabbix_log( LOG_LEVEL_WARNING, "Slave node [%d]", slave_nodeid);

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
			DBexecute("delete from node_configlog where nodeid=%d and sync_slave=1 and sync_master=1 and conflogid<=%d", nodeid, maxlogid);
//			zabbix_log(LOG_LEVEL_WARNING,"delete from node_configlog where nodeid=%d and sync_slave=1 and sync_master=1 and conflogid<=%d", nodeid, maxlogid);
		}
	}

	if(master_nodeid!=0)
	{
		if(master_result == SUCCEED)
		{
			DBexecute("delete from node_configlog where nodeid=%d and sync_master=1 and conflogid<=%d", nodeid, maxlogid);
//			zabbix_log(LOG_LEVEL_WARNING,"delete from node_configlog where nodeid=%d and sync_master=1 and conflogid<=%d", nodeid, maxlogid);
		}
	}

	if(slave_nodeid!=0)
	{
		if(slave_result == SUCCEED)
		{
			DBexecute("delete from node_configlog where nodeid=%d and sync_slave=1 and conflogid<=%d", nodeid, maxlogid);
//			zabbix_log(LOG_LEVEL_WARNING,"delete from node_configlog where nodeid=%d and sync_slave=1 and conflogid<=%d", nodeid, maxlogid);
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

//	zabbix_log( LOG_LEVEL_WARNING, "In process_node(local:%d,node:%d)",local_nodeid, nodeid);
	/* Begin work */

	send_to_master_and_slave(nodeid);

	result = DBselect("select nodeid from nodes where masterid=%d", nodeid);
	while((row=DBfetch(result)))
	{
		process_node(atoi(row[0]));
	}
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: main_nodesender_loop                                             *
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
int main_nodesender_loop()
{
	DB_RESULT	result;
	DB_ROW		row;

//	zabbix_log( LOG_LEVEL_WARNING, "In main_nodesender_loop()");
	for(;;)
	{

		zbx_setproctitle("connecting to the database");

		DBconnect();

		result = DBselect("select nodeid from nodes where nodetype=%d",NODE_TYPE_LOCAL);

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

		DBclose();

		zbx_setproctitle("node sender [sleeping for %d seconds]", 30);

		sleep(30);
	}
}
