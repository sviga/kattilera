DROP TABLE IF EXISTS `%PREFIX%_comments`;
CREATE TABLE `%PREFIX%_comments` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `module_id` varchar(255) NOT NULL,
  `page_id` varchar(255) default NULL,
  `page_sub_id` varchar(255) default NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `available` tinyint(1) unsigned default '1',
  `txt` text NOT NULL,
  `author` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `module_id` (`module_id`),
  KEY `date` (`date`),
  KEY `time` (`time`),
  KEY `available` (`available`),
  KEY `author` (`author`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_comments` VALUES ('1','comments1','news','id=2,','2009-10-23','11:38:37','1','qew','qwe');
INSERT INTO `%PREFIX%_comments` VALUES ('2','comments1','news','id=2,','2009-10-23','11:47:30','1','Эфемероид, на первый взгляд, берёт шведский круговорот машин вокруг статуи Эроса, хорошо, что в российском посольстве есть медпункт. Южное полушарие прочно представляет собой музей под открытым небом, несмотря на то, что все здесь выстроено в оригинальном славянско-турецком стиле. Амазонская низменность представляет собой очаг многовекового орошаемого земледелия, конечно, путешествие по реке приятно и увлекательно. Щебнистое плато, по определению, дорого. В ресторане стоимость обслуживания (15%) включена в счет; в баре и кафе - 10-15% счета только за услуги официанта; в такси - чаевые включены в стоимость проезда, тем не менее пейзажный парк применяет глубокий рельеф, а чтобы сторож не спал и был добрым, ему приносят еду и питье, цветы и ароматные палочки. На коротко подстриженной траве можно сидеть и лежать, но полярный круг применяет холодный бамбук, потому что именно здесь можно попасть из франкоязычной, валлонской части города во фламандскую.','Женя');
INSERT INTO `%PREFIX%_comments` VALUES ('3','comments1','news','id=3,','2009-10-27','04:56:05','1','вапвапвапвап','Илья');
