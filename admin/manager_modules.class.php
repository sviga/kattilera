<?php

/**
 * Менеджер для редактирования и управления модулями сайта
 * @name manager_modules
 * @param
 * @copyright ArtProm (с) 2001-2005
 * @version 1.0
 */

class manager_modules
{
    /**
     * Содержит массив таблиц, после инсталяции каждого экземпляра модуля
     *
     * @var array
     * @access private
     */
    var $mysqltable_compare = array();

	//************************************************************************
	/**
	 * Конструктор класса
	 *
	 * @return manager_modules
	 */
	function manager_modules()
    {

    }

    /**
     * Предопределённая функция для формирования меню
     *
     * Функция орпеделяет какие элементы меню присутсвуют в меню раздела
     * @param pub_interface $show Объект класса pub_interface
     * @return void
     * @access private
     */
	function interface_get_menu($show)
    {
        global $kernel;

        $show->set_menu_block('[#modules_label_structur#]');
        $show->set_menu_default('select_modul');

        //Создаём дерево модулей
        $tree = new data_tree('[#modules_label_structur#]','index', $this->modules_structure_all_get());
        $tree->set_action_click_node('select_modul');
        $tree->set_drag_and_drop(false);
        $tree->set_name_cookie("tree_modules_structure");
        $tree->set_tree_ID('modules');

        $id_mod = $kernel->pub_module_id_get();
        if (empty($id_mod))
            $id_mod = 'index';

        $tree->set_node_default($id_mod);

        //Строим котекстное меню
 		$tree->contextmenu_action_set('[#modules_context_menu1#]','modules_add','index', '', 'ic_modinstall');
        $tree->contextmenu_action_remove('[#modules_context_menu2#]','module_delet','index','[#modules_alert_del#]');
        $tree->contextmenu_delimiter();
        $tree->contextmenu_action_set('[#modules_context_menu3#]','module_reinstall','index','[#modules_alert_sofr_reinstal#]','ic_reinstall');

        $show->set_tree($tree);
    }

    /** Точка входа. Формирует интерфейс для управления и изменения настроек модулей
    *    @return string
    */
    function start()
    {
    	global $kernel;
        $html = '';
        $action = $kernel->pub_section_leftmenu_get();

        switch ($action)
        {
            //Строит дерево модулей, все ноды в нём сразу раскрываются, что бы были
            //сразу видны дочерние модули
    		case 'modules_tree':
    		    $node_id = (isset($_REQUEST['node'])?($_REQUEST['node']):(''));
    		    if ($node_id == 'index')
                    $node_id = '';
                $html = $kernel->pub_json_encode($this->modules_structure_get($node_id));
    		    break;

    		//Показываем интерфейс с настройками модуля
            case 'select_modul':
            	//Установим ID текущего модуля
            	$id = $kernel->pub_httpget_get('id');
            	if (!empty($id) && $id != 'index' && $this->return_info_modules($id))
            	{//форма модуля
            		$kernel->priv_module_current_set($id);
                    $html = $this->show_properties_module();
            	}
                else//список модулей
                    $html = $this->modules_form_installed_get();
            	break;


            //-- Работа с дейсвтиями --
            //Удаляет существующее действие
            case 'action_delet':
           		$id_action = $kernel->pub_httpget_get('id_action');
				$this->action_delet(intval($id_action));
				$kernel->pub_redirect_refresh('select_modul&id='.$kernel->pub_module_id_get());
            	break;

            //Редактируем существующее действие
            case 'action_edit':
                $id_action = $kernel->pub_httpget_get('id_action');
				$html = $this->action_edit($id_action);
            	break;

            //Создаем новое дейсвтие
            case 'action_new':
				//$id_action - "id"(число) строки из таблицы _metods
				//ID метода из которого делаем дейсвтие нужно передать
				//в модуль, что бы он попал в ссылку
				$id_metod = $kernel->pub_httpget_get('id_metod');
				$html = $this->action_edit(0, $id_metod);
                break;

            //Сохраняем отредактирпованное дейсвтие
            case 'action_save':
                $id_action = $kernel->pub_httpget_get('id_action');
                if ($id_action <= 0)
                {
                    $id_metod = $kernel->pub_httpget_get('id_metod');
                    $id_action = $this->action_create_new($id_metod, $this->metod_info_get(0, $id_metod));
                }
                $html = $this->action_save($id_action);
                break;


            //Вызывается дл обновления списка существующих действий
            case 'action_update_list':
                $template = $kernel->pub_template_parse("admin/templates/default/admin_modules.html");
                $html = $this->action_exist_get($kernel->pub_module_id_get(), $template);
                break;

            //-- Работа с модулями --
            //Формирование формы для инсталяции нового базового модуля
        	case 'modules_base_install_form':
                $html = $this->modules_form_installed_get();
            	break;
            case 'modules_base_install':
				$this->modules_base_modul_install();
                $kernel->pub_redirect_refresh_global('/admin/');
            	break;
            case 'module_delet':
            	$node = $kernel->pub_httppost_get('node');
            	if ($node)
            		$this->modules_delet($node);
                $kernel->priv_module_current_set($kernel->pub_httppost_get('nodeparent'));
            	break;
        	case 'modules_add':
                $this->modules_children_install();
            	break;
        	case 'modul_prop_save':
                $html=$this->modules_properties_save();
            	break;
            case 'module_reinstall':
            	$node = $kernel->pub_httppost_get('node');
            	if ($node)
            		$this->reinstall_module($node);
                break;
        }
        return $html;
    }


	/**
	 * Создаёт новое действие для модуля
	 *
	 * Новое действие может быть созданно как администратором сайта в интерфейсе
	 * так и автоматически при инсталяции дочерних модулей (если в инсталятре модуля прописано
	 * создание дейсвтий).
	 * Если в качестве значения параметра $type передаётся <i>true</i> - значит действие создаётся
	 * из интерфейса и в качестве параметра $id_modul передаётся ID строки mySql с конкретным методом.
	 * В противном случае, во втором параметре передаётся ID модуля чьи дейсвтия надо проинсталировать
	 *
	 * Возвращается ID вновь созданного дейсвтия
	 * @param int $id_modul ID метода для действия или ID модуля
	 * @param mixed $array_prop Массив со свойствами
	 * @param mixed $value Массив со значениями параметров действия, вместо тех что по умолчанию
	 * @param mixed $type Массив со значениями параметров действия, вместо тех что по умолчанию
	 * @param string $autoname
	 * @return integer ID вновь созданного дейсвтия
	 */
	function action_create_new($id_modul, $array_prop = false, $value = false, $type = false, $autoname = '')
    {
		global $kernel;

        //Вытащим параметры действия
        $serialize = array();
        if ((isset($array_prop['properties'])) && (!empty($array_prop['properties'])) && (is_string($array_prop['properties'])))
        	$serialize = unserialize(stripslashes($array_prop['properties']));

        //Создадаим массив с параметрами непосредственно для дейсвтия
        $array_param = array();
        if (!empty($serialize))
        {
        	foreach ($serialize as $val)
        	{
        		$array_param[$val['name']] = $val['default'];
        		if (isset($value[$val['name']]))
        		    $array_param[$val['name']] = $value[$val['name']];
        	}
        	$array_prop['caption'] = $autoname;
        }
        //Подготовим данные для запроса
        if ($type)
            $mysql_modul = $id_modul;
        else
            $mysql_modul = $kernel->pub_module_id_get();

        $mysql_caption     = $array_prop['caption'];
        $mysql_metod       = mysql_real_escape_string($array_prop['metod']);
        $mysql_array_prop  = $array_prop['properties'];
        $mysql_array_param = mysql_real_escape_string(serialize($array_param));

        //Сам запрос
        $query = "INSERT INTO ".$kernel->pub_prefix_get()."_action VALUES
        				(
                        	NULL,
                        	'".$mysql_modul."',
                        	'".$mysql_caption."',
                            '".$mysql_metod."',
                            '".$mysql_array_prop."',
                            '".$mysql_array_param."'
                        )
                        ";
        $kernel->runSQL($query);
        $id_action = mysql_insert_id();
        return $id_action;
    }


