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

#include "alerter_internal.h"

#include "zbxdb.h"
#include "zbxhttp.h"
#include "audit/zbxaudit.h"
#include "zbxalgo.h"
#include "zbxstr.h"

/* For token status, bit mask */
#define ZBX_OAUTH_TOKEN_ACCESS_VALID	1
#define ZBX_OAUTH_TOKEN_REFRESH_VALID	2

#define ZBX_OAUTH_TOKEN_VALID		(ZBX_OAUTH_TOKEN_ACCESS_VALID | ZBX_OAUTH_TOKEN_REFRESH_VALID)

typedef struct
{
	char	*token_url;
	char	*client_id;
	char	*client_secret;

	char	*old_refresh_token;
	char	*refresh_token;

	unsigned char	old_tokens_status;
	unsigned char	tokens_status;

	char	*old_access_token;
	char	*access_token;

	time_t	old_access_token_updated;
	time_t	access_token_updated;

	int	old_access_expires_in;
	int	access_expires_in;
} zbx_oauth_data_t;

static int	oauth_fetch_from_db(zbx_uint64_t mediatypeid, const char *mediatype_name, zbx_oauth_data_t *data,
		char **error)
{
#define SET_ERROR(message) 										\
	do 												\
	{												\
		*error = zbx_dsprintf(NULL, "Access token fetch failed: mediatype \"%s\": "		\
			message, mediatype_name);							\
	}												\
	while(0)
#define CHECK_FOR_NULL(index, message)									\
	do												\
	{												\
		if (SUCCEED == zbx_db_is_null(row[index]) || 0 == strlen(row[index]))			\
		{											\
			SET_ERROR(message);								\
			goto out;									\
		}											\
	}												\
	while(0)

	int		ret = FAIL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select("select token_url,client_id,client_secret,refresh_token,access_token,"
			"access_token_updated,access_expires_in,tokens_status"
			" from media_type_oauth"
			" where mediatypeid="ZBX_FS_UI64, mediatypeid);

	if ((zbx_db_result_t)ZBX_DB_DOWN == result)
	{
		*error = zbx_dsprintf(NULL, "cannot fetch access token: database not available");
		goto out1;
	}

	if (NULL == (row = zbx_db_fetch(result)))
	{
		*error = zbx_dsprintf(NULL, "Access token fetch failed: mediatype \"%s\" requires"
				" OAuth2 to be configured in frontend", mediatype_name);
		goto out;
	}

	CHECK_FOR_NULL(0, "token URL is missing");
	CHECK_FOR_NULL(1, "client ID is missing");
	CHECK_FOR_NULL(2, "client secret is missing");
	CHECK_FOR_NULL(3, "refresh token is missing");
	CHECK_FOR_NULL(4, "access token is missing");

	if (0 == atoi(row[5]))
	{
		SET_ERROR("access token update time is zero");
		goto out;
	}

	if (0 == atoi(row[6]))
	{
		SET_ERROR("access token expire time is zero");
		goto out;
	}

	data->token_url = zbx_strdup(NULL, row[0]);
	data->client_id = zbx_strdup(NULL, row[1]);
	data->client_secret = zbx_strdup(NULL, row[2]);
	data->refresh_token = zbx_strdup(NULL, row[3]);
	data->access_token = zbx_strdup(NULL, row[4]);
	data->access_token_updated = (time_t)atoi(row[5]);
	data->access_expires_in = (time_t)atoi(row[6]);
	data->tokens_status = (unsigned char)atoi(row[7]);

	/* for audit it is necessary to keep the original value from DB to compare with, not the intermediate values
	which may appear later in the sequence of unsuccessful attempts to update tokens */
	data->old_tokens_status = data->tokens_status;

	ret = SUCCEED;
out:
	zbx_db_free_result(result);
out1:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): error:%s", __func__, ZBX_NULL2STR(*error));

	return ret;
#undef CHECK_FOR_NULL
#undef SET_ERROR
}

static int	oauth_access_refresh(zbx_oauth_data_t *data, const char *mediatype_name, long timeout,
		const char *config_source_ip, const char *config_ssl_ca_location, char **error)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(data);
	ZBX_UNUSED(mediatype_name);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	*error = zbx_dsprintf(*error, "OAuth requires curl library. This Zabbix server binary was compiled without curl"
		" library support.");
	return FAIL;
#else

#define ZBX_HTTP_STATUS_CODE_OK  200

