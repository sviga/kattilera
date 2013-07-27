<?PHP
		include_once('components/mail.class.php');
        include_once ("components/kcaptcha/kcaptcha.php");

/**
 * Основной управляющий класс модуля «Гостевая книга»
 *
 * Модуль предназначен организации гостевой книги на сайте.
 * 
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0 beta
 */

class feedback
{
	//Путь к административным шаблонам модуля
    private $admin_templates_path = 'modules/feedback/templates_admin/';

    //массив полей в форме с ограничениями
    private $fields = array(
    	'author'     => array(
    					'type' => 'string',
    					'minlength' => 1,
    					'maxlength' => 255 ),
    	'email'   => array(
    					'type' => 'email',
    					'minlength' => 1,
    					'maxlength' => 50 ),
    	'message' => array(
    					'type' => 'string',
    					'minlength' => 1,
    					'maxlength' => 5000 )
    );

    // Массив регулярных выражений для них
    private $regexps = array(
    	'string' => '',
    	'email'  => '/^([A-Za-z\d\-_]+\.)*[A-Za-z\d\-_]+@([A-Za-z\d\-_]+\.)+[a-zA-Z]{2,7}$/'
	);
	
    //Длина видимого сообщения в админке
    private $admin_message_len = 150;

    //флаг, использования капчи
	private $use_captcha = true;

	/**
     * Конструктор класса
     */
    function feedback()
    {
    	global $kernel;
    	$this->use_captcha = $kernel -> pub_modul_properties_get( 'captcha', $kernel -> pub_module_id_get() );
    }


    //***********************************************************************
    //	Наборы Публичных методов из которых будут строится макросы
    //**********************************************************************

   /**
    * Публичный метод для действия 'Показать форму'
    *
    * @param string $template Шаблон вывода формы
    * @return HTML
    */
	public function pub_show_feedback_form( $template )
    {
        global $kernel;

        $error = '';
        // Парсим шаблон
        $template = trim( $template );
        if( !empty( $template ) && file_exists( $template ) )
        	$template = $kernel -> pub_template_parse( $template );
      	else
        	return '[#feedback_error_no_template#]';

		//если получили данные в посте, то..
	    if( is_array( $input_values = $kernel -> pub_httppost_get( 'values' ) ) && count( $input_values ) > 0 && $kernel -> pub_httppost_get( 'feedback_form', true ) == 1 ) {
	    	//..проверяем их корректность
			$errors = $this -> check_input_values( $input_values, $kernel->pub_httppost_get( 'captcha' ) );

			//пришли некорректные данные
			if ( count( $errors ) > 0 || $errors === false ) {
				$error = str_replace( '%error_message%', ( !$errors ) ? 'Ошибка данных!' : $errors[ 0 ], $template[ 'error' ] );
			}
			//с данными всё впорядке, подготовим их для добавления в базу
			else {
				if ( $tobase_values = $this -> bd_prepare( $input_values ) ) {
					//всё ОК, можно добавлять в базу
					if ( $this -> save_in_feedback( $tobase_values ) ) {
						/*успешно добавлено в базу*/
						//отправка писма администратору
						$this -> send_mail_to_admin( $input_values );
						$kernel -> pub_session_set( 'feedback_ok', '[#feedback_user_correct_message_added#]' );
                        if($kernel -> pub_httppost_get( 'js', true ) == 1) {
                            die (json_encode(array('correct'=> 1)));
                        }
						$kernel -> pub_redirect_refresh_global( '/' . $kernel->pub_page_current_get());
					}
					else {
						var_dump(mysql_error());
						$error = str_replace( '%error_message%', '[#feedback_user_error_message_added#]', $template[ 'error' ] );
					}
				}
				else {
					$error = str_replace( '%error_message%', '[#feedback_user_error_data_handling#]', $template[ 'error' ] );
				}
			}
    	}//endif
        $html = $template[ 'begin_form' ];

        $html = str_replace( '%form_action%', '/' . $kernel -> pub_page_current_get() . '.html', $html );

        $lines = $template[ 'fields' ];

        $search  = $this -> get_search_data();
       	$replace = $this -> get_replace_data( $input_values );

   		$lines = str_replace( $search, $replace, $lines );

   		//обязательность полей
   	    /*foreach ( $this -> fields as $name => $value ) {
   			if ( $value[ 'minlength' ] > 0 )
   				$lines = str_replace( '%' . $name . '_ob%', $template[ 'oblige' ], $lines );
   			else
				$lines = str_replace( '%' . $name . '_ob%', '', $lines );
   		}*/

        $html .= $lines . $template[ 'end_form' ];
        
        //error/correct
        $feedback_ok = $kernel -> pub_session_get( 'feedback_ok' );
		if ( !empty( $feedback_ok ) ) {
			$correct = str_replace( '%correct_message%', $feedback_ok, $template[ 'correct' ] );
			$kernel -> pub_session_unset( 'feedback_ok' );
		}
		else
			$correct = '';

        $html = str_replace( '%feedback_error%', $error, $html );
        $html = str_replace( '%feedback_correct%', $correct, $html );

        $captcha = '';
        //если модуль использует Код безопасности
		if ( $this->use_captcha['value'] == 'true') {
        	$captcha = str_replace('%captcha_image%', $this->get_captcha(), $template['captcha']);
		}
       	$html = str_replace('%captcha%', $captcha, $html);

        return $html;

    }//END function pub_show_feedback_form

    
    private function get_search_data ()
	{
		$arr = array();
		foreach ( $this -> fields as $name => $value ) {
			$arr[] = '%' . $name . '_value%';
		}
		return $arr;
	}

