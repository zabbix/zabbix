#include "config.h"

#ifdef HAVE_STDIO_H
	#include <stdio.h>
#endif
#ifdef HAVE_STDLIB_H
	#include <stdlib.h>
#endif
#ifdef HAVE_UNISTD_H
	#include <unistd.h>
#endif
#ifdef HAVE_SYS_STAT_H
	#include <sys/stat.h>
#endif
#ifdef HAVE_SYS_TYPES_H
	#include <sys/types.h>
#endif
/* Linux */
#ifdef HAVE_SYS_VFS_H
	#include <sys/vfs.h>
#endif
#ifdef HAVE_SYS_SYSINFO_H
	#include <sys/sysinfo.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_STATVFS_H
	#include <sys/statvfs.h>
#endif
#ifdef HAVE_SYS_LOADAVG_H
	#include <sys/loadavg.h>
#endif
#ifdef HAVE_SYS_SOCKET_H
	#include <sys/socket.h>
#endif
#ifdef HAVE_NETINET_IN_H
	#include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_INET_H
	#include <arpa/inet.h>
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

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

#include <string.h>

#include "common.h"
#include "sysinfo.h"


COMMAND	commands[]=
	{
	{"memory[total]"		,TOTALMEM, 0},
	{"memory[shared]"		,SHAREDMEM, 0},
	{"memory[buffers]"		,BUFFERSMEM, 0},
	{"memory[cached]"		,CACHEDMEM, 0},
	{"memory[free]"			,FREEMEM, 0},

	{"diskfree[/]"			,DF, "/"},
	{"diskfree[/opt]"		,DF, "/opt"},
	{"diskfree[/tmp]"		,DF, "/tmp"},
	{"diskfree[/usr]"		,DF, "/usr"},
	{"diskfree[/home]"		,DF, "/home"},
	{"diskfree[/var]"		,DF, "/var"},

	{"inodefree[/]"			,INODE, "/"},
	{"inodefree[/opt]"		,INODE, "/opt"},
	{"inodefree[/tmp]"		,INODE, "/tmp"},
	{"inodefree[/usr]"		,INODE, "/usr"},
	{"inodefree[/home]"		,INODE, "/home"},
	{"inodefree[/var]"		,INODE, "/var"},

	{"cksum[/etc/inetd_conf]"	,EXECUTE, "(cksum /etc/inetd.conf 2>/dev/null || echo '-2') | cut -f1 -d' '"},
	{"cksum[/etc/services]"		,EXECUTE, "(cksum /etc/services 2>/dev/null || echo '-2') | cut -f1 -d' '"},
	{"cksum[/vmlinuz]"		,EXECUTE, "(cksum /vmlinuz 2>/dev/null || echo '-2') | cut -f1 -d' '"},
	{"cksum[/etc/passwd]"		,EXECUTE, "(cksum /etc/passwd 2>/dev/null || echo '-2') | cut -f1 -d' '"},
	{"cksum[/usr/sbin/sshd]"	,EXECUTE, "(cksum /usr/sbin/sshd 2>/dev/null || echo '-2') | cut -f1 -d' '"},
	{"cksum[/usr/bin/ssh]"		,EXECUTE, "(cksum /usr/bin/ssh 2>/dev/null || echo '-2') | cut -f1 -d' '"},

	{"filesize[/var/log/syslog]"	,FILESIZE, "/var/log/syslog"},

	{"swap[free]"			,SWAPFREE, 0},
	{"swap[total]"			,SWAPTOTAL, 0},

/****************************************
  	All these perameters require more than 1 second to retrieve.

  	{"swap[in]"			,EXECUTE, "vmstat -n 1 2|tail -1|cut -b37-40"},
	{"swap[out]"			,EXECUTE, "vmstat -n 1 2|tail -1|cut -b41-44"},

	{"system[interrupts]"		,EXECUTE, "vmstat -n 1 2|tail -1|cut -b57-61"},
	{"system[switches]"		,EXECUTE, "vmstat -n 1 2|tail -1|cut -b62-67"},
***************************************/

	{"io[disk_io]"			,DISK_IO,  0},
	{"io[disk_rio]"			,DISK_RIO, 0},
	{"io[disk_wio]"			,DISK_WIO, 0},
	{"io[disk_rblk]"		,DISK_RBLK, 0},
	{"io[disk_wblk]"		,DISK_WBLK, 0},

	{"system[procload]"		,PROCLOAD, 0},
	{"system[procload5]"		,PROCLOAD5, 0},
	{"system[procload15]"		,PROCLOAD15, 0},
	{"system[proccount]"		,PROCCOUNT, 0},
	{"system[procrunning]"		,EXECUTE, "cat /proc/loadavg|cut -f1 -d'/'|cut -f4 -d' '"},
	{"system[uptime]"		,UPTIME, 0},
	{"system[users]"		,EXECUTE, "who|wc -l"},

	{"ping"				,PING, 0},
	{"tcp_count"			,EXECUTE, "netstat -tn|grep EST|wc -l"},

	{"net[listen_23]"		,TCP_LISTEN, "0017"},
	{"net[listen_80]"		,TCP_LISTEN, "0050"},

	{"check_service[ssh]"		,CHECK_SERVICE_SSH, 0},
	{"check_service[smtp]"		,CHECK_SERVICE_SMTP, 0},
	{"check_service[ftp]"		,CHECK_SERVICE_FTP, 0},
	{"check_service[pop]"		,CHECK_SERVICE_POP, 0},
	{"check_service[nntp]"		,CHECK_SERVICE_NNTP, 0},
	{"check_service[imap]"		,CHECK_SERVICE_IMAP, 0},
	{0				,0}
	};

