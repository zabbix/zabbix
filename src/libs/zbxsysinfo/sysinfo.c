/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "log.h"
#include "cfg.h"
#include "alias.h"

#include "common/common.h"

#if defined(WITH_COMMON_METRICS)
#	include "common/common.h"
#endif /* USE_COMMON_METRICS */

#if defined(WITH_SIMPLE_METRICS)
#	include "simple/simple.h"
#endif /* USE_SIMPLE_METRICS */

#if defined(WITH_SPECIFIC_METRICS)
#	include "specsysinfo.h"
#endif /* USE_SPECIFIC_METRICS */

ZBX_METRIC *commands=NULL;

void	add_metric(ZBX_METRIC *new)
{
	register int i;

	assert(new);

	if(new->key == NULL)
		return;

	for(i=0;;i++)
	{
		if(commands[i].key == NULL)
		{

			commands[i].key = strdup(new->key);
			commands[i].flags = new->flags;

			commands[i].function=new->function;

			if(new->main_param == NULL)
				commands[i].main_param=NULL;
			else
				commands[i].main_param=strdup(new->main_param);

			if(new->test_param == NULL)
				commands[i].test_param=NULL;
			else
				commands[i].test_param=strdup(new->test_param);
			
			commands = zbx_realloc(commands,(i+2)*sizeof(ZBX_METRIC));
			memset(&commands[i+1], 0, sizeof(ZBX_METRIC));
			break;
		}
	}
}

void	add_user_parameter(char *key,char *command)
{
	register int i;
	char	usr_cmd[MAX_STRING_LEN];
	char	usr_param[MAX_STRING_LEN];
	unsigned	flag = 0;
	
	i = parse_command(key, usr_cmd, MAX_STRING_LEN, usr_param, MAX_STRING_LEN);
	if(i == 0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Can't add user specifed key \"%s\". Can't parse key!", key);
		return;
	} 
	else if(i == 2) /* with specifed parameters */
	{
		if(strcmp(usr_param,"*")){ /* must be '*' parameters */
			zabbix_log(LOG_LEVEL_WARNING, "Can't add user specifed key \"%s\". Incorrect key!", key);
			return;
		}
		flag |= CF_USEUPARAM;
	}

	for(i=0;;i++)
	{
		/* Add new parameters */
		if( commands[i].key == 0)
		{
			commands[i].key = strdup(usr_cmd);
			commands[i].flags = flag;
			commands[i].function = &EXECUTE_STR;
			commands[i].main_param = strdup(command);
			commands[i].test_param = 0;

			commands = zbx_realloc(commands,(i+2)*sizeof(ZBX_METRIC));
			commands[i+1].key=NULL;

			break;
		}
		
		/* Replace existing parameters */
		if(strcmp(commands[i].key, key) == 0)
		{
			if(commands[i].key)
				free(commands[i].key);
			if(commands[i].main_param)	
				free(commands[i].main_param);
			if(commands[i].test_param)	
				free(commands[i].test_param);

			commands[i].key = strdup(key);
			commands[i].flags = flag;
			commands[i].function = &EXECUTE_STR;
			commands[i].main_param = strdup(command);
			commands[i].test_param = 0;

			break;
		}
	}
}

void	init_metrics(void)
{
	register int 	i;

	commands = malloc(sizeof(ZBX_METRIC));
	commands[0].key=NULL;

#if defined(WITH_COMMON_METRICS)
	for(i=0;parameters_common[i].key!=0;i++)
	{
		add_metric(&parameters_common[i]);
	}
#endif /* USE_COMMON_METRICS */

#if defined(WITH_SPECIFIC_METRICS)
	for(i=0;parameters_specific[i].key!=0;i++)
	{
		add_metric(&parameters_specific[i]);
	}
#endif /* USE_SPECIFIC_METRICS */

#if defined(WITH_SIMPLE_METRICS)
	for(i=0;parameters_simple[i].key!=0;i++)
	{
		add_metric(&parameters_simple[i]);
	}
#endif /* USE_SIMPLE_METRICS */
}

