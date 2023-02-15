/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

/* strptime() on newer and older GNU/Linux systems */
#define _GNU_SOURCE

#include "zbxsysinfo.h"
#include "../sysinfo.h"
#include "software.h"

#include "zbxalgo.h"
#include "zbxexec.h"
#include "cfg.h"
#include "zbxregexp.h"
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

#define TIME_FMT	"%a %b %e %H:%M:%S %Y"

#define DETAIL_BUF	128

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

static int	get_line_from_file(char **line, int size, FILE *f)
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

static int	dpkg_list(const char *line, char *package, size_t max_package_len)
{
	char	fmt[32], tmp[32];

	zbx_snprintf(fmt, sizeof(fmt), "%%" ZBX_FS_SIZE_T "s %%" ZBX_FS_SIZE_T "s",
			(zbx_fs_size_t)(max_package_len - 1), (zbx_fs_size_t)(sizeof(tmp) - 1));

	if (2 != sscanf(line, fmt, package, tmp) || 0 != strcmp(tmp, "install"))
		return FAIL;

	return SUCCEED;
}

static void	add_package_to_json(struct zbx_json *json, const char *name, const char *manager, const char *version,
		zbx_uint64_t size, const char *arch, time_t buildtime_timestamp, const char *buildtime_value,
		time_t installtime_timestamp, const char *installtime_value)
{
	zbx_json_addobject(json, NULL);

	zbx_json_addstring(json, "name", name, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "manager", manager, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "version", version, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, "size", size);
	zbx_json_addstring(json, "arch", arch, ZBX_JSON_TYPE_STRING);

	zbx_json_addobject(json, "buildtime");
	zbx_json_addint64(json, "timestamp", buildtime_timestamp);
	zbx_json_addstring(json, "value", buildtime_value, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);

	zbx_json_addobject(json, "installtime");
	zbx_json_addint64(json, "timestamp", installtime_timestamp);
	zbx_json_addstring(json, "value", installtime_value, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);

	zbx_json_close(json);
}

static void	dpkg_details(const char *manager, const char *line, const char *regex, struct zbx_json *json)
{
	static char	fmt[64] = "";

	char		status[DETAIL_BUF] = "", name[DETAIL_BUF] = "", version[DETAIL_BUF] = "", arch[DETAIL_BUF] = "";
	zbx_uint64_t	size;
	int		rv;

	if ('\0' == fmt[0])
	{
		zbx_snprintf(fmt, sizeof(fmt),
				"%%" ZBX_FS_SIZE_T "[^,],"
				"%%" ZBX_FS_SIZE_T "[^,],"
				"%%" ZBX_FS_SIZE_T "[^,],"
				"%%" ZBX_FS_SIZE_T "[^,],"
				"%" ZBX_FS_UI64,
				(zbx_fs_size_t)(sizeof(status) - 1),
				(zbx_fs_size_t)(sizeof(name) - 1),
				(zbx_fs_size_t)(sizeof(version) - 1),
				(zbx_fs_size_t)(sizeof(arch) - 1));
	}

#define NUM_FIELDS	5
	if (NUM_FIELDS != (rv = sscanf(line, fmt, status, name, version, arch, &size)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: could only collect %d (expected %d) values, ignoring",
				line, rv, NUM_FIELDS);
		return;
	}
#undef NUM_FIELDS

	if (0 != strcmp(status, "install ok installed"))
		return;

	if (NULL != regex && NULL == zbx_regexp_match(name, regex, NULL))
		return;

	/* the reported size is in kB, we want bytes */
	size *= ZBX_KIBIBYTE;

	add_package_to_json(json, name, manager, version, size, arch, 0, "", 0, "");
}

