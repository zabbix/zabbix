alter table functions alter lastvalue varchar(255);


--
-- Table structure for table 'problems'
--

CREATE TABLE problems (
  problemid		int4		NOT NULL auto_increment,
  userid		int4		DEFAULT '0' NOT NULL,
  triggerid		int4,
  lastupdate		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  status		int1		DEFAULT '0' NOT NULL,
  descripion		varchar(255)	DEFAULT '' NOT NULL,
  categoryid		int4,
  priority		int1		DEFAULT '0' NOT NULL,
  PRIMARY KEY (problemid),
  KEY (status),
  KEY (categoryid),
  KEY (priority)
);

--
-- Table structure for table 'categories'
--

CREATE TABLE categories (
  categoryid		int(4)		NOT NULL auto_increment,
  descripion		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (categoryid)
);

--
-- Table structure for table 'problems_comments'
--

CREATE TABLE problems_comments (
  commentid		int(4)		NOT NULL auto_increment,
  problemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4),
  status_before		int(1)		DEFAULT '0' NOT NULL,
  status_after		int(1)		DEFAULT '0' NOT NULL,
  comment		blob,
  PRIMARY KEY (commentid),
  KEY (problemid,clock)
);
