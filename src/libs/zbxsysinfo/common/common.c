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

#include "config.h"

#include <sys/wait.h>

#include "common.h"
#include "sysinfo.h"

#include "md5.h"
#include "log.h"

ZBX_METRIC *commands=NULL;
extern ZBX_METRIC parameters_specific[];

ZBX_METRIC	parameters_common[]=
/*      KEY                     FLAG    FUNCTION        ADD_PARAM       TEST_PARAM */
	{
	{"system.localtime",	0,		SYSTEM_LOCALTIME,	0,	0},
	{"vfs.file.exists",	CF_USEUPARAM,	VFS_FILE_EXISTS,	0,	"/etc/passwd"},
	{"vfs.file.time",       CF_USEUPARAM,   VFS_FILE_TIME,          0,      "/etc/passwd,modify"},
	{"vfs.file.size",	CF_USEUPARAM,	VFS_FILE_SIZE, 		0,	"/etc/passwd"},
	{"vfs.file.regexp",	CF_USEUPARAM,	VFS_FILE_REGEXP,	0,	"/etc/passwd,root"},
	{"vfs.file.regmatch",	CF_USEUPARAM,	VFS_FILE_REGMATCH, 	0,	"/etc/passwd,root"},
	{"system.run",		CF_USEUPARAM,	RUN_COMMAND,	 	0,	"echo test"},
	{"web.page.get",	CF_USEUPARAM,	WEB_PAGE_GET,	 	0,	"www.zabbix.com,,80"},
	{"web.page.perf",	CF_USEUPARAM,	WEB_PAGE_PERF,	 	0,	"www.zabbix.com,,80"},
	{"web.page.regexp",	CF_USEUPARAM,	WEB_PAGE_REGEXP,	0,	"www.zabbix.com,,80"},
	{0}
	};

void	add_metric(ZBX_METRIC *new)
{

	int i;

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
			
			commands=realloc(commands,(i+2)*sizeof(ZBX_METRIC));
			commands[i+1].key=NULL;
			break;
		}
	}
}