void	test_parameters(void)
{
	int	i;
	float	result;
	float	(*function)();
	char	*parameter = NULL;
	char	*key = NULL;

	i=0;
	while(0 != commands[i].function)
	{
		key=commands[i].key;
		function=commands[i].function;
		parameter=commands[i].parameter;

		result = function(parameter);
		if( result == FAIL )
		{
			printf("\tUNSUPPORTED Key: %s\n",key);
		}
		else
		{
			printf("SUPPORTED Key: %s [%f]\n",key,result);
		}

		i++;
	}
}

float	process(char *command)
{
	char	*p;
	float	result;
	int	i;
	float	(*function)();
	char	*parameter = NULL;

	for( p=command+strlen(command)-1; p>command && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;
	
	i=0;
	for(;;)
	{
		if( commands[i].key == 0)
		{
			function=0;
			break;
		}
		if( strcmp(commands[i].key,command) == 0)
		{
			function=commands[i].function;
			parameter=commands[i].parameter;
			break;
		}	
		i++;
	}

	if( function !=0 )
	{
		result = function(parameter);
		if( result == FAIL )
		{
			result = NOTSUPPORTED;
		}
	}
	else
	{
		result=NOTSUPPORTED;
	}

	return	result;
}

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
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  FAIL;
	}

	return  s.f_favail;
#else
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
#endif
}

float	DF(const char * mountPoint)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  FAIL;
	}

	return  s.f_bsize*s.f_bavail;
#else
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
#endif
}

float	TCP_LISTEN(const char *porthex)
{
#ifdef HAVE_PROC
	FILE	*f;
	char	c[1024];

	char	pattern[1024]="0050 00000000:0000 0A";

	strcpy(pattern,porthex);
	strcat(pattern," 00000000:0000 0A");

	f=fopen("/proc/net/tcp","r");
	if(NULL == f)
	{
		return	FAIL;
	}

	while (NULL!=fgets(c,1024,f))
	{
		if(NULL != strstr(c,pattern))
		{
			fclose(f);
			return 1;
		}
	}
	fclose(f);

	return	0;
#else
	return	FAIL;
#endif
}

#ifdef	HAVE_PROC
float	getPROC(char *file,int lineno,int fieldno)
{
	FILE	*f;
	char	*t;
	char	c[1024];
	float	result;
	int	i;

	f=fopen(file,"r");
	if(NULL == f)
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
	}
	fclose(f);

	sscanf(t, "%f", &result );

	return	result;
}
#endif

float	CACHEDMEM(void)
{
#ifdef HAVE_PROC
	return getPROC("/proc/meminfo",8,2);
#else
	return FAIL;
#endif
}

float	BUFFERSMEM(void)
{
#ifdef HAVE_SYSINFO_BUFFERRAM
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	(float)info.bufferram;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
}

