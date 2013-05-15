<?PHP

class Thread
{
        var $id = 0;  // идентификатор потока.
        var $http_debug_enabled = false;                // Выдавать ли заголовки
        var $common_debug_enabled = false;        // Подключаюсь, отключаюсь и т.п.

        var $url;

        var $stream;
        var $domain;
        var $uri = "/";

        var $headers = array
                (
                        'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
                        'Connection' => 'Keep-Alive',
                        'Accept'     => 'image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, application/x-shockwave-flash, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/msword, application/x-gsarcade-launch, */*'

                );
        var $method = "GET";


        var $connect_timeout = 3;
        var $idle_timeout          = 3;
        var $max_size                 = false;

        var $response_headers        = "";
        var $response_body                = "";

        // Статус
        var $connected = false;
        var $headers_sent = false;
        var $response_headers_got = false;
        var $response_body_got = false;
        var $closed = false;


        var $errno = 0;
        var $errors =
                array
                (
                        0 => 'OK',
                        1 => 'Плохой урл',
                        3 => 'Ошибка соединения',
                        4 => 'Таймаут на простой'
                );


        var $content_length;
        var $post_content = "";
        var $last_read_time;

        var $chunk_length_got = false;
        var $chunk_length;
        var $got_chunk_length;
        var $block_size = 10240;


        function Thread($url)
        {
                static $last_thread_id = 0;
                $last_thread_id++;
                $this->id = $last_thread_id;
                $this->url = $url;
        }

        function get_id()
        {
            return $this->id;
        }

        function get_redirect_url_if_exits_and_same_domain()
        {
                $domain = preg_quote($this->domain, "'");
                $redirect_url = $this->get_redirect_url_if_exists();
                if ($redirect_url === false)
                        return false;

                $url_parser = new UrlParser($this->url);
                $redirect_url = $url_parser->get_absolute_url($redirect_url);

                if (preg_match("'^http://".$domain."(/|\\?)'i", $redirect_url) || preg_match("'^http://".$domain."$'i", $redirect_url))
                        return $redirect_url;
                else
                        return false;
        }

        function get_redirect_url_if_exists()
        {
                if (preg_match("/Location: (.+?)\n/", $this->response_headers, $matches))
                        return trim($matches[1]);
                else
                        return false;
        }

        function get_charset()
        {
                if (preg_match("/Content-Type: (.+?); charset=(.+?)\n/i", $this->response_headers, $matches))
                        return trim($matches[2]);
                else
                        return false;
        }

        function get_content_type()
        {
            if (preg_match("/Content-Type: (.+?)(;|\n)/i", $this->response_headers, $matches))
                    return trim($matches[1]);
            else
                    return false;
        }

        function set_connect_timeout($connect_timeout)                {$this->connect_timeout = $connect_timeout;}
        function set_idle_timeout($idle_timeout)                        {$this->idle_timeout = $idle_timeout;}
        function set_max_size($max_size)                                        {$this->max_size = $max_size;}

        function set_method($method)
        {
                $this->method = strtoupper($method);
        }

        function set_header($header_name, $header_value)
        {
                $this->headers[$header_name] = $header_value;
        }



        function set_post_data($post_data)
        {
        	$this->post_content = $post_data;
        }

        function set_post_vars($post_vars)
        {
                $content_parts = array();
                foreach ($post_vars as $key=>$val)
                {
                        if (!is_array($val))
                                $content_parts[] = urlencode($key).'='.urlencode($val);
                        else
                        {
                                $parts = $this->recurs_val(urlencode($key), array(), $val);
                                $content_parts = array_merge($content_parts, $parts);
                        }
                }
                $this->post_content = join("&", $content_parts);
        }