void	add_user_parameter(char *key,char *command)
{
	int i;
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

			commands=realloc(commands,(i+2)*sizeof(ZBX_METRIC));
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

void	init_metrics()
{
	int 	i;

	commands=malloc(sizeof(ZBX_METRIC));
	commands[0].key=NULL;

	for(i=0;parameters_common[i].key!=0;i++)
	{
		add_metric(&parameters_common[i]);
	}

	for(i=0;parameters_specific[i].key!=0;i++)
	{
		add_metric(&parameters_specific[i]);
	}
}

void    escape_string(char *from, char *to, int maxlen)
{
	int     i,ptr;
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

static void	init_result_list(ZBX_LIST *list)
{
 /* don't use `free_result_list(list)`, dangerous recycling */

	/* nothin to do */
}
static void	free_result_list(ZBX_LIST *list)
{
	/* nothin to do */
}

static int	copy_result_list(ZBX_LIST *src, ZBX_LIST *dist)
{
	/* nothin to do */
	return 0;
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
	return copy_result_list(&(src->list), &(dist->list));
}

void	free_result(AGENT_RESULT *result)
{

	if(result->type & AR_STRING)
	{
		free(result->str);
	}

	if(result->type & AR_TEXT)
	{
		free(result->text);
	}
	
	if(result->type & AR_MESSAGE)
	{
		free(result->msg);
	}

	if(result->type & AR_LIST)
	{
		free_result_list(&(result->list));
	}

	init_result(result);
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

	init_result_list(&(result->list));
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

	strncpy(localstr, command, MAX_STRING_LEN);
	
	if(cmd)
		strncpy(cmd, "", cmd_max_len);
	if(param)
		strncpy(param, "", param_max_len);
	
	pl = strstr(localstr, "[");
	pr = strstr(localstr, "]");

	if(pl > pr)
		return 0;

	if((pl && !pr) || (!pl && pr))
		return 0;
	
	if(pl != NULL)
		pl[0] = 0;
	if(pr != NULL)
		pr[0] = 0;

	if(cmd)
		strncpy(cmd, localstr, cmd_max_len);

	if(pl && pr && param)
		strncpy(param, &pl[1] , param_max_len);

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
	int	i;
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
	char *pl, *pr;
	
	assert(out);

	out[0] = '\0';

	if(!cmd && !param)
		return ret;
	
	strncpy(command, cmd, MAX_STRING_LEN);
			
	pl = command;
	while((pr = strchr(pl, '$')) && outlen > 0)
	{
		pr[0] = '\0';
		strncat(out, pl, outlen);
		outlen -= MIN(strlen(pl), outlen);
		pr[0] = '$';
		
		if (pr[1] >= '0' && pr[1] <= '9')
		{
			buf[0] = '\0';

			if(pr[1] == '0')
			{
				strncpy(buf, cmd, MAX_STRING_LEN);
			}
			else
			{
				get_param(param, (int)(pr[1] - '0'), buf, MAX_STRING_LEN);
			}
			
			strncat(out, buf, outlen);
			outlen -= MIN(strlen(buf), outlen);
					
			pl = pr + 2;
			continue;
		}
		pl = pr + 1;
		strncat(out, "$", outlen);
		outlen -= 1;
	}
	strncat(out, pl, outlen);
	outlen -= MIN(strlen(pl), outlen);
	
	return ret;
}

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result)
{
	char	*p;
	int	i;
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
	
	strncpy(usr_command, in_command, MAX_STRING_LEN);
	usr_command_len = strlen(usr_command);
	
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

	if(function != 0)
	{
		param[0] = '\0';	
		
		if(commands[i].flags & CF_USEUPARAM)
		{
			if((flags & PROCESS_TEST) && (flags & PROCESS_USE_TEST_PARAM) && commands[i].test_param)
			{
				strncpy(usr_param, commands[i].test_param, MAX_STRING_LEN);
			}
		} 
		else
		{
			usr_param[0] = '\0';
		}
		
		if(commands[i].main_param)
		{
			err = replace_param(
				commands[i].main_param,
				usr_param,
				param,
				MAX_STRING_LEN);
		}
		else
		{
			snprintf(param, MAX_STRING_LEN, "%s", usr_param);
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
			i = strlen(param)+2;
		} else	i = 0;
		i += strlen(usr_cmd);
		
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

/* MD5 sum calculation */

int	VFS_FILE_MD5SUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	fd;
	int	i,nr;
	struct stat	buf_stat;

        md5_state_t state;
	u_char	buf[16 * 1024];

	unsigned char	hashText[MD5_DIGEST_SIZE*2+1];
	unsigned char	hash[MD5_DIGEST_SIZE];

	char filename[MAX_STRING_LEN];
	
	assert(result);
	
        init_result(result);	

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, filename, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(stat(filename,&buf_stat) != 0)
	{
		/* Cannot stat() file */
		return	SYSINFO_RET_FAIL;
	}

	if(buf_stat.st_size > 64*1024*1024)
	{
		/* Will not calculate MD5 for files larger than 64M */
		return	SYSINFO_RET_FAIL;
	}

	fd=open(filename,O_RDONLY);
	if(fd == -1)
	{
		return	SYSINFO_RET_FAIL;
	}

        md5_init(&state);
	while ((nr = read(fd, buf, sizeof(buf))) > 0)
	{
        	md5_append(&state,(const md5_byte_t *)buf,nr);
	}
        md5_finish(&state,(md5_byte_t *)hash);

	close(fd);

/* Convert MD5 hash to text form */
	for(i=0;i<MD5_DIGEST_SIZE;i++)
		sprintf((char *)&hashText[i<<1],"%02x",hash[i]);

	SET_STR_RESULT(result, strdup((char*)hashText));

	return SYSINFO_RET_OK;
}

/* Code for cksum is based on code from cksum.c */

static u_long crctab[] = {
	0x0,
	0x04c11db7, 0x09823b6e, 0x0d4326d9, 0x130476dc, 0x17c56b6b,
	0x1a864db2, 0x1e475005, 0x2608edb8, 0x22c9f00f, 0x2f8ad6d6,
	0x2b4bcb61, 0x350c9b64, 0x31cd86d3, 0x3c8ea00a, 0x384fbdbd,
	0x4c11db70, 0x48d0c6c7, 0x4593e01e, 0x4152fda9, 0x5f15adac,
	0x5bd4b01b, 0x569796c2, 0x52568b75, 0x6a1936c8, 0x6ed82b7f,
	0x639b0da6, 0x675a1011, 0x791d4014, 0x7ddc5da3, 0x709f7b7a,
	0x745e66cd, 0x9823b6e0, 0x9ce2ab57, 0x91a18d8e, 0x95609039,
	0x8b27c03c, 0x8fe6dd8b, 0x82a5fb52, 0x8664e6e5, 0xbe2b5b58,
	0xbaea46ef, 0xb7a96036, 0xb3687d81, 0xad2f2d84, 0xa9ee3033,
	0xa4ad16ea, 0xa06c0b5d, 0xd4326d90, 0xd0f37027, 0xddb056fe,
	0xd9714b49, 0xc7361b4c, 0xc3f706fb, 0xceb42022, 0xca753d95,
	0xf23a8028, 0xf6fb9d9f, 0xfbb8bb46, 0xff79a6f1, 0xe13ef6f4,
	0xe5ffeb43, 0xe8bccd9a, 0xec7dd02d, 0x34867077, 0x30476dc0,
	0x3d044b19, 0x39c556ae, 0x278206ab, 0x23431b1c, 0x2e003dc5,
	0x2ac12072, 0x128e9dcf, 0x164f8078, 0x1b0ca6a1, 0x1fcdbb16,
	0x018aeb13, 0x054bf6a4, 0x0808d07d, 0x0cc9cdca, 0x7897ab07,
	0x7c56b6b0, 0x71159069, 0x75d48dde, 0x6b93dddb, 0x6f52c06c,
	0x6211e6b5, 0x66d0fb02, 0x5e9f46bf, 0x5a5e5b08, 0x571d7dd1,
	0x53dc6066, 0x4d9b3063, 0x495a2dd4, 0x44190b0d, 0x40d816ba,
	0xaca5c697, 0xa864db20, 0xa527fdf9, 0xa1e6e04e, 0xbfa1b04b,
	0xbb60adfc, 0xb6238b25, 0xb2e29692, 0x8aad2b2f, 0x8e6c3698,
	0x832f1041, 0x87ee0df6, 0x99a95df3, 0x9d684044, 0x902b669d,
	0x94ea7b2a, 0xe0b41de7, 0xe4750050, 0xe9362689, 0xedf73b3e,
	0xf3b06b3b, 0xf771768c, 0xfa325055, 0xfef34de2, 0xc6bcf05f,
	0xc27dede8, 0xcf3ecb31, 0xcbffd686, 0xd5b88683, 0xd1799b34,
	0xdc3abded, 0xd8fba05a, 0x690ce0ee, 0x6dcdfd59, 0x608edb80,
	0x644fc637, 0x7a089632, 0x7ec98b85, 0x738aad5c, 0x774bb0eb,
	0x4f040d56, 0x4bc510e1, 0x46863638, 0x42472b8f, 0x5c007b8a,
	0x58c1663d, 0x558240e4, 0x51435d53, 0x251d3b9e, 0x21dc2629,
	0x2c9f00f0, 0x285e1d47, 0x36194d42, 0x32d850f5, 0x3f9b762c,
	0x3b5a6b9b, 0x0315d626, 0x07d4cb91, 0x0a97ed48, 0x0e56f0ff,
	0x1011a0fa, 0x14d0bd4d, 0x19939b94, 0x1d528623, 0xf12f560e,
	0xf5ee4bb9, 0xf8ad6d60, 0xfc6c70d7, 0xe22b20d2, 0xe6ea3d65,
	0xeba91bbc, 0xef68060b, 0xd727bbb6, 0xd3e6a601, 0xdea580d8,
	0xda649d6f, 0xc423cd6a, 0xc0e2d0dd, 0xcda1f604, 0xc960ebb3,
	0xbd3e8d7e, 0xb9ff90c9, 0xb4bcb610, 0xb07daba7, 0xae3afba2,
	0xaafbe615, 0xa7b8c0cc, 0xa379dd7b, 0x9b3660c6, 0x9ff77d71,
	0x92b45ba8, 0x9675461f, 0x8832161a, 0x8cf30bad, 0x81b02d74,
	0x857130c3, 0x5d8a9099, 0x594b8d2e, 0x5408abf7, 0x50c9b640,
	0x4e8ee645, 0x4a4ffbf2, 0x470cdd2b, 0x43cdc09c, 0x7b827d21,
	0x7f436096, 0x7200464f, 0x76c15bf8, 0x68860bfd, 0x6c47164a,
	0x61043093, 0x65c52d24, 0x119b4be9, 0x155a565e, 0x18197087,
	0x1cd86d30, 0x029f3d35, 0x065e2082, 0x0b1d065b, 0x0fdc1bec,
	0x3793a651, 0x3352bbe6, 0x3e119d3f, 0x3ad08088, 0x2497d08d,
	0x2056cd3a, 0x2d15ebe3, 0x29d4f654, 0xc5a92679, 0xc1683bce,
	0xcc2b1d17, 0xc8ea00a0, 0xd6ad50a5, 0xd26c4d12, 0xdf2f6bcb,
	0xdbee767c, 0xe3a1cbc1, 0xe760d676, 0xea23f0af, 0xeee2ed18,
	0xf0a5bd1d, 0xf464a0aa, 0xf9278673, 0xfde69bc4, 0x89b8fd09,
	0x8d79e0be, 0x803ac667, 0x84fbdbd0, 0x9abc8bd5, 0x9e7d9662,
	0x933eb0bb, 0x97ffad0c, 0xafb010b1, 0xab710d06, 0xa6322bdf,
	0xa2f33668, 0xbcb4666d, 0xb8757bda, 0xb5365d03, 0xb1f740b4
};

/*
 * Compute a POSIX 1003.2 checksum.  These routines have been broken out so
 * that other programs can use them.  The first routine, crc(), takes a file
 * descriptor to read from and locations to store the crc and the number of
 * bytes read.  The second routine, crc_buf(), takes a buffer and a length,
 * and a location to store the crc.  Both routines return 0 on success and 1
 * on failure.  Errno is set on failure.
 */

int	VFS_FILE_CKSUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	register u_char *p;
	register int nr;
/*	AV Crashed under 64 platforms. Must be 32 bit! */
/*	register u_long crc, len;*/
	register uint32_t crc, len;
	u_char buf[16 * 1024];
	u_long cval, clen;
	int	fd;
	char	filename[MAX_STRING_LEN];

	assert(result);

        init_result(result);	

	if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, filename, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	
		
	fd=open(filename,O_RDONLY);
	if(fd == -1)
	{
		return	SYSINFO_RET_FAIL;
	}

#define	COMPUTE(var, ch)	(var) = (var) << 8 ^ crctab[(var) >> 24 ^ (ch)]

	crc = len = 0;
	while ((nr = read(fd, buf, sizeof(buf))) > 0)
	{
		for( len += nr, p = buf; nr--; ++p)
		{
			COMPUTE(crc, *p);
		}
	}
	close(fd);
	
	if (nr < 0)
	{
		return	SYSINFO_RET_FAIL;
	}

	clen = len;

	/* Include the length of the file. */
	for (; len != 0; len >>= 8) {
		COMPUTE(crc, len & 0xff);
	}

	cval = ~crc;

	SET_UI64_RESULT(result, cval);

	return	SYSINFO_RET_OK;
}

int
crc_buf2(p, clen, cval)
	register u_char *p;
	u_long clen;
	u_long *cval;
{
	register u_long crc, len;

	crc = 0;
	for (len = clen; len--; ++p)
		COMPUTE(crc, *p);

	/* Include the length of the file. */
	for (len = clen; len != 0; len >>= 8)
		COMPUTE(crc, len & 0xff);

	*cval = ~crc;
	return (0);
}

int	get_stat(const char *key, unsigned flags, AGENT_RESULT *result)
{
	FILE	*f;
	char	line[MAX_STRING_LEN];
	char	name1[MAX_STRING_LEN];
	char	name2[MAX_STRING_LEN];

        assert(result);

        init_result(result);	

	f=fopen("/tmp/zabbix_agentd.tmp","r");
	if(f==NULL)
	{
		return SYSINFO_RET_FAIL;
	}
	while(fgets(line,MAX_STRING_LEN,f))
	{
		if(sscanf(line,"%s %s\n",name1,name2)==2)
		{
			if(strcmp(name1,key) == 0)
			{
				fclose(f);
				SET_UI64_RESULT(result, atoi(name2));
				return SYSINFO_RET_OK;
			}
		}

	}
	fclose(f);
	return SYSINFO_RET_FAIL;
}

int	NET_IF_IBYTES1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"netloadin1[%s]",param);

	return	get_stat(key, flags, result);
}

