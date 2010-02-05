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

#include "nodesync.h"
#include "../nodewatcher/nodesender.h"

static char	*sql = NULL, *buf = NULL, *fld = NULL, *tmp = NULL;
static int	sql_alloc = 4096, sql_offset, buf_alloc = 128,
		fld_alloc = 1024, fld_offset, tmp_alloc = 16;

/******************************************************************************
 *                                                                            *
 * Function: process_record                                                   *
 *                                                                            *
 * Purpose: process record update                                             *
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
/*
static int	process_record(int nodeid, const char *record, int sender_nodetype)
{
	char		fieldname[ZBX_FIELDNAME_LEN_MAX];
	zbx_uint64_t	recid;
	int		op, res = SUCCEED;
	int		valuetype;
	char		*value_esc;
	const ZBX_TABLE	*table;
	DB_RESULT	result;
	DB_ROW		row;
	const char	*r;
	char		*buffer = NULL, *tmp = NULL, *fields_update = NULL, *fields = NULL, *values = NULL;
	int		buffer_allocated = 16*1024, buffer_len, fieldname_len;
	int		tmp_allocated = 16*1024, tmp_offset = 0;
	int		fields_update_allocated = 16*1024, fields_update_offset = 0;
	int		fields_allocated = 4*1024, fields_offset = 0;
	int		values_allocated = 16*1024, values_offset = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_record [%s]", record);

	r = record;
	buffer = zbx_malloc(buffer, buffer_allocated);

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);

	if (NULL == (table = DBget_table(buffer)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot find table [%s]",
				buffer);
		res = FAIL;
		goto out;
	}

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
	ZBX_STR2UINT64(recid, buffer);

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
	op = atoi(buffer);

	if (op == NODE_CONFIGLOG_OP_DELETE)
	{
		DBexecute("delete from %s where %s=" ZBX_FS_UI64,
				table->table,
				table->recid,
				recid);
		goto out;
	}

	tmp = zbx_malloc(tmp, tmp_allocated);
	fields_update = zbx_malloc(fields_update, fields_update_allocated);
	fields = zbx_malloc(fields, fields_allocated);
	values = zbx_malloc(values, values_allocated);

	zbx_snprintf_alloc(&fields, &fields_allocated, &fields_offset, 128, "%s,", table->recid);
	zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 128, ZBX_FS_UI64",", recid);

	while (NULL != r)
	{
		fieldname_len = zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
		zbx_strlcpy(fieldname, buffer, sizeof(fieldname));

		zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
		valuetype=atoi(buffer);

		buffer_len = zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
		if (op == NODE_CONFIGLOG_OP_UPDATE)
		{
			if (0 == strcmp(buffer, "NULL"))
			{
				zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, fieldname_len + 8, "%s=NULL,",
						fieldname);
				zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 8, "NULL,");
			}
			else
			{
				if (valuetype == ZBX_TYPE_INT || valuetype == ZBX_TYPE_UINT || valuetype == ZBX_TYPE_ID || valuetype == ZBX_TYPE_FLOAT)
				{
					zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, fieldname_len + buffer_len + 4,
							"%s=%s,",
							fieldname,
							buffer);
					zbx_snprintf_alloc(&values, &values_allocated, &values_offset, buffer_len + 4, "%s,",
							buffer);
				}
				else if (valuetype == ZBX_TYPE_BLOB)
				{
					if (*buffer == '\0')
					{
						zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, fieldname_len + 8, "%s='',",
								fieldname);
						zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 8, "'',");
					}
					else
					{
#if defined(HAVE_POSTGRESQL)
						buffer_len = zbx_hex2binary(buffer);
						buffer_len = zbx_pg_escape_bytea((u_char *)buffer, buffer_len, &tmp, &tmp_allocated);
						zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset,
								fieldname_len + buffer_len + 8, "%s='%s',",
								fieldname,
								tmp);
						zbx_snprintf_alloc(&values, &values_allocated, &values_offset, buffer_len + 8, "'%s',",
								tmp);
#else
						zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset,
								fieldname_len + buffer_len + 8, "%s=0x%s,",
								fieldname,
								buffer);
						zbx_snprintf_alloc(&values, &values_allocated, &values_offset, buffer_len + 8, "0x%s,",
								buffer);
#endif
					}
				}
				else * ZBX_TYPE_TEXT, ZBX_TYPE_CHAR *
				{
					zbx_hex2binary(buffer);
					value_esc = DBdyn_escape_string(buffer);
					buffer_len = strlen(value_esc);

					zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset,
							fieldname_len + buffer_len + 8, "%s='%s',",
							fieldname,
							value_esc);
					zbx_snprintf_alloc(&values, &values_allocated, &values_offset, buffer_len + 8, "'%s',",
							value_esc);

					zbx_free(value_esc)
				}
			}

			zbx_snprintf_alloc(&fields, &fields_allocated, &fields_offset, fieldname_len + 4, "%s,", fieldname);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unknown record operation [%d]",
					op);
			res = FAIL;
			goto out;
		}
	}
	if (fields_offset != 0)
		fields[fields_offset - 1] = '\0';
	if (fields_update_offset != 0)
		fields_update[fields_update_offset - 1] = '\0';
	if (values_offset != 0)
		values[values_offset - 1] = '\0';

	if (op == NODE_CONFIGLOG_OP_UPDATE)
	{
		result = DBselect("select 0 from %s where %s="ZBX_FS_UI64,
				table->table,
				table->recid,
				recid);
		if (NULL != (row = DBfetch(result)))
		{
			zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, strlen(table->table) + fields_update_offset + strlen(table->recid) + 64,
					"update %s set %s where %s=" ZBX_FS_UI64,
					table->table,
					fields_update,
					table->recid,
					recid);
		}
		else
		{
			zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, strlen(table->table) + fields_offset + values_offset + 64,
					"insert into %s (%s) values (%s)",
					table->table,
					fields,
					values);
		}
		DBfree_result(result);
	}
	DBexecute("%s", tmp);

	if (FAIL == calculate_checksums(nodeid, table->table, recid) ||
		FAIL == update_checksums(nodeid, sender_nodetype, SUCCEED, table->table, recid, fields) ) {
		res = FAIL;
		goto out;
	}

out:
	if (NULL != buffer)
		zbx_free(buffer);
	if (NULL != tmp)
		zbx_free(tmp);
	if (NULL != fields_update)
		zbx_free(fields_update);
	if (NULL != fields)
		zbx_free(fields);
	if (NULL != values)
		zbx_free(values);

	return res;
}
*/

