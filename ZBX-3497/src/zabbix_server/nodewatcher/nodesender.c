/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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

#include "nodesender.h"
#include "nodewatcher.h"
#include "nodecomms.h"
#include "../trapper/nodesync.h"

/******************************************************************************
 *                                                                            *
 * Function: calculate_checksums                                              *
 *                                                                            *
 * Purpose: calculate checksums of configuration data                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - calculated successfully                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int calculate_checksums(int nodeid, const char *tablename, const zbx_uint64_t id)
{
	const char	*__function_name = "calculate_checksums";

	char	*sql = NULL;
	int	sql_allocated = 2048, sql_offset;
	int	t, f, res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_allocated);
	sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"delete from node_cksum"
			" where nodeid=%d"
				" and cksumtype=%d",
			nodeid, NODE_CKSUM_TYPE_NEW);

	if (NULL != tablename)
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
				" and tablename='%s'",
				tablename);

	if (0 != id)
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				" and recordid=" ZBX_FS_UI64,
				id);

	if (ZBX_DB_OK > DBexecute("%s", sql))
		res = FAIL;

	for (t = 0; 0 != tables[t].table && SUCCEED == res; t++)
	{
		/* Do not sync some of tables */
		if (0 == (tables[t].flags & ZBX_SYNC))
			continue;

		if (NULL != tablename && 0 != strcmp(tablename, tables[t].table))
			continue;

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 256,
				"insert into node_cksum (nodeid,tablename,recordid,cksumtype,cksum)"
				" select %d,'%s',%s,%d,",
				nodeid, tables[t].table, tables[t].recid, NODE_CKSUM_TYPE_NEW);
#ifdef HAVE_MYSQL
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 16, "concat_ws(',',");
#endif

		for (f = 0; NULL != tables[t].fields[f].name; f++)
		{
			const ZBX_FIELD	*field = &tables[t].fields[f];

			if (0 == (field->flags & ZBX_SYNC))
				continue;

			if (field->flags & ZBX_NOTNULL)
			{
				switch (field->type)
				{
					case ZBX_TYPE_ID:
					case ZBX_TYPE_INT:
					case ZBX_TYPE_UINT:
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 1,
								"%s", field->name);
						break;
					case ZBX_TYPE_FLOAT:
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 20,
								"md5(cast(%s as char))", field->name);
						break;
					default:
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 6,
								"md5(%s)", field->name);
						break;
				}
			}
			else
			{
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 31,
						"case when %s is null then 'NULL'", field->name);

				switch (field->type)
				{
					case ZBX_TYPE_ID:
					case ZBX_TYPE_INT:
					case ZBX_TYPE_UINT:
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 25,
								" else cast(%s as char) end", field->name);
						break;
					case ZBX_TYPE_FLOAT:
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 30,
								" else md5(cast(%s as char)) end", field->name);
						break;
					default:
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 16,
								" else md5(%s) end", field->name);
						break;
				}
			}
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 2, ",");
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "||','||");
#endif
		}

		/* remove last delimiter */
		if (f > 0)
		{
#ifdef HAVE_MYSQL
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 2, ")");
#else
			sql_offset -= 7;