int	NET_IF_IBYTES5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"netloadin5[%s]",param);

	return	get_stat(key, flags, result);
}

int	NET_IF_IBYTES15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"netloadin15[%s]",param);

	return	get_stat(key, flags, result);
}

int	NET_IF_OBYTES1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"netloadout1[%s]",param);

	return	get_stat(key, flags, result);
}

int	NET_IF_OBYTES5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"netloadout5[%s]",param);

	return	get_stat(key, flags, result);
}

int	NET_IF_OBYTES15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	key[MAX_STRING_LEN];

	snprintf(key,sizeof(key)-1,"netloadout15[%s]",param);

	return	get_stat(key, flags, result);
}

int	TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_PROC
	FILE	*f;
	char	c[MAX_STRING_LEN];
	char	porthex[MAX_STRING_LEN];
	char	pattern[MAX_STRING_LEN];

        assert(result);

        init_result(result);	

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, porthex, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	
	
	strscpy(pattern,porthex);
	strncat(pattern," 00000000:0000 0A", MAX_STRING_LEN);

	f=fopen("/proc/net/tcp","r");
	if(NULL == f)
	{
		return	SYSINFO_RET_FAIL;
	}

	while (NULL!=fgets(c,MAX_STRING_LEN,f))
	{
		if(NULL != strstr(c,pattern))
		{
			fclose(f);
			SET_UI64_RESULT(result, 1);
			return SYSINFO_RET_OK;
		}
	}
	fclose(f);

	SET_UI64_RESULT(result, 0);
	
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
}

