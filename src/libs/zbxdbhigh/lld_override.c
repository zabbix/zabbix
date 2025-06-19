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

#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbx_trigger_constants.h"
#include "zbx_host_constants.h"
#include "zbxregexp.h"

ZBX_PTR_VECTOR_IMPL(lld_override_ptr, zbx_lld_override_t *)
ZBX_PTR_VECTOR_IMPL(lld_condition_ptr, zbx_lld_condition_t *)
ZBX_PTR_VECTOR_IMPL(lld_override_operation, zbx_lld_override_operation_t*)

void	zbx_lld_override_operation_free(zbx_lld_override_operation_t *override_operation)
{
	zbx_vector_db_tag_ptr_clear_ext(&override_operation->tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&override_operation->tags);

	zbx_vector_uint64_destroy(&override_operation->templateids);

	zbx_free(override_operation->value);
	zbx_free(override_operation->delay);
	zbx_free(override_operation->history);
	zbx_free(override_operation->trends);
	zbx_free(override_operation);
}

/******************************************************************************
 *                                                                            *
 * Purpose: load tag override operations from database                        *
 *                                                                            *
 * Parameters: overrideids - [IN] lld overrideids, sorted                     *
 *             sql         - [IN/OUT] sql query buffer                        *
 *             sql_alloc   - [IN/OUT] sql query buffer size                   *
 *             ops         - [IN/OUT] lld override operations, sorted by      *
 *                                    override_operationid                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_operations_load_tags(const zbx_vector_uint64_t *overrideids, char **sql, size_t *sql_alloc,
		zbx_vector_lld_override_operation_t *ops)
{
	size_t				sql_offset = 0;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_override_operation_t	*op = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset,
			"select o.lld_override_operationid,ot.tag,ot.value"
			" from lld_override_operation o,lld_override_optag ot"
			" where o.lld_override_operationid=ot.lld_override_operationid"
			" and");
	zbx_db_add_condition_alloc(sql, sql_alloc, &sql_offset, "o.lld_overrideid", overrideids->values,
			overrideids->values_num);
	zbx_snprintf_alloc(sql, sql_alloc, &sql_offset, " and o.operationobject in (%d,%d,%d)",
			ZBX_LLD_OVERRIDE_OP_OBJECT_TRIGGER, ZBX_LLD_OVERRIDE_OP_OBJECT_HOST,
			ZBX_LLD_OVERRIDE_OP_OBJECT_ITEM);
	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset, " order by o.lld_override_operationid");

	result = zbx_db_select("%s", *sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	override_operationid;
		zbx_db_tag_t	*tag;

		ZBX_STR2UINT64(override_operationid, row[0]);
		if (NULL == op || op->override_operationid != override_operationid)
		{
			int	index;

			if (FAIL == (index = zbx_vector_ptr_bsearch((const zbx_vector_ptr_t *)ops,
					(const void *)&override_operationid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			op = ops->values[index];
		}

		tag = zbx_db_tag_create(row[1], row[2]);
		zbx_vector_db_tag_ptr_append(&op->tags, tag);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: load template lld override operations from database               *
 *                                                                            *
 * Parameters: overrideids - [IN] lld overrideids, sorted                     *
 *             sql         - [IN/OUT] sql query buffer                        *
 *             sql_alloc   - [IN/OUT] sql query buffer size                   *
 *             ops         - [IN/OUT] lld override operations, sorted by      *
 *                                    override_operationid                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_operations_load_templates(const zbx_vector_uint64_t *overrideids, char **sql,
		size_t *sql_alloc, zbx_vector_lld_override_operation_t *ops)
{
	size_t				sql_offset = 0;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_override_operation_t	*op = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset,
			"select o.lld_override_operationid,ot.templateid"
			" from lld_override_operation o,lld_override_optemplate ot"
			" where o.lld_override_operationid=ot.lld_override_operationid"
			" and");
	zbx_db_add_condition_alloc(sql, sql_alloc, &sql_offset, "o.lld_overrideid", overrideids->values,
			overrideids->values_num);
	zbx_snprintf_alloc(sql, sql_alloc, &sql_offset, " and o.operationobject=%d",
			ZBX_LLD_OVERRIDE_OP_OBJECT_HOST);
	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset, " order by o.lld_override_operationid");

	result = zbx_db_select("%s", *sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	templateid, override_operationid;

		ZBX_STR2UINT64(override_operationid, row[0]);
		if (NULL == op || op->override_operationid != override_operationid)
		{
			int	index;

			if (FAIL == (index = zbx_vector_ptr_bsearch((const zbx_vector_ptr_t *)ops,
					(const void *)&override_operationid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			op = ops->values[index];
		}

		ZBX_STR2UINT64(templateid, row[1]);
		zbx_vector_uint64_append(&op->templateids, templateid);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: load lld override operations from database                        *
 *                                                                            *
 * Parameters: overrideids - [IN] lld overrideids, sorted                     *
 *             sql         - [IN/OUT] sql query buffer                        *
 *             sql_alloc   - [IN/OUT] sql query buffer size                   *
 *             ops         - [OUT] lld override operations, sorted by         *
 *                                    override_operationid                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_load_lld_override_operations(const zbx_vector_uint64_t *overrideids, char **sql, size_t *sql_alloc,
		zbx_vector_lld_override_operation_t *ops)
{
	size_t				sql_offset = 0;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_override_operation_t	*override_operation = NULL;
	zbx_uint64_t			object_mask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset,
			"select o.lld_overrideid,o.lld_override_operationid,o.operationobject,o.operator,o.value,"
				"s.status,"
				"d.discover,"
				"p.delay,"
				"h.history,"
				"t.trends,"
				"os.severity,"
				"i.inventory_mode"
			" from lld_override_operation o"
			" left join lld_override_opstatus s"
				" on o.lld_override_operationid=s.lld_override_operationid"
			" left join lld_override_opdiscover d"
				" on o.lld_override_operationid=d.lld_override_operationid"
			" left join lld_override_opperiod p"
				" on o.lld_override_operationid=p.lld_override_operationid"
			" left join lld_override_ophistory h"
				" on o.lld_override_operationid=h.lld_override_operationid"
			" left join lld_override_optrends t"
				" on o.lld_override_operationid=t.lld_override_operationid"
			" left join lld_override_opseverity os"
				" on o.lld_override_operationid=os.lld_override_operationid"
			" left join lld_override_opinventory i"
				" on o.lld_override_operationid=i.lld_override_operationid"
			" where");
	zbx_db_add_condition_alloc(sql, sql_alloc, &sql_offset, "o.lld_overrideid", overrideids->values,
			overrideids->values_num);
	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset, " order by o.lld_override_operationid");

	result = zbx_db_select("%s", *sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		override_operation = (zbx_lld_override_operation_t *)zbx_malloc(NULL,
				sizeof(zbx_lld_override_operation_t));

		ZBX_STR2UINT64(override_operation->overrideid, row[0]);

		zbx_vector_db_tag_ptr_create(&override_operation->tags);
		zbx_vector_uint64_create(&override_operation->templateids);

		ZBX_STR2UINT64(override_operation->override_operationid, row[1]);
		override_operation->operationtype = (unsigned char)atoi(row[2]);
		override_operation->operator = (unsigned char)atoi(row[3]);
		override_operation->value = zbx_strdup(NULL, row[4]);

		override_operation->status = FAIL == zbx_db_is_null(row[5]) ? (unsigned char)atoi(row[5]) :
				ZBX_PROTOTYPE_STATUS_COUNT;

		override_operation->discover = FAIL == zbx_db_is_null(row[6]) ? (unsigned char)atoi(row[6]) :
				ZBX_PROTOTYPE_DISCOVER_COUNT;

		override_operation->delay = FAIL == zbx_db_is_null(row[7]) ? zbx_strdup(NULL, row[7]) :
				NULL;
		override_operation->history = FAIL == zbx_db_is_null(row[8]) ? zbx_strdup(NULL, row[8]) :
				NULL;
		override_operation->trends = FAIL == zbx_db_is_null(row[9]) ? zbx_strdup(NULL, row[9]) :
				NULL;
		override_operation->severity = FAIL == zbx_db_is_null(row[10]) ? (unsigned char)atoi(row[10]) :
				TRIGGER_SEVERITY_COUNT;

		override_operation->inventory_mode = FAIL == zbx_db_is_null(row[11]) ? (signed char)atoi(row[11]) :
				HOST_INVENTORY_COUNT;

		zbx_vector_lld_override_operation_append(ops, override_operation);

		object_mask |= (__UINT64_C(1) << override_operation->operationtype);
	}
	zbx_db_free_result(result);

	if (0 != (object_mask & ((1 << ZBX_LLD_OVERRIDE_OP_OBJECT_HOST) | (1 << ZBX_LLD_OVERRIDE_OP_OBJECT_TRIGGER) |
			(1 << ZBX_LLD_OVERRIDE_OP_OBJECT_ITEM))))
	{
		lld_override_operations_load_tags(overrideids, sql, sql_alloc, ops);
	}

	if (0 != (object_mask & (1 << ZBX_LLD_OVERRIDE_OP_OBJECT_HOST)))
		lld_override_operations_load_templates(overrideids, sql, sql_alloc, ops);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by filter condition                  *
 *                                                                            *
 * Parameters: condition - [IN] filter condition                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_condition_free(zbx_lld_condition_t *condition)
{
	zbx_regexp_clean_expressions(&condition->regexps);
	zbx_vector_expression_destroy(&condition->regexps);

	zbx_free(condition->macro);
	zbx_free(condition->regexp);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by filter conditions                 *
 *                                                                            *
 * Parameters: conditions - [IN] filter conditions                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_conditions_free(zbx_vector_lld_condition_ptr_t *conditions)
{
	zbx_vector_lld_condition_ptr_clear_ext(conditions, lld_condition_free);
	zbx_vector_lld_condition_ptr_destroy(conditions);
}

void	zbx_lld_filter_init(zbx_lld_filter_t *filter)
{
	zbx_vector_lld_condition_ptr_create(&filter->conditions);
	filter->expression = NULL;
	filter->evaltype = ZBX_CONDITION_EVAL_TYPE_AND_OR;
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by LLD filter                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_lld_filter_clean(zbx_lld_filter_t *filter)
{
	zbx_free(filter->expression);
	lld_conditions_free(&filter->conditions);
}

zbx_lld_override_t	*zbx_lld_override_create(const char *lld_overrideid, const char *itemid, const char *name,
		const char *step, const char *evaltype, const char *formula, const char *stop)
{
	zbx_lld_override_t	*override = (zbx_lld_override_t *)zbx_malloc(NULL, sizeof(zbx_lld_override_t));

	ZBX_DBROW2UINT64(override->overrideid, lld_overrideid);
	ZBX_DBROW2UINT64(override->itemid, itemid);
	override->name = (NULL != name ? zbx_strdup(NULL, name) : NULL);
	ZBX_STR2UCHAR(override->step, step);
	ZBX_STR2UCHAR(override->stop, stop);

	zbx_lld_filter_init(&override->filter);
	override->filter.evaltype = atoi(evaltype);
	override->filter.expression = zbx_strdup(NULL, formula);

	zbx_vector_lld_override_operation_create(&override->override_operations);

	return override;
}

void	zbx_lld_override_free(zbx_lld_override_t *override)
{
	zbx_free(override->name);
	zbx_lld_filter_clean(&override->filter);

	zbx_vector_lld_override_operation_clear_ext(&override->override_operations, zbx_lld_override_operation_free);
	zbx_vector_lld_override_operation_destroy(&override->override_operations);
	zbx_free(override);
}

int	zbx_lld_override_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_override_t	*override_1 = *(const zbx_lld_override_t * const *)d1;
	const zbx_lld_override_t	*override_2 = *(const zbx_lld_override_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(override_1->overrideid, override_2->overrideid);

	return 0;
}

zbx_lld_condition_t	*zbx_lld_filter_condition_create(const char *id, const char *operator, const char *macro,
		const char *value)
{
	zbx_lld_condition_t	*condition = (zbx_lld_condition_t *)zbx_malloc(NULL, sizeof(zbx_lld_condition_t));

	ZBX_STR2UINT64(condition->id, id);
	condition->macro = zbx_strdup(NULL, macro);
	condition->regexp = zbx_strdup(NULL, value);
	ZBX_STR2UCHAR(condition->op, operator);

	zbx_vector_expression_create(&condition->regexps);

	return condition;
}
