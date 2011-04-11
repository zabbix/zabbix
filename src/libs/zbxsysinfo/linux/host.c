/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
#include <sys/mman.h>
#include <sys/utsname.h>

#define DEV_MEM "/dev/mem"
#define SMBIOS_ENTRY_POINT_SIZE 0x20
#define DMI_HEADER_SIZE 4
#define DMI_GET_VENDOR	0x01
#define DMI_GET_CHASSIS	0x02
#define DMI_GET_MODEL	0x04
#define DMI_GET_SERIAL	0x08

#define HOST_OS_NAME "/etc/issue.net"
#define HOST_OS_SHORT "/proc/version_signature"
#define HOST_OS_FULL "/proc/version"
#define DPKG_STATUS_FILE "/var/lib/dpkg/status"

int	HOST_ARCH(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct utsname	name;
	if (-1 == uname(&name))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, strdup(name.machine));
	return SYSINFO_RET_OK;
}

/* read the string #num from data into buf */
int	set_dmi_string(char *buf, int bufsize, unsigned char *data, int num)
{
	char *c = (char *) data;
	if (0 == num)
		return SYSINFO_RET_FAIL;

	c += data[1]; /* skip to string data */
	while (1 < num)
	{
		c += strlen(c);
		c++;
		num--;
	}

	zbx_snprintf(buf, bufsize, "%s", c);
	return SYSINFO_RET_OK;
}

#define CHASSIS_TYPE_BITS 0x7F	/* bits 0-6 represent the chassis type */
#define MAX_CHASSIS_TYPE 0x18
int	set_chassis_type(char *buf, int bufsize, int type)
{
	static const char *chassis_type[] =
	{
		"",			/* 0x00 */
		"Other",
		"Unknown",
		"Desktop",
		"Low Profile Desktop",
		"Pizza Box",
		"Mini Tower",
		"Tower",
		"Portable",
		"LapTop",
		"Notebook",
		"Hand Held",
		"Docking Station",
		"All In One",
		"Sub Notebook",
		"Space-saving",
		"Lunch Box",
		"Main Server Chassis",
		"Expansion Chassis",
		"Sub Chassis",
		"Bus Expansion Chassis",
		"Peripheral Chassis",
		"RAID Chassis",
		"Rack Mount Chassis",
		"Sealed-case PC",	/* 0x18 */
	};

	type = CHASSIS_TYPE_BITS & type;

	if (1 > type || MAX_CHASSIS_TYPE < type)
		return SYSINFO_RET_FAIL;

	zbx_snprintf(buf, bufsize, "%s", chassis_type[type]);
	return SYSINFO_RET_OK;
}

static int	get_dmi_info(char *buf, int bufsize, int flags)
{
	int		ret = SYSINFO_RET_FAIL, fd;
	unsigned char	membuf[SMBIOS_ENTRY_POINT_SIZE], *smbuf, *data;
	size_t		len, fp, smbios_len, smbios = 0;
	void		*mmp = NULL;

	if (-1 == (fd = open(DEV_MEM, O_RDONLY)))
		return ret;

	/* find smbios entry point - located between 0xF0000 and 0xFFFFF (according to the specs) */
	for (fp = 0xF0000L; 0xFFFFF > fp; fp += 16)
	{
		memset(membuf, 0, sizeof(membuf));

		len = fp % getpagesize(); /* mmp needs to be a multiple of pagesize for munmap */
		if (MAP_FAILED == (mmp = mmap(0, len + SMBIOS_ENTRY_POINT_SIZE, PROT_READ, MAP_SHARED, fd, fp - len)))
			goto clean;

		memcpy(membuf, mmp + len, sizeof(membuf));
		munmap(mmp, len + SMBIOS_ENTRY_POINT_SIZE);

		if (0 == strncmp(membuf, "_DMI_", 5)) /* entry point found */
		{
			smbios_len = membuf[7] << 8 | membuf[6];
			smbios = membuf[11] << 24 | membuf[10] << 16 | membuf[9] << 8 | membuf[8];
			break;
		}
	}

	if (0 == smbios)
		goto clean;

	smbuf = zbx_malloc(smbuf, smbios_len);

	len = smbios % getpagesize(); /* mmp needs to be a multiple of pagesize for munmap */
	if (MAP_FAILED == (mmp = mmap(0, len + smbios_len, PROT_READ, MAP_SHARED, fd, smbios - len)))
		goto free_smbuf;

	memcpy(smbuf, mmp + len, smbios_len);
	munmap(mmp, len + smbios_len);

	data = smbuf;
	while (data + DMI_HEADER_SIZE <= smbuf + smbios_len)
	{
		if (1 == data[0]) /* system information */
		{
			if (0 != (flags & DMI_GET_VENDOR))
				ret = set_dmi_string(buf, bufsize, data, data[4]);
			else if (0 != (flags & DMI_GET_MODEL))
				ret = set_dmi_string(buf, bufsize, data, data[5]);
			else if (0 != (flags & DMI_GET_SERIAL))
				ret = set_dmi_string(buf, bufsize, data, data[7]);

			if (SYSINFO_RET_OK == ret)
				break;
		}
		else if (3 == data[0] && 0 != (flags & DMI_GET_CHASSIS)) /* chassis */
		{
			if (SYSINFO_RET_OK == (ret = set_chassis_type(buf, bufsize, data[5])))
				break;
		}

		data += data[1]; /* skip the main data */
		while (data[0] || data[1]) /* string data ends with two nulls */
		{
			data++;
		}
		data += 2;
	}
free_smbuf:
	zbx_free(smbuf);
clean:
	close(fd);
	return ret;
}

