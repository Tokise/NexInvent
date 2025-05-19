<?php
// This file contains the common head content for all pages
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Inventory Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/NexInvent/src/css/global.css">
    <!-- Add Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        
    <link href="/NexInvent/src/css/global.css" rel="stylesheet">
    <?php 
    // Add page-specific stylesheets here if needed
    if (isset($pageStylesheets) && is_array($pageStylesheets)) {
        foreach ($pageStylesheets as $stylesheet) {
            echo '<link href="' . $stylesheet . '" rel="stylesheet">' . "\n";
        }
    }
    ?>
</head>
<style>
    body {
        font-family: 'Inter', sans-serif;
    }
    h1, h2, h3, h4, h5, h6, .card-title {
        font-family: 'Poppins', sans-serif;
    }
</style>
<body>
<?php include 'sidebar.php'; ?>
</body>
</html>