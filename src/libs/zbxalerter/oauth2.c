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
#include "zbxcacheconfig.h"
#include "zbxalgo.h"
#include "zbxstr.h"

int	zbx_oauth2_fetch(zbx_uint64_t mediatypeid, zbx_oauth2_data_t *data, char **error)
{
#define CHECK_FOR_NULL(index, message)								\
	do {											\
		if (SUCCEED == zbx_db_is_null(row[index]) || 0 == strlen(row[index])) {		\
			*error = zbx_dsprintf(NULL, "Access token fetch failed: " message);	\
			goto out; 								\
		}										\
	} while(0)

	int		ret = FAIL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select("select token_url,client_id,client_secret,refresh_token,access_token,"
			"access_token_updated,access_expires_in,tokens_status"
			" from media_type_oauth"
			" where mediatypeid="ZBX_FS_UI64, mediatypeid);

	if (NULL == (row = zbx_db_fetch(result)))
	{
		*error = zbx_dsprintf(NULL, "Access token fetch failed: mediatype requires authorization for OAuth2");
		goto out;
	}

	CHECK_FOR_NULL(0, "token URL is missing");
	CHECK_FOR_NULL(1, "client ID is missing");
	CHECK_FOR_NULL(2, "client secret is missing");
	CHECK_FOR_NULL(3, "refresh token is missing");
	CHECK_FOR_NULL(4, "access token is missing");

	data->token_url = zbx_strdup(NULL, row[0]);
	data->client_id = zbx_strdup(NULL, row[1]);
	data->client_secret = zbx_strdup(NULL, row[2]);
	data->refresh_token = zbx_strdup(NULL, row[3]);
	data->access_token = zbx_strdup(NULL, row[4]);
	data->access_token_updated = (time_t)atoi(row[5]);
	data->access_expires_in = (time_t)atoi(row[6]);
	data->tokens_status = atoi(row[7]);

	ret = SUCCEED;
out:
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __func__, ZBX_NULL2STR(*error));

	return ret;
#undef CHECK_FOR_NULL
}

int	zbx_oauth2_access_refresh(zbx_oauth2_data_t *data, long timeout, const char *config_source_ip,
		const char *config_ssl_ca_location, char **error)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(data);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	*error = zbx_dsprintf(*error, "missing cURL library");
	return FAIL;
#else
	int			ret = FAIL;
	const char		*header;
	char			*out = NULL, *posts = NULL, *tmp = NULL;
	size_t			tmp_alloc = 0;
	long			response_code;
	struct zbx_json_parse	jp;
	time_t			sec;

	header = "Content-Type: application/x-www-form-urlencoded";

	posts = zbx_strdcatf(posts, "grant_type=refresh_token");
	posts = zbx_strdcatf(posts, "&client_id=%s", data->client_id);
	posts = zbx_strdcatf(posts, "&client_secret=%s", data->client_secret);
	posts = zbx_strdcatf(posts, "&refresh_token=%s", data->refresh_token);

	if (SUCCEED != zbx_http_req(data->token_url, header, timeout, NULL, NULL, config_source_ip,
			config_ssl_ca_location, NULL, NULL, &out, posts, &response_code, error))
	{
		goto out;
	}

	sec = time(NULL);

	if (SUCCEED != zbx_json_open(out, &jp))
	{
		*error = zbx_dsprintf(NULL, "Access token retrieval failed: %s", zbx_json_strerror());
		goto out;
	}

	if (200 != response_code)
	{
		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "error", &tmp, &tmp_alloc, NULL))
		{
			*error = zbx_dsprintf(NULL, "Access token retrieval failed: error field not found");
			goto out;
		}

		if (0 != strcmp("invalid_grant", tmp))
			ret = NETWORK_ERROR;	/* you may try again */

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "error_description", &tmp, &tmp_alloc, NULL))
		{
			*error = zbx_dsprintf(NULL, "Access token retrieval failed: error_description field not found");
			goto out;
		}

		*error = zbx_dsprintf(NULL, "Access token retrieval failed: %s", tmp);

		data->tokens_status = (data->tokens_status & ~ZBX_OAUTH2_TOKEN_ACCESS_VALID) & ZBX_OAUTH2_TOKEN_VALID;
	}
	else
	{
		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "token_type", &tmp, &tmp_alloc, NULL))
		{
			*error = zbx_dsprintf(NULL, "Access token retrieval failed: token_type field not found");
			goto out;
		}

		if (0 != strcmp(tmp, "Bearer"))
		{
			*error = zbx_dsprintf(NULL, "Access token retrieval failed: token_type is not \"Bearer\"");
			goto out;
		}

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "access_token", &tmp, &tmp_alloc, NULL))
		{
			*error = zbx_dsprintf(NULL, "Access token retrieval failed: access_token field not found");
			goto out;
		}

		data->old_access_token = data->access_token;
		data->access_token = zbx_strdup(NULL, tmp);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, "expires_in", &tmp, &tmp_alloc, NULL))
		{
			*error = zbx_dsprintf(NULL, "Access token retrieval failed: expires_in field not found");
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

	return ret;
#endif
}

