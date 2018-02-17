<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
 **********************************************/

require_once(AUTHORISER . ".klaso.php");

class CollectivePOS {

    public $area = array();
    public $authoriser = AUTHORISER;
    public $currency = '';
    public $currencyFormatter;		// Value is set by setLocale() method
    public $current_sale;
    public $decimalFormatter;		// Value is set by setLocale() method
    public $decimalSeparator = ".";	// Value is set by setLocale() method
    public $docu;
    public $ext_library = 'ext-4.2.0.663';
    public $firstDayOfWeek = 2;		// 1=Sunday, 2=Monday. Value is set by setLocale() method
    public $from; // from-date
    public $GET = array();
    public $http_host = INSTALL_URI;
    public $language = 'en_GB';
    public $locale;					// Value is set by setLocale() method
    public $main_data = array();
    public $monetaryPrecision = 2;	// Value is set by setLocale() method
    public $mysqli;
    public $POST = array();
    public $preferences;
    public $returi;
    public $root = INSTALL_ROOT;
    public $say = array();
    public $template;
    public $till;
    public $timezone;
    public $title = 'Collective POS';
    public $to; // to-date
    public $user = array();
    public $warnings = array(); // Viktige ting som trenger oppmerksomhet ved innlogging. Meldingene er gruppert i kategori 0-teknisk alarm, 1-advarsel, og 2-orientering/påminnelse


    public function __construct() {
        global $mysqliConnection;
        $this->mysqli = $mysqliConnection;
        if(!version_compare(PHP_VERSION, '7.0.0', '<')) {
            $this->authoriser = new $this->authoriser;
        }
        $this->preferences = new stdClass;

        $this->loadPreferences();
        $this->setLocale();
        $this->getCurrentUser();

        $this->escape();
        $this->from = new DateTime(@$_GET['from'], $this->timezone);
        $this->from->setTime(0, 0, 0);
        $this->from->setTimezone(new DateTimeZone('UTC'));
        $this->to = new DateTime(@$_GET['to'], $this->timezone);
        $this->to->setTime(23, 59, 59);
        $this->to->setTimezone(new DateTimeZone('UTC'));
        $this->docu = @$_GET['docu'];
        $this->returi = new returi;
    }


// Get all active traders
    /****************************************/
//	--------------------------------------
//	return: array of Trader objects
    public function activeTraders() {
        $tp = $this->mysqli->table_prefix;
        $traders = $this->mysqli->arrayData(array(
            'source'		=> "{$tp}traders",
            'where'			=> "{$tp}traders.active and {$tp}traders.id",
            'orderfields'	=> "{$tp}traders.name"
        ))->data;
        foreach($traders as $trader) {
            $result[] = new Trader($trader->id);
        }
        return $result;
    }


    public function add_interval($timestamp, $intervall) {
        $enhet = substr($intervall, -1);
        $verdi = (int)substr($intervall, 1);
        $date_time_array = getdate($timestamp);
        $day = $date_time_array['mday'];
        $month = $date_time_array['mon'];
        $year = $date_time_array['year'];
        switch ($enhet) {
            case 'Y':
                $year += $verdi;
                break;
            case 'M':
                $month += $verdi;
                break;
            case 'D':
                $day += $verdi;
                break;
        }
        $timestamp = mktime(0, 0, 0, $month, $day, $year);
        return $timestamp;
    }


    public function caps($tekst) {
        $tekst = htmlentities($tekst, ENT_QUOTES);
        $smaa = '/&([a-z])(uml|acute|circ|tilde|ring|elig|grave|slash|horn|cedil|th);/e';
        $store = "'&'.strtoupper('\\1').'\\2'.';'";

        $resultat = preg_replace($smaa, $store, $tekst);

        // convert from entities back to characters
        $htmltabell = get_html_translation_table(HTML_ENTITIES);
        foreach($htmltabell as $nr => $verdi) {
            $resultat = ereg_replace(addslashes($verdi),$nr,$resultat);
        }
        return(strtoupper($resultat));
    }


