// ============================================
// ADMIN PANEL - JAVASCRIPT COMPLETO
// ============================================

// Configuración de API (Hostinger)
const API_URL = 'https://vineksec.online/blog-api.php';
const AUTH_URL = 'https://vineksec.online/auth-clean.php';
const UPLOAD_URL = 'https://vineksec.online/upload-image.php';
const UPLOAD_AVATAR_URL = 'https://vineksec.online/upload-avatar.php';
const NEWSLETTER_URL = 'https://vineksec.online/send-newsletter.php';

let currentEditPostId = null;
let currentEditUserId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Verificar autenticación
    checkAuth();
    
    // Cargar datos iniciales
    loadDashboardStats();
    loadRecentPosts();
    
    // Navegación lateral
    setupNavigation();
    
    // Event listeners
    setupEventListeners();
});

// ============================================
// AUTENTICACIÓN
// ============================================

async function checkAuth() {
    try {
        const response = await fetch(AUTH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'checkAuth' })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            window.location.href = 'login.html';
            return;
        }
        
        // VALIDACIÓN DE SEGURIDAD: Solo Admin y Super Admin tienen acceso completo
        if (data.user.role !== 'admin' && data.user.role !== 'super_admin') {
            // Si es autor, redirigir al panel de autor
            if (data.user.role === 'author' || data.user.role === 'editor' || data.user.role === 'moderator') {
                window.location.href = 'author-panel.html';
                return;
            }
            // Si no tiene ningún rol válido, denegar acceso
            alert('⛔ Acceso Denegado: No tienes permisos para acceder.');
            window.location.href = 'index.html';
            return;
        }
        
        // Actualizar info del usuario
        document.getElementById('adminName').textContent = data.user.name || 'Admin';
        const roleText = {
            'super_admin': 'Super Admin',
            'admin': 'Administrador',
            'author': 'Autor'
        };
        document.getElementById('adminRole').textContent = roleText[data.user.role] || 'Usuario';
        
        // Actualizar avatar en sidebar
        const sidebarAvatar = document.querySelector('.sidebar-footer .user-info img');
        console.log('DEBUG - Elemento img encontrado:', sidebarAvatar); // DEBUG
        if (sidebarAvatar) {
            console.log('DEBUG - Avatar del usuario:', data.user.avatar); // DEBUG
            console.log('DEBUG - Tipo de avatar:', typeof data.user.avatar); // DEBUG
            console.log('DEBUG - Avatar es válido:', data.user.avatar && data.user.avatar !== null && data.user.avatar !== ''); // DEBUG
            
            if (data.user.avatar && data.user.avatar !== null && data.user.avatar !== '') {
                console.log('DEBUG - Asignando src:', data.user.avatar); // DEBUG
                sidebarAvatar.src = data.user.avatar;
                console.log('DEBUG - src asignado:', sidebarAvatar.src); // DEBUG
                
                sidebarAvatar.onload = function() {
                    console.log('✅ Avatar cargado exitosamente!'); // DEBUG
                };
                
                sidebarAvatar.onerror = function(e) {
                    console.error('❌ Error cargando avatar:', e); // DEBUG
                    console.error('❌ URL que falló:', this.src); // DEBUG
                    this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.user.name) + '&background=6366f1&color=fff&size=200';
                };
            } else {
                console.log('DEBUG - No hay avatar válido, usando generado'); // DEBUG
                sidebarAvatar.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.user.name) + '&background=6366f1&color=fff&size=200';
            }
        } else {
            console.error('❌ No se encontró el elemento img del sidebar!'); // DEBUG
        }
        
        // Actualizar avatar en la página de Configuración
        const currentAvatarPreview = document.getElementById('currentAvatarPreview');
        if (currentAvatarPreview && data.user.avatar) {
            console.log('DEBUG - Actualizando currentAvatarPreview con:', data.user.avatar); // DEBUG
            currentAvatarPreview.src = data.user.avatar;
        }
        
    } catch (error) {
        console.error('Error verificando autenticación:', error);
        window.location.href = 'login.html';
    }
}

async function logout() {
    if (!confirm('¿Seguro que deseas cerrar sesión?')) return;
    
    try {
        await fetch(AUTH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });
        
        window.location.href = 'login.html';
    } catch (error) {
        console.error('Error cerrando sesión:', error);
        window.location.href = 'login.html';
    }
}

// ============================================
// NAVEGACIÓN
// ============================================

function setupNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remover active de todos
            navItems.forEach(nav => nav.classList.remove('active'));
            document.querySelectorAll('.admin-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Agregar active al clickeado
            this.classList.add('active');
            const section = this.getAttribute('data-section');
            const sectionElement = document.getElementById(`${section}-section`);
            
            if (sectionElement) {
                sectionElement.classList.add('active');
            }
            
            // Actualizar título
            const titles = {
                'dashboard': 'Dashboard',
                'posts': 'Gestión de Posts',
                'new-post': 'Nuevo Post',
                'users': 'Gestión de Usuarios',
                'settings': 'Configuración'
            };
            document.getElementById('sectionTitle').textContent = titles[section] || 'Admin';
            
            // Cargar datos según sección
            if (section === 'posts') loadAllPosts();
            if (section === 'users') loadUsers();
        });
    });
}

// ============================================
// EVENT LISTENERS
// ============================================

function setupEventListeners() {
    // Formulario nuevo post
    const newPostForm = document.getElementById('newPostForm');
    if (newPostForm) {
        newPostForm.addEventListener('submit', handleNewPost);
    }
    
    // Formulario nuevo usuario
    const newUserForm = document.getElementById('newUserForm');
    if (newUserForm) {
        newUserForm.addEventListener('submit', handleNewUser);
    }
    
    // Búsqueda de posts
    const searchPosts = document.getElementById('searchPosts');
    if (searchPosts) {
        searchPosts.addEventListener('input', searchPosts);
    }
    
    // Filtro de categoría
    const filterCategory = document.getElementById('filterCategory');
    if (filterCategory) {
        filterCategory.addEventListener('change', function() {
            loadAllPosts(this.value);
        });
    }
}

// ============================================
// DASHBOARD
// ============================================

async function loadDashboardStats() {
    try {
        const response = await fetch(`${API_URL}?action=getStats`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalPosts').textContent = data.stats.total_posts || 0;
            document.getElementById('totalViews').textContent = data.stats.total_views || 0;
            document.getElementById('totalUsers').textContent = data.stats.total_users || 0;
            document.getElementById('postsThisMonth').textContent = data.stats.posts_this_month || 0;
        }
    } catch (error) {
        console.error('Error cargando estadísticas:', error);
    }
}

