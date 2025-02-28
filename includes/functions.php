<?php

use App\Loggers\FileLogger;
use App\Models\PaymentPlatform;
use App\Models\User;
use App\Payment\General\PaymentMethod;
use App\Routing\UrlGenerator;
use App\Server\Platform;
use App\Support\Collection;
use App\Support\Expression;
use App\Support\Money;
use App\Support\QueryParticle;
use App\System\Auth;
use App\System\Settings;
use App\Translation\TranslationManager;
use App\User\Permission;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\VarDumper;

function app(): Container
{
    return Container::getInstance();
}

function can(Permission $permission, ?User $user = null): bool
{
    if (!$user) {
        /** @var Auth $auth */
        $auth = app()->make(Auth::class);
        $user = $auth->user();
    }

    return $user->can($permission);
}

function cannot(Permission $permission, ?User $user = null): bool
{
    return !can($permission, $user);
}

function get_ip(Request $request): ?string
{
    if ($request->server->has("HTTP_CF_CONNECTING_IP")) {
        $cfIpRanges = [
            "103.21.244.0/22",
            "103.22.200.0/22",
            "103.31.4.0/22",
            "104.16.0.0/12",
            "108.162.192.0/18",
            "131.0.72.0/22",
            "141.101.64.0/18",
            "162.158.0.0/15",
            "172.64.0.0/13",
            "173.245.48.0/20",
            "188.114.96.0/20",
            "190.93.240.0/20",
            "197.234.240.0/22",
            "198.41.128.0/17",
        ];

        foreach ($cfIpRanges as $range) {
            if (ip_in_range($request->server->get("REMOTE_ADDR"), $range)) {
                return $request->server->get("HTTP_CF_CONNECTING_IP");
            }
        }
    }

    return $request->server->get("REMOTE_ADDR");
}

// ip_in_range
// This function takes 2 arguments, an IP address and a "range" in several
// different formats.
// Network ranges can be specified as:
// 1. Wildcard format:     1.2.3.*
// 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
// 3. Start-End IP format: 1.2.3.0-1.2.3.255
// The function will return true if the supplied IP is within the range.
// Note little validation is done on the range inputs - it expects you to
// use one of the above 3 formats.
function ip_in_range($ip, $range): bool
{
    if (strpos($range, "/") !== false) {
        // $range is in IP/NETMASK format
        [$range, $netmask] = explode("/", $range, 2);
        if (strpos($netmask, ".") !== false) {
            // $netmask is a 255.255.0.0 format
            $netmask = str_replace("*", "0", $netmask);
            $netmaskDec = ip2long($netmask);

            return (ip2long($ip) & $netmaskDec) == (ip2long($range) & $netmaskDec);
        } else {
            // $netmask is a CIDR size block
            // fix the range argument
            $x = explode(".", $range);
            while (count($x) < 4) {
                $x[] = "0";
            }
            [$a, $b, $c, $d] = $x;
            $range = sprintf(
                "%u.%u.%u.%u",
                empty($a) ? "0" : $a,
                empty($b) ? "0" : $b,
                empty($c) ? "0" : $c,
                empty($d) ? "0" : $d
            );
            $rangeDec = ip2long($range);
            $ipDec = ip2long($ip);

            # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
            #$netmaskDec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

            # Strategy 2 - Use math to create it
            $wildcardDec = pow(2, 32 - $netmask) - 1;
            $netmaskDec = ~$wildcardDec;

            return ($ipDec & $netmaskDec) == ($rangeDec & $netmaskDec);
        }
    } else {
        // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
        if (strpos($range, "*") !== false) {
            // a.b.*.* format
            // Just convert to A-B format by setting * to 0 for A and 255 for B
            $lower = str_replace("*", "0", $range);
            $upper = str_replace("*", "255", $range);
            $range = "$lower-$upper";
        }

        if (strpos($range, "-") !== false) {
            // A-B format
            [$lower, $upper] = explode("-", $range, 2);
            $lowerDec = (float) sprintf("%u", ip2long($lower));
            $upperDec = (float) sprintf("%u", ip2long($upper));
            $ipDec = (float) sprintf("%u", ip2long($ip));

            return $ipDec >= $lowerDec && $ipDec <= $upperDec;
        }

        return false;
    }
}

