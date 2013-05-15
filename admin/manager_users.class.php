<?php

 /**
 * Управляет администраторами и пользователями сайта
 * @name manager_users
 * @copyright  ArtProm (с) 2002-2012
 * @version 3.0
 */

class manager_users
{
    const symbol_delimiter = '-';

    public static function get_total_users()
    {
        global $kernel;
        $total=0;
        $crec = $kernel->db_get_record_simple("_user","true","COUNT(*) AS count");
        if ($crec)
            $total=$crec['count'];
        return $total;
    }

    /**
     * Предопределённая функция для формирования меню
     *
     * Функция орпеделяет какие элементы меню присутсвуют в меню раздела
     * @param pub_interface $show Объект класса pub_interface
     * @return void
     */
	function interface_get_menu($show)
    {
        global $kernel;
        //Исключаем этот блок, для не главного админа
        if ($kernel->priv_admin_is_root())
        {
            $show->set_menu_block('[#backof_user_label_layer#]');
            $show->set_menu("[#backof_user_label_menu1#]","control_admins");
            $show->set_menu("[#backof_user_label_menu2#]","control_group");
            $show->set_menu("[#backof_user_label_menu3#]","group_access");
            $show->set_menu_default('control_admins');
        }
        else
            $show->set_menu_default('active_admins');

        $show->set_menu_block('[#backof_user_label_layer2#]');
        $show->set_menu("[#backof_user_label_menu4#]","active_admins");
    }

	/**
	 * Точка входа
     * @return string
	 */
	function start()
    {
    	global $kernel;
    	$html_content = "";
    	$action = $kernel->pub_section_leftmenu_get();
        switch ($action)
        {

            //Выводим таблицу с доступными администраторами сайта
            case "control_admins":
                $html_content = $this->bof_show_form_admin();
                break;

            //Редактирование параметров существующего администратора
            //или создание нового
            case "edit_admin":
                $login = $kernel->pub_httpget_get('login');
                if ($kernel->pub_httpget_get('newadmin') == "yes")
                   $login = '';
                $html_content = $this->bof_admin_edit_and_add($login);
                break;

            //Сохраняем полученную информацию о администраторах
            case "save_admin":
                $html_content = $this->bof_admin_save();
                break;

            //Удаляем администратора
            case 'del_user':
                $id_del = $kernel->pub_httpget_get('deladmin');
                if (!empty($id_del))
                    $this->bof_admin_delete($id_del);
                $kernel->pub_redirect_refresh('control_admins');
                break;

            //===================================================================
            //------------------ Обработка групп адмнистраторов -----------------
            //Выводит список существующих групп
            case 'control_group':
                $html_content = $this->bof_group_show_form();
                break;

            //Вызвает форму редактирования (создания новой группы)
            case 'edit_group':
                $id = $kernel->pub_httpget_get('id');
                if ($kernel->pub_httpget_get('newadmin') == "yes")
                   $id = '';
                $html_content = $this->bof_group_edit_and_add($id);
                break;

            case 'save_group':
                $html_content = $this->bof_group_save();
                //$kernel->pub_redirect_refresh_reload('control_group');
                break;

            case "del_group":
                $id_del = $kernel->pub_httpget_get('delid');
                if (!empty($id_del))
                    $this->bof_group_delete($id_del);
                $kernel->pub_redirect_refresh('control_group');
                break;


            //===================================================================
            //--------------------- Обработка прав на группы --------------------

            case 'group_access':
                //Выводим форму, для выбора доступа к группам
                $html_content = $this->bof_groups_show_form_access();
                break;

            case 'save_access':
                $html_content = $this->bof_groups_save_access();
                //$kernel->pub_redirect_refresh_reload('group_access');
                break;

            //===================================================================
            //------------------ Просмотр адмнистраторов ------------------------
            //Список работающих админов
            case 'active_admins':
                $html_content = $this->bof_show_form_active_admins();
                break;
        }
        return $html_content;
    }

    /**
     * Формирует таблицу администраторов, работающих с сайтом
     *
     * @return string
     *
     */
    private function bof_show_form_active_admins()
    {
        global $kernel;
        $sql = "SELECT
                a.*,
                b.login,
                b.full_name,
                b.id AS user_id
                FROM
                `".$kernel->pub_prefix_get()."_admin_trace` a,
                `".$kernel->pub_prefix_get()."_admin` b
                WHERE
                (
                a.time
                BETWEEN
                DATE_ADD(NOW(), INTERVAL -5 MINUTE)
                AND
                NOW()
                )
                AND b.id=a.id_admin
                ";
        $result = $kernel->runSQL($sql);

        if (mysql_num_rows($result) <=0 )
            return '';

        $template = $kernel->pub_template_parse("admin/templates/default/access_list_active_admins.html");
        $html = $template['main'];

        $lines = "";
        $num = 1;
        while ($data = mysql_fetch_assoc($result))
        {
            //Узнаем в каких группах тек администратор
            $sql = "SELECT
                        a.full_name, a.main_admin
                    FROM `".$kernel->pub_prefix_get()."_admin_group` a, `".$kernel->pub_prefix_get()."_admin_cross_group` b
                    WHERE
                        a.id=b.group_id AND b.user_id='".$data['user_id']."'
                    ";

            $group_result = $kernel->runSQL($sql);
            $main_admin = "";
            $group_array = array();
            while ($group_data = mysql_fetch_assoc($group_result))
            {
                $group_array[] = $group_data['full_name'];
                if ($group_data['main_admin'] == "1")
                    $main_admin = ' checked="checked" ';

            }
            $group_string = implode(", ", $group_array);

            //Начнём выводить
            $str = $template['string'];
            $str = str_replace("[#tr_class#]",  $kernel->pub_table_tr_class($num), $str);
            $str = str_replace("[#login#]",     $data['login'], $str);
            $str = str_replace("[#full_name#]", $data['full_name'], $str);
            $str = str_replace("[#groups#]",    $group_string, $str);
            $str = str_replace("[#place#]",     $data['place'], $str);
            $str = str_replace("[#time#]",      $data['time'], $str);
            $str = str_replace("[#ip#]",        $data['ip'], $str);
            $str = str_replace("[#host#]",      $data['host'], $str);
            $str = str_replace("[#root#]",      $main_admin, $str);

            $lines .= $str;
            $num++;
        }
        $html = str_replace("[#backof_user_table_lines#]", $lines, $html);
        return $html;
    }

