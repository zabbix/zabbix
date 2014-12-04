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
#include "zbxalgo.h"
#include "zbxjson.h"
#include "log.h"
#include "dbschema.h"
#include "db.h"

#include "../api.h"
#include "mediatype.h"
#include "user.h"

/* mediatype.get */
#define ZBX_API_PARAM_GET_MEDIATYPEIDS		"mediatypeids"
#define ZBX_API_PARAM_GET_MEDIAIDS		"mediaids"
#define ZBX_API_PARAM_GET_USERIDS		"userids"
#define ZBX_API_PARAM_GET_SELECTUSERS		"selectUsers"

typedef struct
{
	zbx_vector_uint64_t	mediatypeids;

	zbx_vector_uint64_t	mediaids;

	zbx_vector_uint64_t	userids;

	zbx_api_query_t		select_users;

	zbx_api_getoptions_t	options;
}
zbx_api_mediatype_get_t;

/* TODO: wrap into class defining other object properties if necessary */
static zbx_api_property_t zbx_api_object_properties[] = {
		{"mediatypeid", "mediatypeid", NULL, ZBX_API_FIELD_FLAG_SORTABLE},
		{"type", "type", NULL, ZBX_API_FIELD_FLAG_REQUIRED},
		{"description", "description", NULL,  ZBX_API_FIELD_FLAG_REQUIRED},
		{"smtp_server", "smtp_server", NULL,  0},
		{"smtp_email", "smtp_email", NULL, 0},
		{"exec_path", "exec_path", NULL, 0},
		{"gsm_modem", "gsm_modem", NULL, 0},
		{"username", "username", NULL, 0},
		{"passwd", "passwd", NULL, 0},
		{"status", "status", NULL, 0},
		{NULL}
};

zbx_api_object_t	zbx_api_object_mediatype = {"mediatype", "media_type", zbx_api_object_properties};

static int	zbx_api_mediatype_get_init(zbx_api_mediatype_get_t *self, struct zbx_json_parse *json, char **error);
static void	zbx_api_mediatype_get_clean(zbx_api_mediatype_get_t *self);

static int	zbx_api_mediatype_get_init(zbx_api_mediatype_get_t *self, struct zbx_json_parse *jp, char **error)
{
	char		name[ZBX_API_PARAM_NAME_SIZE], *value = NULL;
	const char	*p = NULL;
	int		ret = FAIL;

	memset(self, 0, sizeof(zbx_api_mediatype_get_t));

	zbx_vector_uint64_create(&self->mediatypeids);
	zbx_vector_uint64_create(&self->mediaids);
	zbx_vector_uint64_create(&self->userids);
	zbx_api_query_init(&self->select_users);

	zbx_api_getoptions_init(&self->options);

	while (NULL != (p = zbx_json_pair_next(jp, p, name, sizeof(name))))
	{
		const char	*next = p;

		if (SUCCEED != zbx_api_getoptions_parse(&self->options, &zbx_api_object_mediatype, name, jp, &next,
				error))
		{
			goto out;
		}

		if (next != p)
		{
			/* the parameter was successfully parsed by common get options parser */
			p = next;
			continue;
		}

		if (0 == strcmp(name, ZBX_API_PARAM_GET_MEDIATYPEIDS))
		{
			if (SUCCEED != zbx_api_get_param_idarray(ZBX_API_PARAM_GET_MEDIATYPEIDS, &next,
					&self->mediatypeids, error))
			{
				goto out;
			}
		}
		else if (0 == strcmp(name, ZBX_API_PARAM_GET_MEDIAIDS))
		{
			if (SUCCEED != zbx_api_get_param_idarray(ZBX_API_PARAM_GET_MEDIAIDS, &next,
					&self->mediaids, error))
			{
				goto out;
			}
		}
		else if (0 == strcmp(name, ZBX_API_PARAM_GET_USERIDS))
		{
			if (SUCCEED != zbx_api_get_param_idarray(ZBX_API_PARAM_GET_USERIDS, &next,
					&self->userids, error))
			{
				goto out;
			}
		}
		else if (0 == strcmp(name, ZBX_API_PARAM_GET_SELECTUSERS))
		{
			if (SUCCEED != zbx_api_get_param_query(ZBX_API_PARAM_GET_SELECTUSERS, &next,
					&zbx_api_object_user, &self->select_users, error))
			{
				goto out;
			}
		}
		else
		{
			*error = zbx_dsprintf(*error, "Invalid parameter \"%s\"", name);
			goto out;

		}
	}

	if (SUCCEED != zbx_api_getoptions_finalize(&self->options, &zbx_api_object_mediatype, error))
		goto out;

	if (ZBX_API_TRUE == self->select_users.is_set)
	{
		if (0 == self->options.output.properties.values_num)
		{
			*error = zbx_dsprintf(*error, "Parameter \"selectUsers\" cannot be used with"
					" parameter \"countOutput\"");
			goto out;
		}

		/* ensure that selected output contains mediatypeid field required to select users */
		if (SUCCEED != zbx_api_getoptions_add_output_field(&self->options, &zbx_api_object_mediatype,
				"mediatypeid", &self->select_users.key, error))
			goto out;
	}

	ret = SUCCEED;

out:
	zbx_free(value);

	if (SUCCEED != ret)
		zbx_api_mediatype_get_clean(self);

	return ret;
}

