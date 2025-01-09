/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxkstat.h"

#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)

#include "zbxsysinfo.h"

#include "stats.h"

#include "zbxmutexs.h"
#include "zbxstr.h"

static kstat_ctl_t	*kc = NULL;
static kid_t		kc_id = 0;
static kstat_t		*kc_vminfo;

static zbx_mutex_t	kstat_lock = ZBX_MUTEX_NULL;

/******************************************************************************
 *                                                                            *
 * Purpose: refreshes kstat environment                                       *
 *                                                                            *
 * Parameters: error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - kstat environment was refreshed successfully       *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	zbx_kstat_refresh(char **error)
{
	int	ret;
	kid_t	kid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (-1 == (kid = kstat_chain_update(kc)))
	{
		*error = zbx_dsprintf(*error, "failed to update kstat chain: %s", zbx_strerror(errno));
		ret = FAIL;
		goto out;
	}

	if (0 != kid)
		kc_id = kid;

	if (NULL == (kc_vminfo = kstat_lookup(kc, "unix", -1, "vminfo")))
	{
		*error = zbx_dsprintf(*error, "failed to find vminfo data: %s", zbx_strerror(errno));
		ret = FAIL;
		goto out;
	}
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes kstat environment                                     *
 *                                                                            *
 * Parameters: kstat - [IN] kstat data storage                                *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - kstat environment was initialized successfully     *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_kstat_init(zbx_kstat_t *kstat, char **error)
{
	char	*errmsg = NULL;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
		if (-1 == kstat_close(kc))
			zabbix_log(LOG_LEVEL_DEBUG, "Failed to close kstat");
		kc = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

void	zbx_kstat_destroy(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == kc)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "kc is NULL");
		goto out;
	}

	if (-1 == kstat_close(kc))
		zabbix_log(LOG_LEVEL_DEBUG, "Failed to close kstat");

	zbx_mutex_destroy(&kstat_lock);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: collects kstat stats                                              *
 *                                                                            *
 * Comments: This function is called every second to collect statistics.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_kstat_collect(zbx_kstat_t *kstat)
{
	kid_t		kid;
	char		*error = NULL;
	vminfo_t	vminfo;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (-1 == (kid = kstat_read(kc, kc_vminfo, &vminfo)) || kc_id != kid)
	{
		if (-1 == kid)
			zabbix_log(LOG_LEVEL_DEBUG, "cannot collect kstat data, kstat_read: %s", ZBX_NULL2STR(error));

		if (SUCCEED != zbx_kstat_refresh(&error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot collect kstat data, kstat_refresh: %s",
					ZBX_NULL2STR(error));
			zbx_free(error);
			goto out;
		}
	}

	zbx_mutex_lock(kstat_lock);

	kstat->vminfo_index ^= 1;
	kstat->vminfo[kstat->vminfo_index].freemem = vminfo.freemem;
	kstat->vminfo[kstat->vminfo_index].updates = time(NULL);

	zbx_mutex_unlock(kstat_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "vm_index: %d, freemem: " ZBX_FS_UI64 ", updates: " ZBX_FS_UI64,
			(int)kstat->vminfo_index, kstat->vminfo[kstat->vminfo_index].freemem,
			kstat->vminfo[kstat->vminfo_index].updates);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets free memory size                                             *
 *                                                                            *
 * Parameters: value - [OUT] free memory size in bytes                        *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - free memory size was stored in value               *
 *               FAIL - Either an error occurred (error parameter is set) or  *
 *                      data was not collected yet (error parameter is left   *
 *                      unchanged).                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_kstat_get_freemem(zbx_uint64_t *value, char **error)
{
	int			sysconf_pagesize, last, prev, ret = FAIL;
	zbx_kstat_vminfo_t	*vminfo;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): collector:%p", __func__, get_collector());

	zbx_mutex_lock(kstat_lock);

	if (NULL == get_collector())
		goto out;

	last = (get_collector())->kstat.vminfo_index;
	prev = last ^ 1;
	vminfo = (get_collector())->kstat.vminfo;

	if (0 != vminfo[prev].updates && vminfo[prev].updates < vminfo[last].updates)
	{
		if (-1 == (sysconf_pagesize = sysconf(_SC_PAGESIZE)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "sysconf(_SC_PAGESIZE) failed, errno is: %s", zbx_strerror(errno));
			goto out;
		}

		*value = (vminfo[last].freemem - vminfo[prev].freemem) /
				(vminfo[last].updates - vminfo[prev].updates) * sysconf_pagesize;
		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "no new vminfo update is available");
out:
	zbx_mutex_unlock(kstat_lock);

	if (NULL == get_collector())
	{
		*error = zbx_strdup(*error, "Collector is not started.");
		zabbix_log(LOG_LEVEL_DEBUG, "Collector is not started");
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "last: %d, prev: %d, vminfo[prev].updates: " ZBX_FS_UI64
				", vminfo[last].updates: " ZBX_FS_UI64, last, prev, vminfo[prev].updates,
				vminfo[last].updates);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

#endif /*#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)*/
