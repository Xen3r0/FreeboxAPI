<?php

/**
 * Utilisation de l'API Freebox OS
 *
 * @author Manuel SANTISTEBAN : manuel.santisteban@orange.fr
 * @version 1.0
 */
class FreeboxAPI {

	/**
	 * Server request URL
	 * @var String
	 */
	private $url_host;

	/**
	 * API Version
	 * @var String
	 */
	private $url_api_version;

	/**
	 * API request URL
	 * @var String
	 */
	private $url_api;

	/**
	 * Login URL
	 * @var String
	 */
	private $url_login;

	/**
	 * Authorization URL
	 * @var String
	 */
	private $url_authorize;

	/**
	 * Opening a session
	 * @var String
	 */
	private $url_session;

	/**
	 * Call URL
	 * @var String
	 */
	private $url_call;

	/**
	 * A unique app_id string
	 * @var String
	 */
	private $app_id;

	/**
	 * A descriptive application name (will be displayed on lcd)
	 * @var String
	 */
	private $app_name;

	/**
	 * app version
	 * @var String
	 */
	private $app_version;

	/**
	 * The name of the device on which the app will be used
	 * @var String
	 */
	private $devise_name;

	/**
	 * Freebox JSON result
	 * @var Array
	 */
	private $json_freebox;

	/**
	 * Authorize JSON result
	 * @var Array
	 */
	private $json_authorize;

	/**
	 * Authorize JSON result with track_id
	 * @var Array
	 */
	private $json_authorize_track;

	/**
	 * Login JSON result
	 * @var Array
	 */
	private $json_login;

	/**
	 * Session JSON result
	 * @var Array
	 */
	private $json_session;

	/**
	 * Initialize the API call
	 */
	public function __construct() {
		// Defaults values for your application
		$this->app_id = "xenerodeveloppement";
		$this->app_name = "www.xenero-developpement.com";
		$this->app_version = "1.0";
		$this->device_name = "Manuel-PC";

		// Session already exist
		if (isset($_COOKIE["app_token"]) && !empty($_COOKIE["app_token"]) && isset($_COOKIE["track_id"]) && !empty($_COOKIE["track_id"])) {
			$this->json_authorize["success"] = true;
			$this->json_authorize["result"]["app_token"] = $_COOKIE["app_token"];
			$this->json_authorize["result"]["track_id"] = intval($_COOKIE["track_id"]);
		}

		// Construct URL for call API
		$this->construct_urls();
	}

	/**
	 * Construct the URL
	 */
	private function construct_urls() {
		$this->url_host = "mafreebox.freebox.fr";

		if (!$this->json_freebox["uid"]) {
			$this->url_api = "/api/v1/";
		} else {
			$this->url_api = $this->json_freebox["api_base_url"] . "/v" . (int) $this->json_freebox["api_version"] . "/";
		}

		$this->url_login = $this->url_host . $this->url_api . "login/";
		$this->url_authorize = $this->url_login . "authorize/";
		$this->url_session = $this->url_login . "session/";
		$this->url_call = $this->url_host . $this->url_api . "call/log/";
	}

