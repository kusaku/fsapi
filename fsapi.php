<?php
namespace FS;

/**
 * FSAPI class for fotostrana.ru social network
 *
 * @package server API methods
 * @link    http://fotostrana.ru/api/doc/
 * @autor   Oleg Illarionov, Cyril Arkhipenko
 * @version 1.0
 */
class FSAPI {

	private $app_id;
	private $api_url;
	private $client_key;
	private $server_key;

	public function __construct($cfg) {
		// set default
		$cfg += array(
			'api_url' => 'http://api.fotostrana.ru/apifs.php',
		);

		$this->app_id     = $cfg['appId'];
		$this->api_url    = $cfg['api_url'];
		$this->server_key = $cfg['serverKey'];
		$this->client_key = $cfg['clientKey'];
	}

	public function exec($method, $params = array(), $client = false) {
		$params['appId']     = $this->app_id;
		$params['method']    = $method;
		$params['timestamp'] = time();
		$params['rand']      = rand(0, 10000);
		$params['format']    = '1'; // JSON

		$params['sig'] = $this->sign($params, $client);

		$query = $this->api_url . '?' . http_build_query($params);

		$context = stream_context_create(
			array(
				'http' =>
					array(
						'timeout'       => 5,
						'ignore_errors' => true
					)
			));

		$response = json_decode(file_get_contents($query, false, $context), true);

		if (isset($response['error'])) {
			throw new \Exception("{$response['error']['error_msg']}", @$response['error']['error_code'] * 1E3 + @$response['error']['error_subcode']);
		}

		return $response;
	}

	public function sign($params, $client) {
		$sig = $client ? @$params['userId'] : '';
		ksort($params);
		foreach ($params as $k => $v) {
			$sig .= $k . '=' . $v;
		}
		$sig .= $client ? $this->client_key : $this->server_key;

		return md5($sig);
	}

	public function validate($params) {
		if (isset($params['sig'])) {
			$sig = $params['sig'];
			unset($params['sig']);

			return $sig == $this->sign($params, false);
		}

		return false;
	}

	public function response($data) {
		return json_encode($data);
	}

	public function upload($url, $files) {
		$boundary = uniqid('---------------------------', true);
		$content  = "--{$boundary}";

		foreach ($files as $name => $path) {
			$file_contents = file_get_contents($path);
			$file_name     = basename($path);
			$mime_type     = mime_content_type($path);
			$content .= "\r\n";
			$content .= "Content-Disposition: form-data; name=\"$name\"; filename=\"$file_name\"\r\n";
			$content .= "Content-Type: {$mime_type}\r\n";
			$content .= "Content-Transfer-Encoding: binary\r\n\r\n";
			$content .= $file_contents;
			$content .= "\r\n";
			$content .= "--{$boundary}";
		}

		$content .= "--\r\n";

		$header = "Content-Type: multipart/form-data; boundary={$boundary}\r\nContent-Length: " . strlen($content);

		$context = stream_context_create(
			array(
				'http' => array(
					'method'  => 'POST',
					'header'  => $header,
					'content' => $content,
				)
			));

		$response = json_decode(file_get_contents($url, false, $context), true);

		if (isset($response['error'])) {
			throw new \Exception("{$response['error']['error_msg']}", $response['error']['error_code']);
		}

		return $response;
	}
}