async function loadRecentPosts() {
    try {
        const response = await fetch(`${API_URL}?action=getPosts&limit=5`);
        const data = await response.json();
        
        const container = document.getElementById('recentPostsList');
        
        if (data.success && data.posts && data.posts.length > 0) {
            container.innerHTML = data.posts.map(post => `
                <div class="post-item">
                    <div class="post-info">
                        <h4 class="post-title">${escapeHtml(post.title)}</h4>
                        <div class="post-meta">
                            <span><i class="fas fa-folder"></i> ${escapeHtml(post.category)}</span>
                            <span><i class="fas fa-calendar"></i> ${formatDate(post.created_at)}</span>
                            <span><i class="fas fa-user"></i> ${escapeHtml(post.author)}</span>
                            <span><i class="fas fa-eye"></i> ${post.views || 0}</span>
                        </div>
                    </div>
                    <div class="post-actions">
                        <button onclick="editPost(${post.id})" class="btn-edit">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button onclick="deletePost(${post.id})" class="btn-delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 2rem;">No hay posts recientes</p>';
        }
    } catch (error) {
        console.error('Error cargando posts recientes:', error);
    }
}

// ============================================
// POSTS - LISTAR
// ============================================

async function loadAllPosts(category = 'all') {
    try {
        const url = category === 'all' 
            ? `${API_URL}?action=getPosts` 
            : `${API_URL}?action=getPosts&category=${category}`;
            
        const response = await fetch(url);
        const data = await response.json();
        
        const container = document.getElementById('postsList');
        
        if (data.success && data.posts && data.posts.length > 0) {
            container.innerHTML = data.posts.map(post => `
                <div class="post-item">
                    <div class="post-info">
                        <h4 class="post-title">${escapeHtml(post.title)}</h4>
                        <div class="post-meta">
                            <span><i class="fas fa-folder"></i> ${escapeHtml(post.category)}</span>
                            <span><i class="fas fa-calendar"></i> ${formatDate(post.created_at)}</span>
                            <span><i class="fas fa-user"></i> ${escapeHtml(post.author)}</span>
                            <span><i class="fas fa-eye"></i> ${post.views || 0} vistas</span>
                        </div>
                    </div>
                    <div class="post-actions">
                        <button onclick="editPost(${post.id})" class="btn-edit">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button onclick="deletePost(${post.id})" class="btn-delete">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 2rem;">No hay posts disponibles</p>';
        }
    } catch (error) {
        console.error('Error cargando posts:', error);
    }
}

// ============================================
// POSTS - CREAR
// ============================================

async function handleNewPost(e) {
    e.preventDefault();
    
    const imageUrl = document.getElementById('postImageUrl').value;
    
    const formData = {
        action: 'createPost',
        title: document.getElementById('postTitle').value,
        category: document.getElementById('postCategory').value,
        excerpt: document.getElementById('postExcerpt').value,
        content: document.getElementById('postContent').value,
        featured_image: imageUrl || ''
    };
    
    if (!formData.title || !formData.content) {
        alert('✗ El título y contenido son obligatorios');
        return;
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            const postId = data.id; // ID del nuevo post
            alert('✓ Post publicado exitosamente!');
            
            // Enviar newsletter automáticamente
            sendNewsletterNotification(postId);
            
            document.getElementById('newPostForm').reset();
            document.getElementById('postImagePreview').style.display = 'none';
            loadDashboardStats();
            loadRecentPosts();
        } else {
            alert('✗ Error al publicar: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error creando post:', error);
        alert('✗ Error de conexión');
    }
}

// ============================================
// POSTS - EDITAR
// ============================================

async function editPost(id) {
    try {
        // Obtener datos del post
        const response = await fetch(`${API_URL}?action=getPost&id=${id}`);
        const data = await response.json();
        
        if (!data.success || !data.post) {
            alert('✗ No se pudo cargar el post');
            return;
        }
        
        const post = data.post;
        currentEditPostId = id;
        
        // Crear modal de edición
        const modal = document.createElement('div');
        modal.className = 'modal active';
        modal.id = 'editPostModal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-edit"></i> Editar Post</h2>
                    <button onclick="closeEditPostModal()" class="modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="editPostForm" class="post-form">
                    <div class="form-group">
                        <label for="editPostTitle">Título *</label>
                        <input type="text" id="editPostTitle" value="${escapeHtml(post.title)}" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editPostCategory">Categoría *</label>
                            <select id="editPostCategory" required>
                                <option value="ciberseguridad" ${post.category === 'ciberseguridad' ? 'selected' : ''}>Ciberseguridad</option>
                                <option value="pentesting" ${post.category === 'pentesting' ? 'selected' : ''}>Pentesting</option>
                                <option value="scripts" ${post.category === 'scripts' ? 'selected' : ''}>Scripts / Tools</option>
                                <option value="cursos" ${post.category === 'cursos' ? 'selected' : ''}>Cursos</option>
                                <option value="tutoriales" ${post.category === 'tutoriales' ? 'selected' : ''}>Tutoriales</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editPostSlug">Slug (URL)</label>
                            <input type="text" id="editPostSlug" value="${escapeHtml(post.slug || '')}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editPostExcerpt">Extracto</label>
                        <textarea id="editPostExcerpt" rows="3">${escapeHtml(post.excerpt || '')}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="editPostContent">Contenido HTML *</label>
                        <textarea id="editPostContent" rows="10" required>${escapeHtml(post.content)}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="editPostImage">Imagen Destacada</label>
                        <div class="image-upload-container">
                            <input type="file" id="editPostImageFile" accept="image/*" style="display:none">
                            <input type="hidden" id="editPostImage" value="${escapeHtml(post.featured_image || '')}">
                            <button type="button" onclick="document.getElementById('editPostImageFile').click()" class="btn-upload">
                                <i class="fas fa-upload"></i> Cambiar Imagen
                            </button>
                            <div id="editPostImagePreview" style="${post.featured_image ? 'display:block' : 'display:none'}; margin-top: 15px;">
                                <img id="editPostImagePreviewImg" src="${escapeHtml(post.featured_image || '')}" style="max-width: 100%; max-height: 300px; border-radius: 8px; display: block;">
                                <button type="button" onclick="removeEditPostImage()" class="btn-remove-image" style="margin-top: 10px;">
                                    <i class="fas fa-times"></i> Quitar Imagen
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <button type="button" onclick="closeEditPostModal()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Event listener para el formulario
        document.getElementById('editPostForm').addEventListener('submit', handleEditPost);
        
        // Event listener para upload de imagen en modal de editar
        const editFileInput = document.getElementById('editPostImageFile');
        if (editFileInput) {
            editFileInput.addEventListener('change', async function(e) {
                const file = e.target.files[0];
                if (file) {
                    await uploadEditPostImage(file);
                }
            });
        }
        
    } catch (error) {
        console.error('Error cargando post para editar:', error);
        alert('✗ Error al cargar el post');
    }
}

async function handleEditPost(e) {
    e.preventDefault();
    
    const formData = {
        action: 'updatePost',
        id: currentEditPostId,
        title: document.getElementById('editPostTitle').value,
        slug: document.getElementById('editPostSlug').value,
        category: document.getElementById('editPostCategory').value,
        excerpt: document.getElementById('editPostExcerpt').value,
        content: document.getElementById('editPostContent').value,
        featured_image: document.getElementById('editPostImage').value
    };
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Post actualizado exitosamente!');
            closeEditPostModal();
            loadAllPosts();
            loadRecentPosts();
            loadDashboardStats();
        } else {
            alert('✗ Error al actualizar: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error actualizando post:', error);
        alert('✗ Error de conexión');
    }
}

