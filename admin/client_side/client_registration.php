<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Registration</title>
    <link rel="stylesheet" href="../admin/css/form.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <form id="client-registration-form" action="../backend/register_client.php" method="POST" enctype="multipart/form-data">
        <h2>Client Registration</h2>
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="phone">Phone/Telephone Number:</label>
        <input type="text" id="phone" name="phone">

        <label for="region">Region:</label>
        <select id="region" name="region" required></select>

        <label for="city">City:</label>
        <select id="city" name="city" required></select>

        <label for="company_address">Company Address:</label>
        <textarea id="company_address" name="company_address" required></textarea>

        <label for="business_proof">Business Proof:</label>
        <input type="file" id="business_proof" name="business_proof">

        <button type="submit">Register</button>
    </form>

    <script>
        $(document).ready(function() {
            // Fetch regions and cities from the API
            $.get('https://api.example.com/regions', function(data) {
                data.forEach(region => {
                    $('#region').append(`<option value="${region.name}">${region.name}</option>`);
                });
            });

            $('#region').change(function() {
                const region = $(this).val();
                $('#city').empty();
                $.get(`https://api.example.com/regions/${region}/cities`, function(data) {
                    data.forEach(city => {
                        $('#city').append(`<option value="${city.name}">${city.name}</option>`);
                    });
                });
            });
        });
    </script>
</body>
</html>