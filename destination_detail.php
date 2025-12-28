<?php
// Destination details (you can change these)
$destination_name = "Destination of your choice";
$latitude = 23.6850;
$longitude = 90.3563;

// Google Earth link format
$google_earth_link = "https://earth.google.com/web/@$latitude,$longitude,1000000a,35y,0h,0t,0r";;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $destination_name; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
        }
        a {
            font-size: 18px;
            color: #0b5ed7;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h2><?php echo $destination_name; ?></h2>

    <p>
        📍 View this destination in Google Earth:
        <br><br>
        <a href="<?php echo $google_earth_link; ?>" target="_blank">
            Open in Google Earth 🌍
        </a>
    </p>

</body>
</html>
