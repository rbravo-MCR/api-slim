# API Slim PHP con Autenticación JWT y 2FA

Este proyecto es una API RESTful construida con **Slim Framework 4** que implementa un sistema completo de autenticación, incluyendo registro, inicio de sesión, autenticación de dos factores (2FA) vía correo electrónico, y recuperación de contraseñas.

## 🚀 Características

- **Framework**: Slim 4
- **Autenticación**: JWT (JSON Web Tokens) con Access y Refresh Tokens.
- **Seguridad**: 2FA (OTP) enviado por correo electrónico.
- **Base de Datos**: MySQL con PDO.
- **Correo**: PHPMailer con soporte para SMTP (SSL/TLS).
- **Arquitectura**: Estructura limpia separada en Capas (Application, Infrastructure, Domain).

## 📋 Requisitos

- PHP 8.1 o superior
- Composer
- MySQL 5.7 o superior

## 🛠️ Instalación

1.  **Clonar el repositorio** (si aplica) o descargar los archivos.

2.  **Instalar dependencias**:

    ```bash
    composer install
    ```

3.  **Configurar entorno**:

    - Copia el archivo `.env.example` a `.env` (si no existe, crea uno nuevo).
    - Configura las credenciales de base de datos y correo.

    ```ini
    # .env
    APP_ENV=local
    APP_DEBUG=true
    APP_BASE_URL=http://localhost:8000

    # Base de Datos
    DB_HOST=localhost
    DB_PORT=3306
    DB_DATABASE=api_slim_db
    DB_USERNAME=root
    DB_PASSWORD=

    # JWT
    JWT_SECRET=tu_secreto_super_seguro_y_largo
    JWT_TTL=3600
    JWT_REFRESH_TTL=604800

    # Correo (SMTP)
    MAIL_HOST=smtp.mailtrap.io
    MAIL_PORT=2525
    MAIL_USERNAME=tu_usuario
    MAIL_PASSWORD=tu_password
    MAIL_ENCRYPTION=tls
    MAIL_FROM_ADDRESS=no-reply@tuapp.com
    MAIL_FROM_NAME="Tu App"
    ```

4.  **Base de Datos**:
    - Ejecuta el script SQL incluido para crear las tablas necesarias.
    - Archivo: `database.sql`

## ▶️ Ejecución Local

Para iniciar el servidor de desarrollo localmente y evitar problemas con las rutas (404), utiliza el siguiente comando que hace uso del router personalizado:

```bash
php -S localhost:8000 -t public public/router.php
```

El servidor estará disponible en `http://localhost:8000`.

## 📡 Endpoints de la API

### 🔓 Autenticación (Público)

| Método | Endpoint                | Descripción                           | Body Requerido                                       |
| :----- | :---------------------- | :------------------------------------ | :--------------------------------------------------- |
| `POST` | `/auth/register`        | Registrar nuevo usuario               | `{"email": "...", "password": "...", "name": "..."}` |
| `POST` | `/auth/login`           | Iniciar sesión (envía OTP)            | `{"email": "...", "password": "..."}`                |
| `POST` | `/auth/verify-otp`      | Verificar código 2FA y obtener tokens | `{"email": "...", "code": "123456"}`                 |
| `POST` | `/auth/forgot-password` | Solicitar reset de contraseña         | `{"email": "..."}`                                   |
| `POST` | `/auth/reset-password`  | Cambiar contraseña con token          | `{"token": "...", "newPassword": "..."}`             |
| `POST` | `/auth/refresh`         | Refrescar Access Token                | (Requiere implementación en controller)              |

### 🔒 Privado (Requiere Header `Authorization: Bearer <token>`)

| Método | Endpoint  | Descripción                      |
| :----- | :-------- | :------------------------------- |
| `GET`  | `/api/me` | Obtener datos del usuario actual |
| `GET`  | `/health` | Verificar estado del servicio    |

## 🧪 Pruebas

Puedes probar la API utilizando **Postman** o **Insomnia**.

1.  **Registro**: Crea un usuario.
2.  **Login**: Ingresa tus credenciales. Recibirás un `userId` y un mensaje indicando que se envió el código.
3.  **Verificar OTP**: Usa el código que llegó a tu correo (o revisa la tabla `two_factor_codes` si estás en local sin salida de correo real) en el endpoint `/auth/verify-otp`.
4.  **Acceso**: Copia el `access_token` recibido y úsalo en el Header `Authorization` para acceder a `/api/me`.

## 📁 Estructura del Proyecto

```
/
├── bootstrap/       # Configuración inicial y contenedor DI
├── config/          # Archivos de configuración (settings.php)
├── public/          # Punto de entrada (index.php, router.php)
├── routes/          # Definición de rutas (api.php)
├── src/
│   ├── Application/
│   │   ├── Controllers/  # Lógica de los endpoints
│   │   ├── Middleware/   # JWT Middleware
│   │   └── Services/     # Lógica de negocio (Auth, Mail, User...)
│   └── Infrastructure/
│       └── Database/     # Conexión y Repositorios
├── vendor/          # Dependencias de Composer
└── database.sql     # Script de creación de tablas
```
