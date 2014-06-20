alter table media_type add	gsm_modem	varchar(255)	DEFAULT '' NOT NULL;

alter table actions drop delay;
alter table actions drop nextcheck;

--
-- Table structure for table 'history_text'
--

CREATE TABLE history_text (
  id                    int(4)          NOT NULL auto_increment,
  itemid                int(4)          DEFAULT '0' NOT NULL,
  clock                 int(4)          DEFAULT '0' NOT NULL,
  value                 text            DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  KEY itemidclock (itemid, clock)
) ENGINE=InnoDB;
