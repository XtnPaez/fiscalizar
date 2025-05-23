# FISCALIZAR

## Objetivo general

Desarrollar un sistema web que permita gestionar padrones de graduados, docentes y otros actores vinculados, con la capacidad de:

    Filtrar, cruzar y analizar vínculos con referentes, partidos políticos y lugares de trabajo.

    Registrar procesos electorales y registrar participación (voto) en tiempo real por fiscales.

    Generar reportes dinámicos según múltiples criterios (por referente, por padrón, por trabajo, etc.).
    
## Datos base:

    Padrones:

        Graduados de la facultad (20.000).

        Graduados de la carrera de Ciencia Política (5.000).

        Docentes asociados (1.000), con posible cruce con otros padrones.

    Listas adicionales:

        Referentes (300).

        Partidos políticos.

        Lugares de trabajo.

    Todos los listados de personas tienen DNI como campo común identificador.
    
## Decisiones clave:

    Todos los datos de personas se centralizan en una tabla de personas, con id interno unico y dni como clave alternativa.

    Se mantendrán padrones separados para facultad y ciencia política, por ser elecciones distintas.

    El sistema debe permitir registrar elecciones pasadas y futuras, con identificación de mesas, fiscales y votantes.

    Se prevé que una persona pueda tener múltiples:

        Referentes.

        Afiliaciones políticas.

        Vínculos laborales.
        
## Diseño lógico:

    Modelo centrado en la persona como entidad única, con roles definidos por relaciones (graduado, afiliado, referente, etc.).

    Relaciones muchos-a-muchos manejadas con tablas intermedias, por ejemplo:

        Persona ↔ Referente.

        Persona ↔ Partido.

        Persona ↔ Trabajo.

    Las relaciones deben ser registradas y consultables, no deducidas dinámicamente.
    
## Funcionalidades previstas:

    Búsqueda avanzada por nombre, apellido o DNI.

    Listados filtrables por referente, partido, trabajo, padrón, votó/no votó, etc.

    Registro de votación por fiscales en tiempo real.

    Registro de nuevas elecciones con sus respectivas mesas y asignación de fiscales.
    
🧠 Prompt para reiniciar el contexto (útil para README o futuras sesiones):

    Estoy trabajando en un proyecto llamado Fiscalizar, un sistema web con MySQL que gestiona padrones de personas (graduados, docentes, etc.) y los cruza con listas de referentes, partidos políticos y lugares de trabajo. Se usa una tabla única de personas identificadas por DNI e ID interno, y relaciones muchos-a-muchos mediante tablas intermedias (por ejemplo, persona ↔ referente). También debe gestionar elecciones, registrar quién votó, y permitir que fiscales marquen asistencia desde las mesas. Necesito ayuda para continuar el desarrollo, desde diseño de base de datos hasta lógica de aplicación y consultas/reportes.

## Cómo vamos?

23/5/2025

- Vamos a contratar donweb como hosting para el nuevo desarrollo
- Vamos a alojar la base y nos vamos a conectar remotos
- Ya conectamos Visual con el repo