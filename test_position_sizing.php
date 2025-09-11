<?php
/**
 * Test Position Sizing Logic
 * Demonstrates the hybrid position sizing calculation
 */

echo "=== Position Sizing Logic Test ===\n\n";

// Simulate different scenarios
$scenarios = [
    [
        'auto_margin_per_entry' => 50.00,
        'total_assets' => 1000.00,
        'description' => 'Normal case: Setting below 5% limit'
    ],
    [
        'auto_margin_per_entry' => 100.00,
        'total_assets' => 1000.00,
        'description' => 'Risk management: Setting above 5% limit'
    ],
    [
        'auto_margin_per_entry' => 75.00,
        'total_assets' => 2000.00,
        'description' => 'Higher assets: Setting below 5% limit'
    ],
    [
        'auto_margin_per_entry' => 200.00,
        'total_assets' => 1500.00,
        'description' => 'Aggressive setting: Capped by 5% rule'
    ]
];

foreach ($scenarios as $i => $scenario) {
    $autoMargin = $scenario['auto_margin_per_entry'];
    $totalAssets = $scenario['total_assets'];
    $fivePercent = $totalAssets * 0.05;
    $finalSize = min($autoMargin, $fivePercent);
    
    echo "Scenario " . ($i + 1) . ": " . $scenario['description'] . "\n";
    echo "  AUTO_MARGIN_PER_ENTRY: $autoMargin USDT\n";
    echo "  Total Assets: $totalAssets USDT\n";
    echo "  5% of Assets: $fivePercent USDT\n";
    echo "  Final Position Size: $finalSize USDT\n";
    echo "  Logic: MIN($autoMargin, $fivePercent) = $finalSize\n\n";
}

echo "=== Key Benefits ===\n";
echo "1. Flexible configuration via AUTO_MARGIN_PER_ENTRY setting\n";
echo "2. Automatic risk management via 5% cap\n";
echo "3. Scales with account size\n";
echo "4. Prevents over-risking on individual positions\n";
echo "5. Maintains consistent risk profile regardless of settings\n\n";

echo "=== Configuration Commands ===\n";
echo "-- View current setting:\n";
echo "SELECT * FROM signal_automation_settings WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';\n\n";

echo "-- Set conservative sizing (25 USDT max):\n";
echo "UPDATE signal_automation_settings SET setting_value = '25.00' WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';\n\n";

echo "-- Set aggressive sizing (150 USDT max, but still capped by 5% rule):\n";
echo "UPDATE signal_automation_settings SET setting_value = '150.00' WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';\n\n";

echo "=== Test Completed ===\n";

?>