# Script de setup automático del VPS
# Ejecuta todo en una sola sesión SSH

$VPS_IP = "207.244.255.208"
$VPS_PASS = "w7rxXbgqz792c8BJ"

Write-Host "==> conectando al vps y ejecutando setup completo..." -ForegroundColor Cyan

# Crear script remoto que se ejecutará
$setupScript = @'
#!/bin/bash
set -e

echo "==> verificando recursos del sistema..."
free -h
df -h
echo "CPU cores: $(nproc)"
echo ""

echo "==> actualizando sistema base..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq curl wget git ufw fail2ban htop net-tools

echo "==> instalando docker..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
    sh /tmp/get-docker.sh
    systemctl enable docker
    systemctl start docker
    rm /tmp/get-docker.sh
else
    echo "docker ya instalado"
fi

echo "==> instalando docker-compose..."
if ! command -v docker-compose &> /dev/null; then
    curl -fsSL "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
else
    echo "docker-compose ya instalado"
fi

echo "==> verificando instalación docker..."
docker --version
docker-compose --version

echo "==> instalando postgresql client..."
apt-get install -y -qq postgresql-client

echo "==> creando estructura de directorios..."
mkdir -p /opt/vixy/{backend,frontend,database,labs,logs}
mkdir -p /opt/vixy/labs/{templates,active}
mkdir -p /opt/vixy/backend/{src,config}

echo "==> configurando firewall básico..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment "SSH"
ufw allow 80/tcp comment "HTTP"
ufw allow 443/tcp comment "HTTPS"
ufw allow 3000/tcp comment "API Backend"
echo "y" | ufw enable

echo "==> configurando fail2ban para SSH..."
cat > /etc/fail2ban/jail.local <<EOF
[sshd]
enabled = true
port = 22
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
findtime = 600
EOF

systemctl restart fail2ban

echo "==> optimizando sistema para contenedores..."
cat >> /etc/sysctl.conf <<EOF

# Optimización para Docker
vm.max_map_count=262144
net.ipv4.ip_forward=1
net.bridge.bridge-nf-call-iptables=1
net.bridge.bridge-nf-call-ip6tables=1
fs.file-max=65536
EOF

sysctl -p

echo "==> configurando limits para contenedores..."
cat >> /etc/security/limits.conf <<EOF

# Limits para Docker
* soft nofile 65536
* hard nofile 65536
* soft nproc 4096
* hard nproc 4096
EOF

echo "==> creando archivo de configuración base..."
cat > /opt/vixy/config.env <<EOF
# Configuración base Vixy Platform
NODE_ENV=production
API_PORT=3000
MAX_CONCURRENT_LABS=15
MAX_CONCURRENT_EXAMS=10
CONTAINER_MEMORY_LIMIT=200m
CONTAINER_CPU_LIMIT=0.3
LAB_TIMEOUT=10800
EXAM_TIMEOUT=3600
DATABASE_HOST=localhost
DATABASE_PORT=5432
DATABASE_NAME=vixy_platform
DATABASE_USER=vixy_admin
JWT_SECRET=$(openssl rand -hex 32)
EOF

echo "==> generando password segura para postgres..."
PG_PASSWORD=$(openssl rand -base64 24)
echo "DATABASE_PASSWORD=$PG_PASSWORD" >> /opt/vixy/config.env

echo "==> instalando postgresql con docker..."
docker pull postgres:16-alpine

docker run -d \
  --name vixy-postgres \
  --restart unless-stopped \
  -e POSTGRES_DB=vixy_platform \
  -e POSTGRES_USER=vixy_admin \
  -e POSTGRES_PASSWORD=$PG_PASSWORD \
  -p 5432:5432 \
  -v vixy-pgdata:/var/lib/postgresql/data \
  postgres:16-alpine

echo "==> esperando que postgres inicie..."
sleep 10

echo "==> verificando contenedores activos..."
docker ps

echo "==> resumen de configuración..."
echo ""
echo "===================================="
echo "  VIXY PLATFORM - SETUP COMPLETO"
echo "===================================="
echo ""
echo "Recursos disponibles:"
free -h | grep Mem
df -h / | tail -n 1
echo "CPU cores: $(nproc)"
echo ""
echo "Directorio base: /opt/vixy"
echo "Config file: /opt/vixy/config.env"
echo "PostgreSQL password guardada en config.env"
echo ""
echo "Firewall activo (UFW):"
ufw status numbered
echo ""
echo "Contenedores corriendo:"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo ""
echo "Próximos pasos:"
echo "1. Subir código del backend a /opt/vixy/backend"
echo "2. Subir código del frontend a /opt/vixy/frontend"
echo "3. Crear plantillas de labs en /opt/vixy/labs/templates"
echo "4. Configurar dominio y SSL con certbot"
echo ""
'@

# Guardar script temporalmente con line endings Unix (LF)
$setupScript -replace "`r`n","`n" | Out-File -FilePath "$env:TEMP\vps-setup-remote.sh" -Encoding UTF8 -NoNewline

Write-Host "==> subiendo script de setup al vps..." -ForegroundColor Yellow

# Usar plink si está disponible, sino ssh regular
$plinkPath = "C:\Program Files\PuTTY\plink.exe"
if (Test-Path $plinkPath) {
    Write-Host "==> usando plink (PuTTY)..." -ForegroundColor Green
    
    # Subir script
    & "C:\Program Files\PuTTY\pscp.exe" -pw $VPS_PASS "$env:TEMP\vps-setup-remote.sh" "root@${VPS_IP}:/tmp/setup.sh"
    
    # Ejecutar
    & $plinkPath -batch -pw $VPS_PASS "root@$VPS_IP" "chmod +x /tmp/setup.sh && /tmp/setup.sh && rm /tmp/setup.sh"
    
} else {
    Write-Host "==> usando ssh (modo interactivo)..." -ForegroundColor Yellow
    Write-Host "Cuando pida password, ingresa: $VPS_PASS" -ForegroundColor Cyan
    Write-Host ""
    
    # Crear archivo temporal con comandos
    $sshCommands = @"
cat > /tmp/setup.sh << 'EOFSCRIPT'
$setupScript
EOFSCRIPT
chmod +x /tmp/setup.sh
/tmp/setup.sh
rm /tmp/setup.sh
"@
    
    # Convertir a Unix line endings
    $sshCommands = $sshCommands -replace "`r`n","`n"
    $sshCommands | ssh "root@$VPS_IP" /bin/bash
}

Remove-Item "$env:TEMP\vps-setup-remote.sh" -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "==> SETUP COMPLETADO uwu" -ForegroundColor Green
Write-Host "==> Conecta con: ssh root@$VPS_IP" -ForegroundColor Cyan
