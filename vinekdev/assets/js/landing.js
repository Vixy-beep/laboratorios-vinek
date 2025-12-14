// JORISE Landing Page JavaScript

// ============================================
// CAROUSEL FUNCTIONALITY
// ============================================
let currentSlide = 0;
let autoplayInterval;

function showSlide(index) {
    const slides = document.querySelectorAll('.carousel-slide');
    const indicators = document.querySelectorAll('.indicator');
    
    if (!slides.length) return;
    
    // Loop around
    if (index >= slides.length) currentSlide = 0;
    if (index < 0) currentSlide = slides.length - 1;
    
    // Remove active class from all
    slides.forEach(slide => slide.classList.remove('active'));
    indicators.forEach(indicator => indicator.classList.remove('active'));
    
    // Add active class to current
    slides[currentSlide].classList.add('active');
    indicators[currentSlide].classList.add('active');
}

function carouselNext() {
    currentSlide++;
    showSlide(currentSlide);
    resetAutoplay();
}

function carouselPrev() {
    currentSlide--;
    showSlide(currentSlide);
    resetAutoplay();
}

function carouselGoTo(index) {
    currentSlide = index;
    showSlide(currentSlide);
    resetAutoplay();
}

function startAutoplay() {
    autoplayInterval = setInterval(() => {
        currentSlide++;
        showSlide(currentSlide);
    }, 5000); // Change slide every 5 seconds
}

function resetAutoplay() {
    clearInterval(autoplayInterval);
    startAutoplay();
}

// Initialize carousel on page load
window.addEventListener('DOMContentLoaded', () => {
    showSlide(0);
    startAutoplay();
    
    // Pause autoplay on hover
    const carouselContainer = document.querySelector('.carousel-container');
    if (carouselContainer) {
        carouselContainer.addEventListener('mouseenter', () => {
            clearInterval(autoplayInterval);
        });
        
        carouselContainer.addEventListener('mouseleave', () => {
            startAutoplay();
        });
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') carouselPrev();
        if (e.key === 'ArrowRight') carouselNext();
    });
});

// ============================================
// PRICING TOGGLE (Monthly/Annual)
// ============================================
document.getElementById('pricingToggle')?.addEventListener('change', function(e) {
    const isAnnual = e.target.checked;
    const monthlyPrices = document.querySelectorAll('.monthly-price');
    const annualPrices = document.querySelectorAll('.annual-price');
    
    if (isAnnual) {
        monthlyPrices.forEach(el => el.style.display = 'none');
        annualPrices.forEach(el => el.style.display = 'inline');
    } else {
        monthlyPrices.forEach(el => el.style.display = 'inline');
        annualPrices.forEach(el => el.style.display = 'none');
    }
});

// ============================================
// ROI CALCULATOR
// ============================================
function calculateROI() {
    const traffic = parseInt(document.getElementById('traffic')?.value || 1000000);
    const employees = parseInt(document.getElementById('employees')?.value || 50);
    
    // Traditional SOC costs
    const socAnalystsCost = 150000; // 3 analysts at $50K each
    const siemLicense = 30000;
    const threatIntel = 20000;
    const incidentResponse = 50000;
    const traditionalTotal = socAnalystsCost + siemLicense + threatIntel + incidentResponse;
    
    // JORISE cost (assume PRO AI tier at $99/month)
    const joriseCost = 99 * 12; // $1,188/year
    
    // Calculate savings
    const savings = traditionalTotal - joriseCost;
    const savingsPercentage = Math.round((savings / traditionalTotal) * 100);
    
    // Update UI
    const savingsAmount = document.querySelector('.savings-amount');
    const savingsPercentageEl = document.querySelector('.savings-percentage');
    const oldTotal = document.querySelector('.old-total');
    const newTotal = document.querySelector('.new-total');
    
    if (savingsAmount) savingsAmount.textContent = `$${savings.toLocaleString()}`;
    if (savingsPercentageEl) savingsPercentageEl.textContent = `🎉 ${savingsPercentage}% de ahorro`;
    if (oldTotal) oldTotal.textContent = `$${traditionalTotal.toLocaleString()}/año`;
    if (newTotal) newTotal.textContent = `$${joriseCost.toLocaleString()}/año`;
    
    // Animate result
    const resultBox = document.getElementById('roiResult');
    if (resultBox) {
        resultBox.style.animation = 'none';
        setTimeout(() => {
            resultBox.style.animation = 'fadeInUp 0.5s ease';
        }, 10);
    }
}

