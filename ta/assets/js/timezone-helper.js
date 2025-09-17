/**
 * Timezone Helper for Trading App
 * Handles timezone conversion for time display across different pages
 */
class TimezoneHelper {
    constructor() {
        this.userTimezone = 'Australia/Melbourne'; // Default
        this.loadUserTimezone();
    }

    async loadUserTimezone() {
        try {
            const response = await fetch('api/get_settings.php');
            const data = await response.json();

            if (data.success && data.settings.timezone) {
                this.userTimezone = data.settings.timezone;
                console.log('🌍 User timezone loaded:', this.userTimezone);
            }
        } catch (error) {
            console.warn('⚠️ Could not load user timezone, using default:', error);
        }
    }

    /**
     * Convert server time to user's timezone and return time ago string
     * @param {string} dateString - Server timestamp (assumed to be in server timezone)
     * @param {boolean} useUserTimezone - Whether to convert to user timezone (default: true)
     * @returns {string} - Time ago string like "2h 15m ago"
     */
    getTimeAgo(dateString, useUserTimezone = true) {
        try {
            // Parse the server date (assuming it's in UTC or server timezone)
            const serverDate = new Date(dateString);

            // Get current time in user's timezone
            const now = new Date();
            const userNow = useUserTimezone ?
                new Date(now.toLocaleString("en-US", {timeZone: this.userTimezone})) :
                now;

            // Convert server date to user's timezone for comparison
            const userServerDate = useUserTimezone ?
                new Date(serverDate.toLocaleString("en-US", {timeZone: this.userTimezone})) :
                serverDate;

            const diffMs = userNow - userServerDate;
            const diffSeconds = Math.floor(diffMs / 1000);
            const diffMinutes = Math.floor(diffSeconds / 60);
            const diffHours = Math.floor(diffMinutes / 60);
            const diffDays = Math.floor(diffHours / 24);

            if (diffSeconds < 60) {
                return diffSeconds <= 0 ? 'Just now' : `${diffSeconds}s ago`;
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
                // Return formatted date in user's timezone
                return useUserTimezone ?
                    userServerDate.toLocaleDateString("en-US", {
                        timeZone: this.userTimezone,
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    }) :
                    serverDate.toLocaleDateString();
            }
        } catch (error) {
            console.error('Error calculating time ago:', error);
            return 'Unknown time';
        }
    }

    /**
     * Format a date string in user's timezone
     * @param {string} dateString - Server timestamp
     * @param {object} options - Intl.DateTimeFormat options
     * @returns {string} - Formatted date string
     */
    formatDateInUserTimezone(dateString, options = {}) {
        try {
            const date = new Date(dateString);
            const defaultOptions = {
                timeZone: this.userTimezone,
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };

            return date.toLocaleDateString("en-US", {...defaultOptions, ...options});
        } catch (error) {
            console.error('Error formatting date:', error);
            return dateString;
        }
    }

    /**
     * Get the user's current timezone
     * @returns {string} - Timezone identifier like 'Australia/Melbourne'
     */
    getUserTimezone() {
        return this.userTimezone;
    }

    /**
     * Get timezone display name
     * @returns {string} - Human readable timezone like '+10:00 Melbourne Australia'
     */
    getTimezoneDisplay() {
        const timezoneMap = {
            'Australia/Adelaide': '+9:30 Adelaide Australia',
            'Australia/Melbourne': '+10:00 Melbourne Australia',
            'Asia/Dubai': '+4:00 Dubai UAE',
            'Asia/Tehran': '+3:30 Tehran Iran',
            'America/Philadelphia': '-5:00 Philadelphia USA'
        };

        return timezoneMap[this.userTimezone] || this.userTimezone;
    }
}

// Create global instance
window.timezoneHelper = new TimezoneHelper();

// Backward compatibility - expose getTimeAgo globally
window.getTimeAgoWithTimezone = (dateString) => {
    return window.timezoneHelper.getTimeAgo(dateString);
};

console.log('🌍 Timezone Helper initialized');