	private function get_replace_data ( $values )
    {
    	$flag = is_array( $values ) ? true : false;
		$arr = array();
		foreach ( $this -> fields as $name => $value ) {
			if ( $flag )
				$arr[] = $this -> ss( $values[ $name ] );
			else
				$arr[] = '';
		}
		return $arr;
    }

    private function ss( $val )
    {
     	$val = ( get_magic_quotes_gpc() == 1 ) ? stripslashes($val) : $val;
     	return htmlspecialchars( $val );
    }
    
    
    /**
     * Функция проверяет введённые в форме значения на корректность в соответствии с массивами $fields и $regexps.
     *
     * @param mixed $values	//массив введённых в форме значений
     * @return array		//массив ошибок
     */
    private function check_input_values( $values = null, $captcha )
    {
    	global $kernel;

    	if ( $values == null || !isset( $values ) || empty( $values ) )
    		return false;

    	$errors = array();
 		foreach ( $this -> fields as $name => $detals ) {
	   		//пришел массив
	   		if ( is_array( $values ) ) {
   				if ( strlen( $values[ $name ] ) < $detals[ 'minlength' ] )
	   				if ( strlen( $values[ $name ] ) == 0 )
   						$errors[] = '[#feedback_user_error_empty#] [#feedback_user_' . $name . '_label#]';
   					else
	   					$errors[] = '[#feedback_user_error_sosmall#] [#feedback_user_' . $name . '_label#]';
   				elseif ( strlen( $values[ $name ] ) > $detals[ 'maxlength' ] )
	   				$errors[] = '[#feedback_user_error_sobig#] [#feedback_user_' . $name . '_label#]';
   				else {
		  			if ( strlen( $values[ $name ] ) != 0 && isset( $this -> regexps[ $detals[ 'type' ] ] ) && !empty( $this -> regexps[ $detals[ 'type' ] ] ) ) {
	  					if ( !preg_match($this -> regexps[ $detals[ 'type' ] ], $values[ $name ] ) > 0 ) {
	                    	$errors[] = '[#feedback_user_error_incorrect#] [#feedback_user_' . $name . '_label#]';
						}
	  				}
   				}
	   		}
	   		//пришел не массив
	   		else {
   				if ( strlen( $values ) < $detals[ 'minlength' ] )
	   				if ( strlen( $values ) == 0 )
   						$errors[] = '[#feedback_user_error_empty#] [#feedback_user_' . $values . '_label#]';
   					else
	   					$errors[] = '[#feedback_user_error_sosmall#] [#feedback_user_' . $values . '_label#]';
   				elseif ( strlen( $values ) > $detals[ 'maxlength' ] )
	   				$errors[] = '[#feedback_user_error_sobig#] [#feedback_user_' . $values . '_label#]';
   				else {
		  			if ( strlen( $values ) != 0 && isset( $this -> regexps[ $detals[ 'type' ] ] ) && !empty( $this -> regexps[ $detals[ 'type' ] ] ) ) {
	  					if ( !preg_match($this -> regexps[ $detals[ 'type' ] ], $values ) > 0 ) {
	                    	$errors[] = '[#feedback_user_error_incorrect#] [#feedback_user_' . $values . '_label#]';
						}
	  				}
   				}
	   		}
 		}//endforeach

 		//если модуль использует Код безопасности
 		if ( $this->use_captcha['value'] == 'true' ) {
	 		//проверяем капчу
 			if ( $kernel -> pub_session_get( 'captcha_keystring' ) != $captcha )
	 			$errors[] = '[#feedback_user_error_captcha#]';
			$kernel -> pub_session_unset( 'captcha_keystring' );
 		}

    	return $errors;
    }
    
