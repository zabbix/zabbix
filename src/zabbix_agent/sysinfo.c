#include "config.h"

#include <stdio.h>
#include <stdlib.h>
/* #include <mntent.h> */
#include <sys/stat.h>
/* Linux */
#ifdef HAVE_SYS_VFS_H
	#include <sys/vfs.h>
#endif
/* OpenBSD */
#ifdef HAVE_SYS_PARAM_H
	#include <sys/param.h>
#endif

#ifdef HAVE_SYS_MOUNT_H
	#include <sys/mount.h>
#endif

/* HP-UX */
#ifdef HAVE_SYS_PSTAT_H
	#include <sys/pstat.h>
#endif

#include <string.h>

#include "common.h"
#include "sysinfo.h"

float   FILESIZE(const char * filename)
{
	struct stat	buf;

	if(stat(filename,&buf) == 0)
	{
		return	buf.st_size;
	}

	return	FAIL;
}

float	INODE(const char * mountPoint)
{
	struct statfs   s;
	long            blocks_used;
	long            blocks_percent_used;

	if ( statfs( (char *)mountPoint, &s) != 0 ) 
	{
		return	FAIL;
	}
        
	if ( s.f_blocks > 0 ) {
		blocks_used = s.f_blocks - s.f_bfree;
		blocks_percent_used = (long)
		(blocks_used * 100.0 / (blocks_used + s.f_bavail) + 0.5);

//		printf(
//		"%7.0f %7.0f  %7.0f  %5ld%%   %s\n"
//		,s.f_blocks * (s.f_bsize / 1024.0)
//		,(s.f_blocks - s.f_bfree)  * (s.f_bsize / 1024.0)
//		,s.f_bavail * (s.f_bsize / 1024.0)
//		,blocks_percent_used
//		,mountPoint);
		return s.f_ffree;

	}

	return	FAIL;
}

float	DF(const char * mountPoint)
{
	struct statfs   s;
	long            blocks_used;
	long            blocks_percent_used;

	if ( statfs( (char *)mountPoint, &s) != 0 )
	{
		return	FAIL;
	}
        
	if ( s.f_blocks > 0 ) {
		blocks_used = s.f_blocks - s.f_bfree;
		blocks_percent_used = (long)
		(blocks_used * 100.0 / (blocks_used + s.f_bavail) + 0.5);

//		printf(
//		"%7.0f %7.0f  %7.0f  %5ld%%   %s\n"
//		,s.f_blocks * (s.f_bsize / 1024.0)
//		,(s.f_blocks - s.f_bfree)  * (s.f_bsize / 1024.0)
//		,s.f_bavail * (s.f_bsize / 1024.0)
//		,blocks_percent_used
//		,mountPoint);
		return s.f_bavail * (s.f_bsize / 1024.0);

	}

	return	FAIL;
}

float	getPROC(char *file,int lineno,int fieldno)
{
	FILE	*f;
	char	*t;
	char	c[1024];
	float	result;
	int	i;

	f=fopen(file,"r");
	if( f==NULL)
	{
		return	FAIL;
	}
	for(i=1;i<=lineno;i++)
	{	
		fgets(c,1024,f);
	}
	t=(char *)strtok(c," ");
	for(i=2;i<=fieldno;i++)
	{
		t=(char *)strtok(NULL," ");
		printf("%s\n",t);
	}
	fclose(f);

	sscanf(t, "%f", &result );

	return	result;
}

float	CACHEDMEM(void)
{
	return getPROC("/proc/meminfo",8,2);
}

float	BUFFERSMEM(void)
{
	return getPROC("/proc/meminfo",7,2);
}

float	SHAREDMEM(void)
{
	return getPROC("/proc/meminfo",6,2);
}

float	TOTALMEM(void)
{
#ifdef HAVE_SYS_PSTAT_H
	struct	pst_static pst;
	long	page;

	if(pstat_getstatic(&pst, sizeof(pst), (size_t)1, 0) == -1)
	{
		return FAIL;
	}
	else
	{
		/* Get page size */	
		page = pst.page_size;
		/* Total physical memory in bytes */	
		return page*pst.physical_memory;
	}
#else
	return getPROC("/proc/meminfo",4,2);
#endif
}

float	FREEMEM(void)
{
#ifdef HAVE_SYS_PSTAT_H
	struct	pst_static pst;
	struct	pst_dynamic dyn;
	long	page;

	if(pstat_getstatic(&pst, sizeof(pst), (size_t)1, 0) == -1)
	{
		return FAIL;
	}
	else
	{
		/* Get page size */	
		page = pst.page_size;
//		return pst.physical_memory;

		if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
		{
			return FAIL;
		}
		else
		{
//cout<<"total virtual memory allocated is " << dyn.psd_vm << "
//pages, " << dyn.psd_vm * page << " bytes" << endl;
//cout<<"active virtual memory is " << dyn.psd_avm <<" pages, " <<
//dyn.psd_avm * page << " bytes" << endl;
//cout<<"total real memory is " << dyn.psd_rm << " pages, " <<
//dyn.psd_rm * page << " bytes" << endl;
//cout<<"active real memory is " << dyn.psd_arm << " pages, " <<
//dyn.psd_arm * page << " bytes" << endl;
//cout<<"free memory is " << dyn.psd_free << " pages, " <<
		/* Free memory in bytes */	
			return dyn.psd_free * page;
		}
	}
#else
	return getPROC("/proc/meminfo",5,2);
#endif
}

float	UPTIME(void)
{
	return getPROC("/proc/uptime",1,1);
}

float	PING(void)
{
	return	1;
}

float	PROCLOAD(void)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		return load[0];	
	}
	else
	{
		return FAIL;	
	}
#else
	return	getPROC("/proc/loadavg",1,1);
#endif
}

float	PROCLOAD5(void)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		return load[1];	
	}
	else
	{
		return FAIL;	
	}
#else
	return	getPROC("/proc/loadavg",1,2);
#endif
}

float	PROCLOAD15(void)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		return load[2];	
	}
	else
	{
		return FAIL;	
	}
#else
	return	getPROC("/proc/loadavg",1,3);
#endif
}

float	SWAPFREE(void)
{
	return	getPROC("/proc/meminfo",10,2);
}

float	SWAPTOTAL(void)
{
	return	getPROC("/proc/meminfo",9,2);
}

float	EXECUTE(char *command)
{
	FILE	*f;
	float	result;
	char	c[1024];

	f=popen( command,"r");
	if(f==0)
	{
		return	FAIL;
	}
	fgets(c,1024,f);
	pclose(f);

	sscanf(c, "%f", &result );

	return	result;
}
