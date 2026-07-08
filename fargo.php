<?php
/**
 * FargoRate Proxy API
 * File: fargo.php
 *
 * Deploy this single file to your GoDaddy public_html folder.
 * It acts as a proxy between your Swift app and FargoRate's internal API.
 *
 * Usage:
 *   GET https://www.railshottv.com/fargo.php?name=Shane+Van+Boening
 *   GET https://www.railshottv.com/fargo.php?name=Earl+Strickland&limit=5
 */

// ---------------------------------------------------------------------------
// CORS Headers — allow your Swift app to call this from any origin
// ---------------------------------------------------------------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json; charset=UTF-8");

// ---------------------------------------------------------------------------
// Input validation
// ---------------------------------------------------------------------------
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

if (strlen($name) < 2) {
    http_response_code(400);
    echo json_encode([
        "error" => "The 'name' parameter is required and must be at least 2 characters."
    ]);
    exit;
}

// Clamp limit between 1 and 50
$limit = max(1, min(50, $limit));

// ---------------------------------------------------------------------------
// Call the FargoRate internal API
// ---------------------------------------------------------------------------
$encodedName = urlencode($name);
$fargoUrl = "https://dashboard.fargorate.com/api/indexsearch?q=" . $encodedName;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $fargoUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => [
        "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1",
        "Accept: application/json, text/plain, */*",
        "Referer: https://fairmatch.fargorate.com/",
        "Origin: https://fairmatch.fargorate.com"
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle network / cURL errors
if ($response === false || !empty($curlError)) {
    http_response_code(502);
    echo json_encode(["error" => "Could not reach FargoRate. Please try again."]);
    exit;
}

// Handle non-200 responses from FargoRate
if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(["error" => "FargoRate returned an error (HTTP $httpCode)."]);
    exit;
}

// Decode the JSON from FargoRate
$data = json_decode($response, true);
if (!isset($data['value']) || !is_array($data['value'])) {
    http_response_code(502);
    echo json_encode(["error" => "Unexpected response format from FargoRate."]);
    exit;
}

// ---------------------------------------------------------------------------
// Parse and clean each player record
// ---------------------------------------------------------------------------
function parsePlayer($raw) {
    $rating      = intval($raw['rating'] ?? 0);
    $robustness  = intval($raw['robustness'] ?? 0);
    $provisional = intval($raw['provisionalRating'] ?? 0);
    $effective   = intval($raw['effectiveRating'] ?? 0);

    $firstName = ucwords(strtolower(trim($raw['firstName'] ?? '')));
    $lastName  = ucwords(strtolower(trim($raw['lastName'] ?? '')));

    return [
        "id"               => $raw['id'] ?? '',
        "readableId"       => $raw['readableId'] ?? '',
        "membershipId"     => $raw['membershipId'] ?? '',
        "firstName"        => $firstName,
        "lastName"         => $lastName,
        "fullName"         => trim("$firstName $lastName"),
        "location"         => trim($raw['location'] ?? ''),
        "fargoRating"      => $effective,   // Use this in your app UI
        "officialRating"   => $rating,
        "provisionalRating"=> $provisional,
        "robustness"       => $robustness,
        "isProvisional"    => $robustness < 200,
    ];
}

$players = array_map('parsePlayer', $data['value']);

// Sort by fargoRating descending (highest rated player first)
usort($players, function($a, $b) {
    return $b['fargoRating'] - $a['fargoRating'];
});

// Apply limit
$players = array_slice($players, 0, $limit);

// ---------------------------------------------------------------------------
// Return the final JSON response
// ---------------------------------------------------------------------------
echo json_encode([
    "query"        => $name,
    "totalResults" => count($players),
    "players"      => $players,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