#ifdef	HAVE_PROC
int	getPROC(char *file, int lineno, int fieldno, unsigned flags, AGENT_RESULT *result)
{
	FILE	*f;
	char	*t;
	char	c[MAX_STRING_LEN];
	int	i;
	double	value = 0;
        
	assert(result);

        init_result(result);	
		
	f=fopen(file,"r");
	if(NULL == f)
	{
		return	SYSINFO_RET_FAIL;
	}
	for(i=1;i<=lineno;i++)
	{	
		fgets(c,MAX_STRING_LEN,f);
	}
	t=(char *)strtok(c," ");
	for(i=2;i<=fieldno;i++)
	{
		t=(char *)strtok(NULL," ");
	}
	fclose(f);

	sscanf(t, "%lf", &value);
	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}
#endif

int	AGENT_PING(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        assert(result);

        init_result(result);	
	
	SET_UI64_RESULT(result, 1);
	return SYSINFO_RET_OK;
}

int	PROCCOUNT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_PROCS
	struct sysinfo info;

        assert(result);

        init_result(result);	
	
	if( 0 == sysinfo(&info))
	{
		SET_UI64_RESULT(result, info.procs);
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
#ifdef	HAVE_PROC_0_PSINFO
	DIR	*dir;
	struct	dirent *entries;
	struct	stat buf;
	char	filename[MAX_STRING_LEN];

	int	fd;
/* In the correct procfs.h, the structure name is psinfo_t */
	psinfo_t psinfo;

	int	proccount=0;
        assert(result);

	init_result(result);
		
	dir=opendir("/proc");
	if(NULL == dir)
	{
		return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		strscpy(filename,"/proc/");	
		strncat(filename,entries->d_name,MAX_STRING_LEN);
		strncat(filename,"/psinfo",MAX_STRING_LEN);

		if(stat(filename,&buf)==0)
		{
			fd = open (filename, O_RDONLY);
			if (fd != -1)
			{
				if (read (fd, &psinfo, sizeof(psinfo)) == -1)
				{
					closedir(dir);
					return SYSINFO_RET_FAIL;
				}
				else
				{
					proccount++;
				}
				close (fd);
			}
			else
			{
				continue;
			}
		}
	}
	closedir(dir);
	
	SET_UI64_RESULT(result,	proccount);
	
	return SYSINFO_RET_OK;
#else
#ifdef	HAVE_PROC_1_STATUS
	DIR	*dir;
	struct	dirent *entries;
	struct	stat buf;
	char	filename[MAX_STRING_LEN];
	char	line[MAX_STRING_LEN];

	FILE	*f;

	int	proccount=0;

        assert(result);

        init_result(result);

	dir=opendir("/proc");
	if(NULL == dir)
	{
		return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		strscpy(filename,"/proc/");	
		strncat(filename,entries->d_name,MAX_STRING_LEN);
		strncat(filename,"/status",MAX_STRING_LEN);

		if(stat(filename,&buf)==0)
		{
			f=fopen(filename,"r");
			if(f==NULL)
			{
				continue;
			}
			/* This check can be skipped. No need to read anything from this file. */
			if(NULL != fgets(line,MAX_STRING_LEN,f))
			{
				proccount++;
			}
			fclose(f);
		}
	}
	closedir(dir);

	SET_UI64_RESULT(result,	proccount);
	
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
#endif
#endif
}

int	AGENT_VERSION(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	static	char	version[]=ZABBIX_VERSION;

	assert(result);

        init_result(result);
		
	SET_STR_RESULT(result, strdup(version));
	
	return	SYSINFO_RET_OK;
}

int     OLD_VERSION(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        char    key[MAX_STRING_LEN];
        int     ret;

        assert(result);

        init_result(result);

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, key, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

        if(strcmp(key,"zabbix_agent") == 0)
        {
                ret = AGENT_VERSION(cmd, param, flags, result);
        }
        else
        {
                ret = SYSINFO_RET_FAIL;
        }

        return ret;
}

