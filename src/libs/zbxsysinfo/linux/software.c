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

/* strptime() */
#define _XOPEN_SOURCE

#include "zbxsysinfo.h"
#include "../sysinfo.h"
#include "software.h"

#include "zbxalgo.h"
#include "zbxexec.h"
#include "cfg.h"
#include "zbxregexp.h"
#include "log.h"
#include "zbxstr.h"

#ifdef HAVE_SYS_UTSNAME_H
#       include <sys/utsname.h>
#endif

#define TIME_FMT	"%a %b %d %H:%M:%S %Y"

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

int	system_sw_os(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*type, line[MAX_STRING_LEN], tmp_line[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL, line_read = FAIL;
	FILE	*f = NULL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return ret;
	}

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "full"))
	{
		if (NULL == (f = fopen(SW_OS_FULL, "r")))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open " SW_OS_FULL ": %s",
					zbx_strerror(errno)));
			return ret;
		}
	}
	else if (0 == strcmp(type, "short"))
	{
		if (NULL == (f = fopen(SW_OS_SHORT, "r")))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open " SW_OS_SHORT ": %s",
					zbx_strerror(errno)));
			return ret;
		}
	}
	else if (0 == strcmp(type, "name"))
	{
		/* firstly need to check option PRETTY_NAME in /etc/os-release */
		/* if cannot find it, get value from /etc/issue.net            */
		if (NULL != (f = fopen(SW_OS_NAME_RELEASE, "r")))
		{
			while (NULL != fgets(tmp_line, sizeof(tmp_line), f))
			{
				char	line2[MAX_STRING_LEN];

				if (0 != strncmp(tmp_line, SW_OS_OPTION_PRETTY_NAME,
						ZBX_CONST_STRLEN(SW_OS_OPTION_PRETTY_NAME)))
					continue;

				if (1 == sscanf(tmp_line, SW_OS_OPTION_PRETTY_NAME "=\"%[^\"]", line) ||
						1 == sscanf(tmp_line, SW_OS_OPTION_PRETTY_NAME "=%[^ \t\n] %s",
								line, line2))
				{
					line_read = SUCCEED;
					break;
				}
			}
			zbx_fclose(f);
		}

		if (FAIL == line_read && NULL == (f = fopen(SW_OS_NAME, "r")))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open " SW_OS_NAME ": %s",
					zbx_strerror(errno)));
			return ret;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return ret;
	}

	if (SUCCEED == line_read || NULL != fgets(line, sizeof(line), f))
	{
		ret = SYSINFO_RET_OK;
		zbx_rtrim(line, ZBX_WHITESPACE);
		SET_STR_RESULT(result, zbx_strdup(NULL, line));
	}
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));

	zbx_fclose(f);

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
		const char *arch, zbx_uint64_t size, const char *buildtime_value, time_t buildtime_timestamp,
		const char *installtime_value, time_t installtime_timestamp)
{
	zbx_json_addobject(json, NULL);

	zbx_json_addstring(json, "name", name, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "manager", manager, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "version", version, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "arch", arch, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, "size", size);

	zbx_json_addobject(json, "buildtime");
	zbx_json_addstring(json, "value", buildtime_value, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, "timestamp", buildtime_timestamp);
	zbx_json_close(json);

	zbx_json_addobject(json, "installtime");
	zbx_json_addstring(json, "value", installtime_value, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, "timestamp", installtime_timestamp);
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

	add_package_to_json(json, name, manager, version, arch, size, "", 0, "", 0);
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

	add_package_to_json(json, name, manager, version, arch, size, buildtime_value, buildtime_timestamp,
			installtime_value, installtime_timestamp);
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
	}

	*suffix++ = '\0';

	if (SUCCEED != zbx_is_double(size_str, &size_double))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: unexpected Installed Size \"%s\" (expected type double), ignoring",
				line, size_str);
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
	}

	/* tell mktime() to determine whether daylight saving time is in effect */
	tm.tm_isdst = -1;

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

	add_package_to_json(json, name, manager, version, arch, size, buildtime_value, buildtime_timestamp,
			installtime_value, installtime_timestamp);
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

	add_package_to_json(json, name, manager, version, arch, size, "", 0, "", 0);
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
		"dpkg-query -W -f='${Status},${Package},${Version},${Architecture},${Installed-Size}\n'",
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
		"rpm -qa --queryformat '%{NAME},%{VERSION}-%{RELEASE},%{ARCH},%{SIZE},%{BUILDTIME},%{INSTALLTIME}\n'",
		NULL,
		rpm_details
	},
	{
		"pacman",
		"pacman --version 2> /dev/null",
		"pacman -Q",
		"pacman -Qi 2>/dev/null | grep -E '^(Name|Installed Size|Version|Architecture|(Install|Build) Date)' | cut -f2- -d: | paste -d, - - - - - -",
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

		if (SUCCEED == zbx_execute(mng->test_cmd, &buf, error, sizeof(error), CONFIG_TIMEOUT,
				ZBX_EXIT_CODE_CHECKS_DISABLED, NULL) &&
				'\0' != *buf)	/* consider PMS present, if test_cmd outputs anything to stdout */
		{
			if (SUCCEED != zbx_execute(mng->details_cmd, &buf, error, sizeof(error), CONFIG_TIMEOUT,
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
