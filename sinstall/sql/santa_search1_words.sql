DROP TABLE IF EXISTS `%PREFIX%_search1_words`;
CREATE TABLE `%PREFIX%_search1_words` (
  `id` int(11) NOT NULL auto_increment,
  `word` varchar(50) character set utf8 collate utf8_general_ci default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `word` (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
