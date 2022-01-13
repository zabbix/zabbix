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

#include "common.h"

#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)

#include "zbxkstat.h"
#include "mutexs.h"
#include "log.h"
#include "stats.h"

extern ZBX_COLLECTOR_DATA	*collector;

static kstat_ctl_t	*kc = NULL;
static kid_t		kc_id = 0;
static kstat_t		*kc_vminfo;

static zbx_mutex_t	kstat_lock = ZBX_MUTEX_NULL;

/******************************************************************************
 *                                                                            *
 * Purpose: refreshes kstat environment                                       *
 *                                                                            *
 * Parameters: error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the kstat environment was refreshed successfully   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	zbx_kstat_refresh(char **error)
{
	kid_t	kid;

	if (-1 == (kid = kstat_chain_update(kc)))
	{
		*error = zbx_dsprintf(*error, "failed to update kstat chain: %s", zbx_strerror(errno));
		return FAIL;
	}

	if (0 != kid)
		kc_id = kid;

	if (NULL == (kc_vminfo = kstat_lookup(kc, "unix", -1, "vminfo")))
	{
		*error = zbx_dsprintf(*error, "failed to find vminfo data: %s", zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize kstat environment                                      *
 *                                                                            *
 * Parameters: kstat - [IN] the kstat data storage                            *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the kstat environment was initialized successfully *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_kstat_init(zbx_kstat_t *kstat, char **error)
{
	char	*errmsg = NULL;
	int	ret = FAIL;

	if (NULL == (kc = kstat_open()))
	{
		*error = zbx_dsprintf(*error, "failed to open kstat: %s", zbx_strerror(errno));
		goto out;
	}

	kc_id = kc->kc_chain_id;
	if (SUCCEED != zbx_kstat_refresh(error))
		goto out;

	if (SUCCEED != (ret = zbx_mutex_create(&kstat_lock, ZBX_MUTEX_KSTAT, &errmsg)))
	{
		*error = zbx_dsprintf(*error, "failed to create kstat collector mutex : %s", errmsg);
		zbx_free(errmsg);
	}

	memset(kstat, 0, sizeof(zbx_kstat_t));
out:
	if (SUCCEED != ret && NULL != kc)
	{
		kstat_close(kc);
		kc = NULL;
	}

	return ret;
}

void	zbx_kstat_destroy(void)
{
	if (NULL == kc)
		return;

	kstat_close(kc);
	zbx_mutex_destroy(&kstat_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: collect kstat stats                                               *
 *                                                                            *
 * Comments: This function is called every second to collect statistics.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_kstat_collect(zbx_kstat_t *kstat)
{
	kid_t		kid;
	char		*error = NULL;
	vminfo_t	vminfo;

	while (-1 == (kid = kstat_read(kc, kc_vminfo, &vminfo)) || kc_id != kid)
	{
		if (SUCCEED != zbx_kstat_refresh(&error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot collect kstat data: %s", error);
			zbx_free(error);
			return;
		}
	}

	zbx_mutex_lock(kstat_lock);

	kstat->vminfo_index ^= 1;
	kstat->vminfo[kstat->vminfo_index].freemem = vminfo.freemem;
	kstat->vminfo[kstat->vminfo_index].updates = vminfo.updates;

	zbx_mutex_unlock(kstat_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get free memory size                                              *
 *                                                                            *
 * Parameters: value - [OUT] the free memory size in bytes                    *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the free memory size was stored in value           *
 *               FAIL - either an error occurred (error parameter is set) or  *
 *                      data was not collected yet (error parameter is left   *
 *                      unchanged)                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_kstat_get_freemem(zbx_uint64_t *value, char **error)
{
	int			ret = FAIL, last, prev;
	zbx_kstat_vminfo_t	*vminfo;

	zbx_mutex_lock(kstat_lock);
	if (NULL == collector)
	{
		*error = zbx_strdup(*error, "Collector is not started.");
		goto out;
	}

	last = collector->kstat.vminfo_index;
	prev = last ^ 1;
	vminfo = collector->kstat.vminfo;

	if (0 != vminfo[prev].updates && vminfo[prev].updates < vminfo[last].updates)
	{
		*value = (vminfo[last].freemem - vminfo[prev].freemem) /
				(vminfo[last].updates - vminfo[prev].updates) * sysconf(_SC_PAGESIZE);
		ret = SUCCEED;
	}
out:
	zbx_mutex_unlock(kstat_lock);

	return ret;
}

#endif /*#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)*/