        /*

        Функция сделана для того, чтобы работали формы, где
        в полях name стоит что-нибудь типа massiv[0][bred]

        */
        function recurs_val($varname, $keys, $vals)
        {
                //print "<hr>";
                //print_r($keys);
                $result = array();
                foreach ($vals as $key => $val)
                {
                        $more_keys = $keys;
                        $more_keys[] = urlencode($key);
                        if (is_array($val))
                        {
                                $result = array_merge($result, $this->recurs_val($varname, $more_keys, $val));
                        }
                        else
                        {
                                $key_str = join("][", $more_keys);
                                $key_str = "[$key_str]";
                                $result[] = "$varname$key_str=".urlencode($val);
                        }
                }
                return $result;
        }




        // Состояние
        function connected()                                {return $this->connected;}
        function headers_sent()                                {return $this->headers_sent;}
        function response_headers_got()                {return $this->response_headers_got;}
        function response_body_got()                {return $this->response_body_got;}
        function closed()                                        {return $this->closed;}


        function get_ip($host)
        {
            if ($this->is_dns_correct($host))
                 return gethostbyname($host);
            else
                return false;
        }

        function save_response_body()
        {
            if (empty($this->response_body))
                    return;

            $filename = ( isset($this->domain) ? $this->domain : "no_domain" ) .'.txt';

            $fp = fopen($filename, "w");
            fwrite($fp, $this->response_body);
            fclose($fp);
        }


        function parse_url()
        {
                if (!preg_match("'http://'", $this->url))
                        return false;

                $parts = parse_url($this->url);
                //print_r($parts);

                if (!isset($parts['host']))
                        return false;

                $this->domain = $parts['host'];
                if (isset($parts['path']))
                        $this->uri = $parts['path'];
                else
                        $this->uri = "/";

                if (isset($parts['query']))
                        $this->uri .= "?".$parts['query'];

                return true;
        }


        function is_dns_correct($host)
        {
             $command = "nslookup -type=A -timeout=5 -retry=1 $host";
             $strings = array();
             $last_string = exec($command, $strings);

             //print_r($strings);
             //print "($last_string)";

             foreach ($strings as $string)
                  if (preg_match("/timed.out/i", $string))
                      return false;

             return true;
        }


        function connect()
        {
                $this->debug("Parse url $this->url... \r\n", false);
                if (!$this->parse_url())
                {
                        $this->errno = 1;
                        $this->closed = true;
                        return false;
                }
                $this->set_header("Host", $this->domain);

                $this->debug("Trying to connect... \r\n\r\n", false);


                $ip = $this->get_ip($this->domain);
                if ($ip === false)
                {
                        $this->errno = 3;
                        $this->closed = true;
                        return false;
                }



                $this->stream = fsockopen($ip, 80, $errno, $errstr, $this->connect_timeout);

                if (!$this->stream)
                {
                        $this->errno = 3;
                        $this->closed = true;
                        return false;
                }
                else
                {
                        $this->last_read_time = time();
                        socket_set_blocking($this->stream, 1);
                        $this->connected = true;
                        return true;
                }
        }




        function close()
        {
                fclose($this->stream);
                $this->debug("closing conection (error: $this->errno (".$this->errors[$this->errno]."))\r\n", false);
                $this->closed = true;
        }




        function send_headers()
        {
                $this->debug("Sending headers...\r\n", false);
                $start_string = "$this->method $this->uri HTTP/1.1";
                $this->put_str($start_string);

                if ($this->method == 'POST')
                {
                        $this->set_header("Content-Length", strlen($this->post_content));
                        $this->set_header("Content-Type", "application/x-www-form-urlencoded");
                }

                foreach ($this->headers as $key=>$header)
                        $this->put_str("$key: $header");
                $this->put_str();
                if ($this->method == 'POST')
                        $this->put($this->post_content);
                //$this->put_str();
                socket_set_blocking($this->stream, 0);
                $this->headers_sent = true;
        }



        function try_get_headers()
        {



            if ($this->response_headers_got)
                    return;

            //$this->check_timeout();
            if ($this->closed)
                    return;

            $str = fgets($this->stream, 1024);
            if (strlen($str)>0)
            {
                $this->debug($str);
                $this->last_read_time = time();
                $this->response_headers .= $str;
                //if (trim($str) == "")
                if (preg_match("/(\r\n\r\n|\n\n)/", $this->response_headers))
                {
                    $this->response_headers_got = true;
                    //print $this->response_headers;
                    //die;
                }
            }
            else
            	$this->check_timeout();
        }

