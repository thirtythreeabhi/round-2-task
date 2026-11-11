<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAT number checker</title>
</head>

<body>

<form method="post">
    <label for="vat">VAT number</label>
    <input id="vat" name="vat" type="text" required>
    <button type="submit"> Submit</button>
</form>


<?php

$validCountryCodes = [
    "AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI",
    "FR", "DE", "EL", "HU", "IE", "IT", "LV", "LT", "LU",
    "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE", "XI",
];
$nationalNumberFormats = [
    "AT" => '/^U\d{8}$/',
    "BE" => '/^\d{10}$/',
    "BG" => '/^\d{9,10}$/',
    "HR" => '/^\d{11}$/',
    "CY" => '/^\d{8}[A-Z]$/',
    "CZ" => '/^\d{8,10}$/',
    "DK" => '/^\d{8}$/',
    "EE" => '/^\d{9}$/',
    "FI" => '/^\d{8}$/',
    "FR" => '/^[A-HJ-NP-Z0-9]{2}\d{9}$/',
    "DE" => '/^\d{9}$/',
    "EL" => '/^\d{9}$/',
    "HU" => '/^\d{8}$/',
    "IE" => '/^(?:\d{7}[A-Z]{1,2}|\d[A-Z+*]\d{5}[A-Z])$/',
    "IT" => '/^\d{11}$/',
    "LV" => '/^\d{11}$/',
    "LT" => '/^(?:\d{9}|\d{12})$/',
    "LU" => '/^\d{8}$/',
    "MT" => '/^\d{8}$/',
    "NL" => '/^\d{9}B\d{2}$/',
    "PL" => '/^\d{10}$/',
    "PT" => '/^\d{9}$/',
    "RO" => '/^\d{2,10}$/',
    "SK" => '/^\d{10}$/',
    "SI" => '/^\d{8}$/',
    "ES" => '/^(?:[A-Z]\d{8}|\d{8}[A-Z]|[A-Z]\d{7}[A-Z])$/',
    "SE" => '/^\d{10}01$/',
    "XI" => '/^(?:\d{9}|\d{12})$/',
];

function checkVatWithVies($countryCode, $nationalNumber)
{
    if (!extension_loaded("curl")) {
        return [
            "response" => null,
            "httpStatus" => null,
            "error" => "The PHP cURL extension is not enabled.",
        ];
    }

    $payload = json_encode([
        "countryCode" => $countryCode,
        "vatNumber" => $nationalNumber,
    ]);

    if ($payload === false) {
        return [
            "response" => null,
            "httpStatus" => null,
            "error" => "Could not encode the request as JSON: " . json_last_error_msg(),
        ];
    }

    $url = "https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number";
    $curl = curl_init($url);

    if ($curl === false) {
        return [
            "response" => null,
            "httpStatus" => null,
            "error" => "Could not initialize the cURL request.",
        ];
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $body = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($curl);
        return [
            "response" => null,
            "httpStatus" => $httpStatus,
            "error" => "VIES request failed: " . $error,
        ];
    }

    $response = json_decode($body, true);
    $jsonError = json_last_error();

    if ($httpStatus === 400) {
        return [
            "response" => $jsonError === JSON_ERROR_NONE ? $response : null,
            "httpStatus" => $httpStatus,
            "error" => "VIES rejected the request (HTTP 400).",
        ];
    }

    if ($httpStatus === 500) {
        return [
            "response" => $jsonError === JSON_ERROR_NONE ? $response : null,
            "httpStatus" => $httpStatus,
            "error" => "VIES returned a server error (HTTP 500).",
        ];
    }

    if ($httpStatus !== 200) {
        return [
            "response" => $jsonError === JSON_ERROR_NONE ? $response : null,
            "httpStatus" => $httpStatus,
            "error" => "Unexpected HTTP status from VIES: " . $httpStatus,
        ];
    }

    if ($jsonError !== JSON_ERROR_NONE) {
        return [
            "response" => null,
            "httpStatus" => $httpStatus,
            "error" => "Could not decode the VIES response: " . json_last_error_msg(),
        ];
    }

    if (!is_array($response) || !array_key_exists("valid", $response) || !is_bool($response["valid"])) {
        return [
            "response" => $response,
            "httpStatus" => $httpStatus,
            "error" => "Unexpected VIES response: expected a boolean 'valid' field.",
        ];
    }

    return [
        "response" => $response,
        "httpStatus" => $httpStatus,
        "error" => null,
    ];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rawVat = $_POST["vat"] ?? "";

    if (!is_string($rawVat)) {$rawVat = "";}

    $normalizedVat = preg_replace('/[\s\p{Z}]+/u', '', $rawVat);
    $vat = strtoupper($normalizedVat ?? "");
    $countryCode = substr($vat, 0, 2);
    $nationalNumber = substr($vat, 2);

    if($vat === ""){
        echo "<p>Please enter a valid VAT number. </p>";
    }elseif(!in_array($countryCode, $validCountryCodes, true)){
        echo "<p>Unknown country code</p>";
    }
    elseif($nationalNumber === ""){
        echo "<p> Please enter a county code and a national number, like DE12345.</p>";
    }elseif(!preg_match($nationalNumberFormats[$countryCode], $nationalNumber)){
        echo"<p>National number format is invalid for this country. </p>";
    }
    else{
        echo "<p>Country Code: " . htmlspecialchars($countryCode) . "</p>";
        echo "<p>National Number: " . htmlspecialchars($nationalNumber) . "</p>";
        $viesResult = checkVatWithVies($countryCode, $nationalNumber);

        if ($viesResult["error"] !== null) {
            echo "<p>VIES check could not be completed: "
                . htmlspecialchars($viesResult["error"], ENT_QUOTES, "UTF-8") . "</p>";
        } elseif ($viesResult["response"]["valid"] === true) {
            echo "<p>VAT number is valid (official source)</p>";
        } elseif ($viesResult["response"]["valid"] === false) {
            echo "<p>VAT number is invalid (official source).</p>";
        }
    }
}
?>

</body>


</html>
