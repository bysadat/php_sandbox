<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Converting Page</title>
</head>
<body>
    <h2>Conversion Results</h2>

    <?php

      $amount = $_GET['amount'];
      $crypto = $_GET['crypto'];

      echo"<p>You want to convert: $amount of $crypto</p>";


    ?>
</body>
</html>