int	EXECUTE_STR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	FILE	*f;
	char	c[MAX_STRING_LEN];
	char	command[MAX_STRING_LEN];
	int	i,len;

        assert(result);

        init_result(result);
	
	strncpy(command, param, MAX_STRING_LEN);
	
	f=popen(command,"r");
	if(f==0)
	{
		switch (errno)
		{
			case	EINTR:
/* (char *) to avoid compiler warning */
				return SYSINFO_RET_TIMEOUT;
			default:
				return SYSINFO_RET_FAIL;
		}
	}

	len = fread(c, 1, MAX_STRING_LEN-1, f);

	if(0 != ferror(f))
	{
		switch (errno)
		{
			case	EINTR:
				pclose(f);
/* (char *) to avoid compiler warning */
				return SYSINFO_RET_TIMEOUT;
			default:
				pclose(f);
				return SYSINFO_RET_FAIL;
		}
	}

	c[len]=0;

	zabbix_log(LOG_LEVEL_DEBUG, "Run remote command [%s] Result [%d] [%s]", command, strlen(c), c);

	if(pclose(f) != 0)
	{
		switch (errno)
		{
			case	EINTR:
/* (char *) to avoid compiler warning */
				return SYSINFO_RET_TIMEOUT;
			default:
				return SYSINFO_RET_FAIL;
		}
	}

	/* We got EOL only */
	if(c[0] == '\n')
	{
		return SYSINFO_RET_FAIL;
	}
	
	for(i=strlen(c); i>0; i--)
	{
		if(c[i] == '\n')
		{
			c[i] = '\0';
			break;
		}
	}
	
	SET_TEXT_RESULT(result, strdup(c));
	
	return	SYSINFO_RET_OK;
}

int	EXECUTE(const char *cmd, const char *command, unsigned flags, AGENT_RESULT *result)
{
	FILE	*f;
	char	c[MAX_STRING_LEN];
	double	value = 0;

        assert(result);

	init_result(result);
		
	f=popen( command,"r");
	if(f==0)
	{
		switch (errno)
		{
			case	EINTR:
				return SYSINFO_RET_TIMEOUT;
			default:
				return SYSINFO_RET_FAIL;
		}
	}

	if(NULL == fgets(c,MAX_STRING_LEN,f))
	{
		pclose(f);
		switch (errno)
		{
			case	EINTR:
				return SYSINFO_RET_TIMEOUT;
			default:
				return SYSINFO_RET_FAIL;
		}
	}

	if(pclose(f) != 0)
	{
		switch (errno)
		{
			case	EINTR:
				return SYSINFO_RET_TIMEOUT;
			default:
				return SYSINFO_RET_FAIL;
		}
	}

	/* We got EOL only */
	if(c[0] == '\n')
	{
		return SYSINFO_RET_FAIL;
	}

	sscanf(c, "%lf", &value);
	SET_DBL_RESULT(result, value);

	return	SYSINFO_RET_OK;
}

int	RUN_COMMAND(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	command[MAX_STRING_LEN];
#define MAX_FLAG_LEN 10
	char	flag[MAX_FLAG_LEN];
	pid_t	pid;
	
        assert(result);

	init_result(result);
	

zabbix_log(LOG_LEVEL_WARNING, "RUN_COMMAND cmd = '%s'",cmd);

	if(CONFIG_ENABLE_REMOTE_COMMANDS != 1)
	{
		SET_MSG_RESULT(result, strdup("ZBX_NOTSUPPORTED"));
		return  SYSINFO_RET_FAIL;
	}
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, command, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(command[0] == '\0')
	{
		return SYSINFO_RET_FAIL;
	}
	
	if(get_param(param, 2, flag, MAX_FLAG_LEN) != 0)
        {
                flag[0] = '\0';
        }

	if(flag[0] == '\0')
	{
		snprintf(flag,MAX_FLAG_LEN,"wait");
	}

	zabbix_log(LOG_LEVEL_DEBUG, "RUN_COMMAND flag = '%s'",flag);
	if(strcmp(flag,"wait") == 0)
	{
	zabbix_log(LOG_LEVEL_DEBUG, "RUN_COMMAND is running as WAIT",flag);
		return EXECUTE_STR(cmd,command,flags,result);
	}
	else if(strcmp(flag,"nowait") != 0)
	{
		return SYSINFO_RET_FAIL;
	}
	
	zabbix_log(LOG_LEVEL_DEBUG, "Run remote command '%s'", command);
	
	zabbix_log(LOG_LEVEL_DEBUG, "RUN_COMMAND to be started as NOWAIT",flag);
	
	pid = fork(); /* run new thread 1 */
	switch(pid)
	{
	case -1:
		zabbix_log(LOG_LEVEL_WARNING, "fork failed for '%s'",command);
		return SYSINFO_RET_FAIL;
	case 0:
		pid = fork(); /* run new tread 2 to replace by command */
		switch(pid)
		{
		case -1:
			zabbix_log(LOG_LEVEL_WARNING, "fork2 failed for '%s'",command);
			return SYSINFO_RET_FAIL;
		case 0:
			/* 
			 * DON'T REMVE SLEEP
			 * sleep needed to return server result as "1"
			 * then we can run "execl"
			 * otherwise command print result into socket with STDOUT id
			 */
			sleep(3); 
			/**/
			
			/* replace thread 2 by the execution of command */
			if(execl("/bin/sh", "sh", "-c", command, (char *)0))
			{
				zabbix_log(LOG_LEVEL_WARNING, "execl failed for '%s'",command);
				exit(1);
			}
			/* In normal case the program will never reach this point */
			exit(0);
		default:
			waitpid(pid, NULL, WNOHANG); /* NO WAIT can be used for thread 2 closing */
			exit(0); /* close thread 1 and transmit thread 2 to system (solve zombie state) */
			break;
		}
	default:
		waitpid(pid, NULL, 0); /* wait thread 1 closing */
		break;
	}

	SET_UI64_RESULT(result, 1);
	
	return	SYSINFO_RET_OK;
}
int	forward_request(char *proxy, char *command, int port, unsigned flags, AGENT_RESULT *result)
{
	char	*haddr;
	char	c[1024];
	
	int	s;
	struct	sockaddr_in addr;
	int	addrlen;

	struct hostent *host;

	assert(result);

	init_result(result);
		
	host = gethostbyname(proxy);
	if(host == NULL)
	{
		SET_MSG_RESULT(result, strdup("ZBX_NETWORK_ERROR"));
		return SYSINFO_RET_FAIL;
	}

	haddr=host->h_addr;

	addrlen = sizeof(addr);
	memset(&addr, 0, addrlen);
	addr.sin_port = htons(port);
	addr.sin_family = AF_INET;
	bcopy(haddr, (void *) &addr.sin_addr.s_addr, 4);

	s = socket(AF_INET, SOCK_STREAM, 0);
	if (s == -1)
	{
		close(s);
		SET_MSG_RESULT(result, strdup("ZBX_NOTSUPPORTED"));
		return SYSINFO_RET_FAIL;
	}

	if (connect(s, (struct sockaddr *) &addr, addrlen) == -1)
	{
		close(s);
		SET_MSG_RESULT(result, strdup("ZBX_NETWORK_ERROR"));
		return SYSINFO_RET_FAIL;
	}

	if(write(s,command,strlen(command)) == -1)
	{
		close(s);
		SET_MSG_RESULT(result, strdup("ZBX_NETWORK_ERROR"));
		return SYSINFO_RET_FAIL;
	}

	memset(&c, 0, 1024);
	if(read(s, c, 1024) == -1)
	{
		close(s);
		SET_MSG_RESULT(result, strdup("ZBX_ERROR"));
		return SYSINFO_RET_FAIL;
	}
	close(s);
	
	SET_STR_RESULT(result, strdup(c));

	return	SYSINFO_RET_OK;
}


