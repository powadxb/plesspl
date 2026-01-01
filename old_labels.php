<!DOCTYPE html>
<html lang="en">
<head>
    <title>Generate Product Labels</title>
    <script>
        function openLabelsWindow(url) {
            window.open(url, '_blank', 'width=800,height=600');
        }
    </script>
</head>
<body>
    <h1>Generate Product Labels</h1>
    <form action="generate_labels.php" method="POST" target="_blank" onsubmit="openLabelsWindow(this.action)">
        <label for="sku_list">Enter SKUs or EANs, one per line:</label>
        <br>
        <textarea name="sku_list" id="sku_list" cols="30" rows="10"></textarea>
        <br>
        <button type="submit" formaction="generate_labels.php">Shelf Labels</button>
        <button type="submit" formaction="hanging_labels.php">Hanging Labels</button>
        <button type="submit" formaction="placeholder_labels.php">Placeholder Labels</button>
    </form>
</body>
</html>