    /**
    @return void
    @desc Пробегает по всем страницам сайта и обновляет параметры вызова макроса
     */
    function update_macros_in_page()
    {
        //@todo rewrite - не использовать "run" в serialize для cтруктуры, брать данные из _actions
        global $kernel;
        //Сперва сформируем массив всех макросов, прям в том виде, как они используются на страницах сайта
        $macros = array();
        $actions = $kernel->db_get_list_simple('_action','true');
        foreach($actions as $row)
        {
            $macros[$row['id']]['id_mod'] = $row['id_module'];
            $macros[$row['id']]['id_action'] = $row['id'];
            $macros[$row['id']]['run']['name'] = $row['link_str'];
            $macros[$row['id']]['run']['param'] = $row['param_array'];
        }
        $structs = $kernel->db_get_list_simple('_structure',"true","id, serialize");
        foreach($structs as $struct)
        {
            if ($struct['serialize'])
            {
                $perebor = $curent = unserialize($struct['serialize']);
                foreach ($perebor as $label => $val)
                {
                    $id_action = $val['id_action'];
                    if ($id_action > 0)
                    {
                        if (isset($macros[$id_action]))
                        {
                            $curent[$label] = $macros[$id_action];
                            if (isset($val['postprocessors']))
                                $curent[$label]['postprocessors']=$val['postprocessors'];
                        }
                        else
                            unset($curent[$label]);
                    }
                }
                $kernel->db_update_record('_structure',array('serialize'=>mysql_real_escape_string(serialize($curent))),"id='".$struct['id']."'");
            }
        }
    }

    /**
     * Сохраняет отредактированные значения параметров действия
     *
     * @param integer $id_action Идентификатор действия в таблице
     * @return string
     */
	function action_save($id_action = 0)
    {
		global $kernel;

		$aproperties = $kernel->pub_httppost_get('properties');
		$action_name = $kernel->pub_httppost_get('action_name');

        $url = '';
		//Значит обновляем парметры существующего дейсвтия
		if ($id_action > 0)
		{

			$array_prop = $this->action_info_get($id_action);
			$serialize = array();
			if (isset($array_prop['properties']))
				$serialize = unserialize(stripslashes($array_prop['properties']));

			//Теперь нужно пройтись массиву параметров, и определить что вставлено - а что нет
			//и сформировать строку парметров для метода (если метод вообще поддерживает парметры)
			$array_param = array();
    		if (!empty($aproperties))
    		{
				$pars = new parse_properties();
    			$pars->set_metod();
    			foreach ($serialize as $val)
    			{
    				$array_param[$val['name']] = $pars->return_normal_value($aproperties, $val);
    			}
			}

    		//Теперь создадим окончательную строку для вызова метода
        	$query = 'UPDATE '.$kernel->pub_prefix_get().'_action
        	      	  SET caption = "'.$action_name.'",
        	          param_array = "'.addslashes(serialize($array_param)).'"
                      WHERE id = '.$id_action;

			$kernel->runSQL($query);
			$this->update_macros_in_page();
			$url = $kernel->pub_redirect_for_form('action_save&id_action='.$id_action);
		}

        return $kernel->pub_json_encode(array("success"=>true,"info"=>"[#kernel_ajax_data_saved_ok#]", "newurl"=>$url));
    }


    /**
    * Удаляет кокретный макрос или все макросы списка модулей, а так же все ссылки на него
    * @return void
    * @param mixed $del_macros ID стороки в Mysql таблице с макросами, которую нужно удалить, либо массив из id модулей, чьи макросы нужно удалить
    */
	function action_delet($del_macros)
    {
		global $kernel;

		if (empty($del_macros))
            return;

		//Зачитаем все соответсвия меткам, которорые прописаны у конкретных страниц,
		//чтобы удалить оттуда ссылки на удаляемые макросы
		$pages = array();
		$query = 'SELECT id,serialize
				  FROM `'.$kernel->pub_prefix_get().'_structure`
				 ';
		$result = $kernel->runSQL($query);
		while ($row = mysql_fetch_assoc($result))
		{
			if ($row['serialize'])
				$pages[$row['id']] = unserialize($row['serialize']);
		}

		//Теперь нужно пробижаться по получившемуся массиву страниц, и удалить ссылки на необходимые макросы
		foreach ($pages as $key => $val)
		{
			foreach ($val as $id_perem => $param)
			{
				if (((is_int($del_macros)) && ($param['id_action'] == $del_macros)) ||
					((is_array($del_macros)) && (isset($del_macros[$param['id_mod']]))))
				{
					//Значит это удаляемый макрос
					unset($pages[$key][$id_perem]['id_mod']);
					unset($pages[$key][$id_perem]['id_action']);
					unset($pages[$key][$id_perem]['run']);
					unset($pages[$key][$id_perem]);
				}
			}
		}

		//Изменённые значения нужно записать в базу
		if (count($pages) > 0)
		{
			foreach ($pages as $key => $val)
			{
		    	$query = 'UPDATE `'.$kernel->pub_prefix_get().'_structure`
        	    		  SET serialize = "'.mysql_real_escape_string(serialize($val)).'"
                      	  WHERE id = "'.$key.'"';
				$kernel->runSQL($query);
			}
		}



		//Теперь можно удалить и сам макрос
		$query = "DELETE FROM ".$kernel->pub_prefix_get()."_action ";
		if (is_int($del_macros))
        	$query .= "WHERE id = ".$del_macros;

        elseif (is_array($del_macros))
        {
        	$temp = array_keys($del_macros);
        	if (count($temp) > 1)
        		$query .= "WHERE id_module IN ('".join("','",$temp)."')";
        	else
        		$query .= "WHERE id_module = '".$temp[0]."'";
        }
        $kernel->runSQL($query);
    }
    //************************************************************************