/* 
 * 0 - NOT OK
 * 1 - OK
 * */
int	tcp_expect(char	*hostname, short port, char *request,char *expect,char *sendtoclose, int *value_int)
{
	char	*haddr;
	char	c[1024];
	
	int	s;
	struct	sockaddr_in addr;
	int	addrlen;


	struct hostent *host;

	host = gethostbyname(hostname);
	if(host == NULL)
	{
		*value_int = 0;
		return SYSINFO_RET_OK;
	}

	haddr=host->h_addr;


	addrlen = sizeof(addr);
	memset(&addr, 0, addrlen);
	addr.sin_port = htons(port);
	addr.sin_family = AF_INET;
	bcopy(haddr, (void *) &addr.sin_addr.s_addr, 4);

	s = socket(AF_INET, SOCK_STREAM, 0);
	if (s == -1)
	{
		close(s);
		*value_int = 0;
		return SYSINFO_RET_OK;
	}

	if (connect(s, (struct sockaddr *) &addr, addrlen) == -1)
	{
		close(s);
		*value_int = 0;
		return SYSINFO_RET_OK;
	}

	if( request != NULL)
	{
		send(s,request,strlen(request),0);
	}

	if( expect == NULL)
	{
		close(s);
		*value_int = 1;
		return SYSINFO_RET_OK;
	}

	memset(&c, 0, 1024);
	recv(s, c, 1024, 0);
	if ( strncmp(c, expect, strlen(expect)) == 0 )
	{
		send(s,sendtoclose,strlen(sendtoclose),0);
		close(s);
		*value_int = 1;
		return SYSINFO_RET_OK;
	}
	else
	{
		send(s,sendtoclose,strlen(sendtoclose),0);
		close(s);
		*value_int = 0;
		return SYSINFO_RET_OK;
	}
}

#ifdef HAVE_LDAP
int    check_ldap(char *hostname, short port, int *value_int)
{
	int rc;
	LDAP *ldap;
	LDAPMessage *res;
	LDAPMessage *msg;

	char *base = "";
	int scope = LDAP_SCOPE_BASE;
	char *filter="(objectClass=*)";
	int attrsonly=0;
	char *attrs[2];

	attrs[0] = "namingContexts";
	attrs[1] = NULL;

	BerElement *ber;
	char *attr=NULL;
	char **valRes=NULL;

        assert(value_int);

	ldap = ldap_init(hostname, port);
	if ( !ldap )
	{
		*value_int = 0;
		return	SYSINFO_RET_OK;
	}

	rc = ldap_search_s(ldap, base, scope, filter, attrs, attrsonly, &res);
	if( rc != 0 )
	{
		*value_int = 0;
		return	SYSINFO_RET_OK;
	}

	msg = ldap_first_entry(ldap, res);
	if( !msg )
	{
		*value_int = 0;
		return	SYSINFO_RET_OK;
	}
       
	attr = ldap_first_attribute (ldap, msg, &ber);
	valRes = ldap_get_values( ldap, msg, attr );

	ldap_value_free(valRes);
	ldap_memfree(attr);
	if (ber != NULL) {
		ber_free(ber, 0);
	}
	ldap_msgfree(res);
	ldap_unbind(ldap);
       
	*value_int = 1;
	
	return	SYSINFO_RET_OK;
}
#endif


/* 
 *  0- NOT OK
 *  1 - OK
 * */
