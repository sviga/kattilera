DROP TABLE IF EXISTS `%PREFIX%_stat_uri`;
CREATE TABLE `%PREFIX%_stat_uri` (
  `IDUri` bigint(20) unsigned NOT NULL auto_increment,
  `uri` varchar(255) NOT NULL default '',
  `tstc` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`IDUri`),
  KEY `tstc` (`tstc`),
  KEY `uri_ts` (`uri`,`tstc`)
) ENGINE=MyISAM AUTO_INCREMENT=854 DEFAULT CHARSET=utf8 COMMENT='Статистика по uri';