#endif
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 256,
				" from %s where 1=1" DB_NODE,
				tables[t].table, DBnode(tables[t].recid, nodeid));

		if (0 != id)
		{
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
					" and %s=" ZBX_FS_UI64,
					tables[t].recid, id);
		}

		if (ZBX_DB_OK > DBexecute("%s", sql))
			res = FAIL;
	}

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DMcolect_table_data                                              *
 *                                                                            *
 * Purpose: obtain configuration changes to required node                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DMcollect_table_data(int nodeid, int dest_nodetype, const ZBX_TABLE *table,
		char **data, int *data_alloc, int *data_offset)
{
#define ZBX_REC_UPDATED	'1'
#define ZBX_REC_DELETED	'2'
	const char	*__function_name = "DMcolect_table_data";
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;

	char	*hex = NULL, *sql = NULL, sync[129], *s,
		*curr_cksum, *d_curr_cksum, *prev_cksum, *d_prev_cksum;
	int	sql_offset = 0;
	int	hex_allocated = 1024, sql_allocated = 8 * 1024;
	int	f, j, rowlen;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [table:'%s']", __function_name, table->table);

	hex = zbx_malloc(hex, hex_allocated);
	sql = zbx_malloc(sql, sql_allocated);

	result = DBselect(
			/* Find new records */
			"select curr.recordid,prev.cksum,curr.cksum,curr.sync"
			" from node_cksum curr"
				" left join node_cksum prev"
					" on prev.nodeid=curr.nodeid"
						" and prev.tablename=curr.tablename"
						" and prev.recordid=curr.recordid"
						" and prev.cksumtype=%d"
			" where curr.nodeid=%d"
				" and curr.tablename='%s'"
				" and curr.cksumtype=%d"
				" and prev.tablename is null"
			" union all "
			/* Find updated records */
			"select curr.recordid,prev.cksum,curr.cksum,prev.sync"
			" from node_cksum curr,node_cksum prev"
			" where curr.nodeid=prev.nodeid"
				" and curr.tablename=prev.tablename"
				" and curr.recordid=prev.recordid"
				" and curr.nodeid=%d"
				" and curr.tablename='%s'"
				" and curr.cksumtype=%d"
				" and prev.cksumtype=%d"
			" union all "
			/* Find deleted records */
			"select prev.recordid,prev.cksum,curr.cksum,prev.sync"
			" from node_cksum prev"
				" left join node_cksum curr"
					" on curr.nodeid=prev.nodeid"
						" and curr.tablename=prev.tablename"
						" and curr.recordid=prev.recordid"
						" and curr.cksumtype=%d"
			" where prev.nodeid=%d"
				" and prev.tablename='%s'"
				" and prev.cksumtype=%d"
				" and curr.tablename is null",
			NODE_CKSUM_TYPE_OLD, nodeid, table->table, NODE_CKSUM_TYPE_NEW,
			nodeid, table->table, NODE_CKSUM_TYPE_NEW, NODE_CKSUM_TYPE_OLD,
			NODE_CKSUM_TYPE_NEW, nodeid, table->table, NODE_CKSUM_TYPE_OLD);

	while (NULL != (row = DBfetch(result)))
	{
		if (FAIL == DBis_null(row[3]))
			zbx_strlcpy(sync, row[3], sizeof(sync));
		else
			memset(sync, ' ', sizeof(sync));

		/* Special (simpler) processing for operation DELETE */
		if (SUCCEED == DBis_null(row[2]))
		{
			if ((dest_nodetype == ZBX_NODE_SLAVE && sync[0] != ZBX_REC_DELETED) ||
					(dest_nodetype == ZBX_NODE_MASTER && sync[1] != ZBX_REC_DELETED))
			{
				zbx_snprintf_alloc(data, data_alloc, data_offset, 128, "\n%s%c%s%c%d",
						table->table, ZBX_DM_DELIMITER, row[0], ZBX_DM_DELIMITER,
						NODE_CONFIGLOG_OP_DELETE);
			}
			continue;
		}

		prev_cksum = (SUCCEED == DBis_null(row[1]) ? NULL : row[1]);
		curr_cksum = row[2];
		s = sync;
		f = 0;

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "select ");

		do
		{
			while (0 == (table->fields[f].flags & ZBX_SYNC))
				f++;

			d_prev_cksum = NULL;
			if (NULL != prev_cksum && NULL != (d_prev_cksum = strchr(prev_cksum, ',')))
				*d_prev_cksum = '\0';

			d_curr_cksum = NULL;
			if (NULL != curr_cksum && NULL != (d_curr_cksum = strchr(curr_cksum, ',')))
				*d_curr_cksum = '\0';

			if (prev_cksum == NULL || curr_cksum == NULL ||
					(dest_nodetype == ZBX_NODE_SLAVE && s[0] != ZBX_REC_UPDATED) ||
					(dest_nodetype == ZBX_NODE_MASTER && s[1] != ZBX_REC_UPDATED) ||
					0 != strcmp(prev_cksum, curr_cksum))
			{
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "%s,",
						table->fields[f].name);

				if (table->fields[f].type == ZBX_TYPE_BLOB)
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "length(%s),",
							table->fields[f].name);
				}
			}
			s += 2;
			f++;

			if (d_prev_cksum != NULL)
			{
				*d_prev_cksum = ',';
				prev_cksum = d_prev_cksum + 1;
			}
			else
				prev_cksum = NULL;

			if (d_curr_cksum != NULL)
			{
				*d_curr_cksum = ',';
				curr_cksum = d_curr_cksum + 1;
			}
			else
				curr_cksum = NULL;
		}
		while (NULL != d_prev_cksum || NULL != d_curr_cksum);

		if (sql[sql_offset - 1] != ',')
			continue;

		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, " from %s where %s=%s",
				table->table, table->recid, row[0]);

		result2 = DBselect("%s", sql);
		if (NULL == (row2 = DBfetch(result2)))
			goto out;

		zbx_snprintf_alloc(data, data_alloc, data_offset, 128, "\n%s%c%s%c%d",
				table->table, ZBX_DM_DELIMITER, row[0], ZBX_DM_DELIMITER,
				NODE_CONFIGLOG_OP_UPDATE);

		prev_cksum = DBis_null(row[1]) == SUCCEED ? NULL : row[1];
		curr_cksum = row[2];
		s = sync;
		f = 0;
		j = 0;

		do
		{
			while (0 == (table->fields[f].flags & ZBX_SYNC))
				f++;

			d_prev_cksum = NULL;
			if (NULL != prev_cksum && NULL != (d_prev_cksum = strchr(prev_cksum, ',')))
				*d_prev_cksum = '\0';

			d_curr_cksum = NULL;
			if (NULL != curr_cksum && NULL != (d_curr_cksum = strchr(curr_cksum, ',')))
				*d_curr_cksum = '\0';

			if (prev_cksum == NULL || curr_cksum == NULL ||
					(dest_nodetype == ZBX_NODE_SLAVE && s[0] != ZBX_REC_UPDATED) ||
					(dest_nodetype == ZBX_NODE_MASTER && s[1] != ZBX_REC_UPDATED) ||
					0 != strcmp(prev_cksum, curr_cksum))
			{
				zbx_snprintf_alloc(data, data_alloc, data_offset, 128, "%c%s%c%d%c",
						ZBX_DM_DELIMITER, table->fields[f].name,
						ZBX_DM_DELIMITER, table->fields[f].type,
						ZBX_DM_DELIMITER);

				/* Fieldname, type, value */
				if (SUCCEED == DBis_null(row2[j]))
				{
					zbx_snprintf_alloc(data, data_alloc, data_offset, 5, "NULL");
				}
				else if (table->fields[f].type == ZBX_TYPE_INT ||
					table->fields[f].type == ZBX_TYPE_UINT ||
					table->fields[f].type == ZBX_TYPE_ID ||
					table->fields[f].type == ZBX_TYPE_FLOAT)
				{
					zbx_snprintf_alloc(data, data_alloc, data_offset, 128, "%s", row2[j]);
				}
				else
				{
					if (table->fields[f].type == ZBX_TYPE_BLOB)
						rowlen = atoi(row2[j + 1]);
					else
						rowlen = strlen(row2[j]);
					rowlen = zbx_binary2hex((u_char *)row2[j], rowlen, &hex, &hex_allocated);
					zbx_snprintf_alloc(data, data_alloc, data_offset, rowlen + 1, "%s", hex);
				}

				if (table->fields[f].type == ZBX_TYPE_BLOB)
					j += 2;
				else
					j++;
			}
			s += 2;
			f++;

			if (d_prev_cksum != NULL)
			{
				*d_prev_cksum = ',';
				prev_cksum = d_prev_cksum + 1;
			}
			else
				prev_cksum = NULL;

			if (d_curr_cksum != NULL)
			{
				*d_curr_cksum = ',';
				curr_cksum = d_curr_cksum + 1;
			}
			else
				curr_cksum = NULL;
		}
		while (NULL != d_prev_cksum || NULL != d_curr_cksum);
