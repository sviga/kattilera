<?php

class DownloaderResult
{
    var $url;
	var $errno;
	var $error;

	/**
	 * @var responseheaders
	 */
	var $responseheaders;

	/**
	 * @var ResponseContent
	 */
	var $responsecontent;

	function DownloaderResult()
	{

	}

	function set_errno($errno)
	{
		$this->errno = $errno;
	}

	function set_error($error)
	{
		$this->error = $error;
	}

	function set_responseheaders($headers)
	{
		$this->responseheaders = new ResponseHeaders($headers);
	}

	function set_responsecontent($content)
	{
		$this->responsecontent = new ResponseContent($content);
	}

	function set_url($url)
	{
	    $this->url = $url;
	}

	function set_response($response)
	{
		list($response_headers, $response_body) = explode("\r\n\r\n", $response, 2);
		$this->set_responseheaders($response_headers);
		$this->set_responsecontent($response_body);
	}

}