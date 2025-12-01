#!/bin/bash

# ============================================================================
# INSTALADOR SIMPLE Y AUTOMÁTICO DEL SISTEMA DE LICENCIAS
# ============================================================================

set -e

clear

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║       INSTALADOR AUTOMÁTICO DE LICENCIAS - IMAGINA             ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Este script va a:"
echo ""
echo "  1️⃣  Copiar archivos al SERVIDOR (imagina-updater-server)"
echo "  2️⃣  Copiar archivos al CLIENTE (imagina-updater-client)"
echo "  3️⃣  Mostrarte qué código añadir a cada plugin"
echo ""
echo "Presiona ENTER para continuar o Ctrl+C para cancelar..."
read

# Detectar rutas
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PARENT_DIR=$(dirname "$SCRIPT_DIR")

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  PASO 1: Instalando extensión del SERVIDOR"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Buscar servidor
if [ -d "$PARENT_DIR/imagina-updater-server" ]; then
    SERVER_DIR="$PARENT_DIR/imagina-updater-server"
    echo "✅ Servidor encontrado en: $SERVER_DIR"
else
    echo "❌ No encuentro imagina-updater-server"
    echo ""
    echo "¿Dónde está tu carpeta imagina-updater-server?"
    read -p "Ruta completa: " SERVER_DIR

    if [ ! -d "$SERVER_DIR" ]; then
        echo "❌ ERROR: Esa carpeta no existe"
        exit 1
    fi
fi

# Copiar archivos del servidor
echo ""
echo "Copiando archivos al servidor..."
cp "$SCRIPT_DIR/server-extension/class-license-api.php" "$SERVER_DIR/api/"
echo "  ✅ Copiado: api/class-license-api.php"

cp "$SCRIPT_DIR/server-extension/class-license-crypto-server.php" "$SERVER_DIR/includes/"
echo "  ✅ Copiado: includes/class-license-crypto-server.php"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  PASO 2: Instalando extensión del CLIENTE"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Buscar cliente
if [ -d "$PARENT_DIR/imagina-updater-client" ]; then
    CLIENT_DIR="$PARENT_DIR/imagina-updater-client"
    echo "✅ Cliente encontrado en: $CLIENT_DIR"
else
    echo "❌ No encuentro imagina-updater-client"
    echo ""
    echo "¿Dónde está tu carpeta imagina-updater-client?"
    read -p "Ruta completa: " CLIENT_DIR

    if [ ! -d "$CLIENT_DIR" ]; then
        echo "❌ ERROR: Esa carpeta no existe"
        exit 1
    fi
fi

# Copiar archivos del cliente
echo ""
echo "Copiando archivos al cliente..."
cp "$SCRIPT_DIR/client-extension/class-license-manager.php" "$CLIENT_DIR/includes/"
echo "  ✅ Copiado: includes/class-license-manager.php"

cp "$SCRIPT_DIR/client-extension/class-license-crypto-client.php" "$CLIENT_DIR/includes/"
echo "  ✅ Copiado: includes/class-license-crypto-client.php"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  ✅ INSTALACIÓN COMPLETADA"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Archivos copiados correctamente. Ahora solo falta 1 cosa:"
echo ""
echo "┌────────────────────────────────────────────────────────────────┐"
echo "│  IMPORTANTE: Añadir código a los plugins                      │"
echo "└────────────────────────────────────────────────────────────────┘"
echo ""
echo "Presiona ENTER para ver las instrucciones..."
read

clear

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    ÚLTIMOS PASOS                               ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "1️⃣  EDITAR PLUGIN SERVIDOR"
echo ""
echo "    Archivo: $SERVER_DIR/imagina-updater-server.php"
echo ""
echo "    Añade ESTAS 3 LÍNEAS al final del archivo:"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat << 'EOF'
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-server.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-license-api.php';
add_action( 'rest_api_init', array( 'Imagina_Updater_License_API', 'register_routes' ) );
EOF
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Presiona ENTER para continuar..."
read

clear

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    ÚLTIMOS PASOS                               ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "2️⃣  EDITAR PLUGIN CLIENTE"
echo ""
echo "    Archivo: $CLIENT_DIR/imagina-updater-client.php"
echo ""
echo "    Añade ESTAS 3 LÍNEAS después de cargar otras clases:"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat << 'EOF'
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';
add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ), 5 );
EOF
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Presiona ENTER para ver el resumen final..."
read

clear

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    ✅ RESUMEN FINAL                            ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "✅ Archivos copiados al servidor"
echo "✅ Archivos copiados al cliente"
echo ""
echo "⚠️  AHORA DEBES:"
echo ""
echo "  1. Editar imagina-updater-server.php (añadir 3 líneas)"
echo "  2. Editar imagina-updater-client.php (añadir 3 líneas)"
echo "  3. Desactivar y reactivar ambos plugins en WordPress"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📚 DOCUMENTACIÓN:"
echo ""
echo "  • $SCRIPT_DIR/QUICK_START.md"
echo "    └─> Guía rápida de 5 pasos"
echo ""
echo "  • $SCRIPT_DIR/docs/INTEGRATION.md"
echo "    └─> Guía completa de integración"
echo ""
echo "  • $SCRIPT_DIR/example-premium-plugin/"
echo "    └─> Plugin de ejemplo para copiar"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "¿Quieres ver un ejemplo de cómo crear un plugin premium?"
echo ""
read -p "Responde s/n: " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Ss]$ ]]; then
    clear
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║            CÓMO CREAR TU PLUGIN PREMIUM                        ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo ""
    echo "PASO 1: Copia la carpeta de ejemplo"
    echo ""
    echo "  cp -r $SCRIPT_DIR/example-premium-plugin mi-plugin-premium"
    echo ""
    echo "PASO 2: Copia el SDK al plugin"
    echo ""
    echo "  mkdir -p mi-plugin-premium/vendor"
    echo "  cp -r $SCRIPT_DIR/sdk mi-plugin-premium/vendor/imagina-license-sdk"
    echo ""
    echo "PASO 3: Buscar y reemplazar nombres"
    echo ""
    echo "  En todos los archivos del plugin:"
    echo "  - example-premium     →  mi-plugin-premium"
    echo "  - Example_Premium     →  Mi_Plugin_Premium"
    echo "  - EXAMPLE_PREMIUM     →  MI_PLUGIN_PREMIUM"
    echo "  - Example Premium     →  Mi Plugin Premium"
    echo ""
    echo "PASO 4: Crear ZIP y subir al servidor"
    echo ""
    echo "  zip -r mi-plugin-premium.zip mi-plugin-premium"
    echo ""
    echo "PASO 5: En el panel del servidor:"
    echo "  - Plugins > Añadir Plugin"
    echo "  - Subir el ZIP"
    echo "  - API Keys > Configurar permisos"
    echo ""
    echo "¡Listo! Tu plugin premium está protegido con licencias 🔐"
    echo ""
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "           ✨ ¡Instalación completada con éxito! ✨"
echo "════════════════════════════════════════════════════════════════"
echo ""