    /*	Check if the Shared Deposit is active
    Checks if the Shared Prepayment Deposit is being used
    *****************************************/
//	--------------------------------------
//	return: (boolean): True if the balance of the Shared Prepayment Deposit is not 0
    public function checkIfSharedDepositIsActive() {
        $tp = $this->mysqli->table_prefix;
        $result = $this->mysqli->arrayData(array(
            'source'		=>	"{$tp}prayment_distributions\n"
                .	"inner join {$tp}payments on {$tp}payment_distributions.payment_id = {$tp}payments.id",
            'fields'		=> "SUM({$tp}payment_distributions.amount) AS balance",
            'where'			=> "!{$tp}payment_distributions.trader_id",
            'groupfields'	=> "{$tp}payments.paymentMethod",
            'orderfieds'	=> "abs(SUM({$tp}payment_distributions.amount)) DESC"
        ));
        if( $result->totalRows ) {
            return (bool)$result->data[0]->balance;
        }
        return false;
    }


    public function DateAdd($interval, $number, $date) {
        $date_time_array = getdate($date);
        $hours = $date_time_array['hours'];
        $minutes = $date_time_array['minutes'];
        $seconds = $date_time_array['seconds'];
        $month = $date_time_array['mon'];
        $day = $date_time_array['mday'];
        $year = $date_time_array['year'];

        switch ($interval) {
            case 'yyyy':
                $year+=$number;
                break;
            case 'q':
                $year+=($number*3);
                break;
            case 'm':
                $month+=$number;
                break;
            case 'y':
            case 'd':
            case 'w':
                $day+=$number;
                break;
            case 'ww':
                $day+=($number*7);
                break;
            case 'h':
                $hours+=$number;
                break;
            case 'n':
                $minutes+=$number;
                break;
            case 's':
                $seconds+=$number;
                break;
        }
        $timestamp= mktime($hours,$minutes,$seconds,$month,$day,$year);
        return $timestamp;
    }


    public function datetime($time) {
        $a = new IntlDateFormatter($this->locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, $this->preferences->timezone);
        return $a->format($time);
    }


    public function escape(){
        if (get_magic_quotes_gpc()) {
            $GET = array_map("stripslashes", $_GET);
            $POST = array_map("stripslashes", $_POST);
        }
        else {
            $GET = $_GET;
            $POST = $_POST;
        }
        $this->GET = array_map(array($this, 'real_escape'), $GET);
        $this->POST = array_map(array($this, 'real_escape'), $POST);
    }


    function folder($file) {
        $path = array_reverse(explode("/", $file));
        return $path[1];
    }


