<?php
session_start();
include('connect/connection.php');

// Search logic if submitted
$searchCity = '';
if (isset($_GET['city'])) {
    $searchCity = trim($_GET['city']);
    $searchCityEscaped = mysqli_real_escape_string($connect, $searchCity);
    $query = "SELECT * FROM hotel WHERE address LIKE '%$searchCityEscaped%'";
    $result = mysqli_query($connect, $query);
} else {
    $result = mysqli_query($connect, "SELECT * FROM hotel");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel List with Search</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #suggestions {
            position: absolute;
            background-color: #fff;
            border: 1px solid #ddd;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">Search Hotels by Address</h2>
    <form method="GET" action="">
        <div class="mb-3 position-relative">
            <input type="text" name="city" id="city" class="form-control" placeholder="Search by city or address" value="<?php echo htmlspecialchars($searchCity); ?>">
            <div id="suggestions" class="list-group"></div>
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <hr>

    <div class="row">
        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <img src="<?php echo 'Room Image/' . $row['hotel_image']; ?>" class="card-img-top" alt="Hotel Image">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($row['hotel_name']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($row['address']); ?></p>
                        <a href="view_categories.php?hotel_id=<?php echo $row['hotel_id']; ?>" class="btn btn-primary">View Details</a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.getElementById('city').addEventListener('input', function () {
    const query = this.value;
    const suggestionsDiv = document.getElementById('suggestions');

    if (query.length < 2) {
        suggestionsDiv.innerHTML = '';
        return;
    }

    fetch('autocomplete.php?term=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            suggestionsDiv.innerHTML = '';
            data.forEach(item => {
                const div = document.createElement('div');
                div.textContent = item;
                div.classList.add('list-group-item', 'list-group-item-action');
                div.style.cursor = 'pointer';

                div.addEventListener('click', () => {
                    document.getElementById('city').value = item;
                    suggestionsDiv.innerHTML = '';
                });

                suggestionsDiv.appendChild(div);
            });
        });
});
</script>
</body>
</html>