int	check_ssh(char	*hostname, short port, int *value_int)
{
	char	*haddr;
	char	c[MAX_STRING_LEN];
	char	out[MAX_STRING_LEN];
	char	*ssh_proto=NULL;
	char	*ssh_server=NULL;
	
	int	s;
	struct	sockaddr_in addr;
	int	addrlen;

	struct hostent *host;

        assert(value_int);

	host = gethostbyname(hostname);
	if(host == NULL)
	{
		*value_int = 0;
		return	SYSINFO_RET_OK;
	}

	haddr=host->h_addr;

	addrlen = sizeof(addr);
	memset(&addr, 0, addrlen);
	addr.sin_port = htons(port);
	addr.sin_family = AF_INET;
	bcopy(haddr, (void *) &addr.sin_addr.s_addr, 4);

	s = socket(AF_INET, SOCK_STREAM, 0);
	if (s == -1)
	{
		close(s);
		*value_int = 0;
		return	SYSINFO_RET_OK;
	}

	if (connect(s, (struct sockaddr *) &addr, addrlen) == -1)
	{
		close(s);
		*value_int = 0;
		return	SYSINFO_RET_OK;
	}

	memset(&c, 0, 1024);
	recv(s, c, 1024, 0);
	if ( strncmp(c, "SSH", 3) == 0 )
	{
		ssh_proto = c + 4;
		ssh_server = ssh_proto + strspn (ssh_proto, "0123456789-. ");
		ssh_proto[strspn (ssh_proto, "0123456789-. ")] = 0;

/*		printf("[%s] [%s]\n",ssh_proto, ssh_server);*/

		snprintf(out,sizeof(out)-1,"SSH-%s-%s\r\n", ssh_proto, "zabbix_agent");
		send(s,out,strlen(out),0);

/*		printf("[%s]\n",out);*/

		close(s);
		*value_int = 1;
		return	SYSINFO_RET_OK;
	}

	send(s,"0\n",2,0);
	close(s);
	*value_int = 0;
	return	SYSINFO_RET_OK;
}

/* Example check_service[ssh], check_service[smtp,29],check_service[ssh,127.0.0.1,22]*/
/* check_service[ssh,127.0.0.1,ssh] */
int	CHECK_SERVICE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	port=0;
	char	service[MAX_STRING_LEN];
	char	ip[MAX_STRING_LEN];
	char	str_port[MAX_STRING_LEN];

	struct timeval t1,t2;
	struct timezone tz1,tz2;

	int	ret;
	int	value_int;

	long	exec_time;

        assert(result);

	init_result(result);

	gettimeofday(&t1,&tz1);

        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, service, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, ip, MAX_STRING_LEN) != 0)
        {
                ip[0] = '\0';
        }

	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 3, str_port, MAX_STRING_LEN) != 0)
        {
                str_port[0] = '\0';
        }
	
	if(str_port[0] != '\0')
	{
		port = atoi(str_port);
	}
	else
	{
		port = 0;
	}

/*	printf("IP:[%s]",ip);
	printf("Service:[%s]",service);
	printf("Port:[%d]",port);*/

	if(strcmp(service,"ssh") == 0)
	{
		if(port == 0)	port=22;
		ret=check_ssh(ip,port,&value_int);
	}
#ifdef HAVE_LDAP
	else if(strcmp(service,"ldap") == 0)
	{
		if(port == 0)   port=389;
		ret=check_ldap(ip,port,&value_int);
	}
#endif
	else if(strcmp(service,"smtp") == 0)
	{
		if(port == 0)	port=25;
		ret=tcp_expect(ip,port,NULL,"220","QUIT\n",&value_int);
	}
	else if(strcmp(service,"ftp") == 0)
	{
		if(port == 0)	port=21;
		ret=tcp_expect(ip,port,NULL,"220","",&value_int);
	}
	else if(strcmp(service,"http") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else if(strcmp(service,"pop") == 0)
	{
		if(port == 0)	port=110;
		ret=tcp_expect(ip,port,NULL,"+OK","",&value_int);
	}
	else if(strcmp(service,"nntp") == 0)
	{
		if(port == 0)	port=119;
/* 220 is incorrect */
/*		ret=tcp_expect(ip,port,"220","");*/
		ret=tcp_expect(ip,port,NULL,"200","",&value_int);
	}
	else if(strcmp(service,"imap") == 0)
	{
		if(port == 0)	port=143;
		ret=tcp_expect(ip,port,NULL,"* OK","a1 LOGOUT\n",&value_int);
	}
	else if(strcmp(service,"tcp") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	if(value_int)
	{
		gettimeofday(&t2,&tz2);
   		exec_time=(t2.tv_sec - t1.tv_sec) * 1000000 + (t2.tv_usec - t1.tv_usec);

		SET_DBL_RESULT(result, exec_time / 1000000.0);
		return SYSINFO_RET_OK;
	}
	
	SET_DBL_RESULT(result, 0.0);

	return SYSINFO_RET_OK;
}

/* Example check_service[ssh], check_service[smtp,29],check_service[ssh,127.0.0.1,22]*/
/* check_service[ssh,127.0.0.1,ssh] */
int	CHECK_SERVICE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	port=0;
	char	service[MAX_STRING_LEN];
	char	ip[MAX_STRING_LEN];
	char	str_port[MAX_STRING_LEN];

	int	ret;
	int	value_int;

        assert(result);

        init_result(result);
	
        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, service, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, ip, MAX_STRING_LEN) != 0)
        {
                ip[0] = '\0';
        }

	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 3, str_port, MAX_STRING_LEN) != 0)
        {
                str_port[0] = '\0';
        }
	
	if(str_port[0] != '\0')
	{
		port = atoi(str_port);
	}
	else
	{
		port = 0;
	}

/*	printf("IP:[%s]",ip);
	printf("Service:[%s]",service);
	printf("Port:[%d]",port);*/

	if(strcmp(service,"ssh") == 0)
	{
		if(port == 0)	port=22;
		ret=check_ssh(ip,port,&value_int);
	}
	else if(strcmp(service,"service.ntp") == 0)
	{
		if(port == 0)	port=123;
		ret=check_ntp(ip,port,&value_int);
	}
#ifdef HAVE_LDAP
	else if(strcmp(service,"ldap") == 0)
	{
		if(port == 0)   port=389;
		ret=check_ldap(ip,port,&value_int);
	}