// Auto-calculate on page load
window.addEventListener('DOMContentLoaded', calculateROI);

// ============================================
// DEMO FUNCTIONS
// ============================================
function openDemo() {
    // Hide overlay
    const overlay = document.querySelector('.demo-overlay');
    if (overlay) overlay.style.display = 'none';
    
    // Alternative: Open in new tab
    // window.open('../jorise-soc-v2/frontend/index.html', '_blank');
}

function openLiveDemo() {
    // Open live demo dashboard in new tab
    const demoUrl = 'jorise-dashboard.html'; // Your dashboard file
    window.open(demoUrl, '_blank', 'width=1400,height=900');
}

function openSimulation() {
    // Create attack simulation modal
    const simulationHTML = `
    <div class="simulation-modal" id="simulationModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: flex; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); padding: 40px; border-radius: 20px; max-width: 900px; width: 90%; color: white; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
            <span style="position: absolute; top: 20px; right: 30px; font-size: 32px; cursor: pointer; color: #64748b; transition: color 0.3s;" onclick="closeSimulation()">&times;</span>
            
            <h2 style="font-size: 32px; margin-bottom: 10px; background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                🎯 Simulación de Ataque en Tiempo Real
            </h2>
            <p style="color: #94a3b8; margin-bottom: 30px;">Observa cómo JORISE detecta y responde automáticamente</p>
            
            <div id="simulationLog" style="background: #0f172a; border-radius: 12px; padding: 20px; height: 400px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 14px; margin-bottom: 20px; border: 1px solid #334155;">
                <div style="color: #10b981; margin-bottom: 10px;">$ Iniciando simulación de ataque SQL Injection...</div>
            </div>
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <button onclick="simulateAttack('sql')" style="flex: 1; padding: 12px 20px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    🔥 SQL Injection
                </button>
                <button onclick="simulateAttack('xss')" style="flex: 1; padding: 12px 20px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    ⚠️ XSS Attack
                </button>
                <button onclick="simulateAttack('ddos')" style="flex: 1; padding: 12px 20px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    💥 DDoS
                </button>
                <button onclick="simulateAttack('brute')" style="flex: 1; padding: 12px 20px; background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    🔓 Brute Force
                </button>
            </div>
        </div>
    </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', simulationHTML);
}

function closeSimulation() {
    const modal = document.getElementById('simulationModal');
    if (modal) modal.remove();
}

function simulateAttack(type) {
    const log = document.getElementById('simulationLog');
    log.innerHTML = ''; // Clear previous logs
    
    const attacks = {
        sql: [
            { time: 0, msg: "🔍 [00:00] Detectando tráfico sospechoso desde 185.220.101.45...", color: "#64748b" },
            { time: 500, msg: "⚠️ [00:01] ALERTA: Payload SQL detectado: ' OR '1'='1", color: "#f59e0b" },
            { time: 1000, msg: "🧠 [00:02] IA Analizando patrón... Coincide con SQLMap signature", color: "#3b82f6" },
            { time: 1500, msg: "🌐 [00:03] Consultando Threat Intel: IP en blacklist (AbuseIPDB)", color: "#8b5cf6" },
            { time: 2000, msg: "🛡️ [00:04] WAF bloqueó request automáticamente", color: "#10b981" },
            { time: 2500, msg: "🤖 [00:05] SOAR ejecutando playbook: Block_Malicious_IP", color: "#06b6d4" },
            { time: 3000, msg: "✅ [00:06] IP 185.220.101.45 agregada a blocklist por 24h", color: "#10b981" },
            { time: 3500, msg: "📊 [00:07] Incidente registrado. Severidad: ALTA", color: "#10b981" },
            { time: 4000, msg: "✨ [00:08] ATAQUE NEUTRALIZADO - Tiempo de respuesta: 8 segundos", color: "#22c55e", bold: true }
        ],
        xss: [
            { time: 0, msg: "🔍 [00:00] Analizando input del usuario en /search...", color: "#64748b" },
            { time: 500, msg: "⚠️ [00:01] Script malicioso detectado: <script>alert('XSS')</script>", color: "#f59e0b" },
            { time: 1000, msg: "🧠 [00:02] ML Model predice: 98% probabilidad de XSS", color: "#3b82f6" },
            { time: 1500, msg: "🛡️ [00:03] Input sanitizado automáticamente", color: "#10b981" },
            { time: 2000, msg: "🔒 [00:04] Content Security Policy (CSP) aplicado", color: "#8b5cf6" },
            { time: 2500, msg: "📧 [00:05] Alerta enviada a equipo DevOps", color: "#06b6d4" },
            { time: 3000, msg: "✅ [00:06] Usuario 192.168.1.50 marcado para review", color: "#10b981" },
            { time: 3500, msg: "✨ [00:07] ATAQUE BLOQUEADO - Zero Impact", color: "#22c55e", bold: true }
        ],
        ddos: [
            { time: 0, msg: "🔍 [00:00] Monitoreando tráfico... Baseline: 1K req/min", color: "#64748b" },
            { time: 500, msg: "⚠️ [00:01] ANOMALÍA: 50K req/min desde 847 IPs únicas", color: "#f59e0b" },
            { time: 1000, msg: "🧠 [00:02] IA detecta patrón: DDoS Layer 7 (HTTP Flood)", color: "#3b82f6" },
            { time: 1500, msg: "🌐 [00:03] Threat Intel: Botnet conocida (Mirai variant)", color: "#8b5cf6" },
            { time: 2000, msg: "🤖 [00:04] Activando Rate Limiting agresivo...", color: "#06b6d4" },
            { time: 2500, msg: "🛡️ [00:05] Challenge-response (CAPTCHA) para IPs sospechosas", color: "#10b981" },
            { time: 3000, msg: "☁️ [00:06] Escalando CDN capacity automáticamente", color: "#3b82f6" },
            { time: 3500, msg: "📊 [00:07] Tráfico legítimo: 98% | Bloqueado: 2%", color: "#10b981" },
            { time: 4000, msg: "✨ [00:08] DDOS MITIGADO - Uptime: 99.99%", color: "#22c55e", bold: true }
        ],
        brute: [
            { time: 0, msg: "🔍 [00:00] Login attempt: user@empresa.com desde 203.0.113.5", color: "#64748b" },
            { time: 500, msg: "⚠️ [00:01] 15 intentos fallidos en 30 segundos", color: "#f59e0b" },
            { time: 1000, msg: "🧠 [00:02] UEBA detecta anomalía: Horario inusual (3:00 AM)", color: "#3b82f6" },
            { time: 1500, msg: "🌐 [00:03] IP location: Nigeria (usuario normalmente en España)", color: "#8b5cf6" },
            { time: 2000, msg: "🛡️ [00:04] Cuenta bloqueada temporalmente (15 min)", color: "#f59e0b" },
            { time: 2500, msg: "📧 [00:05] 2FA challenge enviado al propietario real", color: "#06b6d4" },
            { time: 3000, msg: "🤖 [00:06] IP 203.0.113.5 agregada a watchlist", color: "#8b5cf6" },
            { time: 3500, msg: "✅ [00:07] Propietario notificado por email/SMS", color: "#10b981" },
            { time: 4000, msg: "✨ [00:08] CUENTA PROTEGIDA - Brute Force detenido", color: "#22c55e", bold: true }
        ]
    };
    
    const sequence = attacks[type] || attacks.sql;
    
    sequence.forEach(step => {
        setTimeout(() => {
            const line = document.createElement('div');
            line.style.cssText = `
                color: ${step.color}; 
                margin-bottom: 8px; 
                animation: fadeIn 0.3s ease;
                ${step.bold ? 'font-weight: bold; font-size: 16px;' : ''}
            `;
            line.textContent = step.msg;
            log.appendChild(line);
            log.scrollTop = log.scrollHeight; // Auto-scroll
        }, step.time);
    });
}

function scheduleDemo() {
    // Create scheduling modal
    const scheduleHTML = `
    <div class="schedule-modal" id="scheduleModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 50px; border-radius: 20px; max-width: 600px; width: 90%; position: relative;">
            <span style="position: absolute; top: 20px; right: 30px; font-size: 32px; cursor: pointer; color: #64748b;" onclick="document.getElementById('scheduleModal').remove()">&times;</span>
            
            <h2 style="font-size: 32px; margin-bottom: 10px; color: #1a202c;">📅 Agendar Demo Personalizada</h2>
            <p style="color: #64748b; margin-bottom: 30px;">15 minutos con nuestro equipo técnico</p>
            
            <form id="demoScheduleForm" style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Nombre Completo</label>
                    <input type="text" required style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;" placeholder="Juan Pérez">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Email Empresarial</label>
                    <input type="email" required style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;" placeholder="juan@empresa.com">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Empresa</label>
                    <input type="text" required style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;" placeholder="Mi Startup SaaS">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Teléfono (WhatsApp)</label>
                    <input type="tel" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;" placeholder="+52 1 55 1234 5678">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">¿Qué te interesa más?</label>
                    <select style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
                        <option>Protección Web (WAF + IDS/IPS)</option>
                        <option>IA Predictiva (ML Models)</option>
                        <option>Respuesta Automatizada (SOAR)</option>
                        <option>SOC Completo (Todo incluido)</option>
                        <option>Custom Enterprise</option>
                    </select>
                </div>
                
                <button type="submit" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; border: none; border-radius: 10px; font-size: 18px; font-weight: 600; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    📧 Enviar Solicitud
                </button>
            </form>
            
            <p style="text-align: center; margin-top: 20px; color: #94a3b8; font-size: 14px;">
                O escríbenos directamente a: <a href="mailto:info@vineksec.online" style="color: #2563eb; font-weight: 600;">info@vineksec.online</a>
            </p>
        </div>
    </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', scheduleHTML);
    
    // Handle form submission
    document.getElementById('demoScheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // In production, send to your API
        // For now, use mailto
        const name = this.querySelector('input[type="text"]').value;
        const email = this.querySelector('input[type="email"]').value;
        const company = this.querySelectorAll('input[type="text"]')[1].value;
        const phone = this.querySelector('input[type="tel"]').value;
        const interest = this.querySelector('select').value;
        
        const subject = encodeURIComponent(`Demo Request - ${company}`);
        const body = encodeURIComponent(`Solicitud de Demo JORISE SOC

Nombre: ${name}
Email: ${email}
Empresa: ${company}
Teléfono: ${phone}
Interés: ${interest}

Por favor, contactarme para agendar una demo de 15 minutos.

Saludos`);
        
        window.location.href = `mailto:info@vineksec.online?subject=${subject}&body=${body}`;
        
        document.getElementById('scheduleModal').remove();
        
        // Show success message
        alert('✅ ¡Solicitud enviada! Te contactaremos en menos de 24 horas.');
    });
}

