
# Plan de trabajo (Project Leader) – Consulta Padrón → Fiscalización

## 1) Análisis de tablas y datos (estado actual)

### Hallazgos claves
- **Padrones separados**: existen al menos dos padrones:
  - `padroncp` (Carrera Ciencia Política)
  - `padroncd24` (Facultad 2024)
- **Registro de votos**: tabla `votos` como log histórico.
- **Campos accesorios en padrones**: `id_responsable1..3`, `id_partido`, `id_trabajo`, `voto17/19/21`, etc.
- **Problemas**: MyISAM, latin1, sin claves foráneas, sin integridad referencial.

### Impactos
- Falta de integridad y performance.
- Dificultad para escalar o hacer cruces históricos.
- Columnas “voto17/19/21” impiden agregar elecciones nuevas sin modificar estructura.

---

## 2) Propuesta de nueva base (MySQL)

### Principios
- Motor: **InnoDB**, codificación **utf8mb4**.
- `persona` central con `dni UNIQUE`.
- `padron` como entidad que define ámbito (FACULTAD o CARRERA) y año.
- Relaciones N:M con vigencia (`desde/hasta`).
- Tablas `eleccion`, `mesa`, `voto` preparadas para Fiscalización.

### Esquema propuesto
Incluye tablas:  
`persona`, `carrera`, `partido`, `trabajo`, `responsable`, `padron`, `padron_item`, `persona_responsable`, `persona_partido`, `persona_trabajo`, `eleccion`, `mesa`, `tipo_voto`, `usuario`, `voto`.

---

## 3) ETL de los insumos
1. Crear esquema `staging`.
2. Importar `padroncp` y `padroncd24` sin modificar estructura.
3. Normalizar:
   - `persona`
   - `padron` y `padron_item`
   - `partido`, `trabajo`, `responsable`
   - Relaciones N:M
4. Convertir `voto17/19/21` en participaciones históricas (`voto`).

---

## 4) App “Consulta Padrón” (PHP)
- Buscador por persona (DNI, Apellido).
- Reportes por padrón, referente, partido, trabajo.
- Exportar CSV.
- Vistas SQL: `vw_persona_detalle`, `vw_padron_resumen`, `vw_referente_personas`.

### Estructura
```
consulta-padron/
├── public/
├── src/
│   ├── Config/
│   ├── Controllers/
│   ├── Models/
│   ├── Views/
├── vendor/
└── storage/
```

---

## 5) Escalamiento a “Fiscalización”
- Agregar módulos `eleccion`, `mesa`, `voto`, `usuario`.
- Reutilizar `padron` como padrón oficial.
- UI de registro de votos en tiempo real.
- Tablero en vivo de participación.

---

## 6) Gobernanza y GitHub
- Esquema `fiscalizar_core` y `fiscalizar_stage`.
- Migraciones versionadas (`/db/migrations`).
- ETL en `/etl/`.
- Seeds en `/db/seeds`.
- Ramas `feature/*`, `main` protegida, releases taggeadas.

---

## 7) Cronograma
**Semana 1** – Base nueva + carga padrones + reportes básicos.  
**Semana 2** – Limpieza de catálogos + mejoras UI.  
**Semana 3** – Migrar históricos + módulo elecciones.

---

## 8) Riesgos
- Duplicados por DNI → merge asistido.  
- IDs opacos → mapeo iterativo.  
- Textos libres → normalización controlada.  
- Codificación → migrar todo a utf8mb4.

---

## 9) Próximos pasos
1. Crear base y correr migraciones.  
2. Ejecutar ETL.  
3. Crear vistas y controlador PHP.  
4. Cargar votos históricos.  
5. Subir a hosting `consulta-padron/`.

---

## Resumen ejecutivo
- Migrar MyISAM a InnoDB y centralizar datos personales.  
- Diseñar esquema normalizado y escalable.  
- Implementar MVP de reportes y dejar base lista para fiscalización.
