/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "log.h"
#include "dbcache.h"
#include "zbxserver.h"

#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"
#include "dbsync.h"

typedef struct
{
	zbx_hashset_t	strpool;
	ZBX_DC_CONFIG	*cache;
}
zbx_dbsync_env_t;

static zbx_dbsync_env_t	dbsync_env;

/* string pool support */

#define REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

static zbx_hash_t	dbsync_strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((char *)data + REFCOUNT_FIELD_SIZE);
}

static int	dbsync_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp((char *)d1 + REFCOUNT_FIELD_SIZE, (char *)d2 + REFCOUNT_FIELD_SIZE);
}

static char	*dbsync_strdup(const char *str)
{
	void	*ptr;

	ptr = zbx_hashset_search(&dbsync_env.strpool, str - REFCOUNT_FIELD_SIZE);

	if (NULL == ptr)
	{
		ptr = zbx_hashset_insert_ext(&dbsync_env.strpool, str - REFCOUNT_FIELD_SIZE,
				REFCOUNT_FIELD_SIZE + strlen(str) + 1, REFCOUNT_FIELD_SIZE);

		*(zbx_uint32_t *)ptr = 0;
	}

	(*(zbx_uint32_t *)ptr)++;

	return (char *)ptr + REFCOUNT_FIELD_SIZE;
}

static void	dbsync_strfree(char *str)
{
	if (NULL != str)
	{
		void	*ptr = str - REFCOUNT_FIELD_SIZE;

		if (0 == --(*(zbx_uint32_t *)ptr))
			zbx_hashset_remove_direct(&dbsync_env.strpool, ptr);
	}
}

/* macro valie validators */

/******************************************************************************
 *                                                                            *
 * Function: dbsync_numeric_validator                                         *
 *                                                                            *
 * Purpose: validate numeric value                                            *
 *                                                                            *
 * Parameters: value   - [IN] the value to validate                           *
 *                                                                            *
 * Return value: SUCCEED - the value contains valid numeric value             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_numeric_validator(const char *value)
{
	if (SUCCEED == is_double_suffix(value, ZBX_FLAG_DOUBLE_SUFFIX))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_uint64                                            *
 *                                                                            *
 * Purpose: compares 64 bit unsigned integer with a raw database value        *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_uint64(const char *value_raw, zbx_uint64_t value)
{
	zbx_uint64_t	value_ui64;

	ZBX_DBROW2UINT64(value_ui64, value_raw);

	return (value_ui64 == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_int                                               *
 *                                                                            *
 * Purpose: compares 32 bit signed integer with a raw database value          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_int(const char *value_raw, int value)
{
	return (atoi(value_raw) == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_uchar                                             *
 *                                                                            *
 * Purpose: compares unsigned character with a raw database value             *
 *                                                                            *
 ******************************************************************************/

