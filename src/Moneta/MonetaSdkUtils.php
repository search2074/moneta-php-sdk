<?php

namespace Moneta;

use Moneta;


class MonetaSdkUtils
{
	const INI_FILES_PATH 				= "/../../config/";
	const VIEW_FILES_PATH 				= "/../../view/";
    const EVENTS_FILES_PATH 			= "/../../events/";

	const INI_FILE_BASIC_SETTINGS 		= "basic_settings.ini";
	const INI_FILE_DATA_STORAGE 		= "data_storage.ini";
	const INI_FILE_PAYMENT_SYSTEMS 		= "payment_systems.ini";
	const INI_FILE_PAYMENT_URLS 		= "payment_urls.ini";
	const INI_FILE_SUCCESS_FAIL_URLS 	= "success_fail_urls.ini";
	const INI_FILE_ERROR_TEXTS			= "error_texts.ini";
    const INI_FILE_ADDITIONAL_FIELDS	= "additional_field_names.ini";

	const EXCEPTION_NO_INI_FILE 		= ".ini file not found: ";
	const EXCEPTION_NO_VIEW_FILE 		= "view file not found: ";
	const EXCEPTION_NO_VALUE_IN_ARRAY 	= "no vallue in array: ";


	public static function getAllSettings()
	{
		$arBasicSettings 	= self::getSettingsFromIniFile(__DIR__ . self::INI_FILES_PATH . self::INI_FILE_BASIC_SETTINGS);
		$arDataStorage 		= self::getSettingsFromIniFile(__DIR__ . self::INI_FILES_PATH . self::INI_FILE_DATA_STORAGE);
		$arPaymentSystems 	= self::getSettingsFromIniFile(__DIR__ . self::INI_FILES_PATH . self::INI_FILE_PAYMENT_SYSTEMS);
		$arPaymentUrls 		= self::getSettingsFromIniFile(__DIR__ . self::INI_FILES_PATH . self::INI_FILE_PAYMENT_URLS);
		$arSuccessFailUrls 	= self::getSettingsFromIniFile(__DIR__ . self::INI_FILES_PATH . self::INI_FILE_SUCCESS_FAIL_URLS);
		$arErrorTexts 		= self::getSettingsFromIniFile(__DIR__ . self::INI_FILES_PATH . self::INI_FILE_ERROR_TEXTS);
        $arAdditionalFields	= self::getSettingsFromIniFile(__DIR__ . self::INI_FILES_PATH . self::INI_FILE_ADDITIONAL_FIELDS);

		return array_merge($arBasicSettings, $arDataStorage, $arPaymentSystems, $arPaymentUrls, $arSuccessFailUrls, $arErrorTexts, $arAdditionalFields);
	}


	public static function getValueFromArray($value, $array)
	{
		if (!isset($array[$value])) {
			throw new MonetaSdkException(self::EXCEPTION_NO_VALUE_IN_ARRAY . $value);
		}

		return $array[$value];
	}


	public function requireView($viewName, $data, $externalPath = null)
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


    public static function handleEvent($eventName, $externalPath = null)
    {
        // TODO: согласно паттерну, здесь надо только зарегистрировать событие (собрать в массив отдельного класса), а диспатч всех событий надо делать в цикле после полного выполнения основного кода, проходя по собранному ранее массиву

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


	private static function getSettingsFromIniFile($fileName)
	{
		if (!file_exists($fileName)) {
			throw new MonetaSdkException(self::EXCEPTION_NO_INI_FILE . $fileName);
		}

		return parse_ini_file($fileName, true);
	}

}