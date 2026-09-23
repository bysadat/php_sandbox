<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Masters</title>
</head>
<body>
    <h1>Cryto Master Club!</h1>
    <form action="convert.php" method="post">
        <label for="amount">Enter Amount:</label>
        <input id="amount" type="number" name="amount" required>
        <label for="crypto">Cryto Currency</label>
        <select id="crypto" name="crypto" required>
            <option value="BTC">Bitcoin</option>
            <option value="ETH">Ethereum</option>
            <option value="LTC">Litecoin</option>
        </select>
        <button type="submit">convert</button>
            
    </form>
    
</body>
</html>