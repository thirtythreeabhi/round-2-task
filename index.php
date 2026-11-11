<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAT number checker</title>
</head>

<form method="post">
    <label for="vat">VAT number</label>
    <input id="vat" name="vat" type="text" required>
    <button type="submit"> Submit</button>
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $vat = trim($_POST["vat"] ?? "");
    echo "<p>You entered: " . htmlspecialchars($vat, ENT_QUOTES, "UTF-8") . "</p>";
}
?>

<body>

</body>

</html>