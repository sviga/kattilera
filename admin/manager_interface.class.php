<?php
/**
 * Управляет входом в административный интерфейс.
 *
 * Создаёт фреймы для отображения интерфейса а так же отвечает за  вызов основных сервисов
 * для редактирование и сохранения контента, показа карты сайта, для выбора страницы.
 * По версии данного класса определяется версия административного интерфейса
 * @name manager_interface
 * @package AdminInterface
 * @copyright ArtProm (с) 2001-2012
 * @version 3.0
 */

class manager_interface
{
    private $main_menu_template = array();
    private $main_menu = array
    (
        'global_prop' => array('name' => '[#global_properties_CMS#]', 'count_panel' => 1, 'img' => 'settings.gif'),
        'polzovateli' => array('name' => '[#top_menu_items3_main_admin#]', 'count_panel' => 1, 'img' => 'admins.gif'),
        'modules' => array('name' => '[#top_menu_items2_main_admin#]', 'count_panel' => 1, 'img' => 'moduls.gif'),
        'structure' => array('name' => '[#top_menu_items1_main_admin#]', 'count_panel' => 1, 'img' => 'structure.gif'),
        'stat' => array('name' => '[#top_menu_items4_main_admin#]', 'count_panel' => 1, 'img' => 'statistic.gif')
    );


    function manager_interface()
    {
        global $kernel;

        //Распарсим шаблон главного меню
        $this->main_menu_template = $kernel->pub_template_parse("admin/templates/default/topmenu.html");
    }

    /**
     * Точка входа
     *
     * Проверяет возможность отображения административного интерфейса для
     * пользователя или же необходимость показывать форму авторизации
     * @return void
     */
    function start()
    {
        global $kernel;
        if ($kernel->priv_admin_current_get())
        {
            //активируем kcfinder
            $uploadRelPath="/content";
            $_SESSION['KCFINDER'] = array();
            $_SESSION['KCFINDER']['disabled'] = false;
            $_SESSION['KCFINDER']['uploadURL'] = $uploadRelPath;
            $_SESSION['KCFINDER']['uploadDir'] = $kernel->pub_site_root_get().$uploadRelPath;

            $kernel->priv_session_vars_set();
            $this->show_backoffice();
        }
        else
        {
            if (isset($_POST['action']) && $_POST['action'] == 'registration' )
                $kernel->priv_admin_register($_POST['login'], $_POST['pwd']);
            else
                $this->show_reg_form();
        }
    }


    /**
    * Выход из административного интерфейса
    *
    * Осуществялет выход из административного интерфейса с очиской сессии
    * @return void
	*/
    function exit_backofice()
    {
        global $kernel;
        $sql = "UPDATE `".$kernel->pub_prefix_get()."_admin_trace` SET time = '' WHERE id_admin='".$kernel->priv_admin_id_current_get()."'";
        $kernel->runSQL($sql);

        $kernel->priv_session_empty();
        $_SESSION['KCFINDER']['disabled'] = true;//отключаем kcfinder
        $kernel->pub_redirect_refresh_global('/');
    }


    /**
    * Выводит форму авторизации администратора сайта
    *
    * Формирует и выводит HTML форму для автаризации администратора сайта
    * @return void
	*/
    function show_reg_form()
    {
        global $kernel;

        $html = file_get_contents("admin/templates/default/admin_authorisation.html");
        $html = str_replace ("[#server#]", $_SERVER['HTTP_HOST'], $html);

        $errore = '';
        if (isset($_SESSION['vars_kernel']['errore_register']) && !empty($_SESSION['vars_kernel']['errore_register']))
        {
            $errore = trim($_SESSION['vars_kernel']['errore_register']);
            $_SESSION['vars_kernel']['errore_register'] = "";
        }

        $html = str_replace ("[#errore#]", $errore, $html);

        $kernel->priv_output($html);
    }




