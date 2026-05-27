/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "dbconfig_cep.h"
#include "dbsync.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "dbconfig_local.h"
#include "dbconfig.h"
#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxlog.h"

ZBX_PTR_VECTOR_IMPL(cep_rule_ptr, zbx_cep_rule_t *)
ZBX_VECTOR_IMPL(cep_condition, zbx_cep_condition_t)

typedef struct
{
	zbx_uint64_t	conditionid;
	zbx_uint64_t	ruleid;
}
zbx_cep_condition_rule_t;

static void	cep_condition_clear(zbx_cep_condition_t *condition)
{
	zbx_free(condition->value1_str);
	zbx_free(condition->value2_str);
}

static zbx_cep_rule_t	*cep_rule_create(zbx_uint64_t ruleid)
{
	zbx_cep_rule_t	*rule;

	rule = (zbx_cep_rule_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_rule_t));
	rule->ruleid = ruleid;
	rule->refcount = 1;

	zbx_vector_cep_condition_create(&rule->conditions);

	return rule;
}

static zbx_cep_rule_t	*cep_rule_addref(zbx_cep_rule_t *rule)
{
	atomic_fetch_add(&rule->refcount, 1);

	return rule;
}

static void	cep_rule_release(zbx_cep_rule_t *rule)
{
	if (1 != atomic_fetch_sub(&rule->refcount, 1))
		return;

	for (int i = 0; i < rule->conditions.values_num; i++)
		cep_condition_clear(&rule->conditions.values[i]);

	zbx_vector_cep_condition_destroy(&rule->conditions);

	zbx_free(rule->formula);

	zbx_free(rule);
}

static zbx_cep_rule_t	*cep_rule_clone(const zbx_cep_rule_t *rule)
{
	zbx_cep_rule_t	*clone = cep_rule_create(rule->ruleid);

	clone->evaltype = rule->evaltype;
	clone->sortorder = rule->sortorder;
	clone->status = rule->status;
	clone->stop = rule->stop;
	clone->window_type = rule->window_type;
	clone->formula = zbx_strdup(NULL, rule->formula);

	zbx_vector_cep_condition_append_array(&clone->conditions, rule->conditions.values, rule->conditions.values_num);
	for (int i = 0; i < clone->conditions.values_num; i++)
	{
		zbx_cep_condition_t	*cep_cond = &clone->conditions.values[i];

		if (NULL != cep_cond->value1_str)
			cep_cond->value1_str = zbx_strdup(NULL, cep_cond->value1_str);

		if (NULL != cep_cond->value2_str)
			cep_cond->value2_str = zbx_strdup(NULL, cep_cond->value2_str);
	}

	return clone;
}

static zbx_cep_rule_t	*cep_acquire_rule(zbx_cep_rule_ref_t *ref, zbx_uint64_t revision)
{
	if (NULL == ref->rule)
	{
		ref->rule = cep_rule_create(ref->ruleid);
	}
	else if (1 != atomic_load(&ref->rule->refcount))
	{
		zbx_cep_rule_t	*rule = cep_rule_clone(ref->rule);

		cep_rule_release(ref->rule);
		ref->rule = rule;
	}

	ref->rule->revision = revision;

	return ref->rule;
}

static void	cep_rule_ref_clear(void *a)
{
	zbx_cep_rule_ref_t	*ref = (zbx_cep_rule_ref_t *)a;

	cep_rule_release(ref->rule);
}

static void	cep_config_handle_release(zbx_cep_config_handle_t handle)
{
	if (1 != atomic_fetch_sub(&handle->refcount, 1))
		return;

	for (int i = 0; i < handle->rules.values_num; i++)
		cep_rule_release(handle->rules.values[i]);

	zbx_vector_cep_rule_ptr_destroy(&handle->rules);

	zbx_free(handle);
}

