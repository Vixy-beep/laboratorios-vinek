// ============================================
// POST PAGE - JAVASCRIPT
// ============================================

// Configuración de API (Hostinger)
const API_URL = 'https://vineksec.online/blog-api.php';
const COMMENTS_API = 'https://vineksec.online/comments-api.php';
const AUTH_URL = 'https://vineksec.online/auth-clean.php';

document.addEventListener('DOMContentLoaded', function() {
    // Obtener ID del post de la URL
    // Soporta tanto /post/slug-123 como /post.html?id=123
    const urlParams = new URLSearchParams(window.location.search);
    let postId = urlParams.get('id');
    
    // Si no hay ID en query string, extraer del path (para URLs amigables)
    if (!postId) {
        const path = window.location.pathname;
        const match = path.match(/\/post\/[^\/]+-(\d+)$/);
        if (match) {
            postId = match[1];
        }
    }
    
    if (!postId) {
        showError();
        return;
    }
    
    // Cargar el post
    loadPost(postId);
    
    // Incrementar vistas
    incrementViews(postId);
    
    // Setup newsletter
    setupNewsletter();
    
    // Setup comentarios
    setupComments(postId);
});

// Cargar post completo
async function loadPost(postId) {
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const postContent = document.getElementById('post-content');
    
    try {
        const response = await fetch(`${API_URL}?action=getPost&id=${postId}`);
        const data = await response.json();
        
        if (data.success && data.post) {
            const post = data.post;
            
            // Actualizar URL con slug (sin recargar página)
            if (post.slug && window.history.replaceState) {
                const newUrl = `/post/${post.slug}-${post.id}`;
                window.history.replaceState(null, '', newUrl);
            }
            
            // Ocultar loading, mostrar contenido
            loadingState.style.display = 'none';
            postContent.style.display = 'block';
            
            // Actualizar título de la página
            document.getElementById('pageTitle').textContent = `${post.title} | VinekDev`;
            document.title = `${post.title} | VinekDev`;
            
            // Meta description
            const metaDesc = document.querySelector('meta[name="description"]');
            if (metaDesc) {
                metaDesc.content = post.excerpt || post.content.substring(0, 150);
            }
            
            // Breadcrumb
            document.getElementById('post-breadcrumb-category').textContent = post.category;
            
            // Categoría
            const categoryEl = document.getElementById('post-category');
            categoryEl.innerHTML = `${getCategoryIcon(post.category)} ${post.category}`;
            
            // Título
            document.getElementById('post-title').textContent = post.title;
            
            // Autor - usar avatar de la base de datos o generar uno
            const authorImage = post.author_avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(post.author)}&background=6366f1&color=fff&size=200`;
            const authorImgEl = document.getElementById('post-author-image');
            authorImgEl.src = authorImage;
            authorImgEl.onerror = function() {
                this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(post.author)}&background=6366f1&color=fff&size=200`;
            };
            
            // Configurar enlace del autor al perfil
            const authorLink = document.getElementById('post-author-link');
            if (authorLink && post.author_id) {
                authorLink.href = `profile.html?id=${post.author_id}`;
            }
            
            document.getElementById('post-author-name').innerHTML = `${post.author} <i class="fas fa-arrow-right" style="font-size: 0.8rem; margin-left: 0.5rem; color: #6366f1;"></i>`;
            // document.getElementById('post-author-role').textContent = getRoleText(post.author_role);
            
            // Fecha y metadata
            document.getElementById('post-date').textContent = formatDate(post.created_at);
            document.getElementById('post-reading-time').textContent = estimateReadingTime(post.content);
            document.getElementById('post-views').textContent = post.views || 0;
            
            // Imagen destacada
            const imageUrl = post.featured_image || post.image || 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1200&q=80';
            document.getElementById('post-image').src = imageUrl;
            document.getElementById('post-image').alt = post.title;
            
            // Contenido del post
            document.getElementById('post-body-content').innerHTML = post.content;
            
            // Configurar botones de compartir
            setupSocialShare(post);
            
            // Cargar posts relacionados
            loadRelatedPosts(post.category, postId);
            
        } else {
            showError();
        }
    } catch (error) {
        console.error('Error cargando post:', error);
        showError();
    }
}

// Mostrar estado de error
function showError() {
    document.getElementById('loading-state').style.display = 'none';
    document.getElementById('error-state').style.display = 'block';
}

// Incrementar vistas
async function incrementViews(postId) {
    try {
        await fetch(`${API_URL}?action=incrementViews&id=${postId}`);
    } catch (error) {
        console.error('Error incrementando vistas:', error);
    }
}

// Configurar botones de compartir
function setupSocialShare(post) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(post.title);
    const text = encodeURIComponent(post.excerpt || post.title);
    
    document.getElementById('share-twitter').href = `https://twitter.com/intent/tweet?text=${title}&url=${url}`;
    document.getElementById('share-facebook').href = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
    document.getElementById('share-linkedin').href = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
    document.getElementById('share-whatsapp').href = `https://wa.me/?text=${title}%20${url}`;
}

