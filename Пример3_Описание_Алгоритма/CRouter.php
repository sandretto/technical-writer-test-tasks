<?php

/**
	 * Входные данные - объект rules, имеющий свойства:
	 * - type - тип правила, может принимать значения "or", "and", "request_var", "request_uri",
	 * - name - название правил, 
	 * - rules - вложенный список правил, 
	 * - regexp - регулярное выражение, 
	 * - default_parameters - параметры по умолчанию, 
	 * - parameters - список передаваемых параметров, 
	 * - value - значение правила
	 * 
	 * Входные данные могут подаваться в формате json:
	 * 
	 * rules {
	 * 	type: 
	 * 	name:
	 * 	rules:
	 * 	regexp:
	 * 	default_parameters:
	 * 	parameters:
	 *  value:
	 * }
	 * 
	 * Затем json декодируется в object.
	 * 
	 * Алгоритм проверяет соответствие правила заданным значениям, в зависимости от этого возвращает результат: 1 (соответствует) или 0 (не соответствует)
	 * и добавляет параметры правила в массив параметров из шаблона маршрута.
	 * 
	 * 
	 * В начале определяем результат выполнения алгоритма, присваивая значение 0
	 * Если класс CDebug существует и доступен, записываем в лог информацию о типе правила
	 * 2. В зависимости от входных данных алгоритм предоставляет следующие результаты:
	 * 		a) Если тип - "or", проверяем по отдельности каждое вложенное правило,
	 * 			если хотя бы для одного вложенного правила проверка прошла, возвращаем 1, иначе - 0
	 * 		б) Если тип - "and", проверяем по отдельности каждое вложенное правило,
	 * 			если хотя бы для одного вложенного правила проверка не прошла, возвращаем 0, иначе - 1
	 * 		в) Если тип - "request_var" или "request_uri", то:
	 * 			в.1) Если "request_var", сохраняем в переменную var результат вызова метода _REQUEST, в который передаем название правила
	 * 			в.2) Если "request_uri", сохраняем в переменную var результат вызова метода _SERVER, в который передаем "REQUEST_URI"
	 * 			в.3) Если определено регулярное выражение, выполняем проверку:
	 * 				в.3.1) Если var соответствует регулярному выражению, то
	 * 						результат равен 1,
	 * 						в.3.1.1) Если определены параметры по умолчанию, передаем их в routing_parameters
	 * 						в.3.1.2) Если определен список параметров, то в каждый элемент routing_parameters передаем декодированную строку url
	 * 			в.4) Если регулярное выражение не определено:
	 * 				в.4.1) Проверяем, если var равно значению правила или значение правила равно "any" и var не пустое, то
	 * 						результат равен 1,
	 * 				в.4.2) Если класс CDebug существует и доступен, записываем в лог результат выполнения проверки и информацию о правиле.
	 * 		г) Если тип правила принимает любое другое значение:
	 * 			г.1) Если класс CDebug существует и доступен, записываем в лог, что такое правило неизвестно
	 * 		д) В ином случае выполняем описанный выше алгоритм проверки для каждого вложенного правила:
	 * 			 если проверка прошла, возвращаем 1, иначе - 0
	 * 			д.1) Если класс CDebug существует и доступен, записываем в лог результат выполнения проверки
	 * 		е) Возвращаем результат - 1 или 0.
	 */

class CRouter
{
	/**
	 * @var array Параметры из шаблона маршрута
	 */
	static $routing_parameters = array();

	rules {
		type: "or",
		name: "Login",
		rules: [{
			type: "or",
			name: "Email",
			rules: null,	
			regexp:"/^[A-Z0-9._%+-]+@[A-Z0-9-]+.+.[A-Z]{2,4}$/i",
			default_parameters: "",
			parameters: "",
			value: ""
		},
		{
			type: "or",
			name: "Password",
			rules: null,	
			regexp:"/^(?=.*[A-Z].*[A-Z])(?=.*[!@#$&*])(?=.*[0-9].*[0-9])(?=.*[a-z].*[a-z].*[a-z]).{8,}$/",
			default_parameters: "",
			parameters: "",
			value: ""
		}
	],
		regexp: "/^[a-z0-9_-]{3,16}$/",
		default_parameters: "",
		parameters: "",
		value: ""
		}
		

	static function check_route_rules($rules)
	{
		$ok = 0;
        if (class_exists("CDebug",false) && CDebug::isEnabled())
            CDebug::getInstance()->logPrint(__FILE__.":".__LINE__,["ROUTING","check_route_rules ".$rules['type'],$rules]);

		if ($rules["type"] == "or")
		{
			foreach ($rules["rules"] as $rr)
				if (self::check_route_rules($rr)) return 1;
			return 0;
		}
		elseif ($rules["type"] == "and")
		{
			foreach ($rules["rules"] as $rr)
				if (!self::check_route_rules($rr)) return 0;
			return 1;
		}
		elseif ($rules["type"] == "request_var" || $rules["type"] == "request_uri")
		{
			if ($rules["type"] == "request_var")
				$var = $_REQUEST[$rules["name"]];
			elseif ($rules["type"] == "request_uri")
				$var = $_SERVER["REQUEST_URI"];
			if ($rules["regexp"])
			{
				//print_r($r);

				if (preg_match("/".$rules["regexp"]."/", $var, $m))
				{
					$ok = 1;
					if ($rules["default_parameters"])
						self::$routing_parameters = $rules["default_parameters"];
					if ($rules["parameters"])
						foreach ($rules["parameters"] as $i => $p)
							self::$routing_parameters[$p] = urldecode($m[$i + 1]);
				}
			}
			elseif (($var == $rules["value"]) || ($rules["value"] == "any" && isset($var)))
				$ok = 1;
            if (class_exists("CDebug",false) && CDebug::isEnabled())
                CDebug::getInstance()->logPrint(__FILE__.":".__LINE__,["ROUTING","var '".$rules["name"]."' check result=".$ok,["value"=>$var,"rules"=>$rules]]);
			return $ok;
		}
		elseif (isset($rules["type"]))
		{
            if (class_exists("CDebug",false) && CDebug::isEnabled())
                CDebug::getInstance()->logPrint(__FILE__.":".__LINE__,["ROUTING","unknown rule",$rules]);
		}
		else
			foreach ($rules as $r)
			{
				if (self::check_route_rules($r))
					return 1;
				return 0;
			}

        if (class_exists("CDebug",false) && CDebug::isEnabled())
            CDebug::getInstance()->logPrint(__FILE__.":".__LINE__,["ROUTING","check_route_rules result",$ok]);
		return $ok;
	}

}


?>