    /**
     * Функция подготавливает данные к добавлению в базу
     *
     * @param mixed $values
     * @return boolean
     */
    private function bd_prepare( $values = null )
    {
    	if ( $values == null || !isset( $values ) || empty( $values ) )
    		return false;

    	//пришел массив
   		if ( is_array( $values ) ) {
   			$res = array();
   			foreach ( $values as $name => $value ) {
	    		$res[ $name ] = ( get_magic_quotes_gpc() == 1 ) ? stripslashes( $value ) : $value;
    			if ( ($res[ $name ] = mysql_real_escape_string( $res[ $name ] )) === false ) {
    				return false;
    			}
   			}
    	}
    	//пришел не массив
   		else {
	    	if ( get_magic_quotes_gpc() ) {
	    		$res = stripslashes( $values );
   			}
   			if ( ($res = mysql_real_escape_string( $values )) === false ) {
   				return false;
   			}
   		}
    	return $res;
    }

    /**
     * Функция для добавления записи в базу
     *
     * @param mixed $values
     * @return boolean
     */
    private function save_in_feedback( $values = null )
    {
    	if ( $values == null || !isset( $values ) || empty( $values ) )
    		return false;
    		
    	global $kernel;
    	
    	//пришел массив
		$set = '';
    	if ( is_array( $values ) ) {
			foreach ( $values as $name => $value ) {
				$set .= ", $name='$value'";
			}
    	}
    	else {}
    	
    	if ( $set === '' )
    		return false;

		$query = "INSERT INTO `" . PREFIX . "_feedback` SET id=''$set"
							. ", date='" . date('Y-m-d H:i:s') . "'";

    	return $kernel -> runSQL( $query );
    }
    
    
    /**
     * Функция отправляет администратору письмо с сообщением пользователя
     *
     * @param mixed $input_values
     * @return boolean
     */
    private function send_mail_to_admin( $values )
    {
		global $kernel;

	    function crlf() { return chr(10).chr(13); }
	    
		$admin_email = $kernel -> pub_modul_properties_get( 'email', $kernel -> pub_module_id_get() );
		if ( empty( $admin_email[ 'value' ] ) )
			return false;

		$name              = empty( $values[ 'author' ] ) ? 'Аноним' : $values[ 'author' ];
		$email             = empty( $values[ 'email'  ] ) ? ''       : ' <' . $values[ 'email' ] . '>';
		$site              = 'http://' . $_SERVER['HTTP_HOST'];
		$admin_email       = $admin_email[ 'value' ];
		$return_path_email = 'noreply@'.$_SERVER['HTTP_HOST'];
		$subject           = '18+. Новое сообщение в обратной связи';
		
		$message           = $name . $email . crlf()
						   . 'отправил сообщение на сайте' . crlf()
						   . $site . crlf()
						   . '-----------------------------------------------------------'. crlf()
						   . $values[ 'message' ];

		$mail =  new multi_mail;
		$mail -> from          = '18+<'.$return_path_email.'>';
  		$mail -> reply_to      = $return_path_email;
  		$mail -> return_path   = $return_path_email;
		$mail -> to            = $admin_email;
  		$mail -> text_html     = "text/plain";
  		$mail -> input_encode  = "windows-1251";
  		$mail -> output_encode = "windows-1251";
		$mail -> subject       = $subject;
  		$mail -> body          = $message;

		$mail -> send_mail();

  		return true;
    }

    /**
     * Получаем капчу
     *
     * @return string $path_to_captcha
     */
    private function get_captcha()
    {
        global $kernel;

        $captcha = new KCAPTCHA();
        $captcha->root_path = $kernel->pub_site_root_get ();
        $captcha->deleteOld();
        $captcha->makeKcaptcha();
        $kernel->pub_session_set ('captcha_keystring', $captcha->getKeyString());
        return $captcha->gen_img_path;
    }


    
    
    //***********************************************************************
    //	Наборы внутренних методов модуля
    //**********************************************************************


    //***********************************************************************
    //	Наборы методов, для работы с административным интерфейсом модуля
    //**********************************************************************