int	HOST_DEVICE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[MAX_STRING_LEN], buf[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;

	if (1 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return ret;

	if (0 == strcmp(tmp, "vendor"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_VENDOR);
	else if (0 == strcmp(tmp, "chassis"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_CHASSIS);
	else if (0 == strcmp(tmp, "model"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_MODEL);
	else if (0 == strcmp(tmp, "serial"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_SERIAL);

	if (SYSINFO_RET_OK == ret)
		SET_STR_RESULT(result, strdup(buf));

	return ret;
}

int	HOST_LSPCI(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		offset = 0;
	char		buffer[MAX_BUFFER_LEN];
//TODO: list PCI devices

	zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "TODO: list PCI devices");
	SET_TEXT_RESULT(result, strdup(buffer));
	return SYSINFO_RET_OK;
}

int     HOST_MACS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		offset = 0, s, i;
	char		buf[1024], buffer[MAX_BUFFER_LEN];
	struct ifreq	ifr, *IFR;
	struct ifconf	ifc;

	if (-1 == (s = socket(AF_INET, SOCK_DGRAM, 0)))
		return SYSINFO_RET_FAIL;

	ifc.ifc_len = sizeof(buf);
	ifc.ifc_buf = buf;
	ioctl(s, SIOCGIFCONF, &ifc);
	IFR = ifc.ifc_req;

	for (i = ifc.ifc_len / sizeof(struct ifreq); --i >= 0; IFR++) {
		strcpy(ifr.ifr_name, IFR->ifr_name);
		if (0 == ioctl(s, SIOCGIFFLAGS, &ifr))
			if (0 == (ifr.ifr_flags & IFF_LOOPBACK))
				if (0 == ioctl(s, SIOCGIFHWADDR, &ifr))
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%.2x:%.2x:%.2x:%.2x:%.2x:%.2x, ",
						(unsigned char)ifr.ifr_hwaddr.sa_data[0],
						(unsigned char)ifr.ifr_hwaddr.sa_data[1],
						(unsigned char)ifr.ifr_hwaddr.sa_data[2],
						(unsigned char)ifr.ifr_hwaddr.sa_data[3],
						(unsigned char)ifr.ifr_hwaddr.sa_data[4],
						(unsigned char)ifr.ifr_hwaddr.sa_data[5]);
	}

	close(s);

	if (0 < offset)
		buffer[offset - 2] = '\0';
	SET_TEXT_RESULT(result, strdup(buffer));

	return SYSINFO_RET_OK;
}

int     HOST_NAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	tmp[MAX_STRING_LEN];

	if (0 == gethostname(tmp, sizeof(tmp)))
	{
		SET_STR_RESULT(result, strdup(tmp));
		ret = SYSINFO_RET_OK;
	}

	return ret;
}

int     HOST_OS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	type[MAX_STRING_LEN], line[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;
	FILE	*f = NULL;

	if (1 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, type, sizeof(type)))
		*type = '\0';

	if (0 == strcmp(type, "short"))
		f = fopen(HOST_OS_SHORT, "r");
	else if (0 == strcmp(type, "full"))
		f = fopen(HOST_OS_FULL, "r");
	else /* default */
		f = fopen(HOST_OS_NAME, "r");

	if (NULL == f)
		return ret;

	if (NULL != fgets(line, sizeof(line), f))
	{
		zbx_rtrim(line, " \r\n");
		SET_STR_RESULT(result, strdup(line));
		ret = SYSINFO_RET_OK;
	}
	zbx_fclose(f);

	return ret;
}

int     HOST_PACKAGES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		offset = 0;
	char		line[MAX_STRING_LEN], package[MAX_STRING_LEN], status[MAX_STRING_LEN], buffer[MAX_BUFFER_LEN];
	FILE		*f;

	if (NULL == (f = fopen(DPKG_STATUS_FILE, "r")))
		return SYSINFO_RET_FAIL;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (1 != sscanf(line, "Package: %s", package))
			continue;
next_line:	/* find "Status:" line, might not be the next one */
		if (NULL == fgets(line, sizeof(line), f))
			break;
		if (1 != sscanf(line, "Status: %[^\n]", status))
			goto next_line;

		if (0 == strcmp(status, "install ok installed"))
			offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s, ", package);
	}

	zbx_fclose(f);

	if (0 < offset)
		buffer[offset - 2] = '\0';

	SET_TEXT_RESULT(result, strdup(buffer));
	return SYSINFO_RET_OK;
}
