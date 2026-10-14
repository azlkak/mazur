<?php
declare(strict_types=1);

/** Make one authenticated EstiCRM request. Do not put credentials in exceptions. */
function mazurEstiApi(array $config, string $path, array $parameters = [], bool $post = false): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('EstiCRM transport unavailable');
    $parameters = array_merge([
        'company' => (string)$config['esticrm_company'],
        'token' => (string)$config['esticrm_token'],
    ], $parameters);
    $url = 'https://app.esticrm.pl/apiClient/' . $path;
    if (!$post) $url .= '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    $curl = curl_init($url);
    if ($curl === false) throw new RuntimeException('EstiCRM transport unavailable');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    ]);
    if ($post) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_errno($curl);
    curl_close($curl);
    if ($error !== 0 || $status < 200 || $status >= 300 || !is_string($body)) {
        throw new RuntimeException('EstiCRM ' . $path . ' transport failed (HTTP ' . $status . ', cURL ' . $error . ')');
    }
    try {
        $response = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('EstiCRM ' . $path . ' returned invalid JSON', 0, $error);
    }
    if (!is_array($response)) throw new RuntimeException('EstiCRM ' . $path . ' returned invalid data');
    return $response;
}

/** Resolve the offer caretaker without using the enquiring person's identity. */
function mazurEstiCaretakerId(array $config): int
{
    $configured = (int)($config['esticrm_offer_user_id'] ?? 0);
    if ($configured > 0) return $configured;
    $agents = mazurEstiApi($config, 'user/exported-list');
    if (($agents['success'] ?? false) !== true || !is_array($agents['data'] ?? null)) {
        throw new RuntimeException('EstiCRM agent list unavailable');
    }
    $wanted = mb_strtolower((string)$config['esticrm_agent_email']);
    foreach ($agents['data'] as $agent) {
        if (!is_array($agent)) continue;
        if (mb_strtolower((string)($agent['email'] ?? '')) === $wanted && (int)($agent['id'] ?? 0) > 0) {
            return (int)$agent['id'];
        }
    }
    throw new RuntimeException('EstiCRM caretaker not found');
}

/** The external listing is public data; no enquiry-person fields belong here. */
function mazurEstiOfferPayload(array $fields, string $source, string $sourceId, int $caretakerId): array
{
    $publicId = $source === 'vixcrm' ? 'vix-' . $sourceId : $sourceId;
    $url = 'https://mazurestate.pl/oferta/?id=' . rawurlencode($publicId);
    $externalNumber = trim((string)(($fields['numberExport'] ?? '') ?: ($fields['number'] ?? '') ?: $publicId));
    $city = trim((string)(($fields['locationExportCityName'] ?? '') ?: ($fields['locationCityName'] ?? '')));
    $district = trim((string)(($fields['locationExportPrecinctName'] ?? '') ?: ($fields['locationPrecinctName'] ?? '')));
    $title = trim((string)($fields['portalTitle'] ?? ''));
    $description = trim((string)(($fields['descriptionWebsite'] ?? '') ?: ($fields['description'] ?? '')));
    $note = "Oferta ze strony MazurEstate; źródło: {$source}; ID: {$sourceId}; numer źródłowy: {$externalNumber}; adres strony: {$url}";
    if ($city !== '') $note .= '; miejscowość: ' . $city;
    if ($district !== '') $note .= '; dzielnica: ' . $district;
    $payload = [
        'user_id' => $caretakerId,
        'marketing_agreement' => 0,
        'estate' => (int)($fields['mainTypeId'] ?? 0),
        'transaction' => (int)($fields['transaction'] ?? 0),
        'price' => (float)($fields['price'] ?? 0),
        'area_total' => (float)($fields['areaTotal'] ?? 0),
        'description' => mb_substr(trim($title . "\n\n" . $description), 0, 15000),
        'notice' => mb_substr($note, 0, 2000),
    ];
    $currency = (int)($fields['priceCurrency'] ?? 0);
    if (in_array($currency, [1, 2, 3], true)) $payload['price_currency'] = $currency;
    $countryId = (int)($fields['locationCountryId'] ?? 0);
    if ($countryId > 0) $payload['location_country_id'] = $countryId;
    // VixCRM's current import has a city but no country; never assert Poland
    // for an offer whose source data does not establish its country.
    $optional = [
        'market' => 'market',
        'areaUsable' => 'area_usable',
        'apartmentRoomNumber' => 'apartment_room_number',
        'apartmentFloor' => 'apartment_floor',
        'buildingFloornumber' => 'building_floornumber',
        'buildingYear' => 'building_year',
        'locationToken' => 'location_token',
    ];
    foreach ($optional as $from => $to) {
        $value = $fields[$from] ?? null;
        if ($value !== null && $value !== '') $payload[$to] = $value;
    }
    return $payload;
}