    /**
     * Производит мягкую переинстляцию модуля
     *
     * Необходима для того, что бы не убивать созданные у него действия
     * Необходимо вызвать только один раз, и будут задействованы
     * все дочерниии модули
     * @param string $id_modul
     * @access private
     * @return void
     */
    function reinstall_module($id_modul)
    {
        global $kernel;

        $arr_modul = $this->return_info_modules($id_modul);
        if (!empty($arr_modul['parent_id']))
            $id_modul = trim($arr_modul['parent_id']);

        //Теперь узнаем точно всех потомков модуля и соберем массив
        //всех обрабатываемых модулей
        //Ключь - ID модуля, значение -признак того что модуль дочерний
        $all_children = array();
        $all_children[$id_modul] = '';
        foreach ($this->return_modules($id_modul) as $key => $val)
            $all_children[$key] = $val['id_parent'];

        //Подключим инстал, что бы взять новые параметры модуля и новые действия
        //Подключим инсталятор
        $install = new install_modules();
        //Если этого файла не существует, то заканчиваем, так как реинстал
        //значит вызвается из обновления, и этого модуля просто нет
        if (!file_exists('modules/'.$id_modul.'/install.php'))
            return;
        include_once('modules/'.$id_modul.'/install.php');


        //Переинсталируем методы, которыми администратор может пользоваться при
        //конструирование макросов а так же вернем параметры самого модуля
        //и параметры, которые модулю необходимы для каждой страницы
    	$this->set_admin_metods($id_modul, $install, true);

    	//Теперь надо обновить параметры модулей. Для базового добавим
    	//значения по умолчанию а для дочерних  - нет, чтобы они отнаследовались
        foreach ($all_children as $modul_key => $parent_key)
        {
        	$new_prop = array();
        	$curent_prop = array();
        	$arr_modul = $this->return_info_modules($modul_key);
        	if (!empty($arr_modul['serialize']))
        	   $curent_prop = unserialize($arr_modul['serialize']);

        	$save_modul_param = $install->get_modul_properties();
        	foreach ($save_modul_param as $val)
        	{
        	    //Это только для главного модуля
        	    if (empty($parent_key))
        	       $new_prop[$val['name']] = $val['default'];
        	    //Если это свойство есть в таром, то заменим его
        	    //!!! -- Однако есть узкое место. Нет проверки на совпадения типов --
        	    if (isset($curent_prop[$val['name']]))
        	       $new_prop[$val['name']] = $curent_prop[$val['name']];
        	}
            $query = "UPDATE `".$kernel->pub_prefix_get()."_modules` SET
                        type_admin = ".$install->get_admin_interface().",
                        properties = '".serialize($install->get_modul_properties())."',
                        properties_page = '".serialize($install->page_properties_get())."',
                        acces_label = '".serialize($install->get_admin_acces_label())."',
                        serialize = '".serialize($new_prop)."'
                      WHERE id = '".$modul_key."'";

            $kernel->runSQL($query);
        }

        //Теперь проведем Реинсталяцию языковых переменных
        //$lang_install = new mysql_table($kernel->priv_prefix_get(), $kernel);
		//$lang_install->add_langauge('modules/'.$id_modul.'/lang');


		//Проверим необходимость добавления к пользователю Фротнофиса новых полей базовым модулем
        manager_users::add_field_for_user($install->get_users_properties_one(), $id_modul, true);

		//Обновим информацию о макросах
		//Здесь нам нужно только убрать или добавить параметры макросов


		//Эту проверку удобнее будет сделать для каждого модуля в отдельности
		$new_action = $install->get_public_metod();
		$action_for_del = array();
        foreach ($all_children as $modul_key => $parent_key)
        {
            if (empty($parent_key))
                continue;

            //ВЫберем действия, которые возможно нужно изменить
            $query = "SELECT * FROM ".$kernel->pub_prefix_get()."_action
                      WHERE id_module  = '".$modul_key."'";

            $result = $kernel->runSQL($query);
            while ($row = mysql_fetch_assoc($result))
            {
                //Такой метод существует в модуле и у нас введено действие с этим методом
                if (isset($new_action[$row['link_str']]))
                {
                    $new_str_prop = serialize($new_action[$row['link_str']]['parametr']);
                    $curent_str_prop = $row['properties'];
                    if (strval($new_str_prop) != strval($curent_str_prop))
                    {
                        //Значит изменились параметры модуля и нам нужно собрать новый масив
                        //с их значениями, с учетом текущих значений и значений по умолчанию
                        $curent_action_param = unserialize($row['param_array']);
                        $new_action_param = array();
                        foreach ($new_action[$row['link_str']]['parametr'] as $new_action_val)
                        {
                            $new_action_param[$new_action_val['name']] = $new_action_val['default'];
                            if (isset($curent_action_param[$new_action_val['name']]))
                                $new_action_param[$new_action_val['name']] = $curent_action_param[$new_action_val['name']];
                        }
                        $new_action[$row['link_str']]['curent_param'] = $new_action_param;
                        $new_action[$row['link_str']]['change_action'] = $row['id'];

                        $query = "UPDATE ".$kernel->pub_prefix_get()."_action
                          SET
                            properties = '".addslashes(serialize($new_action[$row['link_str']]['parametr']))."',
                            param_array = '".addslashes(serialize($new_action_param))."'
                          WHERE id = ".$row['id'];

                        $kernel->runSQL($query);
                    }

                } else
                    $action_for_del[] = $row['id'];
            }
        }


        //А вот сейчас самое хитрое...
        //Нужно проабдейтить таблицы mysql
        //Делаем это только в том случае, если модуль это не зарпещает в явном виде
        if ($install->get_call_reinstall_mysql())
        {
            foreach ($all_children as $modul_key => $parent_key)
            {
                $is_base = false;
                if (empty($parent_key))
                    $is_base = true;

                $this->mysqltable_reinstall($modul_key, $install, $is_base);
            }
        }
        //И кроме всех дочерних модулей, надо сменить нужно проверить таблицы для базового модуля

        //Почти всё, осталось только удалить упоминания о тех действиях которые удалены
        foreach ($action_for_del as $id_action_del)
            $this->action_delet(intval($id_action_del));

        //Мы не меняем свойств модуля к станицам, можно отложить
        //die;
    }

	/**
	 * Удаляет указанный модуль из системы
	 *
	 * @param String $id_modul ID удаляемого модуля
	 * @return boolean
	 */
	function modules_delet($id_modul)
	{
		global $kernel;

		$mudules_del = array();
		$mudules_del[$id_modul] = '';

		//Проверим, может для удаления выбран базовый модуль, и это значит что
		//нужно удалить и все его дочерние модули
		$arr_modul = $this->return_info_modules($id_modul);
		if (empty($arr_modul['parent_id']))
		{
			$install = new install_modules();
			include 'modules/'.$id_modul.'/install.php';

			$temp = $this->return_modules($id_modul);

			foreach ($temp as $key => $val)
			{
				$mudules_del[$key] = '';
				//Для каждого дочернего модуля нужны вызвать предпредделны метод
				//uninstalla - что бы он в случае надобности удалил всё что ему нужно.
        		@$install->uninstall_children($key);
        		manager_users::delete_field_for_user($key);
			}
			//Теперь вызовем деинстолятор базового модуля
			$install->uninstall($id_modul);
            manager_users::delete_field_for_user($id_modul);
		} else
		{
			//Удаляем только дочерний модуль
			include 'modules/'.$arr_modul['parent_id'].'/install.php';
			//$install = new install_modules();

            /** @var $install install_modules */
            $install->uninstall_children($id_modul);
            manager_users::delete_field_for_user($id_modul);
		}

		//Базы данных модулей, а так же возможные поля к пользователям удалены

		//$mudules_del - содержит массив удаляемых модулей
		//Удалим дейсвтия
		$this->action_delet($mudules_del);

		//Определим те свойства, которые возможно модуль прописал к страницам
		$pages = array();
		$query = 'SELECT id, properties
				  FROM `'.$kernel->pub_prefix_get().'_structure`
				 ';
		$result = $kernel->runSQL($query);
		while ($row = mysql_fetch_assoc($result))
		{
			if ($row['properties'])
				$pages[$row['id']] = unserialize($row['properties']);
		}

		//Теперь нужно пробижаться по получившемуся массиву страниц, и удалить ссылки на необходимые макросы
		foreach ($pages as $key => $val)
		{
			foreach ($mudules_del as $id_m => $m_val)
			{
				$temp = $this->return_info_modules($id_m);
				$temp = unserialize($temp['properties_page']);
				foreach ($temp as $page_properties)
				{
					$name_properties = $id_m.'_'.$page_properties['name'];
					if (isset($val[$name_properties]))
						unset ($pages[$key][$name_properties]);
				}
			}
		}

		//Изменённые значения нужно записать в базу
		if (count($pages) > 0)
		{
			foreach ($pages as $key => $val)
			{
		    	$query = 'UPDATE `'.$kernel->pub_prefix_get().'_structure`
        	    		  SET properties = "'.addslashes(serialize($val)).'"
                      	  WHERE id = "'.$key.'"';
				$kernel->runSQL($query);
			}
		}

		//Теперь необходимо очистить данные о методах, из которых могли строится действия
		$query = "DELETE FROM ".$kernel->pub_prefix_get()."_metods
				 ";
        $temp = array_keys($mudules_del);
        if (count($temp) > 1)
        	$query .= "WHERE id_module IN ('".join("','",$temp)."')";
        else
        	$query .= "WHERE id_module = '".$temp[0]."'";

		$kernel->runSQL($query);

		//Удалим языковые переменные модуля, только в том случае, если
		//удаляется бызовый модуль.
		if (empty($arr_modul['parent_id']))
            mysql_table::del_langauge('modules/'.$arr_modul['id'].'/lang');

       	//ВСЁ удалили, теперь можно удалять модули непосредственно из таблицы модулей
		$query = "DELETE FROM `".$kernel->pub_prefix_get()."_modules` ";
        if (count($temp) > 1)
        	$query .= "WHERE id IN ('".join("','",$temp)."')";
        else
        	$query .= "WHERE id = '".$temp[0]."'";

        $kernel->runSQL($query);
        return true;
	}