void	free_metrics(void)
{
	int i = 0;

	if( commands )
	{
		for(i=0; NULL == commands[i].key; i++)
		{
			zbx_free(commands[i].key);
			zbx_free(commands[i].main_param);
			zbx_free(commands[i].test_param);
		}

		zbx_free(commands);
	}
}

void    escape_string(char *from, char *to, int maxlen)
{
	register int     i,ptr;
	char    *f;

	ptr=0;
	f=(char *)strdup(from);
	for(i=0;f[i]!=0;i++)
	{
		if( (f[i]=='\'') || (f[i]=='\\'))
		{
			if(ptr>maxlen-1)        break;
			to[ptr]='\\';
			if(ptr+1>maxlen-1)      break;
			to[ptr+1]=f[i];
			ptr+=2;
		}
		else
		{
			if(ptr>maxlen-1)        break;
			to[ptr]=f[i];
			ptr++;
		}
	}
	free(f);

	to[ptr]=0;
	to[maxlen-1]=0;
}

int 	copy_result(AGENT_RESULT *src, AGENT_RESULT *dist)
{
	assert(src);
	assert(dist);
	
	free_result(dist);
	dist->type = src->type;
	dist->dbl = src->dbl;
	if(src->str)
	{
		dist->str = strdup(src->str);
		if(!dist->str)
			return 1;
	}
	if(src->msg)
	{
		dist->msg = strdup(src->msg);
		if(!dist->msg)
			return 1;
	}
	return 0;
}

void	free_result(AGENT_RESULT *result)
{
	UNSET_DBL_RESULT(result);
	UNSET_UI64_RESULT(result);
	UNSET_STR_RESULT(result);
	UNSET_TEXT_RESULT(result);
	UNSET_MSG_RESULT(result);
}

void	init_result(AGENT_RESULT *result)
{
 /* don't use `free_result(result)`, dangerous recycling */

	result->type = 0;
	
	result->ui64 = 0;
	result->dbl = 0;	
	result->str = NULL;
	result->text = NULL;
	result->msg = NULL;
}

int parse_command( /* return value: 0 - error; 1 - command without parameters; 2 - command with parameters */
		const char *command,
		char *cmd,
		int cmd_max_len,
		char *param,
		int param_max_len
		)
{
	char *pl, *pr;
	char localstr[MAX_STRING_LEN];
	int ret = 2;

	zbx_strlcpy(localstr, command, MAX_STRING_LEN);
	
	if(cmd)
		zbx_strlcpy(cmd, "", cmd_max_len);
	if(param)
		zbx_strlcpy(param, "", param_max_len);
	
	pl = strchr(localstr, '[');
	pr = strrchr(localstr, ']');

	if(pl > pr)
		return 0;

	if((pl && !pr) || (!pl && pr))
		return 0;
	
	if(pl != NULL)
		pl[0] = 0;
	if(pr != NULL)
		pr[0] = 0;

	if(cmd)
		zbx_strlcpy(cmd, localstr, cmd_max_len);

	if(pl && pr && param)
		zbx_strlcpy(param, &pl[1] , param_max_len);

	if(!pl && !pr)
		ret = 1;
	
	return ret;
}

void	test_parameter(char* key)
{
	AGENT_RESULT	result;

	memset(&result, 0, sizeof(AGENT_RESULT));
	process(key, PROCESS_TEST, &result);
	if(result.type & AR_DOUBLE)
	{
		printf(" [d|" ZBX_FS_DBL "]", result.dbl);
	}
	if(result.type & AR_UINT64)
	{
		printf(" [u|" ZBX_FS_UI64 "]", result.ui64);
	}
	if(result.type & AR_STRING)
	{
		printf(" [s|%s]", result.str);
	}
	if(result.type & AR_TEXT)
	{
		printf(" [t|%s]", result.text);
	}
	if(result.type & AR_MESSAGE)
	{
		printf(" [m|%s]", result.msg);
	}

	free_result(&result);
	printf("\n");

	fflush(stdout);
}