	/**
	 * Сохраняет сразу информацию о всех отредактированных данных
	 * @return array
	 */
	private function bof_admin_save()
    {
    	global $kernel;

    	$data = $kernel->pub_httppost_get();
    	$id_admin = $kernel->pub_httpget_get('id');

        if (!isset($data['login']) || empty($data['login']) || !isset($data['pass']) || empty($data['pass']))
            return $kernel->pub_json_encode(array("success"=>false, "info"=>"[#admin_adminstrators_required_fields_empty#]"));
        if (!isset($data['select_group']) || empty($data['select_group']))
            return $kernel->pub_json_encode(array("success"=>false, "info"=>"[#admins_save_no_groups_error#]"));
        if (!$kernel->pub_is_valid_email($data['login']))
            return $kernel->pub_json_encode(array("success"=>false, "info"=>"[#admin_msg_incorrect_email#]"));
    	if ($id_admin == 0)
    	   return $kernel->pub_json_encode(array("success"=>false, "info"=>"No admin"));

        //проверка уникальности логина
        $cond="`login`='".$kernel->pub_httppost_get('login')."'";
        if ($id_admin>0)
            $cond.=" AND id<>".$id_admin;
        $exrec=$kernel->db_get_record_simple("_admin",$cond);
        if ($exrec)
            return $kernel->pub_json_encode(array("success"=>false, "info"=>"[#admin_msg_not_unique_email#]"));

        if (!isset($data['full_name']))
            $data['full_name']=$data['login'];
        $enabled = 0;
        if (isset($data['enabled']))
            $enabled = 1;
        //обновляем данные уже существующего администратора
        if ($id_admin > 0)
		{
            $query = "UPDATE `".$kernel->pub_prefix_get()."_admin`
            		  SET
               		 	login = '".mysql_real_escape_string($data['login'])."',
                	  	pass 	= '".mysql_real_escape_string($data['pass'])."',
                      	full_name = '".mysql_real_escape_string(trim($data['full_name']))."',
                      	lang = '".$data['lang']."',
                      	code_page = 'utf-8',
                      	enabled = '".$enabled."'
                      WHERE id = ".$id_admin."
                     ";
            $kernel->runSQL($query);

            //Обновить информацию по группам
            //Массив с ID группами, куда входит человек

            //Сначачала удалим ту информацию о вхождениях - что уже есть.
            $query = "DELETE FROM `".$kernel->pub_prefix_get()."_admin_cross_group`
                      WHERE user_id = ".$id_admin;

            $kernel->runSQL($query);

            //теперь добавим всю информацию из формы
            $groups_insert = array();
            if (isset($data['select_group']))
                foreach ($data['select_group'] as $key => $val)
                    $groups_insert[] = '(NULL, "'.$id_admin.'","'.$key.'")';

            if (!empty($groups_insert))
            {
                $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_admin_cross_group` VALUES '.join(',',$groups_insert);
                $kernel->runSQL($query);
            }
        }
        //Добавляем нового администратора
        elseif ($id_admin < 0)
        {
            //Сначала добавим саму запись с администратором
        	$query = "INSERT INTO `".$kernel->pub_prefix_get()."_admin`
        	           (`login`, `pass`, `full_name`, `lang`, `code_page`)
        	          VALUES
        			   ('".mysql_real_escape_string($data['login'])."',
        			   '".mysql_real_escape_string($data['pass'])."',
        			   '".mysql_real_escape_string(trim($data['full_name']))."', '".$data['lang']."', 'utf-8')";
        	$kernel->runSQL($query);

        	//Теперь в таблицу трассировщика
        	$id = mysql_insert_id();
        	$sql = "INSERT INTO `".$kernel->pub_prefix_get()."_admin_trace`
        	        (`id_admin`,`time`,`place`,`ip`,`host`)
        	        VALUES
        	        ('".$id."',NOW(),'','','')";
        	$kernel->runSQL($sql);

        	//И теперь надо поработать с группами
            //добавим всю информацию из формы
            $query = "DELETE FROM `".$kernel->pub_prefix_get()."_admin_cross_group`  WHERE user_id = ".$id;

            $kernel->runSQL($query);

            $groups_insert = array();
            foreach ($data['select_group'] as $key => $val)
            {
                $groups_insert[] = '(NULL, "'.$id.'","'.$key.'")';
            }

            if (!empty($groups_insert))
            {
                $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_admin_cross_group` VALUES '.join(',',$groups_insert);
                $kernel->runSQL($query);
            }

        }

        return $kernel->pub_json_encode(array("success"=>true, "info"=>"[#kernel_ajax_data_saved_ok#]"));
    }


    /**
     * Выводит форму для редактирования(добавления) администратора
     *
     * Выводится форма редактирвоания (добавления) администратора.
     * Если в качетсве логина передано пустое значение, то это форма
     * добавления.
     * @param string $login Логин администратора при редактировании
     * @return string
     */

    private function bof_admin_edit_and_add($login = '')
    {
        global $kernel;

        $template = $kernel->pub_template_parse("admin/templates/default/admin_edit_and_add.html");
        $html = $template['main'];

        //Получим данные редактируемого администратора
        $val['full_name'] = "Новый администратор";
        $val['login'] = "Admin";
        $val['pass'] = "";
        $val['lang'] = "ru";
        $val['code_page'] = '';
        $val['enabled'] = 1;
        $val['id'] = -1;
        if (!empty($login))
        {
            $val = $this->get_array_users($login);
            $val = $val[0];
        } else
        {
            $html = str_replace('[#backof_admin_edit_label#]', "[#backof_admin_edit_labe2#]", $html);
            $html = str_replace('[#full_name#]', "", $html);
        }

        $id_admin = $val['id'];

        $array_lang = $kernel->priv_languages_get();
        $lang_options = "";
        foreach($array_lang as $k=>$v)
        {
            if ($k==$val['lang'])
                $lang_option = $template['lang_option_selected'];
            else
                $lang_option = $template['lang_option'];
            $lang_option = str_replace(array("%key%","%val%"),array($k,$v),$lang_option);
            $lang_options.=$lang_option;
        }

        $html = str_replace('%lang_options%',  $lang_options, $html);

        $html = str_replace('[#full_name#]',   $val['full_name'], $html);
        $html = str_replace('[#login#]',       $val['login'], $html);
        $html = str_replace('[#pass#]',        $val['pass'], $html);


        //Проставим доступность
        if ($val['enabled'] == 1)
            $html = str_replace('[#enabled_checked#]', "checked", $html);
        else
            $html = str_replace('[#enabled_checked#]', "", $html);

        //Ну и осталось вывести группы, в которые он (администратор входит)
        //Узнаем куда входит данный администратор
		$array_groups = $this->get_curent_group_for_users($val['id']);
		$array_all_groups = $this->get_array_groups();
		$group_lines='';
		foreach ($array_all_groups as $val)
		{
		    $str = $template['group_line'];
		    $str = str_replace("[#name_group#]", $val['full_name'], $str);
		    $str = str_replace("[#id_group#]", $val['id'], $str);

			if (isset($array_groups[$id_admin][$val['id']]))
				$str = str_replace("[#group_checked#]", "checked", $str);
            else
                $str = str_replace("[#group_checked#]", "", $str);

            $group_lines .= $str;
		}
        $html = str_replace('[#fields_groups#]', $group_lines, $html);
        $html = str_replace('[#form_action#]', $kernel->pub_redirect_for_form('save_admin&id='.$id_admin), $html);
        return $html;
    }