static int	dbsync_compare_uchar(const char *value_raw, unsigned char value)
{
	unsigned char	value_uchar;

	ZBX_STR2UCHAR(value_uchar, value_raw);
	return (value_uchar == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_str                                               *
 *                                                                            *
 * Purpose: compares string with a raw database value                         *
 *                                                                            *
 ******************************************************************************/

static int	dbsync_compare_str(const char *value_raw, const char *value)
{
	return (0 == strcmp(value_raw, value) ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_add_row                                                   *
 *                                                                            *
 * Purpose: adds a new row to the changeset                                   *
 *                                                                            *
 * Parameter: sync  - [IN] the changeset                                      *
 *            rowid - [IN] the row identifier                                 *
 *            tag   - [IN] the row tag (see ZBX_DBSYNC_ROW_ defines)          *
 *            row   - [IN] the row contents (NULL for ZBX_DBSYNC_ROW_REMOVE)  *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_add_row(zbx_dbsync_t *sync, zbx_uint64_t rowid, unsigned char tag, const DB_ROW dbrow)
{
	int			i;
	zbx_dbsync_row_t	*row;

	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = rowid;
	row->tag = tag;

	if (NULL != dbrow)
	{
		row->row = (char **)zbx_malloc(NULL, sizeof(char *) * sync->columns_num);

		for (i = 0; i < sync->columns_num; i++)
			row->row[i] = (NULL == dbrow[i] ? NULL : dbsync_strdup(dbrow[i]));
	}
	else
		row->row = NULL;

	zbx_vector_ptr_append(&sync->rows, row);

	switch (tag)
	{
		case ZBX_DBSYNC_ROW_ADD:
			sync->add_num++;
			break;
		case ZBX_DBSYNC_ROW_UPDATE:
			sync->update_num++;
			break;
		case ZBX_DBSYNC_ROW_REMOVE:
			sync->remove_num++;
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_prepare                                                   *
 *                                                                            *
 * Purpose: prepares changeset                                                *
 *                                                                            *
 * Parameter: sync             - [IN] the changeset                           *
 *            columns_num      - [IN] the number of columns in the changeset  *
 *            get_hostids_func - [IN] the callback function used to retrieve  *
 *                                    associated hostids (can be NULL if      *
 *                                    user macros are not resolved during     *
 *                                    synchronization process)                *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_prepare(zbx_dbsync_t *sync, int columns_num, zbx_dbsync_preproc_row_func_t preproc_row_func)
{
	sync->columns_num = columns_num;
	sync->preproc_row_func = preproc_row_func;

	sync->row = (char **)zbx_malloc(NULL, sizeof(char *) * columns_num);
	memset(sync->row, 0, sizeof(char *) * columns_num);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_check_row_macros                                          *
 *                                                                            *
 * Purpose: checks if the specified column in the row contains user macros    *
 *                                                                            *
 * Parameter: row    - [IN] the row to check                                  *
 *            column - [IN] the column index                                  *
 *                                                                            *
 * Comments: While not definite, this check is used to filter out rows before *
 *           doing more precise (and resource intense) checks.                *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_check_row_macros(char **row, int column)
{
	if (NULL != strstr(row[column], "{$"))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_preproc_row                                               *
 *                                                                            *
 * Purpose: applies necessary pre-processing before row is compared/used      *
 *                                                                            *
 * Parameter: sync - [IN] the changeset                                       *
 *            row  - [IN/OUT] the data row                                    *
 *                                                                            *
 * Return value: the resulting row                                            *
 *                                                                            *
 ******************************************************************************/
static char	**dbsync_preproc_row(zbx_dbsync_t *sync, char **row)
{
	int	i;

	if (NULL == sync->preproc_row_func)
		return row;

	/* free the resources allocated by last preprocessing call */
	zbx_vector_ptr_clear_ext(&sync->columns, zbx_ptr_free);

	/* copy the original data */
	memcpy(sync->row, row, sizeof(char *) * sync->columns_num);

	sync->row = sync->preproc_row_func(sync->row);

	for (i = 0; i < sync->columns_num; i++)
	{
		if (sync->row[i] != row[i])
			zbx_vector_ptr_append(&sync->columns, sync->row[i]);
	}

	return sync->row;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_init_env                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_init_env(ZBX_DC_CONFIG *cache)
{
	dbsync_env.cache = cache;
	zbx_hashset_create(&dbsync_env.strpool, 100, dbsync_strpool_hash_func, dbsync_strpool_compare_func);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_env_release                                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_free_env(void)
{
	zbx_hashset_destroy(&dbsync_env.strpool);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_init                                                  *
 *                                                                            *
 * Purpose: initializes changeset                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_init(zbx_dbsync_t *sync, unsigned char mode)
{
	sync->columns_num = 0;
	sync->mode = mode;

	sync->add_num = 0;
	sync->update_num = 0;
	sync->remove_num = 0;

	sync->row = NULL;
	sync->preproc_row_func = NULL;
	zbx_vector_ptr_create(&sync->columns);

	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		zbx_vector_ptr_create(&sync->rows);
		sync->row_index = 0;
	}
	else
		sync->dbresult = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_clear                                                 *
 *                                                                            *
 * Purpose: frees resources allocated by changeset                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_clear(zbx_dbsync_t *sync)
{
	/* free the resources allocated by row pre-processing */
	zbx_vector_ptr_clear_ext(&sync->columns, zbx_ptr_free);
	zbx_vector_ptr_destroy(&sync->columns);

	zbx_free(sync->row);

	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		int			i, j;
		zbx_dbsync_row_t	*row;

		for (i = 0; i < sync->rows.values_num; i++)
		{
			row = (zbx_dbsync_row_t *)sync->rows.values[i];

			if (NULL != row->row)
			{
				for (j = 0; j < sync->columns_num; j++)
					dbsync_strfree(row->row[j]);

				zbx_free(row->row);
			}

			zbx_free(row);
		}

		zbx_vector_ptr_destroy(&sync->rows);
	}
	else
	{
		DBfree_result(sync->dbresult);
		sync->dbresult = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_next                                                  *
 *                                                                            *
 * Purpose: gets the next row from the changeset                              *
 *                                                                            *
 * Parameters: sync  - [IN] the changeset                                     *
 *             rowid - [OUT] the row identifier (required for row removal,    *
 *                          optional for new/updated rows)                    *
 *             row   - [OUT] the row data                                     *
 *             tag   - [OUT] the row tag, identifying changes                 *
 *                           (see ZBX_DBSYNC_ROW_* defines)                   *
 *                                                                            *
 * Return value: SUCCEED - the next row was successfully retrieved            *
 *               FAIL    - no more data to retrieve                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_next(zbx_dbsync_t *sync, zbx_uint64_t *rowid, char ***row, unsigned char *tag)
{
	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		zbx_dbsync_row_t	*sync_row;

		if (sync->row_index == sync->rows.values_num)
			return FAIL;

		sync_row = (zbx_dbsync_row_t *)sync->rows.values[sync->row_index++];
		*rowid = sync_row->rowid;
		*row = sync_row->row;
		*tag = sync_row->tag;
	}
	else
	{
		char	**dbrow;

		if (NULL == (dbrow = DBfetch(sync->dbresult)))
		{
			*row = NULL;
			return FAIL;
		}

		*row = dbsync_preproc_row(sync, dbrow);

		*rowid = 0;
		*tag = ZBX_DBSYNC_ROW_ADD;

		sync->add_num++;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_config                                        *
 *                                                                            *
 * Purpose: compares config table with cached configuration data              *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_config(zbx_dbsync_t *sync)
{
	DB_RESULT	result;

	if (NULL == (result = DBselect("select refresh_unsupported,discovery_groupid,snmptrap_logging,"
				"severity_name_0,severity_name_1,severity_name_2,"
				"severity_name_3,severity_name_4,severity_name_5,"
				"hk_events_mode,hk_events_trigger,hk_events_internal,"
				"hk_events_discovery,hk_events_autoreg,hk_services_mode,"
				"hk_services,hk_audit_mode,hk_audit,hk_sessions_mode,hk_sessions,"
				"hk_history_mode,hk_history_global,hk_history,hk_trends_mode,"
				"hk_trends_global,hk_trends,default_inventory_mode"
			" from config"
			" order by configid")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 27, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	DBfree_result(result);

	/* global configuration will be always synchronized directly with database */
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_host                                              *
 *                                                                            *
 * Purpose: compares hosts table row with cached configuration data           *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            host  - [IN] the cached host                                    *
 *            row   - [IN] the database row                                   *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host(ZBX_DC_HOST *host, const DB_ROW dbrow)
{
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
	ZBX_DC_IPMIHOST	*ipmihost;

	if (FAIL == dbsync_compare_uint64(dbrow[1], host->proxy_hostid))
	{
		host->update_items = 1;
		return FAIL;
	}

	if (FAIL == dbsync_compare_uchar(dbrow[22], host->status))
	{
		host->update_items = 1;
		return FAIL;
	}

	host->update_items = 0;

	if (FAIL == dbsync_compare_str(dbrow[2], host->host))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[23], host->name))
		return FAIL;

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (FAIL == dbsync_compare_str(dbrow[31], host->tls_issuer))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[32], host->tls_subject))
		return FAIL;

	if ('\0' == *dbrow[33] || '\0' == *dbrow[34])
	{
		if (NULL != host->tls_dc_psk)
			return FAIL;
	}
	else
	{
		if (NULL == host->tls_dc_psk)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[33], host->tls_dc_psk->tls_psk_identity))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[34], host->tls_dc_psk->tls_psk))
			return FAIL;
	}

	if (FAIL == dbsync_compare_str(dbrow[35], host->proxy_address))
		return FAIL;
#else
	if (FAIL == dbsync_compare_str(dbrow[31], host->proxy_address))
		return FAIL;
#endif
	if (FAIL == dbsync_compare_uchar(dbrow[29], host->tls_connect))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[30], host->tls_accept))
		return FAIL;

	/* IPMI hosts */

	ipmi_authtype = (signed char)atoi(dbrow[3]);
	ipmi_privilege = (unsigned char)atoi(dbrow[4]);

	if (ZBX_IPMI_DEFAULT_AUTHTYPE != ipmi_authtype || ZBX_IPMI_DEFAULT_PRIVILEGE != ipmi_privilege ||
			'\0' != *dbrow[5] || '\0' != *dbrow[6])	/* useipmi */
	{
		if (NULL == (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&dbsync_env.cache->ipmihosts,
				&host->hostid)))
		{
			return FAIL;
		}

		if (ipmihost->ipmi_authtype != ipmi_authtype)
			return FAIL;

		if (ipmihost->ipmi_privilege != ipmi_privilege)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[5], ipmihost->ipmi_username))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[6], ipmihost->ipmi_password))
			return FAIL;
	}
	else if (NULL != zbx_hashset_search(&dbsync_env.cache->ipmihosts, &host->hostid))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_hosts                                         *
 *                                                                            *
 * Purpose: compares hosts table with cached configuration data               *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_hosts(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_HOST		*host;

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (NULL == (result = DBselect(
			"select hostid,proxy_hostid,host,ipmi_authtype,ipmi_privilege,ipmi_username,"
				"ipmi_password,maintenance_status,maintenance_type,maintenance_from,"
				"errors_from,available,disable_until,snmp_errors_from,"
				"snmp_available,snmp_disable_until,ipmi_errors_from,ipmi_available,"
				"ipmi_disable_until,jmx_errors_from,jmx_available,jmx_disable_until,"
				"status,name,lastaccess,error,snmp_error,ipmi_error,jmx_error,tls_connect,tls_accept"
				",tls_issuer,tls_subject,tls_psk_identity,tls_psk,proxy_address"
			" from hosts"
			" where status in (%d,%d,%d,%d)"
				" and flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 36, NULL);
#else
	if (NULL == (result = DBselect(
			"select hostid,proxy_hostid,host,ipmi_authtype,ipmi_privilege,ipmi_username,"
				"ipmi_password,maintenance_status,maintenance_type,maintenance_from,"
				"errors_from,available,disable_until,snmp_errors_from,"
				"snmp_available,snmp_disable_until,ipmi_errors_from,ipmi_available,"
				"ipmi_disable_until,jmx_errors_from,jmx_available,jmx_disable_until,"
				"status,name,lastaccess,error,snmp_error,ipmi_error,jmx_error,tls_connect,tls_accept,"
				"proxy_address"
			" from hosts"
			" where status in (%d,%d,%d,%d)"
				" and flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 32, NULL);
#endif

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->hosts.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&dbsync_env.cache->hosts, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_host(host, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->hosts, &iter);
	while (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &host->hostid))
			dbsync_add_row(sync, host->hostid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_host_inventory                                    *
 *                                                                            *
 * Purpose: compares host inventory table row with cached configuration data  *
 *                                                                            *
 * Parameter: hi  - [IN] the cached host inventory data                       *
 *            row - [IN] the database row                                     *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host_inventory(const ZBX_DC_HOST_INVENTORY *hi, const DB_ROW dbrow)
{
	int	i;

	if (SUCCEED != dbsync_compare_uchar(dbrow[1], hi->inventory_mode))
		return FAIL;

	for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
	{
		if (FAIL == dbsync_compare_str(dbrow[i + 2], hi->values[i]))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_host_inventory                                *
 *                                                                            *
 * Purpose: compares host_inventory table with cached configuration data      *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_inventory(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_HOST_INVENTORY	*hi;
	const char		*sql;

	sql = "select hostid,inventory_mode,type,type_full,name,alias,os,os_full,os_short,serialno_a,"
			"serialno_b,tag,asset_tag,macaddress_a,macaddress_b,hardware,hardware_full,software,"
			"software_full,software_app_a,software_app_b,software_app_c,software_app_d,"
			"software_app_e,contact,location,location_lat,location_lon,notes,chassis,model,"
			"hw_arch,vendor,contract_number,installer_name,deployment_status,url_a,url_b,"
			"url_c,host_networks,host_netmask,host_router,oob_ip,oob_netmask,oob_router,"
			"date_hw_purchase,date_hw_install,date_hw_expiry,date_hw_decomm,site_address_a,"
			"site_address_b,site_address_c,site_city,site_state,site_country,site_zip,site_rack,"
			"site_notes,poc_1_name,poc_1_email,poc_1_phone_a,poc_1_phone_b,poc_1_cell,"
			"poc_1_screen,poc_1_notes,poc_2_name,poc_2_email,poc_2_phone_a,poc_2_phone_b,"
			"poc_2_cell,poc_2_screen,poc_2_notes"
			" from host_inventory";

	if (NULL == (result = DBselect("%s", sql)))
		return FAIL;

	dbsync_prepare(sync, 72, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->host_inventories.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (hi = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&dbsync_env.cache->host_inventories,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_host_inventory(hi, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);

	}

	zbx_hashset_iter_reset(&dbsync_env.cache->host_inventories, &iter);
	while (NULL != (hi = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &hi->hostid))
			dbsync_add_row(sync, hi->hostid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_host_templates                                *
 *                                                                            *
 * Purpose: compares hosts_templates table with cached configuration data     *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_templates(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_iter_t	iter;
	ZBX_DC_HTMPL		*htmpl;
	zbx_hashset_t		htmpls;
	int			i;
	zbx_uint64_pair_t	ht_local, *ht;
	char			hostid_s[MAX_ID_LEN + 1], templateid_s[MAX_ID_LEN + 1];
	char			*del_row[2] = {hostid_s, templateid_s};

	if (NULL == (result = DBselect(
			"select hostid,templateid"
			" from hosts_templates"
			" order by hostid")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&htmpls, 100, ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	/* index all host->template links */
	zbx_hashset_iter_reset(&dbsync_env.cache->htmpls, &iter);
	while (NULL != (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_iter_next(&iter)))
	{
		ht_local.first = htmpl->hostid;

		for (i = 0; i < htmpl->templateids.values_num; i++)
		{
			ht_local.second = htmpl->templateids.values[i];
			zbx_hashset_insert(&htmpls, &ht_local, sizeof(ht_local));
		}
	}

	/* add new rows, remove existing rows from index */
	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(ht_local.first, dbrow[0]);
		ZBX_STR2UINT64(ht_local.second, dbrow[1]);

		if (NULL == (ht = (zbx_uint64_pair_t *)zbx_hashset_search(&htmpls, &ht_local)))
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_ADD, dbrow);
		else
			zbx_hashset_remove_direct(&htmpls, ht);
	}

	/* add removed rows */
	zbx_hashset_iter_reset(&htmpls, &iter);
	while (NULL != (ht = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_snprintf(hostid_s, sizeof(hostid_s), ZBX_FS_UI64, ht->first);
		zbx_snprintf(templateid_s, sizeof(templateid_s), ZBX_FS_UI64, ht->second);
		dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, del_row);
	}

	DBfree_result(result);
	zbx_hashset_destroy(&htmpls);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_global_macro                                      *
 *                                                                            *
 * Purpose: compares global macro table row with cached configuration data    *
 *                                                                            *
 * Parameter: gmacro - [IN] the cached global macro data                      *
 *            row    - [IN] the database row                                  *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_global_macro(const ZBX_DC_GMACRO *gmacro, const DB_ROW dbrow)
{
	char	*macro = NULL, *context = NULL;
	int	ret = FAIL;

	if (FAIL == dbsync_compare_str(dbrow[2], gmacro->value))
		return FAIL;

	if (SUCCEED != zbx_user_macro_parse_dyn(dbrow[1], &macro, &context, NULL))
		return FAIL;

	if (0 != strcmp(gmacro->macro, macro))
		goto out;

	if (NULL == context)
	{
		if (NULL != gmacro->context)
			goto out;

		ret = SUCCEED;
		goto out;
	}

	if (NULL == gmacro->context)
		goto out;

	if (0 == strcmp(gmacro->context, context))
		ret = SUCCEED;
out:
	zbx_free(macro);
	zbx_free(context);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_global_macros                                 *
 *                                                                            *
 * Purpose: compares global macros table with cached configuration data       *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_global_macros(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_GMACRO		*macro;

	if (NULL == (result = DBselect(
			"select globalmacroid,macro,value"
			" from globalmacro")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 3, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->gmacros.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (macro = (ZBX_DC_GMACRO *)zbx_hashset_search(&dbsync_env.cache->gmacros, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_global_macro(macro, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->gmacros, &iter);
	while (NULL != (macro = (ZBX_DC_GMACRO *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &macro->globalmacroid))
			dbsync_add_row(sync, macro->globalmacroid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_host_macro                                        *
 *                                                                            *
 * Purpose: compares host macro table row with cached configuration data      *
 *                                                                            *
 * Parameter: hmacro - [IN] the cached host macro data                        *
 *            row -    [IN] the database row                                  *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host_macro(const ZBX_DC_HMACRO *hmacro, const DB_ROW dbrow)
{
	char	*macro = NULL, *context = NULL;
	int	ret = FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], hmacro->value))
		return FAIL;

	if (FAIL == dbsync_compare_uint64(dbrow[1], hmacro->hostid))
		return FAIL;

	if (SUCCEED != zbx_user_macro_parse_dyn(dbrow[2], &macro, &context, NULL))
		return FAIL;

	if (0 != strcmp(hmacro->macro, macro))
		goto out;

	if (NULL == context)
	{
		if (NULL != hmacro->context)
			goto out;

		ret = SUCCEED;
		goto out;
	}

	if (NULL == hmacro->context)
		goto out;

	if (0 == strcmp(hmacro->context, context))
		ret = SUCCEED;
out:
	zbx_free(macro);
	zbx_free(context);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_host_macros                                   *
 *                                                                            *
 * Purpose: compares global macros table with cached configuration data       *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_macros(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_HMACRO		*macro;

	if (NULL == (result = DBselect(
			"select hostmacroid,hostid,macro,value"
			" from hostmacro")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->hmacros.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (macro = (ZBX_DC_HMACRO *)zbx_hashset_search(&dbsync_env.cache->hmacros, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_host_macro(macro, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->hmacros, &iter);
	while (NULL != (macro = (ZBX_DC_HMACRO *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &macro->hostmacroid))
			dbsync_add_row(sync, macro->hostmacroid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_interface                                         *
 *                                                                            *
 * Purpose: compares interface table row with cached configuration data       *
 *                                                                            *
 * Parameter: interface - [IN] the cached interface data                      *
 *            row       - [IN] the database row                               *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: User macros used in ip, dns fields will always make compare to   *
 *           fail.                                                            *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_interface(const ZBX_DC_INTERFACE *interface, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], interface->hostid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], interface->type))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[3], interface->main))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[4], interface->useip))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[8], interface->bulk))
		return FAIL;

	if (NULL != strstr(dbrow[5], "{$"))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[5], interface->ip))
		return FAIL;

	if (NULL != strstr(dbrow[6], "{$"))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[6], interface->dns))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[7], interface->port))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_interfaces                                    *
 *                                                                            *
 * Purpose: compares interfaces table with cached configuration data          *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_interfaces(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_INTERFACE	*interface;

	if (NULL == (result = DBselect(
			"select interfaceid,hostid,type,main,useip,ip,dns,port,bulk"
			" from interface")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 9, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->interfaces.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&dbsync_env.cache->interfaces, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_interface(interface, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->interfaces, &iter);
	while (NULL != (interface = (ZBX_DC_INTERFACE *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &interface->interfaceid))
			dbsync_add_row(sync, interface->interfaceid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_item                                              *
 *                                                                            *
 * Purpose: compares items table row with cached configuration data           *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            item  - [IN] the cached item                                    *
 *            row   - [IN] the database row                                   *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_item(const ZBX_DC_ITEM *item, const DB_ROW dbrow)
{
	ZBX_DC_NUMITEM		*numitem;
	ZBX_DC_SNMPITEM		*snmpitem;
	ZBX_DC_IPMIITEM		*ipmiitem;
	ZBX_DC_TRAPITEM		*trapitem;
	ZBX_DC_LOGITEM		*logitem;
	ZBX_DC_DBITEM		*dbitem;
	ZBX_DC_SSHITEM		*sshitem;
	ZBX_DC_TELNETITEM	*telnetitem;
	ZBX_DC_SIMPLEITEM	*simpleitem;
	ZBX_DC_JMXITEM		*jmxitem;
	ZBX_DC_CALCITEM		*calcitem;
	ZBX_DC_DEPENDENTITEM	*depitem;
	ZBX_DC_HOST		*host;
	unsigned char		value_type, type, history, trends;
	int			history_sec = 0;

	if (FAIL == dbsync_compare_uint64(dbrow[1], item->hostid))
		return FAIL;

	if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&dbsync_env.cache->hosts, &item->hostid)))
		return FAIL;

	if (0 != host->update_items)
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], item->status))
		return FAIL;

	ZBX_STR2UCHAR(type, dbrow[3]);
	if (item->type != type)
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[8], item->port))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[24], item->flags))
		return FAIL;

	if (FAIL == dbsync_compare_uint64(dbrow[25], item->interfaceid))
		return FAIL;

	if (ZBX_HK_OPTION_ENABLED == dbsync_env.cache->config->hk.history_global)
	{
		history = (0 != dbsync_env.cache->config->hk.history);
		history_sec = dbsync_env.cache->config->hk.history;
	}
	else
	{
		if (SUCCEED != is_time_suffix(dbrow[31], &history_sec, ZBX_LENGTH_UNLIMITED))
			return FAIL;

		history = zbx_time2bool(dbrow[31]);
	}

	if (item->history != history)
		return FAIL;

	if (history_sec != item->history_sec)
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[33], item->inventory_link))
		return FAIL;

	if (FAIL == dbsync_compare_uint64(dbrow[34], item->valuemapid))
		return FAIL;

	ZBX_STR2UCHAR(value_type, dbrow[4]);
	if (item->value_type != value_type)
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[5], item->key))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[14], item->delay))
		return FAIL;

	numitem = (ZBX_DC_NUMITEM *)zbx_hashset_search(&dbsync_env.cache->numitems, &item->itemid);
	if (ITEM_VALUE_TYPE_FLOAT == value_type || ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		if (NULL == numitem)
			return FAIL;

		if (ZBX_HK_OPTION_ENABLED == dbsync_env.cache->config->hk.trends_global)
			trends = (0 != dbsync_env.cache->config->hk.trends);
		else
			trends = zbx_time2bool(dbrow[32]);

		if (trends != numitem->trends)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[35], numitem->units))
			return FAIL;
	}
	else if (NULL != numitem)
		return FAIL;

	snmpitem = (ZBX_DC_SNMPITEM *)zbx_hashset_search(&dbsync_env.cache->snmpitems, &item->itemid);
	if (SUCCEED == is_snmp_type(type))
	{
		if (NULL == snmpitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[6], snmpitem->snmp_community))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[9], snmpitem->snmpv3_securityname))
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[10], snmpitem->snmpv3_securitylevel))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[11], snmpitem->snmpv3_authpassphrase))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[12], snmpitem->snmpv3_privpassphrase))
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[26], snmpitem->snmpv3_authprotocol))
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[27], snmpitem->snmpv3_privprotocol))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[28], snmpitem->snmpv3_contextname))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[7], snmpitem->snmp_oid))
			return FAIL;
	}
	else if (NULL != snmpitem)
		return FAIL;

	ipmiitem = (ZBX_DC_IPMIITEM *)zbx_hashset_search(&dbsync_env.cache->ipmiitems, &item->itemid);
	if (ITEM_TYPE_IPMI == item->type)
	{
		if (NULL == ipmiitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[13], ipmiitem->ipmi_sensor))
			return FAIL;
	}
	else if (NULL != ipmiitem)
		return FAIL;

	trapitem = (ZBX_DC_TRAPITEM *)zbx_hashset_search(&dbsync_env.cache->trapitems, &item->itemid);
	if (ITEM_TYPE_TRAPPER == item->type && '\0' != *dbrow[15])
	{
		if (NULL == trapitem)
			return FAIL;

		zbx_trim_str_list(dbrow[15], ',');

		if (FAIL == dbsync_compare_str(dbrow[15], trapitem->trapper_hosts))
			return FAIL;
	}
	else if (NULL != trapitem)
		return FAIL;

	logitem = (ZBX_DC_LOGITEM *)zbx_hashset_search(&dbsync_env.cache->logitems, &item->itemid);
	if (ITEM_VALUE_TYPE_LOG == item->value_type && '\0' != *dbrow[16])
	{
		if (NULL == logitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[16], logitem->logtimefmt))
			return FAIL;
	}
	else if (NULL != logitem)
		return FAIL;

	dbitem = (ZBX_DC_DBITEM *)zbx_hashset_search(&dbsync_env.cache->dbitems, &item->itemid);
	if (ITEM_TYPE_DB_MONITOR == item->type && '\0' != *dbrow[17])
	{
		if (NULL == dbitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[17], dbitem->params))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[20], dbitem->username))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[21], dbitem->password))
			return FAIL;
	}
	else if (NULL != dbitem)
		return FAIL;

	sshitem = (ZBX_DC_SSHITEM *)zbx_hashset_search(&dbsync_env.cache->sshitems, &item->itemid);
	if (ITEM_TYPE_SSH == item->type)
	{
		if (NULL == sshitem)
			return FAIL;

		if (FAIL == dbsync_compare_uchar(dbrow[19], sshitem->authtype))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[20], sshitem->username))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[21], sshitem->password))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[22], sshitem->publickey))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[23], sshitem->privatekey))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[17], sshitem->params))
			return FAIL;
	}
	else if (NULL != sshitem)
		return FAIL;

	telnetitem = (ZBX_DC_TELNETITEM *)zbx_hashset_search(&dbsync_env.cache->telnetitems, &item->itemid);
	if (ITEM_TYPE_TELNET == item->type)
	{
		if (NULL == telnetitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[20], telnetitem->username))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[21], telnetitem->password))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[17], telnetitem->params))
			return FAIL;
	}
	else if (NULL != telnetitem)
		return FAIL;

	simpleitem = (ZBX_DC_SIMPLEITEM *)zbx_hashset_search(&dbsync_env.cache->simpleitems, &item->itemid);
	if (ITEM_TYPE_SIMPLE == item->type)
	{
		if (NULL == simpleitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[20], simpleitem->username))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[21], simpleitem->password))
			return FAIL;
	}
	else if (NULL != simpleitem)
		return FAIL;

	jmxitem = (ZBX_DC_JMXITEM *)zbx_hashset_search(&dbsync_env.cache->jmxitems, &item->itemid);
	if (ITEM_TYPE_JMX == item->type)
	{
		if (NULL == jmxitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[20], jmxitem->username))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[21], jmxitem->password))
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[37], jmxitem->jmx_endpoint))
			return FAIL;
	}
	else if (NULL != jmxitem)
		return FAIL;

	calcitem = (ZBX_DC_CALCITEM *)zbx_hashset_search(&dbsync_env.cache->calcitems, &item->itemid);
	if (ITEM_TYPE_CALCULATED == item->type)
	{
		if (NULL == calcitem)
			return FAIL;

		if (FAIL == dbsync_compare_str(dbrow[17], calcitem->params))
			return FAIL;
	}
	else if (NULL != calcitem)
		return FAIL;

	depitem = (ZBX_DC_DEPENDENTITEM *)zbx_hashset_search(&dbsync_env.cache->dependentitems, &item->itemid);
	if (ITEM_TYPE_DEPENDENT == item->type)
	{
		if (NULL == depitem)
			return FAIL;

		if (FAIL == dbsync_compare_uint64(dbrow[38], depitem->master_itemid))
			return FAIL;
	}
	else if (NULL != depitem)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_item_preproc_row                                          *
 *                                                                            *
 * Purpose: applies necessary preprocessing before row is compared/used       *
 *                                                                            *
 * Parameter: row - [IN] the row to preprocess                                *
 *                                                                            *
 * Return value: the preprocessed row                                         *
 *                                                                            *
 * Comments: The row preprocessing can be used to expand user macros in       *
 *           some columns.                                                    *
 *                                                                            *
 ******************************************************************************/
