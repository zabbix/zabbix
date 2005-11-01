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

static int	VM_MEMORY_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	VM_MEMORY_SHARED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	VM_MEMORY_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	VM_MEMORY_BUFFERS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	VM_MEMORY_CACHED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define MEM_FNCLIST struct mem_fnclist_s
MEM_FNCLIST
{
	char *mode;
	int (*function)();
};

	MEM_FNCLIST fl[] = 
	{
		{"free",	VM_MEMORY_FREE},
		{"shared",	VM_MEMORY_SHARED},
		{"total",	VM_MEMORY_TOTAL},
		{"buffers",	VM_MEMORY_BUFFERS},
		{"cached",	VM_MEMORY_CACHED},
		{0,	0}
	};
        char    mode[MAX_STRING_LEN];
	int i;

        assert(result);

        clean_result(result);

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, mode, MAX_STRING_LEN) != 0)
        {
                mode[0] = '\0';
        }

        if(mode[0] == '\0')
	{
		/* default parameter */
		sprintf(mode, "total");
	}
	
	for(i=0; fl[i].mode!=0; i++)
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, param, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
}

static int	VM_MEMORY_CACHED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_PROC
/* Get CACHED memory in bytes */
/*	return getPROC("/proc/meminfo",8,2,msg,mlen_max);*/
/* It does not work for both 2.4 and 2.6 */
/*	return getPROC("/proc/meminfo",2,7,msg,mlen_max);*/
	FILE	*f;
	char	*t;
	char	c[MAX_STRING_LEN];
	double	res = 0;

	assert(result);

        clean_result(result);
		
	f=fopen("/proc/meminfo","r");
	if(NULL == f)
	{
		return	SYSINFO_RET_FAIL;
	}
	while(NULL!=fgets(c,MAX_STRING_LEN,f))
	{
		if(strncmp(c,"Cached:",7) == 0)
		{
			t=(char *)strtok(c," ");
			t=(char *)strtok(NULL," ");
			sscanf(t, "%lf", &res );
			break;
		}
	}
	fclose(f);

	result->type |= AR_DOUBLE;	
	result->dbl=res;
	return SYSINFO_RET_OK;
#else
	assert(result);

        clean_result(result);
		
	return SYSINFO_RET_FAIL;
#endif
}

static int	VM_MEMORY_BUFFERS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_BUFFERRAM
	struct sysinfo info;

	assert(result);

        clean_result(result);
		
	if( 0 == sysinfo(&info))
	{
		result->type |= AR_DOUBLE;	
#ifdef HAVE_SYSINFO_MEM_UNIT
		result->dbl=(double)info.bufferram * (double)info.mem_unit;
#else
		result->dbl=(double)info.bufferram;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
	assert(result);

        clean_result(result);
		
	return	SYSINFO_RET_FAIL;
#endif
}

static int	VM_MEMORY_SHARED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_SHAREDRAM
	struct sysinfo info;

	assert(result);

        clean_result(result);
		
	if( 0 == sysinfo(&info))
	{
		result->type |= AR_DOUBLE;	
#ifdef HAVE_SYSINFO_MEM_UNIT
		result->dbl=(double)info.sharedram * (double)info.mem_unit;
#else
		result->dbl=(double)info.sharedram;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#elif defined(HAVE_SYS_VMMETER_VMTOTAL)
	int mib[2],len;
	struct vmtotal v;

	assert(result);

        clean_result(result);
		
	len=sizeof(struct vmtotal);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	sysctl(mib,2,&v,&len,NULL,0);

	result->type |= AR_DOUBLE;	
	result->dbl=(double)(v.t_armshr<<2);
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
}

static int	VM_MEMORY_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/* Solaris */
#ifdef HAVE_UNISTD_SYSCONF
	assert(result);

        clean_result(result);
		
	result->type |= AR_DOUBLE;	
	result->dbl=(double)sysconf(_SC_PHYS_PAGES)*sysconf(_SC_PAGESIZE);
	return SYSINFO_RET_OK;
