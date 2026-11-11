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

function makeViesResult($response = null, $httpStatus = null, $error = null)
{
    return [
        "response" => $response,
        "httpStatus" => $httpStatus,
        "error" => $error,
    ];
}

function getViesAvailability($countryCode)
{
    $curl = curl_init("https://ec.europa.eu/taxation_customs/vies/rest-api/check-status");

    if ($curl === false) {
        return null;
    }

    curl_setopt_array($curl, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ["Accept: application/json"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $body = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($body === false || $httpStatus !== 200) {
        return null;
    }

    $statusResponse = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($statusResponse)) {
        return null;
    }

    $availability = [
        "serviceAvailable" => $statusResponse["vow"]["available"] ?? null,
        "countryAvailability" => null,
    ];

    $countries = $statusResponse["countries"] ?? null;

    if (!is_array($countries)) {
        return $availability;
    }

    foreach ($countries as $countryStatus) {
        if (
            is_array($countryStatus)
            && ($countryStatus["countryCode"] ?? null) === $countryCode
            && is_string($countryStatus["availability"] ?? null)
        ) {
            $availability["countryAvailability"] = $countryStatus["availability"];
            break;
        }
    }

    return $availability;
}

function makeViesHttp500Result($countryCode, $response)
{
    $availability = getViesAvailability($countryCode);

    if (($availability["countryAvailability"] ?? null) === "Unavailable") {
        $error = "VIES is temporarily unavailable for country code " . $countryCode . ". Please try again later.";
    } elseif (($availability["serviceAvailable"] ?? null) === false) {
        $error = "VIES is currently unavailable. Please try again later.";
    } elseif (($availability["countryAvailability"] ?? null) === "Monitoring Disabled") {
        $error = "VIES monitoring is disabled for country code " . $countryCode . "; availability cannot be confirmed. The VIES request returned HTTP 500.";
    } else {
        $error = "VIES returned a server error (HTTP 500).";
    }

    return makeViesResult($response, 500, $error);
}

function getViesApiErrorCode($response)
{
    if (!is_array($response)) {
        return null;
    }

    if (is_array($response["errorWrappers"] ?? null)) {
        foreach ($response["errorWrappers"] as $errorWrapper) {
            if (is_array($errorWrapper) && is_string($errorWrapper["error"] ?? null)) {
                return strtoupper($errorWrapper["error"]);
            }
        }
    }

    $userError = $response["userError"] ?? null;
    if (is_string($userError) && !in_array(strtoupper($userError), ["VALID", "INVALID"], true)) {
        return strtoupper($userError);
    }

    return null;
}

function checkVatWithVies($countryCode, $nationalNumber)
{
    if (!extension_loaded("curl")) {
        return makeViesResult(null, null, "The PHP cURL extension is not enabled.");
    }

    $payload = json_encode([
        "countryCode" => $countryCode,
        "vatNumber" => $nationalNumber,
    ]);

    if ($payload === false) {
        return makeViesResult(null, null, "Could not encode the request as JSON: " . json_last_error_msg());
    }

    $url = "https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number";
    $curl = curl_init($url);

    if ($curl === false) {
        return makeViesResult(null, null, "Could not initialize the cURL request.");
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
        return makeViesResult(null, $httpStatus, "VIES request failed: " . $error);
    }

    $response = json_decode($body, true);
    $jsonError = json_last_error();

    if ($jsonError === JSON_ERROR_NONE) {
        $apiErrorCode = getViesApiErrorCode($response);

        if ($apiErrorCode === "MS_UNAVAILABLE") {
            return makeViesResult($response, $httpStatus, "VIES reports the member state's service is unavailable. Please try again later.");
        }

        if (in_array($apiErrorCode, ["SERVICE_UNAVAILABLE", "TIMEOUT"], true)) {
            return makeViesResult($response, $httpStatus, "VIES is currently unavailable. Please try again later.");
        }

        if (in_array($apiErrorCode, [
            "MS_MAX_CONCURRENT_REQ",
            "GLOBAL_MAX_CONCURRENT_REQ",
            "MS_MAX_CONCURRENT_REQ_TIME",
            "GLOBAL_MAX_CONCURRENT_REQ_TIME",
        ], true)) {
            return makeViesResult($response, $httpStatus, "VIES is busy handling requests. Please try again later.");
        }

        if ($apiErrorCode !== null) {
            return makeViesResult($response, $httpStatus, "VIES returned an API error: " . $apiErrorCode);
        }
    }

    if ($httpStatus !== 200) {
        if ($httpStatus === 500) {
            return makeViesHttp500Result(
                $countryCode,
                $jsonError === JSON_ERROR_NONE ? $response : null
            );
        }

        if ($httpStatus === 400) {
            $error = "VIES rejected the request (HTTP 400).";
        } else {
            $error = "Unexpected HTTP status from VIES: " . $httpStatus;
        }

        return makeViesResult(
            $jsonError === JSON_ERROR_NONE ? $response : null,
            $httpStatus,
            $error
        );
    }

    if ($jsonError !== JSON_ERROR_NONE) {
        return makeViesResult(null, $httpStatus, "Could not decode the VIES response: " . json_last_error_msg());
    }

    if (!is_array($response) || !array_key_exists("valid", $response) || !is_bool($response["valid"])) {
        return makeViesResult($response, $httpStatus, "Unexpected VIES response: expected a boolean 'valid' field.");
    }

    return makeViesResult($response, $httpStatus);
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
    }elseif(!isset($nationalNumberFormats[$countryCode])){
        echo "<p>Unknown country code</p>";
    }
    elseif($nationalNumber === ""){
        echo "<p> Please enter a county code and a national number, like DE12345.</p>";
    }elseif(!preg_match($nationalNumberFormats[$countryCode], $nationalNumber)){
        echo"<p>National number format is invalid for this country. </p>";
    }
    else{
        $viesResult = checkVatWithVies($countryCode, $nationalNumber);

        if ($viesResult["error"] !== null) {
            echo "<p>VIES check could not be completed: "
                . htmlspecialchars($viesResult["error"], ENT_QUOTES, "UTF-8") . "</p>";
        } else {
            $viesResponse = $viesResult["response"];

            if ($viesResponse["valid"] === true) {
                echo "<p>VAT number is valid (official source)</p>";
            } else {
                echo "<p>VAT number is invalid (official source).</p>";
            }

            $fieldsToDisplay = [
                "Company name" => $viesResponse["name"] ?? null,
                "Registered address" => $viesResponse["address"] ?? null,
                "Verification timestamp" => $viesResponse["requestDate"] ?? null,
                "Consultation reference" => $viesResponse["requestIdentifier"] ?? null,
            ];

            foreach ($fieldsToDisplay as $label => $value) {
                if (!is_string($value) || trim($value) === "" || trim($value) === "---") {
                    $displayValue = "Not provided by member state";
                } else {
                    $displayValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
                }

                echo "<p>" . $label . ": " . $displayValue . "</p>";
            }
        }
    }
}
?>

</body>
</html>
