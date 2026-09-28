<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = ['https://mazurestate.pl', 'https://www.mazurestate.pl', 'https://azlkak.github.io'];
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Vary: Origin');
}

function reply(int $status, string $code): never
{
    http_response_code($status);
    echo json_encode(['code' => $code], JSON_THROW_ON_ERROR);
    exit;
}

function inputText(array $input, string $key, int $maxLength): string
{
    $value = trim((string)($input[$key] ?? ''));
    if (mb_strlen($value) > $maxLength) reply(422, 'invalid_input');
    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(in_array($origin, $allowedOrigins, true) ? 204 : 403);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405, 'method_not_allowed');
if (!in_array($origin, $allowedOrigins, true)) reply(403, 'origin_not_allowed');
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'MazurEstateContact') reply(403, 'request_not_allowed');

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 1 || $contentLength > 20_000) reply(413, 'invalid_size');

try {
    try {
        $input = json_decode((string)file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        reply(400, 'invalid_json');
    }
    if (!is_array($input)) reply(400, 'invalid_json');

    // Quietly accept bot submissions caught by the honeypot.
    if (trim((string)($input['website'] ?? '')) !== '') reply(200, 'accepted');

    $startedAt = (int)($input['started_at'] ?? 0);
    $nowMs = (int)round(microtime(true) * 1000);
    if ($startedAt < 1 || $nowMs - $startedAt < 2500 || $nowMs - $startedAt > 86_400_000) {
        reply(422, 'invalid_timing');
    }

    // Limit automated retries per address. The files contain timestamps only,
    // never enquiry data, and live outside the public web directory.
    $privateRoot = dirname(__DIR__, 2) . '/mls';
    $rateDirectory = $privateRoot . '/runtime/enquiry-rate';
    if (!is_dir($rateDirectory) && !mkdir($rateDirectory, 0700, true) && !is_dir($rateDirectory)) {
        throw new RuntimeException('Cannot create enquiry rate directory');
    }
    $clientAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateFile = $rateDirectory . '/' . hash('sha256', $clientAddress) . '.json';
    $rateHandle = fopen($rateFile, 'c+');
    if ($rateHandle === false || !flock($rateHandle, LOCK_EX)) throw new RuntimeException('Cannot lock enquiry rate file');
    $rateContents = stream_get_contents($rateHandle);
    $attempts = is_string($rateContents) && $rateContents !== '' ? json_decode($rateContents, true) : [];
    if (!is_array($attempts)) $attempts = [];
    $windowStart = time() - 900;
    $attempts = array_values(array_filter($attempts, static fn($timestamp): bool => is_int($timestamp) && $timestamp >= $windowStart));
    if (count($attempts) >= 8) {
        flock($rateHandle, LOCK_UN);
        fclose($rateHandle);
        header('Retry-After: 900');
        reply(429, 'rate_limited');
    }
    $attempts[] = time();
    rewind($rateHandle);
    ftruncate($rateHandle, 0);
    fwrite($rateHandle, json_encode($attempts, JSON_THROW_ON_ERROR));
    fflush($rateHandle);
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);

    $firstName = inputText($input, 'first_name', 80);
    $lastName = inputText($input, 'last_name', 100);
    $phone = inputText($input, 'phone', 32);
    $email = inputText($input, 'email', 190);
    $message = inputText($input, 'message', 3000);
    $offerId = inputText($input, 'offer_id', 40);
    $submittedOfferNumber = inputText($input, 'offer_number', 80);
    $submittedOfferNumber = preg_replace('/\s+/u', ' ', $submittedOfferNumber) ?? '';

    if ($firstName === '' || $lastName === '' || mb_strlen($message) < 10 || ($input['consent'] ?? false) !== true) {
        reply(422, 'missing_required');
    }
    if ($phone === '' && $email === '') reply(422, 'missing_contact');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) reply(422, 'invalid_email');
    if ($phone !== '' && !preg_match('/\A[0-9+() .-]{6,32}\z/u', $phone)) reply(422, 'invalid_phone');
    if ($offerId !== '' && !preg_match('/\A[0-9]{1,40}\z/', $offerId)) reply(422, 'invalid_offer');

    $config = require $privateRoot . '/config.php';
    $company = trim((string)($config['esticrm_company'] ?? ''));
    $token = trim((string)($config['esticrm_token'] ?? ''));
    $agentEmail = trim((string)($config['esticrm_agent_email'] ?? ''));
    if ($company === '' || $token === '' || filter_var($agentEmail, FILTER_VALIDATE_EMAIL) === false) {
        reply(503, 'service_unconfigured');
    }

    $contentLines = [$message];
    $request = [
        'company' => $company,
        'token' => $token,
        'agent_email' => $agentEmail,
        'firstname' => $firstName,
        'lastname' => $lastName,
    ];
    if ($email !== '') $request['email'] = $email;
    if ($phone !== '') $request['phone'] = $phone;

    if ($offerId !== '') {
        $db = new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $statement = $db->prepare('SELECT fields_json FROM mls_offers WHERE source_id = ? AND publishable = 1 LIMIT 1');
        $statement->execute([$offerId]);
        $row = $statement->fetch();
        if ($row) {
            $fields = json_decode((string)$row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
            $offerNumber = trim((string)(($fields['numberExport'] ?? '') ?: ($fields['number'] ?? '') ?: $offerId));
            $transaction = (string)($fields['transaction'] ?? '');
            $market = (int)($fields['market'] ?? 0);
            $typeId = (int)($fields['mainTypeId'] ?? 0);

            $request['offer_number'] = $offerNumber;
            $contentLines[] = '';
            $contentLines[] = 'Numer oferty: ' . $offerNumber;
            if ($transaction === '131') $request['transaction'] = 133;
            if ($transaction === '132') $request['transaction'] = 134;
            if (in_array($market, [10, 11], true)) $request['market'] = $market;
            if (in_array($typeId, [1, 2, 3, 4], true)) $request['type_id'] = $typeId;
        } else {
            $offerNumber = $submittedOfferNumber !== '' ? $submittedOfferNumber : $offerId;
            $contentLines[] = '';
            $contentLines[] = 'Numer oferty: ' . $offerNumber;
        }
    }

    if ($offerId === '' && $submittedOfferNumber !== '') {
        $contentLines[] = '';
        $contentLines[] = 'Numer oferty: ' . $submittedOfferNumber;
    }
    $request['content'] = implode("\n", $contentLines);

    if (!function_exists('curl_init')) reply(503, 'service_unavailable');
    $curl = curl_init('https://app.esticrm.pl/apiClient/question/store');
    if ($curl === false) reply(503, 'service_unavailable');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($request, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $responseBody = curl_exec($curl);
    $responseStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if ($curlError !== 0 || $responseStatus < 200 || $responseStatus >= 300 || !is_string($responseBody)) {
        reply(502, 'crm_unavailable');
    }
    try {
        $response = json_decode($responseBody, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        reply(502, 'crm_invalid_response');
    }
    $crmResult = is_array($response) ? ($response['result'] ?? null) : null;
    $crmAccepted = $crmResult === true
        || (is_int($crmResult) && $crmResult > 0)
        || (is_string($crmResult) && ctype_digit($crmResult) && (int)$crmResult > 0);
    if (!$crmAccepted) {
        reply(502, 'crm_rejected');
    }

    reply(201, 'accepted');
} catch (Throwable $error) {
    error_log('Mazur contact gateway: ' . $error->getMessage());
    reply(500, 'server_error');
}
