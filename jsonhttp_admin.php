<?php

define('IN_SCRIPT', "1");
define('SCRIPT_NAME', "jsonhttp_admin");

require_once "global.php";
require_once SCRIPT_ROOT . "includes/functions_jsonhttp.php";

// Pobranie akcji
$action = $_POST['action'];

// Send no cache headers
header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$data = array();
if ($action == "charge_wallet") {
	if (!get_privilages("manage_users")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$uid = $_POST['uid'];
	$amount = $_POST['amount'];

	// ID użytkownika
	if ($warning = check_for_warnings("uid", $uid)) {
		$warnings['uid'] = $warning;
	} else {
		$user2 = $heart->get_user($uid);
		if (!isset($user2['uid'])) {
			$warnings['uid'] = $lang->noaccount_id . "<br />";
		}
	}

	// Wartość Doładowania
	if (!$amount) {
		$warnings['amount'] .= $lang->no_charge_value . "<br />";
	} else if (!is_numeric($amount)) {
		$warnings['amount'] .= $lang->charge_number . "<br />";
	}

	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	// Zmiana wartości amount, aby stan konta nie zszedł poniżej zera
	$amount = max($amount, -$user2['wallet']);
	$amount = number_format($amount, 2);

	$service_module = $heart->get_service_module("charge_wallet");
	if (is_null($service_module))
		json_output("wrong_module", $lang->bad_module, 0);

	// Dodawanie informacji o płatności do bazy
	$payment_id = pay_by_admin($user);

	// Kupujemy usługę
	$purchase_return = $service_module->purchase(array(
		'user' => array(
			'uid' => $user2['uid'],
			'ip' => $user2['ip'],
			'email' => $user2['email'],
			'name' => $user2['username']
		),
		'transaction' => array(
			'method' => "admin",
			'payment_id' => $payment_id
		),
		'order' => array(
			'amount' => $amount
		)
	));

	log_info($lang_shop->sprintf($lang_shop->account_charge, $user['username'], $user['uid'], $user2['username'], $user2['uid'], $amount, $settings['currency']));

	json_output("charged", $lang->sprintf($lang->account_charge_success, $user2['username'], $amount, $settings['currency']), 1);
} else if ($action == "add_user_service") {
	if (!get_privilages("manage_player_services")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Brak usługi
	if (!strlen($_POST['service']))
		json_output("no_service", $lang->no_service_chosen, 0);

	if (($service_module = $heart->get_service_module($_POST['service'])) === NULL || !object_implements($service_module, "IServiceAdminManageUserService"))
		json_output("wrong_module", $lang->bad_module, 0);

	$return_data = $service_module->admin_add_user_service($_POST);

	// Przerabiamy ostrzeżenia, aby lepiej wyglądały
	if ($return_data['status'] == "warnings")
		foreach ($return_data['data']['warnings'] as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$return_data['data']['warnings'][$brick] = $warning;
		}

	json_output($return_data['status'], $return_data['text'], $return_data['positive'], $return_data['data']);
} else if ($action == "edit_user_service") {
	if (!get_privilages("manage_player_services"))
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);

	// Brak usługi
	if (!strlen($_POST['service']))
		json_output("no_service", "Nie wybrano usługi.", 0);

	if (is_null($service_module = $heart->get_service_module($_POST['service'])))
		json_output("wrong_module", $lang->bad_module, 0);

	// Sprawdzamy czy dana usługa gracza istnieje
	$result = $db->query($db->prepare(
		"SELECT * FROM `" . TABLE_PREFIX . "players_services` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	));

	// Brak takiej usługi w bazie
	if (!$db->num_rows($result))
		json_output("no_service", $lang->no_service, 0);

	$user_service = $db->fetch_array_assoc($result);

	// Wykonujemy metode edycji usługi gracza przez admina na odpowiednim module
	$return_data = $service_module->admin_edit_user_service($_POST, $user_service);

	if ($return_data === FALSE)
		json_output("missing_method", $lang->no_edit_method, 0);

	// Przerabiamy ostrzeżenia, aby lepiej wyglądały
	if ($return_data['status'] == "warnings") {
		foreach ($return_data['data']['warnings'] as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$return_data['data']['warnings'][$brick] = $warning;
		}
	}

	json_output($return_data['status'], $return_data['text'], $return_data['positive'], $return_data['data']);
} else if ($action == "delete_player_service") {
	if (!get_privilages("manage_player_services")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Pobieramy usługę z bazy
	$player_service = $db->fetch_array_assoc($db->query($db->prepare(
		"SELECT * FROM `" . TABLE_PREFIX . "players_services` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	)));

	// Brak takiej usługi
	if (empty($player_service))
		json_output("no_service", $lang->no_service, 0);

	// Usunięcie usługi gracza
	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "players_services` " .
		"WHERE `id` = '%d'",
		array($player_service['id'])
	));
	$affected = $db->affected_rows();

	// Wywolujemy akcje przy usuwaniu
	$service_module = $heart->get_service_module($player_service['service']);
	if ($service_module !== NULL) {
		$service_module->delete_player_service($player_service);
	}

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($affected) {
		log_info($lang_shop->sprintf($lang_shop->service_admin_delete, $user['username'], $user['uid'], $player_service['id']));

		json_output("deleted", $lang->delete_service, 1);
	} else
		json_output("not_deleted", $lang->no_delete_service, 0);
} else if ($action == "get_add_user_service_form") {
	if (!get_privilages("manage_player_services")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$output = "";
	if (($service_module = $heart->get_service_module($_POST['service'])) !== NULL)
		$output = json_encode($service_module->admin_get_form_add_user_service());

	output_page($output, "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "add_antispam_question" || $action == "edit_antispam_question") {
	if (!get_privilages("manage_antispam_questions")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Pytanie
	if (!$_POST['question']) {
		$warnings['question'] = $lang->field_no_empty . "<br />";
	}

	// Odpowiedzi
	if (!$_POST['answers']) {
		$warnings['answers'] = $lang->field_no_empty . "<br />";
	}

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	if ($action == "add_antispam_question") {
		$db->query($db->prepare(
			"INSERT INTO `" . TABLE_PREFIX . "antispam_questions` ( question, answers ) " .
			"VALUES ('%s','%s')",
			array($_POST['question'], $_POST['answers'])));

		json_output("added", $lang->antispam_add, 1);
	} else if ($action == "edit_antispam_question") {
		$db->query($db->prepare(
			"UPDATE `" . TABLE_PREFIX . "antispam_questions` " .
			"SET `question` = '%s', `answers` = '%s' " .
			"WHERE `id` = '%d'",
			array($_POST['question'], $_POST['answers'], $_POST['id'])));

		// Zwróć info o prawidłowej lub błędnej edycji
		if ($db->affected_rows()) {
			log_info($lang_shop->sprintf($lang_shop->question_edit, $user['username'], $user['uid'], $_POST['id']));
			json_output("edited", $lang->antispam_edit, 1);
		} else
			json_output("not_edited", $lang->antispam_no_edit, 0);
	}
} else if ($action == "delete_antispam_question") {
	if (!get_privilages("manage_antispam_questions")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "antispam_questions` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->question_delete, $user['username'], $user['uid'], $_POST['id']));
		json_output("deleted", $lang->delete_antispamq, 1);
	} else {
		json_output("not_deleted", $lang->no_delete_antispamq, 0);
	}
} else if ($action == "edit_settings") {
	if (!get_privilages("manage_settings")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$sms_service = $_POST['sms_service'];
	$transfer_service = $_POST['transfer_service'];
	$currency = $_POST['currency'];
	$shop_url = $_POST['shop_url'];
	$sender_email = $_POST['sender_email'];
	$sender_email_name = $_POST['sender_email_name'];
	$signature = $_POST['signature'];
	$vat = $_POST['vat'];
	$contact = $_POST['contact'];
	$row_limit = $_POST['row_limit'];
	$license_login = $_POST['license_login'];
	$license_password = $_POST['license_password'];
	$cron = $_POST['cron'];
	$language = escape_filename($_POST['language']);
	$theme = escape_filename($_POST['theme']);
	$date_format = $_POST['date_format'];
	$delete_logs = $_POST['delete_logs'];

	// Serwis płatności SMS
	if ($sms_service != '0') {
		$result = $db->query($db->prepare(
			"SELECT id " .
			"FROM `" . TABLE_PREFIX . "transaction_services` " .
			"WHERE `id` = '%s' AND sms = '1'",
			array($sms_service)
		));
		if (!$db->num_rows($result)) {
			$warnings['sms_service'] = $lang->no_sms_service . "<br />";
		}
	}

	// Serwis płatności internetowej
	if ($transfer_service != '0') {
		$result = $db->query($db->prepare(
			"SELECT id " .
			"FROM `" . TABLE_PREFIX . "transaction_services` " .
			"WHERE `id` = '%s' AND transfer = '1'",
			array($transfer_service)
		));
		if (!$db->num_rows($result)) {
			$warnings['transfer_service'] = $lang->no_net_service . "<br />";
		}
	}

	// Email dla automatu
	if ($warning = check_for_warnings("email", $sender_email)) {
		$warnings['sender_email'] = $warning;
	}

	// VAT
	if ($warning = check_for_warnings("number", $vat)) {
		$warnings['vat'] = $warning;
	}

	// Usuwanie logów
	if ($warning = check_for_warnings("number", $delete_logs)) {
		$warnings['delete_logs'] = $warning;
	}

	// Wierszy na stronę
	if ($warning = check_for_warnings("number", $row_limit)) {
		$warnings['row_limit'] = $warning;
	}

	// Cron
	if (!in_array($cron, array("1", "0"))) {
		$warnings['cron'] = $lang->only_yes_no;
	}

	// Edytowanie usługi przez gracza
	if (!in_array($_POST['user_edit_service'], array("1", "0"))) {
		$warnings['user_edit_service'] = $lang->only_yes_no;
	}

	// Motyw
	if (!is_dir(SCRIPT_ROOT . "themes/{$theme}") || $theme[0] == '.')
		$warnings['theme'] = $lang->no_theme;

	// Język
	if (!is_dir(SCRIPT_ROOT . "includes/languages/{$language}") || $language[0] == '.')
		$warnings['language'] = $lang->no_language;

	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	if ($license_password) {
		$set_license_password = $db->prepare("WHEN 'license_password' THEN '%s' ", array(md5($license_password)));
		$key_license_password = ",'license_password'";
	}

	// Edytuj ustawienia
	$db->query($db->prepare(
		"UPDATE `" . TABLE_PREFIX . "settings` " .
		"SET value = CASE `key` " .
		"WHEN 'sms_service' THEN '%s' " .
		"WHEN 'transfer_service' THEN '%s' " .
		"WHEN 'currency' THEN '%s' " .
		"WHEN 'shop_url' THEN '%s' " .
		"WHEN 'sender_email' THEN '%s' " .
		"WHEN 'sender_email_name' THEN '%s' " .
		"WHEN 'signature' THEN '%s' " .
		"WHEN 'vat' THEN '%.2f' " .
		"WHEN 'contact' THEN '%s' " .
		"WHEN 'row_limit' THEN '%s' " .
		"WHEN 'license_login' THEN '%s' " .
		"WHEN 'cron_each_visit' THEN '%d' " .
		"WHEN 'user_edit_service' THEN '%d' " .
		"WHEN 'theme' THEN '%s' " .
		"WHEN 'language' THEN '%s' " .
		"WHEN 'date_format' THEN '%s' " .
		"WHEN 'delete_logs' THEN '%d' " .
		$set_license_password .
		"END " .
		"WHERE `key` IN ( 'sms_service','transfer_service','currency','shop_url','sender_email','sender_email_name','signature','vat'," .
		"'contact','row_limit','license_login','cron_each_visit','user_edit_service','theme','language','date_format','delete_logs'{$key_license_password} )",
		array($sms_service, $transfer_service, $currency, $shop_url, $sender_email, $sender_email_name, $signature, $vat, $contact, $row_limit,
			$license_login, $cron, $_POST['user_edit_service'], $theme, $language, $date_format, $delete_logs)
	));

	// Zwróć info o prawidłowej lub błędnej edycji
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->settings_admin_edit, $user['username'], $user['uid']));

		json_output("edited", $lang->settings_edit, 1);
	} else
		json_output("not_edited", $lang->settings_no_edit, 0);
} else if ($action == "edit_transaction_service") {
	if (!get_privilages("manage_settings")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Pobieranie danych
	$result = $db->query($db->prepare(
		"SELECT data " .
		"FROM `" . TABLE_PREFIX . "transaction_services` " .
		"WHERE `id` = '%s'",
		array($_POST['id'])
	));
	$transaction_service = $db->fetch_array_assoc($result);
	$transaction_service['data'] = json_decode($transaction_service['data']);
	foreach ($transaction_service['data'] as $key => $value) {
		$arr[$key] = $_POST[$key];
	}

	$db->query($db->prepare(
		"UPDATE `" . TABLE_PREFIX . "transaction_services` " .
		"SET `data` = '%s' " .
		"WHERE `id` = '%s'",
		array(json_encode($arr), $_POST['id'])));

	// Zwróć info o prawidłowej lub błędnej edycji
	if ($db->affected_rows()) {
		// LOGGING
		log_info($lang_shop->sprintf($lang_shop->payment_admin_edit, $user['username'], $user['uid'], $_POST['id']));

		json_output("edited", $lang->payment_edit, 1);
	} else
		json_output("not_edited", $lang->payment_no_edit, 0);
} else if ($action == "add_service" || $action == "edit_service") {
	if (!get_privilages("manage_services")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// ID
	if (!strlen($_POST['id'])) { // Nie podano id usługi
		$warnings['id'] = $lang->no_service_id . "<br />";
	} else if ($action == "add_service") {
		if (strlen($_POST['id']) > 16)
			$warnings['id'] = $lang->long_service_id . "<br />";
	}

	if (($action == "add_service" && !isset($warnings['id'])) || ($action == "edit_service" && $_POST['id'] !== $_POST['id2']))
		// Sprawdzanie czy usługa o takim ID już istnieje
		if ($heart->get_service($_POST['id']) !== NULL)
			$warnings['id'] = $lang->id_exist . "<br />";

	// Nazwa
	if (!strlen($_POST['name'])) {
		$warnings['name'] = $lang->no_service_name . "<br />";
	}

	// Opis
	if ($warning = check_for_warnings("service_description", $_POST['short_description']))
		$warnings['short_description'] = $warning;

	// Kolejność
	if ($_POST['order'] != intval($_POST['order'])) {
		$warnings['order'] = $lang->field_integer . "<br />";
	}

	// Grupy
	foreach ($_POST['groups'] as $group) {
		if (is_null($heart->get_group($group))) {
			$warnings['groups[]'] .= $lang->wrong_group . "<br />";
			break;
		}
	}

	// Moduł usługi
	if ($action == "add_service") {
		if (($service_module = $heart->get_service_module_s($_POST['module'])) === NULL)
			$warnings['module'] = $lang->wrong_module . "<br />";
	} else
		$service_module = $heart->get_service_module($_POST['id2']);

	// Przed błędami
	if ($service_module !== NULL) {
		$additional_warnings = $service_module->manage_service_pre($_POST);
		$warnings = array_merge((array)$warnings, (array)$additional_warnings);
	}

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	// Po błędach wywołujemy na metodę modułu
	if ($service_module !== NULL)
		$module_data = $service_module->manage_service_post($_POST);

	if ($action == "add_service") {
		$db->query($db->prepare(
			"INSERT INTO `" . TABLE_PREFIX . "services` " .
			"SET `id`='%s', `name`='%s', `short_description`='%s', `description`='%s', `tag`='%s', " .
			"`module`='%s', `groups`='%s', `order` = '%d'{$module_data['query_set']}",
			array($_POST['id'], $_POST['name'], $_POST['short_description'], $_POST['description'], $_POST['tag'], $_POST['module'],
				implode(";", $_POST['groups']), $_POST['order'])
		));

		log_info($lang_shop->sprintf($lang_shop->service_admin_add, $user['username'], $user['uid'], $_POST['id']));
		json_output("added", $lang->service_added, 1, array('length' => 10000));
	} else if ($action == "edit_service") {
		$db->query($db->prepare(
			"UPDATE `" . TABLE_PREFIX . "services` " .
			"SET `id` = '%s', `name` = '%s', `short_description` = '%s', `description` = '%s', " .
			"`tag` = '%s', `groups` = '%s', `order` = '%d' " . $module_data['query_set'] .
			"WHERE `id` = '%s'",
			array($_POST['id'], $_POST['name'], $_POST['short_description'], $_POST['description'], $_POST['tag'], implode(";", $_POST['groups']),
				$_POST['order'], $_POST['id2'])
		));

		// Zwróć info o prawidłowej lub błędnej edycji
		if ($db->affected_rows()) {
			log_info($lang_shop->sprintf($lang_shop->service_admin_edit, $user['username'], $user['uid'], $_POST['id2']));
			json_output("edited", $lang->service_edit, 1);
		} else
			json_output("not_edited", $lang->service_no_edit, 0);
	}
} else if ($action == "delete_service") {
	if (!get_privilages("manage_services"))
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);

	// Wywolujemy akcje przy uninstalacji
	$service_module = $heart->get_service_module($_POST['id']);
	if (!is_null($service_module)) {
		$service_module->delete_service($_POST['id']);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "pricelist` " .
		"WHERE `service` = '%s'",
		array($_POST['id'])
	));

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "services` " .
		"WHERE `id` = '%s'",
		array($_POST['id'])
	));
	$affected = $db->affected_rows();

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($affected) {
		log_info($lang_shop->sprintf($lang_shop->service_admin_delete, $user['username'], $user['uid'], $_POST['id']));
		json_output("deleted", $lang->delete_service, 1);
	} else
		json_output("not_deleted", $lang->no_delete_service, 0);
} else if ($action == "get_service_module_extra_fields") {
	if (!get_privilages("manage_player_services"))
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);

	$output = "";
	// Pobieramy moduł obecnie edytowanej usługi, jeżeli powróciliśmy do pierwotnego modułu
	// W przeciwnym razie pobieramy wybrany moduł
	if (is_null($service_module = $heart->get_service_module($_POST['service'])) || $service_module::MODULE_ID != $_POST['module'])
		$service_module = $heart->get_service_module_s($_POST['module']);

	if (!is_null($service_module))
		$output = $service_module->service_extra_fields();

	output_page($output, "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "add_server" || $action == "edit_server") {
	if (!get_privilages("manage_servers")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Nazwa
	if (!$_POST['name']) { // Nie podano nazwy serwera
		$warnings['name'] = $lang->field_no_empty . "<br />";
	}

	// IP
	if (!$_POST['ip']) { // Nie podano nazwy serwera
		$warnings['ip'] = $lang->field_no_empty . "<br />";
	}
	$_POST['ip'] = trim($_POST['ip']);

	// Port
	if (!$_POST['port']) { // Nie podano nazwy serwera
		$warnings['port'] = $lang->field_no_empty . "<br />";
	}
	$_POST['port'] = trim($_POST['port']);

	// Serwis płatności SMS
	if ($_POST['sms_service']) {
		$result = $db->query($db->prepare(
			"SELECT id " .
			"FROM `" . TABLE_PREFIX . "transaction_services` " .
			"WHERE `id` = '%s' AND sms = '1'",
			array($_POST['sms_service'])
		));
		if (!$db->num_rows($result)) {
			$warnings['sms_service'] = $lang->no_sms_service . "<br />";
		}
	}

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	$set = "";
	foreach ($heart->get_services() as $service) {
		// Dana usługa nie może być kupiona na serwerze
		if (!is_null($service_module = $heart->get_service_module($service['id'])) && !$service_module->info['available_on_servers'])
			continue;

		$set .= $db->prepare(", `%s`='%d'", array($service['id'], $_POST[$service['id']]));
	}

	if ($action == "add_server") {
		$db->query($db->prepare(
			"INSERT INTO `" . TABLE_PREFIX . "servers` " .
			"SET `name`='%s', `ip`='%s', `port`='%s', `sms_service`='%s'{$set}",
			array($_POST['name'], $_POST['ip'], $_POST['port'], $_POST['sms_service'])));

		log_info($lang_shop->sprintf($lang_shop->server_admin_add, $user['username'], $user['uid'], $db->last_id()));
		// Zwróć info o prawidłowym zakończeniu dodawania
		json_output("added", $lang->server_added, 1);
	} else if ($action == "edit_server") {
		$db->query($db->prepare(
			"UPDATE `" . TABLE_PREFIX . "servers` " .
			"SET `name` = '%s', `ip` = '%s', `port` = '%s', `sms_service` = '%s'{$set} " .
			"WHERE `id` = '%s'",
			array($_POST['name'], $_POST['ip'], $_POST['port'], $_POST['sms_service'], $_POST['id'])
		));

		// Zwróć info o prawidłowej lub błędnej edycji
		if ($db->affected_rows()) {
			// LOGGING
			log_info($lang_shop->sprintf($lang_shop->server_admin_edit, $user['username'], $user['uid'], $_POST['id']));
			json_output("edited", $lang->server_edit, 1);
		} else
			json_output("not_edited", $lang->server_no_edit, 0);
	}
} else if ($action == "delete_server") {
	if (!get_privilages("manage_servers")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "servers` " .
		"WHERE `id` = '%s'",
		array($_POST['id'])
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->server_admin_delete, $user['username'], $user['uid'], $_POST['id']));
		json_output("deleted", $lang->delete_server, 1);
	} else json_output("not_deleted", $lang->no_delete_server, 0);
} else if ($action == "edit_user") {
	if (!get_privilages("manage_users")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$user2 = $heart->get_user($_POST['uid']);

	// Nazwa użytkownika
	if ($user2['username'] != $_POST['username']) {
		if ($warning = check_for_warnings("username", $_POST['username']))
			$warnings['username'] = $warning;
		$result = $db->query($db->prepare(
			"SELECT `uid` " .
			"FROM `" . TABLE_PREFIX . "users` " .
			"WHERE username = '%s'",
			array($_POST['username'])
		));
		if ($db->num_rows($result)) {
			$warnings['username'] .= $lang->nick_taken . "<br />";
		}
	}

	// E-mail
	if ($user2['email'] != $_POST['email']) {
		if ($warning = check_for_warnings("email", $_POST['email']))
			$warnings['email'] = $warning;
		$result = $db->query($db->prepare(
			"SELECT `uid` " .
			"FROM `" . TABLE_PREFIX . "users` " .
			"WHERE email = '%s'",
			array($_POST['email'])
		));
		if ($db->num_rows($result)) {
			$warnings['email'] .= $lang->email_taken . "<br />";
		}
	}

	// Grupy
	foreach ($_POST['groups'] as $gid) {
		if (is_null($heart->get_group($gid))) {
			$warnings['groups[]'] .= $lang->wrong_group . "<br />";
			break;
		}
	}

	// Portfel
	if ($warning = check_for_warnings("number", $_POST['wallet']))
		$warnings['wallet'] = $warning;

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	$db->query($db->prepare(
		"UPDATE `" . TABLE_PREFIX . "users` " .
		"SET `username` = '%s', `forename` = '%s', `surname` = '%s', `email` = '%s', `groups` = '%s', `wallet` = '%f' " .
		"WHERE `uid` = '%d'",
		array($_POST['username'], $_POST['forename'], $_POST['surname'], $_POST['email'], implode(";", $_POST['groups']),
			number_format($_POST['wallet'], 2), $_POST['uid'])
	));

	// Zwróć info o prawidłowej lub błędnej edycji
	if ($db->affected_rows()) {
		// LOGGING
		log_info($lang_shop->sprintf($lang_shop->user_admin_edit, $user['username'], $user['uid'], $_POST['uid']));
		json_output("edited", $lang->user_edit, 1);
	} else
		json_output("not_edited", $lang->user_no_edit, 0);
} else if ($action == "delete_user") {
	if (!get_privilages("manage_users")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "users` " .
		"WHERE `uid` = '%d'",
		array($_POST['uid'])
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->user_admin_delete, $user['username'], $user['uid'], $_POST['uid']));
		json_output("deleted", $lang->delete_user, 1);
	} else json_output("not_deleted", $lang->no_delete_user, 0);
} else if ($action == "add_group" || $action == "edit_group") {
	if (!get_privilages("manage_groups")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$set = "";
	$result = $db->query("DESCRIBE " . TABLE_PREFIX . "groups");
	while ($row = $db->fetch_array_assoc($result)) {
		if (in_array($row['Field'], array("id", "name"))) continue;

		$set .= $db->prepare(", `%s`='%d'", array($row['Field'], $_POST[$row['Field']]));
	}

	if ($action == "add_group") {
		$db->query($db->prepare(
			"INSERT INTO `" . TABLE_PREFIX . "groups` " .
			"SET `name` = '%s'{$set}",
			array($_POST['name'])
		));

		log_info($lang_shop->sprintf($lang_shop->group_admin_add, $user['username'], $user['uid'], $db->last_id()));
		// Zwróć info o prawidłowym zakończeniu dodawania
		json_output("added", $lang->group_add, 1);
	} else if ($action == "edit_group") {
		$db->query($db->prepare(
			"UPDATE `" . TABLE_PREFIX . "groups` " .
			"SET `name` = '%s'{$set} " .
			"WHERE `id` = '%d'",
			array($_POST['name'], $_POST['id'])
		));

		// Zwróć info o prawidłowej lub błędnej edycji
		if ($db->affected_rows()) {
			// LOGGING
			log_info($lang_shop->sprintf($lang_shop->group_admin_edit, $user['username'], $user['uid'], $_POST['id']));
			json_output("edited", $lang->group_edit, 1);
		} else
			json_output("not_edited", $lang->group_no_edit, 0);
	}
} else if ($action == "delete_group") {
	if (!get_privilages("manage_groups")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "groups` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->group_admin_delete, $user['username'], $user['uid'], $_POST['id']));
		json_output("deleted", $lang->delete_group, 1);
	} else
		json_output("not_deleted", $lang->no_delete_group, 0);
} else if ($action == "add_tariff") {
	if (!get_privilages("manage_settings")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Taryfa
	if ($warning = check_for_warnings("number", $_POST['tariff'])) {
		$warnings['tariff'] = $warning;
	}
	if (($heart->get_tariff($_POST['tariff'])) !== NULL) {
		$warnings['tariff'] .= $lang->tariff_exist . "<br />";
	}

	// Prowizja
	if ($warning = check_for_warnings("number", $_POST['provision'])) {
		$warnings['provision'] = $warning;
	}

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	$db->query($db->prepare(
		"INSERT " .
		"INTO " . TABLE_PREFIX . "tariffs (tariff,provision) " .
		"VALUES( '%d', '%.2f' )",
		array($_POST['tariff'], $_POST['provision'])
	));

	log_info($lang_shop->sprintf($lang_shop->tariff_admin_add, $user['username'], $user['uid'], $db->last_id()));
	// Zwróć info o prawidłowym dodaniu
	json_output("added", $lang->tariff_add, 1);
} else if ($action == "edit_tariff") {
	if (!get_privilages("manage_settings")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Prowizja
	if ($warning = check_for_warnings("number", $_POST['provision'])) {
		$warnings['provision'] = $warning;
	}

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	$db->query($db->prepare(
		"UPDATE `" . TABLE_PREFIX . "tariffs` " .
		"SET `provision` = '%.2f' " .
		"WHERE `tariff` = '%d'",
		array($_POST['provision'], $_POST['tariff'])));

	// Zwróć info o prawidłowej lub błędnej edycji
	if ($affected || $db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->tariff_admin_edit, $user['username'], $user['uid'], $_POST['id']));
		json_output("edited", $lang->tariff_edit, 1);
	} else json_output("not_edited", $lang->tariff_no_edit, 0);
} else if ($action == "delete_tariff") {
	if (!get_privilages("manage_settings")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "tariffs` " .
		"WHERE `tariff` = '%d' AND `predefined` = '%d'",
		array($_POST['tariff'], 0)
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->tariff_admin_delete, $user['username'], $user['uid'], $_POST['tariff']));
		json_output("deleted", $lang->delete_tariff, 1);
	} else {
		json_output("not_deleted", $lang->no_delete_tariff, 0);
	}
} else if ($action == "add_price" || $action == "edit_price") {
	if (!get_privilages("manage_settings")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Usługa
	if (is_null($heart->get_service($_POST['service']))) {
		$warnings['service'] .= $lang->no_such_service . "<br />";
	}

	// Serwer
	if ($_POST['server'] != -1 && is_null($heart->get_server($_POST['server']))) {
		$warnings['server'] .= $lang->no_such_server . "<br />";
	}

	// Taryfa
	if (($heart->get_tariff($_POST['tariff'])) === NULL) {
		$warnings['tariff'] .= $lang->no_such_tariff . "<br />";
	}

	// Ilość
	if ($warning = check_for_warnings("number", $_POST['amount'])) {
		$warnings['amount'] = $warning;
	}

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	if ($action == "add_price") {
		$db->query($db->prepare(
			"INSERT " .
			"INTO " . TABLE_PREFIX . "pricelist (service,tariff,amount,server) " .
			"VALUES( '%s', '%d', '%d', '%d' )",
			array($_POST['service'], $_POST['tariff'], $_POST['amount'], $_POST['server'])));

		log_info("Admin {$user['username']}({$user['uid']}) dodał cenę. ID: " . $db->last_id());

		// Zwróć info o prawidłowym dodaniu
		json_output("added", $lang->price_add, 1);
	} else if ($action == "edit_price") {
		$db->query($db->prepare(
			"UPDATE `" . TABLE_PREFIX . "pricelist` " .
			"SET `service` = '%s', `tariff` = '%d', `amount` = '%d', `server` = '%d' " .
			"WHERE `id` = '%d'",
			array($_POST['service'], $_POST['tariff'], $_POST['amount'], $_POST['server'], $_POST['id'])));

		// Zwróć info o prawidłowej lub błędnej edycji
		if ($db->affected_rows()) {
			log_info($lang_shop->sprintf($lang_shop->price_admin_edit, $user['username'], $user['uid'], $_POST['id']));
			json_output("edited", $lang->price_edit, 1);
		} else json_output("not_edited", $lang->price_no_edit, 0);
	}
} else if ($action == "delete_price") {
	if (!get_privilages("manage_settings")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "pricelist` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->price_admin_delete, $user['username'], $user['uid'], $_POST['id']));
		json_output("deleted", $lang->delete_price, 1);
	} else json_output("not_deleted", $lang->no_delete_price, 0);
} else if ($action == "add_sms_code") {
	if (!get_privilages("manage_sms_codes")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	// Taryfa
	if ($warning = check_for_warnings("number", $_POST['tariff']))
		$warnings['tariff'] = $warning;

	// Kod SMS
	if ($warning = check_for_warnings("sms_code", $_POST['code']))
		$warnings['code'] = $warning;

	// Błędy
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	$db->query($db->prepare(
		"INSERT INTO `" . TABLE_PREFIX . "sms_codes` (`code`, `tariff`) " .
		"VALUES( '%s', '%d' )",
		array(strtoupper($_POST['code']), $_POST['tariff'])
	));

	log_info($lang_shop->sprintf($lang_shop->sms_code_admin_add, $user['username'], $user['uid'], $_POST['code'], $_POST['tariff']));
	// Zwróć info o prawidłowym dodaniu
	json_output("added", $lang->sms_code_add, 1);
} else if ($action == "delete_sms_code") {
	if (!get_privilages("manage_sms_codes")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$result = $db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "sms_codes` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf($lang_shop->sms_code_admin_delete, $user['username'], $user['uid'], $_POST['id']));
		json_output("deleted", $lang->delete_sms_code, 1);
	} else json_output("not_deleted", $lang->no_delete_sms_code, 0);
} else if ($action == "add_service_code") {
	if (!get_privilages("manage_service_codes"))
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);

	// Brak usługi
	if (!strlen($_POST['service']))
		json_output("no_service", $lang->no_service_chosen, 0);

	if (($service_module = $heart->get_service_module($_POST['service'])) === NULL)
		json_output("wrong_module", $lang->bad_module, 0);

	// Id użytkownika
	if (strlen($_POST['uid']) && ($warning = check_for_warnings("uid", $_POST['uid'])))
		$warnings['uid'] = $warning;

	// Kod
	if (!strlen($_POST['code']))
		$warnings['code'] = $lang->field_no_empty;
	else if (strlen($_POST['code']) > 16)
		$warnings['code'] = $lang->return_code_length_warn;

	// Łączymy zwrócone błędy
	$warnings = array_merge((array)$warnings, (array)$service_module->validate_admin_add_service_code($_POST));

	// Przerabiamy ostrzeżenia, aby lepiej wyglądały
	if (!empty($warnings)) {
		foreach ($warnings as $brick => $warning) {
			$warning = create_dom_element("div", $warning, array(
				'class' => "form_warning"
			));
			$data['warnings'][$brick] = $warning;
		}
		json_output("warnings", $lang->form_wrong_filled, 0, $data);
	}

	json_output($return_data['status'], $return_data['text'], $return_data['positive'], $return_data['data']);
} else if ($action == "delete_service_code") {
	if (!get_privilages("manage_service_codes"))
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);

	$result = $db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "service_codes` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	));

	// Zwróć info o prawidłowym lub błędnym usunięciu
	if ($db->affected_rows()) {
		log_info($lang_shop->sprintf("Admin {1}({2}) usunął kod na usługę. ID: {3}", $user['username'], $user['uid'], $_POST['id'])); // TODO
		json_output("deleted", "Kod na usługę został prawidłowo usunięty.", 1); // TODO
	} else json_output("not_deleted", "Kod na usługę nie został usunięty.", 0); // TODO
} else if ($action == "get_form_add_service_code") {
	if (!get_privilages("manage_service_codes"))
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);

	$output = "";
	if (($service_module = $heart->get_service_module($_POST['service'])) !== NULL &&
		object_implements($service_module, "IServiceAdminManageServiceCodes"))
		$output = $service_module->admin_get_form_add_service_code();

	output_page($output, "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "delete_log") {
	if (!get_privilages("manage_logs")) {
		json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);
	}

	$db->query($db->prepare(
		"DELETE FROM `" . TABLE_PREFIX . "logs` " .
		"WHERE `id` = '%d'",
		array($_POST['id'])
	));

	// Zwróć info o prawidłowym lub błędnym usunieciu
	if ($db->affected_rows()) json_output("deleted", $lang->delete_log, 1);
	else json_output("not_deleted", $lang->no_delete_log, 0);
} else if ($action == "refresh_blocks") {
	if (isset($_POST['bricks']))
		$bricks = explode(";", $_POST['bricks']);

	foreach ($bricks as $brick) {
		// Nie ma takiego bloku do odświeżenia
		if (($block = $heart->get_block($brick)) === NULL)
			continue;

		$data[$block->get_content_id()]['content'] = $block->get_content($_GET, $_POST);
		if ($data[$block->get_content_id()]['content'] !== NULL)
			$data[$block->get_content_id()]['class'] = $block->get_content_class();
		else
			$data[$block->get_content_id()]['class'] = "";
	}

	output_page(json_encode($data), "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "get_action_box") {
	if (!isset($_POST['page_id']) || !isset($_POST['box_id']))
		json_output("no_data", "Nie podano wszystkich potrzebnych danych.", 0); // TODO

	if (($page = $heart->get_page($_POST['page_id'], "admin")) === NULL)
		json_output("wrong_page", "Podano błędne id strony.", 0); // TODO

	if (!object_implements($page, "IPageAdminActionBox"))
		json_output("page_no_action_box", "Strona nie wspiera action boxów.", 0); // TODO

	$action_box = $page->get_action_box($_POST['box_id'], $_POST);

	actionbox_output($action_box['id'], $action_box['text'], $action_box['template']);
} else if ($action == "get_template") {
	$template = $_POST['template'];
	// Zabezpieczanie wszystkich wartości post
	foreach ($_POST as $key => $value)
		$_POST[$key] = htmlspecialchars($value);

	if ($template == "admin_user_wallet") {
		if (!get_privilages("manage_users"))
			json_output("not_logged_in", $lang->not_logged_or_no_perm, 0);

		$user2 = $heart->get_user($_POST['uid']);
	}

	if (!isset($data['template']))
		eval("\$data['template'] = \"" . get_template("jsonhttp/" . $template) . "\";");

	output_page(json_encode($data), "Content-type: text/plain; charset=\"UTF-8\"");
}

json_output("script_error", "An error occured: no action.");