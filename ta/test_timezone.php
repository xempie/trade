<?php
require_once 'auth/config.php';

// Require authentication
requireAuth();

// Get current user
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'meta.php'; ?>
    <title>Timezone Test - Crypto Trading Manager</title>
</head>
<body class="pwa-app">
    <div class="pwa-container">
        <?php include 'header.php'; ?>

        <main class="pwa-main" style="padding-bottom: 150px;">
            <div class="container">
                <div class="form-container">
                    <div class="header">
                        <h1>Timezone Test</h1>
                        <p>Testing timezone conversion functionality</p>
                    </div>

                    <div class="test-section">
                        <h3>Current Settings</h3>
                        <p><strong>User Timezone:</strong> <span id="user-timezone">Loading...</span></p>
                        <p><strong>Timezone Display:</strong> <span id="timezone-display">Loading...</span></p>
                    </div>

                    <div class="test-section">
                        <h3>Time Conversion Tests</h3>
                        <div class="test-item">
                            <label>Test Date (Server Time):</label>
                            <input type="datetime-local" id="test-date" value="2025-01-17T10:00:00">
                            <button onclick="testTimeConversion()">Test Conversion</button>
                        </div>
                        <div class="test-results" id="test-results">
                            <!-- Results will appear here -->
                        </div>
                    </div>

                    <div class="test-section">
                        <h3>Sample Time Displays</h3>
                        <div class="sample-times" id="sample-times">
                            <!-- Sample times will appear here -->
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <?php include 'nav.php'; ?>
    </div>

    <script src="assets/js/timezone-helper.js?v=<?php echo time(); ?>"></script>
    <script>
        // Test timezone functionality
        document.addEventListener('DOMContentLoaded', async () => {
            // Wait for timezone helper to load
            await new Promise(resolve => setTimeout(resolve, 500));

            if (window.timezoneHelper) {
                // Display current settings
                document.getElementById('user-timezone').textContent = window.timezoneHelper.getUserTimezone();
                document.getElementById('timezone-display').textContent = window.timezoneHelper.getTimezoneDisplay();

                // Generate sample times
                generateSampleTimes();
            } else {
                document.getElementById('user-timezone').textContent = 'Timezone helper not loaded';
                document.getElementById('timezone-display').textContent = 'Error loading timezone helper';
            }
        });

        function testTimeConversion() {
            const testDate = document.getElementById('test-date').value;
            const resultsDiv = document.getElementById('test-results');

            if (!testDate) {
                resultsDiv.innerHTML = '<p style="color: red;">Please select a test date</p>';
                return;
            }

            if (!window.timezoneHelper) {
                resultsDiv.innerHTML = '<p style="color: red;">Timezone helper not available</p>';
                return;
            }

            // Convert the local datetime-local input to UTC for testing
            const testDateObj = new Date(testDate);
            const testDateString = testDateObj.toISOString();

            const timeAgo = window.timezoneHelper.getTimeAgo(testDateString);
            const formatted = window.timezoneHelper.formatDateInUserTimezone(testDateString);

            resultsDiv.innerHTML = `
                <div style="background: #f5f5f5; padding: 15px; margin-top: 10px; border-radius: 5px;">
                    <p><strong>Input:</strong> ${testDate} (as entered)</p>
                    <p><strong>ISO String:</strong> ${testDateString}</p>
                    <p><strong>Time Ago:</strong> ${timeAgo}</p>
                    <p><strong>Formatted:</strong> ${formatted}</p>
                </div>
            `;
        }

        function generateSampleTimes() {
            const now = new Date();
            const sampleTimes = [
                new Date(now.getTime() - 5 * 60 * 1000),      // 5 minutes ago
                new Date(now.getTime() - 2 * 60 * 60 * 1000), // 2 hours ago
                new Date(now.getTime() - 24 * 60 * 60 * 1000), // 1 day ago
                new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000), // 1 week ago
            ];

            const sampleDiv = document.getElementById('sample-times');
            let html = '';

            sampleTimes.forEach((date, index) => {
                const dateString = date.toISOString();
                const timeAgo = window.timezoneHelper ? window.timezoneHelper.getTimeAgo(dateString) : 'N/A';
                const formatted = window.timezoneHelper ? window.timezoneHelper.formatDateInUserTimezone(dateString) : 'N/A';

                html += `
                    <div style="background: #f9f9f9; padding: 10px; margin: 5px 0; border-radius: 3px; border-left: 3px solid #007cba;">
                        <strong>Sample ${index + 1}:</strong><br>
                        Server Time: ${dateString}<br>
                        Time Ago: <strong>${timeAgo}</strong><br>
                        Formatted: ${formatted}
                    </div>
                `;
            });

            sampleDiv.innerHTML = html;
        }
    </script>

    <style>
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .test-section h3 {
            margin-top: 0;
            color: #007cba;
        }
        .test-item {
            margin: 10px 0;
        }
        .test-item label {
            display: inline-block;
            width: 180px;
            font-weight: bold;
        }
        .test-item input {
            margin-right: 10px;
            padding: 5px;
        }
        .test-item button {
            padding: 5px 15px;
            background: #007cba;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .test-item button:hover {
            background: #005a8b;
        }
    </style>
</body>
</html>