Este directorio almacena las imágenes subidas por los usuarios del panel admin.

Estructura:
- Las imágenes se nombran con: img_{uniqid}_{timestamp}.{extension}
- Solo se permiten: JPG, PNG, GIF, WEBP
- Tamaño máximo: 5MB

Seguridad:
- .htaccess previene ejecución de PHP
- upload-image.php valida tipo y tamaño
- Requiere autenticación para subir