#elif defined(HAVE_SYS_PSTAT_H)
	struct	pst_static pst;
	long	page;

	assert(result);

        clean_result(result);
		
	if(pstat_getstatic(&pst, sizeof(pst), (size_t)1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		/* Get page size */	
		page = pst.page_size;
		/* Total physical memory in bytes */	
		result->type |= AR_DOUBLE;	
		result->dbl=(double)page*pst.physical_memory;
		return SYSINFO_RET_OK;
	}
#elif defined(HAVE_SYSINFO_TOTALRAM)
	struct sysinfo info;

	assert(result);

        clean_result(result);
		
	if( 0 == sysinfo(&info))
	{
		result->type |= AR_DOUBLE;	
#ifdef HAVE_SYSINFO_MEM_UNIT
		result->dbl=(double)info.totalram * (double)info.mem_unit;
#else
		result->dbl=(double)info.totalram;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#elif defined(HAVE_SYS_VMMETER_VMTOTAL)
	int mib[2],len;
	struct vmtotal v;

	assert(result);

        clean_result(result);
		
	len=sizeof(struct vmtotal);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	sysctl(mib,2,&v,&len,NULL,0);

	result->type |= AR_DOUBLE;	
	result->dbl=(double)(v.t_rm<<2);
	return SYSINFO_RET_OK;
#elif defined(HAVE_SYS_SYSCTL_H)
	static int mib[] = { CTL_HW, HW_PHYSMEM };
	size_t len;
	unsigned int memory;
	int ret;
	
	assert(result);

        clean_result(result);
		
	len=sizeof(memory);

	if(0==sysctl(mib,2,&memory,&len,NULL,0))
	{
		result->type |= AR_DOUBLE;	
		result->dbl=(double)memory;
		ret=SYSINFO_RET_OK;
	}
	else
	{
		ret=SYSINFO_RET_FAIL;
	}
	return ret;
#else
	assert(result);

        clean_result(result);
		
	return	SYSINFO_RET_FAIL;
#endif
}

static int	VM_MEMORY_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/* Solaris */
#ifdef HAVE_UNISTD_SYSCONF
	assert(result);

        clean_result(result);
		
	result->type |= AR_DOUBLE;	
	result->dbl=(double)sysconf(_SC_AVPHYS_PAGES)*sysconf(_SC_PAGESIZE);
	return SYSINFO_RET_OK;
#elif defined(HAVE_SYS_PSTAT_H)
	struct	pst_static pst;
	struct	pst_dynamic dyn;
	long	page;

	assert(result);

        clean_result(result);
		
	if(pstat_getstatic(&pst, sizeof(pst), (size_t)1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		/* Get page size */	
		page = pst.page_size;
/*		return pst.physical_memory;*/

		if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
		{
			return SYSINFO_RET_FAIL;
		}
		else
		{
/*		cout<<"total virtual memory allocated is " << dyn.psd_vm << "
		pages, " << dyn.psd_vm * page << " bytes" << endl;
		cout<<"active virtual memory is " << dyn.psd_avm <<" pages, " <<
		dyn.psd_avm * page << " bytes" << endl;
		cout<<"total real memory is " << dyn.psd_rm << " pages, " <<
		dyn.psd_rm * page << " bytes" << endl;
		cout<<"active real memory is " << dyn.psd_arm << " pages, " <<
		dyn.psd_arm * page << " bytes" << endl;
		cout<<"free memory is " << dyn.psd_free << " pages, " <<
*/
		/* Free memory in bytes */

			result->type |= AR_DOUBLE;	
			result->dbl=(double)dyn.psd_free * page;
			return SYSINFO_RET_OK;
		}
	}
#elif defined(HAVE_SYSINFO_FREERAM)
	struct sysinfo info;

	assert(result);

        clean_result(result);
		
	if( 0 == sysinfo(&info))
	{
		result->type |= AR_DOUBLE;	
#ifdef HAVE_SYSINFO_MEM_UNIT
		result->dbl=(double)info.freeram * (double)info.mem_unit;
#else
		result->dbl=(double)info.freeram;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#elif defined(HAVE_SYS_VMMETER_VMTOTAL)
	int mib[2],len;
	struct vmtotal v;

	assert(result);

        clean_result(result);
		
	len=sizeof(struct vmtotal);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	sysctl(mib,2,&v,&len,NULL,0);

	result->type |= AR_DOUBLE;	
	result->dbl=(double)(v.t_free<<2);
	return SYSINFO_RET_OK;
/* OS/X */
#elif defined(HAVE_MACH_HOST_INFO_H)
	vm_statistics_data_t page_info;
	vm_size_t pagesize;
	mach_msg_type_number_t count;
	kern_return_t kret;
	int ret;
	
	assert(result);

        clean_result(result);
		
	pagesize = 0;
	kret = host_page_size (mach_host_self(), &pagesize);

	count = HOST_VM_INFO_COUNT;
	kret = host_statistics (mach_host_self(), HOST_VM_INFO,
	(host_info_t)&page_info, &count);
	if (kret == KERN_SUCCESS)
	{
		double pw, pa, pi, pf, pu;

		pw = (double)page_info.wire_count*pagesize;
		pa = (double)page_info.active_count*pagesize;
		pi = (double)page_info.inactive_count*pagesize;
		pf = (double)page_info.free_count*pagesize;

		pu = pw+pa+pi;

		result->type |= AR_DOUBLE;	
		result->dbl=(double)pf;
		ret = SYSINFO_RET_OK;
	}
	else
	{
		ret = SYSINFO_RET_FAIL;
	}
	return ret;
#else
	assert(result);

        clean_result(result);
		
	return	SYSINFO_RET_FAIL;
#endif
}