void	test_parameters(void)
{
	register int	i;
	AGENT_RESULT	result;

	memset(&result, 0, sizeof(AGENT_RESULT));
	
	for(i=0; 0 != commands[i].key; i++)
	{
		process(commands[i].key, PROCESS_TEST | PROCESS_USE_TEST_PARAM, &result);
		if(result.type & AR_DOUBLE)
		{
			printf(" [d|" ZBX_FS_DBL "]", result.dbl);
		}
		if(result.type & AR_UINT64)
		{
			printf(" [u|" ZBX_FS_UI64 "]", result.ui64);
		}
		if(result.type & AR_STRING)
		{
			printf(" [s|%s]", result.str);
		}
		if(result.type & AR_TEXT)
		{
			printf(" [t|%s]", result.text);
		}
		if(result.type & AR_MESSAGE)
		{
			printf(" [m|%s]", result.msg);
		}
		free_result(&result);
		printf("\n");

		fflush(stdout);
	}
}

int	replace_param(const char *cmd, const char *param, char *out, int outlen)
{
	int ret = SUCCEED;
	char buf[MAX_STRING_LEN];
	char command[MAX_STRING_LEN];
	register char *pl, *pr;
	
	assert(out);

	out[0] = '\0';

	if(!cmd && !param)
		return ret;
	
	zbx_strlcpy(command, cmd, MAX_STRING_LEN);
			
	pl = command;
	while((pr = strchr(pl, '$')) && outlen > 0)
	{
		pr[0] = '\0';
		zbx_strlcat(out, pl, outlen);
		outlen -= MIN((int)strlen(pl), (int)outlen);
		pr[0] = '$';
		
		if (pr[1] >= '0' && pr[1] <= '9')
		{
			buf[0] = '\0';

			if(pr[1] == '0')
			{
				zbx_strlcpy(buf, command, MAX_STRING_LEN);
			}
			else
			{
				get_param(param, (int)(pr[1] - '0'), buf, MAX_STRING_LEN);
			}
			
			zbx_strlcat(out, buf, outlen);
			outlen -= MIN((int)strlen(buf), (int)outlen);
					
			pl = pr + 2;
			continue;
		} else if(pr[1] == '$')
		{
			pr++; /* remove second '$' symbol */
		}
		
		pl = pr + 1;
		zbx_strlcat(out, "$", outlen);
		outlen -= 1;
	}
	zbx_strlcat(out, pl, outlen);
	outlen -= MIN((int)strlen(pl), (int)outlen);
	
	return ret;
}

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result)
{
	register char	*p;
	register int	i = 0;

	int	(*function)() = NULL;
	int	ret = SUCCEED;
	int	err = SYSINFO_RET_OK;
	
	char	usr_cmd[MAX_STRING_LEN];
	char	usr_param[MAX_STRING_LEN];
	
	char	usr_command[MAX_STRING_LEN];
	int 	usr_command_len;

	char	param[MAX_STRING_LEN];
		
        assert(result);
        init_result(result);
	
	alias_expand(in_command, usr_command, MAX_STRING_LEN);
	
	usr_command_len = (int)strlen(usr_command);

	for( p=usr_command+usr_command_len-1; p>usr_command && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );

	if( (p[1]=='\r') || (p[1]=='\n') || (p[1]==' '))
	{
		p[1]=0;
	}
	
	function=0;
	
	if(parse_command(usr_command, usr_cmd, MAX_STRING_LEN, usr_param, MAX_STRING_LEN) != 0)
	{

		for(i=0; commands[i].key != 0; i++)
		{
			if( strcmp(commands[i].key, usr_cmd) == 0)
			{
				function=commands[i].function;
				break;
			}
		}
	}

	param[0] = '\0';	
	if(function != 0)
	{
		
		if(commands[i].flags & CF_USEUPARAM)
		{
			if((flags & PROCESS_TEST) && (flags & PROCESS_USE_TEST_PARAM) && commands[i].test_param)
			{
				zbx_strlcpy(usr_param, commands[i].test_param, MAX_STRING_LEN);
			}
		} 
		else
		{
			usr_param[0] = '\0';
		}
		
		if(commands[i].main_param)
		{
			if(commands[i].flags & CF_USEUPARAM)
			{
				err = replace_param(
					commands[i].main_param,
					usr_param,
					param,
					MAX_STRING_LEN);
			}
			else
			{
				zbx_snprintf(param, sizeof(param), "%s", commands[i].main_param);
			}
		}
		else
		{
			zbx_snprintf(param, sizeof(param), "%s", usr_param);
		}

		if(err != FAIL)
		{
			err = function(usr_command, param, flags, result);

			if(err == SYSINFO_RET_FAIL)
				err = NOTSUPPORTED;
			else if(err == SYSINFO_RET_TIMEOUT)
				err = TIMEOUT_ERROR;
		}
	}
	else
	{
		err = NOTSUPPORTED;
	}
	
	if(flags & PROCESS_TEST)
	{
		printf("%s", usr_cmd);
		if(commands[i].flags & CF_USEUPARAM)
		{
			printf("[%s]", param);
			i = (int)strlen(param)+2;
		} else	i = 0;
		i += (int)strlen(usr_cmd);
		
#define COLUMN_2_X 45 /* max of spaces count */
		i = i > COLUMN_2_X ? 1 : (COLUMN_2_X - i);
	
		printf("%-*.*s", i, i, " "); /* print spaces */
	}

	if(err == NOTSUPPORTED)
	{
		if(!(result->type & AR_MESSAGE))
		{
			SET_MSG_RESULT(result, strdup("ZBX_NOTSUPPORTED"));
		}
		ret = NOTSUPPORTED;
	}
	else if(err == TIMEOUT_ERROR)
	{
		if(!(result->type & AR_MESSAGE))
		{
			SET_MSG_RESULT(result, strdup("ZBX_ERROR"));
		}
		ret = TIMEOUT_ERROR;
	}

	return ret;
}