// ============================================
// SIGNUP MODAL
// ============================================
function openSignupModal(tier = 'free') {
    const modal = document.getElementById('signupModal');
    if (modal) modal.style.display = 'block';
    
    // Store selected tier
    sessionStorage.setItem('selectedTier', tier);
}

function closeSignupModal() {
    const modal = document.getElementById('signupModal');
    if (modal) modal.style.display = 'none';
}

// Handle signup links
document.querySelectorAll('a[href^="#signup"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        const tier = href.split('-')[1] || 'free';
        openSignupModal(tier);
    });
});

// Close modal on outside click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('signupModal');
    if (e.target === modal) {
        closeSignupModal();
    }
});

// Handle signup form submission
document.getElementById('signupForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = this.querySelector('input[type="email"]').value;
    const password = this.querySelector('input[type="password"]').value;
    const tier = sessionStorage.getItem('selectedTier') || 'free';
    
    try {
        // TODO: Replace with actual API endpoint
        const response = await fetch('/api/signup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password, tier })
        });
        
        if (response.ok) {
            // Redirect to dashboard
            window.location.href = '/dashboard.html';
        } else {
            const error = await response.json();
            alert(`Error: ${error.message}`);
        }
    } catch (err) {
        console.error('Signup error:', err);
        alert('Error al crear cuenta. Intenta de nuevo.');
    }
});