        function check_timeout()
        {
            if (time() - $this->last_read_time > $this->idle_timeout)
            {
                $this->errno = 4;
                $this->close();
            }
        }

        function get_response_type()
        {
                if (preg_match("/Content-Length: (\d+?)(\r|\r\n)/i", $this->response_headers, $matches))
                {
                        $this->content_length = $matches[1];
                        //print "Content-Length: ".$this->content_length."\r\n";
                        return "length";
                }
                elseif (preg_match("/Transfer-Encoding: chunked(\r|\r\n)/i", $this->response_headers))
                        return "chunked";
                else
                        return "feof";
        }



        function go()
        {
        	while (!$this->closed())
        		$this->cycle();
        }

        function cycle()
        {
        	if (!$this->connected())
				$this->connect();
			elseif (!$this->headers_sent)
				$this->send_headers();
			elseif (!$this->response_headers_got())
				$this->try_get_headers();
			elseif (!$this->response_body_got())
				$this->try_get_body();
			elseif (!$this->closed())
				$this->close();
        }

        function try_get_body()
        {
                if ($this->response_body_got)
                        return;
                $str = "";
                switch ($this->get_response_type())
                {
                        case "length":
                                $str = fread($this->stream, $this->block_size);
                                $this->response_body .= $str;
                                if (strlen($this->response_body) >= $this->content_length)
                                        $this->response_body_got = true;
                                break;
                        case "feof":
                                $str = fread($this->stream, $this->block_size);
                                $this->response_body .= $str;
                                if (feof($this->stream))
                                        $this->response_body_got = true;
                                break;
                        case "chunked":

/*                                var $chunk_length_got = false;
                                var $chunk_size;
                                var $chunk_length;
*/
                                if (!$this->chunk_length_got)
                                {
                                        $chunk_str = fgets($this->stream);
                                        if (strlen(trim($chunk_str)) > 0)
                                        {
                                                $this->debug("chunk: 0x".trim($chunk_str)."\r\n");
                                                $chunk_length = hexdec($chunk_str);
                                                $this->chunk_length = $chunk_length;
                                                $this->chunk_length_got = true;
                                                $this->got_chunk_length = 0;

                                                if ($chunk_length == 0)
                                                        $this->response_body_got = true;
                                        }
                                        elseif ($chunk_str == "\r\n")
                                                $this->debug("\r\n");
                                        elseif (feof($this->stream))
                                        {
                                        	$this->response_body_got = true;
                                        	$this->debug("FEOF!");
                                        	//print "Fuck ($chunk_str)";
                                        }
                                }
                                else
                                {
                                        $str = fread($this->stream, min($this->block_size, $this->chunk_length - $this->got_chunk_length));
                                        if (strlen($str) > 0)
                                        {
                                                $this->response_body .= $str;
                                                $this->got_chunk_length += strlen($str);
                                                if ($this->got_chunk_length == $this->chunk_length)
                                                        $this->chunk_length_got = false;
                                        }
                                }

                                break;
                }

                if (strlen($str) > 0)
                {
                        $this->last_read_time = time();
                        $kb = sprintf("%1.02f", strlen($this->response_body)/1024);
                        $this->debug(".($kb)Kb\r\n");
                }
                $this->check_timeout();
                if ($this->max_size !== false)
                {
                        if (strlen($this->response_body) > $this->max_size)
                                $this->response_body_got = true;
                }
        }



        function put_str($str="")
        {
                $str .= "\r\n";
                $this->put($str);
        }


        function put($str)
        {
                $this->debug($str);
                fwrite($this->stream, $str);
        }


        function debug($str, $http=true)
        {
                if ($http && !$this->http_debug_enabled || !$http && !$this->common_debug_enabled)
                        return;
                if ($http)
                        $str = "HTTP> ".$str;

                $str = $this->domain.": ".$str;

                print $str;
                flush();
        }
}


?>