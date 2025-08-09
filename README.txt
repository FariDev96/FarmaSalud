# Proyecto FarmaSalud - Guía 8 (GA8-220501096-AA1-EV02)

## Descripción
Este proyecto corresponde a la evidencia final de integración de módulos para la aplicación web **FarmaSalud**.  
Desarrollé esta solución como parte del programa de Tecnología en Análisis y Desarrollo de Software del **SENA**, orientada a la gestión farmacéutica.  
El sistema permite operaciones como:  
- Registro de usuarios
- Control y CRUD de productos
- Gestión de pedidos y soporte técnico
- Acceso diferenciado según el rol del usuario (Administrador, Cliente, Farmacéutico, Soporte)

---

## Requisitos técnicos
- XAMPP (Apache + MySQL)
- PHP 8.1 o superior
- MySQL 8.x
- Navegador moderno (Chrome, Firefox, Edge)
- Visual Studio Code (recomendado)

---

## Instalación
1. Descomprimir este proyecto dentro de la carpeta `htdocs` de XAMPP:
   ```
   C:/xampp/htdocs/FARMASALUD_GUIA8/
   ```
2. Crear una base de datos en phpMyAdmin con el nombre:
   ```
   farmasalud
   ```
3. Importar el script SQL ubicado en:
   ```
   /sql/base_datos.sql
   ```
4. Iniciar Apache y MySQL desde el panel de XAMPP.
5. Acceder al sistema mediante:
   ```
   http://localhost/FARMASALUD_GUIA8/modulos/login.php
   ```

---

## Accesos de prueba
- **Correo:** `Faridsanchez@gmail.com`
- **Contraseña:** `admin123`  
*(Asegurate de que este usuario exista en tu base de datos.)*

---

## Estructura del proyecto
- `/modulos/`: Lógica del sistema (login, productos, usuarios, etc.)
- `/config/`: Conexión a base de datos (db.php)
- `/css/` & `/js/`: Estilos y validaciones JS
- `/imagenes/`: Recursos visuales
- `/sql/`: Script para crear e importar la base de datos
- `/documentacion/`: Evidencias y documentación técnica

---

## Funcionalidades implementadas
- Login seguro y redirección por rol
- Registro de usuarios con validación
- CRUD de productos farmacéuticos
- Módulo de gestión de usuarios
- Carrito de compras y procesamiento de pedidos
- Panel de soporte y atención al cliente
- Seguridad de contraseñas con `password_hash()`

---

## Pruebas
Las pruebas funcionales por módulo están documentadas en:
```
/documentacion/registro_pruebas.pdf
```

---

## Documentos incluidos
- Acta de aprobación de requerimientos
- Documentación modular (entradas, salidas, procesos)
- Registro de pruebas funcionales
- Manual técnico del sistema
- Archivo `urls_sistema.txt` con rutas de acceso

---

## Autor
**Farid Leonardo Sánchez Bermejo**  
Tecnólogo en Análisis y Desarrollo de Software – Ficha 2977342  
**SENA – Año 2025**