/**
 * Returns request platform
 */
function get_platform(Request $request): string
{
    return $request->headers->get("User-Agent", "");
}

function is_server_platform(string $platform): bool
{
    return in_array($platform, [Platform::AMXMODX, Platform::SOURCEMOD], true);
}

/**
 * Returns sms cost net by number
 */
function get_sms_cost(string $number): Money
{
    if (strlen($number) < 4) {
        return new Money(0);
    }

    if ($number[0] == "7") {
        return $number[1] == "0" ? new Money(50) : new Money(intval($number[1]) * 100);
    }

    if ($number[0] == "9") {
        return new Money(intval($number[1] . $number[2]) * 100);
    }

    return new Money(0);
}

/**
 * Returns sms provision from given net price
 */
function get_sms_provision(Money $smsPrice): Money
{
    return new Money(ceil($smsPrice->asInt() / 2));
}

function hash_password(string $password, string $salt): string
{
    return md5(md5($password) . md5($salt));
}

function escape_filename(string $filename): string
{
    $filename = str_replace("/", "_", $filename);
    $filename = str_replace(" ", "_", $filename);
    return str_replace(".", "_", $filename);
}

function get_random_string(int $length): string
{
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890"; //length:36
    $finalRand = "";
    for ($i = 0; $i < $length; $i++) {
        $finalRand .= $chars[rand(0, strlen($chars) - 1)];
    }

    return $finalRand;
}

function seconds_to_time(int $seconds): string
{
    $dtF = new DateTime("@0");
    $dtT = new DateTime("@$seconds");

    return $dtF->diff($dtT)->format("%a " . __("days") . " " . __("and") . " %h " . __("hours"));
}

/**
 * @param string $string
 * @return string[]
 */
function custom_mb_str_split(string $string): array
{
    return preg_split('/(?<!^)(?!$)/u', $string);
}

/**
 * @param string[] $columns
 * @param string $search
 * @return QueryParticle|null
 */
function create_search_query(array $columns, string $search): ?QueryParticle
{
    if (!$columns) {
        return null;
    }

    $searchLike = "%" . implode("%", custom_mb_str_split($search)) . "%";

    $params = [];
    $values = [];

    foreach ($columns as $searchId) {
        $params[] = "{$searchId} LIKE ?";
        $values[] = $searchLike;
    }

    $queryParticle = new QueryParticle();
    $query = implode(" OR ", $params);
    $queryParticle->add("( $query )", $values);

    return $queryParticle;
}

if (!function_exists("str_ends_with")) {
    function str_ends_with(string $string, string $end): bool
    {
        return substr($string, -strlen($end)) == $end;
    }
}

if (!function_exists("str_starts_with")) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists("str_contains")) {
    function str_contains(string $string, string $needle): bool
    {
        return strpos($string, $needle) !== false;
    }
}

/**
 * Prints var_dump in pre
 */
function pr(mixed $a): void
{
    echo "<pre>";
    var_dump($a);
    echo "</pre>";
}

function my_is_integer(mixed $val): bool
{
    return strlen($val) && trim($val) === strval(intval($val));
}

function array_get(ArrayAccess|array|null $array, mixed $key, mixed $default = null): mixed
{
    return $array[$key] ?? $default;
}

function array_dot_get(ArrayAccess|array|null $array, string $key, mixed $default = null): mixed
{
    foreach (explode(".", $key) as $segment) {
        if (!isset($array[$segment])) {
            return $default;
        }

        $array = $array[$segment];
    }

    return $array;
}

function captureRequest(): Request
{
    $queryAttributes = [];
    foreach ($_GET as $key => $value) {
        $queryAttributes[$key] = urldecode($value);
    }

    $request = Request::createFromGlobals();
    $request->query->replace($queryAttributes);

    return $request;
}

function get_error_code(PDOException $e): int
{
    return $e->errorInfo[1];
}

