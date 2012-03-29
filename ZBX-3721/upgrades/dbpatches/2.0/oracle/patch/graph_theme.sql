ALTER TABLE graph_theme MODIFY graphthemeid DEFAULT NULL;
ALTER TABLE graph_theme MODIFY noneworktimecolor DEFAULT 'CCCCCC';
ALTER TABLE graph_theme RENAME COLUMN noneworktimecolor TO nonworktimecolor;

UPDATE graph_theme SET theme = 'darkblue' WHERE theme = 'css_bb.css';
UPDATE graph_theme SET theme = 'originalblue' WHERE theme = 'css_ob.css';