    /**
     * Выводит форму для редактирования(добавления) администратора
     *
     * Выводится форма редактирвоания (добавления) администратора.
     * Если в качетсве логина передано пустое значение, то это форма
     * добавления.
     * @param mixed $id ID группы
     * @return string
     */
    private function bof_group_edit_and_add($id = '')
    {
        global $kernel;

        $template = $kernel->pub_template_parse("admin/templates/default/group_edit_and_add.html");
        $html = $template['main'];


        //Получим данные редактируемого администратора
        $val['name'] = "Новая группа";
        $val['full_name'] = "";
        $val['main'] = 0;
        $val['id'] = -1;
        if (!empty($id))
            $val = $this->get_array_groups($id);
        else
            $html = str_replace('[#backof_group_edit_label1#]', '[#backof_group_edit_label2#]', $html);

        //Заменим данные в шаблоне
        $id_group = $val['id'];
        //main_admin
        $html = str_replace('[#form_action#]', $kernel->pub_redirect_for_form('save_group&id='.$id_group), $html);
        $html = str_replace('[#full_name#]',   $val['full_name'], $html);
        $html = str_replace('[#name#]',        $val['name'], $html);
        $html = str_replace('[#id#]',          $val['id'], $html);


        if ($val['main'] == 1)
            $html = str_replace('[#root_admin_checked#]', "checked", $html);
        else
            $html = str_replace('[#root_admin_checked#]', "", $html);

        return $html;
    }

	/**
	 * Сохраняет отредактированную (или новую) группу администраторов
	 *
	 * @return string
	 */
	private function bof_group_save()
	{
		global $kernel;

		$data = $kernel->pub_httppost_get();
		$id_group = $kernel->pub_httpget_get('id');

		if (empty($id_group))
			return '';

	    $root_admin = 0;
		if (isset($data['root_admin']))
            $root_admin = 1;

        //Если это обновление
        if ($id_group > 0)
        {
            $query = "UPDATE `".$kernel->pub_prefix_get()."_admin_group`
                      SET
                        name = '".$data['name']."',
                        full_name = '".mysql_real_escape_string(trim($data['full_name']))."',
                        main_admin = ".$root_admin."
                      WHERE id = ".$id_group;
        }
        //Новая группа
        else
        {
            $query = "INSERT INTO `".$kernel->pub_prefix_get()."_admin_group`
                        (name , full_name, main_admin )
                      VALUES
                        ('".$data['name']."', '".mysql_real_escape_string(trim($data['full_name']))."', ".$root_admin.")";
        }
        $kernel->runSQL($query);
        return $kernel->pub_json_encode(array("success"=>true, "info"=>"[#kernel_ajax_data_saved_ok#]"));
	}


  /**
     * Создает в таблице пользователей frontoffice новую запись
     * @param string $login
     * @param string $password
     * @param string $email
     * @param string $name
     * @return integer
     */
    public static function user_add_new($login, $password, $email, $name)
    {
    	global $kernel;

    	//Проверим, может такой пользователь уже существует
        $row = $kernel->db_get_record_simple('_user',"email = '$email' OR login = '$login'","verified");
        if ($row)
        {
    		if (intval($row['verified']) == 0)
    			return -1;
    		else
    			return -2;
        }

    	$curent_date = date("Y-m-d H:i:s");

    	$query = "INSERT INTO ".$kernel->pub_prefix_get()."_user
    			  	(login, password, email, name, date)
    			  VALUES
					('$login', '$password', '$email', '$name', '$curent_date')
					";


        if ($kernel->runSQL($query))
		    return mysql_insert_id();
        else
            return 0;
    }

       /**
     * Проверяет, есть ли такой юзер в базе и, если есть, то возвращает массив его данных,
     * в противном случае возвращает код ошибки
     * @param string $login
     * @param string $password
     * @param boolean $unic_login
     * @param integer $id_user
     * @return mixed
     */
    public static function fof_user_authorization($login, $password, $unic_login = true, $id_user = 0)
    {
    	global $kernel;

    	//Проверим существует ли вообще такой пользователь
    	if ($id_user == 0)
    	{
    		if ($unic_login)
	    		$cond= "login = '$login' ";
    		else
    			$cond= "email = '$login' ";
    		$cond.= " AND password = '$password'";
    	}
        else
    		$cond = "id = '$id_user' ";

        $res = $kernel->db_get_record_simple("_user",$cond);

		if (!$res)
			return -1;

		//Проверим включена ли эта учетная запись
		if (intval($res['enabled']) == 0)
			return -2;

		//Проверим подтверждена ли авторизация этого пользователя
		if (intval($res['verified']) == 0)
			return -3;

		$arr['tree'] = $res;
		$arr['line'] = $res;

		//Теперь данный массив надо дополнить дополнительными полями, прописанными модулями
		$query = "SELECT *
				  FROM ".$kernel->pub_prefix_get()."_user_fields
				  ";

		$result = $kernel->runSQL($query);
		$modul = array();
		$modul2 = array();
		$indexes = array();
		if ($result)
		{
			while ($row = mysql_fetch_assoc($result))
			{
				if (intval($row['only_admin']))
					continue;

				$arr['tree']['fields'][$row['id_modul']][$row['id']]['name'] = $row['id_field'];
				$arr['tree']['fields'][$row['id_modul']][$row['id']]['caption'] = $row['caption'];
				$arr['tree']['fields'][$row['id_modul']][$row['id']]['value'] = '';
				$arr['tree']['fields'][$row['id_modul']][$row['id']]['type_field'] = $row['type_field'];
				$arr['tree']['fields'][$row['id_modul']][$row['id']]['params'] = $row['params'];
				$arr['tree']['fields'][$row['id_modul']][$row['id']]['required'] = $row['required'];

				$arr['line'][$row['id_modul'].self::symbol_delimiter.$row['id_field']] = '';

				$modul[$row['id']] = $row['id_modul'];
				$modul2[$row['id']] = $row['id_modul'].self::symbol_delimiter.$row['id_field'];
				$indexes[$row['id_modul'].self::symbol_delimiter.$row['id_field']] = $row['id'];
			}
            mysql_free_result($result);
		}



		//И последний запрос, что бы узнать текущие значения полей
		$query = "SELECT a.user, a.field, a.value, a.addon, b.only_admin
				  FROM ".$kernel->pub_prefix_get()."_user_fields_value a, ".$kernel->pub_prefix_get()."_user_fields b
				  WHERE (a.user = ".$arr['line']['id'].") AND (b.id = a.field)
				  ";
		$result = $kernel->runSQL($query);

		if ($result)
		{
			while ($row = mysql_fetch_assoc($result))
			{
				if (intval($row['only_admin']))
					continue;
				$arr['tree']['fields'][$modul[$row['field']]][$row['field']]['value'] = $row['value'];
				$arr['line'][$modul2[$row['field']]] = $row['value'];
			}
            mysql_free_result($result);
		}
		$arr['line']['indexes'] = $indexes;
		return $arr;
    }

