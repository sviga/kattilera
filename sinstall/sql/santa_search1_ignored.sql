DROP TABLE IF EXISTS `%PREFIX%_search1_ignored`;
CREATE TABLE `%PREFIX%_search1_ignored` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `word` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `word` (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
