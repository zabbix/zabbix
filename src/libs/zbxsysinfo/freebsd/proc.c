/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2005 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include "common.h"
#include "sysinfo.h"

#include <sys/sysctl.h>

#define DO_SUM 0
#define DO_MAX 1
#define DO_MIN 2
#define DO_AVG 3

#define ZBX_PROC_STAT_ALL 0
#define ZBX_PROC_STAT_RUN 1
#define ZBX_PROC_STAT_SLEEP 2
#define ZBX_PROC_STAT_ZOMB 3

static kvm_t	*kd = NULL;

static char	*proc_argv(struct kinfo_proc *proc)
{
	static struct pargs	pa;
	size_t			sz, s;
	static char		*argv = NULL;
	static size_t		argv_alloc = 0;

	sz = sizeof(pa);
	if (sz == kvm_read(kd, (unsigned long)proc->ki_args, &pa, sz)) {
		if (NULL == argv || argv_alloc < pa.ar_length) {
			argv_alloc = pa.ar_length;
			if (NULL == argv)
				argv = zbx_malloc(argv, argv_alloc);
			else
				argv = zbx_realloc(argv, argv_alloc);
		}

		sz = pa.ar_length;
		if (sz == kvm_read(kd, (unsigned long)proc->ki_args + sizeof(pa.ar_ref) + sizeof(pa.ar_length), argv, sz)) {
			for (s = 0; s < sz - 1; s++)
				if (argv[s] == '\0')
					argv[s] = ' ';
			return argv;
		}
	}

	return NULL;
}

int     PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	procname[MAX_STRING_LEN],
		buffer[MAX_STRING_LEN],
		proccomm[MAX_STRING_LEN],
		*args;
	int	do_task, count, i,
		proc_ok, comm_ok,
		op, arg;

	double	value = 0.0,
		memsize = 0;
	int	proccount = 0;

	size_t	sz;

	struct kinfo_proc	*proc, *pproc;
	struct passwd		*usrinfo;

	assert(result);

	init_result(result);

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';
	else if (strlen(procname) > MAXCOMLEN)
		procname[MAXCOMLEN] = '\0';

	if (0 != get_param(param, 2, buffer, sizeof(buffer)))
		*buffer = '\0';

	if (*buffer != '\0') {
		usrinfo = getpwnam(buffer);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	} else
		usrinfo = NULL;

	if (0 != get_param(param, 3, buffer, sizeof(buffer)))
		*buffer = '\0';

	if (*buffer != '\0') {
		if (0 == strcmp(buffer, "avg"))
			do_task = DO_AVG;
		else if (0 == strcmp(buffer, "max"))
			do_task = DO_MAX;
		else if (0 == strcmp(buffer, "min"))
			do_task = DO_MIN;
		else if (0 == strcmp(buffer, "sum"))
			do_task = DO_SUM;
		else
			return SYSINFO_RET_FAIL;
	} else
		do_task = DO_SUM;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

	if (NULL == kd && NULL == (kd = kvm_open(NULL, NULL, NULL, O_RDONLY, NULL)))
		return SYSINFO_RET_FAIL;

	if (NULL != usrinfo) {
		op = KERN_PROC_UID;
		arg = (int)usrinfo->pw_uid;
	} else {
		op = KERN_PROC_ALL;
		arg = 0;
	}

    	if (NULL == (proc = kvm_getprocs(kd, op, arg, &count)))
		return SYSINFO_RET_FAIL;

	for (pproc = proc, i = 0; i < count; pproc++, i++) {
		proc_ok = 0;
		comm_ok = 0;

		if (*procname == '\0' || 0 == strcmp(procname, pproc->ki_comm))
			proc_ok = 1;

		if (*proccomm != '\0') {
			if (NULL != (args = proc_argv(pproc))) {
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
			}
		} else
			comm_ok = 1;

		if (proc_ok && comm_ok) {
			value = pproc->ki_size;

			if (0 == proccount++)
				memsize = value;
			else {
				if (do_task == DO_MAX)
					memsize = MAX(memsize, value);
				else if (do_task == DO_MIN)
					memsize = MIN(memsize, value);
				else
					memsize += value;
			}
		}
	}
	zbx_free(proc);

	if (do_task == DO_AVG) {
		SET_DBL_RESULT(result, proccount == 0 ? 0 : memsize/proccount);
	} else {
		SET_UI64_RESULT(result, memsize);
	}

	return SYSINFO_RET_OK;
}