    /**
     * Возвращает массив дополнительных полей у пользователя
     * @param string $cond условие
     * @return array
     */
    public static function users_fields_get($cond='true')
    {
        global $kernel;
    	$arr = array();
        $rows = $kernel->db_get_list_simple('_user_fields',$cond);
        foreach ($rows as $row)
        {
            $row['value']='';
            $arr[$row['id_modul']][$row['id']]=$row;
        }
        return $arr;
    }

    /**
     * Собирает массив с информацией по всем пользователям сайта
     *
     * @param integer $id_user ID конкретного пользователя - если необходимо.
     * @param boolean $tree если true, то возвращается в виде "дерева"
     * @param string $orderby поле для сортировки
	 * @param integer $offset смещение
	 * @param integer $limit лимит
	 * @param string $cond условие выборки
     * @return array
     */
    public static function users_info_get($id_user = 0, $tree = true, $orderby="`login`",$offset=null, $limit=null,$cond='true')
    {
    	global $kernel;

    	//сначала соберем массив всех дополнительных полей, прописанных модулями
    	$user_fields = self::users_fields_get();

		//Теперь обратимся к зарегистрированным пользователем и подготовим выходной массив
		$query = "SELECT *, date_format(date, '%d.%m.%y') AS fdate
    			  FROM ".$kernel->pub_prefix_get()."_user ";

		if ($id_user)
			$query .= " WHERE (id = $id_user)";
        else
        {
            $query .= "WHERE ".$cond."  ORDER BY ".$orderby;
            if (!is_null($offset) && !is_null($limit))
                $query.=" LIMIT ".$offset.",".$limit;
        }

		$arr_id = array();
		$res = array();
		$result = $kernel->runSQL($query);
		if (mysql_num_rows($result))
		{
    		while ($row = mysql_fetch_assoc($result))
    		{
    			$id = $row['id'];
    			$res[$id]['id']	  	  = $id;
    			$res[$id]['login']	  = $row['login'];
    			$res[$id]['password'] = $row['password'];
    			$res[$id]['email']	  = $row['email'];
    			$res[$id]['name'] 	  = $row['name'];
    			$res[$id]['date'] 	  = $row['date'];
    			$res[$id]['fdate'] 	  = $row['fdate'];
    			$res[$id]['verified'] = $row['verified'];
    			$res[$id]['enabled']  = $row['enabled'];
    			$arr_id[] = $id;

    			//Обозначим поля, прописанные модулями, что бы было их видно в случае если значения ещё пустые
    			if ($tree == true)
    				$res[$id]['fields'] = $user_fields;
    			else
    			{
    				foreach ($user_fields as $key => $val)
    				{
    					foreach ($val as $inf_fields)
    					{
    						$res[$id]['fields'][$key.self::symbol_delimiter.$inf_fields['id_field']]['value'] = '';
    						$res[$id]['fields'][$key.self::symbol_delimiter.$inf_fields['id_field']]['id'] = $inf_fields['id'];
    						$res[$id]['fields'][$key.self::symbol_delimiter.$inf_fields['id_field']]['caption'] = $inf_fields['caption'];
    					}
    				}
    			}

    		}
            mysql_free_result($result);

    		//Теперь только пропишем значения конкретных полей, если они есть.
    		$query = "SELECT a.user, a.field, a.value, b.id, b.id_modul, b.id_field
        			  FROM ".$kernel->pub_prefix_get()."_user_fields_value a, ".$kernel->pub_prefix_get()."_user_fields b
    				  WHERE (a.user IN (".join(",", $arr_id).")) and (a.field = b.id)";

    		$result = $kernel->runSQL($query);
    		if (!$result)
    			return $res;

    		if ($tree)
    		{
    			while ($row = mysql_fetch_assoc($result))
    				$res[$row['user']]['fields'][$row['id_modul']][$row['id']]['value']			= $row['value'];
    		}
    		else
    		{
    			while ($row = mysql_fetch_assoc($result))
    			{
    				$res[$row['user']]['fields'][$row['id_modul'].self::symbol_delimiter.$row['id_field']]['value'] = $row['value'];
    				$res[$row['user']]['fields'][$row['id_modul'].self::symbol_delimiter.$row['id_field']]['id'] = $row['id'];
    			}
    		}
            mysql_free_result($result);
		}
    	return $res;
    }

    public static function user_info_get($login, $is_login = true)
    {
    	global $kernel;
    	//Проверим существует-ли вообще такой пользователь
    	$query = "SELECT *
    			  FROM ".$kernel->pub_prefix_get()."_user
    			  WHERE ";
    	if ($is_login)
	       $query .= "(login = '$login') ";
    	else
    	   $query .= "(email = '$login') ";
		$result = $kernel->runSQL($query);

		if (!$result)
			return false;

		$res = mysql_fetch_assoc($result);
        if (!$res)
            return false;
		$arr = $res;
		//Теперь данный массив надо дополнить дополнительными полями, прописанными модулями
		$query = "SELECT *
				  FROM ".$kernel->pub_prefix_get()."_user_fields
				  ";

		$result = $kernel->runSQL($query);

		$modul = array();
		$modul2 = array();

		if ($result)
		{
			while ($row = mysql_fetch_assoc($result))
			{
				if (intval($row['only_admin']))
					continue;

				$arr[$row['id_modul'].self::symbol_delimiter.$row['id_field']] = '';

				$modul[$row['id']] = $row['id_modul'];
				$modul2[$row['id']] = $row['id_modul'].self::symbol_delimiter.$row['id_field'];
				$indexes[$row['id_modul'].self::symbol_delimiter.$row['id_field']] = $row['id'];
			}
            mysql_free_result($result);
		}


		if ($arr)
		{
			//И последний запрос, что бы узнать текщие значения полей
			$query = "SELECT a.user, a.field, a.value, a.addon, b.only_admin
					  FROM ".$kernel->pub_prefix_get()."_user_fields_value a, ".$kernel->pub_prefix_get()."_user_fields b
					  WHERE (a.user = ".$arr['id'].") AND (b.id = a.field)
					  ";
			$result = $kernel->runSQL($query);
		}
		if ($result)
		{
			while ($row = mysql_fetch_assoc($result))
			{
				$arr[$modul2[$row['field']]] = $row['value'];
			}
            mysql_free_result($result);
		}
		return $arr;
    }


