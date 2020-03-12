/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "zbxjson.h"
#include "zbxalgo.h"
#include "dbcache.h"

#include "expressions_evaluate.h"
/* #include "preproc.h" */
/* #include "trapper_preproc.h" */
/* #include "../preprocessor/preproc_history.h" */

static int	trapper_parse_expressions_evaluate(const struct zbx_json_parse *jp, zbx_vector_ptr_t *expressions, char **error)
{
	zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS_PARSE FUNC 333x");
	
	char			buffer[MAX_STRING_LEN], *step_params = NULL, *error_handler_params = NULL;
	const char		*ptr;
	zbx_user_t		user;
	int			ret = FAIL;
	struct zbx_json_parse	jp_data, jp_expressions, jp_expression;
	size_t			size;
	//zbx_timespec_t		ts_now;

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, buffer, sizeof(buffer), NULL) ||
			SUCCEED != DBget_user_by_active_session(buffer, &user) || USER_TYPE_ZABBIX_ADMIN > user.type)
	{
		*error = zbx_strdup(NULL, "Permission denied.");
		goto out;
	}

	//	zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSION ALL: ->%s<-",jp);

	
	if (FAIL == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_strdup(NULL, "Missing data field.");
		goto out;
	}

	
	//zbx_timespec(&ts_now);

	
	size = 0;

	/* if (FAIL == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_EXPRESSIONS, &jp_steps)) */
	/* { */
	/* 	*error = zbx_strdup(NULL, "Missing expressions field."); */
	/* 	goto out; */
	/* } */

	char *expression;

	zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS: ->%s<-",jp_data);
	zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS TAG: ->%s<-",ZBX_PROTO_TAG_EXPRESSIONS);


	if (FAIL == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_EXPRESSIONS, &jp_expressions))
	{
		*error = zbx_strdup(NULL, "Missing expressions field.");
		goto out;
	}



	for (ptr = NULL; NULL != (ptr = zbx_json_next_value(&jp_expressions, ptr, buffer, sizeof(buffer), NULL));)
	{


	  
	  zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS_PARSE NEXT 444: ->%s<-",buffer);
	  zbx_vector_ptr_append(expressions, zbx_strdup(NULL,buffer));

	/* 	zbx_free(expression); */


	}


	  
	/* if (FAIL == zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_EXPRESSIONS, buffer, sizeof(buffer), NULL)) */
	/* { */
	/* 	*error = zbx_strdup(NULL, "Missing expressions field."); */
	/* 	goto out; */
	/* } */


	/* expression = zbx_strdup(NULL, buffer); */

	/* zabbix_log(LOG_LEVEL_INFORMATION, "buffer ->%s<-", buffer); */
	/* zabbix_log(LOG_LEVEL_INFORMATION, "expression ->%s<-", expression); */


	
	/* for (ptr = NULL; NULL != (ptr = zbx_json_next(&jp_steps, ptr));) */
	/* { */

	/*   zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS_PARSE NEXT 444: ->%s<-",ptr); */

	  
	  //zbx_preproc_op_t	*step;
	  //unsigned char		step_type, error_handler;



	  

		
		/* if (FAIL == zbx_json_value_by_name(&jp_step, ZBX_PROTO_TAG_TYPE, buffer, sizeof(buffer), NULL)) */
		/* { */
		/* 	*error = zbx_strdup(NULL, "Missing preprocessing step type field."); */
		/* 	goto out; */
		/* } */
		/* step_type = atoi(buffer); */

		/* if (FAIL == zbx_json_value_by_name(&jp_step, ZBX_PROTO_TAG_ERROR_HANDLER, buffer, sizeof(buffer), NULL)) */
		/* { */
		/* 	*error = zbx_strdup(NULL, "Missing preprocessing step type error handler field."); */
		/* 	goto out; */
		/* } */
		/* error_handler = atoi(buffer); */

		/* size = 0; */
		/* if (FAIL == zbx_json_value_by_name_dyn(&jp_step, ZBX_PROTO_TAG_PARAMS, &step_params, &size, NULL)) */
		/* { */
		/* 	*error = zbx_strdup(NULL, "Missing preprocessing step type params field."); */
		/* 	goto out; */
		/* } */

		/* size = 0; */
		/* if (FAIL == zbx_json_value_by_name_dyn(&jp_step, ZBX_PROTO_TAG_ERROR_HANDLER_PARAMS, */
		/* 		&error_handler_params, &size, NULL)) */
		/* { */
		/* 	*error = zbx_strdup(NULL, "Missing preprocessing step type error handler params field."); */
		/* 	goto out; */
		/* } */

		/* step = (zbx_preproc_op_t *)zbx_malloc(NULL, sizeof(zbx_preproc_op_t)); */
		/* step->type = step_type; */
		/* step->params = step_params; */
		/* step->error_handler = error_handler; */
		/* step->error_handler_params = error_handler_params; */






	/* zbx_vector_ptr_append(expressions, expression); */

	/* 	zbx_free(expression); */
		
		
	/* 	step_params = NULL; */
	/* 	error_handler_params = NULL; */
	/* } */

	ret = SUCCEED;
	zbx_free(step_params);
 out:
	if (FAIL == ret)
	{
		zbx_vector_ptr_clear_ext(expressions, (zbx_clean_func_t)zbx_ptr_free);
	}

	zbx_free(step_params);
	zbx_free(error_handler_params);

	return ret;
}