    /**
    * Управляет основой административного интерфейса
    *
    * Определяет действие, запрашиваемое системой и запускаеи соответсвующий
    * метод, соответствующего класса, который отвечает за это действие
    * @return void
	*/
    function show_backoffice()
    {
        global $kernel;

        //Определим текущее действие
        $action = $kernel->pub_httpget_get('action');
        if (empty($action))
            $action = '';
        //Проверка на то, а что собственно можно показывать
        if (!$kernel->priv_admin_access_for_group_get('', $kernel->pub_section_current_get()))
        {
            //К секциям меню добавим и секции модулей
            $arr = $this->main_menu;
            $arr = array_merge($arr, $kernel->pub_modules_get());
            foreach ($arr as $key => $value)
            {
            	if ($kernel->priv_admin_access_for_group_get('', $key))
            	{
                    $kernel->pub_redirect_refresh_global('/admin/index.php?section='.$key);
                    exit();
            	}
            }
            //Если пришли сюда, значит что не нашли секцию разрешенную секцию основного меню
            //к которой разрешён доступ
            $this->exit_backofice();
            exit();
        }

        //Взависимости от того какое действие указано сделаем то что нужно
        $html = '';
        switch ($action)
        {
            //Совсем новые управляющие элементы
            //Формируем секцию левого меню
            case 'get_left_menu':
            	$html .= $this->left_menu_create();
                break;

            //Необходимо передать управление в соответствующий класс
            //для текущей секции, а он уже определит что ему необходимо показывать
            case 'set_left_menu':
                //Выбрали конкретный элемент левого меню. Его надо сделать текущим.
                //для текущей секции
                $kernel->priv_section_leftmenu_set();

                //А теперь надо вызвать метод Start соответсвующего класс,
                //с тем что бы он определил что необходимо вывести в область конетнта
                $html .= $this->section_get_html();
                $html .= $kernel->priv_debug_get(true);
                break;

            //Вызов редактора контента
            case 'edit_content':
                $out_tmp = $this->priv_edit_content_start();
                $kernel->priv_output($out_tmp, true);
                exit();

            //Сохранения контента, который редактировался в отдельном окне
            case 'save_content':
                $this->priv_edit_content_save();
                print $kernel->pub_json_encode(array("success"=>true));
                exit();

            //Вызов редактора контента, но внутри формы, в составе ифрейма
            case 'edit_content_in':
                //$get     = $kernel->pub_httpget_get();
                $content = new edit_content(true);
                //$content->set_full_form();
                //$content->set_close_editor(CLOSE_WINDOWS_ON_SAVE);
                $kernel->priv_output($content->create(), true);
                exit();

            case 'get_help_content':
                $str_file = 'admin/help/'.$kernel->priv_langauge_current_get().'/'.$kernel->pub_section_current_get().'-'.$kernel->pub_section_leftmenu_get().'.html';
                if (file_exists($str_file))
                    $html = file_get_contents($str_file);
                else
                    $html = 'Раздел помощи отсутствует<font color="#000000"> Файл помощи: '.$str_file.'</font>';
                break;

            //Выход из админки интерфейса
            case 'exit':
                $this->exit_backofice();
                break;

            case 'select_page':
                $obj = new parse_properties();
                $kernel->priv_output($obj->get_structure());
                exit();

            //Построение вызов основного шаблона
			default:
				$html = file_get_contents("admin/templates/default/main.html");
				$html = str_replace('%admins_count%', $this->admin_trace(), $html);

				$html = str_replace('[#main_menu#]'         , $this->top_menu_create()    , $html);
				$html = str_replace("[#server#]"            , $_SERVER['HTTP_HOST']     , $html);
				$html = str_replace('[#modules_menus_tabs#]', $this->modules_tabs_create(), $html);
				$html = str_replace("[#curent_version#]"    , SANTAFOX_VERSION            , $html);
				//Если есть GET запрос, нужно его передать дальше
				$next_get = '';
				$tmp = $kernel->pub_httpget_get();
				if (is_array($tmp) && (count($tmp) > 0))
				{
				    foreach ($tmp as $get_name => $get_value)
				        $next_get[] = $get_name."=".$get_value;

				    $next_get = join("&", $next_get);
				}
				$html = str_replace("[#get_url#]"    , $next_get, $html);

				//Проверим, есть ли обновления
				$get_up = new manager_global_properties();
				$str_update = "";
				if ($get_up->update_get(SANTAFOX_VERSION, true) == 1)
				{
				    $kernel->priv_section_leftmenu_set();
				    $str_update = '<a href="#" onclick="start_interface.main_menu_click(document.getElementById(\'main_menu_global_prop\'),\'global_prop\',\'info_site\');">Есть обновления</a>';
				}
                $html = str_replace("[#version_update#]", $str_update, $html);
				//Самым последним вызываем информацию по сообщениям дебага
				$html = str_replace("[#debug_content#]", $kernel->priv_debug_get(), $html);
                break;

        }
        $kernel->priv_output($html);
    }