/******************************************************************************
 *                                                                            *
 * Function: process_deleted_records                                          *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_deleted_records(int nodeid, char *data, int sender_nodetype)
{
	const char	*__function_name = "process_deleted_records";
	char		*r, *lf;
	const ZBX_TABLE	*table = NULL;
	zbx_uint64_t	recid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql_offset = 0;
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	for (r = data; '\0' != *r;)
	{
		if (NULL != (lf = strchr(r, '\n')))
			*lf = '\0';

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

		if (NULL == table || 0 != strcmp(table->table, buf))
			if (NULL == (table = DBget_table(buf)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find table [%s]", __function_name, buf);
				goto next;
			}

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
		ZBX_STR2UINT64(recid, buf);

		if ('2' == *r)	/* NODE_CONFIGLOG_OP_DELETE */
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
					"delete from %s where %s=" ZBX_FS_UI64 ";\n",
					table->table, table->recid, recid);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
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

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

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
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_updated_records(int nodeid, char *data, int sender_nodetype)
{
	const char	*__function_name = "process_updated_records";
	char		*r, *lf, *value_esc;
	int		len, valuetype;
	const ZBX_TABLE	*table = NULL;
	zbx_uint64_t	recid;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql_offset = 0;
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	r = data;
	for (r = data; '\0' != *r;)
	{
		if (NULL != (lf = strchr(r, '\n')))
			*lf = '\0';

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

		if (NULL == table || 0 != strcmp(table->table, buf))
			if (NULL == (table = DBget_table(buf)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find table [%s]", __function_name, buf);
				goto next;
			}

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
		ZBX_STR2UINT64(recid, buf);

		if ('0' == *r)	/* NODE_CONFIGLOG_OP_UPDATE */
		{
			result = DBselect("select 0 from %s where %s=" ZBX_FS_UI64,
					table->table, table->recid, recid);

			if (NULL == (row = DBfetch(result)))
				*r = '1';	/* NODE_CONFIGLOG_OP_ADD */
			DBfree_result(result);

			if ('0' != *r)	/* NODE_CONFIGLOG_OP_UPDATE */
				goto next;

			zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

			fld_offset = 0;
			while (NULL != r)
			{
				len = zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
				zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 2, "%s=", buf);

				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
				valuetype = atoi(buf);

				len = zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

				if (0 == strcmp(buf, "NULL"))
				{
					zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, 6, "NULL,");
					continue;
				}

				switch (valuetype) {
				case ZBX_TYPE_INT:
				case ZBX_TYPE_UINT:
				case ZBX_TYPE_ID:
				case ZBX_TYPE_FLOAT:
					zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 2, "%s,", buf);
					break;
				case ZBX_TYPE_BLOB:
					if ('\0' == *buf)
						zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, 4, "'',");
					else
					{
#if defined(HAVE_POSTGRESQL)
						len = zbx_hex2binary(buf);
						len = zbx_pg_escape_bytea((u_char *)buf, len, &tmp, &tmp_alloc);
						zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 4, "'%s',", tmp);
#else
						zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 4, "0x%s,", buf);
#endif
					}
					break;
				default:	/* ZBX_TYPE_TEXT, ZBX_TYPE_CHAR */
					zbx_hex2binary(buf);
					value_esc = DBdyn_escape_string(buf);
					len = strlen(value_esc);

					zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 4, "'%s',", value_esc);

					zbx_free(value_esc)
				}
			}
			if (fld_offset != 0)
				fld[fld_offset - 1] = '\0';

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, fld_offset + 192,
					"update %s set %s where %s=" ZBX_FS_UI64 ";\n",
					table->table, fld, table->recid, recid);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
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

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_new_records                                              *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_new_records(int nodeid, char *data, int sender_nodetype)
{
	const char	*__function_name = "process_new_records";
	char		*r, *lf, *value_esc;
	int		len, valuetype;
	const ZBX_TABLE	*table = NULL;
	zbx_uint64_t	recid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql_offset = 0;
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	for (r = data; '\0' != *r;)
	{
		if (NULL != (lf = strchr(r, '\n')))
			*lf = '\0';

		if ('#' == *r)	/* already processed */
			goto next;

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

		if (NULL == table || 0 != strcmp(table->table, buf))
			if (NULL == (table = DBget_table(buf)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find table [%s]", __function_name, buf);
				goto next;
			}

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
		ZBX_STR2UINT64(recid, buf);

		if ('1' == *r)	/* NODE_CONFIGLOG_OP_ADD */
		{
			zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
					"insert into %s (%s,", table->table, table->recid);

			fld_offset = 0;
			zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, 32, ZBX_FS_UI64 ",", recid);

			while (NULL != r)
			{
				len = zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, len + 2, "%s,", buf);

				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
				valuetype = atoi(buf);

				len = zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

				if (0 == strcmp(buf, "NULL"))
				{
					zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, 6, "NULL,");
					continue;
				}

				switch (valuetype) {
				case ZBX_TYPE_INT:
				case ZBX_TYPE_UINT:
				case ZBX_TYPE_ID:
				case ZBX_TYPE_FLOAT:
					zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 2, "%s,", buf);
					break;
				case ZBX_TYPE_BLOB:
					if ('\0' == *buf)
						zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, 4, "'',");
					else
					{
#if defined(HAVE_POSTGRESQL)
						len = zbx_hex2binary(buf);
						len = zbx_pg_escape_bytea((u_char *)buf, len, &tmp, &tmp_alloc);
						zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 4, "'%s',", tmp);
