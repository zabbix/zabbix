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

#include "zbxsysinfo.h"
#include "../sysinfo.h"
#include "software.h"

#include "zbxalgo.h"
#include "zbxexec.h"
#include "cfg.h"
#include "zbxregexp.h"
#include "log.h"
#include "zbxstr.h"
#include "zbxjson.h"

#ifdef HAVE_SYS_UTSNAME_H
#       include <sys/utsname.h>
#endif

#define SW_OS_FULL			"/proc/version"
#define SW_OS_SHORT 			"/proc/version_signature"
#define SW_OS_NAME			"/etc/issue.net"
#define SW_OS_NAME_RELEASE		"/etc/os-release"
#define SW_OS_OPTION_PRETTY_NAME	"PRETTY_NAME"

int	system_sw_arch(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct utsname	name;

	ZBX_UNUSED(request);

	if (-1 == uname(&name))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, name.machine));

	return SYSINFO_RET_OK;
}

static int	get_line_from_file(char **line, size_t size, FILE *f)
{
	if (NULL == fgets(*line, size, f))
	{
		*line = zbx_strdup(*line, "Cannot read from file.");
		return FAIL;
	}

	zbx_rtrim(*line, ZBX_WHITESPACE);
	return SUCCEED;
}

static int	get_os_name(char **line)
{
	char	tmp_line[MAX_STRING_LEN];
	FILE	*f = NULL;

	*line = zbx_malloc(NULL, sizeof(char) * MAX_STRING_LEN);

	/* firstly need to check option PRETTY_NAME in /etc/os-release */
	/* if cannot find it, get value from /etc/issue.net            */
	if (NULL != (f = fopen(SW_OS_NAME_RELEASE, "r")))
	{
		char	line2[MAX_STRING_LEN];
		int	line_read = FAIL;

		while (NULL != fgets(tmp_line, sizeof(tmp_line), f))
		{

			if (0 != strncmp(tmp_line, SW_OS_OPTION_PRETTY_NAME,
					ZBX_CONST_STRLEN(SW_OS_OPTION_PRETTY_NAME)))
				continue;

			if (1 == sscanf(tmp_line, SW_OS_OPTION_PRETTY_NAME "=\"%[^\"]", *line) ||
					1 == sscanf(tmp_line, SW_OS_OPTION_PRETTY_NAME "=%[^ \t\n] %s", *line, line2))
			{
				line_read = SUCCEED;
				break;
			}
		}
		zbx_fclose(f);

		if (SUCCEED == line_read)
		{
			zbx_rtrim(*line, ZBX_WHITESPACE);
			goto out;
		}
	}

	if (NULL == (f = fopen(SW_OS_NAME, "r")))
	{
		*line = zbx_dsprintf(*line, "Cannot open " SW_OS_NAME ": %s", zbx_strerror(errno));
		goto error;
	}
	else
	{
		if (FAIL == get_line_from_file(line, MAX_STRING_LEN, f))
			goto error;
		zbx_fclose(f);
	}

out:
	return SUCCEED;

error:
	if (NULL != f)
		zbx_fclose(f);

	return FAIL;
}

static int	get_os_info_file(char **line, const char *filename)
{
	FILE	*f = NULL;
	int	ret = FAIL;

	*line = zbx_malloc(NULL, sizeof(char) * MAX_STRING_LEN);

	if (NULL == (f = fopen(filename, "r")))
	{
		*line = zbx_dsprintf(*line, "Cannot open %s: %s", filename, zbx_strerror(errno));

		return FAIL;
	}

	ret = get_line_from_file(line, MAX_STRING_LEN, f);
	zbx_fclose(f);

	return ret;
}

int	system_sw_os(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*type, *str;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return ret;
	}

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "full"))
	{
		ret = get_os_info_file(&str, SW_OS_FULL);
	}
	else if (0 == strcmp(type, "short"))
	{
		ret = get_os_info_file(&str, SW_OS_SHORT);
	}
	else if (0 == strcmp(type, "name"))
	{
		ret = get_os_name(&str);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return ret;
	}

	if (SUCCEED == ret)
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, str));
		ret = SYSINFO_RET_OK;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, str));
		ret = SYSINFO_RET_FAIL;
	}
	zbx_free(str);

	return ret;
}

