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

#ifndef ZABBIX_CHECKS_SIMPLE_VMWARE_H
#define ZABBIX_CHECKS_SIMPLE_VMWARE_H

#include "common.h"
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
#include "sysinfo.h"

int	check_vmware_vmlist(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmcpunum(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmmemsize(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmuptime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmmemsizeballooned(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmmemsizecompressed(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmmemsizeswapped(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmstoragecommitted(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmstorageuncommitted(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmstorageunshared(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmpowerstate(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_vmcpuusage(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hostuptime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hostmemoryused(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hostcpuusage(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hostfullname(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hostversion(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwvendor(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwmodel(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwuuid(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwmemory(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwcpumodel(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwcpufreq(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwcpucores(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hosthwcputhreads(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_vmware_hoststatus(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif
#endif