    function top_menu_create()
    {
    	global $kernel;
        // Для не ROOT'а убираем "лишние" пункты меню
        //Пока отключили, нужно будет перенести в конструктуро
        /*
        if (!$kernel->priv_admin_is_root())
        {
            foreach ($menus AS $id => $label)
            {
                if (!(($kernel->priv_admin_access_for_group_get("kernel_".$id, "kernel")) || ($kernel->priv_admin_access_for_group_get(null, $id))))
                    unset($menus[$id]);
            }
        }
        */

		$modul = $kernel->pub_section_current_get();
		$arr_menu = array();

		$i = 0;
        foreach ($this->main_menu as $key => $val)
        {
            if (!$kernel->priv_admin_access_for_group_get('', $key))
                continue;

        	if ($modul == $key)
                $str = $this->main_menu_template['activ'];
            else
                $str = $this->main_menu_template['passiv'];

            $str = str_replace("[#name#]", $val['name'] ,$str);
            $str = str_replace("[#count_panel#]", $val['count_panel'], $str);

            $key_panel = "";
            $val_panel = "";
            if ($val['count_panel'] > 1)
            {
                $key_panel = join(',',array_keys($this->main_menu['modules']['panel']));
                $val_panel = join(',',$this->main_menu['modules']['panel']);
            }
            $str = str_replace("[#key_panel#]", $key_panel, $str);
            $str = str_replace("[#val_panel#]", $val_panel, $str);

            $str = str_replace("[#id#]", $key ,$str);
            $str = str_replace("[#link#]", '/admin/index.php?action=set_section&section='.$key ,$str);
            $str = str_replace("%link_admin%", "/admin/templates/default/images/top_icons/".$val['img'] ,$str);

            $arr_menu[] = $str;
            $i++;
        }

        //Создаём HTML
        $html = $this->main_menu_template['begin'];
        $html .= join($this->main_menu_template['delimiter'],$arr_menu);
        $html .= $this->main_menu_template['end'];

        $out = $this->main_menu_template['main'];
        $out = str_replace ("[#main_menu#]", $html, $out);

        //$html = str_replace ("[#admin_trace_count#]", $this->admin_trace(), $html);
        //$html = str_replace ("[#main_top_menu1#]", $this->create_menu_moduls(), $html);
        return $out;
    }

    /**
     * Формирует левое меню
     *
     * В зависимости от текущей секции, создаёт разные объекты и вызывает их методы для
     * формирования левого меню
     * @return string
     */
    function left_menu_create()
    {
        global $kernel;
        $inter = new pub_interface();

        //Узнаем, что за секция и в зависимости от этого выведем соответсвующее левое меню
    	switch ($kernel->pub_section_current_get())
        {
            case 'global_prop':
                $manager = new manager_global_properties();
				break;

        	case 'polzovateli':
                $manager = new manager_users();
				break;

            case 'structure':
                $manager = new manager_structue();
				break;

			case "stat":
			    $manager = new manager_stat();
				break;

            case 'modules':
                $manager = new manager_modules();
				break;

		    //Значит передаваемая секция - это ID модуля
            default:
                $mod = new manager_modules();
                //Необходимо узнать ID родительского модуля и понему подключить
                //класс, так как в качестве секции может быть передан ID дочернего модуля
                $base_modul = false;
                $id_parent = $kernel->pub_section_current_get();
                $arr = $mod->return_info_modules($id_parent);
                //Проверим, а вдруг нет такого модуля или ещё что...
                if (is_array($arr))
                {
                    if (!empty($arr['parent_id']))
                    {
                        $id_parent = $arr['parent_id'];
                        $base_modul = true;
                    }

                    $kernel->priv_module_current_set($kernel->pub_section_current_get(), $base_modul);
                    //Теперь необходимо подключить соответсвующий класс и вызвать построение меню
                    include_once("modules/".$id_parent."/".$id_parent.".class.php");
                    //Создаем объект с основным управляющим модулем для этого класса
                    $manager = new $id_parent();
                } else
                {
                    return 'Неизвестная секция';
                }
                break;
        }

        //Опишем то меню, которое нам необходимо
        @$manager->interface_get_menu($inter);
        //Проверим, можно ли показывать тек. левое меню
        $inter->check_left_element();
        //Создадим и вернём контент левого меню

        return $inter->left_menu_construct();
    }

