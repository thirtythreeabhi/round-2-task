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

    }
}
?>

</body>


</html>