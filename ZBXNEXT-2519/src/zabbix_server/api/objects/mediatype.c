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

typedef struct
{
	zbx_vector_ptr_t	objects;
}
zbx_api_mediatype_create_t;

typedef struct
{
	zbx_vector_ptr_t	objects;
}
zbx_api_mediatype_update_t;

typedef struct
{
	zbx_vector_uint64_t	objectids;
}
zbx_api_mediatype_delete_t;

static zbx_api_property_t zbx_api_class_properties[] = {
		{"mediatypeid", "mediatypeid", NULL, ZBX_API_PROPERTY_SORTABLE},
		{"type", "type", NULL, ZBX_API_PROPERTY_REQUIRED},
		{"description", "description", NULL,  ZBX_API_PROPERTY_REQUIRED},
		{"smtp_server", "smtp_server", NULL,  0},
		{"smtp_email", "smtp_email", NULL, 0},
		{"smtp_helo", "smtp_helo", NULL, 0},
		{"exec_path", "exec_path", NULL, 0},
		{"gsm_modem", "gsm_modem", NULL, 0},
		{"username", "username", NULL, 0},
		{"passwd", "passwd", NULL, 0},
		{"status", "status", NULL, 0},
		{NULL}
};

zbx_api_class_t	zbx_api_class_mediatype = {"mediatype", "media_type", zbx_api_class_properties};


