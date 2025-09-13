#!/bin/bash
# Setup error logging for all directories recursively from /var/www/html/

echo "Setting up error logging for all directories..."

# Function to create .htaccess with error logging
setup_error_log() {
    local dir="$1"
    local htaccess_file="${dir}/.htaccess"
    local error_log_file="${dir}/error_log"
    
    echo "Processing: $dir"
    
    # Create or update .htaccess file
    if [ -f "$htaccess_file" ]; then
        # Remove existing error log settings to avoid duplicates
        sed -i '/php_value log_errors/d' "$htaccess_file"
        sed -i '/php_value error_log/d' "$htaccess_file"
        sed -i '/php_value error_reporting/d' "$htaccess_file"
        sed -i '/php_value display_errors/d' "$htaccess_file"
    fi
    
    # Add error logging configuration
    cat >> "$htaccess_file" << EOF

# Error logging configuration
php_value log_errors On
php_value error_log ${error_log_file}
php_value error_reporting "E_ALL"
php_value display_errors Off
EOF
    
    # Create error log file
    touch "$error_log_file"
    chmod 666 "$error_log_file"
    chown www-data:www-data "$error_log_file" 2>/dev/null || true
    chmod 644 "$htaccess_file"
    
    echo "  ✓ Created .htaccess and error_log in $dir"
}

# Main execution
cd /var/www/html/

# Setup error logging for root directory
setup_error_log "/var/www/html"

# Find and setup for all subdirectories
find /var/www/html -type d -not -path "*/.*" | while read -r dir; do
    if [ "$dir" != "/var/www/html" ]; then
        setup_error_log "$dir"
    fi
done

echo ""
echo "✅ Error logging setup complete!"
echo "Error logs will be created as 'error_log' in each directory when PHP errors occur."
echo ""
echo "To view error logs in any directory:"
echo "  tail -f /path/to/directory/error_log"
echo ""
echo "To view all error logs system-wide:"
echo "  find /var/www/html -name 'error_log' -exec tail -n 5 {} +"