# Mejora visual de los modales del módulo de tareas

**Fecha:** 2026-08-05  
**Estado:** Diseño aprobado

## Objetivo

Rediseñar el modal de alta/edición de tareas y el modal de detalle de una tarea asignada para que se perciban como una experiencia coherente, profesional y fácil de recorrer. La mejora conservará el verde institucional existente y no modificará reglas de negocio, permisos, validaciones ni contratos con las API.

## Alcance

El trabajo se concentra en `pages/tareas.php` y cubre:

- El modal de nueva tarea y su variante de edición.
- El modal de detalle utilizado para consultar, entregar, revisar, prorrogar, editar o eliminar una tarea según los permisos actuales.
- Los estilos compartidos de modal, formulario, destinatarios, adjuntos, estados, revisión e historial.
- Ajustes mínimos de JavaScript destinados únicamente a presentar información visual o estados de interacción.
- Comportamiento responsive, foco de teclado y cierre del modal.

No se cambiarán endpoints, estructura de datos, catálogo de estados, permisos, lógica de lotes ni reglas de entrega y revisión.

## Dirección visual

Se adopta un **panel operativo refinado**. El verde institucional será el color de orientación y acción; blancos y grises suaves darán descanso al contenido. El resultado debe sentirse propio de una herramienta de operaciones, no como un formulario genérico ni como una colección excesiva de tarjetas.

### Tokens principales

- Verde institucional: `#00875A`.
- Verde profundo: `#005C3D`.
- Fondo de contenido: `#F5F8F7`.
- Superficie principal: `#FFFFFF`.
- Texto principal: `#111827`.
- Texto secundario: `#4B5563`.
- Bordes: verdes desaturados ya presentes en el módulo.
- Estados de éxito, advertencia y error: se mantienen los tonos semánticos actuales.
- Tipografía: `DM Sans`, respetando la identidad ya establecida.

La firma visual será una franja institucional en el encabezado del modal: incluirá un icono contextual, el título y una explicación breve, haciendo que ambos flujos se reconozcan como partes de la misma experiencia.

## Modal de nueva tarea y edición

### Estructura

1. **Encabezado institucional:** icono de tarea, título dinámico, texto explicativo y botón de cierre con área interactiva amplia.
2. **Información de la tarea:** título y descripción con etiquetas persistentes, ayudas concisas y foco visible.
3. **Planificación:** prioridad y fecha límite. La prioridad se presentará como un selector visual de tres opciones, pero conservará el `select` actual como fuente de valor o una sincronización equivalente sin cambiar el payload.
4. **Asignación:** listado de destinatarios agrupado por rol, filas de selección más claras y resumen visible de cuántas tareas independientes se crearán.
5. **Edición por lote:** aviso diferenciado, únicamente cuando el comportamiento actual lo habilite.
6. **Pie fijo:** Cancelar como acción secundaria y Crear tarea/Guardar cambios como acción primaria. El texto reflejará el contexto del modal.

### Comportamiento

- El foco inicial continuará en el título.
- Las validaciones existentes se conservarán y los campos con error recibirán una señal visual breve además del toast.
- El contador de destinatarios se actualizará con la selección actual.
- El contenido podrá desplazarse sin perder acceso a las acciones.
- En móvil, los grupos pasarán a una columna y el pie seguirá siendo cómodo de usar.

## Modal de detalle y tarea asignada

### Estructura

1. **Encabezado institucional:** título, persona asignada, rol y creador, manteniendo la información dinámica actual.
2. **Resumen operativo:** estado, prioridad, plazo vigente y señal de atraso o prórroga visibles antes del contenido extenso.
3. **Encargo:** descripción y fechas dentro de una sección limpia y legible.
4. **Observaciones:** cuando existan, se mostrarán antes de la entrega con jerarquía de advertencia clara.
5. **Material y evidencia:** archivos con icono consistente, nombre, tamaño, estado y acción de eliminación accesible.
6. **Zona de entrega:** el área para adjuntar y el comentario formarán un bloque principal para quienes tengan permiso de entregar.
7. **Prórroga, revisión e historial:** conservarán el flujo actual, con jerarquía visual y espaciado uniformes para evitar que el modal parezca una sucesión desordenada de controles.
8. **Pie fijo contextual:** acciones secundarias a la izquierda y la acción principal de entrega a la derecha; en móvil se reorganizarán sin desbordarse.

