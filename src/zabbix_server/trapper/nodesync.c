/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "nodesync.h"
#include "../nodewatcher/nodesender.h"
#include "../nodewatcher/nodewatcher.h"

static char	*buf = NULL, *tmp = NULL;
static size_t	buf_alloc = 128, tmp_alloc = 16;

/******************************************************************************
 *                                                                            *
 * Function: process_deleted_records                                          *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_deleted_records(int nodeid, char *data)
{
	const char	*__function_name = "process_deleted_records";

typedef struct
{
	const ZBX_TABLE		*table;
	zbx_vector_uint64_t	recids;
	void			*next;
}
zbx_records_t;

	char		*r, *lf;
	zbx_uint64_t	recid;
	char		*sql = NULL;
	size_t		sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_records_t	*first = NULL, *rec;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (r = data; '\0' != *r;)
	{
		if (NULL != (lf = strchr(r, '\n')))
			*lf = '\0';

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* table name */

		if (NULL == first || 0 != strcmp(first->table->table, buf))
		{
			rec = zbx_malloc(NULL, sizeof(zbx_records_t));

			if (NULL == (rec->table = DBget_table(buf)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find table [%s]", __function_name, buf);
				zbx_free(rec);
				goto next;
			}

			zbx_vector_uint64_create(&rec->recids);
			rec->next = first;
			first = rec;
		}

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* record id */
		ZBX_STR2UINT64(recid, buf);

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* operation type */

		if ('2' == *buf)	/* deleted record */
			zbx_vector_uint64_append(&rec->recids, recid);
next:
		if (lf != NULL)
		{
			*lf++ = '\n';
			r = lf;
		}
		else
			break;
	}

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (rec = first; NULL != rec; rec = rec->next)
	{
		if (0 == rec->recids.values_num)
			continue;

		zbx_vector_uint64_sort(&rec->recids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where", rec->table->table);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, rec->table->recid,
				rec->recids.values, rec->recids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	while (NULL != first)
	{
		rec = first;
		first = rec->next;

		zbx_vector_uint64_destroy(&rec->recids);
		zbx_free(rec);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_updated_records                                          *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_updated_records(int nodeid, char *data)
{
	const char	*__function_name = "process_updated_records";
	char		*r, *lf, *value_esc;
	int		op, dnum;
	const ZBX_TABLE	*table = NULL;
	const ZBX_FIELD	*field = NULL;
	zbx_uint64_t	recid;
	char		*dsql = NULL,
			*isql = NULL, *ifld = NULL, *ival = NULL,
			*usql = NULL, *ufld = NULL;
	size_t		dsql_alloc = 4 * ZBX_KIBIBYTE, dsql_offset = 0, dtmp_offset = 0,
			isql_alloc = 4 * ZBX_KIBIBYTE, isql_offset = 0,
			ifld_alloc = 4 * ZBX_KIBIBYTE, ifld_offset = 0,
			ival_alloc = 4 * ZBX_KIBIBYTE, ival_offset = 0,
			usql_alloc = 4 * ZBX_KIBIBYTE, usql_offset = 0,
			ufld_alloc = 4 * ZBX_KIBIBYTE, ufld_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	dsql = zbx_malloc(dsql, dsql_alloc);
	isql = zbx_malloc(isql, isql_alloc);
	ifld = zbx_malloc(ifld, ifld_alloc);
	ival = zbx_malloc(ival, ival_alloc);
	usql = zbx_malloc(usql, usql_alloc);
	ufld = zbx_malloc(ufld, ufld_alloc);

#ifdef HAVE_ORACLE
	DBbegin_multiple_update(&dsql, &dsql_alloc, &dsql_offset);
	DBbegin_multiple_update(&isql, &isql_alloc, &isql_offset);
	DBbegin_multiple_update(&usql, &usql_alloc, &usql_offset);
#endif

	for (r = data; '\0' != *r;)
	{
		if (NULL != (lf = strchr(r, '\n')))
			*lf = '\0';

		/* table name */
		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

		if (NULL == table || 0 != strcmp(table->table, buf))
		{
			if (NULL == (table = DBget_table(buf)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find table [%s]", __function_name, buf);
				goto next;
			}
		}

		/* record id */
		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
		ZBX_STR2UINT64(recid, buf);

		if (NULL == r)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): invalid record", __function_name);
			goto next;
		}

		if ('0' == *r)	/* NODE_CONFIGLOG_OP_UPDATE */
		{
			result = DBselect("select 0 from %s where %s=" ZBX_FS_UI64,
					table->table, table->recid, recid);

			if (NULL == (row = DBfetch(result)))
				op = NODE_CONFIGLOG_OP_ADD;
			else
				op = NODE_CONFIGLOG_OP_UPDATE;

			DBfree_result(result);

			zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

			ifld_offset = 0;
			ival_offset = 0;
			ufld_offset = 0;
			dtmp_offset = dsql_offset;
			dnum = 0;

			if (op == NODE_CONFIGLOG_OP_ADD && NULL != table->uniq)
			{
				zbx_snprintf_alloc(&dsql, &dsql_alloc, &dsql_offset,
						"delete from %s where ", table->table);
			}

			while (NULL != r)
			{
				/* field name */
				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

				if (NULL == (field = DBget_field(table, buf)))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find field [%s.%s]",
							__function_name, table->table, buf);
					goto next;
				}

				if (NODE_CONFIGLOG_OP_UPDATE == op)
					zbx_snprintf_alloc(&ufld, &ufld_alloc, &ufld_offset, "%s=", buf);
				else	/* NODE_CONFIGLOG_OP_ADD */
					zbx_snprintf_alloc(&ifld, &ifld_alloc, &ifld_offset, "%s,", buf);

				/* value type (ignored) */
				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

				if (0 == strcmp(buf, "NULL"))
				{
					if (NODE_CONFIGLOG_OP_UPDATE == op)
						zbx_strcpy_alloc(&ufld, &ufld_alloc, &ufld_offset, "NULL,");
					else	/* NODE_CONFIGLOG_OP_ADD */
						zbx_strcpy_alloc(&ival, &ival_alloc, &ival_offset, "NULL,");
					continue;
				}

				switch (field->type)
				{
					case ZBX_TYPE_ID:
						/* if the field relates the same table
						 * for example: host.proxy_hostid relates with host.hostid
						 */
						if (NODE_CONFIGLOG_OP_ADD == op &&
								NULL != field->fk_table &&
								0 == strcmp(table->table, field->fk_table))
						{
							zbx_snprintf_alloc(&ufld, &ufld_alloc, &ufld_offset, "%s=%s,",
									field->name, buf);
							zbx_strcpy_alloc(&ival, &ival_alloc, &ival_offset, "NULL,");
							break;
						}
					case ZBX_TYPE_INT:
					case ZBX_TYPE_UINT:
					case ZBX_TYPE_FLOAT:
						if (NODE_CONFIGLOG_OP_UPDATE == op)
							zbx_snprintf_alloc(&ufld, &ufld_alloc, &ufld_offset,
									"%s,", buf);
						else	/* NODE_CONFIGLOG_OP_ADD */
						{
							zbx_snprintf_alloc(&ival, &ival_alloc, &ival_offset,
									"%s,", buf);

							if (NULL != table->uniq && SUCCEED == str_in_list(table->uniq, field->name, ','))
							{
								zbx_snprintf_alloc(&dsql, &dsql_alloc, &dsql_offset,
										"%s=%s and ", field->name, buf);
								dnum++;
							}
						}
						break;
					case ZBX_TYPE_BLOB:
						if ('\0' == *buf)
						{
							if (NODE_CONFIGLOG_OP_UPDATE == op)
								zbx_strcpy_alloc(&ufld, &ufld_alloc, &ufld_offset, "'',");
							else	/* NODE_CONFIGLOG_OP_ADD */
								zbx_strcpy_alloc(&ival, &ival_alloc, &ival_offset, "'',");
						}
						else
						{
#if defined(HAVE_POSTGRESQL)
							size_t	len;

							len = zbx_hex2binary(buf);
							DBbytea_escape((u_char *)buf, len, &tmp, &tmp_alloc);

							if (NODE_CONFIGLOG_OP_UPDATE == op)
								zbx_snprintf_alloc(&ufld, &ufld_alloc, &ufld_offset,
										"'%s',", tmp);
							else	/* NODE_CONFIGLOG_OP_ADD */
								zbx_snprintf_alloc(&ival, &ival_alloc, &ival_offset,
										"'%s',", tmp);
#else
							if (NODE_CONFIGLOG_OP_UPDATE == op)
								zbx_snprintf_alloc(&ufld, &ufld_alloc, &ufld_offset,
										"0x%s,", buf);
							else	/* NODE_CONFIGLOG_OP_ADD */
								zbx_snprintf_alloc(&ival, &ival_alloc, &ival_offset,
										"0x%s,", buf);
#endif
						}
						break;
					default:	/* ZBX_TYPE_TEXT, ZBX_TYPE_CHAR, ZBX_TYPE_SHORTTEXT, ZBX_TYPE_LONGTEXT */
						zbx_hex2binary(buf);
						value_esc = DBdyn_escape_string(buf);

						if (NODE_CONFIGLOG_OP_UPDATE == op)
							zbx_snprintf_alloc(&ufld, &ufld_alloc, &ufld_offset,
									"'%s',", value_esc);
						else	/* NODE_CONFIGLOG_OP_ADD */
						{
							zbx_snprintf_alloc(&ival, &ival_alloc, &ival_offset,
									"'%s',", value_esc);

							if (NULL != table->uniq && SUCCEED == str_in_list(table->uniq, field->name, ','))
							{
								zbx_snprintf_alloc(&dsql, &dsql_alloc, &dsql_offset,
										"%s='%s' and ", field->name, value_esc);
								dnum++;
							}
						}

						zbx_free(value_esc);
				}
			}

			if (dsql_offset != dtmp_offset)
			{
				if (dnum != num_param(table->uniq))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s(): missing required fields [%s][%s]",
							__function_name, table->table, table->uniq);
					dsql_offset = dtmp_offset;
					goto next;
				}

				dsql_offset -= 5;
				zbx_strcpy_alloc(&dsql, &dsql_alloc, &dsql_offset, ";\n");
			}

			if (0 != ifld_offset)
			{
				ifld[--ifld_offset] = '\0';
				ival[--ival_offset] = '\0';

				zbx_snprintf_alloc(&isql, &isql_alloc, &isql_offset,
						"insert into %s (%s,%s) values (" ZBX_FS_UI64 ",%s);\n",
						table->table, table->recid, ifld, recid, ival);
			}

			if (0 != ufld_offset)
			{
				ufld[--ufld_offset] = '\0';

				zbx_snprintf_alloc(&usql, &usql_alloc, &usql_offset,
						"update %s set %s where %s=" ZBX_FS_UI64 ";\n",
						table->table, ufld, table->recid, recid);
			}

			if (dsql_offset > ZBX_MAX_SQL_SIZE || isql_offset > ZBX_MAX_SQL_SIZE || usql_offset > ZBX_MAX_SQL_SIZE)
			{
				DBend_multiple_update(&dsql, &dsql_alloc, &dsql_offset);
				DBend_multiple_update(&isql, &isql_alloc, &isql_offset);
				DBend_multiple_update(&usql, &usql_alloc, &usql_offset);

				if (dsql_offset > 16)
					DBexecute("%s", dsql);
				if (isql_offset > 16)
					DBexecute("%s", isql);
				if (usql_offset > 16)
					DBexecute("%s", usql);

				dsql_offset = 0;
				isql_offset = 0;
				usql_offset = 0;
#ifdef HAVE_ORACLE
				DBbegin_multiple_update(&dsql, &dsql_alloc, &dsql_offset);
				DBbegin_multiple_update(&isql, &isql_alloc, &isql_offset);
				DBbegin_multiple_update(&usql, &usql_alloc, &usql_offset);
#endif
			}
		}
