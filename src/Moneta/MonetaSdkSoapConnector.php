<?php
/**
 * Класс для доступа к методам вебсервиса платежной системы www.moneta.ru
 * через SOAP
 *
 * PHP version 5
 *
 */

namespace Moneta;

use Moneta\MonetaWebServiceConnector;

class MonetaSdkSoapConnector extends MonetaWebServiceConnector
{

	/**
	 * Версия API Moneta.ru
	 *
	 * @var string
	 */
	public $version = "VERSION_2";

	function __construct()
	{
		echo "__construct<br/>";
	}


}