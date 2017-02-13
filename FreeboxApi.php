<?php

namespace Xen3r0\Freebox\Api;

/**
 * Utilisation de l'API Freebox OS
 *
 * @author  Manuel SANTISTEBAN : manuel.santisteban@orange.fr
 * @version 1.1
 */
class FreeboxApi
{
    /**
     * Server request URL
     *
     * @var string
     */
    private $urlHost;

    /**
     * API request URL
     *
     * @var string
     */
    private $urlApi;

    /**
     * Login URL
     *
     * @var string
     */
    private $urlLogin;

    /**
     * Authorization URL
     *
     * @var string
     */
    private $urlAuthorize;

    /**
     * Opening a session
     *
     * @var string
     */
    private $urlSession;

    /**
     * Call URL
     *
     * @var string
     */
    private $urlCall;

    /**
     * A unique app_id string
     *
     * @var string
     */
    private $appId;

    /**
     * A descriptive application name (will be displayed on lcd)
     *
     * @var string
     */
    private $appName;

    /**
     * app version
     *
     * @var string
     */
    private $appVersion;

    /**
     * Freebox JSON result
     *
     * @var array
     */
    private $jsonFreebox;

    /**
     * Authorize JSON result
     *
     * @var array
     */
    private $jsonAuthorize;

    /**
     * Authorize JSON result with track_id
     *
     * @var array
     */
    private $jsonAuthorizeTrack;

    /**
     * Login JSON result
     *
     * @var array
     */
    private $jsonLogin;

    /**
     * Session JSON result
     *
     * @var array
     */
    private $jsonSession;

    /**
     * Initialize the API call
     */
    public function __construct()
    {
        // Defaults values for your application
        $this->appId = 'xenerodeveloppement';
        $this->appName = 'www.xenero-developpement.com';
        $this->appVersion = '1.0';
        $this->device_name = 'Manuel-PC';

        // Session already exist
        if (isset($_COOKIE['app_token'])
            && !empty($_COOKIE['app_token'])
            && isset($_COOKIE['track_id'])
            && !empty($_COOKIE['track_id'])
        ) {
            $this->jsonAuthorize['success'] = true;
            $this->jsonAuthorize['result']['app_token'] = $_COOKIE['app_token'];
            $this->jsonAuthorize['result']['track_id'] = intval($_COOKIE['track_id']);
        }

        // Construct URL for call API
        $this->constructUrls();
    }

    /**
     * Request authorization
     *
     * @return boolean
     * @throws \Exception
     */
    public function authorize()
    {
        if ($this->jsonAuthorize['success'] && $this->jsonAuthorize['result']['track_id'] <> 0) {
            return $this->authorizeId($this->jsonAuthorize['result']['track_id']);
        }

        $this->jsonAuthorize = $this->callApi(
            $this->urlAuthorize,
            array(
                'app_id' => $this->appId,
                'app_name' => $this->appName,
                'app_version' => $this->appVersion,
                'device_name' => $this->device_name,
            )
        );

        if (!$this->jsonAuthorize['success']) {
            return false;
        } else {
            $expire = time() + (365 * 24 * 60 * 60);
            setcookie('app_token', $this->jsonAuthorize['result']['app_token'], $expire);
            setcookie('track_id', $this->jsonAuthorize['result']['track_id'], $expire);

            return true;
        }
    }

    /**
     * Open session
     *
     * @return boolean
     */
    public function session()
    {
        if (!$this->login()) {
            return false;
        }

        $this->jsonSession = $this->callApi(
            $this->urlSession,
            array(
                'app_id' => $this->appId,
                'password' => $this->generatePassword(),
            )
        );

        return $this->jsonSession['success'];
    }