static int	cep_rule_compare_by_sortorder(const void *a1, const void *a2)
{
	const zbx_cep_rule_t	*r1 = *(const zbx_cep_rule_t * const *)a1;
	const zbx_cep_rule_t	*r2 = *(const zbx_cep_rule_t * const *)a2;

	return r1->sortorder - r2->sortorder;
}

static zbx_cep_config_handle_t	cep_config_handle_create(zbx_cep_config_t *cep_config, zbx_uint64_t revision)
{
	zbx_cep_config_handle_t	handle;
	zbx_hashset_iter_t	iter;
	zbx_cep_rule_ref_t	*ref;

	handle = (zbx_cep_config_handle_t)zbx_malloc(NULL, sizeof(struct zbx_cep_config_handle));

	zbx_vector_cep_rule_ptr_create(&handle->rules);
	zbx_vector_cep_rule_ptr_reserve(&handle->rules, cep_config->rules.num_data);
	handle->refcount = 1;
	handle->revision = revision;

	zbx_hashset_iter_reset(&cep_config->rules, &iter);
	while (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_iter_next(&iter)))
	{
		if (CEP_RULE_ENABLED != ref->rule->status)
			continue;

		zbx_vector_cep_rule_ptr_append(&handle->rules, cep_rule_addref(ref->rule));
	}

	zbx_vector_cep_rule_ptr_sort(&handle->rules, cep_rule_compare_by_sortorder);

	return handle;
}

zbx_cep_config_t	*cep_config_create(void)
{
	zbx_cep_config_t	*cep_config;

	cep_config = (zbx_cep_config_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_config_t));

	if (0 != pthread_mutex_init(&cep_config->lock, NULL))
	{
		zbx_free(cep_config);
		THIS_SHOULD_NEVER_HAPPEN_MSG("failed to initialize CEP cache mutex");
		exit(EXIT_FAILURE);
	}

	zbx_hashset_create_ext(&cep_config->rules, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, cep_rule_ref_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create(&cep_config->condition_rule_index, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	return cep_config;
}

void	cep_config_destroy(zbx_cep_config_t *cep_config)
{
	zbx_hashset_destroy(&cep_config->condition_rule_index);
	zbx_hashset_destroy(&cep_config->rules);

	if (NULL != cep_config->handle)
		cep_config_handle_release(cep_config->handle);

	pthread_mutex_destroy(&cep_config->lock);

	zbx_free(cep_config);
}

static void	cep_sync_rules(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision,
		zbx_vector_cep_rule_ptr_t *rules)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_cep_rule_ref_t	ref_local, *ref;
		zbx_cep_rule_t		*rule;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(ref_local.ruleid, row[0]);
		ref_local.rule = NULL;

		ref = (zbx_cep_rule_ref_t *)zbx_hashset_insert(&cep_config->rules, &ref_local, sizeof(ref_local));

		rule = cep_acquire_rule(ref, revision);

		if (NULL == rule->formula || 0 != strcmp(rule->formula, row[1]))
			rule->formula = zbx_strdup(rule->formula, row[1]);

		rule->window_type = atoi(row[2]);
		rule->evaltype = atoi(row[3]);
		rule->status = atoi(row[4]);
		rule->stop = atoi(row[5]);
		rule->sortorder = atoi(row[6]);

		if (ZBX_DBSYNC_ROW_UPDATE == tag && NULL != rules)
			zbx_vector_cep_rule_ptr_append(rules, rule);
	}

	/* remove deleted correlations */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_cep_rule_ref_t	ref_local, *ref;

		ref_local.ruleid = rowid;

		if (NULL == (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)))
			continue;

		zbx_hashset_remove_direct(&cep_config->rules, ref);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	cep_condition_compare_by_id(const void *a1, const void *a2)
{
	const zbx_cep_condition_t *c1 = (const zbx_cep_condition_t *)a1;
	const zbx_cep_condition_t *c2 = (const zbx_cep_condition_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(c1->conditionid, c2->conditionid);

	return 0;
}

static int	cep_condition_compare_by_type(const void *a1, const void *a2)
{
	const zbx_cep_condition_t *c1 = (const zbx_cep_condition_t *)a1;
	const zbx_cep_condition_t *c2 = (const zbx_cep_condition_t *)a2;

	return c1->type - c2->type;
}

static zbx_cep_condition_t	*cep_acquire_condition(zbx_cep_config_t *cep_config, zbx_uint64_t ruleid,
		zbx_uint64_t conditionid, zbx_uint64_t revision, zbx_vector_cep_rule_ptr_t *rules)
{
	zbx_cep_rule_ref_t	ref_local, *ref;
	zbx_cep_rule_t		*rule;
	int			index;
	zbx_cep_condition_t	condition_local = {.conditionid = conditionid};

	ref_local.ruleid = ruleid;
	if (NULL == (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)))
		return NULL;

	rule = cep_acquire_rule(ref, revision);

	if (FAIL == (index = zbx_vector_cep_condition_search(&rule->conditions, condition_local,
			cep_condition_compare_by_id)))
	{
		zbx_cep_condition_rule_t	cr_local = {.conditionid = conditionid, .ruleid = ruleid};

		index = rule->conditions.values_num;
		zbx_vector_cep_condition_append(&rule->conditions, condition_local);

		zbx_hashset_insert(&cep_config->condition_rule_index, &cr_local, sizeof(cr_local));
	}

	if (NULL != rules)
		zbx_vector_cep_rule_ptr_append(rules, rule);

	return &rule->conditions.values[index];
}

