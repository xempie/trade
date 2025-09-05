class LimitOrdersManager {
    constructor() {
        console.log('🎯 LimitOrdersManager initialized');
    }

    async updateLimitOrdersDisplay() {
        try {
            const response = await fetch('api/get_limit_orders.php?limit=20');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            const limitOrdersContainer = document.getElementById('limit-orders-items');
            if (!limitOrdersContainer) return;
            
            if (!result.success || result.data.length === 0) {
                limitOrdersContainer.innerHTML = '<p class="no-watchlist">No limit orders</p>';
                return;
            }

            const limitOrders = result.data;
            
            limitOrdersContainer.innerHTML = limitOrders.map(order => {
                const entryTypeLabel = this.getEntryTypeLabel(order.entry_type);
                const directionClass = order.direction;
                const directionText = order.direction.toUpperCase();
                const timeAgo = this.getTimeAgo(order.created_at);
                const statusDisplay = order.status.toUpperCase();
                
                return `
                    <div class="watchlist-item" data-id="${order.id}">
                        <div class="watchlist-progress-bar">
                            <div class="watchlist-progress-fill" data-direction="${order.direction}"></div>
                        </div>
                        <div class="watchlist-item-content">
                            <div class="watchlist-item-header">
                                <div class="watchlist-symbol-container">
                                    <strong class="watchlist-symbol ${directionClass}">${order.symbol}</strong>
                                    <span class="watchlist-close-indicator" style="display: none;"></span>
                                </div>
                                <span class="watchlist-time">${timeAgo}</span>
                            </div>
                            <div class="watchlist-price-row">
                                <div class="watchlist-entry-info">
                                    <strong>${entryTypeLabel}:</strong> $${parseFloat(order.entry_price).toFixed(5)}
                                </div>
                                <div class="watchlist-direction ${directionClass}">
                                    ${directionText}
                                </div>
                            </div>
                            <div class="watchlist-current-price">
                                <span class="price-info">Loading price...</span>
                            </div>
                            <div class="watchlist-bottom-row">
                                <span>Quantity: ${parseFloat(order.margin_amount).toFixed(4)}</span>
                                <span class="order-status">${statusDisplay}</span>
                            </div>
                            <button 
                                class="watchlist-remove-btn"
                                onclick="limitOrdersManager.cancelLimitOrder(${order.id})" 
                                title="Cancel limit order"
                            >❌</button>
                        </div>
                    </div>
                `;
            }).join('');

            // Auto-refresh prices after displaying items
            this.refreshLimitOrdersPrices(false);

        } catch (error) {
            console.error('Error loading limit orders:', error);
            const limitOrdersContainer = document.getElementById('limit-orders-items');
            if (limitOrdersContainer) {
                limitOrdersContainer.innerHTML = '<p class="no-watchlist">Error loading limit orders</p>';
            }
        }
    }

    getEntryTypeLabel(entryType) {
        switch (entryType.toLowerCase()) {
            case 'market':
                return 'Market';
            case 'entry_2':
                return 'Entry 2';
            case 'entry_3':
                return 'Entry 3';
            default:
                return 'Limit';
        }
    }

    getTimeAgo(dateString) {
        const now = new Date();
        const past = new Date(dateString);
        const diffInSeconds = Math.floor((now - past) / 1000);
        
        if (diffInSeconds < 60) {
            return `${diffInSeconds}s ago`;
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes}m ago`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours}h ago`;
        } else if (diffInSeconds < 2592000) {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days}d ago`;
        } else {
            return past.toLocaleDateString();
        }
    }

    async refreshLimitOrders() {
        this.showNotification('Refreshing limit orders prices...', 'info');
        await this.refreshLimitOrdersPrices(true);
    }

    async refreshLimitOrdersPrices(showNotification = true) {
        try {
            console.log('🔄 Refreshing limit orders prices...');
            const response = await fetch('api/get_limit_orders_prices.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            console.log('📡 API Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Get the raw text first to see what we're actually receiving
            const rawText = await response.text();
            console.log('📄 Raw response:', rawText.substring(0, 200) + '...');
            
            // Try to parse as JSON
            let result;
            try {
                result = JSON.parse(rawText);
                console.log('📊 API Result:', result);
            } catch (parseError) {
                console.error('🚨 JSON Parse Error:', parseError);
                console.error('Raw response causing error:', rawText);
                throw new Error('API returned invalid JSON: ' + rawText.substring(0, 100));
            }

            if (result.success && result.data) {
                console.log('✅ Price data received, updating display...');
                
                result.data.forEach(order => {
                    const orderElement = document.querySelector(`[data-id="${order.id}"]`);
                    if (!orderElement) {
                        console.warn(`❌ Order element not found for ID: ${order.id}`);
                        return;
                    }

                    const priceInfoElement = orderElement.querySelector('.price-info');
                    const closeIndicator = orderElement.querySelector('.watchlist-close-indicator');
                    
                    if (!priceInfoElement || !closeIndicator) {
                        console.warn(`❌ Price elements not found for order ${order.id}`);
                        return;
                    }

                    if (order.price_status === 'success' && order.current_price !== null) {
                        console.log(`✅ Order ${order.id} - Current: $${order.current_price}, Target: $${order.entry_price}, Direction: ${order.direction}`);
                        
                        const currentPrice = parseFloat(order.current_price);
                        const targetPrice = parseFloat(order.entry_price);
                        const percentageDistance = ((targetPrice - currentPrice) / currentPrice * 100);
                        const absPercentage = Math.abs(percentageDistance);
                        
                        priceInfoElement.innerHTML = `
                            <strong>Current:</strong> $${currentPrice.toFixed(5)}<br>
                            <small class="percentage-distance ${percentageDistance >= 0 ? 'positive' : 'negative'}">
                                ${percentageDistance >= 0 ? '+' : ''}${percentageDistance.toFixed(2)}%
                            </small>
                        `;
                        
                        // Check if order is very close to target price (within 0.1%)
                        if (absPercentage <= 0.1) {
                            closeIndicator.textContent = '🎯';
                            closeIndicator.style.display = 'inline-block';
                            closeIndicator.classList.add('reached');
                            closeIndicator.classList.remove('close');
                        } else {
                            // Normal state - hide indicator
                            closeIndicator.style.display = 'none';
                            closeIndicator.classList.remove('close', 'reached');
                        }
                        
                        // Update progress bar
                        this.updateProgressBar(orderElement, order);
                    } else {
                        console.warn(`❌ Order ${order.id} - Price unavailable! Status: ${order.price_status}, Price: ${order.current_price}`);
                        priceInfoElement.innerHTML = 'Price unavailable';
                        closeIndicator.style.display = 'none';
                        closeIndicator.classList.remove('close', 'reached');
                        
                        // Reset progress bar when price unavailable
                        const progressFill = orderElement.querySelector('.watchlist-progress-fill');
                        if (progressFill) {
                            progressFill.style.height = '0%';
                        }
                    }
                });

                if (showNotification) {
                    this.showNotification(`Prices updated for ${result.data.length} limit orders`, 'success');
                }
            } else {
                throw new Error(result.error || 'No price data received');
            }

        } catch (error) {
            console.error('🚨 Error refreshing limit orders prices:', error);
            if (showNotification) {
                this.showNotification('Failed to refresh prices: ' + error.message, 'error');
            }
        }
    }

    updateProgressBar(orderElement, order) {
        const progressFill = orderElement.querySelector('.watchlist-progress-fill');
        if (!progressFill) return;

        const currentPrice = parseFloat(order.current_price);
        const targetPrice = parseFloat(order.entry_price);
        
        // Debug logging
        console.log('Progress calculation:', {
            symbol: order.symbol,
            direction: order.direction,
            currentPrice,
            targetPrice
        });
        
        let progress = 0;
        
        // Calculate progress based on distance to target price
        // Use a 5% range to show progress (can be made configurable later)
        const maxDistancePercent = 5; // 5% range for progress bar
        
        // Calculate distance percentage
        let distancePercent;
        if (order.direction === 'short') {
            // For short: positive distance means price needs to go UP to reach target
            distancePercent = ((targetPrice - currentPrice) / currentPrice) * 100;
        } else {
            // For long: negative distance means price needs to go DOWN to reach target  
            distancePercent = ((targetPrice - currentPrice) / currentPrice) * 100;
        }
        
        // Use absolute value for progress calculation
        const absDistancePercent = Math.abs(distancePercent);
        
        console.log('Progress calculation:', {
            direction: order.direction,
            currentPrice,
            targetPrice,
            maxDistancePercent,
            distancePercent,
            absDistancePercent
        });
        
        if (absDistancePercent >= maxDistancePercent) {
            // Haven't started moving toward target yet - 0% progress
            progress = 0;
        } else {
            // Calculate progress: closer to target = higher progress
            progress = ((maxDistancePercent - absDistancePercent) / maxDistancePercent) * 100;
        }
        
        // Ensure progress is within bounds
        progress = Math.max(0, Math.min(100, progress));
        
        console.log('Setting progress bar to:', progress + '%');
        progressFill.style.height = progress + '%';
    }

    async cancelLimitOrder(orderId) {
        if (!confirm('Are you sure you want to cancel this limit order?')) {
            return;
        }

        try {
            const response = await fetch(`api/get_limit_orders.php/${orderId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: 'cancelled'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Limit order cancelled', 'success');
                this.updateLimitOrdersDisplay();
            } else {
                throw new Error(result.error || 'Failed to cancel limit order');
            }

        } catch (error) {
            console.error('Error cancelling limit order:', error);
            this.showNotification('Failed to cancel limit order', 'error');
        }
    }

    showNotification(message, type = 'info') {
        // Use the existing notification system from the main app
        if (window.tradingForm && typeof window.tradingForm.showNotification === 'function') {
            window.tradingForm.showNotification(message, type);
        } else {
            // Fallback to console if notification system not available
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }
}