    public function footnote($number, $symbol = "*") {
        $result = "";
        $a = 0;
        while( $a < $number ) {
            $result .= $symbol;
            $a++;
        }
        return $result;
    }


// Outputs at DateTime object as UTC string
    /****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		data: (string) Formatted date
//		msg: (string) Error message
    public function formatUtc($format, $timestamp) {
        $time = clone $timestamp;
        $time->setTimezone(new DateTimeZone('UTC'));
        return $time->format($format);
    }


    public function getCountName($count_no){
        settype($count_no, 'integer');
        return $this->mysqli->arrayData(array(
            'source' => "{$this->mysqli->table_prefix}inventory_count",
            'where' => "count_no = {$count_no}"
        ))->data[0]->count_name;
    }


    public function getCurrencySymbol() {
        $a = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        return $a->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }


    /****************************************/
//	$filter:	associated array/object with these properties to be assigned:
//		from:			DateTime object From Date
//		to:				DateTime object To Date
//		paymentMethod:	(int) Payment Method ID as stored in DB
//	--------------------------------------
//	return: array of stdClass objects with the following properties:
// 		id:						(int) Payment method ID as stored in database
// 		paymentMethod (string): (string) Payment method name
// 		transactionChargeFixed: (number) Fixed transaction fee
// 		transactionChargeRate: (number) Relative transaction fee
// 		quantity:				(int) number of transactions within this payment method
// 		sum:					(number) Sum of transactions within this payment method
// 		cost:					(number) Sum of payment charges within this payment method
// 		traders:				array of stdClass objects with the following properties:
// 			trader:				Trader object
// 			payments:			array of stdClass objects with the following properties:
// 				payment:		Payment object
// 				proportion:		(number) traders proportion of payment given as decimal
// 				amount:			(number) traders share of this payment
// 				cost:			(number) traders share of payment charges
    public function getPaymentCharges($filter = array()) {
        settype($filter, 'array');
        if($filter['from']) {
            $filter['from']->setTimezone(new DateTimeZone('UTC'));
        }
        if($filter['to']) {
            $filter['to']->setTimezone(new DateTimeZone('UTC'));
        }
        $tp = $this->mysqli->table_prefix;

        $query['class'] = "Payment";
        $query['source'] = "{$tp}payments INNER JOIN {$tp}sales ON {$tp}payments.sale = {$tp}sales.id LEFT JOIN {$tp}payment_methods ON {$tp}payments.paymentMethod = {$tp}payment_methods.id";
        $query['where'] = "1";
        $query['where'] .= (@$filter['paymentMethod'] ? (" AND {$tp}payments.paymentMethod >= '" . $this->real_escape($filter['paymentMethod']) . "'") : "");
        $query['where'] .= ($filter['from'] ? (" AND DATE(timestamp) >= '" . $filter['from']->format('Y-m-d H:i:s') . "'") : "");
        $query['where'] .= ($filter['to'] ? (" AND DATE(timestamp) <= '" . $filter['to']->format('Y-m-d H:i:s') . "'") : "");
        $query['fields'] = "{$tp}payments.id";
        $query['orderfields'] = "{$tp}payment_methods.sortorder, {$tp}payments.timestamp DESC";

        $result = array();
        $payments = $this->mysqli->arrayData($query);
        if(!$payments->success) return $payments;
        foreach($payments->data as $payment) {
            $paymentMethodId = $payment->paymentMethod->id;

            settype($result[$paymentMethodId], 'object');
            settype($result[$paymentMethodId]->sum, 'string');
            settype($result[$paymentMethodId]->cost, 'string');
            settype($result[$paymentMethodId]->quantity, 'integer');
            $result[$paymentMethodId]->id = $paymentMethodId;
            $result[$paymentMethodId]->name = $payment->paymentMethod->name;
            $result[$paymentMethodId]->transactionChargeFixed = $payment->paymentMethod->transactionChargeFixed;
            $result[$paymentMethodId]->transactionChargeRate = $payment->paymentMethod->transactionChargeRate;

            $shares = $payment->getShares();
            if(!$shares->success) return $shares;

            foreach($shares->shares as $share) {
                $trader	= $share->trader;
                $traders_share = $trader->getShareOfPayment($payment);

                settype($result[$paymentMethodId]->traders[$trader->id], 'object');
                settype($result[$paymentMethodId]->traders[$trader->id]->sum, 'string');
                settype($result[$paymentMethodId]->traders[$trader->id]->costs, 'string');
                $result[$paymentMethodId]->traders[$trader->id]->trader = $trader;

                $payment_share = (object) array(
                    'id' => $payment->id,
                    'payment' => $payment,
                    'amount' => $traders_share->amount,
                    'proportion' => $traders_share->proportion,
                    'cost' => $traders_share->cost
                );

                $result[$paymentMethodId]->traders[$trader->id]->payments[] = $payment_share;

                $result[$paymentMethodId]->traders[$trader->id]->sum = bcadd(
                    $result[$paymentMethodId]->traders[$trader->id]->sum,
                    $traders_share->amount,
                    6
                );
                $result[$paymentMethodId]->traders[$trader->id]->costs = bcadd(
                    $result[$paymentMethodId]->traders[$trader->id]->costs,
                    $traders_share->cost,
                    6
                );

            }

            $result[$paymentMethodId]->sum = bcadd(
                $result[$paymentMethodId]->sum,
                $payment->amount,
                6
            );
            $result[$paymentMethodId]->cost = bcadd(
                $result[$paymentMethodId]->cost,
                $payment->getCost()->data,
                6
            );
            $result[$paymentMethodId]->quantity += 1;
        }
        return $result;
    }