static void	cep_remove_condition(zbx_cep_config_t *cep_config, zbx_uint64_t conditionid, zbx_uint64_t revision,
		zbx_vector_cep_rule_ptr_t *rules)
{
	zbx_cep_rule_ref_t		ref_local, *ref;
	zbx_cep_rule_t			*rule;
	int				index;
	zbx_cep_condition_t		condition_local = {.conditionid = conditionid};
	zbx_cep_condition_rule_t	cr_local = {.conditionid = conditionid}, *cr;

	if (NULL == (cr = (zbx_cep_condition_rule_t *)zbx_hashset_search(&cep_config->condition_rule_index, &cr_local)))
		return;

	ref_local.ruleid = cr->ruleid;
	if (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)))
	{
		rule = cep_acquire_rule(ref, revision);

		if (FAIL != (index = zbx_vector_cep_condition_search(&rule->conditions, condition_local,
				cep_condition_compare_by_id)))
		{
			cep_condition_clear(&rule->conditions.values[index]);
			zbx_vector_cep_condition_remove(&rule->conditions, index);
		}

		if (NULL != rules)
			zbx_vector_cep_rule_ptr_append(rules, rule);
	}

	zbx_hashset_remove_direct(&cep_config->condition_rule_index, cr);
}

static void	cep_rule_update_formula(zbx_cep_rule_t *rule, char **str, size_t *str_alloc)
{
	const char	*op = NULL;
	size_t		str_offset = 0;

	if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION == rule->evaltype || 0 == rule->conditions.values_num)
		return;

	switch (rule->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_OR:
			op = " or ";
			break;
		case ZBX_CONDITION_EVAL_TYPE_AND:
			op = " and ";
			break;
	}

	if (NULL != op)
	{
		for (int i = 0; i < rule->conditions.values_num; i++)
		{
			if (0 != str_offset)
				zbx_strcpy_alloc(str, str_alloc, &str_offset, op);

			zbx_snprintf_alloc(str, str_alloc, &str_offset, "{" ZBX_FS_UI64 "}",
					rule->conditions.values[i].conditionid);
		}
		rule->formula = zbx_strdup(rule->formula, *str);

		return;
	}

	/* convert and/or evaluation type */

	zbx_vector_cep_condition_sort(&rule->conditions, cep_condition_compare_by_type);

	for (int i = 0, j = 0; i < rule->conditions.values_num; i = j)
	{
		op = "";

		if (0 != str_offset)
			zbx_strcpy_alloc(str, str_alloc, &str_offset, " and ");

		zbx_chrcpy_alloc(str, str_alloc, &str_offset, '(');

		for (j = i; j < rule->conditions.values_num &&
				rule->conditions.values[j].type == rule->conditions.values[i].type; j++)
		{
			zbx_snprintf_alloc(str, str_alloc, &str_offset, "%s{" ZBX_FS_UI64 "}",
					op, rule->conditions.values[j].conditionid);

			op = " or ";
		}

		zbx_chrcpy_alloc(str, str_alloc, &str_offset, ')');
	}

	rule->formula = zbx_strdup(rule->formula, *str);
}

