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

</body>

<?php

$validCountryCodes = [
    "AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI",
    "FR", "DE", "EL", "HU", "IE", "IT", "LV", "LT", "LU",
    "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE", "XI",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $vat = strtoupper(str_replace(' ', '', trim($_POST["vat"] ?? "")));
    $countryCode = substr($vat, 0, 2);
    $nationalNumber = substr($vat, 2);

    if($vat === ""){
        echo "<p>Please enter a valid VAT number. </p>";
    }elseif($nationalNumber === ""){
        echo "<p> Please enter a county code and a national number, like DE12345.</p>";
    }elseif(!preg_match('/^\d+$/', $nationalNumber)){
        echo "<p>national number must contain only digits. </p>";
    }else{
        echo "<p>Country Code: " . htmlspecialchars($countryCode) . "</p>";
        echo "<p>National Number: " . htmlspecialchars($nationalNumber) . "</p>";

    }
}
?>



</html>