static int	trapper_expressions_evaluate_run(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS_EVALUATE RUN FUNC 222");

	char			*evaluate_error = NULL;
	int			ret = FAIL, i;
	unsigned char		value_type;
	zbx_vector_ptr_t	expressions, results, history;
	zbx_timespec_t		ts[2];
	zbx_expressions_evaluate_result_t	*result;

	zbx_vector_ptr_create(&expressions);
	zbx_vector_ptr_create(&results);
	//zbx_vector_ptr_create(&history);

	// parse json
	if (FAIL == trapper_parse_expressions_evaluate(jp,  &expressions, error))
		goto out;

	// run expressions, fill results
	//zbx_vector_ptr_clear_ext(&results, (zbx_clean_func_t)zbx_preproc_result_free);

	if (0 == expressions.values_num)
	{
		zbx_variant_t	value;
	  
		result = (zbx_expressions_evaluate_result_t *)zbx_malloc(NULL, sizeof(zbx_expressions_evaluate_result_t));

		result->error = NULL;

		zbx_vector_ptr_append(&results, result);
	}
		/* else if (FAIL == evaluate( value_type, values[i], /\*&ts[i],*\/ &steps, &results, &history, */
		/* 		&preproc_error, error)) */
		/* { */
		/* 	goto out; */
		/* } */

	else
	{
		for (int ii = 0; ii < expressions.values_num; ii++)
		{
			double expr_result;
			char			err[MAX_STRING_LEN];
			zbx_vector_ptr_t	unknown_msgs;
			zabbix_log(LOG_LEVEL_INFORMATION, "2222222222222, EXPR: ->%s<-",expressions.values[ii]);
			if (SUCCEED != evaluate(&expr_result, expressions.values[ii], err, sizeof(err), &unknown_msgs))
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "BADGER");
				continue;
			}
			zabbix_log(LOG_LEVEL_INFORMATION, "RES: %f",expr_result);
		}
	}

		
	/* 	if (NULL != preproc_error) */
	/* 		break; */

	/* 	if (0 == single) */
	/* 	{ */
	/* 		result = (zbx_expressions_evaluate_result_t *)results.values[results.values_num - 1]; */
	/* 		if (ZBX_VARIANT_NONE != result->value.type && */
	/* 				FAIL == zbx_variant_to_value_type(&result->value, value_type, &preproc_error)) */
	/* 		{ */
	/* 			break; */
	/* 		} */
	/* 	} */


		
	/* zbx_json_addarray(json, ZBX_PROTO_TAG_EXPRESSIONS); */
	/* if (0 != expressions.values_num) */
	/* { */
	/* 	for (i = 0; i < results.values_num; i++) */
	/* 	{ */
	/* 		result = (zbx_preproc_result_t *)results.values[i]; */

	/* 		zbx_json_addobject(json, NULL); */

	/* 		if (NULL != result->error) */
	/* 			zbx_json_addstring(json, ZBX_PROTO_TAG_ERROR, result->error, ZBX_JSON_TYPE_STRING); */

	/* 		if (ZBX_PREPROC_FAIL_DEFAULT != result->action) */
	/* 			zbx_json_adduint64(json, ZBX_PROTO_TAG_ACTION, result->action); */

	/* 		if (i == results.values_num - 1 && NULL != result->error) */
	/* 		{ */
	/* 			if (ZBX_PREPROC_FAIL_SET_ERROR == result->action) */
	/* 			{ */
	/* 				zbx_json_addstring(json, ZBX_PROTO_TAG_FAILED, preproc_error, */
	/* 						ZBX_JSON_TYPE_STRING); */
	/* 			} */
	/* 		} */

	/* 		if (ZBX_VARIANT_NONE != result->value.type) */
	/* 		{ */
	/* 			zbx_json_addstring(json, ZBX_PROTO_TAG_RESULT, zbx_variant_value_desc(&result->value), */
	/* 					ZBX_JSON_TYPE_STRING); */
	/* 		} */
	/* 		else if (NULL == result->error || ZBX_PREPROC_FAIL_DISCARD_VALUE == result->action) */
	/* 			zbx_json_addstring(json, ZBX_PROTO_TAG_RESULT, NULL, ZBX_JSON_TYPE_NULL); */

	/* 		zbx_json_close(json); */
	/* 	} */
	/* } */
	zbx_json_close(json);

	if (NULL == evaluate_error)
	{
		result = (zbx_expressions_evaluate_result_t *)results.values[results.values_num - 1];

		if (ZBX_VARIANT_NONE != result->value.type)
		{
			zbx_json_addstring(json, ZBX_PROTO_TAG_RESULT, zbx_variant_value_desc(&result->value),
					ZBX_JSON_TYPE_STRING);
		}
		else
			zbx_json_addstring(json, ZBX_PROTO_TAG_RESULT, NULL, ZBX_JSON_TYPE_NULL);
	}
	else
		zbx_json_addstring(json, ZBX_PROTO_TAG_ERROR, evaluate_error, ZBX_JSON_TYPE_STRING);

	ret = SUCCEED;