static void	cep_sync_conditions(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision,
	zbx_vector_cep_rule_ptr_t *rules)
{
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_uint64_t	conditionid, ruleid;

		zbx_cep_condition_t	*condition;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(conditionid, row[0]);
		ZBX_STR2UINT64(ruleid, row[1]);

		condition = cep_acquire_condition(cep_config, ruleid, conditionid, revision, rules);

		condition->type = atoi(row[2]);
		condition->operator = atoi(row[3]);
		condition->value_int = atoi(row[10]);

		switch (condition->type)
		{
			case ZBX_CEP_CONDITION_EVENT_NAME:
				if (NULL == condition->value1_str || 0 != strcmp(row[4], condition->value1_str))
					condition->value1_str = zbx_strdup(condition->value1_str, row[4]);
				zbx_free(condition->value2_str);
				break;
			case ZBX_CEP_CONDITION_TAG_NAME:
				if (NULL == condition->value1_str || 0 != strcmp(row[5], condition->value1_str))
					condition->value1_str = zbx_strdup(condition->value1_str, row[5]);
				zbx_free(condition->value2_str);
				break;
			case ZBX_CEP_CONDITION_TAG_VALUE:
				if (NULL == condition->value1_str || 0 != strcmp(row[5], condition->value1_str))
					condition->value1_str = zbx_strdup(condition->value1_str, row[5]);
				if (NULL == condition->value2_str || 0 != strcmp(row[6], condition->value2_str))
					condition->value2_str = zbx_strdup(condition->value2_str, row[6]);
				break;
			case ZBX_CEP_CONDITION_SEVERITY:
				zbx_free(condition->value1_str);
				zbx_free(condition->value2_str);
				break;
			case ZBX_CEP_CONDITION_HOST:
				if (NULL == condition->value1_str || 0 != strcmp(row[7], condition->value1_str))
					condition->value1_str = zbx_strdup(condition->value1_str, row[7]);
				zbx_free(condition->value2_str);
				break;
			case ZBX_CEP_CONDITION_HOST_GROUP:
				if (NULL == condition->value1_str || 0 != strcmp(row[8], condition->value1_str))
					condition->value1_str = zbx_strdup(condition->value1_str, row[8]);
				zbx_free(condition->value2_str);
				break;
			case ZBX_CEP_CONDITION_TIME_PERIOD:
				if (NULL == condition->value1_str || 0 != strcmp(row[9], condition->value1_str))
					condition->value1_str = zbx_strdup(condition->value1_str, row[9]);
				zbx_free(condition->value2_str);
				break;
		}
	}

	/* remove deleted correlations */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		cep_remove_condition(cep_config, rowid, revision, rules);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_condition_dump(zbx_cep_condition_t *condition)
{
	zabbix_log(LOG_LEVEL_TRACE, "    conditionid:" ZBX_FS_UI64 " type:%d operator:%d value1_str:%s value2_str:%s"
			" value_int:%d", condition->conditionid, condition->type, condition->operator,
			condition->value1_str, condition->value2_str, condition->value_int);
}

static void	cep_rule_dump(zbx_cep_rule_t *rule)
{
	zabbix_log(LOG_LEVEL_TRACE, "ruleid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64 " refcount:%u status:%d stop:%d"
			" sortoder:%d window:%d evaltype:%d formula:%s",
			rule->ruleid, rule->revision, atomic_load(&rule->refcount),rule->status, rule->stop,
			rule->sortorder, rule->window_type, rule->evaltype, rule->formula);

	zabbix_log(LOG_LEVEL_TRACE, "  conditions:");
	for (int i = 0; i < rule->conditions.values_num; i++)
		cep_condition_dump(&rule->conditions.values[i]);
}