static void	rpm_details(const char *manager, const char *line, const char *regex, struct zbx_json *json)
{
	static char	fmt[64] = "";

	char		name[DETAIL_BUF] = "", version[DETAIL_BUF] = "", arch[DETAIL_BUF] = "", buildtime_value[DETAIL_BUF],
			installtime_value[DETAIL_BUF];
	zbx_uint64_t	size;
	time_t		buildtime_timestamp, installtime_timestamp;
	int		rv;

	if ('\0' == fmt[0])
	{
		zbx_snprintf(fmt, sizeof(fmt),
				"%%" ZBX_FS_SIZE_T "[^,],"
				"%%" ZBX_FS_SIZE_T "[^,],"
				"%%" ZBX_FS_SIZE_T "[^,],"
				"%" ZBX_FS_TIME_T ","
				"%" ZBX_FS_TIME_T ","
				"%" ZBX_FS_UI64,
				(zbx_fs_size_t)(sizeof(name) - 1),
				(zbx_fs_size_t)(sizeof(version) - 1),
				(zbx_fs_size_t)(sizeof(arch) - 1));
	}

#define NUM_FIELDS	6
	if (NUM_FIELDS != (rv = sscanf(line, fmt, name, version, arch, &size, &buildtime_timestamp,
			&installtime_timestamp)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: could only collect %d (expected %d) values, ignoring",
				line, rv, NUM_FIELDS);
		return;
	}
#undef NUM_FIELDS

	if (NULL != regex && NULL == zbx_regexp_match(name, regex, NULL))
		return;

	strftime(buildtime_value, sizeof(buildtime_value), TIME_FMT, localtime(&buildtime_timestamp));
	strftime(installtime_value, sizeof(installtime_value), TIME_FMT, localtime(&installtime_timestamp));

	add_package_to_json(json, name, manager, version, size, arch, buildtime_timestamp, buildtime_value,
			installtime_timestamp, installtime_value);
}

static void	pacman_details(const char *manager, const char *line, const char *regex, struct zbx_json *json)
{
	static char	fmt[64] = "";

	char		name[DETAIL_BUF] = "", version[DETAIL_BUF] = "", arch[DETAIL_BUF] = "",
			size_str[DETAIL_BUF] = "", buildtime_value[DETAIL_BUF] = "", installtime_value[DETAIL_BUF],
			*suffix;
	const char	*p;
	zbx_uint64_t	size;
	time_t		buildtime_timestamp, installtime_timestamp;
	struct tm	tm;
	double		size_double;
	int		rv;

	/* e. g. " tpm2-tss, 3.2.0-3, x86_64, 2.86 MiB, Tue Nov 1 20:46:18 2022, Sun Nov 6 00:04:09 2022" */
	if ('\0' == fmt[0])
	{
		zbx_snprintf(fmt, sizeof(fmt),
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,]",
				(zbx_fs_size_t)(sizeof(name) - 1),
				(zbx_fs_size_t)(sizeof(version) - 1),
				(zbx_fs_size_t)(sizeof(arch) - 1),
				(zbx_fs_size_t)(sizeof(size_str) - 1),
				(zbx_fs_size_t)(sizeof(buildtime_value) - 1),
				(zbx_fs_size_t)(sizeof(installtime_value) - 1));
	}

#define NUM_FIELDS	6
	if (NUM_FIELDS != (rv = sscanf(line, fmt, name, version, arch, size_str, buildtime_value, installtime_value)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: could only collect %d (expected %d) values, ignoring",
				line, rv, NUM_FIELDS);
		return;
	}
#undef NUM_FIELDS

	if (NULL != regex && NULL == zbx_regexp_match(name, regex, NULL))
		return;

	if (NULL == (suffix = strchr(size_str, ' ')))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected Installed Size \"%s\" (expected whitespace), ignoring",
				line, size_str);
		return;
	}

	*suffix++ = '\0';

	if (SUCCEED != zbx_is_double(size_str, &size_double))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected Installed Size \"%s\" (expected type double), ignoring",
				line, size_str);
		return;
	}

	/* pacman supports the following labels:                       */
	/* "B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB" */
	if (0 == strcmp(suffix, "B"))
	{
		size = (zbx_uint64_t)size_double;
	}
	else if (0 == strcmp(suffix, "KiB"))
	{
		size = (zbx_uint64_t)(size_double * ZBX_KIBIBYTE);
	}
	else if (0 == strcmp(suffix, "MiB"))
	{
		size = (zbx_uint64_t)(size_double * ZBX_MEBIBYTE);
	}
	else if (0 == strcmp(suffix, "GiB"))
	{
		size = (zbx_uint64_t)(size_double * ZBX_GIBIBYTE);
	}
	else if (0 == strcmp(suffix, "TiB"))
	{
		size = (zbx_uint64_t)(size_double * ZBX_TEBIBYTE);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unrecognized Installed Size suffix \"%s %s\", ignoring",
				line, size_str, suffix);
		return;
	}

	memset(&tm, 0, sizeof(tm));
	tm.tm_isdst = -1;	/* tell mktime() to determine whether daylight saving time is in effect */

	if (NULL == (p = strptime(buildtime_value, TIME_FMT, &tm)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected Build Date \"%s\", ignoring", line, buildtime_value);
		return;
	}

	if ('\0' != *p)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected Build Date format at \"%s\" (expected %s), ignoring",
				line, p, TIME_FMT);
		return;
	}

	buildtime_timestamp = mktime(&tm);

	memset(&tm, 0, sizeof(tm));
	tm.tm_isdst = -1;	/* tell mktime() to determine whether daylight saving time is in effect */

	if (NULL == strptime(installtime_value, TIME_FMT, &tm))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected Install Date \"%s\", ignoring", line, installtime_value);
		return;
	}

	if ('\0' != *p)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected Install Date format at \"%s\" (expected %s), ignoring",
				line, p, TIME_FMT);
		return;
	}

	installtime_timestamp = mktime(&tm);

	add_package_to_json(json, name, manager, version, size, arch, buildtime_timestamp, buildtime_value,
			installtime_timestamp, installtime_value);
}