// ============================================
// SMOOTH SCROLL
// ============================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        
        // Skip if it's a modal trigger
        if (href.includes('signup') || href === '#') {
            return;
        }
        
        e.preventDefault();
        const target = document.querySelector(href);
        
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ============================================
// NAVBAR SCROLL EFFECT
// ============================================
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', function() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // Add shadow on scroll
    if (scrollTop > 50) {
        navbar?.classList.add('scrolled');
        navbar.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    } else {
        navbar?.classList.remove('scrolled');
        navbar.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
    }
    
    lastScrollTop = scrollTop;
});

// ============================================
// INTERSECTION OBSERVER (Animate on Scroll)
// ============================================
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe all sections
document.querySelectorAll('.layer, .pricing-card, .testimonial-card, .faq-item').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
});

// ============================================
// STATS COUNTER ANIMATION
// ============================================
function animateCounter(element, target, duration = 2000) {
    const start = 0;
    const increment = target / (duration / 16); // 60fps
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = formatNumber(target);
            clearInterval(timer);
        } else {
            element.textContent = formatNumber(Math.floor(current));
        }
    }, 16);
}

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(0) + 'K';
    }
    return num.toString();
}

// Trigger counter animation when stats section is visible
const statsObserver = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
            entry.target.classList.add('animated');
            
            const statElements = entry.target.querySelectorAll('.stat h3');
            statElements.forEach((el, index) => {
                const targets = [87, 2400000, 5, 99.9]; // Corresponding stat values
                if (targets[index]) {
                    animateCounter(el, targets[index]);
                }
            });
        }
    });
}, { threshold: 0.5 });

