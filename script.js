class TradingForm {
    constructor() {
        this.form = document.getElementById('trading-form');
        this.availableBalance = 0; // Will be loaded from API
        this.totalBalance = 0;
        this.totalAssets = 0;
        this.marginUsed = 0;
        this.positionSizePercent = 3.3;
        this.positionStatus = {}; // Track which positions exist on exchange
        
        this.init();
        this.loadBalanceData();
    }

    init() {
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Add to watchlist button
        document.getElementById('add-to-watchlist').addEventListener('click', () => this.addToWatchlist());
        
        // Reset form button
        document.getElementById('reset-form').addEventListener('click', () => this.resetForm());
        
        // Real-time calculations
        const leverageSelect = document.getElementById('leverage');
        leverageSelect.addEventListener('change', () => this.updateAccountInfo());
        
        // Enable/disable entry points
        this.setupEntryPointToggles();
        
        // Form validation
        this.setupValidation();
        
        // Symbol price fetching
        this.setupSymbolPriceFetch();
        
        // Auto-save draft every 30 seconds
        setInterval(() => this.autoSave(), 30000);
    }

    setupEntryPointToggles() {
        const entryPoints = [
            { checkbox: 'entry_market_enabled', input: 'entry_market', margin: 'entry_market_margin' },
            { checkbox: 'entry_2_enabled', input: 'entry_2', margin: 'entry_2_margin', percent: 'entry_2_percent' },
            { checkbox: 'entry_3_enabled', input: 'entry_3', margin: 'entry_3_margin', percent: 'entry_3_percent' }
        ];

        entryPoints.forEach(({ checkbox, input, margin, percent }) => {
            const checkboxEl = document.getElementById(checkbox);
            const inputEl = document.getElementById(input);
            const marginEl = document.getElementById(margin);
            const percentEl = percent ? document.getElementById(percent) : null;
            
            checkboxEl.addEventListener('change', () => {
                const isEnabled = checkboxEl.checked;
                inputEl.disabled = !isEnabled;
                marginEl.disabled = !isEnabled;
                if (percentEl) percentEl.disabled = !isEnabled;
                
                inputEl.style.opacity = isEnabled ? '1' : '0.5';
                marginEl.style.opacity = isEnabled ? '1' : '0.5';
                if (percentEl) percentEl.style.opacity = isEnabled ? '1' : '0.5';
                
                if (!isEnabled) {
                    inputEl.value = '';
                    marginEl.value = '';
                    if (percentEl) percentEl.value = '';
                }
            });
            
            // Set up percentage calculation for Entry 2 and Entry 3
            if (percentEl) {
                percentEl.addEventListener('input', () => {
                    this.calculateEntryPrice(percent, input);
                });
            }
            
            // Initial state
            const isEnabled = checkboxEl.checked;
            inputEl.disabled = !isEnabled;
            marginEl.disabled = !isEnabled;
            if (percentEl) percentEl.disabled = !isEnabled;
            
            inputEl.style.opacity = isEnabled ? '1' : '0.5';
            marginEl.style.opacity = isEnabled ? '1' : '0.5';
            if (percentEl) percentEl.style.opacity = isEnabled ? '1' : '0.5';
        });
        
        // Also recalculate when market entry changes
        document.getElementById('entry_market').addEventListener('input', () => {
            this.calculateEntryPrice('entry_2_percent', 'entry_2');
            this.calculateEntryPrice('entry_3_percent', 'entry_3');
        });
        
        // Recalculate when direction changes
        document.querySelectorAll('input[name="direction"]').forEach(radio => {
            radio.addEventListener('change', () => {
                this.calculateEntryPrice('entry_2_percent', 'entry_2');
                this.calculateEntryPrice('entry_3_percent', 'entry_3');
                this.updateSubmitButton();
            });
        });
        
        // Initial button update
        this.updateSubmitButton();
    }

    setupSymbolPriceFetch() {
        const symbolField = document.getElementById('symbol');
        let fetchTimeout;
        
        // Auto-uppercase input as user types
        symbolField.addEventListener('input', (e) => {
            const cursorPosition = e.target.selectionStart;
            const value = e.target.value.toUpperCase().replace(/[^A-Z]/g, '');
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
        
        if (!percentage || !marketPrice || percentage === 0) {
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

    updateSubmitButton() {
        const directionEl = document.querySelector('input[name="direction"]:checked');
        const submitBtn = this.form.querySelector('.btn-primary');
        const direction = directionEl ? directionEl.value : 'long';
        
        if (direction === 'short') {
            submitBtn.textContent = 'Open Short Position';
            submitBtn.classList.add('short');
        } else {
            submitBtn.textContent = 'Open Long Position';
            submitBtn.classList.remove('short');
        }
    }

    setupValidation() {
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
        if ((field.name.includes('entry') || field.name.includes('tp') || field.name === 'stop_loss') && field.value) {
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
                this.totalAssets = this.marginUsed + this.availableBalance;
                
                // Update UI
                this.updateAccountInfo();
                
                // Update last updated time
                const lastUpdated = new Date().toLocaleTimeString();
                document.getElementById('last-updated').textContent = lastUpdated;
                
                // Show success notification
                this.showNotification('Balance updated from BingX', 'success');
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
            
            document.getElementById('total-assets').textContent = 'Error';
            document.getElementById('available-balance').textContent = 'Error';
            document.getElementById('position-size').textContent = 'Error';
            document.getElementById('margin-used').textContent = 'Error';
            document.getElementById('last-updated').textContent = 'API Error';
            
            this.showNotification('Failed to load balance from BingX: ' + error.message, 'error');
        }
    }

    updateAccountInfo() {
        const leverage = parseInt(document.getElementById('leverage').value) || 1;
        const positionSize = (this.totalAssets * this.positionSizePercent) / 100;
        const leverageMargin = positionSize / leverage;


        document.getElementById('total-assets').textContent = `$${this.totalAssets.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('available-balance').textContent = `$${this.availableBalance.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('position-size').textContent = `$${Math.ceil(positionSize).toLocaleString()}`;
        document.getElementById('margin-used').textContent = `$${this.marginUsed.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }

    async refreshBalance() {
        await this.loadBalanceData();
    }

    getFormData() {
        const formData = new FormData(this.form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        // Add enabled entry points
        data.enabled_entries = [];
        if (document.getElementById('entry_market_enabled').checked && data.entry_market) {
            data.enabled_entries.push({ 
                type: 'market', 
                price: data.entry_market,
                margin: data.entry_market_margin || 0
            });
        }
        if (document.getElementById('entry_2_enabled').checked && data.entry_2) {
            data.enabled_entries.push({ 
                type: 'limit', 
                price: data.entry_2,
                margin: data.entry_2_margin || 0,
                percentage: data.entry_2_percent || 0
            });
        }
        if (document.getElementById('entry_3_enabled').checked && data.entry_3) {
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
        const inputs = this.form.querySelectorAll('input[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        // At least one entry point must be enabled
        const hasEnabledEntry = document.getElementById('entry_market_enabled').checked ||
                               document.getElementById('entry_2_enabled').checked ||
                               document.getElementById('entry_3_enabled').checked;

        if (!hasEnabledEntry) {
            isValid = false;
            this.showNotification('At least one entry point must be enabled', 'error');
        }

        return isValid;
    }

    async handleSubmit(e) {
        e.preventDefault();

        if (!this.validateForm()) {
            return;
        }

        const submitBtn = this.form.querySelector('.btn-primary');
        const originalText = submitBtn.textContent;
        
        try {
            const direction = document.querySelector('input[name="direction"]:checked').value;
            submitBtn.textContent = `Opening ${direction.charAt(0).toUpperCase() + direction.slice(1)} Position...`;
            submitBtn.disabled = true;
            this.form.classList.add('loading');

            const data = this.getFormData();
            
            // Simulate API call
            await this.createSignal(data);
            
            this.showNotification('Position opened successfully!', 'success');
            this.form.reset();
            this.updateAccountInfo();
            this.updateSubmitButton();
            
        } catch (error) {
            this.showNotification(error.message || 'Failed to create signal', 'error');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            this.form.classList.remove('loading');
        }
    }

    async createSignal(data) {
        try {
            // Call the real order placement API
            const response = await fetch('api/place_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
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
            this.loadBalanceData(); // Refresh balance after order placement
            
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

        // Check entry 2 if enabled and has value
        if (document.getElementById('entry_2_enabled').checked && document.getElementById('entry_2').value) {
            const entry2Price = parseFloat(document.getElementById('entry_2').value);
            const entry2Margin = parseFloat(document.getElementById('entry_2_margin').value) || 0;
            const entry2Percent = parseFloat(document.getElementById('entry_2_percent').value) || null;

            if (entry2Price > 0) {
                watchlistItems.push({
                    entry_type: 'entry_2',
                    entry_price: entry2Price,
                    margin_amount: entry2Margin,
                    percentage: entry2Percent
                });
            }
        }

        // Check entry 3 if enabled and has value
        if (document.getElementById('entry_3_enabled').checked && document.getElementById('entry_3').value) {
            const entry3Price = parseFloat(document.getElementById('entry_3').value);
            const entry3Margin = parseFloat(document.getElementById('entry_3_margin').value) || 0;
            const entry3Percent = parseFloat(document.getElementById('entry_3_percent').value) || null;

            if (entry3Price > 0) {
                watchlistItems.push({
                    entry_type: 'entry_3',
                    entry_price: entry3Price,
                    margin_amount: entry3Margin,
                    percentage: entry3Percent
                });
            }
        }

        if (watchlistItems.length === 0) {
            this.showNotification('At least one entry point (Entry 2 or Entry 3) must be enabled with a valid price', 'error');
            return;
        }

        // Validate entry values for enabled entries
        let validationErrors = [];

        // Check entry 2 validation
        if (document.getElementById('entry_2_enabled').checked) {
            const entry2Price = parseFloat(document.getElementById('entry_2').value) || 0;
            const entry2Margin = parseFloat(document.getElementById('entry_2_margin').value) || 0;
            const entry2Percent = parseFloat(document.getElementById('entry_2_percent').value) || 0;

            if (entry2Price <= 0) {
                validationErrors.push('Entry 2 price must be greater than 0');
            }
            if (entry2Margin <= 0) {
                validationErrors.push('Entry 2 order value must be greater than 0');
            }
            if (entry2Percent <= 0) {
                validationErrors.push('Entry 2 percentage must be greater than 0');
            }
        }

        // Check entry 3 validation
        if (document.getElementById('entry_3_enabled').checked) {
            const entry3Price = parseFloat(document.getElementById('entry_3').value) || 0;
            const entry3Margin = parseFloat(document.getElementById('entry_3_margin').value) || 0;
            const entry3Percent = parseFloat(document.getElementById('entry_3_percent').value) || 0;

            if (entry3Price <= 0) {
                validationErrors.push('Entry 3 price must be greater than 0');
            }
            if (entry3Margin <= 0) {
                validationErrors.push('Entry 3 order value must be greater than 0');
            }
            if (entry3Percent <= 0) {
                validationErrors.push('Entry 3 percentage must be greater than 0');
            }
        }

        if (validationErrors.length > 0) {
            this.showNotification(validationErrors[0], 'error'); // Show first error
            return;
        }

        try {
            // Send to database
            const response = await fetch('api/watchlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    symbol: symbol,
                    direction: direction,
                    watchlist_items: watchlistItems
                })
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
            this.form.reset();
            
            // Reset custom states
            document.getElementById('entry_market_enabled').checked = true;
            document.getElementById('entry_2_enabled').checked = false;
            document.getElementById('entry_3_enabled').checked = false;
            
            // Reset direction to long (default)
            document.querySelector('input[name="direction"][value="long"]').checked = true;
            
            // Refresh entry point states
            const entryPoints = [
                { checkbox: 'entry_market_enabled', input: 'entry_market', margin: 'entry_market_margin' },
                { checkbox: 'entry_2_enabled', input: 'entry_2', margin: 'entry_2_margin', percent: 'entry_2_percent' },
                { checkbox: 'entry_3_enabled', input: 'entry_3', margin: 'entry_3_margin', percent: 'entry_3_percent' }
            ];
            
            entryPoints.forEach(({ checkbox, input, margin, percent }) => {
                const checkboxEl = document.getElementById(checkbox);
                const inputEl = document.getElementById(input);
                const marginEl = document.getElementById(margin);
                const percentEl = percent ? document.getElementById(percent) : null;
                
                const isEnabled = checkboxEl.checked;
                inputEl.disabled = !isEnabled;
                marginEl.disabled = !isEnabled;
                if (percentEl) percentEl.disabled = !isEnabled;
                
                inputEl.style.opacity = isEnabled ? '1' : '0.5';
                marginEl.style.opacity = isEnabled ? '1' : '0.5';
                if (percentEl) percentEl.style.opacity = isEnabled ? '1' : '0.5';
            });
            
            // Update button state
            this.updateSubmitButton();
            
            // Update account info
            this.updateAccountInfo();
            
            // Show success notification
            this.showNotification('Form has been reset', 'success');
        }
    }

    clearWatchlistFields() {
        // Clear entry points 2 and 3 if they were used for watchlist
        if (document.getElementById('entry_2_enabled').checked) {
            document.getElementById('entry_2_percent').value = '';
            document.getElementById('entry_2').value = '';
        }
        if (document.getElementById('entry_3_enabled').checked) {
            document.getElementById('entry_3_percent').value = '';
            document.getElementById('entry_3').value = '';
        }
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
            const response = await fetch('api/get_orders.php?type=positions&status=OPEN&limit=5');
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    this.displayRecentPositions(result.data);
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
        this.displayRecentSignals(filledSignals.slice(0, 5));
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
        
        if (positions.length === 0) {
            positionList.innerHTML = '<p class="no-signals">No open positions</p>';
            return;
        }

        positionList.innerHTML = positions.map(position => {
            // Handle both database positions and localStorage signals
            const direction = position.side || position.signal_type || 'UNKNOWN';
            const symbol = position.symbol || 'UNKNOWN';
            const leverage = position.leverage || 1;
            const openedAt = position.opened_at || position.created_at || position.timestamp;
            const marginUsed = position.margin_used ? parseFloat(position.margin_used).toFixed(2) : '0.00';
            const pnl = position.unrealized_pnl ? parseFloat(position.unrealized_pnl).toFixed(2) : '0.00';
            const pnlClass = parseFloat(pnl) >= 0 ? 'profit' : 'loss';
            
            // Calculate P&L percentage based on margin used
            const pnlPercent = parseFloat(marginUsed) > 0 ? ((parseFloat(pnl) / parseFloat(marginUsed)) * 100).toFixed(2) : '0.00';
            const pnlPercentClass = parseFloat(pnlPercent) >= 0 ? 'profit' : 'loss';
            
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
            
            return `
                <div class="signal-item position-item">
                    <div class="signal-item-header">
                        <strong class="signal-symbol ${direction?.toLowerCase()}">${symbol}</strong>
                        <span class="signal-time">${timeAgo}</span>
                    </div>
                    <div class="signal-details">
                        ${direction?.toUpperCase()} • ${leverage}x • Margin: $${marginUsed}
                    </div>
                    <div class="position-pnl ${pnlClass}">
                        P&L: $${pnl} (<span class="${pnlPercentClass}">${pnlPercent}%</span>)
                    </div>
                    <div class="position-actions">
                        ${this.getPositionButton(position.id, symbol, direction)}
                    </div>
                </div>
            `;
        }).filter(html => html !== '').join('');
    }

    getPositionButton(positionId, symbol, direction) {
        const status = this.positionStatus[positionId];
        
        // If position status is unknown or exists on exchange, show Close button
        if (!status || status.exists_on_exchange) {
            return `
                <button 
                    class="position-close-btn"
                    onclick="tradingForm.closePosition(${positionId}, '${symbol}', '${direction}')" 
                    title="Close position"
                >Close Position</button>
            `;
        } else {
            // Position doesn't exist on exchange, show Remove button
            return `
                <button 
                    class="position-remove-btn"
                    onclick="tradingForm.removePosition(${positionId}, '${symbol}', '${direction}')" 
                    title="Remove position (not on exchange)"
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

    async closePosition(positionId, symbol, direction) {
        if (!confirm(`Are you sure you want to close your ${direction} position on ${symbol}?`)) {
            return;
        }

        try {
            this.showNotification(`Closing ${symbol} ${direction} position...`, 'info');
            
            // Call API to close position
            const response = await fetch('api/close_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    position_id: positionId,
                    symbol: symbol,
                    direction: direction
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
                this.loadBalanceData();
            } else {
                throw new Error(result.error || 'Failed to close position');
            }

        } catch (error) {
            console.error('Close position error:', error);
            this.showNotification(`Failed to close position: ${error.message}`, 'error');
        }
    }

    async removePosition(positionId, symbol, direction) {
        if (!confirm(`Remove ${direction} position on ${symbol}? This position no longer exists on the exchange.`)) {
            return;
        }

        try {
            this.showNotification(`Removing ${symbol} ${direction} position...`, 'info');
            
            // Call API to mark position as closed
            const response = await fetch('api/close_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    position_id: positionId,
                    symbol: symbol,
                    direction: direction
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
                this.loadBalanceData();
            } else {
                throw new Error(result.error || 'Failed to remove position');
            }

        } catch (error) {
            console.error('Remove position error:', error);
            this.showNotification(`Failed to remove position: ${error.message}`, 'error');
        }
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
            const response = await fetch('api/watchlist.php?limit=10');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            const watchlistContainer = document.getElementById('watchlist-items');
            
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
                        <button 
                            class="watchlist-remove-btn"
                            onclick="tradingForm.removeWatchlistItem(${item.id})" 
                            title="Remove from watchlist"
                        >×</button>
                    </div>
                `;
            }).join('');

            // Auto-refresh prices after displaying items
            this.refreshWatchlistPrices(false);

        } catch (error) {
            console.error('Error loading watchlist:', error);
            document.getElementById('watchlist-items').innerHTML = '<p class="no-watchlist">Error loading watchlist</p>';
        }
    }

    async refreshWatchlist() {
        this.showNotification('Refreshing watchlist prices...', 'info');
        await this.refreshWatchlistPrices(true);
    }

    async refreshWatchlistPrices(showNotification = true) {
        try {
            const response = await fetch('api/get_watchlist_prices.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Failed to fetch prices');
            }

            const priceData = result.data;
            
            // Update each watchlist item with current price and distance
            priceData.forEach(item => {
                const watchlistElement = document.querySelector(`[data-id="${item.id}"]`);
                if (!watchlistElement) return;

                const priceInfoElement = watchlistElement.querySelector('.price-info');
                const closeIndicator = watchlistElement.querySelector('.watchlist-close-indicator');
                
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
                } else {
                    priceInfoElement.innerHTML = 'Price unavailable';
                    closeIndicator.style.display = 'none';
                    closeIndicator.classList.remove('close', 'reached');
                }
            });

            if (showNotification) {
                this.showNotification('Watchlist prices updated', 'success');
            }

        } catch (error) {
            console.error('Error refreshing watchlist prices:', error);
            
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

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
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
}

// Initialize the trading form when DOM is loaded
let tradingForm; // Make globally accessible
document.addEventListener('DOMContentLoaded', () => {
    tradingForm = new TradingForm();
    tradingForm.loadDraft();
    tradingForm.updateRecentSignals();
    tradingForm.updateWatchlistDisplay();
});