static char	**dbsync_item_preproc_row(char **row)
{
#define ZBX_DBSYNC_ITEM_COLUMN_DELAY	0x01
#define ZBX_DBSYNC_ITEM_COLUMN_HISTORY	0x02
#define ZBX_DBSYNC_ITEM_COLUMN_TRENDS	0x04

	zbx_uint64_t	hostid;
	unsigned char	flags = 0;

	/* return the original row if user macros are not used in target columns */

	if (SUCCEED == dbsync_check_row_macros(row, 14))
		flags |= ZBX_DBSYNC_ITEM_COLUMN_DELAY;

	if (SUCCEED == dbsync_check_row_macros(row, 31))
		flags |= ZBX_DBSYNC_ITEM_COLUMN_HISTORY;

	if (SUCCEED == dbsync_check_row_macros(row, 32))
		flags |= ZBX_DBSYNC_ITEM_COLUMN_TRENDS;

	if (0 == flags)
		return row;

	/* get associated host identifier */
	ZBX_STR2UINT64(hostid, row[1]);

	/* expand user macros */

	if (0 != (flags & ZBX_DBSYNC_ITEM_COLUMN_DELAY))
		row[14] = zbx_dc_expand_user_macros(row[14], &hostid, 1, NULL);

	if (0 != (flags & ZBX_DBSYNC_ITEM_COLUMN_HISTORY))
		row[31] = zbx_dc_expand_user_macros(row[31], &hostid, 1, NULL);

	if (0 != (flags & ZBX_DBSYNC_ITEM_COLUMN_TRENDS))
		row[32] = zbx_dc_expand_user_macros(row[32], &hostid, 1, NULL);

	return row;

#undef ZBX_DBSYNC_ITEM_COLUMN_DELAY
#undef ZBX_DBSYNC_ITEM_COLUMN_HISTORY
#undef ZBX_DBSYNC_ITEM_COLUMN_TRENDS
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_items                                         *
 *                                                                            *
 * Purpose: compares items table with cached configuration data               *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_items(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_ITEM		*item;
	char			**row;

	if (NULL == (result = DBselect(
			"select i.itemid,i.hostid,i.status,i.type,i.value_type,i.key_,"
				"i.snmp_community,i.snmp_oid,i.port,i.snmpv3_securityname,i.snmpv3_securitylevel,"
				"i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.ipmi_sensor,i.delay,"
				"i.trapper_hosts,i.logtimefmt,i.params,i.state,i.authtype,i.username,i.password,"
				"i.publickey,i.privatekey,i.flags,i.interfaceid,i.snmpv3_authprotocol,"
				"i.snmpv3_privprotocol,i.snmpv3_contextname,i.lastlogsize,i.mtime,"
				"i.history,i.trends,i.inventory_link,i.valuemapid,i.units,i.error,i.jmx_endpoint,"
				"i.master_itemid"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status in (%d,%d)"
				" and i.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 39, dbsync_item_preproc_row);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->items.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		row = dbsync_preproc_row(sync, dbrow);

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&dbsync_env.cache->items, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_item(item, row))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, row);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->items, &iter);
	while (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &item->itemid))
			dbsync_add_row(sync, item->itemid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_trigger                                           *
 *                                                                            *
 * Purpose: compares triggers table row with cached configuration data        *
 *                                                                            *
 * Parameter: trigger - [IN] the cached trigger                               *
 *            row     - [IN] the database row                                 *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_trigger(const ZBX_DC_TRIGGER *trigger, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_str(dbrow[1], trigger->description))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[2], trigger->expression))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[4], trigger->priority))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[5], trigger->type))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[9], trigger->status))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[10], trigger->recovery_mode))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[11], trigger->recovery_expression))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[12], trigger->correlation_mode))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[13], trigger->correlation_tag))
		return FAIL;

	return SUCCEED;
}

