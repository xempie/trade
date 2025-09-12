<?php
/**
 * Remove create_test_signals.php from server
 */

$fileToRemove = 'create_test_signals.php';

if (file_exists($fileToRemove)) {
    if (unlink($fileToRemove)) {
        echo "✅ Successfully removed: $fileToRemove\n";
    } else {
        echo "❌ Failed to remove: $fileToRemove\n";
    }
} else {
    echo "ℹ️  File $fileToRemove does not exist (already removed)\n";
}

?>