out:

	zbx_free(evaluate_error);


	zbx_vector_ptr_clear_ext(&expressions, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_destroy(&expressions);



	/* zbx_vector_ptr_clear_ext(&history, (zbx_clean_func_t)zbx_preproc_op_history_free); */
	/* zbx_vector_ptr_destroy(&history); */
	/* zbx_vector_ptr_clear_ext(&results, (zbx_clean_func_t)zbx_preproc_result_free); */
	/* zbx_vector_ptr_destroy(&results); */
	/* zbx_vector_ptr_clear_ext(&steps, (zbx_clean_func_t)zbx_preproc_op_free); */
	/* zbx_vector_ptr_destroy(&steps); */

	return ret;
}


int	zbx_trapper_expressions_evaluate(zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS_EVALUATE FUNC 111");
	char		*error = NULL;
	int		ret;
	struct zbx_json	json;

	zbx_json_init(&json, 1024);

	if (SUCCEED == (ret = trapper_expressions_evaluate_run(jp, &json, &error)))
	{
		zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size, CONFIG_TIMEOUT);
	}
	else
	{
		zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);
		zbx_free(error);
	}

	zbx_json_free(&json);

	return ret;
}

/* #ifdef HAVE_TESTS */
/* #	include "../../../tests/zabbix_server/trapper/trapper_expressions_evaluate_run.c" */
/* #endif */