    public static function users_info_save($data)
	{
		global $kernel;
		if (!is_array($data))
			return false;

		foreach ($data as $key => $val)
		{
			//Сначала обновим стандартные поля у пользователя, которые строго обозначены ядром
			//при этом будем обновлять только те поля - которые есть в массиве
			$arr_set = array();
        	if (isset($val['name']))
        		$arr_set[] =  "name = '".$val['name']."'";

        	if (isset($val['login']))
        		$arr_set[] =  "login = '".$val['login']."'";

        	if (isset($val['password']))
        		$arr_set[] =  "password = '".$val['password']."'";

        	if (isset($val['email']))
        		$arr_set[] =  "email = '".$val['email']."'";

        	if (isset($val['enabled']))
        	{
        		if ($val['enabled'])
        			$arr_set[] =  "enabled = 1";
        		else
        			$arr_set[] =  "enabled = 0";
        	}
        	if (isset($val['verified']))
        	{
        		if ($val['verified'])
        			$arr_set[] =  "verified = 1";
        		else
        			$arr_set[] =  "verified = 0";
        	}
			if (!empty($arr_set))
			{
    			$query = "UPDATE ".$kernel->pub_prefix_get()."_user
        			  	  SET ".join(",",$arr_set)."
        			  	  WHERE id = ".$key;
				$kernel->runSQL($query);
			}

			//Теперь нужно пройтись по массиву полей и вытащить не цифровые ключи
			$arr_fields = array();
			$arr_fields_str = array();
			$form_data = array();
			if (isset($val['fields']))
                $form_data = $val['fields'];

            foreach ($form_data as $tkey => $tval)
            {
                    if (preg_match('/^[0-9]+$/i', trim($tkey)))
                        $arr_fields[$tkey] = $tval;
                    else
                    {
                        //Сразу строчку для запроса подготовим
                        $tmp = explode(self::symbol_delimiter, $tkey);
                        $arr_fields_str[] = "(id_modul = '".$tmp[0]."' and id_field = '".$tmp[1]."')";
                    }
            }

            //Теперь нужно пройтись оп массиву не цифровых ключей и узнать их id, и внести в массив цифровых
			if (!empty($arr_fields_str))
			{
                $str_where = join(" || ", $arr_fields_str);
                $query = "SELECT id, id_modul, id_field
                          FROM ".$kernel->pub_prefix_get()."_user_fields
                          WHERE ".$str_where;

                $result = $kernel->runSQL($query);
                while ($row = mysql_fetch_assoc($result))
                {
                    $arr_fields[$row['id']] = $val['fields'][$row['id_modul'].'-'.$row['id_field']];
                }

			}

			//Теперь, имея массив всех цифровых ключей, запишем данные о полях
			if (!empty($arr_fields))
			{
				foreach ($arr_fields as $fkey => $fval)
				{
		            $sql = "DELETE FROM ".$kernel->pub_prefix_get()."_user_fields_value
					        WHERE user='$key' AND field='$fkey'";
					$kernel->runSQL($sql);

        			$query = "INSERT INTO ".$kernel->pub_prefix_get()."_user_fields_value
        			  		 (user, field, value)
    	   	       	  		 VALUES
			 		  	     ($key, $fkey, '$fval')";
    				$kernel->runSQL($query);
				}
			}

		}
		return true;
	}

	public static function user_verify($id_user)
	{
		global $kernel;
		if (empty($id_user))
			return false;
    	$query = "UPDATE ".$kernel->pub_prefix_get()."_user
        		  SET verified = 1
                  WHERE id = ".$id_user;
		$kernel->runSQL($query);
		$num = mysql_affected_rows();
		if ($num == 1)
			return true;
		else
			return false;
	}


	public static function user_change_enabled($id_user, $enabled = true)
	{
		global $kernel;

		if (empty($id_user))
			return false;
    	$query = "UPDATE ".$kernel->pub_prefix_get()."_user";
    	if ($enabled)
        	$query .= " SET enabled = 1 ";
        else
        	$query .= " SET enabled = 0 ";
        $query .= " WHERE id = ".$id_user;

		$kernel->runSQL($query);
		$num = mysql_affected_rows();
		if ($num == 1)
			return true;
		else
			return false;
	}


	/**
	 * Удаляет пользователя сайта
	 *
	 * @param integer $id_user
	 * @return boolean
	 */
	public static function user_delete($id_user)
    {
    	global $kernel;

    	if (empty($id_user))
    		return false;

    	$query = "DELETE FROM ".$kernel->pub_prefix_get()."_user
    			  WHERE id = ".$id_user;
    	$kernel->runSQL($query);

		//Пользователя удалили, теперь надо удалить значения полей на него
    	$query = "DELETE FROM ".$kernel->pub_prefix_get()."_user_fields_value
    			  WHERE user = ".$id_user;
    	$kernel->runSQL($query);

		//и записи о принадлежности к группам
    	$query = "DELETE FROM `".$kernel->pub_prefix_get()."_user_cross_group`
    			  WHERE user_id = ".$id_user;
    	$kernel->runSQL($query);
    	return true;
    }


	/**
	 * Удаляет админа из базы данных
	 *
	 * @param integer $id_str ID пользователя, которого надо удалять
	 */

	private function bof_admin_delete($id_str)
    {
    	global $kernel;
    	if (empty($id_str))
			return;
        $query = 'DELETE FROM '.$kernel->pub_prefix_get().'_admin
        		  WHERE id  = '.trim($id_str);
        $kernel->runSQL($query);
        $query = "DELETE FROM ".$kernel->pub_prefix_get()."_admin_cross_group
                  WHERE user_id = '".$id_str."'";
        $kernel->runSQL($query);
    }

    /**
    * Удаляет выбранную группу администраторов сайта
    * @param integer $id_group
    * @return Void
    */
	private function bof_group_delete($id_group)
    {
    	global $kernel;
        //Непосредственно удалим группу
        $query = 'DELETE FROM '.$kernel->pub_prefix_get().'_admin_group
                  WHERE id = '.$id_group;
        $kernel->runSQL($query);

        //Удалим связи между администраторами и этой группой
        $query = "DELETE FROM ".$kernel->pub_prefix_get()."_admin_cross_group
        		  WHERE group_id = '".$id_group."'";
        $kernel->runSQL($query);

        //Удалим права, проставленные для этой группы
        $query = "DELETE FROM ".$kernel->pub_prefix_get()."_admin_group_access
        		  WHERE group_id = '".$id_group."'";
        $kernel->runSQL($query);
    }


    /**
    * Выводит на экран форму с администраторами сайта, для их редактирования
    * @return string
    *
    **/
	private function bof_show_form_admin()
    {
    	global $kernel;

    	$user_tmp = $this->get_array_users();
    	$template = $kernel->pub_template_parse("admin/templates/default/access_list_admin.html");
    	$html = $template['main'];

        //Теперь выведем непосредственно информацию о пользователях
        $array_groups = $this->get_curent_group_for_users();

        $out = '';
        if (!empty($user_tmp))
        {
            $num = 1;
			foreach ($user_tmp as $key => $val)
    	    {
    	        $str = $template['string'];
    	    	//тип подля (type=text) обязательно должен быть без кавычек, что бы работал скрипт

                $str = str_replace('[#class_str#]', $kernel->pub_table_tr_class($num), $str);
                $str = str_replace('[#key#]',       $key, $str);
                $str = str_replace('[#id#]',        $val['id'], $str);
                $str = str_replace('[#num#]',       $num, $str);
                $str = str_replace('[#login#]',     $val['login'], $str);
                //$str = str_replace('[#pass#]',      $val['pass'], $str);
                $str = str_replace('[#full_name#]', $val['full_name'], $str);
                $str = str_replace('[#lang#]',      $val['lang'], $str);
                $str = str_replace('[#code_page#]', $val['code_page'], $str);
    	   		$str = str_replace('[#id#]',      $val['id'], $str);
    	   		$str = str_replace('[#alert_del#]', '[#backof_user_del_alert#] &quot;'.htmlspecialchars($val['login']).'&quot;?', $str);

    	    	//Узнаем группы, к которым принадлежит юзер
    	    	$str_group = '&nbsp;';
    	    	if (isset($array_groups[$val['id']]))
    	    		$str_group  = join(", ",$array_groups[$val['id']]);

    	    	if (mb_strlen($str_group) > 30)
    	    		$str_group = mb_substr($str_group,0, 27)."...";

    	        $str = str_replace('[#list_group#]', $str_group, $str);
    	    	$out .= $str;
				$num++;
	        }
    	}

    	$html = str_replace("[#str_content#]", $out, $html);

        return $html;

    }

