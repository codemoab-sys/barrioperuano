# Manual operativo del rol: Mozo / Cajero

**Sistema de Restaurante Barrio Peruano**

**Versión:** 1.0  
**Fecha:** 21 de septiembre de 2026  
**Aplicación:** Punto de venta, mesas, cocina, clientes, reservas, gastos y caja compartida.

> Este manual está orientado al personal con rol de **Mozo**, **Cajero** y **Cajero/Mozo**. Se basa en el funcionamiento real del sistema y no agrega funcionalidades fuera del flujo vigente.

---

## 1. Acceso al sistema

### 1.1 Ingresar

1. Abre el enlace del sistema en el navegador.
2. En el campo **Correo electrónico**, escribe tu correo registrado.
3. En el campo **Contraseña**, escribe tu contraseña.
4. Pulsa **Ingresar al Sistema**.
5. Si los datos son correctos, se mostrará el sistema según tu rol.

### Captura 1. Pantalla de inicio de sesión

![Pantalla de inicio de sesión](capturas/01-login.png)

> Inserta la captura real del login con datos ficticios y sin credenciales reales.

### 1.2 Cerrar sesión

1. Abre el menú de usuario.
2. Pulsa **Cerrar sesión**.
3. Cierra el navegador si estás usando un equipo compartido.

### 1.3 Problemas de acceso

- **Credenciales incorrectas:** revisa el correo y la contraseña.
- **Cuenta bloqueada:** solicita al administrador que revise la cuenta.
- **No tienes permiso:** tu rol no tiene acceso a ese módulo.
- **Debes abrir un turno de caja:** un cajero o administrador debe abrir la caja compartida.

---

## 2. Roles y permisos del flujo operativo

| Rol | Puede hacer | No debe hacer |
|---|---|---|
| **Mozo** (`waiter`) | Ver sus áreas asignadas, abrir mesas, registrar pedidos, editar items, enviar pedidos a cocina, consultar clientes y mostrar la precuenta | Confirmar pagos, emitir comprobantes, administrar productos, usuarios, configuración o reportes financieros |
| **Cajero** (`cashier`) | Abrir y cerrar la caja compartida, POS, cobros, ventas, gastos, delivery, clientes y comprobantes autorizados | Administrar usuarios, inventario, configuración general o mantenimiento |
| **Cajero/Mozo** (`cashier_waiter`) | Funciones de mozo y cajero: mesas, pedidos, cobros, ventas, gastos, delivery y clientes | Funciones exclusivas del administrador |

---

## 3. Regla de caja compartida

El sistema usa un solo turno abierto para todo el restaurante.

1. El cajero o administrador abre la caja al iniciar la jornada.
2. Todos los usuarios operativos usan ese mismo turno.
3. No se debe intentar abrir otra caja mientras exista una abierta.
4. Las ventas, gastos y deliveries quedan asociados al mismo turno.
5. Al terminar la jornada, el responsable revisa ventas y gastos y cierra la caja.
6. Después del cierre, se debe abrir un nuevo turno para continuar vendiendo.

> Si no hay caja abierta, el mozo puede ver las mesas asignadas, pero no puede agregar productos ni cobrar.

### Captura 2. Estado de la caja y punto de venta

![Caja compartida y POS](capturas/04-abrir-caja.png)

> Inserta la imagen del formulario de apertura de caja o del estado de caja abierta.

---

## 4. Flujo diario recomendado

1. El administrador verifica usuarios, productos, precios y stock.
2. El cajero abre la caja con el monto inicial.
3. El mozo o cajero/mozo entra al POS.
4. Se selecciona el área y la mesa.
5. Se registra el pedido.
6. Cocina prepara el pedido y actualiza sus estados.
7. Se cobra la cuenta.
8. Se entrega el comprobante o ticket.
9. Se registran gastos si corresponde.
10. Al finalizar el turno, el cajero revisa ventas, gastos y cierra la caja.

---

## 5. Manual para el mozo

### 5.1 Ver mesas

1. Ingresa al sistema.
2. Abre **POS**.
3. Selecciona un área que tengas asignada.
4. Revisa el estado de las mesas.
5. Pulsa una mesa disponible para comenzar un pedido.

Si intentas abrir una mesa de un área no asignada, el sistema mostrará acceso denegado.

### Captura 3. Pantalla principal del POS

![Pantalla principal del POS](capturas/02-pos-mesas.png)

En esta pantalla se observa:

- El menú lateral con **Punto de Venta**, **Reservas**, **Cocina (KDS)** y **Clientes**.
- Las pestañas de las áreas, por ejemplo **Barrio Peruano 1** y **Barrio Peruano 2**.
- Las mesas disponibles con el indicador verde **Libre**.
- La leyenda de estados: disponible, ocupada y reservada.
- El usuario conectado en la esquina superior derecha.