function collect(mixed $items = []): Collection
{
    return new Collection($items);
}

function is_list(array $array): bool
{
    if (empty($array)) {
        return true;
    }

    return ctype_digit(implode("", array_keys($array)));
}

function as_money(mixed $value): ?Money
{
    if ($value === null || $value === "") {
        return null;
    }

    return new Money($value);
}

function as_int(mixed $value): ?int
{
    if ($value === null || $value === "") {
        return null;
    }

    if ($value instanceof Money) {
        return $value->asInt();
    }

    return (int) $value;
}

function as_float(mixed $value): ?float
{
    if ($value === null || $value === "") {
        return null;
    }

    if ($value instanceof Money) {
        return $value->asFloat();
    }

    return (float) $value;
}

function as_string(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    if ($value instanceof Money) {
        return $value->asPrice();
    }

    return (string) $value;
}

function as_payment_method(?string $value): ?PaymentMethod
{
    try {
        return new PaymentMethod($value);
    } catch (UnexpectedValueException) {
        return null;
    }
}

function as_platform(?string $value): ?Platform
{
    try {
        return new Platform($value);
    } catch (UnexpectedValueException) {
        return null;
    }
}

function as_datetime(string|int|DateTime|null $value): ?DateTime
{
    if (!$value) {
        return null;
    }

    /** @var Settings $settings */
    $settings = app()->make(Settings::class);

    if ($value instanceof DateTime) {
        $date = clone $value;
    } elseif (my_is_integer($value)) {
        $date = new DateTime("@$value");
    } else {
        $date = new DateTime($value);
    }

    $date->setTimezone(new DateTimeZone($settings->getTimeZone()));

    return $date;
}

function as_date_string(string|int|DateTime|null $value): ?string
{
    $date = as_datetime($value);
    return $date?->format("Y-m-d");
}

function as_datetime_string(int|string|DateTime|null $value, string $format = ""): ?string
{
    if (!strlen($format)) {
        /** @var Settings $settings */
        $settings = app()->make(Settings::class);
        $format = $settings->getDateFormat();
    }

    $date = as_datetime($value);
    return $date?->format($format);
}

function as_expiration_date_string(int|string|DateTime|null $value): ?string
{
    if ($value === -1 || $value === null) {
        return __("never");
    }

    return as_date_string($value);
}

function as_expiration_datetime_string(int|string|DateTime|null $value): ?string
{
    if ($value === -1 || $value === "-1" || $value === null) {
        return __("never");
    }

    return as_datetime_string($value);
}

function serialize_date(?DateTime $date): ?string
{
    return $date?->format("Y-m-d H:i:s");
}

function price_to_int(string|float|null $value): ?int
{
    if ($value === null || $value === "") {
        return null;
    }

    // We do it that way because of the floating point issues
    return (int) str_replace(".", "", number_format($value, 2));
}

/**
 * @param Permission[] $permissions
 * @return array
 */
function as_permission_list($permissions): array
{
    return collect($permissions)
        ->map(function ($permission) {
            try {
                return new Permission($permission);
            } catch (UnexpectedValueException $e) {
                return null;
            }
        })
        ->filter(fn($permission) => $permission)
        ->all();
}

if (!function_exists("is_iterable")) {
    function is_iterable(mixed $value): bool
    {
        return is_array($value) || $value instanceof Traversable;
    }
}

function is_debug(): bool
{
    $debug = getenv("APP_DEBUG");
    return $debug === "1" || $debug === "true" || $debug === 1;
}

function is_testing(): bool
{
    return getenv("APP_ENV") === "testing";
}

function is_demo(): bool
{
    return get_subdomain() === "demo";
}

function is_saas(): bool
{
    return getenv("APP_ENV") === "saas";
}

function get_subdomain(): ?string
{
    $subdomain = getenv("APP_SUBDOMAIN");
    return $subdomain === false ? null : $subdomain;
}

function get_identifier(): ?string
{
    $identifier = getenv("APP_IDENTIFIER");
    return $identifier === false ? null : $identifier;
}