out:
		DBfree_result(result2);
	}
	DBfree_result(result);

	zbx_free(hex);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DMget_table_data                                                 *
 *                                                                            *
 * Purpose: get configuration changes to required node for specified table    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DMget_table_data(int nodeid, int dest_nodetype, const ZBX_TABLE *table,
		char **data, int *data_alloc, int *data_offset,
		char **ptbls, int *ptbls_alloc, int *ptbls_offset)
{
	const char	*__function_name = "DMget_table_data";

	int		f, res = SUCCEED;
	const ZBX_TABLE	*fk_table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [table:'%s']",
			__function_name, table->table);

	if (SUCCEED == str_in_list(*ptbls, table->table, ','))
		return SUCCEED;

	zbx_snprintf_alloc(ptbls, ptbls_alloc, ptbls_offset, ZBX_TABLENAME_LEN + 2, "%s%s",
			*ptbls_offset ? "," : "", table->table);

	for (f = 0; NULL != table->fields[f].name; f++)
	{
		if (0 == (table->fields[f].flags & ZBX_SYNC))
			continue;

		if (NULL == table->fields[f].fk_table)
			continue;

		fk_table = DBget_table(table->fields[f].fk_table);
		DMget_table_data(nodeid, dest_nodetype, fk_table,
				data, data_alloc, data_offset,
				ptbls, ptbls_alloc, ptbls_offset);
	}
	DMcollect_table_data(nodeid, dest_nodetype, table,
			data, data_alloc, data_offset);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DMget_config_data                                                *
 *                                                                            *
 * Purpose: get configuration changes to required node                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