    /**
     * Access granted or not ?
     *
     * @return boolean
     */
    public function accessGranted()
    {
        if (!isset($this->jsonAuthorizeTrack['result']['status'])) {
            return false;
        }

        if ($this->jsonAuthorizeTrack['result']['status'] == 'granted') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Construct the URL
     */
    private function constructUrls()
    {
        $this->urlHost = 'mafreebox.freebox.fr';

        if (!$this->jsonFreebox['uid']) {
            $this->urlApi = '/api/v1/';
        } else {
            $this->urlApi = $this->jsonFreebox['api_base_url'].'/v'.(int)$this->jsonFreebox['api_version'].'/';
        }

        $this->urlLogin = $this->urlHost.$this->urlApi.'login/';
        $this->urlAuthorize = $this->urlLogin.'authorize/';
        $this->urlSession = $this->urlLogin.'session/';
        $this->urlCall = $this->urlHost.$this->urlApi.'call/log/';
    }

    /**
     * Call API URL
     *
     * @param string $url
     * @param array  $post
     *
     * @return array
     * @throws \Exception
     */
    private function callApi($url, $post = array())
    {
        $header = array('Content-Type: application/json');
        $ch = curl_init();

        // Session is open, concat session token
        if ($this->jsonSession['result']['session_token']) {
            array_push($header, 'X-Fbx-App-Auth: '.$this->jsonSession['result']['session_token']);
        }

        $defaults = array(
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTPHEADER => $header,
        );

        // If post method
        if (count($post) > 0) {
            $defaults[CURLOPT_POST] = 1;
            $defaults[CURLOPT_POSTFIELDS] = json_encode($post);
        }

        curl_setopt_array($ch, $defaults);

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \Exception('API call error');
        }

        $json_result = json_decode($result, true);

        if (isset($json_result['error'])) {
            throw new \Exception(json_encode($result));
        }

        if (!$json_result['success']) {
            switch ($json_result['error_code']) {
                case 'auth_required':
                    throw new \Exception('Invalid session token, or not session token sent');
                case 'invalid_token':
                    throw new \Exception('The app token you are trying to use is invalid or has been revoked');
                case 'pending_token':
                    throw new \Exception('The app token you are trying to use has not been validated by user yet');
                case 'insufficient_rights':
                    throw new \Exception('Your app permissions does not allow accessing this API');
                case 'denied_from_external_ip':
                    throw new \Exception('You are trying to get an app_token from a remote IP');
                case 'invalid_request':
                    throw new \Exception('Your request is invalid');
                case 'ratelimited':
                    throw new \Exception('Too many auth error have been made from your IP');
                case 'new_apps_denied':
                    throw new \Exception('New application token request has been disabled');
                case 'apps_denied':
                    throw new \Exception('API access from apps has been disabled');
                case 'internal_error':
                    throw new \Exception('Internal error');
            }
        }

        return $json_result;
    }

    /**
     * Track authorization progress
     *
     * @param int $track_id
     *
     * @return boolean
     * @throws \Exception
     */
    private function authorizeId($track_id)
    {
        $this->jsonAuthorizeTrack = $this->callApi($this->urlAuthorize.$track_id);

        switch ($this->jsonAuthorizeTrack['result']['status']) {
            case 'unknown':
                throw new \Exception('The app_token is invalid or has been revoked');
            case 'timeout':
                throw new \Exception('The user did not confirmed the authorization within the given time');
            case 'denied':
                throw new \Exception('The user denied the authorization request');
        }

        return $this->jsonAuthorizeTrack['success'];
    }

    /**
     * Login application on Freebox Server
     *
     * @return boolean
     * @throws \Exception
     */
    private function login()
    {
        if (!$this->jsonAuthorizeTrack['success'] || !$this->jsonAuthorizeTrack['result']['status'] == 'granted') {
            throw new \Exception('You are not allowed to authenticate to the Freebox Server');
        }

        $this->jsonLogin = $this->callApi($this->urlLogin);

        return $this->jsonLogin['success'];
    }

    /**
     * Generate password for open session
     *
     * @return string
     */
    private function generatePassword()
    {
        return hash_hmac('sha1', $this->jsonLogin['result']['challenge'], $this->jsonAuthorize['result']['app_token']);
    }
}
