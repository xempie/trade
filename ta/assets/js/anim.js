/**
 * Gamification Animation System
 * Handles various visual effects for the crypto trading app
 */

class GamificationAnimations {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.animationId = null;
        this.isActive = false;
        this.items = [];
        this.currentEffect = null;
    }

    // Initialize canvas for animations
    initCanvas() {
        // Create canvas if it doesn't exist
        this.canvas = document.getElementById('gamification-canvas');
        if (!this.canvas) {
            this.canvas = document.createElement('canvas');
            this.canvas.id = 'gamification-canvas';
            this.canvas.style.position = 'fixed';
            this.canvas.style.top = '0';
            this.canvas.style.left = '0';
            this.canvas.style.width = '100%';
            this.canvas.style.height = '100%';
            this.canvas.style.pointerEvents = 'none';
            this.canvas.style.zIndex = '1000';
            document.body.appendChild(this.canvas);
        }

        this.ctx = this.canvas.getContext('2d');
        this.resizeCanvas();

        // Handle window resize
        window.addEventListener('resize', () => this.resizeCanvas());
    }

    resizeCanvas() {
        if (!this.canvas) return;
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    }

    // Start raining effect based on P&L
    startPnLRainEffect(unrealizedPnL) {
        this.stopAnimation(); // Stop any existing animation
        
        if (unrealizedPnL === 0) {
            return; // No effect for zero P&L
        }

        this.initCanvas();
        this.currentEffect = 'rain';
        
        const emoji = unrealizedPnL < 0 ? '🍆' : '🪙'; // Eggplant for loss, coin for profit
        const itemCount = Math.min(30, Math.max(10, Math.abs(unrealizedPnL) / 20)); // Reduced by 50% - Dynamic count based on P&L magnitude
        
        this.items = [];
        for (let i = 0; i < itemCount; i++) {
            this.items.push(new FallingEmoji(emoji, this.canvas));
        }

        this.isActive = true;
        this.animate();
        
        // Auto-stop after 10 seconds with graceful fade-out
        setTimeout(() => {
            if (this.currentEffect === 'rain') {
                this.startFinalFadeOut();
            }
        }, 10000);
    }

    // Start final fade-out for all items
    startFinalFadeOut() {
        // Force all items to start fading immediately
        this.items.forEach(item => {
            if (!item.landed) {
                // Land items that are still falling
                item.landed = true;
                item.fadeStart = Date.now() - 5000; // Start fading immediately
            } else if (item.fadeStart) {
                // Accelerate fade for items already landed
                item.fadeStart = Date.now() - 5000;
            }
        });
    }

    // Animation loop
    animate() {
        if (!this.isActive || !this.ctx) return;

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // Update and draw all items
        this.items.forEach(item => {
            item.update();
            item.draw(this.ctx);
        });

        // Remove disappeared items completely during fade-out phase
        for (let i = this.items.length - 1; i >= 0; i--) {
            if (this.items[i].isGone()) {
                // Don't reset during final fade-out, just remove
                this.items.splice(i, 1);
            }
        }

        // Stop animation when all items are gone
        if (this.items.length === 0) {
            this.stopAnimation();
            return;
        }

        this.animationId = requestAnimationFrame(() => this.animate());
    }

    // Stop current animation
    stopAnimation() {
        this.isActive = false;
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
        if (this.ctx && this.canvas) {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        }
        this.currentEffect = null;
        this.items = [];
    }

    // Clean up - remove canvas
    destroy() {
        this.stopAnimation();
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
        this.canvas = null;
        this.ctx = null;
    }
}

// Falling emoji class for rain effects
class FallingEmoji {
    constructor(emoji, canvas) {
        this.emoji = emoji;
        this.canvas = canvas;
        this.reset();
    }

    reset() {
        this.x = this.random(0, this.canvas.width);
        this.y = this.random(-this.canvas.height, 0);
        this.size = this.random(30, 60);
        this.speed = this.random(2, 5);
        this.angle = this.random(0, Math.PI * 2);
        this.spin = this.random(-0.03, 0.03);
        this.opacity = 1;
        this.landed = false;
        this.fadeStart = null;
    }

    update() {
        if (!this.landed) {
            this.y += this.speed;
            this.angle += this.spin;

            // Check if landed on bottom
            if (this.y + this.size >= this.canvas.height) {
                this.y = this.canvas.height - this.size;
                this.landed = true;
                this.fadeStart = Date.now();
            }
        } else {
            // Start fading 5 seconds after landing for better visual effect
            const timeSinceLanding = Date.now() - this.fadeStart;
            if (timeSinceLanding > 5000) {
                // Smooth fade out over 3 seconds
                const fadeProgress = (timeSinceLanding - 5000) / 3000;
                this.opacity = Math.max(0, 1 - fadeProgress);
            }
        }
    }

    draw(ctx) {
        if (this.opacity <= 0) return;

        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.angle);
        ctx.globalAlpha = this.opacity;
        ctx.font = `${this.size}px serif`;
        ctx.fillText(this.emoji, 0, 0);
        ctx.restore();
    }

    isGone() {
        return this.opacity <= 0;
    }

    random(min, max) {
        return Math.random() * (max - min) + min;
    }
}

// Global instance
window.gamificationAnimations = new GamificationAnimations();

// Auto-trigger rain effect when P&L data is available
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the homepage/dashboard
    const isHomePage = window.location.pathname.includes('home.php') || 
                      window.location.pathname === '/' || 
                      window.location.pathname.includes('index.php');
    
    if (isHomePage) {
        // Monitor for P&L updates and trigger animation
        const checkForPnLUpdates = () => {
            // Look for unrealized P&L in the balance display (specific to home page)
            const pnlElement = document.getElementById('unrealized-pnl');
            if (pnlElement) {
                const pnlText = pnlElement.textContent || pnlElement.innerText;
                const pnlMatch = pnlText.match(/[-+]?\$?([0-9,]+\.?[0-9]*)/);
                
                if (pnlMatch) {
                    const pnlValue = parseFloat(pnlMatch[1].replace(/,/g, ''));
                    const isNegative = pnlText.includes('-');
                    const finalPnL = isNegative ? -pnlValue : pnlValue;
                    
                    console.log('🎮 Gamification: Detected P&L:', finalPnL);
                    
                    // Only trigger if P&L is significant (> $5 or < -$5)
                    if (Math.abs(finalPnL) > 5) {
                        console.log('🎮 Starting rain animation for P&L:', finalPnL);
                        window.gamificationAnimations.startPnLRainEffect(finalPnL);
                    }
                }
            }
        };

        // Check for P&L updates periodically
        setTimeout(checkForPnLUpdates, 2000); // Initial delay
        setInterval(checkForPnLUpdates, 30000); // Check every 30 seconds
    }
});