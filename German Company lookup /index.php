<?php
session_start();

$query = '';
$nameSubmitted = false; // state variable to disable "search glief" button unless submit name is clicked.

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

        if (isset($_POST['action']) && $_POST['action'] === 'search_gleif') {

            echo '<p>Search GLEIF button clicked.</p>';

            // $parameters = http_build_query([
            //     'filter[entity.legalName]' => $query,
            //     'filter[entity.jurisdiction]' => 'DE',
            //     'page[size]' => 10
            // ]);
            // if (
            //     isset($_POST['action']) &&
            //     $_POST['action'] === 'search_gleif'
            // ) {
            //     $url = 'https://api.gleif.org/api/v1/lei-records?' . $parameters;

            //     echo "<p>Request URL: " . htmlspecialchars($url) . "</p>";

            //     $response = file_get_contents($url);

            //     if ($response === false) {
            //         echo "<h2>API Request Failed</h2>";
            //         var_dump(error_get_last());
            //     } else {
            //         echo "<h2>Raw GLEIF Response</h2>";
            //         echo "<pre>";
            //         echo htmlspecialchars($response, ENT_QUOTES, 'UTF-8');
            //         echo "</pre>";
            //     }
            // }
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