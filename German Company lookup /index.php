<?php
session_start();

$query = '';
$nameSubmitted = false; // state variable to disable "search glief" button unless submit name is clicked.

$apiResponse = null;
$apiError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $query = trim($_POST['company'] ?? '');
    if (strlen($query) > 150) {
        $error = "Company name is too long.";
        echo "$error";
    } else {
        echo "You searched for: " . htmlspecialchars($query) . PHP_EOL;
        if ($_POST['action'] === 'search') {
            $_SESSION['submitted_company'] = $query;
            $nameSubmitted = true;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'search_glief') {

            echo '<p>Search GLEIF button clicked.</p>';

            $parameters = http_build_query([
                'filter[entity.legalName]' => $query,
                'filter[entity.jurisdiction]' => 'DE',
                'page[size]' => 10,
            ]);

            $url = 'https://api.gleif.org/api/v1/lei-records?' . $parameters;
            $context = stream_context_create([
                'http' => [
                    'header' => "Accept: application/vnd.api+json\r\n",
                    'timeout' => 15,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $lastError = error_get_last();
                $apiError = $lastError['message'] ?? 'Unknown request error.';
            } else {
                $apiResponse = $response;
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
            required>

        <button type="submit" name="action" value="search">Submit name</button>
        <button type="submit" name="action" value="search_glief"
            id="gliefButton"
            <?= !$nameSubmitted ? 'disabled' : '' ?>>Search GLIEF</button>

        <?php if ($apiError !== null): ?>
            <h2>GLEIF API request failed</h2>
            <pre><?= htmlspecialchars($apiError, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php elseif ($apiResponse !== null): ?>
            <h2>GLEIF API response</h2>
            <pre><?= htmlspecialchars($apiResponse, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php endif; ?>

    </form>

    <script>
        const companyInput = document.getElementById("company");
        const gliefButton = document.getElementById("gliefButton");

        companyInput.addEventListener('input', function() {
            gliefButton.disabled = true;
        })
    </script>

</body>

</html>