#define SET_ERROR(format, ...)											\
	do													\
	{													\
		*error = zbx_dsprintf(NULL, "Access token retrieval failed: mediatype \"%s\": " format,		\
				mediatype_name, __VA_ARGS__);							\
	}													\
	while (0)

	int			ret = FAIL;
	char			*out = NULL, *tmp = NULL;
	size_t			tmp_alloc = 0;
	long			response_code;
	struct zbx_json_parse	jp;
	time_t			sec = time(NULL);
	const char		*header = "Content-Type: application/x-www-form-urlencoded";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	char	*posts = zbx_strdcatf(NULL, "grant_type=refresh_token&client_id=%s&client_secret=%s&refresh_token=%s",
			data->client_id, data->client_secret, data->refresh_token);

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): posts:[%s]", __func__, posts);

	if (SUCCEED != zbx_http_req(data->token_url, header, timeout, NULL, NULL, config_source_ip,
			config_ssl_ca_location, NULL, NULL, &out, posts, &response_code, error))
	{
		goto out;
	}

	tmp = zbx_str_printable_dyn(ZBX_NULL2STR(out));
	zabbix_log(LOG_LEVEL_DEBUG, "%s(): out:[%s]", __func__, tmp);
	zbx_free(tmp);

	if (SUCCEED != zbx_json_open(out, &jp))
	{
		SET_ERROR("%s", zbx_json_strerror());
		goto out;
	}

	if (ZBX_HTTP_STATUS_CODE_OK != response_code)
	{
		int	expected_ret = FAIL;

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "error", &tmp, &tmp_alloc, NULL))
		{
			SET_ERROR("%s", "error field not found in OAuth server response");
			goto out;
		}

		if (0 != strcmp("invalid_grant", tmp))
			expected_ret = NETWORK_ERROR;	/* you may try again */

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "error_description", &tmp, &tmp_alloc, NULL))
		{
			SET_ERROR("%s", "error_description field not found in OAuth server response");
			goto out;
		}

		SET_ERROR("%s", tmp);

		if (NETWORK_ERROR == expected_ret)
		{
			/* remove access token valid bit */
			data->tokens_status = (data->tokens_status & ~ZBX_OAUTH_TOKEN_ACCESS_VALID) &
					ZBX_OAUTH_TOKEN_VALID;
		}
		else /* invalid_grant */
		{
			/* user should renew everything */
			data->tokens_status = 0;
		}

		ret = expected_ret;
	}
	else
	{
		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "token_type", &tmp, &tmp_alloc, NULL))
		{
			SET_ERROR("%s", "token_type field not found in OAuth server response");
			goto out;
		}

		if (0 != strcmp(tmp, "Bearer"))
		{
			SET_ERROR("%s", "token_type is not \"Bearer\" in OAuth server response");
			goto out;
		}

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "access_token", &tmp, &tmp_alloc, NULL))
		{
			SET_ERROR("%s", "access_token field not found in OAuth server response");
			goto out;
		}

		data->old_access_token = data->access_token;
		data->access_token = zbx_strdup(NULL, tmp);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "expires_in", &tmp, &tmp_alloc, NULL))
		{
			SET_ERROR("%s", "expires_in field not found in OAuth server response");
			goto out;
		}

		data->old_access_expires_in = data->access_expires_in;
		data->access_expires_in = atoi(tmp);

		/* request may return the new refresh token */
		if (SUCCEED == zbx_json_value_by_name_dyn(&jp, "refresh_token", &tmp, &tmp_alloc, NULL))
		{
			data->old_refresh_token = data->refresh_token;
			data->refresh_token = zbx_strdup(NULL, tmp);
		}

		data->old_access_token_updated = data->access_token_updated;
		data->access_token_updated = sec;
		ret = SUCCEED;
	}
out:
	zbx_free(posts);
	zbx_free(tmp);
	zbx_free(out);

	if (NULL != *error)
		zabbix_log(LOG_LEVEL_ERR, "%s", *error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
#undef SET_ERROR
#endif
}

static void	oauth_db_update(zbx_uint64_t mediatypeid, zbx_oauth_data_t *data, int fetch_result)
{
	if (SUCCEED != fetch_result)
	{
		data->tokens_status &= ZBX_OAUTH_TOKEN_REFRESH_VALID;

		zbx_db_execute("update media_type_oauth set tokens_status=%hhu"
				" where mediatypeid="ZBX_FS_UI64, data->tokens_status, mediatypeid);
	}
	else
	{
		data->tokens_status |= (ZBX_OAUTH_TOKEN_ACCESS_VALID | ZBX_OAUTH_TOKEN_REFRESH_VALID);

		if (NULL != data->old_refresh_token)	 /* data->refresh_token has changed */
		{
			zbx_db_execute("update media_type_oauth set"
					" access_token='%s',access_token_updated=" ZBX_FS_TIME_T ","
					"access_expires_in=%d,refresh_token='%s',tokens_status=%hhu"
					" where mediatypeid="ZBX_FS_UI64,
					data->access_token, data->access_token_updated, data->access_expires_in,
					data->refresh_token, data->tokens_status,
					mediatypeid);
		}
		else
		{
			zbx_db_execute("update media_type_oauth set"
					" access_token='%s',access_token_updated=" ZBX_FS_TIME_T ","
					"access_expires_in=%d,tokens_status=%hhu"
					" where mediatypeid="ZBX_FS_UI64,
					data->access_token, data->access_token_updated, data->access_expires_in,
					data->tokens_status,
					mediatypeid);
		}
	}
}

