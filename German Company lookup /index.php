<?php

$query = '';
$parameters = http_build_query([
    'filter[entity.legalName]' => $query,
    'filter[entity.jurisdiction]' => 'DE',
    'page[size]' => 10
]);

$url = 'https://api.gleif.org/api/v1/lei-records?' . $parameters;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $query = trim($_POST['company'] ?? '');
    if (strlen($query) > 150) {
        $error = "Company name is too long.";
        echo "$error";
    } else {
        echo "You searched for: " . htmlspecialchars($query) . PHP_EOL;

        if (isset($_POST['action']) && $_POST['action'] === 'search_gleif') {

            $parameters = http_build_query([
                'filter[entity.legalName]' => $query,
                'filter[entity.jurisdiction]' => 'DE',
                'page[size]' => 10
            ]);

            $url = 'https://api.gleif.org/api/v1/lei-records?' . $parameters;

            echo "<p>GLEIF URL: " . htmlspecialchars($url) . "</p>";
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
            required>

        <button type="submit" name="action" value="search">Submit name</button>
        <button type="submit" name="action" value="search_glief">Search GLIEF</button>


    </form>


</body>

</html>