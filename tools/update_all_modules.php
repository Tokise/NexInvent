<?php
// Script to add history protection to all module index files
$modules_dir = __DIR__ . '/src/modules';

// Get all module directories
$modules = scandir($modules_dir);

echo "Updating module history protection...\n";

// History protection script to add to all files
$history_protection = <<<'EOD'
// Add aggressive history protection to prevent back button to login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Store session info in browser storage for history tracking
$session_id = session_id();
$user_id = $_SESSION['user_id'];
EOD;

foreach ($modules as $module) {
    // Skip dots and non-directories
    if ($module === '.' || $module === '..' || !is_dir($modules_dir . '/' . $module)) {
        continue;
    }
    
    $index_file = $modules_dir . '/' . $module . '/index.php';
    
    // Check if index.php exists
    if (file_exists($index_file)) {
        // Read the file
        $content = file_get_contents($index_file);
        
        // Check if this is a PHP file with require_auth.php
        if (strpos($content, "require_once '../../includes/require_auth.php'") !== false) {
            // Check if we already added the history protection
            if (strpos($content, "// Add aggressive history protection") === false) {
                // Add history protection after the require_auth.php line
                $new_content = str_replace(
                    "require_once '../../includes/require_auth.php';",
                    "require_once '../../includes/require_auth.php';\n\n$history_protection",
                    $content
                );
                
                // Write the modified content back to the file
                file_put_contents($index_file, $new_content);
                
                echo "- Added history protection to {$module}/index.php\n";
            } else {
                echo "- History protection already exists in {$module}/index.php\n";
            }
        } else {
            echo "- No require_auth.php found in {$module}/index.php - skipping\n";
        }
    } else {
        echo "- No index.php found in {$module}/\n";
    }
}

echo "Module history protection update complete!\n";
?> 