function mazurEstiOfferNumber(array $config, int $id): string
{
    $details = mazurEstiApi($config, 'offer/details', ['id' => $id]);
    if (($details['success'] ?? false) !== true || !is_array($details['data'] ?? null)) return '';
    return trim((string)(($details['data']['numberExport'] ?? '') ?: ($details['data']['number'] ?? '')));
}

/**
 * A named database lock serializes requests for one listing. The durable
 * pending/review row prevents duplicate creation after an ambiguous timeout.
 * Returns an EstiCRM number only when its link is known.
 */
function mazurEstiLinkExternalOffer(PDO $db, array $config, string $source, string $sourceId, array $fields): string
{
    if (!in_array($source, ['mls', 'vixcrm'], true)) return '';
    if (($config['esticrm_offer_creation_enabled'] ?? false) !== true) return '';
    $lockName = 'mazur_esti_' . substr(hash('sha256', $source . ':' . $sourceId), 0, 48);
    $lock = $db->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('EstiCRM offer link busy');
    try {
        $get = $db->prepare('SELECT state, esticrm_id, esticrm_number FROM esticrm_offer_links WHERE source = ? AND source_id = ? LIMIT 1');
        $get->execute([$source, $sourceId]);
        $linked = $get->fetch(PDO::FETCH_ASSOC);
        if ($linked) {
            if ($linked['state'] !== 'linked') return '';
            $number = trim((string)$linked['esticrm_number']);
            if ($number !== '') return $number;
            $id = (int)$linked['esticrm_id'];
            if ($id < 1) return '';
            $number = mazurEstiOfferNumber($config, $id);
            if ($number !== '') {
                $save = $db->prepare('UPDATE esticrm_offer_links SET esticrm_number = ? WHERE source = ? AND source_id = ?');
                $save->execute([$number, $source, $sourceId]);
            }
            return $number;
        }

        // Preflight the known MLS number; Vix's short IDs (e.g. O-1) are not
        // globally unique and must never be used to link an unrelated offer.
        if ($source === 'mls') {
            $number = trim((string)(($fields['numberExport'] ?? '') ?: ($fields['number'] ?? '')));
            if ($number !== '') {
                $list = mazurEstiApi($config, 'offer/basic-list');
                if (($list['success'] ?? false) !== true || !is_array($list['data'] ?? null)) {
                    throw new RuntimeException('EstiCRM offer preflight unavailable');
                }
                $matches = array_values(array_filter($list['data'], static fn($item): bool => is_array($item)
                    && (($item['number'] ?? null) === $number || ($item['number_export'] ?? null) === $number)
                    && (int)($item['id'] ?? 0) > 0));
                if (count($matches) > 1) throw new RuntimeException('EstiCRM offer number ambiguous');
                if (count($matches) === 1) {
                    $id = (int)$matches[0]['id'];
                    $insert = $db->prepare("INSERT INTO esticrm_offer_links (source,source_id,state,esticrm_id,esticrm_number) VALUES (?,?,'linked',?,?)");
                    $insert->execute([$source, $sourceId, $id, $number]);
                    return $number;
                }
            }
        }

        $caretakerId = mazurEstiCaretakerId($config);
        $payload = mazurEstiOfferPayload($fields, $source, $sourceId, $caretakerId);
        if (!in_array($payload['estate'], [1, 2, 3, 4, 7, 8, 9], true)
            || !in_array($payload['transaction'], [131, 132], true)) {
            throw new RuntimeException('EstiCRM property fields incomplete');
        }
        $insert = $db->prepare("INSERT INTO esticrm_offer_links (source,source_id,state) VALUES (?,?,'pending')");
        $insert->execute([$source, $sourceId]);
        try {
            $created = mazurEstiApi($config, 'offer/store', $payload, true);
            $id = $created['result'] ?? null;
            if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int)$id < 1) {
                throw new RuntimeException('EstiCRM offer creation rejected');
            }
            $id = (int)$id;
            // Save the ID before fetching details: a details timeout must not
            // turn a successful creation into another creation attempt.
            $save = $db->prepare("UPDATE esticrm_offer_links SET state='linked', esticrm_id=? WHERE source=? AND source_id=?");
            $save->execute([$id, $source, $sourceId]);
            try {
                $number = mazurEstiOfferNumber($config, $id);
                if ($number !== '') {
                    $save = $db->prepare('UPDATE esticrm_offer_links SET esticrm_number=? WHERE source=? AND source_id=?');
                    $save->execute([$number, $source, $sourceId]);
                }
                return $number;
            } catch (Throwable $error) {
                error_log('Mazur EstiCRM offer number lookup: ' . $error->getMessage());
                return '';
            }
        } catch (Throwable $error) {
            $review = $db->prepare("UPDATE esticrm_offer_links SET state='review', last_error=? WHERE source=? AND source_id=? AND state='pending'");
            $review->execute([mb_substr($error->getMessage(), 0, 120), $source, $sourceId]);
            throw $error;
        }
    } finally {
        $unlock = $db->prepare('SELECT RELEASE_LOCK(?)');
        $unlock->execute([$lockName]);
    }
}
