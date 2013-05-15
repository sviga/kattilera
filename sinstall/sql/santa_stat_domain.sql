DROP TABLE IF EXISTS `%PREFIX%_stat_domain`;
CREATE TABLE `%PREFIX%_stat_domain` (
  `IDDomain` int(10) unsigned NOT NULL auto_increment,
  `domain` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`IDDomain`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='Статистика по доменам';
