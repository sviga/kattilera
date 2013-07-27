<?php
//###---����� ��� �������� ����� � ��������(��������� ������������� ��������-������� � html-���� ������)
// - ��� ����������� ����� � �������� ���������� ������� ���������� ��� ����������� ������ � ������� �� ������ ���
//   �������; ����� ������� ����� �����-������ ���������
// - ������������� ����������� ���������� ������ ������ ���������, ��� ����� ���������� ������ $this->to ��������:
//   $this->to = array('first@email.ru', 'Next Adress <second@email.ru>');
//error_reporting(E_ALL);
//-----------------------------------------------------------------------------
// ��������� ������� ��� ������������� � preg_replace_callback().
    function mailenc_header_callback($p)
    {
        $encoding = $GLOBALS['mail_enc_header_encoding'];
        // ������� � ����� ��������� �����������������.
        preg_match('/^(.*?)(\s*)$/s', $p[1], $sp);
        return "=?$encoding?B?".base64_encode($sp[1])."?=".$sp[2];
    }


//##############################################################################

class multi_mail
{
    var $from;
    var $reply_to;
    var $return_path;
    var $to;
    var $headers;
    var $text_html;
    var $subject;
    var $body;
    var $input_encode;
    var $output_encode;
//------------------------------------------------------------------------------
//�����������
    function multi_mail()
    {
        $this->from = "";
        $this->reply_to = "";
        $this->return_path = "";
        $this->to = "";
        $this->headers = Array();
        $this->text_html = "text/plain";
        $this->subject = "";
        $this->body = "";
        $this->input_encode = "windows-1251";
        $this->output_encode = "koi8-r";
    }

//------------------------------------------------------------------------------
//---��������� ������ �� ��������� $this->input_encode � $this->output_encode
  function stringEncode($value)
  {
    return convert_cyr_string ($value, 'w', 'k');
    //iconv($this->input_encode, $this->output_encode, $value);
  }
//------------------------------------------------------------------------------
//---������������ ������ �� ������� e-mail injection
//������� ������ ������� <, > � ����� $from, $reply_to ,$return_path, $to;
//                    ������� ������ �������
  function crlfClear($value)
  {
  //��������� �� ������� ��������
      $search  = array("'/\n\s*/s'","'\r\s*/s'");
      $replace = array('','');
      $value = preg_replace ($search, $replace, $value);

  //��������� �� ������� � ����� <, >
      $search  = array('<', '>');
      $replace = array(' ', ' ');
      $value = str_replace($search, $replace, $value);
      $value = preg_replace('{[A-Za-z\d][A-Za-z\d-_.]*[A-Za-z\d]@[A-Za-z\d][A-Za-z\d-]*(\.[A-Za-z\d-]{2,30})*(\.[a-zA-Z]{2,7})}s', ' <$0>', $value);
      $value = preg_replace('/ +/', ' ', trim($value));
      return $value;
  }

//------------------------------------------------------------------------------
// �������� � ������ ����������� ��������� ������������������
// ��������, ������������ � ������������� ������� � ��
// ���������� E-mail (������ E-mail ��������� ��������� < � >).
// ���� � ������ ��� �� ������ ������������� �������, ��������������
// �� ������������. ���������� ����� ����������� base64.
  function mailenc_header($header) {
    $GLOBALS['mail_enc_header_encoding'] = $this->output_encode;
    return preg_replace_callback(
      '/([\x7F-\xFF][^<>\r\n]*)/s',
      'mailenc_header_callback',
      $header
    );
  }
//------------------------------------------------------------------------------
//---������������ ��� ������� ������ ��� ������ crlfClear($value) � ���� ���������� - ��������� �� � ������ ���������
  function inputPrepare()
  {
    //������ ������
    foreach ($this->to as $ind => $mailto)
    {
        $this->to[$ind] = $this->crlfClear($mailto);
    }
      $this->from         = $this->crlfClear($this->from);
      $this->reply_to     = $this->crlfClear($this->reply_to);
      $this->return_path  = $this->crlfClear($this->return_path);
      $this->subject      = $this->crlfClear($this->subject);

      //������������ � ������ ���������
      $encode_types = array("windows-1251", "koi8-r", "UTF-8", "ISO-8859-1", "koi8-u", "ASCII");
      if ( $this->input_encode != $this->output_encode &&
         in_array($this->input_encode, $encode_types) &&
         in_array($this->output_encode, $encode_types) )
        {
            foreach ($this->to as $ind => $mailto)
            {
            $this->to[$ind] = $this->stringEncode($mailto);
            }
            $this->from         = $this->stringEncode($this->from);
            $this->reply_to     = $this->stringEncode($this->reply_to);
            $this->return_path  = $this->stringEncode($this->return_path);
            $this->subject      = $this->stringEncode($this->subject);
            $this->body         = $this->stringEncode($this->body);
        }

        //�������� ��� �������� �� �����
        foreach ($this->to as $ind => $mailto)
    {
        $this->to[$ind] = $this->mailenc_header($mailto);
    }
      $this->from         = $this->mailenc_header($this->from);
      $this->reply_to     = $this->mailenc_header($this->reply_to);
      $this->return_path  = $this->mailenc_header($this->return_path);
      $this->subject      = $this->mailenc_header($this->subject);
  }
//------------------------------------------------------------------------------
//������ ���� - ����� ���������; ����� ������� ������� ������, ������� ������
    function attach_file($file_name = "" , $file_content, $encoding_type = "application/octet-stream")
    {
        $this -> headers[] = array("name" => $file_name,
                                   "content" => $file_content,
                                   "encode" => $encoding_type
                                  );
    }

//------------------------------------------------------------------------------
// ������ ����� ������, ���� �� ���������� ���� ��� ������� �����
    function build_letter($header)
    {
        $letter = $header["content"];
        if ($header["encode"] == "text/plain" || $header["encode"] == "text/html")
        {
            $encoding = "8bit";
        }
        else
        {
            $letter = chunk_split(base64_encode($letter));
            $encoding = "base64";
        }//fi

        $header_name = $this->crlfClear($header["name"]);
        $header_name = $this->stringEncode($header_name);
        $header_name = $this->mailenc_header($header_name);

        return "Content-Type: ".$header["encode"]
               .($header["name"]? "; name = \"".$header_name."\"" : "; charset=\"".$this->output_encode."\"")."\n"
               ."Content-Transfer-Encoding: $encoding"
               .($header["name"]? "\nContent-Disposition: attachment; filename = \"".$header_name."\"" : "")
               ."\n\n$letter\n";
    }

//------------------------------------------------------------------------------
//c������� ������ c ������� �� ������������ ������
//������������ �����-�������������� ����� ������ � ���������� ����������� ����� ����
    function set_multipart_mail()
    {
        $boundary = 'b'.md5(uniqid(time()));

        $multipart = "Content-Type: multipart/mixed; boundary =$boundary\n\nThis is a MIME encoded letter\n\n--$boundary";
        for($step = count($this->headers)-1; $step >=0; $step--)
        {
            $multipart .= "\n".$this->build_letter($this->headers[$step])."--$boundary";
        }//rof
        return $multipart .= "--\n";
    }

//------------------------------------------------------------------------------
//c������� ������ ��� ������ (������ ��������� ����������)
    function set_text_html_mail()
    {
        $multipart = $this->build_letter($this->headers[0]);
        return $multipart .= "\n";
    }

//------------------------------------------------------------------------------
// ��������� ���� ������ (��������� �������) � ��� ����� (���� ��� ����)
// �� ������ �������� ������ ������ (���� ������� ������ )
    function get_full_message()
    {
        $mime  = "From: ".$this->from." \n";
        $mime .= "Reply-To: ".$this->reply_to." \n";
        $mime .= "Return-Path: ".$this->return_path." \n";
        $mime .= "Message-ID: <".md5(time())." TheSystem@".$_SERVER['SERVER_NAME'].">\n";
        $mime .= "X-Mailer: PHP v".phpversion()."\n";
        if (!empty($this->body))
        {
             if (count($this->headers)>0)
             {
                 $this -> attach_file("",$this->body,$this->text_html);
                 $mime .= "MIME-Version: 1.0\n".$this->set_multipart_mail();
             }
             else
             {
                 $this -> attach_file("",$this->body,$this->text_html);
                 $mime .= "MIME-Version: 1.0\n".$this->set_text_html_mail();
             }//fi

        }//fi

        return $mime;
    }

//------------------------------------------------------------------------------
//
    function send_mail()
    {
        if (!is_array($this->to))
        {
          $this->to = array($this->to);
        }

        $this->inputPrepare();
        $mime = $this -> get_full_message();
        preg_match('{[A-Za-z\d][A-Za-z\d-_.]*[A-Za-z\d]@[A-Za-z\d][A-Za-z\d-]*(\.[a-zA-Z]{2,7})*}s', $this->return_path, $return_path);

        foreach ($this->to as $mailto)
        {
            set_time_limit(30);
          @mail($mailto, $this->subject, '', $mime);
          // @mail($this->to, $this->subject, "", $mime);
        }


    }
}


?>
