#!/bin/bash

# Script de instalación automática del sistema de licencias
# Uso: ./install.sh

set -e

echo "================================================"
echo "  Instalador del Sistema de Licencias Imagina"
echo "================================================"
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para imprimir con color
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# Detectar directorio actual
CURRENT_DIR=$(pwd)
SDK_DIR="$CURRENT_DIR"

# Buscar directorios del servidor y cliente
echo "Buscando plugins de Imagina Updater..."
echo ""

# Buscar en el directorio padre
PARENT_DIR=$(dirname "$CURRENT_DIR")

# Buscar servidor
if [ -d "$PARENT_DIR/imagina-updater-server" ]; then
    SERVER_DIR="$PARENT_DIR/imagina-updater-server"
    print_success "Servidor encontrado: $SERVER_DIR"
else
    print_warning "Servidor no encontrado en la ubicación esperada"
    read -p "Introduce la ruta completa al directorio del servidor: " SERVER_DIR
    if [ ! -d "$SERVER_DIR" ]; then
        print_error "Directorio del servidor no existe: $SERVER_DIR"
        exit 1
    fi
fi

# Buscar cliente
if [ -d "$PARENT_DIR/imagina-updater-client" ]; then
    CLIENT_DIR="$PARENT_DIR/imagina-updater-client"
    print_success "Cliente encontrado: $CLIENT_DIR"
else
    print_warning "Cliente no encontrado en la ubicación esperada"
    read -p "Introduce la ruta completa al directorio del cliente: " CLIENT_DIR
    if [ ! -d "$CLIENT_DIR" ]; then
        print_error "Directorio del cliente no existe: $CLIENT_DIR"
        exit 1
    fi
fi

echo ""
echo "================================================"
echo "  Configuración de Instalación"
echo "================================================"
echo "SDK:      $SDK_DIR"
echo "Servidor: $SERVER_DIR"
echo "Cliente:  $CLIENT_DIR"
echo ""

read -p "¿Deseas continuar con la instalación? (s/n): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Ss]$ ]]; then
    print_error "Instalación cancelada"
    exit 0
fi

echo ""
echo "================================================"
echo "  Instalando Extensión del Servidor"
echo "================================================"
echo ""

# Verificar que los archivos fuente existan
if [ ! -f "$SDK_DIR/server-extension/class-license-api.php" ]; then
    print_error "No se encuentra: $SDK_DIR/server-extension/class-license-api.php"
    exit 1
fi

if [ ! -f "$SDK_DIR/server-extension/class-license-crypto-server.php" ]; then
    print_error "No se encuentra: $SDK_DIR/server-extension/class-license-crypto-server.php"
    exit 1
fi

# Copiar archivos del servidor
echo "Copiando archivos de la extensión del servidor..."

cp "$SDK_DIR/server-extension/class-license-api.php" "$SERVER_DIR/api/"
print_success "Copiado: api/class-license-api.php"

cp "$SDK_DIR/server-extension/class-license-crypto-server.php" "$SERVER_DIR/includes/"
print_success "Copiado: includes/class-license-crypto-server.php"

# Verificar si ya está instalado en el archivo principal
SERVER_MAIN_FILE="$SERVER_DIR/imagina-updater-server.php"

if grep -q "class-license-api.php" "$SERVER_MAIN_FILE"; then
    print_warning "La extensión del servidor ya parece estar instalada en imagina-updater-server.php"
else
    echo ""
    print_info "IMPORTANTE: Debes añadir el siguiente código a $SERVER_MAIN_FILE"
    echo ""
    echo "-----------------------------------------------------------"
    cat << 'EOF'
// Extensión de licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-server.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-license-api.php';
add_action( 'rest_api_init', array( 'Imagina_Updater_License_API', 'register_routes' ) );
EOF
    echo "-----------------------------------------------------------"
    echo ""
fi

echo ""
echo "================================================"
echo "  Instalando Extensión del Cliente"
echo "================================================"
echo ""

# Verificar que los archivos fuente existan
if [ ! -f "$SDK_DIR/client-extension/class-license-manager.php" ]; then
    print_error "No se encuentra: $SDK_DIR/client-extension/class-license-manager.php"
    exit 1
fi

if [ ! -f "$SDK_DIR/client-extension/class-license-crypto-client.php" ]; then
    print_error "No se encuentra: $SDK_DIR/client-extension/class-license-crypto-client.php"
    exit 1
fi

# Copiar archivos del cliente
echo "Copiando archivos de la extensión del cliente..."

cp "$SDK_DIR/client-extension/class-license-manager.php" "$CLIENT_DIR/includes/"
print_success "Copiado: includes/class-license-manager.php"

cp "$SDK_DIR/client-extension/class-license-crypto-client.php" "$CLIENT_DIR/includes/"
print_success "Copiado: includes/class-license-crypto-client.php"

# Verificar si ya está instalado en el archivo principal
CLIENT_MAIN_FILE="$CLIENT_DIR/imagina-updater-client.php"

if grep -q "class-license-manager.php" "$CLIENT_MAIN_FILE"; then
    print_warning "La extensión del cliente ya parece estar instalada en imagina-updater-client.php"
else
    echo ""
    print_info "IMPORTANTE: Debes añadir el siguiente código a $CLIENT_MAIN_FILE"
    echo ""
    echo "-----------------------------------------------------------"
    cat << 'EOF'
// Extensión de licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';
add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ), 5 );
EOF
    echo "-----------------------------------------------------------"
    echo ""
fi

echo ""
echo "================================================"
echo "  Creando Plugin de Ejemplo"
echo "================================================"
echo ""

# Preguntar si desea crear el plugin de ejemplo
read -p "¿Deseas crear un ZIP del plugin de ejemplo? (s/n): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Ss]$ ]]; then
    EXAMPLE_DIR="$SDK_DIR/example-premium-plugin"

    # Copiar SDK al plugin de ejemplo
    echo "Copiando SDK al plugin de ejemplo..."
    mkdir -p "$EXAMPLE_DIR/vendor"
    cp -r "$SDK_DIR/sdk" "$EXAMPLE_DIR/vendor/imagina-license-sdk"
    print_success "SDK copiado a vendor/imagina-license-sdk"

    # Crear ZIP
    echo "Creando ZIP del plugin de ejemplo..."
    cd "$EXAMPLE_DIR"
    zip -r "$SDK_DIR/example-premium-plugin.zip" . -x "*.git*" -x "node_modules/*" -x ".DS_Store" > /dev/null 2>&1
    cd "$CURRENT_DIR"
    print_success "ZIP creado: $SDK_DIR/example-premium-plugin.zip"
fi

echo ""
echo "================================================"
echo "  ✓ Instalación Completada"
echo "================================================"
echo ""
print_success "Archivos de extensión del servidor copiados"
print_success "Archivos de extensión del cliente copiados"
echo ""
print_warning "IMPORTANTE: Debes añadir el código mostrado arriba a los archivos principales"
echo ""
echo "Próximos pasos:"
echo ""
echo "1. Edita $SERVER_MAIN_FILE"
echo "   Añade el código de la extensión del servidor"
echo ""
echo "2. Edita $CLIENT_MAIN_FILE"
echo "   Añade el código de la extensión del cliente"
echo ""
echo "3. Reactiva ambos plugins en WordPress"
echo ""
echo "4. Lee la documentación:"
echo "   - QUICK_START.md - Inicio rápido"
echo "   - docs/INTEGRATION.md - Guía de integración"
echo "   - docs/SECURITY.md - Explicación de seguridad"
echo ""
print_success "¡Todo listo! 🚀"
echo ""
