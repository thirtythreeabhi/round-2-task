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
    <button type="submit">Submit</button>
</form>
<?php
$formats = [
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

function checkVatWithVies($country, $number)
{
    if (!extension_loaded("curl")) {
        return ["error" => "PHP cURL extension is not available."];
    }
    $url = "https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number";
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["countryCode" => $country, "vatNumber" => $number]),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Accept: application/json"],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);
    if ($body === false) {
        $error = $curlError === CURLE_OPERATION_TIMEDOUT ? "Network timeout." : "VIES service unavailable.";
        return ["error" => $error, "httpStatus" => $status];
    }

    $data = json_decode($body, true);
    if ($status !== 200 || !is_array($data)) {
        return ["error" => "Unexpected API response from VIES.", "httpStatus" => $status];
    }
    $apiError = strtoupper($data["userError"] ?? $data["errorWrappers"][0]["error"] ?? "");
    if ($apiError === "MS_UNAVAILABLE") {
        return ["error" => "Member-state service unavailable.", "response" => $data, "httpStatus" => $status];
    }
    if ($apiError === "TIMEOUT") {
        return ["error" => "Network timeout.", "response" => $data, "httpStatus" => $status];
    }
    if ($apiError !== "" && $apiError !== "VALID" && $apiError !== "INVALID") {
        return ["error" => "VIES service unavailable.", "response" => $data, "httpStatus" => $status];
    }
    if (!isset($data["valid"]) || !is_bool($data["valid"])) {
        return ["error" => "Unexpected API response from VIES.", "response" => $data, "httpStatus" => $status];
    }
    return ["response" => $data, "httpStatus" => $status];
}

function saveLookupAsJson($lookup)
{
    $directory = __DIR__ . "/data";
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        return false;
    }
    $file = $directory . "/lookup-" . uniqid("", true) . ".json";
    $json = json_encode($lookup, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        return false;
    }
    return file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = is_string($_POST["vat"] ?? null) ? $_POST["vat"] : "";
    $vat = strtoupper(preg_replace('/[\s\p{Z}]+/u', '', $input) ?? "");
    $country = substr($vat, 0, 2);
    $number = substr($vat, 2);
    $lookup = [
        "lookedUpAt" => gmdate(DATE_ATOM),
        "submittedVat" => $vat,
        "countryCode" => $country ?: null,
        "nationalNumber" => $number ?: null,
    ];

    if ($vat === "" || !isset($formats[$country]) || !preg_match($formats[$country], $number)) {
        $lookup["status"] = "invalid_format";
        $lookup["error"] = "Enter a VAT number with a supported country code and format.";
        echo "<p>" . htmlspecialchars($lookup["error"], ENT_QUOTES, "UTF-8") . "</p>";
    } else {
        $result = checkVatWithVies($country, $number);
        $lookup["httpStatus"] = $result["httpStatus"] ?? null;
        $lookup["viesResponse"] = $result["response"] ?? null;
        if (isset($result["error"])) {
            $lookup["status"] = "error";
            $lookup["error"] = $result["error"];
            echo "<p>" . htmlspecialchars($result["error"], ENT_QUOTES, "UTF-8") . "</p>";
        } else {
            $data = $result["response"];
            $lookup["status"] = $data["valid"] ? "valid" : "invalid";
            echo $data["valid"] ? "<p>VAT number is valid (official source).</p>" : "<p>VAT number is invalid (official source).</p>";
            foreach (["Company name" => "name", "Registered address" => "address", "Verification timestamp" => "requestDate", "Consultation reference" => "requestIdentifier"] as $label => $key) {
                $value = $data[$key] ?? "Not provided by member state";
                if (!is_string($value) || trim($value) === "" || trim($value) === "---") $value = "Not provided by member state";
                echo "<p>" . $label . ": " . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</p>";
            }
        }
    }
    if (!saveLookupAsJson($lookup)) {
        echo "<p>Warning: lookup could not be saved.</p>";
    }
}
?>
</body>
</html>
