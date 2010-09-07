ALTER TABLE graph_theme MODIFY graphthemeid DEFAULT NULL;
ALTER TABLE graph_theme MODIFY noneworktimecolor DEFAULT 'CCCCCC';
ALTER TABLE graph_theme RENAME COLUMN noneworktimecolor TO nonworktimecolor;
