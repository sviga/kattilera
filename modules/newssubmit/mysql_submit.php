<?php

class mysql_submit
{
	var $table_name;   // Имя таблицы с подписчиками
	var $table_submit; // Имя таблицы с группами для рассылки
    var $table_cronletters; // Имя таблицы с письмами для крона v2
	var $pristavka;    // приставка к имени для полуения уникального md5


	function mysql_submit($prefix)
	{
		$this->table_name = $prefix."_people";
		$this->table_submit = $prefix."_group";
        $this->table_cronletters = $prefix."_cronletters";
	}


    /** Добавляет письмо в таблицу для дальнейшей отправки через крон2
     * @param  $fromEmail
     * @param  $fromName
     * @param  $toEmail
     * @param  $toName
     * @param  $subj
     * @param  $body
     * @return void
     */
    function addMail2CronLetters($fromEmail, $fromName, $toEmail, $toName, $subj, $body)
    {
        global $kernel;
		$query = "INSERT INTO ".$this->table_cronletters."
		              (`toname`, `toemail`, `fromname`, `fromemail`, `subj`, `body`)
		          VALUES
					('".mysql_real_escape_string($toName)."', '".mysql_real_escape_string($toEmail)."','".mysql_real_escape_string($fromName)."', '".mysql_real_escape_string($fromEmail)."','".mysql_real_escape_string($subj)."','".mysql_real_escape_string($body)."')";
        $kernel->runSQL($query);
    }


