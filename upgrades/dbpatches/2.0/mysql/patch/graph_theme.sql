ALTER TABLE graph_theme MODIFY graphthemeid bigint unsigned NOT NULL,
			CHANGE noneworktimecolor nonworktimecolor varchar(6) DEFAULT 'CCCCCC' NOT NULL;

UPDATE graph_theme SET theme = 'darkblue' WHERE theme = 'css_bb.css';
UPDATE graph_theme SET theme = 'originalblue' WHERE theme = 'css_ob.css';