int	set_result_type(AGENT_RESULT *result, int value_type, char *c)
{
	int ret = FAIL;

	assert(result);

	if(value_type == ITEM_VALUE_TYPE_UINT64)
	{
		del_zeroes(c);
		if(is_uint(c) == SUCCEED)
		{
			SET_UI64_RESULT(result, zbx_atoui64(c));
			ret = SUCCEED;
		}
	}
	else if(value_type == ITEM_VALUE_TYPE_FLOAT)
	{
		if(is_double(c) == SUCCEED)
		{
			SET_DBL_RESULT(result, atof(c));
			ret = SUCCEED;
		}
		else if(is_uint(c) == SUCCEED)
		{
			SET_DBL_RESULT(result, strtod(c, NULL));
			ret = SUCCEED;
		}
	}
	else if(value_type == ITEM_VALUE_TYPE_STR)
	{
		SET_STR_RESULT(result, strdup(c));
		ret = SUCCEED;
	}
	else if(value_type == ITEM_VALUE_TYPE_TEXT)
	{
		SET_TEXT_RESULT(result, strdup(c));
		ret = SUCCEED;
	}
	else if(value_type == ITEM_VALUE_TYPE_LOG)
	{
		SET_STR_RESULT(result, strdup(c));
		ret = SUCCEED;
	}

	return ret;
}

static zbx_uint64_t* get_result_ui64_value(AGENT_RESULT *result)
{
	zbx_uint64_t tmp;

	assert(result);

	if(ISSET_UI64(result))
	{
		/* nothing to do */
	}
	else if(ISSET_DBL(result))
	{
		SET_UI64_RESULT(result, result->dbl);
	}
	else if(ISSET_STR(result))
	{
		if(EOF != sscanf(result->str, ZBX_FS_UI64, &tmp))
			SET_UI64_RESULT(result, tmp);
	}
	else if(ISSET_TEXT(result))
	{
		if(EOF != sscanf(result->text, ZBX_FS_UI64, &tmp))
			SET_UI64_RESULT(result, tmp);
	}
	/* skip AR_MESSAGE - it is information field */

	if(ISSET_UI64(result))
	{
		return &result->ui64;
	}

	return NULL;
}