#define ZBX_DBSYNC_TRIGGER_COLUMN_EXPRESSION		0x01
#define ZBX_DBSYNC_TRIGGER_COLUMN_RECOVERY_EXPRESSION	0x02

/******************************************************************************
 *                                                                            *
 * Function: dbsync_trigger_preproc_row                                       *
 *                                                                            *
 * Purpose: applies necessary preprocessing before row is compared/used       *
 *                                                                            *
 * Parameter: row - [IN] the row to preprocess                                *
 *                                                                            *
 * Return value: the preprocessed row                                         *
 *                                                                            *
 * Comments: The row preprocessing can be used to expand user macros in       *
 *           some columns.                                                    *
 *                                                                            *
 ******************************************************************************/
static char	**dbsync_trigger_preproc_row(char **row)
{
	zbx_vector_uint64_t	hostids, functionids;
	unsigned char		flags = 0;

	/* return the original row if user macros are not used in target columns */

	if (SUCCEED == dbsync_check_row_macros(row, 2))
		flags |= ZBX_DBSYNC_TRIGGER_COLUMN_EXPRESSION;

	if (SUCCEED == dbsync_check_row_macros(row, 11))
		flags |= ZBX_DBSYNC_TRIGGER_COLUMN_RECOVERY_EXPRESSION;

	if (0 == flags)
		return row;

	/* get associated host identifiers */

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&functionids);

	get_functionids(&functionids, row[2]);
	get_functionids(&functionids, row[11]);

	zbx_dc_get_hostids_by_functionids(functionids.values, functionids.values_num, &hostids);

	/* expand user macros */

	if (0 != (flags & ZBX_DBSYNC_TRIGGER_COLUMN_EXPRESSION))
	{
		row[2] = zbx_dc_expand_user_macros(row[2], hostids.values, hostids.values_num,
				dbsync_numeric_validator);
	}

	if (0 != (flags & ZBX_DBSYNC_TRIGGER_COLUMN_RECOVERY_EXPRESSION))
	{
		row[11] = zbx_dc_expand_user_macros(row[11], hostids.values, hostids.values_num,
				dbsync_numeric_validator);
	}

	zbx_vector_uint64_destroy(&functionids);
	zbx_vector_uint64_destroy(&hostids);

	return row;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_triggers                                      *
 *                                                                            *
 * Purpose: compares triggers table with cached configuration data            *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_triggers(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_TRIGGER		*trigger;
	char			**row;

	if (NULL == (result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,t.error,t.priority,t.type,t.value,"
				"t.state,t.lastchange,t.status,t.recovery_mode,t.recovery_expression,"
				"t.correlation_mode,t.correlation_tag"
			" from hosts h,items i,functions f,triggers t"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=t.triggerid"
				" and h.status in (%d,%d)"
				" and t.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 14, dbsync_trigger_preproc_row);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->triggers.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		row = dbsync_preproc_row(sync, dbrow);

		if (NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&dbsync_env.cache->triggers, &rowid)))
		{
			dbsync_add_row(sync, rowid, ZBX_DBSYNC_ROW_ADD, row);
		}
		else
		{
			if (FAIL == dbsync_compare_trigger(trigger, row))
				dbsync_add_row(sync, rowid, ZBX_DBSYNC_ROW_UPDATE, row);
		}
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->triggers, &iter);
	while (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &trigger->triggerid))
			dbsync_add_row(sync, trigger->triggerid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_trigger_dependency                            *
 *                                                                            *
 * Purpose: compares trigger_depends table with cached configuration data     *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_trigger_dependency(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		deps;
	zbx_hashset_iter_t	iter;
	ZBX_DC_TRIGGER_DEPLIST	*dep_down, *dep_up;
	zbx_uint64_pair_t	*dep, dep_local;
	char			down_s[MAX_ID_LEN + 1], up_s[MAX_ID_LEN + 1];
	char			*del_row[2] = {down_s, up_s};
	int			i;

	if (NULL == (result = DBselect(
			"select distinct d.triggerid_down,d.triggerid_up"
			" from trigger_depends d,triggers t,hosts h,items i,functions f"
			" where t.triggerid=d.triggerid_down"
				" and t.flags<>%d"
				" and h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=d.triggerid_down"
				" and h.status in (%d,%d)",
				ZBX_FLAG_DISCOVERY_PROTOTYPE, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&deps, 100, ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC);

	/* index all host->template links */
	zbx_hashset_iter_reset(&dbsync_env.cache->trigdeps, &iter);
	while (NULL != (dep_down = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_iter_next(&iter)))
	{
		dep_local.first = dep_down->triggerid;

		for (i = 0; i < dep_down->dependencies.values_num; i++)
		{
			dep_up = (ZBX_DC_TRIGGER_DEPLIST *)dep_down->dependencies.values[i];
			dep_local.second = dep_up->triggerid;
			zbx_hashset_insert(&deps, &dep_local, sizeof(dep_local));
		}
	}

	/* add new rows, remove existing rows from index */
	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(dep_local.first, dbrow[0]);
		ZBX_STR2UINT64(dep_local.second, dbrow[1]);

		if (NULL == (dep = (zbx_uint64_pair_t *)zbx_hashset_search(&deps, &dep_local)))
			dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_ADD, dbrow);
		else
			zbx_hashset_remove_direct(&deps, dep);
	}

	/* add removed rows */
	zbx_hashset_iter_reset(&deps, &iter);
	while (NULL != (dep = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_snprintf(down_s, sizeof(down_s), ZBX_FS_UI64, dep->first);
		zbx_snprintf(up_s, sizeof(up_s), ZBX_FS_UI64, dep->second);
		dbsync_add_row(sync, 0, ZBX_DBSYNC_ROW_REMOVE, del_row);
	}

	DBfree_result(result);
	zbx_hashset_destroy(&deps);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_function                                          *
 *                                                                            *
 * Purpose: compares functions table row with cached configuration data       *
 *                                                                            *
 * Parameter: function - [IN] the cached function                             *
 *            row      - [IN] the database row                                *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_function(const ZBX_DC_FUNCTION *function, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[0], function->itemid))
		return FAIL;

	if (FAIL == dbsync_compare_uint64(dbrow[4], function->triggerid))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[2], function->function))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], function->parameter))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_functions                                     *
 *                                                                            *
 * Purpose: compares functions table with cached configuration data           *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_functions(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_FUNCTION		*function;

	if (NULL == (result = DBselect(
			"select i.itemid,f.functionid,f.function,f.parameter,t.triggerid"
			" from hosts h,items i,functions f,triggers t"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=t.triggerid"
				" and h.status in (%d,%d)"
				" and t.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 5, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->functions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[1]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&dbsync_env.cache->functions, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_function(function, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->functions, &iter);
	while (NULL != (function = (ZBX_DC_FUNCTION *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &function->functionid))
			dbsync_add_row(sync, function->functionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_expression                                        *
 *                                                                            *
 * Purpose: compares expressions table row with cached configuration data     *
 *                                                                            *
 * Parameter: expression - [IN] the cached expression                         *
 *            row        - [IN] the database row                              *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_expression(const ZBX_DC_EXPRESSION *expression, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_str(dbrow[0], expression->regexp))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[2], expression->expression))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[3], expression->type))
		return FAIL;

	if (*dbrow[4] != expression->delimiter)
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[5], expression->case_sensitive))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_exprssions                                    *
 *                                                                            *
 * Purpose: compares expressions, regexps tables with cached configuration    *
 *          data                                                              *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_expressions(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_EXPRESSION	*expression;

	if (NULL == (result = DBselect(
			"select r.name,e.expressionid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
			" from regexps r,expressions e"
			" where r.regexpid=e.regexpid")))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 6, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->expressions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[1]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (expression = (ZBX_DC_EXPRESSION *)zbx_hashset_search(&dbsync_env.cache->expressions,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_expression(expression, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->expressions, &iter);
	while (NULL != (expression = (ZBX_DC_EXPRESSION *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &expression->expressionid))
			dbsync_add_row(sync, expression->expressionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_action                                            *
 *                                                                            *
 * Purpose: compares actions table row with cached configuration data         *
 *                                                                            *
 * Parameter: action - [IN] the cached action                                 *
 *            row    - [IN] the database row                                  *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_action(const zbx_dc_action_t *action, const DB_ROW dbrow)
{

	if (FAIL == dbsync_compare_uchar(dbrow[1], action->eventsource))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], action->evaltype))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], action->formula))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_actions                                       *
 *                                                                            *
 * Purpose: compares actions table with cached configuration data             *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_actions(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_action_t		*action;

	if (NULL == (result = DBselect(
			"select actionid,eventsource,evaltype,formula"
			" from actions"
			" where status=%d",
			ACTION_STATUS_ACTIVE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->actions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&dbsync_env.cache->actions, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_action(action, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->actions, &iter);
	while (NULL != (action = (zbx_dc_action_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &action->actionid))
			dbsync_add_row(sync, action->actionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_action_op                                         *
 *                                                                            *
 * Purpose: compares action opereation class and flushes update row if        *
 *          necessary                                                         *
 *                                                                            *
 * Parameter: sync     - [OUT] the changeset                                  *
 *            actionid - [IN] the action identifier                           *
 *            opflags  - [IN] the action operation class flags                *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_compare_action_op(zbx_dbsync_t *sync, zbx_uint64_t actionid, unsigned char opflags)
{
	zbx_dc_action_t	*action;

	if (0 == actionid)
		return;

	if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&dbsync_env.cache->actions, &actionid)) ||
			opflags != action->opflags)
	{
		char	actionid_s[MAX_ID_LEN], opflags_s[MAX_ID_LEN];
		char	*row[] = {actionid_s, opflags_s};

		zbx_snprintf(actionid_s, sizeof(actionid_s), ZBX_FS_UI64, actionid);
		zbx_snprintf(opflags_s, sizeof(opflags_s), "%d", opflags);

		dbsync_add_row(sync, actionid, ZBX_DBSYNC_ROW_UPDATE, (DB_ROW)row);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_action_ops                                    *
 *                                                                            *
 * Purpose: compares actions by operation class                               *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_action_ops(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_uint64_t		rowid, actionid = 0;
	unsigned char		opflags = ZBX_ACTION_OPCLASS_NONE;

	if (NULL == (result = DBselect(
			"select a.actionid,o.recovery"
			" from actions a"
			" left join operations o"
				" on a.actionid=o.actionid"
			" where a.status=%d"
			" group by a.actionid,o.recovery"
			" order by a.actionid",
			ACTION_STATUS_ACTIVE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 2, NULL);

	while (NULL != (dbrow = DBfetch(result)))
	{
		ZBX_STR2UINT64(rowid, dbrow[0]);

		if (actionid != rowid)
		{
			dbsync_compare_action_op(sync, actionid, opflags);
			actionid = rowid;
			opflags = ZBX_ACTION_OPCLASS_NONE;
		}

		if (SUCCEED == DBis_null(dbrow[1]))
			continue;

		switch (atoi(dbrow[1]))
		{
			case 0:
				opflags |= ZBX_ACTION_OPCLASS_NORMAL;
				break;
			case 1:
				opflags |= ZBX_ACTION_OPCLASS_RECOVERY;
				break;
			case 2:
				opflags |= ZBX_ACTION_OPCLASS_ACKNOWLEDGE;
				break;
		}
	}

	dbsync_compare_action_op(sync, actionid, opflags);

	DBfree_result(result);

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_action_condition                                  *
 *                                                                            *
 * Purpose: compares conditions table row with cached configuration data      *
 *                                                                            *
 * Parameter: condition - [IN] the cached action condition                    *
 *            row       - [IN] the database row                               *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_action_condition(const zbx_dc_action_condition_t *condition, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uchar(dbrow[2], condition->conditiontype))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[3], condition->op))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[4], condition->value))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[5], condition->value2))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_action_conditions                             *
 *                                                                            *
 * Purpose: compares conditions table with cached configuration data          *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_action_conditions(zbx_dbsync_t *sync)
{
	DB_ROW				dbrow;
	DB_RESULT			result;
	zbx_hashset_t			ids;
	zbx_hashset_iter_t		iter;
	zbx_uint64_t			rowid;
	zbx_dc_action_condition_t	*condition;

	if (NULL == (result = DBselect(
			"select c.conditionid,c.actionid,c.conditiontype,c.operator,c.value,c.value2"
			" from conditions c,actions a"
			" where c.actionid=a.actionid"
				" and a.status=%d",
			ACTION_STATUS_ACTIVE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 6, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->action_conditions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (condition = (zbx_dc_action_condition_t *)zbx_hashset_search(
				&dbsync_env.cache->action_conditions, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_action_condition(condition, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->action_conditions, &iter);
	while (NULL != (condition = (zbx_dc_action_condition_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &condition->conditionid))
			dbsync_add_row(sync, condition->conditionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_trigger_tag                                       *
 *                                                                            *
 * Purpose: compares trigger tags table row with cached configuration data    *
 *                                                                            *
 * Parameter: tag - [IN] the cached trigger tag                               *
 *            row - [IN] the database row                                     *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_trigger_tag(const zbx_dc_trigger_tag_t *tag, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], tag->triggerid))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[2], tag->tag))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], tag->value))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_trigger_tags                                  *
 *                                                                            *
 * Purpose: compares trigger tags table with cached configuration data        *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_trigger_tags(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_trigger_tag_t	*trigger_tag;

	if (NULL == (result = DBselect(
			"select distinct tt.triggertagid,tt.triggerid,tt.tag,tt.value"
			" from trigger_tag tt,triggers t,hosts h,items i,functions f"
			" where t.triggerid=tt.triggerid"
				" and t.flags<>%d"
				" and h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=tt.triggerid"
				" and h.status in (%d,%d)",
				ZBX_FLAG_DISCOVERY_PROTOTYPE, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->trigger_tags.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (trigger_tag = (zbx_dc_trigger_tag_t *)zbx_hashset_search(&dbsync_env.cache->trigger_tags,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_trigger_tag(trigger_tag, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->trigger_tags, &iter);
	while (NULL != (trigger_tag = (zbx_dc_trigger_tag_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &trigger_tag->triggertagid))
			dbsync_add_row(sync, trigger_tag->triggertagid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_correlation                                       *
 *                                                                            *
 * Purpose: compares correlation table row with cached configuration data     *
 *                                                                            *
 * Parameter: correlation - [IN] the cached correlation rule                  *
 *            row         - [IN] the database row                             *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_correlation(const zbx_dc_correlation_t *correlation, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_str(dbrow[1], correlation->name))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], correlation->evaltype))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], correlation->formula))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_correlations                                  *
 *                                                                            *
 * Purpose: compares correlation table with cached configuration data         *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_correlations(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_correlation_t	*correlation;

	if (NULL == (result = DBselect(
			"select correlationid,name,evaltype,formula"
			" from correlation"
			" where status=%d",
			ZBX_CORRELATION_ENABLED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 4, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->correlations.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&dbsync_env.cache->correlations,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_correlation(correlation, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->correlations, &iter);
	while (NULL != (correlation = (zbx_dc_correlation_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &correlation->correlationid))
			dbsync_add_row(sync, correlation->correlationid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_corr_condition                                    *
 *                                                                            *
 * Purpose: compares correlation condition tables dbrow with cached             *
 *          configuration data                                                *
 *                                                                            *
 * Parameter: corr_condition - [IN] the cached correlation condition          *
 *            row            - [IN] the database row                          *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_corr_condition(const zbx_dc_corr_condition_t *corr_condition, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], corr_condition->correlationid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], corr_condition->type))
		return FAIL;

	switch (corr_condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			if (FAIL == dbsync_compare_str(dbrow[3], corr_condition->data.tag.tag))
				return FAIL;
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			if (FAIL == dbsync_compare_str(dbrow[4], corr_condition->data.tag_value.tag))
				return FAIL;
			if (FAIL == dbsync_compare_str(dbrow[5], corr_condition->data.tag_value.value))
				return FAIL;
			if (FAIL == dbsync_compare_uchar(dbrow[6], corr_condition->data.tag_value.op))
				return FAIL;
			break;
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			if (FAIL == dbsync_compare_uint64(dbrow[7], corr_condition->data.group.groupid))
				return FAIL;
			if (FAIL == dbsync_compare_uchar(dbrow[8], corr_condition->data.group.op))
				return FAIL;
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			if (FAIL == dbsync_compare_str(dbrow[9], corr_condition->data.tag_pair.oldtag))
				return FAIL;
			if (FAIL == dbsync_compare_str(dbrow[10], corr_condition->data.tag_pair.newtag))
				return FAIL;
			break;
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_corr_conditions                               *
 *                                                                            *
 * Purpose: compares correlation condition tables with cached configuration   *
 *          data                                                              *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_corr_conditions(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_corr_condition_t	*corr_condition;

	if (NULL == (result = DBselect(
			"select cc.corr_conditionid,cc.correlationid,cc.type,cct.tag,cctv.tag,cctv.value,cctv.operator,"
				" ccg.groupid,ccg.operator,cctp.oldtag,cctp.newtag"
			" from correlation c,corr_condition cc"
			" left join corr_condition_tag cct"
				" on cct.corr_conditionid=cc.corr_conditionid"
			" left join corr_condition_tagvalue cctv"
				" on cctv.corr_conditionid=cc.corr_conditionid"
			" left join corr_condition_group ccg"
				" on ccg.corr_conditionid=cc.corr_conditionid"
			" left join corr_condition_tagpair cctp"
				" on cctp.corr_conditionid=cc.corr_conditionid"
			" where c.correlationid=cc.correlationid"
				" and c.status=%d",
			ZBX_CORRELATION_ENABLED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 11, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->corr_conditions.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (corr_condition = (zbx_dc_corr_condition_t *)zbx_hashset_search(
				&dbsync_env.cache->corr_conditions, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_corr_condition(corr_condition, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->corr_conditions, &iter);
	while (NULL != (corr_condition = (zbx_dc_corr_condition_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &corr_condition->corr_conditionid))
			dbsync_add_row(sync, corr_condition->corr_conditionid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_corr_operation                                    *
 *                                                                            *
 * Purpose: compares correlation operation tables dbrow with cached             *
 *          configuration data                                                *
 *                                                                            *
 * Parameter: corr_operation - [IN] the cached correlation operation          *
 *            row            - [IN] the database row                          *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_corr_operation(const zbx_dc_corr_operation_t *corr_operation, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], corr_operation->correlationid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], corr_operation->type))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_corr_operations                               *
 *                                                                            *
 * Purpose: compares correlation operation tables with cached configuration   *
 *          data                                                              *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_corr_operations(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_corr_operation_t	*corr_operation;

	if (NULL == (result = DBselect(
			"select co.corr_operationid,co.correlationid,co.type"
			" from correlation c,corr_operation co"
			" where c.correlationid=co.correlationid"
				" and c.status=%d",
			ZBX_CORRELATION_ENABLED)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 3, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->corr_operations.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (corr_operation = (zbx_dc_corr_operation_t *)zbx_hashset_search(
				&dbsync_env.cache->corr_operations, &rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_corr_operation(corr_operation, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->corr_operations, &iter);
	while (NULL != (corr_operation = (zbx_dc_corr_operation_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &corr_operation->corr_operationid))
			dbsync_add_row(sync, corr_operation->corr_operationid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_host_group                                        *
 *                                                                            *
 * Purpose: compares host group table row with cached configuration data      *
 *                                                                            *
 * Parameter: group - [IN] the cached host group                              *
 *            row   - [IN] the database row                                   *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host_group(const zbx_dc_hostgroup_t *group, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_str(dbrow[1], group->name))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_host_groups                                   *
 *                                                                            *
 * Purpose: compares host groups table with cached configuration data         *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_groups(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_hostgroup_t	*group;

	if (NULL == (result = DBselect("select groupid,name from groups")))
		return FAIL;

	dbsync_prepare(sync, 2, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->hostgroups.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&dbsync_env.cache->hostgroups, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_host_group(group, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->hostgroups, &iter);
	while (NULL != (group = (zbx_dc_hostgroup_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &group->groupid))
			dbsync_add_row(sync, group->groupid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_item_preproc                                      *
 *                                                                            *
 * Purpose: compares item preproc table row with cached configuration data    *
 *                                                                            *
 * Parameter: group - [IN] the cached item preprocessing operation            *
 *            row   - [IN] the database row                                   *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_item_preproc(const zbx_dc_preproc_op_t *preproc, const DB_ROW dbrow)
{
	if (FAIL == dbsync_compare_uint64(dbrow[1], preproc->itemid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(dbrow[2], preproc->type))
		return FAIL;

	if (FAIL == dbsync_compare_str(dbrow[3], preproc->params))
		return FAIL;

	if (FAIL == dbsync_compare_int(dbrow[4], preproc->step))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_item_preprocessing                            *
 *                                                                            *
 * Purpose: compares item preproc tables with cached configuration data       *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_item_preprocs(zbx_dbsync_t *sync)
{
	DB_ROW			dbrow;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	zbx_dc_preproc_op_t	*preproc;

	if (NULL == (result = DBselect(
			"select pp.item_preprocid,pp.itemid,pp.type,pp.params,pp.step"
			" from item_preproc pp,items i,hosts h"
			" where pp.itemid=i.itemid"
				" and i.hostid=h.hostid"
				" and h.status in (%d,%d)"
				" and i.flags<>%d"
			" order by pp.itemid",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	dbsync_prepare(sync, 5, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		return SUCCEED;
	}

	zbx_hashset_create(&ids, dbsync_env.cache->hostgroups.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (dbrow = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, dbrow[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (preproc = (zbx_dc_preproc_op_t *)zbx_hashset_search(&dbsync_env.cache->preprocops,
				&rowid)))
		{
			tag = ZBX_DBSYNC_ROW_ADD;
		}
		else if (FAIL == dbsync_compare_item_preproc(preproc, dbrow))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, dbrow);
	}

	zbx_hashset_iter_reset(&dbsync_env.cache->preprocops, &iter);
	while (NULL != (preproc = (zbx_dc_preproc_op_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &preproc->item_preprocid))
			dbsync_add_row(sync, preproc->item_preprocid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}
