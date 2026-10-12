# Guia para crear y editar productos
## Sistema de Restaurante Barrio Peruano

Esta guia explica como registrar un producto y mantener actualizada su informacion desde el modulo **Inventario**. Estas acciones deben ser realizadas por un usuario con permisos de administrador.

## 1. Abrir Inventario

1. Inicia sesion con tu usuario.
2. En el menu lateral, abre **Inventario**.
3. En la lista puedes revisar nombre, categoria, costo, precio, stock y estado del producto.

## 2. Crear un producto

1. Pulsa **Nuevo Producto**.
2. Completa **Nombre del Producto**. Por ejemplo: `Lomo Saltado`.
3. Selecciona una **Categoria** activa.
4. Ingresa el **Precio de Venta** en soles. El precio es obligatorio y no puede ser negativo.
5. Si el producto tiene existencias, ingresa la cantidad en **Stock Inicial**. Este valor se registrara como una entrada inicial en el Kardex. Si no corresponde controlar stock, puedes dejarlo vacio.
6. Si usas lector de barras, ingresa el **Codigo de Barras**. Es opcional y no puede repetirse en otro producto.
7. Deja activada la casilla **Disponible para la venta** si el producto debe aparecer para venderse en el POS. Desmarcala si se registrara solo como insumo.
8. Opcionalmente, selecciona una imagen. El archivo debe ser una imagen y no superar 2 MB.
9. Pulsa **Guardar Producto** y confirma que aparezca en el inventario.

## 3. Editar un producto

1. En **Inventario**, busca el producto en la lista. Usa la paginacion si no aparece en la pagina actual.
2. Pulsa el icono de edicion del producto.
3. Actualiza nombre, categoria, precio, codigo de barras o disponibilidad para la venta segun corresponda.
4. Para cambiar la imagen, selecciona un nuevo archivo. Si no eliges otra imagen, se conserva la actual.
5. Revisa el **Stock Actual**. Este campo es solo de lectura en la pantalla de edicion.
6. Pulsa **Guardar Cambios** y verifica el resultado en el inventario.

## 4. Cambiar el stock

El stock actual no se modifica desde **Editar Producto**. Para actualizarlo:

1. Vuelve a **Inventario**.
2. Pulsa el boton **Ajustar Stock** del producto.
3. Selecciona una operacion:
   - **Sumar:** agrega la cantidad indicada al stock actual.
   - **Restar:** descuenta la cantidad indicada; el stock no bajara de cero.
   - **Fijar:** establece la cantidad indicada como nuevo stock total.
4. Ingresa una cantidad entera igual o mayor que cero y pulsa **Guardar Ajuste**.
5. Consulta **Ver Kardex** para revisar el movimiento registrado.

Si el nuevo stock queda por encima de cero, el sistema activa la disponibilidad para la venta cuando esta se encontraba desactivada.

## 5. Estado y disponibilidad

- **Disponible para la venta** determina si el producto puede ofrecerse en el POS. Un producto no vendible puede usarse como insumo de una receta.
- **Activo/Inactivo** es un estado distinto: se cambia desde el boton de estado en la lista de inventario.
- La opcion de eliminar desactiva el producto; no elimina fisicamente su registro. Para volver a usarlo, activalo desde la lista.

## 6. Validaciones frecuentes

| Situacion | Que revisar |
|---|---|
| No aparece una categoria | Debe existir y estar activa para poder seleccionarla. |
| El codigo de barras no se guarda | Verifica que no este asignado a otro producto. |
| El sistema rechaza el precio | Ingresa un valor numerico igual o mayor que cero. |
| No puedes cambiar el stock al editar | Usa **Ajustar Stock** desde la lista de inventario. |
| No se acepta la imagen | Verifica que sea un archivo de imagen de hasta 2 MB. |

> Antes de corregir una diferencia de inventario, revisa el producto y sus movimientos en el Kardex. Evita ajustar stock para ocultar un error de venta sin verificar primero la orden relacionada.