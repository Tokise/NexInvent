<?php
// Script to add security include to all module index files
$modules_dir = __DIR__ . '/src/modules';
$include_line = "require_once '../../includes/require_auth.php';\n";

// Get all module directories
$modules = scandir($modules_dir);

echo "Updating module security...\n";

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
        
        // Only add the include if it doesn't already exist
        if (strpos($content, "require_once '../../includes/require_auth.php'") === false) {
            // Add the include after the opening PHP tag
            $content = preg_replace('/<\?php/', "<?php\n" . $include_line, $content, 1);
            
            // Write the modified content back to the file
            file_put_contents($index_file, $content);
            
            echo "- Updated security in {$module}/index.php\n";
        } else {
            echo "- Security already in place in {$module}/index.php\n";
        }
    } else {
        echo "- No index.php found in {$module}/\n";
    }
}

echo "Module security update complete!\n";
?> 