	/**
	 * Call API URL
	 * @param string $url
	 * @param array $post
	 * @return array
	 * @throws Exception
	 */
	private function call_api($url, $post = array()) {
		$header = array("Content-Type: application/json");
		$ch = curl_init();

		// Session is open, concat session token
		if ($this->json_session["result"]["session_token"]) {
			array_push($header, "X-Fbx-App-Auth: " . $this->json_session["result"]["session_token"]);
		}

		$defaults = array(
			CURLOPT_HEADER => 0,
			CURLOPT_URL => $url,
			CURLOPT_FRESH_CONNECT => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FORBID_REUSE => 1,
			CURLOPT_TIMEOUT => 4,
			CURLOPT_HTTPHEADER => $header
		);

		// If post method
		if (count($post) > 0) {
			$defaults[CURLOPT_POST] = 1;
			$defaults[CURLOPT_POSTFIELDS] = json_encode($post);
		}

		curl_setopt_array($ch, $defaults);

		$result = curl_exec($ch);
		curl_close($ch);

		if ($result === false)
			throw new Exception("API call error");

		$json_result = json_decode($result, true);

		if (isset($json_result["error"]))
			throw new Exception(json_encode($result));

		if (!$json_result["success"]) {
			switch ($json_result["error_code"]) {
				case "auth_required":
					throw new Exception("Invalid session token, or not session token sent");
				case "invalid_token":
					throw new Exception("The app token you are trying to use is invalid or has been revoked");
				case "pending_token":
					throw new Exception("The app token you are trying to use has not been validated by user yet");
				case "insufficient_rights":
					throw new Exception("Your app permissions does not allow accessing this API");
				case "denied_from_external_ip":
					throw new Exception("You are trying to get an app_token from a remote IP");
				case "invalid_request":
					throw new Exception("Your request is invalid");
				case "ratelimited":
					throw new Exception("Too many auth error have been made from your IP");
				case "new_apps_denied":
					throw new Exception("New application token request has been disabled");
				case "apps_denied":
					throw new Exception("API access from apps has been disabled");
				case "internal_error":
					throw new Exception("Internal error");
			}
		}

		return $json_result;
	}

	/**
	 * Request authorization
	 * @return boolean
	 * @throws Exception
	 */
	public function authorize() {
		if ($this->json_authorize["success"] && $this->json_authorize["result"]["track_id"] <> 0)
			return $this->authorize_id($this->json_authorize["result"]["track_id"]);

		$this->json_authorize = $this->call_api($this->url_authorize, array(
			"app_id" => $this->app_id,
			"app_name" => $this->app_name,
			"app_version" => $this->app_version,
			"device_name" => $this->device_name
		));

		if (!$this->json_authorize["success"]) {
			return false;
		} else {
			$expire = time() + (365 * 24 * 60 * 60);
			setcookie('app_token', $this->json_authorize["result"]["app_token"], $expire);
			setcookie('track_id', $this->json_authorize["result"]["track_id"], $expire);

			return true;
		}
	}

	/**
	 * Track authorization progress
	 * @param int $track_id
	 * @return boolean
	 * @throws Exception
	 */
	private function authorize_id($track_id) {
		$this->json_authorize_track = $this->call_api($this->url_authorize . $track_id);

		switch ($this->json_authorize_track["result"]["status"]) {
			case "unknown":
				throw new Exception("The app_token is invalid or has been revoked");
			case "timeout":
				throw new Exception("The user did not confirmed the authorization within the given time");
			case "denied":
				throw new Exception("The user denied the authorization request");
		}

		return $this->json_authorize_track["success"];
	}

	/**
	 * Login application on Freebox Server
	 * @return boolean
	 * @throws Exception
	 */
	private function login() {
		if (!$this->json_authorize_track["success"] || !$this->json_authorize_track["result"]["status"] == "granted")
			throw new Exception("You are not allowed to authenticate to the Freebox Server");

		$this->json_login = $this->call_api($this->url_login);

		return $this->json_login["success"];
	}

	/**
	 * Generate password for open session
	 * @return string
	 */
	private function generate_password() {
		return hash_hmac('sha1', $this->json_login["result"]["challenge"], $this->json_authorize["result"]["app_token"]);
	}

	/**
	 * Open session
	 * @return boolean
	 */
	public function session() {
		if (!$this->login())
			return false;

		$this->json_session = $this->call_api($this->url_session, array(
			"app_id" => $this->app_id,
			"password" => $this->generate_password()
		));

		return $this->json_session["success"];
	}

	/**
	 * Access granted or not ?
	 * @return boolean
	 */
	public function access_granted() {
		if (!isset($this->json_authorize_track["result"]["status"]))
			return false;

		if ($this->json_authorize_track["result"]["status"] == "granted")
			return true;
		else
			return false;
	}

}

?>