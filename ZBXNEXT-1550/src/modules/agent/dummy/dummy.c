/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "sysinfo.h"
#include "module.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_module_version                                               *
 *                                                                            *
 * Purpose: returns version number of the module                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: 1 - current version supported by the agent                   *
 *                                                                            *
 ******************************************************************************/
int zbx_module_version()
{
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_module_item_list                                             *
 *                                                                            *
 * Purpose: returns list of item keys supported by the module                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: list of item keys                                            *
 *                                                                            *
 * Comment: item keys that accept optional parameters must have [*] included  *
 *                                                                            *
 ******************************************************************************/
char **zbx_module_item_list()
{
	/* keys having [*] accept optional parameters */
	/* key, func, useparam, testparam */
	static char *keys[]={"dummy.ping", "dummy.echo[*]", "dummy.random[*]"};

	return keys;
}

static int dummy_ping(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, 1);

	return	ZBX_MODULE_OK;
}

static int dummy_echo(AGENT_REQUEST *request, AGENT_RESULT *result)
{
/* TODO nparam return 1 event in case if there are no parameters, it should be fixed */
	if (request->nparam != 1)
	{
		/* set optional error message */
		SET_MSG_RESULT(result, strdup("Incorrect number of parameters, expected one parameter."));
		return ZBX_MODULE_FAIL;
	}

	SET_STR_RESULT(result, strdup(get_rparam(request,0)));

	return	ZBX_MODULE_OK;
}

static int dummy_random(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	from, to;

	if (request->nparam != 2)
	{
		/* set optional error message */
		SET_MSG_RESULT(result, strdup("Incorrect number of parameters, expected two parameters."));
		return ZBX_MODULE_FAIL;
	}

	/* there is no strict validation of parameters for simplicity sake */
	from = atoi(get_rparam(request, 0));
	to = atoi(get_rparam(request, 1));

	if (from > to)
	{
		SET_MSG_RESULT(result, strdup("Incorrect range given."));
		return ZBX_MODULE_FAIL;
	}

	SET_UI64_RESULT(result, from + rand() % (to - from+1));

	return	ZBX_MODULE_OK;
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_module_item_process                                          *
 *                                                                            *
 * Purpose: a main entry point for processing of items                        *
 *                                                                            *
 * Parameters: request - structure that contains item key and parameters      *
 *              request->key - item key without parameters                    *
 *              request->nparam - number of parameters                        *
 *              request->timeout - processing should not take longer than     *
 *                                 this number of seconds                     *
 *              request->params[N-1] - pointers to item key parameters        *
 *                                                                            *
 *             result - structure that will contain result                    *
 *                                                                            *
 * Return value: SYSINFO_RET_FAIL - function failed, item will be marked      *
 *                                  as not supported by zabbix                *
 *               SYSINFO_RET_OK - success                                     *
 *                                                                            *
 * Comment: get_param(request, N-1) can be used to get a pointer to the Nth   *
 *          parameter starting from 0 (first parameter). Make sure it exists  *
 *          by checking value of request->nparam.                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_module_item_process(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int ret = ZBX_MODULE_FAIL;

	/* it always returns 1 */
	if (0 == strcmp(request->key, "dummy.ping"))
	{
		ret = dummy_ping(request, result);
	}
	/* dummy.echo[param1] accepts one parameter and returns it as a result. For example: dummy.echo[abc] -> abc */
	else if (0 == strcmp(request->key, "dummy.echo"))
	{
		ret = dummy_echo(request, result);
	}
	/* dummy.random[from,to] returns integer random number between 'from' and 'to' */
	else if (0 == strcmp(request->key, "dummy.random"))
	{
		ret = dummy_random(request, result);
	}

	return ZBX_MODULE_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_module_init                                                  *
 *                                                                            *
 * Purpose: the function is called on agent startup                           *
 *          It should be used to call any initialization routines             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: ZBX_MODULE_OK - success                                      *
 *               ZBX_MODULE_FAIL - module initialization failed               *
 *                                                                            *
 * Comment: the module won't be loaded in case of ZBX_MODULE_FAIL             *
 *                                                                            *
 ******************************************************************************/
int zbx_module_init()
{
	/* Initialization for dummy.random */
	srand(time(NULL));

	return ZBX_MODULE_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_module_uninit                                                *
 *                                                                            *
 * Purpose: the function is called on agent shutdown                          *
 *          It should be used to cleanup used resources if there are any      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: ZBX_MODULE_OK - success                                      *
 *               ZBX_MODULE_FAIL - function failed                            *
 *                                                                            *
 ******************************************************************************/
int zbx_module_uninit()
{
	return ZBX_MODULE_OK;
}