	/**
    * Формирует форму с существующими группами
	*
	* Через форму можно вызвать удаление, создание новой и редактирование
	* сущестующей группы
	* @return string
	*/
	private function bof_group_show_form()
    {
    	global $kernel;

    	$template = $kernel->pub_template_parse("admin/templates/default/access_list_group.html");
    	$html = $template['main'];

        $arr_group = $this->get_array_groups();
        $num = 1;
        $out = '';
        if (!empty($arr_group))
        {
			foreach ($arr_group as $val)
    	    {
    	        $str  = $template['string'];
    	        $str = str_replace("[#tr_class#]", $kernel->pub_table_tr_class($num), $str);
    	        $str = str_replace("[#num#]", $num, $str);
    	        $str = str_replace("[#id#]", $val['id'], $str);
    	        $str = str_replace("[#name#]", $val['name'], $str);
    	        $str = str_replace("[#full_name#]", $val['full_name'], $str);
    	        $str = str_replace('[#alert_del#]', '[#backof_groups_del_alert#] &quot;'.htmlspecialchars($val['name']).'&quot;?', $str);

    	    	$chek = "";
    	    	if ($val['main'] > 0)
    	    		$chek = 'checked';

    	    	$str = str_replace("[#root_admin#]", $chek, $str);
    	    	$out .= $str;
				$num++;

	        }
    	}

    	$html = str_replace("[#str_content#]", $out, $html);
        return $html;

    }


	/**
	 * Создаёт форму управления правами для групп
	 *
	 * @return string
	 */
	private function bof_groups_show_form_access()
	{
		global $kernel;
		$template = $kernel->pub_template_parse('admin/templates/default/access_list_access.html');
		$html = $template['main'];
		//Сначала сформируем набор галочек, для всех доступных свойств
		$modules = new manager_modules();
		$arr_access = $modules->get_access();
		$array_name_cross_id = array(); //Массив соответствий ID конкретной отметки и имени
		                                //модуля или объекта, за который она отвечает
        $html_access = '';
        $num = 0;
		foreach ($arr_access as $key => $val)
		{
		    //Выведем галочку для основного объекта
            $str = $template['str_acess'][0];
            $str_name = "[".$key."]";
            $str = str_replace("[#full_name#]", $val['caption'], $str);
            $str = str_replace("[#id#]", $key, $str);
            $str = str_replace("[#num#]", $num, $str);
            $str = str_replace("[#name_obj#]", $str_name, $str);
            $html_access .= $str;
		    $array_name_cross_id[$key] = $num;
		    $num++;

            //Теперь проверим, необходимо ли отслеживать доступ к потомкам
            if (!empty($val['children']))
            {
                foreach ($val['children'] as $chil_key => $chil_val)
                {
                    $str = $template['str_acess'][1];
                    $str_name_chil = "[".$chil_key."]";
                    $str = str_replace("[#full_name#]", $chil_val['caption'], $str);
                    $str = str_replace("[#id#]", $chil_key, $str);
                    $str = str_replace("[#num#]", $num, $str);
                    $str = str_replace("[#name_obj#]", $str_name_chil, $str);
                    $html_access .= $str;
                    $array_name_cross_id[$chil_key] = $num;
                    $num++;

                    //А теперь надо проверить, есть ли у потомков уровни доступа
                    if (!empty($chil_val['access']))
                    {
                        foreach ($chil_val['access'] as $akey => $aval)
                        {
                            $str = $template['str_acess'][2];
                            $str_name_achil = $str_name_chil."[".$akey."]";
                            $str = str_replace("[#full_name#]", $aval, $str);
                            $str = str_replace("[#id#]", $chil_key.$akey, $str);
                            $str = str_replace("[#num#]", $num, $str);
                            $str = str_replace("[#name_obj#]", $str_name_achil, $str);
                            $html_access .= $str;
                            $array_name_cross_id[$chil_key.'_'.$akey] = $num;
                            $num++;
                        }
                    }
                }
            }
            //И последнее, возможно уровни доступа есть непосредственно
            //у главного объекта
            if (isset($val['access']))
            {
                foreach ($val['access'] as $akey => $aval)
                {
                    $str = $template['str_acess'][1];
                    $str_name_achil = $str_name."[".$akey."]";
                    $str = str_replace("[#full_name#]", $aval, $str);
                    $str = str_replace("[#id#]", $key.$akey, $str);
                    $str = str_replace("[#num#]", $num, $str);
                    $str = str_replace("[#name_obj#]", $str_name_achil, $str);
                    $html_access .= $str;
                    $array_name_cross_id[$key.'_'.$akey] = $num;
                    $num++;
                }
            }
        }

        //Теперь начнём формировать список групп, с указанием id тех групп,
        //которые на данный момент отмечены в базе.
        //Эти ID будем получуть по соответствиям в массиве $array_name_cross_id
		$html_group = '';
		$arr_group = $this->get_array_groups();
		$cbarray = array();
		foreach ($arr_group as $val)
		{
		    //Пропустим главных администраторов
		    if ($val['main'] == 1)
				continue;
			//Определим права на эту группу.
			$curent_access = $this->get_all_access_for_group($val['id'], $array_name_cross_id);
		    $str = $template['str_group'];
			$str = str_replace("[#id#]", $val['id'], $str);
			$str = str_replace("[#full_name#]", $val['full_name'], $str);
			$cbarray[] = '"'.$val['id'].'":"'.join(',',$curent_access).'"';
			$html_group .= $str;
		}
		$html = str_replace("[#form_select_group#]", $html_group, $html);
		$html = str_replace("[#form_select_access#]", $html_access, $html);
		$html = str_replace("[#form_action#]", $kernel->pub_redirect_for_form("save_access"), $html);
		$html = str_replace("%cbarray%", implode(",",$cbarray), $html);
		return $html;
	}


