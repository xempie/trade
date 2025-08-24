class TradingForm {
    constructor() {
        this.form = document.getElementById('trading-form');
        this.availableBalance = 10000; // Mock balance
        this.positionSizePercent = 3.3;
        
        this.init();
        this.updateAccountInfo();
    }

    init() {
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Add to watchlist button
        document.getElementById('add-to-watchlist').addEventListener('click', () => this.addToWatchlist());
        
        // Real-time calculations
        const leverageSelect = document.getElementById('leverage');
        leverageSelect.addEventListener('change', () => this.updateAccountInfo());
        
        // Enable/disable entry points
        this.setupEntryPointToggles();
        
        // Form validation
        this.setupValidation();
        
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
            const symbolRegex = /^[A-Z]{2,10}USDT$/;
            if (!symbolRegex.test(field.value.toUpperCase())) {
                isValid = false;
                errorMessage = 'Symbol must end with USDT (e.g., BTCUSDT)';
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

    updateAccountInfo() {
        const leverage = parseInt(document.getElementById('leverage').value) || 1;
        const positionSize = (this.availableBalance * this.positionSizePercent) / 100;
        const marginUsed = positionSize / leverage;

        document.getElementById('available-balance').textContent = `$${this.availableBalance.toLocaleString()}`;
        document.getElementById('position-size').textContent = `$${Math.ceil(positionSize).toLocaleString()}`;
        document.getElementById('margin-used').textContent = `$${Math.ceil(marginUsed).toLocaleString()}`;
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
        // Simulate API delay
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        // Mock API call - replace with actual backend call
        console.log('Creating signal:', data);
        
        // For now, just store in localStorage
        const signals = JSON.parse(localStorage.getItem('trading_signals') || '[]');
        signals.unshift({
            id: Date.now(),
            ...data,
            created_at: new Date().toISOString(),
            status: 'active'
        });
        localStorage.setItem('trading_signals', JSON.stringify(signals));
        
        this.updateRecentSignals();
    }

    addToWatchlist() {
        // Validate that symbol is filled
        const symbolEl = document.getElementById('symbol');
        if (!symbolEl.value.trim()) {
            this.showNotification('Symbol is required to add to watchlist', 'error');
            symbolEl.focus();
            return;
        }

        // Validate symbol format
        const symbolRegex = /^[A-Z]{2,10}USDT$/;
        if (!symbolRegex.test(symbolEl.value.toUpperCase())) {
            this.showNotification('Symbol must end with USDT (e.g., BTCUSDT)', 'error');
            symbolEl.focus();
            return;
        }

        const symbol = symbolEl.value.toUpperCase();
        const watchPrices = [];

        // Check entry 2 if enabled and has value
        if (document.getElementById('entry_2_enabled').checked && document.getElementById('entry_2').value) {
            watchPrices.push({
                price: parseFloat(document.getElementById('entry_2').value),
                type: 'Entry 2',
                percentage: document.getElementById('entry_2_percent').value || null
            });
        }

        // Check entry 3 if enabled and has value
        if (document.getElementById('entry_3_enabled').checked && document.getElementById('entry_3').value) {
            watchPrices.push({
                price: parseFloat(document.getElementById('entry_3').value),
                type: 'Entry 3',
                percentage: document.getElementById('entry_3_percent').value || null
            });
        }

        // Create watchlist item
        const watchlistItem = {
            id: Date.now(),
            symbol: symbol,
            watchPrices: watchPrices,
            created_at: new Date().toISOString(),
            status: 'active'
        };

        // Save to localStorage
        const watchlist = JSON.parse(localStorage.getItem('trading_watchlist') || '[]');
        
        // Check if symbol already exists
        const existingIndex = watchlist.findIndex(item => item.symbol === symbol);
        if (existingIndex !== -1) {
            // Update existing watchlist item
            watchlist[existingIndex] = watchlistItem;
            this.showNotification(`Updated ${symbol} in watchlist`, 'success');
        } else {
            // Add new watchlist item
            watchlist.unshift(watchlistItem);
            this.showNotification(`Added ${symbol} to watchlist`, 'success');
        }

        localStorage.setItem('trading_watchlist', JSON.stringify(watchlist));
        
        // Update watchlist display
        this.updateWatchlistDisplay();
        
        // Clear the form after adding to watchlist
        this.clearWatchlistFields();
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

    updateRecentSignals() {
        const signals = JSON.parse(localStorage.getItem('trading_signals') || '[]');
        const signalList = document.getElementById('signal-list');
        
        if (signals.length === 0) {
            signalList.innerHTML = '<p class="no-signals">No recent positions</p>';
            return;
        }

        signalList.innerHTML = signals.slice(0, 5).map(signal => `
            <div class="signal-item" style="padding: 12px; background: var(--dark-card); border-radius: 6px; border: 1px solid var(--dark-border);">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 8px;">
                    <strong style="color: var(--green-5);">${signal.symbol}</strong>
                    <span style="font-size: 12px; color: var(--dark-text-muted);">${new Date(signal.created_at).toLocaleDateString()}</span>
                </div>
                <div style="font-size: 12px; color: var(--dark-text-secondary);">
                    ${signal.direction.toUpperCase()} • ${signal.leverage}x
                </div>
            </div>
        `).join('');
    }

    updateWatchlistDisplay() {
        const watchlist = JSON.parse(localStorage.getItem('trading_watchlist') || '[]');
        const watchlistContainer = document.getElementById('watchlist-items');
        
        if (watchlist.length === 0) {
            watchlistContainer.innerHTML = '<p class="no-watchlist">No watchlist items</p>';
            return;
        }

        watchlistContainer.innerHTML = watchlist.slice(0, 5).map(item => `
            <div class="watchlist-item" style="padding: 12px; background: var(--dark-card); border-radius: 6px; border: 1px solid var(--dark-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong style="color: var(--green-5);">${item.symbol}</strong>
                    <span style="font-size: 12px; color: var(--dark-text-muted);">${new Date(item.created_at).toLocaleDateString()}</span>
                </div>
                ${item.watchPrices.map(watch => `
                    <div style="font-size: 12px; color: var(--dark-text-secondary); margin-bottom: 4px;">
                        ${watch.type}: $${watch.price.toFixed(5)}${watch.percentage ? ` (${watch.percentage}%)` : ''}
                    </div>
                `).join('')}
                ${item.watchPrices.length === 0 ? '<div style="font-size: 12px; color: var(--dark-text-muted);">No specific prices set</div>' : ''}
            </div>
        `).join('');
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
document.addEventListener('DOMContentLoaded', () => {
    const tradingForm = new TradingForm();
    tradingForm.loadDraft();
    tradingForm.updateRecentSignals();
    tradingForm.updateWatchlistDisplay();
});