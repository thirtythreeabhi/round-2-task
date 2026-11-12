<?php
$query = '';

$gleifRecords = [];
$gleifError = null; // to collect the error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $query = trim($_POST['company'] ?? '');
    if ($query === '') {
        $gleifError = 'PLease enter a company name';
    } elseif (strlen($query) > 150) {
        $gleifError = "Company name is too long.";
    } else {
        if (($_POST['action'] ?? '') === 'search_gleif') {

            $parameters = http_build_query([
                'filter[entity.legalName]' => $query,
                'filter[entity.jurisdiction]' => 'DE',
                'page[size]' => 10,
            ]);

            $url = 'https://api.gleif.org/api/v1/lei-records?' . $parameters;
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Accept: application/vnd.api+json\r\n",
                    'timeout' => 15,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $lastError = error_get_last();
                $gleifError = $lastError['message'] ?? 'Unknown request error';
            } else {
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $gleifError = 'Could not patse the GLIEF response';
                } else {
                    $gleifRecords = $decoded['data'] ?? [];
                }
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>German Company Lookup</title>
</head>

<body>

    <h1>German Company Lookup</h1>

    <form method="POST">
        <label for="company">Company name:</label>

        <input
            type="text"
            name="company"
            id="company"
            placeholder="Enter company name"
            value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>"
            maxlength="150"
            required>

        <button type="submit" name="action" value="search_gleif">
            Search GLEIF
        </button>
    </form>

    <?php if ($gleifError !== null): ?>
        <h2>GLEIF request error</h2>
        <p><?= htmlspecialchars($gleifError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (!empty($gleifRecords)): ?>
        <h2>GLEIF results</h2>
        <p>Found <?= count($gleifRecords) ?> matching companies.</p>
        <?php foreach ($gleifRecords as $record):
            $attributes = $record['attributes'] ?? [];
            $entity = $attributes['entity'] ?? [];
            $legalName = $entity['legalName']['name'] ?? 'Not provided';
            $lei = $attributes['lei'] ?? 'Not provided';
            $registrationNumber = $entity['registeredAs'] ?? 'Not provided';
            $registrationAuthority = $entity['registeredAt']['id'] ?? 'Not provided';
            $legalFormId = $entity['legalForm']['id'] ?? 'Not provided';
            $legalFormDescription = $entity['legalForm']['other'] ?? 'Not provided';
            $address = $entity['legalAddress'] ?? [];
            $street = implode(', ', $address['addressLines'] ?? []);
            $city = $address['city'] ?? 'Not provided';
            $postalCode = $address['postalCode'] ?? 'Not provided';
            $country = $address['country'] ?? 'Not provided';
            $entityStatus = $entity['status'] ?? 'Not provided';
            $registrationStatus = $attributes['registration']['status'] ?? 'Not provided';
        ?>
            <section>
                <h3><?= htmlspecialchars($legalName, ENT_QUOTES, 'UTF-8') ?></h3>
                <p><strong>LEI:</strong> <?= htmlspecialchars($lei, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Registration Number:</strong> <?= htmlspecialchars($registrationNumber, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Registration Authority ID:</strong> <?= htmlspecialchars($registrationAuthority, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Legal Form ID:</strong> <?= htmlspecialchars($legalFormId, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Legal Form:</strong> <?= htmlspecialchars($legalFormDescription, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Street:</strong> <?= htmlspecialchars($street, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>City:</strong> <?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Postal Code:</strong> <?= htmlspecialchars($postalCode, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Country:</strong> <?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Entity Status:</strong> <?= htmlspecialchars($entityStatus, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>LEI Registration Status:</strong> <?= htmlspecialchars($registrationStatus, ENT_QUOTES, 'UTF-8') ?></p>
            </section>
            <hr>
        <?php endforeach; ?>
    <?php elseif (($_POST['action'] ?? '') === 'search_gleif'): ?>
        <p>No matching companies found.</p>
    <?php endif; ?>

</body>

</html>