/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_user_access_objectids                          *
 *                                                                            *
 * Purpose: checks user access for specified media type object identifiers    *
 *                                                                            *
 * Parameters: user      - [IN] the user                                      *
 *             objectids - [IN] a vector of object identifiers to check       *
 *             int       - [IN] the desired access type (ZBX_API_ACCESS_WRITE *
 *                              or ZBX_API_ACCESS_READ)                       *
 *             error     - [OUT] the error message, optional                  *
 *                                                                            *
 * Return value: SUCCEED - the user has the desired access rights to the      *
 *                         specified objects                                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_mediatype_user_access_objectids(const zbx_api_user_t *user,
		const zbx_vector_uint64_t *objectids, int access, char **error)
{
	const char	*__function_name = "zbx_api_mediatype_user_access_objectids";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 " type:%d access:%s",
			__function_name, user->id, user->type,
			(ZBX_API_ACCESS_WRITE == access ? "write" : "read"));

	if (USER_TYPE_SUPER_ADMIN != user->type &&
			(USER_TYPE_ZABBIX_ADMIN == user->type && ZBX_API_ACCESS_WRITE == access))
	{
		*error = zbx_strdup(*error, "insufficient access rights");
		goto out;
	}

	if (ZBX_API_ACCESS_READ == access || NULL == objectids)
	{
		ret = SUCCEED;
		goto out;
	}

	ret =  zbx_api_check_objectids(objectids, &zbx_api_class_mediatype, error);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_user_access_objects                            *
 *                                                                            *
 * Purpose: checks user access for specified media type objects               *
 *                                                                            *
 * Parameters: user    - [IN] the user                                        *
 *             objects - [IN] a vector of objects to check                    *
 *             int     - [IN] the desired access type (ZBX_API_ACCESS_WRITE   *
 *                              or ZBX_API_ACCESS_READ)                       *
 *             error   - [OUT] the error message, optional                    *
 *                                                                            *
 * Return value: SUCCEED - the user has the desired access rights to the      *
 *                         specified objects                                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_mediatype_user_access_objects(const zbx_api_user_t *user, const zbx_vector_ptr_t *objects,
		int access, char **error)
{
	const char		*__function_name = "zbx_api_mediatype_user_access_objects";
	int			ret;
	zbx_vector_uint64_t	objectids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ", __function_name);

	zbx_vector_uint64_create(&objectids);

	zbx_api_objects_to_ids(objects, &objectids);

	zbx_vector_uint64_sort(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	ret = zbx_api_mediatype_user_access_objectids(user, &objectids, access, error);

	zbx_vector_uint64_destroy(&objectids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * mediatype.get
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_get_clean                                      *
 *                                                                            *
 * Purpose: frees resources allocated by mediatype.get request                *
 *                                                                            *
 * Parameters: self  - [IN/OUT] the request                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_mediatype_get_clean(zbx_api_mediatype_get_t *self)
{
	zbx_api_getoptions_clean(&self->options);

	zbx_api_query_clean(&self->select_users);

	zbx_vector_uint64_destroy(&self->userids);
	zbx_vector_uint64_destroy(&self->mediaids);
	zbx_vector_uint64_destroy(&self->mediatypeids);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_get_init                                       *
 *                                                                            *
 * Purpose: initializes mediatype.get request                                 *
 *                                                                            *
 * Parameters: self  - [OUT] the request                                      *
 *             jp    - [IN]  json data containing request parameters          *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCESS - the request was initialized successfully           *
 *               FAIL    - failed to parse request parameters                 *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_mediatype_get_init(zbx_api_mediatype_get_t *self, const struct zbx_json_parse *jp, char **error)
{
	const char	*__function_name = "zbx_api_mediatype_get_init";
	char		name[ZBX_API_PARAM_NAME_SIZE], *value = NULL;
	const char	*p = NULL;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ", __function_name);

	memset(self, 0, sizeof(zbx_api_mediatype_get_t));

	zbx_vector_uint64_create(&self->mediatypeids);
	zbx_vector_uint64_create(&self->mediaids);
	zbx_vector_uint64_create(&self->userids);
	zbx_api_query_init(&self->select_users);

	zbx_api_getoptions_init(&self->options);

	while (NULL != (p = zbx_json_pair_next(jp, p, name, sizeof(name))))
	{
		const char	*next = p;

		if (SUCCEED != zbx_api_getoptions_parse(&self->options, &zbx_api_class_mediatype, name, &next,
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
					&zbx_api_class_user, &self->select_users, error))
			{
				goto out;
			}
		}
		else
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\"", name);
			goto out;

		}
	}

	if (SUCCEED != zbx_api_getoptions_finalize(&self->options, &zbx_api_class_mediatype, error))
		goto out;

	if (ZBX_API_TRUE == self->select_users.is_set)
	{
		if (0 == self->options.output.properties.values_num)
		{
			*error = zbx_dsprintf(*error, "parameter \"selectUsers\" cannot be used with"
					" parameter \"countOutput\"");
			goto out;
		}

		/* ensure that selected output contains mediatypeid field required to select users */
		if (SUCCEED != zbx_api_getoptions_add_output_field(&self->options, &zbx_api_class_mediatype,
				"mediatypeid", &self->select_users.key, error))
			goto out;
	}

	ret = SUCCEED;

out:
	zbx_free(value);

	if (SUCCEED != ret)
		zbx_api_mediatype_get_clean(self);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_get                                            *
 *                                                                            *
 * Purpose: processes mediatype.get request                                   *
 *                                                                            *
 * Parameters: user        - [IN] the user that issued this request           *
 *             jp_request  - [IN] json data containing request                *
 *             output      - [IN/OUT] json response data                      *
 *                                                                            *
 * Return value: SUCCESS - the request was completed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Depending on success or failure either result or error tags      *
 *           with corresponding data will be added to the json output.        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_mediatype_get(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *output)
{
	const char		*__function_name = "zbx_api_mediatype_get";
	zbx_api_mediatype_get_t	request;
	struct zbx_json_parse	jp_params;
	char			*error = NULL, *sql = NULL;
	int			ret = FAIL, join_media = ZBX_API_FALSE;
	size_t			sql_alloc = 0, sql_offset = 0;
	const char		*sql_condition = " where";
	zbx_api_get_result_t	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_api_get_result_init(&result);

	if (SUCCEED != zbx_json_brackets_by_name(jp_request, "params", &jp_params))
	{
		error = zbx_strdup(error, "cannot open parameters");
		goto out;
	}

	if (SUCCEED != zbx_api_mediatype_get_init(&request, &jp_params, &error))
		goto out;

	/* security checks */
	if (SUCCEED != zbx_api_mediatype_user_access_objectids(user, NULL, ZBX_API_ACCESS_READ, &error))
		goto skip_query;

	if (0 != request.userids.values_num || 0 != request.mediaids.values_num)
		join_media = ZBX_API_TRUE;

	zbx_api_sql_add_query(&sql, &sql_alloc, &sql_offset, &request.options.output, "media_type", "mt", join_media);

	if (ZBX_API_TRUE == join_media)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " join media m on m.mediatypeid=mt.mediatypeid");


	if (0 != request.userids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_condition);
		sql_condition = " and";

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "m.userid", request.userids.values,
				request.userids.values_num);
	}

	if (0 != request.mediaids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_condition);
		sql_condition = " and";

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "m.mediaid", request.mediaids.values,
				request.mediaids.values_num);
	}

	if (0 != request.mediatypeids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_condition);
		sql_condition = " and";

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "mt.mediatypeid", request.mediatypeids.values,
				request.mediatypeids.values_num);
	}

	zbx_api_sql_add_filter(&sql, &sql_alloc, &sql_offset, &request.options.filter, "mt", &sql_condition);

	zbx_api_sql_add_sort(&sql, &sql_alloc, &sql_offset, &request.options.sort, "mt");

	DBbegin();

	if (SUCCEED != zbx_api_db_fetch_rows(sql, request.options.output.properties.values_num,
			request.options.limit, &result.rows, &error))
	{
		DBrollback();
		goto clean;
	}

	if (ZBX_API_TRUE == request.select_users.is_set)
	{
		sql_offset = 0;
		zbx_api_sql_add_query(&sql, &sql_alloc, &sql_offset, &request.select_users, "users", "u",
				ZBX_API_TRUE);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " join media m on m.userid=u.userid and"
				" m.mediatypeid=");

		if (SUCCEED != zbx_api_db_fetch_query(&sql, &sql_alloc, &sql_offset, "users", &request.select_users,
				&result, &error))
		{
			DBrollback();
			goto clean;
		}
	}

	DBcommit();