function closeEditPostModal() {
    const modal = document.getElementById('editPostModal');
    if (modal) {
        modal.remove();
    }
    currentEditPostId = null;
}

// ============================================
// POSTS - ELIMINAR
// ============================================

async function deletePost(id) {
    if (!confirm('¿Estás seguro de eliminar este post? Esta acción no se puede deshacer.')) return;
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'deletePost', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Post eliminado exitosamente');
            loadAllPosts();
            loadRecentPosts();
            loadDashboardStats();
        } else {
            alert('✗ Error al eliminar: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error eliminando post:', error);
        alert('✗ Error de conexión');
    }
}

// ============================================
// POSTS - BÚSQUEDA
// ============================================

function searchPosts(e) {
    const query = e.target.value.toLowerCase();
    const posts = document.querySelectorAll('#postsList .post-item');
    
    posts.forEach(post => {
        const title = post.querySelector('.post-title').textContent.toLowerCase();
        post.style.display = title.includes(query) ? 'flex' : 'none';
    });
}

// ============================================
// USUARIOS - LISTAR
// ============================================

async function loadUsers() {
    try {
        const response = await fetch(`${API_URL}?action=getUsers`);
        const data = await response.json();
        
        const container = document.getElementById('usersList');
        
        if (data.success && data.users && data.users.length > 0) {
            container.innerHTML = data.users.map(user => {
                const roleText = {
                    'super_admin': 'Super Admin',
                    'admin': 'Administrador',
                    'author': 'Autor'
                };
                
                const avatarUrl = user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff&size=200`;
                
                return `
                    <div class="user-item">
                        <div class="user-info-item" style="display: flex; align-items: center; gap: 1.5rem;">
                            <img src="${avatarUrl}" alt="${escapeHtml(user.name)}" 
                                 style="width: 60px; height: 60px; border-radius: 50%; border: 3px solid #6366f1; object-fit: cover; flex-shrink: 0;"
                                 onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff&size=200'">
                            <div style="flex: 1;">
                                <h4>${escapeHtml(user.name)}</h4>
                                <p style="color: rgba(255,255,255,0.7); margin: 0.5rem 0;">${escapeHtml(user.email)}</p>
                                <p style="color: #6366f1; font-size: 0.9rem; font-weight: 600;">
                                    <i class="fas fa-user-tag"></i> ${roleText[user.role] || user.role}
                                </p>
                            </div>
                        </div>
                        <div class="post-actions">
                            <button onclick="editUser(${user.id})" class="btn-edit">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            ${user.role !== 'super_admin' ? `
                                <button onclick="deleteUser(${user.id})" class="btn-delete">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 2rem;">No hay usuarios</p>';
        }
    } catch (error) {
        console.error('Error cargando usuarios:', error);
    }
}

// ============================================
// USUARIOS - CREAR
// ============================================

function toggleNewUserForm() {
    const formContainer = document.getElementById('newUserFormContainer');
    const btn = document.getElementById('toggleUserFormBtn');
    
    if (formContainer.style.display === 'none') {
        formContainer.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-times"></i> Cancelar';
    } else {
        formContainer.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Usuario';
        document.getElementById('newUserForm').reset();
    }
}

async function handleNewUser(e) {
    e.preventDefault();
    
    const name = document.getElementById('newUserName').value;
    const email = document.getElementById('newUserEmail').value;
    const password = document.getElementById('newUserPassword').value;
    const passwordConfirm = document.getElementById('newUserPasswordConfirm').value;
    const role = document.getElementById('newUserRole').value;
    const avatar = document.getElementById('newUserAvatar').value.trim();
    
    // Validar contraseñas
    if (password !== passwordConfirm) {
        alert('✗ Las contraseñas no coinciden');
        return;
    }
    
    if (password.length < 8) {
        alert('✗ La contraseña debe tener al menos 8 caracteres');
        return;
    }
    
    const formData = {
        action: 'createUser',
        name: name,
        email: email,
        password: password,
        role: role,
        avatar: avatar || null
    };
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Usuario creado exitosamente!');
            document.getElementById('newUserForm').reset();
            toggleNewUserForm();
            loadUsers();
            loadDashboardStats();
        } else {
            alert('✗ Error al crear usuario: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error creando usuario:', error);
        alert('✗ Error de conexión');
    }
}

// ============================================
// USUARIOS - EDITAR
// ============================================

async function editUser(id) {
    try {
        // Obtener datos del usuario
        const response = await fetch(`${API_URL}?action=getUsers`);
        const data = await response.json();
        
        if (!data.success) {
            alert('✗ No se pudieron cargar los usuarios');
            return;
        }
        
        const user = data.users.find(u => u.id == id);
        if (!user) {
            alert('✗ Usuario no encontrado');
            return;
        }
        
        currentEditUserId = id;
        
        // Crear modal de edición
        const modal = document.createElement('div');
        modal.className = 'modal active';
        modal.id = 'editUserModal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-user-edit"></i> Editar Usuario</h2>
                    <button onclick="closeEditUserModal()" class="modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="editUserForm" class="post-form">
                    <div class="form-group">
                        <label for="editUserName">Nombre Completo *</label>
                        <input type="text" id="editUserName" value="${escapeHtml(user.name)}" required>
                    </div>

                    <div class="form-group">
                        <label for="editUserEmail">Email *</label>
                        <input type="email" id="editUserEmail" value="${escapeHtml(user.email)}" required>
                    </div>

                    <div class="form-group">
                        <label for="editUserRole">Rol *</label>
                        <select id="editUserRole" required ${user.role === 'super_admin' ? 'disabled' : ''}>
                            <option value="author" ${user.role === 'author' ? 'selected' : ''}>Autor (Puede crear posts)</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin (Puede crear posts y usuarios)</option>
                            <option value="super_admin" ${user.role === 'super_admin' ? 'selected' : ''}>Super Admin (Control total)</option>
                        </select>
                        ${user.role === 'super_admin' ? '<p style="color: rgba(255,255,255,0.5); font-size: 0.85rem; margin-top: 0.5rem;">No se puede modificar el rol de Super Admin</p>' : ''}
                    </div>

                    <div class="form-group">
                        <label for="editUserPassword">Nueva Contraseña (opcional)</label>
                        <input type="password" id="editUserPassword" placeholder="Dejar en blanco para mantener la actual" minlength="8">
                        <p style="color: rgba(255,255,255,0.5); font-size: 0.85rem; margin-top: 0.5rem;">Mínimo 8 caracteres. Solo cambiar si deseas actualizar la contraseña.</p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <button type="button" onclick="closeEditUserModal()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Event listener para el formulario
        document.getElementById('editUserForm').addEventListener('submit', handleEditUser);
        
    } catch (error) {
        console.error('Error cargando usuario para editar:', error);
        alert('✗ Error al cargar el usuario');
    }
}

async function handleEditUser(e) {
    e.preventDefault();
    
    const formData = {
        action: 'updateUser',
        id: currentEditUserId,
        name: document.getElementById('editUserName').value,
        email: document.getElementById('editUserEmail').value,
        role: document.getElementById('editUserRole').value
    };
    
    const newPassword = document.getElementById('editUserPassword').value;
    if (newPassword) {
        if (newPassword.length < 8) {
            alert('✗ La nueva contraseña debe tener al menos 8 caracteres');
            return;
        }
        formData.password = newPassword;
    }
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Usuario actualizado exitosamente!');
            closeEditUserModal();
            loadUsers();
            loadDashboardStats();
        } else {
            alert('✗ Error al actualizar: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error actualizando usuario:', error);
        alert('✗ Error de conexión');
    }
}

function closeEditUserModal() {
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.remove();
    }
    currentEditUserId = null;
}

// ============================================
// USUARIOS - ELIMINAR
// ============================================

async function deleteUser(id) {
    if (!confirm('¿Estás seguro de eliminar este usuario? Todos sus posts también se eliminarán. Esta acción no se puede deshacer.')) return;
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'deleteUser', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Usuario eliminado exitosamente');
            loadUsers();
            loadDashboardStats();
        } else {
            alert('✗ Error al eliminar: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error eliminando usuario:', error);
        alert('✗ Error de conexión');
    }
}

// ============================================
// UTILIDADES
// ============================================

function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', options);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// UPLOAD DE IMÁGENES
// ============================================

// Upload de imagen para nuevo post
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('postImageFile');
    if (fileInput) {
        fileInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (file) {
                await uploadPostImage(file);
            }
        });
    }
});

async function uploadPostImage(file) {
    // Validar tamaño (5MB máximo)
    if (file.size > 5 * 1024 * 1024) {
        alert('✗ La imagen es demasiado grande. Máximo 5MB');
        return false;
    }
    
    // Validar tipo
    if (!file.type.startsWith('image/')) {
        alert('✗ Solo se permiten archivos de imagen');
        return false;
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        // Mostrar indicador de carga
        const preview = document.getElementById('postImagePreview');
        const previewImg = document.getElementById('postImagePreviewImg');
        preview.style.display = 'block';
        previewImg.src = '';
        previewImg.alt = 'Subiendo...';
        
        const response = await fetch(UPLOAD_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Actualizar preview
            previewImg.src = data.url;
            previewImg.alt = 'Preview';
            
            // Guardar URL en campo oculto
            document.getElementById('postImageUrl').value = data.url;
            
            alert('✓ Imagen subida exitosamente!');
            console.log('URL guardada:', data.url);
            return true;
        } else {
            alert('✗ Error al subir imagen: ' + (data.message || 'Error desconocido'));
            preview.style.display = 'none';
            return false;
        }
    } catch (error) {
        console.error('Error subiendo imagen:', error);
        alert('✗ Error de conexión al subir imagen');
        document.getElementById('postImagePreview').style.display = 'none';
        return false;
    }
}

function removePostImage() {
    document.getElementById('postImageUrl').value = '';
    document.getElementById('postImageFile').value = '';
    document.getElementById('postImagePreview').style.display = 'none';
}

// Upload de imagen para editar post
async function uploadEditPostImage(file) {
    // Validar tamaño (5MB máximo)
    if (file.size > 5 * 1024 * 1024) {
        alert('✗ La imagen es demasiado grande. Máximo 5MB');
        return;
    }
    
    // Validar tipo
    if (!file.type.startsWith('image/')) {
        alert('✗ Solo se permiten archivos de imagen');
        return;
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        // Mostrar indicador de carga
        const preview = document.getElementById('editPostImagePreview');
        const previewImg = document.getElementById('editPostImagePreviewImg');
        preview.style.display = 'block';
        previewImg.src = '';
        previewImg.alt = 'Subiendo...';
        
        const response = await fetch(UPLOAD_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Actualizar preview
            previewImg.src = data.url;
            previewImg.alt = 'Preview';
            
            // Guardar URL en campo oculto
            document.getElementById('editPostImage').value = data.url;
            
            alert('✓ Imagen subida exitosamente!');
        } else {
            alert('✗ Error al subir imagen: ' + (data.message || 'Error desconocido'));
            preview.style.display = 'none';
        }
    } catch (error) {
        console.error('Error subiendo imagen:', error);
        alert('✗ Error de conexión al subir imagen');
        document.getElementById('editPostImagePreview').style.display = 'none';
    }
}

function removeEditPostImage() {
    document.getElementById('editPostImage').value = '';
    const fileInput = document.getElementById('editPostImageFile');
    if (fileInput) fileInput.value = '';
    document.getElementById('editPostImagePreview').style.display = 'none';
}

// ============================================
// CONFIGURACIÓN - ACTUALIZAR AVATAR
// ============================================

// Preview de avatar cuando se selecciona archivo
document.addEventListener('DOMContentLoaded', function() {
    const avatarFileInput = document.getElementById('userAvatarFile');
    if (avatarFileInput) {
        avatarFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tamaño (máximo 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('✗ La imagen es muy grande. El tamaño máximo es 2 MB.');
                    this.value = '';
                    return;
                }

                // Validar tipo
                if (!file.type.startsWith('image/')) {
                    alert('✗ Por favor selecciona una imagen válida (JPG, PNG, GIF)');
                    this.value = '';
                    return;
                }

                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreviewImg').src = e.target.result;
                    document.getElementById('avatarPreviewContainer').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

function removeAvatarPreview() {
    document.getElementById('userAvatarFile').value = '';
    document.getElementById('avatarPreviewContainer').style.display = 'none';
}

async function updateAvatar() {
    const avatarUrl = document.getElementById('userAvatar').value.trim();
    const avatarFile = document.getElementById('userAvatarFile').files[0];
    
    console.log('Intentando actualizar avatar - URL:', avatarUrl, 'File:', avatarFile); // DEBUG
    
    // Verificar que al menos uno esté presente
    if (!avatarUrl && !avatarFile) {
        alert('✗ Por favor selecciona una imagen desde tu PC o ingresa una URL');
        return;
    }

    // Si hay archivo, subirlo primero
    if (avatarFile) {
        await uploadAvatarFile(avatarFile);
        return;
    }

    // Si solo hay URL, validarla y guardarla
    if (avatarUrl) {
        try {
            new URL(avatarUrl);
        } catch (e) {
            alert('✗ URL inválida. Asegúrate de que sea una URL completa (ejemplo: https://...)');
            return;
        }
        
        await saveAvatarUrl(avatarUrl);
    }
}

// Subir archivo de avatar
async function uploadAvatarFile(file) {
    console.log('Subiendo archivo:', file.name, 'Tamaño:', file.size); // DEBUG
    
    const formData = new FormData();
    formData.append('image', file);
    formData.append('action', 'uploadAvatar');
    
    try {
        const response = await fetch(UPLOAD_AVATAR_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('Respuesta upload:', data); // DEBUG
        
        if (data.success && data.url) {
            console.log('Imagen subida exitosamente, guardando URL:', data.url); // DEBUG
            // Guardar la URL en la base de datos
            await saveAvatarUrl(data.url);
        } else {
            alert('✗ Error al subir imagen: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error subiendo archivo:', error);
        alert('✗ Error al subir la imagen. Verifica tu conexión.');
    }
}

// Guardar URL de avatar en la base de datos
async function saveAvatarUrl(avatarUrl) {
    console.log('Guardando URL en base de datos:', avatarUrl); // DEBUG
    
    try {
        console.log('Enviando petición a:', API_URL + '?action=updateUserAvatar'); // DEBUG
        
        const response = await fetch(API_URL + '?action=updateUserAvatar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ avatar: avatarUrl })
        });
        
        const data = await response.json();
        console.log('Respuesta del servidor:', data); // DEBUG
        
        if (data.success) {
            alert('✓ ¡Foto de perfil actualizada exitosamente!');
            
            // Actualizar avatar en sidebar inmediatamente
            const sidebarAvatar = document.querySelector('.sidebar-footer .user-info img');
            if (sidebarAvatar) {
                console.log('Actualizando imagen del sidebar a:', avatarUrl); // DEBUG
                sidebarAvatar.src = avatarUrl;
            }
            
            // Actualizar preview
            const currentPreview = document.getElementById('currentAvatarPreview');
            if (currentPreview) {
                currentPreview.src = avatarUrl;
            }
            
            // Limpiar formularios
            document.getElementById('userAvatar').value = '';
            removeAvatarPreview();
            
            // Recargar después de 1.5 segundos
            setTimeout(() => {
                console.log('Recargando página...'); // DEBUG
                location.reload();
            }, 1500);
        } else {
            console.error('Error del servidor:', data.message); // DEBUG
            alert('✗ Error al actualizar foto de perfil: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error guardando avatar:', error);
        alert('✗ Error de conexión. Verifica tu conexión a internet.');
    }
}

// ============================================
// MODERACIÓN DE POSTS
// ============================================

// Cargar posts pendientes
async function loadPendingPosts() {
    try {
        const response = await fetch(`${API_URL}?action=getPendingPosts`);
        const data = await response.json();
        
        const pendingPostsList = document.getElementById('pendingPostsList');
        const noPendingPosts = document.getElementById('noPendingPosts');
        const pendingBadge = document.getElementById('pendingBadge');
        
        if (data.success && data.posts && data.posts.length > 0) {
            pendingPostsList.innerHTML = '';
            noPendingPosts.style.display = 'none';
            
            // Actualizar badge en el menú
            pendingBadge.textContent = data.posts.length;
            pendingBadge.style.display = 'inline-block';
            
            data.posts.forEach(post => {
                const postCard = createPendingPostCard(post);
                pendingPostsList.appendChild(postCard);
            });
        } else {
            pendingPostsList.innerHTML = '';
            noPendingPosts.style.display = 'block';
            pendingBadge.style.display = 'none';
        }
    } catch (error) {
        console.error('Error cargando posts pendientes:', error);
        alert('Error al cargar posts pendientes');
    }
}

// Crear tarjeta de post pendiente
function createPendingPostCard(post) {
    const card = document.createElement('div');
    card.className = 'post-card pending-post-card';
    
    const authorImage = post.author_avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(post.author)}&background=6366f1&color=fff&size=200`;
    
    card.innerHTML = `
        <div class="post-card-header">
            <div class="post-author-info">
                <img src="${authorImage}" alt="${post.author}" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                <div>
                    <strong>${post.author}</strong>
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin: 0;">
                        ${new Date(post.created_at).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' })}
                    </p>
                </div>
            </div>
            <span class="post-status pending">⏳ Pendiente</span>
        </div>
        <h3>${post.title}</h3>
        <p class="post-excerpt">${post.excerpt || post.content.substring(0, 150) + '...'}</p>
        <div class="post-meta">
            <span><i class="fas fa-folder"></i> ${post.category}</span>
            <span><i class="fas fa-clock"></i> ${post.reading_time || '5'} min</span>
        </div>
        <div class="post-card-actions">
            <button onclick="viewPostPreview(${post.id})" class="btn-secondary">
                <i class="fas fa-eye"></i> Vista Previa
            </button>
            <button onclick="approvePostAction(${post.id})" class="btn-success">
                <i class="fas fa-check"></i> Aprobar
            </button>
            <button onclick="rejectPostAction(${post.id})" class="btn-danger">
                <i class="fas fa-times"></i> Rechazar
            </button>
        </div>
    `;
    
    return card;
}

// Vista previa de post
function viewPostPreview(postId) {
    window.open(`post.html?id=${postId}`, '_blank');
}

// Aprobar post
async function approvePostAction(postId) {
    if (!confirm('¿Estás seguro de que quieres aprobar y publicar este post?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}?action=approvePost`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: postId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Post aprobado y publicado exitosamente');
            
            // Enviar newsletter automáticamente
            sendNewsletterNotification(postId);
            
            loadPendingPosts();
            loadDashboardStats();
        } else {
            alert('✗ Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error aprobando post:', error);
        alert('✗ Error al aprobar el post');
    }
}

// Rechazar post
async function rejectPostAction(postId) {
    const reason = prompt('¿Por qué rechazas este post? (Opcional)');
    
    if (reason === null) {
        // Usuario canceló
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}?action=rejectPost`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: postId, reason: reason })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Post rechazado');
            loadPendingPosts();
        } else {
            alert('✗ Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error rechazando post:', error);
        alert('✗ Error al rechazar el post');
    }
}

// Verificar si el usuario es admin/super_admin y mostrar menú de moderación
async function checkModerationAccess() {
    try {
        const response = await fetch(AUTH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'checkAuth' })
        });
        
        const data = await response.json();
        
        if (data.success && (data.user.role === 'admin' || data.user.role === 'super_admin')) {
            document.getElementById('moderationNav').style.display = 'block';
            loadPendingPosts();
        }
    } catch (error) {
        console.error('Error verificando acceso a moderación:', error);
    }
}

// ============================================
// NEWSLETTER - ENVIAR NOTIFICACIÓN
// ============================================

async function sendNewsletterNotification(postId) {
    try {
        // Mostrar indicador de envío
        const sendingAlert = document.createElement('div');
        sendingAlert.id = 'sending-newsletter-alert';
        sendingAlert.style.cssText = 'position: fixed; top: 20px; right: 20px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); padding: 1rem 1.5rem; border-radius: 8px; color: #a5b4fc; z-index: 10000;';
        sendingAlert.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando newsletter... (｡•̀ᴗ-)✧';
        document.body.appendChild(sendingAlert);
        
        const formData = new FormData();
        formData.append('post_id', postId);
        
        const response = await fetch(NEWSLETTER_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        // Remover indicador de envío
        sendingAlert.remove();
        
        if (data.success) {
            // Mostrar resultado
            const resultAlert = document.createElement('div');
            resultAlert.style.cssText = 'position: fixed; top: 20px; right: 20px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem 1.5rem; border-radius: 8px; color: #86efac; z-index: 10000;';
            resultAlert.innerHTML = `✓ ${data.message} uwu<br><small>${data.sent} emails enviados exitosamente</small>`;
            document.body.appendChild(resultAlert);
            
            setTimeout(() => resultAlert.remove(), 5000);
        } else {
            alert('✗ Error al enviar newsletter: ' + data.message);
        }
    } catch (error) {
        console.error('Error enviando newsletter:', error);
        alert('✗ Error de conexión al enviar newsletter (¬_¬)');
    }
}

// Llamar al iniciar
document.addEventListener('DOMContentLoaded', function() {
    checkModerationAccess();
});