    /****************************************/
//	$from:				DateTime object
//	$to:				DateTime object
//	$paymentMethod:	integer
//	--------------------------------------
//	return	array:
//				total_payments (float)
//				total_charges (float)
//				payment_methods (array):
//					id	(integer)
//					name (string)
//					paymentGroup (string)
//					transactionChargeFixed (float)
//					transactionChargeRate (float)
//					colour (string)
//					total_payments (float)
//					total_charges (float)
//					payments (array):
//						time (DateTime object)
//						sale (Sale object)
//						amount (float)
//						note (string)
//						payment_charges (float)
    public function getPayments($from = NULL, $to = NULL, $paymentMethod = 0) {
        $result = new stdClass;
        $tp = $this->mysqli->table_prefix;
        if($from) {
            $from->setTimezone(new DateTimeZone('UTC'));
        }
        if($to) {
            $to->setTimezone(new DateTimeZone('UTC'));
        }
        $to->setTimezone(new DateTimeZone('UTC'));
        $payment_methods = $this->mysqli->arrayData(array(
            'class' => "PaymentMethod",
            'source' => "{$tp}payment_methods",
            'where' => ($paymentMethod ? "id = '{$paymentMethod}'" : "1"),
            'fields' => "id, name, paymentGroup, transactionChargeFixed, transactionChargeRate, colour",
            'orderfields' => "{$tp}payment_methods.sortorder"
        ));
        foreach($payment_methods->data as $paymentMethod) {
            settype($paymentMethod->id, "integer");
            settype($paymentMethod->transactionChargeFixed, "float");
            settype($paymentMethod->transactionChargeRate, "float");
            $result->payment_methods[$paymentMethod->id] = $paymentMethod;
            $payments = $this->mysqli->arrayData(array(
                'source' => "{$tp}payments",
                'where' => "paymentMethod = '{$paymentMethod->id}'" . ($from ? " AND DATE(timestamp) >= '" . $from->format('Y-m-d H:i:s') . "'" : "") . ($to ? " AND DATE(timestamp) <= '" . $to->format('Y-m-d H:i:s') . "'" : ""),
                'fields' => ""
            ));
            $result->payment_methods[$paymentMethod->id]->total_payments = 0;
            $result->payment_methods[$paymentMethod->id]->total_charges = 0;
            foreach($payments->data as $key => $payment) {
                $result->payment_methods[$paymentMethod->id]->payments[$key] = $payment;
                $result->payment_methods[$paymentMethod->id]->payments[$key]->time = new DateTime($payment->timestamp, new DateTimeZone('UTC'));
                $result->payment_methods[$paymentMethod->id]->payments[$key]->sale = new Sale(array('id' => $payment->sale));
                $result['payment_methods'][$paymentMethod->id]->total_payments += $result->payment_methods[$paymentMethod->id]->payments[$key]->amount = $payment->amount;
                $result->payment_methods[$paymentMethod->id]->total_charges += $result->payment_methods[$paymentMethod->id]->payments[$key]->payment_charges = ($payment->amount * $paymentMethod->transactionChargeRate + $paymentMethod->transactionChargeFixed);
            }
            $result->total_payments += $result->payment_methods[$paymentMethod->id]->total_payments;
            $result->total_charges += $result->payment_methods[$paymentMethod->id]->total_charges;
        }
        return $result;
    }


    /****************************************/
//	$from:	DateTime object
//	$to:	DateTime object
//	--------------------------------------
//	return:	array of DateTime objects - all dates with sales activity
    public function getSaleDates($from = NULL, $to = NULL) {
        if($from) {
            $from->setTimezone(new DateTimeZone('UTC'));
        }
        if($to) {
            $to->setTimezone(new DateTimeZone('UTC'));
        }
        $tp = $this->mysqli->table_prefix;
        $dates = $this->mysqli->arrayData(array(
            'source' => "{$tp}invoices",
            'where' => "date >= '" . $from->format('Y-m-d H:i:s') . "' AND date <= '" . $to->format('Y-m-d H:i:s') . "'",
            'fields' => "date",
            'groupfields' => "date",
        ));
        $result = array();
        foreach($dates->data as $date) {
            $result[] = new DateTime($date->date, new DateTimeZone('UTC'));
        }
        return $result;
    }


    public function getTaxes() {
        $tp = $this->mysqli->table_prefix;
        $a = $this->mysqli->arrayData(array(
            'source' => "{$tp}tax"
        ));
        foreach($a->data as $tax) {
            $b[$tax->id] = $tax;
        }
        return $b;
    }


    public function get_user($userid){
        $result = $this->mysqli->arrayData(array(
            'source' => "access_user_users",
            'where' => "id = '{$userid}'"
        ));
        return $result->data[0]->real_name;
    }


// Get Users permitted Areas
    /****************************************/
//	--------------------------------------
//	return:	array of stdClass objects
    public function getUserAreas() {
        return $this->mysqli->arrayData(array(
            'source' => "access_user_areas",
            'where' => "userid = '{$this->user['id']}'"
        ))->data;
    }


// Return number grouping symbol according to locale
    /****************************************/
//	--------------------------------------
//	return:	(string) symbol used as grouping sepaator
    public function groupingSymbol() {
        $a = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        return $a->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }


    public function lastSalesdate($trader_id = 0) {
        $tp = $this->mysqli->table_prefix;
        $completed = $this->mysqli->arrayData(array(
            'source' => ($trader_id ? "{$tp}sales INNER JOIN {$tp}invoices ON {$tp}sales.id = {$tp}invoices.sale" : "{$tp}sales"),
            'fields' => "MAX({$tp}sales.completed) AS completed",
            'where' => ($trader_id ? "{$tp}invoices.trader = '{$trader_id}'" : "1")
        ))->data[0]->completed;
        return new DateTime("@{$completed}");
    }


// Load settings and preferences
    /****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
    public function loadPreferences() {
        $tp = $this->mysqli->table_prefix;

        $set = $this->mysqli->arrayData(array(
            'source' => "{$tp}preferences",
            'where' => "!trader"
        ));
        if(!$set->success) {
            throw new Exception('Could not load the preferences');
        }
        foreach ($set->data as $preference) {
            $this->preferences->{$preference->setting} = $preference->value;
        }
        if(isset($this->preferences->currency)) {
            $this->currency = $this->preferences->currency;
        }
        else {
            throw new Exception('Could not load currency from preferences');
        }

        $this->till = new Till($this->preferences->till);
        $this->preferences->till_values = json_decode($this->preferences->till_values);
        return true;
    }


    public function long_datetime($datetime) {
        $a = new IntlDateFormatter($this->locale, IntlDateFormatter::FULL, IntlDateFormatter::SHORT, $this->preferences->timezone);
        return $a->format($datetime);
    }


    public function mission($mission = "") {
        if ($mission == "receive") {
            $this->receive(@$_GET['form']);
        }
        if ($mission == "request") {
            echo $this->request(@$_GET['data']);
        }
        if ($mission == "createpdf") {
            $this->createPDF(@$_GET['pdf']);
        }
        if ($mission == "amend") {
            $this->amend(@$_GET['data']);
        }
        if ($mission == "task") {
            $this->task(@$_GET['task']);
        }
    }

    public function money($sum) {
        $a = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        return $a->formatCurrency($sum, $this->currency);
    }


// Return currency format according to locale
    /****************************************/
//	--------------------------------------
//	return:	(string) sum formatted according to locale settings
    public function money_pattern() {
        $a = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        return $a->getPattern();
    }


    public function number($sum) {
        $a = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        return $a->format($sum);
    }


    public function parse($str) {
        $a = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        return $a->parse($str);
    }


    public function percent($fraction) {
//	$a = new NumberFormatter($this->locale, NumberFormatter::PERCENT);
//	return $a->format($fraction);
        $a = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        return $a->format(round($fraction*100, 2)) . " %";
    }


    public function permission($config) {
        if(!$this->user) {
            $this->getCurrentUser();
        }

        $this->authoriser->postuluIdentigon();

        $a = array(
            'source' => "access_user_areas",
            'where' => "userid = '{$this->user['id']}' AND area = '{$config['area']}'"
        );
        if($config['area'] == 'admin'){
            $a['where'] .=	" AND trader = '{$config['trader']}'\n";
        }
        $hits = $this->mysqli->arrayData($a);
        if(count($hits->data) > 0) {
            return true;
        }
        else {
            return false;
        }
    }


    public function getCurrentUser() {
        if(!$this->user) {
            if(version_compare(PHP_VERSION, '7.0.0', '<')) {
                $access_user = new SessionsManager;
                $access_user->access_page($_SERVER['PHP_SELF'], $_SERVER['QUERY_STRING']);
                $access_user->get_user_info();
                $this->user = get_object_vars(json_decode($access_user->user_info));
                $this->user['name']		= $access_user->user_full_name;
                $this->user['id']		= $access_user->id;
                $this->user['username']	= $access_user->user;
                $this->user['email']	= $access_user->user_email;
                $this->setLocale($this->user['locale']);
            }
            else {
                $authoriser = $this->authoriser;

                $this->user['name']		= $authoriser->akiruNomo();
                $this->user['id']		= $authoriser->akiruId();
                $this->user['username']	= $authoriser->akiruUzantoNomo();
                $this->user['email']	= $authoriser->akiruRetpostadreso();

                $locale = $authoriser->akiri('locale');
                $this->setLocale($locale);

                if ($authoriser->akiri('till')) {
                    $this->till = new Till($authoriser->akiri('till'));
                    $this->preferences->till_values = json_decode($this->preferences->till_values);
                }
            }
        }
        return $this->user;
    }