static void	pkgtools_details(const char *manager, const char *line, const char *regex, struct zbx_json *json)
{
	static char	fmt[64] = "";

	char		name[DETAIL_BUF] = "", version[DETAIL_BUF] = "", arch[DETAIL_BUF] = "",
			size_str[DETAIL_BUF] = "", *out = NULL, *suffix;
	zbx_uint64_t	size, multiplier;
	double		size_double;
	int		rv;

	/* Since <name> can contain dashes we cannot use sscanf() here so regex is the only way. */
	/* /var/log/packages/util-linux-2.27.1-x86_64-1:UNCOMPRESSED PACKAGE SIZE:     1.9M      */
	/* "version" and "build" must be combined: <name>-<version>-<arch>-<build>...:<size>     */
	if (SUCCEED != zbx_regexp_sub(
			line,
			"^/var/log/packages/(.*)-([^-]+)-([^-]+)-([^:]+):UNCOMPRESSED PACKAGE SIZE:\\s+(.*)$",
			"\\1,\\2-\\4,\\3,\\5",
			&out))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "internal error: could not compile regex");
		goto out;
	}

	if (NULL == out)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "unexpected line \"%s\", ignoring", line);
		goto out;
	}

	if ('\0' == fmt[0])
	{
		zbx_snprintf(fmt, sizeof(fmt),
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,],"
				" %%" ZBX_FS_SIZE_T "[^,],",
				(zbx_fs_size_t)(sizeof(name) - 1),
				(zbx_fs_size_t)(sizeof(version) - 1),
				(zbx_fs_size_t)(sizeof(arch) - 1),
				(zbx_fs_size_t)(sizeof(size_str) - 1));
	}

#define NUM_FIELDS	4
	rv = sscanf(out, fmt, name, version, arch, size_str);

	if (NUM_FIELDS != rv)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: could only collect %d (expected %d) values, ignoring",
				line, rv, NUM_FIELDS);
		goto out;
	}
#undef NUM_FIELDS

	if (NULL != regex && NULL == zbx_regexp_match(name, regex, NULL))
		goto out;

	/* according to pkgtools source code the size suffix is    */
	/* either 'K' or 'M' and it may be specified in 3 formats: */
	/*   <n>K                                                  */
	/*   <n>.<n>M                                              */
	/*   <n>M                                                  */
	if (NULL != (suffix = strchr(size_str, 'K')))
	{
		multiplier = ZBX_KIBIBYTE;
	}
	else if (NULL != (suffix = strchr(size_str, 'M')))
	{
		multiplier = ZBX_MEBIBYTE;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected size suffix in \"%s\": expected 'K' or 'M', ignoring",
				line, size_str);
		goto out;
	}

	*suffix = '\0';

	if (SUCCEED != zbx_is_double(size_str, &size_double))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected size in \"%s\"", line, size_str);
		goto out;
	}

	size = (zbx_uint64_t)(size_double * multiplier);

	add_package_to_json(json, name, manager, version, size, arch, 0, "", 0, "");
out:
	zbx_free(out);
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

/**
 * NAME
 * TEST_CMD
 * LIST_CMD
 * DETAILS_CMD
 * LIST_PARSER
 * DETAILS_PARSER
 */
