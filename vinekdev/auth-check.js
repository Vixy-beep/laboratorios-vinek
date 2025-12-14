// ============================================
// Sistema de Autenticación Global - Persistent Login
// ============================================

// Variables globales
let currentUser = null;

// Verificar autenticación al cargar la página
document.addEventListener('DOMContentLoaded', () => {
    checkUserSession();
});

// Función principal para verificar sesión
async function checkUserSession() {
    try {
        const response = await fetch('/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'checkAuth' })
        });

        const data = await response.json();

        if (data.success && data.user) {
            currentUser = data.user;
            updateUIWithUser(data.user);
        } else {
            updateUIWithoutUser();
        }
    } catch (error) {
        console.error('Error verificando sesión:', error);
        updateUIWithoutUser();
    }
}

// Actualizar UI cuando el usuario está logueado
function updateUIWithUser(user) {
    // Actualizar todos los botones de login por menú de perfil
    const loginButtons = document.querySelectorAll('.btn-login');
    loginButtons.forEach(btn => {
        const parent = btn.parentElement;
        if (parent) {
            parent.innerHTML = `
                <div class="user-menu">
                    <div class="user-avatar-btn">
                        <img src="${user.avatar}" alt="${user.name}" class="user-avatar-img">
                        <span class="user-name-text">${user.name}</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="user-dropdown">
                        <a href="/profile.html?id=${user.id}">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                        ${user.role === 'admin' || user.role === 'super_admin' ? `
                            <a href="/admin.html">
                                <i class="fas fa-shield-alt"></i> Panel Admin
                            </a>
                        ` : ''}
                        ${user.role === 'author' || user.role === 'admin' || user.role === 'super_admin' ? `
                            <a href="/author-panel.html">
                                <i class="fas fa-pen"></i> Crear Post
                            </a>
                        ` : ''}
                        <a href="/vixymastery.html">
                            <i class="fas fa-trophy"></i> Vixy Mastery
                        </a>
                        <a href="/settings.html">
                            <i class="fas fa-cog"></i> Configuración
                        </a>
                        <hr>
                        <a href="#" onclick="logoutUser(event)">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
            `;

            // Agregar evento para toggle dropdown
            const avatarBtn = parent.querySelector('.user-avatar-btn');
            const dropdown = parent.querySelector('.user-dropdown');
            
            avatarBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('active');
            });

            // Cerrar dropdown al hacer click fuera
            document.addEventListener('click', (e) => {
                if (!parent.contains(e.target)) {
                    dropdown.classList.remove('active');
                }
            });
        }
    });

    // Actualizar mobile menu
    const mobileMenu = document.querySelector('.mobile-menu');
    if (mobileMenu) {
        // Buscar y remover link de login
        const mobileLoginLink = Array.from(mobileMenu.querySelectorAll('a')).find(a => 
            a.textContent.includes('Login') || a.href.includes('login.html')
        );
        
        if (mobileLoginLink) {
            mobileLoginLink.outerHTML = `
                <div class="mobile-user-info">
                    <img src="${user.avatar}" alt="${user.name}" class="mobile-avatar">
                    <div class="mobile-user-details">
                        <span class="mobile-user-name">${user.name}</span>
                        <span class="mobile-user-role">${getRoleName(user.role)}</span>
                    </div>
                </div>
                <a href="/profile.html?id=${user.id}"><i class="fas fa-user"></i> Mi Perfil</a>
                ${user.role === 'admin' || user.role === 'super_admin' ? `<a href="/admin.html"><i class="fas fa-shield-alt"></i> Panel Admin</a>` : ''}
                ${user.role === 'author' || user.role === 'admin' || user.role === 'super_admin' ? `<a href="/author-panel.html"><i class="fas fa-pen"></i> Crear Post</a>` : ''}
                <a href="/vixymastery.html"><i class="fas fa-trophy"></i> Vixy Mastery</a>
                <a href="/settings.html"><i class="fas fa-cog"></i> Configuración</a>
                <a href="#" onclick="logoutUser(event)" class="logout-link"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            `;
        }
    }
}

// Actualizar UI cuando NO hay usuario logueado
function updateUIWithoutUser() {
    currentUser = null;
    // Los botones de login ya están por defecto, no hacer nada
}

// Cerrar sesión
async function logoutUser(event) {
    if (event) event.preventDefault();

    try {
        const response = await fetch('/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'logout' })
        });

        const data = await response.json();

        if (data.success) {
            // Recargar la página para limpiar todo
            window.location.reload();
        }
    } catch (error) {
        console.error('Error al cerrar sesión:', error);
        alert('Error al cerrar sesión. Intenta de nuevo.');
    }
}

// Obtener nombre del rol en español
function getRoleName(role) {
    const roles = {
        'admin': 'Administrador',
        'author': 'Autor',
        'user': 'Usuario'
    };
    return roles[role] || 'Usuario';
}

// Exportar funciones globales
window.checkUserSession = checkUserSession;
window.logoutUser = logoutUser;
window.currentUser = () => currentUser;