#endif
	else if(strcmp(service,"smtp") == 0)
	{
		if(port == 0)	port=25;
		ret=tcp_expect(ip,port,NULL,"220","QUIT\n",&value_int);
	}
	else if(strcmp(service,"ftp") == 0)
	{
		if(port == 0)	port=21;
		ret=tcp_expect(ip,port,NULL,"220","",&value_int);
	}
	else if(strcmp(service,"http") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else if(strcmp(service,"pop") == 0)
	{
		if(port == 0)	port=110;
		ret=tcp_expect(ip,port,NULL,"+OK","",&value_int);
	}
	else if(strcmp(service,"nntp") == 0)
	{
		if(port == 0)	port=119;
/* 220 is incorrect */
/*		ret=tcp_expect(ip,port,"220","");*/
		ret=tcp_expect(ip,port,NULL,"200","",&value_int);
	}
	else if(strcmp(service,"imap") == 0)
	{
		if(port == 0)	port=143;
		ret=tcp_expect(ip,port,NULL,"* OK","a1 LOGOUT\n",&value_int);
	}
	else if(strcmp(service,"tcp") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value_int);

	return ret;
}

int	CHECK_PORT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	port=0;
	int	value_int;
	int	ret;
	char	ip[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];

        assert(result);

	init_result(result);
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, ip, MAX_STRING_LEN) != 0)
        {
               ip[0] = '\0';
        }
	
	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 2, port_str, MAX_STRING_LEN) != 0)
        {
                port_str[0] = '\0';
        }

	if(port_str[0] == '\0')
	{
		return SYSINFO_RET_FAIL;
	}

	port=atoi(port_str);

	ret = tcp_expect(ip,port,NULL,NULL,"",&value_int);
	
	if(ret == SYSINFO_RET_OK)
	{
		SET_UI64_RESULT(result, value_int);
	}
	
	return ret;
}


int	CHECK_DNS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	res;
	char	ip[MAX_STRING_LEN];
	char	zone[MAX_STRING_LEN];
	char	respbuf[PACKETSZ];
	struct	in_addr in;

	extern struct __res_state _res;
	/* extern char *h_errlist[]; */

        assert(result);

        init_result(result);
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, ip, MAX_STRING_LEN) != 0)
        {
               ip[0] = '\0';
        }
	
	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 2, zone, MAX_STRING_LEN) != 0)
        {
                zone[0] = '\0';
        }

	if(zone[0] == '\0')
	{
		strscpy(zone, "localhost");
	}

	res = inet_aton(ip, &in);
	if(res != 1)
	{
		SET_UI64_RESULT(result,0);
		return SYSINFO_RET_FAIL;
	}

	res_init();

/*
	_res.nsaddr.sin_addr=in;
	_res.nscount=1;
	_res.options= (RES_INIT|RES_AAONLY) & ~RES_RECURSE;
	_res.retrans=5;
	_res.retry=1;
*/

	h_errno=0;

	_res.nsaddr_list[0].sin_addr = in;
	_res.nsaddr_list[0].sin_family = AF_INET;
/*	_res.nsaddr_list[0].sin_port = htons(NS_DEFAULTPORT);*/

	_res.nsaddr_list[0].sin_port = htons(53);
	_res.nscount = 1; 
	_res.retrans=5;

#ifdef	C_IN
	res=res_query(zone,C_IN,T_SOA,(unsigned char *)respbuf,sizeof(respbuf));
#else
	res=res_query(zone,ns_c_in,ns_t_soa,(unsigned char *)respbuf,sizeof(respbuf));
#endif
	SET_UI64_RESULT(result, res != -1 ? 1 : 0);

	return SYSINFO_RET_OK;
}

int     SYSTEM_UNUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        assert(result);

        init_result(result);

        return EXECUTE(cmd, "who|wc -l", flags, result);
}

int     SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        assert(result);

        init_result(result);

        return EXECUTE_STR(cmd, "uname -a", flags, result);
}

int     SYSTEM_HOSTNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        assert(result);

        init_result(result);

        return EXECUTE_STR(cmd, "hostname", flags, result);
}

int     OLD_SYSTEM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        char    key[MAX_STRING_LEN];
        int     ret;

        assert(result);

        init_result(result);

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, key, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

        if(strcmp(key,"proccount") == 0)
        {
                ret = PROCCOUNT(cmd, param, flags, result);
        }
        else if(strcmp(key,"procrunning") == 0)
        {
                ret = EXECUTE(cmd, "cat /proc/loadavg|cut -f1 -d'/'|cut -f4 -d' '", flags, result);
        }
        else if(strcmp(key,"uptime") == 0)
        {
                ret = SYSTEM_UPTIME(cmd, param, flags, result);
        }
        else if(strcmp(key,"procload") == 0)
        {
                ret = SYSTEM_CPU_LOAD(cmd, "all,avg1", flags, result);
        }
        else if(strcmp(key,"procload5") == 0)
        {
                ret = SYSTEM_CPU_LOAD(cmd, "all,avg5", flags, result);
        }
        else if(strcmp(key,"procload15") == 0)
        {
                ret = SYSTEM_CPU_LOAD(cmd, "all,avg15", flags, result);
        }
        else if(strcmp(key,"hostname") == 0)
        {
                ret = SYSTEM_HOSTNAME(cmd, param, flags, result);
        }
        else if(strcmp(key,"uname") == 0)
        {
                ret = SYSTEM_UNAME(cmd, param, flags, result);
        }
        else if(strcmp(key,"users") == 0)
        {
                ret = SYSTEM_UNUM(cmd, param, flags, result);
        }
        else
        {
                ret = SYSINFO_RET_FAIL;
        }

        return ret;
}