    /**
     * Записывает информацию о указанных правах на группы
     *
     * Функция не совсем корректно обрабатывает права на главную страницу структуры
     * это необходимо ещё доделать
     * @return string
     */
    private function bof_groups_save_access()
    {
    	global $kernel;
    	$id_goup = $kernel->pub_httppost_get('selgriup');
    	$data = $kernel->pub_httppost_get('access');
    	if ($id_goup <= 0)
    		return $kernel->pub_json_encode(array("success"=>false, "info"=>"[#admins_save_no_groups_error#]"));
    	//Действем по принципу всё очистили и записали занового
    	$query = "DELETE FROM ".$kernel->pub_prefix_get()."_admin_group_access
         		  WHERE group_id = ".$id_goup;
        $kernel->runSQL($query);

        //Если массив пустой, то никакие права ставить не надо
        if (empty($data))
            return $kernel->pub_json_encode(array("success"=>false, "info"=>"[#admins_save_no_rights_error#]"));

        $array_save = array();
        foreach ($data as $key => $val)
        {
        	$array_save[] = "(".$id_goup.", '".$key."', NULL , 1)";
        	//Проверим, если в качестве значения массив, значит у этого
        	//объекта есть выдеоенные им уровни доступа.
        	if (is_array($val))
        	{
        		foreach (array_keys($val) as $key_in)
        			$array_save[] = "(".$id_goup.", '".$key."', '".$key_in."', 1)";
        	}
        }

    	if (empty($array_save))
        	return $kernel->pub_json_encode(array("success"=>false, "info"=>"[#admins_save_no_rights_error#]"));

        //Теперь запишем новые данные из поста
    	$query = "INSERT INTO `".$kernel->pub_prefix_get()."_admin_group_access`
    			 (`group_id`, `modul_id`, `access_id`, `access`)
    			 VALUES ".join(",",$array_save);
    	$kernel->runSQL($query);

        return $kernel->pub_json_encode(array("success"=>true, "info"=>"[#kernel_ajax_data_saved_ok#]"));
    }


    /**
     * Определяет группы, в которые входить пользователь
     *
     * Возвращает массив групп, которым принадлежить пользователь или все пользователи
     * если не задан конкретный
     * @param integer $user_id ID юзера, по кому нужна информация. если не задан  - то по всем.
     * @return array
     */
    private function get_curent_group_for_users($user_id = 0)
    {
        global $kernel;

        $query = "SELECT ".$kernel->pub_prefix_get()."_admin_cross_group.id,
                         ".$kernel->pub_prefix_get()."_admin_cross_group.user_id,
                         ".$kernel->pub_prefix_get()."_admin_cross_group.group_id,
                         ".$kernel->pub_prefix_get()."_admin.id,
                         ".$kernel->pub_prefix_get()."_admin.login,
                         ".$kernel->pub_prefix_get()."_admin_group.id,
                         ".$kernel->pub_prefix_get()."_admin_group.full_name

                   FROM ".$kernel->pub_prefix_get()."_admin_cross_group,
                          ".$kernel->pub_prefix_get()."_admin,
                          ".$kernel->pub_prefix_get()."_admin_group

                   WHERE (".$kernel->pub_prefix_get()."_admin.id = ".$kernel->pub_prefix_get()."_admin_cross_group.user_id)
                         and (".$kernel->pub_prefix_get()."_admin_group.id = ".$kernel->pub_prefix_get()."_admin_cross_group.group_id)
                  ";

        if ($user_id)
            $query .= " and (".$kernel->pub_prefix_get()."_admin.id = ".$user_id.") ";

        $result = $kernel->runSQL($query);
        $arr = array();
        //Преобразуем результат к массиву нужного нам вида
        //для двух разных случаев
        while ($row = mysql_fetch_assoc($result))
           $arr[$row['user_id']][$row['group_id']] = $row['full_name'];
        return $arr;
    }


	/**
	 * возвращает массив данных на конкретного администратора либо на всех
	 *
	 * @param string $set_login Логин юзера, чьи параметры надо узнать
	 * @return Array
	 */
	private function get_array_users($set_login = '')
    {
    	global $kernel;

		$query = "SELECT * FROM ".$kernel->pub_prefix_get()."_admin ";

        if ($set_login)
			$query .= ' WHERE login = "'.$set_login.'"';

        $result = $kernel->runSQL($query);
        $user_tmp = array();
        $i = 0;
        while ($row = mysql_fetch_assoc($result))
        {
        	$user_tmp[$i]['id'] = $row['id'];
            $user_tmp[$i]['login'] = $row['login'];
            $user_tmp[$i]['full_name'] = $row['full_name'];
            $user_tmp[$i]['pass'] = $row['pass'];
            $user_tmp[$i]['lang'] = $row['lang'];
            $user_tmp[$i]['code_page'] = $row['code_page'];
            $user_tmp[$i]['enabled'] = $row['enabled'];
            $i += 1;
        }
        return $user_tmp;
    }

   /**
    * Возвращает массив групп администраторов
    *
    * Если используется параметр, ту будет возвращена
    * информацию только по группе с переданным ID
    * @return array
    * @param integer $id ID конкретной группы
    **/
	private function get_array_groups($id = 0)
    {
    	global $kernel;
		$query = "SELECT `id`, `name`, `main_admin`, `full_name`
        	      FROM `".$kernel->pub_prefix_get()."_admin_group`
                  ";

		if ($id)
            $query .= " WHERE id = '".$id."'";

        $result = $kernel->runSQL($query);
        $group_tmp = array();
        $i = 0;

        while ($row = mysql_fetch_assoc($result))
        {
            $tmp = array();
            $tmp['id'] = $row['id'];
            $tmp['name'] = $row['name'];
            $tmp['full_name'] = $row['full_name'];
            $tmp['main'] = $row['main_admin'];
            if (empty($id))
                $group_tmp[$i] = $tmp;
            else
                $group_tmp = $tmp;

            $i++;
        }
        mysql_free_result($result);
        return $group_tmp;
    }


   /**
     * Возвращает массив id элементов, на которые у группы есть права
     *
     * @param integer $id_group ID группы
     * @param array $group
     * @return array
     */
    private function get_all_access_for_group($id_group = 0, $group = array())
    {
    	global $kernel;
    	$query = 'SELECT *
    			  FROM `'.$kernel->pub_prefix_get().'_admin_group_access` ';
    	if ($id_group)
    		$query .= 'WHERE group_id = '.$id_group;

    	$query .= ' ORDER BY `'.$kernel->pub_prefix_get().'_admin_group_access`.`access_id` DESC ';

    	$result = $kernel->runSQL($query);
    	$arr = array();
    	while ($row = mysql_fetch_assoc($result))
    	{
    	    $str = $row['modul_id'];
    	    if (!empty($row['access_id']))
    	       $str .= '_'.$row['access_id'];
    	    if (isset($group[$str]))
    	       $arr[] = $group[$str];
    	    //Проставили уровень доступа там где есть
    	    //используются разделения
    	    //if (!empty($row['access_id']))

    	    //   $arr[$row['modul_id']][$row['access_id']] = intval($row['access']);
    	    //else
    	    //{
    	        //А здесь уровень доступа на сам объект
    	    //    if (!isset($arr[$row['modul_id']]))
    	    //       $arr[$row['modul_id']] = intval($row['access']);
    	    //}
    	}
    	return $arr;
    }


