<?php

require_once('core.php');

function build_query_restrict($type = 'queue')
{
	$globalfilter = "";
	$settings = settings();
	if (isset($settings['quarantine-filter']) && $type != 'history')
	{
		foreach($settings['quarantine-filter'] as $q)
		{
			if ($globalfilter != "")
				$globalfilter .= " or ";
			$globalfilter .= "quarantine=$q";
		}
		$globalfilter .= ' or not action=QUARANTINE ';
	}

	$pattern = "{from} or {to}";
	if (isset($settings['filter-pattern']))
		$pattern = $settings['filter-pattern'];

	$filter = "";
	$access = Session::Get()->getAccess();
	if (is_array($access['domain'])) {
		foreach($access['domain'] as $domain) {
			if ($filter != "")
				$filter .= " or ";
			$filter .= str_replace(array('{from}', '{to}'), array("from~%@$domain", "to~%@$domain"), $pattern);
		} 
	}

	if (is_array($access['mail'])) {
		foreach($access['mail'] as $mail) {
			if ($filter != "")
				$filter .= " or ";
			$filter .= str_replace(array('{from}', '{to}'), array("from=$mail", "to=$mail"), $pattern);
		} 
	}
	return $globalfilter.($globalfilter?" && ":"").$filter;
}


function soap_client($n) {
	$r = settings('node', $n);
	if (!$r)
		throw new Exception("Node not configured");
	return new SoapClient($r['address'].'/remote/?wsdl', array(
		'location' => $r['address'].'/remote/',
		'uri' => 'urn:halon',
		'login' => $r['username'],
		'password' => $r['password'],
		'connection_timeout' => 15,
		'trace' => true,
		'features' => SOAP_SINGLE_ELEMENT_ARRAYS
		));
}

function soap_exec($argv, $c)
{
	$data = '';
	try {
		$id = $c->commandRun(array('argv' => $argv, 'cols' => 80, 'rows' => 24))->result;
		do {
			$result = $c->commandPoll(array('commandid' => $id))->result;
			if ($result && @$result->item)
				$data .= implode("", $result->item);
		} while(1);
	} catch(SoapFault $f) {
		if (!$id)
			return false;
	}
	return $data;
}

function p($str) {
	echo htmlspecialchars($str);
} 

function p_select($name, $selected, $options) {
	echo '<select id="'.$name.'" name="'.$name.'">';
	foreach ($options as $value => $label) {
		$extra = '';
		if ((string)$value == $selected)
			$extra = ' selected';
		echo '<option value="'.$value.'"'.$extra.'>'.$label.'</option>';
	}
	echo '</select>';
}

function hql_transform($string)
{
	$string = trim($string);
	if ($string == "")
		return "";
	$messageid = "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}";
	if (preg_match("/^($messageid)$/", $string, $result))
		return "messageid={$result[1]}";
	if (preg_match("/^($messageid):([0-9]+)$/", $string, $result))
		return "messageid={$result[1]} and queueid={$result[2]}";
	if (preg_match("/^([0-9]+)$/", $string, $result))
		return "queueid={$result[1]}";
	if (@inet_pton($string) !== false)
		return "ip=$string";
	if (!preg_match("/[=~><]/", $string)) {
		/* contain a @ either in the beginning or somewhere within */
		$mail = strpos($string, "@");
		if ($mail !== false)
		{
			if ($mail > 0)
				return "from=$string or to=$string";
			else
				return "from~$string or to~$string";
		}
		/* looks like a domain */
		if (preg_match("/^[a-z0-9-]+\.[a-z]{2,5}/", $string))
				return "from~$string or to~$string";
		/* add quotes */
		if (strpos($string, " ") !== false)
			$string = '"'.$string.'"';
		/* try as subject */
		return "subject~$string";
	}
	return $string;
}

function generate_random_password()
{
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$pass = '';
	srand((float) microtime() * 10000000);
	for ($i = 0; $i < rand(10, 12); $i++) {
		$pass .= $chars[rand(0, strlen($chars)-1)];
	}
	return $pass;
}

function mail2($recipient, $subject, $message, $in_headers = null)
{
	$settings = settings();
	$headers = array();
	$headers[] = 'Message-ID: <'.uniqid().'@sp-enduser>';
	if (isset($settings['mail']['from']))
		$headers[] = "From: ".$settings['mail']['from'];
	if ($in_headers !== null)
		$headers = array_merge($headers, $in_headers);
	mail($recipient, $subject, $message, implode("\r\n", $headers));
}

function self_url()
{
	if (isset($_SERVER['HTTPS']))
		$protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
	else
		$protocol = 'http';
	$url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	return preg_replace("#[^/]*$#", "", $url);
}

function ldap_escape($data)
{
	return str_replace(array('\\', '*', '(', ')', '\0'), array('\\5c', '\\2a', '\\28', '\\29', '\\00'), $data);
}

function has_auth_database() {
	$settings = settings();
	foreach ($settings['authentication'] as $a)
		if ($a['type'] == 'database')
			return true;
	return false;
}

?>