    public function possessive($noun) {
        switch(strtolower($this->locale)) {
            case "no":
            case "no_nb":
            case "no_nn":
                $noun = (strtolower(substr($noun, -1, 1)) == 's' ? "{$noun}'" : "{$noun}s");
                break;
            case "en":
            case "en_gb":
            case "en_us":
                $noun = (strtolower(substr($noun, -1, 1)) == 's' ? "{$noun}'" : "{$noun}'s");
                break;
            default:
                break;
        }
        return $noun;
    }


    public function read_proportion($proportion){
        $proportion = str_replace(array(",", "%", " "), array(".", "/100", ""), $proportion);
        $decimal = (float)eval("return $proportion;");
        if($decimal >1 or $decimal <0) return false;
        else return $decimal;
    }


// Funksjon som klargjør alle GET- og POST-verdier for å smettes inn i databasen
    public function real_escape($string) {
        return $this->mysqli->real_escape_string($string);
    }

// Return number formatted according to locale, and with forced positive / negative sign
    /****************************************/
//	$sum:	float
//	--------------------------------------
//	return:	(string) sum formatted according to locale settings
    public function relative($sum) {
        $a = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        return ($sum > 0 ? $a->getSymbol(NumberFormatter::PLUS_SIGN_SYMBOL) : "") . ($sum < 0 ? $a->getSymbol(NumberFormatter::MINUS_SIGN_SYMBOL) : "") . $a->format(abs($sum));
    }


// Round a sum according to locale (number of fractions)
    /****************************************/
//	$sum:	float
//	--------------------------------------
//	return:	(float) sum rounded according to locale settings
    public function roundSum($sum) {
        $a = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        return round($sum, $a->getAttribute(NumberFormatter::MAX_FRACTION_DIGITS));
    }


    public function say($phrase, $array = array()){
        if(@$this->say[$phrase] instanceof MessageFormatter) {
            return $this->say[$phrase]->format($array);
        }
        else if(isset($this->say[$phrase])) {
            return $this->say[$phrase];
        }
        else return "{i18n error: $phrase}";
    }


//	$this->testapp slår av alle epostsendinger
// Sender mail som HTML eller tekst. $config er et objekt som består av:
//	to
// cc
// bcc
// from
// testcopy sender blindkopi til kayegil.hauan@svartlamon.org i tillegg
// reply
// subject
// html HTML-versjonen av meldingen
// text Ren tekst-versjon av meldingen
    public function sendMail($config){
        $random_hash = md5(date('r', time()));

        $header .= $config['cc'] ? "Cc: {$config['cc']}\r\n" : "";
        $header = ($config['bcc'] or $config['testcopy']) ? "Bcc: " : "";
        $header .= $config['bcc'];
        $header .= ($config['bcc'] and $config['testcopy']) ? ", " : "";
        $header .= $config['testcopy'] ? "kyegil@gmail.com" : "";
        $header .= ($config['bcc'] or $config['testcopy']) ? "\r\n" : "";

        $header .= $config['from'] ? "From: {$config['from']}\r\n" : "From: {$this->valg['autoavsender']}\r\n";
        $header .= $config['reply'] ? "Reply-To: {$config['reply']}\r\n" : "Reply-To: {$this->valg['utleier']}<{$this->valg['epost']}>\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= $config['html'] ? "Content-Type: multipart/alternative; boundary=\"PHP-alt-$random_hash\"\r\n" : "Content-type: text/plain; charset=UTF-8\r\n";

        // Dersom teksten bare er oppgitt som HTML og ikke som ren tekst, opprettes en ren tekst-versjon av den HTML-formaterte.
        if($config['html'] and !$config['text']) {
            $config['text'] = $config['html'];
            $search = array(
                "<br>\n",
                "<br/>\n",
                "<br />\n",
                "</p>\n",
                "</h1>\n",
                "</tr>\n",
                "</div>\n"
            );
            $replace = array(
                "<br />",
                "<br />",
                "<br />",
                "</p>",
                "</h1>",
                "</tr>",
                "</div>"
            );
            $config['text'] = str_ireplace($search, $replace, $config['text']);

            $search = array(
                "<br>",
                "<br/>",
                "<br />",
                "</p>",
                "</h1>",
                "</tr>",
                "</div>"
            );
            $replace = array(
                "<br />\n",
                "<br />\n",
                "<br />\n",
                "</p>\n",
                "</h1>\n",
                "</tr>\n",
                "</div>\n"
            );
            $config['text'] = str_ireplace($search, $replace, $config['text']);

            $search = array(
                "<br />\n<br />\n",
                "<br />\n\n",
                "\n\n",
                "&nbsp;&nbsp;&nbsp;&nbsp;",
                "&nbsp;"
            );
            $replace = array(
                "<br />\n",
                "<br />\n",
                "\n",
                "\t",
                " "
            );
            $config['text'] = str_ireplace($search, $replace, $config['text']);
            $config['text'] = strip_tags($config['text']);
        }

        if($config['html']) {
            $innhold = "--PHP-alt-$random_hash\r\nContent-type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$config['text']}\n" .	"--PHP-alt-$random_hash\r\nContent-type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$config['html']}\n--PHP-alt-$random_hash--";
        }
        else {
            $innhold = $config['text'];
        }

        if ($this->testapp) return true;
        else return mail($config['to'], $config['subject'], $innhold, $header);
    }


