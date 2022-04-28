/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "sysinfo.h"
#include "alias/alias.h"

#include "log.h"
#include "zbxthreads.h"
#if !defined(_WINDOWS) && !defined(__MINGW32__)
#include "zbxnix.h"
#endif
#include "zbxalgo.h"
#include "zbxregexp.h"

extern int	CONFIG_TIMEOUT;

#ifdef WITH_AGENT_METRICS
#	include "agent/agent.h"
#endif

#ifdef WITH_COMMON_METRICS
	#include "common/zbxsysinfo_common.h"
#endif

#ifdef WITH_HTTP_METRICS
#	include "common/http_metrics.h"
#endif

#ifdef WITH_SIMPLE_METRICS
#	include "simple/simple.h"
#endif

#ifdef WITH_SPECIFIC_METRICS
#	include "specsysinfo.h"
#endif

typedef struct
{
	char				*pattern;
	zbx_vector_str_t		elements;
	zbx_key_access_rule_type_t	type;
	int				empty_arguments;
}
zbx_key_access_rule_t;

#ifdef WITH_HOSTNAME_METRIC
extern ZBX_METRIC	parameter_hostname;
#endif

static ZBX_METRIC	*commands = NULL;
static ZBX_METRIC	*commands_local = NULL;
zbx_vector_ptr_t	key_access_rules;

#define ZBX_COMMAND_ERROR		0
#define ZBX_COMMAND_WITHOUT_PARAMS	1
#define ZBX_COMMAND_WITH_PARAMS		2

static int	compare_key_access_rules(const void *rule_a, const void *rule_b);
static int	parse_key_access_rule(char *pattern, zbx_key_access_rule_t *rule);

/******************************************************************************
 *                                                                            *
 * Purpose: parses item key and splits it into command and parameters         *
 *                                                                            *
 * Return value: ZBX_COMMAND_ERROR - error                                    *
 *               ZBX_COMMAND_WITHOUT_PARAMS - command without parameters      *
 *               ZBX_COMMAND_WITH_PARAMS - command with parameters            *
 *                                                                            *
 ******************************************************************************/
static int	parse_command_dyn(const char *command, char **cmd, char **param)
{
	const char	*pl, *pr;
	size_t		cmd_alloc = 0, param_alloc = 0,
			cmd_offset = 0, param_offset = 0;

	for (pl = command; SUCCEED == is_key_char(*pl); pl++)
		;

	if (pl == command)
		return ZBX_COMMAND_ERROR;

	zbx_strncpy_alloc(cmd, &cmd_alloc, &cmd_offset, command, pl - command);

	if ('\0' == *pl)	/* no parameters specified */
		return ZBX_COMMAND_WITHOUT_PARAMS;

	if ('[' != *pl)		/* unsupported character */
		return ZBX_COMMAND_ERROR;

	for (pr = ++pl; '\0' != *pr; pr++)
		;

	if (']' != *--pr)
		return ZBX_COMMAND_ERROR;

	zbx_strncpy_alloc(param, &param_alloc, &param_offset, pl, pr - pl);

	return ZBX_COMMAND_WITH_PARAMS;
}