function has_value(mixed $value): bool
{
    if (is_array($value) || is_object($value)) {
        return !!$value;
    }

    return strlen((string) $value) > 0;
}

function log_info(string $text, array $data = []): void
{
    /** @var FileLogger $logger */
    $logger = app()->make(FileLogger::class);
    $logger->info($text, $data);
}

function map_to_params(mixed $data, bool $read): array
{
    $params = [];
    $values = [];

    foreach (to_array($data) as $key => $value) {
        if ($value === null && $read) {
            $params[] = "$key IS NULL";
        } elseif ($value instanceof Expression && my_is_integer($key)) {
            $params[] = "$value";
        } elseif ($value instanceof Expression) {
            $params[] = "$key = $value";
        } else {
            $params[] = "$key = ?";
            $values[] = $value;
        }
    }

    return [$params, $values];
}

function to_array(mixed $items): array
{
    if ($items instanceof Traversable) {
        return iterator_to_array($items);
    }

    if ($items instanceof Arrayable) {
        return $items->toArray();
    }

    if (is_array($items)) {
        return $items;
    }

    if ($items === null) {
        return [];
    }

    return [$items];
}

function __(string $key, mixed ...$args): string
{
    /** @var TranslationManager $translationManager */
    $translationManager = app()->make(TranslationManager::class);
    return $translationManager->user()->t($key, ...$args);
}

function url(string $path, array $query = []): string
{
    /** @var UrlGenerator $url */
    $url = app()->make(UrlGenerator::class);
    return $url->to($path, $query);
}

function versioned(string $path, array $query = []): string
{
    /** @var UrlGenerator $url */
    $url = app()->make(UrlGenerator::class);
    return $url->versioned($path, $query);
}

if (!function_exists("dd")) {
    function dd(mixed ...$vars): void
    {
        foreach ($vars as $v) {
            VarDumper::dump($v);
        }

        exit(1);
    }
}

/**
 * @link https://stackoverflow.com/a/2040279
 */
function generate_uuid4(): string
{
    return sprintf(
        "%04x%04x-%04x-%04x-%04x-%04x%04x%04x",
        // 32 bits for "time_low"
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),

        // 16 bits for "time_mid"
        mt_rand(0, 0xffff),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand(0, 0x0fff) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand(0, 0x3fff) | 0x8000,

        // 48 bits for "node"
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

function generate_id(int $length): string
{
    return substr(hash("sha256", generate_uuid4()), 0, $length);
}

function to_upper(string $string): string
{
    return mb_convert_case($string, MB_CASE_UPPER, "UTF-8");
}

function merge_recursive(mixed $a, mixed $b): mixed
{
    if (!is_array($a) || !is_array($b)) {
        return $b;
    }

    $output = $a;

    foreach ($b as $key => $value) {
        if (!isset($a[$key])) {
            $output[$key] = $value;
        } elseif (is_int($key)) {
            $output[] = $value;
        } else {
            $output[$key] = merge_recursive($output[$key], $value);
        }
    }

    return $output;
}

function multiply(int|float|null $a, int|float|null $b): int|float|null
{
    if ($a === null || $b === null) {
        return null;
    }

    return $a * $b;
}

function make_charge_wallet_option(
    PaymentMethod $paymentMethod,
    PaymentPlatform $paymentPlatform
): string {
    return $paymentMethod . "," . $paymentPlatform->getId();
}

function explode_int_list(?string $list, string $delimiter = ","): array
{
    if ($list === "" || $list === null) {
        return [];
    }

    return collect(explode($delimiter, $list))
        ->map(fn($value) => (int) $value)
        ->all();
}

function get_authorization_value(Request $request): ?string
{
    $authorization = $request->headers->get("Authorization");
    if (!$authorization) {
        return null;
    }

    if (0 === stripos($authorization, "bearer ")) {
        return substr($authorization, 7);
    }

    return $authorization;
}

function selected(mixed $value): string
{
    return $value ? "selected" : "";
}

function is_subset(array $potentialSubset, array $items): bool
{
    $common = array_intersect($items, $potentialSubset);
    return count($common) === count($potentialSubset);
}