    /**
    * Возвращает HTML код для редактирования параметров макроса
    *
    * @param integer $id_action
    * @param string $id_metod
    * @return string
    */
    function action_edit($id_action, $id_metod = '')
	{
    	global $kernel;
    	$template = $kernel->pub_template_parse("admin/templates/default/action_edit.html");
        //Если редактируем действие - то необходимо взять имеющиеся свойства
    	$serialize = array();
        $array_prop = $this->action_info_get($id_action);
        //Необходимо получить параметры из дейсвтия
        if ($id_action <= 0)
            $array_prop = $this->metod_info_get(0,$id_metod);

        if (isset($array_prop['properties']))
            $serialize = unserialize(stripslashes($array_prop['properties']));

    	$pars = new parse_properties();
    	$pars->set_metod();
    	if (isset($array_prop['param_array']))
            $pars->set_value_default(unserialize(stripslashes($array_prop['param_array'])));
		$pars->max_chars_label(40);

		$html = $template['body'];
        //Теперь свойства действия
        $ap_html  = array();
        $ap_code = array();
        $fieldNames=array();

    	foreach ($serialize as $val)
    	{
    	    $value = $pars->create_html($val);
            $ap_html[] = $value['code_html'];
            $ap_code[] = $value['code_js'];
            $fieldNames[]= '"'.$val['name'].'"';

        }
        $html = str_replace('%action_param_html%', implode("\n", $ap_html), $html);
        $html = str_replace('%action_param_code%', implode("\n", $ap_code), $html);

        //new!
        $html = str_replace('%fieldNames%', implode(",",$fieldNames), $html);

		//похожий URL возвращается методом action_save, чтобы в случае если это новое
		//действие, следующее сохранение сработало нормально
		$url = $kernel->pub_redirect_for_form('action_save&id_action='.$id_action.'&id_metod='.$id_metod);
		$html = str_replace("[#action_name#]", htmlspecialchars(trim($array_prop['caption'])), $html);
		$html = str_replace("%url_action%", $url, $html);
    	return $html;
	}


    /**
    * Регистрация публичных методов модуля
    *
    * Производит регистрацию (в таблице БД) всех методов базового модуля, из которых
    * потом будут строиться действия.
    * Также возвращает список параметров этого модуля.
    * Возможен вызов в режиме инсталяции новых модулей и в режиме
    * @param string $id Индентификатор базового модуля
    * @param install_modules $install Объект дочернего класса от install_modules, описанный в install.php данного модуля.
    * @param boolean $reinstall переинсталяция?
    * @return array
    */
	function set_admin_metods($id, $install, $reinstall = false)
    {
		global $kernel;

		if ($reinstall)
        {
            $query = "DELETE FROM ".$kernel->pub_prefix_get()."_metods WHERE (id_module = '".$id."')";
            $kernel->runSQL($query);
        }

        $metods_admin = $install->get_public_metod();
        foreach ($metods_admin as $val)
        {
            //Значит что это реинстал и перед тем как добавлять
            //методы нужно удалить старые
			$query = "INSERT INTO ".$kernel->pub_prefix_get()."_metods
			             (id_module,
			              metod,
			              caption,
			              properties,
			              flag_admin)
			          VALUES
			             ('".$id."',
			              '".$val['id']."',
			              '".$val['name']."',
			              '".mysql_real_escape_string(serialize($val['parametr']))."',
			              1
                         )";
            $kernel->runSQL($query);
        }
    }



	/**
	 * Инсталирует новый базовый модуль в системе
	 */
	function modules_base_modul_install()
    {
		global $kernel;

        $id_modul = $kernel->pub_httppost_get('modul_install');
        $path = "modules/".$id_modul;

        if (empty($id_modul))
            return;

        //Подключим инсталятор
        $install = new install_modules();
        include $path.'/install.php';

        //Пропишем методы, которыми пользователь может пользоваться при
        //конструирование макросов а так же вернем параметры самого модуля
        //и параметры, которые модулю необходимы для каждой страницы
    	$this->set_admin_metods($id_modul, $install);

        $query = "INSERT INTO `".$kernel->pub_prefix_get()."_modules` VALUES
						(
                            '".$id_modul."',
                            NULL,
                            '".$install->get_name()."',
                            ".$install->get_admin_interface().",
                            '".serialize($install->get_modul_properties())."',
                            '".serialize($install->page_properties_get())."',
                            '".serialize($install->get_admin_acces_label())."',
                            '".serialize($install->return_default_properties())."',
                            NULL

                        )
                        ";
        $kernel->runSQL($query);

        //Теперь проведем инсталяцию языковых переменных
        $lang_install = new mysql_table();
		$lang_install->add_langauge($path.'/lang');

		//Проверим необходимость добавления к пользователю Фротнофиса новых полей базовым модулем
        manager_users::add_field_for_user($install->get_users_properties_one(), $id_modul);

		//Вызов непосредственного инсталятора для базового модуля
        //Прежде всего узнаем список существующих mysql таблиц
        //что бы потом узнать, какие таблицы создал модуль и запомнить их
        $this->mysqltable_create_list();
		$install->install($id_modul);
		$this->mysqltable_set($id_modul);

        //Теперь перепишем файлы шаблонов в папку дизайн

		//Проверим необходимость создания потомков базового модуля.
        $mcopy = $install->get_module_copy();
		if (count($mcopy) > 0)
		{
			foreach ($mcopy as $val)
			{
				$id_new_modul = $this->modules_children_install($id_modul, $val['name'], $install);

				//Проверим, возможно надо проинсталирвоать дейсвтия у этого потомка
				if (isset($val['action']) && !empty($val['action']))
				{
					foreach ($val['action'] as $action_param)
					{
					    $prop_metod = $this->metod_info_get(1, $id_modul, $action_param['id_metod']);
					    $ap = array();
					    if (isset($action_param['param']) && (!empty($action_param['param'])))
					       $ap = $action_param['param'];

						$this->action_create_new($id_new_modul, $prop_metod, $ap, true, $action_param['caption']);
					}
				}

				//Возможно прописаны значения свойств самого модуля (базового, или дочерних)
				if (isset($val['properties']) && !empty($val['properties']))
				{
        			$serialize = addslashes(serialize($val['properties']));

        			$query = 'UPDATE `'.$kernel->pub_prefix_get().'_modules`
        	      			  SET serialize = "'.$serialize.'"
                  			  WHERE (id = "'.$id_new_modul.'")';

					$kernel->runSQL($query);
				}

				//Возможно, надо проинсталировать свойтва модуля, для каких-то страниц
				if (isset($val['properties_in_page']) && !empty($val['properties_in_page']))
				{
					foreach ($val['properties_in_page'] as $id_page => $page_proper)
					{
						foreach ($page_proper as $name_prop => $value_prop)
							$kernel->priv_page_property_set($id_page, $name_prop, $value_prop);
					}
				}
			}
		}
        return;
    }