    /**
     * Формирует контент центральной части
     *
     * Контент центральной части зависит от текуший секции..
     * @return string
     */
    function section_get_html()
    {
        global $kernel;

    	switch ($kernel->pub_section_current_get())
        {
        	case 'polzovateli':
                $manager = new manager_users();
            	break;

        	case 'modules':
                $manager = new manager_modules();
            	break;

            case 'structure':
                $manager = new manager_structue();
                break;

            case 'global_prop':
                $manager = new manager_global_properties();
            	break;

			case "stat":
            	$manager = new manager_stat();
            	break;

            //Это значит что необходимо молучить конент для
            //админки модуля
        	default:
                $mod = new manager_modules();
                //Необходимо узнать ID родительского модуля и понему подключить
                //класс, так как в качестве секции может быть передан ID дочернего модуля
                $id_parent = $kernel->pub_section_current_get();
                $base_modul = false;
                $arr = $mod->return_info_modules($id_parent);
                if (!empty($arr['parent_id']))
                {
                    $id_parent = $arr['parent_id'];
                    $base_modul = true;
                }
                $kernel->priv_module_current_set($kernel->pub_section_current_get(), $base_modul);

                //Теперь необходимо подключить соответсвующий класс и вызвать построение меню
                include_once("modules/".$id_parent."/".$id_parent.".class.php");
                //Создаем объект с основным управляющим модулем для этого класса
                $manager = new $id_parent();
                break;
        }
        //У старых модулей вызывается метод start_admin()
        if (method_exists($manager, "start_admin"))
            $html = $manager->start_admin();
        else
            $html = $manager->start();
        return $html;

    }

    /**
     * Создаёт панельки для вызова админок модулей
     *
     * @return string
     */
    function modules_tabs_create()
    {
        $inter = new pub_interface();
        $html = $inter->priv_menus_modules_create();
        return $html;
    }


    /**
     * Обновляет информацию о том кто сейчас работает в адмике
     *
     * @return integer
     * @access private
     */
    function admin_trace()
    {
        global $kernel;

        // Запишем перемещения админа в систему слежения
        $sql = "UPDATE
                `".$kernel->pub_prefix_get()."_admin_trace`
                SET
                time = NOW(),
                place='".$kernel->pub_section_current_get()."',
                ip='".$_SERVER['REMOTE_ADDR']."',
                host='".gethostbyaddr($_SERVER['REMOTE_ADDR'])."'
                WHERE
                id_admin='".$kernel->priv_admin_id_current_get()."'
                ";
        $kernel->runSQL($sql);

        $sql = 'SELECT count(id_admin) AS count '
        . ' FROM `'.$kernel->pub_prefix_get().'_admin_trace` '
        . ' WHERE (time BETWEEN DATE_ADD(NOW(), INTERVAL -5 MINUTE) AND NOW())';
        $result = $kernel->runSQL($sql);
        $data = mysql_fetch_assoc($result);

        return $data['count'];
    }

    /**
     * Функция возращает интерфейс редактора контента,
     * открываемого в новом окне
     *
     */
    private function priv_edit_content_start()
    {
        global $kernel;
        $file_name = $kernel->pub_translit_string($kernel->pub_httpget_get('file'));
        //сначала проверим, что бы файл, открываемый таким способом находился только
        //в области, где лежит весь контент
        $full_name = $kernel->priv_file_full_patch($kernel->pub_path_for_content().$file_name);
        if (!preg_match("/^[a-zA-Z0-9_\\-\\/\\.\\\\:]+$/",$full_name))
            return "Can not open this file";
        $content = new edit_content();
        //Здесь мы будем получать просто название файла,
        $content->set_file($file_name);
        $content->set_full_form();

        //Проверим, может нам надо вывести без редактора контнета
        if (intval($kernel->pub_httpget_get("no_redactor")) > 0)
        	$content->set_form_nothtml();
        return $content->create();
    }

    function priv_edit_content_save()
    {
        global $kernel;

        $file_name_lite = $kernel->pub_translit_string($kernel->pub_httppost_get('file'));
    	$full_name = $kernel->priv_file_full_patch($kernel->pub_path_for_content().$file_name_lite);
        if (!preg_match("/^[a-zA-Z0-9_\\-\\/\\.\\\\:]+$/",$full_name))
            return "Can not save this file";
        $content = $kernel->pub_httppost_get('content_html');
        $kernel->pub_file_save($full_name, stripcslashes($content));
        return $file_name_lite;
    }
}