    public function setCountName($count_no, $count_name){
        settype($count_no, 'integer');
        if($count_no) {
            return $this->mysqli->saveToDb(array(
                'table' => "{$tp}inventory_count",
                'update' => true,
                'where' => "count_no = '{$count_no}'",
                'fields' => array(
                    'count_name' => $count_name
                )
            ))->success;
        }
        else return false;
    }


    public function setLocale($locale = "") {

        // if $locale is given then use this
        if($locale and file_exists("{$this->root}/i18n/$locale.php")){
            $this->locale = $this->say['locale'] = $locale;
        }

        // otherwise try to fing the correct locale
        else {

            // Get the Accept-Language-header from the current request,
            //	if one has been submitted by the browser
            $preference = Locale::acceptFromHttp(getenv('HTTP_ACCEPT_LANGUAGE'));

            // List all the available language files
            $files = scandir("{$this->root}/i18n/");
            $avail = str_replace(".php", "", $files);

            // Search the available language files for the best match to the preferred language
            //	according to RFC 4647's lookup algorithm
            //	The language will default to the one given by $this->language
            $this->locale = $this->say['locale']
                = Locale::lookup(
                $avail,		// The available languages
                $preference, // The preferred language
                false,		// If true, the arguments will be converted to canonical form before matching
                $this->language // The locale to use if no match is found
            );
        }

        //	Get the system timezone from the preferences
        //	if none is given the timezone will be guessed by date_default_timezone_get()
        $this->timezone = new DateTimeZone(date_default_timezone_get());
        if(in_array( $this->preferences->timezone, $this->timezone->listIdentifiers() )) {
            $this->timezone = new DateTimeZone($this->preferences->timezone);
        }

        // include the necessary language files
        include("{$this->root}/i18n/{$this->language}.php");
        include("{$this->root}/i18n/{$this->locale}.php");

        // Apply necessary system wide locale settings
        $this->decimalFormatter
            = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $this->currencyFormatter
            = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        $this->decimalSeparator
            = $this->say['decimal_separator']
            = $this->decimalFormatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $this->monetaryPrecision
            = $this->currencyFormatter->getAttribute(NumberFormatter::FRACTION_DIGITS);

        if(version_compare(PHP_VERSION, '5.5.0', '>=')) {
            $d = IntlCalendar::createInstance(NULL, $this->locale);
            $this->firstDayOfWeek = $d->getFirstDayOfWeek();
        }

        return true;
    }


// Return date formatted according to locale
    /****************************************/
//	$date:	DateTime object
//	--------------------------------------
//	return:	(string) date formatted as short date according to locale settings
    public function shortdate($date) {
        $a = new IntlDateFormatter($this->locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, $this->preferences->timezone);
        return $a->format($date);
    }


// Return time formatted according to locale
    /****************************************/
//	$date:	DateTime object
//	--------------------------------------
//	return:	(string) time formatted as short date according to locale settings
    public function shortdate_format($convert = false) {
        $a = new IntlDateFormatter($this->locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, $this->preferences->timezone);
        $result = $a->getPattern();
        $search = array(
            'dd',
            'd',
            'x',
            'MM',
            'yyyy',
            'yy'
        );
        $replace = array(
            'x',
            'j',
            'd',
            'm',
            'Y',
            'y'
        );
        if($convert) {
            return str_replace($search, $replace, $result);
        }
        else {
            return $result;
        }
    }


