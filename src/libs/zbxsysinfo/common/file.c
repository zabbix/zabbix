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
#include "common.h"
#include "sysinfo.h"

#define MAX_FILE_LEN (1024*1024)

int	VFS_FILE_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct stat	buf;
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
	
	if(stat(filename,&buf) == 0)
	{
		SET_UI64_RESULT(result, buf.st_size);
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FILE_TIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct stat	buf;
	char    filename[MAX_STRING_LEN];
	char    type[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;

        assert(result);

        init_result(result);

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, filename, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
        if(get_param(param, 2, type, MAX_STRING_LEN) != 0)
        {
                type[0] = '\0';
        }

	if(type[0] == '\0')
	{
		strscpy(type, "modify");
	}
	
	if(stat(filename,&buf) == 0)
	{
		if(strcmp(type,"modify") == 0)
		{
			SET_UI64_RESULT(result, buf.st_mtime);
			ret = SYSINFO_RET_OK;
		}
		else if(strcmp(type,"access") == 0)
		{
			SET_UI64_RESULT(result, buf.st_atime);
			ret = SYSINFO_RET_OK;
		}
		else if(strcmp(type,"change") == 0)
		{
			SET_UI64_RESULT(result, buf.st_ctime);
			ret = SYSINFO_RET_OK;
		}
	}
	return	ret;
}

int	VFS_FILE_EXISTS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct stat	buf;
	char    filename[MAX_STRING_LEN];

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

	SET_UI64_RESULT(result, 0);
	/* File exists */
	if(stat(filename,&buf) == 0)
	{
		/* Regular file */
		if(S_ISREG(buf.st_mode))
		{
			SET_UI64_RESULT(result, 1);
		}
	}
	/* File does not exist or any other error */
	return SYSINFO_RET_OK;
}

/* #include<malloc.h> */

int	VFS_FILE_REGEXP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	filename[MAX_STRING_LEN];
	char	regexp[MAX_STRING_LEN];
	FILE	*f = NULL;
	int	len;
	char	tmp[MAX_STRING_LEN];
	char	*c;

	char	*buf = NULL;

        assert(result);

        init_result(result);

	memset(tmp,0,MAX_STRING_LEN);

	if(get_param(param, 1, filename, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, regexp, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}


	if(NULL == (f = fopen(filename,"r")))
	{
		return SYSINFO_RET_FAIL;
	}

	if(NULL == (buf = (char*)calloc((size_t)MAX_FILE_LEN, 1)))
	{
		goto lbl_fail;
	}

	if(0 == fread(buf, 1, MAX_FILE_LEN-1, f))
	{
		goto lbl_fail;
	}
 	zbx_fclose(f);

	c = zbx_regexp_match(buf, regexp, &len);

	if(c == NULL)
	{
		tmp[0]=0;
	}
	else
	{
		strncpy(tmp,c,len);
	}

	SET_STR_RESULT(result, strdup(tmp));

	zbx_free(buf);

	return	SYSINFO_RET_OK;

lbl_fail:

	zbx_free(buf);
	zbx_fclose(f);

	return	SYSINFO_RET_FAIL;
}

int	VFS_FILE_REGMATCH(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	filename[MAX_STRING_LEN];
	char	regexp[MAX_STRING_LEN];
	FILE	*f = NULL;
	int	len;
	char	*c;

	char	*buf = NULL;

        assert(result);

        init_result(result);

	if(get_param(param, 1, filename, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, regexp, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}


	if(NULL == (f = fopen(filename,"r")))
	{
		return SYSINFO_RET_FAIL;
	}

	if(NULL == (buf = (char*)calloc((size_t)MAX_FILE_LEN, 1)))
	{
		goto lbl_fail;
	}

	if(0 == fread(buf, 1, MAX_FILE_LEN-1, f))
	{
		goto lbl_fail;
	}

	zbx_fclose(f);

	c = zbx_regexp_match(buf, regexp, &len);

	if(c == NULL)
	{
		SET_UI64_RESULT(result, 0);
	}
	else
	{
		SET_UI64_RESULT(result, 1);
	}

	zbx_free(buf);

	return	SYSINFO_RET_OK;

lbl_fail:

	zbx_free(buf);
	zbx_fclose(f);

	return	SYSINFO_RET_FAIL;
}
