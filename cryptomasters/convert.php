<?php

   require_once("./cryptomasters/classes.php")


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Converting Page</title>
</head>
<body>
    <h2>Conversion Results</h2>
    <section>


        <?php

        $browserUA = $_SERVER['HTTP_USER_AGENT'];

        if(isset($_GET['amount']) && isset($_GET['crypto'])){
            

                $amount = $_GET['amount'];
                $crypto = $_GET['crypto'];

                $converter = new CryptoConverter($crypto);
                $results = $converter->convert($amount);

                echo"<p>You want to convert: $amount of $crypto</p>";
                echo"<p>Conversion Result: $results</p>";
        }else{
            echo"<p>OOpps, it didn't work. Please provide both amount and crypto type.</p>";
        }

        


        ?>
    </section>
</body>
</html>