static double* get_result_dbl_value(AGENT_RESULT *result)
{
	double tmp;

	assert(result);

	if(ISSET_DBL(result))
	{
		/* nothing to do */
	}
	else if(ISSET_UI64(result))
	{
		SET_DBL_RESULT(result, result->ui64);
	}
	else if(ISSET_STR(result))
	{
		if(EOF != sscanf(result->str, ZBX_FS_DBL, &tmp))
			SET_DBL_RESULT(result, tmp);
	}
	else if(ISSET_TEXT(result))
	{
		if(EOF != sscanf(result->text, ZBX_FS_DBL, &tmp))
			SET_DBL_RESULT(result, tmp);
	}
	/* skip AR_MESSAGE - it is information field */

	if(ISSET_DBL(result))
	{
		return &result->dbl;
	}

	return NULL;
}

static char** get_result_str_value(AGENT_RESULT *result)
{
	register char *p, tmp;

	assert(result);

	if(ISSET_STR(result))
	{
		/* nothing to do */
	}
	else if(ISSET_TEXT(result))
	{
		/* NOTE: copy only line */
		for(p = result->text; *p != '\0' && *p != '\r' && *p != '\n'; p++);
		tmp = *p; /* remember result->text character */
		*p = '\0'; /* replace to EOL */
		SET_STR_RESULT(result, strdup(result->text)); /* copy line */
		*p = tmp; /* restore result->text character */

	}
	else if(ISSET_UI64(result))
	{
		 SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64))
	}
	else if(ISSET_DBL(result))
	{
		 SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl))
	}
	/* skip AR_MESSAGE - it is information field */

	if(ISSET_STR(result))
	{
		return &result->str;
	}

	return NULL;
}

static char** get_result_text_value(AGENT_RESULT *result)
{
	assert(result);

	if(ISSET_TEXT(result))
	{
		/* nothing to do */
	}
	else if(ISSET_STR(result))
	{
		SET_TEXT_RESULT(result, strdup(result->str));
	}
	else if(ISSET_UI64(result))
	{
		 SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, result->ui64))
	}
	else if(ISSET_DBL(result))
	{
		 SET_TEXT_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_DBL, result->dbl))
	}
	/* skip AR_MESSAGE - it is information field */

	if(ISSET_TEXT(result))
	{
		return &result->text;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: get_result_value_by_type                                         *
 *                                                                            *
 * Purpose: return vslue of result in special type                            *
 *          if falue missed convert existed value to requested type           *
 *                                                                            *
 * Return value:                                                              *
 *         NULL - if value are missed or can't be conferted                   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  beter use definitions                                           *
 *                GET_UI64_RESULT                                             *
 *                GET_DBL_RESULT                                              *
 *                GET_STR_RESULT                                              *
 *                GET_TEXT_RESULT                                             *
 *                GET_MSG_RESULT                                              *
 *                                                                            *
 *    AR_MESSAGE - skiped in convertion                                       *
 *                                                                            *
 ******************************************************************************/
void	*get_result_value_by_type(AGENT_RESULT *result, int require_type)
{
	assert(result);

	switch(require_type)
	{
		case AR_UINT64: 
			return (void*)get_result_ui64_value(result);
			break;
		case AR_DOUBLE:
			return (void*)get_result_dbl_value(result);
			break;
		case AR_STRING:
			return (void*)get_result_str_value(result);
			break;
		case AR_TEXT:
			return (void*)get_result_text_value(result);
			break;
		case AR_MESSAGE:
			if(ISSET_MSG(result))	return (void*)(&result->msg);
			break;
		default:
			break;
	}
	return NULL;
}