    public function shorttime($time) {
        $a = new IntlDateFormatter($this->locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $this->preferences->timezone);
        return $a->format($time);
    }


// Return date and time formatted according to locale
    /****************************************/
//	$datetime:	DateTime object
//	--------------------------------------
//	return:	string datetime formatted according to locale settings
    public function short_datetime($datetime) {
        $a = new IntlDateFormatter($this->locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, $this->preferences->timezone);
        return $a->format($datetime);
    }


// Return sign of a given number
    /****************************************/
//	--------------------------------------
//	return:	(int) -1 for negative numbers, 1 for positive numbers, or else 0
    public function sign($number) {
        return min(1, max(-1, (is_nan($number) or $number == 0) ? 0 : $number * INF));
    }


    public function string_or_null($streng, $hermetegn = "'") {
        if($streng !="" and $streng !=null)
            return $hermetegn.$streng.$hermetegn;
        else return 'NULL';
    }


// Setter sammen ei tekstliste over innholdet i et array
    public function summarise($array = array(), $skillestreng = ", ", $sisteskillestreng = " & ") {
        $array = array_values($array);
        if(!is_array($array))
            return "";
        $streng = "";
        $ant = count($array);
        foreach($array as $nr=>$verdi) {
            $streng .= $verdi;
            if($nr < $ant-2) $streng .= $skillestreng;
            if($nr == $ant-2) $streng .= $sisteskillestreng;
        }
        return $streng;
    }


    public function time_colour($time) {
        if(!($time instanceof DateTime)) return null;
        $time->setTimezone(new DateTimeZone('UTC'));
        return "#" . dechex(3*(round($time->format('d')/6))) . dechex(3*(round($time->format('d')/6))) . dechex(3*(round($time->format('i')/11))) . dechex(3*(round($time->format('i')/11))) . dechex(3*(round($time->format('s')/11))) . dechex(3*(round($time->format('s')/11)));
    }


// Converts two-dimensional array to comma separated values
    /****************************************/
//	$data:				Two dimensional array (table)
//	$separator:			(string) Symbol to separate each field
//	$textDelimiter:	(string) Symbol to wrap around text
//	$dateFormat:		(string) Date format
//	--------------------------------------
//	return:	string CSV table
    public function toCsv($data, $separator = ",", $textDelimiter = '"', $dateFormat = 'Y-m-d H:i:s') {
        $result = "";
        settype($data, 'array');
        settype($data[0], 'array');

        $keys = array_keys($data[0]);

        $result .= $textDelimiter . implode($textDelimiter . $separator . $textDelimiter, $keys) . $textDelimiter . "\n";

        foreach($data as $rowindex => $row) {
            settype($data[$rowindex], 'array');
        }
        foreach($data as $rowindex => $row) {
            foreach($row as $field => $value) {
                if((int)$value !== $value) {
                    $data[$rowindex][$field] = $textDelimiter . addslashes($value) . $textDelimiter;
                }
            }
        }
        foreach($data as $rowindex => $row) {
            $result .= implode($separator, $row) . "\n";
        }

        return $result;
    }


// Return date and time formatted according to locale
    /****************************************/
//	$datetime:	DateTime object
//	--------------------------------------
//	return:	string datetime formatted according to locale settings
    /****************************************/
//	--------------------------------------
//	return: associated array (with tax_id as key) containing associated array with the following keys:
//				id
//				taxName
//				taxRate
    public function write_html() {
        if(!file_exists("templates/" . $this->template . ".php"))
            die("File '" . $this->template . ".php' missing in '" . $this->folder($_SERVER['PHP_SELF']) . "/templates' directory.");
        else {
            include("templates/" . $this->template . ".php");
        }
    }


}