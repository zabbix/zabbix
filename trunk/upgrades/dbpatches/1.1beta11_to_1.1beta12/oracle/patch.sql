alter table actions drop delay;
alter table actions drop nextcheck;

--
-- Table structure for table 'history_text'
--

CREATE TABLE history_text (
  itemid                number(10)    DEFAULT '0' NOT NULL,
  clock                 number(10)    DEFAULT '0' NOT NULL,
  value                 BLOB
);

CREATE INDEX history_text_itemidclock on history_text (itemid,clock);