char	*DMget_config_data(int nodeid, int dest_nodetype)
{
	const char	*__function_name = "DMget_config_data";

	char		*ptbls = NULL;	/* list of processed tables */
	int		ptbls_alloc = 1024, ptbls_offset = 0;
	char		*data = NULL;
	int		data_alloc = 1024, data_offset = 0;
	int		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [node:%d] [dest_nodetype:%s]", __function_name,
			nodeid, zbx_nodetype_string(dest_nodetype));

	ptbls = zbx_malloc(ptbls, ptbls_alloc);
	data = zbx_malloc(data, data_alloc);

	zbx_snprintf_alloc(&data, &data_alloc, &data_offset, 16, "Data%c%d%c%d",
			ZBX_DM_DELIMITER, CONFIG_NODEID, ZBX_DM_DELIMITER, nodeid);

	for (t = 0; NULL != tables[t].table; t++)
	{
		if (0 == (tables[t].flags & ZBX_SYNC))
			continue;

		DMget_table_data(nodeid, dest_nodetype, &tables[t],
				&data, &data_alloc, &data_offset,
				&ptbls, &ptbls_alloc, &ptbls_offset);
	}

	zbx_free(ptbls);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: update_checksums                                                 *
 *                                                                            *
 * Purpose: overwrite old checksums with new ones                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - calculated successfully                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int update_checksums(int nodeid, int synked_nodetype, int synked, const char *tablename, const zbx_uint64_t id, char *fields)
{
	const char	*__function_name = "update_checksums";
	char		*r[2], *d[2], sync[129], *s;
	char		c[2], sql[2][256];
	char		cksum[32*64+32], *ck;
	char		*exsql = NULL;
	int		exsql_alloc = 65536, exsql_offset = 0, cksumtype;
	DB_RESULT	result;
	DB_ROW		row;
	int		f;
	const ZBX_TABLE	*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	exsql = zbx_malloc(exsql, exsql_alloc);

	DBbegin();

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&exsql, &exsql_alloc, &exsql_offset, 8, "begin\n");
#endif

	c[0] = synked == SUCCEED ? '1' : ' ';	/* for new and updated records */
	c[1] = synked == SUCCEED ? '2' : ' ';	/* for deleted records */

	if (NULL != tablename) {
		zbx_snprintf(sql[0], sizeof(sql[0]), " and curr.tablename='%s' and curr.recordid=" ZBX_FS_UI64,
			tablename, id);
		zbx_snprintf(sql[1], sizeof(sql[1]), " and prev.tablename='%s' and prev.recordid=" ZBX_FS_UI64,
			tablename, id);
	} else {
		*sql[0] = '\0';
		*sql[1] = '\0';
	}

	result = DBselect(
			/* Find new records */
			"select curr.tablename,curr.recordid,prev.cksum,curr.cksum,NULL"
			" from node_cksum curr"
				" left join node_cksum prev"
					" on prev.nodeid=curr.nodeid"
						" and prev.tablename=curr.tablename"
						" and prev.recordid=curr.recordid"
						" and prev.cksumtype=%d"
			" where curr.nodeid=%d"
				" and curr.cksumtype=%d"
				" and prev.tablename is null%s"
			" union all "
			/* Find updated records */
			"select curr.tablename,curr.recordid,prev.cksum,curr.cksum,prev.sync"
			" from node_cksum curr, node_cksum prev"
			" where curr.nodeid=%d"
				" and prev.nodeid=curr.nodeid"
				" and curr.tablename=prev.tablename"
				" and curr.recordid=prev.recordid"
				" and curr.cksumtype=%d"
				" and prev.cksumtype=%d%s"
			" union all "
			/* Find deleted records */
			"select prev.tablename,prev.recordid,prev.cksum,curr.cksum,prev.sync"
			" from node_cksum prev"
				" left join node_cksum curr"
					" on curr.nodeid=prev.nodeid"
						" and curr.tablename=prev.tablename"
						" and curr.recordid=prev.recordid"
						" and curr.cksumtype=%d"
			" where prev.nodeid=%d"
				" and prev.cksumtype=%d"
				" and curr.tablename is null%s",
			NODE_CKSUM_TYPE_OLD, nodeid, NODE_CKSUM_TYPE_NEW, sql[0],
			nodeid, NODE_CKSUM_TYPE_NEW, NODE_CKSUM_TYPE_OLD, sql[0],
			NODE_CKSUM_TYPE_NEW, nodeid, NODE_CKSUM_TYPE_OLD, sql[1]);

	while (NULL != (row = DBfetch(result)))
	{
		/* Found table */
		if (NULL == (table = DBget_table(row[0])))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot find table [%s]",
					row[0]);
			continue;
		}

		if (FAIL == DBis_null(row[4]))
			zbx_strlcpy(sync, row[4], sizeof(sync));
		else
			memset(sync, ' ', sizeof(sync));

		s = sync;
		ck = cksum;
		*ck = '\0';

		/* Special (simpler) processing for operation DELETE */
		if (SUCCEED == DBis_null(row[3]))
		{
			if (synked == SUCCEED)
			{
				if (synked_nodetype == ZBX_NODE_SLAVE)
					s[0] = c[1];
				else if (synked_nodetype == ZBX_NODE_MASTER)
					s[1] = c[1];
			}

			if ((0 == CONFIG_MASTER_NODEID || s[1] == c[1]) &&
					(CONFIG_NODEID == nodeid || s[0] == c[1]))
			{
				zbx_snprintf_alloc(&exsql, &exsql_alloc, &exsql_offset, 256,
						"delete from node_cksum"
						" where nodeid=%d"
							" and cksumtype=%d"
							" and tablename='%s'"
							" and recordid=%s;\n",
						nodeid, NODE_CKSUM_TYPE_OLD, row[0], row[1]);

				DBexecute_overflowed_sql(&exsql, &exsql_alloc, &exsql_offset);
				continue;
			}

			s += 2;
		}
		else
		{
			r[0] = DBis_null(row[2]) == SUCCEED ? NULL : row[2];
			r[1] = row[3];
			f = 0;

			do {
				while ((table->fields[f].flags & ZBX_SYNC) == 0)
					f++;

				d[0] = NULL;
				d[1] = NULL;
				if (NULL != r[0] && NULL != (d[0] = strchr(r[0], ',')))
					*d[0] = '\0';
				if (NULL != r[1] && NULL != (d[1] = strchr(r[1], ',')))
					*d[1] = '\0';

				if (NULL == tablename || SUCCEED == str_in_list(fields, table->fields[f].name, ','))
				{
					ck += zbx_snprintf(ck, 64, "%s,", NULL != r[1] ? r[1] : r[0]);

					if (r[0] == NULL || r[1] == NULL || strcmp(r[0], r[1]) != 0)
					{
						if (synked_nodetype == ZBX_NODE_SLAVE)
						{
							s[0] = c[0];
							s[1] = ' ';
						}
						else if (synked_nodetype == ZBX_NODE_MASTER)
						{
							s[0] = ' ';
							s[1] = c[0];
						}
					}
					else
					{
						if (synked == SUCCEED)
						{
							if (synked_nodetype == ZBX_NODE_SLAVE)
								s[0] = c[0];
							else if (synked_nodetype == ZBX_NODE_MASTER)
								s[1] = c[0];
						}
					}
				}
				else
					ck += zbx_snprintf(ck, 64, "%s,", NULL != r[0] ? r[0] : "");
				s += 2;
				f++;

				if (d[0] != NULL)
				{
					*d[0] = ',';
					r[0] = d[0] + 1;
				}
				else
					r[0] = NULL;

				if (d[1] != NULL)
				{
					*d[1] = ',';
					r[1] = d[1] + 1;
				}
				else
					r[1] = NULL;
			} while (d[0] != NULL || d[1] != NULL);

			*--ck = '\0';
		}

		*s = '\0';

		if (SUCCEED == DBis_null(row[2]) || SUCCEED == DBis_null(row[3]) ||
				0 != strcmp(row[4], sync) || 0 != strcmp(row[2], row[3]))
		{
			cksumtype = (DBis_null(row[2]) == SUCCEED) ? NODE_CKSUM_TYPE_NEW : NODE_CKSUM_TYPE_OLD;
			zbx_snprintf_alloc(&exsql, &exsql_alloc, &exsql_offset, 2560,
					"update node_cksum"
					" set cksumtype=%d,"
						"cksum='%s',"
						"sync='%s'"
					" where nodeid=%d"
						" and cksumtype=%d"
						" and tablename='%s'"
						" and recordid=%s;\n",
					NODE_CKSUM_TYPE_OLD, cksum, sync,
					nodeid, cksumtype, row[0], row[1]);

			DBexecute_overflowed_sql(&exsql, &exsql_alloc, &exsql_offset);
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&exsql, &exsql_alloc, &exsql_offset, 8, "end;\n");
#endif

	if (exsql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", exsql);
	zbx_free(exsql);

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: node_sync_lock                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void node_sync_lock(int nodeid)
{
	zbx_mutex_lock(&node_sync_access);
}

/******************************************************************************
 *                                                                            *
 * Function: node_sync_unlock                                                 *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void node_sync_unlock(int nodeid)
{
	zbx_mutex_unlock(&node_sync_access);
}

/******************************************************************************
 *                                                                            *
 * Function: process_nodes                                                    *
 *                                                                            *
 * Purpose: calculates checksums of config data                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void process_nodes()
{
	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid;
	int		master_nodeid;
	char		*data, *answer;
	zbx_sock_t	sock;
	int		res;
	int		sender_nodeid;

	master_nodeid = CONFIG_MASTER_NODEID;
	if (0 == master_nodeid)
		return;

	result = DBselect("select nodeid from nodes");
	while (NULL != (row=DBfetch(result))) {
		nodeid = atoi(row[0]);
		if (SUCCEED == is_master_node(CONFIG_NODEID, nodeid))
			continue;

		node_sync_lock(nodeid);

/*		DBbegin();*/

		res = calculate_checksums(nodeid, NULL, 0);
		if (SUCCEED == res && NULL != (data = DMget_config_data(nodeid, ZBX_NODE_MASTER))) {
			zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Sending configuration changes to master node %d for node %d datalen %d",
				CONFIG_NODEID,
				master_nodeid,
				nodeid,
				strlen(data));
			if (SUCCEED == (res = connect_to_node(master_nodeid, &sock))) {
				if (SUCCEED == res)
					res = send_data_to_node(master_nodeid, &sock, data);
				if (SUCCEED == res)
					res = recv_data_from_node(master_nodeid, &sock, &answer);
				if (SUCCEED == res && 0 == strncmp(answer, "Data", 4)) {
					res = update_checksums(nodeid, ZBX_NODE_MASTER, SUCCEED, NULL, 0, NULL);
					if (SUCCEED == res)
						res = node_sync(answer, &sender_nodeid, &nodeid);
					send_data_to_node(master_nodeid, &sock, SUCCEED == res ? "OK" : "FAIL");
				}
				disconnect_node(&sock);
			}
			zbx_free(data);
		}

/*		DBcommit();*/

		node_sync_unlock(nodeid);
	}
	DBfree_result(result);
}
