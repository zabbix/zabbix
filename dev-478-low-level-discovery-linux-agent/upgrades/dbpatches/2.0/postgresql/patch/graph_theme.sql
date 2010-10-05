ALTER TABLE ONLY graph_theme ALTER graphthemeid DROP DEFAULT,
			     ALTER noneworktimecolor SET DEFAULT 'CCCCCC';
ALTER TABLE ONLY graph_theme RENAME noneworktimecolor TO nonworktimecolor;