static void	cep_config_dump(void)
{
	zbx_cep_config_t	*cep_config = dc_local()->cep_config;
	zbx_hashset_iter_t	iter;
	zbx_cep_rule_ref_t	*ref;

	// WDN remove
	int	loglevel = zbx_set_log_level(LOG_LEVEL_TRACE);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);


	zbx_hashset_iter_reset(&cep_config->rules, &iter);
	while (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_iter_next(&iter)))
		cep_rule_dump(ref->rule);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	// WDN remove
	zbx_set_log_level(loglevel);
}

static void	cep_config_update_handle(zbx_cep_config_t *cep_config, zbx_uint64_t revision)
{
	pthread_mutex_lock(&cep_config->lock);

	if (cep_config->revision == revision)
	{
		if (NULL != cep_config->handle)
			cep_config_handle_release(cep_config->handle);

		cep_config->handle = cep_config_handle_create(cep_config, revision);

		atomic_store(&cep_config->rules_num, cep_config->handle->rules.values_num);
	}

	pthread_mutex_unlock(&cep_config->lock);
}

static void	cep_update_formulas(zbx_cep_config_t *cep_config, zbx_vector_cep_rule_ptr_t *rules)
{
	char	*str = NULL;
	size_t	str_alloc = 0;

	if (NULL != rules)
	{
		zbx_vector_cep_rule_ptr_sort(rules, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		zbx_vector_cep_rule_ptr_uniq(rules, ZBX_DEFAULT_PTR_COMPARE_FUNC);

		for (int i = 0; i < rules->values_num; i++)
			cep_rule_update_formula(rules->values[i], &str, &str_alloc);

		zbx_vector_cep_rule_ptr_destroy(rules);
	}
	else
	{
		zbx_hashset_iter_t	iter;
		zbx_cep_rule_ref_t	*ref;

		zbx_hashset_iter_reset(&cep_config->rules, &iter);
		while (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_iter_next(&iter)))
			cep_rule_update_formula(ref->rule, &str, &str_alloc);

	}

	zbx_free(str);
}

void	cep_config_sync(zbx_dbsync_t *rule_sync, zbx_dbsync_t *condition_sync, zbx_uint64_t revision)
{
	zbx_vector_cep_rule_ptr_t	rules, *prules = NULL;
	zbx_cep_config_t		*cep_config = dc_local()->cep_config;

	if (0 != cep_config->rules.num_data)
	{
		zbx_vector_cep_rule_ptr_create(&rules);
		prules = &rules;
	}

	cep_sync_rules(cep_config, rule_sync, revision, prules);
	cep_sync_conditions(cep_config, condition_sync, revision, prules);

	cep_update_formulas(cep_config, prules);
	cep_config_update_handle(cep_config, revision);

	cep_config_dump();

	if (NULL != prules)
		zbx_vector_cep_rule_ptr_destroy(prules);
}

zbx_cep_config_handle_t	zbx_cep_config_open(void)
{
	zbx_cep_config_handle_t	handle;
	zbx_cep_config_t	*cep_config = dc_local()->cep_config;

	if (0 == atomic_load(&cep_config->rules_num))
		return NULL;

	pthread_mutex_lock(&cep_config->lock);
	handle = cep_config->handle;
	atomic_fetch_add(&handle->refcount, 1);
	pthread_mutex_unlock(&cep_config->lock);

	return handle;
}

void	zbx_cep_config_close(zbx_cep_config_handle_t handle)
{
	cep_config_handle_release(handle);
}

const zbx_vector_cep_rule_ptr_t	*zbx_cep_config_get_rules(zbx_cep_config_handle_t handle)
{
	return &handle->rules;
}