int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	procname[MAX_STRING_LEN],
		buffer[MAX_STRING_LEN],
		proccomm[MAX_STRING_LEN],
		*args;
	int	zbx_proc_stat, count, i,
		proc_ok, stat_ok, comm_ok,
		op, arg;

	int	proccount = 0;

	size_t	sz;

	struct kinfo_proc	*proc, *pproc;
	struct passwd		*usrinfo;

	assert(result);

	init_result(result);

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';
	else if (strlen(procname) > MAXCOMLEN)
		procname[MAXCOMLEN] = '\0';

	if (0 != get_param(param, 2, buffer, sizeof(buffer)))
		*buffer = '\0';

	if (*buffer != '\0') {
		usrinfo = getpwnam(buffer);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	} else
		usrinfo = NULL;
    
	if (0 != get_param(param, 3, buffer, sizeof(buffer)))
		*buffer = '\0';
		
	if (*buffer != '\0') {
		if (0 == strcmp(buffer, "run"))
			zbx_proc_stat = ZBX_PROC_STAT_RUN;
		else if (0 == strcmp(buffer, "sleep"))
			zbx_proc_stat = ZBX_PROC_STAT_SLEEP;
		else if (0 == strcmp(buffer, "zomb"))
			zbx_proc_stat = ZBX_PROC_STAT_ZOMB;
		else if (0 == strcmp(buffer, "all"))
			zbx_proc_stat = ZBX_PROC_STAT_ALL;
		else
			return SYSINFO_RET_FAIL;
	} else
		zbx_proc_stat = ZBX_PROC_STAT_ALL;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

	if (NULL == kd && NULL == (kd = kvm_open(NULL, NULL, NULL, O_RDONLY, "kvm_open")))
		return SYSINFO_RET_FAIL;

	if (NULL != usrinfo) {
		op = KERN_PROC_UID;
		arg = (int)usrinfo->pw_uid;
	} else {
		op = KERN_PROC_ALL;
		arg = 0;
	}

    	if (NULL == (proc = kvm_getprocs(kd, op, arg, &count)))
		return SYSINFO_RET_FAIL;

	for (pproc = proc, i = 0; i < count; pproc++, i++) {
		proc_ok = 0;
		stat_ok = 0;
		comm_ok = 0;

		if (*procname == '\0' || 0 == strcmp(procname, pproc->ki_comm))
			proc_ok = 1;

		if (zbx_proc_stat != ZBX_PROC_STAT_ALL) {
			switch (zbx_proc_stat) {
			case ZBX_PROC_STAT_RUN:
				if (pproc->ki_stat == SRUN)
					stat_ok = 1;
				break;
			case ZBX_PROC_STAT_SLEEP:
				if (pproc->ki_stat == SSLEEP)
					stat_ok = 1;
				break;
			case ZBX_PROC_STAT_ZOMB:
				if (pproc->ki_stat == SZOMB)
					stat_ok = 1;
				break;
			}
		} else
			stat_ok = 1;

		if (*proccomm != '\0') {
			if (NULL != (args = proc_argv(pproc))) {
				if (zbx_regexp_match(args, proccomm, NULL) != NULL)
					comm_ok = 1;
			}
		} else
			comm_ok = 1;
		
		if (proc_ok && stat_ok && comm_ok)
			proccount++;
	}
	zbx_free(proc);

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
