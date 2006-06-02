alter table graphs_items add calc_fnc number(3) DEFAULT '2' NOT NULL;
alter table graphs_items add type number(3) DEFAULT '0' NOT NULL;
alter table graphs_items add periods_cnt number(10) DEFAULT '5' NOT NULL;

DROP INDEX history_text_itemidclock;
DROP TABLE history_text;

--
-- Table structure for table 'history_text'
--

CREATE TABLE history_text (
  itemid                number(10)    DEFAULT '0' NOT NULL,
  clock                 number(10)    DEFAULT '0' NOT NULL,
  value                 CLOB
);

CREATE INDEX history_text_itemidclock on history_text (itemid,clock);

