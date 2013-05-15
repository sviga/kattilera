DROP TABLE IF EXISTS `%PREFIX%_search1_docs`;
CREATE TABLE `%PREFIX%_search1_docs` (
  `id` int(11) NOT NULL auto_increment,
  `doc` text,
  `doc_hash` char(32) default NULL,
  `contents_hash` char(32) default NULL,
  `format_id` tinyint(4) default NULL,
  `snipped` mediumblob,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `doc_hash` (`doc_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
