alter table services modify goodsla float8 default '99.9' not null;
alter table services add sortorder int4 default '0' not null;

alter table graphs_items add drawtype int4 default '0' not null;

alter table actions add scope int4 default '0' not null;
alter table actions add severity int4 default '0' not null;

--
-- Table structure for table 'screens'
--

CREATE TABLE screens (
  screenid		serial,
  name			varchar(255)	DEFAULT 'Screen' NOT NULL,
  cols			int4		DEFAULT '1' NOT NULL,
  rows			int4		DEFAULT '1' NOT NULL,
  PRIMARY KEY  (screenid)
);

--
-- Table structure for table 'screens_items'
--

CREATE TABLE screens_items (
  screenitemid		serial,
  screenid		int4		DEFAULT '0' NOT NULL,
  graphid		int4		DEFAULT '0' NOT NULL,
  width			int4		DEFAULT '320' NOT NULL,
  height		int4		DEFAULT '200' NOT NULL,
  x			int4		DEFAULT '0' NOT NULL,
  y			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY  (screenitemid)
);
