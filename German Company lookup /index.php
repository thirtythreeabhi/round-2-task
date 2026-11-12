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
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Accept: application/vnd.api+json\r\n",
                    'timeout' => 15,
                ],
            ]);

            // Generate a small set of equivalent queries, trying the typed spelling first.
            $searchQueries = [$query];
            $pairs = [['ä', 'ae'], ['ö', 'oe'], ['ü', 'ue'], ['ß', 'ss']];
            for ($mask = 1; $mask < (1 << count($pairs)); $mask++) {
                $variant = $query;
                foreach ($pairs as $index => [$umlaut, $digraph]) {
                    if (($mask & (1 << $index)) !== 0) {
                        $variant = str_replace($umlaut, $digraph, $variant);
                    } else {
                        $variant = str_replace($digraph, $umlaut, $variant);
                    }
                }
                if (!in_array($variant, $searchQueries, true)) {
                    $searchQueries[] = $variant;
                }
            }

            $recordsByLei = [];
            foreach ($searchQueries as $searchQuery) {
                $parameters = http_build_query([
                    'filter[entity.legalName]' => $searchQuery,
                    'filter[entity.jurisdiction]' => 'DE',
                    'page[size]' => 10,
                ]);
                $url = 'https://api.gleif.org/api/v1/lei-records?' . $parameters;
                $response = @file_get_contents($url, false, $context);

                if ($response === false) {
                    if (!$recordsByLei && $gleifError === null) {
                        $lastError = error_get_last();
                        $gleifError = $lastError['message'] ?? 'Unknown request error';
                    }
                    continue;
                }

                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if (!$recordsByLei && $gleifError === null) {
                        $gleifError = 'Could not parse the GLEIF response.';
                    }
                    continue;
                }

                foreach ($decoded['data'] ?? [] as $record) {
                    $lei = $record['attributes']['lei'] ?? $record['id'] ?? null;
                    if ($lei !== null && !isset($recordsByLei[$lei])) {
                        $recordsByLei[$lei] = $record;
                        if (count($recordsByLei) >= 10) {
                            break 2;
                        }
                    }
                }
            }
            $gleifRecords = array_values($recordsByLei);
            if ($gleifRecords) {
                $gleifError = null;
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
            $notAvailable = 'Not available from GLEIF';
            $attributes = $record['attributes'] ?? [];
            $entity = $attributes['entity'] ?? [];
            $legalName = $entity['legalName']['name'] ?? $notAvailable;
            $lei = $attributes['lei'] ?? $notAvailable;
            $registrationNumber = $entity['registeredAs'] ?? $notAvailable;
            $registrationAuthorityCode = $entity['registeredAt']['id'] ?? $notAvailable;
            $registrationAuthorityOther = $entity['registeredAt']['other'] ?? $notAvailable;
            $legalFormId = $entity['legalForm']['id'] ?? $notAvailable;
            $legalFormOther = $entity['legalForm']['other'] ?? $notAvailable;
            $address = $entity['legalAddress'] ?? [];
            $addressParts = array_merge(
                $address['addressLines'] ?? [],
                array_filter([
                    $address['addressNumber'] ?? null,
                    $address['addressNumberWithinBuilding'] ?? null,
                    $address['mailRouting'] ?? null,
                    $address['city'] ?? null,
                    $address['region'] ?? null,
                    $address['postalCode'] ?? null,
                    $address['country'] ?? null,
                ], static fn($part) => $part !== null && $part !== '')
            );
            $fullAddress = $addressParts ? implode(', ', $addressParts) : $notAvailable;
            $entityStatus = $entity['status'] ?? $notAvailable;
            $registrationStatus = $attributes['registration']['status'] ?? $notAvailable;
        ?>
            <section>
                <h3><?= htmlspecialchars($legalName, ENT_QUOTES, 'UTF-8') ?></h3>
                <p><strong>LEI:</strong> <?= htmlspecialchars($lei, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Registration Number:</strong> <?= htmlspecialchars($registrationNumber, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Registration Authority Code:</strong> <?= htmlspecialchars($registrationAuthorityCode, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Registration Authority (other):</strong> <?= htmlspecialchars($registrationAuthorityOther, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Legal Form Code:</strong> <?= htmlspecialchars($legalFormId, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Legal Form (other):</strong> <?= htmlspecialchars($legalFormOther, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Full Legal Address:</strong> <?= htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8') ?></p>
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
