/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxdbhigh.h"

#include "zbxcrypto.h"
#include "zbxnum.h"
#include "zbx_host_constants.h"
#include "zbxalgo.h"
#include "zbxdb.h"

#define ZBX_DB_WAIT_DOWN	10

#define ZBX_MAX_SQL_SIZE	262144	/* 256KB */
#ifndef ZBX_MAX_OVERFLOW_SQL_SIZE
#	define ZBX_MAX_OVERFLOW_SQL_SIZE	ZBX_MAX_SQL_SIZE
#elif 0 != ZBX_MAX_OVERFLOW_SQL_SIZE && \
	(1024 > ZBX_MAX_OVERFLOW_SQL_SIZE || ZBX_MAX_OVERFLOW_SQL_SIZE > ZBX_MAX_SQL_SIZE)
#error ZBX_MAX_OVERFLOW_SQL_SIZE is out of range
#endif

#ifdef HAVE_MULTIROW_INSERT
#	define ZBX_ROW_DL	","
#else
#	define ZBX_ROW_DL	";\n"
#endif

ZBX_PTR_VECTOR_IMPL(db_event, zbx_db_event *)
ZBX_PTR_VECTOR_IMPL(events_ptr, zbx_event_t *)
ZBX_PTR_VECTOR_IMPL(escalation_new_ptr, zbx_escalation_new_t *)
ZBX_PTR_VECTOR_IMPL(item_diff_ptr, zbx_item_diff_t *)
ZBX_PTR_VECTOR_IMPL(trigger_diff_ptr, zbx_trigger_diff_t *)

void	zbx_item_diff_free(zbx_item_diff_t *item_diff)
{
	zbx_free(item_diff);
}