### Comportamiento

- No cambia la carga dinámica mediante `get_tarea.php`.
- No cambian permisos ni condiciones para entregar, revisar, prorrogar, editar o eliminar.
- La carga de archivos mantendrá clic y arrastrar/soltar, con estados `hover`, `dragover`, foco y carga visibles.
- El botón principal conservará su bloqueo durante solicitudes de red.
- El modal mantendrá scroll interno y una altura segura respecto a la ventana.

## Accesibilidad y responsive

- Añadir `role="dialog"`, `aria-modal="true"` y relaciones con el título correspondiente.
- Los botones de cierre tendrán etiqueta accesible y un objetivo mínimo de 44 px.
- Se reforzará `:focus-visible` en botones, campos, destinatarios y controles de prioridad.
- Los campos conservarán etiquetas visibles; no dependerán solo del placeholder.
- El contraste mantendrá al menos 4.5:1 para texto normal.
- A 720 px o menos, las rejillas de dos columnas pasarán a una sola columna.
- A 520 px o menos, el modal ocupará casi toda la pantalla, reducirá radios y márgenes, y el pie podrá apilar acciones.
- Las animaciones serán breves y se desactivarán con `prefers-reduced-motion`.

## Arquitectura de implementación

- Se ampliarán las clases existentes `tk-mb`, `tk-modal`, `tk-mh`, `tk-mbody`, `tk-mf`, `tk-field`, `tk-dest`, `tk-sec`, `tk-drop` y `tk-file` para preservar compatibilidad.
- Se añadirán modificadores específicos para el modal de composición y el modal de detalle, evitando estilos globales fuera de `.tk-wrap` y de los fondos de modal.
- El marcado estático del alta/edición se reorganizará en secciones semánticas.
- El HTML dinámico del detalle incorporará el resumen operativo y clases presentacionales, reutilizando los datos ya disponibles en `T`.
- La selección visual de prioridad se sincronizará con `#tm-prioridad`; el valor enviado seguirá siendo `baja`, `media` o `alta`.
- No se extraerán componentes ni se hará una refactorización amplia porque el alcance es visual y el módulo ya concentra esta interfaz en un solo archivo.

## Errores y estados

- Se mantendrán los toasts existentes como comunicación general.
- Título, fecha y destinatarios mostrarán un estado visual de error cuando fallen las validaciones locales.
- Botones deshabilitados tendrán contraste y cursor coherentes.
- Los estados de archivos pendientes o con error conservarán su significado y ganarán claridad visual.
- La ausencia de descripción, material o evidencia seguirá mostrando mensajes vacíos explícitos.

## Verificación

1. Ejecutar la comprobación de sintaxis PHP de `pages/tareas.php`.
2. Ejecutar las pruebas existentes del catálogo de tareas.
3. Probar alta con uno y varios destinatarios, validaciones vacías y guardado exitoso.
4. Probar edición individual y edición por lote.
5. Probar detalle como administrador, coordinador y soporte según los permisos disponibles.
6. Verificar carga y eliminación de archivos, envío, prórroga y revisión sin regresiones visuales.
7. Revisar el modal en escritorio y anchos aproximados de 720, 520 y 375 px.
8. Comprobar navegación por teclado, foco visible, cierre con botones y fondo, y preferencia de movimiento reducido.

## Criterios de aceptación

- Ambos modales comparten una identidad visual clara y consistente con el módulo.
- La información más importante se entiende sin recorrer todo el contenido.
- Crear o entregar una tarea tiene una acción primaria inequívoca.
- No hay contenido oculto detrás del pie ni desbordamientos horizontales en móvil.
- Todas las funciones, permisos y payloads actuales siguen funcionando.
- La interfaz conserva el verde institucional sin saturar cada superficie.