// Cargar posts relacionados
async function loadRelatedPosts(category, currentPostId) {
    try {
        const response = await fetch(`${API_URL}?action=getPosts&category=${category}&limit=3`);
        const data = await response.json();
        
        if (data.success && data.posts) {
            const relatedPosts = data.posts.filter(post => post.id != currentPostId).slice(0, 3);
            const grid = document.getElementById('related-posts-grid');
            
            if (relatedPosts.length > 0) {
                grid.innerHTML = relatedPosts.map(post => createRelatedPostCard(post)).join('');
            } else {
                grid.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">No hay artículos relacionados</p>';
            }
        }
    } catch (error) {
        console.error('Error cargando posts relacionados:', error);
    }
}

// Crear tarjeta de post relacionado
function createRelatedPostCard(post) {
    const imageUrl = post.featured_image || post.image || 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=600&q=80';
    
    return `
        <a href="post.html?id=${post.id}" class="blog-card">
            <div class="blog-card-image">
                <img src="${imageUrl}" alt="${escapeHtml(post.title)}">
                <span class="blog-card-category">${getCategoryIcon(post.category)} ${escapeHtml(post.category)}</span>
            </div>
            <div class="blog-card-content">
                <div class="blog-card-meta">
                    <span><i class="fas fa-calendar-alt"></i> ${formatDate(post.created_at)}</span>
                    <span><i class="fas fa-eye"></i> ${post.views || 0}</span>
                </div>
                <h3>${escapeHtml(post.title)}</h3>
                <p>${escapeHtml(post.excerpt || post.content.substring(0, 100))}...</p>
            </div>
        </a>
    `;
}

// Configurar newsletter
function setupNewsletter() {
    const form = document.getElementById('newsletterForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = form.querySelector('input[type="email"]').value;
            
            // Aquí puedes agregar la lógica para suscribir al newsletter
            alert('¡Gracias por suscribirte! (Funcionalidad pendiente)');
            form.reset();
        });
    }
}

// Estimar tiempo de lectura (palabras por minuto)
function estimateReadingTime(content) {
    const wordsPerMinute = 200;
    const text = content.replace(/<[^>]*>/g, ''); // Quitar HTML
    const wordCount = text.split(/\s+/).length;
    const readingTime = Math.ceil(wordCount / wordsPerMinute);
    return readingTime;
}

// Obtener ícono de categoría
function getCategoryIcon(category) {
    const icons = {
        'Ciberseguridad': '<i class="fas fa-shield-alt"></i>',
        'Pentesting': '<i class="fas fa-user-secret"></i>',
        'Cursos': '<i class="fas fa-graduation-cap"></i>',
        'Tutoriales': '<i class="fas fa-book"></i>',
        'Forense Digital': '<i class="fas fa-fingerprint"></i>',
        'WAF': '<i class="fas fa-fire-extinguisher"></i>'
    };
    return icons[category] || '<i class="fas fa-file-alt"></i>';
}

// Formatear fecha
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', options);
}

// Obtener texto del rol
function getRoleText(role) {
    const roles = {
        'super_admin': 'Super Admin',
        'admin': 'Administrador',
        'author': 'Autor',
        'Security Expert': 'Experto en Seguridad'
    };
    return roles[role] || 'Colaborador';
}

// Escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// SISTEMA DE COMENTARIOS
// ============================================

let currentUser = null;