static ZBX_PACKAGE_MANAGER	package_managers[] =
{
	{
		"dpkg",
		"dpkg --version 2> /dev/null",
		"dpkg --get-selections",
		"LC_ALL=C dpkg-query -W -f='${Status},${Package},${Version},${Architecture},${Installed-Size}\n'",
		dpkg_list,
		dpkg_details
	},
	{
		"pkgtools",
		"[ -d /var/log/packages ] && echo true",
		"ls /var/log/packages",
		"grep -r '^UNCOMPRESSED PACKAGE SIZE' /var/log/packages",
		NULL,
		pkgtools_details
	},
	{
		"rpm",
		"rpm --version 2> /dev/null",
		"rpm -qa",
		"LC_ALL=C rpm -qa --queryformat '%{NAME},%{VERSION}-%{RELEASE},%{ARCH},%{SIZE},%{BUILDTIME},%{INSTALLTIME}\n'",
		NULL,
		rpm_details
	},
	{
		"pacman",
		"pacman --version 2> /dev/null",
		"pacman -Q",
		"LC_ALL=C pacman -Qi 2>/dev/null | grep -E '^(Name|Installed Size|Version|Architecture|(Install|Build) Date)' | cut -f2- -d: | paste -d, - - - - - -",
		NULL,
		pacman_details
	},
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

		if (SUCCEED == zbx_execute(mng->test_cmd, &buf, tmp, sizeof(tmp), sysinfo_get_config_timeout(),
				ZBX_EXIT_CODE_CHECKS_DISABLED, NULL) &&
				'\0' != *buf)	/* consider this manager if test_cmd outputs anything to stdout */
		{
			if (SUCCEED != zbx_execute(mng->list_cmd, &buf, tmp, sizeof(tmp), sysinfo_get_config_timeout(),
					ZBX_EXIT_CODE_CHECKS_DISABLED, NULL))
			{
				continue;
			}

			ret = SYSINFO_RET_OK;

			package = strtok(buf, "\n");

			while (NULL != package)
			{
				if (NULL != mng->list_parser)	/* check if the package name needs to be parsed */
				{
					if (SUCCEED == mng->list_parser(package, tmp, sizeof(tmp)))
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

	if (NULL == *pretty)
		zbx_strcpy_alloc(pretty, &prt_alloc, &prt_offset, str);
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

	if (0 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

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
	zbx_free(prt_version);

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

int	system_sw_packages_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int			ret = SYSINFO_RET_FAIL, i, check_regex, check_manager;
	char			*regex, *manager, *line, *buf = NULL, error[MAX_STRING_LEN];
	ZBX_PACKAGE_MANAGER	*mng;
	struct zbx_json		json;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return ret;
	}

	regex = get_rparam(request, 0);
	manager = get_rparam(request, 1);

	check_regex = (NULL != regex && '\0' != *regex && 0 != strcmp(regex, "all"));
	check_manager = (NULL != manager && '\0' != *manager && 0 != strcmp(manager, "all"));

	zbx_json_initarray(&json, 10 * ZBX_KIBIBYTE);

	for (i = 0; NULL != package_managers[i].name; i++)
	{
		mng = &package_managers[i];

		if (1 == check_manager && 0 != strcmp(manager, mng->name))
			continue;

		if (SUCCEED == zbx_execute(mng->test_cmd, &buf, error, sizeof(error), sysinfo_get_config_timeout(),
				ZBX_EXIT_CODE_CHECKS_DISABLED, NULL) &&
				'\0' != *buf)	/* consider this manager if test_cmd outputs anything to stdout */
		{
			if (SUCCEED != zbx_execute(mng->details_cmd, &buf, error, sizeof(error), sysinfo_get_config_timeout(),
					ZBX_EXIT_CODE_CHECKS_DISABLED, NULL))
			{
				continue;
			}

			ret = SYSINFO_RET_OK;

			line = strtok(buf, "\n");

			while (NULL != line)
			{
				mng->details_parser(mng->name, line, (1 == check_regex ? regex : NULL), &json);

				line = strtok(NULL, "\n");
			}
		}
	}

	zbx_free(buf);

	if (SYSINFO_RET_OK == ret)
		SET_TEXT_RESULT(result, zbx_strdup(NULL, json.buffer));
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain package information."));

	zbx_json_free(&json);

	return ret;
}
