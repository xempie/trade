class TradingForm {
    constructor() {
        this.form = document.getElementById('trading-form');
        this.availableBalance = 0; // Will be loaded from API
        this.totalBalance = 0;
        this.totalAssets = 0;
        this.marginUsed = 0;
        this.positionSizePercent = 3.3; // Default, will be loaded from settings
        this.defaultEntry2Percent = 2.0; // Default, will be loaded from settings
        this.defaultEntry3Percent = 4.0; // Default, will be loaded from settings
        this.positionStatus = {}; // Track which positions exist on exchange
        
        this.loadSettings().then(() => {
            this.init();
            // Load balance data on home page and trade page (needed for position sizing)
            if (window.location.pathname.includes('home.php') || window.location.pathname === '/' || window.location.pathname === '/index.php' || window.location.pathname.includes('trade.php')) {
                this.loadBalanceData();
            }
            this.refreshPositions();
            
            // Listen for settings updates from other pages
            this.setupSettingsListener();
        });
    }

    init() {
        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
        
        // Add to watchlist button (optional)
        const addToWatchlistBtn = document.getElementById('add-to-watchlist');
        if (addToWatchlistBtn) {
            addToWatchlistBtn.addEventListener('click', () => this.addToWatchlist());
        }
        
        // Reset form button (optional)
        const resetFormBtn = document.getElementById('reset-form');
        if (resetFormBtn) {
            resetFormBtn.addEventListener('click', () => this.resetForm());
        }
        
        // Real-time calculations
        const leverageSelect = document.getElementById('leverage');
        if (leverageSelect) {
            leverageSelect.addEventListener('change', () => this.updateAccountInfo());
        }
        
        // Enable/disable entry points
        this.setupEntryPointToggles();
        
        // Try to update entry values if on trade page (DOM is ready now)
        setTimeout(() => {
            this.updateEntryValues();
        }, 100);
        
        // Form validation
        this.setupValidation();
        
        // Symbol price fetching
        this.setupSymbolPriceFetch();
        
        // Signal pattern converter
        this.setupSignalPatternConverter();
        
        // Auto-save draft every 30 seconds
        setInterval(() => this.autoSave(), 30000);
    }

    setupEntryPointToggles() {
        // Set up checkbox toggles for Entry 2 and Entry 3
        let entry2Checkbox = document.getElementById('entry_2_enabled');
        let entry3Checkbox = document.getElementById('entry_3_enabled');
        
        if (entry2Checkbox) {
            entry2Checkbox.addEventListener('change', () => this.toggleEntryInputs('entry_2', entry2Checkbox.checked));
            // Initial state - disable inputs if checkbox is unchecked
            this.toggleEntryInputs('entry_2', entry2Checkbox.checked);
        }
        
        if (entry3Checkbox) {
            entry3Checkbox.addEventListener('change', () => this.toggleEntryInputs('entry_3', entry3Checkbox.checked));
            // Initial state - disable inputs if checkbox is unchecked  
            this.toggleEntryInputs('entry_3', entry3Checkbox.checked);
        }
        
        // Set up percentage calculation for Entry 2 and Entry 3
        const entry2Percent = document.getElementById('entry_2_percent');
        const entry3Percent = document.getElementById('entry_3_percent');
        const takeProfitPercent = document.getElementById('take_profit_percent');
        const stopLossPercent = document.getElementById('stop_loss_percent');
        
        if (entry2Percent) {
            entry2Percent.addEventListener('input', () => {
                this.calculateEntryPrice('entry_2_percent', 'entry_2');
            });
        }
        
        if (entry3Percent) {
            entry3Percent.addEventListener('input', () => {
                this.calculateEntryPrice('entry_3_percent', 'entry_3');
            });
        }

        if (takeProfitPercent) {
            takeProfitPercent.addEventListener('input', () => {
                this.calculateTakeProfitPrice('take_profit_percent', 'take_profit');
            });
        }

        // Also calculate take profit when price is manually entered
        const takeProfitPrice = document.getElementById('take_profit');
        if (takeProfitPrice) {
            takeProfitPrice.addEventListener('input', () => {
                this.calculateTakeProfitPercent('take_profit', 'take_profit_percent');
            });
        }
        
        if (stopLossPercent) {
            stopLossPercent.addEventListener('input', () => {
                this.calculateStopLossPrice('stop_loss_percent', 'stop_loss');
            });
        }
        
        // Also calculate stop loss when price is manually entered
        const stopLossPrice = document.getElementById('stop_loss');
        if (stopLossPrice) {
            stopLossPrice.addEventListener('input', () => {
                this.calculateStopLossPercent('stop_loss', 'stop_loss_percent');
            });
        }
        
        // Also recalculate when market entry changes
        const entryMarketEl = document.getElementById('entry_market');
        if (entryMarketEl) {
            entryMarketEl.addEventListener('input', () => {
                this.calculateEntryPrice('entry_2_percent', 'entry_2');
                this.calculateEntryPrice('entry_3_percent', 'entry_3');
                // Only recalculate take profit if percentage element exists
                if (document.getElementById('take_profit_percent')) {
                    this.calculateTakeProfitPrice('take_profit_percent', 'take_profit');
                }
                // Only recalculate stop loss if percentage element exists
                if (document.getElementById('stop_loss_percent')) {
                    this.calculateStopLossPrice('stop_loss_percent', 'stop_loss');
                }
            });
        }
        
        // Recalculate when direction changes
        document.querySelectorAll('input[name="direction"]').forEach(radio => {
            radio.addEventListener('change', () => {
                this.calculateEntryPrice('entry_2_percent', 'entry_2');
                this.calculateEntryPrice('entry_3_percent', 'entry_3');
                // Only recalculate take profit if percentage element exists
                if (document.getElementById('take_profit_percent')) {
                    this.calculateTakeProfitPrice('take_profit_percent', 'take_profit');
                }
                // Only recalculate stop loss if percentage element exists
                if (document.getElementById('stop_loss_percent')) {
                    this.calculateStopLossPrice('stop_loss_percent', 'stop_loss');
                }
                this.updateSubmitButton();
            });
        });

        // Recalculate when leverage changes
        const leverageEl = document.getElementById('leverage');
        if (leverageEl) {
            leverageEl.addEventListener('change', () => {
                this.calculateEntryPrice('entry_2_percent', 'entry_2');
                this.calculateEntryPrice('entry_3_percent', 'entry_3');
                // Only recalculate take profit if percentage element exists
                if (document.getElementById('take_profit_percent')) {
                    this.calculateTakeProfitPrice('take_profit_percent', 'take_profit');
                }
                // Only recalculate stop loss if percentage element exists
                if (document.getElementById('stop_loss_percent')) {
                    this.calculateStopLossPrice('stop_loss_percent', 'stop_loss');
                }
            });
        }
        
        // Initial button update
        this.updateSubmitButton();
    }

    setupSymbolPriceFetch() {
        const symbolField = document.getElementById('symbol');
        if (!symbolField) return;
        
        let fetchTimeout;
        
        // Auto-uppercase input as user types (allow letters and numbers)
        symbolField.addEventListener('input', (e) => {
            const cursorPosition = e.target.selectionStart;
            const value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            e.target.value = value;
            e.target.setSelectionRange(cursorPosition, cursorPosition);
        });
        
        symbolField.addEventListener('blur', async () => {
            const symbol = symbolField.value.trim().toUpperCase();
            if (!symbol) return;
            
            // Clear any existing timeout
            clearTimeout(fetchTimeout);
            
            // Keep the symbol clean in the form (just BTC, ADA, etc.)
            const cleanSymbol = symbol.replace('USDT', '').replace('.P', '').replace('-', '');
            symbolField.value = cleanSymbol;
            
            // Fetch price after a short delay to avoid rapid requests
            fetchTimeout = setTimeout(async () => {
                await this.fetchMarketPrice(cleanSymbol);
            }, 300);
        });
        
        // Also listen for Enter key
        symbolField.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                symbolField.blur(); // This will trigger the blur event
            }
        });
    }

    async fetchMarketPrice(cleanSymbol) {
        const marketPriceField = document.getElementById('entry_market');
        const originalPlaceholder = marketPriceField.placeholder;
        
        try {
            // Show loading state
            marketPriceField.placeholder = 'Fetching price...';
            marketPriceField.classList.add('loading');
            
            // Fetch price from our PHP backend (it handles the BingX API format conversion)
            const price = await this.getBingXPrice(cleanSymbol);
            
            if (price) {
                marketPriceField.value = price;
                this.showNotification(`Price updated: ${cleanSymbol} = $${price}`, 'success');
                
                // Recalculate entry points 2 and 3 if they have percentages
                this.calculateEntryPrice('entry_2_percent', 'entry_2');
                this.calculateEntryPrice('entry_3_percent', 'entry_3');
                // Recalculate take profit if it has a percentage element
                if (document.getElementById('take_profit_percent')) {
                    this.calculateTakeProfitPrice('take_profit_percent', 'take_profit');
                }
                // Recalculate stop loss if it has a percentage element
                if (document.getElementById('stop_loss_percent')) {
                    this.calculateStopLossPrice('stop_loss_percent', 'stop_loss');
                }
            } else {
                this.showNotification(`Could not fetch price for ${cleanSymbol}`, 'error');
            }
            
        } catch (error) {
            console.error('Error fetching price:', error);
            this.showNotification(`Error fetching price for ${cleanSymbol}`, 'error');
        } finally {
            // Reset loading state
            marketPriceField.placeholder = originalPlaceholder;
            marketPriceField.classList.remove('loading');
        }
    }

    async getBingXPrice(cleanSymbol) {
        try {
            // Call our PHP backend endpoint
            const response = await fetch(`api/get_price.php?symbol=${encodeURIComponent(cleanSymbol)}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.price) {
                return parseFloat(data.price).toFixed(5);
            } else {
                throw new Error(data.error || 'Invalid response from price API');
            }
            
        } catch (error) {
            console.error('Price API Error:', error);
            return null;
        }
    }

    calculateEntryPrice(percentFieldId, priceFieldId) {
        const percentEl = document.getElementById(percentFieldId);
        const priceEl = document.getElementById(priceFieldId);
        const marketPriceEl = document.getElementById('entry_market');
        const directionEl = document.querySelector('input[name="direction"]:checked');
        
        const percentage = parseFloat(percentEl.value);
        const marketPrice = parseFloat(marketPriceEl.value);
        const direction = directionEl ? directionEl.value : 'long';
        
        if (isNaN(percentage) || !marketPrice || percentage === 0) {
            priceEl.value = '';
            return;
        }
        
        let calculatedPrice;
        if (direction === 'long') {
            // For long positions, entry 2 and 3 should be lower than market (buy the dips)
            calculatedPrice = marketPrice * (1 - percentage / 100);
        } else {
            // For short positions, entry 2 and 3 should be higher than market (sell the pumps)
            calculatedPrice = marketPrice * (1 + percentage / 100);
        }
        
        // Format to appropriate decimal places
        priceEl.value = calculatedPrice.toFixed(5);
    }

    calculateStopLossPrice(percentFieldId, priceFieldId) {
        const percentEl = document.getElementById(percentFieldId);
        const priceEl = document.getElementById(priceFieldId);
        const marketPriceEl = document.getElementById('entry_market');
        const leverageEl = document.getElementById('leverage');
        const directionEl = document.querySelector('input[name="direction"]:checked');
        
        // Exit early if elements don't exist
        if (!percentEl || !priceEl) {
            return;
        }
        
        const percentage = parseFloat(percentEl.value);
        const marketPrice = parseFloat(marketPriceEl.value);
        const leverage = parseFloat(leverageEl ? leverageEl.value : 1);
        const direction = directionEl ? directionEl.value : 'long';
        
        if (isNaN(percentage) || !marketPrice || percentage === 0) {
            priceEl.value = '';
            return;
        }
        
        // Calculate the price movement needed for the percentage loss considering leverage
        // With leverage, a smaller price movement gives the same percentage loss
        const priceMovementPercent = percentage / leverage;
        
        let calculatedPrice;
        if (direction === 'long') {
            // For long positions, stop loss should be lower than market (stop when price drops)
            calculatedPrice = marketPrice * (1 - priceMovementPercent / 100);
        } else {
            // For short positions, stop loss should be higher than market (stop when price rises)
            calculatedPrice = marketPrice * (1 + priceMovementPercent / 100);
        }
        
        // Format to appropriate decimal places
        priceEl.value = calculatedPrice.toFixed(5);
    }

    calculateStopLossPercent(priceFieldId, percentFieldId) {
        const priceEl = document.getElementById(priceFieldId);
        const percentEl = document.getElementById(percentFieldId);
        const marketPriceEl = document.getElementById('entry_market');
        const leverageEl = document.getElementById('leverage');
        const directionEl = document.querySelector('input[name="direction"]:checked');
        
        const stopLossPrice = parseFloat(priceEl.value);
        const marketPrice = parseFloat(marketPriceEl.value);
        const leverage = parseFloat(leverageEl ? leverageEl.value : 1);
        const direction = directionEl ? directionEl.value : 'long';
        
        if (!stopLossPrice || !marketPrice || stopLossPrice === 0) {
            percentEl.value = '';
            return;
        }
        
        let priceMovementPercent;
        if (direction === 'long') {
            // For long positions, calculate how much lower stop loss is from market
            priceMovementPercent = ((marketPrice - stopLossPrice) / marketPrice) * 100;
        } else {
            // For short positions, calculate how much higher stop loss is from market
            priceMovementPercent = ((stopLossPrice - marketPrice) / marketPrice) * 100;
        }
        
        // Calculate the actual loss percentage considering leverage
        // With leverage, the same price movement gives higher percentage loss
        const calculatedPercent = priceMovementPercent * leverage;
        
        // Only update if the calculated percentage is positive (valid stop loss direction)
        if (calculatedPercent > 0) {
            percentEl.value = calculatedPercent.toFixed(1);
        } else {
            percentEl.value = '';
        }
    }

    calculateTakeProfitPrice(percentFieldId, priceFieldId) {
        const percentEl = document.getElementById(percentFieldId);
        const priceEl = document.getElementById(priceFieldId);
        const marketPriceEl = document.getElementById('entry_market');
        const leverageEl = document.getElementById('leverage');
        const directionEl = document.querySelector('input[name="direction"]:checked');
        
        // Exit early if elements don't exist
        if (!percentEl || !priceEl) {
            return;
        }
        
        const percentage = parseFloat(percentEl.value);
        const marketPrice = parseFloat(marketPriceEl.value);
        const leverage = parseFloat(leverageEl ? leverageEl.value : 1);
        const direction = directionEl ? directionEl.value : 'long';
        
        if (isNaN(percentage) || !marketPrice || percentage === 0) {
            priceEl.value = '';
            return;
        }
        
        // Calculate the price movement needed for the percentage profit considering leverage
        // With leverage, a smaller price movement gives the same percentage profit
        const priceMovementPercent = percentage / leverage;
        
        let calculatedPrice;
        if (direction === 'long') {
            // For long positions, take profit is above market price
            calculatedPrice = marketPrice * (1 + priceMovementPercent / 100);
        } else {
            // For short positions, take profit is below market price
            calculatedPrice = marketPrice * (1 - priceMovementPercent / 100);
        }
        
        priceEl.value = calculatedPrice.toFixed(8);
    }

    calculateTakeProfitPercent(priceFieldId, percentFieldId) {
        const priceEl = document.getElementById(priceFieldId);
        const percentEl = document.getElementById(percentFieldId);
        const marketPriceEl = document.getElementById('entry_market');
        const leverageEl = document.getElementById('leverage');
        const directionEl = document.querySelector('input[name="direction"]:checked');
        
        const takeProfitPrice = parseFloat(priceEl.value);
        const marketPrice = parseFloat(marketPriceEl.value);
        const leverage = parseFloat(leverageEl ? leverageEl.value : 1);
        const direction = directionEl ? directionEl.value : 'long';
        
        if (!takeProfitPrice || !marketPrice || takeProfitPrice === 0) {
            percentEl.value = '';
            return;
        }
        
        let priceMovementPercent;
        if (direction === 'long') {
            // For long positions, calculate how much higher take profit is from market
            priceMovementPercent = ((takeProfitPrice - marketPrice) / marketPrice) * 100;
        } else {
            // For short positions, calculate how much lower take profit is from market
            priceMovementPercent = ((marketPrice - takeProfitPrice) / marketPrice) * 100;
        }
        
        // Calculate the actual profit percentage considering leverage
        // With leverage, the same price movement gives higher percentage profit
        const calculatedPercent = priceMovementPercent * leverage;
        
        // Only update if the calculated percentage is positive (valid take profit direction)
        if (calculatedPercent > 0) {
            percentEl.value = calculatedPercent.toFixed(1);
        } else {
            percentEl.value = '';
        }
    }

    updateSubmitButton() {
        const directionEl = document.querySelector('input[name="direction"]:checked');
        const submitBtn = this.form ? this.form.querySelector('.btn-primary') : null;
        const direction = directionEl ? directionEl.value : 'long';
        
        if (submitBtn) {
            if (direction === 'short') {
                submitBtn.textContent = 'Open Short Position';
                submitBtn.classList.add('short');
            } else {
                submitBtn.textContent = 'Open Long Position';
                submitBtn.classList.remove('short');
            }
        }
    }
    
    toggleEntryInputs(entryPrefix, enabled) {
        // Toggle the disabled state and visual styling of entry inputs
        const marginInput = document.getElementById(`${entryPrefix}_margin`);
        const percentInput = document.getElementById(`${entryPrefix}_percent`);
        const priceInput = document.getElementById(`${entryPrefix}`);
        
        const inputs = [marginInput, percentInput, priceInput];
        
        inputs.forEach(input => {
            if (input) {
                input.disabled = !enabled;
                if (enabled) {
                    input.style.opacity = '1';
                    input.style.cursor = 'text';
                } else {
                    input.style.opacity = '0.5';
                    input.style.cursor = 'not-allowed';
                }
            }
        });
    }

    setupValidation() {
        if (!this.form) return;
        const inputs = this.form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }

    validateField(field) {
        const formGroup = field.closest('.form-group');
        let isValid = true;
        let errorMessage = '';

        // Remove existing error
        this.clearFieldError(field);

        // Required field validation
        if (field.hasAttribute('required') && !field.value.trim()) {
            isValid = false;
            errorMessage = 'This field is required';
        }

        // Symbol validation
        if (field.name === 'symbol' && field.value) {
            const symbolRegex = /^[A-Z]{1,10}$/;
            if (!symbolRegex.test(field.value.toUpperCase())) {
                isValid = false;
                errorMessage = 'Symbol should be 1-10 letters (e.g., BTC, ADA, ETH)';
            }
        }

        // Price validation
        if ((field.name.includes('entry') || field.name.includes('tp') || field.name === 'take_profit' || field.name === 'stop_loss') && field.value) {
            if (parseFloat(field.value) <= 0) {
                isValid = false;
                errorMessage = 'Price must be greater than 0';
            }
        }

        if (!isValid) {
            formGroup.classList.add('error');
            this.showFieldError(formGroup, errorMessage);
        } else {
            formGroup.classList.add('success');
        }

        return isValid;
    }

    clearFieldError(field) {
        const formGroup = field.closest('.form-group');
        formGroup.classList.remove('error', 'success');
        const existingError = formGroup.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
    }

    showFieldError(formGroup, message) {
        const errorEl = document.createElement('div');
        errorEl.className = 'error-message';
        errorEl.textContent = message;
        formGroup.appendChild(errorEl);
    }

    async loadSettings() {
        try {
            const response = await fetch('api/get_settings.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Update dynamic values from settings
                this.positionSizePercent = data.settings.position_size_percent || 3.3;
                this.defaultEntry2Percent = data.settings.entry_2_percent || 2.0;
                this.defaultEntry3Percent = data.settings.entry_3_percent || 4.0;
                
                // Update default values in form fields if they exist
                const entry2PercentField = document.getElementById('entry_2_percent');
                const entry3PercentField = document.getElementById('entry_3_percent');
                
                if (entry2PercentField && !entry2PercentField.value) {
                    entry2PercentField.value = this.defaultEntry2Percent;
                }
                if (entry3PercentField && !entry3PercentField.value) {
                    entry3PercentField.value = this.defaultEntry3Percent;
                }
                
                // Update position size percentage display on home page
                const positionSizePercentElement = document.getElementById('position-size-percent');
                if (positionSizePercentElement) {
                    positionSizePercentElement.textContent = this.positionSizePercent;
                }
                
                // Update entry values on trade form if balance data is available
                this.updateEntryValues();
            } else {
                console.warn('Failed to load settings:', data.error);
            }
        } catch (error) {
            console.warn('Settings load error:', error);
            // Continue with defaults if settings fail to load
        }
    }

    setupSettingsListener() {
        // Listen for settings updates via BroadcastChannel
        if ('BroadcastChannel' in window) {
            const channel = new BroadcastChannel('settings-update');
            channel.onmessage = (event) => {
                if (event.data.type === 'settings-updated') {
                    console.log('Settings updated, reloading settings and updating entry values');
                    this.loadSettings().then(() => {
                        this.updateEntryValues(true); // Force update when settings change
                    });
                }
            };
        }
    }

    updateEntryValues(forceUpdate = false) {
        // Only populate entry values if we are on trade page
        if (!window.location.pathname.includes('trade.php')) return;
        
        console.log('Debug - updateEntryValues called:', {
            totalAssets: this.totalAssets,
            positionSizePercent: this.positionSizePercent,
            forceUpdate: forceUpdate
        });
        
        // Check if we have the required data
        if (!this.totalAssets || this.totalAssets <= 0) {
            console.log('Debug - No totalAssets available, trying to load balance...');
            // Try to load balance data if not available
            this.loadBalanceData();
            return;
        }
        
        if (!this.positionSizePercent || this.positionSizePercent <= 0) {
            console.log('Debug - No positionSizePercent available, using default 3.3%');
            this.positionSizePercent = 3.3;
        }
        
        // Calculate position size based on settings percentage and total assets
        const positionSize = Math.ceil((this.totalAssets * (this.positionSizePercent / 100)));
        
        console.log('Debug - Calculated position size:', positionSize);
        
        // Get entry value input fields
        const marketMargin = document.getElementById('entry_market_margin');
        const entry2Margin = document.getElementById('entry_2_margin');
        const entry3Margin = document.getElementById('entry_3_margin');
        
        console.log('Debug - Found fields:', {
            marketMargin: !!marketMargin,
            entry2Margin: !!entry2Margin,
            entry3Margin: !!entry3Margin
        });
        
        // Store the previous position size to detect if we should force update
        if (!this.lastCalculatedPositionSize) {
            this.lastCalculatedPositionSize = positionSize;
        }
        
        // Determine if we should update fields
        const shouldUpdate = (field) => {
            if (!field) return false;
            if (forceUpdate) return true;
            if (!field.value) return true; // Empty field
            // If the current value matches our last calculated value, update it
            if (field.value == this.lastCalculatedPositionSize) return true;
            return false;
        };
        
        // Update fields if appropriate
        if (shouldUpdate(marketMargin)) {
            marketMargin.value = positionSize;
            console.log('Debug - Updated market margin to:', positionSize);
        }
        if (shouldUpdate(entry2Margin)) {
            entry2Margin.value = positionSize;
            console.log('Debug - Updated entry 2 margin to:', positionSize);
        }
        if (shouldUpdate(entry3Margin)) {
            entry3Margin.value = positionSize;
            console.log('Debug - Updated entry 3 margin to:', positionSize);
        }
        
        // Update our tracking variable
        this.lastCalculatedPositionSize = positionSize;
    }

    async loadBalanceData() {
        try {
            const response = await fetch('api/get_balance.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            console.log('Balance API Response:', result); // Debug log

            if (result.success) {
                this.totalBalance = result.data.total_balance;
                this.availableBalance = result.data.available_balance;
                this.marginUsed = result.data.margin_used;
                this.unrealizedPnL = result.data.unrealized_pnl || 0;
                this.totalAssets = this.marginUsed + this.availableBalance;
                this.isDemo = result.data.is_demo || false;
                
                // Update UI
                this.updateAccountInfo();
                
                // Update entry values on trade form
                this.updateEntryValues();
                
                // Update last updated time
                const lastUpdated = new Date().toLocaleTimeString();
                const lastUpdatedEl = document.getElementById('last-updated');
                if (lastUpdatedEl) {
                    lastUpdatedEl.textContent = lastUpdated;
                }
                
                // Show success notification with demo indicator
                const balanceType = this.isDemo ? 'Demo' : 'BingX';
                this.showNotification(`Balance updated from ${balanceType}`, 'success');
            } else {
                // Show detailed error information
                let errorMsg = result.error || 'Failed to load balance data';
                if (result.debug) {
                    console.log('API Debug Info:', result.debug);
                    errorMsg += ` (Debug: API Key: ${result.debug.api_key_present ? 'Present' : 'Missing'}, cURL: ${result.debug.curl_available ? 'Available' : 'Missing'})`;
                }
                throw new Error(errorMsg);
            }

        } catch (error) {
            console.error('Error loading balance:', error);
            
            // Don't use demo data - show error state
            this.totalBalance = 0;
            this.availableBalance = 0;
            this.totalAssets = 0;
            this.marginUsed = 0;
            
            // Show error in UI (only if elements exist)
            const totalAssetsEl = document.getElementById('total-assets');
            if (totalAssetsEl) totalAssetsEl.textContent = 'Error';
            
            const availableBalanceEl = document.getElementById('available-balance');
            if (availableBalanceEl) availableBalanceEl.textContent = 'Error';
            
            const positionSizeEl = document.getElementById('position-size');
            if (positionSizeEl) positionSizeEl.textContent = 'Error';
            
            const marginUsedEl = document.getElementById('margin-used');
            if (marginUsedEl) marginUsedEl.textContent = 'Error';
            
            const lastUpdatedEl = document.getElementById('last-updated');
            if (lastUpdatedEl) lastUpdatedEl.textContent = 'API Error';
            
            this.showNotification('Failed to load balance from BingX: ' + error.message, 'error');
        }
    }

    updateAccountInfo() {
        const leverageEl = document.getElementById('leverage');
        const leverage = leverageEl ? parseInt(leverageEl.value) || 1 : 1;
        const positionSize = (this.totalAssets * this.positionSizePercent) / 100;
        const leverageMargin = positionSize / leverage;

        // Update elements only if they exist
        const demoIndicator = this.isDemo ? ' <span class="demo-indicator">DEMO</span>' : '';
        
        const totalAssetsEl = document.getElementById('total-assets');
        if (totalAssetsEl) {
            totalAssetsEl.innerHTML = `$${this.totalAssets.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}${demoIndicator}`;
        }

        const availableBalanceEl = document.getElementById('available-balance');
        if (availableBalanceEl) {
            availableBalanceEl.innerHTML = `$${this.availableBalance.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}${demoIndicator}`;
        }

        const positionSizeEl = document.getElementById('position-size');
        if (positionSizeEl) {
            positionSizeEl.textContent = `$${Math.ceil(positionSize).toLocaleString()}`;
        }

        const marginUsedEl = document.getElementById('margin-used');
        if (marginUsedEl) {
            marginUsedEl.textContent = `$${this.marginUsed.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        }
        
        // Update unrealized PnL with color coding
        const pnlElement = document.getElementById('unrealized-pnl');
        if (pnlElement) {
            const pnlValue = this.unrealizedPnL || 0;
            pnlElement.textContent = `$${pnlValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            pnlElement.className = `balance-value ${pnlValue >= 0 ? 'profit' : 'loss'}`;
        }
    }

    async refreshBalance() {
        await this.loadBalanceData();
    }

    getFormData() {
        if (!this.form) return {};
        
        const formData = new FormData(this.form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            // Convert numeric fields to numbers
            if (key === 'leverage' || key.includes('margin') || key.includes('entry_') || key.includes('take_profit') || key.includes('stop_loss')) {
                data[key] = value ? parseFloat(value) : value;
            } else {
                data[key] = value;
            }
        }

        // Add enabled entry points (based on checkbox state AND having values)
        data.enabled_entries = [];
        
        // Market entry - add if margin has value (price can be empty for market orders)
        if (data.entry_market_margin) {
            data.enabled_entries.push({ 
                type: 'market', 
                price: data.entry_market ? parseFloat(data.entry_market) : 0, // 0 for market price
                margin: parseFloat(data.entry_market_margin)
            });
        }
        
        // Entry 2 - add only if checkbox is checked AND margin and (price or percentage) have values
        let entry2Checkbox = document.getElementById('entry_2_enabled');
        if (entry2Checkbox && entry2Checkbox.checked && data.entry_2_margin && (data.entry_2 || data.entry_2_percent)) {
            data.enabled_entries.push({ 
                type: 'limit', 
                price: data.entry_2,
                margin: data.entry_2_margin || 0,
                percentage: data.entry_2_percent || 0
            });
        }
        
        // Entry 3 - add only if checkbox is checked AND margin and (price or percentage) have values
        let entry3Checkbox = document.getElementById('entry_3_enabled');
        if (entry3Checkbox && entry3Checkbox.checked && data.entry_3_margin && (data.entry_3 || data.entry_3_percent)) {
            data.enabled_entries.push({ 
                type: 'limit', 
                price: data.entry_3,
                margin: data.entry_3_margin || 0,
                percentage: data.entry_3_percent || 0
            });
        }

        return data;
    }

    validateForm() {
        if (!this.form) return true;
        const inputs = this.form.querySelectorAll('input[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        // At least one entry point must have a value (considering checkbox states)
        // Get checkboxes once to reuse throughout function
        let entry2Checkbox = document.getElementById('entry_2_enabled');
        let entry3Checkbox = document.getElementById('entry_3_enabled');
        
        const hasValidEntry = (document.getElementById('entry_market_margin')?.value && document.getElementById('entry_market')?.value) ||
                             (entry2Checkbox && entry2Checkbox.checked && document.getElementById('entry_2_margin')?.value && (document.getElementById('entry_2_percent')?.value || document.getElementById('entry_2')?.value)) ||
                             (entry3Checkbox && entry3Checkbox.checked && document.getElementById('entry_3_margin')?.value && (document.getElementById('entry_3_percent')?.value || document.getElementById('entry_3')?.value));

        if (!hasValidEntry) {
            isValid = false;
            this.showNotification('At least one entry point must have both margin value and price/percentage', 'error');
        }

        // Check total margin usage doesn't exceed 10% of total assets
        let totalMarginUsage = 0;
        
        // Add market entry margin
        const marketMargin = parseFloat(document.getElementById('entry_market_margin')?.value || 0);
        if (marketMargin > 0 && document.getElementById('entry_market')?.value) {
            totalMarginUsage += marketMargin;
        }
        
        // Add entry 2 margin (only if checkbox is checked)
        const entry2Margin = parseFloat(document.getElementById('entry_2_margin')?.value || 0);
        if (entry2Checkbox && entry2Checkbox.checked && entry2Margin > 0 && (document.getElementById('entry_2_percent')?.value || document.getElementById('entry_2')?.value)) {
            totalMarginUsage += entry2Margin;
        }
        
        // Add entry 3 margin (only if checkbox is checked)
        const entry3Margin = parseFloat(document.getElementById('entry_3_margin')?.value || 0);
        if (entry3Checkbox && entry3Checkbox.checked && entry3Margin > 0 && (document.getElementById('entry_3_percent')?.value || document.getElementById('entry_3')?.value)) {
            totalMarginUsage += entry3Margin;
        }
        
        // Check if total margin usage exceeds 10% of total assets
        const maxAllowedMargin = this.totalAssets * 0.10; // 10% of total assets
        if (totalMarginUsage > maxAllowedMargin) {
            isValid = false;
            this.showNotification(
                `Order rejected: Total margin ($${totalMarginUsage.toFixed(2)}) exceeds 10% of total assets ($${maxAllowedMargin.toFixed(2)}). Maximum allowed: $${maxAllowedMargin.toFixed(2)}`, 
                'error'
            );
        }

        return isValid;
    }

    async handleSubmit(e) {
        e.preventDefault();

        if (!this.validateForm()) {
            return;
        }

        const submitBtn = this.form ? this.form.querySelector('.btn-primary') : null;
        const originalText = submitBtn ? submitBtn.textContent : '';
        
        try {
            const directionEl = document.querySelector('input[name="direction"]:checked');
            const direction = directionEl ? directionEl.value : 'long';
            
            if (submitBtn) {
                submitBtn.textContent = `Opening ${direction.charAt(0).toUpperCase() + direction.slice(1)} Position...`;
                submitBtn.disabled = true;
            }
            if (this.form) {
                this.form.classList.add('loading');
            }

            const data = this.getFormData();
            
            // Simulate API call
            await this.createSignal(data);
            
            this.showNotification('Position opened successfully!', 'success');
            if (this.form) {
                this.form.reset();
            }
            this.updateAccountInfo();
            this.updateSubmitButton();
            
        } catch (error) {
            this.showNotification(error.message || 'Failed to create signal', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
            if (this.form) {
                this.form.classList.remove('loading');
            }
        }
    }

    async createSignal(data) {
        try {
            // Debug: First call debug endpoint to see what data we're sending
            console.log('Sending data:', data);
            const debugResponse = await fetch('api/debug_order_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            const debugResult = await debugResponse.json();
            console.log('Debug analysis:', debugResult);
            
            // Call the real order placement API
            const response = await fetch('api/place_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                // Try to get the error details from the API response
                let errorMessage = `HTTP error! status: ${response.status}`;
                try {
                    const errorResult = await response.json();
                    console.log('API Error Response:', errorResult);
                    if (errorResult.error) {
                        errorMessage = errorResult.error;
                    }
                } catch (e) {
                    console.log('Could not parse error response');
                }
                throw new Error(errorMessage);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                console.log('API Success=false Response:', result);
                throw new Error(result.error || 'Failed to place orders');
            }
            
            // Show detailed results
            const marketOrders = result.orders.filter(o => o.order_type === 'MARKET');
            const limitOrders = result.orders.filter(o => o.order_type === 'LIMIT');
            
            let message = `Signal created successfully! Signal ID: ${result.signal_id}\n`;
            
            if (marketOrders.length > 0) {
                message += `\nMarket orders placed: ${marketOrders.length}`;
                marketOrders.forEach(order => {
                    if (order.success) {
                        message += `\n✅ ${order.entry_type.toUpperCase()}: $${order.position_size} (Order ID: ${order.bingx_order_id})`;
                    } else {
                        message += `\n❌ ${order.entry_type.toUpperCase()}: ${order.message}`;
                    }
                });
            }
            
            if (limitOrders.length > 0) {
                message += `\nLimit orders saved for monitoring: ${limitOrders.length}`;
                limitOrders.forEach(order => {
                    message += `\n📝 ${order.entry_type.toUpperCase()}: $${order.position_size} at $${order.price}`;
                });
            }
            
            message += `\nTotal margin used: $${result.total_margin_used.toFixed(2)}`;
            
            // Show success notification with details
            this.showNotification(message, 'success');
            
            // Store in localStorage for display
            const signals = JSON.parse(localStorage.getItem('trading_signals') || '[]');
            signals.unshift({
                id: result.signal_id,
                ...data,
                created_at: new Date().toISOString(),
                status: 'active',
                orders: result.orders,
                margin_used: result.total_margin_used
            });
            localStorage.setItem('trading_signals', JSON.stringify(signals));
            
            // Update displays
            this.updateRecentSignals();
            // Refresh balance only on home page and trade page
            if (window.location.pathname.includes('home.php') || window.location.pathname === '/' || window.location.pathname === '/index.php' || window.location.pathname.includes('trade.php')) {
                this.loadBalanceData();
            }
            
            return result;
            
        } catch (error) {
            console.error('Order placement error:', error);
            throw error;
        }
    }

    async addToWatchlist() {
        // Validate that symbol is filled
        const symbolEl = document.getElementById('symbol');
        if (!symbolEl.value.trim()) {
            this.showNotification('Symbol is required to add to watchlist', 'error');
            symbolEl.focus();
            return;
        }

        // Validate symbol format
        const symbolRegex = /^[A-Z]{1,10}$/;
        if (!symbolRegex.test(symbolEl.value.toUpperCase())) {
            this.showNotification('Symbol should be 1-10 letters (e.g., BTC, ADA, ETH)', 'error');
            symbolEl.focus();
            return;
        }

        // Get direction
        const directionEl = document.querySelector('input[name="direction"]:checked');
        if (!directionEl) {
            this.showNotification('Direction (Long/Short) is required', 'error');
            return;
        }

        const symbol = symbolEl.value.toUpperCase();
        const direction = directionEl.value;
        const watchlistItems = [];
        
        // Get current market price from the form to use as initial_price
        const marketPrice = parseFloat(document.getElementById('entry_market').value) || null;

        // Check entry 2 if it has values and checkbox is checked
        let entry2Checkbox = document.getElementById('entry_2_enabled');
        const entry2Value = document.getElementById('entry_2').value;
        const entry2Margin = document.getElementById('entry_2_margin').value;
        if (entry2Checkbox && entry2Checkbox.checked && entry2Value && entry2Margin) {
            const entry2Price = parseFloat(entry2Value);
            const entry2MarginNum = parseFloat(entry2Margin) || 0;
            const entry2Percent = parseFloat(document.getElementById('entry_2_percent').value) || null;

            if (entry2Price > 0) {
                watchlistItems.push({
                    entry_type: 'entry_2',
                    entry_price: entry2Price,
                    margin_amount: entry2MarginNum,
                    percentage: entry2Percent,
                    initial_price: marketPrice
                });
            }
        }

        // Check entry 3 if it has values and checkbox is checked
        let entry3Checkbox = document.getElementById('entry_3_enabled');
        const entry3Value = document.getElementById('entry_3').value;
        const entry3Margin = document.getElementById('entry_3_margin').value;
        if (entry3Checkbox && entry3Checkbox.checked && entry3Value && entry3Margin) {
            const entry3Price = parseFloat(entry3Value);
            const entry3MarginNum = parseFloat(entry3Margin) || 0;
            const entry3Percent = parseFloat(document.getElementById('entry_3_percent').value) || null;

            if (entry3Price > 0) {
                watchlistItems.push({
                    entry_type: 'entry_3',
                    entry_price: entry3Price,
                    margin_amount: entry3MarginNum,
                    percentage: entry3Percent,
                    initial_price: marketPrice
                });
            }
        }

        if (watchlistItems.length === 0) {
            this.showNotification('At least one entry point (Entry 2 or Entry 3) must have both margin and price values', 'error');
            return;
        }

        // Validate entry values for entries with values
        let validationErrors = [];

        // Check entry 2 validation if it has values
        if (entry2Value && entry2Margin) {
            const entry2Price = parseFloat(entry2Value) || 0;
            const entry2MarginNum = parseFloat(entry2Margin) || 0;
            const entry2Percent = parseFloat(document.getElementById('entry_2_percent').value) || 0;

            if (entry2Price <= 0) {
                validationErrors.push('Entry 2 price must be greater than 0');
            }
            if (entry2MarginNum <= 0) {
                validationErrors.push('Entry 2 order value must be greater than 0');
            }
            if (entry2Percent === 0) {
                validationErrors.push('Entry 2 percentage cannot be 0');
            }
        }

        // Check entry 3 validation if it has values
        if (entry3Value && entry3Margin) {
            const entry3Price = parseFloat(entry3Value) || 0;
            const entry3MarginNum = parseFloat(entry3Margin) || 0;
            const entry3Percent = parseFloat(document.getElementById('entry_3_percent').value) || 0;

            if (entry3Price <= 0) {
                validationErrors.push('Entry 3 price must be greater than 0');
            }
            if (entry3MarginNum <= 0) {
                validationErrors.push('Entry 3 order value must be greater than 0');
            }
            if (entry3Percent === 0) {
                validationErrors.push('Entry 3 percentage cannot be 0');
            }
        }

        if (validationErrors.length > 0) {
            this.showNotification(validationErrors[0], 'error'); // Show first error
            return;
        }

        try {
            const requestData = {
                symbol: symbol,
                direction: direction,
                watchlist_items: watchlistItems
            };
            
            // Debug logging
            console.log('Sending to watchlist API:', requestData);
            
            // Send to database
            const response = await fetch('api/watchlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                
                // Update watchlist display
                this.updateWatchlistDisplay();
                
                // Clear the form after adding to watchlist
                this.clearWatchlistFields();
            } else {
                throw new Error(result.error || 'Failed to add to watchlist');
            }

        } catch (error) {
            console.error('Watchlist API Error:', error);
            this.showNotification('Failed to add to watchlist: ' + error.message, 'error');
        }
    }

    resetForm() {
        // Confirm before resetting
        if (confirm('Are you sure you want to reset all form fields? This action cannot be undone.')) {
            // Reset the entire form
            if (this.form) {
                this.form.reset();
            }
            
            // Reset direction to long (default)
            const longRadio = document.querySelector('input[name="direction"][value="long"]');
            if (longRadio) longRadio.checked = true;
            
            // Update button state
            this.updateSubmitButton();
            
            // Update account info
            this.updateAccountInfo();
            
            // Show success notification
            this.showNotification('Form has been reset', 'success');
        }
    }

    clearWatchlistFields() {
        // Clear entry points 2 and 3 if they have values
        const entry2Percent = document.getElementById('entry_2_percent');
        const entry2Price = document.getElementById('entry_2');
        const entry3Percent = document.getElementById('entry_3_percent');
        const entry3Price = document.getElementById('entry_3');
        
        if (entry2Percent) entry2Percent.value = '';
        if (entry2Price) entry2Price.value = '';
        if (entry3Percent) entry3Percent.value = '';
        if (entry3Price) entry3Price.value = '';
    }

    autoSave() {
        const data = this.getFormData();
        if (Object.keys(data).some(key => data[key])) {
            localStorage.setItem('trading_signal_draft', JSON.stringify(data));
        }
    }

    loadDraft() {
        const draft = localStorage.getItem('trading_signal_draft');
        if (draft) {
            const data = JSON.parse(draft);
            
            Object.keys(data).forEach(key => {
                const field = document.getElementById(key);
                if (field) {
                    if (field.type === 'radio') {
                        const radio = document.querySelector(`input[name="${key}"][value="${data[key]}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        field.value = data[key];
                    }
                }
            });
            
            this.updateAccountInfo();
        }
    }

    async updateRecentSignals() {
        try {
            // Clear localStorage of failed signals first
            this.cleanupFailedSignals();
            
            // Try to fetch actual positions from database first
            const response = await fetch('api/get_orders.php?type=positions&status=OPEN&limit=50');
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success) {
                    this.displayRecentPositions(result.data || []);
                    return;
                }
            }
        } catch (error) {
            console.warn('Failed to fetch recent signals from API, using localStorage:', error);
        }
        
        // Fallback to localStorage - also filter out failed orders
        const signals = JSON.parse(localStorage.getItem('trading_signals') || '[]');
        const filledSignals = signals.filter(signal => {
            // Only show signals that have successful orders or are from localStorage (older format)
            return !signal.orders || signal.orders.some(order => order.success);
        });
        
        // Always call displayRecentPositions, even if empty
        this.displayRecentSignals(filledSignals.slice(0, 50));
    }
    
    cleanupFailedSignals() {
        try {
            const signals = JSON.parse(localStorage.getItem('trading_signals') || '[]');
            const successfulSignals = signals.filter(signal => {
                // Keep signals that have successful orders or no order info (old format)
                return !signal.orders || signal.orders.some(order => order.success);
            });
            localStorage.setItem('trading_signals', JSON.stringify(successfulSignals));
        } catch (error) {
            console.warn('Error cleaning up failed signals:', error);
        }
    }
    
    displayRecentPositions(positions) {
        const positionList = document.getElementById('signal-list');
        if (!positionList) return;
        
        if (positions.length === 0) {
            positionList.innerHTML = '<p class="no-signals">No active positions</p>';
            return;
        }

        positionList.innerHTML = positions.map(position => {
            // Handle both database positions and localStorage signals
            const direction = position.side || position.signal_type || 'UNKNOWN';
            const rawSymbol = position.symbol || 'UNKNOWN';
            const symbol = rawSymbol.replace('-USDT', '').replace('USDT', '');
            const leverage = position.leverage || 1;
            const openedAt = position.opened_at || position.created_at || position.timestamp;
            const marginUsed = position.margin_used ? parseFloat(position.margin_used).toFixed(2) : '0.00';
            const pnl = position.unrealized_pnl ? parseFloat(position.unrealized_pnl).toFixed(2) : '0.00';
            const pnlClass = parseFloat(pnl) >= 0 ? 'profit' : 'loss';
            
            // Calculate P&L percentage based on margin used
            const pnlPercent = parseFloat(marginUsed) > 0 ? ((parseFloat(pnl) / parseFloat(marginUsed)) * 100).toFixed(2) : '0.00';
            const pnlPercentClass = parseFloat(pnlPercent) >= 0 ? 'profit' : 'loss';
            
            // Get planned Stop Loss and Take Profit values from signals table
            const entryPrice = position.entry_price ? parseFloat(position.entry_price) : 0;
            const marginValue = parseFloat(marginUsed);
            const leverageValue = parseInt(leverage);
            
            let stopLossValue = 0;
            let takeProfitValue = 0;
            let stopLossPercentDisplay = 'N/A';
            let takeProfitPercentDisplay = 'N/A';
            
            // Use planned SL from signals table
            if (position.stop_loss && entryPrice > 0) {
                const slPrice = parseFloat(position.stop_loss);
                
                // Calculate dollar value using correct formula: margin × leverage × price change %
                const priceChangePercent = (Math.abs(slPrice - entryPrice) / entryPrice) * 100;
                const slDollarValue = (marginValue * leverageValue * (priceChangePercent / 100));
                stopLossValue = slDollarValue;
                
                // Format display value - show leveraged percentage impact on margin
                const slPercentageImpact = (slDollarValue / marginValue) * 100;
                if (direction === 'LONG') {
                    stopLossPercentDisplay = slPrice < entryPrice ? `-${slPercentageImpact.toFixed(1)}%` : `+${slPercentageImpact.toFixed(1)}%`;
                } else {
                    stopLossPercentDisplay = slPrice > entryPrice ? `+${slPercentageImpact.toFixed(1)}%` : `-${slPercentageImpact.toFixed(1)}%`;
                }
            }
            
            // Use planned TP from signals table (prioritize take_profit_1)
            const takeProfitPrice = position.take_profit_1 || position.take_profit_2 || position.take_profit_3;
            if (takeProfitPrice && entryPrice > 0) {
                const tpPrice = parseFloat(takeProfitPrice);
                
                // Calculate dollar value using correct formula: margin × leverage × price change %
                const priceChangePercent = (Math.abs(tpPrice - entryPrice) / entryPrice) * 100;
                const tpDollarValue = (marginValue * leverageValue * (priceChangePercent / 100));
                takeProfitValue = tpDollarValue;
                
                // Format display value - show leveraged percentage impact on margin
                const tpPercentageImpact = (tpDollarValue / marginValue) * 100;
                if (direction === 'LONG') {
                    takeProfitPercentDisplay = tpPrice > entryPrice ? `+${tpPercentageImpact.toFixed(1)}%` : `-${tpPercentageImpact.toFixed(1)}%`;
                } else {
                    takeProfitPercentDisplay = tpPrice < entryPrice ? `-${tpPercentageImpact.toFixed(1)}%` : `+${tpPercentageImpact.toFixed(1)}%`;
                }
            }
            
            // Calculate time ago - handle various date formats
            let timeAgo = 'Unknown time';
            if (openedAt) {
                try {
                    timeAgo = this.getTimeAgo(openedAt);
                } catch (e) {
                    timeAgo = 'Invalid date';
                }
            }
            
            // Skip positions with invalid or missing data
            if (!symbol || symbol === 'UNKNOWN' || !direction || direction === 'UNKNOWN') {
                return '';
            }
            
            // Check if position has notes
            const notes = position.notes && position.notes.trim() ? position.notes.trim() : null;
            const notesIcon = notes ? `<span class="position-notes-icon" title="${notes.replace(/"/g, '&quot;')}">ℹ️</span>` : '';
            
            // Determine if position is demo or live
            // Handle missing is_demo column gracefully - check multiple possible values
            let isDemo = false;
            if (position.hasOwnProperty('is_demo')) {
                isDemo = position.is_demo === 1 || position.is_demo === '1' || position.is_demo === true || position.is_demo === 'true';
            } else {
                // Fallback: Check if we're in demo mode based on current trading settings
                // This will show LIVE for existing positions without is_demo column
                // New positions will have the column properly set
                isDemo = false; // Default to LIVE for existing positions
                console.warn('Position ID ' + position.id + ' missing is_demo column, defaulting to LIVE mode');
            }
            
            const modeIndicator = isDemo ? 
                `<span class="mode-indicator demo-mode" title="Demo trading">DEMO</span>` : 
                `<span class="mode-indicator live-mode" title="Live trading">LIVE</span>`;
            
            return `
                <div class="signal-item position-item ${isDemo ? 'demo-position' : 'live-position'}" data-position-id="${positionId}">
                    <div class="position-progress-bar">
                        <div class="position-progress-fill ${pnlPercent >= 0 ? 'positive' : 'negative'}" data-pnl="${pnlPercent}"></div>
                    </div>
                    <div class="position-item-content">
                        <div class="signal-item-header">
                            <div class="symbol-with-notes">
                                <strong class="signal-symbol ${direction?.toLowerCase()}">${symbol}</strong>
                                ${notesIcon}
                                ${modeIndicator}
                            </div>
                            <span class="signal-time">${timeAgo}</span>
                        </div>
                        <div class="signal-details">
                            ${direction?.toUpperCase()} • ${leverage}x • Margin: $${marginUsed}
                        </div>
                        <div class="position-pnl ${pnlClass}">
                            P&L: $${pnl} (<span class="${pnlPercentClass}">${pnlPercent}%</span>)
                        </div>
                        <div class="position-sl-tp">
                            <span class="sl-value">SL: $${stopLossValue.toFixed(1)} (${stopLossPercentDisplay})</span> • <span class="tp-value">TP: $${takeProfitValue.toFixed(1)} (${takeProfitPercentDisplay})</span> <span class="tp-info-icon" onclick="tradingForm.showTPPopover(${position.id}, '${symbol}', ${entryPrice}, ${leverageValue}, ${marginValue}, '${direction}', '${JSON.stringify({tp1: position.take_profit_1, tp2: position.take_profit_2, tp3: position.take_profit_3, sl: position.stop_loss}).replace(/"/g, '&quot;')}')" title="Show all targets">ⓘ</span>
                        </div>
                        <div class="position-actions">
                            <button 
                                class="chart-btn chart-btn-bottom"
                                onclick="tradingForm.showChart('${symbol}')" 
                                title="Show ${symbol} chart"
                            >📊</button>
                            ${this.getPositionButton(position.id, symbol, direction, isDemo)}
                        </div>
                    </div>
                </div>
            `;
        }).filter(html => html !== '').join('');
        
        // Update progress bars after rendering
        setTimeout(() => this.updatePositionProgressBars(), 100);
    }

    getPositionButton(positionId, symbol, direction, isDemo) {
        const status = this.positionStatus[positionId];
        const modeText = isDemo ? 'Demo' : 'Live';
        
        // If position status is unknown or exists on exchange, show Close button
        if (!status || status.exists_on_exchange) {
            return `
                <button 
                    class="position-close-btn"
                    onclick="tradingForm.closePosition(${positionId}, '${symbol}', '${direction}', ${isDemo})" 
                    title="Close position on ${modeText} exchange"
                >Close Position</button>
            `;
        } else {
            // Position doesn't exist on exchange, show Remove button
            return `
                <button 
                    class="position-remove-btn"
                    onclick="tradingForm.removePosition(${positionId}, '${symbol}', '${direction}', ${isDemo})" 
                    title="Remove position (not on ${modeText} exchange)"
                >Remove</button>
            `;
        }
    }
    
    displayRecentSignals(signals) {
        // Fallback function for localStorage data
        this.displayRecentPositions(signals);
    }

    async refreshPositions() {
        this.showNotification('Refreshing positions...', 'info');
        
        try {
            // Check positions status against exchange
            const checkResponse = await fetch('api/check_positions.php');
            if (checkResponse.ok) {
                const checkResult = await checkResponse.json();
                if (checkResult.success) {
                    this.positionStatus = checkResult.position_status;
                }
            }
        } catch (error) {
            console.warn('Position status check failed:', error);
            this.positionStatus = {};
        }
        
        // Then update the positions list
        await this.updateRecentSignals();
    }

    async closePosition(positionId, symbol, direction, isDemo = false) {
        const modeText = isDemo ? 'Demo' : 'Live';
        if (!confirm(`Are you sure you want to close your ${direction} position on ${symbol}? (${modeText} mode)`)) {
            return;
        }

        try {
            this.showNotification(`Closing ${symbol} ${direction} position (${modeText} mode)...`, 'info');
            
            // Call API to close position
            const response = await fetch('api/close_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    position_id: positionId,
                    symbol: symbol,
                    direction: direction,
                    is_demo: isDemo
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.showNotification(`Position closed successfully: ${result.message}`, 'success');
                // Refresh positions list and balance
                this.updateRecentSignals();
                if (window.location.pathname.includes('home.php') || window.location.pathname === '/' || window.location.pathname === '/index.php' || window.location.pathname.includes('trade.php')) {
                    this.loadBalanceData();
                }
            } else {
                throw new Error(result.error || 'Failed to close position');
            }

        } catch (error) {
            console.error('Close position error:', error);
            this.showNotification(`Failed to close position: ${error.message}`, 'error');
        }
    }

    async removePosition(positionId, symbol, direction, isDemo = false) {
        const modeText = isDemo ? 'Demo' : 'Live';
        if (!confirm(`Remove ${direction} position on ${symbol}? This position no longer exists on the ${modeText} exchange.`)) {
            return;
        }

        try {
            this.showNotification(`Removing ${symbol} ${direction} position (${modeText} mode)...`, 'info');
            
            // Call API to mark position as closed
            const response = await fetch('api/close_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    position_id: positionId,
                    symbol: symbol,
                    direction: direction,
                    is_demo: isDemo
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.showNotification(`Position removed: ${result.message}`, 'success');
                // Refresh positions list and balance
                this.updateRecentSignals();
                if (window.location.pathname.includes('home.php') || window.location.pathname === '/' || window.location.pathname === '/index.php' || window.location.pathname.includes('trade.php')) {
                    this.loadBalanceData();
                }
            } else {
                throw new Error(result.error || 'Failed to remove position');
            }

        } catch (error) {
            console.error('Remove position error:', error);
            this.showNotification(`Failed to remove position: ${error.message}`, 'error');
        }
    }

    async closeAllPositions() {
        // Get all active positions from the current display
        const positionElements = document.querySelectorAll('.signal-item, .position-item');
        const activePositions = [];
        
        positionElements.forEach(element => {
            // Skip if it's the "no positions" message
            if (element.querySelector('.no-signals')) return;
            
            const symbolElement = element.querySelector('.signal-symbol, .watchlist-symbol');
            const directionElement = element.querySelector('.signal-details, .watchlist-direction');
            
            if (symbolElement && directionElement) {
                const symbol = symbolElement.textContent.trim();
                const directionText = directionElement.textContent || directionElement.innerText;
                let direction = 'LONG'; // default
                
                // Extract direction from text (could be "LONG • 10x • Margin: $100" format)
                if (directionText.includes('SHORT') || directionText.toLowerCase().includes('short')) {
                    direction = 'SHORT';
                } else if (directionText.includes('LONG') || directionText.toLowerCase().includes('long')) {
                    direction = 'LONG';
                }
                
                // Get position ID from element data attribute or onclick handler
                const positionId = element.dataset.positionId || 
                                 element.querySelector('[data-position-id]')?.dataset.positionId ||
                                 this.extractPositionIdFromElement(element);
                
                console.log(`🔍 Position extraction for ${symbol}: ID=${positionId}, Direction=${direction}`);
                
                if (positionId && symbol) {
                    activePositions.push({
                        id: positionId,
                        symbol: symbol,
                        direction: direction,
                        element: element
                    });
                } else {
                    console.log(`❌ Skipping position ${symbol} - missing ID or symbol`);
                }
            }
        });

        if (activePositions.length === 0) {
            this.showNotification('No active positions to close', 'info');
            return;
        }

        // Confirmation dialog
        const positionText = activePositions.length === 1 ? '1 position' : `${activePositions.length} positions`;
        const confirmMessage = `Are you sure you want to close all ${positionText}?\n\n` +
                              activePositions.map(p => `${p.symbol} ${p.direction}`).join('\n');
        
        if (!confirm(confirmMessage)) {
            return;
        }

        // Start closing all positions
        this.showNotification(`Closing ${positionText}...`, 'info');
        let successCount = 0;
        let failCount = 0;

        for (const position of activePositions) {
            try {
                // Determine if it's demo mode (you may need to adjust this logic based on your app)
                const isDemo = position.element.textContent.includes('Demo') || 
                               position.element.querySelector('.demo-indicator');
                
                console.log(`🔄 Closing position: ID=${position.id}, Symbol=${position.symbol}, Direction=${position.direction}, Demo=${isDemo}`);
                
                const requestBody = {
                    position_id: position.id,
                    symbol: position.symbol,
                    direction: position.direction,
                    is_demo: isDemo || false
                };
                
                console.log('📤 Request payload:', requestBody);
                
                const response = await fetch('api/close_position.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestBody)
                });

                const result = await response.json();
                console.log(`📥 Response for ${position.symbol}:`, result);
                
                if (response.ok && result.success) {
                    successCount++;
                    console.log(`✅ Closed ${position.symbol} ${position.direction} - ${result.message}`);
                } else {
                    failCount++;
                    console.error(`❌ Failed to close ${position.symbol} ${position.direction}: HTTP ${response.status}`, result.error);
                }
            } catch (error) {
                failCount++;
                console.error(`❌ Error closing ${position.symbol} ${position.direction}:`, error);
            }

            // Small delay between requests to avoid overwhelming the API
            await new Promise(resolve => setTimeout(resolve, 500)); // Increased delay
        }

        // Show final result
        if (successCount > 0 && failCount === 0) {
            this.showNotification(`Successfully closed all ${successCount} positions`, 'success');
        } else if (successCount > 0 && failCount > 0) {
            this.showNotification(`Closed ${successCount} positions, ${failCount} failed`, 'warning');
        } else {
            this.showNotification(`Failed to close positions`, 'error');
        }

        // Refresh the positions list
        setTimeout(() => {
            this.updateRecentSignals();
            if (window.location.pathname.includes('home.php') || window.location.pathname === '/' || window.location.pathname === '/index.php' || window.location.pathname.includes('trade.php')) {
                this.loadBalanceData();
            }
        }, 1000);
    }

    // Helper function to extract position ID from element
    extractPositionIdFromElement(element) {
        // Try to find position ID in onclick handlers of child buttons
        const buttons = element.querySelectorAll('button[onclick]');
        for (const button of buttons) {
            const onclick = button.getAttribute('onclick');
            if (onclick && (onclick.includes('closePosition') || onclick.includes('removePosition'))) {
                const match = onclick.match(/\((\d+),/);
                if (match) {
                    console.log(`🔍 Extracted position ID: ${match[1]} from onclick: ${onclick}`);
                    return match[1];
                }
            }
        }
        console.log('❌ No position ID found in element onclick handlers');
        return null;
    }

    getTimeAgo(dateString) {
        const now = new Date();
        const past = new Date(dateString);
        const diffMs = now - past;
        const diffMinutes = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        
        if (diffMinutes < 1) {
            return 'Just now';
        } else if (diffMinutes < 60) {
            return `${diffMinutes}m ago`;
        } else if (diffHours < 24) {
            const remainingMinutes = diffMinutes % 60;
            if (remainingMinutes === 0) {
                return `${diffHours}h ago`;
            } else {
                return `${diffHours}h ${remainingMinutes}m ago`;
            }
        } else if (diffDays < 7) {
            return `${diffDays}d ago`;
        } else {
            return past.toLocaleDateString();
        }
    }

    async updateWatchlistDisplay() {
        try {
            const response = await fetch('api/watchlist.php?limit=50');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            const watchlistContainer = document.getElementById('watchlist-items');
            if (!watchlistContainer) return;
            
            if (!result.success || result.data.length === 0) {
                watchlistContainer.innerHTML = '<p class="no-watchlist">No watchlist items</p>';
                return;
            }

            const watchlist = result.data;
            
            watchlistContainer.innerHTML = watchlist.map(item => {
                const entryTypeLabel = item.entry_type === 'entry_2' ? 'Entry 2' : 'Entry 3';
                const directionClass = item.direction;
                const directionText = item.direction.toUpperCase();
                const timeAgo = this.getTimeAgo(item.created_at);
                const percentageDisplay = item.percentage ? `${parseFloat(item.percentage).toFixed(1)}%` : 'No %';
                
                return `
                    <div class="watchlist-item" data-id="${item.id}">
                        <div class="watchlist-progress-bar">
                            <div class="watchlist-progress-fill" data-direction="${item.direction}"></div>
                        </div>
                        <div class="watchlist-item-content">
                            <div class="watchlist-item-header">
                            <div class="watchlist-symbol-container">
                                <strong class="watchlist-symbol ${directionClass}">${item.symbol}</strong>
                                <span class="watchlist-close-indicator" style="display: none;"></span>
                            </div>
                            <span class="watchlist-time">${timeAgo}</span>
                        </div>
                        <div class="watchlist-price-row">
                            <div class="watchlist-entry-info">
                                <strong>${entryTypeLabel}:</strong> $${parseFloat(item.entry_price).toFixed(5)}
                            </div>
                            <div class="watchlist-direction ${directionClass}">
                                ${directionText}
                            </div>
                        </div>
                        <div class="watchlist-current-price">
                            <span class="price-info">Loading price...</span>
                        </div>
                        <div class="watchlist-bottom-row">
                            <span>Margin: $${parseFloat(item.margin_amount).toFixed(2)}</span>
                            <span>${percentageDisplay}</span>
                        </div>
                        <div class="watchlist-actions">
                            <button 
                                class="chart-btn chart-btn-watchlist"
                                onclick="tradingForm.showChart('${item.symbol}')" 
                                title="Show ${item.symbol} chart"
                            >📊</button>
                            <div class="watchlist-right-buttons">
                                <button 
                                    class="watchlist-open-position-btn ${item.direction}"
                                    onclick="tradingForm.openWatchlistPosition('${item.symbol}', '${item.direction}', ${item.entry_price}, ${item.id})" 
                                    title="Open ${item.direction.charAt(0).toUpperCase() + item.direction.slice(1)}"
                                >Open ${item.direction.charAt(0).toUpperCase() + item.direction.slice(1)}</button>
                                <button 
                                    class="watchlist-remove-btn"
                                    onclick="tradingForm.removeWatchlistItem(${item.id})" 
                                    title="Remove from watchlist"
                                >❌</button>
                            </div>
                        </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Auto-refresh prices after displaying items
            this.refreshWatchlistPrices(false);

        } catch (error) {
            console.error('Error loading watchlist:', error);
            const watchlistContainer = document.getElementById('watchlist-items');
            if (watchlistContainer) {
                watchlistContainer.innerHTML = '<p class="no-watchlist">Error loading watchlist</p>';
            }
        }
    }

    async refreshWatchlist() {
        this.showNotification('Refreshing watchlist prices...', 'info');
        await this.refreshWatchlistPrices(true);
    }

    async refreshWatchlistPrices(showNotification = true) {
        try {
            console.log('🔄 Refreshing watchlist prices...');
            const response = await fetch('api/get_watchlist_prices.php', {
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
            
            if (!result.success) {
                console.error('❌ API returned error:', result.error);
                throw new Error(result.error || 'Failed to fetch prices');
            }

            const priceData = result.data;
            console.log('💰 Price data received:', priceData);
            
            // Update each watchlist item with current price and distance
            priceData.forEach(item => {
                console.log(`🔍 Processing item ${item.id}:`, item);
                
                const watchlistElement = document.querySelector(`[data-id="${item.id}"]`);
                if (!watchlistElement) {
                    console.warn(`⚠️ No DOM element found for item ${item.id}`);
                    return;
                }

                const priceInfoElement = watchlistElement.querySelector('.price-info');
                const closeIndicator = watchlistElement.querySelector('.watchlist-close-indicator');
                
                console.log(`💲 Item ${item.id} - Price status: ${item.price_status}, Current price: ${item.current_price}`);
                
                if (item.price_status === 'available' && item.current_price !== null) {
                    const distanceClass = item.distance_percent >= 0 ? 'positive' : 'negative';
                    const distanceSign = item.distance_percent >= 0 ? '+' : '';
                    
                    priceInfoElement.innerHTML = `
                        Current: $${parseFloat(item.current_price).toFixed(5)} 
                        <span class="watchlist-distance ${distanceClass}">
                            (${distanceSign}${item.distance_percent}%)
                        </span>
                    `;
                    
                    // Show/hide indicator based on alert status
                    if (item.alert_status === 'close') {
                        // Price is within 0.1% before reaching target - show blinking
                        closeIndicator.style.display = 'inline-block';
                        closeIndicator.classList.add('close');
                        closeIndicator.classList.remove('reached');
                    } else if (item.alert_status === 'reached') {
                        // Price has reached/passed target - show solid circle
                        closeIndicator.style.display = 'inline-block';
                        closeIndicator.classList.add('reached');
                        closeIndicator.classList.remove('close');
                    } else {
                        // Normal state - hide indicator
                        closeIndicator.style.display = 'none';
                        closeIndicator.classList.remove('close', 'reached');
                    }
                    
                    // Update progress bar
                    this.updateProgressBar(watchlistElement, item);
                } else {
                    console.warn(`❌ Item ${item.id} - Price unavailable! Status: ${item.price_status}, Price: ${item.current_price}`);
                    priceInfoElement.innerHTML = 'Price unavailable';
                    closeIndicator.style.display = 'none';
                    closeIndicator.classList.remove('close', 'reached');
                    
                    // Reset progress bar when price unavailable
                    const progressFill = watchlistElement.querySelector('.watchlist-progress-fill');
                    if (progressFill) {
                        progressFill.style.height = '0%';
                    }
                }
            });

            if (showNotification) {
                this.showNotification('Watchlist prices updated', 'success');
            }

        } catch (error) {
            console.error('💥 Error refreshing watchlist prices:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            
            // Update all price info elements to show error
            document.querySelectorAll('.price-info').forEach(element => {
                element.innerHTML = 'Error loading price';
            });
            
            if (showNotification) {
                this.showNotification('Failed to refresh prices: ' + error.message, 'error');
            }
        }
    }

    async removeWatchlistItem(id) {
        try {
            const response = await fetch(`api/watchlist.php/${id}`, {
                method: 'DELETE'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.showNotification('Removed from watchlist', 'success');
                this.updateWatchlistDisplay();
            } else {
                throw new Error(result.error || 'Failed to remove from watchlist');
            }

        } catch (error) {
            console.error('Error removing watchlist item:', error);
            this.showNotification('Failed to remove from watchlist', 'error');
        }
    }

    updateProgressBar(watchlistElement, item) {
        const progressFill = watchlistElement.querySelector('.watchlist-progress-fill');
        if (!progressFill) return;

        const currentPrice = parseFloat(item.current_price);
        const targetPrice = parseFloat(item.entry_price);
        const initialPrice = item.initial_price ? parseFloat(item.initial_price) : null;
        
        // Debug logging
        console.log('Progress calculation:', {
            symbol: item.symbol,
            direction: item.direction,
            currentPrice,
            targetPrice,
            initialPrice
        });
        
        let progress = 0;
        
        // Calculate progress based on remaining distance to target
        // Progress bar shows how much we've moved toward the target
        
        const dbPercentage = parseFloat(item.percentage) || 5; // Default to 5% if no percentage set
        
        // Calculate signed distance percentage (positive for short, negative for long when moving toward target)
        let distancePercent;
        if (item.direction === 'short') {
            // For short: positive distance means we need price to go UP to reach target
            distancePercent = ((targetPrice - currentPrice) / currentPrice) * 100;
        } else {
            // For long: negative distance means we need price to go DOWN to reach target  
            distancePercent = ((targetPrice - currentPrice) / currentPrice) * 100;
        }
        
        // Use absolute value for progress calculation (ignore sign for long positions)
        const absDistancePercent = Math.abs(distancePercent);
        
        console.log('Progress calculation:', {
            direction: item.direction,
            currentPrice,
            targetPrice,
            dbPercentage,
            distancePercent,
            absDistancePercent
        });
        
        if (absDistancePercent >= dbPercentage) {
            // Haven't started moving toward target yet - 0% progress
            progress = 0;
        } else {
            // Moving toward target - calculate how much progress made
            // Progress = (dbPercentage - remainingDistance) / dbPercentage * 100
            progress = Math.max(0, ((dbPercentage - absDistancePercent) / dbPercentage) * 100);
        }
        
        console.log('Final progress:', progress + '%');
        progressFill.style.height = `${progress}%`;
    }

    updatePositionProgressBars() {
        const progressBars = document.querySelectorAll('.position-progress-fill');
        
        progressBars.forEach(progressFill => {
            const pnlPercent = parseFloat(progressFill.getAttribute('data-pnl'));
            
            console.log('Position progress calculation:', { pnlPercent });
            
            let progress = 0;
            
            if (pnlPercent >= 0) {
                // Positive PnL: green, bottom-to-top, target 10%
                progress = Math.min(100, (pnlPercent / 10) * 100);
                progressFill.style.height = `${progress}%`;
                progressFill.style.top = 'auto';
                progressFill.style.bottom = '0';
            } else {
                // Negative PnL: red, top-to-bottom, target -10%
                progress = Math.min(100, (Math.abs(pnlPercent) / 10) * 100);
                progressFill.style.height = `${progress}%`;
                progressFill.style.top = '0';
                progressFill.style.bottom = 'auto';
            }
            
            console.log('Position final progress:', progress + '%', 'PnL:', pnlPercent + '%');
        });
    }

    // ===== LIMIT ORDERS METHODS (Mirror of Watchlist Methods) =====
    





    setupSignalPatternConverter() {
        const patternBtn = document.getElementById('signal-pattern-btn');
        const patternContainer = document.getElementById('signal-pattern-container');
        const parseBtn = document.getElementById('parse-signal-btn');
        const closeBtn = document.getElementById('close-signal-pattern-btn');
        const patternInput = document.getElementById('signal-pattern-input');
        
        if (patternBtn && patternContainer && parseBtn && closeBtn && patternInput) {
            // Toggle textarea visibility
            patternBtn.addEventListener('click', () => {
                const isVisible = patternContainer.style.display !== 'none';
                patternContainer.style.display = isVisible ? 'none' : 'block';
                if (!isVisible) {
                    patternInput.focus();
                }
            });
            
            // Close textarea
            closeBtn.addEventListener('click', () => {
                patternContainer.style.display = 'none';
                patternInput.value = '';
            });
            
            // Parse signal button
            parseBtn.addEventListener('click', () => {
                this.parseSignalPattern(patternInput.value);
            });
            
            // Parse on Enter key (Ctrl+Enter for new line)
            patternInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.ctrlKey) {
                    e.preventDefault();
                    this.parseSignalPattern(patternInput.value);
                }
            });
        }
    }
    
    parseSignalPattern(signalText) {
        if (!signalText.trim()) {
            this.showNotification('Please paste a signal pattern first', 'error');
            return;
        }
        
        try {
            const parsedData = this.extractSignalData(signalText);
            this.populateFormWithSignal(parsedData);
            
            // Close the pattern input
            const patternContainer = document.getElementById('signal-pattern-container');
            const patternInput = document.getElementById('signal-pattern-input');
            if (patternContainer && patternInput) {
                patternContainer.style.display = 'none';
                patternInput.value = '';
            }
            
            this.showNotification('Signal pattern parsed and applied!', 'success');
        } catch (error) {
            console.error('Signal parsing error:', error);
            this.showNotification('Failed to parse signal pattern: ' + error.message, 'error');
        }
    }
    
    extractSignalData(signalText) {
        const data = {
            symbol: null,
            direction: 'long',
            leverage: 2,
            entries: [],
            targets: [],
            stopLoss: null
        };
        
        // Extract symbol - look for patterns like "رمزارز SYMBOL" or "رمزارز  SYMBOL"
        const symbolMatch = signalText.match(/رمزارز\s+([A-Z]+)/);
        if (symbolMatch) {
            data.symbol = symbolMatch[1];
        }
        
        // Determine direction based on keywords
        if (signalText.includes('شورت') || signalText.includes('short')) {
            data.direction = 'short';
        } else if (signalText.includes('لانگ') || signalText.includes('long')) {
            data.direction = 'long';
        } else if (signalText.includes('📉')) {
            data.direction = 'short';
        } else if (signalText.includes('📈')) {
            data.direction = 'long';
        }
        
        // Extract leverage - look for "لوریج X" or "leverage X"
        const leverageMatch = signalText.match(/لوریج\s+(\d+)|leverage\s+(\d+)/i);
        if (leverageMatch) {
            data.leverage = parseInt(leverageMatch[1] || leverageMatch[2]);
        }
        
        // Extract entry points - look for price patterns
        // Pattern: "در نقاط X و Y" or "در نقاط X, Y" or numbers separated by و
        const entrySection = signalText.match(/در نقاط\s+([0-9.,\s و]+)/);
        if (entrySection) {
            // Split by 'و' (and) or comma and extract numbers
            const entryPrices = entrySection[1]
                .replace(/و/g, ',')
                .split(',')
                .map(s => s.trim())
                .filter(s => s)
                .map(s => parseFloat(s))
                .filter(n => !isNaN(n));
            
            data.entries = entryPrices;
        }
        
        // Extract targets - look for target section
        const targetSection = signalText.match(/🎯تارگت:\s*\n?([0-9.\s\-,]+)/);
        if (targetSection) {
            const targetPrices = targetSection[1]
                .split(/[-,\s]+/)
                .map(s => s.trim())
                .filter(s => s)
                .map(s => parseFloat(s))
                .filter(n => !isNaN(n));
            
            data.targets = targetPrices;
        }
        
        // Extract stop loss - look for stop section
        const stopSection = signalText.match(/❌استاپ:\s*\n?([0-9.]+)/);
        if (stopSection) {
            data.stopLoss = parseFloat(stopSection[1]);
        }
        
        // Validation
        if (!data.symbol) {
            throw new Error('Could not find symbol in signal pattern');
        }
        
        if (data.entries.length === 0) {
            throw new Error('Could not find entry points in signal pattern');
        }
        
        return data;
    }
    
    populateFormWithSignal(data) {
        // Set symbol
        const symbolInput = document.getElementById('symbol');
        if (symbolInput && data.symbol) {
            symbolInput.value = data.symbol;
        }
        
        // Set direction
        const directionRadios = document.querySelectorAll('input[name="direction"]');
        directionRadios.forEach(radio => {
            if (radio.value === data.direction) {
                radio.checked = true;
            }
        });
        
        // Update submit button after setting direction
        this.updateSubmitButton();
        
        // Set leverage
        const leverageSelect = document.getElementById('leverage');
        if (leverageSelect && data.leverage) {
            leverageSelect.value = data.leverage.toString();
        }
        
        // Set entry points
        if (data.entries.length > 0) {
            // Calculate default margin based on 3.3% of total assets
            const totalAssets = this.totalAssets || 0;
            const defaultMargin = Math.ceil((totalAssets * 0.033)).toString();
            
            // Market entry (first entry)
            const marketEntry = document.getElementById('entry_market');
            const marketMargin = document.getElementById('entry_market_margin');
            if (marketEntry && data.entries[0]) {
                marketEntry.value = data.entries[0].toString();
            }
            if (marketMargin && defaultMargin) {
                marketMargin.value = defaultMargin;
            }
            
            // Entry 2 (second entry if available)
            if (data.entries.length > 1) {
                const entry2 = document.getElementById('entry_2');
                const entry2Margin = document.getElementById('entry_2_margin');
                if (entry2 && data.entries[1]) {
                    entry2.value = data.entries[1].toString();
                }
                // Set Entry 2 margin value same as Market Entry margin value
                if (entry2Margin && defaultMargin) {
                    entry2Margin.value = defaultMargin;
                }
            }
            
            // Entry 3 (third entry if available)  
            if (data.entries.length > 2) {
                const entry3 = document.getElementById('entry_3');
                if (entry3 && data.entries[2]) {
                    entry3.value = data.entries[2].toString();
                }
            }
        }
        
        // Set first target to target input and calculate percentage
        if (data.targets.length > 0 && data.entries.length > 0) {
            const firstTarget = data.targets[0];
            const marketPrice = data.entries[0];
            const leverage = data.leverage || 1;
            const direction = data.direction;
            
            const takeProfitInput = document.getElementById('take_profit');
            const takeProfitPercentInput = document.getElementById('take_profit_percent');
            
            if (takeProfitInput) {
                takeProfitInput.value = firstTarget.toString();
            }
            
            if (takeProfitPercentInput) {
                // Calculate price difference percentage
                let priceChangePercent;
                if (direction === 'long') {
                    priceChangePercent = ((firstTarget - marketPrice) / marketPrice) * 100;
                } else {
                    priceChangePercent = ((marketPrice - firstTarget) / marketPrice) * 100;
                }
                
                // Apply leverage to get P&L percentage
                const pnlPercent = (priceChangePercent * leverage).toFixed(1);
                takeProfitPercentInput.value = pnlPercent;
            }
        }
        
        // Set stop loss and calculate percentage
        if (data.stopLoss && data.entries.length > 0) {
            const marketPrice = data.entries[0];
            const leverage = data.leverage || 1;
            const direction = data.direction;
            
            const stopLossInput = document.getElementById('stop_loss');
            const stopLossPercentInput = document.getElementById('stop_loss_percent');
            
            if (stopLossInput) {
                stopLossInput.value = data.stopLoss.toString();
            }
            
            if (stopLossPercentInput) {
                // Calculate price difference percentage
                let priceChangePercent;
                if (direction === 'long') {
                    priceChangePercent = ((data.stopLoss - marketPrice) / marketPrice) * 100;
                } else {
                    priceChangePercent = ((marketPrice - data.stopLoss) / marketPrice) * 100;
                }
                
                // Apply leverage to get P&L percentage (negative for stop loss)
                const pnlPercent = (priceChangePercent * leverage).toFixed(1);
                stopLossPercentInput.value = pnlPercent;
            }
        }
        
        // Empty entry 3 values
        const entry3Input = document.getElementById('entry_3');
        const entry3MarginInput = document.getElementById('entry_3_margin');
        if (entry3Input) {
            entry3Input.value = '';
        }
        if (entry3MarginInput) {
            entry3MarginInput.value = '';
        }
        
        // Tick entry 2 checkbox and untick entry 3 checkbox
        let entry2Checkbox = document.getElementById('entry_2_enabled');
        let entry3Checkbox = document.getElementById('entry_3_enabled');
        
        if (entry2Checkbox) {
            entry2Checkbox.checked = true;
            // Trigger the change event to enable/disable inputs properly
            entry2Checkbox.dispatchEvent(new Event('change'));
        }
        
        if (entry3Checkbox) {
            entry3Checkbox.checked = false;
            // Trigger the change event to enable/disable inputs properly
            entry3Checkbox.dispatchEvent(new Event('change'));
        }
        
        // Add targets info to notes with percentage calculations
        if (data.targets.length > 0) {
            const notesInput = document.getElementById('notes');
            if (notesInput && data.entries.length > 0) {
                const marketPrice = data.entries[0]; // Use first entry as market price
                const leverage = data.leverage || 1;
                const direction = data.direction;
                
                const targetsList = data.targets.map((target, index) => {
                    // Calculate price difference percentage
                    let priceChangePercent;
                    if (direction === 'long') {
                        priceChangePercent = ((target - marketPrice) / marketPrice) * 100;
                    } else {
                        priceChangePercent = ((marketPrice - target) / marketPrice) * 100;
                    }
                    
                    // Apply leverage to get P&L percentage
                    const pnlPercent = (priceChangePercent * leverage).toFixed(1);
                    
                    return `Target ${index + 1}: ${target} (${pnlPercent}%)`;
                }).join('\n');
                notesInput.value = `Targets:\n${targetsList}`;
            }
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            left: 20px;
            padding: 16px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 400;
            font-size: 12px;
            z-index: 1000;
            transition: all 0.3s ease;
            background: ${type === 'success' ? 'var(--green-5)' : type === 'error' ? '#e74c3c' : 'var(--dark-surface)'};
            border: 1px solid ${type === 'success' ? 'var(--green-6)' : type === 'error' ? '#c0392b' : 'var(--dark-border)'};
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Remove after 4 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    // Initialize tab functionality
    initializeTabs() {
        const tabButtons = document.querySelectorAll('.watchlist-tab');
        const tabContents = document.querySelectorAll('.watchlist-tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                button.classList.add('active');
                const targetContent = document.getElementById(`${targetTab}-tab`);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
                
                // Load content for the active tab
                if (targetTab === 'limit-orders') {
                    // Load limit orders functionality here
                }
            });
        });
    }

    // Show chart popover functionality
    showChart(symbol) {
        console.log('showChart called with symbol:', symbol);
        
        // Create popover if it doesn't exist
        let popover = document.getElementById('chart-popover');
        if (!popover) {
            popover = document.createElement('div');
            popover.id = 'chart-popover';
            popover.className = 'chart-popover';
            document.body.appendChild(popover);
        }

        // Try different exchanges in order of preference for the symbol
        // Allow symbol editing so users can search for the correct symbol if needed
        const exchanges = ['BINANCE', 'BYBIT', 'OKX', 'KUCOIN', 'MEXC'];
        let symbolToUse = `BINANCE:${symbol}USDT`; // Default to Binance
        
        // For less common tokens, start with a more general search
        const isCommonToken = ['BTC', 'ETH', 'BNB', 'ADA', 'DOT', 'LINK', 'UNI', 'AVAX', 'MATIC', 'SOL'].includes(symbol);
        if (!isCommonToken) {
            // For uncommon tokens, use a search-friendly approach
            symbolToUse = `${symbol}USDT`;
        }
        
        const chartUrl = `https://www.tradingview.com/widgetembed/?frameElementId=tradingview_${symbol}&symbol=${encodeURIComponent(symbolToUse)}&interval=30&hidesidetoolbar=0&hidetoptoolbar=0&symboledit=1&saveimage=1&toolbarbg=0x131722&studies=%5B%5D&theme=dark&style=1&timezone=Etc%2FUTC&withdateranges=1&hidevolume=1&locale=en&utm_source=localhost&utm_medium=widget_new&utm_campaign=chart&utm_term=${encodeURIComponent(symbolToUse)}`;
        
        popover.innerHTML = `
            <div class="chart-popover-content">
                <div class="chart-popover-header">
                    <h3>${symbol} Chart - (30 min timeframe)</h3>
                    <button class="chart-close-btn" onclick="tradingForm.closeChart()">×</button>
                </div>
                <div class="chart-iframe-container">
                    <iframe 
                        id="tradingview_${symbol}"
                        src="${chartUrl}"
                        frameborder="0"
                        allowtransparency="true"
                        scrolling="no"
                        allowfullscreen="true"
                        class="chart-iframe"
                        style="display:block;width:100%;height:100%;"
                    ></iframe>
                </div>
            </div>
        `;

        // Show popover with animation
        popover.style.display = 'flex';
        setTimeout(() => {
            popover.classList.add('show');
        }, 10);

        // Close on background click
        popover.addEventListener('click', (e) => {
            if (e.target === popover) {
                this.closeChart();
            }
        });

        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                this.closeChart();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    }

    // Close chart popover
    closeChart() {
        const popover = document.getElementById('chart-popover');
        if (popover) {
            popover.classList.remove('show');
            setTimeout(() => {
                popover.style.display = 'none';
                popover.innerHTML = '';
            }, 300);
        }
    }

    // Show TP popover with all targets and stop loss
    showTPPopover(positionId, symbol, entryPrice, leverage, margin, direction, targetsJson) {
        let targets;
        try {
            targets = JSON.parse(targetsJson.replace(/&quot;/g, '"'));
        } catch (e) {
            console.error('Error parsing targets:', e);
            return;
        }

        // Create overlay if it doesn't exist
        let overlay = document.getElementById('tp-popover-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'tp-popover-overlay';
            overlay.className = 'tp-popover-overlay';
            document.body.appendChild(overlay);
        }

        // Create popover if it doesn't exist
        let popover = document.getElementById('tp-popover');
        if (!popover) {
            popover = document.createElement('div');
            popover.id = 'tp-popover';
            popover.className = 'tp-popover';
            document.body.appendChild(popover);
        }

        // Calculate dollar values and percentages for each target - same as main display
        const calculateTargetValue = (targetPrice) => {
            if (!targetPrice || !entryPrice) return null;
            const priceChangePercent = (Math.abs(targetPrice - entryPrice) / entryPrice) * 100;
            const dollarValue = (margin * leverage * (priceChangePercent / 100));
            
            // Calculate percentage impact on margin (same as main display)
            const percentageImpact = (dollarValue / margin) * 100;
            
            let percentDisplay = '';
            if (direction === 'LONG') {
                percentDisplay = targetPrice > entryPrice ? `+${percentageImpact.toFixed(1)}%` : `-${percentageImpact.toFixed(1)}%`;
            } else {
                percentDisplay = targetPrice < entryPrice ? `-${percentageImpact.toFixed(1)}%` : `+${percentageImpact.toFixed(1)}%`;
            }
            
            return {
                percent: percentDisplay,
                dollar: dollarValue.toFixed(1)
            };
        };

        // Build targets list
        let targetsHTML = '';
        
        // Add take profit targets
        if (targets.tp1) {
            const tp1Data = calculateTargetValue(parseFloat(targets.tp1));
            targetsHTML += `
                <div class="tp-popover-item target">
                    <span class="tp-popover-label">Target 1:</span>
                    <span class="tp-popover-value">$${parseFloat(targets.tp1).toFixed(5)} (${tp1Data.percent}) • P&L: $${tp1Data.dollar}</span>
                </div>
            `;
        }
        
        if (targets.tp2) {
            const tp2Data = calculateTargetValue(parseFloat(targets.tp2));
            targetsHTML += `
                <div class="tp-popover-item target">
                    <span class="tp-popover-label">Target 2:</span>
                    <span class="tp-popover-value">$${parseFloat(targets.tp2).toFixed(5)} (${tp2Data.percent}) • P&L: $${tp2Data.dollar}</span>
                </div>
            `;
        }
        
        if (targets.tp3) {
            const tp3Data = calculateTargetValue(parseFloat(targets.tp3));
            targetsHTML += `
                <div class="tp-popover-item target">
                    <span class="tp-popover-label">Target 3:</span>
                    <span class="tp-popover-value">$${parseFloat(targets.tp3).toFixed(5)} (${tp3Data.percent}) • P&L: $${tp3Data.dollar}</span>
                </div>
            `;
        }

        // Add stop loss
        if (targets.sl) {
            const slData = calculateTargetValue(parseFloat(targets.sl));
            targetsHTML += `
                <div class="tp-popover-item stop-loss">
                    <span class="tp-popover-label">Stop Loss:</span>
                    <span class="tp-popover-value">$${parseFloat(targets.sl).toFixed(5)} (${slData.percent}) • P&L: $${slData.dollar}</span>
                </div>
            `;
        }

        popover.innerHTML = `
            <div class="tp-popover-header">
                <span class="tp-popover-title">${symbol} Targets & Stop Loss</span>
                <button class="tp-popover-close" onclick="tradingForm.closeTPPopover()">×</button>
            </div>
            <div class="tp-popover-content">
                ${targetsHTML}
            </div>
        `;

        // Show popover and overlay
        overlay.style.display = 'block';
        popover.style.display = 'block';

        // Close on overlay click
        overlay.onclick = () => this.closeTPPopover();

        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                this.closeTPPopover();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    }

    // Close TP popover
    closeTPPopover() {
        const overlay = document.getElementById('tp-popover-overlay');
        const popover = document.getElementById('tp-popover');
        
        if (overlay) overlay.style.display = 'none';
        if (popover) popover.style.display = 'none';
    }

    // Open position from watchlist
    async openWatchlistPosition(symbol, direction, entryPrice, watchlistId) {
        // Confirm action
        if (!confirm(`Open ${direction.toUpperCase()} position for ${symbol} at market price with 7x leverage?`)) {
            return;
        }

        try {
            this.showNotification(`Opening ${direction.toUpperCase()} position for ${symbol}...`, 'info');

            // First get current balance to calculate position size
            const balanceResponse = await fetch('api/get_balance.php');
            if (!balanceResponse.ok) {
                throw new Error('Failed to get balance information');
            }
            
            const balanceResult = await balanceResponse.json();
            if (!balanceResult.success) {
                throw new Error(balanceResult.error || 'Failed to get balance');
            }

            const totalAssets = parseFloat(balanceResult.data.totalAssets || balanceResult.data.totalBalance || 0);
            if (totalAssets <= 0) {
                throw new Error('Invalid balance data received');
            }

            // Calculate position size based on settings percentage
            const positionSizePercent = this.positionSizePercent || 3.3; // Default from settings or 3.3%
            const marginAmount = Math.ceil((totalAssets * (positionSizePercent / 100)));

            console.log('Opening watchlist position:', {
                symbol,
                direction,
                entryPrice,
                totalAssets,
                positionSizePercent,
                marginAmount
            });

            // Prepare order data for market order
            const orderData = {
                symbol: symbol,
                side: direction,
                type: 'MARKET',
                leverage: 7,
                margin_amount: marginAmount,
                entry_type: 'watchlist_market',
                watchlist_id: watchlistId,
                notes: `Market order from watchlist - ${direction.toUpperCase()} ${symbol}`
            };

            // Place the market order
            const response = await fetch('api/place_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(orderData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                this.showNotification(
                    `${direction.toUpperCase()} position opened successfully for ${symbol}! Margin: $${marginAmount}`, 
                    'success'
                );
                
                // Refresh positions and balance
                this.updateRecentSignals();
                this.loadBalanceData();
                
                // Optionally remove from watchlist after successful order
                if (confirm(`Position opened successfully! Remove ${symbol} from watchlist?`)) {
                    this.removeWatchlistItem(watchlistId);
                }
            } else {
                throw new Error(result.error || 'Failed to place market order');
            }

        } catch (error) {
            console.error('Error opening watchlist position:', error);
            this.showNotification(
                `Failed to open ${direction.toUpperCase()} position for ${symbol}: ${error.message}`, 
                'error'
            );
        }
    }
}

// PWA Navigation Class - DISABLED (using separate pages now)
/*
class PWANavigation {
    constructor() {
        this.currentSection = 'home';
        this.init();
        this.registerServiceWorker();
    }

    init() {
        this.setupBottomNavigation();
        this.setupUserMenu();
        this.showSection('home');
    }

    setupBottomNavigation() {
        const navButtons = document.querySelectorAll('.nav-item');
        navButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const section = button.getAttribute('data-section');
                this.navigateToSection(section);
            });
        });
    }

    setupUserMenu() {
        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');

        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('active');
                }
            });

            // Handle dropdown menu items
            const logoutBtn = document.getElementById('logout-btn');
            const clearCacheBtn = document.getElementById('clear-cache-btn');
            const installBtn = document.getElementById('install-btn');

            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => this.logout());
            }

            if (clearCacheBtn) {
                clearCacheBtn.addEventListener('click', () => this.clearCache());
            }

            if (installBtn) {
                installBtn.addEventListener('click', () => this.installPWA());
            }
        }
    }

    navigateToSection(section) {
        // Update active nav button
        document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
        document.querySelector(`[data-section="${section}"]`).classList.add('active');

        // Show/hide sections
        this.showSection(section);
        this.currentSection = section;

        // Load section-specific data
        this.loadSectionData(section);
    }

    showSection(section) {
        // Hide all sections
        document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
        
        // Show target section
        const targetSection = document.getElementById(`${section}-section`);
        if (targetSection) {
            targetSection.classList.add('active');
        }
    }

    loadSectionData(section) {
        switch(section) {
            case 'home':
                if (window.tradingForm) {
                    window.tradingForm.loadBalanceData();
                    window.tradingForm.updateRecentSignals();
                }
                break;
            case 'trade':
                if (window.tradingForm) {
                    // No balance loading needed on trade page
                }
                break;
            case 'orders':
                if (window.tradingForm) {
                    window.tradingForm.updateRecentSignals();
                }
                break;
            case 'watch':
                if (window.tradingForm) {
                    window.tradingForm.updateWatchlistDisplay();
                    window.tradingForm.initializeTabs();
                }
                break;
            case 'settings':
                this.loadSettingsData();
                break;
        }
    }

    loadSettingsData() {
        // Update cache info
        this.updateCacheInfo();
        
        // Show install button if PWA can be installed
        this.checkInstallPrompt();
    }

    async updateCacheInfo() {
        const cacheInfoEl = document.getElementById('cache-info');
        if (!cacheInfoEl) return;

        try {
            if ('caches' in window) {
                const cacheNames = await caches.keys();
                const totalSize = await this.calculateCacheSize(cacheNames);
                cacheInfoEl.innerHTML = `
                    <p>Cache Status: Active</p>
                    <p>Cached Resources: ${cacheNames.length} caches</p>
                    <p>Estimated Size: ${(totalSize / 1024 / 1024).toFixed(2)} MB</p>
                `;
            } else {
                cacheInfoEl.innerHTML = '<p>Cache not supported</p>';
            }
        } catch (error) {
            cacheInfoEl.innerHTML = '<p>Error loading cache info</p>';
        }
    }

    async calculateCacheSize(cacheNames) {
        let totalSize = 0;
        for (const cacheName of cacheNames) {
            const cache = await caches.open(cacheName);
            const keys = await cache.keys();
            for (const request of keys) {
                try {
                    const response = await cache.match(request);
                    if (response) {
                        const blob = await response.blob();
                        totalSize += blob.size;
                    }
                } catch (error) {
                    console.warn('Error calculating cache size:', error);
                }
            }
        }
        return totalSize;
    }

    async clearCache() {
        if (!confirm('Clear all cached data? This will require re-downloading resources.')) {
            return;
        }

        try {
            if ('caches' in window) {
                const cacheNames = await caches.keys();
                await Promise.all(
                    cacheNames.map(cacheName => caches.delete(cacheName))
                );
                
                // Also clear localStorage
                localStorage.clear();
                
                if (window.tradingForm) {
                    window.tradingForm.showNotification('Cache cleared successfully', 'success');
                }
                
                // Update cache info
                this.updateCacheInfo();
            }
        } catch (error) {
            console.error('Error clearing cache:', error);
            if (window.tradingForm) {
                window.tradingForm.showNotification('Error clearing cache', 'error');
            }
        }
    }

    checkInstallPrompt() {
        const installBtn = document.getElementById('install-btn');
        if (installBtn && window.deferredPrompt) {
            installBtn.style.display = 'block';
        }
    }

    async installPWA() {
        if (window.deferredPrompt) {
            window.deferredPrompt.prompt();
            const choiceResult = await window.deferredPrompt.userChoice;
            
            if (choiceResult.outcome === 'accepted') {
                if (window.tradingForm) {
                    window.tradingForm.showNotification('App installed successfully!', 'success');
                }
            }
            
            window.deferredPrompt = null;
            document.getElementById('install-btn').style.display = 'none';
        }
    }

    logout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'auth/logout.php';
        }
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/trade/sw.js');
                console.log('PWA: Service Worker registered successfully:', registration);
                
                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            if (window.tradingForm) {
                                window.tradingForm.showNotification('App updated! Refresh to use new version.', 'info');
                            }
                        }
                    });
                });
            } catch (error) {
                console.error('PWA: Service Worker registration failed:', error);
            }
        }
    }
}
*/

// Handle PWA install prompt
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredPrompt = e;
});

// TradingForm initialization is now handled by individual pages
// This prevents duplicate event handler attachments