const statsRow = document.querySelector('.stats-row');
if (statsRow) statsObserver.observe(statsRow);

// ============================================
// CONTACT SALES FORM
// ============================================
document.querySelectorAll('a[href="#contact-sales"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Simple mailto for now
        const subject = encodeURIComponent('Solicitud Demo Enterprise - JORISE SOC');
        const body = encodeURIComponent(`Hola,

Estoy interesado en el plan SOC ENTERPRISE de JORISE.

Información de mi empresa:
- Nombre de empresa: 
- Tráfico mensual: 
- Número de empleados: 
- Necesidades específicas: 

¿Podemos agendar una demo?

Saludos`);
        
        window.location.href = `mailto:info@vineksec.online?subject=${subject}&body=${body}`;
    });
});

// ============================================
// ENTERPRISE CTA
// ============================================
document.querySelectorAll('a[href="#enterprise"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        
        const enterpriseSection = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;" id="enterpriseModal">
            <div style="background: white; padding: 50px; border-radius: 20px; max-width: 600px; position: relative;">
                <span style="position: absolute; top: 20px; right: 30px; font-size: 32px; cursor: pointer; color: #64748b;" onclick="document.getElementById('enterpriseModal').remove();">&times;</span>
                
                <h2 style="font-size: 32px; margin-bottom: 20px; color: #1a202c;">🏢 CUSTOM ENTERPRISE</h2>
                
                <p style="font-size: 18px; color: #64748b; margin-bottom: 30px;">Para empresas con necesidades específicas:</p>
                
                <ul style="list-style: none; padding: 0; margin-bottom: 30px;">
                    <li style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-size: 20px;">✓</span>
                        <span style="font-size: 16px;">On-Premise deployment</span>
                    </li>
                    <li style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-size: 20px;">✓</span>
                        <span style="font-size: 16px;">Custom ML models entrenados con tus datos</span>
                    </li>
                    <li style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-size: 20px;">✓</span>
                        <span style="font-size: 16px;">Dedicated SOC analysts 24/7</span>
                    </li>
                    <li style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-size: 20px;">✓</span>
                        <span style="font-size: 16px;">SLA 99.99% uptime</span>
                    </li>
                    <li style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-size: 20px;">✓</span>
                        <span style="font-size: 16px;">Compliance reports (SOC 2, ISO 27001, PCI-DSS)</span>
                    </li>
                    <li style="padding: 15px 0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-size: 20px;">✓</span>
                        <span style="font-size: 16px;">Integraciones custom con tus sistemas</span>
                    </li>
                </ul>
                
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
                    <p style="font-size: 14px; color: #64748b; margin-bottom: 10px;">Precio:</p>
                    <p style="font-size: 36px; font-weight: 800; color: #2563eb;">$999+<span style="font-size: 18px; color: #64748b; font-weight: 400;">/mes</span></p>
                    <p style="font-size: 14px; color: #64748b;">Según necesidades específicas</p>
                </div>
                
                <a href="mailto:info@vineksec.online?subject=Solicitud CUSTOM ENTERPRISE" style="display: block; width: 100%; padding: 16px; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 16px;">
                    📧 Contactar Ventas Enterprise
                </a>
            </div>
        </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', enterpriseSection);
    });
});

