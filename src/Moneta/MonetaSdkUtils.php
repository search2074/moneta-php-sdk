<?php

namespace Moneta;

use Moneta;


class MonetaSdkUtils
{
    /**
     * Paths
     */
	const INI_FILES_PATH 				= "/../../config/";
	const VIEW_FILES_PATH 				= "/../../view/";
    const EVENTS_FILES_PATH 			= "/../../events/";
	const LOGS_FILES_PATH 				= "/../../logs/";

    /**
     * ini Files
     */
	const INI_FILE_BASIC_SETTINGS 		= "basic_settings.ini";
	const INI_FILE_DATA_STORAGE 		= "data_storage.ini";
	const INI_FILE_PAYMENT_SYSTEMS 		= "payment_systems.ini";
	const INI_FILE_PAYMENT_URLS 		= "payment_urls.ini";
	const INI_FILE_SUCCESS_FAIL_URLS 	= "success_fail_urls.ini";
	const INI_FILE_ERROR_TEXTS			= "error_texts.ini";
    const INI_FILE_ADDITIONAL_FIELDS	= "additional_field_names.ini";

    /**
     * Exception messages text
     */
	const EXCEPTION_NO_INI_FILE 		= ".ini file not found: ";
	const EXCEPTION_NO_VIEW_FILE 		= "view file not found: ";
	const EXCEPTION_NO_VALUE_IN_ARRAY 	= "no vallue in array: ";

    /**
     * @var
     */
    public static $redirectArray;


    /**
     * @param null $configPath
     * @return array
     * @throws MonetaSdkException
     */
	public static function getAllSettings($configPath = null)
	{
		$iniFilesPath = self::INI_FILES_PATH;
		if ($configPath) {
			$iniFilesPath = $configPath;
		}

		$arBasicSettings 	= self::getSettingsFromIniFile(__DIR__ . $iniFilesPath . self::INI_FILE_BASIC_SETTINGS);
		$arDataStorage 		= self::getSettingsFromIniFile(__DIR__ . $iniFilesPath . self::INI_FILE_DATA_STORAGE);
		$arPaymentSystems 	= self::getSettingsFromIniFile(__DIR__ . $iniFilesPath . self::INI_FILE_PAYMENT_SYSTEMS);
		$arPaymentUrls 		= self::getSettingsFromIniFile(__DIR__ . $iniFilesPath . self::INI_FILE_PAYMENT_URLS);
		$arSuccessFailUrls 	= self::getSettingsFromIniFile(__DIR__ . $iniFilesPath . self::INI_FILE_SUCCESS_FAIL_URLS);
		$arErrorTexts 		= self::getSettingsFromIniFile(__DIR__ . $iniFilesPath . self::INI_FILE_ERROR_TEXTS);
        $arAdditionalFields	= self::getSettingsFromIniFile(__DIR__ . $iniFilesPath . self::INI_FILE_ADDITIONAL_FIELDS);

		return array_merge($arBasicSettings, $arDataStorage, $arPaymentSystems, $arPaymentUrls, $arSuccessFailUrls, $arErrorTexts, $arAdditionalFields);
	}