skip_query:
	zbx_api_json_add_result(output, &request.options, &result);
	ret = SUCCEED;
clean:
	zbx_api_mediatype_get_clean(&request);
out:
	if (SUCCEED != ret)
		zbx_api_json_add_error(output, "Cannot get media type", error);

	zbx_free(error);
	zbx_free(sql);

	zbx_api_get_result_clean(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * mediatype.create
 */
/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_create_clean                                   *
 *                                                                            *
 * Purpose: frees resources allocated by mediatype.create request             *
 *                                                                            *
 * Parameters: self  - [IN/OUT] the request                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_mediatype_create_clean(zbx_api_mediatype_create_t *self)
{
	zbx_vector_ptr_clear_ext(&self->objects, (zbx_mem_free_func_t)zbx_api_object_free);
	zbx_vector_ptr_destroy(&self->objects);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_create_init                                    *
 *                                                                            *
 * Purpose: initializes mediatype.create request                              *
 *                                                                            *
 * Parameters: self  - [OUT] the request                                      *
 *             jp    - [IN]  json data containing request parameters          *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCESS - the request was initialized successfully           *
 *               FAIL    - failed to parse request parameters                 *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_mediatype_create_init(zbx_api_mediatype_create_t *self, const struct zbx_json_parse *jp,
		char **error)
{
	const char	*__function_name = "zbx_api_mediatype_create_init";
	int		ret = FAIL;
	const char	*next = jp->start;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ", __function_name);

	zbx_vector_ptr_create(&self->objects);

	if (SUCCEED != zbx_api_get_param_objectarray("params", &next, &zbx_api_class_mediatype, &self->objects, error))
		goto out;

	if (SUCCEED != zbx_api_prepare_objects_for_create(&self->objects, &zbx_api_class_mediatype, error))
		goto out;

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_api_mediatype_create_clean(self);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_create                                         *
 *                                                                            *
 * Purpose: processes mediatype.create request                                *
 *                                                                            *
 * Parameters: user        - [IN] the user that issued this request           *
 *             jp_request  - [IN] json data containing request                *
 *             output      - [IN/OUT] json response data                      *
 *                                                                            *
 * Return value: SUCCESS - the request was completed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Depending on success or failure either result or error tags      *
 *           with corresponding data will be added to the json output.        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_mediatype_create(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *output)
{
	const char			*__function_name = "zbx_api_mediatype_create";
	zbx_api_mediatype_create_t	request;
	struct zbx_json_parse		jp_params;
	char				*error = NULL;
	int				ret = FAIL;
	zbx_vector_uint64_t		ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);

	if (SUCCEED != zbx_json_brackets_by_name(jp_request, "params", &jp_params))
	{
		error = zbx_strdup(error, "cannot open parameters");
		goto out;
	}

	if (SUCCEED != zbx_api_mediatype_user_access_objectids(user, NULL, ZBX_API_ACCESS_WRITE, &error))
		goto out;

	if (SUCCEED != zbx_api_mediatype_create_init(&request, &jp_params, &error))
		goto out;

	DBbegin();

	if (SUCCEED != zbx_api_check_objects_for_unique_property(&request.objects, &zbx_api_class_mediatype,
			"description", ZBX_API_FALSE, &error))
	{
		DBrollback();
		goto clean;
	}

	if (SUCCEED != zbx_api_create_objects(&request.objects, &zbx_api_class_mediatype, &ids, &error))
	{
		DBrollback();
		goto clean;
	}

	DBcommit();

	zbx_api_json_add_idarray(output, "mediatypeids", &ids);

	ret = SUCCEED;
clean:
	zbx_api_mediatype_create_clean(&request);
out:
	if (SUCCEED != ret)
		zbx_api_json_add_error(output, "Cannot create media type", error);

	zbx_free(error);

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * mediatype.delete
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_delete_clean                                   *
 *                                                                            *
 * Purpose: frees resources allocated by mediatype.delete request             *
 *                                                                            *
 * Parameters: self  - [IN/OUT] the request                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_mediatype_delete_clean(zbx_api_mediatype_delete_t *self)
{
	zbx_vector_uint64_destroy(&self->objectids);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_delete_init                                    *
 *                                                                            *
 * Purpose: initializes mediatype.delete request                              *
 *                                                                            *
 * Parameters: self  - [OUT] the request                                      *
 *             jp    - [IN]  json data containing request parameters          *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCESS - the request was initialized successfully           *
 *               FAIL    - failed to parse request parameters                 *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_mediatype_delete_init(zbx_api_mediatype_delete_t *self, const struct zbx_json_parse *jp,
		char **error)
{
	const char	*__function_name = "zbx_api_mediatype_delete_init";
	int		ret = FAIL;
	const char	*next = jp->start;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ", __function_name);

	zbx_vector_uint64_create(&self->objectids);

	if (SUCCEED != zbx_api_get_param_idarray("object identifier", &next, &self->objectids, error))
		goto out;

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_api_mediatype_delete_clean(self);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_delete                                         *
 *                                                                            *
 * Purpose: processes mediatype.create request                                *
 *                                                                            *
 * Parameters: user        - [IN] the user that issued this request           *
 *             jp_request  - [IN] json data containing request                *
 *             output      - [IN/OUT] json response data                      *
 *                                                                            *
 * Return value: SUCCESS - the request was completed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Depending on success or failure either result or error tags      *
 *           with corresponding data will be added to the json output.        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_mediatype_delete(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *output)
{
	const char			*__function_name = "zbx_api_mediatype_delete";
	zbx_api_mediatype_delete_t	request;
	struct zbx_json_parse		jp_params;
	char				*error = NULL;
	int				ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != zbx_json_brackets_by_name(jp_request, "params", &jp_params))
	{
		error = zbx_strdup(error, "cannot open parameters");
		goto out;
	}

	if (SUCCEED != zbx_api_mediatype_delete_init(&request, &jp_params, &error))
		goto out;

	if (SUCCEED != zbx_api_mediatype_user_access_objectids(user, &request.objectids, ZBX_API_ACCESS_WRITE, &error))
		goto clean;

	DBbegin();

	if (SUCCEED != zbx_api_delete_objects(&request.objectids, &zbx_api_class_mediatype, &error))
	{
		DBrollback();
		goto clean;
	}

	DBcommit();

	zbx_api_json_add_idarray(output, "mediatypeids", &request.objectids);

	ret = SUCCEED;
clean:
	zbx_api_mediatype_delete_clean(&request);
out:
	if (SUCCEED != ret)
		zbx_api_json_add_error(output, "Cannot delete media type", error);

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * mediatype.update
 */
/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_update_clean                                   *
 *                                                                            *
 * Purpose: frees resources allocated by mediatype.update request             *
 *                                                                            *
 * Parameters: self  - [IN/OUT] the request                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_mediatype_update_clean(zbx_api_mediatype_update_t *self)
{
	zbx_vector_ptr_clear_ext(&self->objects, (zbx_mem_free_func_t)zbx_api_object_free);
	zbx_vector_ptr_destroy(&self->objects);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_update_init                                    *
 *                                                                            *
 * Purpose: initializes mediatype.update request                              *
 *                                                                            *
 * Parameters: self  - [OUT] the request                                      *
 *             jp    - [IN]  json data containing request parameters          *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCESS - the request was initialized successfully           *
 *               FAIL    - failed to parse request parameters                 *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_mediatype_update_init(zbx_api_mediatype_update_t *self, const struct zbx_json_parse *jp,
		char **error)
{
	const char	*__function_name = "zbx_api_mediatype_update_init";
	int		ret = FAIL;
	const char	*next = jp->start;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ", __function_name);

	zbx_vector_ptr_create(&self->objects);

	if (SUCCEED != zbx_api_get_param_objectarray("params", &next, &zbx_api_class_mediatype, &self->objects, error))
		goto out;

	if (SUCCEED != zbx_api_prepare_objects_for_update(&self->objects, &zbx_api_class_mediatype, error))
		goto out;

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_api_mediatype_update_clean(self);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_mediatype_update                                         *
 *                                                                            *
 * Purpose: processes mediatype.update request                                *
 *                                                                            *
 * Parameters: user        - [IN] the user that issued this request           *
 *             jp_request  - [IN] json data containing request                *
 *             output      - [IN/OUT] json response data                      *
 *                                                                            *
 * Return value: SUCCESS - the request was completed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Depending on success or failure either result or error tags      *
 *           with corresponding data will be added to the json output.        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_mediatype_update(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *output)
{
	const char			*__function_name = "zbx_api_mediatype_update";
	zbx_api_mediatype_update_t	request;
	struct zbx_json_parse		jp_params;
	char				*error = NULL;
	int				ret = FAIL;
	zbx_vector_uint64_t		ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);

	if (SUCCEED != zbx_json_brackets_by_name(jp_request, "params", &jp_params))
	{
		error = zbx_strdup(error, "cannot open parameters");
		goto out;
	}

	if (SUCCEED != zbx_api_mediatype_update_init(&request, &jp_params, &error))
		goto out;

	if (SUCCEED != zbx_api_mediatype_user_access_objects(user, &request.objects, ZBX_API_ACCESS_WRITE, &error))
		goto clean;

	DBbegin();

	if (SUCCEED != zbx_api_check_objects_for_unique_property(&request.objects, &zbx_api_class_mediatype,
			"description", ZBX_API_TRUE, &error))
	{
		DBrollback();
		goto clean;
	}

	if (SUCCEED != zbx_api_update_objects(&request.objects, &zbx_api_class_mediatype, &ids, &error))
	{
		DBrollback();
		goto clean;
	}

	DBcommit();

	zbx_api_json_add_idarray(output, "mediatypeids", &ids);

	zbx_api_mediatype_update_clean(&request);

	ret = SUCCEED;
clean:
	zbx_api_mediatype_update_clean(&request);
out:
	if (SUCCEED != ret)
		zbx_api_json_add_error(output, "Cannot update media type", error);

	zbx_free(error);

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