    /**
    * Производит регистрацию потомка базового модуля
    *
    * Функция вызывается при создании дочернего модуля из интерфейса сайта, а так же
    * при инсталяции базового модуля, когда вместе с ним (базовым) указана сразу инсталяция дочерних
    * @param string $id_base ID базового модуля, для которого нужно сделать детей (в автоматич. режиме)
	* @param string $new_name_id ID языковой переменной (в автоматич. режиме)
	* @param install_modules|boolean $install
	* @return string ID вновь созданного дочернего модуля
    */
	function modules_children_install($id_base = '', $new_name_id = '', $install = false)
    {
		global $kernel;


        if (!empty($id_base))
        	$info = $this->return_info_modules($id_base);
        else
        	$info = $this->return_info_modules($kernel->pub_httppost_get('node'));

        $base_modul = $info['id'];
        if (!empty($info['parent_id']))
        {
        	$base_modul = $info['parent_id'];
            $info = $this->return_info_modules($info['parent_id']);
        }
        //В $info в любом случае находится информация по базовому модулю.
        //в независимости от места вызова функции (так как клик мог быть
        //совершен и по базовому модулю и по уже существующему дочернему

        //сгенерируем id нового экземпляра модуля
        $num_id = 1;
        while (true)
        {
        	$query = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_modules`
                  	  WHERE id = "'.$base_modul.$num_id.'"';
        	$result = $kernel->runSQL($query);
        	if (mysql_num_rows($result) == 0 )
        		break;
        	else
        		$num_id++;
        }


        //Создадим id языковой переменной для нового дочернего модуля
        //если это режим автоматического создания, то его id , будет переданно
        //в метод и его не надо добавлять в базу...
        if (!empty($new_name_id))
        	$full_name = $new_name_id;
        else
        {
        	$full_name = trim($info['full_name']);
        	$full_name = str_replace("[#", "", $full_name);
        	$full_name = str_replace("#]", "", $full_name);
			$full_name_search = $full_name;
			$full_name = $full_name.'_'.$num_id;

			//Теперь проверим, прописаныли у базового модуля языковые переменные
        	//его названия, и если да, то скопируем их для нового потомка
        	$array_text = $kernel->priv_textlabel_values_get($full_name_search);
        	if (!empty($array_text))
        	{
				foreach ($array_text as $key => $val)
            	{
		        	$lang_install = new mysql_table();
					$lang_install->add_data_langauge($key, $full_name, $val.'_name'.$num_id);
				}
        	}
        }

        //Производим вызов инстялтора дочернего модуля
        if ($install === false)
        	include 'modules/'.$base_modul.'/install.php';


        //Сохраним существующие таблицы. Если вызов этого метода происходит из
        //инсталятора базового модуля, то значит этот метод уже вызывался
        //и таблицы не обновятся
        $this->mysqltable_create_list();
        $install->install_children($base_modul.$num_id);

        //А вот теперь сохраним состояни таблиц после вызова инсталятора
        //так как возможно вызов происходил из инсталятора базового модуля и надо
        //понять какие таблицы были созданы дочерними а какие - базовым

		//Проверим необходимость добавления к пользователям новых полей
		//$arr = $install->get_users_properties_multi();
        manager_users::add_field_for_user($install->get_users_properties_multi(),$base_modul.$num_id);

		//Регистрируем модуль в таблице инсталированных модулей.
    	$query = "INSERT INTO `".$kernel->pub_prefix_get()."_modules` VALUES
						(
                            '".$base_modul.$num_id."',
                            '".$base_modul."',
                            '[#".$full_name."#]',
                            ".$info['type_admin'].",
                            '".$info['properties']."',
                            '".$info['properties_page']."',
                            '".$info['acces_label']."',
                            NULL,
                            NULL
                        )
                        ";

        $result = $kernel->runSQL($query);

        //Теперь обновим информацию о созданных таблицах
        $this->mysqltable_set($base_modul.$num_id);

        if ($result)
        	return $base_modul.$num_id;
        else
            return 0;
    }


    /**
    * Возвращает список модулей, которые ещё можно проинсталировать
    * @return string
    */
	function modules_form_installed_get()
    {
        global $kernel;

        $template = $kernel->pub_template_parse("admin/templates/default/modules_installbase.html");
        $html = $template['body'];

        //Посмотрим что уже проинсталированно.
		$query = 'SELECT id, parent_id
        	      FROM `'.$kernel->pub_prefix_get().'_modules`
                  WHERE parent_id is NULL';

		$result = $kernel->runSQL($query);

        $alredy_install = array();
        while ($row = mysql_fetch_assoc($result))
        	$alredy_install[$row['id']] = 1;

        //Теперь сформируем строку для select-а
        //$out = '<option class_name="" value="" my_path="">- -</option>';
        $dir = dir('modules');
        $out = array();
        while ($file = $dir->read())
        {
        	$patch = 'modules/'.$file;
        	if ((!is_dir($patch)) || ($file == '.') || ($file == '..'))
        	   continue;

            //Прежде всего проверим, что бы кроме инстала там был
            //и оснваной класс модуля
        	if (!file_exists($patch.'/install.php') || !file_exists($patch.'/'.$file.'.class.php'))
        	   continue;

        	//Проверки прошли, теперь подключим инстал, что бы узнать как модуль называется
        	$install = new install_modules();
            include $patch.'/install.php';

            $id = $install->get_id_modul();
            if ((empty($id)) || (isset($alredy_install[$id])))
            	continue;

            $out[$id] = $this->return_name_lang($patch, $install->get_name());

        }
        $dir->close();
        //Отсортируем список модулей
        asort($out);
        $modules_rows = '';
        foreach($out as $mk=>$ml)
        {
            $line = $template['module_row'];
            $line = str_replace("%key%", $mk, $line);
            $line = str_replace("%label%", $ml, $line);
            $modules_rows.=$line;
        }
        $html = str_replace("%modules_rows%", $modules_rows, $html);
        //$html = str_replace("%store%", $kernel->pub_array_convert_form($out), $html); //
        $html = str_replace("%url_action%", $kernel->pub_redirect_for_form('modules_base_install'), $html);
		return $html;

    }


    /*
        @return string
        @param $path String Путь к каталогу базового модуля
        @param $name String название модуля.
        @desc Возращает имя модуля по умолчанию, в зависимости от текущего языка.
    */
    function return_name_lang($path, $name)
    {
    	global $kernel;

        $return_name = $name;

        $str_file = $path."/lang/".$kernel->priv_langauge_current_get().".php";
        if (file_exists($str_file))
        {
			include $str_file;
			$name = str_replace("[#","",$name);
			$name = str_replace("#]","",$name);
            /** @var $il array */
            $return_name = $il[$name];
            unset($il);
        }

		return $return_name;

    }

    /**
     * Сохраняет отредактированные свойства модуля
     * @return string
     */
	function modules_properties_save()
    {
        global $kernel;
        $moduleid = $kernel->pub_module_id_get();
        $minfo = $this->return_info_modules($moduleid);
        $mpropsdb = unserialize($minfo['properties']);
        $modprops = array();
        foreach ($mpropsdb as $mdb)
        {
            $modprops[$mdb['name']]=$mdb;
        }
        //Сохраним раскодируем языковые переменные
        $array_lang = $kernel->pub_httppost_get("langname");
        foreach ($array_lang as $id_var_lang => $val)
        {
            $kernel->pub_textlabels_update($id_var_lang, $val);
        }

        //Теперь сохраняем сами свойства
        $array_form = $kernel->pub_httppost_get('properties');
        if (empty($array_form))
            $array_form = array();
        $array_inheritance = $kernel->pub_httppost_get('properties_cb');
        foreach ($array_form as $key => $value)
        {
            if (isset($modprops[$key]) && $modprops[$key]['type']=='check') //это свойство типа чекбокс
            {
                if (empty($value) || $value=="false")
                    $array_form[$key]=false;
                else
                    $array_form[$key]=true;
            }
            else
                $array_form[$key] = $value;
            if (isset($array_inheritance['ppf_'.$key]) && !empty($array_inheritance['ppf_'.$key]))
            {
                unset($array_form[$key]);
                continue;
            }
        }
        $query = 'UPDATE `'.$kernel->pub_prefix_get().'_modules`
        	      SET serialize = "'.mysql_real_escape_string(serialize($array_form)).'"
                  WHERE (id = "'.$moduleid.'")';
		$kernel->runSQL($query);
        return $kernel->pub_json_encode(array("success"=>true,"info"=>"[#kernel_ajax_data_saved_ok#]"));
    }


    /**
     *  Возвращет всю информацию о выбранном модуле
     *  @param $id integer
     *  @return array
    */
	function return_info_modules($id)
    {
		global $kernel;
        return $kernel->db_get_record_simple("_modules","`id`='".$id."'");
    }


	/**
	 * Возвращет информацию о выбранном методе в выбранном модуле
	 *
	 * @param integer $type_query Определяет тип поиска нужного метода. 0 - по ID строки. 1 - по ID модуля и метода
	 * @param String $param1 ID строки в таблице или ID модуля
	 * @param String $param2 ID метода
	 * @return array
	 */
	function metod_info_get($type_query, $param1 = "", $param2 = "")
    {
		global $kernel;
		if ($type_query == 0)
    		return $kernel->db_get_record_simple("_metods", "id = ".$param1);
    	else//if ($type_query == 1)
            return $kernel->db_get_record_simple("_metods", '((id_module = "'.trim($param1).'") and (metod = "'.trim($param2).'"))');
    }


    /**
     * Возвращает массив уровней доступа по объектам
     *
     * Возвращаеммый массив содержит структуруподчиения. То есть с учётом подчинения модулей
     *
     * @param String $id ID конкретного модуля, если необходимо
     * @return array
     */
    function get_access($id = '')
    {
    	global $kernel;
    	$arr = array();
    	$query = 'SELECT acces_label, parent_id, id, full_name, type_admin
                  FROM `'.$kernel->pub_prefix_get().'_modules` ';

    	if (!empty($id))
    		$query .= 'WHERE (id = '.$id.')';

        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
        {
        	if (empty($row['parent_id']))
        	{
        		$arr[$row['id']]['caption'] = $row['full_name'];
        		if (intval($row['type_admin']) < 2)
                    $arr[$row['id']]['access'] = unserialize($row['acces_label']);
        	}
        	elseif ((!empty($row['parent_id'])) && (intval($row['type_admin'])>1))
        	{
        		$arr[$row['parent_id']]['children'][$row['id']]['caption'] = $row['full_name'];
        		$arr[$row['parent_id']]['children'][$row['id']]['access'] = unserialize($row['acces_label']);
        	}
        }
        return $arr;

    }


    /**
    * Возвращет информацию о выбранном макроcе
    * @param integer $id
    * @return array
    **/
	function action_info_get($id)
    {
		global $kernel;
        return $kernel->db_get_record_simple("_action","id=".$id);
    }


    /**
    * Подготоавливает информацию для построения дерева модулей
    * @param string $id_modul
    * @return array
    */
    function modules_structure_get($id_modul)
    {
        $out = array();
        $array_modul = $this->return_modules($id_modul, true);
        if (count($array_modul)>0)
        {
        	foreach ($array_modul as $key => $val)
            {
                $out[] = array(
                    'data'     => htmlentities($val['caption'], ENT_QUOTES, 'UTF-8'),
                    'attr'=>array("id"=>$key),
                );
            }
        }
        return $out;
    }

    function modules_structure_all_get($expanded = false)
    {
        $modules = $this->return_modules(false, true);

        $array = array();

        foreach ($modules as $id => $property)
        {
            $children = $this->return_modules($id);

            $data = array(
                'data'     => htmlentities($property['caption'], ENT_QUOTES, 'UTF-8'),
                'attr'=>array('id'=>$id),
            );

            if (empty($children))
            {
                $data['state'] = "closed";
                $data['attr']['rel'] = "default";
            }
            else
            {
                $data['state'] = "open";
                $data['children'] = $this->modules_prepeare_children($children);
                $data['attr']['rel'] = "folder";
            }

            $array[] = $data;
        }

        return $array;
    }

    function modules_prepeare_children($modules)
    {
    	$array = array();

    	foreach ($modules as $id => $property)
    	{
            $array[] = array(
                'attr'=>array('id'=>$id,"rel"=>"default"),
                'data' => htmlentities($property['caption'], ENT_QUOTES, 'UTF-8'),
            );
    	}

    	return $array;
    }


    /**
     * Формирует интерфейс для настройки параметров модуля
     *
     * Выводит на экран страницу для редактирования настроек модуля. Причем для базового модуля
     * нам не нужны действия
     * @return string
     */
    function show_properties_module()
    {
    	global $kernel;

    	$curent_modules = $this->return_info_modules($kernel->pub_module_id_get());

		$template = $kernel->pub_template_parse("admin/templates/default/admin_modules.html");
		$html = $template['body'];

        if ($curent_modules['parent_id'])
        {
            $module_actions_block = $template['module_actions_block'];
            $module_actions_header = $template['module_actions_header'];
        }
        else
        {
            $module_actions_block = '';
            $module_actions_header = '';
        }
        $html = str_replace('%module_actions_block%', $module_actions_block, $html);
        $html = str_replace('%module_actions_header%',$module_actions_header,$html);

		//Сначала построим название модуля во всех языках
        $id_name = mb_substr($curent_modules['full_name'],2,-2);
		$array_text = $kernel->priv_textlabel_values_get($id_name);

		$arr = array();
		$i = 0;
		foreach ($array_text as $lang_code => $lang_name)
        {
            $line = $template['names_module'];
            $line = str_replace("[#code#]", $lang_code, $line);
            $line = str_replace("[#value#]", $lang_name, $line);
            $line = str_replace("%num%", $i, $line);
            $line = str_replace("%id_name%", $id_name, $line);
			$arr[] = $line;
			$i++;
        }
        $html = str_replace("[#modules_form_edit_all_name#]", join("",$arr), $html);
        $html = str_replace('%id_modul%', $kernel->pub_module_id_get(), $html);

        // ============================================================
        //Теперь свойства модуля c учётом наследования
    	$serialize = array();
    	if (isset($curent_modules['properties']))
    		$serialize = unserialize($curent_modules['properties']);
        $pars = new parse_properties();
        //Установим id модуля с которым мы работаем и ID родительского модуля
        $pars->set_modul($curent_modules['id'], $curent_modules['parent_id']);
        //$pars->max_chars_label(40);

        $prop_html  = array();
        $prop_code  = array();

    	foreach ($serialize as $val)
    	{
    	    $value = $pars->create_html($val);
            $prop_html[]  = $value['code_html'];
            $prop_code[]  = $value['code_js'];
        }
        //$html = str_replace ("%str_prop_modul%", addslashes(implode("", $prop_html)), $html);
        $html = str_replace ("%str_prop_modul%", implode("", $prop_html), $html);
        $html = str_replace ("%prop_modul_code%", implode("\n\n", $prop_code), $html);



        // ============================================================
        //Теперь построили часть, отвечающую за вывод существующих действий и
        //создания новых
        $html_action ='';
    	if (!empty($curent_modules['parent_id']))
    	{
    	    $html_action = $template['action_modules'];
    	    $arr = $this->return_metods_module($curent_modules['parent_id'], true);

    	    //Сразу заменим языковые переменные модулей тут,
    	    //что бы в них кавычки были кавычками, без спец символов
    	    $tmp = implode('","', $arr);
            $tmp = $kernel->priv_page_textlabels_replace($tmp, 2);
            $tmp = explode('","', $tmp);
            $i = 0;
            foreach ($arr as $key => $val)
            {
                $arr[$key] = $tmp[$i];
                $i++;
            }
            //В результате мы заменили названия методов с слешами на кавычках (если они есть)
    	    $html_action = str_replace('%array_exist_metod%' , $kernel->pub_array_convert_form($arr), $html_action);

    	}
        $html = str_replace ("[#action_modules#]", $html_action, $html);

        //Теперь, если есть список уже созданных действий сформируем его и вставим
        //он формуется как простой HTML и добавляется в форму
        $html = str_replace ("[#html_action#]", $this->action_exist_get($kernel->pub_module_id_get(), $template), $html);
        $html = str_replace ("[#modules_lable_info#]", '[#modules_lable_info#] "'.$curent_modules['full_name'].'"', $html);
        $html = str_replace ("%url_action%", $kernel->pub_redirect_for_form('modul_prop_save'), $html);
    	return $html;
    }


    /**
    * @return array
    * @param $id_modul string
    * @desc Возвращает массив имеющихся макросов.
    **/
	function list_array_macros($id_modul)
	{
		global $kernel;

		$ret_macros = array();
        $query = 'SELECT `action`.* '
        . ' FROM `'.$kernel->pub_prefix_get().'_action` as `action` '
        . ' LEFT JOIN (`'.$kernel->pub_prefix_get().'_all_lang` as `lang`) '
        . ' ON (CONCAT("[#",`lang`.`element`,"#]") = `action`.`caption` AND `lang`.`lang` = "'.$kernel->priv_langauge_current_get().'") '
        . ' WHERE id_module = "'.$id_modul.'" '
        . ' ORDER BY (CASE '
        . '     WHEN `lang`.`text` IS NOT NULL THEN `lang`.`text` '
        . '     ELSE `action`.`caption` '
        . ' END) ASC';

        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
        {
        	$ret_macros[] = $row;
        }
        return $ret_macros;
	}


    /**
    * Формирует HTML код со списком существующих действий заданного модуля
    *
    * Сформированый код добавляется в форму со свойствами модуля
    * @param  string $id_modul ID модуля, чьи действия нужно вывести
    * @param  array $template HTML шаблон одно строки действия
    * @return string
    **/
	function action_exist_get($id_modul, $template)
	{
        $actions = $this->list_array_macros($id_modul);
        if (empty($actions))
        	return '';

        //Узнаем массив публичных методов, доступных в модуле
        $curent_modul = $this->return_info_modules($id_modul);
        $array_metod = $this->return_metods_module($curent_modul['parent_id']);

        $html_str = '';
        foreach ($actions  as $val)
        {
            $tmp = $template['str_action'];
            $tmp = str_replace("%name%",        $val['caption'],                $tmp);
            $tmp = str_replace("%source_name%", $array_metod[$val['link_str']], $tmp);
            $tmp = str_replace("%id_action%",   $val['id'],                     $tmp);
        	$html_str .= $tmp;
        }
        $html = $template['str_action_begin'];
        $html = str_replace("[#html_action#]", $html_str, $html);
        $html .= $template['str_action_end'];
		return $html;

	}

    /**
    *    @return array
    *    @param string $id_modul
    *    @param Boolean $ret_id
    *    @desc Возвращает массив методов заданного модуля.
    **/
    function return_metods_module($id_modul, $ret_id = false)
    {
		global $kernel;
    	$query = 'SELECT * FROM '.$kernel->pub_prefix_get().'_metods
				  WHERE id_module = "'.$id_modul.'"';
        $result = $kernel->runSQL($query);
		$array_return = array();
        while ($row = mysql_fetch_assoc($result))
        {
        	if ($ret_id)
				$array_return[$row['id']] = addslashes($row['caption']);
			else
				$array_return[$row['metod']] = addslashes($row['caption']);
        }
        return $array_return;
    }


    /**
    * Возвращет массив, подключенных модулей
    *
    * Если параметр не задан, то возвращаются все подключённые модули,
    * при этом, если $only_base задан в true  - то будут возвращены только
    * базовые модули
    * @param string $id_modul ID базвого модуля, для которого нужно выбрать дочерние
    * @param boolean $only_base Признак того, что когда не задан ID, необходимо получить только базовые модули
    * @return array
	*
    */
    function return_modules($id_modul = "", $only_base = false)
    {
    	global $kernel;

		$query = " SELECT id, parent_id, full_name
        		   FROM `".$kernel->pub_prefix_get()."_modules`
        		   WHERE
        		   `id` != 'kernel'
        		   AND `id` != 'structure'
                  ";

		if ($id_modul)
			$query .= 'AND parent_id = "'.$id_modul.'"';
		elseif ((!$id_modul) && $only_base)
            $query .= 'AND parent_id is NULL';


        $result = $kernel->runSQL($query);

		$array_return = array();
		if ($result)
		{
            while ($row = mysql_fetch_assoc($result))
            {
                $array_return[$row['id']]['id_parent'] = $row['parent_id'];
                $array_return[$row['id']]['caption'] = stripslashes($row['full_name']);
                $array_return[$row['id']]['count_macros'] = 0;
                //Так же узнаем есть ли у него макросы
                $array_macros = $this->list_array_macros($row['id']);
                if (!empty($array_macros))
                    $array_return[$row['id']]['count_macros'] = count($array_macros);
            }
		}
        return $array_return;
    }

    /**
     * Возвращет массив готовых HTML строк для каждого дочернего модуля, в которых
       создан код свойств страницы
     *
     * @param Boolean $page_is_main
     * @return Array
     */
    function return_page_properties_all_modules($page_is_main)
    {
    	global $kernel;
    	$arr = array();
    	$all_module = $this->return_modules();
    	foreach ($all_module as $key => $val)
    	{
    	    if (empty($val['id_parent']))
    	       continue;

			$curent_metod = $this->return_info_modules($key);
			$serialize = array();

			if (isset($curent_metod['properties_page']))
				$serialize = unserialize($curent_metod['properties_page']);

			//Если у модуля нет свойств к странице, пропустим этот модуль
		    if (empty($serialize))
                continue;


			//Теперь сформировать сам код свойства для данного модуля
			$pars = new parse_properties();
			$pars->set_modul($curent_metod['id']);
			$pars->set_modul_caption($curent_metod['full_name']);

			$pars->set_page($kernel->pub_page_current_get(), $page_is_main);

			$array_prop = array();
			foreach ($serialize as $sval)
			    $array_prop[] = $pars->create_html($sval);
			$arr[$curent_metod['full_name']] = $array_prop;
    	}
    	return $arr;
    }

    /**
    *    @return array
    *    @desc Возвращет массив, всех возможных свойств страницы, которые прописал каждый модуль
    */
    function return_all_properties_page_all_modules()
    {
    	$all_module = $this->return_modules();
    	$arr = array();
    	foreach ($all_module as $key => $val)
    	{
    		if (!empty($val['id_parent']))
    		{
    			$curent_metod = $this->return_info_modules($key);
    			$serialize = array();
				if (isset($curent_metod['properties_page']))
					$serialize = unserialize($curent_metod['properties_page']);

				if (!empty($serialize))
    				foreach ($serialize as $sval)
    					$arr[] = $curent_metod['id'].'_'.$sval['name'];
    		}
    	}
    	return $arr;
    }

    /**
     * Запоминает таблицы, имеющиеся в базе mySql на момент вызова
     * @param boolean $update
     * @access private
     * @return array
     */
    function mysqltable_create_list($update = true)
    {
        global $kernel;

        $query = "SHOW TABLES FROM `".DB_BASENAME."`";
        $result = $kernel->runSQL($query);
        $arr = array();
        while ($row = mysql_fetch_array($result))
        {
            if ($update)
                $this->mysqltable_compare[$row[0]] = $row[0];

            $arr[$row[0]] = $row[0];
        }
        if ($update)
            return $this->mysqltable_compare;
        else
            return $arr;

    }

    /**
     * Определяет какие таблицы создал модуль
     *
     * Работает только в процессе инсталяции
     * @access private
     * @return array
     */
    function mysqltable_compare()
    {
        $tmp = $this->mysqltable_create_list(false);
        foreach ($this->mysqltable_compare as $id_table)
            unset($tmp[$id_table]);
        return $tmp;
    }

    /**
     * Записывает в параметры модуля таблицы, которые он насоздавал
     *
     * @param string $id_modul Указывается ID модуля
     * @access private
     * @return array
     */

    function mysqltable_set($id_modul)
    {
        global $kernel;

        $cur_arr = $kernel->pub_module_serial_get($id_modul);
        $arr = $this->mysqltable_compare($id_modul);
        if (!empty($arr))
        {
            $arr2 = array();
            //Перед тем как это делать, уберем из таблиц префикс.
            foreach ($arr as $val)
            {
                $str = preg_replace("/^".$kernel->pub_prefix_get()."/i", "", $val);
                $arr2[$str] = $str;
            }
            $cur_arr['table_mysql'] = $arr2;
            $kernel->pub_module_serial_set($cur_arr, $id_modul);
        }
    }

    /**
     * Функций обновления таблиц mysql в момент переинсталяции модуля
     *
     * @param string $id_modul ID модуля с которым работаем
     * @param install_modules $install объект класс инсталл
     * @param boolean $is_base признак того что это базовый модуль
     * @access private
     */

    function mysqltable_reinstall($id_modul, $install, $is_base)
    {
        global $kernel;
        //Прежде всего определим есть ли таблицы, которые надо менять

        $table_mysql = $kernel->pub_module_serial_get($id_modul);
        if ((!isset($table_mysql['table_mysql'])) || (empty($table_mysql['table_mysql'])))
            return;

        $table_mysql = $table_mysql['table_mysql'];

        //Имея массив таблиц, переименуем их
        foreach ($table_mysql as $val)
        {
            $query = 'RENAME TABLE `'.DB_BASENAME.'`.`'.$kernel->pub_prefix_get().$val.'` TO `'.DB_BASENAME.'`.`_tmp_'.$kernel->pub_prefix_get().$val.'`';
            $kernel->runSQL($query);
        }

        //Так, теперь можно вызывать инсталятор, что бы он создал то что ему нужно
        if ($is_base)
            $install->install($id_modul, true);
        else
            $install->install_children($id_modul, true);

        //Так модуль создал то что он хотел, теперь нужно перенести данные из временных
        //таблиц в новые, с учетом того, что возможно части данных не стало, или появились
        //новые поля
        foreach ($table_mysql as $val)
        {
            //Сначала определим вообще есть ли сама таблица
            $query = "SHOW TABLES FROM `".DB_BASENAME."`";
            $result = $kernel->runSQL($query);
            $table_exist = false;
            while ($row = mysql_fetch_array($result))
            {
                if ($row[0] == $kernel->pub_prefix_get().$val)
                    $table_exist = true;

            }
            //Если такой таблицы не стало то ничего не переносим (во всяком случае пока)
            if (!$table_exist)
                continue;

            //Узнаем какие поля у нас были в старой табличке
            $feilds = array();
            $tmp_feilds = array();
            $query = "SHOW COLUMNS FROM `_tmp_".$kernel->pub_prefix_get().$val."`";
            $result = $kernel->runSQL($query);
            while ($row = mysql_fetch_assoc($result))
                $tmp_feilds[$row['Field']] = $row['Field'];


            $query = "SHOW COLUMNS FROM `".$kernel->pub_prefix_get().$val."`";
            $result = $kernel->runSQL($query);
            while ($row = mysql_fetch_assoc($result))
            {
                if (isset($tmp_feilds[$row['Field']]))
                    $feilds[$row['Field']] = '`'.$row['Field'].'`';
            }

            //Теперь можно формировать строчки с добавления данных в таблицу
            //будем добавлять только те поля - значения которых у нас остались
            //$feilds
            $str_add = array();
            $query = 'SELECT '.join(',', $feilds).' FROM `_tmp_'.$kernel->pub_prefix_get().$val."`";
            $result = $kernel->runSQL($query);

            //Порядок следования будет такой же, как порядок задачиполей
            while ($row = mysql_fetch_assoc($result))
            {
                foreach ($row as $rkey => $rval)
                    $row[$rkey] = addslashes($rval);

                $str_add[] = '"'.join('","',$row).'"';
            }
            //Ну и теперь общий запрос на добавление
            if (!empty($str_add))
            {
                $query = "REPLACE INTO `".$kernel->pub_prefix_get().$val."` (".join(',', $feilds).")
                          VALUE (".join('),(', $str_add).")";

                $kernel->runSQL($query);
            }

            //ну и теперь удалим старую таблицу
            $query = "DROP TABLE `_tmp_".$kernel->pub_prefix_get().$val."`";
            $kernel->runSQL($query);
        }
    }

    /**
     * Возвращает количество модулей, которые имеют административный интрефейс
     *
     * Список возвращается с учётом назначенных прав для данного администратора
     * @access private;
     * @return array;
     *
     */
    function priv_modules_admin_interface_count()
    {
        global $kernel;
    	$query = "SELECT * FROM `".$kernel->pub_prefix_get()."_modules`
                  WHERE
                  (parent_id is NULL)
                  AND (type_admin > 0)
                  AND (id != 'kernel')";
        $result = $kernel->runSQL($query);
        $menus = array();
        while ($row = mysql_fetch_assoc($result))
        {
        	$show = $kernel->priv_admin_access_for_group_get('',$row['id']);
    		if (!$show)
    			continue;
			$menus[$row['id']] = $row['full_name'];
        }
		return $menus;
    }
}
?>