int	zbx_item_diff_compare_func(const void *d1, const void *d2)
{
	const zbx_item_diff_t    *id_1 = *(const zbx_item_diff_t **)d1;
	const zbx_item_diff_t    *id_2 = *(const zbx_item_diff_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(id_1->itemid, id_2->itemid);

	return 0;
}

int	zbx_trigger_diff_compare_func(const void *d1, const void *d2)
{
	const zbx_trigger_diff_t    *id_1 = *(const zbx_trigger_diff_t **)d1;
	const zbx_trigger_diff_t    *id_2 = *(const zbx_trigger_diff_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(id_1->triggerid, id_2->triggerid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: writes a json entry in DB with the result for the front-end       *
 *                                                                            *
 * Parameters: version - [IN] entry of DB versions                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_flush_version_requirements(const char *version)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_DB_OK > zbx_db_execute("update config set dbversion_status='%s'", version))
		zabbix_log(LOG_LEVEL_CRIT, "Failed to set dbversion_status");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: verify that Zabbix server/proxy will start with provided DB version  *
 *          and configuration                                                    *
 *                                                                               *
 * Parameters: info              - [IN] DB version information                   *
 *             allow_unsupported - [IN] value of AllowUnsupportedDBVersions flag *
 *             program_type      - [IN]                                          *
 *                                                                               *
 *********************************************************************************/
int	zbx_db_check_version_info(struct zbx_db_version_info_t *info, int allow_unsupported,
		unsigned char program_type)
{
	zbx_db_extract_version_info(info);

	if (DB_VERSION_NOT_SUPPORTED_ERROR == info->flag ||
			DB_VERSION_HIGHER_THAN_MAXIMUM == info->flag || DB_VERSION_LOWER_THAN_MINIMUM == info->flag)
	{
		const char	*program_type_s;
		int		server_db_deprecated;

		program_type_s = get_program_type_string(program_type);

		server_db_deprecated = (DB_VERSION_LOWER_THAN_MINIMUM == info->flag &&
				0 != (program_type & ZBX_PROGRAM_TYPE_SERVER));

		if (0 == allow_unsupported || 0 != server_db_deprecated)
		{
			zabbix_log(LOG_LEVEL_ERR, " ");
			zabbix_log(LOG_LEVEL_ERR, "Unable to start Zabbix %s due to unsupported %s database"
					" version (%s).", program_type_s, info->database,
					info->friendly_current_version);

			if (DB_VERSION_HIGHER_THAN_MAXIMUM == info->flag)
			{
				zabbix_log(LOG_LEVEL_ERR, "Must not be higher than (%s).",
						info->friendly_max_version);
				info->flag = DB_VERSION_HIGHER_THAN_MAXIMUM_ERROR;
			}
			else
			{
				zabbix_log(LOG_LEVEL_ERR, "Must be at least (%s).",
						info->friendly_min_supported_version);
			}

			zabbix_log(LOG_LEVEL_ERR, "Use of supported database version is highly recommended.");

			if (0 == server_db_deprecated)
			{
				zabbix_log(LOG_LEVEL_ERR, "Override by setting AllowUnsupportedDBVersions=1"
						" in Zabbix %s configuration file at your own risk.", program_type_s);
			}

			zabbix_log(LOG_LEVEL_ERR, " ");

			return FAIL;
		}
		else
		{
			zabbix_log(LOG_LEVEL_ERR, " ");
			zabbix_log(LOG_LEVEL_ERR, "Warning! Unsupported %s database version (%s).",
					info->database, info->friendly_current_version);

			if (DB_VERSION_HIGHER_THAN_MAXIMUM == info->flag)
			{
				zabbix_log(LOG_LEVEL_ERR, "Should not be higher than (%s).",
						info->friendly_max_version);
				info->flag = DB_VERSION_HIGHER_THAN_MAXIMUM_WARNING;
			}
			else
			{
				zabbix_log(LOG_LEVEL_ERR, "Should be at least (%s).",
						info->friendly_min_supported_version);
				info->flag = DB_VERSION_NOT_SUPPORTED_WARNING;
			}

			zabbix_log(LOG_LEVEL_ERR, "Use of supported database version is highly recommended.");
			zabbix_log(LOG_LEVEL_ERR, " ");
		}
	}

	return SUCCEED;
}

void	zbx_db_version_info_clear(struct zbx_db_version_info_t *version_info)
{
	zbx_free(version_info->friendly_current_version);
	zbx_free(version_info->extension);
	zbx_free(version_info->ext_friendly_current_version);
}

static char	buf_string[640];

/******************************************************************************
 *                                                                            *
 * Return value: <host> or "???" if host not found                            *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_host_string(zbx_uint64_t hostid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	result = zbx_db_select(
			"select host"
			" from hosts"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	if (NULL != (row = zbx_db_fetch(result)))
		zbx_snprintf(buf_string, sizeof(buf_string), "%s", row[0]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "???");

	zbx_db_free_result(result);

	return buf_string;
}

/******************************************************************************
 *                                                                            *
 * Return value: <host>:<key> or "???" if item not found                      *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_host_key_string(zbx_uint64_t itemid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	result = zbx_db_select(
			"select h.host,i.key_"
			" from hosts h,items i"
			" where h.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			itemid);

	if (NULL != (row = zbx_db_fetch(result)))
		zbx_snprintf(buf_string, sizeof(buf_string), "%s:%s", row[0], row[1]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "???");

	zbx_db_free_result(result);

	return buf_string;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if user has access rights to information - full name,       *
 *          alias, Email, SMS, etc                                            *
 *                                                                            *
 * Parameters: userid           - [IN] user who owns the information          *
 *             recipient_userid - [IN] user who will receive the information  *
 *                                     can be NULL for remote command         *
 *                                                                            *
 * Return value: SUCCEED - if information receiving user has access rights    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Users has access rights or can view personal information only    *
 *           about themselves and other user who belong to their group.       *
 *           "Zabbix Super Admin" can view and has access rights to           *
 *           information about any user.                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_user_permissions(const zbx_uint64_t *userid, const zbx_uint64_t *recipient_userid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		user_type = -1, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == recipient_userid || *userid == *recipient_userid)
		goto out;

	result = zbx_db_select("select r.type from users u,role r where u.roleid=r.roleid and"
			" userid=" ZBX_FS_UI64, *recipient_userid);

	if (NULL != (row = zbx_db_fetch(result)) && FAIL == zbx_db_is_null(row[0]))
		user_type = atoi(row[0]);
	zbx_db_free_result(result);

	if (-1 == user_type)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __func__);
		ret = FAIL;
		goto out;
	}

	if (USER_TYPE_SUPER_ADMIN != user_type)
	{
		/* check if users are from the same user group */
		result = zbx_db_select(
				"select null"
				" from users_groups ug1"
				" where ug1.userid=" ZBX_FS_UI64
					" and exists (select null"
						" from users_groups ug2"
						" where ug1.usrgrpid=ug2.usrgrpid"
							" and ug2.userid=" ZBX_FS_UI64
					")",
				*userid, *recipient_userid);

		if (NULL == zbx_db_fetch(result))
			ret = FAIL;
		zbx_db_free_result(result);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Return value: "Name Surname (Alias)" or "unknown" if user not found        *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_user_string(zbx_uint64_t userid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	result = zbx_db_select("select name,surname,username from users where userid=" ZBX_FS_UI64, userid);

	if (NULL != (row = zbx_db_fetch(result)))
		zbx_snprintf(buf_string, sizeof(buf_string), "%s %s (%s)", row[0], row[1], row[2]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "unknown");

	zbx_db_free_result(result);

	return buf_string;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get user username, name and surname                               *
 *                                                                            *
 * Parameters: userid     - [IN] user id                                      *
 *             username   - [OUT] user alias                                  *
 *             name       - [OUT] user name                                   *
 *             surname    - [OUT] user surname                                *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_get_user_names(zbx_uint64_t userid, char **username, char **name, char **surname)
{
	int		ret = FAIL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (NULL == (result = zbx_db_select(
			"select username,name,surname"
			" from users"
			" where userid=" ZBX_FS_UI64, userid)))
	{
		goto out;
	}

	if (NULL == (row = zbx_db_fetch(result)))
		goto out;

	*username = zbx_strdup(NULL, row[0]);
	*name = zbx_strdup(NULL, row[1]);
	*surname = zbx_strdup(NULL, row[2]);

	ret = SUCCEED;
out:
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: construct a unique host name by the given sample                  *
 *                                                                            *
 * Parameters: host_name_sample - a host name to start constructing from      *
 *             field_name       - field name for host or host visible name    *
 *                                                                            *
 * Return value: unique host name which does not exist in the database        *
 *                                                                            *
 * Comments: the sample cannot be empty                                       *
 *           constructs new by adding "_$(number+1)", where "number"          *
 *           shows count of the sample itself plus already constructed ones   *
 *           host_name_sample is not modified, allocates new memory!          *
 *                                                                            *
 ******************************************************************************/
char	*zbx_db_get_unique_hostname_by_sample(const char *host_name_sample, const char *field_name)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			full_match = 0, i;
	char			*host_name_temp = NULL, *host_name_sample_esc;
	zbx_vector_uint64_t	nums;
	zbx_uint64_t		num = 2;	/* produce alternatives starting from "2" */
	size_t			sz;

	assert(host_name_sample && *host_name_sample);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sample:'%s'", __func__, host_name_sample);

	zbx_vector_uint64_create(&nums);
	zbx_vector_uint64_reserve(&nums, 8);

	sz = strlen(host_name_sample);
	host_name_sample_esc = zbx_db_dyn_escape_like_pattern(host_name_sample);

	result = zbx_db_select(
			"select %s"
			" from hosts"
			" where %s like '%s%%' escape '%c'"
				" and flags<>%d"
				" and status in (%d,%d,%d)",
				field_name, field_name, host_name_sample_esc, ZBX_SQL_LIKE_ESCAPE_CHAR,
			ZBX_FLAG_DISCOVERY_PROTOTYPE,
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE);

	zbx_free(host_name_sample_esc);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	n;
		const char	*p;

		if (0 != strncmp(row[0], host_name_sample, sz))
			continue;

		p = row[0] + sz;

		if ('\0' == *p)
		{
			full_match = 1;
			continue;
		}

		if ('_' != *p || FAIL == zbx_is_uint64(p + 1, &n))
			continue;

		zbx_vector_uint64_append(&nums, n);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(&nums, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 == full_match)
	{
		host_name_temp = zbx_strdup(host_name_temp, host_name_sample);
		goto clean;
	}

	for (i = 0; i < nums.values_num; i++)
	{
		if (num > nums.values[i])
			continue;

		if (num < nums.values[i])	/* found, all others will be bigger */
			break;

		num++;
	}

	host_name_temp = zbx_dsprintf(host_name_temp, "%s_" ZBX_FS_UI64, host_name_sample, num);
clean:
	zbx_vector_uint64_destroy(&nums);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():'%s'", __func__, host_name_temp);

	return host_name_temp;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get corresponding host_inventory field name                       *
 *                                                                            *
 * Parameters: inventory_link - [IN] field link 1..HOST_INVENTORY_FIELD_COUNT *
 *                                                                            *
 * Return value: field name or NULL if value of inventory_link is incorrect   *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_db_get_inventory_field(unsigned char inventory_link)
{
	static const char	*inventory_fields[HOST_INVENTORY_FIELD_COUNT] =
	{
		"type", "type_full", "name", "alias", "os", "os_full", "os_short", "serialno_a", "serialno_b", "tag",
		"asset_tag", "macaddress_a", "macaddress_b", "hardware", "hardware_full", "software", "software_full",
		"software_app_a", "software_app_b", "software_app_c", "software_app_d", "software_app_e", "contact",
		"location", "location_lat", "location_lon", "notes", "chassis", "model", "hw_arch", "vendor",
		"contract_number", "installer_name", "deployment_status", "url_a", "url_b", "url_c", "host_networks",
		"host_netmask", "host_router", "oob_ip", "oob_netmask", "oob_router", "date_hw_purchase",
		"date_hw_install", "date_hw_expiry", "date_hw_decomm", "site_address_a", "site_address_b",
		"site_address_c", "site_city", "site_state", "site_country", "site_zip", "site_rack", "site_notes",
		"poc_1_name", "poc_1_email", "poc_1_phone_a", "poc_1_phone_b", "poc_1_cell", "poc_1_screen",
		"poc_1_notes", "poc_2_name", "poc_2_email", "poc_2_phone_a", "poc_2_phone_b", "poc_2_cell",
		"poc_2_screen", "poc_2_notes"
	};

	if (1 > inventory_link || inventory_link > HOST_INVENTORY_FIELD_COUNT)
		return NULL;

	return inventory_fields[inventory_link - 1];
}

/******************************************************************************
 *                                                                            *
 * Purpose: determine is it a server or a proxy database                      *
 *                                                                            *
 * Return value: ZBX_DB_SERVER - server database                              *
 *               ZBX_DB_PROXY - proxy database                                *
 *               ZBX_DB_UNKNOWN - an error occurred                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_get_database_type(void)
{
	const char	*result_string;
	zbx_db_result_t	result;
	int		ret = ZBX_DB_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (result = zbx_db_select_n("select userid from users", 1)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot select records from \"users\" table");
		goto out;
	}

	if (NULL != zbx_db_fetch(result))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "there is at least 1 record in \"users\" table");
		ret = ZBX_DB_SERVER;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "no records in \"users\" table");
		ret = ZBX_DB_PROXY;
	}

	zbx_db_free_result(result);
out:
	switch (ret)
	{
		case ZBX_DB_SERVER:
			result_string = "ZBX_DB_SERVER";
			break;
		case ZBX_DB_PROXY:
			result_string = "ZBX_DB_PROXY";
			break;
		case ZBX_DB_UNKNOWN:
			result_string = "ZBX_DB_UNKNOWN";
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, result_string);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate that session is active and get associated user data      *
 *                                                                            *
 * Parameters: sessionid - [IN] the session id to validate                    *
 *             user      - [OUT] user information                             *
 *                                                                            *
 * Return value:  SUCCEED - session is active and user data was retrieved     *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_get_user_by_active_session(const char *sessionid, zbx_user_t *user)
{
	char		*sessionid_esc;
	int		ret = FAIL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sessionid:%s", __func__, sessionid);

	sessionid_esc = zbx_db_dyn_escape_string(sessionid);

	if (NULL == (result = zbx_db_select(
			"select u.userid,u.roleid,u.username,r.type"
				" from sessions s,users u,role r"
			" where s.userid=u.userid"
				" and s.sessionid='%s'"
				" and s.status=%d"
				" and u.roleid=r.roleid",
			sessionid_esc, ZBX_SESSION_ACTIVE)))
	{
		goto out;
	}

	if (NULL == (row = zbx_db_fetch(result)))
		goto out;

	ZBX_STR2UINT64(user->userid, row[0]);
	ZBX_STR2UINT64(user->roleid, row[1]);
	user->username = zbx_strdup(NULL, row[2]);
	user->type = atoi(row[3]);

	ret = SUCCEED;
out:
	zbx_db_free_result(result);
	zbx_free(sessionid_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate that token is not expired and is active and then get     *
 *          associated user data                                              *
 *                                                                            *
 * Parameters: formatted_auth_token_hash - [IN] auth token to validate        *
 *             user                      - [OUT] user information             *
 *                                                                            *
 * Return value:  SUCCEED - token is valid and user data was retrieved        *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_get_user_by_auth_token(const char *formatted_auth_token_hash, zbx_user_t *user)
{
	int		ret = FAIL;
	zbx_db_result_t	result = NULL;
	zbx_db_row_t	row;
	time_t		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() auth token:%s", __func__, formatted_auth_token_hash);

	t = time(NULL);

	if ((time_t) - 1 == t)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): failed to get time: %s", __func__, zbx_strerror(errno));
		goto out;
	}

	if (NULL == (result = zbx_db_select(
			"select u.userid,u.roleid,u.username,r.type"
				" from token t,users u,role r"
			" where t.userid=u.userid"
				" and t.token='%s'"
				" and u.roleid=r.roleid"
				" and t.status=%d"
				" and (t.expires_at=%d or t.expires_at > %lu)",
			formatted_auth_token_hash, ZBX_AUTH_TOKEN_ENABLED, ZBX_AUTH_TOKEN_NEVER_EXPIRES,
			(unsigned long)t)))
	{
		goto out;
	}

	if (NULL == (row = zbx_db_fetch(result)))
		goto out;

	ZBX_STR2UINT64(user->userid, row[0]);
	ZBX_STR2UINT64(user->roleid, row[1]);
	user->username = zbx_strdup(NULL, row[2]);
	user->type = atoi(row[3]);
	ret = SUCCEED;
out:
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

void	zbx_user_init(zbx_user_t *user)
{
	user->username = NULL;
}

void	zbx_user_free(zbx_user_t *user)
{
	zbx_free(user->username);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks instanceid value in config table and generates new         *
 *          instance id if its empty                                          *
 *                                                                            *
 * Return value: SUCCEED - valid instance id either exists or was created     *
 *               FAIL    - no valid instance id exists and could not create   *
 *                         one                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_check_instanceid(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;

	result = zbx_db_select("select configid,instanceid from config order by configid");
	if (NULL != (row = zbx_db_fetch(result)))
	{
		if (SUCCEED == zbx_db_is_null(row[1]) || '\0' == *row[1])
		{
			char	*token;

			token = zbx_create_token(0);
			if (ZBX_DB_OK > zbx_db_execute("update config set instanceid='%s' where configid=%s", token, row[0]))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot update instance id in database");
				ret = FAIL;
			}
			zbx_free(token);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot read instance id from database");
		ret = FAIL;
	}
	zbx_db_free_result(result);

	return ret;
}

int	zbx_db_update_software_update_checkid(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;

	result = zbx_db_select("select software_update_checkid from config");
	if (NULL != (row = zbx_db_fetch(result)))
	{
		if (SUCCEED == zbx_db_is_null(row[0]) || '\0' == *row[0])
		{
			char	*token;

			token = zbx_create_token(0);
			if (ZBX_DB_OK > zbx_db_execute("update config set software_update_checkid='%s'", token))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot update software_update_checkid in config table");
				ret = FAIL;
			}
			zbx_free(token);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot read config record from database");
		ret = FAIL;
	}
	zbx_db_free_result(result);

	return ret;
}