// ============================================
// DEMO CALL SCHEDULING
// ============================================
document.querySelectorAll('a[href="#demo-call"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Integrate with Calendly or similar
        // For now, use mailto
        const subject = encodeURIComponent('Solicitud Demo JORISE SOC');
        const body = encodeURIComponent(`Hola,

Quiero agendar una demo en vivo de JORISE SOC.

Mis horarios disponibles:
- Opción 1: 
- Opción 2: 
- Opción 3: 

Información:
- Empresa: 
- Rol: 
- Teléfono: 

Saludos`);
        
        window.location.href = `mailto:info@vineksec.online?subject=${subject}&body=${body}`;
    });
});

// ============================================
// LAYER HOVER EFFECTS
// ============================================
document.querySelectorAll('.layer').forEach(layer => {
    layer.addEventListener('mouseenter', function() {
        const layerNum = this.getAttribute('data-layer');
        const icon = this.querySelector('.layer-icon');
        
        // Pulse animation
        icon.style.animation = 'pulse 0.5s ease';
    });
    
    layer.addEventListener('mouseleave', function() {
        const icon = this.querySelector('.layer-icon');
        icon.style.animation = 'none';
    });
});

// Add pulse animation to CSS dynamically
const style = document.createElement('style');
style.textContent = `
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
`;
document.head.appendChild(style);

// ============================================
// MOBILE MENU (if needed)
// ============================================
function createMobileMenu() {
    if (window.innerWidth <= 768) {
        const navbar = document.querySelector('.navbar .container');
        const navLinks = document.querySelector('.nav-links');
        
        if (!document.querySelector('.mobile-menu-toggle')) {
            const menuToggle = document.createElement('button');
            menuToggle.className = 'mobile-menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.style.cssText = 'background: none; border: none; font-size: 24px; cursor: pointer; color: #2563eb;';
            
            menuToggle.addEventListener('click', function() {
                navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
                navLinks.style.flexDirection = 'column';
                navLinks.style.position = 'absolute';
                navLinks.style.top = '60px';
                navLinks.style.right = '0';
                navLinks.style.background = 'white';
                navLinks.style.padding = '20px';
                navLinks.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                navLinks.style.borderRadius = '12px';
            });
            
            navbar.appendChild(menuToggle);
        }
    }
}

window.addEventListener('resize', createMobileMenu);
createMobileMenu();

// ============================================
// CONSOLE EASTER EGG
// ============================================
console.log('%c🛡️ JORISE SOC V2', 'font-size: 24px; font-weight: bold; color: #2563eb;');
console.log('%c¿Eres desarrollador? Únete a nuestro equipo: careers@vineksec.online', 'font-size: 14px; color: #64748b;');
console.log('%cStack: Python/FastAPI + TensorFlow + PostgreSQL + Redis', 'font-size: 12px; color: #10b981;');

// ============================================
// ANALYTICS (Google Analytics / Plausible)
// ============================================
// TODO: Add tracking code here
// Example: gtag('event', 'signup_click', { tier: 'pro' });

// Track CTA clicks
document.querySelectorAll('.btn-primary, .btn-hero-primary, .btn-cta-large').forEach(btn => {
    btn.addEventListener('click', function() {
        console.log('CTA clicked:', this.textContent.trim());
        // gtag('event', 'cta_click', { button: this.textContent.trim() });
    });
});

// Track pricing card interactions
document.querySelectorAll('.pricing-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        const tier = this.querySelector('h3').textContent;
        console.log('Pricing card hovered:', tier);
        // gtag('event', 'pricing_view', { tier: tier });
    });
});

console.log('✅ JORISE Landing Page loaded successfully');