static void	zbx_api_mediatype_get_clean(zbx_api_mediatype_get_t *self)
{
	zbx_api_getoptions_free(&self->options);

	zbx_api_query_free(&self->select_users);

	zbx_vector_uint64_destroy(&self->userids);
	zbx_vector_uint64_destroy(&self->mediaids);
	zbx_vector_uint64_destroy(&self->mediatypeids);
}

int	zbx_api_mediatype_get(zbx_api_user_t *user, struct zbx_json_parse *jp_request, struct zbx_json *output)
{
	zbx_api_mediatype_get_t	mediatype;
	struct zbx_json_parse	jp_params;
	char			*error = NULL, *sql = NULL;
	int			ret = FAIL, join_media = ZBX_API_FALSE;
	size_t			sql_alloc = 0, sql_offset = 0;
	const char		*sql_condition = " where";
	zbx_api_get_result_t	result;

	zbx_api_get_result_init(&result);

	if (SUCCEED != zbx_json_brackets_by_name(jp_request, "params", &jp_params))
	{
		error = zbx_strdup(error, "Cannot open parameters");
		goto out;
	}

	if (SUCCEED != zbx_api_mediatype_get_init(&mediatype, &jp_params, &error))
		goto out;

	/* security checks */
	if (USER_TYPE_SUPER_ADMIN != user->type &&
			(USER_TYPE_ZABBIX_ADMIN != user->type || 0 != mediatype.options.editable))
		goto skip_query;

	if (0 != mediatype.userids.values_num || 0 != mediatype.mediaids.values_num)
		join_media = ZBX_API_TRUE;

	zbx_api_sql_add_query(&sql, &sql_alloc, &sql_offset, &mediatype.options.output, "media_type", "mt", join_media);

	if (ZBX_API_TRUE == join_media)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " join media m on m.mediatypeid=mt.mediatypeid");


	if (0 != mediatype.userids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_condition);
		sql_condition = " and";

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "m.userid", mediatype.userids.values,
				mediatype.userids.values_num);
	}

	if (0 != mediatype.mediaids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_condition);
		sql_condition = " and";

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "m.mediaid", mediatype.mediaids.values,
				mediatype.mediaids.values_num);
	}

	if (0 != mediatype.mediatypeids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_condition);
		sql_condition = " and";

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "mt.mediatypeid", mediatype.mediatypeids.values,
				mediatype.mediatypeids.values_num);
	}

	zbx_api_sql_add_filter(&sql, &sql_alloc, &sql_offset, &mediatype.options.filter, "mt", &sql_condition);

	zbx_api_sql_add_sort(&sql, &sql_alloc, &sql_offset, &mediatype.options.sort, "mt");

	DBbegin();

	if (SUCCEED != zbx_api_db_fetch_rows(sql, mediatype.options.output.properties.values_num,
			mediatype.options.limit, &result.rows, &error))
	{
		DBrollback();
		goto out;
	}

	if (ZBX_API_TRUE == mediatype.select_users.is_set)
	{
		sql_offset = 0;
		zbx_api_sql_add_query(&sql, &sql_alloc, &sql_offset, &mediatype.select_users, "users", "u",
				ZBX_API_TRUE);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " join media m on m.userid=u.userid and"
				" m.mediatypeid=");

		if (SUCCEED != zbx_api_db_fetch_query(&sql, &sql_alloc, &sql_offset, "users", &mediatype.select_users,
				&result, &error))
		{
			DBrollback();
			goto out;
		}
	}

	DBcommit();

skip_query:
	zbx_api_json_add_result(output, &mediatype.options, &result);

	zbx_api_mediatype_get_clean(&mediatype);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_api_json_add_error(output, error);

	zbx_free(error);
	zbx_free(sql);

	zbx_api_get_result_clean(&result);

	return ret;
}
