<?PHP
require_once realpath(dirname(__FILE__)."/../../")."/include/basemodule.class.php";

/**
 * Основной управляющий класс модуля авторизации
 *
 * Модуль предназначен для управления авторизацией и регистрацией
 * посетителей на сайте, а так же управление личным кабинетом
 * @copyright ArtProm (с) 2001-2013
 * @version 2.0
 */

class auth extends basemodule
{
    protected $path_templates = "modules/auth/templates_user"; //Путь к шаблонам модуля
    protected $path_templates_admin = "modules/auth/templates_admin";

    /**
     * Метод публичного отображения профиля
     * @param $tpl
     * @return string
     */
    function pub_show_profile($tpl)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($tpl));

        $userid = intval($kernel->pub_httpget_get('userid'));
        if ($userid<1)
            return $this->get_template_block('not_found');
        $uinfo=$kernel->pub_users_info_get($userid);
        if (!$uinfo)
            return $this->get_template_block('not_found');
        $uinfo = $uinfo[$userid];
        $content = $this->get_template_block('begin');
        foreach ($uinfo['fields'] as $mods)
        {
            foreach ($mods as $fv)
            {
                if (!$fv['value'])//не выводим незаполненные
                    continue;
                if ($fv['only_admin']) //не выводим админ-поля
                    continue;
                if ($fv['type_field']=='image')
                {
                    $line = $this->get_template_block('line_image');
                    $fv['value'] = '/content/images/auth/tn/'.$fv['value'];
                }
                else
                    $line = $this->get_template_block('line');
                $line = str_replace('%field_name%',$fv['caption'],$line);
                $line = str_replace('%field_value%',$fv['value'],$line);
                $content.=$line;
            }
        }
        $content.= $this->get_template_block('end');
        $content = str_replace('%login%', $uinfo['login'],$content);
        $content = str_replace('%email%', $uinfo['email'],$content);
        $content = str_replace('%name%', $uinfo['name'],$content);
        $content = str_replace('%fdate%', $uinfo['fdate'],$content);
        $kernel->pub_page_title_add($uinfo['login']);
        return $content;
    }

    function pub_show_info($tpl=null, $page_cabinet=null)
    {
        global $kernel;
        $html = "";
        $userid=$kernel->pub_user_is_registred();
        if ($userid)
        {
            if (empty($page_cabinet))
            {
                $page_cabinet = $this->get_module_prop_value('id_page_cabinet');
                if (!$page_cabinet)
                    return ("[#auth_error_no_param_set#] '[#auth_module_method_name1_param2_caption#]'");
            }

            $user_array = $kernel->pub_users_info_get($userid, false);
            if (empty($tpl))
                $tpl = $this->path_templates."/auth_show_cab.html";
            $this->set_templates($kernel->pub_template_parse($tpl));

            $html .= $this->get_template_block('user');
            $html = str_replace("%fio%", $user_array[$userid]['name'], $html);
            $u_lines = "";
            foreach ($user_array[$userid]['fields'] AS $field)
            {
                $u_line = $this->get_template_block('user_line');
                $u_line = str_replace("%caption%", $field['caption'], $u_line);
                $u_line = str_replace("%value%", $field['value'], $u_line);
                $u_lines .= $u_line;
            }
            $html = str_replace("%lines%", $u_lines, $html);
            $html = str_replace("%page_cab%", $page_cabinet, $html);
        }
        return $html;
    }


    function pub_show_remember($tpl=null)
    {
        global $kernel;
        $my_post = $kernel->pub_httppost_get();
        if (empty($tpl))
            $tpl=$this->path_templates."/auth_show_remember.html";
        $this->set_templates($kernel->pub_template_parse($tpl));
        if (isset($my_post['rem']['login']) || isset($my_post['rem']['email']))
        {
            if (isset($my_post['rem']['login']))
			    $array = $kernel->pub_user_login_info_get($my_post['rem']['login'], true);
			else
				$array = $kernel->pub_user_login_info_get($my_post['rem']['email'], false);


            if ($array)
            {
                //$password = $array['password'];
                $toname[] = $array['name'];
                $toaddr[] = $array['email'];
                $fromname = $_SERVER['HTTP_HOST'];
                $fromaddr = "noreply@".$_SERVER['HTTP_HOST'];
                $subject = $_SERVER['HTTP_HOST'].$kernel->pub_page_textlabel_replace("[#auth_other#]");

                $message = $this->get_template_block('mail');
                $message = str_replace("%login%", $array['login'], $message);
                $message = str_replace("%password%", $array['password'], $message);

                $kernel->pub_mail($toaddr, $toname, $fromaddr, $fromname, $subject, $message, 1);

                $html = $this->get_template_block('sended');
            }
            else
                $html = $this->get_template_block('incorrect');
        }
        else
            $html = $this->get_template_block('form');
        return $html;
    }


    /**
     * Форма авторизации
     * @param string $tpl шаблон
     * @param string $page_reg страница регистрации
     * @param string $page_cabinet страница кабинета
     * @param string $redirectToPage страница куда редиректить
     * @return string
     */
    function pub_show_authorize($tpl=null, $page_reg=null, $page_cabinet=null, $redirectToPage="")
    {
        global $kernel;
        $my_post = $kernel->pub_httppost_get();

        if (!$page_reg)
        {
            $page_reg = $this->get_module_prop_value('id_page_registration');
            if (!$page_reg)
                return ("[#auth_error_no_param_set#] '[#auth_module_method_name1_param1_caption#]'");
        }
        if (!$page_cabinet)
        {
            $page_cabinet = $this->get_module_prop_value('id_page_cabinet');
            if (!$page_cabinet)
                return ("[#auth_error_no_param_set#] '[#auth_module_method_name1_param2_caption#]'");
        }

        if (empty($tpl))
            $tpl = $this->path_templates."/auth_show_auth.html";
        $this->set_templates($kernel->pub_template_parse($tpl));
        $html = $this->get_template_block('begin');
        $userid=$kernel->pub_user_is_registred();
        if (!$userid)
        {
            if (isset($my_post['login']) && isset($my_post['pass']))
            {
                if (empty($my_post['login']) || empty($my_post['pass']))
                    $html .= $this->get_template_block('empty_fields');
                else
                {
                    $errorlevel = $kernel->pub_user_register($my_post['login'], $my_post['pass']);
                    switch ($errorlevel)
                    {
                        case 1:
                            if (isset($my_post['redirect2page']) && !empty($my_post['redirect2page']))
                                $kernel->pub_redirect_refresh_global($my_post['redirect2page']);
                            else
                                $kernel->pub_redirect_refresh_global("/".$page_cabinet.".html");
                            break;
                        case -1:
                            $html .= $this->get_template_block('inc_login');
                            break;
                        case -2:
                            $html .= $this->get_template_block('disabled_by_admin');
                            break;
                        case -3:
                            $html .= $this->get_template_block('verifying');
                            break;
                        default:
                            $html .= $this->get_template_block('unknown_err');
                    }
                }
            }
            $html .= str_replace("%redirect2page%", $redirectToPage, $this->get_template_block('login'));
        }
        elseif(isset($my_post['exit']))
        {
            $kernel->pub_user_unregister();
            $kernel->pub_redirect_refresh_global("/");
        }
        elseif (isset($my_post['redirect2page']) && !empty($my_post['redirect2page']))
            $kernel->pub_redirect_refresh_global($my_post['redirect2page']);
        else
        {
            $html .= $this->get_template_block('exit');
            $array = $kernel->pub_user_info_get();
            unset($array['indexes']);
            foreach ($array AS $key => $value)
            {
                $html = str_replace("%".$key."%", $value, $html);
            }
        }
        $html .= $this->get_template_block('end');
        $html = str_replace("%reg%", $page_reg, $html);
        $html = str_replace("%personal%", $kernel->pub_page_current_get(), $html);
        return $html;
    }

    function pub_show_registration($tpl=null, $page_cabinet=null)
    {
        global $kernel;
        $my_post = $kernel->pub_httppost_get();
        $my_get = $kernel->pub_httpget_get();

        if (empty($tpl))
            $tpl = $this->path_templates."/auth_show_reg.html";
        $this->set_templates($kernel->pub_template_parse($tpl));
        $html = $this->get_template_block('begin');
        if (!isset($my_get['regaction']))
            $my_get['regaction'] = "";
        $action = $my_get['regaction'];
        if (!isset($my_post['reg']))
            $my_post['reg']=array();
        switch ($action)
        {
            // Вводим данные для регистрации
            case 'input':
                if (
                    !isset($my_post['reg']['name']) || empty($my_post['reg']['name'])
                    || !isset($my_post['reg']['login']) || empty($my_post['reg']['login'])
                    || !isset($my_post['reg']['email']) || empty($my_post['reg']['email'])
                    || !isset($my_post['reg']['pass']) || empty($my_post['reg']['pass'])
                    || !isset($my_post['reg']['pass2']) || empty($my_post['reg']['pass2'])
                )
                {
                    $html .= $this->get_template_block('required_fields_not_filled');
                    $html .= $this->get_template_block('register');
                }
                elseif($my_post['reg']['pass2']!=$my_post['reg']['pass'])
                {
                    $html .= $this->get_template_block('passwords_dont_match');
                    $html .= $this->get_template_block('register');
                }
                elseif (!$kernel->pub_is_valid_email($my_post['reg']['email']))
                {
                    $html .= $this->get_template_block('invalid_email');
                    $html .= $this->get_template_block('register');
                }
                else
                {
                    $incorrect_fields=array();
                    $additional_fields = array();
                    $ufields = $kernel->pub_users_fields_get('only_admin=0');
                    foreach ($ufields as $module)
                    {
                        foreach ($module as $field)
                        {
                            if ($field['type_field']=='image')
                            {
                                if (!isset($_FILES[$field['id_field']]) || !is_uploaded_file($_FILES[$field['id_field']]['tmp_name']))
                                {
                                    if ($field['required']==1)
                                        $incorrect_fields[]=$field;
                                    continue;
                                }
                                $val = $this->prepare_user_input($_FILES[$field['id_field']], $field);
                            }
                            else
                            {
                                if (!isset($my_post['reg'][$field['id_field']]))
                                {
                                    if ($field['required']==1)
                                        $incorrect_fields[]=$field;
                                    continue;
                                }
                                $val = $this->prepare_user_input($my_post['reg'][$field['id_field']], $field);
                            }

                            if (empty($val))
                            {
                                if ($field['required']==1)
                                    $incorrect_fields[]=$field;
                                continue;
                            }
                            //$additional_fields[$field['id_field']]=$val;
                            $additional_fields[$field['id']]=$val;

                        }
                    }

                    if (count($incorrect_fields)==0)//не было неправильно заполненных (пустых) полей
                    {
                        $reg = $my_post['reg'];
                        foreach ($reg as $rk=>$rv)
                        {
                            $reg[$rk] = $kernel->pub_str_prepare_set($rv);
                        }
                        $id = $kernel->pub_user_add_new($reg['login'], $reg['pass'], $reg['email'], $reg['name']);
                        if ($id > 0)
                        {
                            //Запишем информацию о доп полях к юзеру
                            if ($additional_fields)
                            {
                                $user = array();
                                $user[$id]['name']=$reg['name'];
                                $user[$id]['email']=$reg['email'];
                                foreach ($additional_fields as $afk=>$afv)
                                {
                                    $user[$id]['fields'][$afk]=$afv;
                                }
                                $kernel->pub_users_info_set($user, true);
                            }

                            $message=$this->get_template_block('email2admin_body');
                            $aflines='';
                            $fields2rus=array();

                            $ufs = $kernel->db_get_list_simple('_user_fields',"true");
                            foreach ($ufs as $uf)
                            {
                                $fields2rus[$uf['id_field']]=$uf['caption'];
                            }

                            foreach ($my_post['reg'] as $uk=>$uv)
                            {
                                if ($uk=='pass2')
                                    continue;

                                $afline = $this->get_template_block('email2admin_field_line');
                                $afline=str_replace('%key%',isset($fields2rus[$uk])?$fields2rus[$uk]:htmlspecialchars($uk),$afline);
                                $afline=str_replace('%value%',htmlspecialchars($uv),$afline);
                                $aflines.=$afline;
                            }
                            $message = str_replace('%fields%',$aflines,$message);
                            $adminEmail=explode(",",$this->get_module_prop_value('admin_email_4_registration'));

                            $http_host = $_SERVER['HTTP_HOST'];
                            $mailFrom="mail@".$http_host;

                            $admin_subj = $this->get_module_prop_value('admin_subj_4_registration');
                            if (!$admin_subj)
                                $admin_subj = "Новый пользователь на сайте %host%";
                            $admin_subj = str_replace('%host%',$http_host,$admin_subj);
                            $kernel->pub_mail($adminEmail, $adminEmail, $mailFrom, $http_host, $admin_subj, $message, 1);


                            if ($this->get_module_prop_value('reg_activation_type')=='admin_manual')
                            {
                                $html .= $this->get_template_block('wait_for_confirm');
                            }
                            else
                            {
                                $url = $id.md5($reg['email']);
                                $url = "http://".$http_host."/".$kernel->pub_page_current_get().".html?regaction=confirm&code=".$url;
                                $umessage = $this->get_template_block('mail');
                                $umessage = str_replace("%url%", $url, $umessage);
                                $umessage = str_replace("%name%", $reg['name'], $umessage);
                                $umessage = str_replace("%host%",$_SERVER['HTTP_HOST'], $umessage);
                                $umessage = str_replace("%email%", $reg['email'], $umessage);
                                $usubj = $this->get_module_prop_value('auth_user_subj4reg');
                                if (!$usubj)
                                    $usubj = "Регистрация на сайте %host%";
                                $usubj = str_replace('%host%',$http_host,$usubj);
                                $kernel->pub_mail(array($reg['email']), array($reg['email']), $mailFrom, $http_host, $usubj, $umessage, 1);
                                $html .= $this->get_template_block('follow_link');
                            }
                        }
                        else
                        {
                            $html .= $this->get_template_block('not_unique');
                            $html .= $this->get_template_block('register');
                        }
                    }
                    else//какие-то из обязательных полей не заполнены
                    {
                        $html .= $this->get_template_block('required_fields_not_filled');
                        $html .= $this->get_template_block('register');
                    }
                }
                break;


            case 'confirm':
                $code = $my_get['code'];
                if (preg_match("|^(\\d+)([0-9a-f]{32})$|", $code, $arr))
                {
                    $sql = "SELECT email, password, login FROM ".$kernel->pub_prefix_get()."_user WHERE id='$arr[1]'";
                    $data = mysql_fetch_assoc($kernel->runSQL($sql));
                    if (md5($data['email']) == $arr[2])
                    {
                        $kernel->pub_user_unregister();
                        $kernel->pub_user_verify($arr[1]);
                        $html .= $this->get_template_block('success');
                        $kernel->pub_user_register($data['login'], $data['password']);
                        if (empty($page_cabinet))
                        {
                            $page_cabinet = $kernel->pub_modul_properties_get('id_page_cabinet');
                            $page_cabinet = $page_cabinet['value'];
                        }
                        $kernel->pub_redirect_refresh_global("/".$page_cabinet.".html");
                    }
                }
                else
                    $html .= $this->get_template_block('invalid_link');
                break;

            // Просто зашли на страницу регистрации
            default:
                if (!$kernel->pub_user_is_registred())
                    $html .= $this->get_template_block('register');
                else
                    $html .= $this->get_template_block('also_registred');

        }
        $html .= $this->get_template_block('end');

        foreach ($my_post['reg'] as $rk=>$rv)
        {
            $html=str_replace('%'.$rk.'%',htmlspecialchars($rv),$html);
        }
        $html=$this->clear_left_labels($html);
        return $html;
    }

    function pub_show_cabinet($tpl=null)
    {
        global $kernel;
        $my_post = $kernel->pub_httppost_get();
        if (empty($tpl))
            $tpl = $this->path_templates."/auth_show_cab.html";
        $this->set_templates($kernel->pub_template_parse($tpl));
        $userid = $kernel->pub_user_is_registred();
        if ($userid)
        {
            if (isset($_GET['clearfield']))
            {
                $clearfield=intval($kernel->pub_httpget_get('clearfield'));
                $user=array();
                $user[$userid]['fields'][$clearfield]='';
                $kernel->pub_users_info_set($user, true);
            }
            $ufields = $kernel->pub_users_fields_get('only_admin=0');
            if (isset($my_post['save']))
            {
                if (!isset($my_post['name']) || !isset($my_post['password']) || !isset($my_post['password2']) || !isset($my_post['email'])
                    || empty($my_post['name']) || empty($my_post['email'])
                )
                {
                    return $this->get_template_block('required_fields_not_filled');
                }
                elseif ($my_post['password']!=$my_post['password2'])
                    return $this->get_template_block('passwords_dont_match');
                elseif (!$kernel->pub_is_valid_email($my_post['email']))
                    return $this->get_template_block('invalid_email');

                $exUser = $kernel->db_get_record_simple('_user',"email='".$my_post['email']."'",'id');
                if ($exUser && $exUser['id']!=$userid)
                    return $this->get_template_block('not_unique');


                $incorrect_fields=array();
                $additional_fields = array();

                foreach ($ufields as $module)
                {
                    foreach ($module as $field)
                    {
                        if ($field['type_field']=='image')
                        {
                            if (!isset($_FILES[$field['id_field']]) || !is_uploaded_file($_FILES[$field['id_field']]['tmp_name']))
                            {
                                if ($field['required']==1)
                                    $incorrect_fields[]=$field;
                                continue;
                            }
                            $val = $this->prepare_user_input($_FILES[$field['id_field']], $field);
                        }
                        else
                        {
                            if (!isset($my_post['fields'][$field['id_field']]))
                            {
                                if ($field['required']==1)
                                    $incorrect_fields[]=$field;
                                continue;
                            }
                            $val = $this->prepare_user_input($my_post['fields'][$field['id_field']], $field);
                        }

                        if (empty($val))
                        {
                            if ($field['required']==1)
                                $incorrect_fields[]=$field;
                            continue;
                        }
                        $additional_fields[$field['id']]=$val;

                    }
                }

                if (count($incorrect_fields)==0)//не было неправильно заполненных (пустых) полей
                {
                    $user = array();
                    $user[$userid]['name']=$my_post['name'];
                    $user[$userid]['email']=$my_post['email'];
                    if (!empty($my_post['password']))
                        $user[$userid]['password']=$my_post['password'];
                    //Запишем информацию о доп полях к юзеру
                    if (count($additional_fields)>0)
                    {
                        foreach ($additional_fields as $afk=>$afv)
                        {
                            $user[$userid]['fields'][$afk]=$afv;
                        }
                    }

                    $prevUinfo=$kernel->pub_user_info_get(false);
                    $kernel->pub_users_info_set($user, true);
                    $currUinfo=$kernel->pub_user_info_get(true);

                    $mailbody=trim($this->get_template_block('email_info_changed_body'));
                    $mailsubj=trim($this->get_template_block('email_info_changed_subject'));
                    if ($mailbody && $mailsubj)
                    {
                        $flines='';
                        $commonFields = array(
                            'login'=>'Логин',
                            'password'=>'Пароль',
                            'name'=>'Имя',
                            'email'=>'Email',
                        );

                        foreach ($commonFields as $fk=>$fcaption)
                        {
                            $fline = $this->get_template_block('email_info_changed_field');
                            $fline = str_replace('%caption%',$fcaption,$fline);
                            $fline = str_replace('%value%',$currUinfo[$fk],$fline);
                            $flines.=$fline;
                        }


                        if (isset($currUinfo['fields']['auth']))
                        {
                            foreach ($currUinfo['fields']['auth'] as $uf)
                            {
                                $fline = $this->get_template_block('email_info_changed_field');
                                $fline = $kernel->pub_array_key_2_value($fline,$uf);
                                $flines.=$fline;
                            }
                        }
                        $mailbody = str_replace('%fields%',$flines,$mailbody);
                        $kernel->pub_mail(array($prevUinfo['email']),array($prevUinfo['name']),"mail@".$_SERVER['HTTP_HOST'],$_SERVER['HTTP_HOST'],$mailsubj,$mailbody);
                    }

                    $content = $this->get_template_block('save_success');

                }
                else//какие-то из обязательных полей не заполнены
                {
                    $content = $this->get_template_block('required_fields_not_filled');
                }
            }
            else
            {
                $content = $this->get_template_block('userform_begin');
                $array = $kernel->pub_user_info_get(true);

                if (isset($array['fields']))
                {
                    $fields = $array['fields'];
                    unset($array['fields']);
                }
                else
                    $fields = array();

                //основные поля
                foreach ($array AS $key => $value)
                {
                    if (!is_array($value))
                        $content = str_replace("%".$key."%", htmlspecialchars($value), $content);
                }
                if (!empty($fields))
                {
                    foreach ($fields as $m_key => $m_value)
                    {
                        foreach ($fields[$m_key] as $id_value)
                        {
                            $line = $this->get_template_block('form_line_'.$id_value['name']);
                            $val='';
                            switch ($id_value['type_field'])
                            {
                                case 'text':
                                case 'string':
                                case 'html':
                                case 'date':
                                default:
                                    $val = htmlspecialchars($id_value['value']);
                                    break;
                                case 'image':
                                    if (!$id_value['value'])
                                    {
                                        $line = $this->get_template_block('form_line_'.$id_value['name'].'_empty');
                                        $val='';
                                    }
                                    else
                                        $val = '/content/images/auth/tn/'.$id_value['value'];
                                    break;
                                case 'select':
                                    $params = unserialize($id_value['params']);
                                    $options = '';
                                    foreach ($params['values'] as $option)
                                    {
                                        if ($id_value['value']==$option)
                                            $oline=$this->get_template_block('form_line_'.$id_value['name'].'_option_selected');
                                        else
                                            $oline=$this->get_template_block('form_line_'.$id_value['name'].'_option');
                                        $oline = str_replace('%k%',htmlspecialchars($option),$oline);
                                        $oline = str_replace('%v%',htmlspecialchars($option),$oline);
                                        $options.=$oline;
                                    }
                                    $line = str_replace('%options%',$options,$line);
                                    break;
                            }
                            $line = str_replace("%".$id_value['name']."%", $val, $line);
                            $line = str_replace("%caption%", $id_value['caption'], $line);
                            $content = str_replace('%'.$id_value['name'].'%',$line,$content);
                        }
                    }
                }
                $content = str_replace("%curr_page%", $kernel->pub_page_current_get().".html", $content);
                $content = str_replace("%action%", "auth_users_save", $content);
                $content .= $this->get_template_block('userform_end');
            }
        }
        else
            $content = $this->get_template_block('not_authorized');
        return $content;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления построением меню
     * @return boolean true
     */
	public function interface_get_menu($menu)
	{
	    //Создаётся заголовок первого блока элементов меню
        $menu->set_menu_block('[#auth_leftmenu_caption#]');
        $menu->set_menu("[#auth_group_list#]","group_list");
        $menu->set_menu("[#auth_users_list#]","users_list");
        $menu->set_menu("[#auth_menu_user_fields#]","user_fields");
        $menu->set_menu("[#auth_users_inactive_list#]","unactive_users_list");
        $menu->set_menu("[#auth_protect_dir#]","protect_dir");
        // меню по умолчанию
        $menu->set_menu_default('users_list');
	    return true;
	}

    protected static function get_field_types()
    {
        return array(
            'string'=>'[#auth_user_field_type_string#]',
            'text'=>'[#auth_user_field_type_text#]',
            'html'=>'[#auth_user_field_type_html#]',
            'select'=>'[#auth_user_field_type_select#]',
            'image'=>'[#auth_user_field_type_image#]',
            'date'=>'[#auth_user_field_type_date#]'
        );
    }

    protected function user_field_form()
    {
        global $kernel;
        $id = intval($kernel->pub_httpget_get('id'));
        $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/field.html"));
        $html = $this->get_template_block('content');
        if ($id==0)
        {
            $rec = array('caption'=>'','id_field'=>'','only_admin'=>0,'id'=>0,'type_field'=>'','required'=>0,'params'=>'a:1:{s:6:"values";a:0:{}}');
            $html = str_replace('%type_field_disabled%',"",$html);
        }
        else
        {
            $rec = $kernel->db_get_record_simple('_user_fields','id='.$id);
            $html = str_replace('%type_field_disabled%',"disabled",$html);
        }

        if ($rec['only_admin']==1)
            $html=str_replace('%only_admin_checked%','checked',$html);
        else
            $html=str_replace('%only_admin_checked%','',$html);
        if ($rec['required']==1)
            $html=str_replace('%required_checked%','checked',$html);
        else
            $html=str_replace('%required_checked%','',$html);
        foreach ($rec as $k=>$v)
        {
            if ($k=='type_field')
            {
                $ftypes = self::get_field_types();
                $ftlines = '';
                foreach ($ftypes as $ftk=>$ftv)
                {
                    if ($ftk==$rec['type_field'])
                        $ftlines.="<option value='".$ftk."' selected>".$ftv."</option>";
                    else
                        $ftlines.="<option value='".$ftk."'>".$ftv."</option>";
                }
                $html = str_replace('%types%',$ftlines,$html);
            }
            $html = str_replace('%'.$k.'%',htmlspecialchars($v),$html);
        }
        $rec['params']=unserialize($rec['params']);
        if ($id!=0 && $rec['type_field']!='image')
            $addparam_image='';
        else
        {
            if ($rec['type_field']=='image')
                $img_params= $rec['params'];
            else
                $img_params= self::make_default_pict_prop_addparam();
            $addparam_image=$this->get_template_block('addparam_image');
            $addparam_image=self::process_image_settings_block($addparam_image,$img_params);
        }

        if ($id==0)
            $addparam_enum=$this->get_template_block('addparam_enum_new');
        else
        {
            if ($rec['type_field']=='select')
            {
                $addparam_enum = $this->get_template_block('addparam_enum');
                $vals  = $rec['params']['values'];
                $lines = '';
                foreach ($vals as $val)
                {
                    $line = $this->get_template_block('select_params_edit_line');
                    $line = str_replace('%action_del%','user_field_select_prop_delete&selectval='.urlencode($val).'&id='.$id, $line);
                    $line = str_replace('%val_name%',$val, $line);
                    $line = str_replace('%val_name_escape%',htmlspecialchars($val), $line);
                    $lines .= $line;
                }
                $addparam_enum = str_replace('%vals%', $lines, $addparam_enum);
            }
            else
                $addparam_enum='';
        }


        $html = str_replace('%addparam_enum%',$addparam_enum,$html);
        $html = str_replace('%addparam_image%',$addparam_image,$html);
        $html = str_replace('%action%',$kernel->pub_redirect_for_form('user_field_save'),$html);
        return $html;
    }

    protected function user_field_save()
    {
        global $kernel;
        $id = intval($kernel->pub_httppost_get('id'));
        $caption = $kernel->pub_httppost_get('caption');
        $id_field = $kernel->pub_httppost_get('id_field');
        $type_field = $kernel->pub_httppost_get('type_field');
        $only_admin = isset($_POST['only_admin'])?1:0;
        $required =  isset($_POST['required'])?1:0;
        $moduleid = $kernel->pub_module_id_get();

        if ($id==0)
        {
            $q="INSERT INTO ".$kernel->pub_prefix_get()."_user_fields
                (`id_field`, `id_modul`, `caption`, `only_admin`,`required`, `type_field`,`params`)
                VALUES
                ('".$id_field."',
                '".$moduleid."',
                '".$caption."',
                '".$only_admin."',
                '".$required."',
                '".$type_field."',
                NULL
                )";
            $kernel->runSQL($q);
            $id=mysql_insert_id();
            if (!$id)
                return $kernel->pub_httppost_errore('ERROR',true);
        }

        $exRec = $frec = $kernel->db_get_record_simple('_user_fields','id='.$id);

        $params = array("values"=>array());
        if ($type_field=='select')
            $params['values']=explode("\n",trim($kernel->pub_httppost_get('select_values',false)));


        $new_select_option=trim($kernel->pub_httppost_get('new_select_option',false));

        $q="UPDATE ".$kernel->pub_prefix_get()."_user_fields SET
            id_field='".$id_field."',
            id_modul='".$moduleid."',
            caption='".$caption."',
            only_admin='".$only_admin."',
            required='".$required."' ";

        $params = null;
        switch($exRec['type_field'])
        {
            case 'select':
                if ($new_select_option) //добавление значения в набор
                {
                    $params=unserialize($exRec['params']);
                    if (!isset($params['values']))
                        $params['values']=array();
                    $params['values'][]=$new_select_option;
                    $params['values'] = array_unique($params['values']);
                }
                break;
            case 'html':
                //@todo allowed tags

                break;

            case 'image':
                $small_settings=array();
                $big_settings=array();
                $src_settings=array();
                if (isset($_POST['pict_small_isset']))
                {
                    $small_settings['isset']=true;
                    $small_settings['width']=intval($kernel->pub_httppost_get('pict_small_width'));
                    $small_settings['height']=intval($kernel->pub_httppost_get('pict_small_height'));
                }
                if (isset($_POST['pict_big_isset']))
                {
                    $big_settings['isset']=true;
                    $big_settings['width']=intval($kernel->pub_httppost_get('pict_big_width'));
                    $big_settings['height']=intval($kernel->pub_httppost_get('pict_big_height'));
                    $big_settings['transparency']=intval($kernel->pub_httppost_get('pict_big_transparency'));
                    if ($big_settings['transparency']==0)
                        $big_settings['transparency']="";
                    $big_settings['path']=$kernel->pub_httppost_get('path_big_water_path',false);
                    $big_settings['place']=intval($kernel->pub_httppost_get('pict_big_water_position'));
                    $big_settings['water_add']=intval($kernel->pub_httppost_get('pict_big_water_add'));
                }
                if (isset($_POST['pict_source_isset']))
                {
                    $src_settings['isset']=true;
                    $src_settings['width']=intval($kernel->pub_httppost_get('pict_source_width'));
                    $src_settings['height']=intval($kernel->pub_httppost_get('pict_source_height'));
                    $src_settings['transparency']=intval($kernel->pub_httppost_get('pict_source_transparency'));
                    if ($src_settings['transparency']==0)
                        $src_settings['transparency']="";
                    $src_settings['path']=$kernel->pub_httppost_get('path_source_water_path',false);
                    $src_settings['place']=intval($kernel->pub_httppost_get('pict_source_water_position'));
                    $src_settings['water_add']=intval($kernel->pub_httppost_get('pict_source_water_add'));
                }
                $params = array('small'=>$small_settings,'big'=>$big_settings,'source'=>$src_settings);
                break;
        }
        if ($params)
            $q.=", params='".mysql_real_escape_string(serialize($params))."'";
        $q.=" WHERE id=".$id;
        $kernel->runSQL($q);
        return $kernel->pub_httppost_response('[#auth_user_field_saved#]','user_fields');
    }

    protected function user_fields()
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/fields.html"));
        $ufields = $kernel->pub_users_fields_get();//$this->priv_get_user_fields();
        $html = $this->get_template_block('content');
        $field_lines = '';
        foreach ($ufields as $modules)
        {
            foreach ($modules as $field)
            {
                $line = $this->get_template_block('field');
                if ($field['only_admin'])
                    $field['only_admin']='<img src="/admin/templates/default/images/24-em-check.gif" />';
                else
                    $field['only_admin']='';
                if ($field['required'])
                    $field['required']='<img src="/admin/templates/default/images/24-em-check.gif" />';
                else
                    $field['required']='';
                $field['type_field']='[#auth_user_field_type_'.$field['type_field'].'#]';
                $line = $kernel->pub_array_key_2_value($line,$field);
                $field_lines.=$line;
            }
        }
        $html = str_replace('%fields%', $field_lines, $html);
        $html = str_replace('%generate_action%', $kernel->pub_redirect_for_form('generate_tpls'), $html);
        return $html;
    }

    protected function generate_tpl()
    {
        global $kernel;
        $content='';
        $is_cabinet=false;
        if (isset($_POST['generate_cabinet_tpl']))//шаблон кабинета
        {
            if (empty($_POST['generate_cabinet_tpl']))
                return $kernel->pub_httppost_response('[#auth_generate_filename_empty_msg#]');
            $out_file = $_POST['generate_cabinet_tpl'];
            $tpl_tpl = "_tpl_cabinet.html";
            $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/".$tpl_tpl));
            $is_cabinet=true;
            $content .= "<!-- @userform_begin -->\n".$this->get_template_block('userform_begin')."\n";
            $content .= "<!-- @userform_end -->\n".$this->get_template_block('userform_end')."\n";
            $content .= "<!-- @invalid_email -->\n".$this->get_template_block('invalid_email')."\n";
            $content .= "<!-- @not_unique -->\n".$this->get_template_block('not_unique')."\n";
            $content .= "<!-- @passwords_dont_match -->\n".$this->get_template_block('passwords_dont_match')."\n";
            $content .= "<!-- @required_fields_not_filled -->\n".$this->get_template_block('required_fields_not_filled')."\n";
            $content .= "<!-- @not_authorized -->\n".$this->get_template_block('not_authorized')."\n";
            $content .= "<!-- @save_success -->\n".$this->get_template_block('save_success')."\n";
        }
        else //шаблон регистрации
        {
            if (empty($_POST['generate_reg_tpl']))
                return $kernel->pub_httppost_response('[#auth_generate_filename_empty_msg#]');
            $out_file = $_POST['generate_reg_tpl'];
            $tpl_tpl = "_tpl_reg.html";
            $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/".$tpl_tpl));
            $content .= "<!-- @required_fields_not_filled -->\n".$this->get_template_block('required_fields_not_filled')."\n";
            $content .= "<!-- @passwords_dont_match -->\n".$this->get_template_block('passwords_dont_match')."\n";
            $content .= "<!-- @also_registred -->\n".$this->get_template_block('also_registred')."\n";
            $content .= "<!-- @invalid_email -->\n".$this->get_template_block('invalid_email')."\n";
            $content .= "<!-- @not_unique -->\n".$this->get_template_block('not_unique')."\n";
            $content .= "<!-- @follow_link -->\n".$this->get_template_block('follow_link')."\n";
            $content .= "<!-- @invalid_link -->\n".$this->get_template_block('invalid_link')."\n";
            $content .= "<!-- @begin -->\n".$this->get_template_block('begin')."\n";
            $content .= "<!-- @end -->\n".$this->get_template_block('end')."\n";
            $content .= "<!-- @mail -->\n".$this->get_template_block('mail')."\n";
            $content .= "<!-- @register -->\n";
        }

        $add_fields_lines='';

        $content .= $this->get_template_block('content');
        $ufields = $kernel->pub_users_fields_get('only_admin=0');
        foreach ($ufields as $modules)
        {
            foreach ($modules as $field)
            {
                $line = $this->get_template_block('form_line_'.$field['type_field']);
                $line = str_replace('%value%','%'.$field['id_field'].'%',$line);
                if ($field['type_field']=="select")
                {
                    $params = unserialize($field['params']);
                    $options = '';
                    if ($is_cabinet)
                    {
                        $line.="<!-- @form_line_".$field['id_field']."_option -->".$this->get_template_block('form_line_select_option')."\n";
                        $line.="<!-- @form_line_".$field['id_field']."_option_selected -->".$this->get_template_block('form_line_select_option_selected')."\n";

                    }
                    else
                    {
                        foreach ($params['values'] as $option)
                        {
                            $oline=$this->get_template_block('form_line_select_option');
                            $oline = str_replace('%k%',htmlspecialchars($option),$oline);
                            $oline = str_replace('%v%',htmlspecialchars($option),$oline);
                            $options.=$oline;
                        }
                        $line = str_replace('%options%',$options,$line);
                    }

                }
                elseif ($field['type_field']=="image" && $is_cabinet)
                    $line .= "<!-- @form_line_".$field['id_field']."_empty -->\n".$this->get_template_block('form_line_image_empty')."\n";

                $line = str_replace("%caption%", $field['caption'], $line);
                $line = str_replace("%fieldid%", $field['id'], $line);
                $line = str_replace("%id%", $field['id_field'], $line);
                if ($field['required']==1)
                    $line = str_replace('%required%', $this->get_template_block('form_line_required'),$line);
                else
                    $line = str_replace('%required%', '',$line);

                if ($is_cabinet)
                {
                    $add_fields_lines.="%".$field['id_field']."%\n";
                    $content.="<!-- @form_line_".$field['id_field']." -->\n".$line;
                }
                else
                    $add_fields_lines.=$line;
            }
        }
        $content = str_replace('%additional_fields%',$add_fields_lines,$content);
        $kernel->pub_file_save('modules/auth/templates_user/'.$out_file,$content);
        return $kernel->pub_httppost_response('[#auth_generate_ok_msg#] '.$out_file);
    }

    /**
	 * Предопределйнный метод, используется для вызова административного интерфейса модуля
	 * У данного модуля админка одна, для всех экземпляров
	 */
    function start_admin()
    {
        global $kernel;
        $my_post = $kernel->pub_httppost_get();
        $html = '';
        switch ($kernel->pub_section_leftmenu_get())
        {
            case 'search_login':
                $urec = $kernel->db_get_record_simple('_user',"`login`='".$kernel->pub_httppost_get('slogin')."'");
                if ($urec)
                    return $kernel->pub_httppost_response('[#auth_user_found_msg#]','user_edit&id='.$urec['id']);
                else
                    return $kernel->pub_httppost_response('[#auth_user_not_found_msg#]');
            case 'generate_tpls':
                return $this->generate_tpl();
            //очистка поля при редактировании юзера (картинка)
            case 'user_field_clear':
                $userid=intval($kernel->pub_httpget_get('userid'));
                $fieldid=intval($kernel->pub_httpget_get('fieldid'));
                //$type_field=$kernel->pub_httpget_get('type_field');
                $q='DELETE FROM '.$kernel->pub_prefix_get()."_user_fields_value WHERE user=".$userid." AND field=".$fieldid;
                $kernel->runSQL($q);
                $kernel->pub_redirect_refresh('user_edit&id='.$userid);
                break;
            //удаление значения из набора
            case 'user_field_select_prop_delete':
                $fieldid=intval($kernel->pub_httpget_get('id'));
                $frec = $kernel->db_get_record_simple('_user_fields','id='.$fieldid);
                $frec['params']=unserialize($frec['params']);
                $val2del = $kernel->pub_httpget_get('selectval',false);
                foreach ($frec['params']['values'] as $k=>$val)
                {
                    if ($val==$val2del)
                    {
                        unset($frec['params']['values'][$k]);
                        break;
                    }
                }
                $q = "UPDATE `".$kernel->pub_prefix_get()."_user_fields` SET `params`='".mysql_real_escape_string(serialize($frec['params']))."' WHERE id=".$fieldid;
                $kernel->runSQL($q);
                $kernel->pub_redirect_refresh('user_fields');
                break;
            case 'user_field_delete':
                $fieldid=intval($kernel->pub_httpget_get('id'));
                $q="DELETE FROM `".$kernel->pub_prefix_get()."_user_fields` WHERE id='".$fieldid."'";
                $kernel->runSQL($q);
                $q="DELETE FROM `".$kernel->pub_prefix_get()."_user_fields_value` WHERE field='".$fieldid."'";
                $kernel->runSQL($q);
                $kernel->pub_redirect_refresh('user_fields');
                break;
            case 'user_field_save':
                $html = $this->user_field_save();
                break;
            case 'user_field':
                $html = $this->user_field_form();
                break;
            case 'user_fields':
                $html = $this->user_fields();
                break;
            //Выводим список доступных групп, всё редактируется прямо там
            case 'group_list':
                $html = $this->group_list();
                break;

            //Редактируем группу
            case 'group_edit':
                $id_group = intval($kernel->pub_httpget_get('id_group'));
                $html = $this->group_edit($id_group);
                break;

            //Добовляем новую группу
            case 'group_add':
                $html = $this->group_edit();
                break;

            //Удаляем существующую группу
            case 'group_delet':
                $id_group = intval($kernel->pub_httpget_get('id_group'));
                $this->group_delete($id_group);
                $kernel->pub_redirect_refresh("group_list");
                break;

            //Сохраняем информацию о группах, просто "умираем" так как запрос отправляется без перегрузки страницы
            case 'group_save':
                $html = $this->group_save();
                break;

            case 'protect_dir':
                $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/pfolder.html"));
                $html = $this->get_template_block('form');
                $html = str_replace("%url%", $kernel->pub_redirect_for_form('protect_dir'), $html);

                if (isset($my_post['pfolder']))
                {//защищаем
                    $folder = $my_post['pfolder'];
                    if (mb_substr($folder, mb_strlen($folder)-1, 1) != "/")
                        $folder .= "/"; //добавляем / в конец, если его нет
                    if (mb_substr($folder, 0, 1) != "/")
                        $folder = "/".$folder; //добавляем / в начало, если его нет
                    $path2file = $kernel->pub_site_root_get().$folder.".htaccess";
                    $depth = mb_substr_count($folder, "/", 1);
                    $contents = "<IfModule mod_rewrite.c>\n".
                        " RewriteEngine On\n".
                        " RewriteBase ".$folder."\n".
                        " RewriteRule . ".str_repeat("../", $depth)."modules/auth/download.php\n".
                        "</IfModule>";
                    if ($kernel->pub_file_save($path2file, $contents))
                        return $kernel->pub_httppost_response($this->get_template_block('protect_success'));
                    else
                        return $kernel->pub_httppost_response($this->get_template_block('protect_failed'));
                }
                break;

            //Работа с пользователями
            case 'unactive_users_list':
                $html = $this->user_list('unactive');
                break;
            case 'users_list':
                $html = $this->user_list();
                break;
            //При добавлении нового пользователя просто открываем форму редактирования с пустым ID
            case 'user_add':
                $html = $this->user_edit_form();
                break;
            //Выводит форму для добавления нового и редактирования существующего
            //пользователя сайта и указания групп, в которые он входит
            case 'user_edit':
                $id_user = $kernel->pub_httpget_get('id');
                $html = $this->user_edit_form($id_user);
                break;
            //Сохраняем отредактированного или нового пользователя
            case 'user_save':
                return $this->user_save();
            case 'user_delete':
                $id_user = $kernel->pub_httpget_get('id');
                $kernel->pub_user_delete($id_user);
                $kernel->pub_redirect_refresh("users_list");
                break;
        }
        return $html;
    }

	/**
    * Формирует список существующих в системе групп вместе с галочками
	*
	* @return string
	*/
	protected function group_list()
    {
    	global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/groups.html"));
    	$arr_group = $kernel->pub_users_group_get();
        $number = 1;
        $html_row = '';
		foreach ($arr_group as $val)
	    {
	        $row = $this->get_template_block('all_group_row');
	        $row = str_replace("%number%"   , $number                             , $row);
	        $row = str_replace("%name%"     , $val['name']                        , $row);
	        $row = str_replace("%full_name%", $val['full_name']                   , $row);
	        $row = str_replace("%id%"       , $val['id']                          , $row);
	        $row = str_replace("%classtr%"  , $kernel->pub_table_tr_class($number), $row);
			$html_row .= $row;
			$number++;
        }
        $html = $this->get_template_block('all_group_table');
        $html = str_replace("%rows%", $html_row, $html);
        return $html;
    }

    /**
     * Создаёт форму для редактирования группы пользователей
     *
     * @param int $id Идентификатор группы пользователей, если это реакдтирование
     * @return string
     */

    protected function group_edit($id = 0)
    {
		global $kernel;

		$groups = $kernel->pub_users_group_get();
		if (count($groups) <= 0)
            return "";

        if (($id > 0 ) && (!isset($groups[$id])))
            return "";

        $name  = '';
        $fname = '';
        if ($id > 0)
        {
            $name  = $groups[$id]['name'];
            $fname = $groups[$id]['full_name'];
        }
        $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/groups.html"));
        $html = $this->get_template_block('group_edit');
        $html = str_replace("%name%" , $name , $html);
        $html = str_replace("%fname%", $fname, $html);
        $html = str_replace("%url%"  , $kernel->pub_redirect_for_form('group_save&id='.$id), $html);
		return $html;
    }

    /**
     * Сохраняет изменения в группах пользователей
     *
     * @return string
     */
    protected function group_save()
	{
		global $kernel;

		//Прежде всего проверка на то, что все данные введены
		$id_group  = intval($kernel->pub_httpget_get('id'));
		$name      = $kernel->pub_httppost_get('name');
		$full_name = $kernel->pub_httppost_get('fname');
		if (empty($name) || empty($full_name))
            return $kernel->pub_httppost_errore('[#auth_group_save_errore1#]', true);

        //Теперь либо добавляем новую запись, либо изменяем старую
        if ($id_group <= 0)
        {
            $query = "INSERT INTO `".$kernel->pub_prefix_get()."_user_group`
                       (`name`, `full_name`)
                      VALUES
                       ('".$kernel->pub_str_prepare_set($name)."', '".$kernel->pub_str_prepare_set($full_name)."')";
        }
        else
        {
            $query = "UPDATE `".$kernel->pub_prefix_get()."_user_group`
        			  SET
					  	`name` = '".$kernel->pub_str_prepare_set($name)."',
                        `full_name` = '".$kernel->pub_str_prepare_set($full_name)."'
                      WHERE `id` = ".$id_group;

		}

		//Теперь сообщения
		$message = "[#auth_group_sucse_add#]";
		if ($id_group > 0)
            $message = '[#auth_group_sucse_save#]';


		if (!$kernel->runSQL($query))
            $kernel->pub_httppost_errore('[#auth_group_save_errore2#]');

		//Данные необходимо возвратить через функцию ядра
		return $kernel->pub_httppost_response($message, 'group_list');
	}

    /**
     * Удаляет выбранную группу посетителей сайта
     * @param integer $id_group
     * @return boolean
     */
	protected function group_delete($id_group)
    {
    	global $kernel;

    	$id_group = intval($id_group);
    	if ($id_group <= 0)
    	   return false;

		//Непосредственно удалим группу
    	$query = "DELETE FROM ".$kernel->pub_prefix_get()."_user_group
        		  WHERE id = '".$id_group."'";
        $kernel->runSQL($query);

		//Удалим связи между администраторами и этой группой
    	$query = "DELETE FROM ".$kernel->pub_prefix_get()."_user_cross_group
        		  WHERE group_id = '".$id_group."'";
        $kernel->runSQL($query);
        return true;
    }

    /**
     * Выводит список пользователей сайта
     *
     * @param string $type
     * @return string
     */
    protected function user_list($type='all')
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/users.html"));
        $html = $this->get_template_block('begin');
        $sortFields = array("login","email","date");
        $sortBy = $kernel->pub_httpget_get("sortby");
        if (!in_array($sortBy, $sortFields))
            $sortBy = "login";
        $offset=intval($kernel->pub_httpget_get("offset"));
        $limit=100;
        if ($type=='unactive')
        {
            $action='unactive_users_list';
            //$limit=10000;
            $cond='verified=0';
        }
        else
        {
            $action='users_list';
            $cond="true";
        }
        $array = $kernel->pub_users_info_get("", true, $sortBy,$offset,$limit,$cond);
        $i = $offset+1;
        foreach ($array AS $id => $info)
        {
            $str_html = $this->get_template_block('line');
            $str_html = str_replace('%number%', $i, $str_html);
            $str_html = str_replace('%login%', $info['login'], $str_html);
            $str_html = str_replace('%name%', $info['name'], $str_html);
            $str_html = str_replace('%email%', $info['email'], $str_html);
            $str_html = str_replace('%fdate%', $info['fdate'], $str_html);
            $str_verified = '';
            if ($info['verified'] == 1)
                $str_verified = 'checked="checked"';
            $str_html = str_replace('%verified%', '<input type="checkbox" '.$str_verified.' disabled="disabled"/>', $str_html);
            $str_enabled = '';
            if ($info['enabled'] == 1)
                $str_enabled = 'checked="checked"';
            $str_html = str_replace('%enabled%', '<input type="checkbox" '.$str_enabled.' disabled="disabled"/>', $str_html);
            $str_html = str_replace("%id%", $id, $str_html);
            $html .= $str_html;
            $i++;
        }
        $html .= $this->get_template_block('end');

        //$total = $kernel->pub_users_total_get();
        $crec=$kernel->db_get_record_simple("_user",$cond,"COUNT(*) AS count");
        $total=$crec['count'];


        $html = str_replace('%pages%', $this->build_pages_nav($total,$offset, $limit,$action.'&sortby='.$sortBy.'&offset=',0,'link'), $html);

        $html=str_replace('%search_action%',$kernel->pub_redirect_for_form('search_login'),$html);

        return $html;

    }

    /**
     * Выводит форму для редактирования параметров пользователя.
     *
     * @param integer $id
     * @return string
     */
    protected function user_edit_form($id = 0)
    {
        global $kernel;

        $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/users.html"));
        $html = $this->get_template_block('form_user');

        //Сначала обработаем обязательные поля, которые точно есть
        //и заполним их если редактируется уже сущесвующий элемент
        $login = '';
        $name = '';
        $password = '';
        $email = '';
        //$date = time('Y-m-d H:m:s');
        $fdate = trim(date('d.m.Y'));
        $curent['verified'] = "0";
        $curent['enabled'] = "0";
        $init_button='';
        //Если это редактирование то заменим на существующие данные
        if ($id > 0)
        {
            $curent = $kernel->pub_users_info_get($id);
            $curent = $curent[intval($id)];
            $login      = $curent['login'];
            $name       = $curent['name'];
            $password   = $curent['password'];
            $email      = $curent['email'];
            $fdate      = $curent['fdate'];
            if ($curent['verified']==0)
                $init_button= $this->get_template_block('init_button');
        }

        $html = str_replace("%init_button%",$init_button, $html);
        $html = str_replace("%login%", $login, $html);
        $html = str_replace("%name%", $name, $html);
        $html = str_replace("%password%", $password, $html);
        $html = str_replace("%email%", $email, $html);
        $html = str_replace("%date%", $fdate, $html);
        $html = str_replace("%id%", $id, $html);
        $html = str_replace("%url%", $kernel->pub_redirect_for_form('user_save&id='.$id), $html);

        //Проставим галки, если они есть в текущем, так как по умолчанию они
        //у нас выключены
        $val = '';
        if ($curent['verified'] == "1")
            $val = 'checked="checked"';

        $html = str_replace("%v_checked%", $val, $html);

        $val = '';
        if ($curent['enabled'] == "1")
            $val = 'checked="checked"';

        $html = str_replace("%e_checked%", $val, $html);




        //Теперь пришла очередь дополнительных полей
        $html_fields = '';
        if (empty($id))
            $fields = $kernel->pub_users_fields_get();
        else
            $fields = $curent['fields'];
        $num = 0;
        foreach ($fields AS $m_key => $m_value)
        {
            //Попали в доп. поля конкретного модуля
            foreach ($fields[$m_key] AS $id_key => $id_value)
            {
                //Выводим собственно эти поля
                $html_str = $this->get_template_block('form_line_'.$id_value['type_field']);
                $tmp = '';
                if ($id_value['only_admin'])
                    $tmp = "[#auth_users_edit_user_label11#]";

                switch ($id_value['type_field'])
                {
                    case 'text':
                    case 'string':
                    case 'html':
                    case 'date':
                    default:
                        $val = htmlspecialchars($id_value['value']);
                        break;
                    case 'image':
                        if (empty($id_value['value']))
                        {
                            $html_str = $this->get_template_block('form_line_image_empty');
                            $val='';
                        }
                        else
                            $val = '/content/images/auth/tn/'.$id_value['value'];
                        break;
                    case 'select':
                        $params = unserialize($id_value['params']);
                        $options = '';
                        foreach ($params['values'] as $option)
                        {
                            if ($id_value['value']==$option)
                                $line=$this->get_template_block('form_line_select_option_selected');
                            else
                                $line=$this->get_template_block('form_line_select_option');
                            $line = str_replace('%k%',htmlspecialchars($option),$line);
                            $line = str_replace('%v%',htmlspecialchars($option),$line);
                            $options.=$line;
                        }
                        $html_str = str_replace('%options%',$options,$html_str);
                        break;
                }
                $html_str = str_replace("%caption%",    $id_value['caption'],                $html_str);
                $html_str = str_replace("%value%",      $val,                                $html_str);
                $html_str = str_replace("%id%",         $id_key,                             $html_str);
                $html_str = str_replace("%modid%",      $m_key,                              $html_str);
                $html_str = str_replace("%only_admin%", $tmp,                                $html_str);
                $html_str = str_replace("%class_str%",  $kernel->pub_table_tr_class($num++), $html_str);
                $html_fields .= $html_str;
            }
        }
        $html = str_replace("%str_fields%", $html_fields, $html);

        //Теперь добавим информацию о тех группах, в которые входит
        $html_group = '';
        $arr = $kernel->pub_users_group_get();
        $cgroup = $kernel->pub_user_group_get($id);
        $num = 0;
        foreach ($arr as $val)
        {
            $html_str = $this->get_template_block('form_line_group');
            $chek = '';
            if (isset($cgroup[$val['id']]))
                $chek = 'checked="checked"';

            $html_str = str_replace("%checked%",   $chek,                               $html_str);
            $html_str = str_replace("%id%",        $val['id'],                          $html_str);
            $html_str = str_replace("%name%",      $val['full_name'],                   $html_str);
            $html_str = str_replace("%class_str%", $kernel->pub_table_tr_class($num++), $html_str);

            $html_group .= $html_str;
        }

        $html = str_replace("%str_fields_group%", $html_group, $html);
        $html = str_replace("%userid%",         $id,        $html);
        return $html;
    }

    /**
     * Сохраняет форму с данными пользователя сайта
     *
     */
    protected function user_save()
    {
        global $kernel;
        //ID пользователя
        $id_user  = intval($kernel->pub_httpget_get('id'));
        //Преобразуем параметры, которые выбираются галочками
        $values = $kernel->pub_httppost_get();
        if (isset($values['inituser']))
        {
            $ivalues=array(
                'enabled'=>1,
                'verified'=>1,
            );
            $name = htmlspecialchars($values['name']);
            $toaddr = array($values['email']);
            $toname = array($name);
            $this->set_templates($kernel->pub_template_parse($this->path_templates_admin."/email_user_activated.html"));
            $host = preg_replace('~^www\.~i','',$_SERVER['HTTP_HOST']);
            $message = $this->get_template_block('mailbody');
            $message = str_replace("%name%", $name, $message);
            $message = str_replace("%host%", $host, $message);
            $message = str_replace("%email%", $toaddr, $message);

            $subj = $this->get_template_block('subj');
            $subj = str_replace("%host%", $host, $subj);
            $subj = str_replace("%name%", $name, $subj);

            $kernel->pub_mail($toaddr, $toname, "mail@".$host, $host, $subj, $message);
            $kernel->pub_users_info_set(array($id_user => $ivalues), false);
            return $kernel->pub_httppost_response('[#auth_user_activated_msg#]','users_list');
        }

        if (isset($values['verified']))
            $values['verified'] = 1;
        else
            $values['verified'] = 0;
        if (isset($values['enabled']))
            $values['enabled'] = 1;
        else
            $values['enabled'] = 0;

        //Проверка логина
        $values['login'] = trim($values['login']);
        if (empty($values['login']))
            return $kernel->pub_httppost_errore("[#auth_users_edit_user_errore1#]", true);
        elseif (!preg_match("/^[a-zA-Z0-9]+$/", $values['login']))
            return $kernel->pub_httppost_errore("[#auth_users_edit_user_errore2#]", true);

        //Проверка заполненности пароля
        if (mb_strlen($values['password']) < 4 )
            return $kernel->pub_httppost_errore("[#auth_users_edit_user_errore3#]", true);
        elseif ($values['re_password'] !== $values['password'])
            return $kernel->pub_httppost_errore("[#auth_users_edit_user_errore4#]", true);

        //Проверка заполненности адреса почты
        $values['email'] = trim ($values['email']);
        if (!$values['email'] || !$kernel->pub_is_valid_email($values['email']))
            return $kernel->pub_httppost_errore('[#auth_incorrect_email_msg#]',true);

        $values['fields'] = array();
        $fields = $kernel->pub_users_fields_get();

        foreach ($fields AS $m_key => $m_value)
        {
            //Попали в доп. поля конкретного модуля
            foreach ($fields[$m_key] AS $id_key => $id_value)
            {
                $fname  = 'fields_'.$id_key;
                if ($id_value['type_field']=='image')
                {
                    if (isset($_FILES[$fname]) && is_uploaded_file($_FILES[$fname]['tmp_name']))
                        $values['fields'][$id_key] = $this->prepare_user_input($_FILES[$fname], $id_value);
                    unset($values[$fname]);
                }
                elseif (isset($values[$fname]))
                {
                    if ($id_value['required']==1 && mb_strlen($values[$fname])==0)
                        return $kernel->pub_httppost_errore('[#auth_no_req_field_msg#] - '.$id_value['caption'],true);
                    $values['fields'][$id_key] = $this->prepare_user_input($values[$fname],$id_value['type_field']);
                    unset($values[$fname]);
                }
            }
        }
        //Прошли, и можем обрабатывать данные
        //Ещё надо сохранить список групп, в которые он входит
        //Просмотрим пост в котором есть эти данные
        $select_group = array();
        $all_group = $kernel->pub_users_group_get();
        foreach ($all_group as $val)
        {
            $gname  = 'group_'.$val['id'];
            if (isset($values[$gname]))
            {
                $select_group[] = $val['id'];
                unset($values[$gname]);
            }
        }

        if ($id_user <= 0)
        {
            $id_user = $kernel->pub_user_add_new($values['login'], $values['password'], $values['email'], $values['name']);
            if ($id_user < 1)
            {
                $msg='[#auth_error_adding_user#]';
                if ($id_user < 0)
                    $msg.=' : [#auth_error_adding_user_exists#]';
                return $kernel->pub_httppost_errore($msg,true);
            }
        }

        //Так как функция сохранения расчитана на множество пользователей
        //то массив с параметрами пользователя помещаем к значение с ключом, равным его ID
        $kernel->pub_users_info_set(array($id_user => $values), false);
        $kernel->pub_users_group_set($id_user, $select_group);
        return $kernel->pub_httppost_response('[#auth_user_saved_msg#]','users_list');
    }


    protected function clean_user_html($html)
    {
        $allowed_tags = '<p><li><ul><b><strong><br><a><img><cite>'; //@todo move to field settings
        $html = strip_tags($html,$allowed_tags);
        if (!preg_match_all("/<([a-z]+)(?:[\\s]*)([^>]*)>/is", $html, $matches,PREG_SET_ORDER))
            return $html;

        foreach ($matches as $match)
        {
            $tagName = strtolower($match[1]);
            switch ($tagName)
            {
                case 'a':
                    if (preg_match('~href(?:\s*)=(?:\s*)(?:\'|")https?\://([^\'"]+)(?:\'|")~i',$match[2],$linkmatch))
                        $link='http://'.$linkmatch[1];
                    else
                        $link='';
                    $html = str_replace($match[0],'<a href="'.$link.'" rel="noindex, nofollow">',$html);
                    break;
                case 'img':
                    if (preg_match('~src(?:\s*)=(?:\s*)(?:\'|")https?\://([^\'"]+)(?:\'|")~i', $match[2],$srcmatch))
                        $link='http://'.$srcmatch[1];
                    else
                        $link='';
                    $html = str_replace($match[0],'<img src="'.$link.'"/>',$html);
                    break;
                default:
                    $html = str_replace($match[0],'<'.$tagName.'>',$html);
                    break;
            }
        }
        return $html;
    }


    protected function prepare_user_input($val,$field)
    {
        global $kernel;
        switch ($field['type_field'])
        {
            default:
            case 'string':
            case 'text':
                $val = htmlspecialchars($val);
                break;
            case 'html':
                $val = $this->clean_user_html($val);
                break;
            case 'select':
                if (!isset($field['params']))
                    $field['params']='a:1:{s:6:"values";a:0:{}}';
                $params = unserialize($field['params']);
                if (!in_array($val,$params['values']))
                    $val='';
                break;
            case 'image':
                if (!isset($field['params']))
                    $params=array();
                else
                    $params = unserialize($field['params']);
                $thumb_settings = isset($params['small'])?$params['small']:array();
                $big_settings = isset($params['big'])?$params['big']:array();
                $src_settings = isset($params['source'])?$params['source']:array();
                $path_to_save = 'content/images/auth';
                if (!isset($src_settings['water_add']))
                    $src_settings['water_add']=0;
                if (!isset($big_settings['water_add']))
                    $big_settings['water_add']=0;
                $is_backend=kernel::is_backend();
                if ($src_settings['water_add']==2)
                {
                    if (!$is_backend || ($is_backend && isset($_POST[$field['id_field'].'_need_add_source_water'])))
                        $src_settings['water_add']=1;
                    else
                        $src_settings['water_add']=0;
                }
                if ($big_settings['water_add']==2)
                {
                    if (!$is_backend || ($is_backend && isset($_POST[$field['id_field'].'_need_add_big_water'])))
                        $big_settings['water_add']=1;
                    else
                        $big_settings['water_add']=0;
                }
                $val = $kernel->save_uploaded_image($val, $path_to_save, $thumb_settings,$big_settings,$src_settings);
                break;
            case 'date':
                if (preg_match('~^([\d]{1,2})\.([\d]{1,2})\.([\d]{4})$~',$val,$match))
                {
                    $date = $match[1];
                    $month = $match[2];
                    $year = $match[3];
                    if (!checkdate($month,$date,$year))
                        $val='';
                }
                else
                    $val='';
                break;
        }
        $val = mysql_real_escape_string($val);
        return $val;
    }
}