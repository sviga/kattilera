DROP TABLE IF EXISTS `%PREFIX%_search1_index`;
CREATE TABLE `%PREFIX%_search1_index` (
  `id` int(11) NOT NULL auto_increment,
  `doc_id` int(11) default NULL,
  `word_id` int(11) default NULL,
  `weight` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `doc_id` (`doc_id`,`word_id`),
  KEY `word_id` (`word_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
