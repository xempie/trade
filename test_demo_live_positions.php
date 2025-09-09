<?php
// Test demo/live position display functionality
header('Content-Type: text/html');

echo "<h2>Demo/Live Position Display Test</h2>\n";

// Simulate position data with demo/live flags
$mockPositions = [
    [
        'id' => 1,
        'symbol' => 'BTC',
        'side' => 'LONG',
        'size' => 0.01,
        'entry_price' => 43500.00,
        'leverage' => 5,
        'unrealized_pnl' => 125.50,
        'margin_used' => 87.00,
        'opened_at' => '2025-01-09 14:30:00',
        'is_demo' => 0  // Live position
    ],
    [
        'id' => 2,
        'symbol' => 'ETH',
        'side' => 'SHORT',
        'size' => 0.5,
        'entry_price' => 3250.00,
        'leverage' => 3,
        'unrealized_pnl' => -45.20,
        'margin_used' => 541.67,
        'opened_at' => '2025-01-09 15:45:00',
        'is_demo' => 1  // Demo position
    ],
    [
        'id' => 3,
        'symbol' => 'DOLO',
        'side' => 'LONG',
        'size' => 100,
        'entry_price' => 0.17586,
        'leverage' => 2,
        'unrealized_pnl' => 8.75,
        'margin_used' => 8.79,
        'opened_at' => '2025-01-09 16:20:00',
        'is_demo' => 1  // Demo position
    ]
];

echo "<h3>Mock Position Data Test</h3>\n";
echo "<p>Testing how positions would display with demo/live indicators</p>\n";

foreach ($mockPositions as $position) {
    $symbol = htmlspecialchars($position['symbol']);
    $direction = strtolower($position['side']);
    $isDemo = $position['is_demo'] === 1 || $position['is_demo'] === '1' || $position['is_demo'] === true;
    $modeText = $isDemo ? 'DEMO' : 'LIVE';
    $modeClass = $isDemo ? 'demo-mode' : 'live-mode';
    
    echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 8px; background: #f9f9f9;'>\n";
    echo "  <div style='display: flex; justify-content: space-between; align-items: center;'>\n";
    echo "    <div>\n";
    echo "      <strong>{$symbol}</strong> {$direction}\n";
    echo "      <span class='mode-indicator {$modeClass}' style='font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 8px; background-color: " . ($isDemo ? '#ffa500' : '#31B099') . "; color: #000;'>{$modeText}</span>\n";
    echo "    </div>\n";
    echo "    <button style='padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; background: #fff; cursor: pointer;' ";
    echo "            onclick=\"alert('Would close {$modeText} position for {$symbol}')\" ";
    echo "            title='Close position on {$modeText} exchange'>Close Position</button>\n";
    echo "  </div>\n";
    echo "  <div style='margin-top: 10px; font-size: 14px; color: #666;'>\n";
    echo "    <div>Size: {$position['size']}, Entry: \${$position['entry_price']}, Leverage: {$position['leverage']}x</div>\n";
    echo "    <div>P&L: \${$position['unrealized_pnl']}, Margin: \${$position['margin_used']}</div>\n";
    echo "    <div>Opened: {$position['opened_at']}</div>\n";
    echo "  </div>\n";
    echo "</div>\n";
}

echo "<h3>JavaScript Testing</h3>\n";
echo "<p>Test the position button generation function:</p>\n";
echo "<div id='test-buttons'></div>\n";

echo "<script>\n";
echo "// Mock TradingForm class methods for testing\n";
echo "const testTradingForm = {\n";
echo "  closePosition: function(positionId, symbol, direction, isDemo) {\n";
echo "    const mode = isDemo ? 'Demo' : 'Live';\n";
echo "    alert(`Closing \${mode} position: \${symbol} \${direction} (ID: \${positionId})`);\n";
echo "  },\n";
echo "  removePosition: function(positionId, symbol, direction, isDemo) {\n";
echo "    const mode = isDemo ? 'Demo' : 'Live';\n";
echo "    alert(`Removing \${mode} position: \${symbol} \${direction} (ID: \${positionId})`);\n";
echo "  },\n";
echo "  positionStatus: {},\n";
echo "  getPositionButton: function(positionId, symbol, direction, isDemo) {\n";
echo "    const status = this.positionStatus[positionId];\n";
echo "    const modeText = isDemo ? 'Demo' : 'Live';\n";
echo "    \n";
echo "    if (!status || status.exists_on_exchange) {\n";
echo "      return `<button class='position-close-btn' onclick='testTradingForm.closePosition(\${positionId}, \"\${symbol}\", \"\${direction}\", \${isDemo})' title='Close position on \${modeText} exchange'>Close Position</button>`;\n";
echo "    } else {\n";
echo "      return `<button class='position-remove-btn' onclick='testTradingForm.removePosition(\${positionId}, \"\${symbol}\", \"\${direction}\", \${isDemo})' title='Remove position (not on \${modeText} exchange)'>Remove</button>`;\n";
echo "    }\n";
echo "  }\n";
echo "};\n";

echo "// Test button generation\n";
echo "const testPositions = [\n";
echo "  { id: 1, symbol: 'BTC', direction: 'LONG', isDemo: false },\n";
echo "  { id: 2, symbol: 'ETH', direction: 'SHORT', isDemo: true },\n";
echo "  { id: 3, symbol: 'DOLO', direction: 'LONG', isDemo: true }\n";
echo "];\n";

echo "let buttonsHtml = '';\n";
echo "testPositions.forEach(pos => {\n";
echo "  const modeText = pos.isDemo ? 'DEMO' : 'LIVE';\n";
echo "  buttonsHtml += `<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>`;\n";
echo "  buttonsHtml += `<strong>\${pos.symbol} \${pos.direction} (\${modeText})</strong><br>`;\n";
echo "  buttonsHtml += testTradingForm.getPositionButton(pos.id, pos.symbol, pos.direction, pos.isDemo);\n";
echo "  buttonsHtml += `</div>`;\n";
echo "});\n";
echo "document.getElementById('test-buttons').innerHTML = buttonsHtml;\n";
echo "</script>\n";

echo "<p><strong>Test Summary:</strong></p>\n";
echo "<ul>\n";
echo "<li>✅ Demo positions should show orange 'DEMO' indicator</li>\n";
echo "<li>✅ Live positions should show green 'LIVE' indicator</li>\n";
echo "<li>✅ Close buttons should indicate which exchange (Demo/Live)</li>\n";
echo "<li>✅ JavaScript functions should accept isDemo parameter</li>\n";
echo "</ul>\n";
?>