static int	dpkg_parser(const char *line, char *package, size_t max_package_len)
{
	char	fmt[32], tmp[32];

	zbx_snprintf(fmt, sizeof(fmt), "%%" ZBX_FS_SIZE_T "s %%" ZBX_FS_SIZE_T "s",
			(zbx_fs_size_t)(max_package_len - 1), (zbx_fs_size_t)(sizeof(tmp) - 1));

	if (2 != sscanf(line, fmt, package, tmp) || 0 != strcmp(tmp, "install"))
		return FAIL;

	return SUCCEED;
}

static size_t	print_packages(char *buffer, size_t size, zbx_vector_str_t *packages, const char *manager)
{
	size_t	offset = 0;
	int	i;

	if (NULL != manager)
		offset += zbx_snprintf(buffer + offset, size - offset, "[%s]", manager);

	if (0 < packages->values_num)
	{
		if (NULL != manager)
			offset += zbx_snprintf(buffer + offset, size - offset, " ");

		zbx_vector_str_sort(packages, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (i = 0; i < packages->values_num; i++)
			offset += zbx_snprintf(buffer + offset, size - offset, "%s, ", packages->values[i]);

		offset -= 2;
	}

	buffer[offset] = '\0';

	return offset;
}

static ZBX_PACKAGE_MANAGER	package_managers[] =
/*	NAME		TEST_CMD					LIST_CMD			PARSER */
{
	{"dpkg",	"dpkg --version 2> /dev/null",			"dpkg --get-selections",	dpkg_parser},
	{"pkgtools",	"[ -d /var/log/packages ] && echo true",	"ls /var/log/packages",		NULL},
	{"rpm",		"rpm --version 2> /dev/null",			"rpm -qa",			NULL},
	{"pacman",	"pacman --version 2> /dev/null",		"pacman -Q",			NULL},
	{NULL}
};

int	system_sw_packages(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	size_t			offset = 0;
	int			ret = SYSINFO_RET_FAIL, show_pm, i, check_regex, check_manager;
	char			buffer[MAX_BUFFER_LEN], *regex, *manager, *mode, tmp[MAX_STRING_LEN], *buf = NULL,
				*package;
	zbx_vector_str_t	packages;
	ZBX_PACKAGE_MANAGER	*mng;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return ret;
	}

	regex = get_rparam(request, 0);
	manager = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	check_regex = (NULL != regex && '\0' != *regex && 0 != strcmp(regex, "all"));
	check_manager = (NULL != manager && '\0' != *manager && 0 != strcmp(manager, "all"));

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "full"))
		show_pm = 1;	/* show package managers' names */
	else if (0 == strcmp(mode, "short"))
		show_pm = 0;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return ret;
	}

	*buffer = '\0';
	zbx_vector_str_create(&packages);

	for (i = 0; NULL != package_managers[i].name; i++)
	{
		mng = &package_managers[i];

		if (1 == check_manager && 0 != strcmp(manager, mng->name))
			continue;

		if (SUCCEED == zbx_execute(mng->test_cmd, &buf, tmp, sizeof(tmp), CONFIG_TIMEOUT,
				ZBX_EXIT_CODE_CHECKS_DISABLED, NULL) &&
				'\0' != *buf)	/* consider PMS present, if test_cmd outputs anything to stdout */
		{
			if (SUCCEED != zbx_execute(mng->list_cmd, &buf, tmp, sizeof(tmp), CONFIG_TIMEOUT,
					ZBX_EXIT_CODE_CHECKS_DISABLED, NULL))
			{
				continue;
			}

			ret = SYSINFO_RET_OK;

			package = strtok(buf, "\n");

			while (NULL != package)
			{
				if (NULL != mng->parser)	/* check if the package name needs to be parsed */
				{
					if (SUCCEED == mng->parser(package, tmp, sizeof(tmp)))
						package = tmp;
					else
						goto next;
				}

				if (1 == check_regex && NULL == zbx_regexp_match(package, regex, NULL))
					goto next;

				zbx_vector_str_append(&packages, zbx_strdup(NULL, package));
next:
				package = strtok(NULL, "\n");
			}

			if (1 == show_pm)
			{
				offset += print_packages(buffer + offset, sizeof(buffer) - offset, &packages,
						mng->name);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\n");

				zbx_vector_str_clear_ext(&packages, zbx_str_free);
			}
		}
	}

	zbx_free(buf);

	if (0 == show_pm)
	{
		print_packages(buffer + offset, sizeof(buffer) - offset, &packages, NULL);

		zbx_vector_str_clear_ext(&packages, zbx_str_free);
	}
	else if (0 != offset)
		buffer[--offset] = '\0';

	zbx_vector_str_destroy(&packages);

	if (SYSINFO_RET_OK == ret)
		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain package information."));

	return ret;
}