Para comenzar una venta, selecciona el área correcta y pulsa una mesa con estado **Libre**.

### 5.2 Registrar un pedido

1. Selecciona la mesa.
2. Busca una categoría de productos.
3. Pulsa cada producto para agregarlo.
4. Usa el buscador o lector de código si está disponible.
5. Si el producto requiere sabores, selecciona uno o varios, sin superar la cantidad permitida, en la ventana **Elige los sabores**. No repitas sabores. Si el producto no requiere sabores, continúa con el siguiente paso.
6. Revisa cantidades y precios en el carrito.
7. Agrega observaciones, por ejemplo: sin cebolla o término medio.
8. Confirma el pedido.
9. Verifica que el pedido aparezca en cocina.

### Captura 4. Pedido en la mesa

![Pedido en la mesa](capturas/05-pos-pedido.png)

> Muestra el carrito, productos, cantidades y observaciones del pedido.

### 5.3 Modificar un pedido pendiente

1. Abre nuevamente la mesa.
2. Aumenta o reduce la cantidad.
3. Actualiza la nota del producto si es necesario.
4. Elimina un producto solo antes de cerrar la venta.
5. Revisa el total actualizado.

El sistema valida el stock disponible y el stock reservado por otras mesas.

### 5.4 Cambiar una mesa

1. Abre la orden pendiente.
2. Selecciona **Mover mesa**.
3. Elige una mesa libre de un área permitida.
4. Confirma el cambio.

### 5.5 Mostrar la precuenta

El mozo no confirma el pago. Su responsabilidad termina en mostrar la precuenta y entregar el total al cajero.

1. Abre la orden pendiente.
2. Pulsa **Precuenta** o la opción de vista previa de la orden.
3. Revisa productos, cantidades, descuentos y total.
4. Muestra la precuenta al cliente.
5. Solicita al cajero que realice el cobro y emita el comprobante.

### Captura 5. Precuenta

![Precuenta del cliente](capturas/05-pos-pedido.png)

> Si la precuenta se muestra en la misma pantalla del pedido, reemplaza esta imagen por la vista de la cuenta.

### 5.6 Cobro de una mesa

El cobro solo puede realizarlo el **cajero**, **cajero/mozo** o **administrador**.

1. El cajero abre la orden pendiente.
2. Pulsa **Cobrar**.
3. Selecciona el medio de pago: efectivo, tarjeta o transferencia.
4. Para efectivo, escribe el monto recibido.
5. Verifica el vuelto.
6. Selecciona el tipo de comprobante: ticket, boleta o factura.
7. Selecciona o registra los datos del cliente cuando corresponda.
8. Confirma el cobro.
9. Entrega el ticket o comprobante.

Para una factura se necesita un cliente con RUC y razón social válidos, además de una serie activa configurada.

### Captura 6. Cobro

![Pantalla de cobro](capturas/06-cobro.png)

> Esta captura debe mostrar el tipo de comprobante, método de pago y monto final.

### 5.7 Reservas

1. Abre **Reservas**.
2. Pulsa **Nueva reserva**.
3. Selecciona mesa, fecha y hora.
4. Escribe nombre, teléfono y cantidad de personas.
5. Confirma.
6. Actualiza el estado cuando el cliente llegue o cancele.

El sistema rechaza reservas pasadas, superpuestas o que excedan la capacidad de la mesa.

### Captura 7. Reservas

![Reservas](capturas/09-reservas.png)

> Muestra la agenda de reservas y el formulario de nueva reserva.

### 5.8 Cocina y clientes

1. En **Cocina**, revisa los pedidos pendientes y en preparación.
2. Cuando el pedido esté listo, cocina actualiza el estado.
3. En **Clientes**, puedes consultar registros y asociarlos al cobro cuando corresponda.

### Captura 8. Cocina y clientes

![Monitor de cocina y cartera de clientes](capturas/07-cocina.png)

> Puedes usar una captura del monitor de cocina y otra de la cartera de clientes si lo requiere el documento final.

---

## 6. Manual para el cajero

### 6.1 Abrir la caja

1. Ingresa con tu usuario de cajero.
2. Abre **Abrir caja** o la opción de caja.
3. Escribe el monto inicial.
4. Verifica el monto físico.
5. Pulsa **Abrir turno**.
6. Confirma que el sistema redirija al POS.

La caja queda disponible para mozos, cajeros y cajeros/mozos autorizados.

### Captura 9. Apertura de caja

![Apertura de caja](capturas/04-abrir-caja.png)

### 6.2 Registrar un gasto

1. Abre el módulo **Gastos**.
2. Escribe la descripción.
3. Escribe el monto.
4. Confirma el registro.
5. Guarda el comprobante físico del gasto.

Los gastos registrados afectan el monto esperado del cierre.

### 6.3 Revisar ventas