float	SHAREDMEM(void)
{
#ifdef HAVE_SYSINFO_SHAREDRAM
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	(float)info.sharedram;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
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
#ifdef HAVE_SYSINFO_TOTALRAM
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	(float)info.totalram;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
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
#ifdef HAVE_SYSINFO_FREERAM
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	(float)info.freeram;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
#endif
}

float	UPTIME(void)
{
#ifdef HAVE_SYSINFO_UPTIME
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	(float)info.uptime;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
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
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return FAIL;
	}
	else
	{
		return dyn.psd_avg_1_min;
	}
#else
#ifdef HAVE_PROC
	return	getPROC("/proc/loadavg",1,1);
#else
	return	FAIL;
#endif
#endif
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
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return FAIL;
	}
	else
	{
		return dyn.psd_avg_5_min;
	}
#else
#ifdef	HAVE_PROC
	return	getPROC("/proc/loadavg",1,2);
#else
	return	FAIL;
#endif
#endif
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
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return FAIL;
	}
	else
	{
		return dyn.psd_avg_5_min;
	}
#else
#ifdef	HAVE_PROC
	return	getPROC("/proc/loadavg",1,3);
#else
	return	FAIL;
#endif
#endif
#endif
}

float	SWAPFREE(void)
{
#ifdef HAVE_SYSINFO_FREESWAP
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	(float)info.freeswap;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
}

float	PROCCOUNT(void)
{
#ifdef HAVE_SYSINFO_PROCS
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	info.procs;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
}

float	SWAPTOTAL(void)
{
#ifdef HAVE_SYSINFO_TOTALSWAP
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		return	(float)info.totalswap;
	}
	else
	{
		return FAIL;
	}
#else
	return	FAIL;
#endif
}

float	DISK_IO(void)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",2,2);
#else
	return	FAIL;
#endif
}

float	DISK_RIO(void)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",3,2);
#else
	return	FAIL;
#endif
}

float	DISK_WIO(void)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",4,2);
#else
	return	FAIL;
#endif
}

float	DISK_RBLK(void)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",5,2);
#else
	return	FAIL;
#endif
}

float	DISK_WBLK(void)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",6,2);
#else
	return	FAIL;
#endif
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

	if(pclose(f) != 0)
	{
		return	FAIL;	
	}

	sscanf(c, "%f", &result );

	return	result;
}

float	tcp_expect(char	*hostname, short port, char *expect,char *sendtoclose)
{
	char	*haddr;
	char	c[1024];
	
	int	s;
	struct	sockaddr_in addr;
	int	addrlen;


	struct hostent *host;

	host = gethostbyname(hostname);
	if(host == NULL)
	{
		return	0;
	}

	haddr=host->h_addr;


	addrlen = sizeof(addr);
	memset(&addr, 0, addrlen);
	addr.sin_port = htons(port);
	addr.sin_family = AF_INET;
	bcopy(haddr, (void *) &addr.sin_addr.s_addr, 4);

	s = socket(AF_INET, SOCK_STREAM, 0);
	if (s == -1)
	{
		close(s);
		return	0;
	}

	if (connect(s, (struct sockaddr *) &addr, addrlen) == -1)
	{
		close(s);
		return	0;
	}

	memset(&c, 0, 1024);
	recv(s, c, 1024, 0);
	if (strncmp(c, expect, strlen(expect)) == 0)
	{
		send(s,sendtoclose,strlen(sendtoclose),0);
		close(s);
		return	1;
	}
	else
	{
		send(s,sendtoclose,strlen(sendtoclose),0);
		close(s);
		return	0;
	}
}

float	CHECK_SERVICE_SSH(void)
{
	return	tcp_expect("127.0.0.1",22,"SSH","0\n");
}

float	CHECK_SERVICE_SMTP(void)
{
	return	tcp_expect("127.0.0.1",25,"220","");
}

float	CHECK_SERVICE_FTP(void)
{
	return	tcp_expect("127.0.0.1",21,"220","");
}

float	CHECK_SERVICE_POP(void)
{
	return	tcp_expect("127.0.0.1",110,"+OK","");
}

float	CHECK_SERVICE_NNTP(void)
{
	return	tcp_expect("127.0.0.1",119,"220","");
}

float	CHECK_SERVICE_IMAP(void)
{
	return	tcp_expect("127.0.0.1",143,"* OK","a1 LOGOUT\n");
}
