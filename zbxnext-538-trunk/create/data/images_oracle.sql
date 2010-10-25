-- 
-- Zabbix
-- Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
--

--
-- Dumping data for table images
--

CREATE OR REPLACE DIRECTORY image_dir AS '/home/zabbix/zabbix/create/data/images'
/

CREATE OR REPLACE PROCEDURE LOAD_IMAGE (IMG_ID IN NUMBER, IMG_TYPE IN NUMBER, IMG_NAME IN VARCHAR2, FILE_NAME IN VARCHAR2) 
IS 
	TEMP_BLOB BLOB := EMPTY_BLOB(); 
	BFILE_LOC BFILE; 
BEGIN 
	DBMS_LOB.CREATETEMPORARY(TEMP_BLOB,TRUE,DBMS_LOB.SESSION); 
	BFILE_LOC := BFILENAME('IMAGE_DIR', FILE_NAME); 
	DBMS_LOB.FILEOPEN(BFILE_LOC);
	DBMS_LOB.LOADFROMFILE(TEMP_BLOB, BFILE_LOC, DBMS_LOB.GETLENGTH(BFILE_LOC)); 
	DBMS_LOB.FILECLOSE(BFILE_LOC);
	INSERT INTO IMAGES VALUES (IMG_ID, IMG_TYPE, IMG_NAME, TEMP_BLOB); 
	COMMIT; 
END LOAD_IMAGE;
/

BEGIN
	LOAD_IMAGE(1,1,'Hub'			,'Hub.png');
	LOAD_IMAGE(2,1,'Hub (small)'		,'Hub_small.png');
	LOAD_IMAGE(3,1,'Network'		,'Network.png');
	LOAD_IMAGE(4,1,'Network (small)'	,'Network_small.png');
	LOAD_IMAGE(5,1,'Notebook'		,'Notebook.png');
	LOAD_IMAGE(6,1,'Notebook (small)'	,'Notebook_small.png');
	LOAD_IMAGE(7,1,'Phone'			,'Phone.png');
	LOAD_IMAGE(8,1,'Phone (small)'		,'Phone_small.png');
	LOAD_IMAGE(9,1,'Printer'		,'Printer.png');
	LOAD_IMAGE(10,1,'Printer (small)'	,'Printer_small.png');
	LOAD_IMAGE(11,1,'Router'		,'Router.png');
	LOAD_IMAGE(12,1,'Router (small)'	,'Router_small.png');
	LOAD_IMAGE(13,1,'Satellite'		,'Satellite.png');
	LOAD_IMAGE(14,1,'Satellite (small)'	,'Satellite_small.png');
	LOAD_IMAGE(15,1,'Server'		,'Server.png');
	LOAD_IMAGE(16,1,'Server (small)'	,'Server_small.png');
	LOAD_IMAGE(17,1,'UPS'			,'UPS.png');
	LOAD_IMAGE(18,1,'UPS (small)'		,'UPS_small.png');
	LOAD_IMAGE(19,1,'Workstation'		,'Workstation.png');
	LOAD_IMAGE(20,1,'Workstation (small)'	,'Workstation_small.png');
END;
/

DROP PROCEDURE LOAD_IMAGE;

DROP DIRECTORY image_dir;