next:
		if (lf != NULL)
		{
			*lf++ = '\n';
			r = lf;
		}
		else
			break;
	}

	DBend_multiple_update(&dsql, &dsql_alloc, &dsql_offset);
	DBend_multiple_update(&isql, &isql_alloc, &isql_offset);
	DBend_multiple_update(&usql, &usql_alloc, &usql_offset);

	if (dsql_offset > 16)
		DBexecute("%s", dsql);
	if (isql_offset > 16)
		DBexecute("%s", isql);
	if (usql_offset > 16)
		DBexecute("%s", usql);

	zbx_free(ufld);
	zbx_free(usql);
	zbx_free(ival);
	zbx_free(ifld);
	zbx_free(isql);
	zbx_free(dsql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_checksum                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	process_checksum(int nodeid, char *data, unsigned char sender_nodetype)
{
	const char	*__function_name = "process_checksum";
	char		*r, *lf;
	size_t		tmp_offset;
	const ZBX_TABLE	*table = NULL;
	zbx_uint64_t	recid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (r = data; '\0' != *r;)
	{
		if (NULL != (lf = strchr(r, '\n')))
			*lf = '\0';

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* table name */

		if (NULL == table || 0 != strcmp(table->table, buf))
		{
			if (NULL == (table = DBget_table(buf)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find table [%s]", __function_name, buf);
				goto next;
			}
		}

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* record id */
		ZBX_STR2UINT64(recid, buf);

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* operation type */

		if ('0' == *buf)	/* NODE_CONFIGLOG_OP_UPDATE */
		{
			*tmp = '\0';
			tmp_offset = 0;

			while (NULL != r)
			{
				/* field name */
				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
				if (0 != tmp_offset)
					zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ',');
				zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, buf);
				/* field type */
				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
				/* value */
				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
			}

			if (SUCCEED == calculate_checksums(nodeid, table->table, recid))
				update_checksums(nodeid, sender_nodetype, SUCCEED, table->table, recid, tmp);
		}
next:
		if (NULL != lf)
		{
			*lf++ = '\n';
			r = lf;
		}
		else
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: node_sync                                                        *
 *                                                                            *
 * Purpose: process configuration changes received from a node                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_sync(char *data, int *sender_nodeid, int *nodeid)
{
	const char	*r;
	char		*lf;
	int		datalen, res = SUCCEED;
	unsigned char	sender_nodetype;

	datalen = strlen(data);

	zabbix_log(LOG_LEVEL_DEBUG, "In node_sync() len:%d", datalen);

	tmp = zbx_malloc(tmp, tmp_alloc);

	if (NULL != (lf = strchr(data, '\n')))
		*lf = '\0';

	r = data;
	zbx_get_next_field(&r, &tmp, &tmp_alloc, ZBX_DM_DELIMITER); /* Data */
	zbx_get_next_field(&r, &tmp, &tmp_alloc, ZBX_DM_DELIMITER);
	*sender_nodeid = atoi(tmp);
	sender_nodetype = (*sender_nodeid == CONFIG_MASTER_NODEID) ? ZBX_NODE_MASTER : ZBX_NODE_SLAVE;
	zbx_get_next_field(&r, &tmp, &tmp_alloc, ZBX_DM_DELIMITER);
	*nodeid = atoi(tmp);

	if (0 != *sender_nodeid && 0 != *nodeid)
	{
		if (CONFIG_MASTER_NODEID != *sender_nodeid && FAIL == is_direct_slave_node(*sender_nodeid))
		{
			zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received configuration changes from unknown node %d",
					CONFIG_NODEID, *sender_nodeid);
			res = FAIL;
			goto quit;
		}

		if (CONFIG_MASTER_NODEID != *sender_nodeid && CONFIG_NODEID == *nodeid)
		{
			zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received configuration changes for this node from a non-master node %d",
					CONFIG_NODEID, *sender_nodeid);
			res = FAIL;
			goto quit;
		}

		if (CONFIG_NODEID != *nodeid && FAIL == is_slave_node(CONFIG_NODEID, *nodeid))
		{
			zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received configuration changes for unknown node %d",
					CONFIG_NODEID, *nodeid);
			res = FAIL;
			goto quit;
		}

		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Received configuration changes from %s node %d for node %d datalen %d",
				CONFIG_NODEID, zbx_nodetype_string(sender_nodetype), *sender_nodeid, *nodeid, datalen);

		DBexecute("delete from node_cksum where nodeid=%d and cksumtype=%d",
				*nodeid,
				NODE_CKSUM_TYPE_NEW);

		if (lf != NULL)
		{
			*lf++ = '\n';
			data = lf;

			buf = zbx_malloc(buf, buf_alloc);

			process_updated_records(*nodeid, data);
			process_deleted_records(*nodeid, data);
			process_checksum(*nodeid, data, sender_nodetype);

			zbx_free(buf);
		}
	}
quit:
	zbx_free(tmp);

	return res;
}