#else
						zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 4, "0x%s,", buf);
#endif
					}
					break;
				default:	/* ZBX_TYPE_TEXT, ZBX_TYPE_CHAR */
					zbx_hex2binary(buf);
					value_esc = DBdyn_escape_string(buf);
					len = strlen(value_esc);

					zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 4, "'%s',", value_esc);

					zbx_free(value_esc)
				}
			}
			sql[--sql_offset] = '\0';
			fld[--fld_offset] = '\0';

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, fld_offset + 16, ") values (%s);\n", fld);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			/* mark record as processed */
			*data = '#';
		}
next:
		if (lf != NULL)
		{
			*lf++ = '\n';
			r = lf;
			data = lf;
		}
		else
			break;
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_checksum                                                 *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_checksum(int nodeid, char *data, int sender_nodetype)
{
	const char	*__function_name = "process_checksum";
	char		*r, *lf;
	int		len;
	const ZBX_TABLE	*table = NULL;
	zbx_uint64_t	recid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (r = data; '\0' != *r;)
	{
		if (NULL != (lf = strchr(r, '\n')))
			*lf = '\0';

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

		if (NULL == table || 0 != strcmp(table->table, buf))
			if (NULL == (table = DBget_table(buf)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find table [%s]", __function_name, buf);
				goto next;
			}

		zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
		ZBX_STR2UINT64(recid, buf);

		if ('0' == *r || '1' == *r)	/* NODE_CONFIGLOG_OP_UPDATE || NODE_CONFIGLOG_OP_ADD */
		{
			zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);

			fld_offset = 0;
			while (NULL != r)
			{
				len = zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);
	
				zbx_snprintf_alloc(&fld, &fld_alloc, &fld_offset, len + 2, "%s,", buf);

				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* value type */
				zbx_get_next_field((const char **)&r, &buf, &buf_alloc, ZBX_DM_DELIMITER);	/* value */
			}
			if (fld_offset != 0)
				fld[fld_offset - 1] = '\0';

			if (SUCCEED == calculate_checksums(nodeid, table->table, recid))
				update_checksums(nodeid, sender_nodetype, SUCCEED, table->table, recid, fld);
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
	int		sender_nodetype, datalen, res = SUCCEED;

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
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Received data from %s node %d for node %d datalen %d",
				CONFIG_NODEID, (sender_nodetype == ZBX_NODE_SLAVE) ? "slave" : "master",
				*sender_nodeid, *nodeid, datalen);

		DBexecute("delete from node_cksum where nodeid=%d and cksumtype=%d",
				*nodeid,
				NODE_CKSUM_TYPE_NEW);

		if (lf != NULL)
		{
			*lf++ = '\n';
			data = lf;

			sql = zbx_malloc(sql, sql_alloc);
			buf = zbx_malloc(buf, buf_alloc);
			fld = zbx_malloc(fld, fld_alloc);

			process_deleted_records(*nodeid, data, sender_nodetype);
			process_updated_records(*nodeid, data, sender_nodetype);
			process_new_records(*nodeid, data, sender_nodetype);
			process_checksum(*nodeid, data, sender_nodetype);

			zbx_free(fld);
			zbx_free(buf);
			zbx_free(sql);
		}
	}
	zbx_free(tmp);

	return res;
}