static void append_to_pretty_ver(char **pretty, const char *str)
{
	size_t	prt_alloc = 0, prt_offset = 0;

	if (*pretty == NULL)
		zbx_strcatnl_alloc(pretty, &prt_alloc, &prt_offset, str);
	else
		*pretty = zbx_dsprintf(*pretty, "%s %s", *pretty, str);

	return;
}

int	system_sw_os_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#define SW_OS_GET_TYPE		"os_type"
#define SW_OS_GET_PROD_NAME	"product_name"
#define SW_OS_GET_ARCH		"architecture"
#define SW_OS_GET_KRNL_MAJOR	"kernel_major"
#define SW_OS_GET_KRNL_MINOR	"kernel_minor"
#define SW_OS_GET_KRNL_PATCH	"kernel_patch"
#define SW_OS_GET_KRNL		"kernel"
#define SW_OS_GET_VER_PRETTY	"version_pretty"
#define SW_OS_GET_VER_FULL	"version_full"

	struct zbx_json	j;
	struct utsname	info;
	int		read;
	char		*str, *prt_version = NULL;

	char		major[sizeof(info.release)], minor[sizeof(info.release)], patch[sizeof(info.release)];

	ZBX_UNUSED(request);
	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, SW_OS_GET_TYPE, "linux", ZBX_JSON_TYPE_STRING);

	if (SUCCEED == get_os_name(&str))
	{
		zbx_json_addstring(&j, SW_OS_GET_PROD_NAME, str, ZBX_JSON_TYPE_STRING);
		append_to_pretty_ver(&prt_version, str);
	}
	zbx_free(str);

	if (0 != uname(&info))
		goto out;

	if (0 != strlen(info.machine))
	{
		zbx_json_addstring(&j, SW_OS_GET_ARCH, info.machine, ZBX_JSON_TYPE_STRING);
		append_to_pretty_ver(&prt_version, info.machine);
	}

	if (0 != strlen(info.release))
	{
		read = sscanf(info.release, "%[0-9].%[0-9].%[0-9]", major, minor, patch);

		if (0 < read)
			zbx_json_addstring(&j, SW_OS_GET_KRNL_MAJOR, major, ZBX_JSON_TYPE_STRING);
		if (1 < read)
			zbx_json_addstring(&j, SW_OS_GET_KRNL_MINOR, minor, ZBX_JSON_TYPE_STRING);
		if (2 < read)
			zbx_json_addstring(&j, SW_OS_GET_KRNL_PATCH, patch, ZBX_JSON_TYPE_STRING);

		zbx_json_addstring(&j, SW_OS_GET_KRNL, info.release, ZBX_JSON_TYPE_STRING);
		append_to_pretty_ver(&prt_version, info.release);
	}
out:
	if (NULL != prt_version && 0 != strlen(prt_version))
		zbx_json_addstring(&j, SW_OS_GET_VER_PRETTY, prt_version, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == get_os_info_file(&str, SW_OS_FULL))
		zbx_json_addstring(&j, SW_OS_GET_VER_FULL, str, ZBX_JSON_TYPE_STRING);
	else
		zbx_json_addstring(&j, SW_OS_GET_VER_FULL, "", ZBX_JSON_TYPE_STRING);
	zbx_free(str);

	zbx_json_close(&j);
	SET_STR_RESULT(result, strdup(j.buffer));
	zbx_json_free(&j);

	return SYSINFO_RET_OK;

#undef SW_OS_GET_TYPE
#undef SW_OS_GET_PROD_NAME
#undef SW_OS_GET_ARCH
#undef SW_OS_GET_KRNL_MAJOR
#undef SW_OS_GET_KRNL_MINOR
#undef SW_OS_GET_KRNL_PATCH
#undef SW_OS_GET_KRNL
#undef SW_OS_GET_VER_PRETTY
#undef SW_OS_GET_VER_FULL
}