    /**
     * Формирует меню модуля
     *
     * Здесь описывается меню модуля, которое будет выводиться при входе в административный
     * раздел модуля
     * @param object $menu
     * @return boolean
     */
	public function interface_get_menu( $menu )
	{
        $menu -> set_menu_block( '[#feedback_admin_block_menu1#]' );
        $menu -> set_menu( '[#feedback_admin_block_menu_item1#]', "show_list",      array( 'flush' => 1 ) );
        $menu -> set_menu_default( 'list' );
        $menu->set_menu_default('show_list');

	    return true;
	}

	/**
     * Основной метод модуля, из которого расходиться всё управление административным разделом модуля
	 * 
	 */
    function start_admin()
    {
        global $kernel;
        
        $html = '';
        switch ($kernel->pub_section_leftmenu_get())
        {
            default:
        	case 'show_list':
                return $this -> priv_show_list();
        		break;

        	case 'item_remove':
                $this -> priv_item_delete( $kernel -> pub_httpget_get( 'id' ) );
                $kernel -> pub_redirect_refresh( 'show_list' );
                break;

            case 'show_detail':
                return $this -> priv_show_detail( $kernel -> pub_httpget_get( 'id' ) );
                break;
        }

        return isset( $content ) ? $content : null;
    }

	private function priv_show_list()
	{
        global $kernel;

        $template = $kernel -> pub_template_parse( $this -> admin_templates_path . 'show_list.html' );

       	$query = 'SELECT *, DATE_FORMAT(`date`, "%d.%m.%Y") AS `date_formated` FROM `'.PREFIX.'_feedback` ORDER BY `date` DESC;';
    	$result = $kernel -> runSQL( $query );

    	if ( ( mysql_num_rows( $result ) == 0 ) ) {
            return $template[ 'no_data' ];
    	}

    	$lines = array();
        while ( $row = mysql_fetch_assoc( $result ) )
        {
        	$message = htmlspecialchars( $row['message'] );
        	if ( strlen( $message ) > $this -> admin_message_len ) {
        		$message  = substr( $message, 0, $this -> admin_message_len ) . '...';
        	}
        	
            $line = $template[ 'table_body' ];
            $_1 = array('%id%'
                      , '%date%'
                      , '%author%'
                      , '%email%'
                      , '%message%'
                      , '%action_detail%'
                      , '%action_remove%'
                       );
            $_2 = array($row['id']
                      , $row['date_formated']
                      , $row['author']
                      , ( trim( $row['email'] ) == '' ) ? '' : '&lt;' . $row['email'] . '&gt;'
                      , $message
                      , 'show_detail'
                      , 'item_remove'
                       );
                       
            $line = str_replace( $_1, $_2, $line );
            $lines[] = $line;
        }

        $header  = $template[ 'table_header' ];
        $content = $header . implode( "\n", $lines ) . $template[ 'table_footer' ];
        $content = str_replace( '%form_action%', $kernel -> pub_redirect_for_form( 'list_actions' ), $content );

        return $content;
	}


    private function priv_item_delete( $item_id )
    {
    	global $kernel;

    	$query = 'DELETE FROM `'.PREFIX.'_feedback` WHERE `id` = '.$item_id.' LIMIT 1;';
        $kernel -> runSQL( $query );
    }


    private function priv_show_detail( $item_id )
    {
        global $kernel;

        $template = $kernel -> pub_template_parse( $this -> admin_templates_path . 'show_detail.html' );

       	$query = 'SELECT *, DATE_FORMAT(`date`, "%d.%m.%Y") AS `date_formated` FROM `'.PREFIX.'_feedback` WHERE id=' . $item_id . ' ORDER BY `date` DESC;';
    	$result = $kernel -> runSQL( $query );

    	if ( ( mysql_num_rows( $result ) == 1 ) ) {
    		$row = mysql_fetch_assoc( $result );
            $line = $template[ 'table_body' ];
        	$message = htmlspecialchars( $row['message'] );
            $_1 = array('%id%'
                      , '%date%'
                      , '%author%'
                      , '%email%'
                      , '%message%'
                      , '%action_remove%'
                       );
            $_2 = array($row['id']
                      , $row['date_formated']
                      , $row['author']
                      , ( trim( $row['email'] ) == '' ) ? '' : '&lt;' . $row['email'] . '&gt;'
                      , $message
                      , 'item_remove'
                       );
                       
            $line = str_replace( $_1, $_2, $line );
    	}
    	else {
    		return $template[ 'no_data' ];
    	}

        $header  = $template[ 'table_header' ];
        $content = $header . $line . $template[ 'table_footer' ];
        $content = str_replace( '%form_action%', $kernel -> pub_redirect_for_form( 'list_actions' ), $content );

        return $content;
    }
}


?>