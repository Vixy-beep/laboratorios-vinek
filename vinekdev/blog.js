// ============================================
// BLOG TECH & CURSOS - JAVASCRIPT
// ============================================

// Configuración de API (Hostinger - Producción)
const API_URL = 'https://vineksec.online/blog-api.php';

document.addEventListener('DOMContentLoaded', function() {
    // Cargar posts del blog
    loadBlogPosts();
    
    // Filtros de categorías
    setupCategoryFilters();
});

// Cargar posts desde la API
async function loadBlogPosts(category = 'all') {
    const blogGrid = document.getElementById('blog-grid');
    const blogEmpty = document.getElementById('blog-empty');
    
    try {
        // Mostrar loading
        blogGrid.innerHTML = `
            <div class="blog-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Cargando artículos...</p>
            </div>
        `;
        
        // Hacer petición a la API
        const response = await fetch(`${API_URL}?action=getPosts&category=${category}`);
        const data = await response.json();
        
        if (data.success && data.posts && data.posts.length > 0) {
            blogGrid.innerHTML = '';
            blogEmpty.style.display = 'none';
            
            data.posts.forEach(post => {
                const postCard = createBlogCard(post);
                blogGrid.appendChild(postCard);
            });
            
            // Animar las tarjetas
            animateBlogCards();
        } else {
            blogGrid.innerHTML = '';
            blogEmpty.style.display = 'block';
        }
    } catch (error) {
        console.error('Error cargando posts:', error);
        blogGrid.innerHTML = '';
        blogEmpty.style.display = 'block';
    }
}

// Crear tarjeta de blog
function createBlogCard(post) {
    const card = document.createElement('div');
    card.className = 'blog-card animate-on-scroll';
    card.setAttribute('data-category', post.category);
    
    const imageUrl = post.featured_image || post.image_url || post.image || 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=600&q=80';
    
    // Usar avatar del autor desde la base de datos o generar uno
    const authorImage = post.author_avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(post.author)}&background=6366f1&color=fff&size=200`;
    
    card.innerHTML = `
        <div class="blog-card-image">
            <img src="${imageUrl}" alt="${escapeHtml(post.title)}">
            <span class="blog-card-category">${getCategoryIcon(post.category)} ${escapeHtml(post.category)}</span>
        </div>
        <div class="blog-card-content">
            <div class="blog-card-meta">
                <span><i class="fas fa-calendar-alt"></i> ${formatDate(post.created_at)}</span>
                <span><i class="fas fa-clock"></i> ${post.read_time || post.reading_time || '5'} min</span>
            </div>
            <h3>${escapeHtml(post.title)}</h3>
            <p>${escapeHtml(post.excerpt || post.content.substring(0, 150))}...</p>
            <div class="blog-card-footer">
                <a href="profile.html?id=${post.author_id}" class="blog-card-author" style="text-decoration: none; color: inherit; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <img src="${authorImage}" alt="${escapeHtml(post.author)}">
                    <div class="blog-card-author-info">
                        <span class="blog-card-author-name">${escapeHtml(post.author)} <i class="fas fa-arrow-right" style="font-size: 0.8rem; margin-left: 0.3rem; color: #6366f1;"></i></span>
                    </div>
                </a>
                <a href="post/${post.slug}-${post.id}" class="blog-card-read-more">
                    Leer más <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    `;
    
    return card;
}

// Configurar filtros de categorías
function setupCategoryFilters() {
    const categoryButtons = document.querySelectorAll('.category-btn');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover active de todos los botones
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            
            // Agregar active al botón clickeado
            this.classList.add('active');
            
            // Obtener categoría
            const category = this.getAttribute('data-category');
            
            // Cargar posts de esa categoría
            loadBlogPosts(category);
        });
    });
}

// Animar tarjetas del blog
function animateBlogCards() {
    const cards = document.querySelectorAll('.blog-card');
    
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });
}

// Formatear fecha
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', options);
}

// Obtener icono de categoría
function getCategoryIcon(category) {
    const icons = {
        'ciberseguridad': '<i class="fas fa-shield-alt"></i>',
        'pentesting': '<i class="fas fa-user-secret"></i>',
        'cursos': '<i class="fas fa-graduation-cap"></i>',
        'tutoriales': '<i class="fas fa-code"></i>',
        'noticias': '<i class="fas fa-newspaper"></i>',
        'herramientas': '<i class="fas fa-tools"></i>'
    };
    
    return icons[category.toLowerCase()] || '<i class="fas fa-folder"></i>';
}

// Escapar HTML para prevenir XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Búsqueda de posts (opcional)
function searchPosts(query) {
    const blogCards = document.querySelectorAll('.blog-card');
    
    blogCards.forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        const content = card.querySelector('p').textContent.toLowerCase();
        
        if (title.includes(query.toLowerCase()) || content.includes(query.toLowerCase())) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
