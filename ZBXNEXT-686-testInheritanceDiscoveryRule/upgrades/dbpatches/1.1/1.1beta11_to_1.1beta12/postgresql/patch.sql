alter table media_type add	gsm_modem	varchar(255)	DEFAULT '' NOT NULL;

alter table actions drop delay;
alter table actions drop nextcheck;

--
-- Table structure for table 'history_text'
--

CREATE TABLE history_text (
  itemid                int4    DEFAULT '0' NOT NULL,
  clock                 int4    DEFAULT '0' NOT NULL,
  value                 text    DEFAULT '' NOT NULL,
  KEY itemidclock (itemid, clock)
);

CREATE INDEX history_text_i_c on history_text (itemid, clock);
