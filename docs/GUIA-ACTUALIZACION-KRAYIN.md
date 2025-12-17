# Guía de Actualización de Krayin CRM - ENACOM

## Información General

Este documento describe el procedimiento para actualizar Krayin CRM manteniendo las personalizaciones de ENACOM.

**Fork de ENACOM:** `git@github.com:epalacios-enacom/laravel-crm.git`  
**Repositorio Oficial:** `https://github.com/krayin/laravel-crm.git`  
**Rama principal:** `2.1`

---

## Personalizaciones Realizadas

Las siguientes modificaciones se han hecho al código de Krayin:

### 1. Columna "Empresa" en Grid de Leads
**Archivo:** `packages/Webkul/Admin/src/DataGrids/Lead/LeadDataGrid.php`
- Join a tabla `organizations`
- Select de `organization_name`
- Filtro para organización
- Columna "Empresa" con dropdown de búsqueda

### 2. Filtro "Empresa" en Kanban de Leads
**Archivo:** `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`
- Columna `person.organization.id` en método `getKanbanColumns()`

### 3. Personas Asociadas en Edición de Organización
**Archivos:**
- `packages/Webkul/Admin/src/Http/Controllers/Contact/OrganizationController.php`
- `packages/Webkul/Admin/src/Resources/views/contacts/organizations/edit.blade.php`

---

## Procedimiento de Actualización

### Paso 1: Preparación (En tu máquina local)

```bash
cd e:\MAGENTO\ENACOM\CRM\laravel-crm-fork

# Crear backup de la rama actual
git checkout 2.1
git branch backup-$(date +%Y%m%d)

# Agregar el repositorio oficial como "upstream" (solo la primera vez)
git remote add upstream https://github.com/krayin/laravel-crm.git
```

### Paso 2: Obtener cambios del upstream

```bash
# Obtener los últimos cambios de Krayin oficial
git fetch upstream

# Ver qué commits hay nuevos
git log --oneline HEAD..upstream/2.1
```

### Paso 3: Hacer merge

```bash
# Hacer merge de los cambios
git merge upstream/2.1
```

### Paso 4: Resolver conflictos (si los hay)

Si hay conflictos, Git te lo indicará. Los archivos más probables son:

1. `LeadDataGrid.php` - Revisar que el join de organizations siga presente
2. `LeadController.php` - Verificar que la columna "Empresa" esté en getKanbanColumns()
3. `OrganizationController.php` - Verificar que se carguen las personas
4. `edit.blade.php` (organizations) - Verificar la sección de personas asociadas

Para resolver:
```bash
# Ver archivos en conflicto
git status

# Editar cada archivo manualmente y resolver los conflictos
# Buscar marcadores: <<<<<<< HEAD, =======, >>>>>>> upstream/2.1

# Después de resolver
git add <archivo>
git commit -m "merge: resolve conflicts with upstream 2.1"
```

### Paso 5: Probar localmente

```bash
# Si tienes un entorno local, probarlo ahí primero
# Verificar que las personalizaciones funcionen:
# - Grid de Leads muestra columna Empresa
# - Kanban tiene filtro Empresa
# - Editar organización muestra personas asociadas
```

### Paso 6: Subir cambios

```bash
git push origin 2.1
```

### Paso 7: Desplegar en servidor

```bash
ssh root@CRM-krayin-enacom

cd /opt/krayin-crm/src
git fetch origin
git reset --hard origin/2.1

# Limpiar cachés
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Si hay migraciones nuevas
php artisan migrate --force
```

---

## Rollback (Si algo sale mal)

### En el servidor:
```bash
cd /opt/krayin-crm/src

# Ver commits anteriores
git log --oneline -10

# Volver a un commit específico
git reset --hard <commit-hash>

# Limpiar cachés
php artisan cache:clear
```

### En tu fork local:
```bash
cd e:\MAGENTO\ENACOM\CRM\laravel-crm-fork

# Volver a la rama de backup
git checkout backup-YYYYMMDD

# O revertir el merge
git reset --hard HEAD~1
git push origin 2.1 --force
```

---

## Commits Importantes de ENACOM

Para referencia, estos son los commits con las personalizaciones:

```bash
# Ver commits de ENACOM
git log --oneline --author="tu-email" -20

# O buscar por mensaje
git log --oneline --grep="Empresa"
git log --oneline --grep="organization"
```

---

## Estructura de Repositorios

```
Servidor (/opt/krayin-crm/)
├── packages/Webkul/EnacomLeadOrg/  # Paquete original (ya no se usa)
├── scripts/                         # Scripts de despliegue
└── src/                             # Código de Krayin (fork de ENACOM)
    └── git remote: epalacios-enacom/laravel-crm

Local (e:\MAGENTO\ENACOM\CRM\)
├── krayin-crm-repo/                 # Contiene paquete EnacomLeadOrg y scripts
│   └── git remote: epalacios-enacom/krayin-crm
└── laravel-crm-fork/                # Fork de Krayin con personalizaciones
    └── git remote: epalacios-enacom/laravel-crm
    └── git remote upstream: krayin/laravel-crm
```

---

## Contacto y Recursos

- **Krayin Docs:** https://devdocs.krayincrm.com/
- **Krayin GitHub:** https://github.com/krayin/laravel-crm
- **Changelog:** https://github.com/krayin/laravel-crm/releases

---

*Documento creado: 2024-12-17*
*Última actualización: 2024-12-17*
