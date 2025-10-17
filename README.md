
# Consulta Padrón

Proyecto inicial del sistema **Fiscalizar** para la Facultad de Ciencias Sociales (UBA).  
Permite gestionar y consultar padrones de graduados y sus vínculos con referentes, partidos y trabajos.

## Objetivos
- Consolidar padrones de Facultad y Carrera.
- Permitir cruces entre personas, referentes y espacios políticos.
- Registrar procesos electorales y participación.

## Estructura del proyecto
```
consulta-padron/
├── public/           # punto de entrada (index.php)
├── src/
│   ├── Config/       # credenciales y configuración
│   ├── Controllers/  # lógica de reportes
│   ├── Models/       # acceso a base de datos
│   ├── Views/        # plantillas
├── db/               # migraciones y seeds
├── etl/              # scripts de normalización
└── storage/          # logs y temporales
```

## Base de datos
MySQL 8.x o MariaDB 10.6+  
Motor **InnoDB**, codificación **utf8mb4**.  
Entidad central: `persona` (dni UNIQUE).

## Instalación rápida
1. Crear base de datos `fiscalizar_core`.
2. Correr las migraciones de `/db/migrations`.
3. Configurar `/src/Config/db.php` con credenciales.
4. Subir la carpeta al hosting (`consulta-padron/`).
5. Acceder vía navegador.

## Próximo paso
Migrar el módulo de votación a **Fiscalización**, reutilizando el mismo esquema y agregando la capa de registro de votos en tiempo real.

---

© 2025 – Equipo Fiscalizar