async function setupComments(postId) {
    // Verificar autenticación
    await checkUserAuth();
    
    // Cargar comentarios existentes
    await loadComments(postId);
    
    // Setup formulario
    const form = document.getElementById('comment-form');
    const textarea = document.getElementById('comment-content');
    const charCount = document.getElementById('char-count');
    
    // Contador de caracteres
    textarea.addEventListener('input', function() {
        const count = this.value.length;
        charCount.textContent = `${count} / 1000`;
        
        if (count > 900) {
            charCount.style.color = '#fca5a5';
        } else {
            charCount.style.color = 'rgba(255, 255, 255, 0.5)';
        }
    });
    
    // Enviar comentario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!currentUser) {
            showCommentAlert('Debes iniciar sesión para comentar', 'error');
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
            return;
        }
        
        const content = textarea.value.trim();
        const honeypot = document.getElementById('website').value;
        
        if (!content || content.length < 3) {
            showCommentAlert('El comentario es demasiado corto', 'error');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publicando...';
        
        try {
            const response = await fetch(COMMENTS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    post_id: postId,
                    content: content,
                    website: honeypot
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showCommentAlert('¡Comentario publicado!', 'success');
                textarea.value = '';
                charCount.textContent = '0 / 1000';
                
                // Recargar comentarios
                await loadComments(postId);
            } else {
                showCommentAlert(data.message || 'Error al publicar comentario', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showCommentAlert('Error de conexión', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

async function checkUserAuth() {
    try {
        const response = await fetch(AUTH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'checkAuth' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
        } else {
            currentUser = null;
            // Mostrar mensaje de login
            const formContainer = document.getElementById('comment-form-container');
            formContainer.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-lock" style="font-size: 3rem; color: #6366f1; margin-bottom: 1rem;"></i>
                    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 1.5rem;">Debes tener una cuenta para comentar</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="login.html" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); border: none; padding: 0.75rem 2rem; border-radius: 8px; color: #fff; font-weight: 600; text-decoration: none;">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                        <a href="register.html" class="btn btn-secondary" style="display: inline-block; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); padding: 0.75rem 2rem; border-radius: 8px; color: #a5b4fc; font-weight: 600; text-decoration: none;">
                            <i class="fas fa-user-plus"></i> Registrarse
                        </a>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error checking auth:', error);
        currentUser = null;
    }
}

async function loadComments(postId) {
    const commentsList = document.getElementById('comments-list');
    
    try {
        const response = await fetch(`${COMMENTS_API}?action=getComments&post_id=${postId}`);
        const data = await response.json();
        
        if (data.success) {
            const count = data.count || 0;
            document.getElementById('comments-count').textContent = count;
            
            // SIEMPRE limpiar el loading
            commentsList.innerHTML = '';
            
            if (count === 0) {
                commentsList.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: rgba(255, 255, 255, 0.5);">
                        <i class="fas fa-comments" style="font-size: 3rem; color: #6366f1; margin-bottom: 1rem;"></i>
                        <p>Sé el primero en comentar este artículo</p>
                    </div>
                `;
            } else {
                data.comments.forEach(comment => {
                    const commentEl = createCommentElement(comment);
                    commentsList.appendChild(commentEl);
                });
            }
        } else {
            commentsList.innerHTML = '<p style="text-align: center; color: rgba(255, 255, 255, 0.5);">Error al cargar comentarios</p>';
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        commentsList.innerHTML = '<p style="text-align: center; color: rgba(255, 255, 255, 0.5);">Error al cargar comentarios</p>';
    }
}

function createCommentElement(comment) {
    const div = document.createElement('div');
    div.className = 'comment-item';
    div.style.cssText = 'background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem;';
    
    const canDelete = currentUser && (
        currentUser.id == comment.user_id ||
        currentUser.role === 'super_admin' ||
        (currentUser.role === 'admin' && comment.author_role !== 'super_admin') ||
        (currentUser.role === 'moderator' && !['admin', 'super_admin', 'moderator'].includes(comment.author_role))
    );
    
    div.innerHTML = `
        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
            <a href="profile.html?id=${comment.user_id}" style="text-decoration: none;">
                <img src="${comment.author_avatar}" alt="${comment.author_name}" 
                     style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid #6366f1; object-fit: cover;">
            </a>
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                    <div>
                        <a href="profile.html?id=${comment.user_id}" style="color: #fff; font-weight: 600; text-decoration: none; font-size: 1.05rem;">
                            ${escapeHtml(comment.author_name)}
                        </a>
                        <span style="color: rgba(255, 255, 255, 0.5); font-size: 0.85rem; margin-left: 0.5rem;">
                            <i class="fas fa-clock"></i> ${comment.time_ago}
                        </span>
                    </div>
                    ${canDelete ? `
                        <button onclick="deleteComment(${comment.id})" 
                                style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: all 0.3s ease;"
                                onmouseover="this.style.background='rgba(239, 68, 68, 0.2)'"
                                onmouseout="this.style.background='rgba(239, 68, 68, 0.1)'">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    ` : ''}
                </div>
                <p style="color: rgba(255, 255, 255, 0.9); line-height: 1.6; margin: 0;">${comment.content}</p>
            </div>
        </div>
    `;
    
    return div;
}

async function deleteComment(commentId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este comentario?')) {
        return;
    }
    
    try {
        const response = await fetch(COMMENTS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                comment_id: commentId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showCommentAlert('Comentario eliminado', 'success');
            
            // Obtener post ID y recargar comentarios
            const urlParams = new URLSearchParams(window.location.search);
            const postId = urlParams.get('id');
            await loadComments(postId);
        } else {
            showCommentAlert(data.message || 'Error al eliminar comentario', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showCommentAlert('Error de conexión', 'error');
    }
}

function showCommentAlert(message, type) {
    const alert = document.getElementById('comment-alert');
    alert.style.display = 'block';
    alert.textContent = message;
    
    if (type === 'success') {
        alert.style.background = 'rgba(34, 197, 94, 0.1)';
        alert.style.border = '1px solid rgba(34, 197, 94, 0.3)';
        alert.style.color = '#86efac';
    } else {
        alert.style.background = 'rgba(239, 68, 68, 0.1)';
        alert.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        alert.style.color = '#fca5a5';
    }
    
    setTimeout(() => {
        alert.style.display = 'none';
    }, 5000);
}