    public static function admin_access_for_group_get($id_groups, $count_group, $id_modul, $id_acces = '')
    {
    	global $kernel;
    	if ((empty($id_groups)) || (empty($id_modul)))
    		return false;
    	$query = 'SELECT * FROM '.$kernel->pub_prefix_get().'_admin_group_access
    			  WHERE (group_id IN ('.$id_groups.')) && (modul_id  = "'.$id_modul.'")';
    	if (!empty($id_acces))
    		$query .= " && (access_id = '".$id_acces."')";
    	$result = $kernel->runSQL($query);
    	$sum_access = 0;
    	while ($row = mysql_fetch_assoc($result))
    	{
    		$sum_access += intval($row['access']);
    	}
    	if (intval($sum_access) < intval($count_group))
    		return false;
    	else
    		return true;
    }

    //************************************************************************

	/**
	 * Добавляет новые поля к пользователю сайта, при
	 * инсталяции модуля (как базового так и дочернего)
	 *
	 * @param array $params Массив с парметрами полей
	 * @param string $id_modul ID модуля, который создает поля
	 * @param boolean $reinstall Признак того что сначала надо удалить старые значения
	 */
	public static function add_field_for_user($params, $id_modul, $reinstall = false)
	{
		global $kernel;

		if (empty($params))
			return;


		$arr_insert = array();
		$arr_new_feild = array();
		//Список тех полей, которые будем создавать
		foreach ($params as $key => $val)
			$arr_new_feild[$val['name']] = $key;

	    //Если это реинсталяция, то нужно удалить те поля, которых теперь не стало.
        if ($reinstall)
        {
            $id_feild_for_del = array();

            //Узнаем поля, которые сейчас есть у модуля, так как их
            //создавать не надо. А если их теперь нет - то их надо удалить
            $query = "SELECT * FROM ".$kernel->pub_prefix_get()."_user_fields
                      WHERE (id_modul = '".$id_modul."')";

            $result = $kernel->runSQL($query);
            while ($row = mysql_fetch_assoc($result))
            {
                if (isset($arr_new_feild[$row['id_field']]))
                    unset($params[$arr_new_feild[$row['id_field']]]);
                else
                    $id_feild_for_del[] = $row['id'];
            }

            //Теперь нужно только удалить те поля, которые нам теперь не нужны
            //а так же значения этих полей
            if (count($id_feild_for_del) > 0)
            {
                $query = "DELETE FROM ".$kernel->pub_prefix_get()."_user_fields_value
                        WHERE field IN (".join(",", $id_feild_for_del).")";

                $kernel->runSQL($query);

                //Теперь сами поля
                $query = "DELETE FROM ".$kernel->pub_prefix_get()."_user_fields
                		  WHERE id IN (".join(",", $id_feild_for_del).")";

                $kernel->runSQL($query);
            }

        }

        //В итоге, в $params мы оставили только то, что нужно добавлять
        //при любом случе вызова (интсталяция и реинсталяция
		foreach ($params as $val)
		{
			$str = "('".$val['name']."', '".trim($id_modul)."', '".$val['caption']."', '".$val['type']."', ";
			if ($val['admin'])
				$str .= "1)";
			else
				$str .= "0)";

			$arr_insert[] = $str;
		}

		if (count($arr_insert) > 0)
        {
            $query = "INSERT INTO ".$kernel->pub_prefix_get()."_user_fields
            		  (id_field, id_modul, caption, type_field, only_admin)
            		  VALUES
            		  ".join(",",$arr_insert);
            $kernel->runSQL($query);
        }
	}


	/**
	 * Удаляет дополнительные поля, созданные при инсталяции модуля
	 *
	 * @param string $id_modul ID модуля, чьи поля будут удалены
	 */
	public static function delete_field_for_user($id_modul)
	{
		global $kernel;
        //удаление значений
		$query = "DELETE FROM ".$kernel->pub_prefix_get()."_user_fields_value
				  WHERE field IN (SELECT id FROM ".$kernel->pub_prefix_get()."_user_fields WHERE id_modul = '".$id_modul."')";
		$kernel->runSQL($query);

        //удаление полей
		$query = "DELETE FROM ".$kernel->pub_prefix_get()."_user_fields
				  WHERE (id_modul = '".$id_modul."')";
		$kernel->runSQL($query);
	}

	/**
	 * Формирует массив с группами пользователей сайта
	 *
	 * @return array
	 */
    public static function users_group_get()
    {
		global $kernel;
    	$query = "SELECT * FROM `".$kernel->pub_prefix_get()."_user_group`";
    	$result = $kernel->runSQL($query);
        $group = array();
        while ($row = mysql_fetch_assoc($result))
        {
            $group[$row['id']]['id']        = $row['id'];
            $group[$row['id']]['name']      = $row['name'];
            $group[$row['id']]['full_name'] = $row['full_name'];
        }
        return $group;
    }

    /**
     * Получает инофрмацию о текущих группах у пользователя
     *
     * @param integer $id
     * @param boolean $invers
     * @return array
     */

    public static function user_group_get($id, $invers = false)
    {
        global $kernel;

		$group = array();
		$query = "SELECT *
		          FROM `".$kernel->pub_prefix_get()."_user_cross_group`
		          WHERE user_id = ".$id;

		$result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
        {
            if ($invers)
                $group[$row['group_id']] = $row['id'];
            else
                $group[$row['id']] = $row['group_id'];
        }
        return $group;
    }

    /**
	 * Сохраняет группы, для конкретного пользователя сайта
	 * @param integer $id
	 * @param array $data
	 * @return array
	 */
    public static function users_group_set($id, $data)
    {
		global $kernel;
		//сначала узнаем есть ли текущие группы
	    $curent_group = self::user_group_get($id, true);
	    //Теперь из массива того что есть и того что должно быть
	    //сделаем массив того что нужно удалить и добавить
    	$a_add = array();
    	//значит всё удалить
    	if (empty($data))
    	   $a_del = $curent_group;
    	else
    	{
    	    //удалим те группы, что не нужно менять
    	    //и добавим в отдельный массив те группы, которых нет
    	    foreach ($data as $val)
    	    {
    	        if (isset($curent_group[$val]))
                    unset($curent_group[$val]);
    	        else
                    $a_add[] = $val;
    	    }
    	    $a_del = $curent_group;
    	}

    	//Всё есть список что удалить и есть список что добавить
    	if (!empty($a_del))
    	{
    	    foreach ($a_del as $del_id)
    	    {
    	       $query = "DELETE
    	                 FROM `".$kernel->pub_prefix_get()."_user_cross_group`
                         WHERE id = ".$del_id;
    	       $kernel->runSQL($query);
    	    }
    	}

    	if (!empty($a_add))
    	{
    	    foreach ($a_add as $add_id)
    	    {
    	       $query = "INSERT INTO `".$kernel->pub_prefix_get()."_user_cross_group`
    	                 (`user_id`, `group_id`)
    	                 VALUES ('".$id."','".$add_id."')";
    	       $kernel->runSQL($query);
    	    }
    	}

    }

}