1. Abre **Ventas**.
2. Filtra por fecha o tipo de pago.
3. Revisa total, usuario, cliente y comprobante.
4. Abre una venta para ver su detalle o imprimir el ticket.

### Captura 10. Ventas

![Ventas y reportes del cajero](capturas/11-ventas-reportes.png)

### 6.4 Cerrar la caja

1. Verifica que no haya cobros pendientes.
2. Revisa ventas en efectivo.
3. Revisa gastos registrados.
4. Cuenta el efectivo físico.
5. Abre **Cerrar caja**.
6. Escribe el monto final contado.
7. Agrega observaciones si existe diferencia.
8. Confirma el cierre.
9. Compara monto esperado, monto contado y diferencia.

No cierres la caja si otra persona aún está cobrando.

### Captura 11. Cierre de caja

![Cierre de caja](capturas/16-cerrar-caja.png)

---

## 7. Manual para cajero/mozo

El cajero/mozo combina los dos flujos anteriores:

1. Abre o usa la caja compartida según la organización del local.
2. Entra al POS.
3. Toma pedidos en sus áreas permitidas.
4. Modifica pedidos pendientes.
5. Envía pedidos a cocina.
6. Cobra mesas y deliveries.
7. Registra gastos cuando corresponda.
8. Apoya al cajero en la revisión del turno.

### Captura 12. Flujo del cajero/mozo

![Flujo del cajero/mozo](capturas/05-pos-pedido.png)

> Usa la misma captura del pedido o del cobro para ilustrar la combinación de tareas.

---

## 8. Mensajes frecuentes

| Mensaje o situación | Solución |
|---|---|
| No hay caja abierta | Solicita al cajero o administrador que abra el turno compartido |
| Ya hay una caja abierta | Usa el turno existente; no abras otra caja |
| Producto sin stock | Revisa inventario, reservas de otras mesas o receta |
| Mesa ocupada | Abre la orden pendiente de esa mesa |
| Área no asignada | Solicita al administrador que asigne el área |
| No hay serie activa | El administrador debe configurar la serie por área o general |
| Cliente inválido para factura | Completa RUC y razón social válidos |
| Delivery no puede entregarse | Realiza el cobro desde el detalle del delivery |
| Venta cerrada | No se puede modificar; revisa comprobantes o notas |

---

## 9. Reglas de seguridad para mozo y cajero

- Cada trabajador usa su propio usuario.
- No compartas contraseñas.
- Cierra sesión al terminar.
- No fotografíes contraseñas, tokens, certificados o datos fiscales sensibles.
- Verifica el monto antes de confirmar un cobro.
- Verifica el cliente antes de emitir una factura.
- No cierres la caja mientras existan operaciones pendientes.
- Genera respaldo antes de mantenimiento.
- Reporta errores con fecha, usuario, módulo y captura, sin datos sensibles.

---

## 10. Guía para completar capturas

Guardar las imágenes en la carpeta `docs/capturas/` usando estos nombres. Ocultar correos reales, teléfonos, RUC, tokens, contraseñas y datos de clientes.

| Archivo | Pantalla | Usuario recomendado |
|---|---|---|
| `01-login.png` | Inicio de sesión | Cualquier usuario |
| `02-pos-mesas.png` | Mapa de áreas y mesas | Mozo |
| `04-abrir-caja.png` | Formulario de apertura | Cajero |
| `05-pos-pedido.png` | Productos y carrito | Mozo |
| `06-cobro.png` | Ventana de cobro | Cajero |
| `07-cocina.png` | Monitor de cocina | Cocina |
| `09-reservas.png` | Agenda de reservas | Mozo |
| `10-clientes.png` | Lista o formulario de clientes | Cajero |
| `11-ventas-reportes.png` | Ventas y reportes | Administrador |
| `16-cerrar-caja.png` | Cierre y diferencia | Cajero |

### Cómo tomar una captura útil

1. Abre solo la pantalla que se quiere explicar.
2. Maximiza el navegador.
3. Verifica que se vean menús, botones y datos de ejemplo.
4. Oculta información personal y credenciales.
5. Guarda la imagen con el nombre de la tabla.
6. Inserta la imagen debajo del paso correspondiente.

Ejemplo:

```markdown
![Pantalla POS](capturas/05-pos-pedido.png)
```

---

## 11. Resumen operativo del rol mozo/cajero

El trabajo del mozo y cajero se centra en:

- Atender mesas y pedidos.
- Validar el estado del local y la caja abierta.
- Registrar y modificar comandas.
- Coordinar con cocina.
- Mostrar precuenta.
- Cobrar con el comprobante correspondiente.
- Registrar gastos y revisar ventas.
- Cerrar la caja al final del turno.

Este flujo es el núcleo del funcionamiento del restaurante y debe realizarse con el mismo sistema que está en uso dentro del negocio.