    /** Возвращает письма которые надо отправить крону2
     * @param  $limit
     * @return array
     */
    function getCron2Letters($limit = null)
    {
	    global $kernel;
		$query = "SELECT * FROM ".$this->table_cronletters;
        if (!is_null($limit))
            $query .= " LIMIT ".$limit;
		$result = $kernel->runSQL($query);
		$ret = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$ret[] = $row;
		}
        mysql_free_result($result);
		return $ret;
    }


    /** Удаляет письмо для крон2 из таблицы (после успешной отправки)
     * @param  $id
     * @return void
     */
    function deleteCron2Letter($id)
    {
	    global $kernel;
		$query = "DELETE FROM ".$this->table_cronletters."
				  WHERE id = $id
				  LIMIT 1";
		$kernel->runSQL($query);
    }

	function set_pristavka($str)
	{
		$this->pristavka = $str;
	}

	/**
	 * Удаляет используемые MySql таблицы
	 *
	 */
	function drop_table()
	{
	    global $kernel;

		$query = "DROP TABLE IF EXISTS $this->table_name";
		$kernel->runSQL($query);

		$query = "DROP TABLE IF EXISTS $this->table_submit";
		$kernel->runSQL($query);

		$query = "DROP TABLE IF EXISTS $this->table_cronletters";
		$kernel->runSQL($query);        
	}

	/**
	 * Создаёт используемые MySql таблицы
	 *
	 */
	function create_table()
	{
	    global $kernel;
		$this->drop_table();

		$query ="CREATE TABLE $this->table_name
                (
                        id INT AUTO_INCREMENT NOT NULL,                # Идентификатор
                        name VARCHAR(255) NOT NULL,                    # ФИО
                        mail VARCHAR(255) NOT NULL,                    # Мыло для рассылки
                        submit ENUM('0','1') NOT NULL DEFAULT '1',	   # Участвует в рассылке
                        off ENUM('0','1') NOT NULL DEFAULT '0',	   	   # Исключён из рассылки
                        control VARCHAR(255),						   # Содержит проверочный MD5 от мыла и приставки
                        sub_time DATE NOT NULL,						   # время внесения в базу
                        PRIMARY KEY (id),
                        UNIQUE key (mail),
                        key (submit),
                        key (off)

                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		$kernel->runSQL($query);

		$query ="CREATE TABLE $this->table_submit
                (
                        id INT AUTO_INCREMENT NOT NULL,
                        id_people INT NOT NULL,
                        section VARCHAR(255) NOT NULL,
                        PRIMARY KEY (id),
                        UNIQUE `id_people_section` (`id_people`, `section`),
                        key (id_people),
                        key (section)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		$kernel->runSQL($query);


		$query ="CREATE TABLE $this->table_cronletters
                (
                        id INT AUTO_INCREMENT NOT NULL,
                        toname VARCHAR(255) DEFAULT NULL,
                        toemail VARCHAR(255) NOT NULL,
                        fromname VARCHAR(255) DEFAULT NULL,
                        fromemail VARCHAR(255) NOT NULL,
                        subj VARCHAR(255) NOT NULL,
                        body TEXT NOT NULL,
                        PRIMARY KEY (id)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		$kernel->runSQL($query);

		//Здесь же проверим, если существует таблица newsi
		//и в ней нет колонки, которая отвечает за дату рассылки
        $result = $kernel->runSQL("SHOW TABLES");

        while ($row = mysql_fetch_row($result))
        {
            if (strtolower($row[0]) == strtolower($kernel->pub_prefix_get()."_newsi"))
            {
                //Нашли таблицу с новостями, и надо в неё добавить колонку
                //с датой отправления, если её, колонки опять таки нет
                $result_f = $kernel->runSQL("SHOW COLUMNS FROM `".$kernel->pub_prefix_get()."_newsi`");
                if (mysql_num_rows($result_f) > 0)
                {
                    $exist = false;
                    while ($rowf = mysql_fetch_assoc($result_f))
                    {
                        if (strtolower($rowf['Field']) == "post_date")
                            $exist = true;
                    }

                    //Если колонки нет, то мы её создаём
                    if (!$exist)
                    {
                        $query = "ALTER TABLE `".$kernel->pub_prefix_get()."_newsi` ADD `post_date` DATETIME NULL DEFAULT NULL COMMENT 'Дата расылки'";
                        $kernel->runSQL($query);

                        $query = "ALTER TABLE `".$kernel->pub_prefix_get()."_newsi` ADD INDEX ( `post_date` );";
                        $kernel->runSQL($query);
                    }
                }
            }

        }
	}

	/**
	 * Создаёт новую запись в базе, для подготовки рассылки и формирует письмо на
	 * на подтверждение
	 *
	 * @param string $name Имя
	 * @param string $mail E-mail для рассылки
	 * @param array $news Массив новостей, на которые осуществялется подписка
	 */

	function add_new_user($name, $mail, $news, $activ = '1')
	{
		global $kernel;
		$unicum = md5($this->pristavka.$mail);
		$date = date("Y-m-d");

		$query = "INSERT INTO ".$this->table_name."
		              (`name`, `mail`, `submit`, `control`, `sub_time`)
		          VALUES
					('$name', '$mail','$activ', '$unicum','$date')";
		$kernel->runSQL($query);
		$id_new = mysql_insert_id();

		//Добавим записи о подписанных рубриках
		foreach ($news as $key => $val)
			$this->add_news_group($id_new, $key);

		return $unicum;
	}

	 /**
	 * Добавляет новую группу новостей в рассылку для конкретного юзера
	 *
	 * @param int $id_user
	 * @param string $section_news
	 */
	function add_news_group($id_user, $section_news)
	{
	    global $kernel;
		$query = "INSERT INTO ".$this->table_submit." VALUES
				  (NULL,'$id_user', '$section_news')";
		$kernel->runSQL($query);
	}

	 /**
	 * Удаляет под писку на группу для человека по id
	 *
	 * @param int $id_user
	 */
	function delete_news_group($id_user)
	{
	    global $kernel;
		$query = "DELETE FROM ".$this->table_submit."
				  WHERE id = $id_user
				  LIMIT 1";
		$kernel->runSQL($query);
	}


	/**
	 * Полностью удаляет юзера и всю его подписку
	 *
	 */
	function delet_user($id_user, $code = '')
	{
	    global $kernel;

	    //Если идёт обращение по коду, то нужно сначала
	    //узнать ID, что бы удалить потом и то, на что был
	    //подписан пользователь

	    $query = "DELETE FROM ".$this->table_name."
				  WHERE id = '".$id_user."'";
		$query .= " LIMIT 1";
		$kernel->runSQL($query);

        $query = "DELETE FROM ".$this->table_submit."
                  WHERE id_people = '".$id_user."'";
        $kernel->runSQL($query);
	}



	/**
	 * Возвращает ID строки с юзером по переданному коду
	 *
	 * @param string $code
	 * @return int
	 */
	function veref_user($code)
	{
	    global $kernel;

	    $code = mysql_real_escape_string($code);
		$query = "SELECT id, control FROM ".$this->table_name."
				  WHERE control = '$code'
				  LIMIT 1
				 ";

		$ret = 0;
		$result = $kernel->runSQL($query);
		if ($result)
		{
			$row = mysql_fetch_assoc($result);
			$ret = $row['id'];
		}
		return $ret;
	}

	/**
	 * Возвращает массив с параметрами пользователя.
	 *
	 * @param string $code
	 */
	function get_info_user($code)
	{
		global $kernel;

		$code = mysql_real_escape_string($code);
		$query = "SELECT a.id as user_id, a.name, a.mail, a.sub_time, b.id as id_sec, b.id_people, b.section FROM ".$this->table_name." as a, ".$this->table_submit." as b
				  WHERE a.control = '$code' and a.id = b.id_people
				  GROUP BY b.id
				 ";

		$result = $kernel->runSQL($query);
		$ret = array();
		while ($row = mysql_fetch_assoc($result))
		{
    	    $parts = explode('-', $row['sub_time']);
	       	$date = trim($parts[2]).'.'.trim($parts[1]).'.'.trim($parts[0]);

			$ret['name'] = $row['name'];
			$ret['mail'] = $row['mail'];
			$ret['date'] = $date;
			$ret['id'] = $row['user_id'];
			$ret['section'][$row['section']] = $row['id_sec'];
		}
		return $ret;
	}

	/**
	 * Возвращает массив с параметрами всех пользователей рассылки
	 *
	 */
	function get_all_user($id = null, $page = null, $limit = 25)
	{
		global $kernel;

        $peoples = array();
		if (is_numeric($page)) {
		    $query = 'SELECT `id` FROM '.$this->table_name.' ORDER BY `name` ASC LIMIT '.$limit.' OFFSET '.$page * $limit.'';
		    $result = $kernel->runSQL($query);
			$peoples = array();
			while ($row = mysql_fetch_assoc($result))
			{
                $peoples[] = $row['id'];
			}
		}

		$query = 'SELECT '
        . ' `people`.`id` AS `user_id` '
        . ' , `people`.`name` '
        . ' , `people`.`mail` '
        . ' , `people`.`sub_time` '
        . ' , DATE_FORMAT(`people`.`sub_time`, "%d-%m-%Y") as `date_f` '
        . ' , `people`.`submit` '
        . ' , `people`.`off` '
        . ' , `group`.`id` AS `id_sec` '
        . ' , `group`.`id_people` '
        . ' , `group`.`section` '
        . ' FROM '.$this->table_name.' AS `people` '
        . ' LEFT JOIN ('.$this->table_submit.' AS `group`)  '
        . ' ON ( `group`.`id_people` = `people`.`id` )';

		if (is_numeric($id))
		{
			$query .= ' WHERE `people`.`id` = '.$id;
		}
		elseif (!empty($peoples))
		{
			$query .= ' WHERE `people`.`id` IN ('.implode(',', $peoples).')';
		}

		$query .= ' GROUP BY `people`.`id`, `group`.`id`';

		$result = mysql_query($query) or die("Invalid query: " . mysql_error());
		$ret = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$ret[$row['user_id']]['name'] = $row['name'];
			$ret[$row['user_id']]['mail'] = $row['mail'];
			$ret[$row['user_id']]['date'] = $row['date_f'];
			$ret[$row['user_id']]['activate'] = $row['submit'];
			$ret[$row['user_id']]['off'] = $row['off'];
			//$ret['id'] = $row['user_id'];
			$ret[$row['user_id']]['section'][$row['section']] = $row['id_sec'];
		}
		return $ret;
	}

	function get_num_pages($limit = 25)
	{
		global $kernel;

		$query = 'SELECT COUNT(*) as `total` FROM '.$this->table_name.'';
		$result = $kernel->runSQL($query);
		$total = ceil(mysql_result($result, 0, 'total') / $limit);

		return $total;
	}

	/**
	 * Обновляет информацию о подписчике в таблице подписчиков
	 *
	 * @param int $id
	 * @param array $data
	 */
	function update_user($id, $data, $use_code = false, $code = '')
	{
	    global $kernel;

		if (!is_array($data) || empty($data))
			return false;

		$update_strings = array();
		foreach ($data as $key => $value)
		{
			$value = mysql_real_escape_string($value);
			$update_strings[] = "$key='$value'";
		}
		$update_string = join(", ", $update_strings);


		$query = "UPDATE ".$this->table_name."
				  SET $update_string
				  WHERE id = $id ";

	    if ($use_code)
	       $query .= ' AND control = "'.$code.'" ';

        $query .= "LIMIT 1";
        $result = $kernel->runSQL($query);
        if ($result)
            return true;
        else
            return false;
	}


	/**
	 * Возвращает массив новостей для конкртеной страницы,
	 * которые могут быть разосланы.
	 *
	 * @param string $section
	 */
	function get_news_for_submit($section)
	{
	    if (file_exists('modules/newsi/newsi.class.php'))
		  require_once('modules/newsi/newsi.class.php');

		$news = new newsi();
		return $news->get_news_for_submit($section);

	}

	/**
	 * Возращает полную информацию о новости и делает отмекту
	 * (если не в тестовом режиме) о том, что новость отослана
	 *
	 * @param string $section
	 * @param int $id
	 * @param int $id
	 */
	function get_news($id, $time, $set_submit = true)
	{
	    if (file_exists('modules/newsi/newsi.class.php'))
            require_once('modules/newsi/newsi.class.php');

		$news = new newsi();
		return $news->get_full_info_and_submit($id, $time, $set_submit);

	}

	/**
	 * Возвращает список пользователей, которым необходимо сделать рассылку
	 *
	 */
	function get_users_for_submit($modul_id = '')
	{
		global $kernel;

		$query = "SELECT a.id as user_id, a.name, a.mail, a.submit, a.off, a.control,
						 b.id as id_sec, b.id_people, b.section
				  FROM ".$this->table_name." as a, ".$this->table_submit." as b
				  WHERE a.id = b.id_people and a.off = '0' and a.submit = '1'";
		if (!empty($modul_id))
		  $query .= " and b.section = '".$modul_id."'";

		$query .=" GROUP BY a.id, b.section ";

		$result = mysql_query($query);
		$ret = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$ret[$row['user_id']]['name'] = $row['name'];
			$ret[$row['user_id']]['mail'] = $row['mail'];
			$ret[$row['user_id']]['code'] = $row['control'];
			$ret[$row['user_id']]['section'][$row['id_sec']] = $row['section'];
		}
		return $ret;


	}

	//ключь - id новостной страницы, значение - номер записи в таблице подписки
	//если оно равно 0, значит этой строки ещё нет, её надо добавить

	function isset_email($email)
	{
		$query = "SELECT id, mail FROM ".$this->table_name."
				  WHERE mail = '$email'
				  LIMIT 1
				 ";

		$result = mysql_query($query) or die("Invalid query: " . mysql_error());
		if ((mysql_num_rows($result)) == 0)
			return false;
		else
			return true;
	}


}

?>