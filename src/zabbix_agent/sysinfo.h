#ifndef MON_SYSINFO_H
#define MON_SYSINFO_H
 
float	INODE(const char * mountPoint);
float	FILESIZE(const char * filename);
float	DF(const char * mountPoint);
float	getPROC(char *file,int lineno,int fieldno);
float	FREEMEM(void);
float	TOTALMEM(void);
float	SHAREDMEM(void);
float	BUFFERSMEM(void);
float	CACHEDMEM(void);
float	PING(void);
float	PROCLOAD(void);
float	PROCLOAD5(void);
float	PROCLOAD15(void);
float	SWAPFREE(void);
float	SWAPTOTAL(void);
float	UPTIME(void);
float	EXECUTE(char *command);

#endif