static int	add_to_metrics(ZBX_METRIC **metrics, ZBX_METRIC *metric, char *error, size_t max_error_len)
{
	int		i = 0;

	while (NULL != (*metrics)[i].key)
	{
		if (0 == strcmp((*metrics)[i].key, metric->key))
		{
			zbx_snprintf(error, max_error_len, "key \"%s\" already exists", metric->key);
			return FAIL;	/* metric already exists */
		}
		i++;
	}

	(*metrics)[i].key = zbx_strdup(NULL, metric->key);
	(*metrics)[i].flags = metric->flags;
	(*metrics)[i].function = metric->function;
	(*metrics)[i].test_param = (NULL == metric->test_param ? NULL : zbx_strdup(NULL, metric->test_param));

	*metrics = (ZBX_METRIC *)zbx_realloc(*metrics, (i + 2) * sizeof(ZBX_METRIC));
	memset(&(*metrics)[i + 1], 0, sizeof(ZBX_METRIC));

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers a new item key into the system                          *
 *                                                                            *
 ******************************************************************************/
int	add_metric(ZBX_METRIC *metric, char *error, size_t max_error_len)
{
	return add_to_metrics(&commands, metric, error, max_error_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers a new item key as local into the system                 *
 *                                                                            *
 ******************************************************************************/
int	add_metric_local(ZBX_METRIC *metric, char *error, size_t max_error_len)
{
	return add_to_metrics(&commands_local, metric, error, max_error_len);
}

#if !defined(__MINGW32__)
int	add_user_parameter(const char *itemkey, char *command, char *error, size_t max_error_len)
{
	int		ret;
	unsigned	flags = CF_USERPARAMETER;
	ZBX_METRIC	metric;
	AGENT_REQUEST	request;

	init_request(&request);

	if (SUCCEED == (ret = parse_item_key(itemkey, &request)))
	{
		if (1 == get_rparams_num(&request) && 0 == strcmp("[*]", itemkey + strlen(get_rkey(&request))))
			flags |= CF_HAVEPARAMS;
		else if (0 != get_rparams_num(&request))
			ret = FAIL;
	}

	if (SUCCEED == ret)
	{
		metric.key = get_rkey(&request);
		metric.flags = flags;
		metric.function = &EXECUTE_USER_PARAMETER;
		metric.test_param = command;

		ret = add_metric(&metric, error, max_error_len);
	}
	else
		zbx_strlcpy(error, "syntax error", max_error_len);

	free_request(&request);

	return ret;
}

void	remove_user_parameters(void)
{
	int	i, usr = -1;

	if (NULL == commands)
		return;

	for (i = 0; NULL != commands[i].key; i++)
	{
		if (0 != (CF_USERPARAMETER & commands[i].flags))
		{
			zbx_free(commands[i].key);
			zbx_free(commands[i].test_param);

			if (0 > usr)
				usr = i;
		}
	}

	if (0 < usr)
	{
		commands = (ZBX_METRIC *)zbx_realloc(commands, ((unsigned int)usr + 1) * sizeof(ZBX_METRIC));
		memset(&commands[usr], 0, sizeof(ZBX_METRIC));
	}
	else if (0 == usr)
	{
		zbx_free(commands);
	}
}

void	get_metrics_copy(ZBX_METRIC **metrics)
{
	unsigned int	i;

	if (NULL == commands)
	{
		*metrics = NULL;
		return;
	}

	for (i = 0; NULL != commands[i].key; i++)
		;

	*metrics = (ZBX_METRIC *)zbx_malloc(*metrics, sizeof(ZBX_METRIC) * (i + 1));

	for (i = 0; NULL != commands[i].key; i++)
	{
		(*metrics)[i].key = zbx_strdup(NULL, commands[i].key);
		(*metrics)[i].flags = commands[i].flags;
		(*metrics)[i].function = commands[i].function;
		(*metrics)[i].test_param = (NULL == commands[i].test_param ?
				NULL : zbx_strdup(NULL, commands[i].test_param));
	}

	memset(&(*metrics)[i], 0, sizeof(ZBX_METRIC));
}

void	set_metrics(ZBX_METRIC *metrics)
{
	free_metrics_ext(&commands);
	commands = metrics;
}
#endif

void	init_metrics(void)
{
	int	i;
	char	error[MAX_STRING_LEN];

	init_key_access_rules();

	commands = (ZBX_METRIC *)zbx_malloc(commands, sizeof(ZBX_METRIC));
	commands[0].key = NULL;
	commands_local = (ZBX_METRIC *)zbx_malloc(commands_local, sizeof(ZBX_METRIC));
	commands_local[0].key = NULL;

#ifdef WITH_AGENT_METRICS
	for (i = 0; NULL != parameters_agent[i].key; i++)
	{
		if (SUCCEED != add_metric(&parameters_agent[i], error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add item key: %s", error);
			exit(EXIT_FAILURE);
		}
	}
#endif

#ifdef WITH_COMMON_METRICS
	for (i = 0; NULL != parameters_common[i].key; i++)
	{
		if (SUCCEED != add_metric(&parameters_common[i], error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add item key: %s", error);
			exit(EXIT_FAILURE);
		}
	}

	for (i = 0; NULL != parameters_common_local[i].key; i++)
	{
		if (SUCCEED != add_metric_local(&parameters_common_local[i], error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add item key: %s", error);
			exit(EXIT_FAILURE);
		}
	}
#endif

#ifdef WITH_HTTP_METRICS
	for (i = 0; NULL != parameters_common_http[i].key; i++)
	{
		if (SUCCEED != add_metric(&parameters_common_http[i], error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add item key: %s", error);
			exit(EXIT_FAILURE);
		}
	}
#endif

#ifdef WITH_SPECIFIC_METRICS
	for (i = 0; NULL != parameters_specific[i].key; i++)
	{
		if (SUCCEED != add_metric(&parameters_specific[i], error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add item key: %s", error);
			exit(EXIT_FAILURE);
		}
	}
#endif

#ifdef WITH_SIMPLE_METRICS
	for (i = 0; NULL != parameters_simple[i].key; i++)
	{
		if (SUCCEED != add_metric(&parameters_simple[i], error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add item key: %s", error);
			exit(EXIT_FAILURE);
		}
	}
#endif

#ifdef WITH_HOSTNAME_METRIC
	if (SUCCEED != add_metric(&parameter_hostname, error, sizeof(error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot add item key: %s", error);
		exit(EXIT_FAILURE);
	}
#endif
}

void	free_metrics_ext(ZBX_METRIC **metrics)
{
	if (NULL != *metrics)
	{
		int	i;

		for (i = 0; NULL != (*metrics)[i].key; i++)
		{
			zbx_free((*metrics)[i].key);
			zbx_free((*metrics)[i].test_param);
		}

		zbx_free(*metrics);
	}
}

void	free_metrics(void)
{
	free_metrics_ext(&commands);
	free_metrics_ext(&commands_local);
	free_key_access_rules();
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes key access rule list                                  *
 *                                                                            *
 ******************************************************************************/
void	init_key_access_rules(void)
{
	zbx_vector_ptr_create(&key_access_rules);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees key access rule and its resources                           *
 *                                                                            *
 ******************************************************************************/
static void	zbx_key_access_rule_free(zbx_key_access_rule_t *rule)
{
	zbx_free(rule->pattern);
	zbx_vector_str_clear_ext(&rule->elements, zbx_str_free);
	zbx_vector_str_destroy(&rule->elements);
	zbx_free(rule);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates key access rule                                           *
 *                                                                            *
 * Parameters: pattern - [IN] the rule pattern                                *
 *             type    - [IN] the rule type                                   *
 *                                                                            *
 *  Return value: The created rule or NULL if pattern was invalid.            *
 *                                                                            *
 ******************************************************************************/
static zbx_key_access_rule_t	*zbx_key_access_rule_create(char *pattern, zbx_key_access_rule_type_t type)
{
	zbx_key_access_rule_t	*rule;

	rule = zbx_malloc(NULL, sizeof(zbx_key_access_rule_t));
	rule->type = type;
	rule->pattern = zbx_strdup(NULL, pattern);
	zbx_vector_str_create(&rule->elements);

	if (SUCCEED != parse_key_access_rule(pattern, rule))
	{
		zbx_key_access_rule_free(rule);
		rule = NULL;
	}
	return rule;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates key access rules configuration                          *
 *                                                                            *
 ******************************************************************************/
void	finalize_key_access_rules_configuration(void)
{
	int			i, j, rules_num, sysrun_index = ZBX_MAX_UINT31_1;
	zbx_key_access_rule_t	*rule, *sysrun_deny;
	char			sysrun_pattern[] = "system.run[*]";

	rules_num = key_access_rules.values_num;

	/* prepare default system.run[*] deny rule to be added at the end */
	if (NULL == (sysrun_deny = zbx_key_access_rule_create(sysrun_pattern, ZBX_KEY_ACCESS_DENY)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	if (FAIL != (i = zbx_vector_ptr_search(&key_access_rules, sysrun_deny, compare_key_access_rules)))
	{
		/* exclude system.run[*] from total number of rules */
		rules_num--;

		/* sysrun_index points at the first rule matching system.run[*] */
		sysrun_index = i;
	}

	if (0 != rules_num)
	{
		/* throw out all rules after '*', because they would never match */
		for (i = 0; i < key_access_rules.values_num; i++)
		{
			rule = (zbx_key_access_rule_t*)key_access_rules.values[i];
			if (1 == rule->elements.values_num && 0 == strcmp(rule->elements.values[0], "*"))
			{
				/* 'match all' rule also matches system.run[*] */
				if (i < sysrun_index)
					sysrun_index = i;

				break;
			}
		}

		if (i != key_access_rules.values_num)
		{
			for (j = ++i; j < key_access_rules.values_num; j++)
			{
				rule = (zbx_key_access_rule_t*)key_access_rules.values[j];
				zabbix_log(LOG_LEVEL_WARNING, "removed unreachable %s \"%s\" rule",
						(ZBX_KEY_ACCESS_ALLOW == rule->type ? "AllowKey" : "DenyKey"),
						rule->pattern);
				zbx_key_access_rule_free(rule);
			}
			key_access_rules.values_num = i;
		}

		/* trailing AllowKey rules are meaningless, because AllowKey=* is default behavior, */
		for (i = key_access_rules.values_num - 1; 0 <= i; i--)
		{
			rule = (zbx_key_access_rule_t*)key_access_rules.values[i];

			if (ZBX_KEY_ACCESS_ALLOW != rule->type)
				break;

			/* system.run allow rules are not redundant because of default system.run[*] deny rule */
			if (0 == rule->elements.values_num || 0 != strcmp(rule->elements.values[0], "system.run"))
			{
				if (i != sysrun_index)
				{
					zabbix_log(LOG_LEVEL_WARNING, "removed redundant trailing AllowKey \"%s\" rule",
							rule->pattern);
				}

				zbx_key_access_rule_free(rule);
				zbx_vector_ptr_remove(&key_access_rules, i);
			}
		}

		if (0 == key_access_rules.values_num)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Item key access rules are configured to match all keys,"
					" indicating possible configuration problem. "
					" Please remove the rules if that was the purpose.");
			exit(EXIT_FAILURE);
		}
	}

	if (ZBX_MAX_UINT31_1 == sysrun_index)
		zbx_vector_ptr_append(&key_access_rules, sysrun_deny);
	else
		zbx_key_access_rule_free(sysrun_deny);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses key access rule expression from AllowKey and DenyKey       *
 *                                                                            *
 * Parameters: pattern - [IN] key access rule wildcard                        *
 *             rule    - [IN] key access rule element to fill                 *
 *                                                                            *
 * Return value: SUCCEED - successful execution                               *
 *               FAIL    - pattern parsing failed                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_key_access_rule(char *pattern, zbx_key_access_rule_t *rule)
{
	char		*pl, *pr = NULL, *param;
	size_t		alloc = 0, offset = 0;
	int		i, size;

	for (pl = pattern; SUCCEED == is_key_char(*pl) || '*' == *pl; pl++);

	if (pl == pattern)
		return FAIL; /* empty key */

	/* extract rule elements [0] = key pattern and all parameters follow */
	zbx_strncpy_alloc(&pr, &alloc, &offset, pattern, pl - pattern);
	zbx_wildcard_minimize(pr);
	zbx_vector_str_append(&rule->elements, pr);
	rule->empty_arguments = 0;

	if ('\0' == *pl)	/* no parameters specified */
		return SUCCEED;

	if ('[' != *pl)		/* unsupported character */
		return FAIL;

	for (pr = ++pl; '\0' != *pr; pr++);

	if (']' != *--pr)
		return FAIL;

	if (1 > pr - pl)	/* no parameters specified */
	{
		rule->empty_arguments = 1;
		return SUCCEED;
	}

	*pr = '\0';
	size = num_param(pl);
	zbx_vector_str_reserve(&rule->elements, size);

	for (i = 0; i < size; i++)
	{
		if (NULL == (param = get_param_dyn(pl, i + 1, NULL)))
			return FAIL;

		zbx_wildcard_minimize(param);
		zbx_vector_str_append(&rule->elements, param);
	}

	*pr = ']';

	/* remove repeated trailing "*" parameters */
	if (1 < size && 0 == strcmp(rule->elements.values[i--], "*"))
	{
		for (; 0 < i; i--)
		{
			if (0 != strcmp(rule->elements.values[i], "*"))
				break;

			zbx_free(rule->elements.values[i + 1]);
			zbx_vector_str_remove(&rule->elements, i + 1);
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Compares two zbx_key_access_rule_t values to perform search       *
 *          within vector. Rule type (allow/deny) is not checked here.        *
 *                                                                            *
 * Parameters: rule_a - [IN] key access rule 1                                *
 *             rule_b - [IN] key access rule 2                                *
 *                                                                            *
 * Return value: If key access rule values are the same, 0 is returned        *
 *               otherwise nonzero value is returned.                         *
 *                                                                            *
 ******************************************************************************/
static int	compare_key_access_rules(const void *rule_a, const void *rule_b)
{
	const zbx_key_access_rule_t	*a, *b;
	int				i;

	a = *(zbx_key_access_rule_t **)rule_a;
	b = *(zbx_key_access_rule_t **)rule_b;

	if (a->empty_arguments != b->empty_arguments || a->elements.values_num != b->elements.values_num)
		return 1;

	for (i = 0; a->elements.values_num > i; i++)
	{
		if (0 != strcmp(a->elements.values[i], b->elements.values[i]))
			return 1;
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds new key access rule from AllowKey and DenyKey parameters     *
 *                                                                            *
 * Parameters: parameter - [IN] the parameter that defined the rule           *
 *             pattern   - [IN] key access rule wildcard                      *
 *             type      - [IN] key access rule type (allow/deny)             *
 *                                                                            *
 * Return value: SUCCEED - successful execution                               *
 *               FAIL    - pattern parsing failed                             *
 *                                                                            *
 ******************************************************************************/
int	add_key_access_rule(const char *parameter, char *pattern, zbx_key_access_rule_type_t type)
{
	zbx_key_access_rule_t	*rule, *r;
	int			i;

	if (NULL == (rule = zbx_key_access_rule_create(pattern, type)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to process %s access rule \"%s\"", parameter, pattern);
		return FAIL;

	}

	if (FAIL != (i = zbx_vector_ptr_search(&key_access_rules, rule, compare_key_access_rules)))
	{
		r = (zbx_key_access_rule_t*)key_access_rules.values[i];

		zabbix_log(LOG_LEVEL_WARNING, "%s access rule \"%s\" was not added"
				" because it %s another rule defined above ",
				parameter, pattern, r->type == type ? "duplicates" : "conflicts with");
		zbx_key_access_rule_free(rule);

		return SUCCEED;
	}

	zbx_vector_ptr_append(&key_access_rules, rule);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks agent metric request against configured access rules       *
 *                                                                            *
 * Parameters: request - [IN] metric request (key and parameters)             *
 *                                                                            *
 * Return value: ZBX_KEY_ACCESS_ALLOW - metric access allowed                 *
 *               ZBX_KEY_ACCESS_DENY  - metric access denied                  *
 *                                                                            *
 ******************************************************************************/
int	check_request_access_rules(AGENT_REQUEST *request)
{
	int			i, j, empty_arguments;
	zbx_key_access_rule_t	*rule;

	/* empty arguments flag means key is followed by empty brackets, which is not the same as no brackets */
	empty_arguments = (1 == request->nparam && 0 == strlen(request->params[0]));

	for (i = 0; key_access_rules.values_num > i; i++)
	{
		rule = (zbx_key_access_rule_t*)key_access_rules.values[i];

		if (0 == strcmp("*", rule->elements.values[0]) && 1 == rule->elements.values_num)
			return rule->type; /* match all */

		if (1 < rule->elements.values_num)
		{
			if (0 == strcmp("*", rule->elements.values[rule->elements.values_num - 1]))
			{
				if (2 == rule->elements.values_num && 0 == request->nparam)
					continue;	/* rule: key[*], request: key */
			}
			else
			{
				if (request->nparam < (rule->elements.values_num - 1))
					continue;	/* too few parameters */

				if (request->nparam > (rule->elements.values_num - 1) && 0 == empty_arguments)
					continue;	/* too many parameters */
			}
		}

		if (0 == zbx_wildcard_match(request->key, rule->elements.values[0]))
			continue;	/* key doesn't match */

		if (0 != rule->empty_arguments)	/* rule expects empty argument list: key[] */
		{
			if (0 != empty_arguments)
				return rule->type;

			continue;
		}

		if (0 != empty_arguments && 1 == rule->elements.values_num)
			continue;	/* no parameters expected by rule */

		if (0 == request->nparam && 1 == rule->elements.values_num)	/* no parameters */
			return rule->type;

		for (j = 1; rule->elements.values_num > j; j++)
		{
			if ((rule->elements.values_num - 1) == j)	/* last parameter */
			{
				if (0 == strcmp("*", rule->elements.values[j]))
					return rule->type;	/* skip next parameter checks */

				if (request->nparam < j)
					break;

				if (0 == zbx_wildcard_match(request->params[j - 1], rule->elements.values[j]))
					break;		/* parameter doesn't match pattern */

				return rule->type;
			}

			if (request->nparam < j ||
					0 == zbx_wildcard_match(request->params[j - 1], rule->elements.values[j]))
				break;	/* parameter doesn't match pattern */
		}
	}

	return ZBX_KEY_ACCESS_ALLOW;	/* allow by default for backward compatibility */
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks agent metric request against configured access rules       *
 *                                                                            *
 * Parameters: metric - [IN] metric requested (key and parameters)            *
 *                                                                            *
 * Return value: ZBX_KEY_ACCESS_ALLOW - metric access allowed                 *
 *               ZBX_KEY_ACCESS_DENY  - metric access denied                  *
 *                                                                            *
 ******************************************************************************/
int	check_key_access_rules(const char *metric)
{
	int		ret;
	AGENT_REQUEST	request;

	init_request(&request);

	if (SUCCEED == parse_item_key(metric, &request))
		ret = check_request_access_rules(&request);
	else
		ret = ZBX_KEY_ACCESS_DENY;

	free_request(&request);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleanup key access rule list                                      *
 *                                                                            *
 ******************************************************************************/
void	free_key_access_rules(void)
{
	int	i;

	for(i = 0; i < key_access_rules.values_num; i++)
		zbx_key_access_rule_free((zbx_key_access_rule_t *)key_access_rules.values[i]);

	zbx_vector_ptr_destroy(&key_access_rules);
}

static void	zbx_log_init(zbx_log_t *log)
{
	log->value = NULL;
	log->source = NULL;
	log->timestamp = 0;
	log->severity = 0;
	log->logeventid = 0;
}

void	init_result(AGENT_RESULT *result)
{
	memset(result, 0, sizeof(AGENT_RESULT));
}

static void	zbx_log_clean(zbx_log_t *log)
{
	zbx_free(log->source);
	zbx_free(log->value);
}

void	zbx_log_free(zbx_log_t *log)
{
	zbx_log_clean(log);
	zbx_free(log);
}

void	free_result(AGENT_RESULT *result)
{
	UNSET_UI64_RESULT(result);
	UNSET_DBL_RESULT(result);
	UNSET_STR_RESULT(result);
	UNSET_TEXT_RESULT(result);
	UNSET_LOG_RESULT(result);
	UNSET_MSG_RESULT(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize the request structure                                  *
 *                                                                            *
 * Parameters: request - pointer to the structure                             *
 *                                                                            *
 ******************************************************************************/
void	init_request(AGENT_REQUEST *request)
{
	request->key = NULL;
	request->nparam = 0;
	request->params = NULL;
	request->types = NULL;
	request->lastlogsize = 0;
	request->mtime = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free memory used by the request parameters                        *
 *                                                                            *
 * Parameters: request - pointer to the request structure                     *
 *                                                                            *
 ******************************************************************************/
static void	free_request_params(AGENT_REQUEST *request)
{
	int	i;

	for (i = 0; i < request->nparam; i++)
		zbx_free(request->params[i]);
	zbx_free(request->params);
	zbx_free(request->types);

	request->nparam = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free memory used by the request                                   *
 *                                                                            *
 * Parameters: request - pointer to the request structure                     *
 *                                                                            *
 ******************************************************************************/
void	free_request(AGENT_REQUEST *request)
{
	zbx_free(request->key);
	free_request_params(request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a new parameter                                               *
 *                                                                            *
 * Parameters: request - [OUT] pointer to the request structure               *
 *             pvalue  - [IN]  parameter value string                         *
 *             type    - [IN]  parameter type                                 *
 *                                                                            *
 ******************************************************************************/
static void	add_request_param(AGENT_REQUEST *request, char *pvalue, zbx_request_parameter_type_t type)
{
	request->nparam++;
	request->params = (char **)zbx_realloc(request->params, request->nparam * sizeof(char *));
	request->params[request->nparam - 1] = pvalue;
	request->types = (zbx_request_parameter_type_t*)zbx_realloc(request->types,
			request->nparam * sizeof(zbx_request_parameter_type_t));
	request->types[request->nparam - 1] = type;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse item command (key) and fill AGENT_REQUEST structure         *
 *                                                                            *
 * Parameters: itemkey - complete item key                                    *
 *                                                                            *
 * Return value: request - structure filled with data from item key           *
 *                                                                            *
 * Comments: thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
int	parse_item_key(const char *itemkey, AGENT_REQUEST *request)
{
	int	i, ret = FAIL;
	char	*key = NULL, *params = NULL;

	switch (parse_command_dyn(itemkey, &key, &params))
	{
		case ZBX_COMMAND_WITH_PARAMS:
			if (0 == (request->nparam = num_param(params)))
				goto out;	/* key is badly formatted */

			request->params = (char **)zbx_malloc(request->params, request->nparam * sizeof(char *));
			request->types = (zbx_request_parameter_type_t*)zbx_malloc(request->types,
					request->nparam * sizeof(zbx_request_parameter_type_t));

			for (i = 0; i < request->nparam; i++)
				request->params[i] = get_param_dyn(params, i + 1, &request->types[i]);
			break;
		case ZBX_COMMAND_ERROR:
			goto out;	/* key is badly formatted */
	}

	request->key = key;
	key = NULL;

	ret = SUCCEED;
out:
	zbx_free(params);
	zbx_free(key);

	return ret;
}

void	test_parameter(const char *key)
{
#define ZBX_KEY_COLUMN_WIDTH	45

	AGENT_RESULT	result;

	printf("%-*s", ZBX_KEY_COLUMN_WIDTH, key);

	init_result(&result);

	if (SUCCEED == process(key, PROCESS_WITH_ALIAS, &result))
	{
		char	buffer[ZBX_MAX_DOUBLE_LEN + 1];

		if (0 != ISSET_UI64(&result))
			printf(" [u|" ZBX_FS_UI64 "]", result.ui64);

		if (0 != ISSET_DBL(&result))
			printf(" [d|%s]", zbx_print_double(buffer, sizeof(buffer), result.dbl));

		if (0 != ISSET_STR(&result))
			printf(" [s|%s]", result.str);

		if (0 != ISSET_TEXT(&result))
			printf(" [t|%s]", result.text);

		if (0 != ISSET_MSG(&result))
			printf(" [m|%s]", result.msg);
	}
	else
	{
		if (0 != ISSET_MSG(&result))
			printf(" [m|" ZBX_NOTSUPPORTED "] [%s]", result.msg);
		else
			printf(" [m|" ZBX_NOTSUPPORTED "]");
	}

	free_result(&result);

	printf("\n");

	fflush(stdout);

#undef ZBX_KEY_COLUMN_WIDTH
}

void	test_parameters(void)
{
	int	i;
	char	*key = NULL;
	size_t	key_alloc = 0;

	for (i = 0; NULL != commands[i].key; i++)
	{
		if (0 != strcmp(commands[i].key, "__UserPerfCounter"))
		{
			size_t	key_offset = 0;

			zbx_strcpy_alloc(&key, &key_alloc, &key_offset, commands[i].key);

			if (0 == (commands[i].flags & CF_USERPARAMETER) && NULL != commands[i].test_param)
			{
				zbx_chrcpy_alloc(&key, &key_alloc, &key_offset, '[');
				zbx_strcpy_alloc(&key, &key_alloc, &key_offset, commands[i].test_param);
				zbx_chrcpy_alloc(&key, &key_alloc, &key_offset, ']');
			}

			if (ZBX_KEY_ACCESS_ALLOW == check_key_access_rules(key))
				test_parameter(key);
		}
	}

	zbx_free(key);

	test_aliases();
}

static int	zbx_check_user_parameter(const char *param, char *error, int max_error_len)
{
	const char	suppressed_chars[] = "\\'\"`*?[]{}~$!&;()<>|#@\n", *c;
	char		*buf = NULL;
	size_t		buf_alloc = 128, buf_offset = 0;

	if (0 != CONFIG_UNSAFE_USER_PARAMETERS)
		return SUCCEED;

	for (c = suppressed_chars; '\0' != *c; c++)
	{
		if (NULL == strchr(param, *c))
			continue;

		buf = (char *)zbx_malloc(buf, buf_alloc);

		for (c = suppressed_chars; '\0' != *c; c++)
		{
			if (c != suppressed_chars)
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, ", ");

			if (0 != isprint(*c))
				zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, *c);
			else
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "0x%02x", (unsigned int)(*c));
		}

		zbx_snprintf(error, max_error_len, "Special characters \"%s\" are not allowed in the parameters.", buf);

		zbx_free(buf);

		return FAIL;
	}

	return SUCCEED;
}

static int	replace_param(const char *cmd, const AGENT_REQUEST *request, char **out, char *error, int max_error_len)
{
	const char	*pl = cmd, *pr, *tmp;
	size_t		out_alloc = 0, out_offset = 0;
	int		num, ret = SUCCEED;

	while (NULL != (pr = strchr(pl, '$')))
	{
		zbx_strncpy_alloc(out, &out_alloc, &out_offset, pl, pr - pl);

		/* check if increasing pointer by 1 will not result in buffer overrun */
		if ('\0' != pr[1])
			pr++;

		if ('0' == *pr)
		{
			zbx_strcpy_alloc(out, &out_alloc, &out_offset, cmd);
		}
		else if ('1' <= *pr && *pr <= '9')
		{
			num = (int)(*pr - '0');

			if (request->nparam >= num)
			{
				tmp = get_rparam(request, num - 1);

				if (SUCCEED != (ret = zbx_check_user_parameter(tmp, error, max_error_len)))
					break;

				zbx_strcpy_alloc(out, &out_alloc, &out_offset, tmp);
			}
		}
		else
		{
			if ('$' != *pr)
				zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '$');
			zbx_chrcpy_alloc(out, &out_alloc, &out_offset, *pr);
		}

		pl = pr + 1;
	}

	if (SUCCEED == ret)
		zbx_strcpy_alloc(out, &out_alloc, &out_offset, pl);
	else
		zbx_free(*out);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute agent check                                               *
 *                                                                            *
 * Parameters: in_command - item key                                          *
 *             flags - PROCESS_LOCAL_COMMAND, allow execution of system.run   *
 *                     PROCESS_MODULE_COMMAND, execute item from a module     *
 *                     PROCESS_WITH_ALIAS, substitute agent Alias             *
 *                                                                            *
 * Return value: SUCCEED - successful execution                               *
 *               NOTSUPPORTED - item key is not supported or other error      *
 *               result - contains item value or error message                *
 *                                                                            *
 ******************************************************************************/
int	process(const char *in_command, unsigned flags, AGENT_RESULT *result)
{
	int		ret = NOTSUPPORTED;
	ZBX_METRIC	*command = NULL;
	AGENT_REQUEST	request;

	init_request(&request);

	if (SUCCEED != parse_item_key((0 == (flags & PROCESS_WITH_ALIAS) ? in_command : zbx_alias_get(in_command)),
			&request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto notsupported;
	}

	if (0 == (flags & PROCESS_LOCAL_COMMAND) && ZBX_KEY_ACCESS_ALLOW != check_request_access_rules(&request))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Key access denied: \"%s\"", in_command);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key."));
		goto notsupported;
	}

	/* system.run is not allowed by default except for getting hostname for daemons */
	if (1 != CONFIG_ENABLE_REMOTE_COMMANDS && 0 == (flags & PROCESS_LOCAL_COMMAND) &&
			0 == strcmp(request.key, "system.run"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Remote commands are not enabled."));
		goto notsupported;
	}

	if (0 != (flags & PROCESS_LOCAL_COMMAND))
	{
		for (command = commands_local; NULL != command->key; command++)
		{
			if (0 == strcmp(command->key, request.key))
				break;
		}
	}

	if (NULL == command || NULL == command->key)
	{
		for (command = commands; NULL != command->key; command++)
		{
			if (0 == strcmp(command->key, request.key))
				break;
		}
	}

	/* item key not found */
	if (NULL == command->key)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key."));
		goto notsupported;
	}

	/* expected item from a module */
	if (0 != (flags & PROCESS_MODULE_COMMAND) && 0 == (command->flags & CF_MODULE))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key."));
		goto notsupported;
	}

	/* command does not accept parameters but was called with parameters */
	if (0 == (command->flags & CF_HAVEPARAMS) && 0 != request.nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Item does not allow parameters."));
		goto notsupported;
	}

	if (0 != (command->flags & CF_USERPARAMETER))
	{
		if (0 != (command->flags & CF_HAVEPARAMS))
		{
			char	*parameters = NULL, error[MAX_STRING_LEN];

			if (FAIL == replace_param(command->test_param, &request, &parameters, error, sizeof(error)))
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, error));
				goto notsupported;
			}

			free_request_params(&request);
			add_request_param(&request, parameters, REQUEST_PARAMETER_TYPE_STRING);
		}
		else
		{
			free_request_params(&request);
			add_request_param(&request, zbx_strdup(NULL, command->test_param),
					REQUEST_PARAMETER_TYPE_STRING);
		}
	}

	if (SYSINFO_RET_OK != command->function(&request, result))
	{
		/* "return NOTSUPPORTED;" would be more appropriate here for preserving original error */
		/* message in "result" but would break things relying on ZBX_NOTSUPPORTED message. */
		if (0 != (command->flags & CF_MODULE) && 0 == ISSET_MSG(result))
			SET_MSG_RESULT(result, zbx_strdup(NULL, ZBX_NOTSUPPORTED_MSG));

		goto notsupported;
	}

	ret = SUCCEED;

notsupported:
	free_request(&request);

	return ret;
}

static void	add_log_result(AGENT_RESULT *result, const char *value)
{
	result->log = (zbx_log_t *)zbx_malloc(result->log, sizeof(zbx_log_t));

	zbx_log_init(result->log);

	result->log->value = zbx_strdup(result->log->value, value);
	result->type |= AR_LOG;
}

int	set_result_type(AGENT_RESULT *result, int value_type, char *c)
{
	zbx_uint64_t	value_uint64;
	int		ret = FAIL;

	assert(result);

	switch (value_type)
	{
		double	dbl_tmp;

		case ITEM_VALUE_TYPE_UINT64:
			zbx_trim_integer(c);
			del_zeros(c);

			if (SUCCEED == is_uint64(c, &value_uint64))
			{
				SET_UI64_RESULT(result, value_uint64);
				ret = SUCCEED;
			}
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_trim_float(c);

			if (SUCCEED == is_double(c, &dbl_tmp))
			{
				SET_DBL_RESULT(result, dbl_tmp);
				ret = SUCCEED;
			}
			break;
		case ITEM_VALUE_TYPE_STR:
			zbx_replace_invalid_utf8(c);
			SET_STR_RESULT(result, zbx_strdup(NULL, c));
			ret = SUCCEED;
			break;
		case ITEM_VALUE_TYPE_TEXT:
			zbx_replace_invalid_utf8(c);
			SET_TEXT_RESULT(result, zbx_strdup(NULL, c));
			ret = SUCCEED;
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_replace_invalid_utf8(c);
			add_log_result(result, c);
			ret = SUCCEED;
			break;
	}

	return ret;
}

void	set_result_meta(AGENT_RESULT *result, zbx_uint64_t lastlogsize, int mtime)
{
	result->lastlogsize = lastlogsize;
	result->mtime = mtime;
	result->type |= AR_META;
}

static zbx_uint64_t	*get_result_ui64_value(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	assert(result);

	if (0 != ISSET_UI64(result))
	{
		/* nothing to do */
	}
	else if (0 != ISSET_DBL(result))
	{
		SET_UI64_RESULT(result, result->dbl);
	}
	else if (0 != ISSET_STR(result))
	{
		zbx_trim_integer(result->str);
		del_zeros(result->str);

		if (SUCCEED != is_uint64(result->str, &value))
			return NULL;

		SET_UI64_RESULT(result, value);
	}
	else if (0 != ISSET_TEXT(result))
	{
		zbx_trim_integer(result->text);
		del_zeros(result->text);

		if (SUCCEED != is_uint64(result->text, &value))
			return NULL;

		SET_UI64_RESULT(result, value);
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ISSET_UI64(result))
		return &result->ui64;

	return NULL;
}

static double	*get_result_dbl_value(AGENT_RESULT *result)
{
	double	value;

	assert(result);

	if (0 != ISSET_DBL(result))
	{
		/* nothing to do */
	}
	else if (0 != ISSET_UI64(result))
	{
		SET_DBL_RESULT(result, result->ui64);
	}
	else if (0 != ISSET_STR(result))
	{
		zbx_trim_float(result->str);

		if (SUCCEED != is_double(result->str, &value))
			return NULL;

		SET_DBL_RESULT(result, value);
	}
	else if (0 != ISSET_TEXT(result))
	{
		zbx_trim_float(result->text);

		if (SUCCEED != is_double(result->text, &value))
			return NULL;

		SET_DBL_RESULT(result, value);
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ISSET_DBL(result))
		return &result->dbl;

	return NULL;
}

static char	**get_result_str_value(AGENT_RESULT *result)
{
	char	*p, tmp;

	assert(result);

	if (0 != ISSET_STR(result))
	{
		/* nothing to do */
	}
	else if (0 != ISSET_TEXT(result))
	{
		/* NOTE: copy only line */
		for (p = result->text; '\0' != *p && '\r' != *p && '\n' != *p; p++);
		tmp = *p; /* remember result->text character */
		*p = '\0'; /* temporary replace */
		SET_STR_RESULT(result, zbx_strdup(NULL, result->text)); /* copy line */
		*p = tmp; /* restore result->text character */
	}
	else if (0 != ISSET_UI64(result))
	{
		SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64));
	}
	else if (0 != ISSET_DBL(result))
	{
		SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl));
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ISSET_STR(result))
		return &result->str;

	return NULL;
}

static char	**get_result_text_value(AGENT_RESULT *result)
{
	assert(result);

	if (0 != ISSET_TEXT(result))
	{
		/* nothing to do */
	}
	else if (0 != ISSET_STR(result))
	{
		SET_TEXT_RESULT(result, zbx_strdup(NULL, result->str));
	}
	else if (0 != ISSET_UI64(result))
	{
		SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64));
	}
	else if (0 != ISSET_DBL(result))
	{
		SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl));
	}
	/* skip AR_MESSAGE - it is information field */

	if (0 != ISSET_TEXT(result))
		return &result->text;

	return NULL;
}

static zbx_log_t	*get_result_log_value(AGENT_RESULT *result)
{
	if (0 != ISSET_LOG(result))
		return result->log;

	if (0 != ISSET_VALUE(result))
	{
		result->log = (zbx_log_t *)zbx_malloc(result->log, sizeof(zbx_log_t));

		zbx_log_init(result->log);

		if (0 != ISSET_STR(result))
			result->log->value = zbx_strdup(result->log->value, result->str);
		else if (0 != ISSET_TEXT(result))
			result->log->value = zbx_strdup(result->log->value, result->text);
		else if (0 != ISSET_UI64(result))
			result->log->value = zbx_dsprintf(result->log->value, ZBX_FS_UI64, result->ui64);
		else if (0 != ISSET_DBL(result))
			result->log->value = zbx_dsprintf(result->log->value, ZBX_FS_DBL, result->dbl);

		result->type |= AR_LOG;

		return result->log;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return value of result in special type                            *
 *          if value missing, convert existing value to requested type        *
 *                                                                            *
 * Return value:                                                              *
 *         NULL - if value is missing or can't be converted                   *
 *                                                                            *
 * Comments:  better use definitions                                          *
 *                GET_UI64_RESULT                                             *
 *                GET_DBL_RESULT                                              *
 *                GET_STR_RESULT                                              *
 *                GET_TEXT_RESULT                                             *
 *                GET_LOG_RESULT                                              *
 *                GET_MSG_RESULT                                              *
 *                                                                            *
 *    AR_MESSAGE - skipped in conversion                                      *
 *                                                                            *
 ******************************************************************************/
void	*get_result_value_by_type(AGENT_RESULT *result, int require_type)
{
	assert(result);

	switch (require_type)
	{
		case AR_UINT64:
			return (void *)get_result_ui64_value(result);
		case AR_DOUBLE:
			return (void *)get_result_dbl_value(result);
		case AR_STRING:
			return (void *)get_result_str_value(result);
		case AR_TEXT:
			return (void *)get_result_text_value(result);
		case AR_LOG:
			return (void *)get_result_log_value(result);
		case AR_MESSAGE:
			if (0 != ISSET_MSG(result))
				return (void *)(&result->msg);
			break;
		default:
			break;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unquotes special symbols in item key parameter                    *
 *                                                                            *
 * Parameters: param - [IN/OUT] item key parameter                            *
 *                                                                            *
 * Comments:                                                                  *
 *   "param"     => param                                                     *
 *   "\"param\"" => "param"                                                   *
 *                                                                            *
 ******************************************************************************/
void	unquote_key_param(char *param)
{
	char	*dst;

	if ('"' != *param)
		return;

	for (dst = param++; '\0' != *param; param++)
	{
		if ('\\' == *param && '"' == param[1])
			continue;

		*dst++ = *param;
	}
	*--dst = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: quotes special symbols in item key parameter                      *
 *                                                                            *
 * Parameters: param   - [IN/OUT] item key parameter                          *
 *             forced  - [IN] 1 - enclose parameter in " even if it does not  *
 *                                contain any special characters              *
 *                            0 - do nothing if the parameter does not        *
 *                                contain any special characters              *
 *                                                                            *
 * Return value: SUCCEED - if parameter was successfully quoted or quoting    *
 *                         was not necessary                                  *
 *               FAIL    - if parameter needs to but cannot be quoted due to  *
 *                         backslash in the end                               *
 *                                                                            *
 ******************************************************************************/
int	quote_key_param(char **param, int forced)
{
	size_t	sz_src, sz_dst;

	if (0 == forced)
	{
		if ('"' != **param && ' ' != **param && '[' != **param && NULL == strchr(*param, ',') &&
				NULL == strchr(*param, ']'))
		{
			return SUCCEED;
		}
	}

	if (0 != (sz_src = strlen(*param)) && '\\' == (*param)[sz_src - 1])
		return FAIL;

	sz_dst = zbx_get_escape_string_len(*param, "\"") + 3;

	*param = (char *)zbx_realloc(*param, sz_dst);

	(*param)[--sz_dst] = '\0';
	(*param)[--sz_dst] = '"';

	while (0 < sz_src)
	{
		(*param)[--sz_dst] = (*param)[--sz_src];
		if ('"' == (*param)[sz_src])
			(*param)[--sz_dst] = '\\';
	}
	(*param)[--sz_dst] = '"';

	return SUCCEED;
}

#ifdef HAVE_KSTAT_H
zbx_uint64_t	get_kstat_numeric_value(const kstat_named_t *kn)
{
	switch (kn->data_type)
	{
		case KSTAT_DATA_INT32:
			return kn->value.i32;
		case KSTAT_DATA_UINT32:
			return kn->value.ui32;
		case KSTAT_DATA_INT64:
			return kn->value.i64;
		case KSTAT_DATA_UINT64:
			return kn->value.ui64;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return 0;
	}
}
#endif

#if !defined(_WINDOWS) && !defined(__MINGW32__)
#if defined(WITH_AGENT2_METRICS)
int	zbx_execute_threaded_metric(zbx_metric_func_t metric_func, AGENT_REQUEST *request, AGENT_RESULT *result)
{
	/* calling fork() in a multithreaded program may result in deadlock on mutex */
	return metric_func(request, result);
}
#else
/******************************************************************************
 *                                                                            *
 * Purpose: serialize agent result to transfer over pipe/socket               *
 *                                                                            *
 * Parameters: data        - [IN/OUT] the data buffer                         *
 *             data_alloc  - [IN/OUT] the data buffer allocated size          *
 *             data_offset - [IN/OUT] the data buffer data size               *
 *             agent_ret   - [IN] the agent result return code                *
 *             result      - [IN] the agent result                            *
 *                                                                            *
 * Comments: The agent result is serialized as [rc][type][data] where:        *
 *             [rc] the agent result return code, 4 bytes                     *
 *             [type] the agent result data type, 1 byte                      *
 *             [data] the agent result data, null terminated string (optional)*
 *                                                                            *
 ******************************************************************************/
static void	serialize_agent_result(char **data, size_t *data_alloc, size_t *data_offset, int agent_ret,
		AGENT_RESULT *result)
{
	char	**pvalue, result_type;
	size_t	value_len;

	if (SYSINFO_RET_OK == agent_ret)
	{
		if (ISSET_TEXT(result))
			result_type = 't';
		else if (ISSET_STR(result))
			result_type = 's';
		else if (ISSET_UI64(result))
			result_type = 'u';
		else if (ISSET_DBL(result))
			result_type = 'd';
		else if (ISSET_MSG(result))
			result_type = 'm';
		else
			result_type = '-';
	}
	else
		result_type = 'm';

	switch (result_type)
	{
		case 't':
		case 's':
		case 'u':
		case 'd':
			pvalue = GET_TEXT_RESULT(result);
			break;
		case 'm':
			pvalue = GET_MSG_RESULT(result);
			break;
		default:
			pvalue = NULL;
	}

	if (NULL != pvalue)
	{
		value_len = strlen(*pvalue) + 1;
	}
	else
	{
		value_len = 0;
		result_type = '-';
	}

	if (*data_alloc - *data_offset < value_len + 1 + sizeof(int))
	{
		while (*data_alloc - *data_offset < value_len + 1 + sizeof(int))
			*data_alloc *= 1.5;

		*data = (char *)zbx_realloc(*data, *data_alloc);
	}

	memcpy(*data + *data_offset, &agent_ret, sizeof(int));
	*data_offset += sizeof(int);

	(*data)[(*data_offset)++] = result_type;

	if ('-' != result_type)
	{
		memcpy(*data + *data_offset, *pvalue, value_len);
		*data_offset += value_len;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize agent result                                          *
 *                                                                            *
 * Parameters: data        - [IN] the data to deserialize                     *
 *             result      - [OUT] the agent result                           *
 *                                                                            *
 * Return value: the agent result return code (SYSINFO_RET_*)                 *
 *                                                                            *
 ******************************************************************************/
static int	deserialize_agent_result(char *data, AGENT_RESULT *result)
{
	int	ret, agent_ret;
	char	type;

	memcpy(&agent_ret, data, sizeof(int));
	data += sizeof(int);

	type = *data++;

	if ('m' == type || 0 == strcmp(data, ZBX_NOTSUPPORTED))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, data));
		return agent_ret;
	}

	switch (type)
	{
		case 't':
			ret = set_result_type(result, ITEM_VALUE_TYPE_TEXT, data);
			break;
		case 's':
			ret = set_result_type(result, ITEM_VALUE_TYPE_STR, data);
			break;
		case 'u':
			ret = set_result_type(result, ITEM_VALUE_TYPE_UINT64, data);
			break;
		case 'd':
			ret = set_result_type(result, ITEM_VALUE_TYPE_FLOAT, data);
			break;
		default:
			ret = SUCCEED;
	}

	/* return deserialized return code or SYSINFO_RET_FAIL if setting result data failed */
	return (FAIL == ret ? SYSINFO_RET_FAIL : agent_ret);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute metric in a separate process/thread so it can be          *
 *          killed/terminated when timeout is detected                        *
 *                                                                            *
 * Parameters: metric_func - [IN] the metric function to execute              *
 *             ...                the metric function parameters              *
 *                                                                            *
 * Return value:                                                              *
 *         SYSINFO_RET_OK - the metric was executed successfully              *
 *         SYSINFO_RET_FAIL - otherwise                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_execute_threaded_metric(zbx_metric_func_t metric_func, AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_OK;
	pid_t	pid;
	int	fds[2], n, status;
	char	buffer[MAX_STRING_LEN], *data;
	size_t	data_alloc = MAX_STRING_LEN, data_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __func__, request->key);

	if (-1 == pipe(fds))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot create data pipe: %s", strerror_from_system(errno)));
		ret = SYSINFO_RET_FAIL;
		goto out;
	}

	if (-1 == (pid = zbx_fork()))
	{
		close(fds[0]);
		close(fds[1]);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot fork data process: %s", strerror_from_system(errno)));
		ret = SYSINFO_RET_FAIL;
		goto out;
	}

	data = (char *)zbx_malloc(NULL, data_alloc);

	if (0 == pid)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "executing in data process for key:'%s'", request->key);

		zbx_set_metric_thread_signal_handler();

		close(fds[0]);

		ret = metric_func(request, result);
		serialize_agent_result(&data, &data_alloc, &data_offset, ret, result);

		ret = zbx_write_all(fds[1], data, data_offset);

		zbx_free(data);
		free_result(result);

		close(fds[1]);

		exit(SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE);
	}

	close(fds[1]);

	zbx_alarm_on(CONFIG_TIMEOUT);

	while (0 != (n = read(fds[0], buffer, sizeof(buffer))))
	{
		if (SUCCEED == zbx_alarm_timed_out())
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while waiting for data."));
			kill(pid, SIGKILL);
			ret = SYSINFO_RET_FAIL;
			break;
		}

		if (-1 == n)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Error while reading data: %s", zbx_strerror(errno)));
			kill(pid, SIGKILL);
			ret = SYSINFO_RET_FAIL;
			break;
		}

		if ((int)(data_alloc - data_offset) < n + 1)
		{
			while ((int)(data_alloc - data_offset) < n + 1)
				data_alloc *= 1.5;

			data = (char *)zbx_realloc(data, data_alloc);
		}

		memcpy(data + data_offset, buffer, n);
		data_offset += n;
		data[data_offset] = '\0';
	}

	zbx_alarm_off();

	close(fds[0]);

	while (-1 == waitpid(pid, &status, 0))
	{
		if (EINTR != errno)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to wait on child processes: %s", zbx_strerror(errno));
			ret = SYSINFO_RET_FAIL;
			break;
		}
	}

	if (SYSINFO_RET_OK == ret)
	{
		if (0 == WIFEXITED(status))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Data gathering process terminated unexpectedly with"
					" error %d.", status));
			kill(pid, SIGKILL);
			ret = SYSINFO_RET_FAIL;
		}
		else if (EXIT_SUCCESS != WEXITSTATUS(status))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Data gathering process terminated with error %d.",
					status));
			ret = SYSINFO_RET_FAIL;
		}
		else
			ret = deserialize_agent_result(data, result);
	}

	zbx_free(data);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __func__, zbx_sysinfo_ret_string(ret),
			ISSET_MSG(result) ? result->msg : "");
	return ret;
}
#endif
#else

static ZBX_THREAD_LOCAL zbx_uint32_t	mutex_flag = ZBX_MUTEX_ALL_ALLOW;

zbx_uint32_t get_thread_global_mutex_flag()
{
	return mutex_flag;
}

typedef struct
{
	zbx_metric_func_t	func;
	AGENT_REQUEST		*request;
	AGENT_RESULT		*result;
	zbx_uint32_t		mutex_flag; /* in regular case should always be = ZBX_MUTEX_ALL_ALLOW */
	HANDLE			timeout_event;
	int			agent_ret;
}
zbx_metric_thread_args_t;

ZBX_THREAD_ENTRY(agent_metric_thread, data)
{
	zbx_metric_thread_args_t	*args = (zbx_metric_thread_args_t *)((zbx_thread_args_t *)data)->args;
	mutex_flag = args->mutex_flag;

	zabbix_log(LOG_LEVEL_DEBUG, "executing in data thread for key:'%s'", args->request->key);

	if (SYSINFO_RET_FAIL == (args->agent_ret = args->func(args->request, args->result, args->timeout_event)))
	{
		if (NULL == GET_MSG_RESULT(args->result))
			SET_MSG_RESULT(args->result, zbx_strdup(NULL, ZBX_NOTSUPPORTED));
	}

	zbx_thread_exit(0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute metric in a separate process/thread so it can be          *
 *          killed/terminated when timeout is detected                        *
 *                                                                            *
 * Parameters: metric_func - [IN] the metric function to execute              *
 *             ...                the metric function parameters              *
 *                                                                            *
 * Return value:                                                              *
 *         SYSINFO_RET_OK - the metric was executed successfully              *
 *         SYSINFO_RET_FAIL - otherwise                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_execute_threaded_metric(zbx_metric_func_t metric_func, AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_THREAD_HANDLE		thread;
	zbx_thread_args_t		thread_args;
	zbx_metric_thread_args_t	metric_args = {metric_func, request, result, ZBX_MUTEX_THREAD_DENIED |
							ZBX_MUTEX_LOGGING_DENIED};
	DWORD				rc;
	BOOL				terminate_thread = FALSE;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __func__, request->key);

	if (NULL == (metric_args.timeout_event = CreateEvent(NULL, TRUE, FALSE, NULL)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot create timeout event for data thread: %s",
				strerror_from_system(GetLastError())));
		return SYSINFO_RET_FAIL;
	}

	thread_args.args = (void *)&metric_args;

	zbx_thread_start(agent_metric_thread, &thread_args, &thread);

	if (ZBX_THREAD_ERROR == thread)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot start data thread: %s",
				strerror_from_system(GetLastError())));
		CloseHandle(metric_args.timeout_event);
		return SYSINFO_RET_FAIL;
	}

	/* 1000 is multiplier for converting seconds into milliseconds */
	if (WAIT_FAILED == (rc = WaitForSingleObject(thread, CONFIG_TIMEOUT * 1000)))
	{
		/* unexpected error */

		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot wait for data: %s",
				strerror_from_system(GetLastError())));
		terminate_thread = TRUE;
	}
	else if (WAIT_TIMEOUT == rc)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while waiting for data."));

		/* timeout; notify thread to clean up and exit, if stuck then terminate it */

		if (FALSE == SetEvent(metric_args.timeout_event))
		{
			zabbix_log(LOG_LEVEL_ERR, "SetEvent() failed: %s", strerror_from_system(GetLastError()));
			terminate_thread = TRUE;
		}
		else
		{
			DWORD	timeout_rc = WaitForSingleObject(thread, 3000);	/* wait up to 3 seconds */

			if (WAIT_FAILED == timeout_rc)
			{
				zabbix_log(LOG_LEVEL_ERR, "Waiting for data failed: %s",
						strerror_from_system(GetLastError()));
				terminate_thread = TRUE;
			}
			else if (WAIT_TIMEOUT == timeout_rc)
			{
				zabbix_log(LOG_LEVEL_ERR, "Stuck data thread");
				terminate_thread = TRUE;
			}
			/* timeout_rc must be WAIT_OBJECT_0 (signaled) */
		}
	}

	if (TRUE == terminate_thread)
	{
		if (FALSE != TerminateThread(thread, 0))
		{
			zabbix_log(LOG_LEVEL_ERR, "%s(): TerminateThread() for %s[%s%s] succeeded", __func__,
					request->key, (0 < request->nparam) ? request->params[0] : "",
					(1 < request->nparam) ? ",..." : "");
		}
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "%s(): TerminateThread() for %s[%s%s] failed: %s", __func__,
					request->key, (0 < request->nparam) ? request->params[0] : "",
					(1 < request->nparam) ? ",..." : "",
					strerror_from_system(GetLastError()));
		}
	}

	CloseHandle(thread);
	CloseHandle(metric_args.timeout_event);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __func__,
			zbx_sysinfo_ret_string(metric_args.agent_ret), ISSET_MSG(result) ? result->msg : "");

	return WAIT_OBJECT_0 == rc ? metric_args.agent_ret : SYSINFO_RET_FAIL;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: frees previously allocated mount-point structure                  *
 *                                                                            *
 * Parameters: mpoint - [IN] pointer to structure from vector                 *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mpoints_free(zbx_mpoint_t *mpoint)
{
	zbx_free(mpoint);
}

#ifndef _WINDOWS
int	hostname_handle_params(AGENT_REQUEST *request, AGENT_RESULT *result, char *hostname)
{
	char	*type, *transform;

	type = get_rparam(request, 0);
	transform = get_rparam(request, 1);

	if (NULL != type && '\0' != *type && 0 != strcmp(type, "host"))
	{
		if (0 == strcmp(type, "shorthost"))
		{
			char	*dot;

			if (NULL != (dot = strchr(hostname, '.')))
				*dot = '\0';
		}
		else if (0 == strcmp(type, "netbios"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "NetBIOS is not supported on the current platform."));
			return FAIL;
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
			return FAIL;
		}
	}

	if (NULL != transform && '\0' != *transform && 0 != strcmp(transform, "none"))
	{
		if (0 == strcmp(transform, "lower"))
		{
			zbx_strlower(hostname);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return FAIL;
		}
	}

	SET_STR_RESULT(result, hostname);

	return SUCCEED;
}
#endif
