<?php

$heart->register_page("payment_wallet", "PageAdminPaymentWallet", "admin");

class PageAdminPaymentWallet extends PageAdmin {

	function __construct()
	{
		global $lang;
		$this->title = $lang['payment_wallet'];

		parent::__construct();
	}

	protected function content($get, $post) {
		global $db, $settings, $lang, $G_PAGE;

		$result = $db->query(
			"SELECT SQL_CALC_FOUND_ROWS * " .
			"FROM ({$settings['transactions_query']}) as t " .
			"WHERE t.payment = 'wallet' " .
			"ORDER BY t.timestamp DESC " .
			"LIMIT " . get_row_limit($G_PAGE)
		);
		$rows_count = $db->get_column("SELECT FOUND_ROWS()", "FOUND_ROWS()");

		$tbody = "";
		while ($row = $db->fetch_array_assoc($result)) {
			$row['cost'] = $row['cost'] ? number_format($row['cost'], 2) . " " . $settings['currency'] : "";

			// Podświetlenie konkretnej płatności
			if ($get['highlight'] && $get['payid'] == $row['payment_id']) {
				$row['class'] = "highlighted";
			}

			// Pobranie danych do tabeli
			eval("\$tbody .= \"" . get_template("admin/payment_wallet_trow") . "\";");
		}

		// Nie ma zadnych danych do wyswietlenia
		if (!strlen($tbody))
			eval("\$tbody = \"" . get_template("admin/no_records") . "\";");

		// Pobranie paginacji
		$pagination = get_pagination($rows_count, $G_PAGE, "admin.php", $get);
		if (strlen($pagination))
			$tfoot_class = "display_tfoot";

		// Pobranie nagłówka tabeli
		eval("\$thead = \"" . get_template("admin/payment_wallet_thead") . "\";");

		// Pobranie struktury tabeli
		eval("\$output = \"" . get_template("admin/table_structure") . "\";");
		return $output;
	}

}