void	zbx_oauth2_update(zbx_uint64_t mediatypeid, zbx_oauth2_data_t *data, int fetch_result)
{
	data->old_tokens_status = data->tokens_status;

	if (SUCCEED != fetch_result)
	{
		data->tokens_status &= ZBX_OAUTH2_TOKEN_REFRESH_VALID;

		zbx_db_execute("update media_type_oauth set tokens_status=%d"
				" where mediatypeid="ZBX_FS_UI64, data->tokens_status, mediatypeid);
	}
	else
	{
		data->tokens_status |= ZBX_OAUTH2_TOKEN_ACCESS_VALID;
		if (NULL != data->old_refresh_token)
			data->tokens_status |= ZBX_OAUTH2_TOKEN_REFRESH_VALID;

		zbx_db_execute("update media_type_oauth"
				" set access_token='%s',access_token_updated=%d,access_expires_in=%d,"
				"refresh_token='%s',tokens_status=%d"
				" where mediatypeid="ZBX_FS_UI64,
				data->access_token, data->access_token_updated, data->access_expires_in,
				data->refresh_token, data->tokens_status,
				mediatypeid);
	}
}

void	zbx_oauth2_audit(int audit_context_mode, zbx_uint64_t mediatypeid, const char *mediatype_name,
		const zbx_oauth2_data_t *data, int fetch_result)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_entry_t	*entry = zbx_audit_entry_init(mediatypeid, AUDIT_MEDIATYPE_ID, mediatype_name,
							ZBX_AUDIT_ACTION_UPDATE, ZBX_AUDIT_RESOURCE_MEDIATYPE);

	zbx_audit_entry_append_int(entry, ZBX_AUDIT_ACTION_UPDATE, "mediatype.tokens_status", data->old_tokens_status,
			data->tokens_status);

	if (SUCCEED == fetch_result)
	{
		zbx_audit_entry_append_string(entry, ZBX_AUDIT_ACTION_UPDATE, "mediatype.access_token",
				ZBX_MACRO_SECRET_MASK, ZBX_MACRO_SECRET_MASK);
		zbx_audit_entry_append_int(entry, ZBX_AUDIT_ACTION_UPDATE, "mediatype.access_expires_in",
				data->old_access_expires_in, data->access_expires_in);
		zbx_audit_entry_append_int(entry, ZBX_AUDIT_ACTION_UPDATE, "mediatype.access_token_updated",
				data->old_access_token_updated, data->access_token_updated);
		if (NULL != data->old_refresh_token)
		{
			zbx_audit_entry_append_string(entry, ZBX_AUDIT_ACTION_UPDATE, "mediatype.refresh_token",
					ZBX_MACRO_SECRET_MASK, ZBX_MACRO_SECRET_MASK);
		}
	}

	zbx_hashset_insert(zbx_get_audit_hashset(), &entry, sizeof(entry));
}

void	zbx_oauth2_clean(zbx_oauth2_data_t *data)
{
	zbx_free(data->token_url);
	zbx_free(data->client_id);
	zbx_free(data->client_secret);
	zbx_free(data->old_refresh_token);
	zbx_free(data->refresh_token);
	zbx_free(data->old_access_token);
	zbx_free(data->access_token);
}