static void	oauth_audit(int audit_context_mode, zbx_uint64_t mediatypeid, const char *mediatype_name,
		const zbx_oauth_data_t *data, int fetch_result)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (SUCCEED == fetch_result || data->old_tokens_status != data->tokens_status)
	{
		zbx_audit_entry_t	*entry = zbx_audit_entry_init(mediatypeid, AUDIT_MEDIATYPE_ID, mediatype_name,
				ZBX_AUDIT_ACTION_UPDATE, ZBX_AUDIT_RESOURCE_MEDIATYPE);

		if (data->old_tokens_status != data->tokens_status)
		{
			zbx_audit_entry_update_int(entry, "tokens_status", data->old_tokens_status,
					data->tokens_status);
		}

		if (SUCCEED == fetch_result)
		{
			zbx_audit_entry_update_string(entry, "access_token", ZBX_SECRET_MASK,
					ZBX_SECRET_MASK);
			zbx_audit_entry_update_int(entry, "access_expires_in", data->old_access_expires_in,
					data->access_expires_in);
			zbx_audit_entry_update_int(entry, "access_token_updated", (int)data->old_access_token_updated,
					(int)data->access_token_updated);

			if (NULL != data->old_refresh_token)
			{
				zbx_audit_entry_update_string(entry, "refresh_token", ZBX_SECRET_MASK,
						ZBX_SECRET_MASK);
			}
		}

		zbx_hashset_insert(zbx_get_audit_hashset(), &entry, sizeof(entry));
	}
}

static void	oauth_clean(zbx_oauth_data_t *data)
{
	zbx_free(data->token_url);
	zbx_free(data->client_id);
	zbx_free(data->client_secret);
	zbx_free(data->old_refresh_token);
	zbx_free(data->refresh_token);
	zbx_free(data->old_access_token);
	zbx_free(data->access_token);
}

/*****************************************************************************************
 *                                                                                       *
 * Purpose: get OAuth authorization OAuthBearer used as password                         *
 *                                                                                       *
 * Parameters: mediatypeid            - [IN]                                             *
 *             mediatype_name         - [IN]                                             *
 *             timeout                - [IN] refresh request timeout                     *
 *             maxattempts            - [IN] max attempts on refresh request             *
 *             expire_offset          - [IN] offset before renew access token for OAuth2 *
 *             config_source_ip       - [IN]                                             *
 *             config_ssl_ca_location - [IN]                                             *
 *             oauthbearer            - [OUT]                                            *
 *             expires                - [OUT]                                            *
 *             error                  - [IN/OUT]                                         *
 *                                                                                       *
 * Return value: SUCCEED - function got valid access token successfully                  *
 *               FAIL    - otherwise                                                     *
 *                                                                                       *
 *****************************************************************************************/
int	zbx_oauth_get(zbx_uint64_t mediatypeid, const char *mediatype_name, int timeout, int maxattempts,
		int expire_offset, const char *config_source_ip, const char *config_ssl_ca_location,
		char **oauthbearer, int *expires, char **error)
{
	int			ret;
	zbx_oauth_data_t	data = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = oauth_fetch_from_db(mediatypeid, mediatype_name, &data, error)))
		goto out;

	if (data.access_token_updated + data.access_expires_in - expire_offset < time(NULL))
	{
		char	*suberror = NULL;

		do
		{
			zbx_free(suberror);	/* clear last error */

			ret = oauth_access_refresh(&data, mediatype_name, timeout, config_source_ip,
					config_ssl_ca_location, &suberror);
		}
		while (0 < --maxattempts && NETWORK_ERROR == ret);

		oauth_db_update(mediatypeid, &data, ret);
		oauth_audit(ZBX_AUDIT_ALL_CONTEXT, mediatypeid, mediatype_name, &data, ret);

		if (SUCCEED != ret)
		{
			*error = suberror;
			goto out;
		}
	}

	*oauthbearer = zbx_strdup(*oauthbearer, data.access_token);
	*expires = (int)data.access_token_updated + data.access_expires_in;
out:
	oauth_clean(&data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%s expires:%d", __func__, zbx_result_string(ret),
			(NULL != expires ? *expires : 0));

	return ret;
}