    /**
     * @param $attributes
     * @param $key
     * @return bool
     */
    public static function getValueFromMonetaAttributes($attributes, $key)
    {
        $result = false;
        if (is_array($attributes) && count($attributes)) {
            foreach ($attributes AS $attribute) {
                if (isset($attribute['key']) && isset($attribute['value']) && $attribute['key'] == $key) {
                    $result = $attribute['value'];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param $value
     * @param $array
     * @return mixed
     * @throws MonetaSdkException
     */
	public static function getValueFromArray($value, $array)
	{
		if (!isset($array[$value])) {
			throw new MonetaSdkException(self::EXCEPTION_NO_VALUE_IN_ARRAY . $value);
		}

		return $array[$value];
	}

    /**
     * @param $viewName
     * @param $data
     * @param null $externalPath
     * @return bool|string
     */
	public static function requireView($viewName, $data, $externalPath = null)
	{
        $result = false;
        if (!$externalPath && $externalPath != '') {
            $viewFileName = __DIR__ . $externalPath . $viewName . '.php';
        }
        else {
            $viewFileName = __DIR__ . self::VIEW_FILES_PATH . $viewName . '.php';
        }

		if (file_exists($viewFileName)) {
            ob_start();
			require_once($viewFileName);
            $result = ob_get_contents();
            ob_end_clean();
		}

        return $result;
	}

    /**
     * @param $eventName
     * @param $data
     * @param null $externalPath
     * @return bool
     */
    public static function handleEvent($eventName, $data, $externalPath = null)
    {
        $result = false;
        if (!$externalPath && $externalPath != '') {
            $eventFileName = __DIR__ . $externalPath . $eventName . '.php';
        }
        else {
            $eventFileName = __DIR__ . self::EVENTS_FILES_PATH . $eventName . '.php';
        }

        if (file_exists($eventFileName)) {
            require_once($eventFileName);
            $result = true;
        }

        return $result;
    }

    /**
     * @param $fileName
     * @return array
     * @throws MonetaSdkException
     */
	private static function getSettingsFromIniFile($fileName)
	{
		if (!file_exists($fileName)) {
			throw new MonetaSdkException(self::EXCEPTION_NO_INI_FILE . $fileName);
		}

		return parse_ini_file($fileName, true);
	}

    /**
     * @param $cookieName
     * @return null
     */
	public static function getSdkCookie($cookieName)
	{
		$result = null;
		if ($cookieName && isset($_COOKIE['mnt_data']) && $_COOKIE['mnt_data']) {
			$cookieMntSerializedData = $_COOKIE['mnt_data'];
			$cookieMntData = @unserialize($cookieMntSerializedData);
			if (isset($cookieMntData[$cookieName]) && $cookieMntData[$cookieName]) {
				$result = $cookieMntData[$cookieName];
			}
		}

		return $result;
	}

    /**
     * @param $cookieName
     * @param $cookieValue
     * @return bool
     */
	public static function setSdkCookie($cookieName, $cookieValue)
	{
		if (!$cookieName) {
			return false;
		}
		if (!$cookieValue) {
			$cookieValue = '';
		}
        self::$redirectArray[$cookieName] = $cookieValue;
		$cookieMntData = null;
		if (isset($_COOKIE['mnt_data']) && $_COOKIE['mnt_data']) {
			$cookieMntSerializedData = $_COOKIE['mnt_data'];
			$cookieMntData = @unserialize($cookieMntSerializedData);
		}
		if (!$cookieMntData) {
			$cookieMntData = array();
		}
		$cookieMntData[$cookieName] = $cookieValue;
        $cookieMntData = array_merge($cookieMntData, self::$redirectArray);
		setcookie('mnt_data', serialize($cookieMntData));

		return true;
	}

    /**
     * @param $message
     * @return int|null|void
     */
	public static function addToLog($message)
	{
		$errorHandlerFileName = __DIR__ . self::LOGS_FILES_PATH . date("Ymd").".txt";
        $result = null;
        // save error message to log file
        $fp = fopen($errorHandlerFileName, "a");
        if ($fp) {
            $message = "------------------------------------\n" . date("d.m.Y, H:i:s") . "\n" . $message;
            flock($fp, LOCK_EX);
            $result = fwrite($fp, $message);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $result;
	}

    /**
     * @param $string
     * @param $secret
     * @return string
     */
    public static function encrypt($string, $secret)
    {
        $iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $encrypted_string = mcrypt_encrypt(MCRYPT_BLOWFISH, $secret, utf8_encode($string), MCRYPT_MODE_ECB, $iv);

        return $encrypted_string;
    }

    /**
     * @param $string
     * @param $secret
     * @return string
     */
    public static function decrypt($string, $secret)
    {
        $iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypted_string = mcrypt_decrypt(MCRYPT_BLOWFISH, $secret, $string, MCRYPT_MODE_ECB, $iv);

        return $decrypted_string;
    }

}