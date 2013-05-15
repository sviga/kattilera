<?php

class ResponseHeaders
{
	var $response_code = false;
	var $raw_headers = false;
	var $headers = array();
	var $response_header_lines;

	function ResponseHeaders($response_headers)
	{
		$this->raw_headers = $response_headers;
		$response_header_lines = explode("\r\n", $response_headers);
		$this->response_header_lines = $response_header_lines;


		$http_response_line = array_shift($response_header_lines);

		if (preg_match('@^HTTP/[0-9]\.[0-9] ([0-9]{3})@', $http_response_line, $matches))
			$this->response_code = $matches[1];


		foreach ($response_header_lines as $header_line)
		{

			if (trim($header_line) == '')
				continue;
			list($header, $value) = explode(': ',$header_line,2);
			$this->headers[$header] = $value;
		}
	}

	function get_header($needed_key)
	{
	    foreach ($this->headers as $key=>$header)
	    {
	        if (strtolower($needed_key) == strtolower($key))
	           return $header;
	    }
	    return false;

	}


	function get_simple_cookies()
	{

	    $cookies = array();
	    foreach ($this->response_header_lines as $line)
	    {

	        if (preg_match_all("/Set-cookie:(.+?);/i", $line, $matches))
	        {
	            foreach ($matches[1] as $ravno)
	            {
                    $ravno = trim($ravno);
                    $parts = explode("=", $ravno);
                    $cookies[$parts[0]] = $parts[1];
	            }
	        }
	    }
	    return $cookies;
	}

	function get_content_type()
	{
		$content_type = $this->get_header("Content-Type");
		if (preg_match("/^(.+?)(;|$)/", $content_type, $matches))
			return $matches[1];
		else
			return false;
	}

}